<?php
/**
 * Main Plugin Class
 *
 * Singleton that orchestrates all modules
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Main Plugin Singleton Class
 */
class PAC_VDM_Plugin {
    
    /**
     * Plugin instance
     *
     * @var PAC_VDM_Plugin
     */
    private static $instance = null;
    
    /**
     * Discovery Engine instance
     *
     * @var PAC_VDM_Discovery
     */
    public $discovery;
    
    /**
     * Config Manager instance
     *
     * @var PAC_VDM_Config_Manager
     */
    public $config_manager;
    
    /**
     * Year Expander instance
     *
     * @var PAC_VDM_Year_Expander
     */
    public $year_expander;
    
    /**
     * Data Flattener instance
     *
     * @var PAC_VDM_Data_Flattener
     */
    public $data_flattener;
    
    /**
     * Field Locker instance
     *
     * @var PAC_VDM_Field_Locker
     */
    public $field_locker;
    
    /**
     * Admin Page instance
     *
     * @var PAC_VDM_Admin_Page
     */
    public $admin_page;
    
    /**
     * Get singleton instance
     *
     * @return PAC_VDM_Plugin
     */
    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize all modules
     */
    private function __construct() {
        $this->init_modules();
        
        pac_vdm_debug_log('Plugin modules initialized');
    }
    
    /**
     * Initialize plugin modules
     */
    private function init_modules() {
        // Core modules (no dependencies)
        $this->discovery = new PAC_VDM_Discovery();
        $this->config_manager = new PAC_VDM_Config_Manager();
        
        // Data processing modules
        $this->year_expander = new PAC_VDM_Year_Expander($this->config_manager);
        $this->data_flattener = new PAC_VDM_Data_Flattener($this->config_manager, $this->discovery);
        
        // UI enforcement
        $this->field_locker = new PAC_VDM_Field_Locker($this->config_manager);
        
        // Register hooks for data processors
        $this->year_expander->register_hooks();
        $this->data_flattener->register_hooks();
        $this->field_locker->register_hooks();
        
        // Admin page (only in admin)
        if (is_admin()) {
            $this->admin_page = new PAC_VDM_Admin_Page($this->config_manager, $this->discovery);
            $this->admin_page->register_hooks();
        }
    }
    
    /**
     * Get Discovery Engine instance
     *
     * @return PAC_VDM_Discovery
     */
    public function get_discovery() {
        return $this->discovery;
    }
    
    /**
     * Get Config Manager instance
     *
     * @return PAC_VDM_Config_Manager
     */
    public function get_config_manager() {
        return $this->config_manager;
    }
    
    /**
     * Get Year Expander instance
     *
     * @return PAC_VDM_Year_Expander
     */
    public function get_year_expander() {
        return $this->year_expander;
    }
    
    /**
     * Get Data Flattener instance
     *
     * @return PAC_VDM_Data_Flattener
     */
    public function get_data_flattener() {
        return $this->data_flattener;
    }
    
    /**
     * Get Field Locker instance
     *
     * @return PAC_VDM_Field_Locker
     */
    public function get_field_locker() {
        return $this->field_locker;
    }
    
    /**
     * Get Admin Page instance
     *
     * @return PAC_VDM_Admin_Page|null
     */
    public function get_admin_page() {
        return $this->admin_page;
    }
}

/**
 * Get plugin instance
 *
 * @return PAC_VDM_Plugin
 */
function pac_vdm() {
    return PAC_VDM_Plugin::instance();
}

