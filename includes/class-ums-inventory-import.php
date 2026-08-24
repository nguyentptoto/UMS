<?php
/**
 * Generate and import the controlled inventory input template.
 */
class UMS_Inventory_Import {
	const SHEET_NAME              = 'Template';
	const TEMPLATE_ROW_COUNT      = 500;
	const PREVIEW_TRANSIENT_PREFIX = 'ums_inventory_import_preview_';
	const PREVIEW_TTL              = 3600;

	public static function stream_template() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'Máy chủ PHP chưa bật ZipArchive.' );
		}
		$temp_file = tempnam( get_temp_dir(), 'ums-inventory-' );
		if ( false === $temp_file ) {
			throw new RuntimeException( 'Không tạo được file tạm trên máy chủ.' );
		}
		$zip       = new ZipArchive();
		if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'Không tạo được file XLSX tạm.' );
		}

		$zip->addFromString( '[Content_Types].xml', self::content_types_xml() );
		$zip->addFromString( '_rels/.rels', self::root_relationships_xml() );
		$zip->addFromString( 'docProps/app.xml', self::app_properties_xml() );
		$zip->addFromString( 'docProps/core.xml', self::core_properties_xml() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook_xml() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_relationships_xml() );
		$zip->addFromString( 'xl/styles.xml', self::styles_xml() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', self::worksheet_xml() );
		$zip->close();

		if ( ! is_file( $temp_file ) ) {
			throw new RuntimeException( 'Không hoàn tất được file XLSX.' );
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="ums-template-nhap-kho-' . gmdate( 'Y-m-d' ) . '.xlsx"' );
		header( 'Content-Length: ' . filesize( $temp_file ) );
		readfile( $temp_file );
		unlink( $temp_file );
		exit;
	}

	public static function analyze( $file_path, $file_name ) {
		$reader = new UMS_XLSX_Reader( $file_path );
		if ( ! $reader->has_sheet( self::SHEET_NAME ) ) {
			throw new RuntimeException( 'Không tìm thấy sheet Template.' );
		}

		$sheet   = $reader->read_sheet( self::SHEET_NAME );
		$errors  = array();
		$rows    = array();
		$headers = isset( $sheet[1] ) ? $sheet[1] : array();
		$layout  = self::detect_layout( $headers );
		if ( false === $layout ) {
			$errors[] = 'Template phải có dạng STT, Loại sản phẩm, Số lượng, Ghi chú; hoặc dạng cũ có thêm cột Size riêng.';
		}

		$catalog      = self::build_catalog_index( UMS_DB_Inventory::get_all() );
		$seen_products = array();
		foreach ( $sheet as $row_number => $row ) {
			if ( $row_number < 2 || false === $layout ) {
				continue;
			}
			$product      = sanitize_text_field( isset( $row[ $layout['product'] ] ) ? $row[ $layout['product'] ] : '' );
			$size         = $layout['size'] !== '' ? sanitize_text_field( isset( $row[ $layout['size'] ] ) ? $row[ $layout['size'] ] : '' ) : '';
			$quantity_raw = trim( (string) ( isset( $row[ $layout['quantity'] ] ) ? $row[ $layout['quantity'] ] : '' ) );
			$note         = sanitize_textarea_field( isset( $row[ $layout['note'] ] ) ? $row[ $layout['note'] ] : '' );
			if ( $product === '' && $size === '' && $quantity_raw === '' && $note === '' ) {
				continue;
			}
			if ( $layout['size'] === '' ) {
				$parsed  = self::parse_product_and_size( $product );
				$product = $parsed['product'];
				$size    = $parsed['size'];
			}

			$quantity  = filter_var( $quantity_raw, FILTER_VALIDATE_INT );

			if ( $product === '' ) {
				$errors[] = sprintf( 'Dòng %d: Chưa nhập Loại sản phẩm.', $row_number );
				continue;
			}
			if ( false === $quantity || $quantity <= 0 || $quantity > 1000000 ) {
				$errors[] = sprintf( 'Dòng %d: Số lượng phải là số nguyên từ 1 đến 1.000.000.', $row_number );
				continue;
			}

			if ( $size !== '' ) {
				$catalog_key = self::catalog_key( $product, $size );
				$matches     = isset( $catalog['by_product_size'][ $catalog_key ] ) ? $catalog['by_product_size'][ $catalog_key ] : array();
			} else {
				$product_key = self::normalize( $product );
				$matches     = isset( $catalog['by_product'][ $product_key ] ) ? $catalog['by_product'][ $product_key ] : array();
				if ( empty( $matches ) ) {
					$size = '0';
				}
			}
			if ( count( $matches ) > 1 ) {
				$errors[] = $size === ''
					? sprintf( 'Dòng %d: Loại sản phẩm "%s" có nhiều size. Hãy thêm hậu tố "Size ..." vào tên.', $row_number, $product )
					: sprintf( 'Dòng %d: Loại sản phẩm "%s" với size "%s" đang bị trùng trong kho UMS.', $row_number, $product, $size );
				continue;
			}

			$is_new = empty( $matches );
			$item    = $is_new ? null : reset( $matches );
			$product_key   = self::normalize( $product );
			$product_items = isset( $catalog['by_product'][ $product_key ] ) ? $catalog['by_product'][ $product_key ] : array();
			$price_result  = self::resolve_product_price( $product_items );
			$current_price = $is_new ? 0.0 : (float) $item['base_price'];
			if ( $price_result['ambiguous'] ) {
				$errors[] = sprintf(
					'Dòng %d: Sản phẩm "%s" đang có nhiều đơn giá theo size, không thể tự xác định giá cho size "%s".',
					$row_number,
					$product,
					$size
				);
				continue;
			}
			$unit_price = $current_price > 0 ? $current_price : $price_result['price'];
			if ( $is_new && ! empty( $product_items ) ) {
				// Dùng tên chuẩn đang có để các size không bị tách thành sản phẩm khác
				// chỉ vì khác cách viết dấu hoặc chữ hoa/thường.
				$product = self::product_label( reset( $product_items ) );
			}
			if ( ! $is_new ) {
				$product = self::product_label( $item );
				$size    = trim( (string) $item['size'] );
			}
			$dedupe_key = self::catalog_key( $product, $size );
			if ( isset( $seen_products[ $dedupe_key ] ) ) {
				$errors[] = sprintf(
					'Dòng %d: Sản phẩm "%s" size "%s" đã được nhập tại dòng %d.',
					$row_number, $product, $size, $seen_products[ $dedupe_key ]
				);
				continue;
			}
			$seen_products[ $dedupe_key ] = (int) $row_number;
			$rows[] = array(
				'source_row'  => (int) $row_number,
				'item_id'     => $is_new ? 0 : (int) $item['item_id'],
				'product'     => $product,
				'size'        => $size,
				'category_name' => self::category_name_from_product( $product ),
				'is_new'      => $is_new ? 1 : 0,
				'quantity'    => (int) $quantity,
				'note'        => $note,
				'before_qty'  => $is_new ? 0 : (int) $item['stock_qty'],
				'after_qty'   => ( $is_new ? 0 : (int) $item['stock_qty'] ) + (int) $quantity,
				'unit_price'  => (float) $unit_price,
				'inherit_price' => $current_price <= 0 && $unit_price > 0 ? 1 : 0,
			);
		}

		if ( empty( $rows ) && empty( $errors ) ) {
			$errors[] = 'File chưa có dòng nào nhập Số lượng lớn hơn 0.';
		}

		return array(
			'file_name' => sanitize_file_name( $file_name ),
			'file_hash' => hash_file( 'sha256', $file_path ),
			'rows'      => $rows,
			'errors'    => array_values( array_unique( $errors ) ),
			'total_quantity' => array_sum( array_column( $rows, 'quantity' ) ),
			'new_rows' => count( array_filter( $rows, function( $row ) { return ! empty( $row['is_new'] ); } ) ),
		);
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::PREVIEW_TRANSIENT_PREFIX . $token, $preview, self::PREVIEW_TTL ) ) {
			throw new RuntimeException( 'Không lưu được dữ liệu xem trước.' );
		}
		return $token;
	}

	public static function get_preview( $token ) {
		return get_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function delete_preview( $token ) {
		delete_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	/**
	 * Chuẩn hóa giá dùng chung trên toàn bộ size của từng sản phẩm.
	 */
	public static function repair_missing_prices() {
		$groups = array();
		foreach ( UMS_DB_Inventory::get_all() as $item ) {
			$key = absint( $item['category_id'] ) . '|' . self::normalize( self::product_label( $item ) );
			$groups[ $key ][] = $item;
		}

		$updated   = 0;
		$ambiguous = 0;
		foreach ( $groups as $items ) {
			$price_result = self::resolve_product_price( $items );
			if ( $price_result['ambiguous'] ) {
				$ambiguous += count( $items );
				continue;
			}
			if ( $price_result['price'] <= 0 ) {
				continue;
			}

			$items_to_update = array_filter(
				$items,
				function ( $item ) use ( $price_result ) {
					return round( (float) $item['base_price'], 2 ) !== round( (float) $price_result['price'], 2 );
				}
			);
			if ( empty( $items_to_update ) ) {
				continue;
			}

			$reference = reset( $items );
			if ( false !== UMS_DB_Inventory::update_product_price( $reference['category_id'], self::product_label( $reference ), $price_result['price'] ) ) {
				$updated += count( $items_to_update );
			}
		}

		return array( 'updated' => $updated, 'ambiguous' => $ambiguous );
	}

	public static function import( $preview, $user_id ) {
		if ( ! UMS_DB_Inventory_Import::is_ready() ) {
			return array( 'success' => false, 'errors' => array( 'Database chưa có cấu trúc import kho trong ums.sql.' ) );
		}
		if ( UMS_DB_Inventory_Import::completed_hash_exists( $preview['file_hash'] ) ) {
			return array( 'success' => false, 'errors' => array( 'File này đã được import thành công trước đó.' ) );
		}

		$batch_id = UMS_DB_Inventory_Import::insert(
			array(
				'file_name' => $preview['file_name'], 'file_hash' => $preview['file_hash'],
				'import_status' => 'processing', 'total_rows' => count( $preview['rows'] ),
				'imported_rows' => 0, 'total_quantity' => 0, 'error_count' => 0,
				'error_log' => '', 'imported_by' => absint( $user_id ),
				'created_at' => current_time( 'mysql' ), 'completed_at' => null,
			)
		);
		if ( $batch_id <= 0 ) {
			return array( 'success' => false, 'errors' => array( 'Không tạo được phiên import: ' . UMS_DB_Inventory_Import::get_last_error() ) );
		}

		global $wpdb;
		$errors   = array();
		$imported = 0;
		$total    = 0;
		$live_catalog = self::build_catalog_index( UMS_DB_Inventory::get_all() );
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $preview['rows'] as $row ) {
			if ( (float) $row['unit_price'] <= 0 ) {
				$product_key  = self::normalize( $row['product'] );
				$price_result = self::resolve_product_price(
					isset( $live_catalog['by_product'][ $product_key ] ) ? $live_catalog['by_product'][ $product_key ] : array()
				);
				if ( $price_result['ambiguous'] ) {
					$errors[] = sprintf( 'Dòng %d: Sản phẩm "%s" có nhiều đơn giá, không thể tự kế thừa.', $row['source_row'], $row['product'] );
					break;
				}
				if ( $price_result['price'] > 0 ) {
					$row['unit_price']   = $price_result['price'];
					$row['inherit_price'] = 1;
				}
			}
			$is_new      = ! empty( $row['is_new'] );
			$created_now = false;
			$item        = $is_new ? null : UMS_DB_Inventory::get_by_id_for_update( $row['item_id'] );
			if ( $is_new ) {
				$matches = UMS_DB_Inventory::get_by_name_and_size( $row['product'], $row['size'] );
				if ( count( $matches ) > 1 ) {
					$errors[] = sprintf( 'Dòng %d: Sản phẩm vừa được tạo trùng trong kho. Hãy kiểm tra lại.', $row['source_row'] );
					break;
				}
				if ( count( $matches ) === 1 ) {
					$item = UMS_DB_Inventory::get_by_id_for_update( $matches[0]['item_id'] );
				} else {
					$category_id = self::ensure_parent_category( $row['category_name'] );
					if ( $category_id <= 0 ) {
						$errors[] = sprintf( 'Dòng %d: Không tạo được danh mục cha "%s".', $row['source_row'], $row['category_name'] );
						break;
					}
					$inserted = UMS_DB_Inventory::insert(
						array(
							'category_id' => $category_id, 'item_type' => $row['category_name'],
							'item_variant' => $row['product'], 'size' => $row['size'], 'color_code' => '',
							'stock_qty' => (int) $row['quantity'], 'base_price' => (float) $row['unit_price'],
						)
					);
					if ( false === $inserted ) {
						$errors[] = sprintf( 'Dòng %d: Không tạo được sản phẩm mới: %s', $row['source_row'], UMS_DB_Inventory::get_last_error() );
						break;
					}
					$item = array(
						'item_id' => UMS_DB_Inventory::get_last_insert_id(), 'item_variant' => $row['product'],
						'item_type' => $row['category_name'], 'size' => $row['size'],
						'stock_qty' => (int) $row['quantity'], 'base_price' => (float) $row['unit_price'],
					);
					$created_now = true;
				}
			}

			if ( ! $item ) {
				$errors[] = sprintf( 'Dòng %d: Sản phẩm không còn tồn tại.', $row['source_row'] );
				break;
			}
			if ( self::catalog_key( self::product_label( $item ), $item['size'] ) !== self::catalog_key( $row['product'], $row['size'] ) ) {
				$errors[] = sprintf( 'Dòng %d: Sản phẩm hoặc size đã thay đổi sau bước xem trước. Hãy tải lại file.', $row['source_row'] );
				break;
			}

			$before = $created_now ? 0 : (int) $item['stock_qty'];
			$after  = $created_now ? (int) $row['quantity'] : $before + (int) $row['quantity'];
			$inventory_update = array( 'stock_qty' => $after );
			if ( ! empty( $row['inherit_price'] ) && (float) $item['base_price'] <= 0 && (float) $row['unit_price'] > 0 ) {
				$inventory_update['base_price'] = (float) $row['unit_price'];
				$item['base_price']             = (float) $row['unit_price'];
			}
			if ( ! $created_now && false === UMS_DB_Inventory::update( $item['item_id'], $inventory_update ) ) {
				$errors[] = sprintf( 'Dòng %d: Không cập nhật được tồn kho.', $row['source_row'] );
				break;
			}

			$note = trim( (string) $row['note'] );
			$movement = UMS_DB_Inventory_Movement::insert(
				array(
					'item_id' => $item['item_id'], 'request_id' => null, 'movement_type' => 'in',
					'quantity' => $row['quantity'], 'before_qty' => $before, 'after_qty' => $after,
					'unit_price' => (float) $item['base_price'],
					'total_price' => (float) $item['base_price'] * (int) $row['quantity'],
					'actor_user_id' => absint( $user_id ), 'target_user_id' => null,
					'target_employee_no' => '', 'note' => $note !== '' ? $note : 'Import nhập kho từ Excel.',
					'import_batch_id' => $batch_id, 'source_row' => $row['source_row'],
				)
			);
			if ( ! $movement ) {
				$errors[] = sprintf( 'Dòng %d: Không ghi được lịch sử kho.', $row['source_row'] );
				break;
			}
			$imported++;
			$total += (int) $row['quantity'];
		}

		if ( empty( $errors ) ) {
			$wpdb->query( 'COMMIT' );
		} else {
			$wpdb->query( 'ROLLBACK' );
			$imported = 0;
			$total    = 0;
		}

		UMS_DB_Inventory_Import::update(
			$batch_id,
			array(
				'import_status' => empty( $errors ) ? 'completed' : 'failed',
				'imported_rows' => $imported, 'total_quantity' => $total,
				'error_count' => count( $errors ),
				'error_log' => wp_json_encode( $errors, JSON_UNESCAPED_UNICODE ),
				'completed_at' => current_time( 'mysql' ),
			)
		);

		return array( 'success' => empty( $errors ), 'batch_id' => $batch_id, 'imported' => $imported, 'total' => $total, 'errors' => $errors );
	}

	private static function ensure_parent_category( $category_name ) {
		$category = UMS_DB_Product_Category::get_parent_by_name( $category_name );
		if ( $category ) {
			if ( (int) $category['is_active'] !== 1
				&& false === UMS_DB_Product_Category::update( $category['category_id'], array( 'is_active' => 1 ) ) ) {
				return 0;
			}

			return (int) $category['category_id'];
		}

		$inserted = UMS_DB_Product_Category::insert(
			array( 'parent_id' => 0, 'category_name' => $category_name, 'is_active' => 1 )
		);

		return false === $inserted ? 0 : UMS_DB_Product_Category::get_last_insert_id();
	}

	private static function category_name_from_product( $product ) {
		$parts = preg_split( '/\s+/u', trim( (string) $product ) );
		$name  = isset( $parts[0] ) ? preg_replace( '/[^\p{L}\p{N}_-]/u', '', $parts[0] ) : '';
		if ( $name === '' ) {
			return 'Khác';
		}

		return function_exists( 'mb_convert_case' ) ? mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' ) : ucfirst( strtolower( $name ) );
	}

	private static function product_label( $item ) {
		$label = trim( (string) $item['item_variant'] );
		return $label !== '' ? $label : trim( (string) $item['item_type'] );
	}

	private static function build_catalog_index( $items ) {
		$catalog = array( 'by_product_size' => array(), 'by_product' => array() );
		foreach ( $items as $item ) {
			$product = self::product_label( $item );
			$key     = self::catalog_key( $product, $item['size'] );
			if ( ! isset( $catalog['by_product_size'][ $key ] ) ) {
				$catalog['by_product_size'][ $key ] = array();
			}
			$catalog['by_product_size'][ $key ][] = $item;

			$product_key = self::normalize( $product );
			if ( ! isset( $catalog['by_product'][ $product_key ] ) ) {
				$catalog['by_product'][ $product_key ] = array();
			}
			$catalog['by_product'][ $product_key ][] = $item;
		}

		return $catalog;
	}

	private static function resolve_product_price( $items ) {
		$prices = array();
		foreach ( $items as $item ) {
			$price = isset( $item['base_price'] ) ? round( (float) $item['base_price'], 2 ) : 0;
			if ( $price > 0 ) {
				$prices[ number_format( $price, 2, '.', '' ) ] = $price;
			}
		}

		return array(
			'price'     => count( $prices ) === 1 ? (float) reset( $prices ) : 0.0,
			'ambiguous' => count( $prices ) > 1,
		);
	}

	private static function catalog_key( $product, $size ) {
		return self::normalize( $product ) . "\x1F" . self::normalize( $size );
	}

	private static function detect_layout( $headers ) {
		$header = function( $column ) use ( $headers ) {
			return self::normalize( isset( $headers[ $column ] ) ? $headers[ $column ] : '' );
		};
		$product_headers = array( self::normalize( 'Loại sản phẩm' ), self::normalize( 'Loại' ) );
		if ( $header( 'A' ) !== self::normalize( 'STT' ) || ! in_array( $header( 'B' ), $product_headers, true ) ) {
			return false;
		}
		if ( $header( 'C' ) === self::normalize( 'Số lượng' ) && $header( 'D' ) === self::normalize( 'Ghi chú' ) ) {
			return array( 'product' => 'B', 'size' => '', 'quantity' => 'C', 'note' => 'D' );
		}
		if ( $header( 'C' ) === self::normalize( 'Size' ) && $header( 'D' ) === self::normalize( 'Số lượng' ) && $header( 'E' ) === self::normalize( 'Ghi chú' ) ) {
			return array( 'product' => 'B', 'size' => 'C', 'quantity' => 'D', 'note' => 'E' );
		}

		return false;
	}

	private static function parse_product_and_size( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		if ( preg_match( '/^(.*?)\s+size\s*[:\-]?\s*([^\s]+)\s*$/iu', $value, $matches ) ) {
			return array( 'product' => trim( $matches[1] ), 'size' => trim( $matches[2] ) );
		}

		return array( 'product' => $value, 'size' => '' );
	}

	private static function normalize( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		// Đồng nhất với collation utf8mb4_unicode_ci của MySQL để bước xem trước
		// và bước xác nhận không hiểu khác nhau các tên như "Giày" / "Giầy".
		$value = remove_accents( $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function worksheet_xml() {
		$rows = array();
		$headers = array( 'STT', 'Loại sản phẩm', 'Số lượng', 'Ghi chú' );
		$cells = array();
		foreach ( $headers as $index => $header ) {
			$cells[] = self::string_cell( chr( 65 + $index ) . '1', $header, 1 );
		}
		$rows[] = '<row r="1" ht="24" customHeight="1">' . implode( '', $cells ) . '</row>';

		for ( $index = 0; $index < self::TEMPLATE_ROW_COUNT; $index++ ) {
			$row_number = $index + 2;
			$rows[] = '<row r="' . $row_number . '">'
				. self::number_cell( 'A' . $row_number, $index + 1, 0 )
				. '<c r="B' . $row_number . '" s="3" t="inlineStr"><is><t></t></is></c>'
				. '<c r="C' . $row_number . '" s="2"/>'
				. '<c r="D' . $row_number . '" s="3" t="inlineStr"><is><t></t></is></c>'
				. '</row>';
		}

		$last_row = self::TEMPLATE_ROW_COUNT + 1;
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="52" customWidth="1"/><col min="3" max="3" width="16" customWidth="1"/><col min="4" max="4" width="42" customWidth="1"/></cols>'
			. '<sheetData>' . implode( '', $rows ) . '</sheetData>'
			. '<autoFilter ref="A1:D' . $last_row . '"/>'
			. '<dataValidations count="1"><dataValidation type="whole" operator="between" allowBlank="1" showErrorMessage="1" errorTitle="Số lượng không hợp lệ" error="Chỉ nhập số nguyên từ 1 đến 1000000." sqref="C2:C' . $last_row . '"><formula1>1</formula1><formula2>1000000</formula2></dataValidation></dataValidations>'
			. '</worksheet>';
	}

	private static function string_cell( $reference, $value, $style ) {
		return '<c r="' . $reference . '" s="' . absint( $style ) . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml( $value ) . '</t></is></c>';
	}

	private static function number_cell( $reference, $value, $style ) {
		return '<c r="' . $reference . '" s="' . absint( $style ) . '"><v>' . (int) $value . '</v></c>';
	}

	private static function xml( $value ) {
		$value = wp_check_invalid_utf8( (string) $value, true );
		$value = preg_replace( '/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value );

		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private static function content_types_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
	}

	private static function root_relationships_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
	}

	private static function workbook_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Template" sheetId="1" r:id="rId1"/></sheets></workbook>';
	}

	private static function workbook_relationships_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
	}

	private static function styles_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E1F2"/></left><right style="thin"><color rgb="FFD9E1F2"/></right><top style="thin"><color rgb="FFD9E1F2"/></top><bottom style="thin"><color rgb="FFD9E1F2"/></bottom><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0"><protection locked="1"/></xf><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/><protection locked="1"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1"><protection locked="0"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1"><protection locked="0"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}

	private static function core_properties_xml() {
		$now = gmdate( 'Y-m-d\TH:i:s\Z' );
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>UMS</dc:creator><dc:title>Template nhập kho UMS</dc:title><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created></cp:coreProperties>';
	}

	private static function app_properties_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>UMS</Application></Properties>';
	}
}
