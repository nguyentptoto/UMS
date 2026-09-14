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
    `rule_scope` VARCHAR(30) NOT NULL DEFAULT 'annual' COMMENT 'annual, newcomer, newcomer_september, newcomer_september_override, newcomer_shoe_april, newcomer_shoe_september, special_work_april, special_work_september, maternity, special',
    `apply_type` VARCHAR(20) NOT NULL DEFAULT 'item' COMMENT 'category, item, product, matrix',
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
	`special_work_type` VARCHAR(500) NOT NULL DEFAULT '' COMMENT 'Loại công việc dùng cho ma trận đặc thù T4/T9',
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
	KEY `idx_special_work_type` (`special_work_type`(191)),
    KEY `idx_source_batch_id` (`source_batch_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9B. GAN DINH MUC CONG VIEC DAC THU CHO TUNG NHAN VIEN/KY
CREATE TABLE `wp_uniform_special_work_assignments` (
	`assignment_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
	`employee_no` VARCHAR(50) NOT NULL,
	`period_month` TINYINT UNSIGNED NOT NULL COMMENT '4 hoac 9',
	`special_work_type` VARCHAR(500) NOT NULL,
	`is_active` TINYINT(1) NOT NULL DEFAULT 1,
	`updated_by` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`assignment_id`),
	UNIQUE KEY `idx_employee_period` (`employee_no`, `period_month`),
	KEY `idx_period_work_type` (`period_month`, `special_work_type`(191)),
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

