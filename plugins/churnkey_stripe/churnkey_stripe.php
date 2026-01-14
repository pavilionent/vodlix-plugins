<?php
/**
 * Plugin Name: Churnkey + Stripe Cancel Flow
 * Description: Integrates Churnkey with Stripe to handle subscription cancellations via Churnkey modal on /account/ page
 * Version: 1.0.0
 * Author: Vodlix
 * Plugin Folder: churnkey_stripe
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    exit('No direct script access allowed');
}

// Plugin constants
define('CHURNKEY_STRIPE_VERSION', '1.0.0');
define('CHURNKEY_STRIPE_DIR', dirname(__FILE__));
define('CHURNKEY_STRIPE_INCLUDES', CHURNKEY_STRIPE_DIR . '/includes');
define('CHURNKEY_STRIPE_ADMIN', CHURNKEY_STRIPE_DIR . '/admin');
define('CHURNKEY_STRIPE_API', CHURNKEY_STRIPE_DIR . '/api');
define('CHURNKEY_STRIPE_JS', CHURNKEY_STRIPE_DIR . '/js');

// Include required files
require_once CHURNKEY_STRIPE_INCLUDES . '/db_helper.php';
require_once CHURNKEY_STRIPE_INCLUDES . '/settings.php';
require_once CHURNKEY_STRIPE_INCLUDES . '/user.php';
require_once CHURNKEY_STRIPE_INCLUDES . '/stripe_client.php';
require_once CHURNKEY_STRIPE_INCLUDES . '/churnkey.php';

/**
 * Initialize the Churnkey Stripe plugin
 */
function churnkey_stripe_init() {
    global $Cbucket;
    
    // Register admin menu
    if (function_exists('add_admin_menu')) {
        add_admin_menu(array(
            'title' => 'Churnkey + Stripe',
            'link' => 'admin_churnkey_stripe',
            'parent' => 'Payments',
            'icon' => 'fa fa-credit-card',
            'permission' => 'admin_access'
        ));
    }
    
    // Register admin page handler
    if (function_exists('register_admin_page')) {
        register_admin_page('admin_churnkey_stripe', array(
            'title' => 'Churnkey + Stripe Settings',
            'file' => CHURNKEY_STRIPE_ADMIN . '/settings.php'
        ));
    }
    
    // Register the context API endpoint
    churnkey_stripe_register_api_routes();
    
    // Inject JS on frontend pages
    churnkey_stripe_inject_frontend_js();
}

/**
 * Register API routes/endpoints
 */
function churnkey_stripe_register_api_routes() {
    // Register custom API endpoint if ClipBucket supports it
    if (function_exists('register_api_endpoint')) {
        register_api_endpoint('churnkey/context', array(
            'callback' => 'churnkey_stripe_api_context',
            'methods' => 'GET',
            'auth' => true
        ));
    }
}

/**
 * Inject JavaScript on frontend pages
 * This will inject the cancel button on /account/ page
 */
function churnkey_stripe_inject_frontend_js() {
    // Hook into footer output for frontend pages
    if (function_exists('add_action')) {
        add_action('footer', 'churnkey_stripe_render_account_js');
    }
    
    // Also register with template system if available
    if (function_exists('register_anchor_function')) {
        register_anchor_function('churnkey_stripe_render_account_js', 'global_footer');
    }
    
    // Fallback: Use output_footer hook
    if (function_exists('add_output_footer')) {
        add_output_footer('churnkey_stripe_render_account_js');
    }
}

/**
 * Render the account page JavaScript for cancel button injection
 * This outputs inline JS that will inject the cancel button on /account/
 */
