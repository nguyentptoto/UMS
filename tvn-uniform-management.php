<?php
/**
 * Plugin Name:       Hệ thống Quản lý Đồng phục UMS
 * Description:       Quản lý định mức, tồn kho và luồng phê duyệt cấp phát đồng phục điện tử.
 * Version:           1.1.0
 * Author:            UMS Team
 * Text Domain:       tvn-ums
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'UMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UMS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Legacy employee-return reminders stay disabled independently of connectors.
add_action( 'init', function () {
    if ( wp_next_scheduled( 'ums_daily_employee_exit_reminder' ) ) {
        wp_clear_scheduled_hook( 'ums_daily_employee_exit_reminder' );
    }
} );

/**
 * Khởi tạo và nạp các phân hệ chính của hệ thống
 */
function run_tvn_uniform_management() {
    
    // 1. Nạp Tầng Database Layer (Theo kiến trúc mô-đun phân tách)
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-base.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-approval-flow.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-approval-delegation.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-approval-concurrent-assignment.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-department.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-position.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-factory-location.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-contract-type.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-product-category.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory-movement.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-uniform-material.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-allocation-calculation.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-annual-allowance.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-request.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-user.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-organization.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-employee-exit.php';
	require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-special-work-assignment.php';

    // Sau này thêm kho hay phiếu chỉ cần require thêm tại đây:
    // require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory.php';
    
    // 2. Nạp helper chứa các hàm tiện ích
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-helper.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-password-sync.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-department-import.php';
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-xlsx-reader.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-annual-allowance-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-special-work-assignment-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-employee-allowance-report.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-maternity.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-employee-exit-manager.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-employee-exit-return-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-inventory-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-newcomer-inventory-out-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-issue-registration-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-distribution-email.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-uniform-material-import.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-pr-calculator.php';
	require_once UMS_PLUGIN_DIR . 'includes/class-ums-pr-export.php';
	UMS_DB_Approval_Delegation::ensure_schema();
	UMS_DB_Approval_Concurrent_Assignment::ensure_schema();
	UMS_DB_Allocation_Calculation::ensure_schema();
	UMS_Maternity::ensure_schema();
    
    // 3. Kích hoạt phân hệ Admin
    if ( is_admin() ) {
        require_once UMS_PLUGIN_DIR . 'admin/class-ums-admin.php';
        $ums_admin = new UMS_Admin();
        $ums_admin->init();
		require_once UMS_PLUGIN_DIR . 'admin/class-ums-maternity-admin.php';
		UMS_Maternity_Admin::init();
    }

    require_once UMS_PLUGIN_DIR . 'user/class-ums-user.php';
    UMS_User::init();

    // Published only after the local data, business and admin APIs are available.
    define( 'UMS_SHEETS_API_VERSION', '1.0.0' );
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
	$profile_count = 0;
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $profile_table ) ) === $profile_table ) {
		$profile_count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM $profile_table WHERE user_id = %d", $user->ID )
		);
	}

	$is_organization_user = trim( (string) get_user_meta( $user->ID, 'ums_employee_code', true ) ) !== '';
    if ( ( $profile_count > 0 || $is_organization_user ) && (int) $user->user_status > 0 ) {
        return new WP_Error(
            'ums_inactive_account',
            'Tài khoản của bạn đang bị khóa. Vui lòng liên hệ quản trị viên.'
        );
    }

    return $user;
}
add_filter( 'authenticate', 'ums_block_inactive_wp_user', 30, 3 );
