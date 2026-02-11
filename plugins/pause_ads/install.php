<?php
/**
 * Pause Ads Plugin - Installation Script
 *
 * Creates required database tables and seeds default settings.
 * Compatible with ClipBucket's plugin installation conventions.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

/**
 * Run the pause_ads plugin installation.
 *
 * @return bool True on success
 */
function pause_ads_install()
{
    global $db;

    $tbl_prefix = tbl_prefix();

    // Generate a cryptographically secure salt for IP hashing
    $ip_salt = bin2hex(random_bytes(32));

    // Read schema SQL
    $schema_file = dirname(__FILE__) . '/sql/schema.sql';
    if (!file_exists($schema_file)) {
        e('Pause Ads: Schema file not found.', 'e');
        return false;
    }

    $sql = file_get_contents($schema_file);

    // Replace placeholders
    $sql = str_replace('{tbl_prefix}', $tbl_prefix, $sql);
    $sql = str_replace('{random_salt}', $db->mysqli->real_escape_string($ip_salt), $sql);

    // Split into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function ($s) {
            // Filter out empty and comment-only statements
            $s = trim($s);
            return $s !== '' && strpos($s, '--') !== 0;
        }
    );

    $errors = [];

    foreach ($statements as $stmt) {
        $stmt = trim($stmt);
        if (empty($stmt)) {
            continue;
        }

        try {
            $result = $db->mysqli->query($stmt);
            if ($result === false) {
                $errors[] = $db->mysqli->error . ' | SQL: ' . substr($stmt, 0, 100);
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        foreach ($errors as $err) {
            e('Pause Ads Install Error: ' . htmlspecialchars($err), 'e');
        }
        return false;
    }

    // Create uploads directory if it doesn't exist
    $upload_dir = dirname(__FILE__) . '/uploads';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Create .htaccess to prevent PHP execution in uploads
    $htaccess = $upload_dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, implode("\n", [
            '# Prevent PHP execution in uploads directory',
            '<FilesMatch "\\.php$">',
            '    Order Deny,Allow',
            '    Deny from all',
            '</FilesMatch>',
            '',
            '# Only allow image files',
            '<FilesMatch "\\.(jpg|jpeg|png|gif|webp|svg)$">',
            '    Order Allow,Deny',
            '    Allow from all',
            '</FilesMatch>',
        ]));
    }

    // Register plugin hooks in ClipBucket's system
    pause_ads_register_hooks();

    e('Pause Ads plugin installed successfully.', 'm');
    return true;
}

/**
 * Register plugin hooks with ClipBucket.
 */
function pause_ads_register_hooks()
{
    // ClipBucket hook registration
    // These hooks inject our assets and functionality
    $hooks = [
        [
            'hook_type'   => 'watch_page_right_side',
            'hook_name'   => 'pause_ads_inject_player_overlay',
            'hook_file'   => 'plugins/pause_ads/main.php',
            'hook_function' => 'pause_ads_inject_player_assets',
        ],
        [
            'hook_type'   => 'admin_left_menu',
            'hook_name'   => 'pause_ads_admin_menu',
            'hook_file'   => 'plugins/pause_ads/main.php',
            'hook_function' => 'pause_ads_admin_menu',
        ],
        [
            'hook_type'   => 'header',
            'hook_name'   => 'pause_ads_header',
            'hook_file'   => 'plugins/pause_ads/main.php',
            'hook_function' => 'pause_ads_enqueue_header',
        ],
        [
            'hook_type'   => 'footer',
            'hook_name'   => 'pause_ads_footer',
            'hook_file'   => 'plugins/pause_ads/main.php',
            'hook_function' => 'pause_ads_enqueue_footer',
        ],
        [
            'hook_type'   => 'video_edit_form',
            'hook_name'   => 'pause_ads_avod_toggle',
            'hook_file'   => 'plugins/pause_ads/main.php',
            'hook_function' => 'pause_ads_video_edit_avod_toggle',
        ],
        [
            'hook_type'   => 'video_edit_save',
            'hook_name'   => 'pause_ads_avod_save',
            'hook_file'   => 'plugins/pause_ads/main.php',
            'hook_function' => 'pause_ads_video_edit_avod_save',
        ],
    ];

    global $db;
    $tbl_prefix = tbl_prefix();

    foreach ($hooks as $hook) {
        // Check if hook already exists
        $check = $db->mysqli->prepare(
            "SELECT COUNT(*) as cnt FROM `{$tbl_prefix}plugin_hooks` WHERE `hook_name` = ?"
        );

        if ($check) {
            $check->bind_param('s', $hook['hook_name']);
            $check->execute();
            $result = $check->get_result();
            $row = $result->fetch_assoc();
            $check->close();

            if ($row['cnt'] > 0) {
                continue; // Hook already registered
            }
        }

        $insert = $db->mysqli->prepare(
            "INSERT INTO `{$tbl_prefix}plugin_hooks` (`hook_type`, `hook_name`, `hook_file`, `hook_function`)
             VALUES (?, ?, ?, ?)"
        );

        if ($insert) {
            $insert->bind_param(
                'ssss',
                $hook['hook_type'],
                $hook['hook_name'],
                $hook['hook_file'],
                $hook['hook_function']
            );
            $insert->execute();
            $insert->close();
        }
    }
}

/**
 * Helper: get table prefix
 */
function tbl_prefix()
{
    global $db;
    if (isset($db->db_prefix)) {
        return $db->db_prefix;
    }
    // Fallback: ClipBucket default
    return 'cb_';
}

// Auto-run installation when included
if (defined('STARTER')) {
    pause_ads_install();
}
