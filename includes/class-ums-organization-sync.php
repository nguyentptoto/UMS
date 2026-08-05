<?php
/**
 * Đồng bộ sơ đồ tổ chức từ database TVN bên ngoài vào bảng nội bộ UMS.
 */
class UMS_Organization_Sync {

	const LOCK_KEY  = 'ums_organization_sync_lock';
	const BATCH_SIZE = 500;
	const CRON_RESULT_OPTION = 'ums_organization_sync_cron_result';

	public static function get_config() {
		return array(
			'host'     => defined( 'UMS_ORG_SYNC_DB_HOST' ) ? UMS_ORG_SYNC_DB_HOST : '172.30.134.76',
			'port'     => defined( 'UMS_ORG_SYNC_DB_PORT' ) ? absint( UMS_ORG_SYNC_DB_PORT ) : 3306,
			'user'     => defined( 'UMS_ORG_SYNC_DB_USER' ) ? UMS_ORG_SYNC_DB_USER : 'mims',
			'password' => defined( 'UMS_ORG_SYNC_DB_PASSWORD' ) ? UMS_ORG_SYNC_DB_PASSWORD : '',
			'database' => defined( 'UMS_ORG_SYNC_DB_NAME' ) ? UMS_ORG_SYNC_DB_NAME : 'qa_dims',
			'table'    => defined( 'UMS_ORG_SYNC_DB_TABLE' ) ? UMS_ORG_SYNC_DB_TABLE : 'wp_tvnorg',
		);
	}

	public static function sync() {
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
}
