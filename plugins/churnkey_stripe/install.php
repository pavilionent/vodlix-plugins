<?php
/**
 * Churnkey + Stripe Plugin Installation
 * Creates necessary database tables
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

/**
 * Install the Churnkey Stripe plugin
 * Creates database tables with appropriate prefixes
 */
function churnkey_stripe_install() {
    global $db, $Cbucket;
    
    // Get table prefix
    $prefix = churnkey_stripe_get_table_prefix();
    
    // Array to store installation results
    $results = array();
    
    // Table 1: Settings table
    $table_settings = $prefix . 'churnkey_settings';
    $sql_settings = "CREATE TABLE IF NOT EXISTS `{$table_settings}` (
        `setting_key` VARCHAR(100) NOT NULL,
        `setting_value` TEXT,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $results['settings'] = churnkey_stripe_execute_sql($sql_settings);
    
    // Table 2: Stripe Customer/Subscription Mapping
    $table_stripe_map = $prefix . 'churnkey_stripe_map';
    $sql_stripe_map = "CREATE TABLE IF NOT EXISTS `{$table_stripe_map}` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `userid` INT(11) UNSIGNED NOT NULL,
        `stripe_customer_id` VARCHAR(255) NOT NULL,
        `stripe_subscription_id` VARCHAR(255) DEFAULT NULL,
        `stripe_email` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active', 'canceled', 'past_due', 'trialing', 'unpaid') DEFAULT 'active',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `userid_unique` (`userid`),
        KEY `stripe_customer_id` (`stripe_customer_id`),
        KEY `stripe_subscription_id` (`stripe_subscription_id`),
        KEY `status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $results['stripe_map'] = churnkey_stripe_execute_sql($sql_stripe_map);
    
    // Table 3: Events/Webhook Log (optional but recommended)
    $table_events = $prefix . 'churnkey_events';
    $sql_events = "CREATE TABLE IF NOT EXISTS `{$table_events}` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `event_type` VARCHAR(100) NOT NULL,
        `event_id` VARCHAR(255) DEFAULT NULL,
        `customer_id` VARCHAR(255) DEFAULT NULL,
        `subscription_id` VARCHAR(255) DEFAULT NULL,
        `userid` INT(11) UNSIGNED DEFAULT NULL,
        `payload` LONGTEXT,
        `status` ENUM('received', 'processed', 'failed') DEFAULT 'received',
        `error_message` TEXT DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `processed_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `event_type` (`event_type`),
        KEY `event_id` (`event_id`),
        KEY `customer_id` (`customer_id`),
        KEY `userid` (`userid`),
        KEY `status` (`status`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $results['events'] = churnkey_stripe_execute_sql($sql_events);
    
    // Table 4: Error/Debug Log
    $table_logs = $prefix . 'churnkey_logs';
    $sql_logs = "CREATE TABLE IF NOT EXISTS `{$table_logs}` (
        `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `level` ENUM('debug', 'info', 'warning', 'error') DEFAULT 'info',
        `message` TEXT NOT NULL,
        `context` LONGTEXT DEFAULT NULL,
        `userid` INT(11) UNSIGNED DEFAULT NULL,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `level` (`level`),
        KEY `userid` (`userid`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $results['logs'] = churnkey_stripe_execute_sql($sql_logs);
    
    // Insert default settings
    churnkey_stripe_insert_default_settings($prefix);
    
    // Log installation
    churnkey_stripe_log_event('info', 'Plugin installed', array('results' => $results));
    
    return $results;
}

/**
 * Get the database table prefix
 */
function churnkey_stripe_get_table_prefix() {
    global $Cbucket;
    
    // Try multiple ways to get the prefix
    if (isset($Cbucket->table_prefix)) {
        return $Cbucket->table_prefix;
    }
    
    if (defined('TABLE_PREFIX')) {
        return TABLE_PREFIX;
    }
    
    if (defined('CB_PREFIX')) {
        return CB_PREFIX;
    }
    
    // Default ClipBucket prefix
    return 'cb_';
}

/**
 * Execute SQL query safely
 */
function churnkey_stripe_execute_sql($sql) {
    global $db, $myquery;
    
    try {
        // Try using ClipBucket's database layer
        if (isset($db) && method_exists($db, 'query')) {
            $result = $db->query($sql);
            return array('success' => true, 'result' => $result);
        }
        
        // Try using $myquery (another ClipBucket DB object)
        if (isset($myquery) && method_exists($myquery, 'Execute')) {
            $result = $myquery->Execute($sql);
            return array('success' => true, 'result' => $result);
        }
        
        // Try mysqli directly if available
        if (isset($GLOBALS['mysqli']) && $GLOBALS['mysqli'] instanceof mysqli) {
            $result = $GLOBALS['mysqli']->query($sql);
            return array('success' => $result !== false, 'result' => $result);
        }
        
        // Try PDO if available
        if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
            $result = $GLOBALS['pdo']->exec($sql);
            return array('success' => $result !== false, 'result' => $result);
        }
        
        return array('success' => false, 'error' => 'No database connection available');
    } catch (Exception $e) {
        return array('success' => false, 'error' => $e->getMessage());
    }
}

/**
 * Insert default settings
 */
function churnkey_stripe_insert_default_settings($prefix) {
    $table = $prefix . 'churnkey_settings';
    
    $defaults = array(
        'churnkey_app_id' => '',
        'churnkey_api_key' => '',
        'churnkey_mode' => 'test',
        'churnkey_record' => '1',
        'stripe_secret_key' => '',
        'webhook_secret' => '',
        'enabled' => '0'
    );
    
    foreach ($defaults as $key => $value) {
        $sql = "INSERT IGNORE INTO `{$table}` (`setting_key`, `setting_value`) VALUES ('" . 
               addslashes($key) . "', '" . addslashes($value) . "')";
        churnkey_stripe_execute_sql($sql);
    }
}

/**
 * Simple log event during installation
 */
function churnkey_stripe_log_event($level, $message, $context = array()) {
    $prefix = churnkey_stripe_get_table_prefix();
    $table = $prefix . 'churnkey_logs';
    
    $sql = "INSERT INTO `{$table}` (`level`, `message`, `context`, `ip_address`) VALUES ('" .
           addslashes($level) . "', '" . addslashes($message) . "', '" . 
           addslashes(json_encode($context)) . "', '" . 
           addslashes(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI') . "')";
    
    // Silently try to log - table might not exist yet
    @churnkey_stripe_execute_sql($sql);
}
