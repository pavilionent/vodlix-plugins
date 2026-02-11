# Pause Ads Plugin for ClipBucket

**Version:** 1.0.0  
**Requires:** ClipBucket 4.0+, PHP 7.2+, MySQL 5.7+

## Overview

The Pause Ads plugin enables a **Pause Ads Platform** for ClipBucket. When a viewer pauses an AVOD-enabled video, a static image ad is overlaid on the player for the duration of the pause. The ad disappears instantly when playback resumes.

This plugin includes a complete **Ad Management Platform** inside the ClipBucket admin panel with:

- Ad creative management (upload, schedule, status)
- Targeting rules (genre, category, rating, country, language, device)
- Delivery caps (total, daily, hourly, per-user frequency)
- Weighted random ad selection with priority groups
- Impression and click tracking
- Reporting dashboard with charts and daily breakdown
- Eligibility test mode for debugging
- AVOD video management

---

## Installation

### 1. Upload Plugin Files

Copy the entire `pause_ads` folder into your ClipBucket plugins directory:

```
/your-clipbucket-root/plugins/pause_ads/
```

### 2. Set Directory Permissions

Ensure the uploads directory is writable by your web server:

```bash
chmod 755 plugins/pause_ads/uploads/
chown www-data:www-data plugins/pause_ads/uploads/
```

### 3. Install the Plugin

Navigate to the ClipBucket Admin Panel:

1. Go to **Admin > Plugin Manager**
2. Find "Pause Ads" in the plugin list
3. Click **Install**

Or run the installer manually by visiting:
```
/admin_area/plugin.php?page=pause_ads/install.php
```

The installer will:
- Create all required database tables (7 tables, prefixed with your CB table prefix)
- Register plugin hooks
- Create the uploads directory with security rules
- Set default configuration

### 4. Verify Installation

After installation, check:
- Admin menu shows "Pause Ads" section with submenu items
- Visit **Pause Ads > Settings** to confirm plugin info displays correctly
- The uploads directory shows as "Writable"

---

## Configuration

### Plugin Settings

Go to **Pause Ads > Settings** in the admin panel:

| Setting | Default | Description |
|---------|---------|-------------|
| Enable Plugin | On | Master switch for the entire plugin |
| Min Pause Duration | 1000ms | Minimum pause time before an impression counts |
| Overlay Position | Center | Where the ad appears (center, bottom-right, etc.) |
| Overlay Style | Semi-transparent | Background effect behind the ad |
| Fallback Behavior | None | What to show when no eligible ad exists |
| Rate Limit | 5/min | Max ad requests per session per minute |

---

## How to Use

### Step 1: Mark Videos as AVOD-Enabled

Pause ads only display on AVOD-enabled videos. To enable:

**Option A: Via AVOD Videos page**
1. Go to **Pause Ads > AVOD Videos**
2. Enter a Video ID and click "Mark as AVOD-Enabled"
3. Or use Bulk Add for multiple videos

**Option B: Via Video Edit page**
1. Edit any video in ClipBucket Admin
2. Check the "Enable AVOD pause ads for this video" checkbox
3. Save the video

### Step 2: Create Ads

1. Go to **Pause Ads > Ads**
2. Click **"+ Create New Ad"**
3. Fill in the form:
   - **Name:** Descriptive name for the ad
   - **Image:** Upload a JPG, PNG, GIF, WebP, or SVG (max 5MB)
   - **Click URL:** Landing page URL (optional)
   - **Alt Text:** Accessibility text describing the ad
   - **Status:** Set to "Active" when ready to serve
4. Configure **Schedule** (optional start/end dates)
5. Set **Priority** and **Weight** for ad selection
6. Configure **Delivery Caps** (total, daily, hourly, frequency)
7. Save

### Step 3: Configure Targeting (Optional)

On the ad edit page, add targeting rules:

| Rule Type | Values | Logic |
|-----------|--------|-------|
| Genre | comedy, drama, action... | OR within type |
| Category | Same as genre, depends on your CB setup | OR within type |
| Rating | PG, PG-13, R... | OR within type |
| Country | US, UK, CA... | OR within type |
| Language | en, es, fr... | OR within type |
| Device | mobile, tablet, desktop | OR within type |
| Require Known Age | true/false | Only show to logged-in users |

**Logic:** Rules of the SAME type use OR (any match). Rules of DIFFERENT types use AND (all must match). An ad with NO rules matches ALL videos.

### Step 4: Monitor Performance

Go to **Pause Ads > Dashboard**:

- View impressions and clicks over time
- Check CTR per ad
- See top videos generating pause ad impressions
- Filter by date range

---

## Ad Selection Algorithm

When a viewer pauses an AVOD video:

1. **Plugin check:** Is the plugin enabled?
2. **AVOD check:** Is this video AVOD-enabled?
3. **Active ads:** Find all ads with status=active and within schedule window
4. **Targeting:** Filter by rules against video metadata (genre, category, etc.)
5. **Delivery caps:** Filter out ads that hit total/daily/hourly/frequency caps
6. **Priority groups:** Group remaining ads by priority (highest first)
7. **Weighted random:** Within the highest priority group, select randomly weighted by the weight field
8. **Response:** Return the selected ad with image URL, click URL, and tracking token

---

## Technical Details

### Database Tables

All tables are prefixed with your ClipBucket table prefix (default: `cb_`):

