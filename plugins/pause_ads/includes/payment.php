<?php
/**
 * Pause Ads v2.0 - Payment Integration
 *
 * Hooks into ClipBucket's existing payment gateways to process
 * package purchases. Falls back to a "manual / offline" flow
 * if no gateway is configured.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) { die('No direct access allowed.'); }

/**
 * Initiate a purchase for a package and campaign.
 * Creates a pending purchase record and returns checkout info.
 *
 * @param int $company_id
 * @param int $user_id
 * @param int $package_id
 * @param int $campaign_id
 * @return array ['purchase_id'=>int, 'checkout_url'=>string|null, 'method'=>string]
 */
function pause_ads_initiate_purchase($company_id, $user_id, $package_id, $campaign_id)
{
    $pkg = pa_package_get($package_id);
    if (!$pkg || $pkg['status'] !== 'active') {
        return ['error' => 'Package not available.'];
    }

    // Calculate tax
    $tax_rate = (float) pause_ads_get_setting('tax_rate_percent', '0');
    $subtotal = (float) $pkg['price_amount'];
    $tax = round($subtotal * $tax_rate / 100, 2);
    $total = $subtotal + $tax;
    $currency = $pkg['currency'] ?: pause_ads_get_setting('default_currency', 'USD');

    // Create pending purchase
    $purchase_id = pa_purchase_create([
        'company_id'          => $company_id,
        'user_id'             => $user_id,
        'package_id'          => $package_id,
        'campaign_id'         => $campaign_id,
        'impressions_granted' => (int) $pkg['included_impressions'],
        'amount_paid'         => $total,
        'currency'            => $currency,
        'payment_status'      => 'pending',
        'payment_gateway'     => '',
        'payment_ref'         => '',
    ]);

    if (!$purchase_id) {
        return ['error' => 'Failed to create purchase record.'];
    }

    // Update campaign status to pending_payment
    pa_campaign_update($campaign_id, ['status' => PA_STATUS_PENDING_PAYMENT]);

    // Try ClipBucket payment gateways
    $checkout_url = pause_ads_get_gateway_url($purchase_id, $total, $currency, $pkg['name']);

    return [
        'purchase_id'  => $purchase_id,
        'checkout_url' => $checkout_url,
        'total'        => $total,
        'currency'     => $currency,
        'method'       => $checkout_url ? 'gateway' : 'manual',
    ];
}

/**
 * Try to get a checkout URL from ClipBucket's payment system.
 *
 * @param int    $purchase_id
 * @param float  $amount
 * @param string $currency
 * @param string $description
 * @return string|null
 */
function pause_ads_get_gateway_url($purchase_id, $amount, $currency, $description)
{
    $base = pause_ads_get_base_url();
    $callback = $base . '/' . PAUSE_ADS_AJAX_URL . '/payment_callback.php?purchase_id=' . $purchase_id;

    // Check for ClipBucket payment integration (PayPal, Stripe, etc.)
    if (function_exists('create_payment')) {
        // ClipBucket native payment helper
        try {
            $url = create_payment([
                'amount'      => $amount,
                'currency'    => $currency,
                'description' => 'Pause Ads: ' . $description,
                'return_url'  => $callback . '&status=success',
                'cancel_url'  => $callback . '&status=cancel',
                'custom'      => 'pause_ads_' . $purchase_id,
            ]);
            if ($url) return $url;
        } catch (Exception $e) {
            error_log('Pause Ads payment error: ' . $e->getMessage());
        }
    }

    // Check for PayPal settings in ClipBucket
    global $cbpayment, $Cbucket;
    $paypal_email = '';
    if (isset($cbpayment) && method_exists($cbpayment, 'get_paypal_email')) {
        $paypal_email = $cbpayment->get_paypal_email();
    } elseif (function_exists('get_setting')) {
        $paypal_email = get_setting('paypal_email');
    }

    if (!empty($paypal_email)) {
        // Build PayPal checkout URL
        $paypal_url = 'https://www.paypal.com/cgi-bin/webscr?' . http_build_query([
            'cmd'           => '_xclick',
            'business'      => $paypal_email,
            'item_name'     => 'Pause Ads: ' . $description,
            'amount'        => number_format($amount, 2, '.', ''),
            'currency_code' => $currency,
            'return'        => $callback . '&status=success',
            'cancel_return' => $callback . '&status=cancel',
            'notify_url'    => $callback . '&ipn=1',
            'custom'        => 'pause_ads_' . $purchase_id,
            'no_shipping'   => '1',
        ]);
        return $paypal_url;
    }

    // No gateway available – return null (manual flow)
    return null;
}

/**
 * Complete a purchase after successful payment.
 * Activates the campaign if approvals are not required.
 *
 * @param int    $purchase_id
 * @param string $payment_ref
 * @param string $gateway
 * @return bool
 */
function pause_ads_complete_purchase($purchase_id, $payment_ref = '', $gateway = '')
{
    $purchase = pa_purchase_get($purchase_id);
    if (!$purchase) return false;
    if ($purchase['payment_status'] === 'paid') return true; // idempotent

    // Mark paid
    pa_purchase_mark_paid($purchase_id, $payment_ref, $gateway);

    // Generate invoice
    $pkg = pa_package_get($purchase['package_id']);
    $tax_rate = (float) pause_ads_get_setting('tax_rate_percent', '0');
    $subtotal = (float) $purchase['amount_paid'] / (1 + $tax_rate / 100);
    $tax = (float) $purchase['amount_paid'] - $subtotal;

    $inv_id = pa_invoice_create([
        'company_id'   => $purchase['company_id'],
        'user_id'      => $purchase['user_id'],
        'purchase_id'  => $purchase_id,
        'line_items'   => [
            [
                'description' => ($pkg ? $pkg['name'] : 'Pause Ads Package') . ' – ' . number_format($purchase['impressions_granted']) . ' pause impressions',
                'quantity'    => 1,
                'unit_price'  => round($subtotal, 2),
                'total'       => round($subtotal, 2),
            ]
        ],
        'subtotal'     => round($subtotal, 2),
        'tax_amount'   => round($tax, 2),
        'total_amount' => (float) $purchase['amount_paid'],
        'currency'     => $purchase['currency'],
    ]);

    // Link invoice to purchase
    if ($inv_id) {
        $pt = pause_ads_table('pause_ads_purchases');
        pause_ads_db_execute("UPDATE `{$pt}` SET `invoice_id`=? WHERE `id`=?", 'ii', [$inv_id, $purchase_id]);
    }

    // Activate campaign
    if ($purchase['campaign_id']) {
        $require_approval = pause_ads_get_setting('require_campaign_approval', '0') === '1';
        $new_status = $require_approval ? PA_STATUS_PENDING_REVIEW : PA_STATUS_ACTIVE;
        pa_campaign_update($purchase['campaign_id'], ['status' => $new_status]);
    }

    return true;
}

/**
 * Simulate payment success (for manual/test mode).
 */
function pause_ads_simulate_payment($purchase_id)
{
    return pause_ads_complete_purchase($purchase_id, 'MANUAL-' . time(), 'manual');
}
