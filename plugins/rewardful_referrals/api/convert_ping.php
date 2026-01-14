<?php
/**
 * Rewardful Referrals - Conversion Ping Endpoint
 * 
 * Called by client-side JS after conversion is fired
 * Updates conversion status to 'sent'
 */

if (!defined('BASEDIR')) {
    define('BASEDIR', dirname(dirname(dirname(dirname(__FILE__)))));
}

// Include ClipBucket core
require_once BASEDIR . '/includes/config.inc.php';

// Load plugin classes
require_once dirname(__DIR__) . '/lib/Db.php';
require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/RewardfulService.php';

use RewardfulReferrals\Db;
use RewardfulReferrals\RewardfulService;

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('error' => 'Method not allowed'));
    exit;
}

$db = new Db();
$service = new RewardfulService();

// Get conversion ID from request
$conversionId = isset($_POST['conversion_id']) ? (int)$_POST['conversion_id'] : 0;

if ($conversionId <= 0) {
    http_response_code(400);
    echo json_encode(array('error' => 'Invalid conversion ID'));
    exit;
}

// Update conversion status
$result = $service->markConversionSent($conversionId);

if ($result) {
    $db->log('conversion', 'conversion_sent', 
        "Client confirmed conversion sent to Rewardful",
        array('conversion_id' => $conversionId)
    );
    
    echo json_encode(array(
        'success' => true,
        'message' => 'Conversion marked as sent'
    ));
} else {
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'error' => 'Failed to update conversion status'
    ));
}
