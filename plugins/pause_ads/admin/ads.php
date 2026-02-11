<?php
/**
 * Pause Ads Plugin - Admin: Manage Ads
 *
 * List all ads with status, impressions, clicks, and actions.
 * Provides create, edit, delete operations.
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

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = pause_ads_validate_int($_GET['id']);
    if ($del_id && isset($_GET['nonce'])) {
        if (pause_ads_verify_nonce($_GET['nonce'], 'delete_ad_' . $del_id)) {
            if (pause_ads_delete_ad($del_id)) {
                $success_msg = 'Ad deleted successfully.';
            } else {
                $error_msg = 'Failed to delete ad.';
            }
        } else {
            $error_msg = 'Invalid security token.';
        }
    }
}

// Get status filter
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$ads = pause_ads_get_all_ads($filter_status);

// Render page
pause_ads_admin_header('Manage Ads', 'ads');
?>

<?php if (!empty($success_msg)): ?>
    <div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($success_msg); ?></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($error_msg); ?></div>
<?php endif; ?>

<div class="pa-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h3 style="margin: 0;">
            All Ads
            <?php if (!empty($filter_status)): ?>
                <span class="pa-badge pa-badge-<?php echo pause_ads_esc($filter_status); ?>"><?php echo pause_ads_esc($filter_status); ?></span>
            <?php endif; ?>
            (<?php echo count($ads); ?>)
        </h3>
        <div>
            <a href="<?php echo pause_ads_admin_url('ads.php'); ?>" class="pa-btn pa-btn-sm" style="background:#e9ecef;color:#495057;margin-right:5px;">All</a>
            <a href="<?php echo pause_ads_admin_url('ads.php'); ?>&status=active" class="pa-btn pa-btn-sm pa-btn-success">Active</a>
            <a href="<?php echo pause_ads_admin_url('ads.php'); ?>&status=paused" class="pa-btn pa-btn-sm" style="background:#ffc107;color:#212529;">Paused</a>
            <a href="<?php echo pause_ads_admin_url('ad_edit.php'); ?>" class="pa-btn pa-btn-primary" style="margin-left: 10px;">
                + Create New Ad
            </a>
        </div>
    </div>

    <?php if (empty($ads)): ?>
        <div class="pa-alert pa-alert-info">No ads found. <a href="<?php echo pause_ads_admin_url('ad_edit.php'); ?>">Create your first ad</a>.</div>
    <?php else: ?>
        <table class="pa-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Preview</th>
                    <th>Name</th>
                    <th>Advertiser</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Weight</th>
                    <th>Impressions</th>
                    <th>Clicks</th>
                    <th>CTR</th>
                    <th>Schedule</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ads as $ad): ?>
                    <?php
                    $imp = (int) $ad['total_impressions'];
                    $clk = (int) $ad['total_clicks'];
                    $ctr = $imp > 0 ? round(($clk / $imp) * 100, 2) : 0;
                    $status_class = 'pa-badge-' . $ad['status'];
                    $delete_nonce = pause_ads_create_nonce('delete_ad_' . $ad['id']);
                    ?>
                    <tr>
                        <td><?php echo (int) $ad['id']; ?></td>
                        <td>
                            <?php if (!empty($ad['image_path'])): ?>
                                <img src="<?php echo pause_ads_get_base_url() . '/' . PAUSE_ADS_UPLOADS_URL . '/' . pause_ads_esc($ad['image_path']); ?>"
                                     alt="<?php echo pause_ads_esc($ad['alt_text']); ?>"
                                     style="max-width: 80px; max-height: 50px; border-radius: 4px; border: 1px solid #dee2e6;">
                            <?php else: ?>
                                <span style="color: #999;">No image</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?php echo pause_ads_esc($ad['name']); ?></strong></td>
                        <td><?php echo pause_ads_esc($ad['advertiser'] ?: '—'); ?></td>
                        <td><span class="pa-badge <?php echo $status_class; ?>"><?php echo pause_ads_esc($ad['status']); ?></span></td>
                        <td><?php echo (int) $ad['priority']; ?></td>
                        <td><?php echo (int) ($ad['weight'] ?? 1); ?></td>
                        <td><?php echo number_format($imp); ?></td>
                        <td><?php echo number_format($clk); ?></td>
                        <td><?php echo $ctr; ?>%</td>
                        <td>
                            <?php
                            $start = $ad['start_at'] ? date('M j, Y', strtotime($ad['start_at'])) : 'No start';
                            $end = $ad['end_at'] ? date('M j, Y', strtotime($ad['end_at'])) : 'No end';
                            echo pause_ads_esc($start) . '<br><small>' . pause_ads_esc($end) . '</small>';
                            ?>
                        </td>
                        <td>
                            <a href="<?php echo pause_ads_admin_url('ad_edit.php') . '&id=' . (int) $ad['id']; ?>"
                               class="pa-btn pa-btn-sm pa-btn-primary" title="Edit">Edit</a>
                            <a href="<?php echo pause_ads_admin_url('ads.php') . '&action=delete&id=' . (int) $ad['id'] . '&nonce=' . $delete_nonce; ?>"
                               class="pa-btn pa-btn-sm pa-btn-danger"
                               onclick="return confirm('Are you sure you want to delete this ad? This cannot be undone.');"
                               title="Delete">Del</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php pause_ads_admin_footer(); ?>
