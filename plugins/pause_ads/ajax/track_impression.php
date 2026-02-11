<?php
/**
 * Pause Ads Plugin - Track Impression AJAX Endpoint
 *
 * Called after a pause event lasts longer than the minimum threshold.
 * Records one impression per qualifying pause event.
 *
 * POST /plugins/pause_ads/ajax/track_impression.php
 * Body (JSON or form):
 *   ad_id: int
 *   video_id: int
 *   token: string (HMAC token from get_ad response)
 *   pause_duration_ms: int (optional)
 *
 * Response:
 * { "success": true, "impression_id": 123 }
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

// Parse input (support both JSON and form-encoded)
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
$token = isset($input['token']) ? trim((string) $input['token']) : '';
$pause_duration_ms = isset($input['pause_duration_ms']) ? (int) $input['pause_duration_ms'] : 0;

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

if (empty($token)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing token']);
    exit;
}

// Get session and user info
$session_id = pause_ads_get_session_id();
$user_id = pause_ads_get_current_user_id();

// Verify token integrity
if (!pause_ads_verify_impression_token($token, $ad_id, $video_id, $session_id)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid token']);
    exit;
}

// Rate limiting - prevent impression spam
if (!pause_ads_rate_limit('impression', 10)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limited']);
    exit;
}

// Check minimum pause duration
$min_pause = (int) pause_ads_get_setting('min_pause_ms', '1000');
if ($pause_duration_ms > 0 && $pause_duration_ms < $min_pause) {
    echo json_encode(['success' => false, 'error' => 'Pause too short']);
    exit;
}

// Record the impression
$impression_id = pause_ads_record_impression(
    $ad_id,
    $video_id,
    $session_id,
    $user_id,
    $pause_duration_ms
);

if ($impression_id !== false) {
    echo json_encode([
        'success'       => true,
        'impression_id' => (int) $impression_id,
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to record impression']);
}

exit;
