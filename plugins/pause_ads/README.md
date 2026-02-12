# Pause Ads Plugin v2.0 for ClipBucket

**Version:** 2.0.0 | **Requires:** ClipBucket 4.0+, PHP 7.2+, MySQL 5.7+

## Overview

Pause Ads is a complete **Pause Ads Platform** for ClipBucket. When a viewer pauses an AVOD-enabled video, a random eligible static image ad overlays the player for the duration of the pause. The ad disappears instantly when playback resumes.

### What's Included

**For Platform Operators (Admin):**
- Global analytics dashboard (impressions, clicks, CTR, revenue, geo)
- Campaign management with approval workflow
- Company (advertiser) management
- Pricing packages (CRUD with seed defaults)
- Transaction ledger with manual payment approval
- AVOD video management
- Eligibility test mode
- Configurable settings (geo, overlay, billing, etc.)

**For Advertisers (Self-Service Portal):**
- Company creation and team management (Owner/Admin/Analyst roles)
- Campaign builder with flight scheduling, targeting, delivery caps
- Creative library with image upload
- Package purchase with payment integration
- Invoice generation and viewing (print/PDF)
- Campaign analytics with geo breakdown and time series

**Ad Serving:**
- Campaign-based eligibility engine with weighted random selection
- Flight scheduling (start/end dates)
- Geography targeting (country/region via IP geolocation)
- Content targeting (genre, category, rating, language, device)
- Delivery caps (daily, hourly, per-user frequency)
- Purchased impression budget enforcement
- Anti-abuse: rate limiting, HMAC tokens, IP hashing

---

## Installation

### 1. Upload Plugin Files

Copy the `pause_ads` folder to your ClipBucket plugins directory:

```
/your-clipbucket-root/plugins/pause_ads/
```

### 2. Set Directory Permissions

```bash
chmod 755 plugins/pause_ads/uploads/
chown www-data:www-data plugins/pause_ads/uploads/
```

### 3. Install the Plugin

In ClipBucket Admin Panel:
1. Go to **Admin > Plugin Manager**
2. Find "Pause Ads" and click **Install**

The installer creates 12 database tables and seeds:
- Default settings (min_pause_ms=1000, geo=ip-api, etc.)
- 4 pricing packages (Starter $250, Growth $1,000, Scale $4,000, Enterprise custom)

### 4. Configure

Go to **Admin > Pause Ads > Settings** to configure:
- Enable/disable the plugin
- Minimum pause duration
- Overlay appearance (position, style)
- Campaign approval requirement
- Currency, tax rate
- Geo provider
- Invoice settings

---

## Quick Start Guide

### Step 1: Mark Videos as AVOD

Go to **Admin > Pause Ads > AVOD Videos** and add video IDs.
Alternatively, use the AVOD toggle on the video edit page.

### Step 2: Advertiser Creates Company

Advertisers visit `/plugins/pause_ads/advertiser/dashboard.php` and create a company.

### Step 3: Create Campaign

1. **Campaigns > New Campaign**: Set name, flight dates, targeting rules, delivery caps
2. **Upload Creatives**: Add image ads with click URLs and alt text
3. **Purchase Package**: Go to Billing, select a package for the campaign
4. After payment: Campaign becomes Active (or Pending Review if admin approval enabled)

### Step 4: Ads Start Serving

When viewers pause AVOD-enabled videos, eligible creatives are served based on targeting, budget, and priority.

---

## Database Schema

All tables prefixed with your ClipBucket table prefix (default: `cb_`):

| Table | Purpose |
|-------|---------|
| `pause_ads_settings` | Key-value plugin settings |
| `pause_ads_avod_videos` | AVOD-enabled video registry |
| `pause_ads_companies` | Advertiser companies |
| `pause_ads_company_users` | Company membership with roles |
| `pause_ads_packages` | Pricing packages |
| `pause_ads_campaigns` | Ad campaigns with flight scheduling |
| `pause_ads_creatives` | Image ad creatives per campaign |
| `pause_ads_targeting_rules` | Targeting rules per campaign |
| `pause_ads_purchases` | Package purchases with payment tracking |
| `pause_ads_invoices` | Generated invoices |
| `pause_ads_impressions` | Impression log with geo |
| `pause_ads_clicks` | Click log |

### Campaign Status Workflow

```
Draft → Pending Payment → [Payment] → Active (or Pending Review → [Admin Approve] → Active)
                                        ↕ Paused
                                        → Ended (flight expired or budget exhausted)
                                        → Rejected (admin)
```

---

## Ad Serving Eligibility

When a viewer pauses an AVOD video, the eligibility engine:

1. **Plugin check**: Is enabled?
2. **AVOD check**: Is this video AVOD-enabled?
3. **Auto-end**: Expire campaigns past flight_end or with zero budget
4. **Candidate query**: Active campaigns + active creatives within flight window
5. **Targeting filter**: Genre, category, rating, country, region, language, device
   - Same-type rules: OR (any match)
   - Cross-type rules: AND (all must pass)
   - No rules = match everything
6. **Delivery caps**: Daily/hourly campaign caps, per-user/session frequency cap
7. **Budget filter**: Campaign must have remaining purchased impressions > 0
8. **Selection**: Priority groups (highest first) → weighted random within group

---

## Geography Targeting

### How It Works

- IP geolocation via configurable provider (default: ip-api.com)
- Results cached per session for performance
- Country and region codes stored in impression/click logs
- Targeting rules use ISO 2-letter country codes (US, GB, CA, etc.)

### Configuring Geo

In **Admin > Settings > Geography**, choose:
- **ip-api.com**: Free, 45 requests/minute from server IP
- **Disabled**: No geo resolution, geo targeting rules ignored

