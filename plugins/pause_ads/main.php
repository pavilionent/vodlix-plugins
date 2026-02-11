<?php
/**
 * Pause Ads Plugin - Main Bootstrap
 *
 * Registers hooks, loads dependencies, and provides
 * integration points with ClipBucket.
 *
 * @package PauseAds
 * @version 1.0.0
 */

if (!defined('STARTER')) {
    // Allow direct access for testing, but set a flag
    if (!defined('PAUSE_ADS_DIRECT_ACCESS')) {
        die('No direct access allowed.');
    }
}

// Load plugin dependencies
require_once dirname(__FILE__) . '/includes/constants.php';
require_once dirname(__FILE__) . '/includes/db.php';
require_once dirname(__FILE__) . '/includes/security.php';
require_once dirname(__FILE__) . '/includes/functions.php';

// =========================================================================
// Hook Callbacks
// =========================================================================

/**
 * Inject pause ads JS and CSS assets on the video watch page.
 * Hook: watch_page_right_side / footer / player_after
 *
 * @param array $data Optional data passed by hook
 */
function pause_ads_inject_player_assets($data = [])
{
    if (!pause_ads_is_enabled()) {
        return;
    }

    // Determine video ID from context
    $video_id = 0;

    // Try ClipBucket global
    global $vdo, $video;

    if (isset($vdo) && is_array($vdo) && !empty($vdo['videoid'])) {
        $video_id = (int) $vdo['videoid'];
    } elseif (isset($vdo) && is_array($vdo) && !empty($vdo['videoId'])) {
        $video_id = (int) $vdo['videoId'];
    } elseif (isset($video) && is_array($video) && !empty($video['videoid'])) {
        $video_id = (int) $video['videoid'];
    } elseif (isset($_GET['v'])) {
        $video_id = (int) $_GET['v'];
    } elseif (isset($_GET['vid'])) {
        $video_id = (int) $_GET['vid'];
    }

    if ($video_id <= 0) {
        return;
    }

    // Check if video is AVOD
    if (!pause_ads_is_avod($video_id)) {
        return;
    }

    // Get base URL
    $base_url = pause_ads_get_base_url();

    // Generate session ID
    $session_id = pause_ads_get_session_id();

    // Settings for JS
    $min_pause_ms = (int) pause_ads_get_setting('min_pause_ms', '1000');
    $overlay_position = pause_ads_get_setting('overlay_position', 'center');

    // Output the CSS link
    echo '<link rel="stylesheet" href="' . $base_url . '/' . PAUSE_ADS_ASSETS_URL . '/pause_ads.css?v=' . PAUSE_ADS_VERSION . '">' . "\n";

    // Output the JS configuration and script
    echo '<script>' . "\n";
    echo 'window.PauseAdsConfig = ' . json_encode([
        'videoId'          => $video_id,
        'sessionId'        => $session_id,
        'minPauseMs'       => $min_pause_ms,
        'overlayPosition'  => $overlay_position,
        'getAdUrl'         => $base_url . '/' . PAUSE_ADS_AJAX_URL . '/get_ad.php',
        'trackImpressionUrl' => $base_url . '/' . PAUSE_ADS_AJAX_URL . '/track_impression.php',
        'trackClickUrl'    => $base_url . '/' . PAUSE_ADS_AJAX_URL . '/track_click.php',
        'baseUrl'          => $base_url,
    ]) . ';' . "\n";
    echo '</script>' . "\n";
    echo '<script src="' . $base_url . '/' . PAUSE_ADS_ASSETS_URL . '/pause_ads.js?v=' . PAUSE_ADS_VERSION . '" defer></script>' . "\n";
}

/**
 * Enqueue assets in the page header.
 * Hook: header
 */
function pause_ads_enqueue_header($data = [])
{
    // Only load CSS on watch pages
    if (!pause_ads_is_watch_page()) {
        return;
    }

    if (!pause_ads_is_enabled()) {
        return;
    }

    $base_url = pause_ads_get_base_url();
    echo '<link rel="stylesheet" href="' . $base_url . '/' . PAUSE_ADS_ASSETS_URL . '/pause_ads.css?v=' . PAUSE_ADS_VERSION . '">' . "\n";
}

/**
 * Enqueue JS in the page footer.
 * Hook: footer
 */
function pause_ads_enqueue_footer($data = [])
{
    // Only load JS on watch pages
    if (!pause_ads_is_watch_page()) {
        return;
    }

    // The main injection is handled by pause_ads_inject_player_assets
    // This hook serves as a fallback
    pause_ads_inject_player_assets($data);
}

/**
 * Add admin menu items for the Pause Ads section.
 * Hook: admin_left_menu
 *
 * @param array $data Menu data
 * @return array Modified menu data
 */
