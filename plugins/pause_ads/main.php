<?php
/**
 * Pause Ads Plugin v2.0 - Main Bootstrap
 *
 * Registers hooks, loads dependencies, provides admin/advertiser
 * header/footer renderers, and integration helpers.
 *
 * @package PauseAds
 * @version 2.0.0
 */

if (!defined('STARTER')) {
    if (!defined('PAUSE_ADS_DIRECT_ACCESS')) die('No direct access allowed.');
}

require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/models.php';
require_once __DIR__ . '/includes/geo.php';
require_once __DIR__ . '/includes/eligibility.php';
require_once __DIR__ . '/includes/payment.php';

// =========================================================================
// Hook: Inject player assets on watch page
// =========================================================================
function pause_ads_inject_player_assets($data = [])
{
    if (!pause_ads_is_enabled()) return;

    global $vdo, $video;
    $video_id = 0;
    if (isset($vdo['videoid'])) $video_id = (int)$vdo['videoid'];
    elseif (isset($vdo['videoId'])) $video_id = (int)$vdo['videoId'];
    elseif (isset($video['videoid'])) $video_id = (int)$video['videoid'];
    elseif (isset($_GET['v'])) $video_id = (int)$_GET['v'];
    elseif (isset($_GET['vid'])) $video_id = (int)$_GET['vid'];
    if ($video_id <= 0) return;
    if (!pause_ads_is_avod($video_id)) return;

    $base = pause_ads_get_base_url();
    $sid  = pause_ads_get_session_id();
    $mp   = (int)pause_ads_get_setting('min_pause_ms','1000');
    $pos  = pause_ads_get_setting('overlay_position','center');

    echo '<link rel="stylesheet" href="'.$base.'/'.PAUSE_ADS_ASSETS_URL.'/pause_ads.css?v='.PAUSE_ADS_VERSION.'">'."\n";
    echo '<script>window.PauseAdsConfig='.json_encode([
        'videoId'=>$video_id,'sessionId'=>$sid,'minPauseMs'=>$mp,'overlayPosition'=>$pos,
        'getAdUrl'=>$base.'/'.PAUSE_ADS_AJAX_URL.'/get_ad.php',
        'trackImpressionUrl'=>$base.'/'.PAUSE_ADS_AJAX_URL.'/track_impression.php',
        'trackClickUrl'=>$base.'/'.PAUSE_ADS_AJAX_URL.'/track_click.php',
        'baseUrl'=>$base,
    ]).';</script>'."\n";
    echo '<script src="'.$base.'/'.PAUSE_ADS_ASSETS_URL.'/pause_ads.js?v='.PAUSE_ADS_VERSION.'" defer></script>'."\n";
}

function pause_ads_enqueue_header($data = [])
{
    if (!pause_ads_is_watch_page() || !pause_ads_is_enabled()) return;
    $base = pause_ads_get_base_url();
    echo '<link rel="stylesheet" href="'.$base.'/'.PAUSE_ADS_ASSETS_URL.'/pause_ads.css?v='.PAUSE_ADS_VERSION.'">'."\n";
}

function pause_ads_enqueue_footer($data = [])
{
    if (!pause_ads_is_watch_page()) return;
    pause_ads_inject_player_assets($data);
}

// =========================================================================
// Hook: Admin left menu
// =========================================================================
function pause_ads_admin_menu($data = [])
{
    $u = function($p) { return pause_ads_admin_url($p); };
    echo '
    <li class="nav-item"><a class="nav-link disabled" href="#"><i class="fa fa-pause-circle"></i> <strong>Pause Ads</strong></a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('dashboard.php').'"><i class="fa fa-chart-bar"></i> Dashboard</a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('campaigns.php').'"><i class="fa fa-bullhorn"></i> Campaigns</a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('companies.php').'"><i class="fa fa-building"></i> Companies</a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('packages.php').'"><i class="fa fa-box"></i> Packages</a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('transactions.php').'"><i class="fa fa-receipt"></i> Transactions</a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('avod_videos.php').'"><i class="fa fa-video"></i> AVOD Videos</a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('test_mode.php').'"><i class="fa fa-flask"></i> Test Mode</a></li>
    <li class="nav-item"><a class="nav-link" href="'.$u('settings.php').'"><i class="fa fa-cog"></i> Settings</a></li>';
    return $data;
}

