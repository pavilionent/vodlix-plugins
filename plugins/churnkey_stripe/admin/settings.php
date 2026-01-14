<?php
/**
 * Admin Settings Page for Churnkey + Stripe Plugin
 * Provides configuration interface for admin users
 * 
 * @package ChurnkeyStripe
 */

if (!defined('BASEDIR')) {
    // Standalone access - load ClipBucket
    $base_dir = dirname(dirname(dirname(dirname(__FILE__))));
    if (file_exists($base_dir . '/includes/config.inc.php')) {
        require_once $base_dir . '/includes/config.inc.php';
    } elseif (file_exists($base_dir . '/config.inc.php')) {
        require_once $base_dir . '/config.inc.php';
    }
}

// Ensure plugin files are loaded
$plugin_dir = dirname(dirname(__FILE__));
require_once $plugin_dir . '/includes/db_helper.php';
require_once $plugin_dir . '/includes/settings.php';
require_once $plugin_dir . '/includes/user.php';
require_once $plugin_dir . '/includes/stripe_client.php';
require_once $plugin_dir . '/includes/churnkey.php';

// Check admin access
churnkey_stripe_require_admin();

// Handle form submission
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection (if ClipBucket provides a mechanism)
    $valid_nonce = true;
    if (function_exists('verify_nonce')) {
        $valid_nonce = verify_nonce($_POST['_nonce'] ?? '', 'churnkey_stripe_settings');
    }
    
    if (!$valid_nonce) {
        $message = 'Security check failed. Please try again.';
        $message_type = 'error';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : 'save_settings';
        
        switch ($action) {
            case 'save_settings':
                // Save settings
                $settings = array(
                    'churnkey_app_id' => isset($_POST['churnkey_app_id']) ? trim($_POST['churnkey_app_id']) : '',
                    'churnkey_api_key' => isset($_POST['churnkey_api_key']) ? trim($_POST['churnkey_api_key']) : '',
                    'churnkey_mode' => isset($_POST['churnkey_mode']) ? $_POST['churnkey_mode'] : 'test',
                    'churnkey_record' => isset($_POST['churnkey_record']) ? '1' : '0',
                    'stripe_secret_key' => isset($_POST['stripe_secret_key']) ? trim($_POST['stripe_secret_key']) : '',
                    'webhook_secret' => isset($_POST['webhook_secret']) ? trim($_POST['webhook_secret']) : '',
                    'enabled' => isset($_POST['enabled']) ? '1' : '0'
                );
                
                // Don't overwrite API keys if they're submitted as masked
                if (strpos($settings['churnkey_api_key'], '••••') === 0) {
                    unset($settings['churnkey_api_key']);
                }
                if (strpos($settings['stripe_secret_key'], '••••') === 0) {
                    unset($settings['stripe_secret_key']);
                }
                if (strpos($settings['webhook_secret'], '••••') === 0) {
                    unset($settings['webhook_secret']);
                }
                
                if (churnkey_stripe_save_settings($settings)) {
                    $message = 'Settings saved successfully.';
                    $message_type = 'success';
                    churnkey_stripe_log('info', 'Admin settings updated');
                } else {
                    $message = 'Failed to save settings. Please try again.';
                    $message_type = 'error';
                }
                break;
                
            case 'test_stripe':
                // Test Stripe connection by resolving current admin user
                $email = churnkey_stripe_get_current_email();
                if (!$email) {
                    $message = 'Could not determine your email address.';
                    $message_type = 'error';
                } else {
                    $stripe = churnkey_stripe_client();
                    if (!$stripe->isConfigured()) {
                        $message = 'Stripe API key not configured.';
                        $message_type = 'error';
                    } else {
                        $customer = $stripe->findCustomerByEmail($email);
                        if ($customer) {
                            $subscriptions = $stripe->getCustomerSubscriptions($customer['id']);
                            $sub_count = count($subscriptions);
                            $message = "Found Stripe customer: {$customer['id']} ({$customer['email']}). Active subscriptions: {$sub_count}";
                            $message_type = 'success';
                        } else {
                            $message = "No Stripe customer found for email: {$email}";
                            $message_type = 'warning';
                        }
                    }
                }
                break;
                
            case 'clear_logs':
                if (churnkey_stripe_clear_old_logs(0)) {
                    $message = 'Logs cleared successfully.';
                    $message_type = 'success';
                } else {
                    $message = 'Failed to clear logs.';
                    $message_type = 'error';
                }
                break;
                
            case 'reinstall':
                require_once $plugin_dir . '/install.php';
                $results = churnkey_stripe_install();
                $message = 'Database tables reinstalled.';
                $message_type = 'success';
                break;
        }
    }
}

