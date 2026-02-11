<?php
/**
 * Pause Ads v2.0 - Admin: Settings
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!pause_ads_check_nonce('pa_settings')) { $err='Invalid token.'; }
    else {
        $settings = [
            'enabled' => isset($_POST['enabled']) ? '1' : '0',
            'min_pause_ms' => max(100, min(10000, (int)($_POST['min_pause_ms'] ?? 1000))),
            'overlay_position' => in_array($_POST['overlay_position'] ?? '', ['center','bottom-center','bottom-right','bottom-left','top-right','top-left']) ? $_POST['overlay_position'] : 'center',
            'overlay_style' => in_array($_POST['overlay_style'] ?? '', ['semi-transparent','blur','solid','none']) ? $_POST['overlay_style'] : 'semi-transparent',
            'max_ads_per_session_per_minute' => max(1, min(60, (int)($_POST['max_ads_per_session_per_minute'] ?? 5))),
            'require_campaign_approval' => isset($_POST['require_campaign_approval']) ? '1' : '0',
            'default_currency' => strtoupper(substr(trim($_POST['default_currency'] ?? 'USD'), 0, 3)),
            'tax_rate_percent' => max(0, min(100, (float)($_POST['tax_rate_percent'] ?? 0))),
            'geo_provider' => in_array($_POST['geo_provider'] ?? '', ['ip-api','none']) ? $_POST['geo_provider'] : 'ip-api',
            'platform_name' => trim($_POST['platform_name'] ?? 'Pause Ads'),
            'invoice_prefix' => strtoupper(substr(trim($_POST['invoice_prefix'] ?? 'PA'), 0, 5)),
            'drop_tables_on_uninstall' => isset($_POST['drop_tables_on_uninstall']) ? '1' : '0',
        ];
        foreach ($settings as $k => $v) pause_ads_set_setting($k, (string)$v);
        $msg = 'Settings saved.';
    }
}

$s = [];
foreach (['enabled','min_pause_ms','overlay_position','overlay_style','max_ads_per_session_per_minute',
          'require_campaign_approval','default_currency','tax_rate_percent','geo_provider','platform_name',
          'invoice_prefix','drop_tables_on_uninstall'] as $k) {
    $s[$k] = pause_ads_get_setting($k, '');
}
if (!$s['enabled']) $s['enabled'] = '1';
if (!$s['min_pause_ms']) $s['min_pause_ms'] = '1000';
if (!$s['default_currency']) $s['default_currency'] = 'USD';
if (!$s['platform_name']) $s['platform_name'] = 'Pause Ads';
if (!$s['invoice_prefix']) $s['invoice_prefix'] = 'PA';

pause_ads_admin_header('Settings', 'settings');
?>

<?php if ($msg): ?><div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($err); ?></div><?php endif; ?>

<form method="post">
<?php echo pause_ads_nonce_field('pa_settings'); ?>
<div class="pa-row">
<div class="pa-col">
    <div class="pa-card">
        <h3>General</h3>
        <div class="pa-form-group"><label><input type="checkbox" name="enabled" value="1" <?php echo $s['enabled']==='1'?'checked':''; ?>> Enable Pause Ads Plugin</label></div>
        <div class="pa-form-group"><label>Min Pause Duration (ms)</label><input type="number" name="min_pause_ms" class="pa-input" value="<?php echo (int)$s['min_pause_ms']; ?>" min="100" max="10000"></div>
        <div class="pa-form-group"><label>Max Ad Requests/Session/Min</label><input type="number" name="max_ads_per_session_per_minute" class="pa-input" value="<?php echo (int)$s['max_ads_per_session_per_minute']; ?>" min="1" max="60"></div>
        <div class="pa-form-group"><label><input type="checkbox" name="require_campaign_approval" value="1" <?php echo $s['require_campaign_approval']==='1'?'checked':''; ?>> Require admin approval for campaigns</label><div class="hint">If checked, paid campaigns enter "Pending Review" instead of going active immediately.</div></div>
    </div>

    <div class="pa-card">
        <h3>Overlay Appearance</h3>
        <div class="pa-form-group"><label>Position</label><select name="overlay_position" class="pa-input">
            <?php foreach (['center'=>'Center','bottom-center'=>'Bottom Center','bottom-right'=>'Bottom Right','bottom-left'=>'Bottom Left','top-right'=>'Top Right','top-left'=>'Top Left'] as $pk=>$pl): ?>
                <option value="<?php echo $pk; ?>" <?php echo $s['overlay_position']===$pk?'selected':''; ?>><?php echo $pl; ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="pa-form-group"><label>Background Style</label><select name="overlay_style" class="pa-input">
            <?php foreach (['semi-transparent'=>'Semi-transparent','blur'=>'Blur','solid'=>'Solid dark','none'=>'None'] as $sk=>$sl): ?>
                <option value="<?php echo $sk; ?>" <?php echo $s['overlay_style']===$sk?'selected':''; ?>><?php echo $sl; ?></option>
            <?php endforeach; ?>
        </select></div>
    </div>
</div>

<div class="pa-col">
    <div class="pa-card">
        <h3>Billing & Currency</h3>
        <div class="pa-form-group"><label>Default Currency</label><input type="text" name="default_currency" class="pa-input" value="<?php echo pause_ads_esc($s['default_currency']); ?>" maxlength="3"></div>
        <div class="pa-form-group"><label>Tax Rate (%)</label><input type="number" name="tax_rate_percent" class="pa-input" step="0.01" min="0" max="100" value="<?php echo (float)$s['tax_rate_percent']; ?>"><div class="hint">Applied on all package purchases.</div></div>
        <div class="pa-form-group"><label>Platform Name</label><input type="text" name="platform_name" class="pa-input" value="<?php echo pause_ads_esc($s['platform_name']); ?>"><div class="hint">Shown on invoices.</div></div>
        <div class="pa-form-group"><label>Invoice Prefix</label><input type="text" name="invoice_prefix" class="pa-input" value="<?php echo pause_ads_esc($s['invoice_prefix']); ?>" maxlength="5"></div>
    </div>

    <div class="pa-card">
        <h3>Geography</h3>
        <div class="pa-form-group"><label>Geo Provider</label><select name="geo_provider" class="pa-input">
            <option value="ip-api" <?php echo $s['geo_provider']==='ip-api'?'selected':''; ?>>ip-api.com (free, 45 req/min)</option>
            <option value="none" <?php echo $s['geo_provider']==='none'?'selected':''; ?>>Disabled</option>
        </select><div class="hint">Used for geo targeting and reporting.</div></div>
    </div>

    <div class="pa-card">
        <h3>Advanced</h3>
        <div class="pa-form-group"><label><input type="checkbox" name="drop_tables_on_uninstall" value="1" <?php echo $s['drop_tables_on_uninstall']==='1'?'checked':''; ?>> Drop all tables on uninstall</label>
        <div class="hint" style="color:#dc3545;"><strong>Warning:</strong> Permanently deletes all data.</div></div>
        <p style="font-size:13px;color:#6c757d;">Plugin v<?php echo PAUSE_ADS_VERSION; ?> | DB prefix: <?php echo pause_ads_esc(pause_ads_tbl_prefix()); ?></p>
    </div>

    <button type="submit" name="save_settings" class="pa-btn pa-btn-primary" style="width:100%;">Save Settings</button>
</div>
</div>
</form>

<?php pause_ads_admin_footer(); ?>
