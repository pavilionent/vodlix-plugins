<?php
/**
 * Pause Ads Plugin - Core Functions
 *
 * Ad eligibility logic, weighted selection, delivery cap enforcement,
 * AVOD checking, and impression/click recording.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

require_once dirname(__FILE__) . '/constants.php';
require_once dirname(__FILE__) . '/db.php';
require_once dirname(__FILE__) . '/security.php';

// =========================================================================
// AVOD Video Management
// =========================================================================

/**
 * Check if a video is AVOD-enabled.
 *
 * @param int $video_id
 * @return bool
 */
function pause_ads_is_avod($video_id)
{
    $video_id = (int) $video_id;
    if ($video_id <= 0) {
        return false;
    }

    $table = pause_ads_table('pause_ads_avod_videos');
    $row = pause_ads_db_select_one(
        "SELECT `is_avod` FROM `{$table}` WHERE `video_id` = ?",
        'i',
        [$video_id]
    );

    return $row && $row['is_avod'] == 1;
}

/**
 * Set AVOD status for a video.
 *
 * @param int  $video_id
 * @param bool $is_avod
 * @return bool
 */
function pause_ads_set_avod($video_id, $is_avod)
{
    $video_id = (int) $video_id;
    $is_avod = $is_avod ? 1 : 0;
    $table = pause_ads_table('pause_ads_avod_videos');

    return pause_ads_db_execute(
        "INSERT INTO `{$table}` (`video_id`, `is_avod`) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE `is_avod` = VALUES(`is_avod`)",
        'ii',
        [$video_id, $is_avod]
    ) !== false;
}

/**
 * Get video metadata for targeting.
 * Attempts to read from ClipBucket's video table.
 *
 * @param int $video_id
 * @return array|null
 */
function pause_ads_get_video_meta($video_id)
{
    $video_id = (int) $video_id;

    // Try ClipBucket's native video function
    if (function_exists('get_video_details')) {
        $video = get_video_details($video_id);
        if ($video) {
            return [
                'video_id'   => $video_id,
                'category'   => isset($video['category']) ? $video['category'] : '',
                'genre'      => isset($video['genre']) ? $video['genre'] : (isset($video['category']) ? $video['category'] : ''),
                'rating'     => isset($video['rating']) ? $video['rating'] : '',
                'age_rating' => isset($video['age_rating']) ? $video['age_rating'] : '',
                'country'    => isset($video['country']) ? $video['country'] : '',
                'language'   => isset($video['language']) ? $video['language'] : '',
                'tags'       => isset($video['tags']) ? $video['tags'] : '',
            ];
        }
    }

    // Fallback: query the video table directly
    $prefix = pause_ads_tbl_prefix();

    // Try common ClipBucket table names
    $tables_to_try = [
        "{$prefix}video",
        "{$prefix}videos",
    ];

    foreach ($tables_to_try as $tbl) {
        $row = pause_ads_db_select_one(
            "SELECT * FROM `{$tbl}` WHERE `videoid` = ? OR `videoId` = ? OR `id` = ? LIMIT 1",
            'iii',
            [$video_id, $video_id, $video_id]
        );

        if ($row) {
            return [
                'video_id'   => $video_id,
                'category'   => isset($row['category']) ? $row['category'] : (isset($row['category_id']) ? $row['category_id'] : ''),
                'genre'      => isset($row['genre']) ? $row['genre'] : (isset($row['category']) ? $row['category'] : ''),
                'rating'     => isset($row['rating']) ? $row['rating'] : '',
                'age_rating' => isset($row['age_rating']) ? $row['age_rating'] : (isset($row['content_rating']) ? $row['content_rating'] : ''),
                'country'    => isset($row['country']) ? $row['country'] : '',
                'language'   => isset($row['language']) ? $row['language'] : (isset($row['lang']) ? $row['lang'] : ''),
                'tags'       => isset($row['tags']) ? $row['tags'] : (isset($row['video_tags']) ? $row['video_tags'] : ''),
            ];
        }
    }

    // Return minimal metadata if video table can't be found
    return [
        'video_id' => $video_id,
        'category' => '',
        'genre'    => '',
        'rating'   => '',
        'age_rating' => '',
        'country'  => '',
        'language' => '',
        'tags'     => '',
    ];
}

// =========================================================================
// Ad Eligibility & Selection
// =========================================================================

/**
 * Find an eligible ad for a given video and context.
 *
 * This is the main entry point for the AJAX get_ad endpoint.
 *
 * @param int    $video_id The video being watched
 * @param string $session_id Viewer session ID
 * @param int|null $user_id Viewer user ID (null if anonymous)
 * @return array|null Ad data or null if no eligible ad
 */