function pause_ads_admin_menu($data = [])
{
    $menu_items = '
    <li class="nav-item pause-ads-menu-header">
        <a class="nav-link disabled" href="#">
            <i class="fa fa-pause-circle"></i> <strong>Pause Ads</strong>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="' . pause_ads_admin_url('reports.php') . '">
            <i class="fa fa-chart-bar"></i> Dashboard &amp; Reports
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="' . pause_ads_admin_url('ads.php') . '">
            <i class="fa fa-image"></i> Manage Ads
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="' . pause_ads_admin_url('avod_videos.php') . '">
            <i class="fa fa-video"></i> AVOD Videos
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="' . pause_ads_admin_url('test_mode.php') . '">
            <i class="fa fa-flask"></i> Test Mode
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" href="' . pause_ads_admin_url('settings.php') . '">
            <i class="fa fa-cog"></i> Settings
        </a>
    </li>';

    echo $menu_items;
    return $data;
}

/**
 * Add AVOD toggle to video edit form.
 * Hook: video_edit_form
 *
 * @param array $data Video data
 */
function pause_ads_video_edit_avod_toggle($data = [])
{
    $video_id = 0;

    if (isset($data['videoid'])) {
        $video_id = (int) $data['videoid'];
    } elseif (isset($data['videoId'])) {
        $video_id = (int) $data['videoId'];
    } elseif (isset($data['id'])) {
        $video_id = (int) $data['id'];
    } elseif (isset($_GET['vid'])) {
        $video_id = (int) $_GET['vid'];
    }

    $is_avod = $video_id > 0 ? pause_ads_is_avod($video_id) : false;
    $checked = $is_avod ? 'checked' : '';

    echo '
    <div class="form-group row">
        <label class="col-sm-3 col-form-label">Pause Ads (AVOD)</label>
        <div class="col-sm-9">
            <div class="custom-control custom-switch">
                <input type="checkbox" class="custom-control-input" id="pause_ads_avod"
                       name="pause_ads_avod" value="1" ' . $checked . '>
                <label class="custom-control-label" for="pause_ads_avod">
                    Enable AVOD pause ads for this video
                </label>
            </div>
            <small class="form-text text-muted">
                When enabled, static ads will be shown when viewers pause this video.
            </small>
        </div>
    </div>';
}

/**
 * Save AVOD toggle on video edit.
 * Hook: video_edit_save
 *
 * @param array $data Video save data
 */
function pause_ads_video_edit_avod_save($data = [])
{
    $video_id = 0;

    if (isset($data['videoid'])) {
        $video_id = (int) $data['videoid'];
    } elseif (isset($data['videoId'])) {
        $video_id = (int) $data['videoId'];
    } elseif (isset($data['id'])) {
        $video_id = (int) $data['id'];
    }

    if ($video_id > 0) {
        $is_avod = isset($_POST['pause_ads_avod']) && $_POST['pause_ads_avod'] == 1;
        pause_ads_set_avod($video_id, $is_avod);
    }
}

// =========================================================================
// Helper Functions
// =========================================================================

/**
 * Check if the current page is a video watch page.
 *
 * @return bool
 */
function pause_ads_is_watch_page()
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // Common ClipBucket watch page patterns
    $watch_patterns = [
        '/watch',
        '/video/',
        '/player/',
        'watch_video.php',
        'view_video.php',
    ];

    foreach ($watch_patterns as $pattern) {
        if (stripos($uri, $pattern) !== false) {
            return true;
        }
    }

    // Check for video ID parameter
    if (isset($_GET['v']) || isset($_GET['vid']) || isset($_GET['videoid'])) {
        return true;
    }

    return false;
}

/**
 * Get the site base URL.
 *
 * @return string
 */
function pause_ads_get_base_url()
{
    // Try ClipBucket's base_url
    if (defined('BASEURL')) {
        return rtrim(BASEURL, '/');
    }

    if (function_exists('base_url')) {
        return rtrim(base_url(), '/');
    }

    // Fallback: construct from server variables
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $protocol . '://' . $host;
}

/**
 * Get admin page URL for a pause_ads page.
 *
 * @param string $page Page filename
 * @return string
 */
function pause_ads_admin_url($page)
{
    $base = pause_ads_get_base_url();

    // ClipBucket admin URL pattern
    return $base . '/admin_area/plugin.php?page=pause_ads/admin/' . $page;
}

/**
 * Render the admin page header with navigation.
 *
 * @param string $title Page title
 * @param string $active Active tab name
 */
