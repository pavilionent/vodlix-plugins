<?php
/**
 * Rewardful Referrals - Admin Settings Page
 * 
 * Configuration for Rewardful integration
 */

if (!defined('BASEDIR')) {
    define('BASEDIR', dirname(dirname(dirname(dirname(__FILE__)))));
}

// Include ClipBucket core
require_once BASEDIR . '/includes/config.inc.php';

// Require admin access
if (!has_access('admin_access', true)) {
    die('Access denied');
}

// Load plugin classes
require_once dirname(__DIR__) . '/lib/Db.php';
require_once dirname(__DIR__) . '/lib/Auth.php';
require_once dirname(__DIR__) . '/lib/RewardfulClient.php';
require_once dirname(__DIR__) . '/lib/RewardfulService.php';

use RewardfulReferrals\Db;
use RewardfulReferrals\Auth;
use RewardfulReferrals\RewardfulService;

$db = new Db();
$auth = new Auth();
$service = new RewardfulService();

$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // Verify CSRF
    if (!$auth->validateCSRF()) {
        $message = 'Invalid security token. Please try again.';
        $messageType = 'error';
    } else {
        // Save settings
        $settings = array(
            'enabled' => isset($_POST['enabled']) ? '1' : '0',
            'public_api_key' => trim($_POST['public_api_key'] ?? ''),
            'api_secret' => trim($_POST['api_secret'] ?? ''),
            'webhook_secret' => trim($_POST['webhook_secret'] ?? ''),
            'default_campaign_id' => trim($_POST['default_campaign_id'] ?? ''),
            'allow_self_apply' => isset($_POST['allow_self_apply']) ? '1' : '0',
            'auto_detect_success_pages' => isset($_POST['auto_detect_success_pages']) ? '1' : '0',
            'success_url_patterns' => trim($_POST['success_url_patterns'] ?? ''),
            'conversion_trigger_type' => $_POST['conversion_trigger_type'] ?? 'auto'
        );
        
        foreach ($settings as $key => $value) {
            $db->setSetting($key, $value);
        }
        
        $db->log('info', 'settings_updated', 'Admin updated Rewardful settings', null, userid());
        
        $message = 'Settings saved successfully!';
        $messageType = 'success';
    }
}

// Load current settings
$settings = $db->getAllSettings();

