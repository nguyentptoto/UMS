<?php
/**
 * Đọc CSV và upsert danh mục phòng ban theo department_code.
 */
class UMS_Department_Import {

	const MAX_FILE_SIZE = 2097152;
	const MAX_ROWS      = 5000;

	/**
	 * Kiểm tra file upload và xử lý toàn bộ dòng CSV.
	 */
	public static function import_uploaded_file( $file ) {
		if ( ! is_array( $file ) || ! isset( $file['error'] ) || (int) $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_failed', self::upload_error_message( isset( $file['error'] ) ? (int) $file['error'] : -1 ) );
		}

		$filename = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';
		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$file_size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;

		if ( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) !== 'csv' ) {
			return new WP_Error( 'invalid_file_type', 'Chỉ chấp nhận file CSV.' );
		}
		if ( $file_size <= 0 || $file_size > self::MAX_FILE_SIZE ) {
			return new WP_Error( 'invalid_file_size', 'File CSV phải có dung lượng lớn hơn 0 và không vượt quá 2 MB.' );
		}
		if ( $tmp_name === '' || ! is_uploaded_file( $tmp_name ) ) {
			return new WP_Error( 'invalid_upload', 'Không xác minh được file tải lên.' );
		}

		$handle = fopen( $tmp_name, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'cannot_read_file', 'Không đọc được file CSV.' );
		}

		$first_line = fgets( $handle );
		if ( $first_line === false ) {
			fclose( $handle );
			return new WP_Error( 'empty_file', 'File CSV không có dữ liệu.' );
		}

		$delimiter = self::detect_delimiter( $first_line );
		rewind( $handle );
		$headers = fgetcsv( $handle, 0, $delimiter );
		$columns = self::map_headers( is_array( $headers ) ? $headers : array() );

		if ( ! isset( $columns['department_code'], $columns['department_name'] ) ) {
			fclose( $handle );
			return new WP_Error( 'invalid_headers', 'CSV phải có cột department_code và department_name.' );
		}

		$result = array(
			'received'  => 0,
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'failed'    => 0,
			'errors'    => array(),
		);
		$seen_codes = array();
		$row_number = 1;

		while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			$row_number++;
			if ( self::is_empty_row( $row ) ) {
				continue;
			}
			if ( $result['received'] >= self::MAX_ROWS ) {
				$result['failed']++;
				$result['errors'][] = 'Vượt giới hạn ' . self::MAX_ROWS . ' dòng dữ liệu.';
				break;
			}

			$result['received']++;
			$raw_code = isset( $row[ $columns['department_code'] ] ) ? trim( (string) $row[ $columns['department_code'] ] ) : '';
			$name     = isset( $row[ $columns['department_name'] ] ) ? sanitize_text_field( trim( (string) $row[ $columns['department_name'] ] ) ) : '';
			$group    = isset( $columns['department_group'], $row[ $columns['department_group'] ] )
				? sanitize_text_field( trim( (string) $row[ $columns['department_group'] ] ) )
				: null;
			$code     = sanitize_key( $raw_code );

			if ( $code === '' || $name === '' ) {
				self::add_error( $result, $row_number, 'Thiếu mã hoặc tên phòng ban.' );
				continue;
			}
			if ( strlen( $code ) > 50 || self::text_length( $name ) > 150 || ( $group !== null && self::text_length( $group ) > 150 ) ) {
				self::add_error( $result, $row_number, 'Mã tối đa 50 ký tự; tên và nhóm tối đa 150 ký tự.' );
				continue;
			}
			if ( isset( $seen_codes[ $code ] ) ) {
				self::add_error( $result, $row_number, 'Mã phòng ban bị lặp trong file: ' . $code . '.' );
				continue;
			}
			$seen_codes[ $code ] = true;

			$status_value = isset( $columns['is_active'], $row[ $columns['is_active'] ] ) ? $row[ $columns['is_active'] ] : '1';
			$is_active    = self::normalize_status( $status_value );
			if ( is_wp_error( $is_active ) ) {
				self::add_error( $result, $row_number, $is_active->get_error_message() );
				continue;
			}

			$department_data = array(
				'department_code' => $code,
				'department_name' => $name,
				'is_active'       => $is_active,
			);
			if ( $group !== null ) {
				$department_data['department_group'] = $group;
			}

			$save_result = self::upsert_department( $department_data );

			if ( is_wp_error( $save_result ) ) {
				self::add_error( $result, $row_number, $save_result->get_error_message() );
				continue;
			}

			$result[ $save_result ]++;
		}

		fclose( $handle );
		return $result;
	}

	/**
	 * Mỗi dòng chạy trong transaction để đổi tên phòng ban và hồ sơ luôn đồng nhất.
	 */
	private static function upsert_department( $data ) {
		global $wpdb;

		$existing = UMS_DB_Department::get_by_code( $data['department_code'] );
		if ( ! array_key_exists( 'department_group', $data ) ) {
			$data['department_group'] = $existing && isset( $existing['department_group'] ) ? $existing['department_group'] : '';
		}

		if (
			$existing
			&& $existing['department_name'] === $data['department_name']
			&& (string) $existing['department_group'] === $data['department_group']
			&& (int) $existing['is_active'] === (int) $data['is_active']
		) {
			return 'unchanged';
		}

		$wpdb->query( 'START TRANSACTION' );
		if ( $existing ) {
			$saved = UMS_DB_Department::update( $existing['department_id'], $data );
			if ( $saved !== false && $existing['department_name'] !== $data['department_name'] ) {
				$saved = UMS_DB_User::replace_department_name( $existing['department_name'], $data['department_name'] );
			}
			$action = 'updated';
		} else {
			$saved  = UMS_DB_Department::insert( $data );
			$action = 'created';
		}

		if ( $saved === false ) {
			$error = $wpdb->last_error;
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'department_import_db_error', 'Không ghi được database: ' . $error );
		}

		$wpdb->query( 'COMMIT' );
		return $action;
	}

	private static function map_headers( $headers ) {
		$aliases = array(
			'department_code' => 'department_code',
			'ma_phong_ban'    => 'department_code',
			'ma_pb'           => 'department_code',
			'department_name' => 'department_name',
			'ten_phong_ban'   => 'department_name',
			'ten_phong'       => 'department_name',
			'department_group'=> 'department_group',
			'group'           => 'department_group',
			'nhom'            => 'department_group',
			'nhom_phong_ban'  => 'department_group',
			'is_active'       => 'is_active',
			'status'          => 'is_active',
			'trang_thai'      => 'is_active',
		);
		$columns = array();

		foreach ( $headers as $index => $header ) {
			$key = self::normalize_text_key( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header ) );
			if ( isset( $aliases[ $key ] ) && ! isset( $columns[ $aliases[ $key ] ] ) ) {
				$columns[ $aliases[ $key ] ] = $index;
			}
		}

		return $columns;
	}

	private static function detect_delimiter( $line ) {
		$delimiters = array( ',', ';', "\t" );
		$selected   = ',';
		$max_fields = 0;

		foreach ( $delimiters as $delimiter ) {
			$fields = str_getcsv( $line, $delimiter );
			if ( count( $fields ) > $max_fields ) {
				$max_fields = count( $fields );
				$selected   = $delimiter;
			}
		}

		return $selected;
	}

	private static function normalize_status( $value ) {
		$value = self::normalize_text_key( $value );
		if ( $value === '' || in_array( $value, array( '1', 'active', 'true', 'yes', 'dang_su_dung', 'dang_hoat_dong' ), true ) ) {
			return 1;
		}
		if ( in_array( $value, array( '0', 'inactive', 'false', 'no', 'ngung_su_dung', 'ngung_hoat_dong' ), true ) ) {
			return 0;
		}

		return new WP_Error( 'invalid_status', 'Trạng thái không hợp lệ: ' . sanitize_text_field( (string) $value ) . '.' );
	}

	private static function normalize_text_key( $value ) {
		$value = strtolower( remove_accents( trim( (string) $value ) ) );
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );
		return trim( $value, '_' );
	}

	private static function is_empty_row( $row ) {
		foreach ( $row as $value ) {
			if ( trim( (string) $value ) !== '' ) {
				return false;
			}
		}
		return true;
	}

	private static function add_error( &$result, $row_number, $message ) {
		$result['failed']++;
		if ( count( $result['errors'] ) < 10 ) {
			$result['errors'][] = sprintf( 'Dòng %d: %s', $row_number, $message );
		}
	}

	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
	}

	private static function upload_error_message( $error ) {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => 'File vượt quá giới hạn upload của PHP.',
			UPLOAD_ERR_FORM_SIZE  => 'File vượt quá giới hạn của biểu mẫu.',
			UPLOAD_ERR_PARTIAL    => 'File chỉ được tải lên một phần.',
			UPLOAD_ERR_NO_FILE    => 'Vui lòng chọn file CSV.',
			UPLOAD_ERR_NO_TMP_DIR => 'Máy chủ thiếu thư mục tạm.',
			UPLOAD_ERR_CANT_WRITE => 'Máy chủ không ghi được file tạm.',
			UPLOAD_ERR_EXTENSION  => 'PHP extension đã chặn file upload.',
		);

		return isset( $messages[ $error ] ) ? $messages[ $error ] : 'Không tải được file CSV.';
	}
}
