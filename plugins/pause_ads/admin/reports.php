<?php
/**
 * Pause Ads Plugin - Admin: Reports Dashboard
 *
 * Displays impressions, clicks, CTR, top videos, and
 * daily breakdown charts.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    $cb_root = realpath(dirname(__FILE__) . '/../../../../');
    $bootstrap_files = [
        $cb_root . '/includes/config.inc.php',
        $cb_root . '/include/config.inc.php',
        $cb_root . '/cb_config.php',
    ];
    foreach ($bootstrap_files as $bf) {
        if (file_exists($bf)) {
            define('STARTER', true);
            require_once $bf;
            break;
        }
    }
    if (!defined('STARTER')) {
        define('STARTER', true);
    }
}

require_once dirname(__FILE__) . '/../main.php';
pause_ads_require_admin();

// Date range
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));

// Validate dates
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-d', strtotime('-30 days'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-d');
}

// Fetch report data
$summary = pause_ads_report_summary($start_date, $end_date);
$impressions_by_day = pause_ads_report_impressions_by_day($start_date, $end_date);
$impressions_by_ad = pause_ads_report_impressions_by_ad($start_date, $end_date);
$clicks_by_ad = pause_ads_report_clicks_by_ad($start_date, $end_date);
$top_videos = pause_ads_report_top_videos($start_date, $end_date, 10);

// Build clicks map for merging
$clicks_map = [];
foreach ($clicks_by_ad as $c) {
    $clicks_map[$c['ad_id']] = (int) $c['clicks'];
}

// Prepare chart data
$chart_labels = [];
$chart_data = [];
foreach ($impressions_by_day as $day) {
    $chart_labels[] = date('M j', strtotime($day['day']));
    $chart_data[] = (int) $day['impressions'];
}

pause_ads_admin_header('Dashboard & Reports', 'reports');
?>

<!-- Date Range Filter -->
<div class="pa-card" style="margin-bottom: 15px;">
    <form method="get" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
        <input type="hidden" name="page" value="pause_ads/admin/reports.php">
        <label style="font-weight: 600; font-size: 14px;">Date Range:</label>
        <input type="date" name="start_date" class="pa-input" style="width: auto;" value="<?php echo pause_ads_esc($start_date); ?>">
        <span>to</span>
        <input type="date" name="end_date" class="pa-input" style="width: auto;" value="<?php echo pause_ads_esc($end_date); ?>">
        <button type="submit" class="pa-btn pa-btn-primary pa-btn-sm">Apply</button>
        <a href="<?php echo pause_ads_admin_url('reports.php'); ?>" class="pa-btn pa-btn-sm" style="background:#e9ecef;color:#495057;">Reset</a>
    </form>
</div>

<!-- Summary Stats -->
<div class="pa-stat-grid">
    <div class="pa-stat-card">
        <div class="number"><?php echo number_format($summary['total_impressions']); ?></div>
        <div class="label">Total Impressions</div>
    </div>
    <div class="pa-stat-card">
        <div class="number"><?php echo number_format($summary['total_clicks']); ?></div>
        <div class="label">Total Clicks</div>
    </div>
    <div class="pa-stat-card">
        <div class="number"><?php echo $summary['ctr']; ?>%</div>
        <div class="label">Click-Through Rate</div>
    </div>
    <div class="pa-stat-card">
        <div class="number"><?php echo number_format($summary['active_ads']); ?></div>
        <div class="label">Active Ads</div>
    </div>
    <div class="pa-stat-card">
        <div class="number"><?php echo number_format($summary['unique_sessions']); ?></div>
        <div class="label">Unique Sessions</div>
    </div>
</div>

<!-- Impressions Chart -->
<div class="pa-card">
    <h3>Impressions Over Time</h3>
    <?php if (empty($chart_labels)): ?>
        <div class="pa-alert pa-alert-info">No impression data for this period.</div>
    <?php else: ?>
        <canvas id="impressionsChart" style="width: 100%; height: 300px;"></canvas>
    <?php endif; ?>
</div>

<div class="pa-row">
    <!-- Impressions by Ad -->
    <div class="pa-col" style="flex: 1;">
        <div class="pa-card">
            <h3>Performance by Ad</h3>
            <?php if (empty($impressions_by_ad)): ?>
                <div class="pa-alert pa-alert-info">No data for this period.</div>
            <?php else: ?>
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Ad</th>
                            <th>Impressions</th>
                            <th>Clicks</th>
                            <th>CTR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($impressions_by_ad as $row): ?>
                            <?php
                            $imp = (int) $row['impressions'];
                            $clk = isset($clicks_map[$row['ad_id']]) ? $clicks_map[$row['ad_id']] : 0;
                            $ctr = $imp > 0 ? round(($clk / $imp) * 100, 2) : 0;
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo pause_ads_admin_url('ad_edit.php') . '&id=' . (int) $row['ad_id']; ?>">
                                        <?php echo pause_ads_esc($row['ad_name'] ?: 'Ad #' . $row['ad_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo number_format($imp); ?></td>
                                <td><?php echo number_format($clk); ?></td>
                                <td><?php echo $ctr; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Top Videos -->
    <div class="pa-col" style="flex: 1;">
        <div class="pa-card">
            <h3>Top Videos (by Pause Ad Impressions)</h3>
            <?php if (empty($top_videos)): ?>
                <div class="pa-alert pa-alert-info">No data for this period.</div>
            <?php else: ?>
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Video ID</th>
                            <th>Impressions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_videos as $row): ?>
                            <tr>
                                <td>Video #<?php echo (int) $row['video_id']; ?></td>
                                <td><?php echo number_format((int) $row['impressions']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Daily Breakdown Table -->
<div class="pa-card">
    <h3>Daily Breakdown</h3>
    <?php if (empty($impressions_by_day)): ?>
        <div class="pa-alert pa-alert-info">No data for this period.</div>
    <?php else: ?>
        <table class="pa-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Impressions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_reverse($impressions_by_day) as $day): ?>
                    <tr>
                        <td><?php echo date('l, M j, Y', strtotime($day['day'])); ?></td>
                        <td><?php echo number_format((int) $day['impressions']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($chart_labels)): ?>
<!-- Simple Chart using Canvas (no external dependencies) -->
<script>
(function() {
    var canvas = document.getElementById('impressionsChart');
    if (!canvas) return;

    var ctx = canvas.getContext('2d');
    var labels = <?php echo json_encode($chart_labels); ?>;
    var data = <?php echo json_encode($chart_data); ?>;

    // Set canvas size
    var rect = canvas.parentElement.getBoundingClientRect();
    canvas.width = rect.width - 40;
    canvas.height = 280;

    var padding = { top: 20, right: 20, bottom: 50, left: 60 };
    var chartWidth = canvas.width - padding.left - padding.right;
    var chartHeight = canvas.height - padding.top - padding.bottom;

    var maxVal = Math.max.apply(null, data) || 1;
    var barWidth = Math.max(4, (chartWidth / data.length) - 4);

    // Background
    ctx.fillStyle = '#f8f9fa';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Grid lines
    ctx.strokeStyle = '#e9ecef';
    ctx.lineWidth = 1;
    for (var g = 0; g <= 4; g++) {
        var gy = padding.top + (chartHeight / 4) * g;
        ctx.beginPath();
        ctx.moveTo(padding.left, gy);
        ctx.lineTo(canvas.width - padding.right, gy);
        ctx.stroke();

        // Y-axis labels
        ctx.fillStyle = '#6c757d';
        ctx.font = '11px sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(Math.round(maxVal - (maxVal / 4) * g), padding.left - 8, gy + 4);
    }

    // Bars
    var gradient = ctx.createLinearGradient(0, padding.top, 0, canvas.height - padding.bottom);
    gradient.addColorStop(0, '#667eea');
    gradient.addColorStop(1, '#764ba2');

    for (var i = 0; i < data.length; i++) {
        var x = padding.left + (chartWidth / data.length) * i + 2;
        var barHeight = (data[i] / maxVal) * chartHeight;
        var y = padding.top + chartHeight - barHeight;

        ctx.fillStyle = gradient;
        ctx.beginPath();
        // Rounded top corners
        var r = Math.min(3, barWidth / 2);
        ctx.moveTo(x, y + r);
        ctx.arcTo(x, y, x + barWidth, y, r);
        ctx.arcTo(x + barWidth, y, x + barWidth, y + barHeight, r);
        ctx.lineTo(x + barWidth, y + barHeight);
        ctx.lineTo(x, y + barHeight);
        ctx.closePath();
        ctx.fill();

        // X-axis labels (show every Nth to avoid overlap)
        var showEvery = Math.ceil(data.length / 15);
        if (i % showEvery === 0) {
            ctx.save();
            ctx.fillStyle = '#6c757d';
            ctx.font = '10px sans-serif';
            ctx.textAlign = 'center';
            ctx.translate(x + barWidth / 2, canvas.height - padding.bottom + 15);
            ctx.rotate(-0.5);
            ctx.fillText(labels[i], 0, 0);
            ctx.restore();
        }
    }
})();
</script>
<?php endif; ?>

<?php pause_ads_admin_footer(); ?>
