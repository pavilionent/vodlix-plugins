<?php
/**
 * Rewardful Referrals - Authentication & Authorization Helper
 * 
 * Handles approval checks and access control for referrers
 */

namespace RewardfulReferrals;

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

class Auth {
    
    /**
     * @var Db
     */
    private $db;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Db();
    }
    
    /**
     * Check if current user is logged in
     * 
     * @return bool
     */
    public function isLoggedIn() {
        return function_exists('userid') && userid() > 0;
    }
    
    /**
     * Get current user ID
     * 
     * @return int|null
     */
    public function getCurrentUserId() {
        return function_exists('userid') ? userid() : null;
    }
    
    /**
     * Get current user email
     * 
     * @return string|null
     */
    public function getCurrentUserEmail() {
        global $userquery;
        
        $userid = $this->getCurrentUserId();
        if (!$userid) {
            return null;
        }
        
        if (isset($userquery) && method_exists($userquery, 'get_user_details')) {
            $user = $userquery->get_user_details($userid);
            return $user ? ($user['email'] ?? null) : null;
        }
        
        return null;
    }
    
    /**
     * Check if current user is an admin
     * 
     * @return bool
     */
    public function isAdmin() {
        if (function_exists('has_access')) {
            return has_access('admin_access', true);
        }
        
        global $userquery;
        if (isset($userquery) && method_exists($userquery, 'admin_check')) {
            return $userquery->admin_check();
        }
        
        return false;
    }
    
    /**
     * Check if current user is an approved referrer
     * 
     * @return bool
     */
    public function isApprovedReferrer() {
        $userid = $this->getCurrentUserId();
        if (!$userid) {
            return false;
        }
        
        $referrer = $this->db->getReferrerByUserId($userid);
        return $referrer && $referrer['status'] === 'approved';
    }
    
    /**
     * Get current user's referrer record
     * 
     * @return array|null
     */
    public function getCurrentReferrer() {
        $userid = $this->getCurrentUserId();
        if (!$userid) {
            return null;
        }
        
        return $this->db->getReferrerByUserId($userid);
    }
    
    /**
     * Get referrer status for current user
     * 
     * @return string|null
     */
    public function getReferrerStatus() {
        $referrer = $this->getCurrentReferrer();
        return $referrer ? $referrer['status'] : null;
    }
    
    /**
     * Check if user can view referrals section
     * 
     * @return bool
     */
    public function canViewReferrals() {
        // Admins can always view
        if ($this->isAdmin()) {
            return true;
        }
        
        // Approved referrers can view
        return $this->isApprovedReferrer();
    }
    
    /**
     * Check if user can access referrals page
     * 
     * @return bool
     */
    public function canAccessReferralsPage() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $this->canViewReferrals();
    }
    
    /**
     * Check if user can apply to be a referrer
     * 
     * @return bool
     */
    public function canApply() {
        // Must be logged in
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        // Check if self-apply is enabled
        $allowSelfApply = $this->db->getSetting('allow_self_apply', '0');
        if ($allowSelfApply !== '1') {
            return false;
        }
        
        // Check if user already has a referrer record
        $referrer = $this->getCurrentReferrer();
        if ($referrer) {
            // Can only apply if rejected (allow re-apply) or has no existing record
            return $referrer['status'] === 'rejected';
        }
        
        return true;
    }
    
    /**
     * Check if user can access SSO to Rewardful dashboard
     * 
     * @return bool
     */
    public function canAccessSSO() {
        return $this->isApprovedReferrer();
    }
    
    /**
     * Require approved referrer access
     * Returns error if not authorized
     * 
     * @return array|null Error array or null if authorized
     */
    public function requireApprovedReferrer() {
        if (!$this->isLoggedIn()) {
            return array(
                'error' => true,
                'code' => 'not_logged_in',
                'message' => 'You must be logged in to access this page.'
            );
        }
        
        if (!$this->canViewReferrals()) {
            return array(
                'error' => true,
                'code' => 'not_approved',
                'message' => 'You are not approved as a referrer.'
            );
        }
        
        return null;
    }
    
    /**
     * Require admin access
     * 
     * @return array|null Error array or null if authorized
     */
    public function requireAdmin() {
        if (!$this->isLoggedIn()) {
            return array(
                'error' => true,
                'code' => 'not_logged_in',
                'message' => 'You must be logged in to access this page.'
            );
        }
        
        if (!$this->isAdmin()) {
            return array(
                'error' => true,
                'code' => 'not_admin',
                'message' => 'You do not have permission to access this page.'
            );
        }
        
        return null;
    }
    
    /**
     * Generate CSRF token
     * 
     * @return string
     */
    public function generateCSRFToken() {
        if (!isset($_SESSION['rewardful_csrf_token'])) {
            $_SESSION['rewardful_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['rewardful_csrf_token'];
    }
    
    /**
     * Verify CSRF token
     * 
     * @param string $token
     * @return bool
     */
    public function verifyCSRFToken($token) {
        if (!isset($_SESSION['rewardful_csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['rewardful_csrf_token'], $token);
    }
    
    /**
     * Get CSRF token input field HTML
     * 
     * @return string
     */
    public function csrfField() {
        $token = $this->generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Validate CSRF from POST request
     * 
     * @return bool
     */
    public function validateCSRF() {
        $token = $_POST['csrf_token'] ?? '';
        return $this->verifyCSRFToken($token);
    }
    
    /**
     * Store via token in session for attribution
     * 
     * @param string $token
     */
    public function storeViaToken($token) {
        if (!empty($token) && preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
            $_SESSION['rewardful_via_token'] = $token;
            $_SESSION['rewardful_via_timestamp'] = time();
        }
    }
    
    /**
     * Get stored via token from session
     * 
     * @return string|null
     */
    public function getStoredViaToken() {
        // Check if token exists and is not expired (30 days)
        if (isset($_SESSION['rewardful_via_token']) && isset($_SESSION['rewardful_via_timestamp'])) {
            $age = time() - $_SESSION['rewardful_via_timestamp'];
            if ($age < (30 * 24 * 60 * 60)) {
                return $_SESSION['rewardful_via_token'];
            }
        }
        return null;
    }
    
    /**
     * Clear stored via token
     */
    public function clearViaToken() {
        unset($_SESSION['rewardful_via_token']);
        unset($_SESSION['rewardful_via_timestamp']);
    }
}