// =========================================================================
// Hook: AVOD toggle on video edit
// =========================================================================
function pause_ads_video_edit_avod_toggle($data = [])
{
    $vid = (int)($data['videoid'] ?? ($data['videoId'] ?? ($data['id'] ?? ($_GET['vid'] ?? 0))));
    $checked = ($vid > 0 && pause_ads_is_avod($vid)) ? 'checked' : '';
    echo '<div class="form-group row"><label class="col-sm-3 col-form-label">Pause Ads (AVOD)</label>
    <div class="col-sm-9"><div class="custom-control custom-switch">
    <input type="checkbox" class="custom-control-input" id="pause_ads_avod" name="pause_ads_avod" value="1" '.$checked.'>
    <label class="custom-control-label" for="pause_ads_avod">Enable AVOD pause ads for this video</label>
    </div></div></div>';
}

function pause_ads_video_edit_avod_save($data = [])
{
    $vid = (int)($data['videoid'] ?? ($data['videoId'] ?? ($data['id'] ?? 0)));
    if ($vid > 0) pause_ads_set_avod($vid, isset($_POST['pause_ads_avod']) && $_POST['pause_ads_avod'] == 1);
}

// =========================================================================
// Helpers
// =========================================================================
function pause_ads_is_watch_page()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    foreach (['/watch','/video/','/player/','watch_video.php','view_video.php'] as $p) {
        if (stripos($uri,$p) !== false) return true;
    }
    return isset($_GET['v']) || isset($_GET['vid']) || isset($_GET['videoid']);
}

