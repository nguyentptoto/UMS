<?php
/**
 * Trình đọc XLSX tối giản dành riêng cho template định mức UMS.
 */
class UMS_XLSX_Reader {
	private $zip;
	private $shared_strings = array();
	private $sheets = array();

	public function __construct( $file_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'Máy chủ PHP chưa bật ZipArchive.' );
		}

		$this->zip = new ZipArchive();
		if ( true !== $this->zip->open( $file_path ) ) {
			throw new RuntimeException( 'Không mở được file XLSX.' );
		}

		$this->load_shared_strings();
		$this->load_sheet_map();
	}

	public function __destruct() {
		if ( $this->zip instanceof ZipArchive ) {
			$this->zip->close();
		}
	}

	public function has_sheet( $sheet_name ) {
		return isset( $this->sheets[ $sheet_name ] );
	}

	public function read_sheet( $sheet_name ) {
		if ( ! $this->has_sheet( $sheet_name ) ) {
			throw new RuntimeException( 'Không tìm thấy sheet "' . $sheet_name . '".' );
		}

		$xml = $this->load_xml( $this->sheets[ $sheet_name ] );
		$xml->registerXPathNamespace( 'x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
		$rows = array();

		foreach ( $xml->xpath( '//x:sheetData/x:row' ) as $row ) {
			$row_number = (int) $row->attributes()['r'];
			$values     = array();

			foreach ( $row->children( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' )->c as $cell ) {
				$reference = (string) $cell->attributes()['r'];
				if ( ! preg_match( '/^([A-Z]+)/', $reference, $matches ) ) {
					continue;
				}

				$values[ $matches[1] ] = $this->read_cell_value( $cell );
			}

			$rows[ $row_number ] = $values;
		}

		return $rows;
	}

	private function load_shared_strings() {
		$content = $this->zip->getFromName( 'xl/sharedStrings.xml' );
		if ( false === $content ) {
			return;
		}

		$xml = $this->parse_xml( $content );
		$xml->registerXPathNamespace( 'x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );

		foreach ( $xml->xpath( '//x:si' ) as $item ) {
			$this->shared_strings[] = trim( dom_import_simplexml( $item )->textContent );
		}
	}

	private function load_sheet_map() {
		$workbook = $this->load_xml( 'xl/workbook.xml' );
		$relations = $this->load_xml( 'xl/_rels/workbook.xml.rels' );
		$relation_map = array();

		foreach ( $relations->Relationship as $relation ) {
			$attributes = $relation->attributes();
			$relation_map[ (string) $attributes['Id'] ] = (string) $attributes['Target'];
		}

		$workbook->registerXPathNamespace( 'x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );
		foreach ( $workbook->xpath( '//x:sheets/x:sheet' ) as $sheet ) {
			$attributes          = $sheet->attributes();
			$state               = isset( $attributes['state'] ) ? strtolower( (string) $attributes['state'] ) : '';
			if ( $state !== '' && $state !== 'visible' ) {
				continue;
			}
			$relation_attributes = $sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
			$target              = isset( $relation_map[ (string) $relation_attributes['id'] ] ) ? $relation_map[ (string) $relation_attributes['id'] ] : '';

			if ( strpos( $target, '/' ) === 0 ) {
				$entry = ltrim( $target, '/' );
			} elseif ( strpos( $target, 'xl/' ) === 0 ) {
				$entry = $target;
			} else {
				$entry = 'xl/' . $target;
			}

			$this->sheets[ (string) $attributes['name'] ] = $entry;
		}
	}

	private function read_cell_value( $cell ) {
		$type     = (string) $cell->attributes()['t'];
		$children = $cell->children( 'http://schemas.openxmlformats.org/spreadsheetml/2006/main' );

		if ( $type === 'inlineStr' && isset( $children->is ) ) {
			return trim( dom_import_simplexml( $children->is )->textContent );
		}

		$value = isset( $children->v ) ? (string) $children->v : '';
		if ( $type === 's' && $value !== '' ) {
			return isset( $this->shared_strings[ (int) $value ] ) ? $this->shared_strings[ (int) $value ] : '';
		}

		return $value;
	}

	private function load_xml( $entry ) {
		$content = $this->zip->getFromName( $entry );
		if ( false === $content ) {
			throw new RuntimeException( 'File XLSX thiếu thành phần: ' . $entry );
		}

		return $this->parse_xml( $content );
	}

	private function parse_xml( $content ) {
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $content );
		if ( false === $xml ) {
			throw new RuntimeException( 'Không đọc được cấu trúc XML bên trong XLSX.' );
		}
		return $xml;
	}
}
