<?php
/**
 * Plugin Name: PAC Vehicle Data Manager
 * Plugin URI: https://github.com/your-repo/pac-vehicle-data-manager
 * Description: Automates data inheritance between related CCTs, enforces data integrity via read-only fields, and auto-generates utility data (year ranges) for filtering.
 * Version: 1.0.0
 * Author: PAC Development
 * Author URI: https://yourwebsite.com
 * Text Domain: pac-vehicle-data-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Plugin version
 */
define('PAC_VDM_VERSION', '1.0.0');

/**
 * Plugin directory path
 */
define('PAC_VDM_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * Plugin directory URL
 */
define('PAC_VDM_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Plugin base name
 */
define('PAC_VDM_PLUGIN_BASE', plugin_basename(__FILE__));

/**
 * Minimum required JetEngine version
 */
define('PAC_VDM_MIN_JETENGINE_VERSION', '3.3.1');

/**
 * Settings option name
 */
define('PAC_VDM_SETTINGS_OPTION', 'pac_vdm_settings');

/**
 * Debug options name
 */
define('PAC_VDM_DEBUG_OPTION', 'pac_vdm_debug_options');

/**
 * Check dependencies on activation
 */
register_activation_hook(__FILE__, 'pac_vdm_activate');

function pac_vdm_activate() {
    // Load debug functions first
    require_once PAC_VDM_PLUGIN_DIR . 'includes/helpers/debug.php';
    
    // Check if JetEngine is active
    if (!class_exists('Jet_Engine')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('PAC Vehicle Data Manager requires JetEngine to be installed and activated.', 'pac-vehicle-data-manager'),
            __('Plugin Activation Error', 'pac-vehicle-data-manager'),
            ['back_link' => true]
        );
    }
    
    // Check JetEngine version
    if (defined('JET_ENGINE_VERSION')) {
        if (version_compare(JET_ENGINE_VERSION, PAC_VDM_MIN_JETENGINE_VERSION, '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                sprintf(
                    __('PAC Vehicle Data Manager requires JetEngine version %s or higher. You are running version %s.', 'pac-vehicle-data-manager'),
                    PAC_VDM_MIN_JETENGINE_VERSION,
                    JET_ENGINE_VERSION
                ),
                __('Plugin Activation Error', 'pac-vehicle-data-manager'),
                ['back_link' => true]
            );
        }
    }
    
    // Check if CCT module is enabled
    if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('PAC Vehicle Data Manager requires JetEngine\'s Custom Content Types module to be enabled.', 'pac-vehicle-data-manager'),
            __('Plugin Activation Error', 'pac-vehicle-data-manager'),
            ['back_link' => true]
        );
    }
    
    // Check if Relations module is enabled
    if (!class_exists('\\Jet_Engine\\Relations\\Manager')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            __('PAC Vehicle Data Manager requires JetEngine\'s Relations module to be enabled.', 'pac-vehicle-data-manager'),
            __('Plugin Activation Error', 'pac-vehicle-data-manager'),
            ['back_link' => true]
        );
    }
    
    // Initialize default settings
    if (!get_option(PAC_VDM_SETTINGS_OPTION)) {
        update_option(PAC_VDM_SETTINGS_OPTION, [
            'mappings' => [],
            'year_expander' => [
                'enabled' => false,
                'target_cct' => '',
                'start_field' => '',
                'end_field' => '',
                'output_field' => '',
            ],
        ]);
    }
    
    // Initialize default debug options
    if (!get_option(PAC_VDM_DEBUG_OPTION)) {
        update_option(PAC_VDM_DEBUG_OPTION, [
            'enable_php_logging' => false,
            'enable_js_console' => false,
            'enable_admin_notices' => false,
        ]);
    }
}

/**
 * Clean up on deactivation
 */
register_deactivation_hook(__FILE__, 'pac_vdm_deactivate');

function pac_vdm_deactivate() {
    // Optional: Clear any transients
    delete_transient('pac_vdm_ccts_cache');
    delete_transient('pac_vdm_relations_cache');
}

/**
 * Load plugin text domain for translations
 */
add_action('plugins_loaded', 'pac_vdm_load_textdomain');

function pac_vdm_load_textdomain() {
    load_plugin_textdomain(
        'pac-vehicle-data-manager',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', 'pac_vdm_init', 20);

function pac_vdm_init() {
    // Check dependencies again before init
    if (!class_exists('Jet_Engine')) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('PAC Vehicle Data Manager', 'pac-vehicle-data-manager'); ?>:</strong>
                    <?php _e('JetEngine is required but not active.', 'pac-vehicle-data-manager'); ?>
                </p>
            </div>
            <?php
        });
        return;
    }
    
    // Wrap in try-catch to prevent site crashes
    try {
        // Load debug functions first
        require_once PAC_VDM_PLUGIN_DIR . 'includes/helpers/debug.php';
        
        // Load core classes
        require_once PAC_VDM_PLUGIN_DIR . 'includes/class-discovery.php';
        require_once PAC_VDM_PLUGIN_DIR . 'includes/class-config-manager.php';
        require_once PAC_VDM_PLUGIN_DIR . 'includes/class-year-expander.php';
        require_once PAC_VDM_PLUGIN_DIR . 'includes/class-data-flattener.php';
        require_once PAC_VDM_PLUGIN_DIR . 'includes/class-field-locker.php';
        require_once PAC_VDM_PLUGIN_DIR . 'includes/class-admin-page.php';
        require_once PAC_VDM_PLUGIN_DIR . 'includes/class-plugin.php';
        
        // Initialize plugin
        PAC_VDM_Plugin::instance();
        
        pac_vdm_debug_log('Plugin initialized', ['version' => PAC_VDM_VERSION]);
        
    } catch (Throwable $e) {
        // Catch ALL errors including fatal ones (PHP 7.0+)
        $error_msg = 'PAC Vehicle Data Manager Fatal Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
        error_log($error_msg);
        
        // Try to log to our debug file if available
        @file_put_contents(
            PAC_VDM_PLUGIN_DIR . 'debug.txt',
            "[" . date('Y-m-d H:i:s') . "] [FATAL] " . $error_msg . "\n" . $e->getTraceAsString() . "\n",
            FILE_APPEND
        );
        
        add_action('admin_notices', function() use ($e) {
            ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e('PAC Vehicle Data Manager', 'pac-vehicle-data-manager'); ?>:</strong>
                    <?php echo esc_html($e->getMessage()); ?>
                    <br><small><?php echo esc_html($e->getFile() . ':' . $e->getLine()); ?></small>
                </p>
            </div>
            <?php
        });
    }
}

/**
 * Add settings link on plugins page
 */
add_filter('plugin_action_links_' . PAC_VDM_PLUGIN_BASE, 'pac_vdm_add_settings_link');

function pac_vdm_add_settings_link($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url('admin.php?page=pac-vehicle-data'),
        __('Settings', 'pac-vehicle-data-manager')
    );
    array_unshift($links, $settings_link);
    return $links;
}

