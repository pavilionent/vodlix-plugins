<?php
/**
 * Pause Ads v2.0 - Advertiser: Campaigns List
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
if (!$company_id) { header('Location: '.pause_ads_advertiser_url('company.php').'?setup=1'); exit; }
$cu = pause_ads_require_company_role($company_id, ['owner','admin','analyst']);

// Handle pause/resume
if (isset($_GET['action']) && in_array($cu['role'],['owner','admin'])) {
    $cid = (int)($_GET['cid'] ?? 0);
    if ($_GET['action']==='pause' && $cid) { pa_campaign_update($cid,['status'=>PA_STATUS_PAUSED]); }
    if ($_GET['action']==='resume' && $cid) { pa_campaign_update($cid,['status'=>PA_STATUS_ACTIVE]); }
    header('Location: '.pause_ads_advertiser_url('campaigns.php').'?company_id='.$company_id);
    exit;
}

$campaigns = pa_campaign_list($company_id);
pause_ads_advertiser_header('Campaigns', 'campaigns', $company_id);
?>

<div class="pa-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
        <h3 style="margin:0;">Your Campaigns (<?php echo count($campaigns); ?>)</h3>
        <?php if (in_array($cu['role'],['owner','admin'])): ?>
            <a href="<?php echo pause_ads_advertiser_url('campaign_edit.php').'?company_id='.$company_id; ?>" class="pa-btn pa-btn-primary">+ New Campaign</a>
        <?php endif; ?>
    </div>

    <?php if (empty($campaigns)): ?>
        <div class="pa-alert pa-alert-info">No campaigns yet. Create your first campaign to get started.</div>
    <?php else: ?>
        <table class="pa-table">
            <thead><tr><th>Name</th><th>Status</th><th>Impressions</th><th>Remaining</th><th>Flight</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($campaigns as $c):
                $imp = (int)$c['total_impressions'];
                $rem = (int)$c['remaining_impressions'];
                $pct = ($c['granted_impressions'] > 0) ? round($imp / $c['granted_impressions'] * 100) : 0;
            ?>
                <tr>
                    <td><strong><?php echo pause_ads_esc($c['name']); ?></strong></td>
                    <td><span class="pa-badge pa-badge-<?php echo $c['status']; ?>"><?php echo str_replace('_',' ',$c['status']); ?></span></td>
                    <td><?php echo number_format($imp); ?> <small style="color:#6c757d;">(<?php echo $pct; ?>%)</small></td>
                    <td><?php echo number_format($rem); ?></td>
                    <td>
                        <?php echo $c['flight_start_at'] ? date('M j, Y', strtotime($c['flight_start_at'])) : '—'; ?><br>
                        <small><?php echo $c['flight_end_at'] ? date('M j, Y', strtotime($c['flight_end_at'])) : 'No end'; ?></small>
                    </td>
                    <td>
                        <a href="<?php echo pause_ads_advertiser_url('campaign_edit.php').'?id='.$c['id'].'&company_id='.$company_id; ?>" class="pa-btn pa-btn-sm pa-btn-primary">Edit</a>
                        <?php if ($c['status']==='active' && in_array($cu['role'],['owner','admin'])): ?>
                            <a href="?action=pause&cid=<?php echo $c['id']; ?>&company_id=<?php echo $company_id; ?>" class="pa-btn pa-btn-sm pa-btn-warning">Pause</a>
                        <?php elseif ($c['status']==='paused' && in_array($cu['role'],['owner','admin'])): ?>
                            <a href="?action=resume&cid=<?php echo $c['id']; ?>&company_id=<?php echo $company_id; ?>" class="pa-btn pa-btn-sm pa-btn-success">Resume</a>
                        <?php endif; ?>
                        <a href="<?php echo pause_ads_advertiser_url('analytics.php').'?campaign_id='.$c['id'].'&company_id='.$company_id; ?>" class="pa-btn pa-btn-sm" style="background:#e9ecef;color:#495057;">Stats</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php pause_ads_advertiser_footer(); ?>
