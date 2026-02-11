<?php
/**
 * Pause Ads Plugin - Admin: Create/Edit Ad
 *
 * Full ad editor with image upload, targeting rules,
 * delivery caps, and scheduling.
 *
 * @package PauseAds
 */

if (!defined('STARTER')) {
    $cb_root = realpath(dirname(__FILE__) . '/../../../../');
    $bootstrap_files = [
        $cb_root . '/includes/config.inc.php',
        $cb_root . '/include/config.inc.php',
        $cb_root . '/cb_config.php',
    ];
    foreach ($bootstrap_files as $bf) {
        if (file_exists($bf)) {
            define('STARTER', true);
            require_once $bf;
            break;
        }
    }
    if (!defined('STARTER')) {
        define('STARTER', true);
    }
}

require_once dirname(__FILE__) . '/../main.php';
pause_ads_require_admin();

$ad_id = isset($_GET['id']) ? pause_ads_validate_int($_GET['id']) : 0;
$is_edit = $ad_id > 0;
$ad = null;
$success_msg = '';
$error_msg = '';

if ($is_edit) {
    $ad = pause_ads_get_ad($ad_id);
    if (!$ad) {
        $error_msg = 'Ad not found.';
        $is_edit = false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ad'])) {
    // Verify CSRF
    if (!pause_ads_check_nonce('ad_edit')) {
        $error_msg = 'Invalid security token. Please try again.';
    } else {
        // Collect form data
        $data = [
            'name'       => trim($_POST['name'] ?? ''),
            'status'     => in_array($_POST['status'] ?? '', ['active', 'paused', 'archived']) ? $_POST['status'] : 'paused',
            'click_url'  => trim($_POST['click_url'] ?? ''),
            'alt_text'   => trim($_POST['alt_text'] ?? ''),
            'advertiser' => trim($_POST['advertiser'] ?? ''),
            'priority'   => max(0, (int) ($_POST['priority'] ?? 0)),
            'start_at'   => !empty($_POST['start_at']) ? $_POST['start_at'] : null,
            'end_at'     => !empty($_POST['end_at']) ? $_POST['end_at'] : null,
            // Delivery
            'cap_total_impressions'        => !empty($_POST['cap_total_impressions']) ? (int) $_POST['cap_total_impressions'] : null,
            'cap_daily_impressions'        => !empty($_POST['cap_daily_impressions']) ? (int) $_POST['cap_daily_impressions'] : null,
            'cap_hourly_impressions'       => !empty($_POST['cap_hourly_impressions']) ? (int) $_POST['cap_hourly_impressions'] : null,
            'weight'                       => max(1, (int) ($_POST['weight'] ?? 1)),
            'frequency_cap_per_user_per_day' => !empty($_POST['frequency_cap_per_user_per_day']) ? (int) $_POST['frequency_cap_per_user_per_day'] : null,
        ];

        // Validate
        if (empty($data['name'])) {
            $error_msg = 'Ad name is required.';
        } elseif (!empty($data['click_url']) && pause_ads_validate_url($data['click_url']) === false) {
            $error_msg = 'Invalid click URL format.';
        } else {
            // Handle image upload
            if (!empty($_FILES['image']['tmp_name']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_result = pause_ads_handle_upload($_FILES['image']);
                if ($upload_result['success']) {
                    $data['image_path'] = $upload_result['path'];
                    $data['image_width'] = $upload_result['width'];
                    $data['image_height'] = $upload_result['height'];

                    // Delete old image if editing
                    if ($is_edit && !empty($ad['image_path'])) {
                        $old_file = PAUSE_ADS_UPLOADS_DIR . '/' . $ad['image_path'];
                        if (file_exists($old_file)) {
                            unlink($old_file);
                        }
                    }
                } else {
                    $error_msg = 'Image upload failed: ' . $upload_result['error'];
                }
            }

            if (empty($error_msg)) {
                if ($is_edit) {
                    // Update existing ad
                    if (pause_ads_update_ad($ad_id, $data)) {
                        $success_msg = 'Ad updated successfully.';
                        $ad = pause_ads_get_ad($ad_id); // Refresh
                    } else {
                        $error_msg = 'Failed to update ad.';
                    }
                } else {
                    // Create new ad
                    if (!isset($data['image_path']) || empty($data['image_path'])) {
                        $error_msg = 'Please upload an image for the ad.';
                    } else {
                        $new_id = pause_ads_create_ad($data);
                        if ($new_id) {
                            $ad_id = $new_id;
                            $is_edit = true;
                            $success_msg = 'Ad created successfully.';
                            $ad = pause_ads_get_ad($ad_id);
                        } else {
                            $error_msg = 'Failed to create ad.';
                        }
                    }
                }

                // Save targeting rules
                if (empty($error_msg) && $ad_id > 0 && isset($_POST['targeting'])) {
                    $rules = [];
                    if (is_array($_POST['targeting'])) {
                        foreach ($_POST['targeting'] as $rule) {
                            if (!empty($rule['rule_type']) && isset($rule['rule_value']) && $rule['rule_value'] !== '') {
                                $rules[] = [
                                    'rule_type'  => $rule['rule_type'],
                                    'rule_value' => $rule['rule_value'],
                                ];
                            }
                        }
                    }
                    pause_ads_save_targeting($ad_id, $rules);
                    $ad = pause_ads_get_ad($ad_id); // Refresh with targeting
                }
            }
        }
    }
}

// Defaults for new ad
if (!$ad) {
    $ad = [
        'id' => 0, 'name' => '', 'status' => 'paused', 'image_path' => '',
        'image_width' => 0, 'image_height' => 0, 'click_url' => '',
        'alt_text' => '', 'advertiser' => '', 'priority' => 0,
        'start_at' => '', 'end_at' => '',
        'cap_total_impressions' => '', 'cap_daily_impressions' => '',
        'cap_hourly_impressions' => '', 'weight' => 1,
        'frequency_cap_per_user_per_day' => '', 'targeting' => [],
    ];
}

$page_title = $is_edit ? 'Edit Ad: ' . pause_ads_esc($ad['name']) : 'Create New Ad';

// Available rule types for targeting
$rule_types = [
    'genre'              => 'Genre',
    'category'           => 'Category',
    'rating'             => 'Content Rating',
    'age_rating'         => 'Age Rating',
    'country'            => 'Country',
    'language'           => 'Language',
    'device'             => 'Device Type',
    'requires_age_known' => 'Require Known Age',
];

pause_ads_admin_header($page_title, 'ads');
?>

<?php if (!empty($success_msg)): ?>
    <div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($success_msg); ?></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($error_msg); ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" action="">
    <?php echo pause_ads_nonce_field('ad_edit'); ?>

    <div class="pa-row">
        <!-- Left column: Basic Info -->
        <div class="pa-col" style="flex: 2;">
            <div class="pa-card">
                <h3>Ad Details</h3>

                <div class="pa-form-group">
                    <label for="name">Ad Name *</label>
                    <input type="text" id="name" name="name" class="pa-input"
                           value="<?php echo pause_ads_esc($ad['name']); ?>" required
                           placeholder="e.g., Summer Sale Banner">
                </div>

                <div class="pa-form-group">
                    <label for="advertiser">Advertiser</label>
                    <input type="text" id="advertiser" name="advertiser" class="pa-input"
                           value="<?php echo pause_ads_esc($ad['advertiser']); ?>"
                           placeholder="e.g., Acme Corp">
                </div>

                <div class="pa-form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" class="pa-input">
                        <option value="paused" <?php echo $ad['status'] === 'paused' ? 'selected' : ''; ?>>Paused</option>
                        <option value="active" <?php echo $ad['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="archived" <?php echo $ad['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>

                <div class="pa-form-group">
                    <label for="image">Ad Image *</label>
                    <?php if (!empty($ad['image_path'])): ?>
                        <div style="margin-bottom: 10px;">
                            <img src="<?php echo pause_ads_get_base_url() . '/' . PAUSE_ADS_UPLOADS_URL . '/' . pause_ads_esc($ad['image_path']); ?>"
                                 alt="Current ad image"
                                 style="max-width: 300px; max-height: 200px; border-radius: 6px; border: 1px solid #dee2e6;">
                            <br><small class="hint">Current image: <?php echo pause_ads_esc($ad['image_path']); ?>
                            <?php if ($ad['image_width'] && $ad['image_height']): ?>
                                (<?php echo (int)$ad['image_width']; ?>x<?php echo (int)$ad['image_height']; ?>px)
                            <?php endif; ?>
                            </small>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="image" name="image" class="pa-input" accept="image/*">
                    <div class="hint">Allowed: JPG, PNG, GIF, WebP, SVG. Max 5MB. <?php echo $is_edit ? 'Leave empty to keep current image.' : ''; ?></div>
                </div>

                <div class="pa-form-group">
                    <label for="click_url">Click URL</label>
                    <input type="url" id="click_url" name="click_url" class="pa-input"
                           value="<?php echo pause_ads_esc($ad['click_url']); ?>"
                           placeholder="https://example.com/landing-page">
                    <div class="hint">URL to open when the ad is clicked. Leave empty for non-clickable ads.</div>
                </div>

                <div class="pa-form-group">
                    <label for="alt_text">Alt Text</label>
                    <input type="text" id="alt_text" name="alt_text" class="pa-input"
                           value="<?php echo pause_ads_esc($ad['alt_text']); ?>"
                           placeholder="Descriptive text for accessibility">
                </div>
            </div>

            <!-- Targeting Rules -->
            <div class="pa-card">
                <h3>Targeting Rules</h3>
                <p style="font-size:13px;color:#6c757d;margin-top:-10px;">
                    Rules of the same type use OR logic (any match). Different types use AND logic (all must match).<br>
                    Leave empty to target all videos.
                </p>

                <div id="targeting-rules">
                    <?php
                    $targeting = isset($ad['targeting']) ? $ad['targeting'] : [];
                    if (empty($targeting)) {
                        $targeting = [['rule_type' => '', 'rule_value' => '']];
                    }
                    foreach ($targeting as $idx => $rule):
                    ?>
                        <div class="targeting-rule-row" style="display:flex;gap:10px;margin-bottom:8px;align-items:center;">
                            <select name="targeting[<?php echo $idx; ?>][rule_type]" class="pa-input" style="flex:1;">
                                <option value="">-- Select Type --</option>
                                <?php foreach ($rule_types as $rkey => $rlabel): ?>
                                    <option value="<?php echo pause_ads_esc($rkey); ?>"
                                        <?php echo (isset($rule['rule_type']) && $rule['rule_type'] === $rkey) ? 'selected' : ''; ?>>
                                        <?php echo pause_ads_esc($rlabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="text" name="targeting[<?php echo $idx; ?>][rule_value]" class="pa-input" style="flex:2;"
                                   value="<?php echo pause_ads_esc($rule['rule_value'] ?? ''); ?>"
                                   placeholder="Value (e.g., comedy, PG-13, US)">
                            <button type="button" class="pa-btn pa-btn-sm pa-btn-danger remove-rule-btn"
                                    onclick="this.closest('.targeting-rule-row').remove();" title="Remove rule">&times;</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="pa-btn pa-btn-sm" style="background:#e9ecef;color:#495057;margin-top:5px;"
                        onclick="addTargetingRule()">+ Add Rule</button>
            </div>
        </div>

        <!-- Right column: Schedule, Delivery, Priority -->
        <div class="pa-col" style="flex: 1;">
            <div class="pa-card">
                <h3>Schedule</h3>

                <div class="pa-form-group">
                    <label for="start_at">Start Date</label>
                    <input type="datetime-local" id="start_at" name="start_at" class="pa-input"
                           value="<?php echo !empty($ad['start_at']) ? date('Y-m-d\TH:i', strtotime($ad['start_at'])) : ''; ?>">
                    <div class="hint">Leave empty for immediate start.</div>
                </div>

                <div class="pa-form-group">
                    <label for="end_at">End Date</label>
                    <input type="datetime-local" id="end_at" name="end_at" class="pa-input"
                           value="<?php echo !empty($ad['end_at']) ? date('Y-m-d\TH:i', strtotime($ad['end_at'])) : ''; ?>">
                    <div class="hint">Leave empty for no end date.</div>
                </div>
            </div>

            <div class="pa-card">
                <h3>Priority &amp; Weight</h3>

                <div class="pa-form-group">
                    <label for="priority">Priority</label>
                    <input type="number" id="priority" name="priority" class="pa-input" min="0" max="100"
                           value="<?php echo (int) $ad['priority']; ?>">
                    <div class="hint">Higher = served first. Ads with same priority use weighted random.</div>
                </div>

                <div class="pa-form-group">
                    <label for="weight">Weight</label>
                    <input type="number" id="weight" name="weight" class="pa-input" min="1" max="1000"
                           value="<?php echo max(1, (int) ($ad['weight'] ?? 1)); ?>">
                    <div class="hint">Higher weight = more likely to be selected among same-priority ads.</div>
                </div>
            </div>

            <div class="pa-card">
                <h3>Delivery Caps</h3>

                <div class="pa-form-group">
                    <label for="cap_total_impressions">Total Impression Cap</label>
                    <input type="number" id="cap_total_impressions" name="cap_total_impressions" class="pa-input" min="0"
                           value="<?php echo pause_ads_esc($ad['cap_total_impressions'] ?? ''); ?>"
                           placeholder="Unlimited">
                    <div class="hint">Maximum total impressions. Leave empty for unlimited.</div>
                </div>

                <div class="pa-form-group">
                    <label for="cap_daily_impressions">Daily Impression Cap</label>
                    <input type="number" id="cap_daily_impressions" name="cap_daily_impressions" class="pa-input" min="0"
                           value="<?php echo pause_ads_esc($ad['cap_daily_impressions'] ?? ''); ?>"
                           placeholder="Unlimited">
                </div>

                <div class="pa-form-group">
                    <label for="cap_hourly_impressions">Hourly Impression Cap</label>
                    <input type="number" id="cap_hourly_impressions" name="cap_hourly_impressions" class="pa-input" min="0"
                           value="<?php echo pause_ads_esc($ad['cap_hourly_impressions'] ?? ''); ?>"
                           placeholder="Unlimited">
                </div>

                <div class="pa-form-group">
                    <label for="frequency_cap_per_user_per_day">User Frequency Cap (per day)</label>
                    <input type="number" id="frequency_cap_per_user_per_day" name="frequency_cap_per_user_per_day"
                           class="pa-input" min="0"
                           value="<?php echo pause_ads_esc($ad['frequency_cap_per_user_per_day'] ?? ''); ?>"
                           placeholder="Unlimited">
                    <div class="hint">Max impressions per user/session per day.</div>
                </div>
            </div>

            <div style="text-align: right;">
                <a href="<?php echo pause_ads_admin_url('ads.php'); ?>" class="pa-btn" style="background:#e9ecef;color:#495057;">Cancel</a>
                <button type="submit" name="save_ad" class="pa-btn pa-btn-primary">
                    <?php echo $is_edit ? 'Update Ad' : 'Create Ad'; ?>
                </button>
            </div>
        </div>
    </div>
</form>

<script>
var ruleIndex = <?php echo count($targeting); ?>;

function addTargetingRule() {
    var container = document.getElementById('targeting-rules');
    var ruleTypes = <?php echo json_encode($rule_types); ?>;

    var row = document.createElement('div');
    row.className = 'targeting-rule-row';
    row.style.cssText = 'display:flex;gap:10px;margin-bottom:8px;align-items:center;';

    var select = '<select name="targeting[' + ruleIndex + '][rule_type]" class="pa-input" style="flex:1;">';
    select += '<option value="">-- Select Type --</option>';
    for (var key in ruleTypes) {
        select += '<option value="' + key + '">' + ruleTypes[key] + '</option>';
    }
    select += '</select>';

    row.innerHTML = select +
        '<input type="text" name="targeting[' + ruleIndex + '][rule_value]" class="pa-input" style="flex:2;" placeholder="Value (e.g., comedy, PG-13, US)">' +
        '<button type="button" class="pa-btn pa-btn-sm pa-btn-danger remove-rule-btn" onclick="this.closest(\'.targeting-rule-row\').remove();" title="Remove rule">&times;</button>';

    container.appendChild(row);
    ruleIndex++;
}
</script>

<?php pause_ads_admin_footer(); ?>
