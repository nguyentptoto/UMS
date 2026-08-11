<?php
/**
 * Đồng bộ sơ đồ tổ chức từ database TVN bên ngoài vào bảng nội bộ UMS.
 */
class UMS_Organization_Sync {

	const LOCK_KEY  = 'ums_organization_sync_lock';
	const BATCH_SIZE = 500;
	const CRON_RESULT_OPTION = 'ums_organization_sync_cron_result';
	const REST_NAMESPACE = 'ums/v1';
	const REST_ROUTE = '/sync-organization';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_sheet_sync' ),
				'permission_callback' => array( 'UMS_Sheet_User_Sync', 'authorize_request' ),
			)
		);
	}

	public static function handle_sheet_sync( WP_REST_Request $request ) {
		if ( ! UMS_DB_Organization::table_exists() ) {
			return new WP_Error(
				'organization_table_missing',
				'Chưa có bảng dữ liệu sơ đồ tổ chức nội bộ. Hãy import cấu trúc trong ums.sql.',
				array( 'status' => 503 )
			);
		}

		$payload = $request->get_json_params();
		$rows    = is_array( $payload ) && isset( $payload['rows'] ) && is_array( $payload['rows'] )
			? $payload['rows']
			: array();

		if ( empty( $rows ) && is_array( $payload ) && isset( $payload['organization'] ) && is_array( $payload['organization'] ) ) {
			$rows = $payload['organization'];
		}

		if ( empty( $rows ) ) {
			return new WP_Error(
				'organization_sheet_empty',
				'Payload phải có mảng rows hoặc organization.',
				array( 'status' => 400 )
			);
		}

		if ( count( $rows ) > self::BATCH_SIZE ) {
			return new WP_Error(
				'organization_batch_too_large',
				sprintf( 'Mỗi request chỉ được gửi tối đa %d nhân sự tổ chức.', self::BATCH_SIZE ),
				array( 'status' => 413 )
			);
		}

		$sync_token = self::resolve_sync_token( $payload );
		$synced_at  = current_time( 'mysql' );
		$normalized = array();
		$results    = array();

		foreach ( $rows as $index => $row ) {
			$item = self::normalize_sheet_row( $row, $index );
			if ( is_wp_error( $item ) ) {
				$results[] = array(
					'index'   => (int) $index,
					'action'  => 'error',
					'message' => $item->get_error_message(),
				);
				continue;
			}

			$normalized[] = $item;
			$results[] = array(
				'index'       => (int) $index,
				'action'      => 'accepted',
				'employee_no' => $item['emp_no'],
			);
		}

		$upserted = 0;
		$user_sync = array(
			'created'          => 0,
			'updated'          => 0,
			'password_synced'  => 0,
			'password_default' => 0,
			'errors'           => array(),
		);
		if ( ! empty( $normalized ) ) {
			$upserted = UMS_DB_Organization::upsert_batch( $normalized, $sync_token, $synced_at );
			if ( $upserted === false ) {
				return new WP_Error(
					'organization_save_failed',
					'Không lưu được dữ liệu sơ đồ tổ chức: ' . UMS_DB_Organization::get_last_error(),
					array( 'status' => 500 )
				);
			}

			$user_sync = self::sync_wp_users_from_organization_rows( $normalized );
		}

		$finalize = ! empty( $payload['finalize'] );
		$deleted  = 0;
		if ( $finalize && ! empty( $normalized ) ) {
			$deleted = UMS_DB_Organization::delete_not_in_sync( $sync_token );
			if ( $deleted === false ) {
				return new WP_Error(
					'organization_cleanup_failed',
					'Không dọn được dữ liệu tổ chức cũ: ' . UMS_DB_Organization::get_last_error(),
					array( 'status' => 500 )
				);
			}
		}

		$failed = count( $rows ) - count( $normalized );
		update_option(
			self::CRON_RESULT_OPTION,
			array(
				'status'     => $failed > 0 ? 'partial' : 'success',
				'started_at' => $synced_at,
				'ended_at'   => current_time( 'mysql' ),
				'total'      => count( $normalized ),
				'deleted'    => (int) $deleted,
				'user_sync'  => $user_sync,
				'source'     => 'google-sheet-popup-bridge',
			),
			false
		);

		return new WP_REST_Response(
			array(
				'status'  => $failed > 0 ? 'partial' : 'success',
				'success' => $failed === 0,
				'count'   => count( $rows ),
				'updated' => count( $normalized ),
				'failed'  => $failed,
				'deleted' => (int) $deleted,
				'users_created' => $user_sync['created'],
				'users_updated' => $user_sync['updated'],
				'password_synced' => $user_sync['password_synced'],
				'password_default' => $user_sync['password_default'],
				'user_errors' => $user_sync['errors'],
				'results' => $results,
			),
			$failed > 0 ? 207 : 200
		);
	}

	private static function sync_wp_users_from_organization_rows( $rows ) {
		$summary = array(
			'created'          => 0,
			'updated'          => 0,
			'password_synced'  => 0,
			'password_default' => 0,
			'errors'           => array(),
		);

		foreach ( $rows as $row ) {
			$result = self::ensure_wp_user_from_organization_row( $row );
			if ( is_wp_error( $result ) ) {
				$summary['errors'][] = $result->get_error_message();
				continue;
			}

			$summary[ $result['action'] ]++;

			$password_result = UMS_Password_Sync::sync_user_password_with_default_fallback( $result['user_id'] );
			if ( is_wp_error( $password_result ) ) {
				$summary['errors'][] = $row['emp_no'] . ': ' . $password_result->get_error_message();
				continue;
			}

			if ( ! empty( $password_result['source'] ) && $password_result['source'] === 'external' ) {
				$summary['password_synced']++;
			} else {
				$summary['password_default']++;
			}
		}

		$summary['errors'] = array_slice( $summary['errors'], 0, 30 );
		return $summary;
	}

	private static function ensure_wp_user_from_organization_row( $row ) {
		$employee_no = isset( $row['emp_no'] ) ? trim( (string) $row['emp_no'] ) : '';
		$user_login  = sanitize_user( $employee_no, true );
		if ( $user_login === '' ) {
			return new WP_Error( 'organization_wp_user_login_invalid', 'Mã nhân viên không thể dùng để tạo tài khoản WordPress: ' . $employee_no );
		}

		$email = isset( $row['email'] ) ? sanitize_email( (string) $row['email'] ) : '';

		/*
		 * TODO: Khi Google Sheet bổ sung cột email, bật lại điều kiện dưới đây
		 * để chỉ tạo tài khoản đăng nhập cho email công ty dạng @toto...
		 *
		 * if ( $email === '' || ! preg_match( '/@toto/i', $email ) ) {
		 *     return new WP_Error( 'organization_wp_user_email_not_allowed', 'Nhân sự không có email TOTO hợp lệ: ' . $employee_no );
		 * }
		 */

		$display_name = ! empty( $row['fname'] ) ? sanitize_text_field( $row['fname'] ) : $user_login;
		$user_id      = username_exists( $user_login );
		$is_new       = ! $user_id;

		if ( $is_new ) {
			$user_data = array(
				'user_login'   => $user_login,
				'user_pass'    => UMS_Password_Sync::DEFAULT_PASSWORD,
				'display_name' => $display_name,
				'nickname'     => $display_name,
				'role'         => 'subscriber',
				'user_status'  => 0,
			);

			if ( $email !== '' && is_email( $email ) ) {
				$user_data['user_email'] = $email;
			}

			$user_id = wp_insert_user( $user_data );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
		} else {
			$user_data = array(
				'ID'           => absint( $user_id ),
				'display_name' => $display_name,
				'nickname'     => $display_name,
				'user_status'  => 0,
			);

			if ( $email !== '' && is_email( $email ) ) {
				$user_data['user_email'] = $email;
			}

			$updated = wp_update_user( $user_data );
			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
			$user_id = absint( $updated );
		}

		update_user_meta( $user_id, 'ums_employee_code', $employee_no );
		update_user_meta( $user_id, 'ums_organization_source_id', absint( $row['id'] ) );
		update_user_meta( $user_id, 'ums_department', isset( $row['department'] ) ? sanitize_text_field( $row['department'] ) : '' );
		update_user_meta( $user_id, 'ums_job_position', isset( $row['position'] ) ? sanitize_text_field( $row['position'] ) : '' );
		update_user_meta( $user_id, 'ums_date_joined', isset( $row['date_joined'] ) ? sanitize_text_field( $row['date_joined'] ) : '' );
		update_user_meta( $user_id, 'ums_organization_synced_at', current_time( 'mysql', 0 ) );

		return array(
			'user_id' => absint( $user_id ),
			'action'  => $is_new ? 'created' : 'updated',
		);
	}

	public static function get_config() {
		return array(
			'host'     => '',
			'port'     => 0,
			'user'     => '',
			'password' => '',
			'database' => '',
			'table'    => '',
		);
	}

	public static function sync() {
		return new WP_Error( 'organization_external_sync_disabled', 'Sơ đồ tổ chức TVN hiện đồng bộ từ Google Sheet qua Popup Bridge, không còn kết nối database ngoài.' );

		if ( ! UMS_DB_Organization::table_exists() ) {
			return new WP_Error( 'organization_table_missing', 'Chưa có bảng dữ liệu sơ đồ tổ chức nội bộ. Hãy import cấu trúc mới trong ums.sql trước.' );
		}

		if ( get_transient( self::LOCK_KEY ) ) {
			return new WP_Error( 'organization_sync_running', 'Một phiên đồng bộ sơ đồ tổ chức đang chạy. Vui lòng thử lại sau.' );
		}

		if ( ! function_exists( 'mysqli_init' ) ) {
			return new WP_Error( 'mysqli_missing', 'Máy chủ PHP chưa bật mysqli.' );
		}

		set_transient( self::LOCK_KEY, 1, 10 * MINUTE_IN_SECONDS );
		wp_raise_memory_limit( 'admin' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		$config = self::get_config();
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $config['database'] ) || ! preg_match( '/^[A-Za-z0-9_]+$/', $config['table'] ) ) {
			delete_transient( self::LOCK_KEY );
			return new WP_Error( 'organization_config_invalid', 'Tên database hoặc bảng nguồn không hợp lệ.' );
		}

		$conn = mysqli_init();
		mysqli_options( $conn, MYSQLI_OPT_CONNECT_TIMEOUT, 8 );

		if ( ! @mysqli_real_connect( $conn, $config['host'], $config['user'], $config['password'], $config['database'], $config['port'] ) ) {
			$error = mysqli_connect_error();
			delete_transient( self::LOCK_KEY );
			return new WP_Error( 'organization_connect_failed', 'Không kết nối được database sơ đồ tổ chức: ' . $error );
		}

		mysqli_set_charset( $conn, 'utf8mb4' );
		$source_table = '`' . $config['database'] . '`.`' . $config['table'] . '`';
		$sync_token   = wp_generate_password( 32, false, false );
		$synced_at    = current_time( 'mysql' );
		$last_id      = 0;
		$total        = 0;
		$source_version = 0;

		try {
			$version_result = mysqli_query( $conn, "SELECT MAX(version) AS latest_version FROM $source_table" );
			if ( ! $version_result ) {
				throw new RuntimeException( mysqli_error( $conn ) );
			}
			$version_row    = mysqli_fetch_assoc( $version_result );
			$source_version = isset( $version_row['latest_version'] ) ? (int) $version_row['latest_version'] : 0;
			mysqli_free_result( $version_result );

			if ( $source_version <= 0 ) {
				throw new RuntimeException( 'Không xác định được version hiện hành của dữ liệu nguồn.' );
			}

			do {
				$sql = "SELECT id, version, emp_no, fname, division, department, section, team, position, email,
					factory, time_create, time_update
					FROM $source_table
					WHERE version = " . (int) $source_version . ' AND id > ' . (int) $last_id . '
					ORDER BY id ASC
					LIMIT ' . self::BATCH_SIZE;
				$result = mysqli_query( $conn, $sql );

				if ( ! $result ) {
					throw new RuntimeException( mysqli_error( $conn ) );
				}

				$batch = array();
				while ( $row = mysqli_fetch_assoc( $result ) ) {
					$last_id = max( $last_id, (int) $row['id'] );
					$batch[] = self::normalize_row( $row );
				}
				mysqli_free_result( $result );

				if ( ! empty( $batch ) ) {
					$upserted = UMS_DB_Organization::upsert_batch( $batch, $sync_token, $synced_at );
					if ( $upserted === false ) {
						throw new RuntimeException( UMS_DB_Organization::get_last_error() );
					}
					$total += count( $batch );
				}
			} while ( count( $batch ) === self::BATCH_SIZE );

			if ( $total <= 0 ) {
				throw new RuntimeException( 'Bảng nguồn không có dữ liệu; danh sách nội bộ được giữ nguyên.' );
			}

			$deleted = UMS_DB_Organization::delete_not_in_sync( $sync_token );
			if ( $deleted === false ) {
				throw new RuntimeException( UMS_DB_Organization::get_last_error() );
			}

			mysqli_close( $conn );
			delete_transient( self::LOCK_KEY );

			return array(
				'total'          => $total,
				'deleted'        => (int) $deleted,
				'source_version' => $source_version,
				'synced_at'      => $synced_at,
			);
		} catch ( Throwable $exception ) {
			mysqli_close( $conn );
			delete_transient( self::LOCK_KEY );
			return new WP_Error( 'organization_sync_failed', 'Đồng bộ thất bại: ' . $exception->getMessage() );
		}
	}

	/**
	 * Chạy đồng bộ từ WP-Cron và lưu kết quả để Admin có thể kiểm tra.
	 */
	public static function run_scheduled_sync() {
		$started_at = current_time( 'mysql' );
		$result     = self::sync();

		if ( is_wp_error( $result ) ) {
			$cron_result = array(
				'status'     => 'failed',
				'started_at' => $started_at,
				'ended_at'   => current_time( 'mysql' ),
				'error_code' => $result->get_error_code(),
				'message'    => $result->get_error_message(),
			);
		} else {
			$cron_result = array(
				'status'         => 'success',
				'started_at'     => $started_at,
				'ended_at'       => current_time( 'mysql' ),
				'total'          => isset( $result['total'] ) ? absint( $result['total'] ) : 0,
				'deleted'        => isset( $result['deleted'] ) ? absint( $result['deleted'] ) : 0,
				'source_version' => isset( $result['source_version'] ) ? absint( $result['source_version'] ) : 0,
			);
		}

		update_option( self::CRON_RESULT_OPTION, $cron_result, false );
		do_action( 'ums_after_scheduled_organization_sync', $result, $cron_result );
	}

	private static function normalize_row( $row ) {
		$text_fields = array( 'emp_no', 'fname', 'division', 'department', 'section', 'team', 'position', 'factory' );
		foreach ( $text_fields as $field ) {
			$row[ $field ] = sanitize_text_field( trim( (string) $row[ $field ] ) );
		}

		$row['email']       = sanitize_email( trim( (string) $row['email'] ) );
		$row['time_create'] = self::normalize_datetime( $row['time_create'] );
		$row['time_update'] = self::normalize_datetime( $row['time_update'] );

		return $row;
	}

	private static function normalize_datetime( $value ) {
		$value = trim( (string) $value );
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : null;
	}

	private static function normalize_sheet_row( $row, $index ) {
		if ( ! is_array( $row ) ) {
			return new WP_Error( 'organization_row_invalid', sprintf( 'Dòng %d không phải JSON object.', (int) $index + 2 ) );
		}

		$employee_no = sanitize_text_field( self::first_scalar( $row, array( 'employee_no', 'emp_no', 'ma_nv', 'mã nv', 'mã nhân viên' ) ) );
		if ( $employee_no === '' ) {
			return new WP_Error( 'organization_employee_no_missing', sprintf( 'Dòng %d thiếu mã nhân viên.', (int) $index + 2 ) );
		}

		$source_id = self::stable_source_id( $employee_no );

		$item = array(
			'id'          => $source_id,
			'version'     => absint( self::first_scalar( $row, array( 'source_version', 'version' ) ) ),
			'sheet_stt'   => absint( self::first_scalar( $row, array( 'stt', 'sheet_stt' ) ) ),
			'emp_no'      => $employee_no,
			'fname'       => sanitize_text_field( self::first_scalar( $row, array( 'full_name', 'fname', 'ho_ten', 'họ tên', 'họ và tên' ) ) ),
			'division'    => '',
			'department'  => sanitize_text_field( self::first_scalar( $row, array( 'department', 'phong_ban', 'phòng ban' ) ) ),
			'section'     => '',
			'team'        => sanitize_text_field( self::first_scalar( $row, array( 'team', 'nhom', 'nhóm' ) ) ),
			'position'    => sanitize_text_field( self::first_scalar( $row, array( 'position', 'chuc_danh', 'chức danh' ) ) ),
			'email'       => sanitize_email( self::first_scalar( $row, array( 'email', 'e-mail', 'mail' ) ) ),
			'factory'     => '',
			'cost_center' => sanitize_text_field( self::first_scalar( $row, array( 'cost_center', 'mã cost center', 'ma cost center' ) ) ),
			'date_joined' => self::normalize_date( self::first_scalar( $row, array( 'date_joined', 'ngày vào', 'ngay vao' ) ) ),
			'previous_position' => sanitize_text_field( self::first_scalar( $row, array( 'previous_position', 'vị trí trước tt', 'vi tri truoc tt' ) ) ),
			'time_create' => self::normalize_datetime( self::first_scalar( $row, array( 'source_created_at', 'time_create', 'created_at' ) ) ),
			'time_update' => self::normalize_datetime( self::first_scalar( $row, array( 'source_updated_at', 'time_update', 'updated_at' ) ) ),
		);

		return $item;
	}

	private static function first_scalar( $row, $keys ) {
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $row ) && is_scalar( $row[ $key ] ) ) {
				return trim( (string) $row[ $key ] );
			}
		}

		return '';
	}

	private static function resolve_sync_token( $payload ) {
		if ( is_array( $payload ) && ! empty( $payload['sync_token'] ) && is_scalar( $payload['sync_token'] ) ) {
			$token = sanitize_key( (string) $payload['sync_token'] );
			if ( strlen( $token ) >= 16 ) {
				return substr( $token, 0, 32 );
			}
		}

		return wp_generate_password( 32, false, false );
	}

	private static function stable_source_id( $employee_no ) {
		return (int) sprintf( '%u', crc32( strtoupper( trim( (string) $employee_no ) ) ) );
	}

	private static function normalize_date( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return null;
		}

		$formats = array( 'Y-m-d', 'd/m/Y', 'n/j/Y', 'm/d/Y' );
		foreach ( $formats as $format ) {
			$date = DateTime::createFromFormat( $format, $value );
			if ( $date instanceof DateTime ) {
				return $date->format( 'Y-m-d' );
			}
		}

		return null;
	}
}
