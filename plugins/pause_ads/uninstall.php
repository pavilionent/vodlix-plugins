<?php
/**
 * Pause Ads Plugin v2.0 - Uninstallation Script
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

function pause_ads_uninstall()
{
    global $db;
    $prefix = isset($db->db_prefix) ? $db->db_prefix : 'cb_';

    // Remove hooks
    $hook_names = [
        'pause_ads_inject_player_overlay','pause_ads_admin_menu',
        'pause_ads_header','pause_ads_footer',
        'pause_ads_avod_toggle','pause_ads_avod_save',
    ];
    foreach ($hook_names as $hn) {
        @$db->mysqli->query("DELETE FROM `{$prefix}plugin_hooks` WHERE `hook_name`='" . $db->mysqli->real_escape_string($hn) . "'");
    }

    // Check drop preference
    $drop = false;
    $r = @$db->mysqli->query("SELECT `setting_value` FROM `{$prefix}pause_ads_settings` WHERE `setting_key`='drop_tables_on_uninstall'");
    if ($r && ($row = $r->fetch_assoc())) {
        $drop = $row['setting_value'] === '1';
    }

    if ($drop) {
        $tables = [
            'pause_ads_clicks','pause_ads_impressions','pause_ads_invoices',
            'pause_ads_purchases','pause_ads_targeting_rules','pause_ads_creatives',
            'pause_ads_campaigns','pause_ads_company_users','pause_ads_packages',
            'pause_ads_companies','pause_ads_avod_videos','pause_ads_settings',
        ];
        foreach ($tables as $t) {
            $db->mysqli->query("DROP TABLE IF EXISTS `{$prefix}{$t}`");
        }
        e('Pause Ads: All tables dropped.', 'm');
    } else {
        e('Pause Ads: Hooks removed. Tables preserved.', 'm');
    }

    e('Pause Ads plugin uninstalled.', 'm');
    return true;
}

if (defined('STARTER')) {
    pause_ads_uninstall();
}
