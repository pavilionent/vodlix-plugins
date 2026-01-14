<?php
/**
 * Rewardful Referrals - SSO Redirect Endpoint
 * 
 * Generates a Rewardful SSO magic link and redirects approved referrer to dashboard
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

$db = new Db();
$auth = new Auth();
$service = new RewardfulService();

// Verify user is logged in
if (!$auth->isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    die('Please log in to access this page.');
}

// Verify user is an approved referrer
if (!$auth->isApprovedReferrer() && !$auth->isAdmin()) {
    header('HTTP/1.1 403 Forbidden');
    die('You are not authorized to access this page.');
}

// Get referrer record
$referrer = $auth->getCurrentReferrer();

if (!$referrer) {
    header('HTTP/1.1 404 Not Found');
    die('Referrer record not found.');
}

// Generate SSO link
$result = $service->generateSSOLink($referrer['id']);

if (!$result['success']) {
    // Log the error
    $db->log('error', 'sso_redirect_failed', 
        'Failed to generate SSO link: ' . ($result['error'] ?? 'Unknown error'),
        array('referrer_id' => $referrer['id']),
        $auth->getCurrentUserId()
    );
    
    header('HTTP/1.1 500 Internal Server Error');
    die('Unable to generate dashboard link. Please try again later or contact support.');
}

// Redirect to SSO URL
$ssoUrl = $result['url'];

// Log successful SSO redirect
$db->log('info', 'sso_redirect', 
    'User redirected to Rewardful dashboard',
    array('referrer_id' => $referrer['id']),
    $auth->getCurrentUserId()
);

header('Location: ' . $ssoUrl);
exit;