-- 10A. KET QUA TINH SO LUONG CAP PHAT DUNG CHO PR
CREATE TABLE IF NOT EXISTS `wp_uniform_allocation_calculation_batches` (
    `batch_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    `calculation_year` SMALLINT UNSIGNED NOT NULL,
    `period_month` TINYINT UNSIGNED NOT NULL COMMENT '4 hoac 9',
    `file_name` VARCHAR(255) NOT NULL,
    `file_hash` CHAR(64) NOT NULL,
    `employee_count` INT NOT NULL DEFAULT 0,
    `detail_count` INT NOT NULL DEFAULT 0,
    `requested_qty` INT NOT NULL DEFAULT 0,
    `allocated_qty` INT NOT NULL DEFAULT 0,
    `warning_count` INT NOT NULL DEFAULT 0,
    `warnings_log` LONGTEXT DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `calculated_by` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`batch_id`),
    KEY `idx_period_active` (`calculation_year`, `period_month`, `is_active`),
    KEY `idx_file_hash` (`file_hash`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_uniform_allocation_calculation_details` (
    `detail_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    `batch_id` BIGINT(20) UNSIGNED NOT NULL,
    `source_row` INT NOT NULL DEFAULT 0,
    `employee_no` VARCHAR(50) NOT NULL,
    `item_id` INT NOT NULL,
    `requested_quantity` INT NOT NULL DEFAULT 0,
    `allocated_quantity` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`detail_id`),
    KEY `idx_batch_id` (`batch_id`),
    KEY `idx_employee_no` (`employee_no`),
    KEY `idx_item_id` (`item_id`),
    KEY `idx_batch_item` (`batch_id`, `item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `wp_uniform_inventory_movements` (
    `movement_id` INT AUTO_INCREMENT NOT NULL,
    `item_id` INT NOT NULL,
    `request_id` INT DEFAULT NULL,
    `movement_type` VARCHAR(30) NOT NULL COMMENT 'in, out, adjust, request_out, return_in',
    `quantity` INT NOT NULL,
    `before_qty` INT DEFAULT NULL,
    `after_qty` INT DEFAULT NULL,
    `unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `actor_user_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `target_user_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `target_employee_no` VARCHAR(100) DEFAULT NULL,
	`target_name_snapshot` VARCHAR(255) DEFAULT NULL,
	`target_department_snapshot` VARCHAR(255) DEFAULT NULL,
	`target_date_joined_snapshot` DATE DEFAULT NULL,
	`target_position_snapshot` VARCHAR(100) DEFAULT NULL,
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
    KEY `idx_created_at` (`created_at`),
    KEY `idx_allowance_employee_history` (`target_employee_no`, `movement_type`, `created_at`),
    KEY `idx_allowance_user_history` (`target_user_id`, `movement_type`, `created_at`)
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
    KEY `idx_current_status` (`current_status`),
    KEY `idx_allowance_request_history` (`target_user_id`, `created_at`, `current_status`)
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
	`first_contract_date` DATE DEFAULT NULL,
    `previous_position` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `factory` VARCHAR(255) DEFAULT NULL,
    `source_created_at` DATETIME DEFAULT NULL,
    `source_updated_at` DATETIME DEFAULT NULL,
    `synced_at` DATETIME NOT NULL,
    `sync_token` CHAR(32) NOT NULL,
	`employment_status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active, left',
	`last_seen_at` DATETIME DEFAULT NULL,
	`left_detected_at` DATETIME DEFAULT NULL,
    PRIMARY KEY (`source_id`),
    KEY `idx_employee_no` (`employee_no`(50)),
    UNIQUE KEY `idx_employee_no_unique` (`employee_no`(50)),
    KEY `idx_sheet_stt` (`sheet_stt`),
    KEY `idx_division` (`division`(100)),
    KEY `idx_department` (`department`(100)),
    KEY `idx_cost_center` (`cost_center`),
    KEY `idx_date_joined` (`date_joined`),
	KEY `idx_first_contract_date` (`first_contract_date`),
    KEY `idx_factory` (`factory`(100)),
    KEY `idx_source_updated_at` (`source_updated_at`),
    KEY `idx_synced_at` (`synced_at`),
	KEY `idx_employment_status` (`employment_status`),
	KEY `idx_left_detected_at` (`left_detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. HO SO CNV NGHI VIEC VA CHI TIET HOAN TRA
CREATE TABLE IF NOT EXISTS `wp_uniform_employee_exit_cases` (
	`exit_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
	`employee_no` VARCHAR(100) NOT NULL,
	`full_name` VARCHAR(255) DEFAULT NULL,
	`department` VARCHAR(255) DEFAULT NULL,
	`team` VARCHAR(255) DEFAULT NULL,
	`cost_center` VARCHAR(100) DEFAULT NULL,
	`position` VARCHAR(50) DEFAULT NULL,
	`date_joined` DATE DEFAULT NULL,
	`first_contract_date` DATE DEFAULT NULL,
	`employee_type` VARCHAR(30) NOT NULL COMMENT 'probation, official, labor_leasing',
	`detected_at` DATETIME NOT NULL,
	`actual_leave_date` DATE NOT NULL,
	`status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, in_progress, completed, cancelled',
	`notes` TEXT DEFAULT NULL,
	`organization_source_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_by` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	`completed_at` DATETIME DEFAULT NULL,
	PRIMARY KEY (`exit_id`),
	KEY `idx_exit_employee` (`employee_no`),
	KEY `idx_exit_status` (`status`),
	KEY `idx_exit_type` (`employee_type`),
	KEY `idx_exit_detected` (`detected_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wp_uniform_employee_exit_items` (
	`return_item_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
	`exit_id` BIGINT(20) UNSIGNED NOT NULL,
	`item_id` INT NOT NULL DEFAULT 0 COMMENT '0 cho the nhan vien va day deo the',
	`item_group` VARCHAR(30) NOT NULL,
	`item_name` VARCHAR(255) NOT NULL,
	`size` VARCHAR(20) NOT NULL DEFAULT '',
	`issued_quantity` INT NOT NULL DEFAULT 0,
	`required_quantity` INT NOT NULL DEFAULT 0,
	`returned_quantity` INT NOT NULL DEFAULT 0,
	`exempt_quantity` INT NOT NULL DEFAULT 0,
	`reusable_quantity` INT NOT NULL DEFAULT 0,
	`restocked_quantity` INT NOT NULL DEFAULT 0,
	`exemption_reason` VARCHAR(255) DEFAULT NULL,
	`latest_issued_at` DATETIME DEFAULT NULL,
	`display_order` INT NOT NULL DEFAULT 0,
	`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (`return_item_id`),
	KEY `idx_exit_item_case` (`exit_id`),
	KEY `idx_exit_inventory_item` (`item_id`),
	KEY `idx_exit_item_group` (`item_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- UPDATE CHO DATABASE DA TON TAI: DINH MUC CNV MOI VA GIAY N+1
-- Chay rieng khoi ALTER nay neu wp_uniform_annual_allowance_rules da ton tai.
-- Khong xoa va khong thay doi du lieu rule hien co.
-- ============================================================
ALTER TABLE `wp_uniform_annual_allowance_rules`
    MODIFY COLUMN `rule_scope` VARCHAR(30) NOT NULL DEFAULT 'annual'
        COMMENT 'annual, newcomer, newcomer_september, newcomer_september_override, newcomer_shoe_april, newcomer_shoe_september, special_work_april, special_work_september, maternity, special',
    MODIFY COLUMN `apply_type` VARCHAR(20) NOT NULL DEFAULT 'item'
        COMMENT 'category, item, product, matrix';

-- UPDATE CHO DATABASE DA TON TAI: DINH MUC CONG VIEC DAC THU T4/T9
ALTER TABLE `wp_uniform_annual_allowance_rules`
	ADD COLUMN IF NOT EXISTS `special_work_type` VARCHAR(500) NOT NULL DEFAULT ''
		COMMENT 'Loai cong viec dung cho ma tran dac thu T4/T9' AFTER `position_code`,
	ADD INDEX IF NOT EXISTS `idx_special_work_type` (`special_work_type`(191));

CREATE TABLE IF NOT EXISTS `wp_uniform_special_work_assignments` (
	`assignment_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
	`employee_no` VARCHAR(50) NOT NULL,
	`period_month` TINYINT UNSIGNED NOT NULL COMMENT '4 hoac 9',
	`special_work_type` VARCHAR(500) NOT NULL,
	`is_active` TINYINT(1) NOT NULL DEFAULT 1,
	`updated_by` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
	`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	PRIMARY KEY (`assignment_id`),
	UNIQUE KEY `idx_employee_period` (`employee_no`, `period_month`),
	KEY `idx_period_work_type` (`period_month`, `special_work_type`(191)),
	KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- UPDATE CHO DATABASE DA TON TAI: NGAY KY HOP DONG DAU TIEN TU SO DO TO CHUC
ALTER TABLE `wp_uniform_organization_employees`
	ADD COLUMN IF NOT EXISTS `first_contract_date` DATE DEFAULT NULL AFTER `date_joined`,
	ADD INDEX IF NOT EXISTS `idx_first_contract_date` (`first_contract_date`);

-- UPDATE CHO DATABASE DA TON TAI: QUAN LY CNV NGHI VIEC
ALTER TABLE `wp_uniform_organization_employees`
	ADD COLUMN IF NOT EXISTS `employment_status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `sync_token`,
	ADD COLUMN IF NOT EXISTS `last_seen_at` DATETIME DEFAULT NULL AFTER `employment_status`,
	ADD COLUMN IF NOT EXISTS `left_detected_at` DATETIME DEFAULT NULL AFTER `last_seen_at`,
	ADD INDEX IF NOT EXISTS `idx_employment_status` (`employment_status`),
	ADD INDEX IF NOT EXISTS `idx_left_detected_at` (`left_detected_at`);

UPDATE `wp_uniform_organization_employees`
SET `last_seen_at` = COALESCE(`last_seen_at`, `synced_at`)
WHERE `employment_status` = 'active';

-- UPDATE CHO DATABASE DA TON TAI: LUU THONG TIN CNV KHI CAP PHAT NGAY DAU
ALTER TABLE `wp_uniform_inventory_movements`
	ADD COLUMN IF NOT EXISTS `target_name_snapshot` VARCHAR(255) DEFAULT NULL AFTER `target_employee_no`,
	ADD COLUMN IF NOT EXISTS `target_department_snapshot` VARCHAR(255) DEFAULT NULL AFTER `target_name_snapshot`,
	ADD COLUMN IF NOT EXISTS `target_date_joined_snapshot` DATE DEFAULT NULL AFTER `target_department_snapshot`,
	ADD COLUMN IF NOT EXISTS `target_position_snapshot` VARCHAR(100) DEFAULT NULL AFTER `target_date_joined_snapshot`;
