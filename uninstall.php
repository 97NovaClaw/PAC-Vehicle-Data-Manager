<?php
/**
 * Uninstall Handler
 *
 * Runs when the plugin is deleted via WordPress admin
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data on uninstall
 */

// Delete plugin options
delete_option('pac_vdm_settings');
delete_option('pac_vdm_debug_options');

// Delete transients
delete_transient('pac_vdm_ccts_cache');
delete_transient('pac_vdm_relations_cache');

// Delete debug log file
$debug_file = plugin_dir_path(__FILE__) . 'debug.txt';
if (file_exists($debug_file)) {
    @unlink($debug_file);
}

// Clear any scheduled hooks (if any were added in future)
// wp_clear_scheduled_hook('pac_vdm_scheduled_event');

