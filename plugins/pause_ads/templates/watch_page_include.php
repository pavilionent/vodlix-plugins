<?php
/**
 * Pause Ads Plugin - Manual Watch Page Integration Template
 *
 * If the ClipBucket hooks do not inject the pause ads assets automatically,
 * include this file in your watch page template (e.g., watch_video.html or
 * the Smarty/Twig template for the player page).
 *
 * Usage in a PHP template:
 *   <?php include '/path/to/plugins/pause_ads/templates/watch_page_include.php'; ?>
 *
 * Usage in a Smarty template:
 *   {include file="../../plugins/pause_ads/templates/watch_page_include.php"}
 *
 * Make sure the variable $video_id (or $vdo['videoid']) is available
 * in the scope where this file is included.
 *
 * @package PauseAds
 */

// Prevent direct access
if (!defined('STARTER') && !isset($video_id)) {
    return;
}

// Bootstrap the plugin if not already loaded
if (!function_exists('pause_ads_is_enabled')) {
    $plugin_main = dirname(dirname(__FILE__)) . '/main.php';
    if (file_exists($plugin_main)) {
        if (!defined('STARTER')) {
            define('STARTER', true);
        }
        require_once $plugin_main;
    } else {
        return;
    }
}

// Determine video ID
$_pa_video_id = 0;

if (isset($video_id) && $video_id > 0) {
    $_pa_video_id = (int) $video_id;
} elseif (isset($vdo) && is_array($vdo)) {
    if (!empty($vdo['videoid'])) {
        $_pa_video_id = (int) $vdo['videoid'];
    } elseif (!empty($vdo['videoId'])) {
        $_pa_video_id = (int) $vdo['videoId'];
    } elseif (!empty($vdo['id'])) {
        $_pa_video_id = (int) $vdo['id'];
    }
} elseif (isset($_GET['v'])) {
    $_pa_video_id = (int) $_GET['v'];
} elseif (isset($_GET['vid'])) {
    $_pa_video_id = (int) $_GET['vid'];
}

// Only proceed if we have a valid video ID and the plugin is enabled
if ($_pa_video_id <= 0 || !pause_ads_is_enabled() || !pause_ads_is_avod($_pa_video_id)) {
    return;
}

// Get configuration
$_pa_session_id = pause_ads_get_session_id();
$_pa_min_pause = (int) pause_ads_get_setting('min_pause_ms', '1000');
$_pa_position = pause_ads_get_setting('overlay_position', 'center');
$_pa_base_url = pause_ads_get_base_url();
?>

<!-- Pause Ads Plugin - Assets -->
<link rel="stylesheet" href="<?php echo $_pa_base_url . '/' . PAUSE_ADS_ASSETS_URL; ?>/pause_ads.css?v=<?php echo PAUSE_ADS_VERSION; ?>">
<script>
window.PauseAdsConfig = <?php echo json_encode([
    'videoId'          => $_pa_video_id,
    'sessionId'        => $_pa_session_id,
    'minPauseMs'       => $_pa_min_pause,
    'overlayPosition'  => $_pa_position,
    'getAdUrl'         => $_pa_base_url . '/' . PAUSE_ADS_AJAX_URL . '/get_ad.php',
    'trackImpressionUrl' => $_pa_base_url . '/' . PAUSE_ADS_AJAX_URL . '/track_impression.php',
    'trackClickUrl'    => $_pa_base_url . '/' . PAUSE_ADS_AJAX_URL . '/track_click.php',
    'baseUrl'          => $_pa_base_url,
]); ?>;
</script>
<script src="<?php echo $_pa_base_url . '/' . PAUSE_ADS_ASSETS_URL; ?>/pause_ads.js?v=<?php echo PAUSE_ADS_VERSION; ?>" defer></script>
<!-- /Pause Ads Plugin -->
