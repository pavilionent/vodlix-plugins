<?php
/**
 * Pause Ads v2.0 - Payment Callback
 *
 * Handles return from payment gateways (PayPal, Stripe, etc.)
 * and IPN/webhook notifications.
 *
 * GET /plugins/pause_ads/ajax/payment_callback.php?purchase_id=X&status=success
 *
 * @package PauseAds
 */

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
require_once __DIR__ . '/../includes/payment.php';

$purchase_id = pause_ads_validate_int($_GET['purchase_id'] ?? '');
$status = trim($_GET['status'] ?? '');
$ipn = isset($_GET['ipn']);

if (!$purchase_id) {
    die('Invalid request.');
}

$purchase = pa_purchase_get($purchase_id);
if (!$purchase) {
    die('Purchase not found.');
}

// Handle IPN (background notification)
if ($ipn) {
    // For PayPal IPN, verify and process
    $payment_ref = $_POST['txn_id'] ?? ($_GET['txn_id'] ?? 'IPN-' . time());
    $payment_status = strtolower($_POST['payment_status'] ?? 'completed');

    if ($payment_status === 'completed') {
        pause_ads_complete_purchase($purchase_id, $payment_ref, 'paypal');
    }

    http_response_code(200);
    echo 'OK';
    exit;
}

// Handle return redirect
if ($status === 'success') {
    // Mark as paid (the IPN may also arrive separately for verification)
    $ref = $_GET['tx'] ?? ($_GET['txn_id'] ?? 'RETURN-' . time());
    pause_ads_complete_purchase($purchase_id, $ref, 'gateway');

    // Redirect to advertiser billing page
    $base = pause_ads_get_base_url();
    header('Location: ' . $base . '/' . PAUSE_ADS_ADVERTISER_URL . '/billing.php?success=1&company_id=' . $purchase['company_id']);
    exit;
}

if ($status === 'cancel') {
    // Payment cancelled - campaign stays in pending_payment
    $base = pause_ads_get_base_url();
    header('Location: ' . $base . '/' . PAUSE_ADS_ADVERTISER_URL . '/billing.php?cancelled=1&company_id=' . $purchase['company_id']);
    exit;
}

// Default: redirect to billing
$base = pause_ads_get_base_url();
header('Location: ' . $base . '/' . PAUSE_ADS_ADVERTISER_URL . '/billing.php?company_id=' . $purchase['company_id']);
exit;
