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
        
        // Setup Wizard - CCT Mapping
        add_action('wp_ajax_pac_vdm_save_cct_mapping', [$this, 'ajax_save_cct_mapping']);
        
        // Setup Wizard - Create CCT
        add_action('wp_ajax_pac_vdm_create_cct', [$this, 'ajax_create_cct']);
        
        // Setup Wizard - Add missing fields to CCT
        add_action('wp_ajax_pac_vdm_add_missing_fields', [$this, 'ajax_add_missing_fields']);
        
        // Setup Wizard - Create relation
        add_action('wp_ajax_pac_vdm_create_relation', [$this, 'ajax_create_relation']);
        
        // Setup Wizard - Create all missing relations
        add_action('wp_ajax_pac_vdm_create_all_relations', [$this, 'ajax_create_all_relations']);
        
        // Setup Wizard - Auto-create all field mappings
        add_action('wp_ajax_pac_vdm_auto_create_mappings', [$this, 'ajax_auto_create_mappings']);
        
        // Get setup status
        add_action('wp_ajax_pac_vdm_get_setup_status', [$this, 'ajax_get_setup_status']);
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
    
    /**
     * AJAX: Save CCT role mapping
     */
    public function ajax_save_cct_mapping() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_builder = new PAC_VDM_CCT_Builder();
        $ccts = isset($_POST['ccts']) ? $_POST['ccts'] : [];
        
        // Check if any roles are set to create new
        $created_ccts = [];
        
        foreach ($ccts as $role => $slug) {
            if ($slug === '__create_new__') {
                // Create the CCT
                $result = $cct_builder->create_cct($role);
                
                if (is_wp_error($result)) {
                    wp_send_json_error([
                        'message' => sprintf(
                            __('Failed to create CCT for %s: %s', 'pac-vehicle-data-manager'),
                            $role,
                            $result->get_error_message()
                        ),
                    ]);
                    return;
                }
                
                // Get the role definition to get the default slug
                $roles = $cct_builder->get_cct_roles();
                $ccts[$role] = $roles[$role]['slug'];
                $created_ccts[] = $roles[$role]['name'];
            }
        }
        
        // Save the mapping
        $setup = $cct_builder->get_setup();
        $setup['ccts'] = array_merge($setup['ccts'] ?? [], $ccts);
        
        if ($cct_builder->save_setup($setup)) {
            pac_vdm_debug_log('CCT mapping saved', $setup);
            
            $message = __('CCT mapping saved successfully!', 'pac-vehicle-data-manager');
            
            if (!empty($created_ccts)) {
                $message .= ' ' . sprintf(
                    __('Created CCTs: %s', 'pac-vehicle-data-manager'),
                    implode(', ', $created_ccts)
                );
            }
            
            wp_send_json_success([
                'message' => $message,
                'mapping_status' => $cct_builder->get_mapping_status(),
                'relations_status' => $cct_builder->get_relations_status(),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save CCT mapping.', 'pac-vehicle-data-manager')]);
        }
    }
    
    /**
     * AJAX: Create CCT for a role
     */
    public function ajax_create_cct() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $role = isset($_POST['role']) ? sanitize_key($_POST['role']) : '';
        $custom_slug = isset($_POST['custom_slug']) ? sanitize_title($_POST['custom_slug']) : '';
        $custom_name = isset($_POST['custom_name']) ? sanitize_text_field($_POST['custom_name']) : '';
        
        if (empty($role)) {
            wp_send_json_error(['message' => __('Role is required.', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_builder = new PAC_VDM_CCT_Builder();
        $result = $cct_builder->create_cct($role, $custom_slug, $custom_name);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        $roles = $cct_builder->get_cct_roles();
        $slug = !empty($custom_slug) ? $custom_slug : $roles[$role]['slug'];
        
        // Update setup mapping
        $setup = $cct_builder->get_setup();
        $setup['ccts'][$role] = $slug;
        $cct_builder->save_setup($setup);
        
        pac_vdm_debug_log('CCT created via wizard', ['role' => $role, 'cct_id' => $result]);
        
        wp_send_json_success([
            'message' => __('CCT created successfully!', 'pac-vehicle-data-manager'),
            'cct_id' => $result,
            'slug' => $slug,
            'mapping_status' => $cct_builder->get_mapping_status(),
            'relations_status' => $cct_builder->get_relations_status(),
        ]);
    }
    
    /**
     * AJAX: Add missing fields to CCT
     * 
     * FIXED: Added detailed error logging for debugging
     */
    public function ajax_add_missing_fields() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $role = isset($_POST['role']) ? sanitize_key($_POST['role']) : '';
        $slug = isset($_POST['slug']) ? sanitize_text_field($_POST['slug']) : '';
        
        if (empty($role) || empty($slug)) {
            wp_send_json_error(['message' => __('Role and slug are required.', 'pac-vehicle-data-manager')]);
            return;
        }
        
        pac_vdm_debug_log('AJAX: Add missing fields request', [
            'role' => $role,
            'slug' => $slug
        ], 'critical');
        
        $cct_builder = new PAC_VDM_CCT_Builder();
        $roles = $cct_builder->get_cct_roles();
        
        if (!isset($roles[$role])) {
            pac_vdm_debug_log('Invalid role specified', ['role' => $role], 'error');
            wp_send_json_error(['message' => __('Invalid role.', 'pac-vehicle-data-manager')]);
            return;
        }
        
        try {
            $result = $cct_builder->add_missing_fields_to_cct($slug, $roles[$role]['fields']);
            
            pac_vdm_debug_log('add_missing_fields_to_cct returned', ['result' => $result]);
            
            if ($result) {
                pac_vdm_debug_log('Preparing success response...', null, 'critical');
                
                // Get mapping status - wrapped separately in case it fails
                $mapping_status = [];
                try {
                    $mapping_status = $cct_builder->get_mapping_status();
                    pac_vdm_debug_log('Got mapping status successfully');
                } catch (\Exception $e) {
                    pac_vdm_debug_log('get_mapping_status failed', ['error' => $e->getMessage()], 'warning');
                }
                
                pac_vdm_debug_log('Sending success response', null, 'critical');
                
                wp_send_json_success([
                    'message' => __('Missing fields added successfully!', 'pac-vehicle-data-manager'),
                    'mapping_status' => $mapping_status,
                ]);
            } else {
                pac_vdm_debug_log('add_missing_fields_to_cct returned false', null, 'error');
                
                wp_send_json_error([
                    'message' => __('Failed to add missing fields. Check debug log for details.', 'pac-vehicle-data-manager')
                ]);
            }
        } catch (\Throwable $e) {
            pac_vdm_debug_log('Exception in ajax_add_missing_fields', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 'error');
            
            wp_send_json_error([
                'message' => sprintf(__('Error: %s', 'pac-vehicle-data-manager'), $e->getMessage())
            ]);
        }
    }
    
    /**
     * AJAX: Create single relation
     */
    public function ajax_create_relation() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $parent_slug = isset($_POST['parent_slug']) ? sanitize_text_field($_POST['parent_slug']) : '';
        $child_slug = isset($_POST['child_slug']) ? sanitize_text_field($_POST['child_slug']) : '';
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        
        if (empty($parent_slug) || empty($child_slug) || empty($name)) {
            wp_send_json_error(['message' => __('Parent, child, and name are required.', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_builder = new PAC_VDM_CCT_Builder();
        $result = $cct_builder->create_relation($parent_slug, $child_slug, $name);
        
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
            return;
        }
        
        pac_vdm_debug_log('Relation created via wizard', [
            'relation_id' => $result,
            'parent' => $parent_slug,
            'child' => $child_slug,
        ]);
        
        wp_send_json_success([
            'message' => __('Relation created successfully!', 'pac-vehicle-data-manager'),
            'relation_id' => $result,
            'relations_status' => $cct_builder->get_relations_status(),
        ]);
    }
    
    /**
     * AJAX: Create all missing relations
     */
    public function ajax_create_all_relations() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_builder = new PAC_VDM_CCT_Builder();
        $relations_status = $cct_builder->get_relations_status();
        $relations_defs = $cct_builder->get_relation_definitions();
        
        $created = 0;
        $errors = [];
        
        foreach ($relations_status as $key => $status) {
            if ($status['is_ready'] && !$status['is_complete']) {
                $result = $cct_builder->create_relation(
                    $status['parent_slug'],
                    $status['child_slug'],
                    $status['name']
                );
                
                if (is_wp_error($result)) {
                    $errors[] = $status['name'] . ': ' . $result->get_error_message();
                } else {
                    $created++;
                }
            }
        }
        
        if ($created > 0 || empty($errors)) {
            $message = sprintf(
                _n('%d relation created.', '%d relations created.', $created, 'pac-vehicle-data-manager'),
                $created
            );
            
            if (!empty($errors)) {
                $message .= ' ' . __('Errors:', 'pac-vehicle-data-manager') . ' ' . implode('; ', $errors);
            }
            
            pac_vdm_debug_log('Created all relations via wizard', ['count' => $created]);
            
            wp_send_json_success([
                'message' => $message,
                'created' => $created,
                'relations_status' => $cct_builder->get_relations_status(),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to create relations.', 'pac-vehicle-data-manager') . ' ' . implode('; ', $errors),
            ]);
        }
    }
    
    /**
     * AJAX: Auto-create all field mappings
     */
    public function ajax_auto_create_mappings() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_builder = new PAC_VDM_CCT_Builder();
        $created_mappings = $cct_builder->auto_create_field_mappings();
        
        $count = count($created_mappings);
        
        if ($count > 0) {
            wp_send_json_success([
                'message' => sprintf(
                    _n('%d field mapping created.', '%d field mappings created.', $count, 'pac-vehicle-data-manager'),
                    $count
                ),
                'mappings' => $this->config_manager->get_mappings(),
                'year_expander' => $this->config_manager->get_year_expander_config(),
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No mappings to create. Ensure relations are set up first.', 'pac-vehicle-data-manager'),
            ]);
        }
    }
    
    /**
     * AJAX: Get current setup status
     */
    public function ajax_get_setup_status() {
        check_ajax_referer('pac_vdm_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'pac-vehicle-data-manager')]);
            return;
        }
        
        $cct_builder = new PAC_VDM_CCT_Builder();
        
        wp_send_json_success([
            'mapping_status' => $cct_builder->get_mapping_status(),
            'relations_status' => $cct_builder->get_relations_status(),
            'cct_roles' => $cct_builder->get_cct_roles(),
            'relation_definitions' => $cct_builder->get_relation_definitions(),
        ]);
    }
}