function pause_ads_find_eligible_ad($video_id, $session_id, $user_id = null)
{
    // 1. Check plugin is enabled
    if (!pause_ads_is_enabled()) {
        return null;
    }

    // 2. Check video is AVOD
    if (!pause_ads_is_avod($video_id)) {
        return null;
    }

    // 3. Get video metadata for targeting
    $video_meta = pause_ads_get_video_meta($video_id);

    // 4. Get all active ads within schedule window
    $candidates = pause_ads_get_active_ads();

    if (empty($candidates)) {
        return null;
    }

    // 5. Filter by targeting rules
    $candidates = pause_ads_filter_by_targeting($candidates, $video_meta);

    if (empty($candidates)) {
        return null;
    }

    // 6. Filter by delivery caps
    $candidates = pause_ads_filter_by_caps($candidates, $session_id, $user_id);

    if (empty($candidates)) {
        return null;
    }

    // 7. Select using weighted random with priority groups
    $selected = pause_ads_weighted_select($candidates);

    if (!$selected) {
        return null;
    }

    // 8. Build response
    return [
        'eligible'  => true,
        'ad_id'     => (int) $selected['id'],
        'name'      => $selected['name'],
        'image_url' => PAUSE_ADS_UPLOADS_URL . '/' . $selected['image_path'],
        'click_url' => $selected['click_url'],
        'alt_text'  => $selected['alt_text'],
        'token'     => pause_ads_generate_impression_token($selected['id'], $video_id, $session_id),
    ];
}

/**
 * Get all active ads within their schedule window.
 *
 * Uses a single efficient query.
 *
 * @return array
 */
function pause_ads_get_active_ads()
{
    $ads_table = pause_ads_table('pause_ads_ads');
    $delivery_table = pause_ads_table('pause_ads_delivery');

    $sql = "
        SELECT a.*, d.cap_total_impressions, d.cap_daily_impressions,
               d.cap_hourly_impressions, d.weight, d.frequency_cap_per_user_per_day
        FROM `{$ads_table}` a
        LEFT JOIN `{$delivery_table}` d ON d.ad_id = a.id
        WHERE a.status = 'active'
          AND (a.start_at IS NULL OR a.start_at <= NOW())
          AND (a.end_at IS NULL OR a.end_at >= NOW())
        ORDER BY a.priority DESC
    ";

    return pause_ads_db_select($sql);
}

/**
 * Filter ads by targeting rules against video metadata.
 *
 * An ad with NO targeting rules matches ALL videos.
 * An ad with targeting rules only matches if ALL rules pass.
 *
 * @param array $ads Candidate ads
 * @param array $video_meta Video metadata
 * @return array Filtered ads
 */
function pause_ads_filter_by_targeting($ads, $video_meta)
{
    if (empty($ads)) {
        return [];
    }

    // Batch-load all targeting rules for candidate ads
    $ad_ids = array_column($ads, 'id');
    $targeting_table = pause_ads_table('pause_ads_targeting');

    $placeholders = implode(',', array_fill(0, count($ad_ids), '?'));
    $types = str_repeat('i', count($ad_ids));

    $rules = pause_ads_db_select(
        "SELECT * FROM `{$targeting_table}` WHERE `ad_id` IN ({$placeholders})",
        $types,
        $ad_ids
    );

    // Group rules by ad_id
    $rules_by_ad = [];
    foreach ($rules as $rule) {
        $rules_by_ad[$rule['ad_id']][] = $rule;
    }

    $filtered = [];

    foreach ($ads as $ad) {
        $ad_id = $ad['id'];

        // No targeting rules = matches everything
        if (!isset($rules_by_ad[$ad_id]) || empty($rules_by_ad[$ad_id])) {
            $filtered[] = $ad;
            continue;
        }

        // Group rules by type - within the same type, any match is OK (OR logic)
        // Across different types, all must pass (AND logic)
        $rules_grouped = [];
        foreach ($rules_by_ad[$ad_id] as $rule) {
            $rules_grouped[$rule['rule_type']][] = $rule['rule_value'];
        }

        $passes = true;

        foreach ($rules_grouped as $rule_type => $values) {
            if (!pause_ads_check_targeting_rule($rule_type, $values, $video_meta)) {
                $passes = false;
                break;
            }
        }

        if ($passes) {
            $filtered[] = $ad;
        }
    }

    return $filtered;
}

/**
 * Check a single targeting rule type against video metadata.
 *
 * @param string $rule_type Rule type (genre, category, rating, etc.)
 * @param array  $values Allowed values (OR logic within type)
 * @param array  $video_meta Video metadata
 * @return bool True if the rule passes
 */
