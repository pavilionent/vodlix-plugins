<?php
/**
 * Rewardful Referrals - Admin Logs Page
 * 
 * View webhook events, API calls, conversions, and errors
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

use RewardfulReferrals\Db;
use RewardfulReferrals\Auth;

$db = new Db();
$auth = new Auth();

$message = '';
$messageType = '';

// Handle clear logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCSRF()) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'clear_old_logs') {
        $days = (int)($_POST['days'] ?? 90);
        $db->clearOldLogs($days);
        $message = "Logs older than {$days} days have been cleared.";
        $messageType = 'success';
    }
}

// Filters
$logType = $_GET['type'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Get logs
$logs = $db->getLogs($logType === 'all' ? null : $logType, $perPage, $offset);

// Get events
$events = $db->getRecentEvents(20);

// Get conversions
$conversions = $db->getRecentConversions(20);

$page_title = 'Rewardful Logs';
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
        .log-type { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .log-type-api { background: #dbeafe; color: #1e40af; }
        .log-type-webhook { background: #f3e8ff; color: #6b21a8; }
        .log-type-conversion { background: #d1fae5; color: #065f46; }
        .log-type-error { background: #fee2e2; color: #991b1b; }
        .log-type-info { background: #e5e7eb; color: #374151; }
        .log-entry { border-bottom: 1px solid #f0f0f0; padding: 12px 0; }
        .log-entry:last-child { border-bottom: none; }
        .log-time { font-size: 12px; color: #888; }
        .log-action { font-weight: 500; color: #333; }
        .log-message { color: #666; font-size: 14px; }
        pre.json-preview { background: #f8f9fa; padding: 10px; border-radius: 6px; font-size: 12px; max-height: 200px; overflow: auto; }
        .nav-tabs .nav-link { border-radius: 8px 8px 0 0; }
        .nav-tabs .nav-link.active { background: white; border-bottom: 2px solid #667eea; font-weight: 600; }
        .status-badge { padding: 3px 8px; border-radius: 4px; font-size: 11px; }
        .status-received { background: #dbeafe; color: #1e40af; }
        .status-processed { background: #d1fae5; color: #065f46; }
        .status-failed { background: #fee2e2; color: #991b1b; }
        .status-sent { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #d1fae5; color: #065f46; }
        .status-pending { background: #e5e7eb; color: #374151; }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <a href="/admin_area" class="back-link"><i class="fas fa-arrow-left me-2"></i>Back to Admin</a>
            <h1 class="mt-3"><i class="fas fa-list-alt me-2"></i><?php echo $page_title; ?></h1>
            <p class="mb-0 mt-2 opacity-75">View webhook events, API calls, conversions, and system logs</p>
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
                <a class="nav-link" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="referrers.php"><i class="fas fa-users me-2"></i>Referrers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="logs.php"><i class="fas fa-list-alt me-2"></i>Logs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="health.php"><i class="fas fa-heartbeat me-2"></i>Health</a>
            </li>
        </ul>
        
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs mb-3" id="logTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                    <i class="fas fa-history me-2"></i>Activity Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="webhooks-tab" data-bs-toggle="tab" data-bs-target="#webhooks" type="button">
                    <i class="fas fa-plug me-2"></i>Webhook Events
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="conversions-tab" data-bs-toggle="tab" data-bs-target="#conversions" type="button">
                    <i class="fas fa-chart-line me-2"></i>Conversions
                </button>
            </li>
        </ul>
        
        <div class="tab-content">
            <!-- Activity Logs Tab -->
            <div class="tab-pane fade show active" id="activity" role="tabpanel">
                <!-- Filter -->
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div class="btn-group" role="group">
                        <a href="?type=all" class="btn btn-<?php echo $logType === 'all' ? 'primary' : 'outline-secondary'; ?> btn-sm">All</a>
                        <a href="?type=api" class="btn btn-<?php echo $logType === 'api' ? 'primary' : 'outline-secondary'; ?> btn-sm">API</a>
                        <a href="?type=webhook" class="btn btn-<?php echo $logType === 'webhook' ? 'primary' : 'outline-secondary'; ?> btn-sm">Webhook</a>
                        <a href="?type=conversion" class="btn btn-<?php echo $logType === 'conversion' ? 'primary' : 'outline-secondary'; ?> btn-sm">Conversion</a>
                        <a href="?type=error" class="btn btn-<?php echo $logType === 'error' ? 'primary' : 'outline-secondary'; ?> btn-sm">Error</a>
                        <a href="?type=info" class="btn btn-<?php echo $logType === 'info' ? 'primary' : 'outline-secondary'; ?> btn-sm">Info</a>
                    </div>
                    
                    <form method="post" action="" class="d-flex gap-2">
                        <?php echo $auth->csrfField(); ?>
                        <input type="hidden" name="action" value="clear_old_logs">
                        <select name="days" class="form-select form-select-sm" style="width: auto;">
                            <option value="30">30 days</option>
                            <option value="60">60 days</option>
                            <option value="90" selected>90 days</option>
                        </select>
                        <button type="submit" class="btn btn-outline-danger btn-sm" 
                            onclick="return confirm('Are you sure you want to delete old logs?')">
                            <i class="fas fa-trash me-1"></i>Clear Old Logs
                        </button>
                    </form>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($logs)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No logs found
                        </div>
                        <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <div class="log-entry">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="log-type log-type-<?php echo $log['log_type']; ?>">
                                        <?php echo $log['log_type']; ?>
                                    </span>
                                    <span class="log-action ms-2"><?php echo htmlspecialchars($log['action']); ?></span>
                                    <div class="log-message mt-1"><?php echo htmlspecialchars($log['message']); ?></div>
                                    <?php if ($log['context_json']): ?>
                                    <details class="mt-2">
                                        <summary class="text-muted" style="cursor: pointer; font-size: 12px;">Show details</summary>
                                        <pre class="json-preview mt-2"><?php 
                                            echo htmlspecialchars(json_encode(json_decode($log['context_json']), JSON_PRETTY_PRINT)); 
                                        ?></pre>
                                    </details>
                                    <?php endif; ?>
                                </div>
                                <span class="log-time">
                                    <?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Webhook Events Tab -->
            <div class="tab-pane fade" id="webhooks" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-plug me-2"></i>Recent Webhook Events</span>
                        <span class="badge bg-secondary"><?php echo count($events); ?> events</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($events)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No webhook events received yet
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Event ID</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Received</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($events as $event): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars(substr($event['event_id'], 0, 20)); ?>...</code></td>
                                        <td><?php echo htmlspecialchars($event['event_type']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $event['status']; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, g:i A', strtotime($event['created_at'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" 
                                                data-bs-toggle="collapse" data-bs-target="#event-<?php echo $event['id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="collapse" id="event-<?php echo $event['id']; ?>">
                                        <td colspan="5">
                                            <pre class="json-preview mb-0"><?php 
                                                echo htmlspecialchars(json_encode(json_decode($event['payload_json']), JSON_PRETTY_PRINT)); 
                                            ?></pre>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Conversions Tab -->
            <div class="tab-pane fade" id="conversions" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-chart-line me-2"></i>Recent Conversions</span>
                        <span class="badge bg-secondary"><?php echo count($conversions); ?> conversions</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($conversions)): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-chart-line fa-2x mb-2 d-block"></i>
                            No conversions recorded yet
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Email</th>
                                        <th>Via Token</th>
                                        <th>Status</th>
                                        <th>Subscription ID</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($conversions as $conversion): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($conversion['email']); ?>
                                            <?php if ($conversion['userid']): ?>
                                            <br><small class="text-muted">User ID: <?php echo $conversion['userid']; ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($conversion['via_token']): ?>
                                            <code><?php echo htmlspecialchars($conversion['via_token']); ?></code>
                                            <?php else: ?>
                                            <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $conversion['status']; ?>">
                                                <?php echo ucfirst($conversion['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo $conversion['subscription_id'] ? htmlspecialchars($conversion['subscription_id']) : '-'; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($conversion['created_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
