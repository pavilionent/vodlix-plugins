-- ============================================================
-- Pause Ads Plugin v2.0 - Database Schema
-- Full self-service advertiser platform with campaigns,
-- packages, purchases, invoices, and geo targeting.
-- All tables prefixed with {tbl_prefix}pause_ads_
-- ============================================================

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

-- Companies (advertisers / brands / agencies)
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_companies` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `status` ENUM('active','suspended') NOT NULL DEFAULT 'active',
    `billing_email` VARCHAR(255) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Company-user membership with roles
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_company_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `role` ENUM('owner','admin','analyst') NOT NULL DEFAULT 'admin',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_company_user` (`company_id`, `user_id`),
    KEY `idx_user_id` (`user_id`),
    CONSTRAINT `fk_cu_company` FOREIGN KEY (`company_id`)
        REFERENCES `{tbl_prefix}pause_ads_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Packages (pricing plans)
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_packages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `price_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
    `included_impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `max_flight_days` INT UNSIGNED DEFAULT NULL,
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status_sort` (`status`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Campaigns
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_campaigns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `status` ENUM('draft','pending_payment','pending_review','active','paused','ended','rejected') NOT NULL DEFAULT 'draft',
    `flight_start_at` DATETIME DEFAULT NULL,
    `flight_end_at` DATETIME DEFAULT NULL,
    `daily_cap` INT UNSIGNED DEFAULT NULL,
    `hourly_cap` INT UNSIGNED DEFAULT NULL,
    `freq_cap_per_user_per_day` INT UNSIGNED DEFAULT NULL,
    `priority` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_company` (`company_id`),
    KEY `idx_status` (`status`),
    KEY `idx_flight` (`flight_start_at`, `flight_end_at`),
    KEY `idx_active_flight` (`status`, `flight_start_at`, `flight_end_at`),
    CONSTRAINT `fk_camp_company` FOREIGN KEY (`company_id`)
        REFERENCES `{tbl_prefix}pause_ads_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Creatives (ad images within a campaign)
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_creatives` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL DEFAULT '',
    `image_path` VARCHAR(500) NOT NULL DEFAULT '',
    `image_width` INT UNSIGNED NOT NULL DEFAULT 0,
    `image_height` INT UNSIGNED NOT NULL DEFAULT 0,
    `click_url` VARCHAR(2048) NOT NULL DEFAULT '',
    `alt_text` VARCHAR(255) NOT NULL DEFAULT '',
    `status` ENUM('active','paused') NOT NULL DEFAULT 'active',
    `weight` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_campaign` (`campaign_id`),
    KEY `idx_status` (`status`),
    CONSTRAINT `fk_cre_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `{tbl_prefix}pause_ads_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Targeting rules (per campaign)
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_targeting_rules` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `campaign_id` INT UNSIGNED NOT NULL,
    `rule_type` VARCHAR(50) NOT NULL DEFAULT 'genre',
    `rule_value` VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    KEY `idx_campaign` (`campaign_id`),
    KEY `idx_type` (`rule_type`),
    CONSTRAINT `fk_tr_campaign` FOREIGN KEY (`campaign_id`)
        REFERENCES `{tbl_prefix}pause_ads_campaigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchases (package bought by company)
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_purchases` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `package_id` INT UNSIGNED DEFAULT NULL,
    `campaign_id` INT UNSIGNED DEFAULT NULL,
    `impressions_granted` INT UNSIGNED NOT NULL DEFAULT 0,
    `impressions_remaining` INT UNSIGNED NOT NULL DEFAULT 0,
    `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
    `payment_status` ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    `payment_gateway` VARCHAR(50) NOT NULL DEFAULT '',
    `payment_ref` VARCHAR(255) NOT NULL DEFAULT '',
    `invoice_id` INT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_company` (`company_id`),
    KEY `idx_campaign` (`campaign_id`),
    KEY `idx_payment_status` (`payment_status`),
    KEY `idx_payment_ref` (`payment_ref`),
    CONSTRAINT `fk_pur_company` FOREIGN KEY (`company_id`)
        REFERENCES `{tbl_prefix}pause_ads_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Invoices
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_invoices` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `purchase_id` INT UNSIGNED DEFAULT NULL,
    `invoice_number` VARCHAR(50) NOT NULL DEFAULT '',
    `line_items_json` TEXT NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
    `status` ENUM('issued','void') NOT NULL DEFAULT 'issued',
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_company` (`company_id`),
    KEY `idx_invoice_number` (`invoice_number`),
    CONSTRAINT `fk_inv_company` FOREIGN KEY (`company_id`)
        REFERENCES `{tbl_prefix}pause_ads_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Impressions log
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_impressions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creative_id` INT UNSIGNED NOT NULL,
    `campaign_id` INT UNSIGNED NOT NULL,
    `company_id` INT UNSIGNED NOT NULL,
    `video_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(64) NOT NULL DEFAULT '',
    `country_code` VARCHAR(2) NOT NULL DEFAULT '',
    `region_code` VARCHAR(10) NOT NULL DEFAULT '',
    `ip_hash` VARCHAR(64) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_creative` (`creative_id`),
    KEY `idx_campaign` (`campaign_id`),
    KEY `idx_company` (`company_id`),
    KEY `idx_video` (`video_id`),
    KEY `idx_session` (`session_id`),
    KEY `idx_created` (`created_at`),
    KEY `idx_campaign_created` (`campaign_id`, `created_at`),
    KEY `idx_country` (`country_code`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clicks log
CREATE TABLE IF NOT EXISTS `{tbl_prefix}pause_ads_clicks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `creative_id` INT UNSIGNED NOT NULL,
    `campaign_id` INT UNSIGNED NOT NULL,
    `company_id` INT UNSIGNED NOT NULL,
    `video_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `user_id` INT UNSIGNED DEFAULT NULL,
    `session_id` VARCHAR(64) NOT NULL DEFAULT '',
    `country_code` VARCHAR(2) NOT NULL DEFAULT '',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_creative` (`creative_id`),
    KEY `idx_campaign` (`campaign_id`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
