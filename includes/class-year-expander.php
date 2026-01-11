<?php
/**
 * Year Expander Module
 *
 * Generates year arrays from start/end range on CCT save
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Year Expander Class
 */
class PAC_VDM_Year_Expander {
    
    /**
     * Config Manager instance
     *
     * @var PAC_VDM_Config_Manager
     */
    private $config_manager;
    
    /**
     * Constructor
     *
     * @param PAC_VDM_Config_Manager $config_manager Config manager instance
     */
    public function __construct($config_manager) {
        $this->config_manager = $config_manager;
    }
    
    /**
     * Register hooks for year expansion
     */
    public function register_hooks() {
        // Hook into the item-to-update filter to inject expanded years before save
        add_filter(
            'jet-engine/custom-content-types/item-to-update',
            [$this, 'maybe_expand_years'],
            10,
            3
        );
        
        pac_vdm_debug_log('Year Expander hooks registered');
    }
    
    /**
     * Check if year expansion should be applied and process
     *
     * @param array  $item    Item data being saved
     * @param array  $fields  CCT field definitions
     * @param object $handler Item handler instance
     * @return array Modified item data
     */
    public function maybe_expand_years($item, $fields, $handler) {
        try {
            // Get the CCT slug from the handler
            $cct_slug = $this->get_cct_slug_from_handler($handler);
            
            if (!$cct_slug) {
                return $item;
            }
            
            // Check if this CCT has year expansion enabled
            $config = $this->config_manager->get_year_expander_config();
            
            if (!$this->should_process($cct_slug, $config)) {
                return $item;
            }
            
            pac_vdm_debug_log('Year Expander processing', [
                'cct_slug' => $cct_slug,
                'config' => $config,
            ]);
            
            // Expand the year range
            $item = $this->expand_year_range($item, $config);
            
        } catch (Throwable $e) {
            pac_vdm_log_error('Year Expander error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
        
        return $item;
    }
    
    /**
     * Check if this CCT should have years expanded
     *
     * @param string $cct_slug Current CCT slug
     * @param array  $config   Year expander configuration
     * @return bool
     */
    private function should_process($cct_slug, $config) {
        // Must be enabled
        if (empty($config['enabled'])) {
            return false;
        }
        
        // Must match target CCT
        if (empty($config['target_cct']) || $config['target_cct'] !== $cct_slug) {
            return false;
        }
        
        // Must have all required field configs
        if (empty($config['start_field']) || empty($config['end_field']) || empty($config['output_field'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Expand year range and inject into item data
     *
     * @param array $item   Item data
     * @param array $config Year expander config
     * @return array Modified item data with expanded years
     */
    private function expand_year_range($item, $config) {
        $start_field = $config['start_field'];
        $end_field = $config['end_field'];
        $output_field = $config['output_field'];
        
        // Get start and end values
        $start_year = isset($item[$start_field]) ? intval($item[$start_field]) : 0;
        $end_year = isset($item[$end_field]) ? intval($item[$end_field]) : 0;
        
        pac_vdm_debug_log('Expanding year range', [
            'start_year' => $start_year,
            'end_year' => $end_year,
        ]);
        
        // Validate years
        if ($start_year <= 0 || $end_year <= 0) {
            pac_vdm_log_warning('Invalid year values', [
                'start_year' => $start_year,
                'end_year' => $end_year,
            ]);
            $item[$output_field] = [];
            return $item;
        }
        
        // Handle edge case: start > end (swap or return empty)
        if ($start_year > $end_year) {
            pac_vdm_log_warning('Start year greater than end year, swapping values');
            $temp = $start_year;
            $start_year = $end_year;
            $end_year = $temp;
        }
        
        // Generate year array
        $years = [];
        for ($i = $start_year; $i <= $end_year; $i++) {
            $years[] = $i;
        }
        
        // Store as serialized array (JetEngine compatible)
        $item[$output_field] = $years;
        
        pac_vdm_debug_log('Year range expanded', [
            'output_field' => $output_field,
            'years' => $years,
            'count' => count($years),
        ]);
        
        return $item;
    }
    
    /**
     * Get CCT slug from handler
     *
     * @param object $handler Item handler instance
     * @return string|null CCT slug or null
     */
    private function get_cct_slug_from_handler($handler) {
        if (!is_object($handler)) {
            return null;
        }
        
        // Try to get factory from handler
        if (method_exists($handler, 'get_factory')) {
            $factory = $handler->get_factory();
            if ($factory && method_exists($factory, 'get_arg')) {
                return $factory->get_arg('slug');
            }
        }
        
        // Fallback: Check if handler has factory property
        if (property_exists($handler, 'factory') && is_object($handler->factory)) {
            if (method_exists($handler->factory, 'get_arg')) {
                return $handler->factory->get_arg('slug');
            }
        }
        
        return null;
    }
    
    /**
     * Manual year expansion (for testing/utility)
     *
     * @param int $start_year Start year
     * @param int $end_year   End year
     * @return array Array of years
     */
    public function generate_year_array($start_year, $end_year) {
        $start = intval($start_year);
        $end = intval($end_year);
        
        if ($start <= 0 || $end <= 0) {
            return [];
        }
        
        if ($start > $end) {
            $temp = $start;
            $start = $end;
            $end = $temp;
        }
        
        $years = [];
        for ($i = $start; $i <= $end; $i++) {
            $years[] = $i;
        }
        
        return $years;
    }
}

