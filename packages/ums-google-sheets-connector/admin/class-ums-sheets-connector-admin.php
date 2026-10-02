<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class UMS_Sheets_Connector_Admin {
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'add_menu' ), 20 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
		add_action( 'admin_post_ums_save_allocation_sheet_sources', array( __CLASS__, 'handle_save_allocation_sheet_sources' ) );
        add_action( 'admin_post_ums_sync_organization', array( __CLASS__, 'handle_sync_organization' ) );
        add_action( 'admin_post_ums_save_sheet_sync_settings', array( __CLASS__, 'handle_save_sheet_sync_settings' ) );
		add_action( 'wp_ajax_ums_allocation_sync_status', array( __CLASS__, 'handle_allocation_sync_status' ) );
        add_action( 'ums_render_allocation_sheet_controls', array( __CLASS__, 'render_allocation_controls' ), 10, 2 );
        add_action( 'ums_organization_sync_summary', array( __CLASS__, 'render_organization_summary' ) );
        add_action( 'ums_organization_sync_button', array( __CLASS__, 'render_organization_button' ) );
        add_action( 'ums_organization_sync_status', array( __CLASS__, 'render_organization_status' ) );
        add_filter( 'ums_admin_notice_messages', array( __CLASS__, 'notice_messages' ) );
    }

    public static function add_menu() {
        add_submenu_page(
            'tvn-uniform-management',
            'Đồng bộ Google Sheet',
            'Đồng bộ Sheet',
            'manage_options',
            'tvn-ums-sheet-sync',
            array( __CLASS__, 'render_sheet_sync_page' )
        );
    }

    public static function enqueue_assets( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        if ( ! current_user_can( 'manage_options' ) || ! in_array( $page, array( 'tvn-uniform-management', 'tvn-ums-sheet-sync', 'tvn-ums-allocation-calculation' ), true ) ) { return; }
        wp_enqueue_style( 'ums-sheets-connector', UMS_SHEETS_CONNECTOR_URL . 'admin/css/ums-sheets-connector.css', array( 'ums-admin-css' ), '1.0.0' );
        wp_enqueue_script( 'ums-sheets-connector', UMS_SHEETS_CONNECTOR_URL . 'admin/js/ums-sheets-connector.js', array( 'ums-admin-js' ), '1.0.0', true );
        wp_localize_script( 'ums-sheets-connector', 'umsSheetsConnector', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'allocationSyncNonce' => wp_create_nonce( 'ums_allocation_sync_status' ),
        ) );
    }

    public static function notice_messages( $messages ) {
        return array_merge( $messages, array(
            'sheet_sync_settings_saved' => array( 'success', 'Đã lưu cấu hình đồng bộ Google Sheet.' ),
			'allocation_sheet_settings_saved' => array( 'success', 'Đã lưu 6 nguồn Google Sheet cấp phát theo nhà máy và kỳ.' ),
			'allocation_sheet_settings_error' => array( 'error', 'Không lưu được cấu hình Google Sheet cấp phát.' ),
            'organization_synced' => array( 'success', 'Đồng bộ sơ đồ tổ chức thành công.' ),
            'organization_sync_failed' => array( 'error', 'Không thể đồng bộ sơ đồ tổ chức.' ),
        ) );
    }

    public static function render_allocation_controls( $selected_factory_code, $allocation_calculation_ready ) {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $factories = UMS_DB_Inventory::get_factory_options();
        $allocation_sheet_sources = UMS_Allocation_Sheet_Sync::get_sources();
        $allocation_sheet_apps_script_url = UMS_Allocation_Sheet_Sync::get_apps_script_url();
        $allocation_sheet_rest_endpoint = rest_url( UMS_Allocation_Sheet_Sync::REST_NAMESPACE . UMS_Allocation_Sheet_Sync::REST_ROUTE );
        $allocation_sheet_sync_token = UMS_Sheet_User_Sync::get_sync_token();
        include UMS_SHEETS_CONNECTOR_DIR . 'admin/partials/view-allocation-sheet-controls.php';
    }

    public static function render_organization_summary() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $cron_result = get_option( UMS_Organization_Sync::CRON_RESULT_OPTION, array() );
        include UMS_SHEETS_CONNECTOR_DIR . 'admin/partials/view-organization-sync-summary.php';
    }

    public static function render_organization_button( $table_ready ) {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $apps_script_url = (string) get_option( 'ums_sheet_sync_apps_script_url', '' );
        $rest_endpoint = rest_url( UMS_Organization_Sync::REST_NAMESPACE . UMS_Organization_Sync::REST_ROUTE );
        $sync_token = UMS_Sheet_User_Sync::get_sync_token();
        $auto_start_sync = isset( $_GET['ums_auto_sync'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ums_auto_sync'] ) );
        include UMS_SHEETS_CONNECTOR_DIR . 'admin/partials/view-organization-sync-button.php';
    }

    public static function render_organization_status() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $apps_script_url = (string) get_option( 'ums_sheet_sync_apps_script_url', '' );
        include UMS_SHEETS_CONNECTOR_DIR . 'admin/partials/view-organization-sync-status.php';
    }

    public static function render_sheet_sync_page() {
        $apps_script_url = (string) get_option( 'ums_sheet_sync_apps_script_url', '' );
        $rest_endpoint   = rest_url( UMS_Organization_Sync::REST_NAMESPACE . UMS_Organization_Sync::REST_ROUTE );
        $sync_token      = UMS_Sheet_User_Sync::get_sync_token();
        $bridge_url      = UMS_Auto_Sync_Bridge::get_bridge_url();
        $last_log        = UMS_Sheet_User_Sync::get_last_log();
        $notice          = UMS_Admin::get_notice();

        if ( file_exists( UMS_SHEETS_CONNECTOR_DIR . 'admin/partials/view-sheet-sync.php' ) ) {
            include_once UMS_SHEETS_CONNECTOR_DIR . 'admin/partials/view-sheet-sync.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-sheet-sync.php</p></div>';
        }
    }

    public static function handle_save_sheet_sync_settings() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_sheet_sync_settings' );

        $apps_script_url = isset( $_POST['apps_script_url'] ) ? esc_url_raw( wp_unslash( $_POST['apps_script_url'] ) ) : '';
        update_option( 'ums_sheet_sync_apps_script_url', $apps_script_url, false );

        self::redirect_to_sheet_sync( array( 'notice' => 'sheet_sync_settings_saved' ) );
    }

    public static function handle_sync_organization() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_sync_organization' );

        $result = UMS_Organization_Sync::sync();
        if ( is_wp_error( $result ) ) {
            self::redirect_to_organization(
                array(
                    'notice'       => 'organization_sync_failed',
                    'notice_extra' => $result->get_error_message(),
                )
            );
        }

        self::redirect_to_organization(
            array(
                'notice'       => 'organization_synced',
                'notice_extra' => sprintf(
					'Đã nhận %s nhân sự từ version %s; ghi nhận %s CNV không còn trong sơ đồ là nghỉ việc.',
                    number_format_i18n( $result['total'] ),
                    number_format_i18n( $result['source_version'] ),
                    number_format_i18n( $result['deleted'] )
                ),
            )
        );
    }

	public static function handle_save_allocation_sheet_sources() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
		}
		check_admin_referer( 'ums_save_allocation_sheet_sources' );
		$apps_script_result = UMS_Allocation_Sheet_Sync::save_apps_script_url( $_POST['allocation_apps_script_url'] ?? '' );
		if ( is_wp_error( $apps_script_result ) ) {
			self::redirect_to_allocation_calculation( array( 'notice' => 'allocation_sheet_settings_error', 'notice_extra' => $apps_script_result->get_error_message() ) );
		}
		$result = UMS_Allocation_Sheet_Sync::save_sources( $_POST['allocation_sheet_sources'] ?? array() );
		if ( is_wp_error( $result ) ) {
			self::redirect_to_allocation_calculation( array( 'notice' => 'allocation_sheet_settings_error', 'notice_extra' => $result->get_error_message() ) );
		}
		self::redirect_to_allocation_calculation( array( 'notice' => 'allocation_sheet_settings_saved' ) );
	}

	public static function handle_allocation_sync_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Bạn không có quyền xem kết quả đồng bộ.' ), 403 );
		}
		check_ajax_referer( 'ums_allocation_sync_status', 'security' );
		$client_sync_id = isset( $_POST['client_sync_id'] ) ? sanitize_key( wp_unslash( $_POST['client_sync_id'] ) ) : '';
		$status = UMS_Allocation_Sheet_Sync::get_sync_status( $client_sync_id );
		wp_send_json_success( $status ?: array( 'state' => 'pending' ) );
	}

    private static function redirect_to_sheet_sync( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-sheet-sync' ),
                    $args
                ),
                function( $value ) {
                    return $value !== null && $value !== '';
                }
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

    private static function redirect_to_organization( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-uniform-management' ),
                    $args
                ),
                function( $value ) {
                    return $value !== null && $value !== '';
                }
            ),
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $url );
        exit;
    }

	private static function redirect_to_allocation_calculation( $args = array() ) {
		if ( empty( $args['factory_code'] ) && ! empty( $_POST['factory_code'] ) ) {
			$args['factory_code'] = UMS_DB_Inventory::normalize_factory_code( wp_unslash( $_POST['factory_code'] ) );
		}

		$url = add_query_arg(
			array_filter(
				array_merge(
					array( 'page' => 'tvn-ums-allocation-calculation' ),
					$args
				),
				function( $value ) {
					return $value !== null && $value !== '';
				}
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