function churnkey_stripe_render_account_js() {
    // Only render for logged-in users
    if (!churnkey_stripe_is_user_logged_in()) {
        return;
    }
    
    // Get settings
    $settings = churnkey_stripe_get_all_settings();
    $app_id = isset($settings['churnkey_app_id']) ? $settings['churnkey_app_id'] : '';
    
    // Don't render if not configured
    if (empty($app_id)) {
        return;
    }
    
    // Get plugin URL for API endpoint
    $plugin_url = churnkey_stripe_get_plugin_url();
    $context_url = $plugin_url . '/api/context.php';
    
    // Output the inline JavaScript
    ?>
    <script type="text/javascript">
    (function() {
        'use strict';
        
        // Configuration
        var CHURNKEY_APP_ID = <?php echo json_encode($app_id); ?>;
        var CONTEXT_API_URL = <?php echo json_encode($context_url); ?>;
        
        // Only run on /account/ page
        var path = window.location.pathname;
        if (path !== '/account/' && path !== '/account' && !path.match(/\/account\/?$/)) {
            return;
        }
        
        // Wait for DOM ready
        function ready(fn) {
            if (document.readyState !== 'loading') {
                fn();
            } else {
                document.addEventListener('DOMContentLoaded', fn);
            }
        }
        
        // Load Churnkey script
        function loadChurnkeyScript(callback) {
            if (window.churnkey && typeof window.churnkey.init === 'function') {
                callback();
                return;
            }
            
            var script = document.createElement('script');
            script.src = 'https://assets.churnkey.co/js/app.js?appId=' + encodeURIComponent(CHURNKEY_APP_ID);
            script.async = true;
            script.onload = function() {
                // Wait a bit for churnkey to initialize
                var checkInterval = setInterval(function() {
                    if (window.churnkey && typeof window.churnkey.init === 'function') {
                        clearInterval(checkInterval);
                        callback();
                    }
                }, 100);
                
                // Timeout after 10 seconds
                setTimeout(function() {
                    clearInterval(checkInterval);
                }, 10000);
            };
            script.onerror = function() {
                console.error('ChurnkeyStripe: Failed to load Churnkey script');
            };
            document.head.appendChild(script);
        }
        
        // Fetch context from server
        function fetchContext(callback) {
            var xhr = new XMLHttpRequest();
            xhr.open('GET', CONTEXT_API_URL, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.withCredentials = true;
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var data = JSON.parse(xhr.responseText);
                            if (data.success && data.context) {
                                callback(null, data.context);
                            } else {
                                callback(new Error(data.error || 'Unknown error'));
                            }
                        } catch (e) {
                            callback(new Error('Failed to parse response'));
                        }
                    } else {
                        callback(new Error('Request failed: ' + xhr.status));
                    }
                }
            };
            
            xhr.send();
        }
        
        // Launch Churnkey modal
        function launchChurnkey(context) {
            loadChurnkeyScript(function() {
                if (!window.churnkey || typeof window.churnkey.init !== 'function') {
                    alert('Unable to load cancellation flow. Please try again.');
                    return;
                }
                
                window.churnkey.init('show', {
                    customerId: context.customerId,
                    authHash: context.authHash,
                    subscriptionId: context.subscriptionId,
                    appId: context.appId,
                    mode: context.mode,
                    provider: 'stripe',
                    record: context.record
                });
            });
        }
        
        // Find and inject cancel button
        function injectCancelButton() {
            // Multiple strategies to find the "Select Plan" button
            var selectPlanBtn = null;
            var container = null;
            
            // Strategy 1: Find by button text
            var buttons = document.querySelectorAll('button, a.btn, .btn, input[type="button"], input[type="submit"]');
            for (var i = 0; i < buttons.length; i++) {
                var btn = buttons[i];
                var text = (btn.textContent || btn.innerText || btn.value || '').trim().toLowerCase();
                if (text === 'select plan' || text === 'change plan' || text === 'manage plan') {
                    selectPlanBtn = btn;
                    break;
                }
            }
            
            // Strategy 2: Look in membership/billing section
            if (!selectPlanBtn) {
                var sections = document.querySelectorAll('.membership, .billing, [class*="membership"], [class*="billing"], [id*="membership"], [id*="billing"]');
                for (var j = 0; j < sections.length; j++) {
                    var btns = sections[j].querySelectorAll('button, a.btn, .btn');
                    if (btns.length > 0) {
                        selectPlanBtn = btns[0];
                        break;
                    }
                }
            }
            
            // Strategy 3: Look for common subscription management areas
            if (!selectPlanBtn) {
                var planContainers = document.querySelectorAll('.subscription-actions, .plan-actions, .membership-actions, .billing-actions');
                for (var k = 0; k < planContainers.length; k++) {
                    var actionBtns = planContainers[k].querySelectorAll('button, a');
                    if (actionBtns.length > 0) {
                        selectPlanBtn = actionBtns[0];
                        break;
                    }
                }
            }
            
            if (!selectPlanBtn) {
                console.log('ChurnkeyStripe: Could not find Select Plan button to inject cancel button');
                // Fallback: append to body or a common container
                container = document.querySelector('.account-settings, .settings-container, .user-settings, main, .content');
            } else {
                container = selectPlanBtn.parentElement;
            }
            
            // Check if cancel button already exists
            if (document.getElementById('churnkey-cancel-btn')) {
                return;
            }
            
            // Create cancel button
            var cancelBtn = document.createElement('button');
            cancelBtn.id = 'churnkey-cancel-btn';
            cancelBtn.type = 'button';
            cancelBtn.textContent = 'Cancel Subscription';
            
            // Copy classes from Select Plan button for consistent styling
            if (selectPlanBtn) {
                cancelBtn.className = selectPlanBtn.className;
                // Add some distinction
                cancelBtn.classList.add('cancel-subscription-btn');
                cancelBtn.style.marginLeft = '10px';
            } else {
                // Default styling
                cancelBtn.className = 'btn btn-danger cancel-subscription-btn';
                cancelBtn.style.cssText = 'padding: 10px 20px; margin: 10px; cursor: pointer;';
            }
            
            // Add click handler
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                cancelBtn.disabled = true;
                cancelBtn.textContent = 'Loading...';
                
                fetchContext(function(err, context) {
                    if (err) {
                        cancelBtn.disabled = false;
                        cancelBtn.textContent = 'Cancel Subscription';
                        
                        if (err.message.indexOf('No active subscription') !== -1) {
                            alert('You do not have an active subscription to cancel.');
                        } else if (err.message.indexOf('Not logged in') !== -1) {
                            alert('Please log in to manage your subscription.');
                        } else {
                            alert('Unable to load cancellation flow. Please contact support.');
                            console.error('ChurnkeyStripe error:', err.message);
                        }
                        return;
                    }
                    
                    cancelBtn.disabled = false;
                    cancelBtn.textContent = 'Cancel Subscription';
                    launchChurnkey(context);
                });
            });
            
            // Insert the button
            if (selectPlanBtn && selectPlanBtn.parentElement) {
                selectPlanBtn.parentElement.insertBefore(cancelBtn, selectPlanBtn.nextSibling);
            } else if (container) {
                container.appendChild(cancelBtn);
            } else {
                // Last resort: append to body
                document.body.appendChild(cancelBtn);
            }
        }
        
        // Initialize on DOM ready
        ready(function() {
            // Small delay to ensure page is fully rendered
            setTimeout(injectCancelButton, 500);
        });
    })();
    </script>
    <?php
}

/**
 * Get the plugin URL
 */
function churnkey_stripe_get_plugin_url() {
    global $Cbucket;
    
    // Try to get base URL from ClipBucket
    $base_url = '';
    if (isset($Cbucket->baseurl)) {
        $base_url = rtrim($Cbucket->baseurl, '/');
    } elseif (defined('BASEURL')) {
        $base_url = rtrim(BASEURL, '/');
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
    }
    
    return $base_url . '/plugins/churnkey_stripe';
}

/**
 * Plugin activation callback
 */
function churnkey_stripe_activate() {
    // Run installation
    require_once CHURNKEY_STRIPE_DIR . '/install.php';
    churnkey_stripe_install();
}

/**
 * Plugin deactivation callback
 */
function churnkey_stripe_deactivate() {
    // Run uninstallation
    require_once CHURNKEY_STRIPE_DIR . '/uninstall.php';
    churnkey_stripe_uninstall();
}

// Initialize plugin
churnkey_stripe_init();

// Register activation/deactivation hooks if available
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'churnkey_stripe_activate');
}
if (function_exists('register_deactivation_hook')) {
    register_deactivation_hook(__FILE__, 'churnkey_stripe_deactivate');
}
