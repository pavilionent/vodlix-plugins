<?php
/**
 * Pause Ads v2.0 - Advertiser: Campaign Create/Edit
 * Full campaign builder with creatives, targeting, flight, delivery.
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
$cu = pause_ads_require_company_role($company_id, ['owner','admin']);

$camp_id = pause_ads_validate_int($_GET['id'] ?? 0);
$is_edit = false; $camp = null; $msg=''; $err='';

if ($camp_id) {
    $camp = pa_campaign_get($camp_id);
    if (!$camp || (int)$camp['company_id'] !== $company_id) { die('Campaign not found.'); }
    $is_edit = true;
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_campaign'])) {
    if (!pause_ads_check_nonce('campaign_edit')) { $err='Invalid token.'; }
    else {
        $data = [
            'company_id' => $company_id,
            'name' => trim($_POST['name'] ?? ''),
            'flight_start_at' => !empty($_POST['flight_start_at']) ? $_POST['flight_start_at'] : null,
            'flight_end_at'   => !empty($_POST['flight_end_at'])   ? $_POST['flight_end_at']   : null,
            'daily_cap' => !empty($_POST['daily_cap']) ? (int)$_POST['daily_cap'] : null,
            'hourly_cap' => !empty($_POST['hourly_cap']) ? (int)$_POST['hourly_cap'] : null,
            'freq_cap_per_user_per_day' => !empty($_POST['freq_cap']) ? (int)$_POST['freq_cap'] : null,
            'priority' => max(0,(int)($_POST['priority'] ?? 0)),
            'created_by_user_id' => $user_id,
        ];

        if (empty($data['name'])) { $err='Campaign name is required.'; }
        else {
            if ($is_edit) {
                pa_campaign_update($camp_id, $data);
                $msg = 'Campaign updated.';
            } else {
                $data['status'] = PA_STATUS_DRAFT;
                $camp_id = pa_campaign_create($data);
                if ($camp_id) { $is_edit = true; $msg = 'Campaign created.'; }
                else { $err = 'Failed to create campaign.'; }
            }

            // Save targeting
            if ($camp_id && isset($_POST['targeting']) && is_array($_POST['targeting'])) {
                $rules = [];
                foreach ($_POST['targeting'] as $r) {
                    if (!empty($r['rule_type']) && $r['rule_value'] !== '') {
                        $rules[] = ['rule_type'=>$r['rule_type'],'rule_value'=>$r['rule_value']];
                    }
                }
                pa_targeting_save($camp_id, $rules);
            }

            $camp = pa_campaign_get($camp_id);
        }
    }
}

// Handle creative upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_creative']) && $camp_id) {
    if (!pause_ads_check_nonce('creative_upload')) { $err='Invalid token.'; }
    else {
        $upload = pause_ads_handle_upload($_FILES['image'] ?? []);
        if ($upload['success']) {
            pa_creative_create([
                'campaign_id'=>$camp_id, 'name'=>trim($_POST['creative_name'] ?? 'Creative'),
                'image_path'=>$upload['path'], 'image_width'=>$upload['width'], 'image_height'=>$upload['height'],
                'click_url'=>trim($_POST['click_url'] ?? ''), 'alt_text'=>trim($_POST['alt_text'] ?? ''),
                'weight'=>max(1,(int)($_POST['weight'] ?? 1)),
            ]);
            $msg = 'Creative uploaded.';
            $camp = pa_campaign_get($camp_id);
        } else { $err = 'Upload failed: ' . $upload['error']; }
    }
}

// Handle creative delete
if (isset($_GET['del_creative'])) {
    $dcid = (int)$_GET['del_creative'];
    $cr = pa_creative_get($dcid);
    if ($cr && (int)$cr['campaign_id'] === $camp_id) { pa_creative_delete($dcid); $msg='Creative deleted.'; $camp=pa_campaign_get($camp_id); }
}

// Defaults
if (!$camp) {
    $camp = ['id'=>0,'name'=>'','status'=>'draft','flight_start_at'=>'','flight_end_at'=>'',
             'daily_cap'=>'','hourly_cap'=>'','freq_cap_per_user_per_day'=>'','priority'=>0,
             'targeting'=>[],'creatives'=>[]];
}

$rule_types = ['genre'=>'Genre','category'=>'Category','rating'=>'Content Rating','country'=>'Country',
               'region'=>'Region/State','language'=>'Language','device'=>'Device Type'];

$title = $is_edit ? 'Edit Campaign: '.pause_ads_esc($camp['name']) : 'New Campaign';
pause_ads_advertiser_header($title, 'campaigns', $company_id);
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($err); ?></div><?php endif; ?>

<form method="post" action="">
<?php echo pause_ads_nonce_field('campaign_edit'); ?>
<div class="pa-row">
<div class="pa-col" style="flex:2;">
    <div class="pa-card">
        <h3>Campaign Details</h3>
        <div class="pa-form-group">
            <label>Campaign Name *</label>
            <input type="text" name="name" class="pa-input" value="<?php echo pause_ads_esc($camp['name']); ?>" required placeholder="e.g., Holiday Sale Q4">
        </div>
        <div class="pa-row">
            <div class="pa-col"><div class="pa-form-group">
                <label>Flight Start</label>
                <input type="datetime-local" name="flight_start_at" class="pa-input" value="<?php echo !empty($camp['flight_start_at']) ? date('Y-m-d\TH:i',strtotime($camp['flight_start_at'])) : ''; ?>">
            </div></div>
            <div class="pa-col"><div class="pa-form-group">
                <label>Flight End</label>
                <input type="datetime-local" name="flight_end_at" class="pa-input" value="<?php echo !empty($camp['flight_end_at']) ? date('Y-m-d\TH:i',strtotime($camp['flight_end_at'])) : ''; ?>">
            </div></div>
        </div>
    </div>

    <!-- Targeting -->
    <div class="pa-card">
        <h3>Targeting Rules</h3>
        <p style="font-size:13px;color:#6c757d;margin-top:-10px;">Same type = OR; different types = AND. Empty = all videos.</p>
        <div id="targeting-rules">
        <?php $targeting = $camp['targeting'] ?: [['rule_type'=>'','rule_value'=>'']];
        foreach ($targeting as $i=>$r): ?>
            <div class="targeting-row" style="display:flex;gap:8px;margin-bottom:8px;align-items:center;">
                <select name="targeting[<?php echo $i; ?>][rule_type]" class="pa-input" style="flex:1;">
                    <option value="">-- Type --</option>
                    <?php foreach ($rule_types as $rk=>$rl): ?>
                        <option value="<?php echo $rk; ?>" <?php echo ($r['rule_type']??'')===$rk?'selected':''; ?>><?php echo $rl; ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="targeting[<?php echo $i; ?>][rule_value]" class="pa-input" style="flex:2;" value="<?php echo pause_ads_esc($r['rule_value']??''); ?>" placeholder="Value (e.g., US, comedy)">
                <button type="button" class="pa-btn pa-btn-sm pa-btn-danger" onclick="this.closest('.targeting-row').remove();">&times;</button>
            </div>
        <?php endforeach; ?>
        </div>
        <button type="button" class="pa-btn pa-btn-sm" style="background:#e9ecef;color:#495057;" onclick="addRule()">+ Add Rule</button>
    </div>
</div>

<div class="pa-col" style="flex:1;">
    <div class="pa-card">
        <h3>Delivery Caps</h3>
        <div class="pa-form-group"><label>Daily Cap</label><input type="number" name="daily_cap" class="pa-input" min="0" value="<?php echo pause_ads_esc($camp['daily_cap']??''); ?>" placeholder="Unlimited"></div>
        <div class="pa-form-group"><label>Hourly Cap</label><input type="number" name="hourly_cap" class="pa-input" min="0" value="<?php echo pause_ads_esc($camp['hourly_cap']??''); ?>" placeholder="Unlimited"></div>
        <div class="pa-form-group"><label>User Freq Cap/Day</label><input type="number" name="freq_cap" class="pa-input" min="0" value="<?php echo pause_ads_esc($camp['freq_cap_per_user_per_day']??''); ?>" placeholder="Unlimited"></div>
        <div class="pa-form-group"><label>Priority</label><input type="number" name="priority" class="pa-input" min="0" max="100" value="<?php echo (int)$camp['priority']; ?>"><div class="hint">Higher = served first.</div></div>
    </div>

    <?php if ($is_edit): ?>
    <div class="pa-card">
        <h3>Status</h3>
        <p><span class="pa-badge pa-badge-<?php echo $camp['status']; ?>"><?php echo str_replace('_',' ',$camp['status']); ?></span></p>
        <?php if ($camp['status'] === 'draft'): ?>
            <p style="font-size:13px;color:#6c757d;">Upload at least one creative, then purchase a package to activate.</p>
            <?php if (!empty($camp['creatives'])): ?>
                <a href="<?php echo pause_ads_advertiser_url('billing.php').'?campaign_id='.$camp_id.'&company_id='.$company_id; ?>" class="pa-btn pa-btn-success" style="width:100%;text-align:center;">Purchase Package &amp; Activate</a>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <button type="submit" name="save_campaign" class="pa-btn pa-btn-primary" style="width:100%;"><?php echo $is_edit ? 'Update Campaign' : 'Create Campaign'; ?></button>
</div>
</div>
</form>

<?php if ($is_edit): ?>
<!-- Creatives -->
<div class="pa-card">
    <h3>Creatives (<?php echo count($camp['creatives']); ?>)</h3>
    <?php if (!empty($camp['creatives'])): ?>
        <table class="pa-table">
            <thead><tr><th>Preview</th><th>Name</th><th>Click URL</th><th>Weight</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($camp['creatives'] as $cr): ?>
                <tr>
                    <td><img src="<?php echo pause_ads_get_base_url().'/'.PAUSE_ADS_UPLOADS_URL.'/'.pause_ads_esc($cr['image_path']); ?>" style="max-width:80px;max-height:50px;border-radius:4px;"></td>
                    <td><?php echo pause_ads_esc($cr['name']); ?></td>
                    <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;"><?php echo pause_ads_esc($cr['click_url']?:'—'); ?></td>
                    <td><?php echo (int)$cr['weight']; ?></td>
                    <td><a href="?id=<?php echo $camp_id; ?>&del_creative=<?php echo $cr['id']; ?>&company_id=<?php echo $company_id; ?>" class="pa-btn pa-btn-sm pa-btn-danger" onclick="return confirm('Delete this creative?');">Delete</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h4 style="margin-top:15px;font-size:15px;">Upload New Creative</h4>
    <form method="post" enctype="multipart/form-data">
        <?php echo pause_ads_nonce_field('creative_upload'); ?>
        <div class="pa-row">
            <div class="pa-col"><div class="pa-form-group"><label>Name</label><input type="text" name="creative_name" class="pa-input" value="Creative" required></div></div>
            <div class="pa-col"><div class="pa-form-group"><label>Image *</label><input type="file" name="image" class="pa-input" accept="image/*" required></div></div>
        </div>
        <div class="pa-row">
            <div class="pa-col"><div class="pa-form-group"><label>Click URL</label><input type="url" name="click_url" class="pa-input" placeholder="https://..."></div></div>
            <div class="pa-col"><div class="pa-form-group"><label>Alt Text</label><input type="text" name="alt_text" class="pa-input" placeholder="Description"></div></div>
            <div class="pa-col" style="flex:0.5;"><div class="pa-form-group"><label>Weight</label><input type="number" name="weight" class="pa-input" value="1" min="1"></div></div>
        </div>
        <button type="submit" name="upload_creative" class="pa-btn pa-btn-success">Upload Creative</button>
    </form>
</div>
<?php endif; ?>

<script>
var ri = <?php echo count($targeting); ?>;
function addRule() {
    var c = document.getElementById('targeting-rules');
    var types = <?php echo json_encode($rule_types); ?>;
    var row = document.createElement('div');
    row.className='targeting-row'; row.style.cssText='display:flex;gap:8px;margin-bottom:8px;align-items:center;';
    var sel='<select name="targeting['+ri+'][rule_type]" class="pa-input" style="flex:1;"><option value="">-- Type --</option>';
    for(var k in types) sel+='<option value="'+k+'">'+types[k]+'</option>';
    sel+='</select>';
    row.innerHTML=sel+'<input type="text" name="targeting['+ri+'][rule_value]" class="pa-input" style="flex:2;" placeholder="Value">'
        +'<button type="button" class="pa-btn pa-btn-sm pa-btn-danger" onclick="this.closest(\'.targeting-row\').remove();">&times;</button>';
    c.appendChild(row); ri++;
}
</script>

<?php pause_ads_advertiser_footer(); ?>
