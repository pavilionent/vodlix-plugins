<?php
/**
 * User Helper for Churnkey + Stripe Plugin
 * Provides user-related functions using ClipBucket's user system
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

/**
 * Check if user is logged in
 * 
 * @return bool
 */
function churnkey_stripe_is_user_logged_in() {
    global $userquery, $Cbucket, $user;
    
    // Try ClipBucket's user query object
    if (isset($userquery) && is_object($userquery)) {
        if (method_exists($userquery, 'login_check')) {
            return $userquery->login_check() !== false;
        }
        if (method_exists($userquery, 'is_loggedin')) {
            return $userquery->is_loggedin();
        }
        if (method_exists($userquery, 'is_logged_in')) {
            return $userquery->is_logged_in();
        }
    }
    
    // Try global user variable
    if (isset($user) && is_array($user) && !empty($user['userid'])) {
        return true;
    }
    
    // Try session
    if (isset($_SESSION['userid']) && !empty($_SESSION['userid'])) {
        return true;
    }
    if (isset($_SESSION['user']['userid']) && !empty($_SESSION['user']['userid'])) {
        return true;
    }
    
    // Try Cbucket
    if (isset($Cbucket) && is_object($Cbucket)) {
        if (isset($Cbucket->user['userid']) && !empty($Cbucket->user['userid'])) {
            return true;
        }
    }
    
    return false;
}

/**
 * Get current user ID
 * 
 * @return int|null User ID or null if not logged in
 */
function churnkey_stripe_get_current_userid() {
    global $userquery, $Cbucket, $user;
    
    // Try ClipBucket's user query object
    if (isset($userquery) && is_object($userquery)) {
        if (method_exists($userquery, 'get_user_id')) {
            $id = $userquery->get_user_id();
            if ($id) return $id;
        }
        if (method_exists($userquery, 'userid')) {
            $id = $userquery->userid();
            if ($id) return $id;
        }
        if (isset($userquery->userid)) {
            return $userquery->userid;
        }
        if (isset($userquery->user_id)) {
            return $userquery->user_id;
        }
    }
    
    // Try global user variable
    if (isset($user) && is_array($user)) {
        if (!empty($user['userid'])) {
            return $user['userid'];
        }
        if (!empty($user['user_id'])) {
            return $user['user_id'];
        }
    }
    
    // Try session
    if (isset($_SESSION['userid'])) {
        return $_SESSION['userid'];
    }
    if (isset($_SESSION['user']['userid'])) {
        return $_SESSION['user']['userid'];
    }
    
    // Try Cbucket
    if (isset($Cbucket) && is_object($Cbucket)) {
        if (isset($Cbucket->user['userid'])) {
            return $Cbucket->user['userid'];
        }
    }
    
    return null;
}

/**
 * Get current user email
 * 
 * @return string|null Email or null if not logged in
 */
function churnkey_stripe_get_current_email() {
    global $userquery, $Cbucket, $user;
    
    // Try ClipBucket's user query object
    if (isset($userquery) && is_object($userquery)) {
        if (method_exists($userquery, 'get_user_email')) {
            $email = $userquery->get_user_email();
            if ($email) return $email;
        }
        if (isset($userquery->email)) {
            return $userquery->email;
        }
    }
    
    // Try global user variable
    if (isset($user) && is_array($user)) {
        if (!empty($user['email'])) {
            return $user['email'];
        }
        if (!empty($user['user_email'])) {
            return $user['user_email'];
        }
    }
    
    // Try session
    if (isset($_SESSION['user']['email'])) {
        return $_SESSION['user']['email'];
    }
    
    // Try Cbucket
    if (isset($Cbucket) && is_object($Cbucket)) {
        if (isset($Cbucket->user['email'])) {
            return $Cbucket->user['email'];
        }
    }
    
    // Fallback: Look up by user ID
    $userid = churnkey_stripe_get_current_userid();
    if ($userid) {
        return churnkey_stripe_get_user_email_by_id($userid);
    }
    
    return null;
}

/**
 * Get user email by user ID
 * 
 * @param int $userid User ID
 * @return string|null Email or null if not found
 */
