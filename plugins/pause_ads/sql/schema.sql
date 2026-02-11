-- ============================================================
-- Pause Ads Plugin - Database Schema
-- ClipBucket Plugin: pause_ads
-- All tables prefixed with {tbl_prefix}pause_ads_
-- ============================================================

-- Ads table: stores each ad creative
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_ads` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `status` ENUM('active','paused','archived') NOT NULL DEFAULT 'paused',
    `image_path` VARCHAR(500) NOT NULL DEFAULT '',
    `image_width` INT UNSIGNED NOT NULL DEFAULT 0,
    `image_height` INT UNSIGNED NOT NULL DEFAULT 0,
    `click_url` VARCHAR(2048) NOT NULL DEFAULT '',
    `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
    `advertiser` VARCHAR(255) NOT NULL DEFAULT '',
    `priority` INT UNSIGNED NOT NULL DEFAULT 0,
    `start_at` DATETIME DEFAULT NULL,
    `end_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_schedule` (`start_at`, `end_at`),
    KEY `idx_priority` (`priority` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Targeting rules: each row is one rule for one ad
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_targeting` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id` INT UNSIGNED NOT NULL,
    `rule_type` VARCHAR(50) NOT NULL DEFAULT 'genre',
    `rule_value` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_ad_id` (`ad_id`),
    KEY `idx_rule_type` (`rule_type`),
    CONSTRAINT `fk_targeting_ad` FOREIGN KEY (`ad_id`)
        REFERENCES `{tbl_prefix}pause_ads_ads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Delivery configuration: caps and weights per ad
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_delivery` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id` INT UNSIGNED NOT NULL,
    `cap_total_impressions` INT UNSIGNED DEFAULT NULL,
    `cap_daily_impressions` INT UNSIGNED DEFAULT NULL,
    `cap_hourly_impressions` INT UNSIGNED DEFAULT NULL,
    `weight` INT UNSIGNED NOT NULL DEFAULT 1,
    `frequency_cap_per_user_per_day` INT UNSIGNED DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_ad_id` (`ad_id`),
    CONSTRAINT `fk_delivery_ad` FOREIGN KEY (`ad_id`)
        REFERENCES `{tbl_prefix}pause_ads_ads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Impressions log
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_impressions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id` INT UNSIGNED NOT NULL,
    `video_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(64) NOT NULL DEFAULT '',
    `ip_hash` VARCHAR(64) NOT NULL DEFAULT '',
    `user_agent_hash` VARCHAR(64) NOT NULL DEFAULT '',
    `country` VARCHAR(10) NOT NULL DEFAULT '',
    `pause_duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ad_id` (`ad_id`),
    KEY `idx_video_id` (`video_id`),
    KEY `idx_session_id` (`session_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_ad_created` (`ad_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clicks log
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_clicks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ad_id` INT UNSIGNED NOT NULL,
    `impression_id` BIGINT UNSIGNED DEFAULT NULL,
    `video_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(64) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_ad_id` (`ad_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings key-value store
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_settings` (
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NOT NULL,
    PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AVOD-enabled videos tracking
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_avod_videos` (
    `video_id` BIGINT UNSIGNED NOT NULL,
    `is_avod` TINYINT(1) NOT NULL DEFAULT 1,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`video_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default settings
INSERT INTO `{tbl_prefix}pause_ads_settings` (`setting_key`, `setting_value`) VALUES
    ('enabled', '1'),
    ('min_pause_ms', '1000'),
    ('overlay_position', 'center'),
    ('overlay_style', 'semi-transparent'),
    ('fallback_behavior', 'none'),
    ('ip_salt', '{random_salt}'),
    ('max_ads_per_session_per_minute', '5'),
    ('drop_tables_on_uninstall', '0')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;
