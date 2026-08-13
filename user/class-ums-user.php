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
        add_action( 'admin_post_ums_reject_uniform_request', array( __CLASS__, 'handle_reject_uniform_request' ) );
        add_action( 'admin_init', array( __CLASS__, 'redirect_user_from_wp_admin' ) );
        add_filter( 'template_include', array( __CLASS__, 'use_standalone_template' ), 99 );
        add_filter( 'login_redirect', array( __CLASS__, 'redirect_user_after_login' ), 20, 3 );
    }

    public static function register_assets() {
        wp_register_style(
            'ums-jqx-base-css',
            UMS_PLUGIN_URL . 'assets/css/jqx.base.ums.css',
            array(),
            '1.0.0'
        );

        wp_register_style(
            'ums-jqx-energyblue-css',
            UMS_PLUGIN_URL . 'assets/css/jqx.energyblue.css',
            array( 'ums-jqx-base-css' ),
            '1.0.0'
        );

        wp_register_style(
            'ums-user-css',
            UMS_PLUGIN_URL . 'user/css/ums-user.css',
            array( 'ums-jqx-energyblue-css' ),
            '1.2.1'
        );

        wp_register_script(
            'ums-jqx-all',
            UMS_PLUGIN_URL . 'assets/js/jqx-all.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_register_script(
            'ums-user-js',
            UMS_PLUGIN_URL . 'user/js/ums-user.js',
            array( 'jquery', 'ums-jqx-all' ),
            '1.1.8',
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

    public static function redirect_user_after_login( $redirect_to, $requested_redirect_to, $user ) {
        if ( is_wp_error( $user ) || ! $user instanceof WP_User ) {
            return $redirect_to;
        }

        if ( user_can( $user, 'manage_options' ) ) {
            return $redirect_to;
        }

        $profile = UMS_DB_User::get_by_wp_user_id( (int) $user->ID );
        if ( ! $profile ) {
            return $redirect_to;
        }

        return home_url( '/' );
    }

    public static function redirect_user_from_wp_admin() {
        if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( wp_doing_ajax() ) {
            return;
        }

        global $pagenow;
        if ( in_array( $pagenow, array( 'admin-post.php', 'admin-ajax.php' ), true ) ) {
            return;
        }

        $profile = UMS_DB_User::get_by_wp_user_id( get_current_user_id() );
        if ( ! $profile ) {
            return;
        }

        wp_safe_redirect( home_url( '/' ) );
        exit;
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
        wp_add_inline_script(
            'jquery',
            'window.$ = window.jQuery;',
            'after'
        );
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
        $allowance_errors = self::validate_request_allowances( $details, $target_profile, $edit_request_id );
        if ( ! empty( $allowance_errors ) ) {
            self::redirect_with_notice(
                $redirect_url,
                'request_allowance_error',
                array(
                    'ums_notice_extra' => implode( ' ', $allowance_errors ),
                )
            );
        }

        $request_data    = array(
            'creator_id'     => $current_user_id,
            'target_user_id' => (int) $target_profile['user_id'],
            'request_type'   => 'Yêu cầu cấp đồng phục',
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

            self::send_approval_step_email( $edit_request_id, $request_data, $flows, $request_data['current_status'], $redirect_url );
            self::redirect_with_notice( $redirect_url, 'request_updated', array( 'request_id' => $edit_request_id, 'ums_page' => 'my-requests' ) );
        }

        $request_id = UMS_DB_Request::insert_with_details( $request_data, $details );

        if ( ! $request_id ) {
            self::redirect_with_notice( $redirect_url, 'request_db_error' );
        }

        self::send_approval_step_email( $request_id, $request_data, $flows, $request_data['current_status'], $redirect_url );
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
        $flows          = $department ? UMS_DB_Approval_Flow::get_all(
            array(
                'department_id' => (int) $department['department_id'],
                'status'        => 'active',
            )
        ) : array();

        if ( ! self::can_approve_step( $profile, $flows, $step_order ) ) {
            self::redirect_with_notice( $redirect_url, 'request_not_approvable', array( 'ums_page' => 'my-requests' ) );
        }

        $next_status = self::get_next_status_after_approval( $flows, $step_order );
        if ( $next_status === 'completed' ) {
            $updated = UMS_DB_Request::complete_approved_request( $request_id, $current_user_id );
            if ( $updated ) {
                UMS_DB_Request::add_log( $request_id, $step_order, $current_user_id, 'approved', 'Đã duyệt bước ' . $step_order . ' và ghi nhận xuất kho.' );
            }
        } else {
            $updated = UMS_DB_Request::update_status( $request_id, $next_status );
            if ( $updated !== false ) {
                UMS_DB_Request::add_log( $request_id, $step_order, $current_user_id, 'approved', 'Đã duyệt bước ' . $step_order . '.' );
                $request['current_status'] = $next_status;
                self::send_approval_step_email( $request_id, $request, $flows, $next_status, $redirect_url );
            }
        }

        self::redirect_with_notice( $redirect_url, ! $updated ? 'request_stock_error' : 'request_approved', array( 'ums_page' => 'my-requests' ) );
    }

    public static function handle_reject_uniform_request() {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url() );
            exit;
        }

        $request_id    = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        $redirect_url  = isset( $_POST['portal_url'] ) ? esc_url_raw( wp_unslash( $_POST['portal_url'] ) ) : home_url();
        $reject_reason = isset( $_POST['reject_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reject_reason'] ) ) : '';
        check_admin_referer( 'ums_reject_uniform_request_' . $request_id );

        if ( $reject_reason === '' ) {
            self::redirect_with_notice( $redirect_url, 'request_reject_reason_required', array( 'ums_page' => 'my-requests' ) );
        }

        $current_user_id = get_current_user_id();
        $profile         = UMS_DB_User::get_by_wp_user_id( $current_user_id );
        $request         = UMS_DB_Request::get_by_id( $request_id );

        if ( ! self::can_current_user_approve_request( $request, $profile ) ) {
            self::redirect_with_notice( $redirect_url, 'request_not_approvable', array( 'ums_page' => 'my-requests' ) );
        }

        $step_order = self::get_status_step_order( $request['current_status'] );
        UMS_DB_Request::add_log( $request_id, $step_order, $current_user_id, 'rejected', $reject_reason );
        $updated = UMS_DB_Request::update_status( $request_id, 'rejected' );

        self::redirect_with_notice( $redirect_url, $updated === false ? 'request_db_error' : 'request_rejected', array( 'ums_page' => 'my-requests' ) );
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
        $is_admin_view   = current_user_can( 'manage_options' );

        if ( ! $profile && ! $is_admin_view ) {
            return self::render_missing_profile();
        }

        if ( ! $profile && $is_admin_view ) {
            $profile = self::get_admin_virtual_profile( $current_user_id );
        }

        if ( ! $is_admin_view && ! empty( $profile['user_status'] ) ) {
            return self::render_inactive_account();
        }

        $department     = ! empty( $profile['department'] ) ? self::get_department_by_name( $profile['department'] ) : null;
        $department_id  = $department ? (int) $department['department_id'] : 0;
        $approval_flows = $department_id ? UMS_DB_Approval_Flow::get_all(
            array(
                'department_id' => $department_id,
                'status'        => 'active',
            )
        ) : array();
        $approval_flows      = self::prepare_approval_flows( $approval_flows );
        $can_create_request = self::can_create_request( $profile, $approval_flows );
        $portal_pages       = self::get_portal_pages( $can_create_request );
        $current_page       = self::get_current_page( $portal_pages );
        $page_template      = self::get_page_template( $current_page, $portal_pages );
        $portal_url         = get_permalink();
        $current_user       = wp_get_current_user();
        $portal_notice      = self::get_portal_notice();

        $teammates          = array();
        $inventory_items    = array();
        $category_tree      = array();
        $approval_requests  = array();
        $completed_requests = array();
        $dashboard_stats    = array();
        $editing_request    = null;
        $detail_request     = null;
        $detail_can_approve = false;

        if ( $current_page === 'dashboard' ) {
            $dashboard_stats = $is_admin_view ? self::get_admin_dashboard_stats() : self::get_dashboard_stats( $current_user_id, $profile, $approval_flows );
        }

        if ( $current_page === 'request' ) {
            $teammates       = $is_admin_view ? UMS_DB_User::get_all( array( 'status' => 'active' ) ) : self::get_active_teammates( $profile );
            $inventory_items = UMS_DB_Inventory::get_all( array( 'stock' => 'available' ) );
            $category_tree   = self::get_active_product_category_tree();
            $editing_request = self::get_editing_request_for_form( $current_user_id );
        }

        if ( $current_page === 'my-requests' ) {
            $approval_requests  = $is_admin_view ? self::get_admin_pending_requests() : self::get_pending_requests_for_portal_tab( $current_user_id, $profile, $approval_flows );
            $completed_requests = $is_admin_view ? self::get_admin_completed_requests() : self::get_completed_requests_for_profile( $current_user_id, $profile, $approval_flows );
        }

        if ( $current_page === 'request-detail' ) {
            $detail_request     = self::get_detail_request_for_page( $current_user_id, $profile );
            $detail_can_approve = $detail_request ? self::can_current_user_approve_request( $detail_request, $profile ) : false;
        }

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

    private static function validate_request_allowances( $details, $target_profile, $exclude_request_id = 0 ) {
        $errors      = array();
        $rule_groups = array();
        $position_id = self::get_profile_position_id( $target_profile );
        $allowance_context = UMS_DB_Annual_Allowance::get_employee_context( $target_profile );

        foreach ( $details as $detail ) {
            $item_id   = isset( $detail['item_id'] ) ? absint( $detail['item_id'] ) : 0;
            $quantity  = isset( $detail['quantity'] ) ? max( 1, absint( $detail['quantity'] ) ) : 1;
            $inventory = $item_id ? UMS_DB_Inventory::get_by_id( $item_id ) : null;

            if ( ! $inventory ) {
                $errors[] = 'Có dòng đồng phục không còn tồn tại trong kho.';
                continue;
            }

            $rule = UMS_DB_Annual_Allowance::get_active_rule_for_item( $item_id, $position_id, $allowance_context );
            if ( ! $rule ) {
                $errors[] = 'Sản phẩm "' . self::get_inventory_label( $inventory ) . '" chưa có định mức cấp phát phù hợp với người nhận.';
                continue;
            }

            $rule_id = (int) $rule['rule_id'];
            if ( ! isset( $rule_groups[ $rule_id ] ) ) {
                $rule_groups[ $rule_id ] = array(
                    'rule'     => $rule,
                    'quantity' => 0,
                    'labels'   => array(),
                );
            }

            $rule_groups[ $rule_id ]['quantity'] += $quantity;
            $rule_groups[ $rule_id ]['labels'][]  = self::get_inventory_label( $inventory );
        }

        if ( empty( $rule_groups ) ) {
            return array_unique( $errors );
        }

        $now_timestamp = current_time( 'timestamp' );
        $month         = (int) date( 'n', $now_timestamp );
        $month_start   = date( 'Y-m-01 00:00:00', $now_timestamp );
        $month_end     = date( 'Y-m-t 23:59:59', $now_timestamp );
        $period_end    = date( 'Y-m-d H:i:s', $now_timestamp );

        foreach ( $rule_groups as $group ) {
            $rule       = $group['rule'];
            $labels     = implode( ', ', array_unique( $group['labels'] ) );
            $requested  = max( 1, absint( $group['quantity'] ) );
            $quantities = json_decode( (string) $rule['monthly_quantities'], true );
            $quantities = is_array( $quantities ) ? $quantities : array();
            $month_quota = isset( $quantities[ $month ] ) ? absint( $quantities[ $month ] ) : 0;

            if ( $month_quota <= 0 ) {
                $errors[] = 'Định mức của "' . $labels . '" không cho phép cấp trong tháng ' . $month . '.';
                continue;
            }

            $month_usage = UMS_DB_Request::get_allowance_usage(
                (int) $target_profile['user_id'],
                $rule,
                $month_start,
                $month_end,
                $exclude_request_id
            );
            $manual_month_usage = UMS_DB_Inventory_Movement::get_manual_allowance_usage(
                (int) $target_profile['user_id'],
                $rule,
                $month_start,
                $month_end
            );
            $used_in_month = (int) $month_usage['quantity'] + (int) $manual_month_usage['quantity'];

            if ( $used_in_month + $requested > $month_quota ) {
                $errors[] = 'Số lượng "' . $labels . '" vượt định mức tháng ' . $month . ' (' . $used_in_month . ' đã dùng, yêu cầu thêm ' . $requested . ', tối đa ' . $month_quota . ').';
            }

            $frequency_count = max( 1, absint( $rule['frequency_count'] ?? 1 ) );
            $frequency_years = max( 1, absint( $rule['frequency_years'] ?? 1 ) );
            $period_start    = date( 'Y-m-d H:i:s', strtotime( '-' . $frequency_years . ' years', $now_timestamp ) );
            $period_usage    = UMS_DB_Request::get_allowance_usage(
                (int) $target_profile['user_id'],
                $rule,
                $period_start,
                $period_end,
                $exclude_request_id
            );
            $manual_period_usage = UMS_DB_Inventory_Movement::get_manual_allowance_usage(
                (int) $target_profile['user_id'],
                $rule,
                $period_start,
                $period_end
            );
            $used_times = (int) $period_usage['request_count'] + (int) $manual_period_usage['request_count'];

            if ( $used_times + 1 > $frequency_count ) {
                $errors[] = 'Định mức của "' . $labels . '" chỉ cho phép ' . $frequency_count . ' lần / ' . $frequency_years . ' năm.';
            }
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

    private static function get_inventory_label( $inventory ) {
        $parts = array();

        if ( ! empty( $inventory['parent_category_name'] ) ) {
            $parts[] = $inventory['parent_category_name'];
        }

        if ( ! empty( $inventory['category_name'] ) ) {
            $parts[] = $inventory['category_name'];
        } elseif ( ! empty( $inventory['item_type'] ) ) {
            $parts[] = $inventory['item_type'];
        }

        $label = implode( ' / ', $parts );
        if ( ! empty( $inventory['item_variant'] ) ) {
            $label .= ' - ' . $inventory['item_variant'];
        }
        if ( ! empty( $inventory['size'] ) ) {
            $label .= ' - Size ' . $inventory['size'];
        }

        return $label !== '' ? $label : 'Sản phẩm #' . absint( $inventory['item_id'] );
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

        $approver_ids = self::get_flow_approver_ids( $first_step );
        if ( empty( $approver_ids ) ) {
            return false;
        }

        return in_array( (int) $profile['profile_id'], $approver_ids, true );
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

            return in_array( (int) $profile['profile_id'], self::get_flow_approver_ids( $flow ), true );
        }

        return false;
    }

    private static function can_current_user_approve_request( $request, $profile ) {
        if ( ! $request || ! $profile ) {
            return false;
        }

        $step_order = self::get_status_step_order( $request['current_status'] );
        if ( $step_order <= 1 ) {
            return false;
        }

        $target_profile = UMS_DB_User::get_by_wp_user_id( (int) $request['target_user_id'] );
        $department     = $target_profile ? self::get_department_by_name( $target_profile['department'] ) : null;
        $flows          = $department ? UMS_DB_Approval_Flow::get_all(
            array(
                'department_id' => (int) $department['department_id'],
                'status'        => 'active',
            )
        ) : array();

        return self::can_approve_step( $profile, $flows, $step_order );
    }

    private static function can_edit_created_request( $request, $creator_user_id ) {
        return $request
            && (int) $request['creator_id'] === (int) $creator_user_id
            && in_array( (string) $request['current_status'], array( 'pending_step_2', 'rejected' ), true );
    }

    private static function get_requests_waiting_for_profile_approval( $profile, $approval_flows ) {
        $statuses = self::get_waiting_approval_statuses_for_profile( $profile, $approval_flows );

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

    private static function get_waiting_approval_statuses_for_profile( $profile, $approval_flows ) {
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

        return array_values( array_unique( $statuses ) );
    }

    private static function get_pending_requests_for_creator( $current_user_id ) {
        $requests = UMS_DB_Request::get_all(
            array(
                'creator_id' => $current_user_id,
            )
        );

        return array_values(
            array_filter(
                $requests,
                function ( $request ) {
                    return ! empty( $request['current_status'] )
                        && preg_match( '/^pending_step_\d+$/', (string) $request['current_status'] );
                }
            )
        );
    }

    private static function get_pending_requests_for_portal_tab( $current_user_id, $profile, $approval_flows ) {
        $requests = array();

        foreach ( self::get_pending_requests_for_creator( $current_user_id ) as $request ) {
            $request['_ums_action_mode']              = 'owner';
            $requests[ (int) $request['request_id'] ] = $request;
        }

        foreach ( self::get_requests_waiting_for_profile_approval( $profile, $approval_flows ) as $request ) {
            $request['_ums_action_mode']              = 'approval';
            $requests[ (int) $request['request_id'] ] = $request;
        }

        $requests = array_values( $requests );
        usort(
            $requests,
            function ( $left, $right ) {
                return strtotime( $right['created_at'] ) <=> strtotime( $left['created_at'] );
            }
        );

        return $requests;
    }

    private static function get_dashboard_stats( $current_user_id, $profile, $approval_flows ) {
        $created_counts = UMS_DB_Request::get_status_counts(
            array(
                'creator_id' => $current_user_id,
            )
        );
        $waiting_statuses = self::get_waiting_approval_statuses_for_profile( $profile, $approval_flows );
        $waiting_counts   = empty( $waiting_statuses )
            ? array( 'total' => 0 )
            : UMS_DB_Request::get_status_counts(
                array(
                    'department' => $profile['department'],
                    'status_in'  => $waiting_statuses,
                )
            );

        return array(
            'created_total'    => $created_counts['total'],
            'created_pending'  => $created_counts['pending'],
            'created_done'     => $created_counts['completed'],
            'created_rejected' => $created_counts['rejected'],
            'waiting_approval' => $waiting_counts['total'],
        );
    }

    private static function get_admin_pending_requests() {
        $requests = UMS_DB_Request::get_all( array( 'limit' => 0 ) );

        $requests = array_values(
            array_filter(
                $requests,
                function ( $request ) {
                    return ! empty( $request['current_status'] )
                        && preg_match( '/^pending_step_\d+$/', (string) $request['current_status'] );
                }
            )
        );

        foreach ( $requests as $index => $request ) {
            $requests[ $index ]['_ums_action_mode'] = 'view';
        }

        return $requests;
    }

    private static function get_admin_completed_requests() {
        $requests = UMS_DB_Request::get_all(
            array(
                'status_in' => array( 'completed', 'rejected' ),
                'limit'     => 0,
            )
        );

        foreach ( $requests as $index => $request ) {
            $requests[ $index ]['_ums_action_mode'] = 'view';
        }

        return $requests;
    }

    private static function get_admin_dashboard_stats() {
        $counts = UMS_DB_Request::get_status_counts();

        return array(
            'created_total'    => $counts['total'],
            'created_pending'  => $counts['pending'],
            'created_done'     => $counts['completed'],
            'created_rejected' => $counts['rejected'],
            'waiting_approval' => $counts['pending'],
        );
    }

    private static function get_completed_requests_for_profile( $current_user_id, $profile, $approval_flows ) {
        $requests = UMS_DB_Request::get_all(
            array(
                'creator_id' => $current_user_id,
                'status_in'  => array( 'completed', 'rejected' ),
            )
        );

        $can_approve_in_department = false;
        foreach ( $approval_flows as $flow ) {
            $step = (int) $flow['step_order'];
            if ( $step > 1 && self::can_approve_step( $profile, $approval_flows, $step ) ) {
                $can_approve_in_department = true;
                break;
            }
        }

        if ( $can_approve_in_department ) {
            $requests = array_merge(
                $requests,
                UMS_DB_Request::get_all(
                    array(
                        'department' => $profile['department'],
                        'status_in'  => array( 'completed', 'rejected' ),
                    )
                )
            );
        }

        $unique = array();
        foreach ( $requests as $request ) {
            $unique[ (int) $request['request_id'] ] = $request;
        }

        return array_values( $unique );
    }

    private static function get_admin_virtual_profile( $user_id ) {
        $user = get_userdata( (int) $user_id );

        return array(
            'profile_id'         => 0,
            'user_id'            => (int) $user_id,
            'employee_code'      => 'ADMIN',
            'full_name'          => $user ? ( $user->display_name ?: $user->user_login ) : 'Administrator',
            'gender'             => '-',
            'factory_location'   => 'Tất cả nhà máy',
            'department'         => 'Tất cả phòng ban',
            'job_position'       => 'Quản trị hệ thống',
            'contract_type'      => '-',
            'date_joined'        => current_time( 'Y-m-d' ),
            'resignation_date'   => null,
            'transfer_date'      => null,
            'is_maternity'       => 0,
            'is_outdoor_worker'  => 0,
            'user_status'        => 0,
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

    private static function get_detail_request_for_page( $current_user_id, $profile ) {
        $request_id = isset( $_GET['request_id'] ) ? absint( $_GET['request_id'] ) : 0;
        if ( $request_id <= 0 ) {
            return null;
        }

        $request = UMS_DB_Request::get_by_id( $request_id );
        if ( ! self::can_view_request_detail( $request, $current_user_id, $profile ) ) {
            return null;
        }

        $request['details'] = UMS_DB_Request::get_details( $request_id );
        return $request;
    }

    private static function can_view_request_detail( $request, $current_user_id, $profile ) {
        if ( ! $request ) {
            return false;
        }

        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        if ( empty( $profile['profile_id'] ) ) {
            return false;
        }

        if ( (int) $request['creator_id'] === (int) $current_user_id || (int) $request['target_user_id'] === (int) $current_user_id ) {
            return true;
        }

        if ( in_array( (string) $request['current_status'], array( 'completed', 'rejected' ), true ) ) {
            return self::can_profile_view_department_request_history( $request, $profile );
        }

        return self::can_current_user_approve_request( $request, $profile );
    }

    private static function can_profile_view_department_request_history( $request, $profile ) {
        $target_profile = UMS_DB_User::get_by_wp_user_id( (int) $request['target_user_id'] );
        if ( ! $target_profile || (string) $target_profile['department'] !== (string) $profile['department'] ) {
            return false;
        }

        $department = self::get_department_by_name( $target_profile['department'] );
        if ( ! $department ) {
            return false;
        }

        $flows = UMS_DB_Approval_Flow::get_all(
            array(
                'department_id' => (int) $department['department_id'],
                'status'        => 'active',
            )
        );

        foreach ( $flows as $flow ) {
            $step = (int) $flow['step_order'];
            if ( $step > 1 && self::can_approve_step( $profile, $flows, $step ) ) {
                return true;
            }
        }

        return false;
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
            'request_submitted'       => array( 'success', 'Đã gửi phiếu vào luồng duyệt tiếp theo.' ),
            'request_updated'         => array( 'success', 'Đã cập nhật phiếu yêu cầu.' ),
            'request_deleted'         => array( 'success', 'Đã xóa phiếu yêu cầu.' ),
            'request_approved'        => array( 'success', 'Đã duyệt phiếu và chuyển sang bước tiếp theo.' ),
            'request_rejected'        => array( 'success', 'Đã từ chối phiếu yêu cầu.' ),
            'request_not_editable'    => array( 'error', 'Phiếu này không còn ở trạng thái cho phép sửa hoặc xóa.' ),
            'request_not_approvable'  => array( 'error', 'Bạn không có quyền duyệt phiếu ở bước hiện tại.' ),
            'request_reject_reason_required' => array( 'error', 'Vui lòng nhập lý do từ chối phiếu.' ),
            'request_stock_error'     => array( 'error', 'Không thể ghi nhận xuất kho. Vui lòng kiểm tra tồn kho hoặc lịch sử xuất kho của phiếu.' ),
            'request_invalid_profile' => array( 'error', 'Hồ sơ của bạn không hợp lệ hoặc tài khoản đang bị khóa.' ),
            'request_no_permission'   => array( 'error', 'Bạn không thuộc bước 1 của luồng duyệt nên không có quyền tạo yêu cầu.' ),
            'request_invalid_target'  => array( 'error', 'Người nhận đồng phục không hợp lệ.' ),
            'request_empty_items'     => array( 'error', 'Vui lòng chọn ít nhất một dòng đồng phục hợp lệ.' ),
            'request_invalid_reason'  => array( 'error', 'Lý do yêu cầu không hợp lệ.' ),
            'request_invalid_payment' => array( 'error', 'Vui lòng chọn phương thức thanh toán cho Lý do 3.' ),
            'request_allowance_error' => array( 'error', 'Phiếu chưa phù hợp với định mức cấp phát.' ),
            'request_db_error'        => array( 'error', 'Không lưu được phiếu yêu cầu. Vui lòng thử lại.' ),
        );

        if ( ! isset( $messages[ $notice ] ) ) {
            return null;
        }

        $extra = isset( $_GET['ums_notice_extra'] ) ? sanitize_text_field( wp_unslash( $_GET['ums_notice_extra'] ) ) : '';

        return array(
            'type'    => $messages[ $notice ][0],
            'message' => trim( $messages[ $notice ][1] . ' ' . $extra ),
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

        $pages['my-requests'] = array(
            'label' => 'Phiếu của tôi',
            'file'  => 'page-my-requests.php',
        );

        $pages['request-detail'] = array(
            'label' => 'Chi tiết phiếu',
            'file'  => 'page-request-detail.php',
            'nav'   => false,
        );

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
        $department_name = trim( (string) $department_name );
        if ( $department_name === '' ) {
            return null;
        }

        $departments          = UMS_DB_Department::get_active();
        $normalized_reference = self::normalize_department_identifier( $department_name );
        $compatible_match     = null;
        $compatible_length    = 0;

        foreach ( $departments as $department ) {
            $department_id   = isset( $department['department_id'] ) ? (string) $department['department_id'] : '';
            $department_code = isset( $department['department_code'] ) ? trim( (string) $department['department_code'] ) : '';
            $stored_name     = isset( $department['department_name'] ) ? trim( (string) $department['department_name'] ) : '';
            $normalized_code = self::normalize_department_identifier( $department_code );
            $normalized_name = self::normalize_department_identifier( $stored_name );

            if (
                $stored_name === $department_name
                || $department_code === $department_name
                || $department_id === $department_name
                || ( $normalized_reference !== '' && in_array( $normalized_reference, array( $normalized_name, $normalized_code ), true ) )
            ) {
                return $department;
            }

            // Hồ sơ cũ có thể lưu thêm hậu tố nhà máy, ví dụ "Information Technology DA".
            if (
                $normalized_name !== ''
                && (
                    strpos( $normalized_reference, $normalized_name . '-' ) === 0
                    || strpos( $normalized_name, $normalized_reference . '-' ) === 0
                )
                && strlen( $normalized_name ) > $compatible_length
            ) {
                $compatible_match  = $department;
                $compatible_length = strlen( $normalized_name );
            }
        }

        return $compatible_match;
    }

    private static function normalize_department_identifier( $value ) {
        return trim( sanitize_title( remove_accents( (string) $value ) ), '-' );
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
        $flow         = is_array( $approver_profile_ids ) ? $approver_profile_ids : array( 'approver_profile_ids' => $approver_profile_ids );
        $approver_ids = self::get_flow_approver_ids( $flow );
        if ( empty( $approver_ids ) ) {
            return 'Chưa chọn người duyệt';
        }

        $names = array();
        foreach ( $approver_ids as $approver_id ) {
            $approver = self::get_cached_profile_by_id( absint( $approver_id ) );
            if ( $approver ) {
                $names[] = $approver['full_name'];
            }
        }

        return ! empty( $names ) ? implode( ', ', $names ) : 'Chưa chọn người duyệt';
    }

    private static function get_cached_profile_by_id( $profile_id ) {
        static $profile_cache = array();

        $profile_id = absint( $profile_id );
        if ( $profile_id <= 0 ) {
            return null;
        }

        if ( ! array_key_exists( $profile_id, $profile_cache ) ) {
            $profile_cache[ $profile_id ] = UMS_DB_User::get_by_id( $profile_id );
        }

        return $profile_cache[ $profile_id ];
    }

    private static function get_flow_approver_ids( $flow ) {
        $approver_ids = array();

        if ( ! empty( $flow['approver_profile_ids'] ) ) {
            $decoded = json_decode( (string) $flow['approver_profile_ids'], true );
            if ( is_array( $decoded ) ) {
                $approver_ids = $decoded;
            }
        }

        if ( empty( $approver_ids ) && ! empty( $flow['approver_profile_id'] ) ) {
            $approver_ids = array( $flow['approver_profile_id'] );
        }

        return array_values( array_unique( array_filter( array_map( 'absint', $approver_ids ) ) ) );
    }

    private static function send_approval_step_email( $request_id, $request, $approval_flows, $status, $portal_url ) {
        $step_order = self::get_status_step_order( $status );
        if ( $step_order <= 1 ) {
            return;
        }

        $flow = self::get_flow_by_step( $approval_flows, $step_order );
        if ( ! $flow ) {
            return;
        }

        $approver_ids = self::get_flow_approver_ids( $flow );
        if ( empty( $approver_ids ) ) {
            return;
        }

        $target_profile = UMS_DB_User::get_by_wp_user_id( (int) $request['target_user_id'] );
        $detail_url     = add_query_arg(
            array(
                'ums_page'   => 'request-detail',
                'request_id' => absint( $request_id ),
            ),
            $portal_url
        );

        $subject = '[UMS] Phiếu #' . absint( $request_id ) . ' đang chờ bạn duyệt';
        $message = "Xin chào,\n\n";
        $message .= 'Phiếu yêu cầu cấp đồng phục #' . absint( $request_id ) . ' đã chuyển đến bước "' . $flow['step_name'] . "\".\n";
        if ( $target_profile ) {
            $message .= 'Người nhận: ' . $target_profile['employee_code'] . ' - ' . $target_profile['full_name'] . "\n";
            $message .= 'Phòng ban: ' . $target_profile['department'] . "\n";
        }
        $message .= "\nVui lòng truy cập liên kết sau để xem chi tiết và duyệt phiếu:\n" . esc_url_raw( $detail_url ) . "\n\n";
        $message .= 'UMS - Uniform Management System';

        foreach ( array_unique( array_map( 'absint', $approver_ids ) ) as $approver_profile_id ) {
            $approver = self::get_cached_profile_by_id( $approver_profile_id );
            if ( ! $approver || empty( $approver['user_id'] ) ) {
                continue;
            }

            $wp_user = get_userdata( (int) $approver['user_id'] );
            if ( ! $wp_user || empty( $wp_user->user_email ) || ! is_email( $wp_user->user_email ) ) {
                continue;
            }

            wp_mail( $wp_user->user_email, $subject, $message );
        }
    }

    private static function get_flow_by_step( $approval_flows, $step_order ) {
        foreach ( $approval_flows as $flow ) {
            if ( (int) $flow['step_order'] === (int) $step_order ) {
                return $flow;
            }
        }

        return null;
    }

    private static function prepare_approval_flows( $approval_flows ) {
        foreach ( $approval_flows as $index => $flow ) {
            $approval_flows[ $index ]['approver_names'] = self::format_approver_names( $flow );
        }

        return $approval_flows;
    }
}
