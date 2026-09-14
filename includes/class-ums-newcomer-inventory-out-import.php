<?php
/**
 * Imports first-day inventory issues for newcomers from the GA workbook.
 */
class UMS_Newcomer_Inventory_Out_Import {
	const SHEET_NAME     = 'Template_NewCommer';
	const PREVIEW_PREFIX = 'ums_newcomer_out_preview_';
	const PREVIEW_TTL    = 2 * HOUR_IN_SECONDS;

	public static function analyze( $file_path, $file_name ) {
		$reader = new UMS_XLSX_Reader( $file_path );
		if ( ! $reader->has_sheet( self::SHEET_NAME ) ) {
			throw new RuntimeException( 'Không tìm thấy sheet "' . self::SHEET_NAME . '".' );
		}
		$sheet = $reader->read_sheet( self::SHEET_NAME );
		self::validate_headers( $sheet[1] ?? array() );

		$errors       = array();
		$warnings     = array();
		$entries      = array();
		$employee_rows = array();
		foreach ( $sheet as $row_number => $row ) {
			if ( $row_number < 2 || self::row_is_empty( $row ) ) {
				continue;
			}
			$employee_no = strtoupper( preg_replace( '/\s+/u', '', trim( (string) ( $row['B'] ?? '' ) ) ) );
			if ( ! preg_match( '/^[A-Z][0-9]{6}$/', $employee_no ) ) {
				$errors[] = sprintf( 'Dòng %d: Mã nhân viên "%s" không đúng định dạng.', $row_number, $employee_no );
				continue;
			}
			$employee_rows[ $employee_no ][] = $row_number;
			$raw_date_joined = trim( (string) ( $row['E'] ?? '' ) );
			$date_joined     = self::parse_date( $raw_date_joined );
			if ( $raw_date_joined !== '' && $date_joined === '' ) {
				$warnings[] = sprintf(
					'Dòng %d, CNV %s: ngày vào "%s" không đọc được; hệ thống vẫn tiếp tục import và để trống ngày vào trong lịch sử.',
					$row_number,
					$employee_no,
					$raw_date_joined
				);
			}
			$entries[] = array(
				'source_row' => (int) $row_number,
				'employee_no' => $employee_no,
				'full_name' => trim( sanitize_text_field( (string) ( $row['C'] ?? '' ) ) ),
				'department' => trim( sanitize_text_field( (string) ( $row['D'] ?? '' ) ) ),
				'date_joined' => $date_joined,
				'position' => trim( sanitize_text_field( (string) ( $row['F'] ?? '' ) ) ),
				'requests' => self::parse_requests( $row_number, $row, $errors ),
			);
		}

		foreach ( $employee_rows as $employee_no => $row_numbers ) {
			if ( count( $row_numbers ) > 1 ) {
				$errors[] = sprintf( 'CNV %s bị trùng tại các dòng %s.', $employee_no, implode( ', ', $row_numbers ) );
			}
		}

		$employee_nos = array_keys( $employee_rows );
		$organization = UMS_DB_Organization::get_by_employee_nos( $employee_nos );
		$inventory = UMS_DB_Inventory::get_all();
		$inventory_by_id = array();
		foreach ( $inventory as $item ) {
			$inventory_by_id[ absint( $item['item_id'] ) ] = $item;
		}
		$materials = UMS_DB_Uniform_Material::get_all( array( 'status' => 'active', 'limit' => 10000 ) );

		$details        = array();
		$projected_stock = array();
		foreach ( $entries as $entry ) {
			$employee_no = $entry['employee_no'];
			if ( count( $employee_rows[ $employee_no ] ?? array() ) !== 1 ) {
				continue;
			}
			$employee = $organization[ $employee_no ] ?? null;
			if ( $employee ) {
				self::compare_employee_metadata( $entry, $employee, $warnings );
			} else {
				$warnings[] = sprintf(
					'Dòng %d: CNV %s chưa có trong Sơ đồ tổ chức; lịch sử vẫn được lưu theo mã trong file.',
					$entry['source_row'], $employee_no
				);
			}
			foreach ( $entry['requests'] as $request ) {
				$item = self::resolve_requested_item( $request, $materials, $inventory_by_id );
				if ( is_wp_error( $item ) ) {
					$errors[] = sprintf( 'Dòng %d, CNV %s: %s', $entry['source_row'], $employee_no, $item->get_error_message() );
					continue;
				}
				$item_id = absint( $item['item_id'] );
				$before  = isset( $projected_stock[ $item_id ] ) ? $projected_stock[ $item_id ] : (int) $item['stock_qty'];
				$after   = $before - $request['quantity'];
				if ( $after < 0 ) {
					$errors[] = sprintf(
						'Dòng %d, CNV %s: "%s" size "%s" cần %d nhưng tồn khả dụng chỉ còn %d.',
						$entry['source_row'], $employee_no, $item['item_variant'], $item['size'], $request['quantity'], max( 0, $before )
					);
					continue;
				}

				$projected_stock[ $item_id ] = $after;
				$details[] = array(
					'source_row' => $entry['source_row'], 'employee_no' => $employee_no,
					'full_name' => $entry['full_name'] !== '' ? $entry['full_name'] : (string) ( $employee['full_name'] ?? '' ),
					'department' => $entry['department'] !== '' ? $entry['department'] : (string) ( $employee['department'] ?? '' ),
					'date_joined' => $entry['date_joined'] !== '' ? $entry['date_joined'] : (string) ( $employee['date_joined'] ?? '' ),
					'position' => $entry['position'] !== '' ? $entry['position'] : (string) ( $employee['position'] ?? '' ),
					'item_id' => $item_id,
					'product' => (string) $item['item_variant'], 'size' => (string) $item['size'],
					'group' => $request['group'], 'group_label' => self::group_label( $request['group'] ),
					'quantity' => $request['quantity'],
					'before_qty' => $before, 'after_qty' => $after,
					'unit_price' => (float) $item['base_price'],
				);
			}
		}

		if ( empty( $details ) && empty( $errors ) ) {
			$errors[] = 'File không có sản phẩm nào có số lượng xuất lớn hơn 0.';
		}

		return array(
			'file_name' => sanitize_file_name( $file_name ),
			'file_hash' => hash( 'sha256', 'newcomer-first-day-out|' . hash_file( 'sha256', $file_path ) ),
			'employee_count' => count( $employee_nos ),
			'rows' => $details, 'total_quantity' => array_sum( array_column( $details, 'quantity' ) ),
			'errors' => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	public static function import( $preview, $actor_user_id ) {
		if ( ! empty( $preview['errors'] ) ) {
			return array( 'success' => false, 'errors' => $preview['errors'] );
		}
		if ( UMS_DB_Inventory_Import::completed_hash_exists( $preview['file_hash'] ) ) {
			return array( 'success' => false, 'errors' => array( 'File cấp phát ngày đầu này đã được xác nhận trước đó.' ) );
		}
		$batch_id = UMS_DB_Inventory_Import::insert(
			array(
				'file_name' => 'Cấp ngày đầu - ' . $preview['file_name'], 'file_hash' => $preview['file_hash'],
				'import_status' => 'processing', 'total_rows' => count( $preview['rows'] ),
				'imported_rows' => 0, 'total_quantity' => 0, 'error_count' => 0, 'error_log' => '',
				'imported_by' => absint( $actor_user_id ), 'created_at' => current_time( 'mysql' ), 'completed_at' => null,
			)
		);
		if ( $batch_id <= 0 ) {
			return array( 'success' => false, 'errors' => array( 'Không tạo được phiên import xuất kho.' ) );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$errors = array();
		$imported = 0;
		$total = 0;
		$user_ids = array();
		foreach ( $preview['rows'] as $row ) {
			$item = UMS_DB_Inventory::get_by_id_for_update( $row['item_id'] );
			if ( ! $item || self::normalize_size( $item['size'] ) !== self::normalize_size( $row['size'] )
				|| self::normalize( $item['item_variant'] ) !== self::normalize( $row['product'] ) ) {
				$errors[] = sprintf( 'Dòng %d: sản phẩm hoặc size đã thay đổi sau bước xem trước.', $row['source_row'] );
				break;
			}
			$before = (int) $item['stock_qty'];
			$after  = $before - (int) $row['quantity'];
			if ( $after < 0 ) {
				$errors[] = sprintf( 'Dòng %d: tồn kho "%s" size "%s" không đủ.', $row['source_row'], $row['product'], $row['size'] );
				break;
			}
			if ( false === UMS_DB_Inventory::update( $row['item_id'], array( 'stock_qty' => $after ) ) ) {
				$errors[] = sprintf( 'Dòng %d: không trừ được tồn kho.', $row['source_row'] );
				break;
			}

			$employee_no = $row['employee_no'];
			if ( ! array_key_exists( $employee_no, $user_ids ) ) {
				$user = get_user_by( 'login', $employee_no );
				if ( ! $user ) {
					$matches = get_users( array( 'meta_key' => 'ums_employee_code', 'meta_value' => $employee_no, 'number' => 1 ) );
					$user = $matches ? reset( $matches ) : null;
				}
				$user_ids[ $employee_no ] = $user instanceof WP_User ? (int) $user->ID : 0;
			}

			if ( ! UMS_DB_Inventory_Movement::insert(
				array(
					'item_id' => (int) $row['item_id'], 'request_id' => null, 'movement_type' => 'out',
					'quantity' => (int) $row['quantity'], 'before_qty' => $before, 'after_qty' => $after,
					'unit_price' => (float) $item['base_price'],
					'total_price' => (float) $item['base_price'] * (int) $row['quantity'],
					'actor_user_id' => absint( $actor_user_id ),
					'target_user_id' => $user_ids[ $employee_no ] > 0 ? $user_ids[ $employee_no ] : null,
					'target_employee_no' => $employee_no,
					'target_name_snapshot' => $row['full_name'],
					'target_department_snapshot' => $row['department'],
					'target_date_joined_snapshot' => $row['date_joined'] !== '' ? $row['date_joined'] : null,
					'target_position_snapshot' => $row['position'],
					'note' => sprintf( 'Import cấp phát ngày đầu làm việc từ %s, dòng %d.', $preview['file_name'], $row['source_row'] ),
					'import_batch_id' => $batch_id, 'source_row' => (int) $row['source_row'],
				)
			) ) {
				$errors[] = sprintf( 'Dòng %d: không ghi được lịch sử xuất kho.', $row['source_row'] );
				break;
			}
			$imported++;
			$total += (int) $row['quantity'];
		}

		if ( $errors ) {
			$wpdb->query( 'ROLLBACK' );
			$imported = 0;
			$total = 0;
		} else {
			$wpdb->query( 'COMMIT' );
		}
		UMS_DB_Inventory_Import::update(
			$batch_id,
			array(
				'import_status' => $errors ? 'failed' : 'completed', 'imported_rows' => $imported,
				'total_quantity' => $total, 'error_count' => count( $errors ),
				'error_log' => wp_json_encode( $errors, JSON_UNESCAPED_UNICODE ), 'completed_at' => current_time( 'mysql' ),
			)
		);

		return array( 'success' => empty( $errors ), 'imported' => $imported, 'total' => $total, 'errors' => $errors );
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::PREVIEW_PREFIX . $token, $preview, self::PREVIEW_TTL ) ) {
			throw new RuntimeException( 'Không lưu được dữ liệu xem trước cấp phát ngày đầu.' );
		}
		return $token;
	}

	public static function get_preview( $token ) {
		return get_transient( self::PREVIEW_PREFIX . sanitize_key( $token ) );
	}

	public static function delete_preview( $token ) {
		delete_transient( self::PREVIEW_PREFIX . sanitize_key( $token ) );
	}

	private static function parse_requests( $row_number, $row, &$errors ) {
		$requests = array();
		self::add_request( $requests, 'hat', $row['G'] ?? '', '0', $row['H'] ?? '', $row_number, $errors );
		self::add_request( $requests, 'shoes', $row['I'] ?? '', $row['J'] ?? '', $row['K'] ?? '', $row_number, $errors );
		self::add_request( $requests, 'pants', $row['L'] ?? '', '', $row['M'] ?? '', $row_number, $errors );
		self::add_request( $requests, 'shirt', $row['N'] ?? '', '', $row['O'] ?? '', $row_number, $errors );
		self::add_request( $requests, 'jacket', $row['P'] ?? '', '', $row['Q'] ?? '', $row_number, $errors );
		if ( empty( $requests ) ) {
			$errors[] = sprintf( 'Dòng %d: chưa có sản phẩm nào với số lượng xuất lớn hơn 0.', $row_number );
		}
		return $requests;
	}

	private static function add_request( &$requests, $group, $raw_product, $raw_size, $raw_quantity, $row, &$errors ) {
		$product  = preg_replace( '/\s+/u', ' ', trim( sanitize_text_field( (string) $raw_product ) ) );
		$quantity = trim( (string) $raw_quantity );
		if ( $quantity === '' || $quantity === '0' ) {
			return;
		}
		if ( ! preg_match( '/^\d+$/', $quantity ) || (int) $quantity <= 0 || (int) $quantity > 1000 ) {
			$errors[] = sprintf( 'Dòng %d: số lượng %s phải là số nguyên từ 1 đến 1.000.', $row, self::group_label( $group ) );
			return;
		}
		if ( $product === '' ) {
			$errors[] = sprintf( 'Dòng %d: có số lượng %s nhưng chưa có loại sản phẩm.', $row, self::group_label( $group ) );
			return;
		}

		$size = trim( sanitize_text_field( (string) $raw_size ) );
		if ( $size === '' && preg_match( '/^(.*?)\s+size\s*[:\-]?\s*([^\s]+)\s*$/iu', $product, $matches ) ) {
			$product = trim( $matches[1] );
			$size    = trim( $matches[2] );
		}
		if ( $size === '' ) {
			$errors[] = sprintf( 'Dòng %d: "%s" thiếu size.', $row, $product );
			return;
		}
		$size = self::normalize_size( $size );
		if ( $size === '' ) {
			$errors[] = sprintf( 'Dòng %d: "%s" thiếu size.', $row, $product );
			return;
		}
		$requests[] = array(
			'group' => $group, 'source_product' => $product, 'size' => $size, 'quantity' => (int) $quantity,
		);
	}

	private static function resolve_requested_item( $request, $materials, $inventory ) {
		$item_ids   = array();
		$source_key = self::product_identity( $request['source_product'], $request['group'] );
		$size       = self::normalize_size( $request['size'] );

		foreach ( (array) $materials as $material ) {
			if ( self::product_identity( $material['item_name'] ?? '', $request['group'] ) !== $source_key
				|| self::normalize_size( $material['size'] ?? '' ) !== $size ) {
				continue;
			}
			$item_id = absint( $material['inventory_item_id'] ?? 0 );
			if ( $item_id > 0 && isset( $inventory[ $item_id ] ) ) {
				$item_ids[ $item_id ] = true;
			}
		}

		// The template may also use the already-normalized UMS product name.
		foreach ( $inventory as $item_id => $item ) {
			if ( self::product_identity( $item['item_variant'] ?? '', $request['group'] ) === $source_key
				&& self::normalize_size( $item['size'] ?? '' ) === $size ) {
				$item_ids[ absint( $item_id ) ] = true;
			}
		}

		if ( count( $item_ids ) !== 1 ) {
			return new WP_Error(
				'newcomer_inventory_item',
				sprintf(
					'loại %s "%s" size "%s" phải ánh xạ đúng một dòng kho qua master Mã SAP (tìm thấy %d).',
					self::group_label( $request['group'] ), $request['source_product'], $size, count( $item_ids )
				)
			);
		}

		$item_id = (int) array_key_first( $item_ids );
		return $inventory[ $item_id ];
	}

	private static function compare_employee_metadata( $entry, $employee, &$warnings ) {
		$checks = array(
			'full_name' => 'Họ tên', 'department' => 'Bộ phận', 'position' => 'Vị trí', 'date_joined' => 'Ngày vào',
		);
		foreach ( $checks as $field => $label ) {
			$file_value = trim( (string) ( $entry[ $field ] ?? '' ) );
			$system_value = trim( (string) ( $employee[ $field ] ?? '' ) );
			if ( $file_value !== '' && self::normalize( $file_value ) !== self::normalize( $system_value ) ) {
				$warnings[] = sprintf(
					'Dòng %d, CNV %s: %s trong file là "%s", hệ thống là "%s"; hệ thống sử dụng dữ liệu Sơ đồ tổ chức.',
					$entry['source_row'], $entry['employee_no'], $label, $file_value, $system_value
				);
			}
		}
	}

	private static function validate_headers( $headers ) {
		$expected = array(
			'A' => 'STT', 'B' => 'Mã nhân viên', 'C' => 'Tên nhân viên', 'D' => 'Bộ phận', 'E' => 'Ngày Vào',
			'F' => 'Vị trí', 'G' => 'Loại mũ', 'H' => 'Số lượng mũ', 'I' => 'Loại giày', 'J' => 'Size giày',
			'K' => 'Số lượng giày', 'L' => 'Loại quần', 'M' => 'Số lượng', 'N' => 'Loại áo', 'O' => 'Số lượng',
			'P' => 'Loại áo khoác', 'Q' => 'Số lượng',
		);
		foreach ( $expected as $column => $label ) {
			if ( self::normalize( $headers[ $column ] ?? '' ) !== self::normalize( $label ) ) {
				throw new RuntimeException( sprintf( 'Cột %s phải là "%s".', $column, $label ) );
			}
		}
	}

	private static function parse_date( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d', ( (int) $value - 25569 ) * DAY_IN_SECONDS );
		}
		foreach ( array( 'd/m/Y', 'j/n/Y', 'Y-m-d' ) as $format ) {
			$date = DateTime::createFromFormat( '!' . $format, $value );
			if ( $date && $date->format( $format ) === $value ) {
				return $date->format( 'Y-m-d' );
			}
		}
		return '';
	}

	private static function product_identity( $value, $group ) {
		$value = preg_replace( '/\s+size\s*[:\-]?\s*[^\s]+\s*$/iu', '', trim( (string) $value ) );
		$value = self::normalize( $value );
		if ( $group === 'shoes' ) {
			$value = preg_replace( '/^giay\s+/', '', $value );
		}
		return trim( $value );
	}

	private static function normalize_size( $value ) {
		$value = strtoupper( preg_replace( '/\s+/u', '', trim( (string) $value ) ) );
		$aliases = array( '2XL' => 'XXL', '3XL' => 'XXXL' );
		return $aliases[ $value ] ?? $value;
	}

	private static function normalize( $value ) {
		$value = strtolower( remove_accents( preg_replace( '/\s+/u', ' ', trim( (string) $value ) ) ) );
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', $value ) );
	}

	private static function group_label( $group ) {
		return array( 'hat' => 'mũ', 'shoes' => 'giày', 'pants' => 'quần', 'shirt' => 'áo', 'jacket' => 'áo khoác' )[ $group ] ?? $group;
	}

	private static function row_is_empty( $row ) {
		return trim( implode( '', array_map( 'strval', (array) $row ) ) ) === '';
	}
}