function pause_ads_check_targeting_rule($rule_type, $values, $video_meta)
{
    $values_lower = array_map('strtolower', array_map('trim', $values));

    switch ($rule_type) {
        case 'genre':
            $video_genre = strtolower(trim($video_meta['genre']));
            if (empty($video_genre)) {
                return true; // Unknown genre, allow
            }
            // Check if any value matches (genre may be comma-separated)
            $video_genres = array_map('trim', array_map('strtolower', explode(',', $video_genre)));
            return !empty(array_intersect($values_lower, $video_genres));

        case 'category':
            $video_cat = strtolower(trim($video_meta['category']));
            if (empty($video_cat)) {
                return true;
            }
            $video_cats = array_map('trim', array_map('strtolower', explode(',', $video_cat)));
            return !empty(array_intersect($values_lower, $video_cats));

        case 'rating':
            $video_rating = strtolower(trim($video_meta['rating']));
            if (empty($video_rating)) {
                return true;
            }
            return in_array($video_rating, $values_lower);

        case 'age_rating':
            $video_age = strtolower(trim($video_meta['age_rating']));
            if (empty($video_age)) {
                return true;
            }
            return in_array($video_age, $values_lower);

        case 'country':
            $video_country = strtolower(trim($video_meta['country']));
            if (empty($video_country)) {
                return true;
            }
            return in_array($video_country, $values_lower);

        case 'language':
            $video_lang = strtolower(trim($video_meta['language']));
            if (empty($video_lang)) {
                return true;
            }
            return in_array($video_lang, $values_lower);

        case 'min_age':
            // User age targeting - would need user profile
            // For now, if rule requires known age and user doesn't have it, skip
            return true; // Permissive for unknown age

        case 'max_age':
            return true; // Permissive for unknown age

        case 'device':
            // Detect device type from user agent
            $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
            foreach ($values_lower as $device) {
                if ($device === 'mobile' && preg_match('/mobile|android|iphone/i', $ua)) {
                    return true;
                }
                if ($device === 'tablet' && preg_match('/tablet|ipad/i', $ua)) {
                    return true;
                }
                if ($device === 'desktop' && !preg_match('/mobile|android|iphone|tablet|ipad/i', $ua)) {
                    return true;
                }
            }
            return false;

        case 'requires_age_known':
            // If this rule is set and user age is unknown, reject
            $user_id = pause_ads_get_current_user_id();
            if ($values_lower[0] === 'true' || $values_lower[0] === '1') {
                return $user_id !== null; // Only show to logged-in users
            }
            return true;

        default:
            // Unknown rule type - pass by default
            return true;
    }
}

/**
 * Filter ads by delivery caps (total, daily, hourly, frequency).
 *
 * Uses efficient batch queries to check impression counts.
 *
 * @param array  $ads Candidate ads
 * @param string $session_id
 * @param int|null $user_id
 * @return array Filtered ads
 */
function pause_ads_filter_by_caps($ads, $session_id, $user_id = null)
{
    if (empty($ads)) {
        return [];
    }

    $impressions_table = pause_ads_table('pause_ads_impressions');
    $ad_ids = array_column($ads, 'id');

    // Batch: total impressions per ad
    $placeholders = implode(',', array_fill(0, count($ad_ids), '?'));
    $types = str_repeat('i', count($ad_ids));

    $total_counts = pause_ads_db_select(
        "SELECT `ad_id`, COUNT(*) as cnt
         FROM `{$impressions_table}`
         WHERE `ad_id` IN ({$placeholders})
         GROUP BY `ad_id`",
        $types,
        $ad_ids
    );
    $total_map = array_column($total_counts, 'cnt', 'ad_id');

    // Batch: daily impressions per ad (today)
    $daily_counts = pause_ads_db_select(
        "SELECT `ad_id`, COUNT(*) as cnt
         FROM `{$impressions_table}`
         WHERE `ad_id` IN ({$placeholders})
           AND `created_at` >= CURDATE()
         GROUP BY `ad_id`",
        $types,
        $ad_ids
    );
    $daily_map = array_column($daily_counts, 'cnt', 'ad_id');

    // Batch: hourly impressions per ad (last hour)
    $hourly_counts = pause_ads_db_select(
        "SELECT `ad_id`, COUNT(*) as cnt
         FROM `{$impressions_table}`
         WHERE `ad_id` IN ({$placeholders})
           AND `created_at` >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
         GROUP BY `ad_id`",
        $types,
        $ad_ids
    );
    $hourly_map = array_column($hourly_counts, 'cnt', 'ad_id');

    // Frequency cap: user/session per day
    $freq_identifier = $user_id ? $user_id : $session_id;
    $freq_field = $user_id ? 'user_id' : 'session_id';

    $freq_counts = pause_ads_db_select(
        "SELECT `ad_id`, COUNT(*) as cnt
         FROM `{$impressions_table}`
         WHERE `ad_id` IN ({$placeholders})
           AND `{$freq_field}` = ?
           AND `created_at` >= CURDATE()
         GROUP BY `ad_id`",
        $types . 's',
        array_merge($ad_ids, [(string) $freq_identifier])
    );
    $freq_map = array_column($freq_counts, 'cnt', 'ad_id');

    $filtered = [];

    foreach ($ads as $ad) {
        $ad_id = $ad['id'];

        // Check total cap
        if (!empty($ad['cap_total_impressions'])) {
            $total = isset($total_map[$ad_id]) ? (int) $total_map[$ad_id] : 0;
            if ($total >= (int) $ad['cap_total_impressions']) {
                continue;
            }
        }

        // Check daily cap
        if (!empty($ad['cap_daily_impressions'])) {
            $daily = isset($daily_map[$ad_id]) ? (int) $daily_map[$ad_id] : 0;
            if ($daily >= (int) $ad['cap_daily_impressions']) {
                continue;
            }
        }

        // Check hourly cap
        if (!empty($ad['cap_hourly_impressions'])) {
            $hourly = isset($hourly_map[$ad_id]) ? (int) $hourly_map[$ad_id] : 0;
            if ($hourly >= (int) $ad['cap_hourly_impressions']) {
                continue;
            }
        }

        // Check frequency cap per user/session/day
        if (!empty($ad['frequency_cap_per_user_per_day'])) {
            $freq = isset($freq_map[$ad_id]) ? (int) $freq_map[$ad_id] : 0;
            if ($freq >= (int) $ad['frequency_cap_per_user_per_day']) {
                continue;
            }
        }

        $filtered[] = $ad;
    }

    return $filtered;
}

