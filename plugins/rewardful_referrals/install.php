<?php
/**
 * Rewardful Referrals - Installation Script
 * 
 * Creates required database tables and initial settings
 */

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

global $db, $cbplugin;

/**
 * Create database tables for Rewardful Referrals plugin
 */
function rewardful_create_tables() {
    global $db;
    
    $tables = array();
    
    // Settings table
    $tables[] = "CREATE TABLE IF NOT EXISTS `{$db->db_prefix}rewardful_settings` (
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Referrers table
    $tables[] = "CREATE TABLE IF NOT EXISTS `{$db->db_prefix}rewardful_referrers` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `userid` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ClipBucket user ID (nullable for external referrers)',
        `email` VARCHAR(255) NOT NULL,
        `first_name` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) DEFAULT NULL,
        `status` ENUM('pending','approved','rejected','disabled') NOT NULL DEFAULT 'pending',
        `notes` TEXT,
        `application_message` TEXT COMMENT 'Self-apply message from user',
        `created_by_admin_userid` INT(11) UNSIGNED DEFAULT NULL,
        `approved_by_admin_userid` INT(11) UNSIGNED DEFAULT NULL,
        `approved_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_userid` (`userid`),
        UNIQUE KEY `idx_email` (`email`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Affiliates table (Rewardful affiliate data)
    $tables[] = "CREATE TABLE IF NOT EXISTS `{$db->db_prefix}rewardful_affiliates` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `referrer_id` INT(11) UNSIGNED NOT NULL,
        `rewardful_affiliate_id` VARCHAR(100) NOT NULL,
        `token` VARCHAR(100) NOT NULL COMMENT 'Rewardful referral token (via= parameter)',
        `campaign_id` VARCHAR(100) DEFAULT NULL,
        `first_name` VARCHAR(100) DEFAULT NULL,
        `last_name` VARCHAR(100) DEFAULT NULL,
        `referral_link` VARCHAR(500) DEFAULT NULL,
        `visitors_count` INT(11) DEFAULT 0,
        `leads_count` INT(11) DEFAULT 0,
        `conversions_count` INT(11) DEFAULT 0,
        `synced_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_referrer_id` (`referrer_id`),
        UNIQUE KEY `idx_rewardful_affiliate_id` (`rewardful_affiliate_id`),
        KEY `idx_token` (`token`),
        CONSTRAINT `fk_affiliate_referrer` FOREIGN KEY (`referrer_id`) 
            REFERENCES `{$db->db_prefix}rewardful_referrers` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Webhook events table
    $tables[] = "CREATE TABLE IF NOT EXISTS `{$db->db_prefix}rewardful_events` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_id` VARCHAR(100) NOT NULL COMMENT 'Rewardful event ID or payload hash for idempotency',
        `event_type` VARCHAR(100) NOT NULL,
        `payload_json` LONGTEXT,
        `status` ENUM('received','processed','failed','ignored') NOT NULL DEFAULT 'received',
        `error_message` TEXT,
        `processed_at` DATETIME DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_event_id` (`event_id`),
        KEY `idx_event_type` (`event_type`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Conversions table
    $tables[] = "CREATE TABLE IF NOT EXISTS `{$db->db_prefix}rewardful_conversions` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `userid` INT(11) UNSIGNED DEFAULT NULL COMMENT 'ClipBucket user ID',
        `email` VARCHAR(255) NOT NULL,
        `via_token` VARCHAR(100) DEFAULT NULL COMMENT 'Referral token from via= param',
        `subscription_id` VARCHAR(100) DEFAULT NULL COMMENT 'ClipBucket subscription/order ID',
        `order_id` VARCHAR(100) DEFAULT NULL,
        `amount` DECIMAL(10,2) DEFAULT NULL,
        `currency` VARCHAR(10) DEFAULT 'USD',
        `status` ENUM('pending','sent','confirmed','failed') NOT NULL DEFAULT 'pending',
        `conversion_key` VARCHAR(255) NOT NULL COMMENT 'Unique key for idempotency',
        `sent_at` DATETIME DEFAULT NULL,
        `confirmed_at` DATETIME DEFAULT NULL,
        `error_message` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `idx_conversion_key` (`conversion_key`),
        KEY `idx_userid` (`userid`),
        KEY `idx_email` (`email`),
        KEY `idx_via_token` (`via_token`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // API/Activity logs table
    $tables[] = "CREATE TABLE IF NOT EXISTS `{$db->db_prefix}rewardful_logs` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `log_type` ENUM('api','webhook','conversion','error','info') NOT NULL DEFAULT 'info',
        `action` VARCHAR(100) NOT NULL,
        `message` TEXT,
        `context_json` LONGTEXT,
        `userid` INT(11) UNSIGNED DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_log_type` (`log_type`),
        KEY `idx_action` (`action`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    // Execute table creation
    foreach ($tables as $sql) {
        $db->execute($sql);
    }
}

/**
 * Insert default settings
 */
function rewardful_insert_default_settings() {
    global $db;
    
    $defaults = array(
        'enabled' => '0',
        'public_api_key' => '',
        'api_secret' => '',
        'webhook_secret' => '',
        'default_campaign_id' => '',
        'allow_self_apply' => '0',
        'success_url_patterns' => '',
        'auto_detect_success_pages' => '1',
        'js_snippet_version' => '1',
        'last_health_check' => '',
        'conversion_trigger_type' => 'auto' // 'auto' or 'manual'
    );
    
    $table = $db->db_prefix . 'rewardful_settings';
    
    foreach ($defaults as $key => $value) {
        // Insert or update
        $sql = "INSERT INTO `{$table}` (`setting_key`, `setting_value`) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)";
        
        // Only insert if not exists (preserve existing values)
        $check = "SELECT setting_value FROM `{$table}` WHERE setting_key = ?";
        $result = $db->select($check, array($key));
        
        if (empty($result)) {
            $db->execute($sql, array($key, $value));
        }
    }
}

/**
 * Main installation function
 */
function rewardful_install() {
    global $db;
    
    try {
        // Create tables
        rewardful_create_tables();
        
        // Insert default settings
        rewardful_insert_default_settings();
        
        // Log installation
        $log_table = $db->db_prefix . 'rewardful_logs';
        $db->execute(
            "INSERT INTO `{$log_table}` (`log_type`, `action`, `message`) VALUES (?, ?, ?)",
            array('info', 'plugin_installed', 'Rewardful Referrals plugin installed successfully. Version: ' . REWARDFUL_VERSION)
        );
        
        return true;
        
    } catch (Exception $e) {
        error_log('Rewardful Referrals install error: ' . $e->getMessage());
        return false;
    }
}

// Run installation
rewardful_install();
