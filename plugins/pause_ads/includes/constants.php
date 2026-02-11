<?php
/**
 * Pause Ads Plugin v2.0 - Constants
 * @package PauseAds
 */
if (!defined('STARTER')) { die('No direct access allowed.'); }

define('PAUSE_ADS_VERSION', '2.0.0');

define('PAUSE_ADS_DIR', dirname(dirname(__FILE__)));
define('PAUSE_ADS_INCLUDES_DIR', PAUSE_ADS_DIR . '/includes');
define('PAUSE_ADS_ADMIN_DIR', PAUSE_ADS_DIR . '/admin');
define('PAUSE_ADS_ADVERTISER_DIR', PAUSE_ADS_DIR . '/advertiser');
define('PAUSE_ADS_AJAX_DIR', PAUSE_ADS_DIR . '/ajax');
define('PAUSE_ADS_ASSETS_DIR', PAUSE_ADS_DIR . '/assets');
define('PAUSE_ADS_UPLOADS_DIR', PAUSE_ADS_DIR . '/uploads');

define('PAUSE_ADS_PLUGIN_URL', 'plugins/pause_ads');
define('PAUSE_ADS_ASSETS_URL', PAUSE_ADS_PLUGIN_URL . '/assets');
define('PAUSE_ADS_AJAX_URL', PAUSE_ADS_PLUGIN_URL . '/ajax');
define('PAUSE_ADS_UPLOADS_URL', PAUSE_ADS_PLUGIN_URL . '/uploads');
define('PAUSE_ADS_ADVERTISER_URL', PAUSE_ADS_PLUGIN_URL . '/advertiser');

define('PAUSE_ADS_ALLOWED_TYPES', serialize([
    'image/jpeg','image/png','image/gif','image/webp','image/svg+xml',
]));
define('PAUSE_ADS_MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
define('PAUSE_ADS_ALLOWED_EXTENSIONS', serialize([
    'jpg','jpeg','png','gif','webp','svg',
]));

// Campaign statuses
define('PA_STATUS_DRAFT',           'draft');
define('PA_STATUS_PENDING_PAYMENT', 'pending_payment');
define('PA_STATUS_PENDING_REVIEW',  'pending_review');
define('PA_STATUS_ACTIVE',          'active');
define('PA_STATUS_PAUSED',          'paused');
define('PA_STATUS_ENDED',           'ended');
define('PA_STATUS_REJECTED',        'rejected');