/**
 * Select an ad using priority groups + weighted random selection.
 *
 * Ads are grouped by priority (highest first).
 * Within the highest priority group, a weighted random selection is made.
 *
 * @param array $ads Eligible ads
 * @return array|null Selected ad
 */
function pause_ads_weighted_select($ads)
{
    if (empty($ads)) {
        return null;
    }

    // Group by priority (descending)
    $groups = [];
    foreach ($ads as $ad) {
        $priority = (int) ($ad['priority'] ?? 0);
        $groups[$priority][] = $ad;
    }
    krsort($groups); // Highest priority first

    // Take the highest priority group
    $top_group = reset($groups);

    if (count($top_group) === 1) {
        return $top_group[0];
    }

    // Weighted random selection within the group
    $total_weight = 0;
    foreach ($top_group as $ad) {
        $total_weight += max(1, (int) ($ad['weight'] ?? 1));
    }

    $rand = mt_rand(1, $total_weight);
    $cumulative = 0;

    foreach ($top_group as $ad) {
        $cumulative += max(1, (int) ($ad['weight'] ?? 1));
        if ($rand <= $cumulative) {
            return $ad;
        }
    }

    // Fallback
    return $top_group[array_rand($top_group)];
}

// =========================================================================
// Impression & Click Tracking
// =========================================================================

/**
 * Generate a signed token for impression tracking.
 * Prevents forged impression requests.
 *
 * @param int    $ad_id
 * @param int    $video_id
 * @param string $session_id
 * @return string
 */
function pause_ads_generate_impression_token($ad_id, $video_id, $session_id)
{
    $salt = pause_ads_get_setting('ip_salt', 'fallback_salt');
    $data = implode('|', [$ad_id, $video_id, $session_id, date('YmdH')]);
    return hash_hmac('sha256', $data, $salt);
}

/**
 * Verify an impression tracking token.
 *
 * @param string $token
 * @param int    $ad_id
 * @param int    $video_id
 * @param string $session_id
 * @return bool
 */
function pause_ads_verify_impression_token($token, $ad_id, $video_id, $session_id)
{
    $salt = pause_ads_get_setting('ip_salt', 'fallback_salt');

    // Check current hour and previous hour (token window)
    $data_current = implode('|', [$ad_id, $video_id, $session_id, date('YmdH')]);
    $data_prev = implode('|', [$ad_id, $video_id, $session_id, date('YmdH', strtotime('-1 hour'))]);

    return hash_equals(hash_hmac('sha256', $data_current, $salt), $token)
        || hash_equals(hash_hmac('sha256', $data_prev, $salt), $token);
}

/**
 * Record an ad impression.
 *
 * @param int    $ad_id
 * @param int    $video_id
 * @param string $session_id
 * @param int|null $user_id
 * @param int    $pause_duration_ms
 * @return int|false Impression ID or false
 */
