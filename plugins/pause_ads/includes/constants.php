<?php
/**
 * Pause Ads Plugin - Constants
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

// Plugin version
define('PAUSE_ADS_VERSION', '1.0.0');

// Plugin directory paths
define('PAUSE_ADS_DIR', dirname(dirname(__FILE__)));
define('PAUSE_ADS_INCLUDES_DIR', PAUSE_ADS_DIR . '/includes');
define('PAUSE_ADS_ADMIN_DIR', PAUSE_ADS_DIR . '/admin');
define('PAUSE_ADS_AJAX_DIR', PAUSE_ADS_DIR . '/ajax');
define('PAUSE_ADS_ASSETS_DIR', PAUSE_ADS_DIR . '/assets');
define('PAUSE_ADS_UPLOADS_DIR', PAUSE_ADS_DIR . '/uploads');

// Plugin URL paths (relative to site root)
define('PAUSE_ADS_PLUGIN_URL', 'plugins/pause_ads');
define('PAUSE_ADS_ASSETS_URL', PAUSE_ADS_PLUGIN_URL . '/assets');
define('PAUSE_ADS_AJAX_URL', PAUSE_ADS_PLUGIN_URL . '/ajax');
define('PAUSE_ADS_UPLOADS_URL', PAUSE_ADS_PLUGIN_URL . '/uploads');

// Allowed image types for ad creatives
define('PAUSE_ADS_ALLOWED_TYPES', serialize([
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
]));

// Max upload size (5MB)
define('PAUSE_ADS_MAX_UPLOAD_SIZE', 5 * 1024 * 1024);

// Allowed image extensions
define('PAUSE_ADS_ALLOWED_EXTENSIONS', serialize([
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
]));

// Default settings
define('PAUSE_ADS_DEFAULT_MIN_PAUSE_MS', 1000);
define('PAUSE_ADS_DEFAULT_OVERLAY_POSITION', 'center');
define('PAUSE_ADS_DEFAULT_MAX_ADS_PER_MIN', 5);
