<?php
/**
 * Pause Ads Plugin - Admin: Settings
 *
 * Configure plugin-wide settings including minimum pause duration,
 * overlay style, enable/disable, and other options.
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

$success_msg = '';
$error_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (!pause_ads_check_nonce('pause_ads_settings')) {
        $error_msg = 'Invalid security token.';
    } else {
        $settings = [
            'enabled'                       => isset($_POST['enabled']) ? '1' : '0',
            'min_pause_ms'                  => max(100, min(10000, (int) ($_POST['min_pause_ms'] ?? 1000))),
            'overlay_position'              => in_array($_POST['overlay_position'] ?? '', ['center', 'bottom-right', 'bottom-left', 'top-right', 'top-left', 'bottom-center']) ? $_POST['overlay_position'] : 'center',
            'overlay_style'                 => in_array($_POST['overlay_style'] ?? '', ['semi-transparent', 'solid', 'blur', 'none']) ? $_POST['overlay_style'] : 'semi-transparent',
            'fallback_behavior'             => in_array($_POST['fallback_behavior'] ?? '', ['none', 'placeholder']) ? $_POST['fallback_behavior'] : 'none',
            'max_ads_per_session_per_minute' => max(1, min(60, (int) ($_POST['max_ads_per_session_per_minute'] ?? 5))),
            'drop_tables_on_uninstall'      => isset($_POST['drop_tables_on_uninstall']) ? '1' : '0',
        ];

        foreach ($settings as $key => $value) {
            pause_ads_set_setting($key, (string) $value);
        }

        $success_msg = 'Settings saved successfully.';
    }
}

// Load current settings
$settings = [
    'enabled'                       => pause_ads_get_setting('enabled', '1'),
    'min_pause_ms'                  => pause_ads_get_setting('min_pause_ms', '1000'),
    'overlay_position'              => pause_ads_get_setting('overlay_position', 'center'),
    'overlay_style'                 => pause_ads_get_setting('overlay_style', 'semi-transparent'),
    'fallback_behavior'             => pause_ads_get_setting('fallback_behavior', 'none'),
    'max_ads_per_session_per_minute' => pause_ads_get_setting('max_ads_per_session_per_minute', '5'),
    'drop_tables_on_uninstall'      => pause_ads_get_setting('drop_tables_on_uninstall', '0'),
];

pause_ads_admin_header('Settings', 'settings');
?>

<?php if (!empty($success_msg)): ?>
    <div class="pa-alert pa-alert-success"><?php echo pause_ads_esc($success_msg); ?></div>
<?php endif; ?>
<?php if (!empty($error_msg)): ?>
    <div class="pa-alert pa-alert-error"><?php echo pause_ads_esc($error_msg); ?></div>
<?php endif; ?>

<form method="post" action="">
    <?php echo pause_ads_nonce_field('pause_ads_settings'); ?>

    <div class="pa-row">
        <div class="pa-col" style="flex: 1;">
            <!-- General Settings -->
            <div class="pa-card">
                <h3>General</h3>

                <div class="pa-form-group">
                    <label>
                        <input type="checkbox" name="enabled" value="1"
                               <?php echo $settings['enabled'] === '1' ? 'checked' : ''; ?>>
                        &nbsp;Enable Pause Ads Plugin
                    </label>
                    <div class="hint">When disabled, no pause ads will be served on any video.</div>
                </div>

                <div class="pa-form-group">
                    <label for="min_pause_ms">Minimum Pause Duration (ms)</label>
                    <input type="number" id="min_pause_ms" name="min_pause_ms" class="pa-input"
                           value="<?php echo (int) $settings['min_pause_ms']; ?>"
                           min="100" max="10000" step="100">
                    <div class="hint">
                        The ad overlay will appear immediately on pause, but an impression is only counted
                        after the viewer has been paused for at least this duration. Default: 1000ms (1 second).
                    </div>
                </div>

                <div class="pa-form-group">
                    <label for="max_ads_per_session_per_minute">Max Ad Requests per Session per Minute</label>
                    <input type="number" id="max_ads_per_session_per_minute" name="max_ads_per_session_per_minute"
                           class="pa-input"
                           value="<?php echo (int) $settings['max_ads_per_session_per_minute']; ?>"
                           min="1" max="60">
                    <div class="hint">Rate limiting to prevent abuse. Default: 5.</div>
                </div>
            </div>

            <!-- Overlay Settings -->
            <div class="pa-card">
                <h3>Ad Overlay Appearance</h3>

                <div class="pa-form-group">
                    <label for="overlay_position">Overlay Position</label>
                    <select id="overlay_position" name="overlay_position" class="pa-input">
                        <option value="center" <?php echo $settings['overlay_position'] === 'center' ? 'selected' : ''; ?>>Center (full overlay)</option>
                        <option value="bottom-center" <?php echo $settings['overlay_position'] === 'bottom-center' ? 'selected' : ''; ?>>Bottom Center</option>
                        <option value="bottom-right" <?php echo $settings['overlay_position'] === 'bottom-right' ? 'selected' : ''; ?>>Bottom Right</option>
                        <option value="bottom-left" <?php echo $settings['overlay_position'] === 'bottom-left' ? 'selected' : ''; ?>>Bottom Left</option>
                        <option value="top-right" <?php echo $settings['overlay_position'] === 'top-right' ? 'selected' : ''; ?>>Top Right</option>
                        <option value="top-left" <?php echo $settings['overlay_position'] === 'top-left' ? 'selected' : ''; ?>>Top Left</option>
                    </select>
                    <div class="hint">Where the ad image is positioned over the paused video.</div>
                </div>

                <div class="pa-form-group">
                    <label for="overlay_style">Overlay Background Style</label>
                    <select id="overlay_style" name="overlay_style" class="pa-input">
                        <option value="semi-transparent" <?php echo $settings['overlay_style'] === 'semi-transparent' ? 'selected' : ''; ?>>Semi-transparent dark overlay</option>
                        <option value="blur" <?php echo $settings['overlay_style'] === 'blur' ? 'selected' : ''; ?>>Blur background</option>
                        <option value="solid" <?php echo $settings['overlay_style'] === 'solid' ? 'selected' : ''; ?>>Solid dark background</option>
                        <option value="none" <?php echo $settings['overlay_style'] === 'none' ? 'selected' : ''; ?>>No background (image only)</option>
                    </select>
                    <div class="hint">The background effect behind the ad image when the player is paused.</div>
                </div>

                <div class="pa-form-group">
                    <label for="fallback_behavior">Fallback (No Eligible Ad)</label>
                    <select id="fallback_behavior" name="fallback_behavior" class="pa-input">
                        <option value="none" <?php echo $settings['fallback_behavior'] === 'none' ? 'selected' : ''; ?>>Show nothing</option>
                        <option value="placeholder" <?php echo $settings['fallback_behavior'] === 'placeholder' ? 'selected' : ''; ?>>Show placeholder</option>
                    </select>
                    <div class="hint">What to display when no eligible ad is found for a pause event.</div>
                </div>
            </div>
        </div>

        <div class="pa-col" style="flex: 1;">
            <!-- Advanced Settings -->
            <div class="pa-card">
                <h3>Advanced</h3>

                <div class="pa-form-group">
                    <label>
                        <input type="checkbox" name="drop_tables_on_uninstall" value="1"
                               <?php echo $settings['drop_tables_on_uninstall'] === '1' ? 'checked' : ''; ?>>
                        &nbsp;Drop database tables on uninstall
                    </label>
                    <div class="hint" style="color: #dc3545;">
                        <strong>Warning:</strong> If checked, uninstalling the plugin will permanently delete
                        all ads, impressions, clicks, and settings data. This cannot be undone.
                    </div>
                </div>
            </div>

            <!-- Plugin Info -->
            <div class="pa-card">
                <h3>Plugin Information</h3>
                <table style="width:100%;font-size:14px;">
                    <tr>
                        <td style="padding:5px 0;font-weight:600;width:40%;">Version</td>
                        <td style="padding:5px 0;"><?php echo PAUSE_ADS_VERSION; ?></td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;font-weight:600;">Plugin Directory</td>
                        <td style="padding:5px 0;"><code style="font-size:12px;"><?php echo pause_ads_esc(PAUSE_ADS_DIR); ?></code></td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;font-weight:600;">Uploads Directory</td>
                        <td style="padding:5px 0;">
                            <code style="font-size:12px;"><?php echo pause_ads_esc(PAUSE_ADS_UPLOADS_DIR); ?></code>
                            <?php if (is_writable(PAUSE_ADS_UPLOADS_DIR)): ?>
                                <span style="color: #28a745;">&#10003; Writable</span>
                            <?php else: ?>
                                <span style="color: #dc3545;">&#10007; Not writable</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:5px 0;font-weight:600;">DB Table Prefix</td>
                        <td style="padding:5px 0;"><code style="font-size:12px;"><?php echo pause_ads_esc(pause_ads_tbl_prefix()); ?></code></td>
                    </tr>
                </table>
            </div>

            <div style="text-align: right; margin-top: 15px;">
                <button type="submit" name="save_settings" class="pa-btn pa-btn-primary">
                    Save Settings
                </button>
            </div>
        </div>
    </div>
</form>

<?php pause_ads_admin_footer(); ?>
