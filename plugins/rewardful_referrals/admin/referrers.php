<?php
/**
 * Rewardful Referrals - Admin Referrers Management Page
 * 
 * Manage referrer approvals and status
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
$adminUserId = userid();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $auth->validateCSRF()) {
    $action = $_POST['action'] ?? '';
    $referrerId = (int)($_POST['referrer_id'] ?? 0);
    
    switch ($action) {
        case 'approve':
            $result = $service->approveReferrer($referrerId, $adminUserId);
            if ($result['success']) {
                $message = 'Referrer approved successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to approve referrer: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'error';
            }
            break;
            
        case 'reject':
            $result = $service->rejectReferrer($referrerId, $adminUserId);
            if ($result['success']) {
                $message = 'Referrer rejected.';
                $messageType = 'success';
            } else {
                $message = 'Failed to reject referrer: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'error';
            }
            break;
            
        case 'disable':
            $result = $service->disableReferrer($referrerId, $adminUserId);
            if ($result['success']) {
                $message = 'Referrer disabled.';
                $messageType = 'success';
            } else {
                $message = 'Failed to disable referrer: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'error';
            }
            break;
            
        case 'sync':
            $result = $service->syncAffiliateWithRewardful($referrerId);
            if ($result['success']) {
                $message = 'Referrer synced with Rewardful successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to sync: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'error';
            }
            break;
            
        case 'create':
            $email = trim($_POST['email'] ?? '');
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $autoApprove = isset($_POST['auto_approve']);
            
            $result = $service->createReferrer(array(
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'notes' => $notes,
                'status' => $autoApprove ? 'approved' : 'pending'
            ), $adminUserId);
            
            if ($result['success']) {
                if ($autoApprove) {
                    $service->syncAffiliateWithRewardful($result['referrer_id']);
                }
                $message = 'Referrer created successfully!';
                $messageType = 'success';
            } else {
                $message = 'Failed to create referrer: ' . ($result['error'] ?? 'Unknown error');
                $messageType = 'error';
            }
            break;
    }
}

// Filter
$statusFilter = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get referrers
if ($statusFilter === 'all') {
    $referrers = $db->getAllReferrers($perPage, $offset);
    $totalCount = $db->countReferrers();
} else {
    $referrers = $db->getReferrersByStatus($statusFilter, $perPage, $offset);
    $totalCount = $db->countReferrers($statusFilter);
}

// Get stats
$stats = $service->getStats();

$page_title = 'Manage Referrers';
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
        .btn-primary:hover { background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%); }
        .nav-pills .nav-link { color: #666; border-radius: 8px; }
        .nav-pills .nav-link.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: uppercase; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-disabled { background: #e5e7eb; color: #374151; }
        .stat-card { text-align: center; padding: 20px; border-radius: 10px; background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card .number { font-size: 32px; font-weight: bold; }
        .stat-card.approved .number { color: #10b981; }
        .stat-card.pending .number { color: #f59e0b; }
        .stat-card.rejected .number { color: #ef4444; }
        .stat-card.disabled .number { color: #6b7280; }
        .back-link { color: rgba(255,255,255,0.9); text-decoration: none; }
        .back-link:hover { color: white; }
        .table th { font-weight: 600; color: #374151; }
        .action-btn { padding: 5px 10px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <div class="container">
            <a href="/admin_area" class="back-link"><i class="fas fa-arrow-left me-2"></i>Back to Admin</a>
            <h1 class="mt-3"><i class="fas fa-users me-2"></i><?php echo $page_title; ?></h1>
            <p class="mb-0 mt-2 opacity-75">Approve and manage referrers for your affiliate program</p>
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
                <a class="nav-link active" href="referrers.php"><i class="fas fa-users me-2"></i>Referrers</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php"><i class="fas fa-list-alt me-2"></i>Logs</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="health.php"><i class="fas fa-heartbeat me-2"></i>Health</a>
            </li>
        </ul>
        
        <!-- Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card approved">
                    <div class="number"><?php echo $stats['referrers']['approved']; ?></div>
                    <div class="label text-muted">Approved</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card pending">
                    <div class="number"><?php echo $stats['referrers']['pending']; ?></div>
                    <div class="label text-muted">Pending</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card rejected">
                    <div class="number"><?php echo $stats['referrers']['rejected']; ?></div>
                    <div class="label text-muted">Rejected</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card disabled">
                    <div class="number"><?php echo $stats['referrers']['disabled']; ?></div>
                    <div class="label text-muted">Disabled</div>
                </div>
            </div>
        </div>
        
        <!-- Create Referrer -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-user-plus me-2"></i>Create New Referrer
            </div>
            <div class="card-body">
                <form method="post" action="" class="row g-3">
                    <?php echo $auth->csrfField(); ?>
                    <input type="hidden" name="action" value="create">
                    
                    <div class="col-md-4">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required 
                            placeholder="referrer@example.com">
                    </div>
                    <div class="col-md-2">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name">
                    </div>
                    <div class="col-md-2">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name">
                    </div>
                    <div class="col-md-3">
                        <label for="notes" class="form-label">Notes</label>
                        <input type="text" class="form-control" id="notes" name="notes" 
                            placeholder="Internal notes">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="auto_approve" name="auto_approve" checked>
                            <label class="form-check-label" for="auto_approve">
                                Auto-approve and sync with Rewardful
                            </label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Filter -->
        <div class="mb-3">
            <div class="btn-group" role="group">
                <a href="?status=all" class="btn btn-<?php echo $statusFilter === 'all' ? 'primary' : 'outline-secondary'; ?>">
                    All (<?php echo $stats['referrers']['total']; ?>)
                </a>
                <a href="?status=approved" class="btn btn-<?php echo $statusFilter === 'approved' ? 'primary' : 'outline-secondary'; ?>">
                    Approved (<?php echo $stats['referrers']['approved']; ?>)
                </a>
                <a href="?status=pending" class="btn btn-<?php echo $statusFilter === 'pending' ? 'primary' : 'outline-secondary'; ?>">
                    Pending (<?php echo $stats['referrers']['pending']; ?>)
                </a>
                <a href="?status=rejected" class="btn btn-<?php echo $statusFilter === 'rejected' ? 'primary' : 'outline-secondary'; ?>">
                    Rejected (<?php echo $stats['referrers']['rejected']; ?>)
                </a>
                <a href="?status=disabled" class="btn btn-<?php echo $statusFilter === 'disabled' ? 'primary' : 'outline-secondary'; ?>">
                    Disabled (<?php echo $stats['referrers']['disabled']; ?>)
                </a>
            </div>
        </div>
        
        <!-- Referrers Table -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Email</th>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Referral Token</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($referrers)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <i class="fas fa-users fa-2x mb-2 d-block"></i>
                                    No referrers found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($referrers as $referrer): 
                                $affiliate = $db->getAffiliateByReferrerId($referrer['id']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($referrer['email']); ?></strong>
                                    <?php if ($referrer['userid']): ?>
                                    <br><small class="text-muted">User ID: <?php echo $referrer['userid']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $name = trim(($referrer['first_name'] ?? '') . ' ' . ($referrer['last_name'] ?? ''));
                                    echo $name ? htmlspecialchars($name) : '<span class="text-muted">-</span>';
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $referrer['status']; ?>">
                                        <?php echo ucfirst($referrer['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($affiliate && $affiliate['token']): ?>
                                    <code><?php echo htmlspecialchars($affiliate['token']); ?></code>
                                    <?php else: ?>
                                    <span class="text-muted">Not synced</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?php echo date('M j, Y', strtotime($referrer['created_at'])); ?></small>
                                </td>
                                <td class="text-end">
                                    <form method="post" action="" class="d-inline">
                                        <?php echo $auth->csrfField(); ?>
                                        <input type="hidden" name="referrer_id" value="<?php echo $referrer['id']; ?>">
                                        
                                        <?php if ($referrer['status'] === 'pending'): ?>
                                        <button type="submit" name="action" value="approve" class="btn btn-success action-btn">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="btn btn-danger action-btn">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                        <?php elseif ($referrer['status'] === 'approved'): ?>
                                        <button type="submit" name="action" value="sync" class="btn btn-info action-btn">
                                            <i class="fas fa-sync"></i> Sync
                                        </button>
                                        <button type="submit" name="action" value="disable" class="btn btn-secondary action-btn">
                                            <i class="fas fa-ban"></i> Disable
                                        </button>
                                        <?php elseif ($referrer['status'] === 'rejected' || $referrer['status'] === 'disabled'): ?>
                                        <button type="submit" name="action" value="approve" class="btn btn-success action-btn">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalCount > $perPage): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php 
                $totalPages = ceil($totalCount / $perPage);
                for ($i = 1; $i <= $totalPages; $i++): 
                ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link" href="?status=<?php echo $statusFilter; ?>&page=<?php echo $i; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
