-- =====================================================
-- 短链接服务 - 建表语句
-- 在 InfinityFree 数据库管理 (phpMyAdmin) 中执行此 SQL
-- =====================================================

CREATE TABLE IF NOT EXISTS `links` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(32) NOT NULL UNIQUE,
    `original_url` TEXT NOT NULL,
    `title` VARCHAR(255) DEFAULT '',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `visit_count` INT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
