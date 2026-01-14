<?php
/**
 * Settings Helper for Churnkey + Stripe Plugin
 * Handles plugin configuration storage and retrieval
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

/**
 * Get a plugin setting
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value or default
 */
function churnkey_stripe_get_setting($key, $default = null) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_settings');
    
    $sql = "SELECT `setting_value` FROM `{$table}` WHERE `setting_key` = " . $db->escape($key) . " LIMIT 1";
    $row = $db->getRow($sql);
    
    if ($row && isset($row['setting_value'])) {
        return $row['setting_value'];
    }
    
    return $default;
}

/**
 * Set a plugin setting
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @return bool Success
 */
function churnkey_stripe_set_setting($key, $value) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_settings');
    
    // Try update first
    $sql = "INSERT INTO `{$table}` (`setting_key`, `setting_value`, `updated_at`) 
            VALUES (" . $db->escape($key) . ", " . $db->escape($value) . ", NOW())
            ON DUPLICATE KEY UPDATE `setting_value` = " . $db->escape($value) . ", `updated_at` = NOW()";
    
    return $db->execute($sql);
}

/**
 * Get all plugin settings
 * 
 * @return array Associative array of all settings
 */
function churnkey_stripe_get_all_settings() {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_settings');
    
    $sql = "SELECT `setting_key`, `setting_value` FROM `{$table}`";
    $rows = $db->getRows($sql);
    
    $settings = array();
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    return $settings;
}

/**
 * Save multiple settings at once
 * 
 * @param array $settings Associative array of settings
 * @return bool Success
 */
function churnkey_stripe_save_settings($settings) {
    $success = true;
    
    foreach ($settings as $key => $value) {
        if (!churnkey_stripe_set_setting($key, $value)) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Delete a setting
 * 
 * @param string $key Setting key
 * @return bool Success
 */
function churnkey_stripe_delete_setting($key) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_settings');
    
    return $db->delete('churnkey_settings', "`setting_key` = " . $db->escape($key));
}

/**
 * Check if plugin is enabled
 * 
 * @return bool
 */
function churnkey_stripe_is_enabled() {
    return churnkey_stripe_get_setting('enabled', '0') === '1';
}

/**
 * Check if plugin is properly configured
 * 
 * @return array Status array with 'configured' bool and 'missing' array
 */
function churnkey_stripe_is_configured() {
    $settings = churnkey_stripe_get_all_settings();
    
    $required = array(
        'churnkey_app_id',
        'churnkey_api_key'
    );
    
    $missing = array();
    foreach ($required as $key) {
        if (empty($settings[$key])) {
            $missing[] = $key;
        }
    }
    
    return array(
        'configured' => empty($missing),
        'missing' => $missing,
        'settings' => $settings
    );
}

/**
 * Get Churnkey App ID
 */
function churnkey_stripe_get_app_id() {
    return churnkey_stripe_get_setting('churnkey_app_id', '');
}

/**
 * Get Churnkey API Key (for server-side use only)
 */
function churnkey_stripe_get_api_key() {
    return churnkey_stripe_get_setting('churnkey_api_key', '');
}

/**
 * Get Churnkey mode (test/live)
 */
function churnkey_stripe_get_mode() {
    return churnkey_stripe_get_setting('churnkey_mode', 'test');
}

/**
 * Get record sessions setting
 */
function churnkey_stripe_get_record() {
    return churnkey_stripe_get_setting('churnkey_record', '1') === '1';
}

/**
 * Get Stripe Secret Key (for server-side use only)
 */
function churnkey_stripe_get_stripe_key() {
    return churnkey_stripe_get_setting('stripe_secret_key', '');
}

/**
 * Get webhook secret
 */
function churnkey_stripe_get_webhook_secret() {
    return churnkey_stripe_get_setting('webhook_secret', '');
}

/**
 * Log a message
 * 
 * @param string $level Log level (debug, info, warning, error)
 * @param string $message Log message
 * @param array $context Additional context
 */
function churnkey_stripe_log($level, $message, $context = array()) {
    $db = churnkey_stripe_db();
    
    $data = array(
        'level' => $level,
        'message' => $message,
        'context' => json_encode($context),
        'userid' => function_exists('churnkey_stripe_get_current_userid') ? churnkey_stripe_get_current_userid() : null,
        'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null
    );
    
    $db->insert('churnkey_logs', $data);
}

/**
 * Get recent logs
 * 
 * @param int $limit Number of logs to retrieve
 * @param string|null $level Filter by level
 * @return array
 */
function churnkey_stripe_get_logs($limit = 100, $level = null) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_logs');
    
    $where = '1=1';
    if ($level !== null) {
        $where .= " AND `level` = " . $db->escape($level);
    }
    
    $sql = "SELECT * FROM `{$table}` WHERE {$where} ORDER BY `created_at` DESC LIMIT " . intval($limit);
    
    return $db->getRows($sql);
}

/**
 * Clear old logs
 * 
 * @param int $days_old Delete logs older than this many days
 * @return bool
 */
function churnkey_stripe_clear_old_logs($days_old = 30) {
    $db = churnkey_stripe_db();
    $table = $db->table('churnkey_logs');
    
    $sql = "DELETE FROM `{$table}` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL " . intval($days_old) . " DAY)";
    
    return $db->execute($sql);
}