function pause_ads_admin_header($title, $active = '')
{
    ?>
    <div class="pause-ads-admin">
        <style>
            .pause-ads-admin { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .pause-ads-admin .pa-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px 25px; border-radius: 8px 8px 0 0; margin-bottom: 0; }
            .pause-ads-admin .pa-header h2 { margin: 0; font-size: 24px; font-weight: 600; }
            .pause-ads-admin .pa-header p { margin: 5px 0 0; opacity: 0.85; font-size: 14px; }
            .pause-ads-admin .pa-nav { background: #f8f9fa; border: 1px solid #dee2e6; border-top: none; padding: 0; margin-bottom: 20px; border-radius: 0 0 8px 8px; overflow: hidden; }
            .pause-ads-admin .pa-nav a { display: inline-block; padding: 12px 20px; text-decoration: none; color: #495057; font-size: 14px; font-weight: 500; border-bottom: 3px solid transparent; transition: all 0.2s; }
            .pause-ads-admin .pa-nav a:hover { background: #e9ecef; color: #212529; }
            .pause-ads-admin .pa-nav a.active { color: #667eea; border-bottom-color: #667eea; background: #fff; }
            .pause-ads-admin .pa-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
            .pause-ads-admin .pa-card h3 { margin: 0 0 15px; font-size: 18px; font-weight: 600; color: #212529; }
            .pause-ads-admin table.pa-table { width: 100%; border-collapse: collapse; }
            .pause-ads-admin table.pa-table th { background: #f8f9fa; padding: 10px 12px; text-align: left; font-size: 13px; font-weight: 600; color: #495057; border-bottom: 2px solid #dee2e6; }
            .pause-ads-admin table.pa-table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; vertical-align: middle; }
            .pause-ads-admin table.pa-table tr:hover td { background: #f8f9fa; }
            .pause-ads-admin .pa-btn { display: inline-block; padding: 8px 16px; border-radius: 6px; font-size: 14px; font-weight: 500; text-decoration: none; cursor: pointer; border: none; transition: all 0.2s; }
            .pause-ads-admin .pa-btn-primary { background: #667eea; color: #fff; }
            .pause-ads-admin .pa-btn-primary:hover { background: #5a6fd6; color: #fff; }
            .pause-ads-admin .pa-btn-success { background: #28a745; color: #fff; }
            .pause-ads-admin .pa-btn-success:hover { background: #218838; color: #fff; }
            .pause-ads-admin .pa-btn-danger { background: #dc3545; color: #fff; }
            .pause-ads-admin .pa-btn-danger:hover { background: #c82333; color: #fff; }
            .pause-ads-admin .pa-btn-sm { padding: 4px 10px; font-size: 12px; }
            .pause-ads-admin .pa-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
            .pause-ads-admin .pa-badge-active { background: #d4edda; color: #155724; }
            .pause-ads-admin .pa-badge-paused { background: #fff3cd; color: #856404; }
            .pause-ads-admin .pa-badge-archived { background: #f8d7da; color: #721c24; }
            .pause-ads-admin .pa-form-group { margin-bottom: 15px; }
            .pause-ads-admin .pa-form-group label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 5px; color: #212529; }
            .pause-ads-admin .pa-form-group .hint { font-size: 12px; color: #6c757d; margin-top: 3px; }
            .pause-ads-admin .pa-input { width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.2s; box-sizing: border-box; }
            .pause-ads-admin .pa-input:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.15); }
            .pause-ads-admin select.pa-input { appearance: auto; }
            .pause-ads-admin .pa-alert { padding: 12px 16px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
            .pause-ads-admin .pa-alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            .pause-ads-admin .pa-alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
            .pause-ads-admin .pa-alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
            .pause-ads-admin .pa-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
            .pause-ads-admin .pa-stat-card { background: #fff; border: 1px solid #dee2e6; border-radius: 8px; padding: 20px; text-align: center; }
            .pause-ads-admin .pa-stat-card .number { font-size: 32px; font-weight: 700; color: #667eea; }
            .pause-ads-admin .pa-stat-card .label { font-size: 13px; color: #6c757d; margin-top: 5px; }
            .pause-ads-admin .pa-row { display: flex; gap: 15px; flex-wrap: wrap; }
            .pause-ads-admin .pa-col { flex: 1; min-width: 200px; }
        </style>

        <div class="pa-header">
            <h2><i class="fa fa-pause-circle"></i> <?php echo htmlspecialchars($title); ?></h2>
            <p>Pause Ads Platform v<?php echo PAUSE_ADS_VERSION; ?></p>
        </div>

        <div class="pa-nav">
            <a href="<?php echo pause_ads_admin_url('reports.php'); ?>" class="<?php echo $active === 'reports' ? 'active' : ''; ?>">
                <i class="fa fa-chart-bar"></i> Dashboard
            </a>
            <a href="<?php echo pause_ads_admin_url('ads.php'); ?>" class="<?php echo $active === 'ads' ? 'active' : ''; ?>">
                <i class="fa fa-image"></i> Ads
            </a>
            <a href="<?php echo pause_ads_admin_url('avod_videos.php'); ?>" class="<?php echo $active === 'avod' ? 'active' : ''; ?>">
                <i class="fa fa-video"></i> AVOD Videos
            </a>
            <a href="<?php echo pause_ads_admin_url('test_mode.php'); ?>" class="<?php echo $active === 'test' ? 'active' : ''; ?>">
                <i class="fa fa-flask"></i> Test
            </a>
            <a href="<?php echo pause_ads_admin_url('settings.php'); ?>" class="<?php echo $active === 'settings' ? 'active' : ''; ?>">
                <i class="fa fa-cog"></i> Settings
            </a>
        </div>
    <?php
}

/**
 * Render the admin page footer.
 */
function pause_ads_admin_footer()
{
    echo '</div><!-- .pause-ads-admin -->';
}
