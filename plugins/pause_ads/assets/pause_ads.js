/**
 * Pause Ads Plugin - Frontend Overlay Controller
 *
 * Detects the HTML5 video player (native <video>, video.js, or custom wrapper),
 * binds to pause/play events, fetches eligible ads via AJAX, and manages
 * the overlay display and impression/click tracking.
 *
 * @package PauseAds
 * @version 1.0.0
 */
(function () {
    'use strict';

    // =========================================================================
    // Configuration
    // =========================================================================

    var config = window.PauseAdsConfig || {};
    if (!config.videoId || !config.getAdUrl) {
        // Plugin not configured for this page
        return;
    }

    var STATE = {
        videoId: config.videoId,
        sessionId: config.sessionId || '',
        minPauseMs: config.minPauseMs || 1000,
        overlayPosition: config.overlayPosition || 'center',
        getAdUrl: config.getAdUrl,
        trackImpressionUrl: config.trackImpressionUrl,
        trackClickUrl: config.trackClickUrl,
        baseUrl: config.baseUrl || '',
        // Runtime state
        player: null,
        overlay: null,
        currentAd: null,
        pauseTimer: null,
        impressionRecorded: false,
        impressionId: null,
        overlayVisible: false,
        lastPauseTime: 0,
        initializing: false,
        fetchInProgress: false,
    };

    // =========================================================================
    // Player Detection
    // =========================================================================

    /**
     * Find the video player element.
     * Tries: video.js players, native <video> elements, common wrappers.
     */
    function detectPlayer() {
        // 1. Try video.js instances
        if (typeof videojs !== 'undefined') {
            try {
                var players = videojs.getPlayers ? videojs.getPlayers() : {};
                for (var id in players) {
                    if (players.hasOwnProperty(id) && players[id]) {
                        return { type: 'videojs', instance: players[id], element: players[id].el() };
                    }
                }
            } catch (e) {
                // video.js not properly initialized
            }
        }

        // 2. Try getting video.js player by common selectors
        if (typeof videojs !== 'undefined') {
            var vjsElements = document.querySelectorAll('.video-js');
            for (var i = 0; i < vjsElements.length; i++) {
                var vjsPlayer = videojs(vjsElements[i].id || vjsElements[i]);
                if (vjsPlayer) {
                    return { type: 'videojs', instance: vjsPlayer, element: vjsElements[i] };
                }
            }
        }

        // 3. Native <video> element
        var videos = document.querySelectorAll('video');
        if (videos.length > 0) {
            // Prefer the largest/most visible video
            var bestVideo = null;
            var bestArea = 0;
            for (var v = 0; v < videos.length; v++) {
                var rect = videos[v].getBoundingClientRect();
                var area = rect.width * rect.height;
                if (area > bestArea) {
                    bestArea = area;
                    bestVideo = videos[v];
                }
            }
            if (bestVideo) {
                return { type: 'native', instance: bestVideo, element: bestVideo.parentElement || bestVideo };
            }
        }

        // 4. Common player wrappers
        var selectors = [
            '#player', '#video-player', '#main-player',
            '.player-container', '.video-container',
            '.plyr', '.flowplayer', '.jwplayer',
            '[data-player]', '[data-video-id]',
        ];
        for (var s = 0; s < selectors.length; s++) {
            var container = document.querySelector(selectors[s]);
            if (container) {
                var innerVideo = container.querySelector('video');
                if (innerVideo) {
                    return { type: 'native', instance: innerVideo, element: container };
                }
            }
        }

        return null;
    }

    // =========================================================================
    // Event Binding
    // =========================================================================

    /**
     * Bind pause/play events to the detected player.
     */
    function bindEvents(player) {
        STATE.player = player;

        if (player.type === 'videojs') {
            // video.js event binding
            player.instance.on('pause', function () {
                // Ignore if video ended
                if (!player.instance.ended()) {
                    onPause();
                }
            });
            player.instance.on('play', onPlay);
            player.instance.on('playing', onPlay);
            player.instance.on('ended', onPlay); // Clean up on end
        } else if (player.type === 'native') {
            // Native HTML5 video events
            player.instance.addEventListener('pause', function () {
                if (!player.instance.ended) {
                    onPause();
                }
            });
            player.instance.addEventListener('play', onPlay);
            player.instance.addEventListener('playing', onPlay);
            player.instance.addEventListener('ended', onPlay);
        }
    }

    // =========================================================================
    // Pause / Play Handlers
    // =========================================================================

    /**
     * Handle video pause event.
     */
    function onPause() {
        // Prevent duplicate handling
        if (STATE.overlayVisible || STATE.fetchInProgress) {
            return;
        }

        STATE.lastPauseTime = Date.now();
        STATE.impressionRecorded = false;
        STATE.impressionId = null;
        STATE.currentAd = null;

        // Fetch an eligible ad
        fetchAd();
    }

    /**
     * Handle video play/resume event.
     */
    function onPlay() {
        // Immediately hide overlay
        hideOverlay();

        // Cancel pending timers
        if (STATE.pauseTimer) {
            clearTimeout(STATE.pauseTimer);
            STATE.pauseTimer = null;
        }

        STATE.currentAd = null;
        STATE.fetchInProgress = false;
    }

    // =========================================================================
    // Ad Fetching
    // =========================================================================

    /**
     * Fetch an eligible ad from the server.
     */
    function fetchAd() {
        STATE.fetchInProgress = true;

        var url = STATE.getAdUrl + '?video_id=' + encodeURIComponent(STATE.videoId);

        var xhr = new XMLHttpRequest();
        xhr.open('GET', url, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.timeout = 5000;

        xhr.onload = function () {
            STATE.fetchInProgress = false;

            if (xhr.status !== 200) {
                return;
            }

            try {
                var data = JSON.parse(xhr.responseText);
            } catch (e) {
                return;
            }

            if (!data.eligible || !data.image_url) {
                return;
            }

            // Check if we're still paused
            if (isPlaying()) {
                return;
            }

            STATE.currentAd = data;
            showOverlay(data);

            // Start impression timer
            startImpressionTimer(data);
        };

        xhr.onerror = function () {
            STATE.fetchInProgress = false;
        };

        xhr.ontimeout = function () {
            STATE.fetchInProgress = false;
        };

        xhr.send();
    }

    /**
     * Check if the video is currently playing.
     */
    function isPlaying() {
        if (!STATE.player) return true;

        if (STATE.player.type === 'videojs') {
            return !STATE.player.instance.paused();
        } else if (STATE.player.type === 'native') {
            return !STATE.player.instance.paused;
        }

        return true;
    }

    // =========================================================================
    // Overlay Management
    // =========================================================================

    /**
     * Create and show the ad overlay.
     */
    function showOverlay(adData) {
        // Remove any existing overlay first
        removeOverlay();

        var playerEl = STATE.player.element;
        var container = playerEl;

        // Ensure the container has relative positioning
        var containerStyle = window.getComputedStyle(container);
        if (containerStyle.position === 'static') {
            container.style.position = 'relative';
        }

        // Create overlay wrapper
        var overlay = document.createElement('div');
        overlay.className = 'pause-ads-overlay pause-ads-position-' + STATE.overlayPosition;
        overlay.id = 'pause-ads-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', 'Advertisement');

        // Background layer
        var bg = document.createElement('div');
        bg.className = 'pause-ads-bg';
        overlay.appendChild(bg);

        // Ad content container
        var content = document.createElement('div');
        content.className = 'pause-ads-content';

        // "Ad" label
        var label = document.createElement('div');
        label.className = 'pause-ads-label';
        label.textContent = 'AD';
        content.appendChild(label);

        // Build image URL
        var imgUrl = adData.image_url;
        if (imgUrl && imgUrl.indexOf('http') !== 0 && imgUrl.indexOf('//') !== 0) {
            imgUrl = STATE.baseUrl + '/' + imgUrl;
        }

        // Ad image (potentially wrapped in link)
        var imgWrapper;
        if (adData.click_url) {
            imgWrapper = document.createElement('a');
            imgWrapper.href = adData.click_url;
            imgWrapper.target = '_blank';
            imgWrapper.rel = 'noopener noreferrer sponsored';
            imgWrapper.className = 'pause-ads-link';
            imgWrapper.addEventListener('click', function (e) {
                trackClick(adData);
            });
        } else {
            imgWrapper = document.createElement('div');
            imgWrapper.className = 'pause-ads-link';
        }

        var img = document.createElement('img');
        img.src = imgUrl;
        img.alt = adData.alt_text || 'Advertisement';
        img.className = 'pause-ads-image';
        img.draggable = false;

        // Prevent image from breaking layout on error
        img.onerror = function () {
            this.style.display = 'none';
        };

        imgWrapper.appendChild(img);
        content.appendChild(imgWrapper);

        overlay.appendChild(content);

        // Click handler on background to NOT close overlay (ad stays visible while paused)
        bg.addEventListener('click', function (e) {
            e.stopPropagation();
            // Do nothing - overlay stays until play
        });

        // Prevent overlay clicks from reaching the player (which would trigger play)
        overlay.addEventListener('click', function (e) {
            e.stopPropagation();
        });

        container.appendChild(overlay);
        STATE.overlay = overlay;
        STATE.overlayVisible = true;

        // Animate in
        requestAnimationFrame(function () {
            overlay.classList.add('pause-ads-visible');
        });
    }

    /**
     * Hide and remove the overlay.
     */
    function hideOverlay() {
        STATE.overlayVisible = false;

        if (STATE.overlay) {
            STATE.overlay.classList.remove('pause-ads-visible');
            STATE.overlay.classList.add('pause-ads-hiding');

            // Remove after animation
            var overlayRef = STATE.overlay;
            setTimeout(function () {
                removeOverlay();
            }, 300);
        }
    }

    /**
     * Remove overlay element from DOM.
     */
    function removeOverlay() {
        var existing = document.getElementById('pause-ads-overlay');
        if (existing) {
            existing.parentNode.removeChild(existing);
        }
        STATE.overlay = null;
        STATE.overlayVisible = false;
    }

    // =========================================================================
    // Impression Tracking
    // =========================================================================

    /**
     * Start the impression timer.
     * Only counts after minimum pause duration AND overlay was displayed.
     */
    function startImpressionTimer(adData) {
        if (STATE.pauseTimer) {
            clearTimeout(STATE.pauseTimer);
        }

        STATE.pauseTimer = setTimeout(function () {
            // Verify still paused and overlay is visible
            if (!isPlaying() && STATE.overlayVisible && !STATE.impressionRecorded) {
                trackImpression(adData);
            }
        }, STATE.minPauseMs);
    }

    /**
     * Send impression tracking request.
     */
    function trackImpression(adData) {
        if (STATE.impressionRecorded) {
            return;
        }

        STATE.impressionRecorded = true;

        var pauseDuration = Date.now() - STATE.lastPauseTime;

        var payload = JSON.stringify({
            ad_id: adData.ad_id,
            video_id: STATE.videoId,
            token: adData.token,
            pause_duration_ms: pauseDuration,
        });

        var xhr = new XMLHttpRequest();
        xhr.open('POST', STATE.trackImpressionUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.timeout = 5000;

        xhr.onload = function () {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.impression_id) {
                        STATE.impressionId = data.impression_id;
                    }
                } catch (e) {
                    // Silent fail
                }
            }
        };

        xhr.send(payload);
    }

    /**
     * Send click tracking request.
     */
    function trackClick(adData) {
        var payload = JSON.stringify({
            ad_id: adData.ad_id,
            video_id: STATE.videoId,
            token: adData.token,
            impression_id: STATE.impressionId || null,
        });

        // Use sendBeacon if available (works even if page navigates)
        if (navigator.sendBeacon) {
            var blob = new Blob([payload], { type: 'application/json' });
            navigator.sendBeacon(STATE.trackClickUrl, blob);
        } else {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', STATE.trackClickUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.send(payload);
        }
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    /**
     * Initialize the pause ads system.
     * Retries player detection with exponential backoff.
     */
    function init(attempt) {
        if (STATE.initializing) return;

        attempt = attempt || 0;
        var maxAttempts = 10;

        if (attempt >= maxAttempts) {
            console.warn('[PauseAds] Could not detect video player after ' + maxAttempts + ' attempts.');
            return;
        }

        var player = detectPlayer();

        if (!player) {
            // Retry with increasing delay
            var delay = Math.min(500 * Math.pow(1.5, attempt), 5000);
            setTimeout(function () {
                init(attempt + 1);
            }, delay);
            return;
        }

        STATE.initializing = true;
        bindEvents(player);

        // If player is already paused, trigger ad fetch
        if (!isPlaying()) {
            onPause();
        }
    }

    // Start initialization when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            // Small delay to ensure player is initialized
            setTimeout(function () { init(0); }, 500);
        });
    } else {
        setTimeout(function () { init(0); }, 500);
    }

})();