function pause_ads_record_impression($ad_id, $video_id, $session_id, $user_id, $pause_duration_ms = 0)
{
    $table = pause_ads_table('pause_ads_impressions');

    $ip_hash = pause_ads_hash_ip(pause_ads_get_client_ip());
    $ua_hash = pause_ads_hash_ua($_SERVER['HTTP_USER_AGENT'] ?? '');
    $country = ''; // Could integrate GeoIP here

    return pause_ads_db_execute(
        "INSERT INTO `{$table}`
            (`ad_id`, `video_id`, `user_id`, `session_id`, `ip_hash`, `user_agent_hash`, `country`, `pause_duration_ms`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'iiisssi',
        [
            (int) $ad_id,
            (int) $video_id,
            $user_id,
            $session_id,
            $ip_hash,
            $ua_hash,
            $country,
            (int) $pause_duration_ms,
        ]
    );
}

/**
 * Record an ad click.
 *
 * @param int    $ad_id
 * @param int    $video_id
 * @param string $session_id
 * @param int|null $user_id
 * @param int|null $impression_id
 * @return int|false
 */
function pause_ads_record_click($ad_id, $video_id, $session_id, $user_id = null, $impression_id = null)
{
    $table = pause_ads_table('pause_ads_clicks');

    return pause_ads_db_execute(
        "INSERT INTO `{$table}`
            (`ad_id`, `impression_id`, `video_id`, `user_id`, `session_id`)
         VALUES (?, ?, ?, ?, ?)",
        'iiiss',
        [
            (int) $ad_id,
            $impression_id ? (int) $impression_id : null,
            (int) $video_id,
            $user_id,
            $session_id,
        ]
    );
}

// =========================================================================
// Reporting Queries
// =========================================================================

/**
 * Get impressions by day for a date range.
 *
 * @param string $start_date Y-m-d
 * @param string $end_date Y-m-d
 * @return array
 */
function pause_ads_report_impressions_by_day($start_date, $end_date)
{
    $table = pause_ads_table('pause_ads_impressions');

    return pause_ads_db_select(
        "SELECT DATE(`created_at`) as day, COUNT(*) as impressions
         FROM `{$table}`
         WHERE `created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY DATE(`created_at`)
         ORDER BY day ASC",
        'ss',
        [$start_date, $end_date]
    );
}

/**
 * Get impressions by ad for a date range.
 *
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function pause_ads_report_impressions_by_ad($start_date, $end_date)
{
    $impressions_table = pause_ads_table('pause_ads_impressions');
    $ads_table = pause_ads_table('pause_ads_ads');

    return pause_ads_db_select(
        "SELECT i.ad_id, a.name as ad_name, COUNT(*) as impressions
         FROM `{$impressions_table}` i
         LEFT JOIN `{$ads_table}` a ON a.id = i.ad_id
         WHERE i.created_at >= ? AND i.created_at < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY i.ad_id, a.name
         ORDER BY impressions DESC",
        'ss',
        [$start_date, $end_date]
    );
}

/**
 * Get clicks by ad for a date range.
 *
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function pause_ads_report_clicks_by_ad($start_date, $end_date)
{
    $clicks_table = pause_ads_table('pause_ads_clicks');
    $ads_table = pause_ads_table('pause_ads_ads');

    return pause_ads_db_select(
        "SELECT c.ad_id, a.name as ad_name, COUNT(*) as clicks
         FROM `{$clicks_table}` c
         LEFT JOIN `{$ads_table}` a ON a.id = c.ad_id
         WHERE c.created_at >= ? AND c.created_at < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY c.ad_id, a.name
         ORDER BY clicks DESC",
        'ss',
        [$start_date, $end_date]
    );
}

/**
 * Get top videos generating pause ad impressions.
 *
 * @param string $start_date
 * @param string $end_date
 * @param int    $limit
 * @return array
 */
function pause_ads_report_top_videos($start_date, $end_date, $limit = 20)
{
    $table = pause_ads_table('pause_ads_impressions');

    return pause_ads_db_select(
        "SELECT `video_id`, COUNT(*) as impressions
         FROM `{$table}`
         WHERE `created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY `video_id`
         ORDER BY impressions DESC
         LIMIT ?",
        'ssi',
        [$start_date, $end_date, (int) $limit]
    );
}

/**
 * Get summary stats for dashboard.
 *
 * @param string $start_date
 * @param string $end_date
 * @return array
 */
function pause_ads_report_summary($start_date, $end_date)
{
    $impressions_table = pause_ads_table('pause_ads_impressions');
    $clicks_table = pause_ads_table('pause_ads_clicks');
    $ads_table = pause_ads_table('pause_ads_ads');

    $total_impressions = (int) pause_ads_db_scalar(
        "SELECT COUNT(*) FROM `{$impressions_table}` WHERE `created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)",
        'ss',
        [$start_date, $end_date]
    );

    $total_clicks = (int) pause_ads_db_scalar(
        "SELECT COUNT(*) FROM `{$clicks_table}` WHERE `created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)",
        'ss',
        [$start_date, $end_date]
    );

    $active_ads = (int) pause_ads_db_scalar(
        "SELECT COUNT(*) FROM `{$ads_table}` WHERE `status` = 'active'"
    );

    $unique_sessions = (int) pause_ads_db_scalar(
        "SELECT COUNT(DISTINCT `session_id`) FROM `{$impressions_table}` WHERE `created_at` >= ? AND `created_at` < DATE_ADD(?, INTERVAL 1 DAY)",
        'ss',
        [$start_date, $end_date]
    );

    $ctr = $total_impressions > 0 ? round(($total_clicks / $total_impressions) * 100, 2) : 0;

    return [
        'total_impressions' => $total_impressions,
        'total_clicks'      => $total_clicks,
        'active_ads'        => $active_ads,
        'unique_sessions'   => $unique_sessions,
        'ctr'               => $ctr,
    ];
}

// =========================================================================
// Ad CRUD Operations
// =========================================================================

/**
 * Get all ads with delivery info.
 *
 * @param string $status Filter by status (empty = all)
 * @return array
 */
function pause_ads_get_all_ads($status = '')
{
    $ads_table = pause_ads_table('pause_ads_ads');
    $delivery_table = pause_ads_table('pause_ads_delivery');
    $impressions_table = pause_ads_table('pause_ads_impressions');
    $clicks_table = pause_ads_table('pause_ads_clicks');

    $where = '';
    $params = [];
    $types = '';

    if (!empty($status)) {
        $where = 'WHERE a.status = ?';
        $params[] = $status;
        $types = 's';
    }

    return pause_ads_db_select(
        "SELECT a.*,
                d.cap_total_impressions, d.cap_daily_impressions, d.cap_hourly_impressions,
                d.weight, d.frequency_cap_per_user_per_day,
                COALESCE(imp.imp_count, 0) as total_impressions,
                COALESCE(clk.clk_count, 0) as total_clicks
         FROM `{$ads_table}` a
         LEFT JOIN `{$delivery_table}` d ON d.ad_id = a.id
         LEFT JOIN (SELECT ad_id, COUNT(*) as imp_count FROM `{$impressions_table}` GROUP BY ad_id) imp ON imp.ad_id = a.id
         LEFT JOIN (SELECT ad_id, COUNT(*) as clk_count FROM `{$clicks_table}` GROUP BY ad_id) clk ON clk.ad_id = a.id
         {$where}
         ORDER BY a.created_at DESC",
        $types,
        $params
    );
}

/**
 * Get a single ad by ID with all related data.
 *
 * @param int $ad_id
 * @return array|null
 */
function pause_ads_get_ad($ad_id)
{
    $ads_table = pause_ads_table('pause_ads_ads');
    $delivery_table = pause_ads_table('pause_ads_delivery');

    $ad = pause_ads_db_select_one(
        "SELECT a.*, d.cap_total_impressions, d.cap_daily_impressions, d.cap_hourly_impressions,
                d.weight, d.frequency_cap_per_user_per_day
         FROM `{$ads_table}` a
         LEFT JOIN `{$delivery_table}` d ON d.ad_id = a.id
         WHERE a.id = ?",
        'i',
        [$ad_id]
    );

    if ($ad) {
        // Load targeting rules
        $targeting_table = pause_ads_table('pause_ads_targeting');
        $ad['targeting'] = pause_ads_db_select(
            "SELECT * FROM `{$targeting_table}` WHERE `ad_id` = ? ORDER BY `rule_type`",
            'i',
            [$ad_id]
        );
    }

    return $ad;
}

/**
 * Create a new ad.
 *
 * @param array $data Ad data
 * @return int|false New ad ID or false
 */
function pause_ads_create_ad($data)
{
    $table = pause_ads_table('pause_ads_ads');

    $ad_id = pause_ads_db_execute(
        "INSERT INTO `{$table}`
            (`name`, `status`, `image_path`, `image_width`, `image_height`, `click_url`, `alt_text`, `advertiser`, `priority`, `start_at`, `end_at`)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'sssiisissss',
        [
            $data['name'],
            $data['status'] ?? 'paused',
            $data['image_path'] ?? '',
            (int) ($data['image_width'] ?? 0),
            (int) ($data['image_height'] ?? 0),
            $data['click_url'] ?? '',
            $data['alt_text'] ?? '',
            $data['advertiser'] ?? '',
            (int) ($data['priority'] ?? 0),
            !empty($data['start_at']) ? $data['start_at'] : null,
            !empty($data['end_at']) ? $data['end_at'] : null,
        ]
    );

    if ($ad_id) {
        // Create default delivery record
        pause_ads_save_delivery($ad_id, $data);
    }

    return $ad_id;
}

/**
 * Update an existing ad.
 *
 * @param int   $ad_id
 * @param array $data
 * @return bool
 */
function pause_ads_update_ad($ad_id, $data)
{
    $table = pause_ads_table('pause_ads_ads');

    $fields = [];
    $params = [];
    $types = '';

    $allowed = [
        'name' => 's', 'status' => 's', 'image_path' => 's',
        'image_width' => 'i', 'image_height' => 'i',
        'click_url' => 's', 'alt_text' => 's', 'advertiser' => 's',
        'priority' => 'i', 'start_at' => 's', 'end_at' => 's',
    ];

    foreach ($allowed as $field => $type) {
        if (array_key_exists($field, $data)) {
            $fields[] = "`{$field}` = ?";
            $val = $data[$field];
            if (($field === 'start_at' || $field === 'end_at') && empty($val)) {
                $val = null;
            }
            $params[] = $val;
            $types .= $type;
        }
    }

    if (empty($fields)) {
        return false;
    }

    $params[] = (int) $ad_id;
    $types .= 'i';

    $result = pause_ads_db_execute(
        "UPDATE `{$table}` SET " . implode(', ', $fields) . " WHERE `id` = ?",
        $types,
        $params
    );

    // Update delivery
    pause_ads_save_delivery($ad_id, $data);

    return $result !== false;
}

/**
 * Save delivery settings for an ad.
 *
 * @param int   $ad_id
 * @param array $data
 * @return bool
 */
function pause_ads_save_delivery($ad_id, $data)
{
    $table = pause_ads_table('pause_ads_delivery');

    $cap_total = !empty($data['cap_total_impressions']) ? (int) $data['cap_total_impressions'] : null;
    $cap_daily = !empty($data['cap_daily_impressions']) ? (int) $data['cap_daily_impressions'] : null;
    $cap_hourly = !empty($data['cap_hourly_impressions']) ? (int) $data['cap_hourly_impressions'] : null;
    $weight = max(1, (int) ($data['weight'] ?? 1));
    $freq_cap = !empty($data['frequency_cap_per_user_per_day']) ? (int) $data['frequency_cap_per_user_per_day'] : null;

    return pause_ads_db_execute(
        "INSERT INTO `{$table}` (`ad_id`, `cap_total_impressions`, `cap_daily_impressions`, `cap_hourly_impressions`, `weight`, `frequency_cap_per_user_per_day`)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            `cap_total_impressions` = VALUES(`cap_total_impressions`),
            `cap_daily_impressions` = VALUES(`cap_daily_impressions`),
            `cap_hourly_impressions` = VALUES(`cap_hourly_impressions`),
            `weight` = VALUES(`weight`),
            `frequency_cap_per_user_per_day` = VALUES(`frequency_cap_per_user_per_day`)",
        'iiiiii',
        [$ad_id, $cap_total, $cap_daily, $cap_hourly, $weight, $freq_cap]
    ) !== false;
}

/**
 * Save targeting rules for an ad (replaces all existing rules).
 *
 * @param int   $ad_id
 * @param array $rules Array of ['rule_type' => ..., 'rule_value' => ...]
 * @return bool
 */
function pause_ads_save_targeting($ad_id, $rules)
{
    $table = pause_ads_table('pause_ads_targeting');

    // Delete existing rules
    pause_ads_db_execute(
        "DELETE FROM `{$table}` WHERE `ad_id` = ?",
        'i',
        [$ad_id]
    );

    // Insert new rules
    foreach ($rules as $rule) {
        if (empty($rule['rule_type']) || !isset($rule['rule_value'])) {
            continue;
        }

        pause_ads_db_execute(
            "INSERT INTO `{$table}` (`ad_id`, `rule_type`, `rule_value`) VALUES (?, ?, ?)",
            'iss',
            [(int) $ad_id, trim($rule['rule_type']), trim($rule['rule_value'])]
        );
    }

    return true;
}

/**
 * Delete an ad and all related data.
 *
 * @param int $ad_id
 * @return bool
 */
function pause_ads_delete_ad($ad_id)
{
    $ad = pause_ads_get_ad($ad_id);
    if (!$ad) {
        return false;
    }

    // Delete image file
    if (!empty($ad['image_path'])) {
        $file = PAUSE_ADS_UPLOADS_DIR . '/' . $ad['image_path'];
        if (file_exists($file)) {
            unlink($file);
        }
    }

    // Cascading FK will handle targeting and delivery
    $table = pause_ads_table('pause_ads_ads');
    return pause_ads_db_execute(
        "DELETE FROM `{$table}` WHERE `id` = ?",
        'i',
        [$ad_id]
    ) !== false;
}

/**
 * Handle ad image upload.
 *
 * @param array $file $_FILES entry
 * @return array ['success' => bool, 'path' => string, 'error' => string]
 */
function pause_ads_handle_upload($file)
{
    $validation = pause_ads_validate_upload($file);
    if (!$validation['valid']) {
        return ['success' => false, 'path' => '', 'error' => $validation['error']];
    }

    // Generate unique filename
    $ext = $validation['extension'];
    $filename = 'ad_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = PAUSE_ADS_UPLOADS_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['success' => false, 'path' => '', 'error' => 'Failed to move uploaded file.'];
    }

    // Get image dimensions
    $width = 0;
    $height = 0;
    if ($validation['mime'] !== 'image/svg+xml') {
        $info = @getimagesize($dest);
        if ($info) {
            $width = $info[0];
            $height = $info[1];
        }
    }

    return [
        'success' => true,
        'path'    => $filename,
        'width'   => $width,
        'height'  => $height,
        'error'   => null,
    ];
}

