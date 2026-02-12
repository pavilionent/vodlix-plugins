<?php
/**
 * Pause Ads v2.0 - Admin: Global Dashboard & Analytics
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

$end = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

$summary = pause_ads_report_summary($start, $end);
$imp_by_day = pause_ads_report_impressions_by_day($start, $end);
$top_videos = pause_ads_report_top_videos($start, $end, 10);
$geo = pause_ads_report_geo_breakdown($start, $end);

// Revenue
$pur_t = pause_ads_table('pause_ads_purchases');
$revenue = (float) pause_ads_db_scalar(
    "SELECT COALESCE(SUM(amount_paid),0) FROM `{$pur_t}` WHERE payment_status='paid' AND created_at>=? AND created_at<DATE_ADD(?,INTERVAL 1 DAY)",
    'ss', [$start, $end]
);

$companies_t = pause_ads_table('pause_ads_companies');
$total_companies = (int) pause_ads_db_scalar("SELECT COUNT(*) FROM `{$companies_t}`");

pause_ads_admin_header('Global Dashboard', 'dashboard');
?>

<div class="pa-card" style="margin-bottom:15px;">
    <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="page" value="pause_ads/admin/dashboard.php">
        <label style="font-weight:600;font-size:14px;">Period:</label>
        <input type="date" name="start_date" class="pa-input" style="width:auto;" value="<?php echo pause_ads_esc($start); ?>">
        <span>to</span>
        <input type="date" name="end_date" class="pa-input" style="width:auto;" value="<?php echo pause_ads_esc($end); ?>">
        <button type="submit" class="pa-btn pa-btn-primary pa-btn-sm">Apply</button>
    </form>
</div>

<div class="pa-stat-grid">
    <div class="pa-stat-card"><div class="number"><?php echo number_format($summary['total_imp']); ?></div><div class="label">Impressions</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo number_format($summary['total_clk']); ?></div><div class="label">Clicks</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo $summary['ctr']; ?>%</div><div class="label">CTR</div></div>
    <div class="pa-stat-card"><div class="number">$<?php echo number_format($revenue,2); ?></div><div class="label">Revenue</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo $summary['active_camps']; ?></div><div class="label">Active Campaigns</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo $total_companies; ?></div><div class="label">Companies</div></div>
</div>

<div class="pa-row">
    <div class="pa-col" style="flex:2;">
        <div class="pa-card">
            <h3>Impressions Trend</h3>
            <?php if (empty($imp_by_day)): ?>
                <div class="pa-alert pa-alert-info">No data.</div>
            <?php else: ?>
                <canvas id="impChart" style="width:100%;height:250px;"></canvas>
                <script>
                (function(){
                    var c=document.getElementById('impChart'),ctx=c.getContext('2d');
                    var data=<?php echo json_encode(array_map('intval',array_column($imp_by_day,'impressions'))); ?>;
                    c.width=c.parentElement.offsetWidth-40;c.height=230;
                    var p={t:15,r:15,b:30,l:50},cw=c.width-p.l-p.r,ch=c.height-p.t-p.b,mx=Math.max.apply(null,data)||1;
                    ctx.fillStyle='#f8f9fa';ctx.fillRect(0,0,c.width,c.height);
                    var gr=ctx.createLinearGradient(0,p.t,0,c.height-p.b);gr.addColorStop(0,'#667eea');gr.addColorStop(1,'#764ba2');
                    var bw=Math.max(3,(cw/data.length)-2);
                    for(var i=0;i<data.length;i++){var x=p.l+(cw/data.length)*i+1,bh=(data[i]/mx)*ch;ctx.fillStyle=gr;ctx.fillRect(x,p.t+ch-bh,bw,bh);}
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>
    <div class="pa-col" style="flex:1;">
        <div class="pa-card">
            <h3>Top Countries</h3>
            <?php if (empty($geo)): ?><p style="color:#999;">No geo data.</p>
            <?php else: ?>
                <table class="pa-table"><thead><tr><th>Country</th><th>Imp.</th></tr></thead><tbody>
                <?php foreach (array_slice($geo,0,10) as $g): ?>
                    <tr><td><?php echo pause_ads_esc($g['country_code']?:'—'); ?></td><td><?php echo number_format((int)$g['impressions']); ?></td></tr>
                <?php endforeach; ?></tbody></table>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="pa-card">
    <h3>Top Videos</h3>
    <?php if (empty($top_videos)): ?><p style="color:#999;">No data.</p>
    <?php else: ?>
        <table class="pa-table"><thead><tr><th>Video ID</th><th>Impressions</th></tr></thead><tbody>
        <?php foreach ($top_videos as $v): ?>
            <tr><td>Video #<?php echo (int)$v['video_id']; ?></td><td><?php echo number_format((int)$v['impressions']); ?></td></tr>
        <?php endforeach; ?></tbody></table>
    <?php endif; ?>
</div>

<?php pause_ads_admin_footer(); ?>
