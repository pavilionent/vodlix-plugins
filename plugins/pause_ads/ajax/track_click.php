<?php
/**
 * Pause Ads v2.0 - Track Click (AJAX)
 *
 * POST with JSON body:
 *   {creative_id, campaign_id, video_id, token}
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false]);
    exit;
}

$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($ct,'application/json') !== false) {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
} else {
    $input = $_POST;
}

$creative_id = pause_ads_validate_int($input['creative_id'] ?? '');
$campaign_id = pause_ads_validate_int($input['campaign_id'] ?? '');
$video_id    = pause_ads_validate_int($input['video_id'] ?? '');
$token       = trim((string)($input['token'] ?? ''));

if (!$creative_id || !$campaign_id || !$video_id) {
    http_response_code(400);
    echo json_encode(['success'=>false]);
    exit;
}

$session_id = pause_ads_get_session_id();
if (!pause_ads_verify_impression_token($token, $creative_id, $campaign_id, $video_id, $session_id)) {
    http_response_code(403);
    echo json_encode(['success'=>false]);
    exit;
}

if (!pause_ads_rate_limit('click', 20)) {
    http_response_code(429);
    echo json_encode(['success'=>false]);
    exit;
}

$camp = pa_campaign_get($campaign_id);
$company_id = $camp ? (int)$camp['company_id'] : 0;
$user_id = pause_ads_get_current_user_id();
$geo = pause_ads_resolve_geo();

$r = pause_ads_record_click($creative_id, $campaign_id, $company_id, $video_id, $session_id, $user_id, $geo['country_code']);
echo json_encode(['success' => $r !== false]);
exit;