// =========================================================================
// Eligibility Test Mode
// =========================================================================

/**
 * Test ad eligibility for a video and return diagnostic info.
 *
 * @param int $video_id
 * @param int|null $ad_id Specific ad to test (null = all)
 * @return array Diagnostic results
 */
function pause_ads_test_eligibility($video_id, $ad_id = null)
{
    $results = [
        'video_id'    => $video_id,
        'is_avod'     => pause_ads_is_avod($video_id),
        'plugin_enabled' => pause_ads_is_enabled(),
        'video_meta'  => pause_ads_get_video_meta($video_id),
        'ads_tested'  => [],
    ];

    if (!$results['is_avod']) {
        $results['reason'] = 'Video is not AVOD-enabled.';
        return $results;
    }

    if (!$results['plugin_enabled']) {
        $results['reason'] = 'Plugin is disabled.';
        return $results;
    }

    $candidates = pause_ads_get_active_ads();

    if ($ad_id) {
        $candidates = array_filter($candidates, function ($a) use ($ad_id) {
            return (int) $a['id'] === (int) $ad_id;
        });
    }

    foreach ($candidates as $ad) {
        $test = [
            'ad_id'   => $ad['id'],
            'name'    => $ad['name'],
            'status'  => $ad['status'],
            'checks'  => [],
        ];

        // Schedule check
        $in_schedule = true;
        if (!empty($ad['start_at']) && strtotime($ad['start_at']) > time()) {
            $in_schedule = false;
        }
        if (!empty($ad['end_at']) && strtotime($ad['end_at']) < time()) {
            $in_schedule = false;
        }
        $test['checks']['schedule'] = $in_schedule ? 'PASS' : 'FAIL (outside schedule window)';

        // Targeting check
        $targeting_pass = !empty(pause_ads_filter_by_targeting([$ad], $results['video_meta']));
        $test['checks']['targeting'] = $targeting_pass ? 'PASS' : 'FAIL (targeting rules do not match video metadata)';

        // Cap check
        $session_id = pause_ads_get_session_id();
        $user_id = pause_ads_get_current_user_id();
        $cap_pass = !empty(pause_ads_filter_by_caps([$ad], $session_id, $user_id));
        $test['checks']['delivery_caps'] = $cap_pass ? 'PASS' : 'FAIL (delivery cap reached)';

        $test['eligible'] = $in_schedule && $targeting_pass && $cap_pass;
        $results['ads_tested'][] = $test;
    }

    return $results;
}

/**
 * Get AVOD video list for admin.
 *
 * @return array
 */
function pause_ads_get_avod_videos()
{
    $avod_table = pause_ads_table('pause_ads_avod_videos');
    $prefix = pause_ads_tbl_prefix();

    // Try to join with video table for names
    $video_tables = ["{$prefix}video", "{$prefix}videos"];

    foreach ($video_tables as $vtable) {
        $check = pause_ads_db_select_one("SELECT 1 FROM `{$vtable}` LIMIT 1");
        if ($check !== null) {
            $results = pause_ads_db_select(
                "SELECT av.video_id, av.is_avod, av.updated_at,
                        COALESCE(v.title, v.video_title, CONCAT('Video #', av.video_id)) as video_title
                 FROM `{$avod_table}` av
                 LEFT JOIN `{$vtable}` v ON (v.videoid = av.video_id OR v.videoId = av.video_id OR v.id = av.video_id)
                 ORDER BY av.updated_at DESC"
            );
            return $results;
        }
    }

    // Fallback: just return AVOD table data
    return pause_ads_db_select(
        "SELECT video_id, is_avod, updated_at, CONCAT('Video #', video_id) as video_title
         FROM `{$avod_table}` ORDER BY updated_at DESC"
    );
}
