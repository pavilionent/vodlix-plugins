<?php
/**
 * Pause Ads Plugin - Uninstallation Script
 *
 * Removes plugin hooks and optionally drops database tables.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

/**
 * Run the pause_ads plugin uninstallation.
 *
 * @return bool True on success
 */
function pause_ads_uninstall()
{
    global $db;

    $tbl_prefix = pause_ads_get_prefix();

    // Remove registered hooks
    $hook_names = [
        'pause_ads_inject_player_overlay',
        'pause_ads_admin_menu',
        'pause_ads_header',
        'pause_ads_footer',
        'pause_ads_avod_toggle',
        'pause_ads_avod_save',
    ];

    foreach ($hook_names as $hook_name) {
        $stmt = $db->mysqli->prepare(
            "DELETE FROM `{$tbl_prefix}plugin_hooks` WHERE `hook_name` = ?"
        );
        if ($stmt) {
            $stmt->bind_param('s', $hook_name);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Check if we should drop tables
    $drop_tables = false;
    $settings_table = $tbl_prefix . 'pause_ads_settings';

    $check = $db->mysqli->query(
        "SELECT `setting_value` FROM `{$settings_table}` WHERE `setting_key` = 'drop_tables_on_uninstall'"
    );

    if ($check && $row = $check->fetch_assoc()) {
        $drop_tables = ($row['setting_value'] === '1');
    }

    if ($drop_tables) {
        $tables = [
            'pause_ads_clicks',
            'pause_ads_impressions',
            'pause_ads_delivery',
            'pause_ads_targeting',
            'pause_ads_avod_videos',
            'pause_ads_settings',
            'pause_ads_ads',
        ];

        foreach ($tables as $table) {
            $full_table = $tbl_prefix . $table;
            $db->mysqli->query("DROP TABLE IF EXISTS `{$full_table}`");
        }

        e('Pause Ads: All tables dropped.', 'm');
    } else {
        e('Pause Ads: Plugin hooks removed. Database tables preserved (change in settings to drop on uninstall).', 'm');
    }

    e('Pause Ads plugin uninstalled successfully.', 'm');
    return true;
}

/**
 * Helper: get table prefix
 */
function pause_ads_get_prefix()
{
    global $db;
    if (isset($db->db_prefix)) {
        return $db->db_prefix;
    }
    return 'cb_';
}

// Auto-run uninstallation when included
if (defined('STARTER')) {
    pause_ads_uninstall();
}
