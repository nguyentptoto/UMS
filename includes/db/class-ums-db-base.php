<?php
/**
 * Lớp cơ sở (Base/Abstract Class) cho tầng Database
 */
abstract class UMS_DB_Base {
    
    /**
     * Trả về thực thể $wpdb toàn cục của WordPress
     */
    protected static function db() {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Tự động lấy tiền tố bảng (Prefix) của WordPress
     */
    protected static function prefix() {
        return self::db()->prefix;
    }
}