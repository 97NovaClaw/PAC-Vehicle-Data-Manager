<?php
/**
 * Admin Page Handler
 *
 * Manages admin menu, settings page, and AJAX handlers
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Admin Page Class
 */
class PAC_VDM_Admin_Page {
    
    /**
     * Config Manager instance
     *
     * @var PAC_VDM_Config_Manager
     */
    private $config_manager;
    
    /**
     * Discovery Engine instance
     *
     * @var PAC_VDM_Discovery
     */
    private $discovery;
    
    /**
     * Constructor
     *
     * @param PAC_VDM_Config_Manager $config_manager Config manager instance
     * @param PAC_VDM_Discovery      $discovery      Discovery engine instance
     */
    public function __construct($config_manager, $discovery) {
        $this->config_manager = $config_manager;
        $this->discovery = $discovery;
    }
    
    /**
     * Register hooks
     */
    public function register_hooks() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        $this->register_ajax_handlers();
    }
    
    /**
     * Register admin menu
     */
    public function register_admin_menu() {
        add_menu_page(
            __('PAC Vehicle Data', 'pac-vehicle-data-manager'),
            __('PAC Vehicle Data', 'pac-vehicle-data-manager'),
            'manage_options',
            'pac-vehicle-data',
            [$this, 'render_admin_page'],
            'dashicons-database-view',
            80
        );
    }
    
    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our settings page
        if ($hook !== 'toplevel_page_pac-vehicle-data') {
            return;
        }
        
        // Enqueue CSS
        wp_enqueue_style(
            'pac-vdm-admin',
            PAC_VDM_PLUGIN_URL . 'assets/css/admin.css',
            [],
            PAC_VDM_VERSION
        );
        
        // Enqueue JavaScript
        wp_enqueue_script(
            'pac-vdm-admin',
            PAC_VDM_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            PAC_VDM_VERSION,
            true
        );
        
        // Localize script
        $this->localize_admin_script();
    }
    
    /**
     * Localize admin script with data
     */
    private function localize_admin_script() {
        $ccts = $this->discovery->get_all_ccts();
        $relations = $this->discovery->get_all_relations();
        $mappings = $this->config_manager->get_mappings();
        $year_expander = $this->config_manager->get_year_expander_config();
        $debug_options = get_option(PAC_VDM_DEBUG_OPTION, []);
        
        wp_localize_script('pac-vdm-admin', 'pacVdmAdmin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pac_vdm_admin_nonce'),
            'ccts' => $ccts,
            'relations' => $relations,
            'mappings' => $mappings,
            'year_expander' => $year_expander,
            'debug_options' => $debug_options,
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this mapping?', 'pac-vehicle-data-manager'),
                'save_success' => __('Settings saved successfully!', 'pac-vehicle-data-manager'),
                'save_error' => __('Failed to save settings.', 'pac-vehicle-data-manager'),
                'loading' => __('Loading...', 'pac-vehicle-data-manager'),
                'select_cct' => __('-- Select CCT --', 'pac-vehicle-data-manager'),
                'select_relation' => __('-- Select Relation --', 'pac-vehicle-data-manager'),
                'select_field' => __('-- Select Field --', 'pac-vehicle-data-manager'),
                'no_relations' => __('No relations found for this CCT', 'pac-vehicle-data-manager'),
            ],
        ]);
    }
    
    /**
     * Register AJAX handlers
     */
    private function register_ajax_handlers() {
        // Save all mappings
        add_action('wp_ajax_pac_vdm_save_mappings', [$this, 'ajax_save_mappings']);
        
        // Delete single mapping
        add_action('wp_ajax_pac_vdm_delete_mapping', [$this, 'ajax_delete_mapping']);
        
        // Get relations for CCT (filtered)
        add_action('wp_ajax_pac_vdm_get_cct_relations', [$this, 'ajax_get_cct_relations']);
        
        // Get fields for CCT
        add_action('wp_ajax_pac_vdm_get_cct_fields', [$this, 'ajax_get_cct_fields']);
        
        // Get parent CCT fields (for source field dropdown)
        add_action('wp_ajax_pac_vdm_get_parent_fields', [$this, 'ajax_get_parent_fields']);
        
        // Save year expander config
        add_action('wp_ajax_pac_vdm_save_year_expander', [$this, 'ajax_save_year_expander']);
        
        // Save debug settings
        add_action('wp_ajax_pac_vdm_save_debug_settings', [$this, 'ajax_save_debug_settings']);
        
        // View log
        add_action('wp_ajax_pac_vdm_view_log', [$this, 'ajax_view_log']);
        
        // Clear log
        add_action('wp_ajax_pac_vdm_clear_log', [$this, 'ajax_clear_log']);
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'pac-vehicle-data-manager'));
        }
        
        include PAC_VDM_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
    
    /**
     * AJAX: Save all mappings
     */
    public function ajax_save_mappings() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $mappings = isset($_POST['mappings']) ? $_POST['mappings'] : [];
        
        // Sanitize and save each mapping
        $sanitized_mappings = [];
        
        foreach ($mappings as $mapping) {
            $sanitized = [
                'id' => sanitize_text_field($mapping['id'] ?? ''),
                'target_cct' => sanitize_text_field($mapping['target_cct'] ?? ''),
                'trigger_relation' => absint($mapping['trigger_relation'] ?? 0),
                'source_field' => sanitize_text_field($mapping['source_field'] ?? ''),
                'destination_field' => sanitize_text_field($mapping['destination_field'] ?? ''),
                'direction' => in_array($mapping['direction'] ?? '', ['pull', 'push', 'both']) ? $mapping['direction'] : 'pull',
                'ui_behavior' => in_array($mapping['ui_behavior'] ?? '', ['readonly', 'hidden']) ? $mapping['ui_behavior'] : 'readonly',
                'enabled' => !empty($mapping['enabled']),
            ];
            
            // Validate
            if (empty($sanitized['target_cct']) || empty($sanitized['trigger_relation'])) {
                continue;
            }
            
            $sanitized_mappings[] = $sanitized;
        }
        
        // Save to settings
        $settings = $this->config_manager->get_settings();
        $settings['mappings'] = $sanitized_mappings;
        
        if ($this->config_manager->save_settings($settings)) {
            pac_vdm_debug_log('Mappings saved via AJAX', ['count' => count($sanitized_mappings)]);
            wp_send_json_success([
                'message' => __('Mappings saved successfully!', 'pac-vehicle-data-manager'),
                'mappings' => $sanitized_mappings,
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save mappings.', 'pac-vehicle-data-manager')]);
        }
    }
    
    /**
     * AJAX: Delete single mapping
     */
    public function ajax_delete_mapping() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $mapping_id = isset($_POST['mapping_id']) ? sanitize_text_field($_POST['mapping_id']) : '';
        
        if (empty($mapping_id)) {
            wp_send_json_error(['message' => __('Mapping ID required', 'pac-vehicle-data-manager')]);
            return;
        }
        
        if ($this->config_manager->delete_mapping($mapping_id)) {
            wp_send_json_success(['message' => __('Mapping deleted.', 'pac-vehicle-data-manager')]);
        } else {
            wp_send_json_error(['message' => __('Failed to delete mapping.', 'pac-vehicle-data-manager')]);
        }
    }
    
    /**
     * AJAX: Get relations for a CCT (where CCT is child - for PULL)
     */
    public function ajax_get_cct_relations() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug required', 'pac-vehicle-data-manager')]);
            return;
        }
        
        // Get relations where this CCT is the CHILD (for pulling parent data)
        $relations = $this->discovery->get_relations_as_child($cct_slug);
        
        pac_vdm_debug_log('Fetched relations for CCT', [
            'cct_slug' => $cct_slug,
            'relations_count' => count($relations),
        ]);
        
        wp_send_json_success(['relations' => $relations]);
    }
    
    /**
     * AJAX: Get fields for a CCT
     */
    public function ajax_get_cct_fields() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_slug = isset($_POST['cct_slug']) ? sanitize_text_field($_POST['cct_slug']) : '';
        
        if (empty($cct_slug)) {
            wp_send_json_error(['message' => __('CCT slug required', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct = $this->discovery->get_cct($cct_slug);
        
        if (!$cct) {
            wp_send_json_error(['message' => __('CCT not found', 'pac-vehicle-data-manager')]);
            return;
        }
        
        wp_send_json_success(['fields' => $cct['fields']]);
    }
    
    /**
     * AJAX: Get parent CCT fields for a relation
     */
    public function ajax_get_parent_fields() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $relation_id = isset($_POST['relation_id']) ? absint($_POST['relation_id']) : 0;
        
        if (empty($relation_id)) {
            wp_send_json_error(['message' => __('Relation ID required', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $relation = $this->discovery->get_relation($relation_id);
        
        if (!$relation) {
            wp_send_json_error(['message' => __('Relation not found', 'pac-vehicle-data-manager')]);
            return;
        }
        
        // Parse parent object to get CCT slug
        $parsed = $this->discovery->parse_relation_object($relation['parent_object']);
        
        if ($parsed['type'] !== 'cct') {
            wp_send_json_error(['message' => __('Parent is not a CCT', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $parent_cct = $this->discovery->get_cct($parsed['slug']);
        
        if (!$parent_cct) {
            wp_send_json_error(['message' => __('Parent CCT not found', 'pac-vehicle-data-manager')]);
            return;
        }
        
        wp_send_json_success([
            'parent_cct' => $parsed['slug'],
            'parent_name' => $parent_cct['name'],
            'fields' => $parent_cct['fields'],
        ]);
    }
    
    /**
     * AJAX: Save year expander config
     */
    public function ajax_save_year_expander() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $config = [
            'enabled' => !empty($_POST['enabled']),
            'target_cct' => sanitize_text_field($_POST['target_cct'] ?? ''),
            'start_field' => sanitize_text_field($_POST['start_field'] ?? ''),
            'end_field' => sanitize_text_field($_POST['end_field'] ?? ''),
            'output_field' => sanitize_text_field($_POST['output_field'] ?? ''),
        ];
        
        if ($this->config_manager->save_year_expander_config($config)) {
            wp_send_json_success(['message' => __('Year Expander settings saved!', 'pac-vehicle-data-manager')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save settings.', 'pac-vehicle-data-manager')]);
        }
    }
    
    /**
     * AJAX: Save debug settings
     */
    public function ajax_save_debug_settings() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $options = [
            'enable_php_logging' => !empty($_POST['enable_php_logging']),
            'enable_js_console' => !empty($_POST['enable_js_console']),
            'enable_admin_notices' => !empty($_POST['enable_admin_notices']),
        ];
        
        if (update_option(PAC_VDM_DEBUG_OPTION, $options)) {
            pac_vdm_debug_log('Debug settings updated', $options);
            wp_send_json_success(['message' => __('Debug settings saved!', 'pac-vehicle-data-manager')]);
        } else {
            wp_send_json_error(['message' => __('Failed to save settings.', 'pac-vehicle-data-manager')]);
        }
    }
    
    /**
     * AJAX: View debug log
     */
    public function ajax_view_log() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $contents = pac_vdm_get_log_contents();
        $size = pac_vdm_get_log_size();
        
        wp_send_json_success([
            'contents' => $contents,
            'size' => $size,
        ]);
    }
    
    /**
     * AJAX: Clear debug log
     */
    public function ajax_clear_log() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        if (pac_vdm_clear_log()) {
            wp_send_json_success(['message' => __('Log cleared.', 'pac-vehicle-data-manager')]);
        } else {
            wp_send_json_error(['message' => __('Failed to clear log.', 'pac-vehicle-data-manager')]);
        }
    }
}

