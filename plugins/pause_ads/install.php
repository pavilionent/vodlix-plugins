<?php
/**
 * Pause Ads Plugin v2.0 - Installation Script
 *
 * Creates all required database tables, seeds default settings
 * and pricing packages, and registers ClipBucket hooks.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

function pause_ads_install()
{
    global $db;

    $prefix = pause_ads_install_prefix();
    $ip_salt = bin2hex(random_bytes(32));

    // -- 1. Read and execute schema -------------------------------------------
    $schema_file = dirname(__FILE__) . '/sql/schema.sql';
    if (!file_exists($schema_file)) {
        e('Pause Ads: Schema file not found.', 'e');
        return false;
    }

    $sql = file_get_contents($schema_file);
    $sql = str_replace('{tbl_prefix}', $prefix, $sql);

    $statements = array_filter(array_map('trim', explode(';', $sql)), function ($s) {
        $s = trim($s);
        return $s !== '' && strpos($s, '--') !== 0;
    });

    $errors = [];
    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) continue;
        try {
            if ($db->mysqli->query($stmt) === false) {
                $errors[] = $db->mysqli->error . ' | ' . substr($stmt, 0, 120);
            }
        } catch (Exception $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!empty($errors)) {
        foreach ($errors as $err) {
            e('Pause Ads Install: ' . htmlspecialchars($err), 'e');
        }
        return false;
    }

    // -- 2. Seed default settings ---------------------------------------------
    $settings_table = $prefix . 'pause_ads_settings';
    $defaults = [
        'enabled'                       => '1',
        'min_pause_ms'                  => '1000',
        'overlay_position'              => 'center',
        'overlay_style'                 => 'semi-transparent',
        'fallback_behavior'             => 'none',
        'ip_salt'                       => $ip_salt,
        'max_ads_per_session_per_minute' => '5',
        'drop_tables_on_uninstall'      => '0',
        'require_campaign_approval'     => '0',
        'default_currency'              => 'USD',
        'tax_rate_percent'              => '0',
        'geo_provider'                  => 'ip-api',
        'platform_name'                 => 'Pause Ads',
        'invoice_prefix'                => 'PA',
    ];

    foreach ($defaults as $k => $v) {
        $esc_k = $db->mysqli->real_escape_string($k);
        $esc_v = $db->mysqli->real_escape_string($v);
        $db->mysqli->query(
            "INSERT IGNORE INTO `{$settings_table}` (`setting_key`,`setting_value`) VALUES ('{$esc_k}','{$esc_v}')"
        );
    }

    // -- 3. Seed default packages ---------------------------------------------
    $pkg_table = $prefix . 'pause_ads_packages';
    $count = $db->mysqli->query("SELECT COUNT(*) as c FROM `{$pkg_table}`")->fetch_assoc()['c'];
    if ((int)$count === 0) {
        $packages = [
            ['Starter',    250.00,  'USD', 10000,   30, 'active', 1],
            ['Growth',    1000.00,  'USD', 50000,   60, 'active', 2],
            ['Scale',     4000.00,  'USD', 250000,  90, 'active', 3],
            ['Enterprise',   0.00,  'USD', 0,     NULL, 'inactive', 4],
        ];
        foreach ($packages as $p) {
            $max_flight = $p[4] === null ? 'NULL' : (int)$p[4];
            $db->mysqli->query(
                "INSERT INTO `{$pkg_table}` (`name`,`price_amount`,`currency`,`included_impressions`,`max_flight_days`,`status`,`sort_order`)
                 VALUES ('" . $db->mysqli->real_escape_string($p[0]) . "', {$p[1]}, '{$p[2]}', {$p[3]}, {$max_flight}, '{$p[5]}', {$p[6]})"
            );
        }
    }

    // -- 4. Create uploads directory ------------------------------------------
    $upload_dir = dirname(__FILE__) . '/uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $htaccess = $upload_dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, implode("\n", [
            '<FilesMatch "\\.php$">',
            '    Order Deny,Allow',
            '    Deny from all',
            '</FilesMatch>',
            '<FilesMatch "\\.(jpg|jpeg|png|gif|webp|svg)$">',
            '    Order Allow,Deny',
            '    Allow from all',
            '</FilesMatch>',
        ]));
    }

    // -- 5. Register hooks ----------------------------------------------------
    pause_ads_install_register_hooks($prefix);

    e('Pause Ads v2.0 installed successfully.', 'm');
    return true;
}

function pause_ads_install_register_hooks($prefix)
{
    global $db;
    $hooks = [
        ['watch_page_right_side', 'pause_ads_inject_player_overlay', 'plugins/pause_ads/main.php', 'pause_ads_inject_player_assets'],
        ['admin_left_menu',       'pause_ads_admin_menu',            'plugins/pause_ads/main.php', 'pause_ads_admin_menu'],
        ['header',                'pause_ads_header',                'plugins/pause_ads/main.php', 'pause_ads_enqueue_header'],
        ['footer',                'pause_ads_footer',                'plugins/pause_ads/main.php', 'pause_ads_enqueue_footer'],
        ['video_edit_form',       'pause_ads_avod_toggle',           'plugins/pause_ads/main.php', 'pause_ads_video_edit_avod_toggle'],
        ['video_edit_save',       'pause_ads_avod_save',             'plugins/pause_ads/main.php', 'pause_ads_video_edit_avod_save'],
    ];

    // Attempt hook registration – table may not exist on all CB versions
    foreach ($hooks as $h) {
        $check = @$db->mysqli->query(
            "SELECT 1 FROM `{$prefix}plugin_hooks` WHERE `hook_name`='" . $db->mysqli->real_escape_string($h[1]) . "' LIMIT 1"
        );
        if ($check && $check->num_rows > 0) continue;
        @$db->mysqli->query(
            "INSERT INTO `{$prefix}plugin_hooks` (`hook_type`,`hook_name`,`hook_file`,`hook_function`) VALUES (
                '" . $db->mysqli->real_escape_string($h[0]) . "',
                '" . $db->mysqli->real_escape_string($h[1]) . "',
                '" . $db->mysqli->real_escape_string($h[2]) . "',
                '" . $db->mysqli->real_escape_string($h[3]) . "'
            )"
        );
    }
}

function pause_ads_install_prefix()
{
    global $db;
    return isset($db->db_prefix) ? $db->db_prefix : 'cb_';
}

// Auto-run
if (defined('STARTER')) {
    pause_ads_install();
}
