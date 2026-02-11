<?php
/**
 * Pause Ads v2.0 - Advertiser: Billing & Packages
 * Browse packages, purchase for a campaign, view history.
 * @package PauseAds
 */
$cb_root = realpath(__DIR__ . '/../../../');
foreach (['/includes/config.inc.php','/include/config.inc.php','/cb_config.php'] as $f) {
    if (file_exists($cb_root.$f)) { define('STARTER',true); require_once $cb_root.$f; break; }
}
if (!defined('STARTER')) define('STARTER',true);
require_once __DIR__ . '/../main.php';

$user_id = pause_ads_require_login();
$company_id = pause_ads_get_active_company_id();
if (!$company_id) { header('Location: '.pause_ads_advertiser_url('company.php')); exit; }
$cu = pause_ads_require_company_role($company_id, ['owner','admin']);

$msg=''; $err='';
if (isset($_GET['success'])) $msg = 'Payment successful! Your campaign is being activated.';
if (isset($_GET['cancelled'])) $err = 'Payment was cancelled. You can try again.';

$campaign_id = pause_ads_validate_int($_GET['campaign_id'] ?? 0);

// Handle purchase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['purchase_package'])) {
    if (!pause_ads_check_nonce('purchase')) { $err='Invalid token.'; }
    else {
        $pkg_id = (int)$_POST['package_id'];
        $camp_id = (int)$_POST['campaign_id'];

        // Verify campaign belongs to company
        $camp = pa_campaign_get($camp_id);
        if (!$camp || (int)$camp['company_id'] !== $company_id) { $err='Invalid campaign.'; }
        else {
            $result = pause_ads_initiate_purchase($company_id, $user_id, $pkg_id, $camp_id);
            if (isset($result['error'])) { $err = $result['error']; }
            elseif ($result['checkout_url']) {
                header('Location: ' . $result['checkout_url']);
                exit;
            } else {
                // Manual payment: auto-complete for now (admin can configure)
                pause_ads_simulate_payment($result['purchase_id']);
                $msg = 'Purchase completed! Campaign activated.';
            }
        }
    }
}

$packages = pa_package_list('active');
$purchases = pa_purchase_list($company_id);

// Get campaigns in draft/pending for selection
$camps = pa_campaign_list($company_id);
$eligible_camps = array_filter($camps, function($c){ return in_array($c['status'],['draft','pending_payment','active','paused']); });

$tax_rate = (float)pause_ads_get_setting('tax_rate_percent','0');

pause_ads_advertiser_header('Billing & Packages', 'billing', $company_id);
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($err); ?></div><?php endif; ?>

<!-- Available Packages -->
<div class="pa-card">
    <h3>Available Packages</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:15px;">
    <?php foreach ($packages as $pkg): ?>
        <div style="border:2px solid #dee2e6;border-radius:10px;padding:20px;text-align:center;background:#fff;">
            <h4 style="margin:0 0 5px;font-size:20px;"><?php echo pause_ads_esc($pkg['name']); ?></h4>
            <div style="font-size:36px;font-weight:700;color:#3498db;margin:10px 0;">
                $<?php echo number_format((float)$pkg['price_amount'],2); ?>
            </div>
            <div style="font-size:14px;color:#6c757d;margin-bottom:10px;">
                <?php echo number_format((int)$pkg['included_impressions']); ?> pause impressions
            </div>
            <?php if ($pkg['max_flight_days']): ?>
                <div style="font-size:13px;color:#999;">Max <?php echo $pkg['max_flight_days']; ?> day flight</div>
            <?php endif; ?>
            <?php if ($tax_rate > 0): ?>
                <div style="font-size:12px;color:#999;margin-top:5px;">+ <?php echo $tax_rate; ?>% tax</div>
            <?php endif; ?>

            <?php if (!empty($eligible_camps)): ?>
                <form method="post" style="margin-top:15px;">
                    <?php echo pause_ads_nonce_field('purchase'); ?>
                    <input type="hidden" name="package_id" value="<?php echo $pkg['id']; ?>">
                    <select name="campaign_id" class="pa-input" style="margin-bottom:8px;" required>
                        <option value="">Select Campaign...</option>
                        <?php foreach ($eligible_camps as $ec): ?>
                            <option value="<?php echo $ec['id']; ?>" <?php echo ($campaign_id==(int)$ec['id'])?'selected':''; ?>>
                                <?php echo pause_ads_esc($ec['name']); ?> (<?php echo $ec['status']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" name="purchase_package" class="pa-btn pa-btn-primary" style="width:100%;">Purchase</button>
                </form>
            <?php else: ?>
                <p style="font-size:13px;color:#999;margin-top:10px;">Create a campaign first.</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- Purchase History -->
<div class="pa-card">
    <h3>Purchase History</h3>
    <?php if (empty($purchases)): ?>
        <div class="pa-alert pa-alert-info">No purchases yet.</div>
    <?php else: ?>
        <table class="pa-table">
            <thead><tr><th>Date</th><th>Package</th><th>Impressions</th><th>Remaining</th><th>Amount</th><th>Status</th><th>Invoice</th></tr></thead>
            <tbody>
            <?php foreach ($purchases as $p): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($p['created_at'])); ?></td>
                    <td><?php echo pause_ads_esc($p['package_name'] ?? 'Custom'); ?></td>
                    <td><?php echo number_format((int)$p['impressions_granted']); ?></td>
                    <td><?php echo number_format((int)$p['impressions_remaining']); ?></td>
                    <td>$<?php echo number_format((float)$p['amount_paid'],2); ?> <?php echo pause_ads_esc($p['currency']); ?></td>
                    <td><span class="pa-badge pa-badge-<?php echo $p['payment_status']; ?>"><?php echo $p['payment_status']; ?></span></td>
                    <td>
                        <?php if ($p['invoice_id']): ?>
                            <a href="<?php echo pause_ads_advertiser_url('invoices.php').'?view='.$p['invoice_id'].'&company_id='.$company_id; ?>">View</a>
                        <?php else: echo '—'; endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php pause_ads_advertiser_footer(); ?>
