<?php
/**
 * Pause Ads v2.0 - Eligibility Engine
 *
 * Determines which creative to serve for a given video pause event.
 * Considers: AVOD, campaign status, flight window, targeting (genre,
 * category, rating, country/region, device, language), delivery caps
 * (daily/hourly/frequency), purchased impression budget, and uses
 * weighted random selection within priority groups.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) { die('No direct access allowed.'); }

/**
 * Main entry point: find one eligible creative for a video pause.
 *
 * @param int         $video_id
 * @param string      $session_id
 * @param int|null    $user_id
 * @param string      $country_code  Viewer country
 * @param string      $region_code   Viewer region
 * @return array|null  Creative data or null
 */
function pause_ads_find_eligible($video_id, $session_id, $user_id = null, $country_code = '', $region_code = '')
{
    // 1. Plugin enabled?
    if (!pause_ads_is_enabled()) return null;

    // 2. AVOD?
    if (!pause_ads_is_avod($video_id)) return null;

    // 3. Video metadata
    $meta = pause_ads_get_video_meta($video_id);

    // 4. Auto-end expired campaigns (cheap check, runs at most once/request)
    static $ended = false;
    if (!$ended) { pa_campaigns_auto_end(); $ended = true; }

    // 5. Get candidate campaigns + creatives in one query
    $candidates = pa_eligible_get_candidates();
    if (empty($candidates)) return null;

    // 6. Filter by targeting
    $candidates = pa_eligible_filter_targeting($candidates, $meta, $country_code, $region_code);
    if (empty($candidates)) return null;

    // 7. Filter by delivery caps
    $candidates = pa_eligible_filter_caps($candidates, $session_id, $user_id);
    if (empty($candidates)) return null;

    // 8. Filter by remaining purchased impressions
    $candidates = pa_eligible_filter_budget($candidates);
    if (empty($candidates)) return null;

    // 9. Weighted random selection (priority groups)
    $winner = pa_eligible_weighted_select($candidates);
    if (!$winner) return null;

    // 10. Build response
    return [
        'eligible'     => true,
        'creative_id'  => (int)$winner['creative_id'],
        'campaign_id'  => (int)$winner['campaign_id'],
        'company_id'   => (int)$winner['company_id'],
        'image_url'    => PAUSE_ADS_UPLOADS_URL . '/' . $winner['image_path'],
        'click_url'    => $winner['click_url'],
        'alt_text'     => $winner['alt_text'],
        'token'        => pause_ads_generate_impression_token($winner['creative_id'], $winner['campaign_id'], $video_id, $session_id),
    ];
}

/**
 * Get all active campaigns with active creatives, within flight window.
 * Flattened: one row per creative.
 */
function pa_eligible_get_candidates()
{
    $camp  = pause_ads_table('pause_ads_campaigns');
    $cre   = pause_ads_table('pause_ads_creatives');
    $comp  = pause_ads_table('pause_ads_companies');

    return pause_ads_db_select(
        "SELECT cr.id as creative_id, cr.campaign_id, cr.image_path, cr.click_url, cr.alt_text, cr.weight as creative_weight,
                c.company_id, c.priority as campaign_priority, c.daily_cap, c.hourly_cap, c.freq_cap_per_user_per_day,
                c.flight_start_at, c.flight_end_at
         FROM `{$cre}` cr
         JOIN `{$camp}` c ON c.id = cr.campaign_id
         JOIN `{$comp}` co ON co.id = c.company_id
         WHERE c.status = 'active'
           AND cr.status = 'active'
           AND co.status = 'active'
           AND (c.flight_start_at IS NULL OR c.flight_start_at <= NOW())
           AND (c.flight_end_at   IS NULL OR c.flight_end_at   >= NOW())
         ORDER BY c.priority DESC"
    );
}

/**
 * Filter candidates by targeting rules.
 *
 * Rules are at the campaign level. A campaign with no rules matches all.
 * Same-type rules = OR; cross-type = AND.
 */
