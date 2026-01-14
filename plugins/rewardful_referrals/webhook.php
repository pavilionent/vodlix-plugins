<?php
/**
 * Rewardful Referrals - Webhook Endpoint
 * 
 * Receives and processes webhook events from Rewardful
 * Verifies signature using HMAC-SHA256
 */

if (!defined('BASEDIR')) {
    define('BASEDIR', dirname(dirname(dirname(__FILE__))));
}

// Include ClipBucket core
require_once BASEDIR . '/includes/config.inc.php';

// Load plugin classes
require_once __DIR__ . '/lib/Db.php';
require_once __DIR__ . '/lib/RewardfulClient.php';
require_once __DIR__ . '/lib/RewardfulService.php';

use RewardfulReferrals\Db;
use RewardfulReferrals\RewardfulService;

$db = new Db();
$service = new RewardfulService();

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Method not allowed'));
    exit;
}

// Get raw POST body
$payload = file_get_contents('php://input');

if (empty($payload)) {
    $db->log('webhook', 'webhook_empty', 'Empty webhook payload received');
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Empty payload'));
    exit;
}

// Get signature from header
$signature = '';
$signatureHeaders = array(
    'HTTP_REWARDFUL_SIGNATURE',
    'HTTP_X_REWARDFUL_SIGNATURE',
    'HTTP_SIGNATURE'
);

foreach ($signatureHeaders as $header) {
    if (isset($_SERVER[$header])) {
        $signature = $_SERVER[$header];
        break;
    }
}

// Check if webhook secret is configured
$webhookSecret = $db->getSetting('webhook_secret', '');

if (!empty($webhookSecret)) {
    // Verify signature
    if (empty($signature)) {
        $db->log('webhook', 'webhook_no_signature', 
            'Webhook received without signature',
            array('headers' => getallheaders())
        );
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'Missing signature'));
        exit;
    }
    
    // Verify HMAC-SHA256 signature
    if (!$service->verifyWebhookSignature($payload, $signature)) {
        $db->log('webhook', 'webhook_invalid_signature', 
            'Webhook signature verification failed',
            array('signature_received' => substr($signature, 0, 20) . '...')
        );
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(array('error' => 'Invalid signature'));
        exit;
    }
}

// Parse payload
$data = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $db->log('webhook', 'webhook_invalid_json', 
        'Invalid JSON payload: ' . json_last_error_msg(),
        array('payload_length' => strlen($payload))
    );
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(array('error' => 'Invalid JSON'));
    exit;
}

// Extract event type
$eventType = $data['type'] ?? $data['event'] ?? 'unknown';

// Log received webhook
$db->log('webhook', 'webhook_received', 
    "Webhook received: {$eventType}",
    array(
        'event_type' => $eventType,
        'payload_keys' => array_keys($data)
    )
);

// Process the webhook
try {
    $result = $service->processWebhook($eventType, $data);
    
    if ($result['success']) {
        if (!empty($result['duplicate'])) {
            // Already processed
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => true,
                'message' => 'Event already processed'
            ));
        } else {
            // Successfully processed
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => true,
                'message' => 'Webhook processed successfully'
            ));
        }
    } else {
        // Processing failed
        $db->log('webhook', 'webhook_processing_failed', 
            "Webhook processing failed: " . ($result['error'] ?? 'Unknown error'),
            array('event_type' => $eventType)
        );
        
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => false,
            'error' => $result['error'] ?? 'Processing failed'
        ));
    }
    
} catch (Exception $e) {
    $db->log('error', 'webhook_exception', 
        "Webhook exception: " . $e->getMessage(),
        array(
            'event_type' => $eventType,
            'exception' => $e->getMessage()
        )
    );
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(array(
        'success' => false,
        'error' => 'Internal server error'
    ));
}
