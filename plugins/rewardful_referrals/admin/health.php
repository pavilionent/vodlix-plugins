<?php
/**
 * Rewardful Referrals - Admin Health Check Page
 * 
 * Test API connection, verify JS snippet installation, check system status
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
use RewardfulReferrals\RewardfulClient;
use RewardfulReferrals\RewardfulService;

$db = new Db();
$auth = new Auth();
$service = new RewardfulService();
$client = new RewardfulClient();

// Run health check
$health = $service->healthCheck();

// Additional checks
$checks = array();

// 1. Plugin Enabled
$checks['enabled'] = array(
    'name' => 'Plugin Enabled',
    'status' => $health['enabled'],
    'message' => $health['enabled'] ? 'Rewardful integration is enabled' : 'Integration is disabled',
    'icon' => 'power-off'
);

// 2. Public API Key
$publicKey = $service->getPublicApiKey();
$checks['public_key'] = array(
    'name' => 'Public API Key',
    'status' => !empty($publicKey),
    'message' => !empty($publicKey) 
        ? 'Public API key is configured (' . substr($publicKey, 0, 10) . '...)' 
        : 'Public API key not configured',
    'icon' => 'key'
);

// 3. API Secret
$checks['api_secret'] = array(
    'name' => 'API Secret',
    'status' => $health['configured'],
    'message' => $health['configured'] 
        ? 'API secret is configured' 
        : 'API secret not configured',
    'icon' => 'lock'
);

// 4. API Authentication
$checks['api_auth'] = array(
    'name' => 'API Authentication',
    'status' => $health['api_auth'],
    'message' => $health['api_auth'] 
        ? 'Successfully authenticated with Rewardful API' 
        : 'API authentication failed' . ($health['api_error'] ? ': ' . $health['api_error'] : ''),
    'icon' => 'shield-alt'
);

// 5. JS Snippet
$checks['js_snippet'] = array(
    'name' => 'JavaScript Snippet',
    'status' => $health['js_snippet'],
    'message' => $health['js_snippet'] 
        ? 'JS snippet will be injected on all pages' 
        : 'JS snippet not configured (missing public API key)',
    'icon' => 'code'
);

// 6. Webhook Secret
$webhookSecret = $db->getSetting('webhook_secret', '');
$checks['webhook'] = array(
    'name' => 'Webhook Secret',
    'status' => !empty($webhookSecret),
    'message' => !empty($webhookSecret) 
        ? 'Webhook signing secret is configured' 
        : 'Webhook secret not configured (webhook signature verification disabled)',
    'icon' => 'plug'
);

// 7. Last Conversion
$checks['last_conversion'] = array(
    'name' => 'Last Conversion',
    'status' => !empty($health['last_conversion']),
    'message' => !empty($health['last_conversion']) 
        ? 'Last conversion: ' . date('M j, Y g:i A', strtotime($health['last_conversion']))
        : 'No conversions recorded yet',
    'icon' => 'chart-line',
    'neutral' => empty($health['last_conversion'])
);

// 8. Last Webhook
$checks['last_webhook'] = array(
    'name' => 'Last Webhook',
    'status' => !empty($health['last_webhook']),
    'message' => !empty($health['last_webhook']) 
        ? 'Last webhook: ' . date('M j, Y g:i A', strtotime($health['last_webhook']))
        : 'No webhooks received yet',
    'icon' => 'satellite-dish',
    'neutral' => empty($health['last_webhook'])
);

// 9. Database Tables
$tablesOk = true;
try {
    $db->getAllSettings();
    $db->countReferrers();
} catch (Exception $e) {
    $tablesOk = false;
}
$checks['database'] = array(
    'name' => 'Database Tables',
    'status' => $tablesOk,
    'message' => $tablesOk 
        ? 'All required database tables exist and are accessible' 
        : 'Database tables may not be properly initialized',
    'icon' => 'database'
);

// Calculate overall health score
$total = count($checks);
$passed = 0;
foreach ($checks as $check) {
    if ($check['status'] || !empty($check['neutral'])) {
        $passed++;
    }
}
$healthScore = round(($passed / $total) * 100);

$page_title = 'Rewardful Health Check';
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
        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; }
        .nav-pills .nav-link { color: #666; border-radius: 8px; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .back-link { color: rgba(255,255,255,0.9); text-decoration: none; }
        .back-link:hover { color: white; }
        .health-score { 
            width: 150px; height: 150px; border-radius: 50%; 
            display: flex; align-items: center; justify-content: center;
            font-size: 48px; font-weight: bold; margin: 0 auto 20px;
        }
        .health-score.good { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; }
        .health-score.warning { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; }
        .health-score.bad { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; }
        .check-item { padding: 15px 0; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; }
        .check-item:last-child { border-bottom: none; }
        .check-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; margin-right: 15px; }
        .check-icon.pass { background: #d1fae5; color: #059669; }
        .check-icon.fail { background: #fee2e2; color: #dc2626; }
        .check-icon.neutral { background: #f3f4f6; color: #6b7280; }
        .check-status { width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-left: auto; }
        .check-status.pass { background: #10b981; color: white; }
        .check-status.fail { background: #ef4444; color: white; }
        .check-status.neutral { background: #9ca3af; color: white; }
        .check-name { font-weight: 500; color: #111; }
        .check-message { font-size: 13px; color: #666; }
        .info-box { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; }
        .info-box code { background: #e5e7eb; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <a href="/admin_area" class="back-link"><i class="fas fa-arrow-left me-2"></i>Back to Admin</a>
            <h1 class="mt-3"><i class="fas fa-heartbeat me-2"></i><?php echo $page_title; ?></h1>
            <p class="mb-0 mt-2 opacity-75">Monitor your Rewardful integration status and troubleshoot issues</p>
        </div>
    </div>
    
    <div class="container">
        <!-- Navigation -->
        <ul class="nav nav-pills mb-4">
            <li class="nav-item">
                <a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="referrers.php"><i class="fas fa-users me-2"></i>Referrers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php"><i class="fas fa-list-alt me-2"></i>Logs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="health.php"><i class="fas fa-heartbeat me-2"></i>Health</a>
            </li>
        </ul>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Health Checks -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tasks me-2"></i>System Checks
                    </div>
                    <div class="card-body">
                        <?php foreach ($checks as $key => $check): 
                            $isNeutral = !empty($check['neutral']);
                            $statusClass = $isNeutral ? 'neutral' : ($check['status'] ? 'pass' : 'fail');
                        ?>
                        <div class="check-item">
                            <div class="check-icon <?php echo $statusClass; ?>">
                                <i class="fas fa-<?php echo $check['icon']; ?>"></i>
                            </div>
                            <div>
                                <div class="check-name"><?php echo $check['name']; ?></div>
                                <div class="check-message"><?php echo htmlspecialchars($check['message']); ?></div>
                            </div>
                            <div class="check-status <?php echo $statusClass; ?>">
                                <?php if ($isNeutral): ?>
                                <i class="fas fa-minus" style="font-size: 10px;"></i>
                                <?php elseif ($check['status']): ?>
                                <i class="fas fa-check" style="font-size: 10px;"></i>
                                <?php else: ?>
                                <i class="fas fa-times" style="font-size: 10px;"></i>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-flex gap-3 flex-wrap">
                            <a href="settings.php" class="btn btn-outline-primary">
                                <i class="fas fa-cog me-2"></i>Configure Settings
                            </a>
                            <a href="referrers.php" class="btn btn-outline-primary">
                                <i class="fas fa-user-plus me-2"></i>Add Referrer
                            </a>
                            <a href="logs.php" class="btn btn-outline-primary">
                                <i class="fas fa-list-alt me-2"></i>View Logs
                            </a>
                            <a href="javascript:location.reload()" class="btn btn-outline-secondary">
                                <i class="fas fa-sync me-2"></i>Refresh
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Health Score -->
                <div class="card text-center">
                    <div class="card-body py-4">
                        <div class="health-score <?php echo $healthScore >= 80 ? 'good' : ($healthScore >= 50 ? 'warning' : 'bad'); ?>">
                            <?php echo $healthScore; ?>%
                        </div>
                        <h5 class="mb-2">Overall Health</h5>
                        <p class="text-muted mb-0">
                            <?php echo $passed; ?> of <?php echo $total; ?> checks passed
                        </p>
                    </div>
                </div>
                
                <!-- Configuration Info -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>Configuration Info
                    </div>
                    <div class="card-body">
                        <div class="info-box">
                            <strong>Webhook URL</strong><br>
                            <code class="small"><?php echo BASEURL; ?>/plugins/rewardful_referrals/webhook.php</code>
                        </div>
                        
                        <div class="info-box">
                            <strong>Plugin Version</strong><br>
                            <code><?php echo REWARDFUL_VERSION; ?></code>
                        </div>
                        
                        <div class="info-box">
                            <strong>Last Health Check</strong><br>
                            <?php 
                            $lastCheck = $db->getSetting('last_health_check');
                            echo $lastCheck ? date('M j, Y g:i A', strtotime($lastCheck)) : 'Never';
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- Help -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-question-circle me-2"></i>Need Help?
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-3">
                            If you're experiencing issues with the Rewardful integration, check the following:
                        </p>
                        <ul class="small text-muted mb-0">
                            <li>Verify your API keys in Rewardful settings</li>
                            <li>Ensure webhook URL is configured in Rewardful</li>
                            <li>Check browser console for JS errors</li>
                            <li>Review the activity logs for errors</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
