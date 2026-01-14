<?php
/**
 * Rewardful Referrals - ClipBucket Hooks Integration
 * 
 * Handles hook registration and template injection for ClipBucket
 */

namespace RewardfulReferrals;

if (!defined('BASEDIR')) {
    die('Direct access not allowed');
}

class ClipBucketHooks {
    
    /**
     * @var Db
     */
    private $db;
    
    /**
     * @var Auth
     */
    private $auth;
    
    /**
     * @var RewardfulService
     */
    private $service;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new Db();
        $this->auth = new Auth();
        $this->service = new RewardfulService();
    }
    
    /**
     * Register all hooks with ClipBucket
     */
    public function register() {
        // Register head injection for JS snippet
        $this->registerHeadHook();
        
        // Register footer injection for conversion tracking
        $this->registerFooterHook();
        
        // Register account page injection
        $this->registerAccountHook();
        
        // Capture via token from URL
        $this->captureViaToken();
        
        // Register admin pages
        $this->registerAdminPages();
    }
    
    /**
     * Register head hook for Rewardful JS snippet
     */
    private function registerHeadHook() {
        // Use ClipBucket's hook system
        if (function_exists('add_action')) {
            add_action('head', array($this, 'injectHeadScript'));
        }
        
        // Alternative: register with cb_header_includes
        if (function_exists('register_header_file')) {
            // This would be called from template
        }
        
        // Start output buffering to inject into head
        if (!$this->service->isEnabled()) {
            return;
        }
        
        // Store callback for later use in templates
        $GLOBALS['rewardful_head_callback'] = array($this, 'injectHeadScript');
    }
    
    /**
     * Inject Rewardful JavaScript into head
     */
    public function injectHeadScript() {
        if (!$this->service->isEnabled()) {
            return;
        }
        
        $publicKey = $this->service->getPublicApiKey();
        
        if (empty($publicKey)) {
            return;
        }
        
        echo $this->getHeadScriptHtml($publicKey);
    }
    
    /**
     * Get Rewardful head script HTML
     * 
     * @param string $publicKey
     * @return string
     */
    public function getHeadScriptHtml($publicKey) {
        $html = <<<HTML
<!-- Rewardful Referrals Tracking -->
<script>(function(w,r){w._rwq=r;w[r]=w[r]||function(){(w[r].q=w[r].q||[]).push(arguments)}})(window,'rewardful');</script>
<script async src="https://r.wdfl.co/rw.js" data-rewardful="{$publicKey}"></script>
<!-- End Rewardful Tracking -->

HTML;
        return $html;
    }
    
    /**
     * Register footer hook for conversion tracking
     */
    private function registerFooterHook() {
        if (function_exists('add_action')) {
            add_action('footer', array($this, 'injectFooterScript'));
        }
        
        $GLOBALS['rewardful_footer_callback'] = array($this, 'injectFooterScript');
    }
    
    /**
     * Inject conversion tracking script into footer
     */
    public function injectFooterScript() {
        if (!$this->service->isEnabled()) {
            return;
        }
        
        // Check if we should fire conversion on this page
        if ($this->shouldFireConversion()) {
            echo $this->getConversionScript();
        }
        
        // Add account page injection script
        if ($this->isAccountPage()) {
            echo $this->getAccountInjectionScript();
        }
    }
    
    /**
     * Check if conversion should be fired on current page
     * 
     * @return bool
     */
    private function shouldFireConversion() {
        global $userquery;
        
        // Check if already converted in this session
        if (isset($_SESSION['rewardful_converted'])) {
            return false;
        }
        
        // Get current page/URL
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check manual override URLs
        $successPatterns = $this->db->getSetting('success_url_patterns', '');
        if (!empty($successPatterns)) {
            $patterns = array_filter(array_map('trim', explode("\n", $successPatterns)));
            foreach ($patterns as $pattern) {
                if (strpos($currentUrl, $pattern) !== false) {
                    return true;
                }
            }
        }
        
        // Auto-detect success pages
        if ($this->db->getSetting('auto_detect_success_pages', '1') === '1') {
            // Common success page patterns
            $successIndicators = array(
                'subscription/success',
                'payment/success',
                'payment/complete',
                'checkout/success',
                'order/complete',
                'thank-you',
                'thankyou',
                'subscription-activated'
            );
            
            foreach ($successIndicators as $indicator) {
                if (stripos($currentUrl, $indicator) !== false) {
                    return true;
                }
            }
            
            // Check for success GET parameter
            if (isset($_GET['subscription_success']) || isset($_GET['payment_success'])) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get conversion tracking script
     * 
     * @return string
     */
    private function getConversionScript() {
        $email = $this->auth->getCurrentUserEmail();
        
        if (!$email) {
            return '';
        }
        
        // Record conversion in DB
        $result = $this->service->recordConversion(array(
            'email' => $email,
            'userid' => $this->auth->getCurrentUserId(),
            'subscription_id' => $_GET['subscription_id'] ?? null,
            'order_id' => $_GET['order_id'] ?? null
        ));
        
        if (!$result['success'] || !empty($result['duplicate'])) {
            return '';
        }
        
        $conversionId = $result['conversion_id'];
        $escapedEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        
        // Mark as converted in session
        $_SESSION['rewardful_converted'] = true;
        
        $html = <<<HTML
<!-- Rewardful Conversion Tracking -->
<script>
(function() {
    if (typeof rewardful === 'function') {
        rewardful('convert', { email: '{$escapedEmail}' });
        
        // Notify server that conversion was sent
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/plugins/rewardful_referrals/api/convert_ping.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('conversion_id={$conversionId}');
    }
})();
</script>
<!-- End Rewardful Conversion -->

HTML;
        
        return $html;
    }
    
    /**
     * Check if current page is the account page
     * 
     * @return bool
     */
    private function isAccountPage() {
        $currentUrl = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check various account page patterns
        $accountPatterns = array(
            '/account',
            '/my_account',
            '/myaccount',
            '/user/account'
        );
        
        foreach ($accountPatterns as $pattern) {
            if (strpos($currentUrl, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get account page injection script
     * 
     * @return string
     */
    private function getAccountInjectionScript() {
        // Only inject for approved referrers or admins
        if (!$this->auth->canViewReferrals()) {
            return '';
        }
        
        $referrer = $this->auth->getCurrentReferrer();
        $referralLink = '';
        $token = '';
        
        if ($referrer) {
            $linkResult = $this->service->getReferralLink($referrer['id']);
            if ($linkResult['success']) {
                $referralLink = $linkResult['link'];
                $token = $linkResult['token'];
            }
        }
        
        $escapedLink = htmlspecialchars($referralLink, ENT_QUOTES, 'UTF-8');
        $ssoUrl = REWARDFUL_PLUGIN_URL . '/api/referrer_sso.php';
        
        $html = <<<HTML
<!-- Rewardful Account Section Injection -->
<script>
(function() {
    function injectReferralsSection() {
        // Find a good injection point on the account page
        var containers = [
            '.account-sidebar',
            '.user-sidebar', 
            '.my-account-menu',
            '#account-menu',
            '.account-content',
            '.user-content',
            '#main-content'
        ];
        
        var container = null;
        for (var i = 0; i < containers.length; i++) {
            container = document.querySelector(containers[i]);
            if (container) break;
        }
        
        if (!container) {
            // Fallback: prepend to body
            container = document.body;
        }
        
        var section = document.createElement('div');
        section.id = 'rewardful-referrals-section';
        section.className = 'rewardful-referrals-widget';
        section.innerHTML = `
            <div class="referrals-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; padding: 20px; margin: 15px 0; color: white; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                <h3 style="margin: 0 0 15px 0; font-size: 18px; display: flex; align-items: center;">
                    <svg style="width: 24px; height: 24px; margin-right: 10px;" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2M21 9V7L15 10L21 13V11C21 11 18.5 10 18 10C17.5 10 17 10.25 17 10.25V7.75C17 7.75 17.5 8 18 8C18.5 8 21 7 21 7V9M10 22V16H8L12 8L16 16H14V22H10Z"/>
                    </svg>
                    Referral Program
                </h3>
                <p style="margin: 0 0 15px 0; opacity: 0.9; font-size: 14px;">Share your link and earn rewards for every successful referral!</p>
                
                <div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                    <label style="font-size: 12px; opacity: 0.8; display: block; margin-bottom: 5px;">Your Referral Link:</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="text" id="referral-link-input" value="{$escapedLink}" readonly 
                            style="flex: 1; padding: 8px; border: none; border-radius: 4px; font-size: 13px; background: white; color: #333;">
                        <button onclick="copyReferralLink()" 
                            style="padding: 8px 16px; background: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; color: #667eea;">
                            Copy
                        </button>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="/my_account/referrals" 
                        style="flex: 1; text-align: center; padding: 10px 15px; background: rgba(255,255,255,0.2); border-radius: 6px; text-decoration: none; color: white; font-size: 14px;">
                        View Details
                    </a>
                    <a href="{$ssoUrl}" target="_blank"
                        style="flex: 1; text-align: center; padding: 10px 15px; background: white; border-radius: 6px; text-decoration: none; color: #667eea; font-size: 14px; font-weight: bold;">
                        Open Dashboard
                    </a>
                </div>
            </div>
        `;
        
        // Insert at appropriate location
        if (container.classList.contains('account-sidebar') || 
            container.classList.contains('user-sidebar') ||
            container.classList.contains('my-account-menu')) {
            container.appendChild(section);
        } else {
            container.insertBefore(section, container.firstChild);
        }
    }
    
    window.copyReferralLink = function() {
        var input = document.getElementById('referral-link-input');
        if (input) {
            input.select();
            input.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Show feedback
            var btn = input.nextElementSibling;
            var originalText = btn.textContent;
            btn.textContent = 'Copied!';
            btn.style.background = '#10b981';
            btn.style.color = 'white';
            
            setTimeout(function() {
                btn.textContent = originalText;
                btn.style.background = 'white';
                btn.style.color = '#667eea';
            }, 2000);
        }
    };
    
    // Inject when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectReferralsSection);
    } else {
        injectReferralsSection();
    }
})();
</script>
<link rel="stylesheet" href="/plugins/rewardful_referrals/assets/css/referrals.css">
<!-- End Rewardful Account Section -->

HTML;
        
        return $html;
    }
    
    /**
     * Capture via token from URL
     */
    private function captureViaToken() {
        if (isset($_GET['via']) && !empty($_GET['via'])) {
            $this->auth->storeViaToken($_GET['via']);
            
            $this->db->log('info', 'via_token_captured', 
                "Via token captured: " . $_GET['via'], 
                array('via' => $_GET['via'])
            );
        }
    }
    
    /**
     * Register admin pages
     */
    private function registerAdminPages() {
        global $admin_pages;
        
        if (!isset($admin_pages)) {
            $admin_pages = array();
        }
        
        // Register admin page handlers
        $admin_pages['rewardful_settings'] = array(
            'title' => 'Rewardful Settings',
            'file' => REWARDFUL_PLUGIN_DIR . '/admin/settings.php'
        );
        
        $admin_pages['rewardful_referrers'] = array(
            'title' => 'Manage Referrers',
            'file' => REWARDFUL_PLUGIN_DIR . '/admin/referrers.php'
        );
        
        $admin_pages['rewardful_logs'] = array(
            'title' => 'Rewardful Logs',
            'file' => REWARDFUL_PLUGIN_DIR . '/admin/logs.php'
        );
        
        $admin_pages['rewardful_health'] = array(
            'title' => 'Rewardful Health',
            'file' => REWARDFUL_PLUGIN_DIR . '/admin/health.php'
        );
    }
    
    /**
     * Get admin pages array for ClipBucket admin routing
     * 
     * @return array
     */
    public function getAdminPages() {
        return array(
            'rewardful_settings' => array(
                'title' => 'Rewardful Settings',
                'file' => REWARDFUL_PLUGIN_DIR . '/admin/settings.php',
                'menu' => 'Tool Box'
            ),
            'rewardful_referrers' => array(
                'title' => 'Manage Referrers',
                'file' => REWARDFUL_PLUGIN_DIR . '/admin/referrers.php',
                'menu' => 'Tool Box'
            ),
            'rewardful_logs' => array(
                'title' => 'Rewardful Logs',
                'file' => REWARDFUL_PLUGIN_DIR . '/admin/logs.php',
                'menu' => 'Tool Box'
            ),
            'rewardful_health' => array(
                'title' => 'Rewardful Health',
                'file' => REWARDFUL_PLUGIN_DIR . '/admin/health.php',
                'menu' => 'Tool Box'
            )
        );
    }
}
