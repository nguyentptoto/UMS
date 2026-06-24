<?php
/**
 * Frontend user portal for UMS.
 */
class UMS_User {

    public static function init() {
        add_shortcode( 'ums_user_portal', array( __CLASS__, 'render_portal_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
    }

    public static function register_assets() {
        wp_register_style(
            'ums-user-css',
            UMS_PLUGIN_URL . 'user/css/ums-user.css',
            array(),
            '1.0.0'
        );

        wp_register_script(
            'ums-user-js',
            UMS_PLUGIN_URL . 'user/js/ums-user.js',
            array(),
            '1.0.0',
            true
        );
    }

    public static function render_portal_shortcode() {
        wp_enqueue_style( 'ums-user-css' );
        wp_enqueue_script( 'ums-user-js' );

        if ( ! is_user_logged_in() ) {
            return self::render_login_required();
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
        $approval_flows  = $department_id ? UMS_DB_Approval_Flow::get_all(
            array(
                'department_id' => $department_id,
                'status'        => 'active',
            )
        ) : array();

        ob_start();
        include UMS_PLUGIN_DIR . 'user/partials/view-user-portal.php';
        return ob_get_clean();
    }

    private static function render_login_required() {
        ob_start();
        include UMS_PLUGIN_DIR . 'user/partials/view-login-required.php';
        return ob_get_clean();
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

    private static function get_department_by_name( $department_name ) {
        $departments = UMS_DB_Department::get_active();

        foreach ( $departments as $department ) {
            if ( $department['department_name'] === $department_name ) {
                return $department;
            }
        }

        return null;
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
}