function pause_ads_get_base_url()
{
    if (defined('BASEURL')) return rtrim(BASEURL,'/');
    if (function_exists('base_url')) return rtrim(base_url(),'/');
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https' : 'http';
    return $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function pause_ads_admin_url($page)
{
    return pause_ads_get_base_url() . '/admin_area/plugin.php?page=pause_ads/admin/' . $page;
}

function pause_ads_advertiser_url($page)
{
    return pause_ads_get_base_url() . '/' . PAUSE_ADS_ADVERTISER_URL . '/' . $page;
}

// =========================================================================
// Shared UI header/footer (admin + advertiser)
// =========================================================================
function pause_ads_admin_header($title, $active = '')
{
    ?>
    <div class="pause-ads-admin">
    <style>
    .pause-ads-admin{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:1400px;margin:0 auto;padding:15px}
    .pause-ads-admin .pa-header{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff;padding:20px 25px;border-radius:8px 8px 0 0}
    .pause-ads-admin .pa-header h2{margin:0;font-size:24px;font-weight:600}
    .pause-ads-admin .pa-header p{margin:5px 0 0;opacity:.85;font-size:14px}
    .pause-ads-admin .pa-nav{background:#f8f9fa;border:1px solid #dee2e6;border-top:0;padding:0;margin-bottom:20px;border-radius:0 0 8px 8px;overflow-x:auto;white-space:nowrap}
    .pause-ads-admin .pa-nav a{display:inline-block;padding:12px 18px;text-decoration:none;color:#495057;font-size:13px;font-weight:500;border-bottom:3px solid transparent;transition:.2s}
    .pause-ads-admin .pa-nav a:hover{background:#e9ecef;color:#212529}
    .pause-ads-admin .pa-nav a.active{color:#667eea;border-bottom-color:#667eea;background:#fff}
    .pause-ads-admin .pa-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
    .pause-ads-admin .pa-card h3{margin:0 0 15px;font-size:18px;font-weight:600}
    .pause-ads-admin table.pa-table{width:100%;border-collapse:collapse}
    .pause-ads-admin table.pa-table th{background:#f8f9fa;padding:10px 12px;text-align:left;font-size:13px;font-weight:600;color:#495057;border-bottom:2px solid #dee2e6}
    .pause-ads-admin table.pa-table td{padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;vertical-align:middle}
    .pause-ads-admin table.pa-table tr:hover td{background:#f8f9fa}
    .pause-ads-admin .pa-btn{display:inline-block;padding:8px 16px;border-radius:6px;font-size:14px;font-weight:500;text-decoration:none;cursor:pointer;border:none;transition:.2s}
    .pause-ads-admin .pa-btn-primary{background:#667eea;color:#fff}
    .pause-ads-admin .pa-btn-primary:hover{background:#5a6fd6;color:#fff}
    .pause-ads-admin .pa-btn-success{background:#28a745;color:#fff}
    .pause-ads-admin .pa-btn-success:hover{background:#218838;color:#fff}
    .pause-ads-admin .pa-btn-danger{background:#dc3545;color:#fff}
    .pause-ads-admin .pa-btn-danger:hover{background:#c82333;color:#fff}
    .pause-ads-admin .pa-btn-warning{background:#ffc107;color:#212529}
    .pause-ads-admin .pa-btn-sm{padding:4px 10px;font-size:12px}
    .pause-ads-admin .pa-badge{display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase}
    .pause-ads-admin .pa-badge-active,.pause-ads-admin .pa-badge-paid{background:#d4edda;color:#155724}
    .pause-ads-admin .pa-badge-paused,.pause-ads-admin .pa-badge-pending,.pause-ads-admin .pa-badge-pending_payment,.pause-ads-admin .pa-badge-pending_review{background:#fff3cd;color:#856404}
    .pause-ads-admin .pa-badge-ended,.pause-ads-admin .pa-badge-archived,.pause-ads-admin .pa-badge-rejected,.pause-ads-admin .pa-badge-failed{background:#f8d7da;color:#721c24}
    .pause-ads-admin .pa-badge-draft{background:#e2e3e5;color:#383d41}
    .pause-ads-admin .pa-badge-inactive{background:#e2e3e5;color:#383d41}
    .pause-ads-admin .pa-form-group{margin-bottom:15px}
    .pause-ads-admin .pa-form-group label{display:block;font-weight:600;font-size:14px;margin-bottom:5px}
    .pause-ads-admin .pa-form-group .hint{font-size:12px;color:#6c757d;margin-top:3px}
    .pause-ads-admin .pa-input{width:100%;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:14px;box-sizing:border-box;transition:border-color .2s}
    .pause-ads-admin .pa-input:focus{border-color:#667eea;outline:0;box-shadow:0 0 0 3px rgba(102,126,234,.15)}
    .pause-ads-admin select.pa-input{appearance:auto}
    .pause-ads-admin .pa-alert{padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px}
    .pause-ads-admin .pa-alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .pause-ads-admin .pa-alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    .pause-ads-admin .pa-alert-info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb}
    .pause-ads-admin .pa-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:15px;margin-bottom:20px}
    .pause-ads-admin .pa-stat-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px;text-align:center}
    .pause-ads-admin .pa-stat-card .number{font-size:28px;font-weight:700;color:#667eea}
    .pause-ads-admin .pa-stat-card .label{font-size:12px;color:#6c757d;margin-top:4px}
    .pause-ads-admin .pa-row{display:flex;gap:15px;flex-wrap:wrap}
    .pause-ads-admin .pa-col{flex:1;min-width:200px}
    </style>
    <div class="pa-header">
        <h2><?php echo pause_ads_esc($title); ?></h2>
        <p>Pause Ads Platform v<?php echo PAUSE_ADS_VERSION; ?></p>
    </div>
    <div class="pa-nav">
    <?php
    $tabs = [
        'dashboard'=>['Dashboard','dashboard'], 'campaigns'=>['Campaigns','campaigns'],
        'companies'=>['Companies','companies'], 'packages'=>['Packages','packages'],
        'transactions'=>['Transactions','transactions'], 'avod'=>['AVOD','avod_videos'],
        'test'=>['Test','test_mode'], 'settings'=>['Settings','settings'],
    ];
    foreach ($tabs as $key=>$t):
        $cls = $active === $key ? 'active' : '';
    ?>
        <a href="<?php echo pause_ads_admin_url($t[1].'.php'); ?>" class="<?php echo $cls; ?>"><?php echo $t[0]; ?></a>
    <?php endforeach; ?>
    </div>
    <?php
}

function pause_ads_admin_footer() { echo '</div>'; }

/**
 * Advertiser portal header.
 */
function pause_ads_advertiser_header($title, $active = '', $company_id = 0)
{
    ?>
    <div class="pause-ads-admin">
    <style>
    .pause-ads-admin{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:1200px;margin:0 auto;padding:15px}
    .pause-ads-admin .pa-header{background:linear-gradient(135deg,#2c3e50 0%,#3498db 100%);color:#fff;padding:20px 25px;border-radius:8px 8px 0 0}
    .pause-ads-admin .pa-header h2{margin:0;font-size:22px;font-weight:600}
    .pause-ads-admin .pa-header p{margin:5px 0 0;opacity:.85;font-size:13px}
    .pause-ads-admin .pa-nav{background:#f8f9fa;border:1px solid #dee2e6;border-top:0;padding:0;margin-bottom:20px;border-radius:0 0 8px 8px;overflow-x:auto;white-space:nowrap}
    .pause-ads-admin .pa-nav a{display:inline-block;padding:11px 16px;text-decoration:none;color:#495057;font-size:13px;font-weight:500;border-bottom:3px solid transparent;transition:.2s}
    .pause-ads-admin .pa-nav a:hover{background:#e9ecef;color:#212529}
    .pause-ads-admin .pa-nav a.active{color:#3498db;border-bottom-color:#3498db;background:#fff}
    .pause-ads-admin .pa-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:20px;margin-bottom:20px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
    .pause-ads-admin .pa-card h3{margin:0 0 15px;font-size:18px;font-weight:600}
    .pause-ads-admin table.pa-table{width:100%;border-collapse:collapse}
    .pause-ads-admin table.pa-table th{background:#f8f9fa;padding:10px 12px;text-align:left;font-size:13px;font-weight:600;border-bottom:2px solid #dee2e6}
    .pause-ads-admin table.pa-table td{padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;vertical-align:middle}
    .pause-ads-admin .pa-btn{display:inline-block;padding:8px 16px;border-radius:6px;font-size:14px;font-weight:500;text-decoration:none;cursor:pointer;border:none;transition:.2s}
    .pause-ads-admin .pa-btn-primary{background:#3498db;color:#fff}.pause-ads-admin .pa-btn-primary:hover{background:#2980b9;color:#fff}
    .pause-ads-admin .pa-btn-success{background:#28a745;color:#fff}.pause-ads-admin .pa-btn-success:hover{background:#218838;color:#fff}
    .pause-ads-admin .pa-btn-danger{background:#dc3545;color:#fff}
    .pause-ads-admin .pa-btn-sm{padding:4px 10px;font-size:12px}
    .pause-ads-admin .pa-badge{display:inline-block;padding:3px 8px;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase}
    .pause-ads-admin .pa-badge-active,.pause-ads-admin .pa-badge-paid{background:#d4edda;color:#155724}
    .pause-ads-admin .pa-badge-paused,.pause-ads-admin .pa-badge-pending,.pause-ads-admin .pa-badge-pending_payment,.pause-ads-admin .pa-badge-pending_review{background:#fff3cd;color:#856404}
    .pause-ads-admin .pa-badge-ended,.pause-ads-admin .pa-badge-rejected,.pause-ads-admin .pa-badge-failed{background:#f8d7da;color:#721c24}
    .pause-ads-admin .pa-badge-draft,.pause-ads-admin .pa-badge-inactive{background:#e2e3e5;color:#383d41}
    .pause-ads-admin .pa-form-group{margin-bottom:15px}
    .pause-ads-admin .pa-form-group label{display:block;font-weight:600;font-size:14px;margin-bottom:5px}
    .pause-ads-admin .pa-form-group .hint{font-size:12px;color:#6c757d;margin-top:3px}
    .pause-ads-admin .pa-input{width:100%;padding:8px 12px;border:1px solid #ced4da;border-radius:6px;font-size:14px;box-sizing:border-box}
    .pause-ads-admin .pa-input:focus{border-color:#3498db;outline:0;box-shadow:0 0 0 3px rgba(52,152,219,.15)}
    .pause-ads-admin select.pa-input{appearance:auto}
    .pause-ads-admin .pa-alert{padding:12px 16px;border-radius:6px;margin-bottom:15px;font-size:14px}
    .pause-ads-admin .pa-alert-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
    .pause-ads-admin .pa-alert-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
    .pause-ads-admin .pa-alert-info{background:#d1ecf1;color:#0c5460;border:1px solid #bee5eb}
    .pause-ads-admin .pa-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px}
    .pause-ads-admin .pa-stat-card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:16px;text-align:center}
    .pause-ads-admin .pa-stat-card .number{font-size:26px;font-weight:700;color:#3498db}
    .pause-ads-admin .pa-stat-card .label{font-size:12px;color:#6c757d;margin-top:4px}
    .pause-ads-admin .pa-row{display:flex;gap:15px;flex-wrap:wrap}
    .pause-ads-admin .pa-col{flex:1;min-width:200px}
    </style>
    <div class="pa-header">
        <h2><?php echo pause_ads_esc($title); ?></h2>
        <p>Advertiser Portal<?php if ($company_id) { $co = pa_company_get($company_id); if ($co) echo ' &mdash; ' . pause_ads_esc($co['name']); } ?></p>
    </div>
    <div class="pa-nav">
    <?php
    $cq = $company_id ? '&company_id='.$company_id : '';
    $tabs = [
        'dashboard'=>'Dashboard','campaigns'=>'Campaigns','billing'=>'Billing & Packages',
        'invoices'=>'Invoices','analytics'=>'Analytics','company'=>'Company',
    ];
    foreach ($tabs as $k=>$label):
        $cls = $active === $k ? 'active' : '';
    ?>
        <a href="<?php echo pause_ads_advertiser_url($k.'.php').'?'.$cq; ?>" class="<?php echo $cls; ?>"><?php echo $label; ?></a>
    <?php endforeach; ?>
    </div>
    <?php
}

function pause_ads_advertiser_footer() { echo '</div>'; }
