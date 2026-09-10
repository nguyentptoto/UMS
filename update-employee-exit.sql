-- Chay file nay cho database UMS da ton tai truoc khi dong bo Google Sheet lan tiep theo.

ALTER TABLE `wp_uniform_organization_employees`
	ADD COLUMN IF NOT EXISTS `employment_status` VARCHAR(20) NOT NULL DEFAULT 'active' AFTER `sync_token`,
	ADD COLUMN IF NOT EXISTS `last_seen_at` DATETIME DEFAULT NULL AFTER `employment_status`,
	ADD COLUMN IF NOT EXISTS `left_detected_at` DATETIME DEFAULT NULL AFTER `last_seen_at`,
	ADD INDEX IF NOT EXISTS `idx_employment_status` (`employment_status`),
	ADD INDEX IF NOT EXISTS `idx_left_detected_at` (`left_detected_at`);

UPDATE `wp_uniform_organization_employees`
SET `last_seen_at` = COALESCE(`last_seen_at`, `synced_at`)
WHERE `employment_status` = 'active';

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
