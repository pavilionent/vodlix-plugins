<?php
/**
 * Plugin Name: Rewardful Referrals
 * Plugin URI: https://github.com/pavilionent/vodlix-plugins
 * Description: Invite/approve-only referral/affiliate program integration with Rewardful for ClipBucket
 * Version: 1.0.0
 * Author: Pavilion Entertainment
 * Author URI: https://vodlix.com
 * License: GPL-2.0+
 */

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

// Plugin constants
define('REWARDFUL_PLUGIN_NAME', 'rewardful_referrals');
define('REWARDFUL_PLUGIN_DIR', dirname(__FILE__));
define('REWARDFUL_PLUGIN_URL', BASEURL . '/plugins/' . REWARDFUL_PLUGIN_NAME);
define('REWARDFUL_VERSION', '1.0.0');

// Load library classes
require_once REWARDFUL_PLUGIN_DIR . '/lib/Db.php';
require_once REWARDFUL_PLUGIN_DIR . '/lib/Auth.php';
require_once REWARDFUL_PLUGIN_DIR . '/lib/RewardfulClient.php';
require_once REWARDFUL_PLUGIN_DIR . '/lib/RewardfulService.php';
require_once REWARDFUL_PLUGIN_DIR . '/lib/ClipBucketHooks.php';

/**
 * Initialize the Rewardful Referrals plugin
 */
function rewardful_referrals_init() {
    global $cbplugin, $Cbucket, $userquery;
    
    // Initialize plugin hooks
    $hooks = new RewardfulReferrals\ClipBucketHooks();
    $hooks->register();
    
    // Register admin menu items
    rewardful_register_admin_menu();
    
    // Register user area pages
    rewardful_register_user_pages();
}

/**
 * Register admin menu items
 */
function rewardful_register_admin_menu() {
    global $AdminMenu;
    
    // Add to Tool Box menu or create new menu section
    $menu_items = array(
        array(
            'title' => 'Rewardful Settings',
            'link' => 'rewardful_settings',
            'icon' => 'fa fa-cog',
            'section' => 'toolbox',
        ),
        array(
            'title' => 'Referrers',
            'link' => 'rewardful_referrers', 
            'icon' => 'fa fa-users',
            'section' => 'toolbox',
        ),
        array(
            'title' => 'Rewardful Logs',
            'link' => 'rewardful_logs',
            'icon' => 'fa fa-list-alt',
            'section' => 'toolbox',
        ),
        array(
            'title' => 'Rewardful Health',
            'link' => 'rewardful_health',
            'icon' => 'fa fa-heartbeat',
            'section' => 'toolbox',
        ),
    );
    
    // Add menu items using ClipBucket's admin menu system
    if (isset($AdminMenu) && is_object($AdminMenu)) {
        foreach ($menu_items as $item) {
            $AdminMenu->addMenuItem('toolbox', $item['link'], $item['title'], array(
                'icon' => $item['icon']
            ));
        }
    }
}

/**
 * Register user area pages
 */
function rewardful_register_user_pages() {
    global $pages;
    
    // Register referrals page in my_account area
    if (!isset($pages['my_account']['referrals'])) {
        $pages['my_account']['referrals'] = array(
            'name' => 'Referrals',
            'require_login' => true,
            'file' => REWARDFUL_PLUGIN_DIR . '/user/referrals.php'
        );
    }
}

/**
 * Check if plugin is enabled
 * 
 * @return bool
 */
function rewardful_is_enabled() {
    $db = new RewardfulReferrals\Db();
    return $db->getSetting('enabled') === '1';
}

/**
 * Get current user's referrer status
 * 
 * @param int|null $userid
 * @return string|null Status or null if not a referrer
 */
function rewardful_get_referrer_status($userid = null) {
    global $userquery;
    
    if ($userid === null) {
        $userid = userid();
    }
    
    if (!$userid) {
        return null;
    }
    
    $db = new RewardfulReferrals\Db();
    $referrer = $db->getReferrerByUserId($userid);
    
    return $referrer ? $referrer['status'] : null;
}

/**
 * Check if current user is an approved referrer
 * 
 * @param int|null $userid
 * @return bool
 */
function rewardful_is_approved_referrer($userid = null) {
    return rewardful_get_referrer_status($userid) === 'approved';
}

/**
 * Check if current user can view referrals section
 * 
 * @return bool
 */
function rewardful_can_view_referrals() {
    global $userquery;
    
    // Admins can always view
    if (has_access('admin_access', true)) {
        return true;
    }
    
    // Approved referrers can view
    return rewardful_is_approved_referrer();
}

/**
 * Plugin activation hook
 */
function rewardful_activate() {
    // Run install script
    require_once REWARDFUL_PLUGIN_DIR . '/install.php';
}

/**
 * Plugin deactivation hook
 */
function rewardful_deactivate() {
    // Optional: cleanup or disable
}

// Register activation/deactivation hooks with ClipBucket
if (function_exists('register_activation_function')) {
    register_activation_function(REWARDFUL_PLUGIN_NAME, 'rewardful_activate');
}

if (function_exists('register_deactivation_function')) {
    register_deactivation_function(REWARDFUL_PLUGIN_NAME, 'rewardful_deactivate');
}

// Initialize plugin
add_action('init', 'rewardful_referrals_init');

// Alternative initialization for older ClipBucket versions
if (!function_exists('add_action')) {
    rewardful_referrals_init();
}
