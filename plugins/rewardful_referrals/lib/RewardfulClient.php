<?php
/**
 * Rewardful Referrals - Rewardful API Client
 * 
 * HTTP client for Rewardful REST API
 * Uses Basic Auth with API Secret as username, blank password
 */

namespace RewardfulReferrals;

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

class RewardfulClient {
    
    /**
     * @var string Base URL for Rewardful API
     */
    const API_BASE_URL = 'https://api.getrewardful.com/v1';
    
    /**
     * @var string API Secret (used as Basic Auth username)
     */
    private $apiSecret;
    
    /**
     * @var Db
     */
    private $db;
    
    /**
     * @var int Request timeout in seconds
     */
    private $timeout = 30;
    
    /**
     * @var array Last response info
     */
    private $lastResponse = array();
    
    /**
     * Constructor
     * 
     * @param string|null $apiSecret
     */
    public function __construct($apiSecret = null) {
        $this->db = new Db();
        
        if ($apiSecret) {
            $this->apiSecret = $apiSecret;
        } else {
            $this->apiSecret = $this->db->getSetting('api_secret', '');
        }
    }
    
    /**
     * Set API Secret
     * 
     * @param string $secret
     */
    public function setApiSecret($secret) {
        $this->apiSecret = $secret;
    }
    
    /**
     * Check if API is configured
     * 
     * @return bool
     */
    public function isConfigured() {
        return !empty($this->apiSecret);
    }
    
    /**
     * Make API request
     * 
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response data
     */
    public function request($method, $endpoint, $data = array()) {
        if (!$this->isConfigured()) {
            return array(
                'success' => false,
                'error' => 'API not configured',
                'code' => 'not_configured'
            );
        }
        
        $url = self::API_BASE_URL . '/' . ltrim($endpoint, '/');
        
        // Initialize cURL
        $ch = curl_init();
        
        // Set common options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Basic Auth: API Secret as username, blank password
        curl_setopt($ch, CURLOPT_USERPWD, $this->apiSecret . ':');
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        
        // Set headers
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded'
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // Handle request method and data
        $method = strtoupper($method);
        
        switch ($method) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
                
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                }
                break;
                
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
                
            case 'GET':
            default:
                if (!empty($data)) {
                    $url .= '?' . http_build_query($data);
                }
                break;
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        
        curl_close($ch);
        
        // Store last response info
        $this->lastResponse = array(
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error,
            'errno' => $errno
        );
        
        // Log API call
        $this->db->log('api', $method . ' ' . $endpoint, 
            "HTTP {$httpCode}", 
            array(
                'url' => $url,
                'http_code' => $httpCode,
                'request_data' => $data,
                'response_length' => strlen($response)
            )
        );
        
        // Handle cURL errors
        if ($errno) {
            $this->db->log('error', 'api_curl_error', $error, array('errno' => $errno));
            return array(
                'success' => false,
                'error' => 'cURL error: ' . $error,
                'code' => 'curl_error',
                'errno' => $errno
            );
        }
        
        // Parse response
        $decoded = json_decode($response, true);
        
        // Check for HTTP errors
        if ($httpCode >= 400) {
            $errorMessage = isset($decoded['error']) ? $decoded['error'] : 'HTTP error ' . $httpCode;
            $this->db->log('error', 'api_http_error', $errorMessage, array(
                'http_code' => $httpCode,
                'response' => $decoded
            ));
            
            return array(
                'success' => false,
                'error' => $errorMessage,
                'code' => 'http_error',
                'http_code' => $httpCode,
                'data' => $decoded
            );
        }
        
