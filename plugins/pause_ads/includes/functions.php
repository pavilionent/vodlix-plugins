<?php
/**
 * Pause Ads Plugin v2.0 - Core Functions
 *
 * AVOD helpers, video meta, upload handling, impression token,
 * and shared reporting queries.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) { die('No direct access allowed.'); }

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// =========================================================================
// AVOD
// =========================================================================

function pause_ads_is_avod($video_id)
{
    $video_id = (int)$video_id;
    if ($video_id <= 0) return false;
    $t = pause_ads_table('pause_ads_avod_videos');
    $r = pause_ads_db_select_one("SELECT `is_avod` FROM `{$t}` WHERE `video_id`=?",'i',[$video_id]);
    return $r && $r['is_avod'] == 1;
}

function pause_ads_set_avod($video_id, $is_avod)
{
    $t = pause_ads_table('pause_ads_avod_videos');
    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`video_id`,`is_avod`) VALUES (?,?) ON DUPLICATE KEY UPDATE `is_avod`=VALUES(`is_avod`)",
        'ii', [(int)$video_id, $is_avod ? 1 : 0]
    ) !== false;
}

function pause_ads_get_avod_videos()
{
    $avod = pause_ads_table('pause_ads_avod_videos');
    $prefix = pause_ads_tbl_prefix();
    foreach (["{$prefix}video", "{$prefix}videos"] as $vt) {
        $chk = @pause_ads_db_select_one("SELECT 1 FROM `{$vt}` LIMIT 1");
        if ($chk !== null) {
            return pause_ads_db_select(
                "SELECT av.video_id, av.is_avod, av.updated_at,
                        COALESCE(v.title, v.video_title, CONCAT('Video #', av.video_id)) as video_title
                 FROM `{$avod}` av
                 LEFT JOIN `{$vt}` v ON (v.videoid=av.video_id OR v.videoId=av.video_id OR v.id=av.video_id)
                 ORDER BY av.updated_at DESC"
            );
        }
    }
    return pause_ads_db_select("SELECT video_id, is_avod, updated_at, CONCAT('Video #', video_id) as video_title FROM `{$avod}` ORDER BY updated_at DESC");
}

// =========================================================================
// Video metadata
// =========================================================================

function pause_ads_get_video_meta($video_id)
{
    $video_id = (int)$video_id;

    if (function_exists('get_video_details')) {
        $v = get_video_details($video_id);
        if ($v) {
            return [
                'video_id'=>$video_id,
                'category'=>$v['category'] ?? '',
                'genre'=>$v['genre'] ?? ($v['category'] ?? ''),
                'rating'=>$v['rating'] ?? '',
                'age_rating'=>$v['age_rating'] ?? '',
                'country'=>$v['country'] ?? '',
                'language'=>$v['language'] ?? '',
                'tags'=>$v['tags'] ?? '',
            ];
        }
    }

    $prefix = pause_ads_tbl_prefix();
    foreach (["{$prefix}video","{$prefix}videos"] as $tbl) {
        $row = @pause_ads_db_select_one("SELECT * FROM `{$tbl}` WHERE `videoid`=? OR `videoId`=? OR `id`=? LIMIT 1",'iii',[$video_id,$video_id,$video_id]);
        if ($row) {
            return [
                'video_id'=>$video_id,
                'category'=>$row['category'] ?? ($row['category_id'] ?? ''),
                'genre'=>$row['genre'] ?? ($row['category'] ?? ''),
                'rating'=>$row['rating'] ?? '',
                'age_rating'=>$row['age_rating'] ?? ($row['content_rating'] ?? ''),
                'country'=>$row['country'] ?? '',
                'language'=>$row['language'] ?? ($row['lang'] ?? ''),
                'tags'=>$row['tags'] ?? ($row['video_tags'] ?? ''),
            ];
        }
    }

    return ['video_id'=>$video_id,'category'=>'','genre'=>'','rating'=>'','age_rating'=>'','country'=>'','language'=>'','tags'=>''];
}

// =========================================================================
// Upload handling
// =========================================================================

function pause_ads_handle_upload($file)
{
    $v = pause_ads_validate_upload($file);
    if (!$v['valid']) return ['success'=>false,'path'=>'','error'=>$v['error']];

    $fn = 'ad_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $v['extension'];
    $dest = PAUSE_ADS_UPLOADS_DIR . '/' . $fn;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success'=>false,'path'=>'','error'=>'Failed to move uploaded file.'];
    }

    $w = 0; $h = 0;
    if ($v['mime'] !== 'image/svg+xml') {
        $info = @getimagesize($dest);
        if ($info) { $w = $info[0]; $h = $info[1]; }
    }

    return ['success'=>true,'path'=>$fn,'width'=>$w,'height'=>$h,'error'=>null];
}

// =========================================================================
// Impression token (HMAC)
// =========================================================================

function pause_ads_generate_impression_token($creative_id, $campaign_id, $video_id, $session_id)
{
    $salt = pause_ads_get_setting('ip_salt', 'fallback');
    $data = implode('|', [$creative_id, $campaign_id, $video_id, $session_id, date('YmdH')]);
    return hash_hmac('sha256', $data, $salt);
}

function pause_ads_verify_impression_token($token, $creative_id, $campaign_id, $video_id, $session_id)
{
    $salt = pause_ads_get_setting('ip_salt', 'fallback');
    foreach ([date('YmdH'), date('YmdH', strtotime('-1 hour'))] as $hour) {
        $data = implode('|', [$creative_id, $campaign_id, $video_id, $session_id, $hour]);
        if (hash_equals(hash_hmac('sha256', $data, $salt), $token)) return true;
    }
    return false;
}

// =========================================================================
// Impression & click recording (v2: campaign-aware)
// =========================================================================

function pause_ads_record_impression($creative_id, $campaign_id, $company_id, $video_id, $session_id, $user_id, $country_code = '', $region_code = '')
{
    $t = pause_ads_table('pause_ads_impressions');
    $ip_hash = pause_ads_hash_ip(pause_ads_get_client_ip());

    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`creative_id`,`campaign_id`,`company_id`,`video_id`,`user_id`,`session_id`,`country_code`,`region_code`,`ip_hash`)
         VALUES (?,?,?,?,?,?,?,?,?)",
        'iiiisssss',
        [(int)$creative_id,(int)$campaign_id,(int)$company_id,(int)$video_id,$user_id?(int)$user_id:0,$session_id,$country_code,$region_code,$ip_hash]
    );
}

function pause_ads_record_click($creative_id, $campaign_id, $company_id, $video_id, $session_id, $user_id = null, $country_code = '')
{
    $t = pause_ads_table('pause_ads_clicks');
    return pause_ads_db_execute(
        "INSERT INTO `{$t}` (`creative_id`,`campaign_id`,`company_id`,`video_id`,`user_id`,`session_id`,`country_code`)
         VALUES (?,?,?,?,?,?,?)",
        'iiiisss',
        [(int)$creative_id,(int)$campaign_id,(int)$company_id,(int)$video_id,$user_id?(int)$user_id:0,$session_id,$country_code]
    );
}

/**
 * After recording an impression, decrement the purchase allocation.
 */
