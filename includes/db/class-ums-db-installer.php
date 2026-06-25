<?php
/**
 * Tạo/cập nhật các bảng database tối thiểu khi kích hoạt plugin.
 */
class UMS_DB_Installer {

    const DB_VERSION = '2.3.0';

    /**
     * Chạy khi plugin được kích hoạt.
     */
    public static function activate() {
        self::create_departments_table();
        self::create_department_approval_flows_table();
        self::create_product_categories_table();
        self::create_user_profiles_table();
        self::create_inventory_table();
        self::create_requests_table();
        self::create_request_details_table();
        self::create_approval_logs_table();
        self::migrate_user_profiles_primary_key();
        self::migrate_user_profiles_wp_users();
        self::migrate_product_category_parent_id();
        self::migrate_product_category_code_optional();
        self::migrate_inventory_category_id();
        self::migrate_inventory_base_price_precision();
        self::migrate_approval_flow_approvers_json();
        update_option( 'ums_db_version', self::DB_VERSION );
    }

    /**
     * Bảng danh mục phòng ban phục vụ hồ sơ nhân sự và luồng tạo phiếu hộ.
     */
    private static function create_departments_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_departments';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            department_id INT NOT NULL AUTO_INCREMENT,
            department_code VARCHAR(50) NOT NULL,
            department_name VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (department_id),
            UNIQUE KEY idx_department_code (department_code),
            KEY idx_department_name (department_name),
            KEY idx_is_active (is_active)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Bảng chuỗi luồng duyệt động theo phòng ban.
     */
    private static function create_department_approval_flows_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_department_approval_flows';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            flow_id INT NOT NULL AUTO_INCREMENT,
            department_id INT NOT NULL,
            step_order INT NOT NULL,
            step_name VARCHAR(150) NOT NULL,
            approver_profile_ids JSON NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (flow_id),
            UNIQUE KEY idx_department_step (department_id, step_order),
            KEY idx_department_id (department_id),
            KEY idx_is_active (is_active)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Bảng danh mục sản phẩm cha-con.
     */
    private static function create_product_categories_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_product_categories';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            category_id INT NOT NULL AUTO_INCREMENT,
            parent_id INT NOT NULL DEFAULT 0,
            category_name VARCHAR(150) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (category_id),
            KEY idx_parent_id (parent_id),
            KEY idx_category_name (category_name),
            KEY idx_is_active (is_active)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Bảng hồ sơ nhân sự phục vụ chức năng Quản lý Hồ sơ Nhân sự.
     */
    private static function create_user_profiles_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_user_profiles';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            profile_id INT NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            employee_code VARCHAR(50) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            gender ENUM('Nam', 'Nữ') NOT NULL,
            factory_location ENUM('Đông Anh', 'Hưng Yên', 'Vĩnh Phúc') NOT NULL,
            department VARCHAR(100) NOT NULL,
            job_position VARCHAR(100) NOT NULL,
            contract_type ENUM('Thử việc', 'Tập nghề', 'Tập nghề biên phiên dịch', 'Thuê lại lao động', 'Hợp đồng lao động') NOT NULL,
            date_joined DATE NOT NULL,
            resignation_date DATE DEFAULT NULL,
            transfer_date DATE DEFAULT NULL,
            is_maternity TINYINT(1) NOT NULL DEFAULT 0,
            is_outdoor_worker TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (profile_id),
            KEY idx_user_id (user_id),
            UNIQUE KEY idx_employee_code (employee_code)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Bảng danh mục sản phẩm và tổng kho.
     */
    private static function create_inventory_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_inventory';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            item_id INT NOT NULL AUTO_INCREMENT,
            category_id INT DEFAULT NULL,
            item_type VARCHAR(100) NOT NULL,
            item_variant VARCHAR(100) DEFAULT NULL,
            size VARCHAR(20) NOT NULL,
            color_code VARCHAR(50) NOT NULL,
            stock_qty INT NOT NULL DEFAULT 0,
            base_price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (item_id),
            KEY idx_category_id (category_id),
            KEY idx_item_type (item_type),
            KEY idx_stock_qty (stock_qty)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    private static function create_requests_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_requests';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            request_id INT NOT NULL AUTO_INCREMENT,
            creator_id BIGINT(20) UNSIGNED NOT NULL,
            target_user_id BIGINT(20) UNSIGNED NOT NULL,
            request_type VARCHAR(50) NOT NULL DEFAULT 'Phát sinh',
            reason_type TINYINT(1) NOT NULL,
            reason_detail TEXT DEFAULT NULL,
            payment_method TINYINT(1) NOT NULL DEFAULT 0,
            current_status VARCHAR(50) NOT NULL DEFAULT 'pending_step_1',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (request_id),
            KEY idx_creator (creator_id),
            KEY idx_target_user (target_user_id),
            KEY idx_current_status (current_status)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    private static function create_request_details_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_request_details';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            detail_id INT NOT NULL AUTO_INCREMENT,
            request_id INT NOT NULL,
            item_id INT NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            price_at_request DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (detail_id),
            KEY idx_request (request_id),
            KEY idx_item (item_id)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    private static function create_approval_logs_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'uniform_approval_logs';
        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql = "CREATE TABLE $table_name (
            log_id INT NOT NULL AUTO_INCREMENT,
            request_id INT NOT NULL,
            step_order INT NOT NULL,
            approver_id BIGINT(20) UNSIGNED DEFAULT NULL,
            action VARCHAR(50) NOT NULL,
            comment TEXT DEFAULT NULL,
            action_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (log_id),
            KEY idx_request_log (request_id),
            KEY idx_step_order (step_order),
            KEY idx_action (action)
        ) $charset_collate;";

        dbDelta( $sql );
    }

    /**
     * Bổ sung category_id cho bảng kho cũ nếu chưa có.
     */
    private static function migrate_inventory_category_id() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'uniform_inventory';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $category_id_column = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'category_id'" );
        if ( ! $category_id_column ) {
            $wpdb->query( "ALTER TABLE $table_name ADD category_id INT NULL DEFAULT NULL AFTER item_id" );
        }

        $category_id_index = $wpdb->get_var( "SHOW INDEX FROM $table_name WHERE Key_name = 'idx_category_id'" );
        if ( ! $category_id_index ) {
            $wpdb->query( "ALTER TABLE $table_name ADD KEY idx_category_id (category_id)" );
        }
    }

    /**
     * Mở rộng độ lớn đơn giá để không bị MySQL cắt về ngưỡng 99,999,999.99.
     */
    private static function migrate_inventory_base_price_precision() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'uniform_inventory';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $base_price_column = $wpdb->get_row( "SHOW COLUMNS FROM $table_name LIKE 'base_price'", ARRAY_A );
        if ( $base_price_column ) {
            $wpdb->query( "ALTER TABLE $table_name MODIFY base_price DECIMAL(15,2) NOT NULL DEFAULT 0.00" );
        }
    }

    /**
     * Cho phép một bước duyệt có nhiều người, chỉ chặn trùng cùng người trong cùng bước.
     */
    private static function migrate_approval_flow_approvers_json() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'uniform_department_approval_flows';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $json_column = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'approver_profile_ids'" );
        if ( ! $json_column ) {
            $wpdb->query( "ALTER TABLE $table_name ADD approver_profile_ids JSON NULL AFTER step_name" );
        }

        $old_single_column = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'approver_profile_id'" );
        if ( $old_single_column ) {
            $rows = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY department_id ASC, step_order ASC, flow_id ASC", ARRAY_A );
            $groups = array();
            foreach ( $rows as $row ) {
                $key = $row['department_id'] . ':' . $row['step_order'];
                if ( ! isset( $groups[ $key ] ) ) {
                    $groups[ $key ] = array(
                        'keep_flow_id' => (int) $row['flow_id'],
                        'approvers'    => array(),
                        'delete_ids'   => array(),
                    );
                } else {
                    $groups[ $key ]['delete_ids'][] = (int) $row['flow_id'];
                }

                $groups[ $key ]['approvers'][] = (int) $row['approver_profile_id'];
            }

            foreach ( $groups as $group ) {
                $approvers_json = wp_json_encode( array_values( array_unique( array_filter( $group['approvers'] ) ) ) );
                $wpdb->update(
                    $table_name,
                    array( 'approver_profile_ids' => $approvers_json ),
                    array( 'flow_id' => $group['keep_flow_id'] ),
                    array( '%s' ),
                    array( '%d' )
                );

                foreach ( $group['delete_ids'] as $delete_id ) {
                    $wpdb->delete( $table_name, array( 'flow_id' => $delete_id ), array( '%d' ) );
                }
            }

            $old_approver_key = $wpdb->get_var( "SHOW INDEX FROM $table_name WHERE Key_name = 'idx_approver_profile_id'" );
            if ( $old_approver_key ) {
                $wpdb->query( "ALTER TABLE $table_name DROP INDEX idx_approver_profile_id" );
            }
            $wpdb->query( "ALTER TABLE $table_name DROP COLUMN approver_profile_id" );
        }

        $old_multi_key = $wpdb->get_var( "SHOW INDEX FROM $table_name WHERE Key_name = 'idx_department_step_approver'" );
        if ( $old_multi_key ) {
            $wpdb->query( "ALTER TABLE $table_name DROP INDEX idx_department_step_approver" );
        }

        $step_key = $wpdb->get_var( "SHOW INDEX FROM $table_name WHERE Key_name = 'idx_department_step'" );
        if ( ! $step_key ) {
            $wpdb->query( "ALTER TABLE $table_name ADD UNIQUE KEY idx_department_step (department_id, step_order)" );
        }

        $wpdb->query( "UPDATE $table_name SET approver_profile_ids = '[]' WHERE approver_profile_ids IS NULL" );
        $wpdb->query( "ALTER TABLE $table_name MODIFY approver_profile_ids JSON NOT NULL" );
    }

    /**
     * Chuẩn hóa danh mục cha bằng parent_id = 0.
     */
    private static function migrate_product_category_parent_id() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'uniform_product_categories';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $parent_id_column = $wpdb->get_row( "SHOW COLUMNS FROM $table_name LIKE 'parent_id'", ARRAY_A );
        if ( $parent_id_column ) {
            $wpdb->query( "UPDATE $table_name SET parent_id = 0 WHERE parent_id IS NULL" );
            $wpdb->query( "ALTER TABLE $table_name MODIFY parent_id INT NOT NULL DEFAULT 0" );
        }
    }

    /**
     * Chuyển bảng hồ sơ cũ từ khóa user_id sang profile_id độc lập.
     */
    private static function migrate_user_profiles_primary_key() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'uniform_user_profiles';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $profile_id_column = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'profile_id'" );
        $primary_index     = $wpdb->get_row( "SHOW INDEX FROM $table_name WHERE Key_name = 'PRIMARY'", ARRAY_A );
        $primary_column    = $primary_index && isset( $primary_index['Column_name'] ) ? $primary_index['Column_name'] : '';

        if ( ! $profile_id_column ) {
            if ( $primary_column ) {
                $wpdb->query( "ALTER TABLE $table_name DROP PRIMARY KEY" );
            }
            $wpdb->query( "ALTER TABLE $table_name ADD profile_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST" );
        } elseif ( $primary_column !== 'profile_id' ) {
            if ( $primary_column ) {
                $wpdb->query( "ALTER TABLE $table_name DROP PRIMARY KEY" );
            }
            $wpdb->query( "ALTER TABLE $table_name MODIFY profile_id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (profile_id)" );
        }

        $user_id_column = $wpdb->get_row( "SHOW COLUMNS FROM $table_name LIKE 'user_id'", ARRAY_A );
        if ( $user_id_column && strtoupper( $user_id_column['Null'] ) === 'NO' ) {
            $wpdb->query( "ALTER TABLE $table_name MODIFY user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL" );
        }

        $user_id_index = $wpdb->get_var( "SHOW INDEX FROM $table_name WHERE Key_name = 'idx_user_id'" );
        if ( ! $user_id_index ) {
            $wpdb->query( "ALTER TABLE $table_name ADD KEY idx_user_id (user_id)" );
        }
    }

    /**
     * Bổ sung trạng thái tài khoản nội bộ và mật khẩu hash mặc định cho hồ sơ nhân sự.
     */
    private static function migrate_user_profiles_wp_users() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'uniform_user_profiles';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $rows = $wpdb->get_results( "SELECT * FROM $table_name WHERE user_id IS NULL OR user_id = 0", ARRAY_A );
        foreach ( $rows as $row ) {
            $user_login = sanitize_user( $row['employee_code'], true );
            if ( $user_login === '' ) {
                continue;
            }

            $existing_user_id = username_exists( $user_login );
            if ( ! $existing_user_id ) {
                $existing_user_id = wp_insert_user( array(
                    'user_login'   => $user_login,
                    'user_pass'    => '12345678',
                    'display_name' => $row['full_name'],
                    'nickname'     => $row['full_name'],
                    'role'         => 'subscriber',
                    'user_status'  => isset( $row['account_status'] ) && $row['account_status'] === 'inactive' ? 1 : 0,
                ) );
            }

            if ( is_wp_error( $existing_user_id ) ) {
                continue;
            }

            $wpdb->update(
                $table_name,
                array( 'user_id' => (int) $existing_user_id ),
                array( 'profile_id' => (int) $row['profile_id'] ),
                array( '%d' ),
                array( '%d' )
            );
        }

        $account_status_column = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'account_status'" );
        if ( $account_status_column ) {
            $wpdb->query( "ALTER TABLE $table_name DROP COLUMN account_status" );
        }

        $password_hash_column = $wpdb->get_var( "SHOW COLUMNS FROM $table_name LIKE 'password_hash'" );
        if ( $password_hash_column ) {
            $wpdb->query( "ALTER TABLE $table_name DROP COLUMN password_hash" );
        }

        $unlinked_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE user_id IS NULL OR user_id = 0" );
        if ( $unlinked_count === 0 ) {
            $wpdb->query( "ALTER TABLE $table_name MODIFY user_id BIGINT(20) UNSIGNED NOT NULL" );
        }
    }

    /**
     * Bản cũ từng có category_code bắt buộc; bản mới không dùng mã danh mục sản phẩm nữa.
     */
    private static function migrate_product_category_code_optional() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'uniform_product_categories';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
            return;
        }

        $category_code_column = $wpdb->get_row( "SHOW COLUMNS FROM $table_name LIKE 'category_code'", ARRAY_A );
        if ( $category_code_column ) {
            $wpdb->query( "ALTER TABLE $table_name MODIFY category_code VARCHAR(50) NULL DEFAULT NULL" );
        }
    }
}