function pa_eligible_filter_targeting($candidates, $meta, $country_code, $region_code)
{
    if (empty($candidates)) return [];

    // Get unique campaign IDs
    $camp_ids = array_values(array_unique(array_column($candidates, 'campaign_id')));
    if (empty($camp_ids)) return [];

    $tr = pause_ads_table('pause_ads_targeting_rules');
    $ph = implode(',', array_fill(0, count($camp_ids), '?'));
    $tp = str_repeat('i', count($camp_ids));

    $rules = pause_ads_db_select("SELECT * FROM `{$tr}` WHERE `campaign_id` IN ({$ph})", $tp, $camp_ids);

    // Group rules by campaign_id => rule_type => [values]
    $rules_map = [];
    foreach ($rules as $r) {
        $rules_map[$r['campaign_id']][$r['rule_type']][] = $r['rule_value'];
    }

    // Determine which campaigns pass
    $passing_camps = [];
    foreach ($camp_ids as $cid) {
        if (!isset($rules_map[$cid]) || empty($rules_map[$cid])) {
            $passing_camps[$cid] = true; // No rules = match all
            continue;
        }

        $pass = true;
        foreach ($rules_map[$cid] as $rtype => $rvals) {
            if (!pa_eligible_check_rule($rtype, $rvals, $meta, $country_code, $region_code)) {
                $pass = false;
                break;
            }
        }
        if ($pass) $passing_camps[$cid] = true;
    }

    return array_filter($candidates, function($c) use ($passing_camps) {
        return isset($passing_camps[$c['campaign_id']]);
    });
}

/**
 * Check a single targeting rule type.
 */
function pa_eligible_check_rule($type, $values, $meta, $country_code, $region_code)
{
    $vals = array_map('strtolower', array_map('trim', $values));

    switch ($type) {
        case 'genre':
            $vg = strtolower(trim($meta['genre']));
            if (!$vg) return true;
            return !empty(array_intersect($vals, array_map('trim', explode(',', $vg))));

        case 'category':
            $vc = strtolower(trim($meta['category']));
            if (!$vc) return true;
            return !empty(array_intersect($vals, array_map('trim', explode(',', $vc))));

        case 'rating':
        case 'age_rating':
            $vr = strtolower(trim($meta[$type] ?? ''));
            return !$vr || in_array($vr, $vals);

        case 'country':
            $cc = strtolower(trim($country_code));
            return !$cc || in_array($cc, $vals);

        case 'region':
            $rc = strtolower(trim($region_code));
            return !$rc || in_array($rc, $vals);

        case 'language':
            $vl = strtolower(trim($meta['language']));
            return !$vl || in_array($vl, $vals);

        case 'device':
            $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
            foreach ($vals as $dev) {
                if ($dev === 'mobile' && preg_match('/mobile|android|iphone/i', $ua)) return true;
                if ($dev === 'tablet' && preg_match('/tablet|ipad/i', $ua)) return true;
                if ($dev === 'desktop' && !preg_match('/mobile|android|iphone|tablet|ipad/i', $ua)) return true;
            }
            return false;

        default:
            return true;
    }
}

/**
 * Filter by delivery caps (campaign-level daily/hourly/frequency).
 */
