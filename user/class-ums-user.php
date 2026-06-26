<?php
/**
 * Frontend user portal for UMS.
 */
class UMS_User {

    public static function init() {
        add_shortcode( 'ums_user_portal', array( __CLASS__, 'render_portal_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
        add_action( 'admin_post_ums_submit_uniform_request', array( __CLASS__, 'handle_submit_uniform_request' ) );
        add_action( 'admin_post_ums_delete_uniform_request', array( __CLASS__, 'handle_delete_uniform_request' ) );
        add_action( 'admin_post_ums_approve_uniform_request', array( __CLASS__, 'handle_approve_uniform_request' ) );
        add_filter( 'template_include', array( __CLASS__, 'use_standalone_template' ), 99 );
    }

    public static function register_assets() {
        wp_register_style(
            'ums-user-css',
            UMS_PLUGIN_URL . 'user/css/ums-user.css',
            array(),
            '1.0.9'
        );

        wp_register_script(
            'ums-user-js',
            UMS_PLUGIN_URL . 'user/js/ums-user.js',
            array(),
            '1.1.0',
            true
        );
    }

    public static function render_portal_shortcode() {
        try {
            return self::render_portal();
        } catch ( Throwable $error ) {
            error_log( 'UMS user portal error: ' . $error->getMessage() );
            return self::render_portal_error( $error->getMessage() );
        }
    }

    public static function use_standalone_template( $template ) {
        if ( is_admin() || ! is_singular() || ! self::current_page_has_portal_shortcode() ) {
            return $template;
        }

        $standalone_template = UMS_PLUGIN_DIR . 'user/templates/standalone-portal.php';
        return file_exists( $standalone_template ) ? $standalone_template : $template;
    }

    public static function current_page_has_portal_shortcode() {
        $post = get_post();
        if ( ! $post || empty( $post->post_content ) ) {
            return false;
        }

        return has_shortcode( $post->post_content, 'ums_user_portal' );
    }

    public static function enqueue_portal_assets() {
        self::register_assets();
        wp_enqueue_style( 'ums-user-css' );
        self::enqueue_late_portal_css();
        wp_enqueue_script( 'ums-user-js' );
    }

    public static function handle_submit_uniform_request() {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        check_admin_referer( 'ums_submit_uniform_request' );

        $current_user_id = get_current_user_id();
        $profile         = UMS_DB_User::get_by_wp_user_id( $current_user_id );
        $redirect_url    = isset( $_POST['portal_url'] ) ? esc_url_raw( wp_unslash( $_POST['portal_url'] ) ) : home_url();

        if ( ! $profile || ! empty( $profile['user_status'] ) ) {
            self::redirect_with_notice( $redirect_url, 'request_invalid_profile' );
        }

        $department    = self::get_department_by_name( $profile['department'] );
        $department_id = $department ? (int) $department['department_id'] : 0;
        $flows         = $department_id ? UMS_DB_Approval_Flow::get_all(
            array(
                'department_id' => $department_id,
                'status'        => 'active',
            )
        ) : array();

        if ( ! self::can_create_request( $profile, $flows ) ) {
            self::redirect_with_notice( $redirect_url, 'request_no_permission' );
        }

        $raw_target_user_id = isset( $_POST['target_user_id'] ) ? absint( $_POST['target_user_id'] ) : 0;
        $target_profile     = self::get_active_teammate_by_user_id( $profile, $raw_target_user_id );
        if ( ! $target_profile ) {
            self::redirect_with_notice( $redirect_url, 'request_invalid_target' );
        }

        $raw_items = isset( $_POST['request_items'] ) && is_array( $_POST['request_items'] )
            ? wp_unslash( $_POST['request_items'] )
            : array();
        $details = self::sanitize_request_items( $raw_items );
        if ( empty( $details ) ) {
            self::redirect_with_notice( $redirect_url, 'request_empty_items' );
        }

        $reason_type = isset( $_POST['reason_type'] ) ? absint( $_POST['reason_type'] ) : 0;
        if ( ! in_array( $reason_type, array( 1, 2, 3 ), true ) ) {
            self::redirect_with_notice( $redirect_url, 'request_invalid_reason' );
        }

        $payment_method = 0;
        if ( $reason_type === 3 ) {
            $raw_payment = isset( $_POST['payment_method'] ) ? sanitize_key( wp_unslash( $_POST['payment_method'] ) ) : '';
            if ( $raw_payment === 'salary' ) {
                $payment_method = 1;
            } elseif ( $raw_payment === 'direct' ) {
                $payment_method = 2;
            } else {
                self::redirect_with_notice( $redirect_url, 'request_invalid_payment' );
            }
        }

        $edit_request_id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $request_data    = array(
            'creator_id'     => $current_user_id,
            'target_user_id' => (int) $target_profile['user_id'],
            'request_type'   => 'Phát sinh',
            'reason_type'    => $reason_type,
            'reason_detail'  => isset( $_POST['reason_detail'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason_detail'] ) ) : '',
            'payment_method' => $payment_method,
            'current_status' => self::get_initial_pending_status( $flows ),
        );

        if ( $edit_request_id > 0 ) {
            $editing_request = UMS_DB_Request::get_by_id( $edit_request_id );
            if ( ! self::can_edit_created_request( $editing_request, $current_user_id ) ) {
                self::redirect_with_notice( $redirect_url, 'request_not_editable' );
            }

            $updated = UMS_DB_Request::update_with_details( $edit_request_id, $request_data, $details );
            if ( ! $updated ) {
                self::redirect_with_notice( $redirect_url, 'request_db_error' );
            }

            self::redirect_with_notice( $redirect_url, 'request_updated', array( 'request_id' => $edit_request_id, 'ums_page' => 'my-requests' ) );
        }

        $request_id = UMS_DB_Request::insert_with_details( $request_data, $details );

        if ( ! $request_id ) {
            self::redirect_with_notice( $redirect_url, 'request_db_error' );
        }

        self::redirect_with_notice( $redirect_url, 'request_submitted', array( 'request_id' => $request_id, 'ums_page' => 'my-requests' ) );
    }

    public static function handle_delete_uniform_request() {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $request_id   = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;
        $redirect_url = isset( $_GET['portal_url'] ) ? esc_url_raw( wp_unslash( $_GET['portal_url'] ) ) : home_url();
        check_admin_referer( 'ums_delete_uniform_request_' . $request_id );

        $request = UMS_DB_Request::get_by_id( $request_id );
        if ( ! self::can_edit_created_request( $request, get_current_user_id() ) ) {
            self::redirect_with_notice( $redirect_url, 'request_not_editable', array( 'ums_page' => 'my-requests' ) );
        }

        $deleted = UMS_DB_Request::delete_request( $request_id );
        self::redirect_with_notice( $redirect_url, $deleted ? 'request_deleted' : 'request_db_error', array( 'ums_page' => 'my-requests' ) );
    }

    public static function handle_approve_uniform_request() {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $request_id   = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $redirect_url = isset( $_POST['portal_url'] ) ? esc_url_raw( wp_unslash( $_POST['portal_url'] ) ) : home_url();
        check_admin_referer( 'ums_approve_uniform_request_' . $request_id );

        $current_user_id = get_current_user_id();
        $profile         = UMS_DB_User::get_by_wp_user_id( $current_user_id );
        $request         = UMS_DB_Request::get_by_id( $request_id );

        if ( ! $profile || ! $request ) {
            self::redirect_with_notice( $redirect_url, 'request_invalid_profile', array( 'ums_page' => 'my-requests' ) );
        }

        $step_order = self::get_status_step_order( $request['current_status'] );
        if ( $step_order <= 1 ) {
            self::redirect_with_notice( $redirect_url, 'request_not_approvable', array( 'ums_page' => 'my-requests' ) );
        }

        $target_profile = UMS_DB_User::get_by_wp_user_id( (int) $request['target_user_id'] );
        $department     = $target_profile ? self::get_department_by_name( $target_profile['department'] ) : null;
        $flows          = $department ? self::prepare_approval_flows(
            UMS_DB_Approval_Flow::get_all(
                array(
                    'department_id' => (int) $department['department_id'],
                    'status'        => 'active',
                )
            )
        ) : array();

        if ( ! self::can_approve_step( $profile, $flows, $step_order ) ) {
            self::redirect_with_notice( $redirect_url, 'request_not_approvable', array( 'ums_page' => 'my-requests' ) );
        }

        $next_status = self::get_next_status_after_approval( $flows, $step_order );
        UMS_DB_Request::add_log( $request_id, $step_order, $current_user_id, 'approved', 'Đã duyệt bước ' . $step_order . '.' );
        $updated = UMS_DB_Request::update_status( $request_id, $next_status );

        self::redirect_with_notice( $redirect_url, $updated === false ? 'request_db_error' : 'request_approved', array( 'ums_page' => 'my-requests' ) );
    }

    private static function render_portal() {
        self::enqueue_portal_assets();

        if ( ! is_user_logged_in() ) {
            if ( ! headers_sent() ) {
                wp_safe_redirect( wp_login_url( get_permalink() ) );
                exit;
            }

            return '';
        }

        $current_user_id = get_current_user_id();
        $profile         = UMS_DB_User::get_by_wp_user_id( $current_user_id );

        if ( ! $profile ) {
            return self::render_missing_profile();
        }

        if ( ! empty( $profile['user_status'] ) ) {
            return self::render_inactive_account();
        }

        $department      = self::get_department_by_name( $profile['department'] );
        $department_id   = $department ? (int) $department['department_id'] : 0;
        $teammates       = self::get_active_teammates( $profile );
        $inventory_items = UMS_DB_Inventory::get_all( array( 'stock' => 'available' ) );
        $category_tree   = self::get_active_product_category_tree();
        $approval_flows  = $department_id ? UMS_DB_Approval_Flow::get_all(
            array(
                'department_id' => $department_id,
                'status'        => 'active',
            )
        ) : array();
        $approval_flows     = self::prepare_approval_flows( $approval_flows );
        $can_create_request = self::can_create_request( $profile, $approval_flows );
        $portal_pages       = self::get_portal_pages( $can_create_request );
        $current_page       = self::get_current_page( $portal_pages );
        $page_template      = self::get_page_template( $current_page, $portal_pages );
        $portal_url         = get_permalink();
        $current_user       = wp_get_current_user();
        $portal_notice      = self::get_portal_notice();
        $my_requests        = UMS_DB_Request::get_all( array( 'creator_id' => $current_user_id ) );
        $approval_requests  = self::get_requests_waiting_for_profile_approval( $profile, $approval_flows );
        $editing_request    = self::get_editing_request_for_form( $current_user_id );

        ob_start();
        include UMS_PLUGIN_DIR . 'user/partials/view-user-portal.php';
        return ob_get_clean();
    }

    private static function sanitize_request_items( $raw_items ) {
        $details = array();

        foreach ( $raw_items as $raw_item ) {
            if ( ! is_array( $raw_item ) ) {
                continue;
            }

            $item_id  = isset( $raw_item['inventory_item_id'] ) ? absint( $raw_item['inventory_item_id'] ) : 0;
            $quantity = isset( $raw_item['quantity'] ) ? max( 1, absint( $raw_item['quantity'] ) ) : 1;

            if ( $item_id <= 0 ) {
                continue;
            }

            $inventory = UMS_DB_Inventory::get_by_id( $item_id );
            if ( ! $inventory || (int) $inventory['stock_qty'] <= 0 ) {
                continue;
            }

            $details[] = array(
                'item_id'          => $item_id,
                'quantity'         => $quantity,
                'price_at_request' => (float) $inventory['base_price'] * $quantity,
            );
        }

        return $details;
    }

    private static function can_create_request( $profile, $approval_flows ) {
        if ( empty( $profile['profile_id'] ) || empty( $approval_flows ) ) {
            return false;
        }

        $first_step = null;
        foreach ( $approval_flows as $flow ) {
            if ( (int) $flow['step_order'] === 1 ) {
                $first_step = $flow;
                break;
            }
        }

        if ( ! $first_step ) {
            return false;
        }

        $approver_ids = json_decode( $first_step['approver_profile_ids'], true );
        if ( ! is_array( $approver_ids ) ) {
            return false;
        }

        return in_array( (int) $profile['profile_id'], array_map( 'intval', $approver_ids ), true );
    }

    private static function get_initial_pending_status( $approval_flows ) {
        $next_step = self::get_next_step_order( $approval_flows, 1 );
        return $next_step ? 'pending_step_' . $next_step : 'completed';
    }

    private static function get_status_step_order( $status ) {
        return preg_match( '/^pending_step_(\d+)$/', (string) $status, $matches ) ? absint( $matches[1] ) : 0;
    }

    private static function get_next_step_order( $approval_flows, $current_step ) {
        $next_step = 0;
        foreach ( $approval_flows as $flow ) {
            $step = (int) $flow['step_order'];
            if ( $step > (int) $current_step && ( $next_step === 0 || $step < $next_step ) ) {
                $next_step = $step;
            }
        }

        return $next_step;
    }

    private static function get_next_status_after_approval( $approval_flows, $current_step ) {
        $next_step = self::get_next_step_order( $approval_flows, $current_step );
        return $next_step ? 'pending_step_' . $next_step : 'completed';
    }

    private static function can_approve_step( $profile, $approval_flows, $step_order ) {
        foreach ( $approval_flows as $flow ) {
            if ( (int) $flow['step_order'] !== (int) $step_order ) {
                continue;
            }

            $approver_ids = json_decode( $flow['approver_profile_ids'], true );
            return is_array( $approver_ids ) && in_array( (int) $profile['profile_id'], array_map( 'intval', $approver_ids ), true );
        }

        return false;
    }

    private static function can_edit_created_request( $request, $creator_user_id ) {
        return $request
            && (int) $request['creator_id'] === (int) $creator_user_id
            && $request['current_status'] === 'pending_step_2';
    }

    private static function get_requests_waiting_for_profile_approval( $profile, $approval_flows ) {
        if ( empty( $profile['profile_id'] ) || empty( $approval_flows ) ) {
            return array();
        }

        $statuses = array();
        foreach ( $approval_flows as $flow ) {
            $step = (int) $flow['step_order'];
            if ( $step <= 1 || ! self::can_approve_step( $profile, $approval_flows, $step ) ) {
                continue;
            }
            $statuses[] = 'pending_step_' . $step;
        }

        if ( empty( $statuses ) ) {
            return array();
        }

        return UMS_DB_Request::get_all(
            array(
                'department' => $profile['department'],
                'status_in'  => array_values( array_unique( $statuses ) ),
            )
        );
    }

    private static function get_editing_request_for_form( $current_user_id ) {
        $edit_request_id = isset( $_GET['edit_request_id'] ) ? absint( $_GET['edit_request_id'] ) : 0;
        if ( $edit_request_id <= 0 ) {
            return null;
        }

        $request = UMS_DB_Request::get_by_id( $edit_request_id );
        if ( ! self::can_edit_created_request( $request, $current_user_id ) ) {
            return null;
        }

        $request['details'] = UMS_DB_Request::get_details( $edit_request_id );
        return $request;
    }

    private static function get_active_teammate_by_user_id( $profile, $user_id ) {
        foreach ( self::get_active_teammates( $profile ) as $teammate ) {
            if ( (int) $teammate['user_id'] === (int) $user_id ) {
                return $teammate;
            }
        }

        return null;
    }

    private static function redirect_with_notice( $redirect_url, $notice, $extra = array() ) {
        $args = array_merge(
            array(
                'ums_page'   => 'request',
                'ums_notice' => $notice,
            ),
            $extra
        );

        wp_safe_redirect( add_query_arg( $args, $redirect_url ) );
        exit;
    }

    private static function get_portal_notice() {
        $notice = isset( $_GET['ums_notice'] ) ? sanitize_key( wp_unslash( $_GET['ums_notice'] ) ) : '';
        if ( $notice === '' ) {
            return null;
        }

        $messages = array(
            'request_submitted'       => array( 'success', 'Đã gửi phiếu vào luồng duyệt bước 1.' ),
            'request_invalid_profile' => array( 'error', 'Hồ sơ của bạn không hợp lệ hoặc tài khoản đang bị khóa.' ),
            'request_no_permission'   => array( 'error', 'Bạn không thuộc bước 1 của luồng duyệt nên không có quyền tạo yêu cầu.' ),
            'request_invalid_target'  => array( 'error', 'Người nhận đồng phục không hợp lệ.' ),
            'request_empty_items'     => array( 'error', 'Vui lòng chọn ít nhất một dòng đồng phục hợp lệ.' ),
            'request_invalid_reason'  => array( 'error', 'Lý do yêu cầu không hợp lệ.' ),
            'request_invalid_payment' => array( 'error', 'Vui lòng chọn phương thức thanh toán cho Lý do 3.' ),
            'request_db_error'        => array( 'error', 'Không lưu được phiếu yêu cầu. Vui lòng thử lại.' ),
        );

        if ( ! isset( $messages[ $notice ] ) ) {
            return null;
        }

        return array(
            'type'    => $messages[ $notice ][0],
            'message' => $messages[ $notice ][1],
        );
    }

    private static function render_portal_error( $message ) {
        ob_start();
        include UMS_PLUGIN_DIR . 'user/partials/view-portal-error.php';
        return ob_get_clean();
    }

    private static function enqueue_late_portal_css() {
        $css_file = UMS_PLUGIN_DIR . 'user/css/ums-user.css';
        if ( file_exists( $css_file ) ) {
            wp_add_inline_style( 'ums-user-css', file_get_contents( $css_file ) );
        }
    }

    private static function render_missing_profile() {
        ob_start();
        include UMS_PLUGIN_DIR . 'user/partials/view-missing-profile.php';
        return ob_get_clean();
    }

    private static function render_inactive_account() {
        ob_start();
        include UMS_PLUGIN_DIR . 'user/partials/view-inactive-account.php';
        return ob_get_clean();
    }

    private static function get_portal_pages( $can_create_request ) {
        $pages = array(
            'dashboard' => array(
                'label' => 'Tổng quan',
                'file'  => 'page-dashboard.php',
            ),
        );

        if ( $can_create_request ) {
            $pages['request'] = array(
                'label' => 'Tạo yêu cầu',
                'file'  => 'page-request.php',
            );
        }

        $pages['approval-flow'] = array(
            'label' => 'Luồng duyệt',
            'file'  => 'page-approval-flow.php',
        );
        $pages['profile'] = array(
            'label' => 'Hồ sơ của tôi',
            'file'  => 'page-profile.php',
        );

        return $pages;
    }

    private static function get_current_page( $portal_pages ) {
        $page = isset( $_GET['ums_page'] ) ? sanitize_key( wp_unslash( $_GET['ums_page'] ) ) : 'dashboard';
        return isset( $portal_pages[ $page ] ) ? $page : 'dashboard';
    }

    private static function get_page_template( $current_page, $portal_pages ) {
        $file = isset( $portal_pages[ $current_page ]['file'] ) ? $portal_pages[ $current_page ]['file'] : 'page-dashboard.php';
        $path = UMS_PLUGIN_DIR . 'user/partials/pages/' . $file;

        return file_exists( $path ) ? $path : UMS_PLUGIN_DIR . 'user/partials/pages/page-dashboard.php';
    }

    private static function get_department_by_name( $department_name ) {
        $departments = UMS_DB_Department::get_active();

        foreach ( $departments as $department ) {
            if ( $department['department_name'] === $department_name ) {
                return $department;
            }
        }

        return null;
    }

    private static function get_active_product_category_tree() {
        $tree = UMS_DB_Product_Category::get_tree();

        foreach ( $tree as $parent_id => $parent ) {
            if ( (int) $parent['is_active'] !== 1 ) {
                unset( $tree[ $parent_id ] );
                continue;
            }

            $tree[ $parent_id ]['children'] = array_values(
                array_filter(
                    isset( $parent['children'] ) ? $parent['children'] : array(),
                    function ( $child ) {
                        return (int) $child['is_active'] === 1;
                    }
                )
            );
        }

        return $tree;
    }

    private static function get_active_teammates( $profile ) {
        $users = UMS_DB_User::get_all(
            array(
                'department' => $profile['department'],
                'status'     => 'active',
            )
        );

        return array_values(
            array_filter(
                $users,
                function ( $user ) {
                    return empty( $user['user_status'] );
                }
            )
        );
    }

    public static function format_approver_names( $approver_profile_ids ) {
        $approver_ids = json_decode( $approver_profile_ids, true );
        if ( ! is_array( $approver_ids ) || empty( $approver_ids ) ) {
            return 'Chưa chọn người duyệt';
        }

        $names = array();
        foreach ( $approver_ids as $approver_id ) {
            $approver = UMS_DB_User::get_by_id( absint( $approver_id ) );
            if ( $approver ) {
                $names[] = $approver['full_name'];
            }
        }

        return ! empty( $names ) ? implode( ', ', $names ) : 'Chưa chọn người duyệt';
    }

    private static function prepare_approval_flows( $approval_flows ) {
        foreach ( $approval_flows as $index => $flow ) {
            $approval_flows[ $index ]['approver_names'] = self::format_approver_names( $flow['approver_profile_ids'] );
        }

        return $approval_flows;
    }
}
