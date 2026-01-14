<?php
/**
 * Churnkey + Stripe Plugin Uninstallation
 * Removes database tables (optional - can be configured to preserve data)
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

// Include helper if not already loaded
if (!function_exists('churnkey_stripe_get_table_prefix')) {
    require_once dirname(__FILE__) . '/install.php';
}

/**
 * Uninstall the Churnkey Stripe plugin
 * 
 * @param bool $preserve_data If true, keeps the data tables (only removes if explicitly asked)
 */
function churnkey_stripe_uninstall($preserve_data = true) {
    global $db, $Cbucket;
    
    // Get table prefix
    $prefix = churnkey_stripe_get_table_prefix();
    
    // Array to store uninstallation results
    $results = array();
    
    // By default, preserve data on uninstall
    // Only drop tables if explicitly requested
    if (!$preserve_data) {
        // Tables to drop
        $tables = array(
            'churnkey_settings',
            'churnkey_stripe_map',
            'churnkey_events',
            'churnkey_logs'
        );
        
        foreach ($tables as $table) {
            $full_table = $prefix . $table;
            $sql = "DROP TABLE IF EXISTS `{$full_table}`";
            $results[$table] = churnkey_stripe_execute_sql($sql);
        }
    } else {
        // Just mark as uninstalled in settings
        $table = $prefix . 'churnkey_settings';
        $sql = "UPDATE `{$table}` SET `setting_value` = '0' WHERE `setting_key` = 'enabled'";
        $results['disable'] = churnkey_stripe_execute_sql($sql);
    }
    
    return $results;
}

/**
 * Complete removal - drops all tables and data
 * Use with caution - this is irreversible
 */
function churnkey_stripe_complete_uninstall() {
    return churnkey_stripe_uninstall(false);
}

/**
 * Clean up old event logs (maintenance function)
 * 
 * @param int $days_old Remove events older than this many days
 */
function churnkey_stripe_cleanup_old_events($days_old = 90) {
    $prefix = churnkey_stripe_get_table_prefix();
    $table = $prefix . 'churnkey_events';
    
    $sql = "DELETE FROM `{$table}` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL " . 
           intval($days_old) . " DAY)";
    
    return churnkey_stripe_execute_sql($sql);
}

/**
 * Clean up old logs (maintenance function)
 * 
 * @param int $days_old Remove logs older than this many days
 */
function churnkey_stripe_cleanup_old_logs($days_old = 30) {
    $prefix = churnkey_stripe_get_table_prefix();
    $table = $prefix . 'churnkey_logs';
    
    $sql = "DELETE FROM `{$table}` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL " . 
           intval($days_old) . " DAY)";
    
    return churnkey_stripe_execute_sql($sql);
}
