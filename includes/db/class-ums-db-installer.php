<?php
/**
 * Tạo/cập nhật các bảng database tối thiểu khi kích hoạt plugin.
 */
class UMS_DB_Installer {

    const DB_VERSION = '1.8.0';

    /**
     * Chạy khi plugin được kích hoạt.
     */
    public static function activate() {
        self::create_departments_table();
        self::create_department_approval_flows_table();
        self::create_product_categories_table();
        self::create_user_profiles_table();
        self::create_inventory_table();
        self::migrate_user_profiles_primary_key();
        self::migrate_product_category_parent_id();
        self::migrate_product_category_code_optional();
        self::migrate_inventory_category_id();
        self::migrate_inventory_base_price_precision();
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
            approver_profile_id INT NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (flow_id),
            UNIQUE KEY idx_department_step (department_id, step_order),
            KEY idx_department_id (department_id),
            KEY idx_approver_profile_id (approver_profile_id),
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
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
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
