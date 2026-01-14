<?php
/**
 * Rewardful Referrals - Uninstallation Script
 * 
 * Removes database tables and cleans up plugin data
 * 
 * WARNING: This will permanently delete all Rewardful Referrals data!
 */

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

global $db;

/**
 * Drop all Rewardful Referrals database tables
 * 
 * @param bool $confirm Set to true to confirm deletion
 * @return bool
 */
function rewardful_drop_tables($confirm = false) {
    global $db;
    
    if (!$confirm) {
        return false;
    }
    
    // Tables in reverse order of dependencies
    $tables = array(
        'rewardful_logs',
        'rewardful_conversions',
        'rewardful_events',
        'rewardful_affiliates',
        'rewardful_referrers',
        'rewardful_settings'
    );
    
    foreach ($tables as $table) {
        $full_table = $db->db_prefix . $table;
        $sql = "DROP TABLE IF EXISTS `{$full_table}`";
        $db->execute($sql);
    }
    
    return true;
}

/**
 * Clean up plugin files and cache
 * 
 * @return bool
 */
function rewardful_cleanup_files() {
    // Clean up any cached files
    $cache_dir = BASEDIR . '/files/cache/rewardful';
    if (is_dir($cache_dir)) {
        rewardful_recursive_rmdir($cache_dir);
    }
    
    return true;
}

/**
 * Recursively remove directory
 * 
 * @param string $dir
 * @return bool
 */
function rewardful_recursive_rmdir($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object !== "." && $object !== "..") {
                $path = $dir . "/" . $object;
                if (is_dir($path)) {
                    rewardful_recursive_rmdir($path);
                } else {
                    unlink($path);
                }
            }
        }
        rmdir($dir);
    }
    return true;
}

/**
 * Main uninstall function
 * 
 * @param bool $delete_data Whether to delete all data (default: false for safety)
 * @return bool
 */
function rewardful_uninstall($delete_data = false) {
    try {
        if ($delete_data) {
            // Drop all tables
            rewardful_drop_tables(true);
            
            // Clean up files
            rewardful_cleanup_files();
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log('Rewardful Referrals uninstall error: ' . $e->getMessage());
        return false;
    }
}

// Only run uninstall if explicitly called with confirmation
// This prevents accidental data loss
if (defined('REWARDFUL_CONFIRM_UNINSTALL') && REWARDFUL_CONFIRM_UNINSTALL === true) {
    rewardful_uninstall(true);
}
