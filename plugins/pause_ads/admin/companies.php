<?php
/**
 * Pause Ads v2.0 - Admin: Companies Management
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

// Toggle suspend
if (isset($_GET['action']) && isset($_GET['cid'])) {
    $cid = (int)$_GET['cid'];
    if ($_GET['action'] === 'suspend') { pa_company_update($cid, ['status'=>'suspended']); $msg='Company suspended.'; }
    if ($_GET['action'] === 'activate') { pa_company_update($cid, ['status'=>'active']); $msg='Company activated.'; }
}

$companies = pa_company_list();

// Augment with stats
$imp_t = pause_ads_table('pause_ads_impressions');
$camp_t = pause_ads_table('pause_ads_campaigns');
$pur_t = pause_ads_table('pause_ads_purchases');

pause_ads_admin_header('Companies', 'companies');
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>

<div class="pa-card">
    <h3>Advertiser Companies (<?php echo count($companies); ?>)</h3>
    <table class="pa-table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Campaigns</th><th>Total Spent</th><th>Created</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($companies as $co):
            $camp_count = (int) pause_ads_db_scalar("SELECT COUNT(*) FROM `{$camp_t}` WHERE company_id=?",'i',[$co['id']]);
            $spent = (float) pause_ads_db_scalar("SELECT COALESCE(SUM(amount_paid),0) FROM `{$pur_t}` WHERE company_id=? AND payment_status='paid'",'i',[$co['id']]);
        ?>
            <tr>
                <td>#<?php echo (int)$co['id']; ?></td>
                <td><strong><?php echo pause_ads_esc($co['name']); ?></strong></td>
                <td><?php echo pause_ads_esc($co['billing_email']); ?></td>
                <td><span class="pa-badge pa-badge-<?php echo $co['status']; ?>"><?php echo $co['status']; ?></span></td>
                <td><?php echo $camp_count; ?></td>
                <td>$<?php echo number_format($spent,2); ?></td>
                <td><?php echo date('M j, Y', strtotime($co['created_at'])); ?></td>
                <td>
                    <?php if ($co['status']==='active'): ?>
                        <a href="?action=suspend&cid=<?php echo $co['id']; ?>" class="pa-btn pa-btn-sm pa-btn-danger" onclick="return confirm('Suspend this company?');">Suspend</a>
                    <?php else: ?>
                        <a href="?action=activate&cid=<?php echo $co['id']; ?>" class="pa-btn pa-btn-sm pa-btn-success">Activate</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php pause_ads_admin_footer(); ?>
