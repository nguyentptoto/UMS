<?php
/**
 * Phân hệ điều phối và quản lý giao diện Admin (Controller)
 */
class UMS_Admin {

    /**
     * Khởi chạy các bộ móc (Hooks) của WordPress Admin
     */
    public static function init() {
        // Móc hàm tạo Menu vào hệ thống WordPress
        add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
        
        // Móc hàm nạp các file CSS/JS vào trang Admin
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

        add_action( 'admin_post_ums_save_user_profile', array( __CLASS__, 'handle_save_user_profile' ) );
        add_action( 'admin_post_ums_delete_user_profile', array( __CLASS__, 'handle_delete_user_profile' ) );
        add_action( 'admin_post_ums_save_department', array( __CLASS__, 'handle_save_department' ) );
        add_action( 'admin_post_ums_delete_department', array( __CLASS__, 'handle_delete_department' ) );
        add_action( 'admin_post_ums_import_departments', array( __CLASS__, 'handle_import_departments' ) );
        add_action( 'admin_post_ums_download_department_import_template', array( __CLASS__, 'handle_download_department_import_template' ) );
        add_action( 'admin_post_ums_save_position', array( __CLASS__, 'handle_save_position' ) );
        add_action( 'admin_post_ums_delete_position', array( __CLASS__, 'handle_delete_position' ) );
        add_action( 'admin_post_ums_save_factory_location', array( __CLASS__, 'handle_save_factory_location' ) );
        add_action( 'admin_post_ums_delete_factory_location', array( __CLASS__, 'handle_delete_factory_location' ) );
        add_action( 'admin_post_ums_save_contract_type', array( __CLASS__, 'handle_save_contract_type' ) );
        add_action( 'admin_post_ums_delete_contract_type', array( __CLASS__, 'handle_delete_contract_type' ) );
        add_action( 'admin_post_ums_save_approval_flow', array( __CLASS__, 'handle_save_approval_flow' ) );
        add_action( 'admin_post_ums_delete_approval_flow', array( __CLASS__, 'handle_delete_approval_flow' ) );
        add_action( 'admin_post_ums_save_product_category', array( __CLASS__, 'handle_save_product_category' ) );
        add_action( 'admin_post_ums_delete_product_category', array( __CLASS__, 'handle_delete_product_category' ) );
        add_action( 'admin_post_ums_save_inventory_item', array( __CLASS__, 'handle_save_inventory_item' ) );
        add_action( 'admin_post_ums_delete_inventory_item', array( __CLASS__, 'handle_delete_inventory_item' ) );
        add_action( 'admin_post_ums_manual_inventory_out', array( __CLASS__, 'handle_manual_inventory_out' ) );
		add_action( 'admin_post_ums_download_inventory_import_template', array( __CLASS__, 'handle_download_inventory_import_template' ) );
		add_action( 'admin_post_ums_preview_inventory_import', array( __CLASS__, 'handle_preview_inventory_import' ) );
		add_action( 'admin_post_ums_confirm_inventory_import', array( __CLASS__, 'handle_confirm_inventory_import' ) );
        add_action( 'admin_post_ums_save_annual_allowance', array( __CLASS__, 'handle_save_annual_allowance' ) );
        add_action( 'admin_post_ums_delete_annual_allowance', array( __CLASS__, 'handle_delete_annual_allowance' ) );
        add_action( 'admin_post_ums_preview_annual_allowance_import', array( __CLASS__, 'handle_preview_annual_allowance_import' ) );
        add_action( 'admin_post_ums_confirm_annual_allowance_import', array( __CLASS__, 'handle_confirm_annual_allowance_import' ) );
        add_action( 'admin_post_ums_sync_organization', array( __CLASS__, 'handle_sync_organization' ) );
        add_action( 'admin_post_ums_save_sheet_sync_settings', array( __CLASS__, 'handle_save_sheet_sync_settings' ) );
        add_action( 'wp_ajax_ums_sync_user_password', array( __CLASS__, 'handle_sync_user_password' ) );
        add_action( 'wp_ajax_ums_get_organization_employees', array( __CLASS__, 'handle_get_organization_employees' ) );
    }

    /**
     * Tạo Menu "Quản lý Đồng phục" trên thanh công cụ Admin Sidebar
     */
    public static function add_admin_menu() {
        add_menu_page(
            'Quản lý Đồng phục UMS',          // Tiêu đề trang (Page Title)
            'Quản lý Đồng phục',              // Tên hiển thị trên Menu (Menu Title)
            'manage_options',                 // Quyền hạn bắt buộc (Chỉ Admin mới thấy)
            'tvn-uniform-management',         // Mã định danh Menu (Slug)
            array( __CLASS__, 'render_user_list_page' ), // Hàm gọi hiển thị giao diện
            'dashicons-businessman',          // Biểu tượng Menu (Icon áo vest nhân sự)
            30                                // Vị trí xuất hiện trên Sidebar
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Hồ sơ Nhân sự',
            'Hồ sơ Nhân sự',
            'manage_options',
            'tvn-uniform-management',
            array( __CLASS__, 'render_user_list_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Quản lý Phòng ban',
            'Phòng ban',
            'manage_options',
            'tvn-ums-departments',
            array( __CLASS__, 'render_department_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Quản lý Chức danh',
            'Chức danh',
            'manage_options',
            'tvn-ums-positions',
            array( __CLASS__, 'render_position_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Quản lý Nhà máy',
            'Nhà máy',
            'manage_options',
            'tvn-ums-factory-locations',
            array( __CLASS__, 'render_factory_location_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Quản lý Hợp đồng',
            'Hợp đồng',
            'manage_options',
            'tvn-ums-contract-types',
            array( __CLASS__, 'render_contract_type_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Quản lý Luồng duyệt',
            'Luồng duyệt',
            'manage_options',
            'tvn-ums-approval-flows',
            array( __CLASS__, 'render_approval_flow_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Quản lý Sản phẩm & Tổng kho',
            'Sản phẩm & Kho',
            'manage_options',
            'tvn-ums-inventory',
            array( __CLASS__, 'render_inventory_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Quản lý Danh mục Sản phẩm',
            'Danh mục SP',
            'manage_options',
            'tvn-ums-product-categories',
            array( __CLASS__, 'render_product_category_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Lịch sử nhập xuất kho',
            'Lịch sử kho',
            'manage_options',
            'tvn-ums-inventory-movements',
            array( __CLASS__, 'render_inventory_movement_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Định mức cấp phát hàng năm',
            'Định mức năm',
            'manage_options',
            'tvn-ums-annual-allowances',
            array( __CLASS__, 'render_annual_allowance_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Sơ đồ tổ chức TVN',
            'Sơ đồ tổ chức TVN',
            'manage_options',
            'tvn-ums-organization',
            array( __CLASS__, 'render_organization_page' )
        );

        add_submenu_page(
            'tvn-uniform-management',
            'Đồng bộ Google Sheet',
            'Đồng bộ Sheet',
            'manage_options',
            'tvn-ums-sheet-sync',
            array( __CLASS__, 'render_sheet_sync_page' )
        );
    }

    /**
     * Nạp các file CSS và Javascript bổ trợ cho giao diện Admin
     */
    public static function enqueue_admin_assets( $hook ) {
        // Chỉ nạp CSS/JS khi Admin đang đứng đúng trong trang của plugin UMS
        if ( strpos( $hook, 'tvn-uniform-management' ) === false && strpos( $hook, 'tvn-ums-departments' ) === false && strpos( $hook, 'tvn-ums-positions' ) === false && strpos( $hook, 'tvn-ums-factory-locations' ) === false && strpos( $hook, 'tvn-ums-contract-types' ) === false && strpos( $hook, 'tvn-ums-approval-flows' ) === false && strpos( $hook, 'tvn-ums-inventory' ) === false && strpos( $hook, 'tvn-ums-product-categories' ) === false && strpos( $hook, 'tvn-ums-inventory-movements' ) === false && strpos( $hook, 'tvn-ums-annual-allowances' ) === false && strpos( $hook, 'tvn-ums-organization' ) === false && strpos( $hook, 'tvn-ums-sheet-sync' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'ums-jqx-base-css',
            UMS_PLUGIN_URL . 'assets/css/jqx.base.ums.css',
            array(),
            '1.0.0'
        );

        // Nạp file CSS riêng sau jqx để override icon/theme khi cần.
        wp_enqueue_style( 
            'ums-admin-css', 
            UMS_PLUGIN_URL . 'admin/css/ums-admin.css', 
            array( 'ums-jqx-energyblue-css' ), 
            '1.1.0'
        );

        wp_enqueue_style(
            'ums-jqx-energyblue-css',
            UMS_PLUGIN_URL . 'assets/css/jqx.energyblue.css',
            array( 'ums-jqx-base-css' ),
            '1.0.0'
        );

        wp_enqueue_script(
            'ums-jqx-all',
            UMS_PLUGIN_URL . 'assets/js/jqx-all.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_add_inline_script(
            'jquery',
            'window.$ = window.jQuery;',
            'after'
        );

        // Nạp file Javascript
        wp_enqueue_script( 
            'ums-admin-js', 
            UMS_PLUGIN_URL . 'admin/js/ums-admin.js', 
            array( 'jquery', 'ums-jqx-all' ),
            '1.3.0',
            true 
        );

        wp_localize_script(
            'ums-admin-js',
            'umsAdmin',
            array(
                'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
                'passwordSyncNonce' => wp_create_nonce( 'ums_sync_user_password' ),
            )
        );
    }

    /**
     * Hàm gọi file Giao diện (View) danh sách nhân sự
     */
    public static function render_user_list_page() {
        $filters = array(
            'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'department' => isset( $_GET['department'] ) ? sanitize_text_field( wp_unslash( $_GET['department'] ) ) : '',
            'status'     => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_profile_id = isset( $_GET['edit_profile_id'] ) ? absint( $_GET['edit_profile_id'] ) : 0;
        $editing_user    = $edit_profile_id ? UMS_DB_User::get_by_id( $edit_profile_id ) : null;
        $departments  = UMS_DB_User::get_departments();
        $department_options = UMS_DB_Department::get_active();
        $position_options = UMS_DB_Position::get_active();
        $factory_location_options = UMS_DB_Factory_Location::get_active();
        $contract_type_options = UMS_DB_Contract_Type::get_active();
        $users        = UMS_DB_User::get_all( $filters );
        $notice       = self::get_notice();
        $form_values  = self::get_default_profile_values( $editing_user );

        // Nạp file giao diện HTML
        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-user-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-user-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-user-list.php</p></div>';
        }
    }

    /**
     * Hàm gọi file giao diện quản lý phòng ban.
     */
    public static function render_department_page() {
        $filters = array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'group'  => isset( $_GET['group'] ) ? sanitize_text_field( wp_unslash( $_GET['group'] ) ) : '',
            'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_department_id = isset( $_GET['edit_department_id'] ) ? absint( $_GET['edit_department_id'] ) : 0;
        $editing_department = $edit_department_id ? UMS_DB_Department::get_by_id( $edit_department_id ) : null;
        $departments        = UMS_DB_Department::get_all( $filters );
        $department_groups  = UMS_DB_Department::get_groups();
        $notice             = self::get_notice();
        $form_values        = self::get_default_department_values( $editing_department );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-department-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-department-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-department-list.php</p></div>';
        }
    }

    /**
     * Hàm gọi file giao diện quản lý chức danh.
     */
    public static function render_position_page() {
        $filters = array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_position_id = isset( $_GET['edit_position_id'] ) ? absint( $_GET['edit_position_id'] ) : 0;
        $editing_position = $edit_position_id ? UMS_DB_Position::get_by_id( $edit_position_id ) : null;
        $positions        = UMS_DB_Position::get_all( $filters );
        $notice           = self::get_notice();
        $form_values      = self::get_default_position_values( $editing_position );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-position-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-position-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-position-list.php</p></div>';
        }
    }

    public static function render_factory_location_page() {
        $filters = array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_factory_location_id = isset( $_GET['edit_factory_location_id'] ) ? absint( $_GET['edit_factory_location_id'] ) : 0;
        $editing_factory_location = $edit_factory_location_id ? UMS_DB_Factory_Location::get_by_id( $edit_factory_location_id ) : null;
        $factory_locations        = UMS_DB_Factory_Location::get_all( $filters );
        $notice                   = self::get_notice();
        $form_values              = self::get_default_factory_location_values( $editing_factory_location );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-factory-location-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-factory-location-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-factory-location-list.php</p></div>';
        }
    }

    public static function render_contract_type_page() {
        $filters = array(
            'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_contract_type_id = isset( $_GET['edit_contract_type_id'] ) ? absint( $_GET['edit_contract_type_id'] ) : 0;
        $editing_contract_type = $edit_contract_type_id ? UMS_DB_Contract_Type::get_by_id( $edit_contract_type_id ) : null;
        $contract_types        = UMS_DB_Contract_Type::get_all( $filters );
        $notice                = self::get_notice();
        $form_values           = self::get_default_contract_type_values( $editing_contract_type );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-contract-type-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-contract-type-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-contract-type-list.php</p></div>';
        }
    }

    /**
     * Hàm gọi file giao diện quản lý chuỗi luồng duyệt theo phòng ban.
     */
    public static function render_approval_flow_page() {
        $filters = array(
            'department_id' => isset( $_GET['department_id'] ) ? sanitize_text_field( wp_unslash( $_GET['department_id'] ) ) : '',
            'status'        => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_flow_id   = isset( $_GET['edit_flow_id'] ) ? absint( $_GET['edit_flow_id'] ) : 0;
        $editing_flow   = $edit_flow_id ? UMS_DB_Approval_Flow::get_by_id( $edit_flow_id ) : null;
        $approval_flows = UMS_DB_Approval_Flow::get_all( $filters );
        $departments    = UMS_DB_Department::get_active();
        $approvers      = UMS_DB_User::get_all( array( 'status' => 'active' ) );
        $notice         = self::get_notice();
        $form_values    = self::get_default_approval_flow_values( $editing_flow );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-approval-flow-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-approval-flow-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-approval-flow-list.php</p></div>';
        }
    }

    /**
     * Hàm gọi file giao diện quản lý sản phẩm và tổng kho.
     */
    public static function render_inventory_page() {
        $filters = array(
            'search'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'parent_id'   => isset( $_GET['parent_id'] ) ? sanitize_text_field( wp_unslash( $_GET['parent_id'] ) ) : '',
            'category_id' => '',
            'stock'       => isset( $_GET['stock'] ) ? sanitize_key( wp_unslash( $_GET['stock'] ) ) : '',
        );

        $edit_item_id   = isset( $_GET['edit_item_id'] ) ? absint( $_GET['edit_item_id'] ) : 0;
        $editing_item   = $edit_item_id ? UMS_DB_Inventory::get_by_id( $edit_item_id ) : null;
        $inventory      = UMS_DB_Inventory::get_all( $filters );
        $category_tree  = UMS_DB_Product_Category::get_tree();
        $child_categories = UMS_DB_Product_Category::get_child_categories();
        $notice            = self::get_notice();
        $form_values       = self::get_default_inventory_values( $editing_item );
        $available_items   = UMS_DB_Inventory::get_all( array( 'stock' => 'available' ) );
        $recipient_options = UMS_DB_Organization::get_recipient_options();
		$inventory_import_ready = UMS_DB_Inventory_Import::is_ready();
		$inventory_preview_token = isset( $_GET['inventory_preview_token'] ) ? sanitize_key( wp_unslash( $_GET['inventory_preview_token'] ) ) : '';
		$inventory_import_preview = $inventory_preview_token !== '' ? UMS_Inventory_Import::get_preview( $inventory_preview_token ) : null;

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-inventory-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-inventory-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-inventory-list.php</p></div>';
        }
    }

    public static function render_inventory_movement_page() {
        $filters = array(
            'search'        => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'movement_type' => isset( $_GET['movement_type'] ) ? sanitize_key( wp_unslash( $_GET['movement_type'] ) ) : '',
            'date_from'     => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
            'date_to'       => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
        );

        $movements = UMS_DB_Inventory_Movement::get_all( $filters );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-inventory-movement-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-inventory-movement-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-inventory-movement-list.php</p></div>';
        }
    }

    public static function render_annual_allowance_page() {
        $filters = array(
            'search'      => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'status'      => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_rule_id  = isset( $_GET['edit_rule_id'] ) ? absint( $_GET['edit_rule_id'] ) : 0;
        $editing_rule  = $edit_rule_id ? UMS_DB_Annual_Allowance::get_by_id( $edit_rule_id ) : null;
        $rules         = UMS_DB_Annual_Allowance::get_all( array_merge( $filters, array( 'limit' => 5000 ) ) );
        $notice        = self::get_notice();
        $form_values   = self::get_default_annual_allowance_values( $editing_rule );
        $preview_token = isset( $_GET['preview_token'] ) ? sanitize_key( wp_unslash( $_GET['preview_token'] ) ) : '';
        $import_preview = $preview_token !== '' ? UMS_Annual_Allowance_Import::get_preview( $preview_token ) : null;
        $product_groups = UMS_DB_Inventory::get_product_groups();
        $allowance_product_columns = UMS_Annual_Allowance_Import::get_product_columns();
        $organization_ready = UMS_DB_Organization::table_exists();
        $organization_departments = $organization_ready ? UMS_DB_Organization::get_distinct_values( 'department' ) : array();
        $organization_teams = $organization_ready ? UMS_DB_Organization::get_distinct_values( 'team' ) : array();
        $organization_cost_centers = $organization_ready ? UMS_DB_Organization::get_distinct_values( 'cost_center' ) : array();
        $organization_positions = $organization_ready ? UMS_DB_Organization::get_distinct_values( 'position' ) : array();
        $allowance_import_ready = UMS_DB_Annual_Allowance::is_import_ready();

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-annual-allowance-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-annual-allowance-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-annual-allowance-list.php</p></div>';
        }
    }

    /**
     * Hiển thị danh sách nhân sự thuộc sơ đồ tổ chức đã đồng bộ về UMS.
     */
    public static function render_organization_page() {
        $filters = array(
            'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'division'   => isset( $_GET['division'] ) ? sanitize_text_field( wp_unslash( $_GET['division'] ) ) : '',
            'department' => isset( $_GET['department'] ) ? sanitize_text_field( wp_unslash( $_GET['department'] ) ) : '',
            'factory'    => isset( $_GET['factory'] ) ? sanitize_text_field( wp_unslash( $_GET['factory'] ) ) : '',
        );
        $table_ready     = UMS_DB_Organization::table_exists();
        $total_employees = $table_ready ? UMS_DB_Organization::get_count() : 0;
        $last_synced_at  = $table_ready ? UMS_DB_Organization::get_last_synced_at() : null;
        $cron_result     = get_option( UMS_Organization_Sync::CRON_RESULT_OPTION, array() );
        $divisions       = $table_ready ? UMS_DB_Organization::get_distinct_values( 'division' ) : array();
        $departments     = $table_ready ? UMS_DB_Organization::get_distinct_values( 'department' ) : array();
        $factories       = $table_ready ? UMS_DB_Organization::get_distinct_values( 'factory' ) : array();
        $apps_script_url = (string) get_option( 'ums_sheet_sync_apps_script_url', '' );
        $rest_endpoint   = rest_url( UMS_Organization_Sync::REST_NAMESPACE . UMS_Organization_Sync::REST_ROUTE );
        $sync_token      = UMS_Sheet_User_Sync::get_sync_token();
        $notice          = self::get_notice();

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-organization-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-organization-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-organization-list.php</p></div>';
        }
    }

    /**
     * Trả dữ liệu phân trang cho jqxGrid của sơ đồ tổ chức.
     */
    public static function render_sheet_sync_page() {
        $apps_script_url = (string) get_option( 'ums_sheet_sync_apps_script_url', '' );
        $rest_endpoint   = rest_url( UMS_Organization_Sync::REST_NAMESPACE . UMS_Organization_Sync::REST_ROUTE );
        $sync_token      = UMS_Sheet_User_Sync::get_sync_token();
        $bridge_url      = UMS_Auto_Sync_Bridge::get_bridge_url();
        $last_log        = UMS_Sheet_User_Sync::get_last_log();
        $notice          = self::get_notice();

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-sheet-sync.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-sheet-sync.php';
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

    public static function handle_get_organization_employees() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json( array( 'rows' => array(), 'total' => 0, 'message' => 'Bạn không có quyền xem dữ liệu này.' ), 403 );
        }

        check_ajax_referer( 'ums_get_organization_employees', 'security' );

        if ( ! UMS_DB_Organization::table_exists() ) {
            wp_send_json( array( 'rows' => array(), 'total' => 0, 'message' => 'Chưa có bảng dữ liệu sơ đồ tổ chức.' ), 500 );
        }

        $page_index = isset( $_POST['pagenum'] ) ? absint( $_POST['pagenum'] ) : 0;
        $per_page   = isset( $_POST['pagesize'] ) ? absint( $_POST['pagesize'] ) : 20;
        $args = array(
            'search'     => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
            'division'   => isset( $_POST['division'] ) ? sanitize_text_field( wp_unslash( $_POST['division'] ) ) : '',
            'department' => isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : '',
            'factory'    => isset( $_POST['factory'] ) ? sanitize_text_field( wp_unslash( $_POST['factory'] ) ) : '',
            'page'       => $page_index + 1,
            'per_page'   => $per_page,
            'orderby'    => isset( $_POST['sortdatafield'] ) ? sanitize_key( wp_unslash( $_POST['sortdatafield'] ) ) : 'employee_no',
            'order'      => isset( $_POST['sortorder'] ) ? sanitize_key( wp_unslash( $_POST['sortorder'] ) ) : 'asc',
        );

        wp_send_json(
            array(
                'rows'  => UMS_DB_Organization::get_page( $args ),
                'total' => UMS_DB_Organization::get_count( $args ),
            )
        );
    }

    /**
     * Handler cũ được giữ để tương thích URL cũ; dữ liệu tổ chức nay đồng bộ từ Google Sheet.
     */
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
                    'Đã nhận %s nhân sự từ version %s; loại bỏ %s bản ghi không còn ở snapshot hiện hành.',
                    number_format_i18n( $result['total'] ),
                    number_format_i18n( $result['source_version'] ),
                    number_format_i18n( $result['deleted'] )
                ),
            )
        );
    }

