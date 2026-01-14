# Rewardful Referrals Plugin for ClipBucket

A production-ready ClipBucket plugin that integrates [Rewardful](https://www.getrewardful.com/) to run an invite/approve-only referral/affiliate program for subscription-based streaming sites.

## Features

- **Sitewide Tracking**: Automatic Rewardful JavaScript injection for attribution cookies
- **Conversion Tracking**: Client-side and server-assisted conversion tracking
- **Approval-Gated Referrers**: Admin-controlled referrer approval workflow
- **User Dashboard**: Gated referrals section on `/account/` page for approved referrers
- **Rewardful SSO**: Magic link access to Rewardful dashboard for referrers
- **Webhook Integration**: Secure webhook endpoint with signature verification
- **Admin Interface**: Complete admin panel for settings, referrer management, logs, and health checks

## Requirements

- ClipBucket v4.x or compatible
- PHP 7.4+
- MySQL 5.7+
- cURL extension enabled
- Rewardful account with API access

## Installation

1. Copy the `rewardful_referrals` folder to your ClipBucket `/plugins/` directory
2. Navigate to Admin → Plugins and activate "Rewardful Referrals"
3. Go to Tool Box → Rewardful Settings to configure your API keys

## Configuration

### Rewardful API Keys

Obtain these from your Rewardful dashboard (Settings → Integrations):

| Setting | Description |
|---------|-------------|
| Public API Key | Used for client-side tracking (safe to expose) |
| API Secret | Used for server-side API calls (keep secret) |
| Webhook Secret | Used to verify incoming webhooks (keep secret) |

### Webhook Setup

Configure your Rewardful webhook URL:
```
https://yoursite.com/plugins/rewardful_referrals/webhook.php
```

### Conversion Tracking

The plugin supports two modes:

1. **Auto-detect**: Automatically detects common success page patterns
2. **Manual**: Specify custom URL patterns where conversions should fire

## Referrer Workflow

### Admin Actions

1. **Create Referrer**: Add referrer by email or ClipBucket user
2. **Approve/Reject**: Control who can access the referral program
3. **Sync with Rewardful**: Create/update affiliate in Rewardful
4. **Disable**: Temporarily disable a referrer

### User Flow

1. Admin approves user as referrer
2. User sees "Referrals" section on `/account/` page
3. User can access `/my_account/referrals` for full dashboard
4. User can SSO into Rewardful for detailed stats and payouts

### Self-Apply (Optional)

Enable in settings to allow users to apply to become referrers:
- Users submit application from `/my_account/referrals`
- Admin reviews and approves/rejects

## Database Tables

| Table | Purpose |
|-------|---------|
| `cb_rewardful_settings` | Plugin configuration |
| `cb_rewardful_referrers` | Referrer records and status |
| `cb_rewardful_affiliates` | Rewardful affiliate data cache |
| `cb_rewardful_events` | Webhook events (idempotent) |
| `cb_rewardful_conversions` | Conversion tracking |
| `cb_rewardful_logs` | Activity and error logs |

## File Structure

```
plugins/rewardful_referrals/
├── rewardful_referrals.php    # Main plugin bootstrap
├── install.php                 # Database setup
├── uninstall.php              # Cleanup (optional)
├── webhook.php                # Webhook endpoint
├── admin/
│   ├── settings.php           # Configuration page
│   ├── referrers.php          # Referrer management
│   ├── logs.php               # Activity logs
│   └── health.php             # Health check dashboard
├── user/
│   └── referrals.php          # User referrals page
├── api/
│   ├── referrer_sso.php       # SSO redirect
│   ├── referrer_link.php      # Get referral link
│   ├── convert_ping.php       # Conversion confirmation
│   └── capture_via.php        # Via token capture
├── lib/
│   ├── Db.php                 # Database helper
│   ├── Auth.php               # Authentication/authorization
│   ├── RewardfulClient.php    # Rewardful API client
│   ├── RewardfulService.php   # Business logic
│   └── ClipBucketHooks.php    # Hook integration
└── assets/
    ├── css/referrals.css      # Widget styles
    └── js/rewardful-tracking.js # Client-side tracking
```

## Security

- API secrets never exposed client-side
- CSRF protection on all admin forms
- Webhook signature verification (HMAC-SHA256)
- Referral access gated by approval status
- Prepared statements for all database queries
- Idempotent conversion and event handling

## Troubleshooting

### Health Check

Visit Tool Box → Rewardful Health to diagnose issues:
- API authentication status
- JS snippet configuration
- Last conversion/webhook timestamps
- Database connectivity

### Common Issues

1. **API Authentication Failed**: Verify API secret is correct
2. **Conversions Not Tracking**: Check JS snippet is loading, verify success page patterns
3. **SSO Not Working**: Ensure affiliate is synced with Rewardful
4. **Webhooks Failing**: Verify webhook secret and check logs

## License

GPL-2.0+

## Support

For issues and feature requests, contact support or open an issue in the repository.
