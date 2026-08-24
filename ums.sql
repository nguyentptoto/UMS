-- 1. BANG DANH MUC PHONG BAN
CREATE TABLE `wp_uniform_departments` (
    `department_id` INT AUTO_INCREMENT NOT NULL,
    `department_code` VARCHAR(50) NOT NULL,
    `department_name` VARCHAR(150) NOT NULL,
    `department_group` VARCHAR(150) NOT NULL DEFAULT '',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`department_id`),
    UNIQUE KEY `idx_department_code` (`department_code`),
    KEY `idx_department_name` (`department_name`),
    KEY `idx_department_group` (`department_group`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. BANG DANH MUC CHUC DANH
CREATE TABLE `wp_uniform_positions` (
    `position_id` INT AUTO_INCREMENT NOT NULL,
    `position_code` VARCHAR(50) NOT NULL,
    `position_name` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`position_id`),
    UNIQUE KEY `idx_position_code` (`position_code`),
    KEY `idx_position_name` (`position_name`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BANG DANH MUC NHA MAY / DIA DIEM LAM VIEC
CREATE TABLE `wp_uniform_factory_locations` (
    `factory_location_id` INT AUTO_INCREMENT NOT NULL,
    `factory_location_code` VARCHAR(50) NOT NULL,
    `factory_location_name` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`factory_location_id`),
    UNIQUE KEY `idx_factory_location_code` (`factory_location_code`),
    KEY `idx_factory_location_name` (`factory_location_name`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BANG DANH MUC LOAI HOP DONG
CREATE TABLE `wp_uniform_contract_types` (
    `contract_type_id` INT AUTO_INCREMENT NOT NULL,
    `contract_type_code` VARCHAR(50) NOT NULL,
    `contract_type_name` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`contract_type_id`),
    UNIQUE KEY `idx_contract_type_code` (`contract_type_code`),
    KEY `idx_contract_type_name` (`contract_type_name`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. BANG CHUOI LUONG DUYET DONG THEO PHONG BAN
CREATE TABLE `wp_uniform_department_approval_flows` (
    `flow_id` INT AUTO_INCREMENT NOT NULL,
    `department_id` INT NOT NULL,
    `step_order` INT NOT NULL,
    `step_name` VARCHAR(150) NOT NULL,
    `approver_profile_ids` JSON NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`flow_id`),
    UNIQUE KEY `idx_department_step` (`department_id`, `step_order`),
    KEY `idx_department_id` (`department_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. BANG HO SO NHAN SU MO RONG
CREATE TABLE `wp_uniform_user_profiles` (
    `profile_id` INT AUTO_INCREMENT NOT NULL,
    `user_id` BIGINT(20) UNSIGNED NOT NULL,
    `employee_code` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `gender` ENUM('Nam', 'Nữ') NOT NULL,
    `factory_location` VARCHAR(150) NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `job_position` VARCHAR(100) NOT NULL,
    `contract_type` VARCHAR(150) NOT NULL,
    `date_joined` DATE NOT NULL,
    `resignation_date` DATE DEFAULT NULL,
    `transfer_date` DATE DEFAULT NULL,
    `is_maternity` TINYINT(1) NOT NULL DEFAULT 0,
    `is_outdoor_worker` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`profile_id`),
    KEY `idx_user_id` (`user_id`),
    UNIQUE KEY `idx_employee_code` (`employee_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BANG DANH MUC SAN PHAM CHA-CON
CREATE TABLE `wp_uniform_product_categories` (
    `category_id` INT AUTO_INCREMENT NOT NULL,
    `parent_id` INT NOT NULL DEFAULT 0,
    `category_name` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`category_id`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_category_name` (`category_name`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BANG DANH MUC SAN PHAM VA TONG KHO
CREATE TABLE `wp_uniform_inventory` (
    `item_id` INT AUTO_INCREMENT NOT NULL,
    `category_id` INT DEFAULT NULL,
    `item_type` VARCHAR(100) NOT NULL,
    `item_variant` VARCHAR(100) DEFAULT NULL,
    `size` VARCHAR(20) NOT NULL,
    `color_code` VARCHAR(50) NOT NULL,
    `stock_qty` INT NOT NULL DEFAULT 0,
    `base_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Don gia dung chung cua san pham, dong bo tren moi size',
    PRIMARY KEY (`item_id`),
    KEY `idx_category_id` (`category_id`),
	KEY `idx_product` (`category_id`, `item_variant`),
    KEY `idx_item_type` (`item_type`),
    KEY `idx_stock_qty` (`stock_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8A. MASTER MA SAP DONG PHUC IMPORT TU FILE GA
CREATE TABLE `wp_uniform_sap_import_batches` (
    `batch_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_hash` CHAR(64) NOT NULL,
    `import_status` VARCHAR(20) NOT NULL DEFAULT 'processing' COMMENT 'processing, completed, failed',
    `total_rows` INT NOT NULL DEFAULT 0,
    `inserted_rows` INT NOT NULL DEFAULT 0,
    `updated_rows` INT NOT NULL DEFAULT 0,
    `deactivated_rows` INT NOT NULL DEFAULT 0,
    `warning_count` INT NOT NULL DEFAULT 0,
    `warnings_log` LONGTEXT DEFAULT NULL,
    `imported_by` BIGINT(20) UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`batch_id`),
    KEY `idx_file_hash` (`file_hash`),
    KEY `idx_import_status` (`import_status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wp_uniform_sap_materials` (
    `material_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    `source_key` CHAR(64) NOT NULL COMMENT 'Khoa on dinh sinh tu cot Loai trong file GA',
    `sap_code` VARCHAR(30) NOT NULL,
    `item_name` VARCHAR(255) NOT NULL COMMENT 'Cot Loai trong sheet Ma dong phuc',
    `product_name` VARCHAR(150) NOT NULL COMMENT 'Cot Loai dong phuc len PR',
    `size` VARCHAR(20) NOT NULL DEFAULT '',
    `inventory_item_id` INT NOT NULL COMMENT 'Dong san pham/size tuong ung trong uniform_inventory',
    `mapping_status` VARCHAR(30) NOT NULL DEFAULT 'valid' COMMENT 'valid, duplicate_sap',
    `source_row` INT NOT NULL,
    `source_batch_id` BIGINT(20) UNSIGNED NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`material_id`),
    UNIQUE KEY `idx_source_key` (`source_key`),
    KEY `idx_sap_code` (`sap_code`),
    KEY `idx_product_size` (`product_name`, `size`),
    KEY `idx_inventory_item_id` (`inventory_item_id`),
    KEY `idx_mapping_status` (`mapping_status`),
    KEY `idx_source_batch_id` (`source_batch_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BANG DINH MUC CAP PHAT HANG NAM
CREATE TABLE `wp_uniform_annual_allowance_rules` (
    `rule_id` INT AUTO_INCREMENT NOT NULL,
    `rule_key` CHAR(64) DEFAULT NULL COMMENT 'Khóa duy nhất sinh từ nguồn import và điều kiện áp dụng',
    `rule_scope` VARCHAR(30) NOT NULL DEFAULT 'annual' COMMENT 'annual, newcomer, newcomer_september, maternity, special',
    `apply_type` VARCHAR(20) NOT NULL DEFAULT 'item' COMMENT 'category, item, product',
    `category_id` INT DEFAULT NULL,
    `item_id` INT DEFAULT NULL,
    `item_variant` VARCHAR(100) DEFAULT NULL COMMENT 'Tên sản phẩm áp dụng cho toàn bộ size trong danh mục',
    `source_product_name` VARCHAR(150) DEFAULT NULL,
    `target_type` VARCHAR(30) NOT NULL DEFAULT 'all' COMMENT 'all, position, organization',
    `position_id` INT DEFAULT NULL,
    `department` VARCHAR(255) NOT NULL DEFAULT '',
    `team` VARCHAR(255) NOT NULL DEFAULT '',
    `cost_center` VARCHAR(100) NOT NULL DEFAULT '',
    `position_code` VARCHAR(100) NOT NULL DEFAULT '',
    `employment_start_md` CHAR(5) DEFAULT NULL COMMENT 'MM-DD, dùng cho CNV mới',
    `employment_end_md` CHAR(5) DEFAULT NULL COMMENT 'MM-DD, dùng cho CNV mới; hỗ trợ khoảng qua năm',
    `eligibility_note` VARCHAR(255) DEFAULT NULL,
    `frequency_count` INT NOT NULL DEFAULT 1,
    `frequency_years` INT NOT NULL DEFAULT 1,
    `monthly_quantities` JSON NOT NULL,
    `priority` INT NOT NULL DEFAULT 0,
    `source_batch_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`rule_id`),
    UNIQUE KEY `idx_rule_key` (`rule_key`),
    KEY `idx_rule_scope` (`rule_scope`),
    KEY `idx_apply_type` (`apply_type`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_item_id` (`item_id`),
    KEY `idx_product_group` (`category_id`, `item_variant`),
    KEY `idx_target_type` (`target_type`),
    KEY `idx_position_id` (`position_id`),
    KEY `idx_org_department` (`department`(100)),
    KEY `idx_org_team` (`team`(100)),
    KEY `idx_org_cost_center` (`cost_center`),
    KEY `idx_org_position` (`position_code`),
    KEY `idx_source_batch_id` (`source_batch_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9A. BANG THEO DOI CAC LAN IMPORT DINH MUC TU EXCEL
CREATE TABLE `wp_uniform_allowance_import_batches` (
    `batch_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_hash` CHAR(64) NOT NULL,
    `import_status` VARCHAR(20) NOT NULL DEFAULT 'processing' COMMENT 'processing, completed, failed',
    `total_rules` INT NOT NULL DEFAULT 0,
    `inserted_rules` INT NOT NULL DEFAULT 0,
    `updated_rules` INT NOT NULL DEFAULT 0,
    `error_count` INT NOT NULL DEFAULT 0,
    `error_log` LONGTEXT DEFAULT NULL,
    `imported_by` BIGINT(20) UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`batch_id`),
    KEY `idx_file_hash` (`file_hash`),
    KEY `idx_import_status` (`import_status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BANG LICH SU NHAP/XUAT/DIEU CHINH KHO
CREATE TABLE `wp_uniform_inventory_import_batches` (
    `batch_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_hash` CHAR(64) NOT NULL,
    `import_status` VARCHAR(20) NOT NULL DEFAULT 'processing' COMMENT 'processing, completed, failed',
    `total_rows` INT NOT NULL DEFAULT 0,
    `imported_rows` INT NOT NULL DEFAULT 0,
    `total_quantity` INT NOT NULL DEFAULT 0,
    `error_count` INT NOT NULL DEFAULT 0,
    `error_log` LONGTEXT DEFAULT NULL,
    `imported_by` BIGINT(20) UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`batch_id`),
    KEY `idx_file_hash` (`file_hash`),
    KEY `idx_import_status` (`import_status`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wp_uniform_inventory_movements` (
    `movement_id` INT AUTO_INCREMENT NOT NULL,
    `item_id` INT NOT NULL,
    `request_id` INT DEFAULT NULL,
    `movement_type` VARCHAR(30) NOT NULL COMMENT 'in, out, adjust, request_out',
    `quantity` INT NOT NULL,
    `before_qty` INT DEFAULT NULL,
    `after_qty` INT DEFAULT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `actor_user_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `target_user_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `target_employee_no` VARCHAR(100) DEFAULT NULL,
    `note` TEXT DEFAULT NULL,
    `import_batch_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `source_row` INT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`movement_id`),
    KEY `idx_item_id` (`item_id`),
    KEY `idx_request_id` (`request_id`),
    KEY `idx_target_user_id` (`target_user_id`),
    KEY `idx_target_employee_no` (`target_employee_no`),
    KEY `idx_import_batch_id` (`import_batch_id`),
    KEY `idx_movement_type` (`movement_type`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. BANG PHIEU YEU CAU CAP PHAT
CREATE TABLE `wp_uniform_requests` (
    `request_id` INT AUTO_INCREMENT NOT NULL,
    `creator_id` BIGINT(20) UNSIGNED NOT NULL,
    `target_user_id` BIGINT(20) UNSIGNED NOT NULL,
    `request_type` VARCHAR(50) NOT NULL DEFAULT 'Yêu cầu cấp đồng phục',
    `reason_type` TINYINT(1) NOT NULL COMMENT '1: Thay doi vi tri, 2: Do cong viec, 3: Loi ca nhan/khac',
    `reason_detail` TEXT DEFAULT NULL,
    `payment_method` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0: Mien phi, 1: Khau tru luong, 2: Tien mat/Chuyen khoan',
    `current_status` VARCHAR(50) NOT NULL DEFAULT 'pending_step_1',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`request_id`),
    KEY `idx_creator` (`creator_id`),
    KEY `idx_target_user` (`target_user_id`),
    KEY `idx_current_status` (`current_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. BANG CHI TIET PHIEU YEU CAU CAP PHAT
CREATE TABLE `wp_uniform_request_details` (
    `detail_id` INT AUTO_INCREMENT NOT NULL,
    `request_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `price_at_request` DECIMAL(15,2) NOT NULL,
    PRIMARY KEY (`detail_id`),
    KEY `idx_request` (`request_id`),
    KEY `idx_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. BANG NHAT KY PHE DUYET THEO CHUOI LUONG DONG
CREATE TABLE `wp_uniform_approval_logs` (
    `log_id` INT AUTO_INCREMENT NOT NULL,
    `request_id` INT NOT NULL,
    `step_order` INT NOT NULL,
    `approver_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `action` VARCHAR(50) NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `action_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_request_log` (`request_id`),
    KEY `idx_step_order` (`step_order`),
    KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. BANG BIEN BAN HOAN TRA VA THU HOI DONG PHUC
CREATE TABLE `wp_uniform_returns` (
    `return_id` INT AUTO_INCREMENT NOT NULL,
    `return_type` ENUM('Nghỉ việc', 'Chuyển bộ phận') NOT NULL,
    `target_user_id` BIGINT(20) UNSIGNED NOT NULL,
    `creator_id` BIGINT(20) UNSIGNED NOT NULL,
    `expected_items` JSON NOT NULL COMMENT 'Danh sach do bat buoc phai tra tinh tu lich su',
    `actual_items` JSON NOT NULL COMMENT 'Danh sach do thuc te thu hoi tai kho',
    `penalty_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Tien phat neu thieu do',
    `payment_status` ENUM('Chưa thu', 'Đã thu', 'Khấu trừ vào lương') NOT NULL DEFAULT 'Chưa thu',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`return_id`),
    KEY `idx_return_target` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. BANG DU LIEU SO DO TO CHUC TVN DONG BO TU GOOGLE SHEET
CREATE TABLE `wp_uniform_organization_employees` (
    `source_id` BIGINT(20) UNSIGNED NOT NULL,
    `sheet_stt` INT DEFAULT NULL,
    `source_version` INT NOT NULL DEFAULT 0,
    `employee_no` VARCHAR(255) DEFAULT NULL,
    `full_name` VARCHAR(255) DEFAULT NULL,
    `division` VARCHAR(255) DEFAULT NULL,
    `department` VARCHAR(255) DEFAULT NULL,
    `section` VARCHAR(255) DEFAULT NULL,
    `team` VARCHAR(255) DEFAULT NULL,
    `position` VARCHAR(50) DEFAULT NULL,
    `cost_center` VARCHAR(100) DEFAULT NULL,
    `date_joined` DATE DEFAULT NULL,
    `previous_position` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `factory` VARCHAR(255) DEFAULT NULL,
    `source_created_at` DATETIME DEFAULT NULL,
    `source_updated_at` DATETIME DEFAULT NULL,
    `synced_at` DATETIME NOT NULL,
    `sync_token` CHAR(32) NOT NULL,
    PRIMARY KEY (`source_id`),
    KEY `idx_employee_no` (`employee_no`(50)),
    UNIQUE KEY `idx_employee_no_unique` (`employee_no`(50)),
    KEY `idx_sheet_stt` (`sheet_stt`),
    KEY `idx_division` (`division`(100)),
    KEY `idx_department` (`department`(100)),
    KEY `idx_cost_center` (`cost_center`),
    KEY `idx_date_joined` (`date_joined`),
    KEY `idx_factory` (`factory`(100)),
    KEY `idx_source_updated_at` (`source_updated_at`),
    KEY `idx_synced_at` (`synced_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
