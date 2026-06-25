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
        add_action( 'admin_post_ums_save_approval_flow', array( __CLASS__, 'handle_save_approval_flow' ) );
        add_action( 'admin_post_ums_delete_approval_flow', array( __CLASS__, 'handle_delete_approval_flow' ) );
        add_action( 'admin_post_ums_save_product_category', array( __CLASS__, 'handle_save_product_category' ) );
        add_action( 'admin_post_ums_delete_product_category', array( __CLASS__, 'handle_delete_product_category' ) );
        add_action( 'admin_post_ums_save_inventory_item', array( __CLASS__, 'handle_save_inventory_item' ) );
        add_action( 'admin_post_ums_delete_inventory_item', array( __CLASS__, 'handle_delete_inventory_item' ) );
        add_action( 'wp_ajax_ums_sync_user_password', array( __CLASS__, 'handle_sync_user_password' ) );
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
    }

    /**
     * Nạp các file CSS và Javascript bổ trợ cho giao diện Admin
     */
    public static function enqueue_admin_assets( $hook ) {
        // Chỉ nạp CSS/JS khi Admin đang đứng đúng trong trang của plugin UMS
        if ( strpos( $hook, 'tvn-uniform-management' ) === false && strpos( $hook, 'tvn-ums-departments' ) === false && strpos( $hook, 'tvn-ums-approval-flows' ) === false && strpos( $hook, 'tvn-ums-inventory' ) === false && strpos( $hook, 'tvn-ums-product-categories' ) === false ) {
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
            array( 'ums-jqx-fluent-css' ), 
            '1.0.0' 
        );

        wp_enqueue_style(
            'ums-jqx-fluent-css',
            UMS_PLUGIN_URL . 'assets/css/jqx.fluent.ums.css',
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
            '1.0.0', 
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
            'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
        );

        $edit_department_id = isset( $_GET['edit_department_id'] ) ? absint( $_GET['edit_department_id'] ) : 0;
        $editing_department = $edit_department_id ? UMS_DB_Department::get_by_id( $edit_department_id ) : null;
        $departments        = UMS_DB_Department::get_all( $filters );
        $notice             = self::get_notice();
        $form_values        = self::get_default_department_values( $editing_department );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-department-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-department-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-department-list.php</p></div>';
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
            'category_id' => isset( $_GET['category_id'] ) ? sanitize_text_field( wp_unslash( $_GET['category_id'] ) ) : '',
            'stock'       => isset( $_GET['stock'] ) ? sanitize_key( wp_unslash( $_GET['stock'] ) ) : '',
        );

        $edit_item_id   = isset( $_GET['edit_item_id'] ) ? absint( $_GET['edit_item_id'] ) : 0;
        $editing_item   = $edit_item_id ? UMS_DB_Inventory::get_by_id( $edit_item_id ) : null;
        $inventory      = UMS_DB_Inventory::get_all( $filters );
        $category_tree  = UMS_DB_Product_Category::get_tree();
        $child_categories = UMS_DB_Product_Category::get_child_categories();
        $notice         = self::get_notice();
        $form_values    = self::get_default_inventory_values( $editing_item );

        if ( file_exists( UMS_PLUGIN_DIR . 'admin/partials/view-inventory-list.php' ) ) {
            include_once UMS_PLUGIN_DIR . 'admin/partials/view-inventory-list.php';
        } else {
            echo '<div class="notice notice-error"><p>Lỗi: Không tìm thấy file view-inventory-list.php</p></div>';
        }
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

        $department_id = $data['department_id'];
        unset( $data['department_id'] );

        $result = $is_edit
            ? UMS_DB_Department::update( $department_id, $data )
            : UMS_DB_Department::insert( $data );

        if ( $result === false ) {
            self::redirect_to_departments( array(
                'notice'             => 'db_error',
                'notice_extra'       => UMS_DB_Department::get_last_error(),
                'edit_department_id' => $is_edit ? $department_id : null,
            ) );
        }

        self::redirect_to_departments( array( 'notice' => $is_edit ? 'department_updated' : 'department_created' ) );
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

        $item_id = $data['item_id'];
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
            'color_code'   => isset( $raw['color_code'] ) ? sanitize_text_field( $raw['color_code'] ) : '',
            'stock_qty'    => isset( $raw['stock_qty'] ) ? (int) $raw['stock_qty'] : 0,
            'base_price'   => isset( $raw['base_price'] ) ? self::normalize_money_value( $raw['base_price'] ) : 0,
        );
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

        if ( ! in_array( $data['factory_location'], UMS_DB_User::FACTORY_LOCATIONS, true ) ) {
            $errors[] = 'Nhà máy không hợp lệ.';
        }

        if ( ! in_array( $data['contract_type'], UMS_DB_User::CONTRACT_TYPES, true ) ) {
            $errors[] = 'Loại hợp đồng không hợp lệ.';
        }

        if ( ! in_array( $data['account_status'], array( 'active', 'inactive' ), true ) ) {
            $errors[] = 'Trạng thái tài khoản không hợp lệ.';
        }

        if ( ! self::is_known_department( $data['department'] ) ) {
            $errors[] = 'Phòng ban chưa có trong danh mục hoặc đang ngừng sử dụng.';
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

        if ( UMS_DB_Department::code_exists( $data['department_code'], $is_edit ? $data['department_id'] : 0 ) ) {
            $errors[] = 'Mã phòng ban đã tồn tại.';
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
            $errors[] = 'Vui lòng chọn danh mục con cho sản phẩm.';
        } else {
            $category = UMS_DB_Product_Category::get_by_id( $data['category_id'] );
            if ( ! $category || empty( $category['parent_id'] ) || (int) $category['is_active'] !== 1 ) {
                $errors[] = 'Sản phẩm phải thuộc một danh mục con đang sử dụng.';
            }
        }

        foreach ( array( 'size' ) as $field ) {
            if ( $data[ $field ] === '' ) {
                $errors[] = 'Vui lòng nhập đầy đủ size.';
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
            $errors[] = 'Biến thể sản phẩm này đã tồn tại trong kho.';
        }

        return array_unique( $errors );
    }

    private static function is_valid_date( $date ) {
        $parsed = DateTime::createFromFormat( 'Y-m-d', $date );
        return $parsed && $parsed->format( 'Y-m-d' ) === $date;
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
        $defaults = array(
            'profile_id'        => 0,
            'user_id'           => null,
            'employee_code'     => '',
            'full_name'         => '',
            'gender'            => 'Nam',
            'factory_location'  => 'Đông Anh',
            'department'        => '',
            'job_position'      => '',
            'contract_type'     => 'Hợp đồng lao động',
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
            'is_active'          => 1,
        );

        return $department ? wp_parse_args( $department, $defaults ) : $defaults;
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

    private static function get_notice() {
        $code = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
        if ( $code === '' ) {
            return null;
        }

        $messages = array(
            'created'          => array( 'success', 'Đã thêm hồ sơ nhân sự mới.' ),
            'updated'          => array( 'success', 'Đã cập nhật hồ sơ nhân sự.' ),
            'deleted'          => array( 'success', 'Đã xóa hồ sơ nhân sự.' ),
            'department_created' => array( 'success', 'Đã thêm phòng ban mới.' ),
            'department_updated' => array( 'success', 'Đã cập nhật phòng ban.' ),
            'department_deleted' => array( 'success', 'Đã xóa phòng ban.' ),
            'approval_flow_created' => array( 'success', 'Đã thêm bước duyệt mới.' ),
            'approval_flow_updated' => array( 'success', 'Đã cập nhật bước duyệt.' ),
            'approval_flow_deleted' => array( 'success', 'Đã xóa bước duyệt.' ),
            'product_category_created' => array( 'success', 'Đã thêm danh mục sản phẩm mới.' ),
            'product_category_updated' => array( 'success', 'Đã cập nhật danh mục sản phẩm.' ),
            'product_category_deleted' => array( 'success', 'Đã xóa danh mục sản phẩm.' ),
            'inventory_created'  => array( 'success', 'Đã thêm sản phẩm/tồn kho mới.' ),
            'inventory_updated'  => array( 'success', 'Đã cập nhật sản phẩm/tồn kho.' ),
            'inventory_deleted'  => array( 'success', 'Đã xóa sản phẩm khỏi danh mục kho.' ),
            'invalid_user'     => array( 'error', 'Không tìm thấy nhân sự cần xử lý.' ),
            'invalid_department' => array( 'error', 'Không tìm thấy phòng ban cần xử lý.' ),
            'invalid_approval_flow' => array( 'error', 'Không tìm thấy bước duyệt cần xử lý.' ),
            'invalid_product_category' => array( 'error', 'Không tìm thấy danh mục sản phẩm cần xử lý.' ),
            'invalid_inventory_item' => array( 'error', 'Không tìm thấy sản phẩm cần xử lý.' ),
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
}
