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

		$sheet  = $reader->read_sheet( self::SHEET_NAME );
		$errors = array();
		$rows   = array();
		$headers = isset( $sheet[1] ) ? $sheet[1] : array();
		$expected_headers = array(
			'A' => 'STT', 'B' => 'Loại sản phẩm', 'C' => 'Size', 'D' => 'Số lượng', 'E' => 'Ghi chú',
		);

		foreach ( $expected_headers as $column => $header ) {
			if ( self::normalize( isset( $headers[ $column ] ) ? $headers[ $column ] : '' ) !== self::normalize( $header ) ) {
				$errors[] = sprintf( 'Cột %s không đúng cấu trúc template UMS.', $column );
			}
		}

		$catalog = self::build_catalog_index( UMS_DB_Inventory::get_all() );
		foreach ( $sheet as $row_number => $row ) {
			if ( $row_number < 2 ) {
				continue;
			}
			$product      = sanitize_text_field( isset( $row['B'] ) ? $row['B'] : '' );
			$size         = sanitize_text_field( isset( $row['C'] ) ? $row['C'] : '' );
			$quantity_raw = trim( (string) ( isset( $row['D'] ) ? $row['D'] : '' ) );
			$note         = sanitize_textarea_field( isset( $row['E'] ) ? $row['E'] : '' );
			if ( $product === '' && $size === '' && $quantity_raw === '' && $note === '' ) {
				continue;
			}

			$quantity  = filter_var( $quantity_raw, FILTER_VALIDATE_INT );

			if ( $product === '' ) {
				$errors[] = sprintf( 'Dòng %d: Chưa nhập Loại sản phẩm.', $row_number );
				continue;
			}
			if ( $size === '' ) {
				$errors[] = sprintf( 'Dòng %d: Chưa nhập Size.', $row_number );
				continue;
			}
			if ( false === $quantity || $quantity <= 0 || $quantity > 1000000 ) {
				$errors[] = sprintf( 'Dòng %d: Số lượng phải là số nguyên từ 1 đến 1.000.000.', $row_number );
				continue;
			}

			$catalog_key = self::catalog_key( $product, $size );
			$matches     = isset( $catalog[ $catalog_key ] ) ? $catalog[ $catalog_key ] : array();
			if ( empty( $matches ) ) {
				$errors[] = sprintf( 'Dòng %d: Loại sản phẩm "%s" với size "%s" không tồn tại trong hệ thống.', $row_number, $product, $size );
				continue;
			}
			if ( count( $matches ) > 1 ) {
				$errors[] = sprintf( 'Dòng %d: Loại sản phẩm "%s" với size "%s" đang bị trùng trong kho UMS.', $row_number, $product, $size );
				continue;
			}

			$item = reset( $matches );
			$rows[] = array(
				'source_row'  => (int) $row_number,
				'item_id'     => (int) $item['item_id'],
				'product'     => self::product_label( $item ),
				'size'        => trim( (string) $item['size'] ),
				'quantity'    => (int) $quantity,
				'note'        => $note,
				'before_qty'  => (int) $item['stock_qty'],
				'after_qty'   => (int) $item['stock_qty'] + (int) $quantity,
				'unit_price'  => (float) $item['base_price'],
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
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $preview['rows'] as $row ) {
			$item = UMS_DB_Inventory::get_by_id_for_update( $row['item_id'] );
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
			if ( false === UMS_DB_Inventory::update( $row['item_id'], array( 'stock_qty' => $after ) ) ) {
				$errors[] = sprintf( 'Dòng %d: Không cập nhật được tồn kho.', $row['source_row'] );
				break;
			}

			$note = trim( (string) $row['note'] );
			$movement = UMS_DB_Inventory_Movement::insert(
				array(
					'item_id' => $row['item_id'], 'request_id' => null, 'movement_type' => 'in',
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

	private static function product_label( $item ) {
		$label = trim( (string) $item['item_variant'] );
		return $label !== '' ? $label : trim( (string) $item['item_type'] );
	}

	private static function build_catalog_index( $items ) {
		$catalog = array();
		foreach ( $items as $item ) {
			$key = self::catalog_key( self::product_label( $item ), $item['size'] );
			if ( ! isset( $catalog[ $key ] ) ) {
				$catalog[ $key ] = array();
			}
			$catalog[ $key ][] = $item;
		}

		return $catalog;
	}

	private static function catalog_key( $product, $size ) {
		return self::normalize( $product ) . "\x1F" . self::normalize( $size );
	}

	private static function normalize( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function worksheet_xml() {
		$rows = array();
		$headers = array( 'STT', 'Loại sản phẩm', 'Size', 'Số lượng', 'Ghi chú' );
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
				. '<c r="C' . $row_number . '" s="3" t="inlineStr"><is><t></t></is></c>'
				. '<c r="D' . $row_number . '" s="2"/>'
				. '<c r="E' . $row_number . '" s="3" t="inlineStr"><is><t></t></is></c>'
				. '</row>';
		}

		$last_row = self::TEMPLATE_ROW_COUNT + 1;
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="42" customWidth="1"/><col min="3" max="3" width="14" customWidth="1"/><col min="4" max="4" width="16" customWidth="1"/><col min="5" max="5" width="42" customWidth="1"/></cols>'
			. '<sheetData>' . implode( '', $rows ) . '</sheetData>'
			. '<autoFilter ref="A1:E' . $last_row . '"/>'
			. '<dataValidations count="1"><dataValidation type="whole" operator="between" allowBlank="1" showErrorMessage="1" errorTitle="Số lượng không hợp lệ" error="Chỉ nhập số nguyên từ 1 đến 1000000." sqref="D2:D' . $last_row . '"><formula1>1</formula1><formula2>1000000</formula2></dataValidation></dataValidations>'
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