        return array(
            'success' => true,
            'data' => $decoded,
            'http_code' => $httpCode
        );
    }
    
    /**
     * GET request
     */
    public function get($endpoint, $params = array()) {
        return $this->request('GET', $endpoint, $params);
    }
    
    /**
     * POST request
     */
    public function post($endpoint, $data = array()) {
        return $this->request('POST', $endpoint, $data);
    }
    
    /**
     * PUT request
     */
    public function put($endpoint, $data = array()) {
        return $this->request('PUT', $endpoint, $data);
    }
    
    /**
     * DELETE request
     */
    public function delete($endpoint, $data = array()) {
        return $this->request('DELETE', $endpoint, $data);
    }
    
    // ========================================
    // AFFILIATES
    // ========================================
    
    /**
     * List affiliates
     * 
     * @param array $params Optional params (page, limit, expand)
     * @return array
     */
    public function listAffiliates($params = array()) {
        return $this->get('affiliates', $params);
    }
    
    /**
     * Get affiliate by ID
     * 
     * @param string $affiliateId
     * @return array
     */
    public function getAffiliate($affiliateId) {
        return $this->get('affiliates/' . $affiliateId);
    }
    
    /**
     * Create affiliate
     * 
     * @param array $data (email required, first_name, last_name, campaign_id optional)
     * @return array
     */
    public function createAffiliate($data) {
        return $this->post('affiliates', $data);
    }
    
    /**
     * Update affiliate
     * 
     * @param string $affiliateId
     * @param array $data
     * @return array
     */
    public function updateAffiliate($affiliateId, $data) {
        return $this->put('affiliates/' . $affiliateId, $data);
    }
    
    /**
     * Get affiliate by email
     * 
     * @param string $email
     * @return array
     */
    public function getAffiliateByEmail($email) {
        $result = $this->listAffiliates(array('email' => $email));
        
        if ($result['success'] && !empty($result['data'])) {
            // Rewardful may return an array of affiliates
            $affiliates = $result['data'];
            if (isset($affiliates['data'])) {
                $affiliates = $affiliates['data'];
            }
            
            foreach ($affiliates as $affiliate) {
                if (strtolower($affiliate['email']) === strtolower($email)) {
                    return array(
                        'success' => true,
                        'data' => $affiliate
                    );
                }
            }
        }
        
        return array(
            'success' => false,
            'error' => 'Affiliate not found',
            'code' => 'not_found'
        );
    }
    
    /**
     * Create or get affiliate by email
     * 
     * @param string $email
     * @param array $data Additional affiliate data
     * @return array
     */
    public function ensureAffiliate($email, $data = array()) {
        // First try to find existing
        $existing = $this->getAffiliateByEmail($email);
        
        if ($existing['success']) {
            return $existing;
        }
        
        // Create new affiliate
        $data['email'] = $email;
        return $this->createAffiliate($data);
    }
    
    // ========================================
    // SSO
    // ========================================
    
    /**
     * Generate SSO magic link for affiliate
     * 
     * @param string $affiliateId
     * @param string $redirectTo Optional redirect path after login
     * @return array
     */
    public function generateSSOLink($affiliateId, $redirectTo = null) {
        $data = array();
        if ($redirectTo) {
            $data['redirect_to'] = $redirectTo;
        }
        
        return $this->post('affiliates/' . $affiliateId . '/sso', $data);
    }
    
    // ========================================
    // CAMPAIGNS
    // ========================================
    
    /**
     * List campaigns
     * 
     * @return array
     */
    public function listCampaigns() {
        return $this->get('campaigns');
    }
    
    /**
     * Get campaign by ID
     * 
     * @param string $campaignId
     * @return array
     */
    public function getCampaign($campaignId) {
        return $this->get('campaigns/' . $campaignId);
    }
    
    // ========================================
    // REFERRALS
    // ========================================
    
    /**
     * List referrals
     * 
     * @param array $params (affiliate_id, conversion_state, etc.)
     * @return array
     */
    public function listReferrals($params = array()) {
        return $this->get('referrals', $params);
    }
    
    /**
     * Get referral by ID
     * 
     * @param string $referralId
     * @return array
     */
    public function getReferral($referralId) {
        return $this->get('referrals/' . $referralId);
    }
    
    // ========================================
    // COMMISSIONS
    // ========================================
    
    /**
     * List commissions
     * 
     * @param array $params
     * @return array
     */
    public function listCommissions($params = array()) {
        return $this->get('commissions', $params);
    }
    
    // ========================================
    // HEALTH CHECK
    // ========================================
    
    /**
     * Test API authentication
     * 
     * @return array
     */
    public function testAuth() {
        // Try to list campaigns as a simple auth test
        $result = $this->listCampaigns();
        
        return array(
            'success' => $result['success'],
            'authenticated' => $result['success'],
            'error' => $result['error'] ?? null,
            'http_code' => $result['http_code'] ?? null
        );
    }
    
    /**
     * Get last response info
     * 
     * @return array
     */
    public function getLastResponse() {
        return $this->lastResponse;
    }
}
