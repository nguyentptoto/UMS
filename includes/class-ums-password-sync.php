<?php
/**
 * Đồng bộ hash mật khẩu WordPress từ hệ thống nguồn bên ngoài.
 */
class UMS_Password_Sync {

    const DEFAULT_PASSWORD = '12345678';

    public static function get_external_db_config() {
        return array(
            'host'     => defined( 'UMS_PASSWORD_SYNC_DB_HOST' ) ? UMS_PASSWORD_SYNC_DB_HOST : '172.30.134.15',
            'user'     => defined( 'UMS_PASSWORD_SYNC_DB_USER' ) ? UMS_PASSWORD_SYNC_DB_USER : 'Tvnsoft',
            'password' => defined( 'UMS_PASSWORD_SYNC_DB_PASSWORD' ) ? UMS_PASSWORD_SYNC_DB_PASSWORD : '',
            'database' => defined( 'UMS_PASSWORD_SYNC_DB_NAME' ) ? UMS_PASSWORD_SYNC_DB_NAME : 'tvnias',
        );
    }

    public static function sync_user_password( $user_id ) {
        $user_id = absint( $user_id );
        $user    = $user_id > 0 ? get_userdata( $user_id ) : null;

        if ( ! $user ) {
            return new WP_Error( 'ums_invalid_user', 'Người dùng không hợp lệ.' );
        }

        $username = (string) $user->user_login;
        if ( $username === '' || ! username_exists( $username ) ) {
            return new WP_Error( 'ums_missing_wp_user', 'Tài khoản WordPress không tồn tại.' );
        }

        if ( ! function_exists( 'mysqli_connect' ) ) {
            return new WP_Error( 'ums_missing_mysqli', 'Máy chủ PHP chưa bật mysqli, không thể kết nối DB đồng bộ mật khẩu.' );
        }

        $config = self::get_external_db_config();
        $conn   = @mysqli_connect( $config['host'], $config['user'], $config['password'], $config['database'] );
        if ( ! $conn ) {
            return new WP_Error( 'ums_external_db_connect_failed', 'Không kết nối được DB đồng bộ mật khẩu: ' . mysqli_connect_error() );
        }

        mysqli_set_charset( $conn, 'utf8mb4' );
        $stmt = mysqli_prepare( $conn, 'SELECT password FROM users WHERE manv = ? AND active = 1 LIMIT 1' );
        if ( ! $stmt ) {
            $error = mysqli_error( $conn );
            mysqli_close( $conn );
            return new WP_Error( 'ums_external_db_prepare_failed', 'Không chuẩn bị được truy vấn DB đồng bộ: ' . $error );
        }

        mysqli_stmt_bind_param( $stmt, 's', $username );
        mysqli_stmt_execute( $stmt );
        mysqli_stmt_bind_result( $stmt, $external_password );
        $found = mysqli_stmt_fetch( $stmt );
        mysqli_stmt_close( $stmt );
        mysqli_close( $conn );

        $external_password = (string) $external_password;
        if ( ! $found || $external_password === '' ) {
            return new WP_Error( 'ums_external_password_not_found', 'Không tìm thấy mật khẩu active cho user_login/manv: ' . $username );
        }

        if ( strlen( $external_password ) > 4096 ) {
            return new WP_Error( 'ums_external_password_invalid', 'Hash mật khẩu từ DB nguồn quá dài hoặc không hợp lệ. Mật khẩu hiện tại được giữ nguyên.' );
        }

        global $wpdb;
        $old_password_hash = (string) ( $user->user_pass ?? '' );
        $updated           = $wpdb->update(
            $wpdb->users,
            array( 'user_pass' => $external_password ),
            array( 'ID' => $user_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            if ( $old_password_hash !== '' ) {
                $wpdb->update(
                    $wpdb->users,
                    array( 'user_pass' => $old_password_hash ),
                    array( 'ID' => $user_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }

            clean_user_cache( $user_id );
            return new WP_Error(
                'ums_password_write_failed',
                'Không ghi được hash mật khẩu vào WordPress. Hệ thống đã giữ lại mật khẩu cũ.',
                array( 'db_error' => $wpdb->last_error )
            );
        }

        clean_user_cache( $user_id );
        update_user_meta( $user_id, 'ums_password_synced_at', current_time( 'mysql', 0 ) );
        update_user_meta( $user_id, 'ums_password_synced_by', get_current_user_id() );

        return array(
            'message' => 'Đã đồng bộ mật khẩu cho tài khoản ' . $username . '. Người dùng cần đăng nhập lại bằng mật khẩu từ hệ thống nguồn.',
            'user_id' => $user_id,
        );
    }

    public static function sync_user_password_with_default_fallback( $user_id ) {
        $result = self::sync_user_password( $user_id );
        if ( ! is_wp_error( $result ) ) {
            $result['source'] = 'external';
            return $result;
        }

        $fallback_codes = array(
            'ums_missing_mysqli',
            'ums_external_db_connect_failed',
            'ums_external_db_prepare_failed',
            'ums_external_password_not_found',
            'ums_external_password_invalid',
        );

        if ( ! in_array( $result->get_error_code(), $fallback_codes, true ) ) {
            return $result;
        }

        return self::set_default_password( $user_id, $result->get_error_message() );
    }

    public static function set_default_password( $user_id, $reason = '' ) {
        $user_id = absint( $user_id );
        $user    = $user_id > 0 ? get_userdata( $user_id ) : null;

        if ( ! $user ) {
            return new WP_Error( 'ums_invalid_user', 'Người dùng không hợp lệ.' );
        }

        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->users,
            array( 'user_pass' => wp_hash_password( self::DEFAULT_PASSWORD ) ),
            array( 'ID' => $user_id ),
            array( '%s' ),
            array( '%d' )
        );

        if ( $updated === false ) {
            clean_user_cache( $user_id );
            return new WP_Error(
                'ums_default_password_write_failed',
                'Không ghi được mật khẩu mặc định vào WordPress.',
                array( 'db_error' => $wpdb->last_error )
            );
        }

        clean_user_cache( $user_id );
        update_user_meta( $user_id, 'ums_password_synced_at', current_time( 'mysql', 0 ) );
        update_user_meta( $user_id, 'ums_password_synced_by', get_current_user_id() );
        update_user_meta( $user_id, 'ums_password_sync_fallback_reason', $reason );

        return array(
            'message' => 'Không đồng bộ được từ DB nguồn nên đã đặt mật khẩu mặc định cho tài khoản ' . $user->user_login . '.',
            'user_id' => $user_id,
            'source'  => 'default',
            'reason'  => $reason,
        );
    }
}
