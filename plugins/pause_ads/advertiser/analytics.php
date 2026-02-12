<?php
/**
 * Pause Ads v2.0 - Advertiser: Analytics Dashboard
 * Campaign performance, geo breakdown, top videos.
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

$campaign_id = pause_ads_validate_int($_GET['campaign_id'] ?? 0);
$end = isset($_GET['end_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start = isset($_GET['start_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

$summary = pause_ads_report_summary($start, $end, $company_id);
$imp_by_day = pause_ads_report_impressions_by_day($start, $end, $campaign_id ?: null, $company_id);
$clk_by_day = pause_ads_report_clicks_by_day($start, $end, $campaign_id ?: null, $company_id);
$top_videos = pause_ads_report_top_videos($start, $end, 10, $company_id);
$geo = pause_ads_report_geo_breakdown($start, $end, $company_id);

// Campaign-level stats
$camp_name = 'All Campaigns';
if ($campaign_id) {
    $camp = pa_campaign_get($campaign_id);
    if ($camp && (int)$camp['company_id'] === $company_id) $camp_name = $camp['name'];
}

$campaigns = pa_campaign_list($company_id);

pause_ads_advertiser_header('Analytics' . ($campaign_id ? ': ' . pause_ads_esc($camp_name) : ''), 'analytics', $company_id);
?>

<!-- Filters -->
<div class="pa-card" style="margin-bottom:15px;">
    <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <input type="hidden" name="company_id" value="<?php echo $company_id; ?>">
        <label style="font-weight:600;font-size:14px;">Period:</label>
        <input type="date" name="start_date" class="pa-input" style="width:auto;" value="<?php echo pause_ads_esc($start); ?>">
        <span>to</span>
        <input type="date" name="end_date" class="pa-input" style="width:auto;" value="<?php echo pause_ads_esc($end); ?>">
        <label style="font-weight:600;font-size:14px;margin-left:10px;">Campaign:</label>
        <select name="campaign_id" class="pa-input" style="width:auto;">
            <option value="">All Campaigns</option>
            <?php foreach ($campaigns as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo $campaign_id==(int)$c['id']?'selected':''; ?>><?php echo pause_ads_esc($c['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="pa-btn pa-btn-primary pa-btn-sm">Apply</button>
    </form>
</div>

<!-- Stats -->
<div class="pa-stat-grid">
    <div class="pa-stat-card"><div class="number"><?php echo number_format($summary['total_imp']); ?></div><div class="label">Impressions</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo number_format($summary['total_clk']); ?></div><div class="label">Clicks</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo $summary['ctr']; ?>%</div><div class="label">CTR</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo number_format($summary['unique_sessions']); ?></div><div class="label">Unique Viewers</div></div>
</div>

<div class="pa-row">
    <!-- Impressions chart -->
    <div class="pa-col" style="flex:2;">
        <div class="pa-card">
            <h3>Impressions Over Time</h3>
            <?php if (empty($imp_by_day)): ?>
                <div class="pa-alert pa-alert-info">No data for this period.</div>
            <?php else: ?>
                <canvas id="impChart" style="width:100%;height:250px;"></canvas>
                <script>
                (function(){
                    var c=document.getElementById('impChart'),ctx=c.getContext('2d');
                    var data=<?php echo json_encode(array_map('intval',array_column($imp_by_day,'impressions'))); ?>;
                    c.width=c.parentElement.offsetWidth-40;c.height=230;
                    var p={t:15,r:15,b:30,l:50},cw=c.width-p.l-p.r,ch=c.height-p.t-p.b,mx=Math.max.apply(null,data)||1;
                    ctx.fillStyle='#f8f9fa';ctx.fillRect(0,0,c.width,c.height);
                    var gr=ctx.createLinearGradient(0,p.t,0,c.height-p.b);gr.addColorStop(0,'#3498db');gr.addColorStop(1,'#2c3e50');
                    var bw=Math.max(3,(cw/data.length)-2);
                    for(var i=0;i<data.length;i++){var x=p.l+(cw/data.length)*i+1,bh=(data[i]/mx)*ch;ctx.fillStyle=gr;ctx.fillRect(x,p.t+ch-bh,bw,bh);}
                })();
                </script>
            <?php endif; ?>
        </div>
    </div>

    <!-- Geo breakdown -->
    <div class="pa-col" style="flex:1;">
        <div class="pa-card">
            <h3>Geography</h3>
            <?php if (empty($geo)): ?>
                <div class="pa-alert pa-alert-info">No geo data.</div>
            <?php else: ?>
                <table class="pa-table">
                    <thead><tr><th>Country</th><th>Impressions</th></tr></thead>
                    <tbody>
                    <?php foreach ($geo as $g): ?>
                        <tr><td><?php echo pause_ads_esc($g['country_code']?:'Unknown'); ?></td><td><?php echo number_format((int)$g['impressions']); ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Top videos -->
<div class="pa-card">
    <h3>Top Videos</h3>
    <?php if (empty($top_videos)): ?>
        <div class="pa-alert pa-alert-info">No data.</div>
    <?php else: ?>
        <table class="pa-table">
            <thead><tr><th>Video ID</th><th>Impressions</th></tr></thead>
            <tbody>
            <?php foreach ($top_videos as $v): ?>
                <tr><td>Video #<?php echo (int)$v['video_id']; ?></td><td><?php echo number_format((int)$v['impressions']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php pause_ads_advertiser_footer(); ?>
