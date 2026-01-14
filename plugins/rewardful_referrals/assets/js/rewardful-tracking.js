/**
 * Rewardful Referrals - Client-side Tracking Script
 * 
 * Handles via token capture and conversion tracking
 */

(function() {
    'use strict';
    
    // Configuration
    var STORAGE_KEY = 'rewardful_via';
    var STORAGE_TIMESTAMP_KEY = 'rewardful_via_ts';
    var COOKIE_DAYS = 30;
    
    /**
     * Get URL parameter by name
     */
    function getUrlParam(name) {
        var urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(name);
    }
    
    /**
     * Set cookie
     */
    function setCookie(name, value, days) {
        var expires = '';
        if (days) {
            var date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = '; expires=' + date.toUTCString();
        }
        document.cookie = name + '=' + (value || '') + expires + '; path=/; SameSite=Lax';
    }
    
    /**
     * Get cookie
     */
    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }
    
    /**
     * Store via token
     */
    function storeViaToken(token) {
        if (!token) return;
        
        // Validate token format
        if (!/^[a-zA-Z0-9_-]+$/.test(token)) {
            console.warn('Invalid via token format');
            return;
        }
        
        // Store in cookie
        setCookie(STORAGE_KEY, token, COOKIE_DAYS);
        setCookie(STORAGE_TIMESTAMP_KEY, Date.now().toString(), COOKIE_DAYS);
        
        // Also store in localStorage as backup
        try {
            localStorage.setItem(STORAGE_KEY, token);
            localStorage.setItem(STORAGE_TIMESTAMP_KEY, Date.now().toString());
        } catch (e) {
            // localStorage not available
        }
        
        console.log('Rewardful: Via token stored:', token);
    }
    
    /**
     * Get stored via token
     */
    function getStoredViaToken() {
        // Try cookie first
        var token = getCookie(STORAGE_KEY);
        var timestamp = getCookie(STORAGE_TIMESTAMP_KEY);
        
        // Fall back to localStorage
        if (!token) {
            try {
                token = localStorage.getItem(STORAGE_KEY);
                timestamp = localStorage.getItem(STORAGE_TIMESTAMP_KEY);
            } catch (e) {
                // localStorage not available
            }
        }
        
        // Check if token is expired (30 days)
        if (token && timestamp) {
            var age = Date.now() - parseInt(timestamp, 10);
            var maxAge = COOKIE_DAYS * 24 * 60 * 60 * 1000;
            if (age > maxAge) {
                // Token expired
                return null;
            }
        }
        
        return token;
    }
    
    /**
     * Send via token to server for session storage
     */
    function syncViaTokenWithServer(token) {
        // Create a hidden iframe or use fetch to send to server
        var img = new Image();
        img.src = '/plugins/rewardful_referrals/api/capture_via.php?via=' + encodeURIComponent(token) + '&_=' + Date.now();
    }
    
    /**
     * Initialize tracking
     */
    function init() {
        // Check for via parameter in URL
        var viaToken = getUrlParam('via');
        
        if (viaToken) {
            storeViaToken(viaToken);
            syncViaTokenWithServer(viaToken);
        }
        
        // Expose API for conversion tracking
        window.RewardfulReferrals = {
            getViaToken: getStoredViaToken,
            storeViaToken: storeViaToken
        };
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();
