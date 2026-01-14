<?php
/**
 * Rewardful Referrals - Get Referral Link API
 * 
 * Returns the referral link and token for approved referrers
 */

if (!defined('BASEDIR')) {
    define('BASEDIR', dirname(dirname(dirname(dirname(__FILE__)))));
}

// Include ClipBucket core
require_once BASEDIR . '/includes/config.inc.php';

// Load plugin classes
require_once dirname(__DIR__) . '/lib/Db.php';
require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/RewardfulClient.php';
require_once dirname(__DIR__) . '/lib/RewardfulService.php';

use RewardfulReferrals\Db;
use RewardfulReferrals\Auth;
use RewardfulReferrals\RewardfulService;

header('Content-Type: application/json');

$auth = new Auth();
$service = new RewardfulService();

// Verify user is logged in
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'error' => 'Authentication required'
    ));
    exit;
}

// Verify user is an approved referrer
if (!$auth->isApprovedReferrer() && !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'error' => 'Not an approved referrer'
    ));
    exit;
}

// Get referrer record
$referrer = $auth->getCurrentReferrer();

if (!$referrer) {
    http_response_code(404);
    echo json_encode(array(
        'success' => false,
        'error' => 'Referrer record not found'
    ));
    exit;
}

// Get referral link
$result = $service->getReferralLink($referrer['id']);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => $result['error'] ?? 'Failed to get referral link'
    ));
    exit;
}

echo json_encode(array(
    'success' => true,
    'token' => $result['token'],
    'link' => $result['link'],
    'rewardful_link' => $result['rewardful_link'] ?? null
));
