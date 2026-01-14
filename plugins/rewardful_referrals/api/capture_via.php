<?php
/**
 * Rewardful Referrals - Via Token Capture Endpoint
 * 
 * Captures via token from URL and stores in session
 * Called by client-side JS to ensure server-side attribution
 */

if (!defined('BASEDIR')) {
    define('BASEDIR', dirname(dirname(dirname(dirname(__FILE__)))));
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include ClipBucket core (optional, for logging)
if (file_exists(BASEDIR . '/includes/config.inc.php')) {
    require_once BASEDIR . '/includes/config.inc.php';
    
    // Load plugin classes for logging
    require_once dirname(__DIR__) . '/lib/Db.php';
    require_once dirname(__DIR__) . '/lib/Auth.php';
    
    $auth = new RewardfulReferrals\Auth();
}

// Get via token
$viaToken = isset($_GET['via']) ? $_GET['via'] : '';

// Validate token format
if (!empty($viaToken) && preg_match('/^[a-zA-Z0-9_-]+$/', $viaToken)) {
    // Store in session
    $_SESSION['rewardful_via_token'] = $viaToken;
    $_SESSION['rewardful_via_timestamp'] = time();
    
    // Return 1x1 transparent GIF
    header('Content-Type: image/gif');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 1x1 transparent GIF
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
} else {
    // Invalid token
    http_response_code(400);
    header('Content-Type: image/gif');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
}
