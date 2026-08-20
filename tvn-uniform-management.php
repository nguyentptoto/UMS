<?php
/**
 * Plugin Name:       Hệ thống Quản lý Đồng phục UMS
 * Description:       Quản lý định mức, tồn kho và luồng phê duyệt cấp phát đồng phục điện tử.
 * Version:           1.0.0
 * Author:            UMS Team
 * Text Domain:       tvn-ums
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'UMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'UMS_ORGANIZATION_SYNC_CRON_HOOK', 'ums_daily_organization_sync' );

/**
 * Đăng ký tác vụ đồng bộ sơ đồ tổ chức một lần mỗi ngày.
 */
function ums_schedule_daily_organization_sync() {
    if ( wp_next_scheduled( UMS_ORGANIZATION_SYNC_CRON_HOOK ) ) {
        wp_clear_scheduled_hook( UMS_ORGANIZATION_SYNC_CRON_HOOK );
    }
    ums_ensure_sheet_sync_token();
    ums_ensure_auto_sync_bridge_token();
}

/**
 * Tạo token nhận dữ liệu Google Sheet nếu hệ thống chưa có.
 */
function ums_ensure_sheet_sync_token() {
    $token = (string) get_option( 'ums_sheet_sync_token', '' );
    if ( strlen( $token ) >= 32 ) {
        return $token;
    }

    $token = wp_generate_password( 48, false, false );
    update_option( 'ums_sheet_sync_token', $token, false );

    return $token;
}

/**
 * Tạo token cho bridge tự động nội bộ nếu hệ thống chưa có.
 */
function ums_ensure_auto_sync_bridge_token() {
    $token = (string) get_option( 'ums_auto_sync_bridge_token', '' );
    if ( strlen( $token ) >= 32 ) {
        return $token;
    }

    $token = wp_generate_password( 48, false, false );
    update_option( 'ums_auto_sync_bridge_token', $token, false );

    return $token;
}

/**
 * Xóa lịch nền khi plugin bị vô hiệu hóa.
 */
function ums_clear_daily_organization_sync() {
    wp_clear_scheduled_hook( UMS_ORGANIZATION_SYNC_CRON_HOOK );
}

register_activation_hook( __FILE__, 'ums_schedule_daily_organization_sync' );
register_deactivation_hook( __FILE__, 'ums_clear_daily_organization_sync' );
add_action( 'init', 'ums_schedule_daily_organization_sync' );

/**
 * Khởi tạo và nạp các phân hệ chính của hệ thống
 */
function run_tvn_uniform_management() {
    
    // 1. Nạp Tầng Database Layer (Theo kiến trúc mô-đun phân tách)
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-base.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-approval-flow.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-department.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-position.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-factory-location.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-contract-type.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-product-category.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory-movement.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-uniform-material.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-annual-allowance.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-request.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-user.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-organization.php';

    // Sau này thêm kho hay phiếu chỉ cần require thêm tại đây:
    // require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory.php';
    
    // 2. Nạp helper chứa các hàm tiện ích
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-helper.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-password-sync.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-organization-sync.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-sheet-user-sync.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-auto-sync-bridge.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-department-import.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-xlsx-reader.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-annual-allowance-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-inventory-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-uniform-material-import.php';
    UMS_Sheet_User_Sync::init();
    UMS_Organization_Sync::init();
    UMS_Auto_Sync_Bridge::init();
    
    // 3. Kích hoạt phân hệ Admin
    if ( is_admin() ) {
        require_once UMS_PLUGIN_DIR . 'admin/class-ums-admin.php';
        $ums_admin = new UMS_Admin();
        $ums_admin->init();
    }

    require_once UMS_PLUGIN_DIR . 'user/class-ums-user.php';
    UMS_User::init();
}
add_action( 'plugins_loaded', 'run_tvn_uniform_management' );

/**
 * Khóa đăng nhập cho tài khoản UMS đã đặt inactive trong wp_users.user_status.
 */
function ums_block_inactive_wp_user( $user, $username, $password ) {
    if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
        return $user;
    }

    global $wpdb;
    $profile_table = $wpdb->prefix . 'uniform_user_profiles';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $profile_table ) ) !== $profile_table ) {
        return $user;
    }

    $profile_count = (int) $wpdb->get_var(
        $wpdb->prepare( "SELECT COUNT(*) FROM $profile_table WHERE user_id = %d", $user->ID )
    );

    if ( $profile_count > 0 && (int) $user->user_status > 0 ) {
        return new WP_Error(
            'ums_inactive_account',
            'Tài khoản của bạn đang bị khóa. Vui lòng liên hệ quản trị viên.'
        );
    }

    return $user;
}
add_filter( 'authenticate', 'ums_block_inactive_wp_user', 30, 3 );