    /**
     * Hàm gọi file giao diện quản lý danh mục sản phẩm.
     */
    public static function render_product_category_page() {
        $parent_categories = UMS_DB_Product_Category::get_parent_categories();
        $filters = array(
            'search'    => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
            'parent_id' => isset( $_GET['parent_id'] ) ? sanitize_text_field( wp_unslash( $_GET['parent_id'] ) ) : '',
            'status'    => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_category_id = isset( $_GET['edit_category_id'] ) ? absint( $_GET['edit_category_id'] ) : 0;
        $editing_category = $edit_category_id ? UMS_DB_Product_Category::get_by_id( $edit_category_id ) : null;

        if ( $editing_category && $filters['parent_id'] === '' ) {
            $filters['parent_id'] = ! empty( $editing_category['parent_id'] ) ? (string) $editing_category['parent_id'] : (string) $editing_category['category_id'];
        }

        $categories       = UMS_DB_Product_Category::get_all( $filters );
        $notice           = self::get_notice();
        $form_values      = self::get_default_product_category_values( $editing_category );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-product-category-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-product-category-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-product-category-list.php</p></div>';
        }
    }

    /**
     * Lưu hồ sơ nhân sự từ màn hình Admin.
     */
    public static function handle_save_user_profile() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_user_profile' );