| Table | Purpose |
|-------|---------|
| `pause_ads_ads` | Ad creatives (name, image, click URL, schedule, status) |
| `pause_ads_targeting` | Targeting rules per ad |
| `pause_ads_delivery` | Delivery caps and weights per ad |
| `pause_ads_impressions` | Impression log (ad_id, video_id, session, timestamp) |
| `pause_ads_clicks` | Click log |
| `pause_ads_settings` | Key-value plugin settings |
| `pause_ads_avod_videos` | AVOD-enabled video registry |

### AJAX Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/plugins/pause_ads/ajax/get_ad.php?video_id=X` | GET | Fetch eligible ad |
| `/plugins/pause_ads/ajax/track_impression.php` | POST | Record impression |
| `/plugins/pause_ads/ajax/track_click.php` | POST | Record click |

### Security Features

- **CSRF tokens** (nonces) on all admin forms
- **HMAC tokens** for impression/click tracking (prevents forgery)
- **IP hashing** (SHA-256 with salt) instead of storing raw IPs
- **File validation** for uploads (extension, MIME type, getimagesize)
- **Rate limiting** per session per minute
- **Prepared statements** for all SQL queries
- **Input sanitization** on all user inputs
- **Upload directory protection** via .htaccess

### Player Detection

The frontend JS automatically detects:
1. **video.js** players (via `videojs.getPlayers()`)
2. **Native HTML5** `<video>` elements (selects largest visible)
3. **Common wrappers** (#player, .player-container, etc.)

If detection fails, it retries with exponential backoff up to 10 attempts.

### Impression Rules

An impression is counted ONLY when:
- The video is AVOD-enabled
- The pause lasts at least the configured minimum (default 1s)
- The ad overlay was actually displayed
- The same pause event hasn't already been counted
- Rate limits are not exceeded
- The tracking token is valid

---

## File Structure

```
plugins/pause_ads/
  plugin.json              # Plugin metadata
  main.php                 # Bootstrap, hooks, admin utilities
  install.php              # Database installer
  uninstall.php            # Cleanup script
  includes/
    constants.php          # Plugin constants
    db.php                 # Database helpers
    security.php           # CSRF, validation, rate limiting
    functions.php          # Core logic (eligibility, CRUD, reports)
  ajax/
    get_ad.php             # Fetch eligible ad (GET)
    track_impression.php   # Record impression (POST)
    track_click.php        # Record click (POST)
  admin/
    ads.php                # Ad list view
    ad_edit.php            # Ad create/edit form
    avod_videos.php        # AVOD video management
    reports.php            # Dashboard & reports
    settings.php           # Plugin settings
    test_mode.php          # Eligibility test tool
  assets/
    pause_ads.js           # Frontend overlay controller
    pause_ads.css          # Overlay styles
  uploads/                 # Ad image uploads
    .htaccess              # Security rules
  sql/
    schema.sql             # Database schema
  README.md                # This file
```

---

## Uninstallation

1. Go to **Pause Ads > Settings**
2. Optionally check "Drop database tables on uninstall"
3. Go to **Admin > Plugin Manager**
4. Click **Uninstall** on the Pause Ads plugin

If you chose to drop tables, ALL ad data, impressions, and settings will be permanently deleted.

---

## Troubleshooting

### Ads not showing on pause

1. **Check AVOD status:** Is the video marked as AVOD-enabled?  
   Go to Pause Ads > AVOD Videos to verify.

2. **Check plugin status:** Is the plugin enabled?  
   Go to Pause Ads > Settings.

3. **Check ad status:** Is at least one ad set to "Active"?  
   Go to Pause Ads > Ads.

4. **Use Test Mode:** Go to Pause Ads > Test Mode, enter the video ID,  
   and run the eligibility test to see detailed diagnostics.

5. **Check browser console:** Look for `[PauseAds]` messages or JS errors.

6. **Verify JS injection:** View page source on a watch page and search for  
   `PauseAdsConfig` to confirm the script is being injected.

### Player not detected

The plugin tries to detect the player automatically. If it fails:

1. Check that the watch page has an HTML5 `<video>` element
2. If using video.js, ensure it's initialized before our script loads
3. As a fallback, you can manually include the overlay script:

```html
<!-- Add to your watch page template -->
<script>
window.PauseAdsConfig = {
    videoId: YOUR_VIDEO_ID,
    sessionId: 'SESSION_ID',
    minPauseMs: 1000,
    overlayPosition: 'center',
    getAdUrl: '/plugins/pause_ads/ajax/get_ad.php',
    trackImpressionUrl: '/plugins/pause_ads/ajax/track_impression.php',
    trackClickUrl: '/plugins/pause_ads/ajax/track_click.php',
    baseUrl: ''
};
</script>
<link rel="stylesheet" href="/plugins/pause_ads/assets/pause_ads.css">
<script src="/plugins/pause_ads/assets/pause_ads.js" defer></script>
```

### Image upload errors

- Check that `plugins/pause_ads/uploads/` is writable (chmod 755)
- Verify PHP's `upload_max_filesize` is at least 5M
- Only JPG, PNG, GIF, WebP, and SVG files are allowed
- Maximum file size is 5MB

### Hook registration issues

If admin menu items don't appear, the hooks may not have registered properly.
Check that the `plugin_hooks` table contains entries for `pause_ads_*` hooks.
You can re-run the installer to re-register hooks.

---

## License

This plugin is provided as part of the ClipBucket plugin ecosystem.
