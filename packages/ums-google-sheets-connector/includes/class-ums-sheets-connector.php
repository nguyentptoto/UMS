<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class UMS_Sheets_Connector {
    private static $booted = false;

    public static function boot() {
        if ( self::$booted ) { return; }
        if ( ! defined( 'UMS_SHEETS_API_VERSION' )
            || version_compare( UMS_SHEETS_API_VERSION, '1.0.0', '<' )
            || version_compare( UMS_SHEETS_API_VERSION, '2.0.0', '>=' )
            || ! class_exists( 'UMS_DB_Organization' )
            || ! class_exists( 'UMS_DB_User' )
            || ! class_exists( 'UMS_Allocation_Calculation' )
            || ! class_exists( 'UMS_Employee_Exit_Manager' ) ) {
            add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
            return;
        }
        // An old UMS or a second connector must never register a second receiver.
        foreach ( array( 'UMS_Sheet_User_Sync', 'UMS_Organization_Sync', 'UMS_Allocation_Sheet_Sync', 'UMS_Auto_Sync_Bridge' ) as $class ) {
            if ( class_exists( $class, false ) ) {
                add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
                return;
            }
        }
        self::$booted = true;
        if ( ! defined( 'UMS_ORGANIZATION_SYNC_CRON_HOOK' ) ) {
            define( 'UMS_ORGANIZATION_SYNC_CRON_HOOK', 'ums_daily_organization_sync' );
        }
        require_once UMS_SHEETS_CONNECTOR_DIR . 'includes/class-ums-sheet-user-sync.php';
        require_once UMS_SHEETS_CONNECTOR_DIR . 'includes/class-ums-organization-sync.php';
        require_once UMS_SHEETS_CONNECTOR_DIR . 'includes/class-ums-allocation-sheet-sync.php';
        require_once UMS_SHEETS_CONNECTOR_DIR . 'includes/class-ums-auto-sync-bridge.php';
        UMS_Sheet_User_Sync::init();
        UMS_Organization_Sync::init();
        UMS_Allocation_Sheet_Sync::init();
        UMS_Auto_Sync_Bridge::init();
        add_action( 'init', array( __CLASS__, 'prepare' ) );
        if ( is_admin() ) {
            require_once UMS_SHEETS_CONNECTOR_DIR . 'admin/class-ums-sheets-connector-admin.php';
            UMS_Sheets_Connector_Admin::init();
        }
    }

    public static function prepare() {
        // Reuse existing secrets. Do not restore the obsolete database-sync cron.
        UMS_Sheet_User_Sync::get_sync_token();
        UMS_Auto_Sync_Bridge::get_token();
        if ( wp_next_scheduled( 'ums_daily_organization_sync' ) ) {
            wp_clear_scheduled_hook( 'ums_daily_organization_sync' );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'ums_daily_organization_sync' );
        // Configuration, tokens, previews and synchronized business data survive.
    }

    public static function dependency_notice() {
        if ( ! current_user_can( 'activate_plugins' ) ) { return; }
        echo '<div class="notice notice-warning"><p>' . esc_html( 'UMS Google Sheets Connector chưa chạy. Cần kích hoạt bản UMS hỗ trợ Connector API 1.x và không nạp đồng thời bộ đồng bộ cũ.' ) . '</p></div>';
    }
}
