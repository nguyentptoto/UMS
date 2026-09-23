-- Tach ton kho theo 3 nha may: HY (Hung Yen), DA (Dong Anh), VP (Vinh Phuc).
-- Danh muc san pham va master SAP van dung chung, chi so du ton kho duoc tach rieng.

CREATE TABLE IF NOT EXISTS `wp_uniform_inventory_stocks` (
    `stock_id` BIGINT(20) UNSIGNED AUTO_INCREMENT NOT NULL,
    `item_id` INT NOT NULL,
    `factory_code` VARCHAR(10) NOT NULL COMMENT 'HY, DA, VP',
    `stock_qty` INT NOT NULL DEFAULT 0,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`stock_id`),
    UNIQUE KEY `idx_item_factory` (`item_id`, `factory_code`),
    KEY `idx_factory_stock` (`factory_code`, `stock_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Du lieu ton kho chung cu duoc dua toan bo vao Hung Yen.
INSERT INTO `wp_uniform_inventory_stocks` (`item_id`, `factory_code`, `stock_qty`)
SELECT `item_id`, 'HY', `stock_qty`
FROM `wp_uniform_inventory`
ON DUPLICATE KEY UPDATE `stock_qty` = VALUES(`stock_qty`);

ALTER TABLE `wp_uniform_inventory_movements`
    ADD COLUMN IF NOT EXISTS `factory_code` VARCHAR(10) NOT NULL DEFAULT 'HY' AFTER `request_id`,
    ADD INDEX IF NOT EXISTS `idx_factory_created` (`factory_code`, `created_at`);

UPDATE `wp_uniform_inventory_movements`
SET `factory_code` = 'HY'
WHERE `factory_code` IS NULL OR `factory_code` = '';
