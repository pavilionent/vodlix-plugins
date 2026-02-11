<?php
/**
 * Pause Ads v2.0 - Manual Watch Page Integration Template
 *
 * Include this in your watch page template if hooks don't auto-inject.
 *
 * Usage (PHP template):
 *   <?php include '/path/to/plugins/pause_ads/templates/watch_page_include.php'; ?>
 *
 * @package PauseAds
 */

if (!defined('STARTER') && !isset($video_id)) return;

if (!function_exists('pause_ads_is_enabled')) {
    $plugin_main = dirname(dirname(__FILE__)) . '/main.php';
    if (file_exists($plugin_main)) {
        if (!defined('STARTER')) define('STARTER', true);
        require_once $plugin_main;
    } else return;
}

$_pa_vid = 0;
if (isset($video_id) && $video_id > 0) $_pa_vid = (int)$video_id;
elseif (isset($vdo['videoid'])) $_pa_vid = (int)$vdo['videoid'];
elseif (isset($vdo['videoId'])) $_pa_vid = (int)$vdo['videoId'];
elseif (isset($_GET['v'])) $_pa_vid = (int)$_GET['v'];
elseif (isset($_GET['vid'])) $_pa_vid = (int)$_GET['vid'];

if ($_pa_vid <= 0 || !pause_ads_is_enabled() || !pause_ads_is_avod($_pa_vid)) return;

$_pa_base = pause_ads_get_base_url();
$_pa_sid = pause_ads_get_session_id();
?>
<link rel="stylesheet" href="<?php echo $_pa_base.'/'.PAUSE_ADS_ASSETS_URL; ?>/pause_ads.css?v=<?php echo PAUSE_ADS_VERSION; ?>">
<script>
window.PauseAdsConfig=<?php echo json_encode([
    'videoId'=>$_pa_vid,'sessionId'=>$_pa_sid,
    'minPauseMs'=>(int)pause_ads_get_setting('min_pause_ms','1000'),
    'overlayPosition'=>pause_ads_get_setting('overlay_position','center'),
    'getAdUrl'=>$_pa_base.'/'.PAUSE_ADS_AJAX_URL.'/get_ad.php',
    'trackImpressionUrl'=>$_pa_base.'/'.PAUSE_ADS_AJAX_URL.'/track_impression.php',
    'trackClickUrl'=>$_pa_base.'/'.PAUSE_ADS_AJAX_URL.'/track_click.php',
    'baseUrl'=>$_pa_base,
]); ?>;
</script>
<script src="<?php echo $_pa_base.'/'.PAUSE_ADS_ASSETS_URL; ?>/pause_ads.js?v=<?php echo PAUSE_ADS_VERSION; ?>" defer></script>
