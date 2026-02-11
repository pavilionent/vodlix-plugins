<?php
/**
 * Pause Ads v2.0 - Admin: Test Mode / Eligibility Tester
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

$results = null; $test_vid = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_test'])) {
    if (!pause_ads_check_nonce('test_mode')) { $err='Invalid token.'; }
    else {
        $test_vid = (int)($_POST['test_video_id'] ?? 0);
        if ($test_vid <= 0) { $err='Enter a valid Video ID.'; }
        else {
            $session_id = pause_ads_get_session_id();
            $user_id = pause_ads_get_current_user_id();
            $geo = pause_ads_resolve_geo();

            $results = [
                'video_id' => $test_vid,
                'is_avod' => pause_ads_is_avod($test_vid),
                'plugin_enabled' => pause_ads_is_enabled(),
                'video_meta' => pause_ads_get_video_meta($test_vid),
                'geo' => $geo,
            ];

            // Test eligible creative
            $ad = pause_ads_find_eligible($test_vid, $session_id, $user_id, $geo['country_code'], $geo['region_code']);
            $results['selected_ad'] = $ad;

            // Get all candidates for diagnostic
            $candidates = pa_eligible_get_candidates();
            $results['total_candidates'] = count($candidates);
            $results['after_targeting'] = count(pa_eligible_filter_targeting($candidates, $results['video_meta'], $geo['country_code'], $geo['region_code']));
        }
    }
}

pause_ads_admin_header('Eligibility Test', 'test');
?>

<div class="pa-card">
    <h3>Test Ad Eligibility</h3>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <?php echo pause_ads_nonce_field('test_mode'); ?>
        <div class="pa-form-group" style="flex:1;min-width:200px;"><label>Video ID</label><input type="number" name="test_video_id" class="pa-input" value="<?php echo (int)$test_vid; ?>" required min="1"></div>
        <button type="submit" name="run_test" class="pa-btn pa-btn-primary" style="margin-bottom:15px;">Run Test</button>
    </form>
</div>

<?php if ($err): ?><div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($err); ?></div><?php endif; ?>

<?php if ($results): ?>
<div class="pa-card">
    <h3>Results for Video #<?php echo (int)$results['video_id']; ?></h3>
    <table class="pa-table">
        <tr><td style="width:40%;font-weight:600;">Plugin Enabled</td><td><?php echo $results['plugin_enabled'] ? '<span style="color:#28a745;">YES</span>' : '<span style="color:#dc3545;">NO</span>'; ?></td></tr>
        <tr><td style="font-weight:600;">Video is AVOD</td><td><?php echo $results['is_avod'] ? '<span style="color:#28a745;">YES</span>' : '<span style="color:#dc3545;">NO</span>'; ?></td></tr>
        <tr><td style="font-weight:600;">Detected Geo</td><td><?php echo pause_ads_esc($results['geo']['country_code'] ?: 'Unknown'); ?> / <?php echo pause_ads_esc($results['geo']['region_code'] ?: '—'); ?></td></tr>
        <tr><td style="font-weight:600;">Total Active Candidates</td><td><?php echo $results['total_candidates']; ?> creatives</td></tr>
        <tr><td style="font-weight:600;">After Targeting Filter</td><td><?php echo $results['after_targeting']; ?> creatives</td></tr>
        <tr><td style="font-weight:600;">Video Metadata</td><td><pre style="margin:0;font-size:12px;"><?php echo pause_ads_esc(json_encode($results['video_meta'], JSON_PRETTY_PRINT)); ?></pre></td></tr>
    </table>

    <?php if ($results['selected_ad']): ?>
        <div class="pa-alert pa-alert-success" style="margin-top:15px;">
            <strong>Selected Creative:</strong> #<?php echo (int)$results['selected_ad']['creative_id']; ?>
            (Campaign #<?php echo (int)$results['selected_ad']['campaign_id']; ?>)
        </div>
        <div style="text-align:center;padding:20px;background:#333;border-radius:8px;">
            <img src="<?php echo pause_ads_get_base_url().'/'.pause_ads_esc($results['selected_ad']['image_url']); ?>" style="max-width:400px;max-height:300px;border-radius:4px;">
        </div>
    <?php else: ?>
        <div class="pa-alert pa-alert-info" style="margin-top:15px;">No eligible ad found. Check AVOD status, active campaigns, targeting, and budget.</div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php pause_ads_admin_footer(); ?>
