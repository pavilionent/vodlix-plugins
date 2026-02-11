<?php
/**
 * Pause Ads v2.0 - Track Impression (AJAX)
 *
 * POST with JSON body:
 *   {creative_id, campaign_id, video_id, token, pause_duration_ms}
 *
 * @package PauseAds
 */

$cb_root = realpath(__DIR__ . '/../../../');
foreach (['/includes/config.inc.php','/include/config.inc.php','/cb_config.php'] as $f) {
    if (file_exists($cb_root.$f)) { define('STARTER',true); require_once $cb_root.$f; break; }
}
if (!defined('STARTER')) define('STARTER',true);

define('PAUSE_ADS_AJAX', true);
require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/geo.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'error'=>'Method not allowed']);
    exit;
}

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct,'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

$creative_id  = pause_ads_validate_int($input['creative_id'] ?? '');
$campaign_id  = pause_ads_validate_int($input['campaign_id'] ?? '');
$video_id     = pause_ads_validate_int($input['video_id'] ?? '');
$token        = trim((string)($input['token'] ?? ''));
$pause_ms     = (int)($input['pause_duration_ms'] ?? 0);

if (!$creative_id || !$campaign_id || !$video_id) {
    http_response_code(400);
    echo json_encode(['success'=>false,'error'=>'Missing required fields']);
    exit;
}

$session_id = pause_ads_get_session_id();
$user_id = pause_ads_get_current_user_id();

// Verify token
if (!pause_ads_verify_impression_token($token, $creative_id, $campaign_id, $video_id, $session_id)) {
    http_response_code(403);
    echo json_encode(['success'=>false,'error'=>'Invalid token']);
    exit;
}

// Rate limit
if (!pause_ads_rate_limit('impression', 10)) {
    http_response_code(429);
    echo json_encode(['success'=>false,'error'=>'Rate limited']);
    exit;
}

// Check min pause
$min_pause = (int)pause_ads_get_setting('min_pause_ms','1000');
if ($pause_ms > 0 && $pause_ms < $min_pause) {
    echo json_encode(['success'=>false,'error'=>'Pause too short']);
    exit;
}

// Look up company_id from campaign
$camp = pa_campaign_get($campaign_id);
$company_id = $camp ? (int)$camp['company_id'] : 0;

// Geo for logging
$geo = pause_ads_resolve_geo();

// Record impression
$imp_id = pause_ads_record_impression(
    $creative_id, $campaign_id, $company_id, $video_id,
    $session_id, $user_id, $geo['country_code'], $geo['region_code']
);

if ($imp_id !== false) {
    // Decrement purchased allocation
    pause_ads_decrement_impression_allocation($campaign_id);

    echo json_encode(['success'=>true,'impression_id'=>(int)$imp_id]);
} else {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'Record failed']);
}
exit;
