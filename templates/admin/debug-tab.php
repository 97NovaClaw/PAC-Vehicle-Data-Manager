<?php
/**
 * Debug Tab Template
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$debug_options = get_option(PAC_VDM_DEBUG_OPTION, [
    'enable_php_logging' => false,
    'enable_js_console' => false,
    'enable_admin_notices' => false,
]);

$log_file = PAC_VDM_PLUGIN_DIR . 'debug.txt';
$log_exists = file_exists($log_file);
$log_size = $log_exists ? pac_vdm_get_log_size() : '0 bytes';
?>

<div class="tab-header">
    <div class="description">
        <p><?php _e('Enable debug logging to track data sync operations, field locking, and troubleshoot issues.', 'pac-vehicle-data-manager'); ?></p>
    </div>
</div>

<h3><?php _e('Debug Settings', 'pac-vehicle-data-manager'); ?></h3>

<table class="form-table">
    <tbody>
        <tr>
            <th scope="row">
                <label for="enable-php-logging"><?php _e('PHP Logging', 'pac-vehicle-data-manager'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" id="enable-php-logging" name="enable_php_logging" 
                           <?php checked($debug_options['enable_php_logging'] ?? false); ?>>
                    <?php _e('Enable PHP debug logging to file', 'pac-vehicle-data-manager'); ?>
                </label>
                <p class="description"><?php _e('Logs all data sync operations to debug.txt file.', 'pac-vehicle-data-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="enable-js-console"><?php _e('JavaScript Console', 'pac-vehicle-data-manager'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" id="enable-js-console" name="enable_js_console"
                           <?php checked($debug_options['enable_js_console'] ?? false); ?>>
                    <?php _e('Enable JavaScript console logging', 'pac-vehicle-data-manager'); ?>
                </label>
                <p class="description"><?php _e('Outputs field locker activity to browser console.', 'pac-vehicle-data-manager'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">
                <label for="enable-admin-notices"><?php _e('Admin Notices', 'pac-vehicle-data-manager'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" id="enable-admin-notices" name="enable_admin_notices"
                           <?php checked($debug_options['enable_admin_notices'] ?? false); ?>>
                    <?php _e('Show admin notices for sync operations', 'pac-vehicle-data-manager'); ?>
                </label>
                <p class="description"><?php _e('Displays success/error notices after CCT saves.', 'pac-vehicle-data-manager'); ?></p>
            </td>
        </tr>
    </tbody>
</table>

<p class="submit">
    <button type="button" id="save-debug-settings-btn" class="button button-primary">
        <span class="dashicons dashicons-saved"></span>
        <?php _e('Save Debug Settings', 'pac-vehicle-data-manager'); ?>
    </button>
    <span class="spinner" id="debug-settings-spinner" style="float: none; margin-left: 10px;"></span>
    <span id="debug-settings-message" style="margin-left: 10px;"></span>
</p>

<hr>

<h3><?php _e('Debug Log', 'pac-vehicle-data-manager'); ?></h3>

<div class="log-info">
    <p>
        <strong><?php _e('Log File:', 'pac-vehicle-data-manager'); ?></strong>
        <code><?php echo esc_html($log_file); ?></code>
    </p>
    <p>
        <strong><?php _e('Log Size:', 'pac-vehicle-data-manager'); ?></strong>
        <span id="log-size"><?php echo esc_html($log_size); ?></span>
    </p>
</div>

<div class="log-controls" style="margin: 20px 0;">
    <button type="button" id="view-log-btn" class="button button-secondary" <?php disabled(!$log_exists); ?>>
        <span class="dashicons dashicons-visibility"></span>
        <?php _e('View Log', 'pac-vehicle-data-manager'); ?>
    </button>
    
    <button type="button" id="refresh-log-btn" class="button button-secondary" <?php disabled(!$log_exists); ?>>
        <span class="dashicons dashicons-update"></span>
        <?php _e('Refresh', 'pac-vehicle-data-manager'); ?>
    </button>
    
    <button type="button" id="clear-log-btn" class="button button-secondary" <?php disabled(!$log_exists); ?>>
        <span class="dashicons dashicons-trash"></span>
        <?php _e('Clear Log', 'pac-vehicle-data-manager'); ?>
    </button>
    
    <span class="spinner" id="log-spinner" style="float: none; margin-left: 10px;"></span>
    <span id="log-message" style="margin-left: 10px;"></span>
</div>

<div id="log-viewer" style="display: none;">
    <pre id="log-contents"></pre>
</div>

<style>
#log-viewer {
    margin-top: 20px;
}

#log-contents {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 20px;
    border-radius: 4px;
    max-height: 500px;
    overflow: auto;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    font-size: 12px;
    line-height: 1.5;
    white-space: pre-wrap;
    word-wrap: break-word;
}

#log-contents:empty::before {
    content: "<?php _e('No log entries yet.', 'pac-vehicle-data-manager'); ?>";
    color: #666;
}

.log-info code {
    background: #f0f0f1;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
}
</style>

