<?php
/**
 * Receive periodic allocation registrations from the six factory/period Sheets.
 */
class UMS_Allocation_Sheet_Sync {
	const REST_NAMESPACE = 'ums/v1';
	const REST_ROUTE = '/sync-allocation-registration';
	const SOURCES_OPTION = 'ums_allocation_sheet_sources';
	const STAGING_PREFIX = 'ums_allocation_sheet_stage_';
	const DEFAULT_SHEET_NAME = 'Câu trả lời biểu mẫu 1';
	const BATCH_SIZE = 200;

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( __CLASS__, 'handle_sync' ),
				'permission_callback' => array( 'UMS_Sheet_User_Sync', 'authorize_request' ),
			)
		);
	}

	public static function get_sources() {
		$saved = get_option( self::SOURCES_OPTION, array() );
		$sources = array();
		foreach ( UMS_DB_Inventory::get_factory_options() as $factory_code => $factory_name ) {
			foreach ( array( 4, 9 ) as $month ) {
				$row = is_array( $saved[ $factory_code ][ $month ] ?? null ) ? $saved[ $factory_code ][ $month ] : array();
				$url = esc_url_raw( (string) ( $row['url'] ?? '' ) );
				$sources[ $factory_code ][ $month ] = array(
					'url' => $url,
					'spreadsheet_id' => self::extract_spreadsheet_id( $url ),
					'sheet_name' => sanitize_text_field( (string) ( $row['sheet_name'] ?? self::DEFAULT_SHEET_NAME ) ) ?: self::DEFAULT_SHEET_NAME,
				);
			}
		}
		return $sources;
	}

	public static function save_sources( $input ) {
		$clean = array();
		foreach ( UMS_DB_Inventory::get_factory_options() as $factory_code => $factory_name ) {
			foreach ( array( 4, 9 ) as $month ) {
				$row = is_array( $input[ $factory_code ][ $month ] ?? null ) ? $input[ $factory_code ][ $month ] : array();
				$url = esc_url_raw( wp_unslash( (string) ( $row['url'] ?? '' ) ) );
				if ( $url !== '' && self::extract_spreadsheet_id( $url ) === '' ) {
					return new WP_Error( 'allocation_sheet_url_invalid', sprintf( 'Link Google Sheet %s T%d không hợp lệ.', $factory_name, $month ) );
				}
				$sheet_name = sanitize_text_field( wp_unslash( (string) ( $row['sheet_name'] ?? '' ) ) );
				$clean[ $factory_code ][ $month ] = array(
					'url' => $url,
					'sheet_name' => $sheet_name !== '' ? $sheet_name : self::DEFAULT_SHEET_NAME,
				);
			}
		}
		update_option( self::SOURCES_OPTION, $clean, false );
		return true;
	}

	public static function extract_spreadsheet_id( $url ) {
		$url = trim( (string) $url );
		if ( preg_match( '~docs\.google\.com/spreadsheets/d/([a-zA-Z0-9_-]+)~', $url, $matches ) ) {
			return $matches[1];
		}
		return '';
	}

	public static function handle_sync( WP_REST_Request $request ) {
		if ( ! UMS_DB_Allocation_Calculation::supports_factory_sources() ) {
			return new WP_Error( 'allocation_schema_missing', 'Database chưa sẵn sàng lưu kết quả cấp phát theo nhà máy.', array( 'status' => 503 ) );
		}

		$payload = $request->get_json_params();
		$payload = is_array( $payload ) ? $payload : array();
		$factory_code = strtoupper( sanitize_key( (string) ( $payload['factory_code'] ?? '' ) ) );
		$month = absint( $payload['period_month'] ?? 0 );
		$year = absint( $payload['calculation_year'] ?? 0 );
		if ( ! array_key_exists( $factory_code, UMS_DB_Inventory::get_factory_options() ) || ! in_array( $month, array( 4, 9 ), true ) || $year < 2000 || $year > 2100 ) {
			return new WP_Error( 'allocation_scope_invalid', 'Nhà máy, năm hoặc kỳ cấp phát không hợp lệ.', array( 'status' => 400 ) );
		}

		$sources = self::get_sources();
		$source = $sources[ $factory_code ][ $month ] ?? array();
		$spreadsheet_id = sanitize_text_field( (string) ( $payload['spreadsheet_id'] ?? '' ) );
		$sheet_name = sanitize_text_field( (string) ( $payload['sheet_name'] ?? '' ) );
		if ( empty( $source['spreadsheet_id'] ) || ! hash_equals( (string) $source['spreadsheet_id'], $spreadsheet_id ) ) {
			return new WP_Error( 'allocation_source_mismatch', 'Google Sheet gửi về không khớp link đã cấu hình cho nhà máy và kỳ này.', array( 'status' => 400 ) );
		}
		if ( $sheet_name !== (string) $source['sheet_name'] ) {
			return new WP_Error( 'allocation_sheet_mismatch', 'Tên tab Google Sheet không khớp cấu hình.', array( 'status' => 400 ) );
		}

		$rows = isset( $payload['rows'] ) && is_array( $payload['rows'] ) ? $payload['rows'] : array();
		if ( count( $rows ) > self::BATCH_SIZE ) {
			return new WP_Error( 'allocation_batch_too_large', 'Mỗi batch chỉ được gửi tối đa ' . self::BATCH_SIZE . ' dòng.', array( 'status' => 413 ) );
		}
		$sync_token = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) ( $payload['sync_token'] ?? '' ) );
		if ( strlen( $sync_token ) < 12 ) {
			return new WP_Error( 'allocation_sync_token_invalid', 'Phiên đồng bộ Google Sheet không hợp lệ.', array( 'status' => 400 ) );
		}

		$key = self::STAGING_PREFIX . md5( $sync_token . '|' . $factory_code . '|' . $month . '|' . $year );
		$offset = absint( $payload['batch_offset'] ?? 0 );
		$stage = $offset === 0 ? array() : get_transient( $key );
		if ( ! is_array( $stage ) ) {
			return new WP_Error( 'allocation_sync_expired', 'Phiên đồng bộ đã hết hạn hoặc các batch được gửi sai thứ tự.', array( 'status' => 409 ) );
		}
		$stored_rows = isset( $stage['rows'] ) && is_array( $stage['rows'] ) ? $stage['rows'] : array();
		if ( $offset !== count( $stored_rows ) ) {
			return new WP_Error( 'allocation_batch_order_invalid', 'Thứ tự batch Google Sheet không hợp lệ.', array( 'status' => 409 ) );
		}

		$headers = isset( $payload['headers'] ) && is_array( $payload['headers'] ) ? $payload['headers'] : ( $stage['headers'] ?? array() );
		$stage = array(
			'headers' => $headers,
			'rows' => array_merge( $stored_rows, $rows ),
			'factory_code' => $factory_code,
			'period_month' => $month,
			'calculation_year' => $year,
			'spreadsheet_id' => $spreadsheet_id,
			'sheet_name' => $sheet_name,
		);

		if ( empty( $payload['finalize'] ) ) {
			set_transient( $key, $stage, 30 * MINUTE_IN_SECONDS );
			return new WP_REST_Response( array( 'success' => true, 'count' => count( $rows ), 'received' => count( $stage['rows'] ) ), 200 );
		}

		delete_transient( $key );
		try {
			$preview = UMS_Allocation_Calculation::analyze_sheet_rows(
				$stage['headers'],
				$stage['rows'],
				array(
					'source_name' => sprintf( 'Google Sheet %s T%d', UMS_DB_Inventory::get_factory_options()[ $factory_code ], $month ),
					'spreadsheet_id' => $spreadsheet_id,
					'sheet_name' => $sheet_name,
				),
				$year,
				$month,
				$factory_code
			);
			$preview_token = UMS_Allocation_Calculation::store_preview( $preview );
			$preview_url = add_query_arg(
				array(
					'page' => 'tvn-ums-inventory',
					'factory_code' => $factory_code,
					'notice' => empty( $preview['errors'] ) ? 'allocation_calculation_ready' : 'allocation_calculation_error',
					'allocation_preview_token' => $preview_token,
				),
				admin_url( 'admin.php' )
			) . '#ums-allocation-calculation';

			return new WP_REST_Response(
				array(
					'success' => empty( $preview['errors'] ),
					'count' => count( $rows ),
					'received' => count( $stage['rows'] ),
					'preview_token' => $preview_token,
					'preview_url' => $preview_url,
					'error_count' => count( $preview['errors'] ),
					'warning_count' => count( $preview['warnings'] ),
				),
				empty( $preview['errors'] ) ? 200 : 207
			);
		} catch ( Throwable $error ) {
			return new WP_Error( 'allocation_analysis_failed', $error->getMessage(), array( 'status' => 422 ) );
		}
	}
}
