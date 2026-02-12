<?php
/**
 * Pause Ads Plugin - Security Functions
 *
 * CSRF protection, input validation, rate limiting, and session management.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    die('No direct access allowed.');
}

/**
 * Generate or retrieve a CSRF nonce token.
 *
 * @param string $action Action identifier
 * @return string The nonce token
 */
function pause_ads_create_nonce($action = 'pause_ads')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $nonce_key = 'pause_ads_nonce_' . $action;

    if (empty($_SESSION[$nonce_key])) {
        $_SESSION[$nonce_key] = bin2hex(random_bytes(32));
    }

    return $_SESSION[$nonce_key];
}

/**
 * Verify a CSRF nonce token.
 *
 * @param string $token Token to verify
 * @param string $action Action identifier
 * @return bool True if valid
 */
function pause_ads_verify_nonce($token, $action = 'pause_ads')
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $nonce_key = 'pause_ads_nonce_' . $action;

    if (empty($_SESSION[$nonce_key]) || empty($token)) {
        return false;
    }

    $valid = hash_equals($_SESSION[$nonce_key], $token);

    // Regenerate nonce after use for one-time use
    if ($valid) {
        $_SESSION[$nonce_key] = bin2hex(random_bytes(32));
    }

    return $valid;
}

/**
 * Output a hidden nonce input field for forms.
 *
 * @param string $action Action identifier
 * @return string HTML hidden input
 */
function pause_ads_nonce_field($action = 'pause_ads')
{
    $token = pause_ads_create_nonce($action);
    return '<input type="hidden" name="_pause_ads_nonce" value="' . htmlspecialchars($token) . '">';
}

/**
 * Verify the nonce from a POST request.
 *
 * @param string $action Action identifier
 * @return bool True if valid
 */
function pause_ads_check_nonce($action = 'pause_ads')
{
    $token = isset($_POST['_pause_ads_nonce']) ? $_POST['_pause_ads_nonce'] : '';
    return pause_ads_verify_nonce($token, $action);
}

/**
 * Hash an IP address for privacy-friendly storage.
 *
 * @param string $ip Raw IP address
 * @return string Hashed IP
 */
function pause_ads_hash_ip($ip)
{
    $salt = pause_ads_get_setting('ip_salt', 'default_fallback_salt_change_me');
    return hash('sha256', $ip . $salt);
}

/**
 * Hash a user agent string.
 *
 * @param string $ua User agent string
 * @return string Hashed UA
 */
function pause_ads_hash_ua($ua)
{
    return hash('sha256', $ua);
}

/**
 * Get or create a session ID for anonymous tracking.
 *
 * @return string Session UUID
 */
function pause_ads_get_session_id()
{
    $cookie_name = 'pause_ads_sid';

    if (isset($_COOKIE[$cookie_name]) && pause_ads_is_valid_uuid($_COOKIE[$cookie_name])) {
        return $_COOKIE[$cookie_name];
    }

    // Generate a new UUID v4
    $uuid = pause_ads_generate_uuid();

    // Set cookie for 24 hours
    setcookie($cookie_name, $uuid, time() + 86400, '/', '', false, true);
    $_COOKIE[$cookie_name] = $uuid;

    return $uuid;
}

/**
 * Generate a UUID v4.
 *
 * @return string
 */
function pause_ads_generate_uuid()
{
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant 1

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Validate a UUID v4 string.
 *
 * @param string $uuid
 * @return bool
 */
function pause_ads_is_valid_uuid($uuid)
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $uuid
    );
}

/**
 * Sanitize and validate a positive integer.
 *
 * @param mixed $value
 * @return int|false
 */
function pause_ads_validate_int($value)
{
    $val = filter_var($value, FILTER_VALIDATE_INT);
    return ($val !== false && $val >= 0) ? $val : false;
}

/**
 * Sanitize a string for output.
 *
 * @param string $str
 * @return string
 */
function pause_ads_esc($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize a URL.
 *
 * @param string $url
 * @return string|false
 */
function pause_ads_validate_url($url)
{
    $url = trim($url);
    if (empty($url)) {
        return '';
    }
    return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : false;
}

/**
 * Basic rate limiting per session per minute.
 * Uses a simple in-memory check via session.
 *
 * @param string $action Action to rate limit
 * @param int    $max_per_minute Maximum allowed actions per minute
 * @return bool True if within limit, false if exceeded
 */
function pause_ads_rate_limit($action = 'impression', $max_per_minute = 5)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $key = 'pause_ads_rl_' . $action;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Remove entries older than 60 seconds
    $_SESSION[$key] = array_filter($_SESSION[$key], function ($ts) use ($now) {
        return ($now - $ts) < 60;
    });

    if (count($_SESSION[$key]) >= $max_per_minute) {
        return false; // Rate limited
    }

    $_SESSION[$key][] = $now;
    return true;
}

/**
 * Validate an uploaded file as an allowed image.
 *
 * @param array $file $_FILES entry
 * @return array ['valid' => bool, 'error' => string|null]
 */
