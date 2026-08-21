<?php
/**
 * Xuất kết quả tính PR vào đúng workbook mẫu của UMS.
 */
class UMS_PR_Export {
	const TEMPLATE_PATH = 'assets/templates/ums-pr-template.xlsx';
	const SHEET_ENTRY   = 'xl/worksheets/sheet1.xml';
	const XML_NS        = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

	public static function stream( $calculation, $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'delivery_date'       => '',
				'requesting_section'  => '',
				'using_cost_center'   => '',
			)
		);
		$rows = array_values(
			array_filter(
				$calculation['rows'],
				function ( $row ) {
					return (int) $row['final_pr_qty'] > 0;
				}
			)
		);

		if ( empty( $rows ) ) {
			throw new RuntimeException( 'Không có sản phẩm nào cần lên PR.' );
		}
		foreach ( $rows as $row ) {
			if ( (float) $row['base_price'] <= 0 ) {
				throw new RuntimeException( 'Sản phẩm "' . $row['item_name'] . '" chưa có đơn giá.' );
			}
		}

		$template = UMS_PLUGIN_DIR . self::TEMPLATE_PATH;
		if ( ! is_readable( $template ) ) {
			throw new RuntimeException( 'Không tìm thấy file mẫu xuất PR trong plugin.' );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'Máy chủ PHP chưa bật ZipArchive.' );
		}

		$temp_file = wp_tempnam( 'ums-pr-' );
		if ( ! $temp_file || ! copy( $template, $temp_file ) ) {
			throw new RuntimeException( 'Không tạo được file PR tạm.' );
		}

		try {
			self::write_rows( $temp_file, $rows, $options );
			$filename = sprintf(
				'UMS-PR-T%02d-%d-%s.xlsx',
				absint( $calculation['period_month'] ),
				absint( $calculation['year'] ),
				gmdate( 'Ymd-His' )
			);

			nocache_headers();
			header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . filesize( $temp_file ) );
			readfile( $temp_file );
		} finally {
			if ( file_exists( $temp_file ) ) {
				unlink( $temp_file );
			}
		}
		exit;
	}

	private static function write_rows( $file_path, $rows, $options ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			throw new RuntimeException( 'Không mở được workbook PR tạm.' );
		}

		$xml_source = $zip->getFromName( self::SHEET_ENTRY );
		if ( false === $xml_source ) {
			$zip->close();
			throw new RuntimeException( 'Workbook mẫu thiếu sheet PR Total.' );
		}

		$document = new DOMDocument( '1.0', 'UTF-8' );
		$document->preserveWhiteSpace = false;
		$document->formatOutput       = false;
		if ( ! $document->loadXML( $xml_source ) ) {
			$zip->close();
			throw new RuntimeException( 'Không đọc được cấu trúc sheet PR Total.' );
		}

		$xpath = new DOMXPath( $document );
		$xpath->registerNamespace( 'x', self::XML_NS );
		$template_row = $xpath->query( '//x:sheetData/x:row[@r="3"]' )->item( 0 );
		$sheet_data   = $xpath->query( '//x:sheetData' )->item( 0 );
		if ( ! $template_row || ! $sheet_data ) {
			$zip->close();
			throw new RuntimeException( 'Workbook mẫu không có dòng dữ liệu định dạng tại dòng 3.' );
		}

		foreach ( iterator_to_array( $xpath->query( '//x:sheetData/x:row[number(@r) >= 3]' ) ) as $old_row ) {
			$sheet_data->removeChild( $old_row );
		}

		$delivery_date = preg_replace( '/[^0-9]/', '', str_replace( '-', '', $options['delivery_date'] ) );
		foreach ( $rows as $index => $source ) {
			$row_number = $index + 3;
			$row_node   = $template_row->cloneNode( true );
			$row_node->setAttribute( 'r', (string) $row_number );
			$values = array(
				'A' => 'A', 'B' => 'Y206', 'C' => $index === 0 ? 'GL: 6356018' : '', 'D' => 'K', 'E' => '',
				'F' => $source['sap_code'], 'G' => $source['item_name'], 'H' => (int) $source['final_pr_qty'],
				'I' => $delivery_date, 'J' => '', 'K' => '', 'L' => $options['requesting_section'],
				'M' => $options['using_cost_center'], 'N' => '', 'O' => $options['using_cost_center'], 'P' => '',
				'Q' => (float) $source['base_price'], 'R' => 'VND', 'S' => '',
			);

			foreach ( $values as $column => $value ) {
				self::set_cell( $document, $xpath, $row_node, $column, $row_number, $value, in_array( $column, array( 'H', 'Q' ), true ) );
			}
			$sheet_data->appendChild( $row_node );
		}

		$dimension = $xpath->query( '//x:dimension' )->item( 0 );
		if ( $dimension ) {
			$dimension->setAttribute( 'ref', 'A1:S' . ( count( $rows ) + 2 ) );
		}

		$updated_xml = $document->saveXML();
		$rebuilt_path = $file_path . '.rebuilt-' . wp_generate_password( 8, false, false );
		$rebuilt      = new ZipArchive();
		if ( true !== $rebuilt->open( $rebuilt_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			$zip->close();
			throw new RuntimeException( 'Không tạo được archive PR mới.' );
		}

		for ( $index = 0; $index < $zip->numFiles; $index++ ) {
			$name    = $zip->getNameIndex( $index );
			$content = $name === self::SHEET_ENTRY ? $updated_xml : $zip->getFromIndex( $index );
			if ( false === $content || ! $rebuilt->addFromString( $name, $content ) ) {
				$rebuilt->close();
				$zip->close();
				@unlink( $rebuilt_path );
				throw new RuntimeException( 'Không sao chép được thành phần workbook: ' . $name );
			}
		}

		$zip->close();
		if ( ! $rebuilt->close() ) {
			@unlink( $rebuilt_path );
			throw new RuntimeException( 'Không hoàn tất được workbook PR.' );
		}
		if ( ! unlink( $file_path ) || ! rename( $rebuilt_path, $file_path ) ) {
			@unlink( $rebuilt_path );
			throw new RuntimeException( 'Không thay được workbook PR sau khi ghi dữ liệu.' );
		}
	}

	private static function set_cell( $document, $xpath, $row, $column, $row_number, $value, $numeric ) {
		$cell = $xpath->query( './/x:c[starts-with(@r, "' . $column . '")]', $row )->item( 0 );
		if ( ! $cell ) {
			$cell = $document->createElementNS( self::XML_NS, 'c' );
			$row->appendChild( $cell );
		}
		$cell->setAttribute( 'r', $column . $row_number );
		while ( $cell->firstChild ) {
			$cell->removeChild( $cell->firstChild );
		}

		if ( $numeric ) {
			$cell->removeAttribute( 't' );
			$cell->appendChild( $document->createElementNS( self::XML_NS, 'v', (string) $value ) );
			return;
		}

		$cell->setAttribute( 't', 'inlineStr' );
		$inline = $document->createElementNS( self::XML_NS, 'is' );
		$text   = $document->createElementNS( self::XML_NS, 't' );
		$text->appendChild( $document->createTextNode( (string) $value ) );
		$inline->appendChild( $text );
		$cell->appendChild( $inline );
	}
}
