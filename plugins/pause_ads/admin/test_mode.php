<?php
/**
 * Pause Ads Plugin - Admin: Test Mode / Eligibility Tester
 *
 * Simulates ad eligibility for a given video and shows detailed
 * diagnostic information about why each ad was/wasn't eligible.
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

$results = null;
$test_video_id = '';
$test_ad_id = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_test'])) {
    if (!pause_ads_check_nonce('test_mode')) {
        $error_msg = 'Invalid security token.';
    } else {
        $test_video_id = (int) ($_POST['test_video_id'] ?? 0);
        $test_ad_id = !empty($_POST['test_ad_id']) ? (int) $_POST['test_ad_id'] : null;

        if ($test_video_id <= 0) {
            $error_msg = 'Please enter a valid Video ID.';
        } else {
            $results = pause_ads_test_eligibility($test_video_id, $test_ad_id);
        }
    }
}

// Get list of all ads for the dropdown
$all_ads = pause_ads_get_all_ads();

pause_ads_admin_header('Eligibility Test Mode', 'test');
?>

<div class="pa-card">
    <h3>Test Ad Eligibility</h3>
    <p style="font-size:13px;color:#6c757d;margin-top:-10px;">
        Enter a Video ID to simulate a pause event and see which ads are eligible and why.
        This helps debug targeting, delivery caps, and scheduling issues.
    </p>

    <form method="post" action="">
        <?php echo pause_ads_nonce_field('test_mode'); ?>

        <div class="pa-row">
            <div class="pa-col">
                <div class="pa-form-group">
                    <label for="test_video_id">Video ID *</label>
                    <input type="number" id="test_video_id" name="test_video_id" class="pa-input"
                           value="<?php echo (int) $test_video_id; ?>" min="1" required
                           placeholder="Enter a video ID to test">
                </div>
            </div>
            <div class="pa-col">
                <div class="pa-form-group">
                    <label for="test_ad_id">Specific Ad (optional)</label>
                    <select id="test_ad_id" name="test_ad_id" class="pa-input">
                        <option value="">-- Test All Active Ads --</option>
                        <?php foreach ($all_ads as $ad): ?>
                            <option value="<?php echo (int) $ad['id']; ?>"
                                <?php echo ($test_ad_id == $ad['id']) ? 'selected' : ''; ?>>
                                #<?php echo (int) $ad['id']; ?> - <?php echo pause_ads_esc($ad['name']); ?>
                                (<?php echo pause_ads_esc($ad['status']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="pa-col" style="flex: 0.5; display: flex; align-items: flex-end;">
                <button type="submit" name="run_test" class="pa-btn pa-btn-primary" style="width:100%; margin-bottom: 15px;">
                    Run Test
                </button>
            </div>
        </div>
    </form>
</div>

<?php if (!empty($error_msg)): ?>
    <div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($error_msg); ?></div>
<?php endif; ?>

<?php if ($results !== null): ?>
    <!-- Test Results -->
    <div class="pa-card">
        <h3>Test Results for Video #<?php echo (int) $results['video_id']; ?></h3>

        <!-- System Checks -->
        <table class="pa-table" style="margin-bottom: 20px;">
            <thead>
                <tr>
                    <th style="width: 40%;">System Check</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Plugin Enabled</strong></td>
                    <td>
                        <?php if ($results['plugin_enabled']): ?>
                            <span style="color: #28a745; font-weight: 600;">&#10003; YES</span>
                        <?php else: ?>
                            <span style="color: #dc3545; font-weight: 600;">&#10007; NO</span>
                            <small> - Plugin is disabled in settings</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Video is AVOD-Enabled</strong></td>
                    <td>
                        <?php if ($results['is_avod']): ?>
                            <span style="color: #28a745; font-weight: 600;">&#10003; YES</span>
                        <?php else: ?>
                            <span style="color: #dc3545; font-weight: 600;">&#10007; NO</span>
                            <small> - <a href="<?php echo pause_ads_admin_url('avod_videos.php'); ?>">Enable AVOD for this video</a></small>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Video Metadata -->
        <?php if (!empty($results['video_meta'])): ?>
            <h4 style="margin: 15px 0 10px; font-size: 15px;">Video Metadata (for targeting)</h4>
            <table class="pa-table" style="margin-bottom: 20px;">
                <tbody>
                    <?php foreach ($results['video_meta'] as $key => $value): ?>
                        <tr>
                            <td style="width: 30%; font-weight: 600;"><?php echo pause_ads_esc($key); ?></td>
                            <td>
                                <?php if (!empty($value)): ?>
                                    <code><?php echo pause_ads_esc($value); ?></code>
                                <?php else: ?>
                                    <span style="color: #999;">empty</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Per-Ad Results -->
        <?php if (!empty($results['ads_tested'])): ?>
            <h4 style="margin: 15px 0 10px; font-size: 15px;">Ad Eligibility Results</h4>
            <table class="pa-table">
                <thead>
                    <tr>
                        <th>Ad</th>
                        <th>Schedule</th>
                        <th>Targeting</th>
                        <th>Delivery Caps</th>
                        <th>Final</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results['ads_tested'] as $test): ?>
                        <tr>
                            <td>
                                <strong>#<?php echo (int) $test['ad_id']; ?></strong>
                                - <?php echo pause_ads_esc($test['name']); ?>
                                <br><small class="pa-badge pa-badge-<?php echo $test['status']; ?>"><?php echo $test['status']; ?></small>
                            </td>
                            <?php foreach (['schedule', 'targeting', 'delivery_caps'] as $check): ?>
                                <td>
                                    <?php
                                    $val = $test['checks'][$check] ?? 'N/A';
                                    if (strpos($val, 'PASS') === 0): ?>
                                        <span style="color: #28a745; font-weight: 600;">&#10003; PASS</span>
                                    <?php else: ?>
                                        <span style="color: #dc3545; font-weight: 600;">&#10007; FAIL</span>
                                        <br><small style="color: #dc3545;"><?php echo pause_ads_esc(str_replace('FAIL ', '', $val)); ?></small>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <?php if ($test['eligible']): ?>
                                    <span class="pa-badge pa-badge-active" style="font-size: 13px;">ELIGIBLE</span>
                                <?php else: ?>
                                    <span class="pa-badge pa-badge-archived" style="font-size: 13px;">NOT ELIGIBLE</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (isset($results['reason'])): ?>
            <div class="pa-alert pa-alert-error">
                <strong>Cannot test:</strong> <?php echo pause_ads_esc($results['reason']); ?>
            </div>
        <?php else: ?>
            <div class="pa-alert pa-alert-info">
                No active ads found to test. <a href="<?php echo pause_ads_admin_url('ad_edit.php'); ?>">Create an ad</a> first.
            </div>
        <?php endif; ?>
    </div>

    <!-- Simulate Selection -->
    <?php if ($results['is_avod'] && $results['plugin_enabled']): ?>
        <div class="pa-card">
            <h3>Simulated Ad Selection</h3>
            <?php
            $session_id = pause_ads_get_session_id();
            $user_id = pause_ads_get_current_user_id();
            $selected = pause_ads_find_eligible_ad($results['video_id'], $session_id, $user_id);
            ?>
            <?php if ($selected): ?>
                <div class="pa-alert pa-alert-success">
                    <strong>Selected Ad:</strong> #<?php echo (int) $selected['ad_id']; ?>
                    - <?php echo pause_ads_esc($selected['name']); ?>
                </div>
                <div style="text-align: center; padding: 20px; background: #333; border-radius: 8px;">
                    <img src="<?php echo pause_ads_get_base_url() . '/' . pause_ads_esc($selected['image_url']); ?>"
                         alt="<?php echo pause_ads_esc($selected['alt_text']); ?>"
                         style="max-width: 400px; max-height: 300px; border-radius: 4px;">
                    <?php if (!empty($selected['click_url'])): ?>
                        <br><br>
                        <a href="<?php echo pause_ads_esc($selected['click_url']); ?>" target="_blank"
                           style="color: #fff; font-size: 13px;">
                            Click URL: <?php echo pause_ads_esc($selected['click_url']); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="pa-alert pa-alert-info">
                    No eligible ad was selected. All candidates may have been filtered out by targeting or caps.
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php pause_ads_admin_footer(); ?>
