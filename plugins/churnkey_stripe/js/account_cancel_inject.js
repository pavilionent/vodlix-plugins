/**
 * Churnkey + Stripe Account Page Cancel Button Injection
 * This script is injected on the /account/ page to add the cancel subscription button
 * 
 * Configuration (set by PHP):
 * - CHURNKEY_APP_ID: Churnkey application ID
 * - CHURNKEY_CONTEXT_URL: URL to fetch signed context from
 * 
 * @package ChurnkeyStripe
 */
(function() {
    'use strict';
    
    // Configuration - these should be set by the PHP template
    var config = window.ChurnkeyStripeConfig || {};
    var CHURNKEY_APP_ID = config.appId || '';
    var CONTEXT_API_URL = config.contextUrl || '/plugins/churnkey_stripe/api/context.php';
    
    // Exit early if not configured
    if (!CHURNKEY_APP_ID) {
        console.warn('ChurnkeyStripe: App ID not configured');
        return;
    }
    
    // Only run on /account/ page
    var path = window.location.pathname;
    if (path !== '/account/' && path !== '/account' && !path.match(/\/account\/?$/)) {
        return;
    }
    
    /**
     * Wait for DOM ready
     */
    function ready(fn) {
        if (document.readyState !== 'loading') {
            fn();
        } else {
            document.addEventListener('DOMContentLoaded', fn);
        }
    }
    
    /**
     * Load Churnkey script dynamically
     */
    function loadChurnkeyScript(callback) {
        // Check if already loaded
        if (window.churnkey && typeof window.churnkey.init === 'function') {
            callback();
            return;
        }
        
        var script = document.createElement('script');
        script.src = 'https://assets.churnkey.co/js/app.js?appId=' + encodeURIComponent(CHURNKEY_APP_ID);
        script.async = true;
        
        script.onload = function() {
            // Poll for churnkey to be available
            var attempts = 0;
            var maxAttempts = 100;
            var checkInterval = setInterval(function() {
                attempts++;
                if (window.churnkey && typeof window.churnkey.init === 'function') {
                    clearInterval(checkInterval);
                    callback();
                } else if (attempts >= maxAttempts) {
                    clearInterval(checkInterval);
                    console.error('ChurnkeyStripe: Churnkey failed to initialize');
                }
            }, 100);
        };
        
        script.onerror = function() {
            console.error('ChurnkeyStripe: Failed to load Churnkey script');
        };
        
        document.head.appendChild(script);
    }
    
    /**
     * Fetch context from server
     */
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
                } else if (xhr.status === 401) {
                    callback(new Error('Not logged in'));
                } else if (xhr.status === 404) {
                    callback(new Error('No active subscription found'));
                } else {
                    callback(new Error('Request failed: ' + xhr.status));
                }
            }
        };
        
        xhr.send();
    }
    
    /**
     * Launch Churnkey cancel flow modal
     */
    function launchChurnkey(context) {
        loadChurnkeyScript(function() {
            if (!window.churnkey || typeof window.churnkey.init !== 'function') {
                alert('Unable to load cancellation flow. Please try again or contact support.');
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
    
    /**
     * Handle cancel button click
     */
    function handleCancelClick(btn) {
        var originalText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Loading...';
        
        fetchContext(function(err, context) {
            btn.disabled = false;
            btn.textContent = originalText;
            
            if (err) {
                var message = err.message;
                
                if (message.indexOf('No active subscription') !== -1) {
                    alert('You do not have an active subscription to cancel.');
                } else if (message.indexOf('Not logged in') !== -1) {
                    alert('Please log in to manage your subscription.');
                    window.location.href = '/login';
                } else {
                    alert('Unable to load cancellation flow. Please try again or contact support.');
                    console.error('ChurnkeyStripe error:', message);
                }
                return;
            }
            
            launchChurnkey(context);
        });
    }
    
    /**
     * Find the Select Plan button using multiple strategies
     */
    function findSelectPlanButton() {
        var buttons = document.querySelectorAll('button, a.btn, .btn, input[type="button"], input[type="submit"]');
        
        // Strategy 1: Find by exact or partial text match
        var textMatches = ['select plan', 'change plan', 'manage plan', 'upgrade plan', 'plan'];
        
        for (var i = 0; i < buttons.length; i++) {
            var btn = buttons[i];
            var text = (btn.textContent || btn.innerText || btn.value || '').trim().toLowerCase();
            
            for (var j = 0; j < textMatches.length; j++) {
                if (text === textMatches[j] || text.indexOf(textMatches[j]) !== -1) {
                    return btn;
                }
            }
        }
        
        // Strategy 2: Look in membership/billing sections
        var sectionSelectors = [
            '.membership', '.billing', '.subscription',
            '[class*="membership"]', '[class*="billing"]', '[class*="subscription"]',
            '[id*="membership"]', '[id*="billing"]', '[id*="subscription"]',
            '.plan-section', '.account-plan', '.user-plan'
        ];
        
        for (var k = 0; k < sectionSelectors.length; k++) {
            var sections = document.querySelectorAll(sectionSelectors[k]);
            for (var l = 0; l < sections.length; l++) {
                var btns = sections[l].querySelectorAll('button, a.btn, .btn');
                if (btns.length > 0) {
                    return btns[0];
                }
            }
        }
        
        // Strategy 3: Look for common action container classes
        var actionSelectors = [
            '.subscription-actions', '.plan-actions', '.membership-actions',
            '.billing-actions', '.account-actions', '.user-actions'
        ];
        
        for (var m = 0; m < actionSelectors.length; m++) {
            var containers = document.querySelectorAll(actionSelectors[m]);
            for (var n = 0; n < containers.length; n++) {
                var actionBtns = containers[n].querySelectorAll('button, a');
                if (actionBtns.length > 0) {
                    return actionBtns[0];
                }
            }
        }
        
        return null;
    }
    
    /**
     * Find a suitable container for the cancel button
     */
    function findContainer() {
        var containerSelectors = [
            '.account-settings', '.settings-container', '.user-settings',
            '.membership-section', '.billing-section', '.subscription-section',
            'main', '.main-content', '.content', '#content'
        ];
        
        for (var i = 0; i < containerSelectors.length; i++) {
            var container = document.querySelector(containerSelectors[i]);
            if (container) {
                return container;
            }
        }
        
        return document.body;
    }
    
    /**
     * Inject the cancel button
     */
    function injectCancelButton() {
        // Check if already injected
        if (document.getElementById('churnkey-cancel-subscription-btn')) {
            return;
        }
        
        var selectPlanBtn = findSelectPlanButton();
        var container = selectPlanBtn ? selectPlanBtn.parentElement : findContainer();
        
        // Create cancel button
        var cancelBtn = document.createElement('button');
        cancelBtn.id = 'churnkey-cancel-subscription-btn';
        cancelBtn.type = 'button';
        cancelBtn.textContent = 'Cancel Subscription';
        
        // Copy styling from Select Plan button if found
        if (selectPlanBtn) {
            cancelBtn.className = selectPlanBtn.className;
            // Ensure it has the cancel-specific class
            if (cancelBtn.className.indexOf('cancel-subscription') === -1) {
                cancelBtn.className += ' cancel-subscription-btn';
            }
            cancelBtn.style.marginLeft = '10px';
        } else {
            // Default styling
            cancelBtn.className = 'btn btn-outline-danger cancel-subscription-btn';
            cancelBtn.style.cssText = 'padding: 10px 20px; margin: 10px 0; cursor: pointer; border: 1px solid #dc3545; color: #dc3545; background: transparent; border-radius: 4px;';
        }
        
        // Add hover effect
        cancelBtn.addEventListener('mouseenter', function() {
            if (!selectPlanBtn) {
                this.style.background = '#dc3545';
                this.style.color = '#fff';
            }
        });
        cancelBtn.addEventListener('mouseleave', function() {
            if (!selectPlanBtn) {
                this.style.background = 'transparent';
                this.style.color = '#dc3545';
            }
        });
        
        // Add click handler
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            handleCancelClick(this);
        });
        
        // Insert button
        if (selectPlanBtn && selectPlanBtn.parentElement) {
            // Insert after Select Plan button
            if (selectPlanBtn.nextSibling) {
                selectPlanBtn.parentElement.insertBefore(cancelBtn, selectPlanBtn.nextSibling);
            } else {
                selectPlanBtn.parentElement.appendChild(cancelBtn);
            }
            console.log('ChurnkeyStripe: Cancel button injected next to Select Plan button');
        } else if (container) {
            // Append to container
            container.appendChild(cancelBtn);
            console.log('ChurnkeyStripe: Cancel button injected into container');
        }
    }
    
    // Initialize on DOM ready
    ready(function() {
        // Small delay to ensure page is fully rendered
        setTimeout(injectCancelButton, 500);
        
        // Also watch for dynamic content changes (SPA support)
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                // Debounce
                clearTimeout(window._churnkeyInjectTimeout);
                window._churnkeyInjectTimeout = setTimeout(function() {
                    if (!document.getElementById('churnkey-cancel-subscription-btn')) {
                        injectCancelButton();
                    }
                }, 200);
            });
            
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    });
})();