function pa_eligible_filter_caps($candidates, $session_id, $user_id)
{
    if (empty($candidates)) return [];

    $camp_ids = array_values(array_unique(array_column($candidates, 'campaign_id')));
    $imp = pause_ads_table('pause_ads_impressions');
    $ph = implode(',', array_fill(0, count($camp_ids), '?'));
    $tp = str_repeat('i', count($camp_ids));

    // Daily counts
    $daily = pause_ads_db_select(
        "SELECT `campaign_id`, COUNT(*) as cnt FROM `{$imp}` WHERE `campaign_id` IN ({$ph}) AND `created_at`>=CURDATE() GROUP BY `campaign_id`",
        $tp, $camp_ids
    );
    $daily_map = array_column($daily, 'cnt', 'campaign_id');

    // Hourly counts
    $hourly = pause_ads_db_select(
        "SELECT `campaign_id`, COUNT(*) as cnt FROM `{$imp}` WHERE `campaign_id` IN ({$ph}) AND `created_at`>=DATE_SUB(NOW(),INTERVAL 1 HOUR) GROUP BY `campaign_id`",
        $tp, $camp_ids
    );
    $hourly_map = array_column($hourly, 'cnt', 'campaign_id');

    // Frequency per user/session today
    $fid = $user_id ? (string)$user_id : $session_id;
    $ffield = $user_id ? 'user_id' : 'session_id';
    $freq = pause_ads_db_select(
        "SELECT `campaign_id`, COUNT(*) as cnt FROM `{$imp}` WHERE `campaign_id` IN ({$ph}) AND `{$ffield}`=? AND `created_at`>=CURDATE() GROUP BY `campaign_id`",
        $tp . 's', array_merge($camp_ids, [$fid])
    );
    $freq_map = array_column($freq, 'cnt', 'campaign_id');

    $passing = [];
    // Check per unique campaign
    $checked = [];
    foreach ($candidates as $c) {
        $cid = $c['campaign_id'];
        if (isset($checked[$cid])) { if ($checked[$cid]) $passing[] = $c; continue; }

        $ok = true;
        if (!empty($c['daily_cap']) && (int)($daily_map[$cid] ?? 0) >= (int)$c['daily_cap']) $ok = false;
        if ($ok && !empty($c['hourly_cap']) && (int)($hourly_map[$cid] ?? 0) >= (int)$c['hourly_cap']) $ok = false;
        if ($ok && !empty($c['freq_cap_per_user_per_day']) && (int)($freq_map[$cid] ?? 0) >= (int)$c['freq_cap_per_user_per_day']) $ok = false;

        $checked[$cid] = $ok;
        if ($ok) $passing[] = $c;
    }

    return $passing;
}

/**
 * Filter by remaining purchased impressions.
 */
function pa_eligible_filter_budget($candidates)
{
    if (empty($candidates)) return [];

    $camp_ids = array_values(array_unique(array_column($candidates, 'campaign_id')));
    $pur = pause_ads_table('pause_ads_purchases');
    $ph = implode(',', array_fill(0, count($camp_ids), '?'));
    $tp = str_repeat('i', count($camp_ids));

    $budgets = pause_ads_db_select(
        "SELECT `campaign_id`, SUM(`impressions_remaining`) as rem FROM `{$pur}` WHERE `campaign_id` IN ({$ph}) AND `payment_status`='paid' GROUP BY `campaign_id`",
        $tp, $camp_ids
    );
    $budget_map = array_column($budgets, 'rem', 'campaign_id');

    return array_filter($candidates, function($c) use ($budget_map) {
        $rem = (int)($budget_map[$c['campaign_id']] ?? 0);
        return $rem > 0;
    });
}

/**
 * Weighted random selection using priority groups.
 * Highest priority group first; within group, weighted random by creative_weight.
 */
function pa_eligible_weighted_select($candidates)
{
    if (empty($candidates)) return null;

    $candidates = array_values($candidates);

    // Group by campaign priority
    $groups = [];
    foreach ($candidates as $c) {
        $p = (int)($c['campaign_priority'] ?? 0);
        $groups[$p][] = $c;
    }
    krsort($groups);

    $top = reset($groups);
    if (count($top) === 1) return $top[0];

    // Weighted random
    $total = 0;
    foreach ($top as $c) $total += max(1, (int)($c['creative_weight'] ?? 1));

    $rand = mt_rand(1, $total);
    $cum = 0;
    foreach ($top as $c) {
        $cum += max(1, (int)($c['creative_weight'] ?? 1));
        if ($rand <= $cum) return $c;
    }

    return $top[array_rand($top)];
}