function pause_ads_decrement_impression_allocation($campaign_id)
{
    $t = pause_ads_table('pause_ads_purchases');
    // Decrement the oldest active purchase with remaining impressions
    return pause_ads_db_execute(
        "UPDATE `{$t}` SET `impressions_remaining` = `impressions_remaining` - 1
         WHERE `campaign_id` = ? AND `payment_status` = 'paid' AND `impressions_remaining` > 0
         ORDER BY `created_at` ASC LIMIT 1",
        'i', [$campaign_id]
    );
}

/**
 * Get total remaining impressions for a campaign.
 */
function pause_ads_campaign_remaining_impressions($campaign_id)
{
    $t = pause_ads_table('pause_ads_purchases');
    return (int) pause_ads_db_scalar(
        "SELECT COALESCE(SUM(`impressions_remaining`),0) FROM `{$t}` WHERE `campaign_id`=? AND `payment_status`='paid'",
        'i', [$campaign_id]
    );
}

// =========================================================================
// Reporting helpers
// =========================================================================

function pause_ads_report_impressions_by_day($start, $end, $campaign_id = null, $company_id = null)
{
    $t = pause_ads_table('pause_ads_impressions');
    $where = "`created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)";
    $types = 'ss'; $params = [$start, $end];

    if ($campaign_id) { $where .= " AND `campaign_id`=?"; $types .= 'i'; $params[] = $campaign_id; }
    if ($company_id)  { $where .= " AND `company_id`=?";  $types .= 'i'; $params[] = $company_id; }

    return pause_ads_db_select(
        "SELECT DATE(`created_at`) as day, COUNT(*) as impressions FROM `{$t}` WHERE {$where} GROUP BY DATE(`created_at`) ORDER BY day",
        $types, $params
    );
}

