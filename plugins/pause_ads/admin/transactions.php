<?php
/**
 * Pause Ads v2.0 - Admin: Transactions & Invoices
 * @package PauseAds
 */
if (!defined('STARTER')) {
    $cb_root = realpath(__DIR__ . '/../../../../');
    foreach (['/includes/config.inc.php','/include/config.inc.php','/cb_config.php'] as $f) {
        if (file_exists($cb_root.$f)) { define('STARTER',true); require_once $cb_root.$f; break; }
    }
    if (!defined('STARTER')) define('STARTER',true);
}
require_once __DIR__ . '/../main.php';
pause_ads_require_admin();

$msg='';

// Manual approve payment
if (isset($_GET['approve_purchase'])) {
    $pid = (int)$_GET['approve_purchase'];
    pause_ads_complete_purchase($pid, 'ADMIN-APPROVED-'.time(), 'admin');
    $msg = 'Purchase #'.$pid.' approved and campaign activated.';
}

$purchases = pa_purchase_list();
$invoices = pa_invoice_list();

// Company name map
$co_t = pause_ads_table('pause_ads_companies');
$co_map = [];
foreach (pause_ads_db_select("SELECT id,name FROM `{$co_t}`") as $c) $co_map[$c['id']] = $c['name'];

pause_ads_admin_header('Transactions & Invoices', 'transactions');
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>

<div class="pa-card">
    <h3>Purchases (<?php echo count($purchases); ?>)</h3>
    <table class="pa-table">
        <thead><tr><th>ID</th><th>Date</th><th>Company</th><th>Package</th><th>Amount</th><th>Impressions</th><th>Remaining</th><th>Status</th><th>Ref</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($purchases as $p): ?>
            <tr>
                <td>#<?php echo (int)$p['id']; ?></td>
                <td><?php echo date('M j, Y H:i', strtotime($p['created_at'])); ?></td>
                <td><?php echo pause_ads_esc($co_map[$p['company_id']] ?? '—'); ?></td>
                <td><?php echo pause_ads_esc($p['package_name'] ?? 'Custom'); ?></td>
                <td>$<?php echo number_format((float)$p['amount_paid'],2); ?></td>
                <td><?php echo number_format((int)$p['impressions_granted']); ?></td>
                <td><?php echo number_format((int)$p['impressions_remaining']); ?></td>
                <td><span class="pa-badge pa-badge-<?php echo $p['payment_status']; ?>"><?php echo $p['payment_status']; ?></span></td>
                <td style="max-width:120px;overflow:hidden;text-overflow:ellipsis;"><?php echo pause_ads_esc($p['payment_ref']?:'-'); ?></td>
                <td>
                    <?php if ($p['payment_status'] === 'pending'): ?>
                        <a href="?approve_purchase=<?php echo $p['id']; ?>" class="pa-btn pa-btn-sm pa-btn-success" onclick="return confirm('Approve this payment and activate the campaign?');">Approve</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="pa-card">
    <h3>Invoices (<?php echo count($invoices); ?>)</h3>
    <table class="pa-table">
        <thead><tr><th>Invoice #</th><th>Date</th><th>Company</th><th>Amount</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $inv): ?>
            <tr>
                <td><strong><?php echo pause_ads_esc($inv['invoice_number']); ?></strong></td>
                <td><?php echo date('M j, Y', strtotime($inv['issued_at'])); ?></td>
                <td><?php echo pause_ads_esc($co_map[$inv['company_id']] ?? '—'); ?></td>
                <td>$<?php echo number_format((float)$inv['total_amount'],2); ?> <?php echo pause_ads_esc($inv['currency']); ?></td>
                <td><span class="pa-badge pa-badge-<?php echo $inv['status']==='issued'?'active':'archived'; ?>"><?php echo $inv['status']; ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php pause_ads_admin_footer(); ?>
