<?php
/**
 * Pause Ads v2.0 - Advertiser: Invoices
 * List invoices and view/print individual invoice.
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
pause_ads_require_company_role($company_id, ['owner','admin','analyst']);

$view_id = pause_ads_validate_int($_GET['view'] ?? 0);

if ($view_id) {
    $inv = pa_invoice_get($view_id);
    if (!$inv || (int)$inv['company_id'] !== $company_id) { die('Invoice not found.'); }
    $company = pa_company_get($company_id);
    $platform = pause_ads_get_setting('platform_name', 'Pause Ads');

    // Render printable invoice
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"><title>Invoice <?php echo pause_ads_esc($inv['invoice_number']); ?></title>
    <style>
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:800px;margin:40px auto;padding:0 20px;color:#333}
    .inv-header{display:flex;justify-content:space-between;margin-bottom:30px}
    .inv-header h1{margin:0;font-size:28px;color:#667eea}
    .inv-meta{text-align:right;font-size:14px;color:#666}
    .inv-parties{display:flex;justify-content:space-between;margin-bottom:30px}
    .inv-parties div{font-size:14px}
    .inv-parties strong{display:block;margin-bottom:5px}
    table{width:100%;border-collapse:collapse;margin-bottom:20px}
    th{background:#f8f9fa;padding:10px;text-align:left;font-size:13px;border-bottom:2px solid #dee2e6}
    td{padding:10px;border-bottom:1px solid #eee;font-size:14px}
    .inv-totals{text-align:right;font-size:14px}
    .inv-totals .total{font-size:20px;font-weight:700;color:#667eea}
    .no-print{margin-bottom:20px}
    @media print{.no-print{display:none}}
    </style></head><body>
    <div class="no-print">
        <a href="<?php echo pause_ads_advertiser_url('invoices.php').'?company_id='.$company_id; ?>">&laquo; Back to Invoices</a>
        &nbsp; <button onclick="window.print();" style="padding:8px 16px;border-radius:6px;background:#667eea;color:#fff;border:none;cursor:pointer;">Print / Save PDF</button>
    </div>
    <div class="inv-header">
        <div><h1>INVOICE</h1><p style="color:#666;"><?php echo pause_ads_esc($platform); ?></p></div>
        <div class="inv-meta">
            <strong><?php echo pause_ads_esc($inv['invoice_number']); ?></strong>
            <div>Issued: <?php echo date('F j, Y', strtotime($inv['issued_at'])); ?></div>
            <div>Status: <?php echo pause_ads_esc(ucfirst($inv['status'])); ?></div>
        </div>
    </div>
    <div class="inv-parties">
        <div><strong>Bill To:</strong><?php echo pause_ads_esc($company['name']); ?><br><?php echo pause_ads_esc($company['billing_email']); ?></div>
        <div style="text-align:right;"><strong>From:</strong><?php echo pause_ads_esc($platform); ?></div>
    </div>
    <table>
        <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th style="text-align:right;">Total</th></tr></thead>
        <tbody>
        <?php foreach ($inv['line_items'] as $li): ?>
            <tr>
                <td><?php echo pause_ads_esc($li['description']); ?></td>
                <td><?php echo (int)$li['quantity']; ?></td>
                <td>$<?php echo number_format((float)$li['unit_price'],2); ?></td>
                <td style="text-align:right;">$<?php echo number_format((float)$li['total'],2); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <div class="inv-totals">
        <div>Subtotal: $<?php echo number_format((float)$inv['subtotal'],2); ?></div>
        <?php if ((float)$inv['tax_amount'] > 0): ?>
            <div>Tax: $<?php echo number_format((float)$inv['tax_amount'],2); ?></div>
        <?php endif; ?>
        <div class="total">Total: $<?php echo number_format((float)$inv['total_amount'],2); ?> <?php echo pause_ads_esc($inv['currency']); ?></div>
    </div>
    <hr style="margin-top:40px;border:none;border-top:1px solid #eee;">
    <p style="font-size:12px;color:#999;text-align:center;">Thank you for your business.</p>
    </body></html>
    <?php
    exit;
}

// List invoices
$invoices = pa_invoice_list($company_id);
pause_ads_advertiser_header('Invoices', 'invoices', $company_id);
?>

<div class="pa-card">
    <h3>Invoices</h3>
    <?php if (empty($invoices)): ?>
        <div class="pa-alert pa-alert-info">No invoices yet.</div>
    <?php else: ?>
        <table class="pa-table">
            <thead><tr><th>Invoice #</th><th>Date</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong><?php echo pause_ads_esc($inv['invoice_number']); ?></strong></td>
                    <td><?php echo date('M j, Y', strtotime($inv['issued_at'])); ?></td>
                    <td>$<?php echo number_format((float)$inv['total_amount'],2); ?> <?php echo pause_ads_esc($inv['currency']); ?></td>
                    <td><span class="pa-badge pa-badge-<?php echo $inv['status']==='issued'?'active':'archived'; ?>"><?php echo $inv['status']; ?></span></td>
                    <td><a href="?view=<?php echo $inv['id']; ?>&company_id=<?php echo $company_id; ?>" class="pa-btn pa-btn-sm pa-btn-primary">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php pause_ads_advertiser_footer(); ?>
