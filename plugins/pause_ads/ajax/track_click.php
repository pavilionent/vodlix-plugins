<?php
/**
 * Pause Ads Plugin - Track Click AJAX Endpoint
 *
 * Called when a viewer clicks on the ad overlay.
 * Records the click event.
 *
 * POST /plugins/pause_ads/ajax/track_click.php
 * Body (JSON or form):
 *   ad_id: int
 *   video_id: int
 *   impression_id: int (optional)
 *   token: string
 *
 * Response:
 * { "success": true }
 *
 * @package PauseAds
 */

// Bootstrap ClipBucket
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

if (!$bootstrapped) {
    define('STARTER', true);
}

require_once dirname(__FILE__) . '/../includes/constants.php';
require_once dirname(__FILE__) . '/../includes/db.php';
require_once dirname(__FILE__) . '/../includes/security.php';
require_once dirname(__FILE__) . '/../includes/functions.php';

// Set JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Parse input
$input = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

if (stripos($content_type, 'application/json') !== false) {
    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?: [];
} else {
    $input = $_POST;
}

// Validate required fields
$ad_id = isset($input['ad_id']) ? pause_ads_validate_int($input['ad_id']) : false;
$video_id = isset($input['video_id']) ? pause_ads_validate_int($input['video_id']) : false;
$impression_id = isset($input['impression_id']) ? pause_ads_validate_int($input['impression_id']) : null;
$token = isset($input['token']) ? trim((string) $input['token']) : '';

if ($ad_id === false || $ad_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid ad_id']);
    exit;
}

if ($video_id === false || $video_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid video_id']);
    exit;
}

// Verify token
$session_id = pause_ads_get_session_id();

if (empty($token) || !pause_ads_verify_impression_token($token, $ad_id, $video_id, $session_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

// Rate limiting for clicks
if (!pause_ads_rate_limit('click', 20)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limited']);
    exit;
}

// Get user info
$user_id = pause_ads_get_current_user_id();

// Record the click
$result = pause_ads_record_click(
    $ad_id,
    $video_id,
    $session_id,
    $user_id,
    $impression_id
);

if ($result !== false) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to record click']);
}

exit;
