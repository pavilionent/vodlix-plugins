# Churnkey + Stripe Cancel Flow Plugin

A ClipBucket plugin that integrates [Churnkey](https://churnkey.co) with Stripe to handle subscription cancellations through Churnkey's retention modal.

## Features

- **Native Integration**: Injects a "Cancel Subscription" button directly on the `/account/` page, next to the existing "Select Plan" button
- **Churnkey Modal**: Opens Churnkey's cancel flow modal for a seamless user experience
- **Stripe Integration**: Resolves Stripe customer and subscription IDs automatically
- **Secure Auth**: Generates HMAC-SHA256 auth hashes server-side; never exposes API keys client-side
- **Local Mapping**: Caches Stripe customer/subscription mappings for performance
- **Email Fallback**: Can query Stripe by email if no local mapping exists
- **Webhook Support**: Receives and logs Churnkey events for debugging and automation
- **Admin Dashboard**: Configure all settings through an admin UI

## Installation

1. **Copy Plugin Files**
   
   Copy the entire `churnkey_stripe` folder to your ClipBucket `plugins/` directory:
   
   ```
   /plugins/churnkey_stripe/
   ```

2. **Create Database Tables**
   
   The plugin will automatically create tables on first load, or you can manually run:
   
   ```php
   require_once 'plugins/churnkey_stripe/install.php';
   churnkey_stripe_install();
   ```

3. **Configure Settings**
   
   Navigate to **Admin Panel → Payments → Churnkey + Stripe** and enter:
   
   - **Churnkey App ID** (required)
   - **Churnkey API Key** (required)
   - **Mode**: `test` or `live`
   - **Record Sessions**: Enable session recording
   - **Stripe Secret Key** (optional, for email-based customer lookup)
   - **Webhook Secret** (optional, for webhook signature verification)

4. **Enable the Plugin**
   
   Check "Enable Plugin" in the admin settings.

## Configuration

### Required Settings

| Setting | Description |
|---------|-------------|
| Churnkey App ID | Your Churnkey application ID (e.g., `ck_app_xxxxx`) |
| Churnkey API Key | Your Churnkey API key for auth hash generation |

### Optional Settings

| Setting | Description |
|---------|-------------|
| Mode | `test` for development, `live` for production |
| Record Sessions | Enable Churnkey session recording |
| Stripe Secret Key | For email-based customer lookup fallback |
| Webhook Secret | For verifying Churnkey webhook signatures |

## Database Tables

The plugin creates the following tables (with your configured prefix):

### `{prefix}churnkey_settings`
Stores plugin configuration.

### `{prefix}churnkey_stripe_map`
Maps ClipBucket user IDs to Stripe customer/subscription IDs.

| Column | Type | Description |
|--------|------|-------------|
| userid | INT | ClipBucket user ID |
| stripe_customer_id | VARCHAR | Stripe customer ID |
| stripe_subscription_id | VARCHAR | Stripe subscription ID |
| stripe_email | VARCHAR | User email |
| status | ENUM | active, canceled, past_due, trialing, unpaid |

### `{prefix}churnkey_events`
Logs webhook events from Churnkey.

### `{prefix}churnkey_logs`
Debug and error logs.

## API Endpoints

### GET `/plugins/churnkey_stripe/api/context.php`

Returns the signed Churnkey context for the currently logged-in user.

**Response:**
```json
{
  "success": true,
  "context": {
    "appId": "ck_app_xxxxx",
    "mode": "test",
    "record": true,
    "provider": "stripe",
    "customerId": "cus_xxxxx",
    "subscriptionId": "sub_xxxxx",
    "authHash": "..."
  }
}
```

**Security:**
- Requires authenticated user
- Only returns context for the current user (cannot request arbitrary customer IDs)
- Never exposes API keys

### POST `/plugins/churnkey_stripe/webhook.php`

Receives webhook events from Churnkey.

Configure this URL in your Churnkey dashboard to receive:
- `subscription.canceled`
- `subscription.paused`
- `subscription.reactivated`
- `offer.accepted`
- `session.completed`

## Stripe Customer Resolution

The plugin resolves Stripe customer IDs in this order:

1. **Local Mapping Table**: Checks `{prefix}churnkey_stripe_map` for cached mapping
2. **Stripe API Lookup**: If no mapping found and Stripe key is configured, queries Stripe Customers API by email
3. **Cache Result**: Saves successful lookups to the mapping table

### Populating Mappings

You can populate mappings when users subscribe:

```php
// After successful Stripe subscription creation
churnkey_stripe_save_mapping(
    $userid,              // ClipBucket user ID
    $stripe_customer_id,  // Stripe customer ID
    $stripe_subscription_id, // Stripe subscription ID (optional)
    $user_email           // User email (optional)
);
```

## Customization

### Button Placement

The cancel button is automatically placed next to the "Select Plan" button on `/account/`. The button finder uses multiple strategies:

1. Text matching: "Select Plan", "Change Plan", "Manage Plan"
2. Section matching: Elements with classes containing "membership", "billing", "subscription"
3. Container matching: `.subscription-actions`, `.plan-actions`, etc.

### Styling

The cancel button inherits CSS classes from the adjacent "Select Plan" button. You can also target it with:

```css
#churnkey-cancel-subscription-btn,
.cancel-subscription-btn {
    /* Your custom styles */
}
```

## Troubleshooting

### Cancel Button Not Appearing

1. Check that the plugin is enabled in admin settings
2. Verify Churnkey App ID is configured
3. Check browser console for errors
4. Ensure user is logged in

### "No Active Subscription" Error

1. Verify user has a Stripe customer mapping
2. Check that customer has an active subscription in Stripe
3. If using email fallback, verify Stripe Secret Key is configured

### Auth Hash Errors

1. Verify Churnkey API Key is correctly configured
2. Check that the API key matches between ClipBucket and Churnkey dashboard

### Webhook Not Working

1. Verify webhook URL is correctly configured in Churnkey
2. Check `{prefix}churnkey_events` table for received events
3. If using signature verification, ensure webhook secret matches

## Security Considerations

- API keys are never exposed client-side
- Auth hashes are generated server-side using HMAC-SHA256
- Context endpoint only returns data for the authenticated user
- Webhook signatures are verified when secret is configured
- All database queries use prepared statements/escaping

## Support

For issues with:
- **This plugin**: Check the logs in Admin → Churnkey + Stripe → Logs tab
- **Churnkey**: Contact [Churnkey Support](https://churnkey.co)
- **Stripe**: Check [Stripe Documentation](https://stripe.com/docs)

## License

This plugin is provided as-is for use with ClipBucket video CMS.