function churnkey_stripe_get_user_email_by_id($userid) {
    global $userquery;
    
    // Try ClipBucket's user query object
    if (isset($userquery) && is_object($userquery)) {
        if (method_exists($userquery, 'get_user_details')) {
            $user = $userquery->get_user_details($userid);
            if ($user && isset($user['email'])) {
                return $user['email'];
            }
        }
        if (method_exists($userquery, 'get_user')) {
            $user = $userquery->get_user($userid);
            if ($user && isset($user['email'])) {
                return $user['email'];
            }
        }
    }
    
    // Direct database lookup
    $db = churnkey_stripe_db();
    
    // Try common user table names
    $tables = array('users', 'user', 'cb_users');
    
    foreach ($tables as $table) {
        $sql = "SELECT `email` FROM `{$table}` WHERE `userid` = " . intval($userid) . " LIMIT 1";
        $row = $db->getRow($sql);
        if ($row && isset($row['email'])) {
            return $row['email'];
        }
    }
    
    return null;
}

/**
 * Get user by ID with full details
 * 
 * @param int $userid User ID
 * @return array|null User data or null if not found
 */
function churnkey_stripe_get_user_by_id($userid) {
    global $userquery;
    
    // Try ClipBucket's user query object
    if (isset($userquery) && is_object($userquery)) {
        if (method_exists($userquery, 'get_user_details')) {
            $user = $userquery->get_user_details($userid);
            if ($user) return $user;
        }
        if (method_exists($userquery, 'get_user')) {
            $user = $userquery->get_user($userid);
            if ($user) return $user;
        }
    }
    
    // Direct database lookup
    $db = churnkey_stripe_db();
    
    // Try common user table names
    $tables = array('users', 'user', 'cb_users');
    
    foreach ($tables as $table) {
        $sql = "SELECT * FROM `{$table}` WHERE `userid` = " . intval($userid) . " LIMIT 1";
        $row = $db->getRow($sql);
        if ($row) {
            return $row;
        }
    }
    
    return null;
}

/**
 * Get current user data
 * 
 * @return array|null Current user data or null if not logged in
 */
function churnkey_stripe_get_current_user() {
    $userid = churnkey_stripe_get_current_userid();
    if (!$userid) {
        return null;
    }
    
    return churnkey_stripe_get_user_by_id($userid);
}

/**
 * Check if current user is admin
 * 
 * @return bool
 */
function churnkey_stripe_is_admin() {
    global $userquery, $Cbucket;
    
    // Try ClipBucket's user query object
    if (isset($userquery) && is_object($userquery)) {
        if (method_exists($userquery, 'admin_check')) {
            return $userquery->admin_check() === true;
        }
        if (method_exists($userquery, 'is_admin')) {
            return $userquery->is_admin();
        }
    }
    
    // Try session
    if (isset($_SESSION['user']['level']) && $_SESSION['user']['level'] === 'admin') {
        return true;
    }
    if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
        return true;
    }
    
    // Try Cbucket
    if (isset($Cbucket) && is_object($Cbucket)) {
        if (isset($Cbucket->user['level']) && $Cbucket->user['level'] === 'admin') {
            return true;
        }
    }
    
    return false;
}

/**
 * Require user to be logged in
 * Exits with error if not logged in
 * 
 * @param bool $json_response If true, outputs JSON error; otherwise redirects
 */
function churnkey_stripe_require_login($json_response = false) {
    if (!churnkey_stripe_is_user_logged_in()) {
        if ($json_response) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(array(
                'success' => false,
                'error' => 'Not logged in'
            ));
            exit;
        } else {
            // Redirect to login
            $login_url = '/login';
            if (defined('BASEURL')) {
                $login_url = BASEURL . '/login';
            }
            header('Location: ' . $login_url);
            exit;
        }
    }
}

/**
 * Require user to be admin
 * Exits with error if not admin
 * 
 * @param bool $json_response If true, outputs JSON error; otherwise redirects
 */
function churnkey_stripe_require_admin($json_response = false) {
    churnkey_stripe_require_login($json_response);
    
    if (!churnkey_stripe_is_admin()) {
        if ($json_response) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(array(
                'success' => false,
                'error' => 'Admin access required'
            ));
            exit;
        } else {
            // Redirect to home
            $home_url = '/';
            if (defined('BASEURL')) {
                $home_url = BASEURL;
            }
            header('Location: ' . $home_url);
            exit;
        }
    }
}
