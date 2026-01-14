<?php
/**
 * Churnkey Context API Endpoint
 * Returns signed Churnkey context for the current logged-in user
 * 
 * SECURITY:
 * - Only returns context for the currently authenticated user
 * - Does not accept arbitrary customerId input
 * - Never exposes API keys or secrets client-side
 * 
 * @package ChurnkeyStripe
 */

// Allow CORS for same-origin requests (AJAX from account page)
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Determine BASEDIR
$base_dir = dirname(dirname(dirname(dirname(__FILE__))));

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

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load plugin files
$plugin_dir = dirname(dirname(__FILE__));
require_once $plugin_dir . '/includes/db_helper.php';
require_once $plugin_dir . '/includes/settings.php';
require_once $plugin_dir . '/includes/user.php';
require_once $plugin_dir . '/includes/stripe_client.php';
require_once $plugin_dir . '/includes/churnkey.php';

/**
 * Output JSON response and exit
 */
function output_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

/**
 * Output error response and exit
 */
function output_error($message, $status_code = 400) {
    output_response(array(
        'success' => false,
        'error' => $message
    ), $status_code);
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    output_error('Method not allowed', 405);
}

// Verify request is AJAX (basic check)
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Allow non-AJAX for testing but log it
if (!$is_ajax) {
    churnkey_stripe_log('debug', 'Non-AJAX request to context API', array(
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ));
}

// Check if user is logged in
if (!churnkey_stripe_is_user_logged_in()) {
    churnkey_stripe_log('debug', 'Context API called without authentication');
    output_error('Not logged in', 401);
}

// Check if plugin is enabled
if (!churnkey_stripe_is_enabled()) {
    churnkey_stripe_log('debug', 'Context API called but plugin is disabled');
    output_error('Service temporarily unavailable', 503);
}

// Get context for current user
$result = churnkey_stripe_get_context_for_current_user();

if (!$result['success']) {
    // Map specific errors to appropriate HTTP status codes
    $error = $result['error'];
    $status_code = 400;
    
    if (strpos($error, 'Not logged in') !== false) {
        $status_code = 401;
    } elseif (strpos($error, 'not configured') !== false) {
        $status_code = 503;
    } elseif (strpos($error, 'No active subscription') !== false || 
              strpos($error, 'No Stripe customer') !== false) {
        $status_code = 404;
    }
    
    output_error($error, $status_code);
}

// Success - return context
// Note: context contains: appId, mode, record, provider, customerId, subscriptionId, authHash
output_response(array(
    'success' => true,
    'context' => $result['context']
));
