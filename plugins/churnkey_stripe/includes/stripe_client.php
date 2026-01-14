<?php
/**
 * Stripe Client for Churnkey + Stripe Plugin
 * Provides Stripe API interaction via cURL (no SDK dependency)
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

/**
 * Class ChurnkeyStripeClient
 * Simple Stripe API client using cURL
 */
class ChurnkeyStripeClient {
    
    /**
     * @var string Stripe API base URL
     */
    const API_BASE = 'https://api.stripe.com/v1';
    
    /**
     * @var string Stripe Secret Key
     */
    private $secretKey;
    
    /**
     * @var self Singleton instance
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->secretKey = churnkey_stripe_get_stripe_key();
    }
    
    /**
     * Set API key (for testing or manual override)
     */
    public function setApiKey($key) {
        $this->secretKey = $key;
    }
    
    /**
     * Check if Stripe is configured
     */
    public function isConfigured() {
        return !empty($this->secretKey);
    }
    
    /**
     * Make an API request to Stripe
     * 
     * @param string $method HTTP method (GET, POST, DELETE)
     * @param string $endpoint API endpoint (without base URL)
     * @param array $params Request parameters
     * @return array Response array with 'success', 'data', 'error' keys
     */
    public function request($method, $endpoint, $params = array()) {
        if (!$this->isConfigured()) {
            return array(
                'success' => false,
                'error' => 'Stripe API key not configured'
            );
        }
        
        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        
        // Build query string for GET requests
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        
        $headers = array(
            'Authorization: Bearer ' . $this->secretKey,
            'Content-Type: application/x-www-form-urlencoded'
        );
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true
        ));
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            churnkey_stripe_log('error', 'Stripe API cURL error', array(
                'error' => $error,
                'endpoint' => $endpoint
            ));
            return array(
                'success' => false,
                'error' => 'Network error: ' . $error
            );
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return array(
                'success' => true,
                'data' => $data
            );
        } else {
            $errorMessage = 'Stripe API error';
            if (isset($data['error']['message'])) {
                $errorMessage = $data['error']['message'];
            }
            
            churnkey_stripe_log('error', 'Stripe API error', array(
                'http_code' => $httpCode,
                'error' => $errorMessage,
                'endpoint' => $endpoint
            ));
            
            return array(
                'success' => false,
                'error' => $errorMessage,
                'http_code' => $httpCode
            );
        }
    }
    
    /**
     * Search for a customer by email
     * 
     * @param string $email Customer email
     * @return array|null Customer data or null if not found
     */
    public function findCustomerByEmail($email) {
        $result = $this->request('GET', 'customers', array(
            'email' => $email,
            'limit' => 1
        ));
        
        if ($result['success'] && !empty($result['data']['data'])) {
            return $result['data']['data'][0];
        }
        
        return null;
    }
    
    /**
     * Get customer by ID
     * 
     * @param string $customerId Stripe customer ID
     * @return array|null Customer data or null if not found
     */
    public function getCustomer($customerId) {
        $result = $this->request('GET', 'customers/' . $customerId);
        
        if ($result['success']) {
            return $result['data'];
        }
        
        return null;
    }
    
    /**
     * Get customer's active subscriptions
     * 
     * @param string $customerId Stripe customer ID
     * @return array Array of subscriptions
     */
    public function getCustomerSubscriptions($customerId) {
        $result = $this->request('GET', 'subscriptions', array(
            'customer' => $customerId,
            'status' => 'active',
            'limit' => 10
        ));
        
        if ($result['success'] && isset($result['data']['data'])) {
            return $result['data']['data'];
        }
        
        return array();
    }
    
    /**
     * Get the first active subscription for a customer
     * 
     * @param string $customerId Stripe customer ID
     * @return array|null Subscription data or null if none found
     */
    public function getActiveSubscription($customerId) {
        $subscriptions = $this->getCustomerSubscriptions($customerId);
        
        if (!empty($subscriptions)) {
            return $subscriptions[0];
        }
        
        return null;
    }
    
    /**
     * Get subscription by ID
     * 
     * @param string $subscriptionId Stripe subscription ID
     * @return array|null Subscription data or null if not found
     */
    public function getSubscription($subscriptionId) {
        $result = $this->request('GET', 'subscriptions/' . $subscriptionId);
        
        if ($result['success']) {
            return $result['data'];
        }
        
        return null;
    }
    
    /**
     * Cancel a subscription
     * 
     * @param string $subscriptionId Stripe subscription ID
     * @param bool $immediately If true, cancel immediately; if false, cancel at period end
     * @return array|null Updated subscription data or null on error
     */
    public function cancelSubscription($subscriptionId, $immediately = false) {
        if ($immediately) {
            $result = $this->request('DELETE', 'subscriptions/' . $subscriptionId);
        } else {
            $result = $this->request('POST', 'subscriptions/' . $subscriptionId, array(
                'cancel_at_period_end' => 'true'
            ));
        }
        
        if ($result['success']) {
            return $result['data'];
        }
        
        return null;
    }
    
    /**
     * Create a customer
     * 
     * @param array $data Customer data (email, name, etc.)
     * @return array|null Created customer data or null on error
     */
    public function createCustomer($data) {
        $result = $this->request('POST', 'customers', $data);
        
        if ($result['success']) {
            return $result['data'];
        }
        
        return null;
    }
    
    /**
     * Update a customer
     * 
     * @param string $customerId Stripe customer ID
     * @param array $data Customer data to update
     * @return array|null Updated customer data or null on error
     */
    public function updateCustomer($customerId, $data) {
        $result = $this->request('POST', 'customers/' . $customerId, $data);
        
        if ($result['success']) {
            return $result['data'];
        }
        
        return null;
    }
}

/**
 * Get Stripe client instance (convenience function)
 */
function churnkey_stripe_client() {
    return ChurnkeyStripeClient::getInstance();
}

/**
 * Check if Stripe is configured
 */
function churnkey_stripe_is_stripe_configured() {
    return churnkey_stripe_client()->isConfigured();
}
