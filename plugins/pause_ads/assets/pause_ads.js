/**
 * Pause Ads v2.0 - Frontend Overlay Controller
 *
 * Detects HTML5 video player (native <video> or video.js), binds
 * pause/play events, fetches eligible creatives, and manages overlay
 * display with impression/click tracking.
 *
 * @package PauseAds
 * @version 2.0.0
 */
(function () {
    'use strict';

    var config = window.PauseAdsConfig || {};
    if (!config.videoId || !config.getAdUrl) return;

    var S = {
        videoId: config.videoId, sessionId: config.sessionId || '',
        minPauseMs: config.minPauseMs || 1000,
        overlayPosition: config.overlayPosition || 'center',
        getAdUrl: config.getAdUrl, trackImpressionUrl: config.trackImpressionUrl,
        trackClickUrl: config.trackClickUrl, baseUrl: config.baseUrl || '',
        player: null, overlay: null, currentAd: null, pauseTimer: null,
        impressionRecorded: false, impressionId: null, overlayVisible: false,
        lastPauseTime: 0, fetchInProgress: false
    };

    // ---- Player Detection ----
    function detectPlayer() {
        if (typeof videojs !== 'undefined') {
            try {
                var ps = videojs.getPlayers ? videojs.getPlayers() : {};
                for (var id in ps) { if (ps[id]) return {type:'videojs',instance:ps[id],element:ps[id].el()}; }
            } catch(e) {}
            var vjs = document.querySelectorAll('.video-js');
            for (var i=0;i<vjs.length;i++) { var p=videojs(vjs[i].id||vjs[i]); if(p) return {type:'videojs',instance:p,element:vjs[i]}; }
        }
        var vids = document.querySelectorAll('video');
        if (vids.length) {
            var best=null, bestA=0;
            for (var v=0;v<vids.length;v++) { var r=vids[v].getBoundingClientRect(),a=r.width*r.height; if(a>bestA){bestA=a;best=vids[v];} }
            if (best) return {type:'native',instance:best,element:best.parentElement||best};
        }
        return null;
    }

    function bindEvents(player) {
        S.player = player;
        if (player.type === 'videojs') {
            player.instance.on('pause', function(){ if(!player.instance.ended()) onPause(); });
            player.instance.on('play', onPlay); player.instance.on('playing', onPlay); player.instance.on('ended', onPlay);
        } else {
            player.instance.addEventListener('pause', function(){ if(!player.instance.ended) onPause(); });
            player.instance.addEventListener('play', onPlay);
            player.instance.addEventListener('playing', onPlay);
            player.instance.addEventListener('ended', onPlay);
        }
    }

    function isPlaying() {
        if (!S.player) return true;
        return S.player.type==='videojs' ? !S.player.instance.paused() : !S.player.instance.paused;
    }

    // ---- Handlers ----
    function onPause() {
        if (S.overlayVisible || S.fetchInProgress) return;
        S.lastPauseTime = Date.now();
        S.impressionRecorded = false; S.impressionId = null; S.currentAd = null;
        fetchAd();
    }

    function onPlay() {
        hideOverlay();
        if (S.pauseTimer) { clearTimeout(S.pauseTimer); S.pauseTimer = null; }
        S.currentAd = null; S.fetchInProgress = false;
    }

    // ---- Fetch ----
    function fetchAd() {
        S.fetchInProgress = true;
        var xhr = new XMLHttpRequest();
        xhr.open('GET', S.getAdUrl + '?video_id=' + encodeURIComponent(S.videoId), true);
        xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
        xhr.timeout = 5000;
        xhr.onload = function() {
            S.fetchInProgress = false;
            if (xhr.status!==200) return;
            try { var d=JSON.parse(xhr.responseText); } catch(e){ return; }
            if (!d.eligible || !d.image_url) return;
            if (isPlaying()) return;
            S.currentAd = d;
            showOverlay(d);
            startImpressionTimer(d);
        };
        xhr.onerror = xhr.ontimeout = function(){ S.fetchInProgress=false; };
        xhr.send();
    }

    // ---- Overlay ----
    function showOverlay(ad) {
        removeOverlay();
        var container = S.player.element;
        if (window.getComputedStyle(container).position==='static') container.style.position='relative';

        var ov = document.createElement('div');
        ov.className = 'pause-ads-overlay pause-ads-position-' + S.overlayPosition;
        ov.id = 'pause-ads-overlay';
        ov.setAttribute('role','dialog'); ov.setAttribute('aria-label','Advertisement');

        var bg = document.createElement('div'); bg.className='pause-ads-bg'; ov.appendChild(bg);
        var content = document.createElement('div'); content.className='pause-ads-content';

        var label = document.createElement('div'); label.className='pause-ads-label'; label.textContent='AD';
        content.appendChild(label);

        var imgUrl = ad.image_url;
        if (imgUrl && imgUrl.indexOf('http')!==0 && imgUrl.indexOf('//')!==0) imgUrl = S.baseUrl+'/'+imgUrl;

        var wrapper;
        if (ad.click_url) {
            wrapper = document.createElement('a');
            wrapper.href = ad.click_url; wrapper.target='_blank'; wrapper.rel='noopener noreferrer sponsored';
            wrapper.className='pause-ads-link';
            wrapper.addEventListener('click', function(){ trackClick(ad); });
        } else {
            wrapper = document.createElement('div'); wrapper.className='pause-ads-link';
        }

        var img = document.createElement('img');
        img.src=imgUrl; img.alt=ad.alt_text||'Advertisement'; img.className='pause-ads-image'; img.draggable=false;
        img.onerror=function(){this.style.display='none';};
        wrapper.appendChild(img); content.appendChild(wrapper); ov.appendChild(content);

        bg.addEventListener('click',function(e){e.stopPropagation();});
        ov.addEventListener('click',function(e){e.stopPropagation();});

        container.appendChild(ov);
        S.overlay=ov; S.overlayVisible=true;
        requestAnimationFrame(function(){ov.classList.add('pause-ads-visible');});
    }

    function hideOverlay() {
        S.overlayVisible=false;
        if(S.overlay){S.overlay.classList.remove('pause-ads-visible');S.overlay.classList.add('pause-ads-hiding');}
        setTimeout(removeOverlay,300);
    }

    function removeOverlay() {
        var el=document.getElementById('pause-ads-overlay');
        if(el) el.parentNode.removeChild(el);
        S.overlay=null; S.overlayVisible=false;
    }

    // ---- Tracking ----
    function startImpressionTimer(ad) {
        if(S.pauseTimer) clearTimeout(S.pauseTimer);
        S.pauseTimer=setTimeout(function(){
            if(!isPlaying()&&S.overlayVisible&&!S.impressionRecorded) trackImpression(ad);
        }, S.minPauseMs);
    }

    function trackImpression(ad) {
        if(S.impressionRecorded) return;
        S.impressionRecorded=true;
        var payload=JSON.stringify({creative_id:ad.creative_id,campaign_id:ad.campaign_id,video_id:S.videoId,token:ad.token,pause_duration_ms:Date.now()-S.lastPauseTime});
        var xhr=new XMLHttpRequest();
        xhr.open('POST',S.trackImpressionUrl,true);
        xhr.setRequestHeader('Content-Type','application/json');
        xhr.setRequestHeader('X-Requested-With','XMLHttpRequest');
        xhr.timeout=5000;
        xhr.onload=function(){if(xhr.status===200){try{var d=JSON.parse(xhr.responseText);if(d.impression_id)S.impressionId=d.impression_id;}catch(e){}}};
        xhr.send(payload);
    }

    function trackClick(ad) {
        var payload=JSON.stringify({creative_id:ad.creative_id,campaign_id:ad.campaign_id,video_id:S.videoId,token:ad.token,impression_id:S.impressionId||null});
        if(navigator.sendBeacon){navigator.sendBeacon(S.trackClickUrl,new Blob([payload],{type:'application/json'}));}
        else{var x=new XMLHttpRequest();x.open('POST',S.trackClickUrl,true);x.setRequestHeader('Content-Type','application/json');x.send(payload);}
    }

    // ---- Init ----
    function init(attempt) {
        attempt=attempt||0;
        if(attempt>=10){console.warn('[PauseAds] Player not found.');return;}
        var p=detectPlayer();
        if(!p){setTimeout(function(){init(attempt+1);},Math.min(500*Math.pow(1.5,attempt),5000));return;}
        bindEvents(p);
        if(!isPlaying()) onPause();
    }

    if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',function(){setTimeout(function(){init(0);},500);});
    else setTimeout(function(){init(0);},500);
})();
