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

	public static function analyze( $file_path, $file_name, $factory_code = UMS_DB_Inventory::DEFAULT_FACTORY ) {
		$factory_code = UMS_DB_Inventory::normalize_factory_code( $factory_code );
		if ( ! UMS_DB_Uniform_Material::is_ready() ) {
			throw new RuntimeException( 'Database chưa có cấu trúc master Mã SAP.' );
		}

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

		$inventory    = UMS_DB_Inventory::get_all( array( 'factory_code' => $factory_code ) );
		$catalog      = self::build_catalog_index( $inventory );
		$materials    = UMS_DB_Uniform_Material::get_all( array( 'status' => 'active', 'limit' => 10000, 'factory_code' => $factory_code ) );
		$master_index = self::build_material_index( $materials );
		$seen_products = array();
		$projected_stock = array();
		foreach ( $sheet as $row_number => $row ) {
			if ( $row_number < 2 || false === $layout ) {
				continue;
			}
			$product      = sanitize_text_field( isset( $row[ $layout['product'] ] ) ? $row[ $layout['product'] ] : '' );
			$source_product = $product;
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

			$resolved = self::resolve_material_mapping( $master_index, $source_product, $product, $size );
			if ( is_wp_error( $resolved ) ) {
				$errors[] = sprintf( 'Dòng %d: %s', $row_number, $resolved->get_error_message() );
				continue;
			}

			$item_id = absint( $resolved['inventory_item_id'] );
			$item    = isset( $catalog['by_id'][ $item_id ] ) ? $catalog['by_id'][ $item_id ] : null;
			if ( ! $item ) {
				$errors[] = sprintf( 'Dòng %d: ánh xạ Mã SAP của "%s" trỏ tới sản phẩm kho #%d không tồn tại.', $row_number, $source_product, $item_id );
				continue;
			}

			$product = self::product_label( $item );
			$size    = trim( (string) $item['size'] );
			if ( self::normalize( $resolved['product_name'] ) !== self::normalize( $product )
				|| self::normalize_size( $resolved['size'] ) !== self::normalize_size( $size ) ) {
				$errors[] = sprintf(
					'Dòng %d: ánh xạ Mã SAP của "%s" không còn khớp sản phẩm UMS "%s" size "%s". Hãy import lại master Mã SAP.',
					$row_number, $source_product, $product, $size
				);
				continue;
			}
			$before_qty = isset( $projected_stock[ $item_id ] ) ? $projected_stock[ $item_id ] : (int) $item['stock_qty'];
			$after_qty  = $before_qty + (int) $quantity;
			$projected_stock[ $item_id ] = $after_qty;

			$dedupe_key = self::catalog_key( $source_product, $size );
			if ( isset( $seen_products[ $dedupe_key ] ) ) {
				$errors[] = sprintf(
					'Dòng %d: Loại trong file "%s" size "%s" đã được nhập tại dòng %d.',
					$row_number, $source_product, $size, $seen_products[ $dedupe_key ]
				);
				continue;
			}
			$seen_products[ $dedupe_key ] = (int) $row_number;
			$rows[] = array(
				'source_row'  => (int) $row_number,
				'item_id'     => (int) $item['item_id'],
				'material_id' => absint( $resolved['material_id'] ),
				'source_product' => $source_product,
				'product'     => $product,
				'size'        => $size,
				'quantity'    => (int) $quantity,
				'note'        => $note,
				'before_qty'  => $before_qty,
				'after_qty'   => $after_qty,
				'unit_price'  => (float) $item['base_price'],
			);
		}

		if ( empty( $rows ) && empty( $errors ) ) {
			$errors[] = 'File chưa có dòng nào nhập Số lượng lớn hơn 0.';
		}

		return array(
			'file_name' => sanitize_file_name( $file_name ),
			'file_hash' => $factory_code === UMS_DB_Inventory::DEFAULT_FACTORY
				? hash_file( 'sha256', $file_path )
				: hash( 'sha256', 'inventory-in|' . $factory_code . '|' . hash_file( 'sha256', $file_path ) ),
			'factory_code' => $factory_code,
			'factory_name' => UMS_DB_Inventory::get_factory_options()[ $factory_code ],
			'rows'      => $rows,
			'errors'    => array_values( array_unique( $errors ) ),
			'total_quantity' => array_sum( array_column( $rows, 'quantity' ) ),
			'new_rows' => 0,
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
		$factory_code = UMS_DB_Inventory::normalize_factory_code( $preview['factory_code'] ?? '' );
		if ( ! UMS_DB_Inventory::supports_factory_stock() ) {
			return array( 'success' => false, 'errors' => array( 'Chưa cập nhật bảng tồn kho theo nhà máy trong ums.sql.' ) );
		}
		if ( ! UMS_DB_Inventory_Import::is_ready() ) {
			return array( 'success' => false, 'errors' => array( 'Database chưa có cấu trúc import kho trong ums.sql.' ) );
		}
		if ( UMS_DB_Inventory_Import::completed_hash_exists( $preview['file_hash'] ) ) {
			return array( 'success' => false, 'errors' => array( 'File này đã được import thành công trước đó.' ) );
		}
		if ( ! UMS_DB_Uniform_Material::is_ready() ) {
			return array( 'success' => false, 'errors' => array( 'Database chưa có cấu trúc master Mã SAP.' ) );
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
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $preview['rows'] as $row ) {
			$material = UMS_DB_Uniform_Material::get_by_id( $row['material_id'] );
			if ( ! $material || empty( $material['is_active'] ) || absint( $material['inventory_item_id'] ) !== absint( $row['item_id'] ) ) {
				$errors[] = sprintf( 'Dòng %d: ánh xạ master Mã SAP đã thay đổi sau bước xem trước. Hãy tải lại file.', $row['source_row'] );
				break;
			}
			$item = UMS_DB_Inventory::get_by_id_for_update( $row['item_id'], $factory_code );

			if ( ! $item ) {
				$errors[] = sprintf( 'Dòng %d: Sản phẩm không còn tồn tại.', $row['source_row'] );
				break;
			}
			if ( self::catalog_key( self::product_label( $item ), $item['size'] ) !== self::catalog_key( $row['product'], $row['size'] ) ) {
				$errors[] = sprintf( 'Dòng %d: Sản phẩm hoặc size đã thay đổi sau bước xem trước. Hãy tải lại file.', $row['source_row'] );
				break;
			}

			$before = (int) $item['stock_qty'];
			$after  = $before + (int) $row['quantity'];
			$inventory_update = array( 'stock_qty' => $after );
			if ( false === UMS_DB_Inventory::update( $item['item_id'], $inventory_update, $factory_code ) ) {
				$errors[] = sprintf( 'Dòng %d: Không cập nhật được tồn kho.', $row['source_row'] );
				break;
			}

			$note = trim( (string) $row['note'] );
			$movement_note = sprintf(
				'Import nhập kho: %s -> %s, size %s.',
				$row['source_product'],
				$row['product'],
				$row['size']
			);
			if ( $note !== '' ) {
				$movement_note .= ' Ghi chú: ' . $note;
			}
			$movement = UMS_DB_Inventory_Movement::insert(
				array(
					'factory_code' => $factory_code,
					'item_id' => $item['item_id'], 'request_id' => null, 'movement_type' => 'in',
					'quantity' => $row['quantity'], 'before_qty' => $before, 'after_qty' => $after,
					'unit_price' => (float) $item['base_price'],
					'total_price' => (float) $item['base_price'] * (int) $row['quantity'],
					'actor_user_id' => absint( $user_id ), 'target_user_id' => null,
					'target_employee_no' => '', 'note' => $movement_note,
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

	private static function product_label( $item ) {
		$label = trim( (string) $item['item_variant'] );
		return $label !== '' ? $label : trim( (string) $item['item_type'] );
	}

	private static function build_catalog_index( $items ) {
		$catalog = array( 'by_product_size' => array(), 'by_product' => array(), 'by_id' => array() );
		foreach ( $items as $item ) {
			$catalog['by_id'][ absint( $item['item_id'] ) ] = $item;
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

	private static function build_material_index( $materials ) {
		$index = array( 'by_source' => array(), 'by_target' => array() );
		foreach ( $materials as $material ) {
			$source_key = self::normalize( $material['item_name'] );
			$target_key = self::catalog_key( $material['product_name'], self::normalize_size( $material['size'] ) );
			$index['by_source'][ $source_key ][] = $material;
			$index['by_target'][ $target_key ][] = $material;
		}
		return $index;
	}

	private static function resolve_material_mapping( $index, $source_product, $product, $size ) {
		$source_key = self::normalize( $source_product );
		$candidates = isset( $index['by_source'][ $source_key ] ) ? $index['by_source'][ $source_key ] : array();
		if ( ! empty( $candidates ) && trim( (string) $size ) !== '' ) {
			$normalized_size = self::normalize_size( $size );
			$candidates = array_values( array_filter( $candidates, function( $candidate ) use ( $normalized_size ) {
				return self::normalize_size( $candidate['size'] ) === $normalized_size;
			} ) );
		}
		if ( empty( $candidates ) ) {
			$target_key = self::catalog_key( $product, self::normalize_size( $size ) );
			$candidates = isset( $index['by_target'][ $target_key ] ) ? $index['by_target'][ $target_key ] : array();
		}
		if ( empty( $candidates ) ) {
			return new WP_Error( 'material_not_found', sprintf(
				'Loại "%s"%s chưa có ánh xạ trong master Mã SAP.',
				$source_product, $size !== '' ? ' size "' . $size . '"' : ''
			) );
		}

		$by_item = array();
		foreach ( $candidates as $candidate ) {
			$item_id = absint( $candidate['inventory_item_id'] );
			if ( $item_id > 0 ) {
				$by_item[ $item_id ][] = $candidate;
			}
		}
		if ( count( $by_item ) !== 1 ) {
			return new WP_Error( 'material_ambiguous', sprintf(
				'Loại "%s"%s phải quy về đúng một sản phẩm UMS qua master Mã SAP, hiện tìm thấy %d.',
				$source_product, $size !== '' ? ' size "' . $size . '"' : '', count( $by_item )
			) );
		}

		$matches = reset( $by_item );
		return reset( $matches );
	}

	private static function normalize_size( $size ) {
		$size = strtoupper( preg_replace( '/\s+/u', '', trim( (string) $size ) ) );
		$aliases = array( '' => '0', '2XL' => 'XXL', '3XL' => 'XXXL' );
		return isset( $aliases[ $size ] ) ? $aliases[ $size ] : $size;
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
		return UMS_DB_Inventory::normalize_product_identity( $value );
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
