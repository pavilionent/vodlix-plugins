<?php
/**
 * Pause Ads Plugin - Admin: AVOD Videos Management
 *
 * Enable/disable AVOD (pause ads) on individual videos.
 * Videos must be marked as AVOD for pause ads to display.
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

$success_msg = '';
$error_msg = '';

// Handle add video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_avod'])) {
    if (!pause_ads_check_nonce('avod_manage')) {
        $error_msg = 'Invalid security token.';
    } else {
        $video_id = pause_ads_validate_int($_POST['video_id'] ?? '');
        if ($video_id === false || $video_id <= 0) {
            $error_msg = 'Please enter a valid Video ID.';
        } else {
            if (pause_ads_set_avod($video_id, true)) {
                $success_msg = 'Video #' . $video_id . ' marked as AVOD-enabled.';
            } else {
                $error_msg = 'Failed to update AVOD status.';
            }
        }
    }
}

// Handle bulk add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add_avod'])) {
    if (!pause_ads_check_nonce('avod_manage')) {
        $error_msg = 'Invalid security token.';
    } else {
        $ids_raw = trim($_POST['video_ids'] ?? '');
        if (empty($ids_raw)) {
            $error_msg = 'Please enter at least one Video ID.';
        } else {
            $ids = array_filter(array_map('trim', preg_split('/[\s,;]+/', $ids_raw)));
            $added = 0;
            foreach ($ids as $vid) {
                $vid = pause_ads_validate_int($vid);
                if ($vid && $vid > 0) {
                    if (pause_ads_set_avod($vid, true)) {
                        $added++;
                    }
                }
            }
            if ($added > 0) {
                $success_msg = $added . ' video(s) marked as AVOD-enabled.';
            } else {
                $error_msg = 'No valid Video IDs found.';
            }
        }
    }
}

// Handle toggle
if (isset($_GET['action']) && $_GET['action'] === 'toggle' && isset($_GET['vid'])) {
    $vid = pause_ads_validate_int($_GET['vid']);
    $new_state = isset($_GET['state']) && $_GET['state'] === '1';
    if ($vid && isset($_GET['nonce']) && pause_ads_verify_nonce($_GET['nonce'], 'avod_toggle_' . $vid)) {
        pause_ads_set_avod($vid, $new_state);
        $success_msg = 'Video #' . $vid . ' AVOD status updated.';
    }
}

// Handle remove
if (isset($_GET['action']) && $_GET['action'] === 'remove' && isset($_GET['vid'])) {
    $vid = pause_ads_validate_int($_GET['vid']);
    if ($vid && isset($_GET['nonce']) && pause_ads_verify_nonce($_GET['nonce'], 'avod_remove_' . $vid)) {
        pause_ads_set_avod($vid, false);
        $success_msg = 'Video #' . $vid . ' removed from AVOD.';
    }
}

// Get current AVOD videos
$avod_videos = pause_ads_get_avod_videos();

pause_ads_admin_header('AVOD Video Management', 'avod');
?>

<?php if (!empty($success_msg)): ?>
    <div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($success_msg); ?></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($error_msg); ?></div>
<?php endif; ?>

<div class="pa-row">
    <div class="pa-col" style="flex: 2;">
        <div class="pa-card">
            <h3>AVOD-Enabled Videos (<?php echo count($avod_videos); ?>)</h3>
            <p style="font-size:13px;color:#6c757d;margin-top:-10px;">
                Only videos marked as AVOD-enabled will display pause ads to viewers.
            </p>

            <?php if (empty($avod_videos)): ?>
                <div class="pa-alert pa-alert-info">
                    No videos are currently AVOD-enabled. Add video IDs using the form on the right to get started.
                </div>
            <?php else: ?>
                <table class="pa-table">
                    <thead>
                        <tr>
                            <th>Video ID</th>
                            <th>Title</th>
                            <th>AVOD Status</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($avod_videos as $v): ?>
                            <?php
                            $toggle_state = $v['is_avod'] ? '0' : '1';
                            $toggle_nonce = pause_ads_create_nonce('avod_toggle_' . $v['video_id']);
                            $remove_nonce = pause_ads_create_nonce('avod_remove_' . $v['video_id']);
                            ?>
                            <tr>
                                <td><strong>#<?php echo (int) $v['video_id']; ?></strong></td>
                                <td><?php echo pause_ads_esc($v['video_title'] ?? 'Unknown'); ?></td>
                                <td>
                                    <?php if ($v['is_avod']): ?>
                                        <span class="pa-badge pa-badge-active">Enabled</span>
                                    <?php else: ?>
                                        <span class="pa-badge pa-badge-paused">Disabled</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $v['updated_at'] ? date('M j, Y H:i', strtotime($v['updated_at'])) : '—'; ?></td>
                                <td>
                                    <a href="<?php echo pause_ads_admin_url('avod_videos.php') . '&action=toggle&vid=' . (int)$v['video_id'] . '&state=' . $toggle_state . '&nonce=' . $toggle_nonce; ?>"
                                       class="pa-btn pa-btn-sm <?php echo $v['is_avod'] ? '' : 'pa-btn-success'; ?>"
                                       style="<?php echo $v['is_avod'] ? 'background:#ffc107;color:#212529;' : ''; ?>">
                                        <?php echo $v['is_avod'] ? 'Disable' : 'Enable'; ?>
                                    </a>
                                    <a href="<?php echo pause_ads_admin_url('avod_videos.php') . '&action=remove&vid=' . (int)$v['video_id'] . '&nonce=' . $remove_nonce; ?>"
                                       class="pa-btn pa-btn-sm pa-btn-danger"
                                       onclick="return confirm('Remove this video from AVOD list?');">
                                        Remove
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="pa-col" style="flex: 1;">
        <!-- Add Single Video -->
        <div class="pa-card">
            <h3>Add Single Video</h3>
            <form method="post" action="">
                <?php echo pause_ads_nonce_field('avod_manage'); ?>
                <div class="pa-form-group">
                    <label for="video_id">Video ID</label>
                    <input type="number" id="video_id" name="video_id" class="pa-input"
                           placeholder="Enter video ID" min="1" required>
                </div>
                <button type="submit" name="add_avod" class="pa-btn pa-btn-primary" style="width:100%;">
                    Mark as AVOD-Enabled
                </button>
            </form>
        </div>

        <!-- Bulk Add -->
        <div class="pa-card">
            <h3>Bulk Add Videos</h3>
            <form method="post" action="">
                <?php echo pause_ads_nonce_field('avod_manage'); ?>
                <div class="pa-form-group">
                    <label for="video_ids">Video IDs</label>
                    <textarea id="video_ids" name="video_ids" class="pa-input" rows="5"
                              placeholder="Enter video IDs separated by commas, spaces, or newlines&#10;&#10;Example:&#10;101, 102, 103&#10;204&#10;305"></textarea>
                </div>
                <button type="submit" name="bulk_add_avod" class="pa-btn pa-btn-success" style="width:100%;">
                    Bulk Enable AVOD
                </button>
            </form>
        </div>

        <!-- Info -->
        <div class="pa-card">
            <h3>How It Works</h3>
            <ul style="font-size:13px;color:#495057;padding-left:20px;margin:0;">
                <li>Add video IDs to enable AVOD monetization.</li>
                <li>When a viewer pauses an AVOD video, an eligible ad will overlay the player.</li>
                <li>You can also enable AVOD from the video edit page in ClipBucket admin.</li>
                <li>Impressions are only counted after the minimum pause duration.</li>
            </ul>
        </div>
    </div>
</div>

<?php pause_ads_admin_footer(); ?>
