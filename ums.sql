-- 1. BẢNG DANH MỤC PHÒNG BAN
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

-- 2. BẢNG CHUỖI LUỒNG DUYỆT ĐỘNG THEO PHÒNG BAN
CREATE TABLE `wp_uniform_department_approval_flows` (
    `flow_id` INT AUTO_INCREMENT NOT NULL,
    `department_id` INT NOT NULL,
    `step_order` INT NOT NULL,
    `step_name` VARCHAR(150) NOT NULL,
    `approver_profile_id` INT NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`flow_id`),
    UNIQUE KEY `idx_department_step` (`department_id`, `step_order`),
    KEY `idx_department_id` (`department_id`),
    KEY `idx_approver_profile_id` (`approver_profile_id`),
    KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. BẢNG HỒ SƠ NGƯỜI DÙNG MỞ RỘNG (Liên kết với tài khoản wp_users mặc định)
CREATE TABLE `wp_uniform_user_profiles` (
    `profile_id` INT AUTO_INCREMENT NOT NULL,
    `user_id` BIGINT(20) UNSIGNED DEFAULT NULL,
    `employee_code` VARCHAR(50) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `gender` ENUM('Nam', 'Nữ') NOT NULL,
    `factory_location` ENUM('Đông Anh', 'Hưng Yên', 'Vĩnh Phúc') NOT NULL,
    `department` VARCHAR(100) NOT NULL,
    `job_position` VARCHAR(100) NOT NULL,
    `contract_type` ENUM('Thử việc', 'Tập nghề', 'Tập nghề biên phiên dịch', 'Thuê lại lao động', 'Hợp đồng lao động') NOT NULL,
    `date_joined` DATE NOT NULL,
    `resignation_date` DATE DEFAULT NULL,
    `transfer_date` DATE DEFAULT NULL,
    `is_maternity` TINYINT(1) NOT NULL DEFAULT 0,
    `is_outdoor_worker` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`profile_id`),
    KEY `idx_user_id` (`user_id`),
    UNIQUE KEY `idx_employee_code` (`employee_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. BẢNG DANH MỤC SẢN PHẨM CHA-CON
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

-- 5. BẢNG DANH MỤC SẢN PHẨM & TỔNG KHO
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

-- 6. BẢNG PHIẾU YÊU CẦU CẤP PHÁT (TỔNG QUAN)
CREATE TABLE `wp_uniform_requests` (
    `request_id` INT AUTO_INCREMENT NOT NULL,
    `creator_id` BIGINT(20) UNSIGNED NOT NULL, 
    `target_user_id` BIGINT(20) UNSIGNED NOT NULL, 
    `request_type` ENUM('Cấp mới', 'Định kỳ', 'Phát sinh') NOT NULL,
    `reason_type` TINYINT(1) NOT NULL COMMENT '1: Thay đổi vị trí, 2: Do công việc, 3: Lỗi cá nhân/khác',
    `reason_detail` TEXT DEFAULT NULL,
    `payment_method` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0: Miễn phí, 1: Khấu trừ lương, 2: Tiền mặt/Chuyển khoản',
    `current_status` VARCHAR(50) NOT NULL DEFAULT 'pending_level1',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`request_id`),
    KEY `idx_creator` (`creator_id`),
    KEY `idx_target_user` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. BẢNG CHI TIẾT PHIẾU YÊU CẦU CẤP PHÁT
CREATE TABLE `wp_uniform_request_details` (
    `detail_id` INT AUTO_INCREMENT NOT NULL,
    `request_id` INT NOT NULL,
    `item_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `price_at_request` DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`detail_id`),
    KEY `idx_request` (`request_id`),
    KEY `idx_item` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. BẢNG NHẬT KÝ PHÊ DUYỆT CẤP TỐC ( Cấp độ phê duyệt theo quy định)
CREATE TABLE `wp_uniform_approval_logs` (
    `log_id` INT AUTO_INCREMENT NOT NULL,
    `request_id` INT NOT NULL,
    `approval_level` TINYINT(1) NOT NULL COMMENT '1: DMG/MG Bộ phận, 2: DGM/GM Bộ phận, 3: DMG/MG HCNS, 4: DGM/GM HCNS',
    `approver_id` BIGINT(20) UNSIGNED NOT NULL,
    `action` ENUM('Ph phê duyệt', 'Từ chối') NOT NULL,
    `comment` TEXT DEFAULT NULL,
    `action_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_request_log` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. BẢNG BIÊN BẢN HOÀN TRẢ & THU HỒI ĐỒNG PHỤC
CREATE TABLE `wp_uniform_returns` (
    `return_id` INT AUTO_INCREMENT NOT NULL,
    `return_type` ENUM('Nghỉ việc', 'Chuyển bộ phận') NOT NULL,
    `target_user_id` BIGINT(20) UNSIGNED NOT NULL,
    `creator_id` BIGINT(20) UNSIGNED NOT NULL,
    `expected_items` JSON NOT NULL COMMENT 'Danh sách đồ bắt buộc phải trả tính từ lịch sử',
    `actual_items` JSON NOT NULL COMMENT 'Danh sách đồ thực tế thu hồi tại kho',
    `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Tiền phạt nếu thiếu đồ',
    `payment_status` ENUM('Chưa thu', 'Đã thu', 'Khấu trừ vào lương') NOT NULL DEFAULT 'Chưa thu',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`return_id`),
    KEY `idx_return_target` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