// Set page title
$page_title = 'Rewardful Settings';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .admin-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 0; margin-bottom: 30px; }
        .admin-header h1 { margin: 0; font-size: 28px; }
        .card { border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.08); border-radius: 12px; margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 1px solid #eee; font-weight: 600; padding: 15px 20px; border-radius: 12px 12px 0 0 !important; }
        .form-label { font-weight: 500; color: #333; }
        .form-text { font-size: 13px; color: #666; }
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 10px 25px; }
        .btn-primary:hover { background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%); }
        .form-switch .form-check-input { width: 50px; height: 26px; }
        .form-switch .form-check-input:checked { background-color: #667eea; border-color: #667eea; }
        .alert { border-radius: 10px; }
        .nav-pills .nav-link { color: #666; border-radius: 8px; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .back-link { color: rgba(255,255,255,0.9); text-decoration: none; }
        .back-link:hover { color: white; }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <a href="/admin_area" class="back-link"><i class="fas fa-arrow-left me-2"></i>Back to Admin</a>
            <h1 class="mt-3"><i class="fas fa-cog me-2"></i><?php echo $page_title; ?></h1>
            <p class="mb-0 mt-2 opacity-75">Configure your Rewardful integration for the referral program</p>
        </div>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Navigation -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link active" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="referrers.php"><i class="fas fa-users me-2"></i>Referrers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php"><i class="fas fa-list-alt me-2"></i>Logs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="health.php"><i class="fas fa-heartbeat me-2"></i>Health</a>
            </li>
        </ul>
        
        <form method="post" action="">
            <?php echo $auth->csrfField(); ?>
            
            <!-- General Settings -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-toggle-on me-2"></i>General Settings
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="enabled" name="enabled" 
                            <?php echo ($settings['enabled'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enabled">
                            <strong>Enable Rewardful Integration</strong>
                        </label>
                        <div class="form-text">Turn on/off the entire Rewardful referral tracking system</div>
                    </div>
                    
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="allow_self_apply" name="allow_self_apply"
                            <?php echo ($settings['allow_self_apply'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allow_self_apply">
                            <strong>Allow Self-Apply</strong>
                        </label>
                        <div class="form-text">If enabled, users can apply to become a referrer (requires admin approval)</div>
                    </div>
                </div>
            </div>
            
            <!-- API Configuration -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-key me-2"></i>Rewardful API Configuration
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="public_api_key" class="form-label">Public API Key</label>
                        <input type="text" class="form-control" id="public_api_key" name="public_api_key"
                            value="<?php echo htmlspecialchars($settings['public_api_key'] ?? ''); ?>"
                            placeholder="Enter your Rewardful Public API Key">
                        <div class="form-text">
                            Found in Rewardful → Settings → Integrations. Used for client-side tracking.
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="api_secret" class="form-label">API Secret</label>
                        <input type="password" class="form-control" id="api_secret" name="api_secret"
                            value="<?php echo htmlspecialchars($settings['api_secret'] ?? ''); ?>"
                            placeholder="Enter your Rewardful API Secret">
                        <div class="form-text">
                            Found in Rewardful → Settings → API. Used for server-side API calls (affiliate management, SSO).
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="webhook_secret" class="form-label">Webhook Signing Secret</label>
                        <input type="password" class="form-control" id="webhook_secret" name="webhook_secret"
                            value="<?php echo htmlspecialchars($settings['webhook_secret'] ?? ''); ?>"
                            placeholder="Enter your webhook signing secret">
                        <div class="form-text">
                            Found in Rewardful → Settings → Webhooks. Used to verify incoming webhook requests.
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label for="default_campaign_id" class="form-label">Default Campaign ID (Optional)</label>
                        <input type="text" class="form-control" id="default_campaign_id" name="default_campaign_id"
                            value="<?php echo htmlspecialchars($settings['default_campaign_id'] ?? ''); ?>"
                            placeholder="Enter default campaign ID">
                        <div class="form-text">
                            If specified, new affiliates will be assigned to this campaign by default.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Conversion Tracking -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i>Conversion Tracking
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Conversion Trigger Mode</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="conversion_trigger_type" 
                                id="trigger_auto" value="auto"
                                <?php echo ($settings['conversion_trigger_type'] ?? 'auto') === 'auto' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="trigger_auto">
                                <strong>Auto-detect</strong> - Automatically detect subscription success pages
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="conversion_trigger_type" 
                                id="trigger_manual" value="manual"
                                <?php echo ($settings['conversion_trigger_type'] ?? 'auto') === 'manual' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="trigger_manual">
                                <strong>Manual</strong> - Only fire on manually specified URLs
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="auto_detect_success_pages" 
                            name="auto_detect_success_pages"
                            <?php echo ($settings['auto_detect_success_pages'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="auto_detect_success_pages">
                            <strong>Auto-detect Success Pages</strong>
                        </label>
                        <div class="form-text">
                            Look for common success page patterns (e.g., /subscription/success, /payment/complete)
                        </div>
                    </div>
                    
                    <div class="mb-0">
                        <label for="success_url_patterns" class="form-label">Success URL Patterns (One per line)</label>
                        <textarea class="form-control" id="success_url_patterns" name="success_url_patterns" 
                            rows="4" placeholder="/subscription/success&#10;/checkout/thankyou"><?php 
                            echo htmlspecialchars($settings['success_url_patterns'] ?? ''); 
                        ?></textarea>
                        <div class="form-text">
                            Manually specify URL patterns where conversion should be fired. Uses substring matching.
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Webhook Configuration -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plug me-2"></i>Webhook Endpoint
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Webhook URL:</strong>
                        <code class="ms-2"><?php echo BASEURL; ?>/plugins/rewardful_referrals/webhook.php</code>
                        <br><br>
                        Configure this URL in your Rewardful webhook settings to receive real-time event notifications.
                    </div>
                </div>
            </div>
            
            <div class="d-flex gap-3">
                <button type="submit" name="save_settings" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Save Settings
                </button>
                <a href="health.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-heartbeat me-2"></i>Test Connection
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