        $raw     = isset( $_POST['ums_profile'] ) && is_array( $_POST['ums_profile'] ) ? wp_unslash( $_POST['ums_profile'] ) : array();
        $data    = self::sanitize_profile_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_profile_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_profiles( array(
                'notice'       => 'validation_error',
                'notice_extra' => implode( ' ', $errors ),
                'edit_profile_id' => $is_edit ? $data['profile_id'] : null,
            ) );
        }

        $profile_id = $data['profile_id'];
        unset( $data['profile_id'] );

        $account_status = $data['account_status'];
        unset( $data['account_status'] );

        $existing_profile = $is_edit ? UMS_DB_User::get_by_id( $profile_id ) : null;
        if ( $is_edit && ! $existing_profile ) {
            self::redirect_to_profiles( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Không tìm thấy hồ sơ nhân sự cần cập nhật.',
            ) );
        }

        $wp_user_id = self::ensure_wp_user_for_profile( $data, $account_status, $existing_profile, ! empty( $raw['reset_password'] ) );
        if ( is_wp_error( $wp_user_id ) ) {
            self::redirect_to_profiles( array(
                'notice'       => 'validation_error',
                'notice_extra' => $wp_user_id->get_error_message(),
                'edit_profile_id' => $is_edit ? $profile_id : null,
            ) );
        }
        $data['user_id'] = (int) $wp_user_id;

        $result = $is_edit
            ? UMS_DB_User::update( $profile_id, $data )
            : UMS_DB_User::insert( $data );

        if ( $result === false ) {
            self::redirect_to_profiles( array(
                'notice'       => 'db_error',
                'notice_extra' => UMS_DB_User::get_last_error(),
                'edit_profile_id' => $is_edit ? $profile_id : null,
            ) );
        }

        self::redirect_to_profiles( array( 'notice' => $is_edit ? 'updated' : 'created' ) );
    }

    /**
     * Xóa hồ sơ nhân sự.
     */
    public static function handle_delete_user_profile() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $profile_id = isset( $_GET['profile_id'] ) ? absint( $_GET['profile_id'] ) : 0;
        check_admin_referer( 'ums_delete_user_profile_' . $profile_id );

        if ( $profile_id <= 0 ) {
            self::redirect_to_profiles( array( 'notice' => 'invalid_user' ) );
        }

        $result = UMS_DB_User::delete( $profile_id );
        self::redirect_to_profiles( array( 'notice' => $result === false ? 'db_error' : 'deleted' ) );
    }

    /**
     * Lưu danh mục phòng ban.
     */
    public static function handle_sync_user_password() {
        if ( ! current_user_can( 'promote_users' ) ) {
            wp_send_json_error( array( 'message' => 'Bạn không có quyền đồng bộ mật khẩu người dùng.' ), 403 );
        }

        check_ajax_referer( 'ums_sync_user_password', 'security' );

        $user_ids = array();
        if ( isset( $_POST['user_ids'] ) && is_array( $_POST['user_ids'] ) ) {
            $user_ids = array_map( 'absint', wp_unslash( $_POST['user_ids'] ) );
        } elseif ( isset( $_POST['user_id'] ) ) {
            $user_ids = array( absint( $_POST['user_id'] ) );
        }
        $user_ids = array_values( array_unique( array_filter( $user_ids ) ) );

        if ( empty( $user_ids ) ) {
            wp_send_json_error( array( 'message' => 'Không có tài khoản WordPress nào để đồng bộ.' ), 400 );
        }

        $summary = array(
            'external' => 0,
            'default'  => 0,
            'failed'   => 0,
            'messages' => array(),
        );

        foreach ( $user_ids as $user_id ) {
            $result = UMS_Password_Sync::sync_user_password_with_default_fallback( $user_id );

            if ( is_wp_error( $result ) ) {
                $summary['failed']++;
                $summary['messages'][] = $result->get_error_message();
                continue;
            }

            if ( isset( $result['source'] ) && $result['source'] === 'default' ) {
                $summary['default']++;
            } else {
                $summary['external']++;
            }
        }

        if ( $summary['failed'] > 0 && $summary['external'] === 0 && $summary['default'] === 0 ) {
            wp_send_json_error(
                array(
                    'message' => implode( ' ', array_unique( $summary['messages'] ) ),
                    'summary' => $summary,
                ),
                400
            );
        }

        wp_send_json_success(
            array(
                'message' => sprintf(
                    'Đã xử lý %d tài khoản. Đồng bộ nguồn: %d. Mật khẩu mặc định: %d. Lỗi: %d.',
                    count( $user_ids ),
                    $summary['external'],
                    $summary['default'],
                    $summary['failed']
                ),
                'summary' => $summary,
            )
        );
    }

    public static function handle_save_department() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_department' );

        $raw     = isset( $_POST['ums_department'] ) && is_array( $_POST['ums_department'] ) ? wp_unslash( $_POST['ums_department'] ) : array();
        $data    = self::sanitize_department_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_department_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_departments( array(
                'notice'             => 'validation_error',
                'notice_extra'       => implode( ' ', $errors ),
                'edit_department_id' => $is_edit ? $data['department_id'] : null,
            ) );
        }

        $department_id  = $data['department_id'];
        $old_department = $is_edit ? UMS_DB_Department::get_by_id( $department_id ) : null;
        unset( $data['department_id'] );

        if ( $is_edit && ! $old_department ) {
            self::redirect_to_departments( array( 'notice' => 'invalid_department' ) );
        }

        global $wpdb;
        if ( $is_edit ) {
            $wpdb->query( 'START TRANSACTION' );
        }

        $result = $is_edit
            ? UMS_DB_Department::update( $department_id, $data )
            : UMS_DB_Department::insert( $data );

        if ( $result === false ) {
            if ( $is_edit ) {
                $wpdb->query( 'ROLLBACK' );
            }

            self::redirect_to_departments( array(
                'notice'             => 'db_error',
                'notice_extra'       => UMS_DB_Department::get_last_error(),
                'edit_department_id' => $is_edit ? $department_id : null,
            ) );
        }

        if ( $is_edit && $old_department['department_name'] !== $data['department_name'] ) {
            $sync_result = UMS_DB_User::replace_department_name( $old_department['department_name'], $data['department_name'] );

            if ( $sync_result === false ) {
                $sync_error = $wpdb->last_error;
                $wpdb->query( 'ROLLBACK' );
                self::redirect_to_departments( array(
                    'notice'             => 'db_error',
                    'notice_extra'       => $sync_error,
                    'edit_department_id' => $department_id,
                ) );
            }
        }

        if ( $is_edit ) {
            $wpdb->query( 'COMMIT' );
        }

        self::redirect_to_departments( array( 'notice' => $is_edit ? 'department_updated' : 'department_created' ) );
    }

    /**
     * Import CSV phòng ban và upsert theo mã phòng ban.
     */
    public static function handle_import_departments() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_import_departments' );
        $file   = isset( $_FILES['ums_department_import_file'] ) ? $_FILES['ums_department_import_file'] : array();
        $result = UMS_Department_Import::import_uploaded_file( $file );

        if ( is_wp_error( $result ) ) {
            self::redirect_to_departments(
                array(
                    'notice'       => 'validation_error',
                    'notice_extra' => $result->get_error_message(),
                )
            );
        }

        $summary = sprintf(
            'Đã đọc %d dòng: tạo mới %d, cập nhật %d, không thay đổi %d, lỗi %d.',
            $result['received'],
            $result['created'],
            $result['updated'],
            $result['unchanged'],
            $result['failed']
        );
        if ( ! empty( $result['errors'] ) ) {
            $summary .= ' ' . implode( ' ', array_slice( $result['errors'], 0, 3 ) );
        }

        $has_success = ( $result['created'] + $result['updated'] + $result['unchanged'] ) > 0;
        self::redirect_to_departments(
            array(
                'notice'       => $has_success ? 'department_imported' : 'validation_error',
                'notice_extra' => $summary,
            )
        );
    }

    /**
     * Tải CSV UTF-8 mẫu để tránh sai tiêu đề khi import.
     */
    public static function handle_download_department_import_template() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_download_department_import_template' );
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="ums-department-import-template.csv"' );

        $output = fopen( 'php://output', 'wb' );
        echo "\xEF\xBB\xBF";
        fputcsv( $output, array( 'department_code', 'department_name', 'department_group', 'is_active' ) );
        fputcsv( $output, array( 'it_da', 'Information Technology', 'Khối hỗ trợ', '1' ) );
        fputcsv( $output, array( 'hrga1', 'Hành chính nhân sự Đông Anh', 'Khối hỗ trợ', '1' ) );
        fclose( $output );
        exit;
    }

    /**
     * Xóa danh mục phòng ban.
     */
    public static function handle_delete_department() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $department_id = isset( $_GET['department_id'] ) ? absint( $_GET['department_id'] ) : 0;
        check_admin_referer( 'ums_delete_department_' . $department_id );

        if ( $department_id <= 0 ) {
            self::redirect_to_departments( array( 'notice' => 'invalid_department' ) );
        }

        $department = UMS_DB_Department::get_by_id( $department_id );
        if ( ! $department ) {
            self::redirect_to_departments( array( 'notice' => 'invalid_department' ) );
        }

        if ( UMS_DB_User::get_all( array( 'department' => $department['department_name'] ) ) ) {
            self::redirect_to_departments( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Phòng ban đang có hồ sơ nhân sự, hãy chuyển nhân sự sang phòng ban khác trước khi xóa.',
            ) );
        }

        $result = UMS_DB_Department::delete( $department_id );
        self::redirect_to_departments( array( 'notice' => $result === false ? 'db_error' : 'department_deleted' ) );
    }

    /**
     * Lưu danh mục chức danh.
     */
    public static function handle_save_position() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_position' );

        $raw     = isset( $_POST['ums_position'] ) && is_array( $_POST['ums_position'] ) ? wp_unslash( $_POST['ums_position'] ) : array();
        $data    = self::sanitize_position_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_position_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_positions( array(
                'notice'           => 'validation_error',
                'notice_extra'     => implode( ' ', $errors ),
                'edit_position_id' => $is_edit ? $data['position_id'] : null,
            ) );
        }

        $position_id = $data['position_id'];
        unset( $data['position_id'] );

        $result = $is_edit
            ? UMS_DB_Position::update( $position_id, $data )
            : UMS_DB_Position::insert( $data );

        if ( $result === false ) {
            self::redirect_to_positions( array(
                'notice'           => 'db_error',
                'notice_extra'     => UMS_DB_Position::get_last_error(),
                'edit_position_id' => $is_edit ? $position_id : null,
            ) );
        }

        self::redirect_to_positions( array( 'notice' => $is_edit ? 'position_updated' : 'position_created' ) );
    }

    /**
     * Xóa danh mục chức danh.
     */
    public static function handle_delete_position() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $position_id = isset( $_GET['position_id'] ) ? absint( $_GET['position_id'] ) : 0;
        check_admin_referer( 'ums_delete_position_' . $position_id );

        if ( $position_id <= 0 ) {
            self::redirect_to_positions( array( 'notice' => 'invalid_position' ) );
        }

        $position = UMS_DB_Position::get_by_id( $position_id );
        if ( ! $position ) {
            self::redirect_to_positions( array( 'notice' => 'invalid_position' ) );
        }

        if ( UMS_DB_User::get_all( array( 'position' => $position['position_name'] ) ) ) {
            self::redirect_to_positions( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Chức danh đang có hồ sơ nhân sự, hãy chuyển nhân sự sang chức danh khác trước khi xóa.',
            ) );
        }

        $result = UMS_DB_Position::delete( $position_id );
        self::redirect_to_positions( array( 'notice' => $result === false ? 'db_error' : 'position_deleted' ) );
    }

    public static function handle_save_factory_location() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_factory_location' );

        $raw     = isset( $_POST['ums_factory_location'] ) && is_array( $_POST['ums_factory_location'] ) ? wp_unslash( $_POST['ums_factory_location'] ) : array();
        $data    = self::sanitize_factory_location_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_factory_location_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_factory_locations( array(
                'notice'                   => 'validation_error',
                'notice_extra'             => implode( ' ', $errors ),
                'edit_factory_location_id' => $is_edit ? $data['factory_location_id'] : null,
            ) );
        }

        $factory_location_id = $data['factory_location_id'];
        unset( $data['factory_location_id'] );

        $result = $is_edit
            ? UMS_DB_Factory_Location::update( $factory_location_id, $data )
            : UMS_DB_Factory_Location::insert( $data );

        if ( $result === false ) {
            self::redirect_to_factory_locations( array(
                'notice'                   => 'db_error',
                'notice_extra'             => UMS_DB_Factory_Location::get_last_error(),
                'edit_factory_location_id' => $is_edit ? $factory_location_id : null,
            ) );
        }

        self::redirect_to_factory_locations( array( 'notice' => $is_edit ? 'factory_location_updated' : 'factory_location_created' ) );
    }

    public static function handle_delete_factory_location() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $factory_location_id = isset( $_GET['factory_location_id'] ) ? absint( $_GET['factory_location_id'] ) : 0;
        check_admin_referer( 'ums_delete_factory_location_' . $factory_location_id );

        if ( $factory_location_id <= 0 ) {
            self::redirect_to_factory_locations( array( 'notice' => 'invalid_factory_location' ) );
        }

        $factory_location = UMS_DB_Factory_Location::get_by_id( $factory_location_id );
        if ( ! $factory_location ) {
            self::redirect_to_factory_locations( array( 'notice' => 'invalid_factory_location' ) );
        }

        if ( UMS_DB_User::get_all( array( 'factory_location' => $factory_location['factory_location_name'] ) ) ) {
            self::redirect_to_factory_locations( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Nhà máy đang có hồ sơ nhân sự, hãy chuyển nhân sự sang nhà máy khác trước khi xóa.',
            ) );
        }

        $result = UMS_DB_Factory_Location::delete( $factory_location_id );
        self::redirect_to_factory_locations( array( 'notice' => $result === false ? 'db_error' : 'factory_location_deleted' ) );
    }

    public static function handle_save_contract_type() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_contract_type' );

        $raw     = isset( $_POST['ums_contract_type'] ) && is_array( $_POST['ums_contract_type'] ) ? wp_unslash( $_POST['ums_contract_type'] ) : array();
        $data    = self::sanitize_contract_type_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_contract_type_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_contract_types( array(
                'notice'                => 'validation_error',
                'notice_extra'          => implode( ' ', $errors ),
                'edit_contract_type_id' => $is_edit ? $data['contract_type_id'] : null,
            ) );
        }

        $contract_type_id = $data['contract_type_id'];
        unset( $data['contract_type_id'] );

        $result = $is_edit
            ? UMS_DB_Contract_Type::update( $contract_type_id, $data )
            : UMS_DB_Contract_Type::insert( $data );

        if ( $result === false ) {
            self::redirect_to_contract_types( array(
                'notice'                => 'db_error',
                'notice_extra'          => UMS_DB_Contract_Type::get_last_error(),
                'edit_contract_type_id' => $is_edit ? $contract_type_id : null,
            ) );
        }

        self::redirect_to_contract_types( array( 'notice' => $is_edit ? 'contract_type_updated' : 'contract_type_created' ) );
    }

    public static function handle_delete_contract_type() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $contract_type_id = isset( $_GET['contract_type_id'] ) ? absint( $_GET['contract_type_id'] ) : 0;
        check_admin_referer( 'ums_delete_contract_type_' . $contract_type_id );

        if ( $contract_type_id <= 0 ) {
            self::redirect_to_contract_types( array( 'notice' => 'invalid_contract_type' ) );
        }

        $contract_type = UMS_DB_Contract_Type::get_by_id( $contract_type_id );
        if ( ! $contract_type ) {
            self::redirect_to_contract_types( array( 'notice' => 'invalid_contract_type' ) );
        }

        if ( UMS_DB_User::get_all( array( 'contract_type' => $contract_type['contract_type_name'] ) ) ) {
            self::redirect_to_contract_types( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Loại hợp đồng đang có hồ sơ nhân sự, hãy chuyển nhân sự sang loại hợp đồng khác trước khi xóa.',
            ) );
        }

        $result = UMS_DB_Contract_Type::delete( $contract_type_id );
        self::redirect_to_contract_types( array( 'notice' => $result === false ? 'db_error' : 'contract_type_deleted' ) );
    }

    /**
     * Lưu một bước trong chuỗi luồng duyệt động.
     */
    public static function handle_save_approval_flow() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_approval_flow' );

        $raw     = isset( $_POST['ums_approval_flow'] ) && is_array( $_POST['ums_approval_flow'] ) ? wp_unslash( $_POST['ums_approval_flow'] ) : array();
        $data    = self::sanitize_approval_flow_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_approval_flow_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_approval_flows( array(
                'notice'       => 'validation_error',
                'notice_extra' => implode( ' ', $errors ),
                'edit_flow_id' => $is_edit ? $data['flow_id'] : null,
            ) );
        }

        $flow_id = $data['flow_id'];
        unset( $data['flow_id'] );
        $data['approver_profile_ids'] = wp_json_encode( $data['approver_profile_ids'] );

        $result = $is_edit
            ? UMS_DB_Approval_Flow::update( $flow_id, $data )
            : UMS_DB_Approval_Flow::insert( $data );

        if ( $result === false ) {
            self::redirect_to_approval_flows( array(
                'notice'       => 'db_error',
                'notice_extra' => UMS_DB_Approval_Flow::get_last_error(),
                'edit_flow_id' => $is_edit ? $flow_id : null,
            ) );
        }

        self::redirect_to_approval_flows( array( 'notice' => $is_edit ? 'approval_flow_updated' : 'approval_flow_created' ) );
    }

    /**
     * Xóa một bước khỏi chuỗi luồng duyệt.
     */
    public static function handle_delete_approval_flow() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $flow_id = isset( $_GET['flow_id'] ) ? absint( $_GET['flow_id'] ) : 0;
        check_admin_referer( 'ums_delete_approval_flow_' . $flow_id );

        if ( $flow_id <= 0 ) {
            self::redirect_to_approval_flows( array( 'notice' => 'invalid_approval_flow' ) );
        }

        $result = UMS_DB_Approval_Flow::delete( $flow_id );
        self::redirect_to_approval_flows( array( 'notice' => $result === false ? 'db_error' : 'approval_flow_deleted' ) );
    }

    /**
     * Lưu danh mục sản phẩm cha-con.
     */
    public static function handle_save_product_category() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_product_category' );

        $raw     = isset( $_POST['ums_product_category'] ) && is_array( $_POST['ums_product_category'] ) ? wp_unslash( $_POST['ums_product_category'] ) : array();
        $data    = self::sanitize_product_category_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_product_category_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_product_categories( array(
                'notice'           => 'validation_error',
                'notice_extra'     => implode( ' ', $errors ),
                'edit_category_id' => $is_edit ? $data['category_id'] : null,
            ) );
        }

        $category_id = $data['category_id'];
        unset( $data['category_id'] );

        $result = $is_edit
            ? UMS_DB_Product_Category::update( $category_id, $data )
            : UMS_DB_Product_Category::insert( $data );

        if ( $result === false ) {
            self::redirect_to_product_categories( array(
                'notice'           => 'db_error',
                'notice_extra'     => UMS_DB_Product_Category::get_last_error(),
                'edit_category_id' => $is_edit ? $category_id : null,
            ) );
        }

        self::redirect_to_product_categories( array( 'notice' => $is_edit ? 'product_category_updated' : 'product_category_created' ) );
    }

    /**
     * Xóa danh mục sản phẩm.
     */
    public static function handle_delete_product_category() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $category_id = isset( $_GET['category_id'] ) ? absint( $_GET['category_id'] ) : 0;
        check_admin_referer( 'ums_delete_product_category_' . $category_id );

        if ( $category_id <= 0 ) {
            self::redirect_to_product_categories( array( 'notice' => 'invalid_product_category' ) );
        }

        if ( UMS_DB_Product_Category::has_children( $category_id ) ) {
            self::redirect_to_product_categories( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Danh mục đang có danh mục con, hãy xóa hoặc chuyển danh mục con trước.',
            ) );
        }

        if ( UMS_DB_Inventory::category_has_items( $category_id ) ) {
            self::redirect_to_product_categories( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Danh mục đang được sử dụng trong kho, không thể xóa.',
            ) );
        }

        $result = UMS_DB_Product_Category::delete( $category_id );
        self::redirect_to_product_categories( array( 'notice' => $result === false ? 'db_error' : 'product_category_deleted' ) );
    }

    /**
     * Lưu danh mục sản phẩm và tồn kho.
     */
    public static function handle_save_inventory_item() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_inventory_item' );

        $raw     = isset( $_POST['ums_inventory'] ) && is_array( $_POST['ums_inventory'] ) ? wp_unslash( $_POST['ums_inventory'] ) : array();
        $data    = self::sanitize_inventory_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_inventory_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_inventory( array(
                'notice'       => 'validation_error',
                'notice_extra' => implode( ' ', $errors ),
                'edit_item_id' => $is_edit ? $data['item_id'] : null,
            ) );
        }

        $item_id      = $data['item_id'];
        $old_item     = $is_edit ? UMS_DB_Inventory::get_by_id( $item_id ) : null;
        $old_stock    = $old_item ? (int) $old_item['stock_qty'] : 0;
        unset( $data['item_id'] );

        $result = $is_edit
            ? UMS_DB_Inventory::update( $item_id, $data )
            : UMS_DB_Inventory::insert( $data );

        if ( $result === false ) {
            self::redirect_to_inventory( array(
                'notice'       => 'db_error',
                'notice_extra' => UMS_DB_Inventory::get_last_error(),
                'edit_item_id' => $is_edit ? $item_id : null,
            ) );
        }

        $saved_item_id = $is_edit ? $item_id : UMS_DB_Inventory::get_last_insert_id();
        self::record_inventory_admin_movement( $saved_item_id, $old_stock, (int) $data['stock_qty'], (float) $data['base_price'], $is_edit );

        self::redirect_to_inventory( array( 'notice' => $is_edit ? 'inventory_updated' : 'inventory_created' ) );
    }

    /**
     * Xóa sản phẩm khỏi danh mục/tổng kho.
     */
    public static function handle_delete_inventory_item() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $item_id = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        check_admin_referer( 'ums_delete_inventory_item_' . $item_id );

        if ( $item_id <= 0 ) {
            self::redirect_to_inventory( array( 'notice' => 'invalid_inventory_item' ) );
        }

        $result = UMS_DB_Inventory::delete( $item_id );
        self::redirect_to_inventory( array( 'notice' => $result === false ? 'db_error' : 'inventory_deleted' ) );
    }

    public static function handle_save_annual_allowance() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_save_annual_allowance' );

        $raw     = isset( $_POST['ums_annual_allowance'] ) && is_array( $_POST['ums_annual_allowance'] ) ? wp_unslash( $_POST['ums_annual_allowance'] ) : array();
        if ( isset( $raw['product_rules'] ) && is_array( $raw['product_rules'] ) ) {
            $matrix_result = self::save_annual_allowance_matrix( $raw );
            if ( ! empty( $matrix_result['errors'] ) ) {
                self::redirect_to_annual_allowances( array(
                    'notice'       => 'validation_error',
                    'notice_extra' => implode( ' ', $matrix_result['errors'] ),
                ) );
            }
            self::redirect_to_annual_allowances( array( 'notice' => 'annual_allowance_created' ) );
        }

        $data    = self::sanitize_annual_allowance_data( $raw );
        $is_edit = ! empty( $raw['is_edit'] );
        $errors  = self::validate_annual_allowance_data( $data, $is_edit );

        if ( ! empty( $errors ) ) {
            self::redirect_to_annual_allowances( array(
                'notice'       => 'validation_error',
                'notice_extra' => implode( ' ', $errors ),
                'edit_rule_id' => $is_edit ? $data['rule_id'] : null,
            ) );
        }

        $rule_id = $data['rule_id'];
        unset( $data['rule_id'] );

        $result = $is_edit
            ? UMS_DB_Annual_Allowance::update( $rule_id, $data )
            : UMS_DB_Annual_Allowance::insert( $data );

        if ( $result === false ) {
            self::redirect_to_annual_allowances( array(
                'notice'       => 'db_error',
                'notice_extra' => UMS_DB_Annual_Allowance::get_last_error(),
                'edit_rule_id' => $is_edit ? $rule_id : null,
            ) );
        }

        self::redirect_to_annual_allowances( array( 'notice' => $is_edit ? 'annual_allowance_updated' : 'annual_allowance_created' ) );
    }

    /**
     * Lưu đồng thời 25 cột sản phẩm của ma trận Phát T4/Phát T9.
     */
    private static function save_annual_allowance_matrix( $raw ) {
        $department   = isset( $raw['department'] ) ? sanitize_text_field( $raw['department'] ) : '';
        $team         = isset( $raw['team'] ) ? sanitize_text_field( $raw['team'] ) : '';
        $cost_center  = isset( $raw['cost_center'] ) ? sanitize_text_field( $raw['cost_center'] ) : '';
        $position     = isset( $raw['position_code'] ) ? UMS_DB_Annual_Allowance::normalize_position_code( sanitize_text_field( $raw['position_code'] ) ) : '';
        $note         = isset( $raw['eligibility_note'] ) ? sanitize_text_field( $raw['eligibility_note'] ) : '';
        $is_active    = ! empty( $raw['is_active'] ) ? 1 : 0;
        $product_data = isset( $raw['product_rules'] ) && is_array( $raw['product_rules'] ) ? $raw['product_rules'] : array();
        $errors       = array();
        $rules        = array();

        if ( $department === '' ) {
            $errors[] = 'Vui lòng chọn Bộ phận.';
        }
        if ( $position === '' ) {
            $errors[] = 'Vui lòng chọn Vị trí.';
        }

        foreach ( UMS_Annual_Allowance_Import::get_product_columns() as $product_name ) {
            $product_key = hash( 'sha256', $product_name );
            $row         = isset( $product_data[ $product_key ] ) && is_array( $product_data[ $product_key ] ) ? $product_data[ $product_key ] : array();
            $april_qty   = isset( $row[4] ) ? max( 0, absint( $row[4] ) ) : 0;
            $september_qty = isset( $row[9] ) ? max( 0, absint( $row[9] ) ) : 0;

            if ( $april_qty === 0 && $september_qty === 0 ) {
                continue;
            }

            $mapping = isset( $row['mapping'] ) ? explode( '|', sanitize_text_field( $row['mapping'] ), 2 ) : array();
            $category_id = isset( $mapping[0] ) ? absint( $mapping[0] ) : 0;
            $item_variant = isset( $mapping[1] ) ? sanitize_text_field( $mapping[1] ) : '';
            if ( $category_id <= 0 || $item_variant === '' || ! UMS_DB_Inventory::product_group_exists( $category_id, $item_variant ) ) {
                $errors[] = 'Chưa ánh xạ sản phẩm: ' . $product_name . '.';
                continue;
            }

            $monthly_quantities    = array_fill( 1, 12, 0 );
            $monthly_quantities[4] = $april_qty;
            $monthly_quantities[9] = $september_qty;
            $rule = array(
                'rule_scope' => 'annual', 'apply_type' => 'product', 'category_id' => $category_id,
                'item_id' => 0, 'item_variant' => $item_variant, 'source_product_name' => $product_name,
                'target_type' => 'organization', 'position_id' => 0, 'department' => $department,
                'team' => $team, 'cost_center' => $cost_center, 'position_code' => $position,
                'employment_start_md' => '', 'employment_end_md' => '', 'eligibility_note' => $note,
                'frequency_count' => count( array_filter( array( $april_qty, $september_qty ) ) ),
                'frequency_years' => 1, 'monthly_quantities' => wp_json_encode( $monthly_quantities ),
                'priority' => 100, 'is_active' => $is_active,
            );
            $rule['rule_key'] = UMS_DB_Annual_Allowance::build_rule_key( $rule );
            $rules[] = $rule;
        }

        if ( empty( $rules ) ) {
            $errors[] = 'Vui lòng nhập số lượng Tháng 4 hoặc Tháng 9 cho ít nhất một sản phẩm.';
        }
        if ( ! empty( $errors ) ) {
            return array( 'errors' => array_unique( $errors ) );
        }

        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );
        foreach ( $rules as $rule ) {
            $saved = UMS_DB_Annual_Allowance::upsert_import_rule( $rule );
            if ( false === $saved['result'] ) {
                $errors[] = UMS_DB_Annual_Allowance::get_last_error();
                break;
            }
        }
        $wpdb->query( empty( $errors ) ? 'COMMIT' : 'ROLLBACK' );

        return array( 'errors' => array_filter( $errors ) );
    }

    public static function handle_delete_annual_allowance() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        $rule_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;
        check_admin_referer( 'ums_delete_annual_allowance_' . $rule_id );

        if ( $rule_id <= 0 ) {
            self::redirect_to_annual_allowances( array( 'notice' => 'invalid_annual_allowance' ) );
        }

        $result = UMS_DB_Annual_Allowance::delete( $rule_id );
        self::redirect_to_annual_allowances( array( 'notice' => $result === false ? 'db_error' : 'annual_allowance_deleted' ) );
    }

	public static function handle_download_inventory_import_template() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
		}

		check_admin_referer( 'ums_download_inventory_import_template' );

		try {
			UMS_Inventory_Import::stream_template( UMS_DB_Inventory::get_all() );
		} catch ( Throwable $error ) {
			self::redirect_to_inventory(
				array( 'notice' => 'inventory_import_invalid_file', 'notice_extra' => $error->getMessage() )
			);
		}
	}

	public static function handle_preview_inventory_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
		}

		check_admin_referer( 'ums_preview_inventory_import' );
		if ( ! UMS_DB_Inventory_Import::is_ready() ) {
			self::redirect_to_inventory( array( 'notice' => 'inventory_import_schema_missing' ) );
		}

		$file = isset( $_FILES['ums_inventory_import_file'] ) ? $_FILES['ums_inventory_import_file'] : array();
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] )
			|| (int) $file['size'] > 10 * MB_IN_BYTES
			|| strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'xlsx' ) {
			self::redirect_to_inventory( array( 'notice' => 'inventory_import_invalid_file' ) );
		}

		try {
			$preview = UMS_Inventory_Import::analyze( $file['tmp_name'], $file['name'] );
			$token   = UMS_Inventory_Import::store_preview( $preview );
			self::redirect_to_inventory(
				array(
					'notice' => empty( $preview['errors'] ) ? 'inventory_import_preview_ready' : 'inventory_import_preview_warning',
					'inventory_preview_token' => $token,
				)
			);
		} catch ( Throwable $error ) {
			self::redirect_to_inventory(
				array( 'notice' => 'inventory_import_invalid_file', 'notice_extra' => $error->getMessage() )
			);
		}
	}

	public static function handle_confirm_inventory_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
		}

		check_admin_referer( 'ums_confirm_inventory_import' );
		$token   = isset( $_POST['inventory_preview_token'] ) ? sanitize_key( wp_unslash( $_POST['inventory_preview_token'] ) ) : '';
		$preview = UMS_Inventory_Import::get_preview( $token );
		if ( ! is_array( $preview ) ) {
			self::redirect_to_inventory( array( 'notice' => 'inventory_import_preview_expired' ) );
		}
		if ( ! empty( $preview['errors'] ) ) {
			self::redirect_to_inventory(
				array( 'notice' => 'inventory_import_preview_warning', 'inventory_preview_token' => $token )
			);
		}

		$result = UMS_Inventory_Import::import( $preview, get_current_user_id() );
		if ( empty( $result['success'] ) ) {
			self::redirect_to_inventory(
				array(
					'notice' => 'inventory_import_failed',
					'inventory_preview_token' => $token,
					'notice_extra' => implode( ' ', array_slice( $result['errors'], 0, 5 ) ),
				)
			);
		}

		UMS_Inventory_Import::delete_preview( $token );
		self::redirect_to_inventory(
			array(
				'notice' => 'inventory_import_completed',
				'notice_extra' => sprintf( 'Đã nhập %d dòng, cộng tổng %s sản phẩm.', $result['imported'], number_format_i18n( $result['total'] ) ),
			)
		);
	}

    public static function handle_manual_inventory_out() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_manual_inventory_out' );

        $raw            = isset( $_POST['ums_manual_out'] ) && is_array( $_POST['ums_manual_out'] ) ? wp_unslash( $_POST['ums_manual_out'] ) : array();
        $item_id        = isset( $raw['item_id'] ) ? absint( $raw['item_id'] ) : 0;
        $quantity       = isset( $raw['quantity'] ) ? absint( $raw['quantity'] ) : 0;
        $target_employee_no = isset( $raw['target_employee_no'] ) ? sanitize_text_field( $raw['target_employee_no'] ) : '';
        $note           = isset( $raw['note'] ) ? sanitize_textarea_field( $raw['note'] ) : '';

        if ( $item_id <= 0 || $quantity <= 0 ) {
            self::redirect_to_inventory( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Vui lòng chọn sản phẩm và nhập số lượng xuất kho hợp lệ.',
            ) );
        }

        if ( $target_employee_no === '' ) {
            self::redirect_to_inventory( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Vui lòng chọn người nhận để kiểm tra định mức trước khi xuất kho chủ động.',
            ) );
        }

        $item = UMS_DB_Inventory::get_by_id( $item_id );
        if ( ! $item ) {
            self::redirect_to_inventory( array( 'notice' => 'invalid_inventory_item' ) );
        }

        $target_organization = UMS_DB_Organization::get_by_employee_no( $target_employee_no );
        if ( ! $target_organization ) {
            self::redirect_to_inventory( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Người nhận không tồn tại trong Sơ đồ tổ chức TVN.',
            ) );
        }

        $target_user = get_user_by( 'login', $target_employee_no );
        if ( ! $target_user ) {
            $matched_users = get_users(
                array(
                    'meta_key'   => 'ums_employee_code',
                    'meta_value' => $target_employee_no,
                    'number'     => 1,
                    'fields'     => 'all',
                )
            );
            $target_user = ! empty( $matched_users ) ? reset( $matched_users ) : null;
        }
        $target_user_id = $target_user instanceof WP_User ? (int) $target_user->ID : 0;
        $target_profile = array(
            'user_id'       => $target_user_id,
            'employee_code' => (string) $target_organization['employee_no'],
            'full_name'     => (string) $target_organization['full_name'],
            'department'    => (string) $target_organization['department'],
            'job_position'  => (string) $target_organization['position'],
            'date_joined'   => (string) $target_organization['date_joined'],
        );

        $allowance_errors = self::validate_manual_inventory_out_allowance( $item, $quantity, $target_profile );
        if ( ! empty( $allowance_errors ) ) {
            self::redirect_to_inventory( array(
                'notice'       => 'validation_error',
                'notice_extra' => implode( ' ', $allowance_errors ),
            ) );
        }

        $before_qty = (int) $item['stock_qty'];
        if ( $before_qty < $quantity ) {
            self::redirect_to_inventory( array(
                'notice'       => 'validation_error',
                'notice_extra' => 'Số lượng xuất kho vượt quá tồn kho hiện tại.',
            ) );
        }

        $after_qty = $before_qty - $quantity;
        global $wpdb;
        $wpdb->query( 'START TRANSACTION' );

        $updated = UMS_DB_Inventory::update(
            $item_id,
            array(
                'stock_qty' => $after_qty,
            )
        );

        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            self::redirect_to_inventory( array(
                'notice'       => 'db_error',
                'notice_extra' => UMS_DB_Inventory::get_last_error(),
            ) );
        }

        $unit_price = (float) $item['base_price'];
        $inserted   = UMS_DB_Inventory_Movement::insert(
            array(
                'item_id'        => $item_id,
                'request_id'     => null,
                'movement_type'  => 'out',
                'quantity'       => $quantity,
                'before_qty'     => $before_qty,
                'after_qty'      => $after_qty,
                'unit_price'     => $unit_price,
                'total_price'    => $unit_price * $quantity,
                'actor_user_id'  => get_current_user_id(),
                'target_user_id' => $target_user_id > 0 ? $target_user_id : null,
                'target_employee_no' => $target_employee_no,
                'note'           => $note !== '' ? $note : 'Admin xuất kho chủ động.',
            )
        );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            self::redirect_to_inventory( array(
                'notice'       => 'db_error',
                'notice_extra' => UMS_DB_Inventory_Movement::get_last_error(),
            ) );
        }

        $wpdb->query( 'COMMIT' );
        self::redirect_to_inventory( array( 'notice' => 'inventory_manual_out_created' ) );
    }

    private static function record_inventory_admin_movement( $item_id, $before_qty, $after_qty, $unit_price, $is_edit ) {
        if ( $item_id <= 0 ) {
            return;
        }

        $diff = (int) $after_qty - (int) $before_qty;
        if ( ! $is_edit ) {
            $movement_type = 'in';
            $quantity      = max( 0, (int) $after_qty );
            $note          = 'Admin tạo mới sản phẩm/tồn kho.';
        } elseif ( $diff > 0 ) {
            $movement_type = 'in';
            $quantity      = $diff;
            $note          = 'Admin tăng số lượng tồn kho.';
        } elseif ( $diff < 0 ) {
            $movement_type = 'out';
            $quantity      = abs( $diff );
            $note          = 'Admin giảm số lượng tồn kho.';
        } else {
            $movement_type = 'adjust';
            $quantity      = 0;
            $note          = 'Admin cập nhật thông tin sản phẩm/tồn kho.';
        }

        UMS_DB_Inventory_Movement::insert(
            array(
                'item_id'        => $item_id,
                'request_id'     => null,
                'movement_type'  => $movement_type,
                'quantity'       => $quantity,
                'before_qty'     => (int) $before_qty,
                'after_qty'      => (int) $after_qty,
                'unit_price'     => (float) $unit_price,
                'total_price'    => (float) $unit_price * $quantity,
                'actor_user_id'  => get_current_user_id(),
                'target_user_id' => null,
                'note'           => $note,
            )
        );
    }

    private static function validate_manual_inventory_out_allowance( $item, $quantity, $target_profile ) {
        $errors      = array();
        $position_id = self::get_profile_position_id( $target_profile );
        $allowance_context = UMS_DB_Annual_Allowance::get_employee_context( $target_profile );
        $rule        = UMS_DB_Annual_Allowance::get_active_rule_for_item( (int) $item['item_id'], $position_id, $allowance_context );

        if ( ! $rule ) {
            return array( 'Sản phẩm "' . self::get_inventory_label( $item ) . '" chưa có định mức cấp phát phù hợp với người nhận.' );
        }

        $now_timestamp = current_time( 'timestamp' );
        $month         = (int) date( 'n', $now_timestamp );
        $month_start   = date( 'Y-m-01 00:00:00', $now_timestamp );
        $month_end     = date( 'Y-m-t 23:59:59', $now_timestamp );
        $period_end    = date( 'Y-m-d H:i:s', $now_timestamp );
        $quantities    = json_decode( (string) $rule['monthly_quantities'], true );
        $quantities    = is_array( $quantities ) ? $quantities : array();
        $month_quota   = isset( $quantities[ $month ] ) ? absint( $quantities[ $month ] ) : 0;
        $label         = self::get_inventory_label( $item );

        if ( $month_quota <= 0 ) {
            return array( 'Định mức của "' . $label . '" không cho phép cấp trong tháng ' . $month . '.' );
        }

        if ( UMS_DB_Annual_Allowance::requires_exact_quantity( $rule ) && (int) $quantity !== $month_quota ) {
            return array( 'Định mức CNV mới của "' . $label . '" yêu cầu xuất đúng ' . $month_quota . ' sản phẩm; số lượng đã nhập là ' . (int) $quantity . '.' );
        }

        $request_month_usage = UMS_DB_Request::get_allowance_usage(
            (int) $target_profile['user_id'],
            $rule,
            $month_start,
            $month_end
        );
        $manual_month_usage  = UMS_DB_Inventory_Movement::get_manual_allowance_usage(
            (int) $target_profile['user_id'],
            $rule,
            $month_start,
            $month_end,
            (string) $target_profile['employee_code']
        );
        $used_in_month = (int) $request_month_usage['quantity'] + (int) $manual_month_usage['quantity'];

        if ( $used_in_month + (int) $quantity > $month_quota ) {
            $errors[] = 'Số lượng "' . $label . '" vượt định mức tháng ' . $month . ' (' . $used_in_month . ' đã dùng, xuất thêm ' . (int) $quantity . ', tối đa ' . $month_quota . ').';
        }

        $frequency_count = max( 1, absint( $rule['frequency_count'] ?? 1 ) );
        $frequency_years = max( 1, absint( $rule['frequency_years'] ?? 1 ) );
        $period_start    = date( 'Y-m-d H:i:s', strtotime( '-' . $frequency_years . ' years', $now_timestamp ) );
        $request_usage   = UMS_DB_Request::get_allowance_usage(
            (int) $target_profile['user_id'],
            $rule,
            $period_start,
            $period_end
        );
        $manual_usage    = UMS_DB_Inventory_Movement::get_manual_allowance_usage(
            (int) $target_profile['user_id'],
            $rule,
            $period_start,
            $period_end,
            (string) $target_profile['employee_code']
        );
        $used_times      = (int) $request_usage['request_count'] + (int) $manual_usage['request_count'];

        if ( $used_times + 1 > $frequency_count ) {
            $errors[] = 'Định mức của "' . $label . '" chỉ cho phép ' . $frequency_count . ' lần / ' . $frequency_years . ' năm.';
        }

        return array_unique( $errors );
    }

    private static function get_profile_position_id( $profile ) {
        $job_position = isset( $profile['job_position'] ) ? trim( (string) $profile['job_position'] ) : '';
        if ( $job_position === '' ) {
            return 0;
        }

        foreach ( UMS_DB_Position::get_active() as $position ) {
            if (
                (string) $position['position_name'] === $job_position
                || (string) $position['position_code'] === $job_position
            ) {
                return (int) $position['position_id'];
            }
        }

        return 0;
    }

    private static function get_inventory_label( $item ) {
        $parts = array();

        if ( ! empty( $item['parent_category_name'] ) ) {
            $parts[] = $item['parent_category_name'];
        }

        if ( ! empty( $item['category_name'] ) ) {
            $parts[] = $item['category_name'];
        } elseif ( ! empty( $item['item_type'] ) ) {
            $parts[] = $item['item_type'];
        }

        $label = implode( ' / ', $parts );
        if ( ! empty( $item['item_variant'] ) ) {
            $label .= ' - ' . $item['item_variant'];
        }
        if ( ! empty( $item['size'] ) ) {
            $label .= ' - Size ' . $item['size'];
        }

        return $label !== '' ? $label : 'Sản phẩm #' . absint( $item['item_id'] );
    }

    private static function sanitize_profile_data( $raw ) {
        return array(
            'profile_id'       => isset( $raw['profile_id'] ) ? absint( $raw['profile_id'] ) : 0,
            'employee_code'    => isset( $raw['employee_code'] ) ? sanitize_text_field( $raw['employee_code'] ) : '',
            'full_name'        => isset( $raw['full_name'] ) ? sanitize_text_field( $raw['full_name'] ) : '',
            'gender'           => isset( $raw['gender'] ) ? sanitize_text_field( $raw['gender'] ) : '',
            'factory_location' => isset( $raw['factory_location'] ) ? sanitize_text_field( $raw['factory_location'] ) : '',
            'department'       => isset( $raw['department'] ) ? sanitize_text_field( $raw['department'] ) : '',
            'job_position'     => isset( $raw['job_position'] ) ? sanitize_text_field( $raw['job_position'] ) : '',
            'contract_type'    => isset( $raw['contract_type'] ) ? sanitize_text_field( $raw['contract_type'] ) : '',
            'date_joined'      => isset( $raw['date_joined'] ) ? sanitize_text_field( $raw['date_joined'] ) : '',
            'resignation_date' => ! empty( $raw['resignation_date'] ) ? sanitize_text_field( $raw['resignation_date'] ) : null,
            'transfer_date'    => ! empty( $raw['transfer_date'] ) ? sanitize_text_field( $raw['transfer_date'] ) : null,
            'is_maternity'     => ! empty( $raw['is_maternity'] ) ? 1 : 0,
            'is_outdoor_worker'=> ! empty( $raw['is_outdoor_worker'] ) ? 1 : 0,
            'account_status'   => isset( $raw['account_status'] ) ? sanitize_key( $raw['account_status'] ) : 'active',
        );
    }

    private static function sanitize_department_data( $raw ) {
        return array(
            'department_id'      => isset( $raw['department_id'] ) ? absint( $raw['department_id'] ) : 0,
            'department_code'    => isset( $raw['department_code'] ) ? sanitize_key( $raw['department_code'] ) : '',
            'department_name'    => isset( $raw['department_name'] ) ? sanitize_text_field( $raw['department_name'] ) : '',
            'department_group'   => isset( $raw['department_group'] ) ? sanitize_text_field( $raw['department_group'] ) : '',
            'is_active'          => ! empty( $raw['is_active'] ) ? 1 : 0,
        );
    }

    private static function sanitize_position_data( $raw ) {
        return array(
            'position_id'   => isset( $raw['position_id'] ) ? absint( $raw['position_id'] ) : 0,
            'position_code' => isset( $raw['position_code'] ) ? sanitize_key( $raw['position_code'] ) : '',
            'position_name' => isset( $raw['position_name'] ) ? sanitize_text_field( $raw['position_name'] ) : '',
            'is_active'     => ! empty( $raw['is_active'] ) ? 1 : 0,
        );
    }

    private static function sanitize_factory_location_data( $raw ) {
        return array(
            'factory_location_id'   => isset( $raw['factory_location_id'] ) ? absint( $raw['factory_location_id'] ) : 0,
            'factory_location_code' => isset( $raw['factory_location_code'] ) ? sanitize_key( $raw['factory_location_code'] ) : '',
            'factory_location_name' => isset( $raw['factory_location_name'] ) ? sanitize_text_field( $raw['factory_location_name'] ) : '',
            'is_active'             => ! empty( $raw['is_active'] ) ? 1 : 0,
        );
    }

    private static function sanitize_contract_type_data( $raw ) {
        return array(
            'contract_type_id'   => isset( $raw['contract_type_id'] ) ? absint( $raw['contract_type_id'] ) : 0,
            'contract_type_code' => isset( $raw['contract_type_code'] ) ? sanitize_key( $raw['contract_type_code'] ) : '',
            'contract_type_name' => isset( $raw['contract_type_name'] ) ? sanitize_text_field( $raw['contract_type_name'] ) : '',
            'is_active'          => ! empty( $raw['is_active'] ) ? 1 : 0,
        );
    }

    private static function sanitize_approval_flow_data( $raw ) {
        $approver_ids = array();
        if ( isset( $raw['approver_profile_ids'] ) && is_array( $raw['approver_profile_ids'] ) ) {
            $approver_ids = array_map( 'absint', $raw['approver_profile_ids'] );
        } elseif ( isset( $raw['approver_profile_id'] ) ) {
            $approver_ids = array( absint( $raw['approver_profile_id'] ) );
        }
        $approver_ids = array_values( array_unique( array_filter( $approver_ids ) ) );

        return array(
            'flow_id'             => isset( $raw['flow_id'] ) ? absint( $raw['flow_id'] ) : 0,
            'department_id'       => isset( $raw['department_id'] ) ? absint( $raw['department_id'] ) : 0,
            'step_order'          => isset( $raw['step_order'] ) ? absint( $raw['step_order'] ) : 0,
            'step_name'           => isset( $raw['step_name'] ) ? sanitize_text_field( $raw['step_name'] ) : '',
            'approver_profile_ids'=> $approver_ids,
            'is_active'           => ! empty( $raw['is_active'] ) ? 1 : 0,
        );
    }

    private static function sanitize_product_category_data( $raw ) {
        return array(
            'category_id'   => isset( $raw['category_id'] ) ? absint( $raw['category_id'] ) : 0,
            'parent_id'     => ! empty( $raw['parent_id'] ) ? absint( $raw['parent_id'] ) : 0,
            'category_name' => isset( $raw['category_name'] ) ? sanitize_text_field( $raw['category_name'] ) : '',
            'is_active'     => ! empty( $raw['is_active'] ) ? 1 : 0,
        );
    }

    private static function sanitize_inventory_data( $raw ) {
        $category_id = isset( $raw['category_id'] ) ? absint( $raw['category_id'] ) : 0;
        $category    = $category_id ? UMS_DB_Product_Category::get_by_id( $category_id ) : null;

        return array(
            'item_id'      => isset( $raw['item_id'] ) ? absint( $raw['item_id'] ) : 0,
            'category_id'  => $category_id,
            'item_type'    => $category ? $category['category_name'] : '',
            'item_variant' => isset( $raw['item_variant'] ) ? sanitize_text_field( $raw['item_variant'] ) : '',
            'size'         => isset( $raw['size'] ) ? sanitize_text_field( $raw['size'] ) : '',
            'color_code'   => '',
            'stock_qty'    => isset( $raw['stock_qty'] ) ? (int) $raw['stock_qty'] : 0,
            'base_price'   => isset( $raw['base_price'] ) ? self::normalize_money_value( $raw['base_price'] ) : 0,
        );
    }

    private static function sanitize_annual_allowance_data( $raw ) {
        $monthly_quantities = array_fill( 1, 12, 0 );
        $raw_months = isset( $raw['monthly_quantities'] ) && is_array( $raw['monthly_quantities'] ) ? $raw['monthly_quantities'] : array();
        $monthly_quantities[4] = isset( $raw_months[4] ) ? max( 0, absint( $raw_months[4] ) ) : 0;
        $monthly_quantities[9] = isset( $raw_months[9] ) ? max( 0, absint( $raw_months[9] ) ) : 0;

        $product     = isset( $raw['product_group'] ) ? explode( '|', sanitize_text_field( $raw['product_group'] ), 2 ) : array();
        $category_id = count( $product ) === 2 ? absint( $product[0] ) : 0;
        $item_variant = count( $product ) === 2 ? sanitize_text_field( $product[1] ) : '';
        $frequency_count = count( array_filter( array( $monthly_quantities[4], $monthly_quantities[9] ) ) );

        $data = array(
            'rule_id'            => isset( $raw['rule_id'] ) ? absint( $raw['rule_id'] ) : 0,
            'rule_scope'         => 'annual',
            'apply_type'         => 'product',
            'category_id'        => $category_id,
            'item_id'            => 0,
            'item_variant'       => $item_variant,
            'source_product_name'=> $item_variant,
            'target_type'        => 'organization',
            'position_id'        => 0,
            'department'         => isset( $raw['department'] ) ? sanitize_text_field( $raw['department'] ) : '',
            'team'               => isset( $raw['team'] ) ? sanitize_text_field( $raw['team'] ) : '',
            'cost_center'        => isset( $raw['cost_center'] ) ? sanitize_text_field( $raw['cost_center'] ) : '',
            'position_code'      => isset( $raw['position_code'] ) ? UMS_DB_Annual_Allowance::normalize_position_code( sanitize_text_field( $raw['position_code'] ) ) : '',
            'employment_start_md'=> '',
            'employment_end_md'  => '',
            'eligibility_note'   => isset( $raw['eligibility_note'] ) ? sanitize_text_field( $raw['eligibility_note'] ) : '',
            'frequency_count'    => max( 1, $frequency_count ),
            'frequency_years'    => 1,
            'monthly_quantities' => wp_json_encode( $monthly_quantities ),
            'priority'           => 100,
            'is_active'          => ! empty( $raw['is_active'] ) ? 1 : 0,
        );

        $data['rule_key'] = UMS_DB_Annual_Allowance::build_rule_key( $data );
        return $data;
    }

    private static function normalize_money_value( $value ) {
        $value = trim( sanitize_text_field( (string) $value ) );
        $value = str_replace( ' ', '', $value );

        if ( $value === '' ) {
            return 0;
        }

        $has_comma = strpos( $value, ',' ) !== false;
        $has_dot   = strpos( $value, '.' ) !== false;

        if ( $has_comma && $has_dot ) {
            $last_comma = strrpos( $value, ',' );
            $last_dot   = strrpos( $value, '.' );

            if ( $last_comma > $last_dot ) {
                $value = str_replace( '.', '', $value );
                $value = str_replace( ',', '.', $value );
            } else {
                $value = str_replace( ',', '', $value );
            }
        } elseif ( $has_comma ) {
            $parts = explode( ',', $value );
            $last  = end( $parts );

            if ( count( $parts ) > 2 || strlen( $last ) === 3 ) {
                $value = str_replace( ',', '', $value );
            } else {
                $value = str_replace( ',', '.', $value );
            }
        } elseif ( $has_dot ) {
            $parts = explode( '.', $value );
            $last  = end( $parts );

            if ( count( $parts ) > 2 || strlen( $last ) === 3 ) {
                $value = str_replace( '.', '', $value );
            }
        }

        return is_numeric( $value ) ? (float) $value : -1;
    }

    private static function validate_profile_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['profile_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy hồ sơ nhân sự cần cập nhật.';
        }

        foreach ( array( 'employee_code', 'full_name', 'department', 'job_position', 'date_joined' ) as $field ) {
            if ( $data[ $field ] === '' ) {
                $errors[] = 'Các trường bắt buộc chưa được nhập đầy đủ.';
                break;
            }
        }

        if ( ! in_array( $data['gender'], UMS_DB_User::GENDERS, true ) ) {
            $errors[] = 'Giới tính không hợp lệ.';
        }

        if ( ! in_array( $data['account_status'], array( 'active', 'inactive' ), true ) ) {
            $errors[] = 'Trạng thái tài khoản không hợp lệ.';
        }

        if ( ! self::is_known_department( $data['department'] ) ) {
            $errors[] = 'Phòng ban chưa có trong danh mục hoặc đang ngừng sử dụng.';
        }

        if ( ! self::is_known_position( $data['job_position'] ) ) {
            $errors[] = 'Chức danh chưa có trong danh mục hoặc đang ngừng sử dụng.';
        }

        if ( ! self::is_known_factory_location( $data['factory_location'] ) ) {
            $errors[] = 'Nhà máy chưa có trong danh mục hoặc đang ngừng sử dụng.';
        }

        if ( ! self::is_known_contract_type( $data['contract_type'] ) ) {
            $errors[] = 'Loại hợp đồng chưa có trong danh mục hoặc đang ngừng sử dụng.';
        }

        foreach ( array( 'date_joined', 'resignation_date', 'transfer_date' ) as $date_field ) {
            if ( $data[ $date_field ] !== null && $data[ $date_field ] !== '' && ! self::is_valid_date( $data[ $date_field ] ) ) {
                $errors[] = 'Ngày nhập chưa đúng định dạng.';
                break;
            }
        }

        if ( UMS_DB_User::employee_code_exists( $data['employee_code'], $is_edit ? $data['profile_id'] : 0 ) ) {
            $errors[] = 'Mã nhân viên đã tồn tại.';
        }

        if ( ! $is_edit && username_exists( $data['employee_code'] ) ) {
            $errors[] = 'Mã nhân viên này đã tồn tại trong tài khoản WordPress.';
        }

        return array_unique( $errors );
    }

    private static function validate_department_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['department_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy phòng ban cần cập nhật.';
        }

        if ( $data['department_code'] === '' || $data['department_name'] === '' ) {
            $errors[] = 'Vui lòng nhập đầy đủ mã phòng ban và tên phòng ban.';
        }

        if ( strlen( $data['department_code'] ) > 50 || self::text_length( $data['department_name'] ) > 150 || self::text_length( $data['department_group'] ) > 150 ) {
            $errors[] = 'Mã phòng ban tối đa 50 ký tự; tên phòng ban và nhóm tối đa 150 ký tự.';
        }

        if ( UMS_DB_Department::code_exists( $data['department_code'], $is_edit ? $data['department_id'] : 0 ) ) {
            $errors[] = 'Mã phòng ban đã tồn tại.';
        }

        return array_unique( $errors );
    }

    private static function validate_position_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['position_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy chức danh cần cập nhật.';
        }

        if ( $data['position_code'] === '' || $data['position_name'] === '' ) {
            $errors[] = 'Vui lòng nhập đầy đủ mã chức danh và tên chức danh.';
        }

        if ( UMS_DB_Position::code_exists( $data['position_code'], $is_edit ? $data['position_id'] : 0 ) ) {
            $errors[] = 'Mã chức danh đã tồn tại.';
        }

        return array_unique( $errors );
    }

    private static function validate_factory_location_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['factory_location_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy nhà máy cần cập nhật.';
        }

        if ( $data['factory_location_code'] === '' || $data['factory_location_name'] === '' ) {
            $errors[] = 'Vui lòng nhập đầy đủ mã nhà máy và tên nhà máy.';
        }

        if ( UMS_DB_Factory_Location::code_exists( $data['factory_location_code'], $is_edit ? $data['factory_location_id'] : 0 ) ) {
            $errors[] = 'Mã nhà máy đã tồn tại.';
        }

        return array_unique( $errors );
    }

    private static function validate_contract_type_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['contract_type_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy loại hợp đồng cần cập nhật.';
        }

        if ( $data['contract_type_code'] === '' || $data['contract_type_name'] === '' ) {
            $errors[] = 'Vui lòng nhập đầy đủ mã hợp đồng và tên loại hợp đồng.';
        }

        if ( UMS_DB_Contract_Type::code_exists( $data['contract_type_code'], $is_edit ? $data['contract_type_id'] : 0 ) ) {
            $errors[] = 'Mã hợp đồng đã tồn tại.';
        }

        return array_unique( $errors );
    }

    private static function validate_approval_flow_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['flow_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy bước duyệt cần cập nhật.';
        }

        if ( $data['department_id'] <= 0 || ! UMS_DB_Department::get_by_id( $data['department_id'] ) ) {
            $errors[] = 'Vui lòng chọn phòng ban hợp lệ.';
        }

        if ( $data['step_order'] <= 0 ) {
            $errors[] = 'Thứ tự bước duyệt phải lớn hơn 0.';
        }

        if ( $data['step_name'] === '' ) {
            $errors[] = 'Vui lòng nhập tên bước duyệt.';
        }

        if ( empty( $data['approver_profile_ids'] ) ) {
            $errors[] = 'Vui lòng chọn ít nhất một người duyệt.';
        }

        foreach ( $data['approver_profile_ids'] as $approver_profile_id ) {
            if ( $approver_profile_id <= 0 || ! UMS_DB_User::get_by_id( $approver_profile_id ) ) {
                $errors[] = 'Danh sách người duyệt có hồ sơ không hợp lệ.';
                break;
            }
        }

        if ( UMS_DB_Approval_Flow::step_order_exists( $data['department_id'], $data['step_order'], $is_edit ? $data['flow_id'] : 0 ) ) {
            $errors[] = 'Phòng ban này đã có bước duyệt với thứ tự đã chọn.';
        }

        return array_unique( $errors );
    }

    private static function validate_product_category_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['category_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy danh mục cần cập nhật.';
        }

        if ( $data['category_name'] === '' ) {
            $errors[] = 'Vui lòng nhập tên danh mục.';
        }

        if ( $data['parent_id'] && ! UMS_DB_Product_Category::get_by_id( $data['parent_id'] ) ) {
            $errors[] = 'Danh mục cha không hợp lệ.';
        }

        if ( $is_edit && $data['parent_id'] && (int) $data['parent_id'] === (int) $data['category_id'] ) {
            $errors[] = 'Danh mục không thể là cha của chính nó.';
        }

        return array_unique( $errors );
    }

    private static function validate_inventory_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['item_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy sản phẩm cần cập nhật.';
        }

        if ( $data['category_id'] <= 0 ) {
            $errors[] = 'Vui lòng chọn danh mục cha cho sản phẩm.';
        } else {
            $category = UMS_DB_Product_Category::get_by_id( $data['category_id'] );
            if ( ! $category || (int) $category['parent_id'] !== 0 || (int) $category['is_active'] !== 1 ) {
                $errors[] = 'Sản phẩm phải thuộc một danh mục cha đang sử dụng.';
            }
        }

        foreach ( array( 'item_variant', 'size' ) as $field ) {
            if ( $data[ $field ] === '' ) {
                $errors[] = 'Vui lòng nhập đầy đủ tên sản phẩm và size.';
                break;
            }
        }

        if ( $data['stock_qty'] < 0 ) {
            $errors[] = 'Số lượng tồn kho không được âm.';
        }

        if ( $data['base_price'] < 0 ) {
            $errors[] = 'Đơn giá gốc không được âm.';
        }

        if ( UMS_DB_Inventory::variant_exists( $data, $is_edit ? $data['item_id'] : 0 ) ) {
            $errors[] = 'Sản phẩm và size này đã tồn tại trong kho.';
        }

        return array_unique( $errors );
    }

    private static function validate_annual_allowance_data( $data, $is_edit ) {
        $errors = array();

        if ( $is_edit && $data['rule_id'] <= 0 ) {
            $errors[] = 'Không tìm thấy định mức cần cập nhật.';
        }

        if ( $data['category_id'] <= 0 || $data['item_variant'] === '' || ! UMS_DB_Inventory::product_group_exists( $data['category_id'], $data['item_variant'] ) ) {
            $errors[] = 'Vui lòng chọn sản phẩm áp dụng hợp lệ.';
        }

        if ( $data['department'] === '' ) {
            $errors[] = 'Vui lòng chọn Bộ phận.';
        }

        if ( $data['position_code'] === '' ) {
            $errors[] = 'Vui lòng chọn Vị trí.';
        }

        $monthly_quantities = json_decode( $data['monthly_quantities'], true );
        if ( ! is_array( $monthly_quantities ) || absint( $monthly_quantities[4] ?? 0 ) + absint( $monthly_quantities[9] ?? 0 ) <= 0 ) {
            $errors[] = 'Số lượng Tháng 4 hoặc Tháng 9 phải lớn hơn 0.';
        }

        return array_unique( $errors );
    }

    private static function is_valid_date( $date ) {
        $parsed = DateTime::createFromFormat( 'Y-m-d', $date );
        return $parsed && $parsed->format( 'Y-m-d' ) === $date;
    }

    public static function handle_preview_annual_allowance_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_preview_annual_allowance_import' );

        if ( ! UMS_DB_Annual_Allowance::is_import_ready() ) {
            self::redirect_to_annual_allowances( array( 'notice' => 'allowance_import_schema_missing' ) );
        }
        $file = isset( $_FILES['ums_allowance_import_file'] ) ? $_FILES['ums_allowance_import_file'] : array();

        if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) ) {
            self::redirect_to_annual_allowances( array( 'notice' => 'allowance_import_invalid_file' ) );
        }

        if ( (int) $file['size'] > 10 * MB_IN_BYTES || strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) !== 'xlsx' ) {
            self::redirect_to_annual_allowances( array( 'notice' => 'allowance_import_invalid_file' ) );
        }

        try {
            $preview = UMS_Annual_Allowance_Import::analyze( $file['tmp_name'], $file['name'] );
            $token   = UMS_Annual_Allowance_Import::store_preview( $preview );
            self::redirect_to_annual_allowances(
                array(
                    'notice'        => empty( $preview['errors'] ) ? 'allowance_import_preview_ready' : 'allowance_import_preview_warning',
                    'preview_token' => $token,
                )
            );
        } catch ( Throwable $error ) {
            self::redirect_to_annual_allowances(
                array(
                    'notice'       => 'allowance_import_invalid_file',
                    'notice_extra' => $error->getMessage(),
                )
            );
        }
    }

    public static function handle_confirm_annual_allowance_import() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'tvn-ums' ) );
        }

        check_admin_referer( 'ums_confirm_annual_allowance_import' );
        $token   = isset( $_POST['preview_token'] ) ? sanitize_key( wp_unslash( $_POST['preview_token'] ) ) : '';
        $preview = UMS_Annual_Allowance_Import::get_preview( $token );
        $mappings = isset( $_POST['product_mapping'] ) && is_array( $_POST['product_mapping'] )
            ? wp_unslash( $_POST['product_mapping'] )
            : array();

        if ( ! is_array( $preview ) ) {
            self::redirect_to_annual_allowances( array( 'notice' => 'allowance_import_preview_expired' ) );
        }

        if ( ! empty( $preview['errors'] ) ) {
            self::redirect_to_annual_allowances(
                array( 'notice' => 'allowance_import_preview_warning', 'preview_token' => $token )
            );
        }

        $result = UMS_Annual_Allowance_Import::import( $preview, $mappings, get_current_user_id() );
        if ( empty( $result['success'] ) ) {
            self::redirect_to_annual_allowances(
                array(
                    'notice' => 'allowance_import_failed', 'preview_token' => $token,
                    'notice_extra' => implode( ' ', array_slice( $result['errors'], 0, 5 ) ),
                )
            );
        }

        UMS_Annual_Allowance_Import::delete_preview( $token );
        self::redirect_to_annual_allowances(
            array(
                'notice' => 'allowance_import_completed',
                'notice_extra' => sprintf( 'Đã thêm %d và cập nhật %d rule.', $result['inserted'], $result['updated'] ),
            )
        );
    }

    private static function text_length( $value ) {
        return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $value, 'UTF-8' ) : strlen( (string) $value );
    }

    private static function ensure_wp_user_for_profile( $data, $account_status, $existing_profile = null, $reset_password = false ) {
        $user_id = $existing_profile && ! empty( $existing_profile['user_id'] ) ? (int) $existing_profile['user_id'] : 0;
        $user    = $user_id ? get_user_by( 'id', $user_id ) : false;

        if ( ! $user ) {
            $user_login = sanitize_user( $data['employee_code'], true );
            if ( $user_login === '' ) {
                return new WP_Error( 'invalid_user_login', 'Mã nhân viên không thể dùng để tạo tài khoản WordPress.' );
            }

            if ( username_exists( $user_login ) ) {
                return new WP_Error( 'user_login_exists', 'Mã nhân viên này đã tồn tại trong tài khoản WordPress.' );
            }

            $user_id = wp_insert_user( array(
                'user_login'   => $user_login,
                'user_pass'    => '12345678',
                'display_name' => $data['full_name'],
                'nickname'     => $data['full_name'],
                'role'         => 'subscriber',
                'user_status'  => $account_status === 'inactive' ? 1 : 0,
            ) );

            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }

            return (int) $user_id;
        }

        $user_login = sanitize_user( $data['employee_code'], true );
        if ( $user_login === '' ) {
            return new WP_Error( 'invalid_user_login', 'Mã nhân viên không thể dùng để cập nhật tài khoản WordPress.' );
        }

        if ( $user->user_login !== $user_login ) {
            $existing_user_id = username_exists( $user_login );
            if ( $existing_user_id && (int) $existing_user_id !== $user_id ) {
                return new WP_Error( 'user_login_exists', 'Mã nhân viên này đã tồn tại trong tài khoản WordPress.' );
            }

            global $wpdb;
            $wpdb->update(
                $wpdb->users,
                array(
                    'user_login'    => $user_login,
                    'user_nicename' => sanitize_title( $user_login ),
                ),
                array( 'ID' => $user_id ),
                array( '%s', '%s' ),
                array( '%d' )
            );
        }

        $update_data = array(
            'ID'           => $user_id,
            'display_name' => $data['full_name'],
            'nickname'     => $data['full_name'],
            'user_status'  => $account_status === 'inactive' ? 1 : 0,
        );

        if ( $reset_password ) {
            $update_data['user_pass'] = '12345678';
        }

        $updated_user_id = wp_update_user( $update_data );
        return is_wp_error( $updated_user_id ) ? $updated_user_id : (int) $updated_user_id;
    }

    private static function get_default_profile_values( $profile = null ) {
        $factory_locations = UMS_DB_Factory_Location::get_active();
        $contract_types    = UMS_DB_Contract_Type::get_active();

        $defaults = array(
            'profile_id'        => 0,
            'user_id'           => null,
            'employee_code'     => '',
            'full_name'         => '',
            'gender'            => 'Nam',
            'factory_location'  => ! empty( $factory_locations[0]['factory_location_name'] ) ? $factory_locations[0]['factory_location_name'] : '',
            'department'        => '',
            'job_position'      => '',
            'contract_type'     => ! empty( $contract_types[0]['contract_type_name'] ) ? $contract_types[0]['contract_type_name'] : '',
            'date_joined'       => current_time( 'Y-m-d' ),
            'resignation_date'  => '',
            'transfer_date'     => '',
            'is_maternity'      => 0,
            'is_outdoor_worker' => 0,
            'account_status'    => 'active',
        );

        $values = $profile ? wp_parse_args( $profile, $defaults ) : $defaults;
        $values['account_status'] = ! empty( $values['user_status'] ) ? 'inactive' : 'active';

        return $values;
    }

    private static function get_default_department_values( $department = null ) {
        $defaults = array(
            'department_id'      => 0,
            'department_code'    => '',
            'department_name'    => '',
            'department_group'   => '',
            'is_active'          => 1,
        );

        return $department ? wp_parse_args( $department, $defaults ) : $defaults;
    }

    private static function get_default_position_values( $position = null ) {
        $defaults = array(
            'position_id'   => 0,
            'position_code' => '',
            'position_name' => '',
            'is_active'     => 1,
        );

        return $position ? wp_parse_args( $position, $defaults ) : $defaults;
    }

    private static function get_default_factory_location_values( $factory_location = null ) {
        $defaults = array(
            'factory_location_id'   => 0,
            'factory_location_code' => '',
            'factory_location_name' => '',
            'is_active'             => 1,
        );

        return $factory_location ? wp_parse_args( $factory_location, $defaults ) : $defaults;
    }

    private static function get_default_contract_type_values( $contract_type = null ) {
        $defaults = array(
            'contract_type_id'   => 0,
            'contract_type_code' => '',
            'contract_type_name' => '',
            'is_active'          => 1,
        );

        return $contract_type ? wp_parse_args( $contract_type, $defaults ) : $defaults;
    }

    private static function get_default_approval_flow_values( $flow = null ) {
        $defaults = array(
            'flow_id'             => 0,
            'department_id'       => 0,
            'step_order'          => 1,
            'step_name'           => '',
            'approver_profile_ids'=> array(),
            'is_active'           => 1,
        );

        $values = $flow ? wp_parse_args( $flow, $defaults ) : $defaults;
        if ( is_string( $values['approver_profile_ids'] ) ) {
            $decoded = json_decode( $values['approver_profile_ids'], true );
            $values['approver_profile_ids'] = is_array( $decoded ) ? array_map( 'absint', $decoded ) : array();
        }

        return $values;
    }

    private static function get_default_product_category_values( $category = null ) {
        $defaults = array(
            'category_id'   => 0,
            'parent_id'     => 0,
            'category_name' => '',
            'is_active'     => 1,
        );

        return $category ? wp_parse_args( $category, $defaults ) : $defaults;
    }

    private static function get_default_inventory_values( $item = null ) {
        $defaults = array(
            'item_id'      => 0,
            'category_id'  => 0,
            'item_type'    => '',
            'item_variant' => '',
            'size'         => '',
            'color_code'   => '',
            'stock_qty'    => 0,
            'base_price'   => '0.00',
        );

        $values = $item ? wp_parse_args( $item, $defaults ) : $defaults;
        if ( isset( $values['base_price'] ) && is_numeric( $values['base_price'] ) ) {
            $price = (float) $values['base_price'];
            $values['base_price'] = floor( $price ) === $price
                ? number_format( $price, 0, '.', '' )
                : number_format( $price, 2, '.', '' );
        }

        return $values;
    }

    private static function get_default_annual_allowance_values( $rule = null ) {
        $monthly_quantities = array();
        for ( $month = 1; $month <= 12; $month++ ) {
            $monthly_quantities[ $month ] = 0;
        }

        $defaults = array(
            'rule_id'            => 0,
            'rule_scope'         => 'annual',
            'apply_type'         => 'product',
            'category_id'        => 0,
            'item_id'            => 0,
            'item_variant'       => '',
            'target_type'        => 'organization',
            'position_id'        => 0,
            'department'         => '',
            'team'               => '',
            'cost_center'        => '',
            'position_code'      => '',
            'employment_start_md'=> '',
            'employment_end_md'  => '',
            'eligibility_note'   => '',
            'frequency_count'    => 1,
            'frequency_years'    => 1,
            'priority'           => 100,
            'monthly_quantities' => $monthly_quantities,
            'is_active'          => 1,
        );

        $values = $rule ? wp_parse_args( $rule, $defaults ) : $defaults;

        if ( is_string( $values['monthly_quantities'] ) ) {
            $decoded = json_decode( $values['monthly_quantities'], true );
            $values['monthly_quantities'] = is_array( $decoded ) ? $decoded : array();
        }

        $normalised_months = array();
        for ( $month = 1; $month <= 12; $month++ ) {
            $normalised_months[ $month ] = isset( $values['monthly_quantities'][ $month ] )
                ? absint( $values['monthly_quantities'][ $month ] )
                : 0;
        }
        $values['monthly_quantities'] = $normalised_months;
        $values['product_group'] = $values['apply_type'] === 'product'
            ? absint( $values['category_id'] ) . '|' . (string) $values['item_variant']
            : '';

        return $values;
    }

    private static function is_known_department( $department_name ) {
        $departments = UMS_DB_Department::get_active();

        if ( empty( $departments ) ) {
            return true;
        }

        foreach ( $departments as $department ) {
            if ( $department['department_name'] === $department_name ) {
                return true;
            }
        }

        return false;
    }

    private static function is_known_position( $position_name ) {
        $positions = UMS_DB_Position::get_active();

        if ( empty( $positions ) ) {
            return true;
        }

        foreach ( $positions as $position ) {
            if ( $position['position_name'] === $position_name ) {
                return true;
            }
        }

        return false;
    }

    private static function is_known_factory_location( $factory_location_name ) {
        $factory_locations = UMS_DB_Factory_Location::get_active();

        if ( empty( $factory_locations ) ) {
            return true;
        }

        foreach ( $factory_locations as $factory_location ) {
            if ( $factory_location['factory_location_name'] === $factory_location_name ) {
                return true;
            }
        }

        return false;
    }

    private static function is_known_contract_type( $contract_type_name ) {
        $contract_types = UMS_DB_Contract_Type::get_active();

        if ( empty( $contract_types ) ) {
            return true;
        }

        foreach ( $contract_types as $contract_type ) {
            if ( $contract_type['contract_type_name'] === $contract_type_name ) {
                return true;
            }
        }

        return false;
    }

    private static function get_notice() {
        $code = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
        if ( $code === '' ) {
            return null;
        }

        $messages = array(
            'sheet_sync_settings_saved' => array( 'success', 'Đã lưu cấu hình đồng bộ Google Sheet.' ),
            'created'          => array( 'success', 'Đã thêm hồ sơ nhân sự mới.' ),
            'updated'          => array( 'success', 'Đã cập nhật hồ sơ nhân sự.' ),
            'deleted'          => array( 'success', 'Đã xóa hồ sơ nhân sự.' ),
            'department_created' => array( 'success', 'Đã thêm phòng ban mới.' ),
            'department_updated' => array( 'success', 'Đã cập nhật phòng ban.' ),
            'department_deleted' => array( 'success', 'Đã xóa phòng ban.' ),
            'department_imported' => array( 'success', 'Import phòng ban hoàn tất.' ),
            'position_created' => array( 'success', 'Đã thêm chức danh mới.' ),
            'position_updated' => array( 'success', 'Đã cập nhật chức danh.' ),
            'position_deleted' => array( 'success', 'Đã xóa chức danh.' ),
            'factory_location_created' => array( 'success', 'Đã thêm nhà máy mới.' ),
            'factory_location_updated' => array( 'success', 'Đã cập nhật nhà máy.' ),
            'factory_location_deleted' => array( 'success', 'Đã xóa nhà máy.' ),
            'contract_type_created' => array( 'success', 'Đã thêm loại hợp đồng mới.' ),
            'contract_type_updated' => array( 'success', 'Đã cập nhật loại hợp đồng.' ),
            'contract_type_deleted' => array( 'success', 'Đã xóa loại hợp đồng.' ),
            'approval_flow_created' => array( 'success', 'Đã thêm bước duyệt mới.' ),
            'approval_flow_updated' => array( 'success', 'Đã cập nhật bước duyệt.' ),
            'approval_flow_deleted' => array( 'success', 'Đã xóa bước duyệt.' ),
            'product_category_created' => array( 'success', 'Đã thêm danh mục sản phẩm mới.' ),
            'product_category_updated' => array( 'success', 'Đã cập nhật danh mục sản phẩm.' ),
            'product_category_deleted' => array( 'success', 'Đã xóa danh mục sản phẩm.' ),
            'inventory_created'  => array( 'success', 'Đã thêm sản phẩm/tồn kho mới.' ),
            'inventory_updated'  => array( 'success', 'Đã cập nhật sản phẩm/tồn kho.' ),
            'inventory_deleted'  => array( 'success', 'Đã xóa sản phẩm khỏi danh mục kho.' ),
            'inventory_manual_out_created' => array( 'success', 'Đã ghi nhận xuất kho chủ động và trừ tồn kho.' ),
			'inventory_import_preview_ready' => array( 'success', 'Đã đọc template nhập kho. Hãy kiểm tra số lượng trước khi xác nhận.' ),
			'inventory_import_preview_warning' => array( 'warning', 'Template nhập kho còn lỗi và chưa thể xác nhận.' ),
			'inventory_import_invalid_file' => array( 'error', 'File nhập kho không hợp lệ hoặc không đọc được.' ),
			'inventory_import_schema_missing' => array( 'error', 'Database chưa có cấu trúc import kho. Hãy cập nhật các bảng trong ums.sql.' ),
			'inventory_import_preview_expired' => array( 'error', 'Dữ liệu xem trước nhập kho đã hết hạn. Vui lòng tải lại file.' ),
			'inventory_import_failed' => array( 'error', 'Import nhập kho không thành công.' ),
			'inventory_import_completed' => array( 'success', 'Import nhập kho hoàn tất.' ),
            'annual_allowance_created' => array( 'success', 'Đã thêm định mức cấp phát hàng năm.' ),
            'annual_allowance_updated' => array( 'success', 'Đã cập nhật định mức cấp phát hàng năm.' ),
            'annual_allowance_deleted' => array( 'success', 'Đã xóa định mức cấp phát hàng năm.' ),
            'allowance_import_preview_ready' => array( 'success', 'Đã đọc Excel. Hãy kiểm tra và ánh xạ sản phẩm trước khi import.' ),
            'allowance_import_preview_warning' => array( 'warning', 'Đã đọc Excel nhưng còn lỗi cần xử lý.' ),
            'allowance_import_invalid_file' => array( 'error', 'File định mức không hợp lệ hoặc không đọc được.' ),
            'allowance_import_schema_missing' => array( 'error', 'Database chưa có cấu trúc định mức linh hoạt. Hãy chạy phần nâng cấp trong ums.sql trước khi import.' ),
            'allowance_import_preview_expired' => array( 'error', 'Dữ liệu xem trước đã hết hạn. Vui lòng tải lại file Excel.' ),
            'allowance_import_failed' => array( 'error', 'Import định mức không thành công.' ),
            'allowance_import_completed' => array( 'success', 'Import định mức hoàn tất.' ),
            'organization_synced' => array( 'success', 'Đồng bộ sơ đồ tổ chức thành công.' ),
            'organization_sync_failed' => array( 'error', 'Không thể đồng bộ sơ đồ tổ chức.' ),
            'invalid_user'     => array( 'error', 'Không tìm thấy nhân sự cần xử lý.' ),
            'invalid_department' => array( 'error', 'Không tìm thấy phòng ban cần xử lý.' ),
            'invalid_position' => array( 'error', 'Không tìm thấy chức danh cần xử lý.' ),
            'invalid_factory_location' => array( 'error', 'Không tìm thấy nhà máy cần xử lý.' ),
            'invalid_contract_type' => array( 'error', 'Không tìm thấy loại hợp đồng cần xử lý.' ),
            'invalid_approval_flow' => array( 'error', 'Không tìm thấy bước duyệt cần xử lý.' ),
            'invalid_product_category' => array( 'error', 'Không tìm thấy danh mục sản phẩm cần xử lý.' ),
            'invalid_inventory_item' => array( 'error', 'Không tìm thấy sản phẩm cần xử lý.' ),
            'invalid_annual_allowance' => array( 'error', 'Không tìm thấy định mức cần xử lý.' ),
            'validation_error' => array( 'error', 'Dữ liệu chưa hợp lệ.' ),
            'db_error'         => array( 'error', 'Không thể ghi dữ liệu vào database.' ),
        );

        if ( ! isset( $messages[ $code ] ) ) {
            return null;
        }

        $extra = isset( $_GET['notice_extra'] ) ? sanitize_text_field( wp_unslash( $_GET['notice_extra'] ) ) : '';

        return array(
            'type'    => $messages[ $code ][0],
            'message' => trim( $messages[ $code ][1] . ' ' . $extra ),
        );
    }

    private static function redirect_to_profiles( $args = array() ) {
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

    private static function redirect_to_departments( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-departments' ),
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

    private static function redirect_to_positions( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-positions' ),
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

    private static function redirect_to_factory_locations( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-factory-locations' ),
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

    private static function redirect_to_contract_types( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-contract-types' ),
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

    private static function redirect_to_approval_flows( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-approval-flows' ),
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

    private static function redirect_to_inventory( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-inventory' ),
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

    private static function redirect_to_annual_allowances( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-annual-allowances' ),
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

    private static function redirect_to_product_categories( $args = array() ) {
        $url = add_query_arg(
            array_filter(
                array_merge(
                    array( 'page' => 'tvn-ums-product-categories' ),
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
                    array( 'page' => 'tvn-ums-organization' ),
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
}