function pause_ads_validate_upload($file)
{
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'No file uploaded.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    // Check file size
    if ($file['size'] > PAUSE_ADS_MAX_UPLOAD_SIZE) {
        return ['valid' => false, 'error' => 'File exceeds maximum size of 5MB.'];
    }

    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = unserialize(PAUSE_ADS_ALLOWED_EXTENSIONS);
    if (!in_array($ext, $allowed_ext)) {
        return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_ext)];
    }

    // Verify MIME type using fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_types = unserialize(PAUSE_ADS_ALLOWED_TYPES);
    if (!in_array($mime, $allowed_types)) {
        return ['valid' => false, 'error' => 'Invalid MIME type: ' . $mime];
    }

    // Additional check: try to read as image (except SVG)
    if ($mime !== 'image/svg+xml') {
        $img_info = @getimagesize($file['tmp_name']);
        if ($img_info === false) {
            return ['valid' => false, 'error' => 'File is not a valid image.'];
        }
    }

    return ['valid' => true, 'error' => null, 'extension' => $ext, 'mime' => $mime];
}

/**
 * Require admin authentication.
 * Stops execution if the current user is not an admin.
 */
function pause_ads_require_admin()
{
    // ClipBucket admin check
    if (function_exists('has_access')) {
        if (!has_access('admin_access', true)) {
            die('Access denied. Admin privileges required.');
        }
    } elseif (function_exists('is_admin')) {
        if (!is_admin()) {
            die('Access denied. Admin privileges required.');
        }
    } else {
        // Fallback: check session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
            die('Access denied. Admin privileges required.');
        }
    }
}

/**
 * Get the current user's ID, or null for anonymous.
 *
 * @return int|null
 */
function pause_ads_get_current_user_id()
{
    // ClipBucket user detection
    if (function_exists('userid')) {
        $uid = userid();
        return $uid > 0 ? (int) $uid : null;
    }

    global $userquery;
    if (isset($userquery) && is_object($userquery)) {
        if (method_exists($userquery, 'get_user_id')) {
            $uid = $userquery->get_user_id();
            return $uid > 0 ? (int) $uid : null;
        }
        if (isset($userquery->userid) && $userquery->userid > 0) {
            return (int) $userquery->userid;
        }
    }

    return null;
}

/**
 * Get the client's real IP address.
 *
 * @return string
 */
function pause_ads_get_client_ip()
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',  // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

/**
 * Require a logged-in ClipBucket user. Returns user_id or dies.
 *
 * @return int
 */
function pause_ads_require_login()
{
    $uid = pause_ads_get_current_user_id();
    if (!$uid) {
        if (defined('PAUSE_ADS_AJAX')) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['error' => 'Login required']);
            exit;
        }
        $base = function_exists('base_url') ? base_url() : '/';
        header('Location: ' . $base . 'signup.php?mode=login&next=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    return $uid;
}

/**
 * Require that the current user has a specific company role.
 *
 * @param int    $company_id
 * @param array  $allowed_roles e.g. ['owner','admin']
 * @return array Company-user row
 */
function pause_ads_require_company_role($company_id, $allowed_roles = ['owner','admin','analyst'])
{
    $uid = pause_ads_require_login();
    $table = pause_ads_table('pause_ads_company_users');
    $row = pause_ads_db_select_one(
        "SELECT * FROM `{$table}` WHERE `company_id` = ? AND `user_id` = ?",
        'ii', [$company_id, $uid]
    );
    if (!$row || !in_array($row['role'], $allowed_roles)) {
        if (defined('PAUSE_ADS_AJAX')) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            exit;
        }
        die('Access denied: insufficient company role.');
    }
    return $row;
}

/**
 * Get the companies the current user belongs to.
 *
 * @param int|null $user_id
 * @return array
 */
function pause_ads_get_user_companies($user_id = null)
{
    if (!$user_id) $user_id = pause_ads_get_current_user_id();
    if (!$user_id) return [];

    $cu = pause_ads_table('pause_ads_company_users');
    $co = pause_ads_table('pause_ads_companies');

    return pause_ads_db_select(
        "SELECT c.*, cu.role FROM `{$cu}` cu
         JOIN `{$co}` c ON c.id = cu.company_id
         WHERE cu.user_id = ? AND c.status = 'active'
         ORDER BY c.name",
        'i', [$user_id]
    );
}

/**
 * Get the "active" company_id for the current advertiser session.
 * Falls back to the first company or 0.
 *
 * @return int
 */
function pause_ads_get_active_company_id()
{
    if (session_status() === PHP_SESSION_NONE) session_start();

    // If explicitly set in session
    if (!empty($_SESSION['pause_ads_company_id'])) {
        return (int) $_SESSION['pause_ads_company_id'];
    }

    // If URL param
    if (!empty($_GET['company_id'])) {
        $cid = (int) $_GET['company_id'];
        $_SESSION['pause_ads_company_id'] = $cid;
        return $cid;
    }

    // Default to first company
    $companies = pause_ads_get_user_companies();
    if (!empty($companies)) {
        $_SESSION['pause_ads_company_id'] = (int) $companies[0]['id'];
        return (int) $companies[0]['id'];
    }

    return 0;
}
