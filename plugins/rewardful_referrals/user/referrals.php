<?php
/**
 * Rewardful Referrals - User Referrals Page
 * 
 * Gated page showing referral link and stats for approved referrers
 */

if (!defined('BASEDIR')) {
    define('BASEDIR', dirname(dirname(dirname(dirname(__FILE__)))));
}

// Include ClipBucket core
require_once BASEDIR . '/includes/config.inc.php';

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

// Check if plugin is enabled
if (!$service->isEnabled()) {
    header('Location: /account');
    exit;
}

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: /login?redirect=' . urlencode('/my_account/referrals'));
    exit;
}

$message = '';
$messageType = '';
$canViewReferrals = $auth->canViewReferrals();
$referrer = $auth->getCurrentReferrer();
$affiliate = null;
$referralLink = null;

// Handle application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    if ($auth->validateCSRF() && $auth->canApply()) {
        $applicationMessage = trim($_POST['application_message'] ?? '');
        $result = $service->submitApplication($auth->getCurrentUserId(), $applicationMessage);
        
        if ($result['success']) {
            $message = $result['message'];
            $messageType = 'success';
            // Refresh referrer data
            $referrer = $auth->getCurrentReferrer();
        } else {
            $message = $result['error'];
            $messageType = 'error';
        }
    }
}

// If approved, get affiliate data and link
if ($canViewReferrals && $referrer && $referrer['status'] === 'approved') {
    $affiliate = $db->getAffiliateByReferrerId($referrer['id']);
    $linkResult = $service->getReferralLink($referrer['id']);
    
    if ($linkResult['success']) {
        $referralLink = $linkResult['link'];
    }
}

