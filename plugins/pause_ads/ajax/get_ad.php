<?php
/**
 * Pause Ads v2.0 - Get Eligible Ad (AJAX)
 *
 * GET /plugins/pause_ads/ajax/get_ad.php?video_id=123
 *
 * Returns JSON: {eligible, creative_id, campaign_id, image_url, click_url, alt_text, token}
 *
 * @package PauseAds
 */

// Bootstrap ClipBucket
$cb_root = realpath(__DIR__ . '/../../../');
foreach (['/includes/config.inc.php','/include/config.inc.php','/cb_config.php'] as $f) {
    if (file_exists($cb_root.$f)) { define('STARTER',true); require_once $cb_root.$f; break; }
}
if (!defined('STARTER')) define('STARTER',true);

require_once __DIR__ . '/../includes/constants.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/geo.php';
require_once __DIR__ . '/../includes/eligibility.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['eligible'=>false,'error'=>'Method not allowed']);
    exit;
}

$video_id = isset($_GET['video_id']) ? pause_ads_validate_int($_GET['video_id']) : false;
if ($video_id === false || $video_id <= 0) {
    http_response_code(400);
    echo json_encode(['eligible'=>false,'error'=>'Invalid video_id']);
    exit;
}

if (!pause_ads_is_enabled()) {
    echo json_encode(['eligible'=>false]);
    exit;
}

// Rate limit
$max = (int)pause_ads_get_setting('max_ads_per_session_per_minute','5');
if (!pause_ads_rate_limit('get_ad',$max)) {
    echo json_encode(['eligible'=>false,'error'=>'Rate limited']);
    exit;
}

$session_id = pause_ads_get_session_id();
$user_id = pause_ads_get_current_user_id();
$geo = pause_ads_resolve_geo();

$ad = pause_ads_find_eligible($video_id, $session_id, $user_id, $geo['country_code'], $geo['region_code']);

if (!$ad) {
    echo json_encode(['eligible'=>false]);
    exit;
}

echo json_encode($ad, JSON_UNESCAPED_SLASHES);
exit;
