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

require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-installer.php';
register_activation_hook( __FILE__, array( 'UMS_DB_Installer', 'activate' ) );

/**
 * Khởi tạo và nạp các phân hệ chính của hệ thống
 */
function run_tvn_uniform_management() {
    if ( get_option( 'ums_db_version' ) !== UMS_DB_Installer::DB_VERSION ) {
        UMS_DB_Installer::activate();
    }
    
    // 1. Nạp Tầng Database Layer (Theo kiến trúc mô-đun phân tách)
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-base.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-approval-flow.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-department.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-product-category.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory.php';
    require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-user.php';
    // Sau này thêm kho hay phiếu chỉ cần require thêm tại đây:
    // require_once UMS_PLUGIN_DIR . 'includes/db/class-ums-db-inventory.php';
    
    // 2. Nạp helper chứa các hàm tiện ích
    require_once UMS_PLUGIN_DIR . 'includes/class-ums-helper.php';
    
    // 3. Kích hoạt phân hệ Admin
    if ( is_admin() ) {
        require_once UMS_PLUGIN_DIR . 'admin/class-ums-admin.php';
        $ums_admin = new UMS_Admin();
        $ums_admin->init();
    }
}
add_action( 'plugins_loaded', 'run_tvn_uniform_management' );
