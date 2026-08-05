<?php
/**
 * REST receiver và Upsert Engine cho dữ liệu nhân sự gửi từ Google Sheet.
 */
class UMS_Sheet_User_Sync {

	const REST_NAMESPACE   = 'ums/v1';
	const REST_ROUTE       = '/sync-users';
	const MAX_BATCH_SIZE   = 500;
	const DEFAULT_PASSWORD = '12345678';
	const TOKEN_OPTION     = 'ums_sheet_sync_token';
	const LAST_LOG_OPTION  = 'ums_sheet_sync_last_log';

	/**
	 * Đăng ký hook REST.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
		add_filter( 'rest_allowed_cors_headers', array( __CLASS__, 'allow_sync_cors_headers' ) );
	}

	public static function allow_sync_cors_headers( $headers ) {
		$headers[] = 'X-Sync-Token';
		$headers[] = 'X-UMS-Sync-Key';
		return array_values( array_unique( $headers ) );
	}

	/**
	 * Đăng ký POST /wp-json/ums/v1/sync-users.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_sync' ),
				'permission_callback' => array( __CLASS__, 'authorize_request' ),
			)
		);
	}

	/**
	 * Xác thực sender bằng shared secret, không dùng cookie/nonce của Admin.
	 */
	public static function authorize_request( WP_REST_Request $request ) {
		$expected_token = self::get_sync_token();
		if ( strlen( $expected_token ) < 32 ) {
			return new WP_Error(
				'ums_sheet_sync_not_configured',
				'REST đồng bộ Google Sheet chưa được cấu hình shared secret.',
				array( 'status' => 503 )
			);
		}

		$provided_token = trim( (string) $request->get_header( 'x-sync-token' ) );
		if ( $provided_token === '' ) {
			$provided_token = trim( (string) $request->get_header( 'x-ums-sync-key' ) );
		}

		if ( $provided_token === '' || ! hash_equals( $expected_token, $provided_token ) ) {
			return new WP_Error(
				'ums_sheet_sync_unauthorized',
				'Khóa đồng bộ không hợp lệ.',
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Nhận một batch JSON và xử lý từng nhân sự độc lập.
	 */
	public static function handle_sync( WP_REST_Request $request ) {
		if ( ! UMS_DB_User::table_exists() ) {
			return new WP_Error(
				'ums_profile_table_missing',
				'Chưa có bảng hồ sơ nhân sự. Hãy import cấu trúc trong ums.sql.',
				array( 'status' => 503 )
			);
		}

		$payload = $request->get_json_params();
		$users   = is_array( $payload ) && isset( $payload['users'] ) && is_array( $payload['users'] )
			? $payload['users']
			: array();

		if ( empty( $users ) ) {
			self::record_sync_log(
				array(
					'success' => false,
					'summary' => array(
						'received' => 0,
						'created'  => 0,
						'updated'  => 0,
						'failed'   => 0,
					),
					'errors'  => array( 'Payload phải có mảng users và ít nhất một nhân sự.' ),
					'source'  => self::payload_source( $payload ),
				)
			);

			return new WP_Error(
				'ums_sheet_sync_empty',
				'Payload phải có mảng users và ít nhất một nhân sự.',
				array( 'status' => 400 )
			);
		}

		if ( count( $users ) > self::MAX_BATCH_SIZE ) {
			self::record_sync_log(
				array(
					'success' => false,
					'summary' => array(
						'received' => count( $users ),
						'created'  => 0,
						'updated'  => 0,
						'failed'   => count( $users ),
					),
					'errors'  => array( sprintf( 'Mỗi request chỉ được gửi tối đa %d nhân sự.', self::MAX_BATCH_SIZE ) ),
					'source'  => self::payload_source( $payload ),
				)
			);

			return new WP_Error(
				'ums_sheet_sync_batch_too_large',
				sprintf( 'Mỗi request chỉ được gửi tối đa %d nhân sự.', self::MAX_BATCH_SIZE ),
				array( 'status' => 413 )
			);
		}

		$summary = array(
			'received' => count( $users ),
			'created'  => 0,
			'updated'  => 0,
			'failed'   => 0,
		);
		$results       = array();
		$batch_codes   = array();

		foreach ( $users as $index => $raw_user ) {
			$employee_code = is_array( $raw_user ) && isset( $raw_user['employee_code'] )
				? trim( sanitize_text_field( (string) $raw_user['employee_code'] ) )
				: '';
			$batch_code_key = strtolower( $employee_code );

			if ( $employee_code !== '' && isset( $batch_codes[ $batch_code_key ] ) ) {
				$result = new WP_Error( 'duplicate_employee_code', 'Mã nhân viên bị lặp trong cùng payload.' );
			} elseif ( ! is_array( $raw_user ) ) {
				$result = new WP_Error( 'invalid_user_row', 'Dữ liệu nhân sự phải là một JSON object.' );
			} else {
				if ( $employee_code !== '' ) {
					$batch_codes[ $batch_code_key ] = true;
				}
				$result = self::upsert_user( $raw_user );
			}

			if ( is_wp_error( $result ) ) {
				$summary['failed']++;
				$results[] = array(
					'index'         => (int) $index,
					'employee_code' => $employee_code,
					'action'        => 'error',
					'error_code'    => $result->get_error_code(),
					'message'       => $result->get_error_message(),
				);
				continue;
			}

			$summary[ $result['action'] ]++;
			$results[] = array(
				'index'         => (int) $index,
				'employee_code' => $result['employee_code'],
				'user_id'       => $result['user_id'],
				'profile_id'    => $result['profile_id'],
				'action'        => $result['action'],
			);
		}

		$errors = array();
		foreach ( $results as $result ) {
			if ( isset( $result['action'] ) && $result['action'] === 'error' ) {
				$errors[] = sprintf(
					'Row %d%s: %s',
					isset( $result['index'] ) ? (int) $result['index'] + 2 : 0,
					! empty( $result['employee_code'] ) ? ' - ' . $result['employee_code'] : '',
					isset( $result['message'] ) ? $result['message'] : 'Lỗi không xác định'
				);
			}
		}

		self::record_sync_log(
			array(
				'success' => $summary['failed'] === 0,
				'summary' => $summary,
				'errors'  => $errors,
				'source'  => self::payload_source( $payload ),
			)
		);

		return new WP_REST_Response(
			array(
				'status'  => $summary['failed'] === 0 ? 'success' : 'partial',
				'success' => $summary['failed'] === 0,
				'count'   => $summary['received'],
				'created' => $summary['created'],
				'updated' => $summary['updated'],
				'failed'  => $summary['failed'],
				'errors'  => $errors,
				'summary' => $summary,
				'results' => $results,
			),
			$summary['failed'] > 0 ? 207 : 200
		);
	}

	public static function get_sync_token() {
		$token = (string) get_option( self::TOKEN_OPTION, '' );
		if ( strlen( $token ) >= 32 ) {
			return $token;
		}

		$token = wp_generate_password( 48, false, false );
		update_option( self::TOKEN_OPTION, $token, false );

		return $token;
	}

	public static function record_sync_log( $log ) {
		$summary = isset( $log['summary'] ) && is_array( $log['summary'] ) ? $log['summary'] : array();
		$errors  = isset( $log['errors'] ) && is_array( $log['errors'] ) ? array_slice( $log['errors'], 0, 50 ) : array();

		update_option(
			self::LAST_LOG_OPTION,
			array(
				'synced_at' => current_time( 'mysql', 0 ),
				'success'   => ! empty( $log['success'] ),
				'source'    => isset( $log['source'] ) ? sanitize_text_field( (string) $log['source'] ) : '',
				'summary'   => array(
					'received' => isset( $summary['received'] ) ? absint( $summary['received'] ) : 0,
					'created'  => isset( $summary['created'] ) ? absint( $summary['created'] ) : 0,
					'updated'  => isset( $summary['updated'] ) ? absint( $summary['updated'] ) : 0,
					'failed'   => isset( $summary['failed'] ) ? absint( $summary['failed'] ) : 0,
				),
				'errors'    => array_map( 'sanitize_text_field', $errors ),
			),
			false
		);
	}

	public static function get_last_log() {
		$log = get_option( self::LAST_LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	private static function payload_source( $payload ) {
		if ( ! is_array( $payload ) ) {
			return '';
		}

		if ( ! empty( $payload['source'] ) ) {
			return sanitize_text_field( (string) $payload['source'] );
		}

		if ( ! empty( $payload['spreadsheet_id'] ) ) {
			return 'google-sheet:' . sanitize_text_field( (string) $payload['spreadsheet_id'] );
		}

		return 'popup-bridge';
	}

	/**
	 * Upsert một tài khoản và hồ sơ theo employee_code.
	 */
	private static function upsert_user( $raw_user ) {
		$employee_code = isset( $raw_user['employee_code'] ) ? trim( sanitize_text_field( (string) $raw_user['employee_code'] ) ) : '';
		if ( $employee_code === '' ) {
			return new WP_Error( 'employee_code_required', 'Thiếu Mã nhân viên (employee_code).' );
		}
		if ( self::text_length( $employee_code ) > 50 ) {
			return new WP_Error( 'employee_code_too_long', 'Mã nhân viên không được dài quá 50 ký tự.' );
		}

		$user_login = sanitize_user( $employee_code, true );
		if ( $user_login === '' ) {
			return new WP_Error( 'invalid_employee_code', 'Mã nhân viên không thể dùng làm tài khoản WordPress.' );
		}

		$existing = UMS_DB_User::get_by_employee_code( $employee_code );
		$data     = self::normalize_profile_data( $raw_user, $existing );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		$wp_user_id = self::ensure_wp_user( $data, $existing );
		if ( is_wp_error( $wp_user_id ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $wp_user_id;
		}

		$profile_data            = $data;
		$profile_data['user_id'] = $wp_user_id;
		unset( $profile_data['account_status'], $profile_data['user_email'] );

		if ( $existing ) {
			$profile_id = absint( $existing['profile_id'] );
			$saved      = UMS_DB_User::update( $profile_id, $profile_data );
			$action     = 'updated';
		} else {
			$saved      = UMS_DB_User::insert( $profile_data );
			$profile_id = $saved === false ? 0 : absint( $wpdb->insert_id );
			$action     = 'created';
		}

		if ( $saved === false || $profile_id <= 0 ) {
			$error = UMS_DB_User::get_last_error();
			$wpdb->query( 'ROLLBACK' );
			clean_user_cache( $wp_user_id );
			return new WP_Error( 'profile_save_failed', 'Không lưu được hồ sơ nhân sự: ' . $error );
		}

		self::sync_profile_meta( $wp_user_id, $data );
		$wpdb->query( 'COMMIT' );
		clean_user_cache( $wp_user_id );

		return array(
			'action'        => $action,
			'employee_code' => $employee_code,
			'user_id'       => $wp_user_id,
			'profile_id'    => $profile_id,
		);
	}

	/**
	 * Chuẩn hóa payload; khi update, trường không gửi lên sẽ giữ nguyên.
	 */
	private static function normalize_profile_data( $raw, $existing ) {
		$is_new = empty( $existing );
		$data   = array(
			'employee_code'     => trim( sanitize_text_field( (string) $raw['employee_code'] ) ),
			'full_name'         => self::incoming_or_existing( $raw, 'full_name', $existing, '' ),
			'gender'            => self::incoming_or_existing( $raw, 'gender', $existing, '' ),
			'factory_location'  => self::incoming_or_existing( $raw, 'factory_location', $existing, '' ),
			'department'        => self::incoming_or_existing( $raw, 'department', $existing, '' ),
			'job_position'      => self::incoming_or_existing( $raw, 'job_position', $existing, '' ),
			'contract_type'     => self::incoming_or_existing( $raw, 'contract_type', $existing, '' ),
			'date_joined'       => self::incoming_or_existing( $raw, 'date_joined', $existing, '' ),
			'resignation_date'  => self::nullable_date_value( $raw, 'resignation_date', $existing ),
			'transfer_date'     => self::nullable_date_value( $raw, 'transfer_date', $existing ),
			'is_maternity'      => self::boolean_value( $raw, 'is_maternity', $existing ),
			'is_outdoor_worker' => self::boolean_value( $raw, 'is_outdoor_worker', $existing ),
			'account_status'    => self::incoming_or_existing(
				$raw,
				'account_status',
				$existing,
				'active',
				$existing && isset( $existing['user_status'] ) && (int) $existing['user_status'] > 0 ? 'inactive' : 'active'
			),
			'user_email'        => self::incoming_or_existing( $raw, 'email', $existing, '', isset( $existing['user_email'] ) ? $existing['user_email'] : '' ),
		);

		foreach ( array( 'full_name', 'gender', 'factory_location', 'department', 'job_position', 'contract_type', 'date_joined' ) as $required ) {
			$data[ $required ] = sanitize_text_field( (string) $data[ $required ] );
			if ( $data[ $required ] === '' ) {
				return new WP_Error(
					'missing_required_field',
					sprintf( 'Nhân sự %s thiếu trường bắt buộc: %s.', $data['employee_code'], $required )
				);
			}
		}

		$field_limits = array(
			'full_name'        => 255,
			'factory_location' => 150,
			'department'       => 100,
			'job_position'     => 100,
			'contract_type'    => 150,
		);
		foreach ( $field_limits as $field => $limit ) {
			if ( self::text_length( $data[ $field ] ) > $limit ) {
				return new WP_Error( 'field_too_long', sprintf( '%s không được dài quá %d ký tự.', $field, $limit ) );
			}
		}

		if ( ! in_array( $data['gender'], UMS_DB_User::GENDERS, true ) ) {
			return new WP_Error( 'invalid_gender', 'Giới tính chỉ nhận Nam hoặc Nữ.' );
		}

		if ( ! self::is_valid_date( $data['date_joined'] ) ) {
			return new WP_Error( 'invalid_date_joined', 'Ngày vào công ty phải có định dạng YYYY-MM-DD.' );
		}

		foreach ( array( 'resignation_date', 'transfer_date' ) as $date_field ) {
			if ( $data[ $date_field ] !== null && ! self::is_valid_date( $data[ $date_field ] ) ) {
				return new WP_Error( 'invalid_optional_date', sprintf( '%s phải có định dạng YYYY-MM-DD.', $date_field ) );
			}
		}

		$data['account_status'] = sanitize_key( (string) $data['account_status'] );
		if ( ! in_array( $data['account_status'], array( 'active', 'inactive' ), true ) ) {
			return new WP_Error( 'invalid_account_status', 'account_status chỉ nhận active hoặc inactive.' );
		}

		if ( $data['user_email'] !== '' ) {
			$email = sanitize_email( (string) $data['user_email'] );
			if ( $email === '' ) {
				return new WP_Error( 'invalid_email', 'Email nhân sự không hợp lệ.' );
			}
			$data['user_email'] = $email;
		}

		$catalog_fields = array(
			'department'       => array( UMS_DB_Department::get_active(), 'department_code', 'department_name' ),
			'job_position'     => array( UMS_DB_Position::get_active(), 'position_code', 'position_name' ),
			'factory_location' => array( UMS_DB_Factory_Location::get_active(), 'factory_location_code', 'factory_location_name' ),
			'contract_type'    => array( UMS_DB_Contract_Type::get_active(), 'contract_type_code', 'contract_type_name' ),
		);

		foreach ( $catalog_fields as $field => $catalog ) {
			$field_was_sent = array_key_exists( $field, $raw );
			if ( ! $is_new && ! $field_was_sent ) {
				continue;
			}

			$resolved = self::resolve_catalog_value( $data[ $field ], $catalog[0], $catalog[1], $catalog[2] );
			if ( $resolved === '' ) {
				return new WP_Error(
					'unknown_catalog_value',
					sprintf( '%s "%s" chưa có trong danh mục đang sử dụng.', $field, $data[ $field ] )
				);
			}
			$data[ $field ] = $resolved;
		}

		return $data;
	}

	/**
	 * Tạo/cập nhật wp_users. Tài khoản mới dùng mật khẩu mặc định của UMS.
	 */
	private static function ensure_wp_user( $data, $existing ) {
		$user_id = $existing && ! empty( $existing['user_id'] ) ? absint( $existing['user_id'] ) : 0;
		$user    = $user_id ? get_user_by( 'id', $user_id ) : false;
		$login   = sanitize_user( $data['employee_code'], true );

		if ( ! $user ) {
			$existing_user_id = username_exists( $login );
			if ( $existing_user_id ) {
				$user_id = absint( $existing_user_id );
				$user    = get_user_by( 'id', $user_id );
			}
		}

		if ( ! $user ) {
			$user_data = array(
				'user_login'   => $login,
				'user_pass'    => self::DEFAULT_PASSWORD,
				'display_name' => $data['full_name'],
				'nickname'     => $data['full_name'],
				'role'         => 'subscriber',
				'user_status'  => $data['account_status'] === 'inactive' ? 1 : 0,
			);
			if ( $data['user_email'] !== '' ) {
				$user_data['user_email'] = $data['user_email'];
			}

			$user_id = wp_insert_user( $user_data );
			return is_wp_error( $user_id ) ? $user_id : absint( $user_id );
		}

		$update_data = array(
			'ID'           => $user->ID,
			'display_name' => $data['full_name'],
			'nickname'     => $data['full_name'],
			'user_status'  => $data['account_status'] === 'inactive' ? 1 : 0,
		);
		if ( $data['user_email'] !== '' ) {
			$update_data['user_email'] = $data['user_email'];
		}

		$updated = wp_update_user( $update_data );
		return is_wp_error( $updated ) ? $updated : absint( $updated );
	}

	/**
	 * Giữ wp_usermeta tương thích với màn hình User Profile chuẩn của WordPress.
	 */
	private static function sync_profile_meta( $user_id, $data ) {
		update_user_meta( $user_id, 'ums_employee_code', $data['employee_code'] );
		update_user_meta( $user_id, 'ums_date_joined', $data['date_joined'] );
		update_user_meta( $user_id, 'ums_department', $data['department'] );
		update_user_meta( $user_id, 'ums_job_position', $data['job_position'] );
		update_user_meta( $user_id, 'ums_is_maternity', $data['is_maternity'] );
		update_user_meta( $user_id, 'ums_has_resigned', $data['resignation_date'] !== null ? 1 : 0 );
		update_user_meta( $user_id, 'ums_is_outdoor_worker', $data['is_outdoor_worker'] );
	}

	private static function incoming_or_existing( $raw, $field, $existing, $new_default = '', $existing_default = null ) {
		if ( array_key_exists( $field, $raw ) ) {
			return is_scalar( $raw[ $field ] ) ? trim( (string) $raw[ $field ] ) : '';
		}

		if ( $existing ) {
			if ( $existing_default !== null ) {
				return $existing_default;
			}
			return isset( $existing[ $field ] ) ? $existing[ $field ] : '';
		}

		return $new_default;
	}

	private static function nullable_date_value( $raw, $field, $existing ) {
		if ( array_key_exists( $field, $raw ) ) {
			$value = is_scalar( $raw[ $field ] ) ? trim( (string) $raw[ $field ] ) : '';
			return $value !== '' ? $value : null;
		}

		return $existing && ! empty( $existing[ $field ] ) ? $existing[ $field ] : null;
	}

	private static function boolean_value( $raw, $field, $existing ) {
		if ( array_key_exists( $field, $raw ) ) {
			$value = $raw[ $field ];
			if ( is_bool( $value ) ) {
				return $value ? 1 : 0;
			}

			return in_array( strtolower( trim( (string) $value ) ), array( '1', 'true', 'yes', 'y', 'có', 'co' ), true ) ? 1 : 0;
		}

		return $existing && ! empty( $existing[ $field ] ) ? 1 : 0;
	}

	private static function resolve_catalog_value( $value, $rows, $code_field, $name_field ) {
		$value = trim( (string) $value );
		foreach ( $rows as $row ) {
			$code = isset( $row[ $code_field ] ) ? trim( (string) $row[ $code_field ] ) : '';
			$name = isset( $row[ $name_field ] ) ? trim( (string) $row[ $name_field ] ) : '';
			if ( $value === $code || $value === $name || strcasecmp( $value, $code ) === 0 || strcasecmp( $value, $name ) === 0 ) {
				return $name;
			}
		}

		return '';
	}

	private static function is_valid_date( $date ) {
		$parsed = DateTime::createFromFormat( 'Y-m-d', (string) $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}

	private static function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
	}
}
