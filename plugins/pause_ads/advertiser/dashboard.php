<?php
/**
 * Pause Ads v2.0 - Advertiser Dashboard
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

// If no company, redirect to company setup
if (!$company_id) {
    header('Location: ' . pause_ads_advertiser_url('company.php') . '?setup=1');
    exit;
}
$cu = pause_ads_require_company_role($company_id, ['owner','admin','analyst']);

$end = date('Y-m-d');
$start = date('Y-m-d', strtotime('-30 days'));
$summary = pause_ads_report_summary($start, $end, $company_id);
$imp_by_day = pause_ads_report_impressions_by_day($start, $end, null, $company_id);
$campaigns = pa_campaign_list($company_id);

// Remaining total impressions
$pur_t = pause_ads_table('pause_ads_purchases');
$total_remaining = (int) pause_ads_db_scalar(
    "SELECT COALESCE(SUM(impressions_remaining),0) FROM `{$pur_t}` WHERE company_id=? AND payment_status='paid'",
    'i', [$company_id]
);

pause_ads_advertiser_header('Dashboard', 'dashboard', $company_id);
?>

<div class="pa-stat-grid">
    <div class="pa-stat-card"><div class="number"><?php echo number_format($summary['total_imp']); ?></div><div class="label">Impressions (30d)</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo number_format($summary['total_clk']); ?></div><div class="label">Clicks (30d)</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo $summary['ctr']; ?>%</div><div class="label">CTR</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo count($campaigns); ?></div><div class="label">Total Campaigns</div></div>
    <div class="pa-stat-card"><div class="number"><?php echo number_format($total_remaining); ?></div><div class="label">Impressions Remaining</div></div>
</div>

<div class="pa-card">
    <h3>Active Campaigns</h3>
    <?php
    $active = array_filter($campaigns, function($c){ return $c['status'] === 'active'; });
    if (empty($active)): ?>
        <div class="pa-alert pa-alert-info">No active campaigns. <a href="<?php echo pause_ads_advertiser_url('campaign_edit.php').'?company_id='.$company_id; ?>">Create one</a>.</div>
    <?php else: ?>
        <table class="pa-table">
            <thead><tr><th>Campaign</th><th>Status</th><th>Impressions</th><th>Remaining</th><th>Flight</th></tr></thead>
            <tbody>
            <?php foreach ($active as $c): ?>
                <tr>
                    <td><a href="<?php echo pause_ads_advertiser_url('campaign_edit.php').'?id='.$c['id'].'&company_id='.$company_id; ?>"><?php echo pause_ads_esc($c['name']); ?></a></td>
                    <td><span class="pa-badge pa-badge-<?php echo $c['status']; ?>"><?php echo $c['status']; ?></span></td>
                    <td><?php echo number_format((int)$c['total_impressions']); ?></td>
                    <td><?php echo number_format((int)$c['remaining_impressions']); ?></td>
                    <td><?php echo $c['flight_start_at'] ? date('M j',strtotime($c['flight_start_at'])) : '—'; ?> – <?php echo $c['flight_end_at'] ? date('M j',strtotime($c['flight_end_at'])) : '—'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div class="pa-card">
    <h3>Impressions (Last 30 Days)</h3>
    <?php if (empty($imp_by_day)): ?>
        <div class="pa-alert pa-alert-info">No impression data yet.</div>
    <?php else: ?>
        <canvas id="impChart" style="width:100%;height:250px;"></canvas>
        <script>
        (function(){
            var c=document.getElementById('impChart'),ctx=c.getContext('2d');
            var labels=<?php echo json_encode(array_column($imp_by_day,'day')); ?>;
            var data=<?php echo json_encode(array_map('intval',array_column($imp_by_day,'impressions'))); ?>;
            c.width=c.parentElement.offsetWidth-40;c.height=230;
            var pad={t:15,r:15,b:40,l:50},cw=c.width-pad.l-pad.r,ch=c.height-pad.t-pad.b;
            var mx=Math.max.apply(null,data)||1,bw=Math.max(4,(cw/data.length)-3);
            ctx.fillStyle='#f8f9fa';ctx.fillRect(0,0,c.width,c.height);
            for(var g=0;g<=4;g++){var gy=pad.t+(ch/4)*g;ctx.strokeStyle='#e9ecef';ctx.beginPath();ctx.moveTo(pad.l,gy);ctx.lineTo(c.width-pad.r,gy);ctx.stroke();ctx.fillStyle='#6c757d';ctx.font='11px sans-serif';ctx.textAlign='right';ctx.fillText(Math.round(mx-(mx/4)*g),pad.l-6,gy+4);}
            var gr=ctx.createLinearGradient(0,pad.t,0,c.height-pad.b);gr.addColorStop(0,'#3498db');gr.addColorStop(1,'#2c3e50');
            for(var i=0;i<data.length;i++){var x=pad.l+(cw/data.length)*i+1,bh=(data[i]/mx)*ch,y=pad.t+ch-bh;ctx.fillStyle=gr;ctx.fillRect(x,y,bw,bh);}
        })();
        </script>
    <?php endif; ?>
</div>

<?php pause_ads_advertiser_footer(); ?>
