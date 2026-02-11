<?php
/**
 * Pause Ads v2.0 - Admin: AVOD Videos Management
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

$msg=''; $err='';

// Add single
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_avod'])) {
    if (!pause_ads_check_nonce('avod_manage')) { $err='Invalid token.'; }
    else {
        $vid = pause_ads_validate_int($_POST['video_id'] ?? '');
        if (!$vid || $vid <= 0) { $err='Invalid Video ID.'; }
        else { pause_ads_set_avod($vid, true); $msg='Video #'.$vid.' AVOD-enabled.'; }
    }
}

// Bulk add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_add'])) {
    if (!pause_ads_check_nonce('avod_manage')) { $err='Invalid token.'; }
    else {
        $ids = array_filter(array_map('trim', preg_split('/[\s,;]+/', $_POST['video_ids'] ?? '')));
        $added = 0;
        foreach ($ids as $id) {
            $id = pause_ads_validate_int($id);
            if ($id && $id > 0) { pause_ads_set_avod($id, true); $added++; }
        }
        $msg = $added . ' video(s) AVOD-enabled.';
    }
}

// Toggle/remove
if (isset($_GET['action']) && isset($_GET['vid'])) {
    $vid = (int)$_GET['vid'];
    if ($_GET['action']==='disable') { pause_ads_set_avod($vid, false); $msg='Disabled.'; }
    if ($_GET['action']==='enable')  { pause_ads_set_avod($vid, true); $msg='Enabled.'; }
}

$avod_videos = pause_ads_get_avod_videos();
pause_ads_admin_header('AVOD Videos', 'avod');
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($err); ?></div><?php endif; ?>

<div class="pa-row">
<div class="pa-col" style="flex:2;">
    <div class="pa-card">
        <h3>AVOD-Enabled Videos (<?php echo count($avod_videos); ?>)</h3>
        <?php if (empty($avod_videos)): ?>
            <div class="pa-alert pa-alert-info">No videos AVOD-enabled yet.</div>
        <?php else: ?>
            <table class="pa-table">
                <thead><tr><th>Video ID</th><th>Title</th><th>AVOD</th><th>Updated</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($avod_videos as $v): ?>
                    <tr>
                        <td>#<?php echo (int)$v['video_id']; ?></td>
                        <td><?php echo pause_ads_esc($v['video_title'] ?? '—'); ?></td>
                        <td><?php echo $v['is_avod'] ? '<span class="pa-badge pa-badge-active">Yes</span>' : '<span class="pa-badge pa-badge-inactive">No</span>'; ?></td>
                        <td><?php echo date('M j, Y', strtotime($v['updated_at'])); ?></td>
                        <td>
                            <?php if ($v['is_avod']): ?>
                                <a href="?action=disable&vid=<?php echo $v['video_id']; ?>" class="pa-btn pa-btn-sm pa-btn-warning">Disable</a>
                            <?php else: ?>
                                <a href="?action=enable&vid=<?php echo $v['video_id']; ?>" class="pa-btn pa-btn-sm pa-btn-success">Enable</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<div class="pa-col" style="flex:1;">
    <div class="pa-card">
        <h3>Add Video</h3>
        <form method="post"><?php echo pause_ads_nonce_field('avod_manage'); ?>
            <div class="pa-form-group"><label>Video ID</label><input type="number" name="video_id" class="pa-input" min="1" required></div>
            <button type="submit" name="add_avod" class="pa-btn pa-btn-primary" style="width:100%;">Enable AVOD</button>
        </form>
    </div>
    <div class="pa-card">
        <h3>Bulk Add</h3>
        <form method="post"><?php echo pause_ads_nonce_field('avod_manage'); ?>
            <div class="pa-form-group"><label>Video IDs</label><textarea name="video_ids" class="pa-input" rows="4" placeholder="101, 102, 103"></textarea></div>
            <button type="submit" name="bulk_add" class="pa-btn pa-btn-success" style="width:100%;">Bulk Enable</button>
        </form>
    </div>
</div>
</div>

<?php pause_ads_admin_footer(); ?>