$page_title = 'My Referrals';
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .page-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 40px 0; 
            margin-bottom: 30px; 
        }
        .page-header h1 { margin: 0; font-size: 32px; }
        .card { border: none; box-shadow: 0 2px 15px rgba(0,0,0,0.08); border-radius: 12px; margin-bottom: 20px; }
        .card-header { 
            background: white; 
            border-bottom: 1px solid #eee; 
            font-weight: 600; 
            padding: 15px 20px; 
            border-radius: 12px 12px 0 0 !important; 
        }
        .btn-primary { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border: none; 
            padding: 12px 25px;
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, #5a6fd6 0%, #6a4190 100%); 
        }
        .referral-link-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 25px;
            color: white;
        }
        .referral-link-input {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 15px;
            border-radius: 8px;
            font-size: 16px;
        }
        .referral-link-input::placeholder { color: rgba(255,255,255,0.6); }
        .copy-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 15px 25px;
            border-radius: 8px;
            font-weight: 600;
        }
        .copy-btn:hover { background: #f0f0f0; color: #667eea; }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #667eea; }
        .stat-card .label { color: #666; font-size: 14px; }
        .status-badge { 
            display: inline-block; 
            padding: 8px 16px; 
            border-radius: 20px; 
            font-size: 14px; 
            font-weight: 600; 
        }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .back-link { color: rgba(255,255,255,0.9); text-decoration: none; }
        .back-link:hover { color: white; }
        .apply-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 12px;
            padding: 30px;
        }
        .sso-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid white;
            padding: 12px 25px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
        }
        .sso-btn:hover { background: white; color: #667eea; }
    </style>
</head>
<body>
    <div class="page-header">
        <div class="container">
            <a href="/account" class="back-link"><i class="fas fa-arrow-left me-2"></i>Back to Account</a>
            <h1 class="mt-3"><i class="fas fa-gift me-2"></i><?php echo $page_title; ?></h1>
            <p class="mb-0 mt-2 opacity-75">Earn rewards by referring new subscribers</p>
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
        
        <?php if ($canViewReferrals && $referrer && $referrer['status'] === 'approved'): ?>
        <!-- Approved Referrer View -->
        
        <!-- Referral Link -->
        <div class="referral-link-box mb-4">
            <h4 class="mb-3"><i class="fas fa-link me-2"></i>Your Referral Link</h4>
            <p class="opacity-75 mb-3">Share this link with your audience. You'll earn a commission for every subscription!</p>
            
            <div class="d-flex gap-3 mb-4">
                <input type="text" id="referral-link" class="form-control referral-link-input" 
                    value="<?php echo htmlspecialchars($referralLink ?? 'Link not available'); ?>" readonly>
                <button type="button" class="copy-btn" onclick="copyLink()">
                    <i class="fas fa-copy me-2"></i>Copy
                </button>
            </div>
            
            <div class="d-flex gap-3 flex-wrap">
                <a href="/plugins/rewardful_referrals/api/referrer_sso.php" class="sso-btn" target="_blank">
                    <i class="fas fa-external-link-alt me-2"></i>Open Rewardful Dashboard
                </a>
                
                <!-- Social Share Buttons -->
                <button type="button" class="sso-btn" onclick="shareTwitter()">
                    <i class="fab fa-twitter me-2"></i>Share on Twitter
                </button>
                <button type="button" class="sso-btn" onclick="shareFacebook()">
                    <i class="fab fa-facebook me-2"></i>Share on Facebook
                </button>
            </div>
        </div>
        
        <!-- Stats -->
        <?php if ($affiliate): ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="number"><?php echo number_format($affiliate['visitors_count'] ?? 0); ?></div>
                    <div class="label">Visitors</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="number"><?php echo number_format($affiliate['leads_count'] ?? 0); ?></div>
                    <div class="label">Leads</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="number"><?php echo number_format($affiliate['conversions_count'] ?? 0); ?></div>
                    <div class="label">Conversions</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- How It Works -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-question-circle me-2"></i>How It Works
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 text-center mb-4 mb-md-0">
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-share-alt fa-2x text-primary"></i>
                        </div>
                        <h5>1. Share Your Link</h5>
                        <p class="text-muted mb-0">Share your unique referral link with friends, followers, and your audience.</p>
                    </div>
                    <div class="col-md-4 text-center mb-4 mb-md-0">
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-user-plus fa-2x text-primary"></i>
                        </div>
                        <h5>2. They Subscribe</h5>
                        <p class="text-muted mb-0">When someone clicks your link and subscribes, we track the referral.</p>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                            <i class="fas fa-dollar-sign fa-2x text-primary"></i>
                        </div>
                        <h5>3. Earn Rewards</h5>
                        <p class="text-muted mb-0">You earn a commission for every successful referral. Payouts handled via Rewardful.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php elseif ($referrer && $referrer['status'] === 'pending'): ?>
        <!-- Pending Application -->
        <div class="card">
            <div class="card-body text-center py-5">
                <span class="status-badge status-pending mb-3">
                    <i class="fas fa-clock me-2"></i>Application Pending
                </span>
                <h3 class="mt-3">Your Application is Under Review</h3>
                <p class="text-muted mb-0">
                    We're reviewing your application to join our referral program. 
                    You'll receive an email once it's been approved.
                </p>
            </div>
        </div>
        
        <?php elseif ($referrer && $referrer['status'] === 'rejected'): ?>
        <!-- Rejected -->
        <div class="card">
            <div class="card-body text-center py-5">
                <span class="status-badge status-rejected mb-3">
                    <i class="fas fa-times-circle me-2"></i>Application Not Approved
                </span>
                <h3 class="mt-3">Your Application Was Not Approved</h3>
                <p class="text-muted mb-4">
                    Unfortunately, your application to join our referral program was not approved at this time.
                </p>
                
                <?php if ($auth->canApply()): ?>
                <p class="mb-0">
                    <a href="#apply-form" class="btn btn-primary">
                        <i class="fas fa-redo me-2"></i>Submit New Application
                    </a>
                </p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Not a Referrer -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <?php if ($auth->canApply()): ?>
                <!-- Application Form -->
                <div class="apply-card mb-4">
                    <h3 class="mb-3"><i class="fas fa-rocket me-2"></i>Join Our Referral Program</h3>
                    <p class="opacity-90 mb-4">
                        Become a referrer and earn rewards for every new subscriber you bring to our platform!
                    </p>
                    
                    <form method="post" action="" id="apply-form">
                        <?php echo $auth->csrfField(); ?>
                        
                        <div class="mb-3">
                            <label for="application_message" class="form-label">Why do you want to be a referrer? (Optional)</label>
                            <textarea class="form-control" id="application_message" name="application_message" rows="3" 
                                placeholder="Tell us about yourself and how you plan to promote our platform..."></textarea>
                        </div>
                        
                        <button type="submit" name="apply" value="1" class="btn btn-light btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <!-- Not Eligible -->
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                        <h3>Referral Program</h3>
                        <p class="text-muted mb-0">
                            Our referral program is currently invite-only. 
                            If you're interested in becoming a referrer, please contact us.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Benefits -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-star me-2"></i>Referrer Benefits
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Earn Commissions</strong> - Get rewarded for every successful referral
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Real-Time Tracking</strong> - Monitor your referrals and earnings
                            </li>
                            <li class="mb-3">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Easy Payouts</strong> - Get paid quickly and reliably
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                <strong>Dedicated Dashboard</strong> - Access your own Rewardful dashboard
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyLink() {
            var input = document.getElementById('referral-link');
            if (input) {
                input.select();
                input.setSelectionRange(0, 99999);
                document.execCommand('copy');
                
                // Show feedback
                var btn = input.nextElementSibling;
                var originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-2"></i>Copied!';
                btn.style.background = '#10b981';
                btn.style.color = 'white';
                
                setTimeout(function() {
                    btn.innerHTML = originalHtml;
                    btn.style.background = 'white';
                    btn.style.color = '#667eea';
                }, 2000);
            }
        }
        
        function shareTwitter() {
            var link = document.getElementById('referral-link').value;
            var text = encodeURIComponent('Check out this amazing streaming platform! ');
            window.open('https://twitter.com/intent/tweet?text=' + text + '&url=' + encodeURIComponent(link), '_blank');
        }
        
        function shareFacebook() {
            var link = document.getElementById('referral-link').value;
            window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(link), '_blank');
        }
    </script>
</body>
</html>
