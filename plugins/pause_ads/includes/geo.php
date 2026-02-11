<?php
/**
 * Pause Ads v2.0 - GeoIP Resolution
 *
 * Lightweight geo resolver with pluggable providers.
 * Caches lookups per-session for performance.
 *
 * Providers:
 *  - ip-api   : free ip-api.com (no key needed, 45 req/min)
 *  - none     : disabled (no geo targeting)
 *
 * @package PauseAds
 */

if (!defined('STARTER')) { die('No direct access allowed.'); }

/**
 * Resolve the current viewer's geography.
 * Returns ['country_code'=>'US', 'region_code'=>'CA'] or empty strings.
 *
 * Caches in session per IP to avoid repeated lookups.
 *
 * @param string|null $ip  Override IP (null = auto-detect)
 * @return array
 */
function pause_ads_resolve_geo($ip = null)
{
    if ($ip === null) {
        $ip = pause_ads_get_client_ip();
    }

    // Return empty for private/loopback IPs
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return ['country_code' => '', 'region_code' => ''];
    }

    // Session cache
    if (session_status() === PHP_SESSION_NONE) @session_start();
    $cache_key = 'pause_ads_geo_' . md5($ip);
    if (isset($_SESSION[$cache_key])) {
        return $_SESSION[$cache_key];
    }

    $provider = pause_ads_get_setting('geo_provider', 'ip-api');

    $result = ['country_code' => '', 'region_code' => ''];

    switch ($provider) {
        case 'ip-api':
            $result = pause_ads_geo_ipapi($ip);
            break;

        case 'none':
        default:
            break;
    }

    // Cache for the session
    $_SESSION[$cache_key] = $result;

    return $result;
}

/**
 * ip-api.com free provider.
 * Limit: 45 requests/minute from the same server IP.
 *
 * @param string $ip
 * @return array
 */
function pause_ads_geo_ipapi($ip)
{
    $result = ['country_code' => '', 'region_code' => ''];

    $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,countryCode,region';

    // Use cURL if available, else file_get_contents
    $json = null;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $json = curl_exec($ch);
        $err = curl_errno($ch);
        curl_close($ch);
        if ($err) $json = null;
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => 2]]);
        $json = @file_get_contents($url, false, $ctx);
    }

    if ($json) {
        $data = json_decode($json, true);
        if (isset($data['status']) && $data['status'] === 'success') {
            $result['country_code'] = strtoupper($data['countryCode'] ?? '');
            $result['region_code']  = strtoupper($data['region'] ?? '');
        }
    }

    return $result;
}

/**
 * Utility: get the current viewer's country code (cached).
 *
 * @return string Two-letter country code or ''
 */
function pause_ads_get_viewer_country()
{
    $geo = pause_ads_resolve_geo();
    return $geo['country_code'];
}

/**
 * Utility: get the current viewer's region code (cached).
 *
 * @return string
 */
function pause_ads_get_viewer_region()
{
    $geo = pause_ads_resolve_geo();
    return $geo['region_code'];
}