// Get current settings
$settings = churnkey_stripe_get_all_settings();
$config_status = churnkey_stripe_is_configured();

// Get recent logs
$recent_logs = churnkey_stripe_get_logs(20);

// Get recent events
$recent_events = churnkey_stripe_get_events(20);

// Generate nonce if available
$nonce = '';
if (function_exists('create_nonce')) {
    $nonce = create_nonce('churnkey_stripe_settings');
}

// Mask sensitive values for display
function mask_secret($value) {
    if (empty($value)) return '';
    $length = strlen($value);
    if ($length <= 8) return str_repeat('•', $length);
    return '••••' . substr($value, -4);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Churnkey + Stripe Settings</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { margin-bottom: 20px; color: #1a1a1a; }
        h2 { margin-top: 30px; margin-bottom: 15px; color: #333; border-bottom: 2px solid #e0e0e0; padding-bottom: 10px; }
        .card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 5px; color: #555; }
        .help-text { font-size: 0.85em; color: #888; margin-top: 5px; }
        input[type="text"], input[type="password"], select { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
        input[type="text"]:focus, input[type="password"]:focus, select:focus { border-color: #4a90d9; outline: none; box-shadow: 0 0 0 3px rgba(74,144,217,0.1); }
        input[type="checkbox"] { width: auto; margin-right: 8px; }
        .checkbox-label { display: flex; align-items: center; font-weight: normal; }
        .btn { display: inline-block; padding: 10px 20px; border: none; border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; transition: background 0.2s; }
        .btn-primary { background: #4a90d9; color: white; }
        .btn-primary:hover { background: #3a7dc4; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-secondary:hover { background: #5a6268; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-danger:hover { background: #c82333; }
        .btn-success { background: #28a745; color: white; }
        .btn-success:hover { background: #218838; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .alert-info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .status-badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-badge.success { background: #d4edda; color: #155724; }
        .status-badge.warning { background: #fff3cd; color: #856404; }
        .status-badge.error { background: #f8d7da; color: #721c24; }
        .row { display: flex; flex-wrap: wrap; margin: -10px; }
        .col { padding: 10px; flex: 1; min-width: 300px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; color: #555; }
        tr:hover { background: #f8f9fa; }
        .log-level { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .log-level.debug { background: #e9ecef; color: #6c757d; }
        .log-level.info { background: #d1ecf1; color: #0c5460; }
        .log-level.warning { background: #fff3cd; color: #856404; }
        .log-level.error { background: #f8d7da; color: #721c24; }
        .truncate { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .tabs { display: flex; border-bottom: 2px solid #e0e0e0; margin-bottom: 20px; }
        .tab { padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; font-weight: 600; color: #666; }
        .tab:hover { color: #333; }
        .tab.active { border-bottom-color: #4a90d9; color: #4a90d9; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .actions { display: flex; gap: 10px; margin-top: 20px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Churnkey + Stripe Settings</h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Status Overview -->
        <div class="card">
            <h2 style="margin-top: 0;">Integration Status</h2>
            <div class="row">
                <div class="col">
                    <strong>Plugin Status:</strong><br>
                    <?php if (isset($settings['enabled']) && $settings['enabled'] === '1'): ?>
                        <span class="status-badge success">Enabled</span>
                    <?php else: ?>
                        <span class="status-badge warning">Disabled</span>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <strong>Configuration:</strong><br>
                    <?php if ($config_status['configured']): ?>
                        <span class="status-badge success">Configured</span>
                    <?php else: ?>
                        <span class="status-badge error">Missing: <?php echo htmlspecialchars(implode(', ', $config_status['missing'])); ?></span>
                    <?php endif; ?>
                </div>
                <div class="col">
                    <strong>Mode:</strong><br>
                    <?php $mode = isset($settings['churnkey_mode']) ? $settings['churnkey_mode'] : 'test'; ?>
                    <span class="status-badge <?php echo $mode === 'live' ? 'success' : 'warning'; ?>">
                        <?php echo ucfirst($mode); ?>
                    </span>
                </div>
                <div class="col">
                    <strong>Account Page Integration:</strong><br>
                    <?php if ($config_status['configured'] && isset($settings['enabled']) && $settings['enabled'] === '1'): ?>
                        <span class="status-badge success">Active</span>
                    <?php else: ?>
                        <span class="status-badge warning">Inactive</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="settings">Settings</div>
            <div class="tab" data-tab="logs">Logs</div>
            <div class="tab" data-tab="events">Events</div>
            <div class="tab" data-tab="tools">Tools</div>
        </div>
        
        <!-- Settings Tab -->
        <div id="settings" class="tab-content active">
            <div class="card">
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    <?php if ($nonce): ?>
                    <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars($nonce); ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col">
                            <h3>Churnkey Configuration</h3>
                            
                            <div class="form-group">
                                <label for="churnkey_app_id">Churnkey App ID *</label>
                                <input type="text" id="churnkey_app_id" name="churnkey_app_id" 
                                       value="<?php echo htmlspecialchars($settings['churnkey_app_id'] ?? ''); ?>"
                                       placeholder="ck_app_xxxxx">
                                <div class="help-text">Your Churnkey application ID from the dashboard.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="churnkey_api_key">Churnkey API Key *</label>
                                <input type="password" id="churnkey_api_key" name="churnkey_api_key" 
                                       value="<?php echo mask_secret($settings['churnkey_api_key'] ?? ''); ?>"
                                       placeholder="ck_key_xxxxx">
                                <div class="help-text">Your Churnkey API key. Used server-side for auth hash generation.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="churnkey_mode">Mode</label>
                                <select id="churnkey_mode" name="churnkey_mode">
                                    <option value="test" <?php echo ($settings['churnkey_mode'] ?? 'test') === 'test' ? 'selected' : ''; ?>>Test</option>
                                    <option value="live" <?php echo ($settings['churnkey_mode'] ?? 'test') === 'live' ? 'selected' : ''; ?>>Live</option>
                                </select>
                                <div class="help-text">Use "test" mode during development.</div>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="churnkey_record" <?php echo ($settings['churnkey_record'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                    Record Sessions
                                </label>
                                <div class="help-text">Enable session recording for analytics in Churnkey dashboard.</div>
                            </div>
                        </div>
                        
                        <div class="col">
                            <h3>Stripe Configuration</h3>
                            
                            <div class="form-group">
                                <label for="stripe_secret_key">Stripe Secret Key (Optional)</label>
                                <input type="password" id="stripe_secret_key" name="stripe_secret_key" 
                                       value="<?php echo mask_secret($settings['stripe_secret_key'] ?? ''); ?>"
                                       placeholder="sk_test_xxxxx or sk_live_xxxxx">
                                <div class="help-text">Used for email-based customer lookup fallback. Leave empty if you manage mappings manually.</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="webhook_secret">Webhook Secret (Optional)</label>
                                <input type="password" id="webhook_secret" name="webhook_secret" 
                                       value="<?php echo mask_secret($settings['webhook_secret'] ?? ''); ?>"
                                       placeholder="whsec_xxxxx">
                                <div class="help-text">For verifying webhook signatures from Churnkey.</div>
                            </div>
                            
                            <h3>Plugin Status</h3>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="enabled" <?php echo ($settings['enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                    Enable Plugin
                                </label>
                                <div class="help-text">Enable the cancel button injection on /account/ page.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Logs Tab -->
        <div id="logs" class="tab-content">
            <div class="card">
                <h3>Recent Logs</h3>
                <?php if (empty($recent_logs)): ?>
                    <p>No logs yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Level</th>
                                <th>Message</th>
                                <th>User ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                <td><span class="log-level <?php echo htmlspecialchars($log['level']); ?>"><?php echo htmlspecialchars($log['level']); ?></span></td>
                                <td class="truncate" title="<?php echo htmlspecialchars($log['message']); ?>"><?php echo htmlspecialchars($log['message']); ?></td>
                                <td><?php echo $log['userid'] ? htmlspecialchars($log['userid']) : '-'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <form method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="clear_logs">
                    <?php if ($nonce): ?>
                    <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars($nonce); ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to clear all logs?');">Clear All Logs</button>
                </form>
            </div>
        </div>
        
        <!-- Events Tab -->
        <div id="events" class="tab-content">
            <div class="card">
                <h3>Recent Events</h3>
                <?php if (empty($recent_events)): ?>
                    <p>No events yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Event Type</th>
                                <th>Customer ID</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_events as $event): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($event['created_at']); ?></td>
                                <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                <td class="truncate"><?php echo htmlspecialchars($event['customer_id'] ?? '-'); ?></td>
                                <td><span class="log-level <?php echo $event['status'] === 'processed' ? 'info' : ($event['status'] === 'failed' ? 'error' : 'debug'); ?>"><?php echo htmlspecialchars($event['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tools Tab -->
        <div id="tools" class="tab-content">
            <div class="card">
                <h3>Diagnostic Tools</h3>
                
                <div class="row">
                    <div class="col">
                        <h4>Test Stripe Connection</h4>
                        <p>Test the Stripe API connection by searching for your admin account email.</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="test_stripe">
                            <?php if ($nonce): ?>
                            <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars($nonce); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-secondary">Test Stripe Lookup</button>
                        </form>
                    </div>
                    
                    <div class="col">
                        <h4>Reinstall Database Tables</h4>
                        <p>Recreate database tables (will not delete existing data).</p>
                        <form method="POST">
                            <input type="hidden" name="action" value="reinstall">
                            <?php if ($nonce): ?>
                            <input type="hidden" name="_nonce" value="<?php echo htmlspecialchars($nonce); ?>">
                            <?php endif; ?>
                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure you want to reinstall database tables?');">Reinstall Tables</button>
                        </form>
                    </div>
                </div>
                
                <hr style="margin: 30px 0;">
                
                <h4>Integration Information</h4>
                <table>
                    <tr>
                        <td><strong>Context API Endpoint:</strong></td>
                        <td><code>/plugins/churnkey_stripe/api/context.php</code></td>
                    </tr>
                    <tr>
                        <td><strong>Webhook Endpoint:</strong></td>
                        <td><code>/plugins/churnkey_stripe/webhook.php</code></td>
                    </tr>
                    <tr>
                        <td><strong>Cancel Button Target:</strong></td>
                        <td><code>/account/</code> page, next to "Select Plan" button</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <script>
    // Tab switching
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            var tabId = this.getAttribute('data-tab');
            
            // Update active tab
            document.querySelectorAll('.tab').forEach(function(t) { t.classList.remove('active'); });
            this.classList.add('active');
            
            // Update active content
            document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // Show/hide password fields on focus
    document.querySelectorAll('input[type="password"]').forEach(function(input) {
        var originalValue = input.value;
        input.addEventListener('focus', function() {
            if (this.value.indexOf('••••') === 0) {
                this.value = '';
                this.type = 'text';
            }
        });
        input.addEventListener('blur', function() {
            if (this.value === '' && originalValue) {
                this.value = originalValue;
                this.type = 'password';
            }
        });
    });
    </script>
</body>
</html>
