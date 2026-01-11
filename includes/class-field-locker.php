<?php
/**
 * Field Locker
 *
 * Enforces read-only fields on CCT edit screens via JavaScript
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Field Locker Class
 */
class PAC_VDM_Field_Locker {
    
    /**
     * Config Manager instance
     *
     * @var PAC_VDM_Config_Manager
     */
    private $config_manager;
    
    /**
     * Current CCT slug (if on CCT edit page)
     *
     * @var string|null
     */
    private $current_cct = null;
    
    /**
     * Constructor
     *
     * @param PAC_VDM_Config_Manager $config_manager Config manager instance
     */
    public function __construct($config_manager) {
        $this->config_manager = $config_manager;
    }
    
    /**
     * Register hooks
     */
    public function register_hooks() {
        add_action('admin_enqueue_scripts', [$this, 'maybe_load_field_locker']);
    }
    
    /**
     * Check if we're on a CCT edit page and load field locker
     *
     * @param string $hook Current admin page hook
     */
    public function maybe_load_field_locker($hook) {
        // Only load on CCT edit pages
        if (!$this->is_cct_edit_page()) {
            return;
        }
        
        $this->current_cct = $this->get_current_cct_slug();
        
        if (!$this->current_cct) {
            return;
        }
        
        // Get fields to lock
        $readonly_fields = $this->config_manager->get_readonly_fields($this->current_cct);
        $hidden_fields = $this->config_manager->get_hidden_fields($this->current_cct);
        
        if (empty($readonly_fields) && empty($hidden_fields)) {
            pac_vdm_debug_log('No fields to lock for CCT', ['cct' => $this->current_cct]);
            return;
        }
        
        pac_vdm_debug_log('Loading field locker', [
            'cct' => $this->current_cct,
            'readonly_fields' => $readonly_fields,
            'hidden_fields' => $hidden_fields,
        ]);
        
        // Enqueue the field locker script
        $this->enqueue_field_locker($readonly_fields, $hidden_fields);
    }
    
    /**
     * Check if current page is a CCT edit page
     *
     * @return bool
     */
    private function is_cct_edit_page() {
        global $pagenow;
        
        if (!is_admin()) {
            return false;
        }
        
        // JetEngine CCT edit pages format: admin.php?page=jet-cct-{slug}
        if ($pagenow === 'admin.php' && isset($_GET['page']) && strpos($_GET['page'], 'jet-cct-') === 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Get current CCT slug from URL
     *
     * @return string|null
     */
    private function get_current_cct_slug() {
        if (!isset($_GET['page'])) {
            return null;
        }
        
        $page = $_GET['page'];
        
        if (strpos($page, 'jet-cct-') === 0) {
            return str_replace('jet-cct-', '', $page);
        }
        
        return null;
    }
    
    /**
     * Enqueue field locker script and localize data
     *
     * @param array $readonly_fields Fields to make read-only
     * @param array $hidden_fields   Fields to hide
     */
    private function enqueue_field_locker($readonly_fields, $hidden_fields) {
        wp_enqueue_script(
            'pac-vdm-field-locker',
            PAC_VDM_PLUGIN_URL . 'assets/js/field-locker.js',
            ['jquery'],
            PAC_VDM_VERSION,
            true
        );
        
        wp_localize_script('pac-vdm-field-locker', 'pacVdmFieldLocker', [
            'cct_slug' => $this->current_cct,
            'readonly_fields' => $readonly_fields,
            'hidden_fields' => $hidden_fields,
            'debug' => pac_vdm_is_js_debug_enabled(),
            'i18n' => [
                'locked_tooltip' => __('This field is managed automatically', 'pac-vehicle-data-manager'),
                'inherited_label' => __('Auto-synced', 'pac-vehicle-data-manager'),
            ],
        ]);
        
        // Add inline styles for locked fields
        $this->add_inline_styles();
    }
    
    /**
     * Add inline CSS for locked field styling
     */
    private function add_inline_styles() {
        $css = '
            .pac-vdm-locked {
                background: #f0f0f1 !important;
                opacity: 0.7;
                cursor: not-allowed;
                pointer-events: none;
            }
            
            .pac-vdm-locked-wrapper {
                position: relative;
            }
            
            .pac-vdm-locked-badge {
                display: inline-flex;
                align-items: center;
                gap: 4px;
                padding: 2px 8px;
                background: #dcdcde;
                border-radius: 3px;
                font-size: 11px;
                color: #50575e;
                margin-left: 8px;
                vertical-align: middle;
            }
            
            .pac-vdm-locked-badge .dashicons {
                width: 14px;
                height: 14px;
                font-size: 14px;
            }
            
            .pac-vdm-hidden-field {
                display: none !important;
            }
            
            /* Ensure select2 and other enhanced inputs also get locked */
            .pac-vdm-locked-wrapper .select2-container,
            .pac-vdm-locked-wrapper .cx-vui-component__control {
                pointer-events: none;
                opacity: 0.7;
            }
        ';
        
        wp_add_inline_style('wp-admin', $css);
    }
    
    /**
     * Get current CCT (public accessor)
     *
     * @return string|null
     */
    public function get_current_cct() {
        return $this->current_cct;
    }
}

