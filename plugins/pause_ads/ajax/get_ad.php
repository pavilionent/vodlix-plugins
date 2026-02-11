<?php
/**
 * Pause Ads Plugin - Get Eligible Ad AJAX Endpoint
 *
 * Called by the frontend JS on each pause event to fetch a
 * random eligible ad. Returns JSON.
 *
 * GET /plugins/pause_ads/ajax/get_ad.php?video_id=123
 *
 * Response (success):
 * {
 *   "eligible": true,
 *   "ad_id": 5,
 *   "name": "Summer Sale",
 *   "image_url": "plugins/pause_ads/uploads/ad_123_abc.jpg",
 *   "click_url": "https://example.com/offer",
 *   "alt_text": "Summer Sale - 50% off",
 *   "token": "hmac_token_here"
 * }
 *
 * Response (no ad):
 * { "eligible": false }
 *
 * @package PauseAds
 */

// Bootstrap ClipBucket if available
$cb_root = realpath(dirname(__FILE__) . '/../../../');
$bootstrap_files = [
    $cb_root . '/includes/config.inc.php',
    $cb_root . '/include/config.inc.php',
    $cb_root . '/cb_config.php',
];

$bootstrapped = false;
foreach ($bootstrap_files as $bf) {
    if (file_exists($bf)) {
        define('STARTER', true);
        require_once $bf;
        $bootstrapped = true;
        break;
    }
}

// If ClipBucket bootstrap not found, define STARTER for standalone mode
if (!$bootstrapped) {
    define('STARTER', true);

    // Minimal DB connection for standalone testing
    // In production, ClipBucket's config handles this
}

// Load plugin files
require_once dirname(__FILE__) . '/../includes/constants.php';
require_once dirname(__FILE__) . '/../includes/db.php';
require_once dirname(__FILE__) . '/../includes/security.php';
require_once dirname(__FILE__) . '/../includes/functions.php';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

// CORS headers for AJAX
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (!empty($origin)) {
    // Only allow same-origin in production
    $base = pause_ads_get_base_url();
    if (strpos($origin, parse_url($base, PHP_URL_HOST)) !== false) {
        header('Access-Control-Allow-Origin: ' . $origin);
    }
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['eligible' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate video_id
$video_id = isset($_GET['video_id']) ? pause_ads_validate_int($_GET['video_id']) : false;

if ($video_id === false || $video_id <= 0) {
    http_response_code(400);
    echo json_encode(['eligible' => false, 'error' => 'Invalid video_id']);
    exit;
}

// Check plugin is enabled
if (!pause_ads_is_enabled()) {
    echo json_encode(['eligible' => false]);
    exit;
}

// Rate limiting
$max_per_min = (int) pause_ads_get_setting('max_ads_per_session_per_minute', '5');
if (!pause_ads_rate_limit('get_ad', $max_per_min)) {
    echo json_encode(['eligible' => false, 'error' => 'Rate limited']);
    exit;
}

// Get session and user info
$session_id = pause_ads_get_session_id();
$user_id = pause_ads_get_current_user_id();

// Find eligible ad
$ad = pause_ads_find_eligible_ad($video_id, $session_id, $user_id);

if (!$ad) {
    echo json_encode(['eligible' => false]);
    exit;
}

// Return the ad data
echo json_encode($ad, JSON_UNESCAPED_SLASHES);
exit;