function pause_ads_report_clicks_by_day($start, $end, $campaign_id = null, $company_id = null)
{
    $t = pause_ads_table('pause_ads_clicks');
    $where = "`created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)";
    $types = 'ss'; $params = [$start, $end];

    if ($campaign_id) { $where .= " AND `campaign_id`=?"; $types .= 'i'; $params[] = $campaign_id; }
    if ($company_id)  { $where .= " AND `company_id`=?";  $types .= 'i'; $params[] = $company_id; }

    return pause_ads_db_select(
        "SELECT DATE(`created_at`) as day, COUNT(*) as clicks FROM `{$t}` WHERE {$where} GROUP BY DATE(`created_at`) ORDER BY day",
        $types, $params
    );
}

function pause_ads_report_summary($start, $end, $company_id = null)
{
    $imp_t = pause_ads_table('pause_ads_impressions');
    $clk_t = pause_ads_table('pause_ads_clicks');
    $camp_t = pause_ads_table('pause_ads_campaigns');

    $extra_where = $company_id ? " AND `company_id`=?" : "";
    $extra_type  = $company_id ? "i" : "";
    $extra_param = $company_id ? [$company_id] : [];

    $total_imp = (int) pause_ads_db_scalar(
        "SELECT COUNT(*) FROM `{$imp_t}` WHERE `created_at`>=? AND `created_at`<DATE_ADD(?,INTERVAL 1 DAY) {$extra_where}",
        'ss'.$extra_type, array_merge([$start,$end], $extra_param)
    );

    $total_clk = (int) pause_ads_db_scalar(
        "SELECT COUNT(*) FROM `{$clk_t}` WHERE `created_at`>=? AND `created_at`<DATE_ADD(?,INTERVAL 1 DAY) {$extra_where}",
        'ss'.$extra_type, array_merge([$start,$end], $extra_param)
    );

    $camp_where = $company_id ? " AND `company_id`=?" : "";
    $active_camps = (int) pause_ads_db_scalar(
        "SELECT COUNT(*) FROM `{$camp_t}` WHERE `status`='active' {$camp_where}",
        $extra_type, $extra_param
    );

    $unique_sessions = (int) pause_ads_db_scalar(
        "SELECT COUNT(DISTINCT `session_id`) FROM `{$imp_t}` WHERE `created_at`>=? AND `created_at`<DATE_ADD(?,INTERVAL 1 DAY) {$extra_where}",
        'ss'.$extra_type, array_merge([$start,$end], $extra_param)
    );

    $ctr = $total_imp > 0 ? round(($total_clk / $total_imp) * 100, 2) : 0;

    return compact('total_imp','total_clk','active_camps','unique_sessions','ctr');
}

function pause_ads_report_top_videos($start, $end, $limit = 10, $company_id = null)
{
    $t = pause_ads_table('pause_ads_impressions');
    $where = "`created_at`>=? AND `created_at`<DATE_ADD(?,INTERVAL 1 DAY)";
    $types = 'ss'; $params = [$start,$end];
    if ($company_id) { $where .= " AND `company_id`=?"; $types .= 'i'; $params[] = $company_id; }
    $params[] = (int)$limit; $types .= 'i';

    return pause_ads_db_select(
        "SELECT `video_id`, COUNT(*) as impressions FROM `{$t}` WHERE {$where} GROUP BY `video_id` ORDER BY impressions DESC LIMIT ?",
        $types, $params
    );
}

function pause_ads_report_geo_breakdown($start, $end, $company_id = null)
{
    $t = pause_ads_table('pause_ads_impressions');
    $where = "`created_at`>=? AND `created_at`<DATE_ADD(?,INTERVAL 1 DAY)";
    $types = 'ss'; $params = [$start,$end];
    if ($company_id) { $where .= " AND `company_id`=?"; $types .= 'i'; $params[] = $company_id; }

    return pause_ads_db_select(
        "SELECT `country_code`, COUNT(*) as impressions FROM `{$t}` WHERE {$where} AND `country_code` != '' GROUP BY `country_code` ORDER BY impressions DESC LIMIT 30",
        $types, $params
    );
}
