<?php
/**
 * Pause Ads v2.0 - Admin: Campaign Management
 * Approve/reject, force pause, view all campaigns.
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

// Actions
if (isset($_GET['action']) && isset($_GET['cid'])) {
    $cid = (int)$_GET['cid'];
    switch ($_GET['action']) {
        case 'approve': pa_campaign_update($cid, ['status'=>PA_STATUS_ACTIVE]); $msg='Campaign approved.'; break;
        case 'reject':  pa_campaign_update($cid, ['status'=>PA_STATUS_REJECTED]); $msg='Campaign rejected.'; break;
        case 'pause':   pa_campaign_update($cid, ['status'=>PA_STATUS_PAUSED]); $msg='Campaign paused.'; break;
        case 'resume':  pa_campaign_update($cid, ['status'=>PA_STATUS_ACTIVE]); $msg='Campaign resumed.'; break;
        case 'end':     pa_campaign_update($cid, ['status'=>PA_STATUS_ENDED]); $msg='Campaign ended.'; break;
    }
}

$filter = $_GET['status'] ?? '';
$all_camps = pa_campaign_list(null, $filter);

// Get company names
$co_t = pause_ads_table('pause_ads_companies');
$co_map = [];
foreach (pause_ads_db_select("SELECT id,name FROM `{$co_t}`") as $c) $co_map[$c['id']] = $c['name'];

pause_ads_admin_header('Campaigns', 'campaigns');
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>

<div class="pa-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:8px;">
        <h3 style="margin:0;">All Campaigns (<?php echo count($all_camps); ?>)</h3>
        <div>
            <?php foreach ([''=>'All','active'=>'Active','pending_review'=>'Pending Review','paused'=>'Paused','draft'=>'Draft','ended'=>'Ended'] as $fk=>$fl): ?>
                <a href="<?php echo pause_ads_admin_url('campaigns.php').($fk ? '&status='.$fk : ''); ?>" class="pa-btn pa-btn-sm" style="<?php echo $filter===$fk?'background:#667eea;color:#fff;':'background:#e9ecef;color:#495057;'; ?>"><?php echo $fl; ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <table class="pa-table">
        <thead><tr><th>ID</th><th>Campaign</th><th>Company</th><th>Status</th><th>Impressions</th><th>Remaining</th><th>Flight</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($all_camps as $c): ?>
            <tr>
                <td>#<?php echo (int)$c['id']; ?></td>
                <td><strong><?php echo pause_ads_esc($c['name']); ?></strong></td>
                <td><?php echo pause_ads_esc($co_map[$c['company_id']] ?? '—'); ?></td>
                <td><span class="pa-badge pa-badge-<?php echo $c['status']; ?>"><?php echo str_replace('_',' ',$c['status']); ?></span></td>
                <td><?php echo number_format((int)$c['total_impressions']); ?></td>
                <td><?php echo number_format((int)$c['remaining_impressions']); ?></td>
                <td><?php echo $c['flight_start_at'] ? date('M j',strtotime($c['flight_start_at'])) : '—'; ?> – <?php echo $c['flight_end_at'] ? date('M j',strtotime($c['flight_end_at'])) : '—'; ?></td>
                <td style="white-space:nowrap;">
                    <?php if ($c['status']===PA_STATUS_PENDING_REVIEW): ?>
                        <a href="?action=approve&cid=<?php echo $c['id']; ?>" class="pa-btn pa-btn-sm pa-btn-success">Approve</a>
                        <a href="?action=reject&cid=<?php echo $c['id']; ?>" class="pa-btn pa-btn-sm pa-btn-danger">Reject</a>
                    <?php endif; ?>
                    <?php if ($c['status']===PA_STATUS_ACTIVE): ?>
                        <a href="?action=pause&cid=<?php echo $c['id']; ?>" class="pa-btn pa-btn-sm pa-btn-warning">Pause</a>
                        <a href="?action=end&cid=<?php echo $c['id']; ?>" class="pa-btn pa-btn-sm pa-btn-danger">End</a>
                    <?php endif; ?>
                    <?php if ($c['status']===PA_STATUS_PAUSED): ?>
                        <a href="?action=resume&cid=<?php echo $c['id']; ?>" class="pa-btn pa-btn-sm pa-btn-success">Resume</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php pause_ads_admin_footer(); ?>
