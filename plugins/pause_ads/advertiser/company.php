<?php
/**
 * Pause Ads v2.0 - Advertiser: Company Management
 * Create company, manage users/roles.
 * @package PauseAds
 */
$cb_root = realpath(__DIR__ . '/../../../');
foreach (['/includes/config.inc.php','/include/config.inc.php','/cb_config.php'] as $f) {
    if (file_exists($cb_root.$f)) { define('STARTER',true); require_once $cb_root.$f; break; }
}
if (!defined('STARTER')) define('STARTER',true);
require_once __DIR__ . '/../main.php';

$user_id = pause_ads_require_login();
$msg = ''; $err = '';

// Create company
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_company'])) {
    if (!pause_ads_check_nonce('company_create')) { $err = 'Invalid token.'; }
    else {
        $name = trim($_POST['company_name'] ?? '');
        $email = trim($_POST['billing_email'] ?? '');
        if (empty($name)) { $err = 'Company name is required.'; }
        else {
            $cid = pa_company_create($name, $email, $user_id);
            if ($cid) {
                $_SESSION['pause_ads_company_id'] = $cid;
                header('Location: ' . pause_ads_advertiser_url('dashboard.php') . '?company_id=' . $cid);
                exit;
            } else { $err = 'Failed to create company.'; }
        }
    }
}

$company_id = pause_ads_get_active_company_id();

// Add user (owner/admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user']) && $company_id) {
    pause_ads_require_company_role($company_id, ['owner','admin']);
    if (!pause_ads_check_nonce('company_user')) { $err = 'Invalid token.'; }
    else {
        $new_uid = (int)($_POST['new_user_id'] ?? 0);
        $new_role = in_array($_POST['new_role'] ?? '', ['admin','analyst']) ? $_POST['new_role'] : 'analyst';
        if ($new_uid > 0) {
            pa_company_add_user($company_id, $new_uid, $new_role);
            $msg = 'User added.';
        }
    }
}

// Remove user
if (isset($_GET['action']) && $_GET['action'] === 'remove_user' && $company_id) {
    pause_ads_require_company_role($company_id, ['owner']);
    $rm_uid = (int)($_GET['uid'] ?? 0);
    if ($rm_uid && $rm_uid != $user_id) {
        pa_company_remove_user($company_id, $rm_uid);
        $msg = 'User removed.';
    }
}

$companies = pause_ads_get_user_companies($user_id);
$company = $company_id ? pa_company_get($company_id) : null;
$users = $company_id ? pa_company_get_users($company_id) : [];
$cu_role = '';
foreach ($users as $u) { if ((int)$u['user_id'] === $user_id) $cu_role = $u['role']; }

pause_ads_advertiser_header('Company', 'company', $company_id);
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($err); ?></div><?php endif; ?>

<?php if (isset($_GET['setup']) || empty($companies)): ?>
<div class="pa-card">
    <h3>Create Your Company</h3>
    <p style="color:#6c757d;font-size:14px;">To start advertising, create a company profile.</p>
    <form method="post">
        <?php echo pause_ads_nonce_field('company_create'); ?>
        <div class="pa-form-group">
            <label>Company Name *</label>
            <input type="text" name="company_name" class="pa-input" required placeholder="Your Brand / Agency Name">
        </div>
        <div class="pa-form-group">
            <label>Billing Email</label>
            <input type="email" name="billing_email" class="pa-input" placeholder="billing@company.com">
        </div>
        <button type="submit" name="create_company" class="pa-btn pa-btn-primary">Create Company</button>
    </form>
</div>
<?php endif; ?>

<?php if (count($companies) > 1): ?>
<div class="pa-card">
    <h3>Your Companies</h3>
    <table class="pa-table">
        <thead><tr><th>Company</th><th>Role</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($companies as $co): ?>
            <tr>
                <td><?php echo pause_ads_esc($co['name']); ?></td>
                <td><span class="pa-badge pa-badge-active"><?php echo $co['role']; ?></span></td>
                <td><a href="<?php echo pause_ads_advertiser_url('dashboard.php').'?company_id='.$co['id']; ?>" class="pa-btn pa-btn-sm pa-btn-primary">Select</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($company && in_array($cu_role, ['owner','admin'])): ?>
<div class="pa-card">
    <h3>Team Members — <?php echo pause_ads_esc($company['name']); ?></h3>
    <table class="pa-table">
        <thead><tr><th>User ID</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td>#<?php echo (int)$u['user_id']; ?></td>
                <td><span class="pa-badge pa-badge-<?php echo $u['role']==='owner'?'active':'paused'; ?>"><?php echo $u['role']; ?></span></td>
                <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                <td>
                    <?php if ($cu_role === 'owner' && (int)$u['user_id'] !== $user_id): ?>
                        <a href="?action=remove_user&uid=<?php echo $u['user_id']; ?>&company_id=<?php echo $company_id; ?>" class="pa-btn pa-btn-sm pa-btn-danger" onclick="return confirm('Remove this user?');">Remove</a>
                    <?php else: echo '—'; endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h4 style="margin-top:20px;font-size:15px;">Add Team Member</h4>
    <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
        <?php echo pause_ads_nonce_field('company_user'); ?>
        <div class="pa-form-group" style="flex:1;min-width:150px;">
            <label>ClipBucket User ID</label>
            <input type="number" name="new_user_id" class="pa-input" min="1" required placeholder="User ID">
        </div>
        <div class="pa-form-group" style="flex:1;min-width:150px;">
            <label>Role</label>
            <select name="new_role" class="pa-input">
                <option value="admin">Admin (manage campaigns)</option>
                <option value="analyst">Analyst (read-only)</option>
            </select>
        </div>
        <button type="submit" name="add_user" class="pa-btn pa-btn-primary" style="margin-bottom:15px;">Add</button>
    </form>
</div>
<?php endif; ?>

<?php pause_ads_advertiser_footer(); ?>
