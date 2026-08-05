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
		if ( ! empty( $normalized ) ) {
			$upserted = UMS_DB_Organization::upsert_batch( $normalized, $sync_token, $synced_at );
			if ( $upserted === false ) {
				return new WP_Error(
					'organization_save_failed',
					'Không lưu được dữ liệu sơ đồ tổ chức: ' . UMS_DB_Organization::get_last_error(),
					array( 'status' => 500 )
				);
			}
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
				'results' => $results,
			),
			$failed > 0 ? 207 : 200
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

		$source_id = self::first_scalar( $row, array( 'source_id', 'id' ) );
		if ( $source_id === '' ) {
			return new WP_Error( 'organization_source_id_missing', sprintf( 'Dòng %d thiếu id/source_id.', (int) $index + 2 ) );
		}

		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return new WP_Error( 'organization_source_id_invalid', sprintf( 'Dòng %d có id/source_id không hợp lệ.', (int) $index + 2 ) );
		}

		$item = array(
			'id'          => $source_id,
			'version'     => absint( self::first_scalar( $row, array( 'source_version', 'version' ) ) ),
			'emp_no'      => sanitize_text_field( self::first_scalar( $row, array( 'employee_no', 'emp_no', 'ma_nv', 'mã nv' ) ) ),
			'fname'       => sanitize_text_field( self::first_scalar( $row, array( 'full_name', 'fname', 'ho_ten', 'họ tên' ) ) ),
			'division'    => sanitize_text_field( self::first_scalar( $row, array( 'division', 'khoi', 'khối' ) ) ),
			'department'  => sanitize_text_field( self::first_scalar( $row, array( 'department', 'phong_ban', 'phòng ban' ) ) ),
			'section'     => sanitize_text_field( self::first_scalar( $row, array( 'section', 'bo_phan', 'bộ phận' ) ) ),
			'team'        => sanitize_text_field( self::first_scalar( $row, array( 'team', 'nhom', 'nhóm' ) ) ),
			'position'    => sanitize_text_field( self::first_scalar( $row, array( 'position', 'chuc_danh', 'chức danh' ) ) ),
			'email'       => sanitize_email( self::first_scalar( $row, array( 'email' ) ) ),
			'factory'     => sanitize_text_field( self::first_scalar( $row, array( 'factory', 'nha_may', 'nhà máy' ) ) ),
			'time_create' => self::normalize_datetime( self::first_scalar( $row, array( 'source_created_at', 'time_create', 'created_at' ) ) ),
			'time_update' => self::normalize_datetime( self::first_scalar( $row, array( 'source_updated_at', 'time_update', 'updated_at' ) ) ),
		);

		if ( $item['emp_no'] === '' ) {
			return new WP_Error( 'organization_employee_no_missing', sprintf( 'Dòng %d thiếu mã nhân viên.', (int) $index + 2 ) );
		}

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
}
