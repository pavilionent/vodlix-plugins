<?php
/**
 * Churnkey Webhook Handler
 * Receives and processes webhook events from Churnkey
 * 
 * Configure this URL in your Churnkey dashboard:
 * https://yoursite.com/plugins/churnkey_stripe/webhook.php
 * 
 * @package ChurnkeyStripe
 */

// Prevent output buffering issues
while (ob_get_level()) {
    ob_end_clean();
}

// Set headers
header('Content-Type: application/json');

// Determine BASEDIR
$base_dir = dirname(dirname(dirname(__FILE__)));

// Try to load ClipBucket config
$config_loaded = false;
$config_paths = array(
    $base_dir . '/includes/config.inc.php',
    $base_dir . '/config.inc.php',
    dirname($base_dir) . '/includes/config.inc.php',
    dirname($base_dir) . '/config.inc.php'
);

foreach ($config_paths as $config_path) {
    if (file_exists($config_path)) {
        require_once $config_path;
        $config_loaded = true;
        break;
    }
}

// Define BASEDIR if not already defined
if (!defined('BASEDIR')) {
    define('BASEDIR', $base_dir);
}

// Load plugin files
$plugin_dir = dirname(__FILE__);
require_once $plugin_dir . '/includes/db_helper.php';
require_once $plugin_dir . '/includes/settings.php';
require_once $plugin_dir . '/includes/user.php';
require_once $plugin_dir . '/includes/stripe_client.php';
require_once $plugin_dir . '/includes/churnkey.php';

/**
 * Output JSON response and exit
 */
function webhook_response($success, $message = '', $status_code = 200) {
    http_response_code($status_code);
    echo json_encode(array(
        'success' => $success,
        'message' => $message
    ));
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    webhook_response(false, 'Method not allowed', 405);
}

// Get raw POST body
$payload = file_get_contents('php://input');

if (empty($payload)) {
    churnkey_stripe_log('warning', 'Webhook received empty payload');
    webhook_response(false, 'Empty payload', 400);
}

// Parse JSON payload
$data = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    churnkey_stripe_log('warning', 'Webhook received invalid JSON', array(
        'error' => json_last_error_msg()
    ));
    webhook_response(false, 'Invalid JSON', 400);
}

// Verify webhook signature if secret is configured
$webhook_secret = churnkey_stripe_get_webhook_secret();
if (!empty($webhook_secret)) {
    $signature = isset($_SERVER['HTTP_X_CHURNKEY_SIGNATURE']) 
        ? $_SERVER['HTTP_X_CHURNKEY_SIGNATURE'] 
        : (isset($_SERVER['HTTP_X_SIGNATURE']) ? $_SERVER['HTTP_X_SIGNATURE'] : '');
    
    if (empty($signature)) {
        churnkey_stripe_log('warning', 'Webhook missing signature');
        webhook_response(false, 'Missing signature', 401);
    }
    
    // Compute expected signature (HMAC-SHA256)
    $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
    
    // Compare signatures (timing-safe comparison)
    if (!hash_equals($expected_signature, $signature)) {
        churnkey_stripe_log('warning', 'Webhook signature mismatch', array(
            'received' => substr($signature, 0, 10) . '...'
        ));
        webhook_response(false, 'Invalid signature', 401);
    }
}

// Extract event details
$event_type = isset($data['type']) ? $data['type'] : (isset($data['event']) ? $data['event'] : 'unknown');
$event_id = isset($data['id']) ? $data['id'] : null;
$customer_id = isset($data['customer_id']) ? $data['customer_id'] : 
               (isset($data['customerId']) ? $data['customerId'] : 
               (isset($data['data']['customer_id']) ? $data['data']['customer_id'] : null));
$subscription_id = isset($data['subscription_id']) ? $data['subscription_id'] :
                   (isset($data['subscriptionId']) ? $data['subscriptionId'] :
                   (isset($data['data']['subscription_id']) ? $data['data']['subscription_id'] : null));

// Log the event
$event_log_id = churnkey_stripe_log_event($event_type, array(
    'id' => $event_id,
    'customer_id' => $customer_id,
    'subscription_id' => $subscription_id,
    'data' => $data,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
));

churnkey_stripe_log('info', 'Webhook received', array(
    'event_type' => $event_type,
    'event_id' => $event_id,
    'customer_id' => $customer_id
));

// Process the event based on type
try {
    switch ($event_type) {
        case 'subscription.canceled':
        case 'subscription_canceled':
        case 'cancel':
            // User canceled their subscription via Churnkey
            if ($customer_id) {
                $mapping = churnkey_stripe_get_mapping_by_customer($customer_id);
                if ($mapping) {
                    churnkey_stripe_update_status($mapping['userid'], 'canceled');
                    churnkey_stripe_log('info', 'Subscription marked as canceled', array(
                        'userid' => $mapping['userid'],
                        'customer_id' => $customer_id
                    ));
                }
            }
            break;
            
        case 'subscription.paused':
        case 'subscription_paused':
        case 'pause':
            // User paused their subscription
            if ($customer_id) {
                $mapping = churnkey_stripe_get_mapping_by_customer($customer_id);
                if ($mapping) {
                    // Update to a paused state (treating as canceled for now since we don't have a paused status)
                    churnkey_stripe_log('info', 'Subscription paused', array(
                        'userid' => $mapping['userid'],
                        'customer_id' => $customer_id
                    ));
                }
            }
            break;
            
        case 'subscription.reactivated':
        case 'subscription_reactivated':
        case 'reactivate':
            // User reactivated their subscription
            if ($customer_id) {
                $mapping = churnkey_stripe_get_mapping_by_customer($customer_id);
                if ($mapping) {
                    churnkey_stripe_update_status($mapping['userid'], 'active');
                    churnkey_stripe_log('info', 'Subscription reactivated', array(
                        'userid' => $mapping['userid'],
                        'customer_id' => $customer_id
                    ));
                }
            }
            break;
            
        case 'offer.accepted':
        case 'offer_accepted':
            // User accepted a retention offer
            churnkey_stripe_log('info', 'Retention offer accepted', array(
                'customer_id' => $customer_id,
                'offer_data' => $data
            ));
            break;
            
        case 'session.completed':
        case 'session_completed':
            // Churnkey session completed (regardless of outcome)
            churnkey_stripe_log('info', 'Churnkey session completed', array(
                'customer_id' => $customer_id,
                'outcome' => isset($data['outcome']) ? $data['outcome'] : 'unknown'
            ));
            break;
            
        case 'ping':
        case 'test':
            // Test webhook
            churnkey_stripe_log('info', 'Webhook test/ping received');
            break;
            
        default:
            // Unknown event type - log but don't fail
            churnkey_stripe_log('debug', 'Unknown webhook event type', array(
                'event_type' => $event_type,
                'data' => $data
            ));
            break;
    }
    
    // Mark event as processed
    if ($event_log_id) {
        churnkey_stripe_update_event_status($event_log_id, 'processed');
    }
    
    webhook_response(true, 'Event processed successfully');
    
} catch (Exception $e) {
    // Mark event as failed
    if ($event_log_id) {
        churnkey_stripe_update_event_status($event_log_id, 'failed', $e->getMessage());
    }
    
    churnkey_stripe_log('error', 'Webhook processing failed', array(
        'error' => $e->getMessage(),
        'event_type' => $event_type
    ));
    
    webhook_response(false, 'Processing failed: ' . $e->getMessage(), 500);
}
