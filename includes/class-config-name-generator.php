<?php
/**
 * Config Name Generator Module
 *
 * Auto-generates config_name from vehicle data on save
 * Template: "{year_start}-{year_end} {make_name} {model_name} {generation_code}"
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Config Name Generator Class
 */
class PAC_VDM_Config_Name_Generator {
    
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
     * Register hooks
     */
    public function register_hooks() {
        // Hook into item-to-update filter to generate config name before save
        add_filter(
            'jet-engine/custom-content-types/item-to-update',
            [$this, 'maybe_generate_config_name'],
            20, // After data flattener (priority 15)
            3
        );
        
        pac_vdm_debug_log('Config Name Generator hooks registered');
    }
    
    /**
     * Check if config name generation should be applied
     *
     * @param array  $item    Item data being saved
     * @param array  $fields  CCT field definitions
     * @param object $handler Item handler instance
     * @return array Modified item data
     */
    public function maybe_generate_config_name($item, $fields, $handler) {
        try {
            // Get the CCT slug from the handler
            $cct_slug = $this->get_cct_slug_from_handler($handler);
            
            if (!$cct_slug) {
                return $item;
            }
            
            // Get config
            $config = $this->config_manager->get_config_name_generator_config();
            
            if (!$this->should_process($cct_slug, $config)) {
                return $item;
            }
            
            pac_vdm_debug_log('Config Name Generator processing', [
                'cct_slug' => $cct_slug,
                'config' => $config,
            ]);
            
            // Generate the config name
            $config_name = $this->build_config_name($item, $config);
            
            if (!empty($config_name)) {
                $output_field = $config['output_field'];
                $item[$output_field] = $config_name;
                
                pac_vdm_debug_log('Config name generated', [
                    'output_field' => $output_field,
                    'config_name' => $config_name,
                ], 'critical');
            }
            
            return $item;
            
        } catch (\Exception $e) {
            pac_vdm_log_error('Config Name Generator error: ' . $e->getMessage());
            return $item;
        }
    }
    
    /**
     * Check if this item should be processed
     *
     * @param string $cct_slug CCT slug
     * @param array  $config   Config settings
     * @return bool
     */
    private function should_process($cct_slug, $config) {
        if (empty($config['enabled'])) {
            return false;
        }
        
        if (empty($config['target_cct'])) {
            return false;
        }
        
        if ($cct_slug !== $config['target_cct']) {
            return false;
        }
        
        if (empty($config['output_field'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Build config name from template and available data
     *
     * @param array $item   Item data
     * @param array $config Config settings
     * @return string Generated config name
     */
    private function build_config_name($item, $config) {
        $template = !empty($config['template']) ? $config['template'] : '{year_start}-{year_end} {make_name} {model_name} {generation_code}';
        
        pac_vdm_debug_log('Building config name', [
            'template' => $template,
            'available_fields' => array_keys($item),
        ]);
        
        // Extract field values from item data
        $values = [];
        
        // Parse template to find all {field_name} placeholders
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $template, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $field_name) {
                $values[$field_name] = isset($item[$field_name]) ? trim($item[$field_name]) : '';
            }
        }
        
        pac_vdm_debug_log('Extracted field values', $values);
        
        // Replace placeholders in template
        $config_name = $template;
        
        foreach ($values as $field_name => $value) {
            if (!empty($value)) {
                $config_name = str_replace('{' . $field_name . '}', $value, $config_name);
            } else {
                // Remove placeholder for empty values
                $config_name = str_replace('{' . $field_name . '}', '', $config_name);
            }
        }
        
        // Clean up extra spaces
        $config_name = preg_replace('/\s+/', ' ', $config_name);
        $config_name = trim($config_name);
        
        pac_vdm_debug_log('Generated config name', [
            'result' => $config_name,
            'length' => strlen($config_name),
        ]);
        
        return $config_name;
    }
    
    /**
     * Get CCT slug from handler
     *
     * @param object $handler Item handler instance
     * @return string|null CCT slug
     */
    private function get_cct_slug_from_handler($handler) {
        if (!$handler) {
            return null;
        }
        
        // Try different methods to get the slug
        if (method_exists($handler, 'get_type_slug')) {
            return $handler->get_type_slug();
        }
        
        if (isset($handler->type_slug)) {
            return $handler->type_slug;
        }
        
        if (isset($handler->content_type) && method_exists($handler->content_type, 'get_arg')) {
            return $handler->content_type->get_arg('slug');
        }
        
        return null;
    }
}

