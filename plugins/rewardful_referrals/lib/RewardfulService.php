<?php
/**
 * Rewardful Referrals - Service Layer
 * 
 * High-level business logic for Rewardful integration
 */

namespace RewardfulReferrals;

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

class RewardfulService {
    
    /**
     * @var Db
     */
    private $db;
    
    /**
     * @var RewardfulClient
     */
    private $client;
    
    /**
     * @var Auth
     */
    private $auth;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Db();
        $this->client = new RewardfulClient();
        $this->auth = new Auth();
    }
    
    /**
     * Check if integration is enabled
     * 
     * @return bool
     */
    public function isEnabled() {
        return $this->db->getSetting('enabled') === '1';
    }
    
    /**
     * Check if API is configured
     * 
     * @return bool
     */
    public function isConfigured() {
        return $this->client->isConfigured();
    }
    
    /**
     * Get public API key (safe for client-side use)
     * 
     * @return string
     */
    public function getPublicApiKey() {
        return $this->db->getSetting('public_api_key', '');
    }
    
    // ========================================
    // REFERRER MANAGEMENT
    // ========================================
    
    /**
     * Create a new referrer (admin action)
     * 
     * @param array $data Referrer data
     * @param int|null $adminUserId Creating admin's user ID
     * @return array Result with success/error
     */
    public function createReferrer($data, $adminUserId = null) {
        // Validate email
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return array(
                'success' => false,
                'error' => 'Valid email is required'
            );
        }
        
        // Check for existing referrer
        $existing = $this->db->getReferrerByEmail($data['email']);
        if ($existing) {
            return array(
                'success' => false,
                'error' => 'A referrer with this email already exists'
            );
        }
        
        // Check for existing user by email if userid not provided
        if (empty($data['userid'])) {
            $data['userid'] = $this->findUserIdByEmail($data['email']);
        }
        
        // Set admin creator
        if ($adminUserId) {
            $data['created_by_admin_userid'] = $adminUserId;
        }
        
        // Default status is pending unless specified
        if (!isset($data['status'])) {
            $data['status'] = 'pending';
        }
        
        $referrerId = $this->db->createReferrer($data);
        
        if ($referrerId) {
            $this->db->log('info', 'referrer_created', 
                "Referrer created: {$data['email']}", 
                array('referrer_id' => $referrerId, 'data' => $data),
                $adminUserId
            );
            
            return array(
                'success' => true,
                'referrer_id' => $referrerId
            );
        }
        
        return array(
            'success' => false,
            'error' => 'Failed to create referrer'
        );
    }
    
    /**
     * Approve a referrer
     * 
     * @param int $referrerId
     * @param int|null $adminUserId
     * @return array
     */
    public function approveReferrer($referrerId, $adminUserId = null) {
        $referrer = $this->db->getReferrerById($referrerId);
        
        if (!$referrer) {
            return array('success' => false, 'error' => 'Referrer not found');
        }
        
        $result = $this->db->updateReferrer($referrerId, array(
            'status' => 'approved',
            'approved_by_admin_userid' => $adminUserId,
            'approved_at' => date('Y-m-d H:i:s')
        ));
        
        if ($result) {
            $this->db->log('info', 'referrer_approved', 
                "Referrer approved: {$referrer['email']}", 
                array('referrer_id' => $referrerId),
                $adminUserId
            );
            
            // Automatically sync with Rewardful
            $this->syncAffiliateWithRewardful($referrerId);
            
            return array('success' => true);
        }
        
        return array('success' => false, 'error' => 'Failed to approve referrer');
    }
    
    /**
     * Reject a referrer
     * 
     * @param int $referrerId
     * @param int|null $adminUserId
     * @return array
     */
    public function rejectReferrer($referrerId, $adminUserId = null) {
        $referrer = $this->db->getReferrerById($referrerId);
        
        if (!$referrer) {
            return array('success' => false, 'error' => 'Referrer not found');
        }
        
        $result = $this->db->updateReferrer($referrerId, array(
            'status' => 'rejected'
        ));
        
        if ($result) {
            $this->db->log('info', 'referrer_rejected', 
                "Referrer rejected: {$referrer['email']}", 
                array('referrer_id' => $referrerId),
                $adminUserId
            );
            
            return array('success' => true);
        }
        
        return array('success' => false, 'error' => 'Failed to reject referrer');
    }
    
    /**
     * Disable a referrer
     * 
     * @param int $referrerId
     * @param int|null $adminUserId
     * @return array
     */
    public function disableReferrer($referrerId, $adminUserId = null) {
        $referrer = $this->db->getReferrerById($referrerId);
        
        if (!$referrer) {
            return array('success' => false, 'error' => 'Referrer not found');
        }
        
        $result = $this->db->updateReferrer($referrerId, array(
            'status' => 'disabled'
        ));
        
        if ($result) {
            $this->db->log('info', 'referrer_disabled', 
                "Referrer disabled: {$referrer['email']}", 
                array('referrer_id' => $referrerId),
                $adminUserId
            );
            
            return array('success' => true);
        }
        
        return array('success' => false, 'error' => 'Failed to disable referrer');
    }
    
    /**
     * Submit referrer application (self-apply)
     * 
     * @param int $userId ClipBucket user ID
     * @param string $message Application message
     * @return array
     */
    public function submitApplication($userId, $message = '') {
        global $userquery;
        
        // Check if self-apply is enabled
        if ($this->db->getSetting('allow_self_apply') !== '1') {
            return array(
                'success' => false,
                'error' => 'Referrer applications are not currently open'
            );
        }
        
        // Get user details
        $user = $userquery->get_user_details($userId);
        if (!$user) {
            return array('success' => false, 'error' => 'User not found');
        }
        
        // Check for existing application
        $existing = $this->db->getReferrerByUserId($userId);
        if ($existing && $existing['status'] !== 'rejected') {
            return array(
                'success' => false,
                'error' => 'You already have a pending or active referrer status'
            );
        }
        
        // Create or update referrer record
        if ($existing) {
            // Re-apply after rejection
            $this->db->updateReferrer($existing['id'], array(
                'status' => 'pending',
                'application_message' => $message
            ));
            $referrerId = $existing['id'];
        } else {
            $referrerId = $this->db->createReferrer(array(
                'userid' => $userId,
                'email' => $user['email'],
                'first_name' => $user['first_name'] ?? '',
                'last_name' => $user['last_name'] ?? '',
                'status' => 'pending',
                'application_message' => $message
            ));
        }
        
        if ($referrerId) {
            $this->db->log('info', 'referrer_application', 
                "User applied to be a referrer: {$user['email']}", 
                array('referrer_id' => $referrerId, 'userid' => $userId)
            );
            
            return array(
                'success' => true,
                'message' => 'Your application has been submitted and is pending review.'
            );
        }
        
        return array('success' => false, 'error' => 'Failed to submit application');
    }
    
    // ========================================
    // REWARDFUL SYNC
    // ========================================
    
    /**
     * Sync referrer with Rewardful (create/update affiliate)
     * 
     * @param int $referrerId
     * @return array
     */
    public function syncAffiliateWithRewardful($referrerId) {
        $referrer = $this->db->getReferrerById($referrerId);
        
        if (!$referrer) {
            return array('success' => false, 'error' => 'Referrer not found');
        }
        
        if ($referrer['status'] !== 'approved') {
            return array('success' => false, 'error' => 'Referrer is not approved');
        }
        
        if (!$this->isConfigured()) {
            return array('success' => false, 'error' => 'Rewardful API not configured');
        }
        
        // Build affiliate data
        $affiliateData = array(
            'email' => $referrer['email']
        );
        
        if (!empty($referrer['first_name'])) {
            $affiliateData['first_name'] = $referrer['first_name'];
        }
        if (!empty($referrer['last_name'])) {
            $affiliateData['last_name'] = $referrer['last_name'];
        }
        
        // Add campaign if configured
        $campaignId = $this->db->getSetting('default_campaign_id');
        if (!empty($campaignId)) {
            $affiliateData['campaign_id'] = $campaignId;
        }
        
        // Create or get affiliate in Rewardful
        $result = $this->client->ensureAffiliate($referrer['email'], $affiliateData);
        
        if (!$result['success']) {
            $this->db->log('error', 'affiliate_sync_failed', 
                "Failed to sync affiliate: {$referrer['email']}", 
                array('referrer_id' => $referrerId, 'error' => $result['error'])
            );
            
            return array(
                'success' => false,
                'error' => 'Failed to create/sync affiliate: ' . ($result['error'] ?? 'Unknown error')
            );
        }
        
        // Store affiliate data locally
        $affiliate = $result['data'];
        
        $localData = array(
            'rewardful_affiliate_id' => $affiliate['id'],
            'token' => $affiliate['token'] ?? '',
            'campaign_id' => $affiliate['campaign']['id'] ?? null,
            'first_name' => $affiliate['first_name'] ?? '',
            'last_name' => $affiliate['last_name'] ?? '',
            'referral_link' => $affiliate['link'] ?? '',
            'visitors_count' => $affiliate['visitors'] ?? 0,
            'leads_count' => $affiliate['leads'] ?? 0,
            'conversions_count' => $affiliate['conversions'] ?? 0
        );
        
        $this->db->upsertAffiliate($referrerId, $localData);
        
        $this->db->log('info', 'affiliate_synced', 
            "Affiliate synced: {$referrer['email']}", 
            array('referrer_id' => $referrerId, 'affiliate_id' => $affiliate['id'])
        );
        
        return array(
            'success' => true,
            'affiliate' => $affiliate
        );
    }
    
    /**
     * Get referral link for approved referrer
     * 
     * @param int $referrerId
     * @return array
     */
    public function getReferralLink($referrerId) {
        $referrer = $this->db->getReferrerById($referrerId);
        
        if (!$referrer || $referrer['status'] !== 'approved') {
            return array('success' => false, 'error' => 'Not an approved referrer');
        }
        
        $affiliate = $this->db->getAffiliateByReferrerId($referrerId);
        
        if (!$affiliate) {
            // Try to sync
            $sync = $this->syncAffiliateWithRewardful($referrerId);
            if (!$sync['success']) {
                return $sync;
            }
            $affiliate = $this->db->getAffiliateByReferrerId($referrerId);
        }
        
        if (!$affiliate || empty($affiliate['token'])) {
            return array('success' => false, 'error' => 'Referral link not available');
        }
        
        // Build referral URL
        $baseUrl = defined('BASEURL') ? BASEURL : '';
        $referralLink = $baseUrl . '?via=' . $affiliate['token'];
        
        return array(
            'success' => true,
            'token' => $affiliate['token'],
            'link' => $referralLink,
            'rewardful_link' => $affiliate['referral_link']
        );
    }
    
    /**
     * Generate SSO link for referrer to access Rewardful dashboard
     * 
     * @param int $referrerId
     * @return array
     */
    public function generateSSOLink($referrerId) {
        $referrer = $this->db->getReferrerById($referrerId);
        
        if (!$referrer || $referrer['status'] !== 'approved') {
            return array('success' => false, 'error' => 'Not an approved referrer');
        }
        
        $affiliate = $this->db->getAffiliateByReferrerId($referrerId);
        
        if (!$affiliate) {
            return array('success' => false, 'error' => 'Affiliate not synced with Rewardful');
        }
        
        if (!$this->isConfigured()) {
            return array('success' => false, 'error' => 'Rewardful API not configured');
        }
        
        $result = $this->client->generateSSOLink($affiliate['rewardful_affiliate_id']);
        
        if (!$result['success']) {
            $this->db->log('error', 'sso_generation_failed', 
                "Failed to generate SSO link: {$referrer['email']}", 
                array('referrer_id' => $referrerId, 'error' => $result['error'])
            );
            
            return array(
                'success' => false,
                'error' => 'Failed to generate SSO link'
            );
        }
        
        $ssoUrl = $result['data']['url'] ?? $result['data']['link'] ?? null;
        
        if (!$ssoUrl) {
            return array('success' => false, 'error' => 'SSO URL not returned');
        }
        
        $this->db->log('info', 'sso_generated', 
            "SSO link generated: {$referrer['email']}", 
            array('referrer_id' => $referrerId)
        );
        
        return array(
            'success' => true,
            'url' => $ssoUrl
        );
    }
    
    // ========================================
    // CONVERSIONS
    // ========================================
    
    /**
     * Record a conversion (subscription purchase)
     * 
     * @param array $data Conversion data
     * @return array
     */
    public function recordConversion($data) {
        // Validate required fields
        if (empty($data['email'])) {
            return array('success' => false, 'error' => 'Email is required');
        }
        
        // Generate idempotency key
        $conversionKey = $this->db->generateConversionKey($data);
        
        // Check if already recorded
        if ($this->db->conversionExists($conversionKey)) {
            $this->db->log('info', 'conversion_duplicate', 
                "Duplicate conversion ignored: {$data['email']}", 
                array('conversion_key' => $conversionKey)
            );
            
            return array(
                'success' => true,
                'duplicate' => true,
                'message' => 'Conversion already recorded'
            );
        }
        
        // Get via token from session if not provided
        if (empty($data['via_token'])) {
            $data['via_token'] = $this->auth->getStoredViaToken();
        }
        
        // Set default status
        $data['status'] = 'pending';
        $data['conversion_key'] = $conversionKey;
        
        $conversionId = $this->db->createConversion($data);
        
        if ($conversionId) {
            $this->db->log('conversion', 'conversion_recorded', 
                "Conversion recorded: {$data['email']}", 
                array(
                    'conversion_id' => $conversionId,
                    'via_token' => $data['via_token'] ?? null
                )
            );
            
            return array(
                'success' => true,
                'conversion_id' => $conversionId,
                'email' => $data['email'],
                'via_token' => $data['via_token'] ?? null
            );
        }
        
        return array('success' => false, 'error' => 'Failed to record conversion');
    }
    
    /**
     * Mark conversion as sent (after client-side JS fires)
     * 
     * @param int $conversionId
     * @return bool
     */
    public function markConversionSent($conversionId) {
        return $this->db->updateConversionStatus($conversionId, 'sent');
    }
    
    // ========================================
    // WEBHOOKS
    // ========================================
    
    /**
     * Verify webhook signature
     * 
     * @param string $payload Raw request body
     * @param string $signature Signature from header
     * @return bool
     */
    public function verifyWebhookSignature($payload, $signature) {
        $secret = $this->db->getSetting('webhook_secret', '');
        
        if (empty($secret)) {
            return false;
        }
        
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        
        // Constant-time comparison
        return hash_equals($expectedSignature, $signature);
    }
    
    /**
     * Process webhook event
     * 
     * @param string $eventType
     * @param array $payload
     * @return array
     */
    public function processWebhook($eventType, $payload) {
        // Generate unique event ID
        $eventId = $payload['id'] ?? hash('sha256', json_encode($payload) . time());
        
        // Check idempotency
        if ($this->db->eventExists($eventId)) {
            return array(
                'success' => true,
                'duplicate' => true,
                'message' => 'Event already processed'
            );
        }
        
        // Store event
        $eventDbId = $this->db->createEvent(array(
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload_json' => json_encode($payload),
            'status' => 'received'
        ));
        
        $this->db->log('webhook', 'webhook_received', 
            "Webhook event: {$eventType}", 
            array('event_id' => $eventId)
        );
        
        // Process based on event type
        try {
            $result = $this->handleWebhookEvent($eventType, $payload);
            
            $this->db->updateEventStatus($eventDbId, 'processed');
            
            return array(
                'success' => true,
                'processed' => true,
                'result' => $result
            );
            
        } catch (\Exception $e) {
            $this->db->updateEventStatus($eventDbId, 'failed', $e->getMessage());
            
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Handle specific webhook event types
     * 
     * @param string $eventType
     * @param array $payload
     * @return array
     */
    private function handleWebhookEvent($eventType, $payload) {
        switch ($eventType) {
            case 'affiliate.created':
            case 'affiliate.updated':
                // Could sync affiliate data back
                break;
                
            case 'referral.created':
            case 'referral.converted':
                // Could update local stats
                break;
                
            case 'commission.created':
            case 'commission.updated':
                // Could notify referrer
                break;
                
            default:
                // Unknown event type, just log
                break;
        }
        
        return array('handled' => true);
    }
    
    // ========================================
    // HELPERS
    // ========================================
    
    /**
     * Find ClipBucket user ID by email
     * 
     * @param string $email
     * @return int|null
     */
    private function findUserIdByEmail($email) {
        global $userquery;
        
        if (isset($userquery) && method_exists($userquery, 'get_user_details')) {
            $user = $userquery->get_user_details(null, $email);
            return $user ? ($user['userid'] ?? null) : null;
        }
        
        return null;
    }
    
    /**
     * Get stats for admin dashboard
     * 
     * @return array
     */
    public function getStats() {
        return array(
            'referrers' => array(
                'total' => $this->db->countReferrers(),
                'approved' => $this->db->countReferrers('approved'),
                'pending' => $this->db->countReferrers('pending'),
                'rejected' => $this->db->countReferrers('rejected'),
                'disabled' => $this->db->countReferrers('disabled')
            )
        );
    }
    
    /**
     * Health check
     * 
     * @return array
     */
    public function healthCheck() {
        $health = array(
            'enabled' => $this->isEnabled(),
            'configured' => $this->isConfigured(),
            'api_auth' => false,
            'js_snippet' => !empty($this->getPublicApiKey()),
            'last_conversion' => null,
            'last_webhook' => null
        );
        
        // Test API auth
        if ($health['configured']) {
            $authTest = $this->client->testAuth();
            $health['api_auth'] = $authTest['authenticated'] ?? false;
            $health['api_error'] = $authTest['error'] ?? null;
        }
        
        // Get last conversion
        $conversions = $this->db->getRecentConversions(1);
        if (!empty($conversions)) {
            $health['last_conversion'] = $conversions[0]['created_at'];
        }
        
        // Get last webhook
        $events = $this->db->getRecentEvents(1);
        if (!empty($events)) {
            $health['last_webhook'] = $events[0]['created_at'];
        }
        
        // Store health check time
        $this->db->setSetting('last_health_check', date('Y-m-d H:i:s'));
        
        return $health;
    }
}
