-- 1. BANG DANH MUC PHONG BAN
CREATE TABLE `wp_uniform_departments` (
    `department_id` INT AUTO_INCREMENT NOT NULL,
    `department_code` VARCHAR(50) NOT NULL,
    `department_name` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`department_id`),
    UNIQUE KEY `idx_department_code` (`department_code`),
    KEY `idx_department_name` (`department_name`),
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
    `base_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (`item_id`),
    KEY `idx_category_id` (`category_id`),
    KEY `idx_item_type` (`item_type`),
    KEY `idx_stock_qty` (`stock_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BANG LICH SU NHAP/XUAT/DIEU CHINH KHO
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
    `note` TEXT DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`movement_id`),
    KEY `idx_item_id` (`item_id`),
    KEY `idx_request_id` (`request_id`),
    KEY `idx_movement_type` (`movement_type`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. BANG PHIEU YEU CAU CAP PHAT
CREATE TABLE `wp_uniform_requests` (
    `request_id` INT AUTO_INCREMENT NOT NULL,
    `creator_id` BIGINT(20) UNSIGNED NOT NULL,
    `target_user_id` BIGINT(20) UNSIGNED NOT NULL,
    `request_type` VARCHAR(50) NOT NULL DEFAULT 'Phát sinh',
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

-- 11. BANG CHI TIET PHIEU YEU CAU CAP PHAT
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

-- 12. BANG NHAT KY PHE DUYET THEO CHUOI LUONG DONG
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

-- 13. BANG BIEN BAN HOAN TRA VA THU HOI DONG PHUC
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