### Adding Geo Targeting

In the campaign editor, add targeting rules:
- Type: **Country** → Value: `US` (or `GB`, `CA`, etc.)
- Type: **Region** → Value: `CA` (California) or `NY` (New York)

---

## Payment Integration

### ClipBucket Gateways

The plugin integrates with ClipBucket's existing payment system:
1. If `create_payment()` function exists, it's used directly
2. If PayPal email is configured in ClipBucket, PayPal checkout is used
3. Otherwise, a manual payment flow is used (admin approves)

### Manual Payment Flow

If no gateway is configured:
- Purchases are auto-completed (configurable)
- Admin can manually approve pending purchases in **Admin > Transactions**

### Payment Callback

Returns from payment gateways hit:
```
/plugins/pause_ads/ajax/payment_callback.php?purchase_id=X&status=success
```

PayPal IPN notifications are handled at:
```
/plugins/pause_ads/ajax/payment_callback.php?purchase_id=X&ipn=1
```

---

## Pricing Packages (Defaults)

| Package | Price | Impressions | Max Flight |
|---------|-------|-------------|------------|
| Starter | $250 | 10,000 | 30 days |
| Growth | $1,000 | 50,000 | 60 days |
| Scale | $4,000 | 250,000 | 90 days |
| Enterprise | Custom | Custom | Unlimited |

All packages are editable in **Admin > Packages**.

---

## AJAX Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/plugins/pause_ads/ajax/get_ad.php?video_id=X` | GET | Fetch eligible creative |
| `/plugins/pause_ads/ajax/track_impression.php` | POST | Record impression |
| `/plugins/pause_ads/ajax/track_click.php` | POST | Record click |
| `/plugins/pause_ads/ajax/payment_callback.php` | GET | Payment return handler |

---

## Security

- **CSRF nonces** on all admin/advertiser form submissions
- **HMAC tokens** for impression/click tracking (prevents forgery)
- **IP hashing** (SHA-256 + salt) — raw IPs never stored
- **File upload validation** (extension, MIME type, getimagesize)
- **Rate limiting** per session per minute
- **Prepared statements** for all SQL queries
- **Role-based access** (company owner/admin/analyst)
- **Upload directory protection** via .htaccess

---

## File Structure

```
plugins/pause_ads/
├── plugin.json                 # Plugin metadata
├── main.php                    # Bootstrap, hooks, UI renderers
├── install.php                 # DB installer + seed data
├── uninstall.php               # Cleanup
├── includes/
│   ├── constants.php           # Plugin constants
│   ├── db.php                  # Database helpers
│   ├── security.php            # Auth, CSRF, rate limiting
│   ├── functions.php           # Core utils, AVOD, reporting
│   ├── models.php              # CRUD for all entities
│   ├── eligibility.php         # Ad serving engine
│   ├── geo.php                 # GeoIP resolver
│   └── payment.php             # Payment gateway integration
├── ajax/
│   ├── get_ad.php              # Fetch eligible creative
│   ├── track_impression.php    # Record impression
│   ├── track_click.php         # Record click
│   └── payment_callback.php    # Payment return handler
├── admin/
│   ├── dashboard.php           # Global analytics
│   ├── campaigns.php           # Campaign management
│   ├── companies.php           # Company management
│   ├── packages.php            # Pricing packages CRUD
│   ├── transactions.php        # Purchase ledger & invoices
│   ├── avod_videos.php         # AVOD video management
│   ├── test_mode.php           # Eligibility tester
│   └── settings.php            # Plugin settings
├── advertiser/
│   ├── dashboard.php           # Advertiser overview
│   ├── company.php             # Company setup & team
│   ├── campaigns.php           # Campaign list
│   ├── campaign_edit.php       # Campaign builder
│   ├── billing.php             # Packages & purchase
│   ├── invoices.php            # Invoice list & view
│   └── analytics.php           # Campaign analytics
├── assets/
│   ├── pause_ads.js            # Frontend overlay controller
│   ├── pause_ads.css           # Overlay styles
│   └── admin.css               # Admin styles
├── templates/
│   └── watch_page_include.php  # Manual integration fallback
├── uploads/                    # Ad image uploads
├── sql/
│   └── schema.sql              # Database schema
└── README.md
```

---

## Troubleshooting

### Ads not showing on pause

1. Is the plugin enabled? (Admin > Settings)
2. Is the video AVOD-enabled? (Admin > AVOD Videos)
3. Are there active campaigns with active creatives? (Admin > Campaigns)
4. Do campaigns have purchased impressions remaining? (Admin > Transactions)
5. Use **Admin > Test Mode** to diagnose eligibility

### Player not detected

The JS automatically detects video.js and native `<video>` elements.
If detection fails, manually include the template:

```html
<?php include 'plugins/pause_ads/templates/watch_page_include.php'; ?>
```

### Geo targeting not working

- Check geo provider is set to "ip-api" in Admin > Settings
- ip-api.com doesn't work with private/loopback IPs
- Check browser console for geo resolution errors

### Payment issues

- If no payment gateway is configured, purchases auto-complete
- Admin can manually approve pending purchases in Admin > Transactions
- Check ClipBucket's PayPal settings if using PayPal

---

## Advertiser Portal Access

Advertisers access the self-service portal at:
```
/plugins/pause_ads/advertiser/dashboard.php
```

They must be logged in with a ClipBucket user account. The first visit prompts company creation.

---

## Uninstallation

1. Optionally check "Drop all tables on uninstall" in Admin > Settings
2. Go to Admin > Plugin Manager > Uninstall Pause Ads
3. If tables are preserved, data remains for potential reinstall

---

## License

This plugin is provided as part of the ClipBucket plugin ecosystem.
