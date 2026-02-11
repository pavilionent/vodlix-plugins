<?php
/**
 * Pause Ads v2.0 - Admin: Package Management (CRUD)
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

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_package'])) {
    if (!pause_ads_check_nonce('pkg_edit')) { $err='Invalid token.'; }
    else {
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'price_amount' => (float)($_POST['price_amount'] ?? 0),
            'currency' => trim($_POST['currency'] ?? 'USD'),
            'included_impressions' => (int)($_POST['included_impressions'] ?? 0),
            'max_flight_days' => !empty($_POST['max_flight_days']) ? (int)$_POST['max_flight_days'] : null,
            'status' => in_array($_POST['status']??'',['active','inactive']) ? $_POST['status'] : 'active',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
        ];
        $pkg_id = (int)($_POST['pkg_id'] ?? 0);
        if (empty($data['name'])) { $err='Name required.'; }
        else {
            if ($pkg_id > 0) { pa_package_update($pkg_id, $data); $msg='Package updated.'; }
            else { pa_package_create($data); $msg='Package created.'; }
        }
    }
}

$packages = pa_package_list();
$edit_pkg = null;
if (isset($_GET['edit'])) { $edit_pkg = pa_package_get((int)$_GET['edit']); }

pause_ads_admin_header('Manage Packages', 'packages');
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($err); ?></div><?php endif; ?>

<div class="pa-row">
<div class="pa-col" style="flex:2;">
    <div class="pa-card">
        <h3>Packages (<?php echo count($packages); ?>)</h3>
        <table class="pa-table">
            <thead><tr><th>Name</th><th>Price</th><th>Impressions</th><th>Max Flight</th><th>Status</th><th>Order</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($packages as $p): ?>
                <tr>
                    <td><strong><?php echo pause_ads_esc($p['name']); ?></strong></td>
                    <td>$<?php echo number_format((float)$p['price_amount'],2); ?> <?php echo pause_ads_esc($p['currency']); ?></td>
                    <td><?php echo number_format((int)$p['included_impressions']); ?></td>
                    <td><?php echo $p['max_flight_days'] ? $p['max_flight_days'].' days' : 'Unlimited'; ?></td>
                    <td><span class="pa-badge pa-badge-<?php echo $p['status']; ?>"><?php echo $p['status']; ?></span></td>
                    <td><?php echo (int)$p['sort_order']; ?></td>
                    <td><a href="?edit=<?php echo $p['id']; ?>" class="pa-btn pa-btn-sm pa-btn-primary">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="pa-col" style="flex:1;">
    <div class="pa-card">
        <h3><?php echo $edit_pkg ? 'Edit Package' : 'New Package'; ?></h3>
        <form method="post">
            <?php echo pause_ads_nonce_field('pkg_edit'); ?>
            <input type="hidden" name="pkg_id" value="<?php echo $edit_pkg ? (int)$edit_pkg['id'] : 0; ?>">
            <div class="pa-form-group"><label>Name *</label><input type="text" name="name" class="pa-input" value="<?php echo pause_ads_esc($edit_pkg['name'] ?? ''); ?>" required></div>
            <div class="pa-form-group"><label>Price</label><input type="number" name="price_amount" class="pa-input" step="0.01" min="0" value="<?php echo (float)($edit_pkg['price_amount'] ?? 0); ?>"></div>
            <div class="pa-form-group"><label>Currency</label><input type="text" name="currency" class="pa-input" value="<?php echo pause_ads_esc($edit_pkg['currency'] ?? 'USD'); ?>" maxlength="3"></div>
            <div class="pa-form-group"><label>Impressions</label><input type="number" name="included_impressions" class="pa-input" min="0" value="<?php echo (int)($edit_pkg['included_impressions'] ?? 0); ?>"></div>
            <div class="pa-form-group"><label>Max Flight Days</label><input type="number" name="max_flight_days" class="pa-input" min="0" value="<?php echo pause_ads_esc($edit_pkg['max_flight_days'] ?? ''); ?>" placeholder="Unlimited"></div>
            <div class="pa-form-group"><label>Status</label><select name="status" class="pa-input">
                <option value="active" <?php echo ($edit_pkg['status']??'')==='active'?'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo ($edit_pkg['status']??'')==='inactive'?'selected':''; ?>>Inactive</option>
            </select></div>
            <div class="pa-form-group"><label>Sort Order</label><input type="number" name="sort_order" class="pa-input" value="<?php echo (int)($edit_pkg['sort_order'] ?? 0); ?>"></div>
            <button type="submit" name="save_package" class="pa-btn pa-btn-primary" style="width:100%;">Save Package</button>
        </form>
    </div>
</div>
</div>

<?php pause_ads_admin_footer(); ?>
