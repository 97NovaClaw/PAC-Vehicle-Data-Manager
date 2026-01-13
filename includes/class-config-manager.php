<?php
/**
 * Configuration Manager
 *
 * Handles CRUD operations for mapping configurations
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Configuration Manager Class
 */
class PAC_VDM_Config_Manager {
    
    /**
     * Get all settings
     *
     * @return array
     */
    public function get_settings() {
        $defaults = [
            'mappings' => [],
            'year_expander' => [
                'enabled' => false,
                'target_cct' => '',
                'start_field' => '',
                'end_field' => '',
                'output_field' => '',
            ],
        ];
        
        $settings = get_option(PAC_VDM_SETTINGS_OPTION, $defaults);
        
        return array_replace_recursive($defaults, $settings);
    }
    
    /**
     * Save all settings
     *
     * @param array $settings Settings array
     * @return bool
     */
    public function save_settings($settings) {
        $result = update_option(PAC_VDM_SETTINGS_OPTION, $settings);
        
        if ($result) {
            pac_vdm_debug_log('Settings saved successfully', $settings);
        } else {
            pac_vdm_log_error('Failed to save settings');
        }
        
        return $result;
    }
    
    /**
     * Get all mappings
     *
     * @param bool $enabled_only Return only enabled mappings
     * @return array
     */
    public function get_mappings($enabled_only = false) {
        $settings = $this->get_settings();
        $mappings = isset($settings['mappings']) ? $settings['mappings'] : [];
        
        if ($enabled_only) {
            $mappings = array_filter($mappings, function($mapping) {
                return !empty($mapping['enabled']);
            });
        }
        
        return $mappings;
    }
    
    /**
     * Get mappings for a specific CCT
     *
     * @param string $cct_slug     CCT slug
     * @param bool   $enabled_only Return only enabled
     * @return array
     */
    public function get_mappings_for_cct($cct_slug, $enabled_only = true) {
        $mappings = $this->get_mappings($enabled_only);
        
        return array_filter($mappings, function($mapping) use ($cct_slug) {
            return isset($mapping['target_cct']) && $mapping['target_cct'] === $cct_slug;
        });
    }
    
    /**
     * Get a single mapping by ID
     *
     * @param string $mapping_id Mapping ID
     * @return array|null
     */
    public function get_mapping($mapping_id) {
        $mappings = $this->get_mappings();
        
        foreach ($mappings as $mapping) {
            if (isset($mapping['id']) && $mapping['id'] === $mapping_id) {
                return $mapping;
            }
        }
        
        return null;
    }
    
    /**
     * Save a mapping (create or update)
     *
     * @param array $mapping_data Mapping data
     * @return string|false Mapping ID on success, false on failure
     */
    public function save_mapping($mapping_data) {
        $settings = $this->get_settings();
        $mappings = isset($settings['mappings']) ? $settings['mappings'] : [];
        
        pac_vdm_debug_log('save_mapping called', [
            'target_cct' => $mapping_data['target_cct'] ?? 'N/A',
            'source_field' => $mapping_data['source_field'] ?? 'N/A',
            'dest_field' => $mapping_data['destination_field'] ?? 'N/A',
            'existing_count' => count($mappings)
        ]);
        
        // FIXED: Check for functional duplicates (same CCT + relation + fields)
        $duplicate_key = null;
        foreach ($mappings as $key => $existing) {
            if (isset($existing['target_cct']) && $existing['target_cct'] === ($mapping_data['target_cct'] ?? '') &&
                isset($existing['trigger_relation']) && $existing['trigger_relation'] == ($mapping_data['trigger_relation'] ?? 0) &&
                isset($existing['source_field']) && $existing['source_field'] === ($mapping_data['source_field'] ?? '') &&
                isset($existing['destination_field']) && $existing['destination_field'] === ($mapping_data['destination_field'] ?? '')) {
                $duplicate_key = $key;
                pac_vdm_debug_log('Found functional duplicate', [
                    'existing_id' => $existing['id'] ?? 'N/A',
                    'key' => $key,
                    'will_update' => true
                ]);
                break;
            }
        }
        
        // Generate ID if not provided
        if (empty($mapping_data['id'])) {
            if ($duplicate_key !== null) {
                // Reuse existing mapping's ID
                $mapping_data['id'] = $mappings[$duplicate_key]['id'];
                $mapping_data['created_at'] = $mappings[$duplicate_key]['created_at'] ?? current_time('mysql');
                pac_vdm_debug_log('Reusing duplicate mapping ID', ['id' => $mapping_data['id']]);
            } else {
                // Generate new ID
                $mapping_data['id'] = 'map_' . wp_generate_uuid4();
                $mapping_data['created_at'] = current_time('mysql');
                pac_vdm_debug_log('Generated new mapping ID', ['id' => $mapping_data['id']]);
            }
        }
        
        // Add/update timestamp
        $mapping_data['updated_at'] = current_time('mysql');
        
        // Merge with defaults
        $mapping_data = $this->merge_mapping_defaults($mapping_data);
        
        // Update duplicate or find by ID
        if ($duplicate_key !== null) {
            $mappings[$duplicate_key] = $mapping_data;
            pac_vdm_debug_log('Updated duplicate mapping', ['key' => $duplicate_key]);
        } else {
            // Find and update existing by ID, or add new
            $found = false;
            foreach ($mappings as $key => $existing) {
                if (isset($existing['id']) && $existing['id'] === $mapping_data['id']) {
                    $mappings[$key] = $mapping_data;
                    $found = true;
                    pac_vdm_debug_log('Updated mapping by ID', ['key' => $key]);
                    break;
                }
            }
            
            if (!$found) {
                $mappings[] = $mapping_data;
                pac_vdm_debug_log('Added new mapping', ['new_total' => count($mappings)]);
            }
        }
        
        $settings['mappings'] = $mappings;
        
        if ($this->save_settings($settings)) {
            pac_vdm_debug_log('Mapping saved successfully', [
                'id' => $mapping_data['id'],
                'total_mappings' => count($mappings)
            ], 'critical');
            return $mapping_data['id'];
        }
        
        pac_vdm_debug_log('Failed to save settings', null, 'error');
        return false;
    }
    
    /**
     * Delete a mapping
     *
     * @param string $mapping_id Mapping ID
     * @return bool
     */
    public function delete_mapping($mapping_id) {
        $settings = $this->get_settings();
        $mappings = isset($settings['mappings']) ? $settings['mappings'] : [];
        
        $mappings = array_filter($mappings, function($mapping) use ($mapping_id) {
            return !isset($mapping['id']) || $mapping['id'] !== $mapping_id;
        });
        
        $settings['mappings'] = array_values($mappings); // Re-index
        
        if ($this->save_settings($settings)) {
            pac_vdm_debug_log('Mapping deleted', ['id' => $mapping_id]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Toggle mapping enabled state
     *
     * @param string $mapping_id Mapping ID
     * @param bool   $enabled    Enabled state
     * @return bool
     */
    public function toggle_mapping($mapping_id, $enabled) {
        $mapping = $this->get_mapping($mapping_id);
        
        if (!$mapping) {
            return false;
        }
        
        $mapping['enabled'] = $enabled;
        
        return $this->save_mapping($mapping) !== false;
    }
    
    /**
     * Merge mapping with defaults
     *
     * @param array $mapping Mapping data
     * @return array
     */
    private function merge_mapping_defaults($mapping) {
        $defaults = [
            'id' => '',
            'target_cct' => '',
            'trigger_relation' => 0,
            'source_field' => '',
            'destination_field' => '',
            'direction' => 'pull', // 'pull', 'push', or 'both'
            'ui_behavior' => 'readonly', // 'readonly' or 'hidden'
            'enabled' => true,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];
        
        return array_merge($defaults, $mapping);
    }
    
    /**
     * Get year expander configuration
     *
     * @return array
     */
    public function get_year_expander_config() {
        $settings = $this->get_settings();
        
        return isset($settings['year_expander']) ? $settings['year_expander'] : [
            'enabled' => false,
            'target_cct' => '',
            'start_field' => '',
            'end_field' => '',
            'output_field' => '',
        ];
    }
    
    /**
     * Save year expander configuration
     *
     * @param array $config Year expander config
     * @return bool
     */
    public function save_year_expander_config($config) {
        $settings = $this->get_settings();
        
        // Sanitize config
        $settings['year_expander'] = [
            'enabled' => !empty($config['enabled']),
            'target_cct' => sanitize_text_field($config['target_cct'] ?? ''),
            'start_field' => sanitize_text_field($config['start_field'] ?? ''),
            'end_field' => sanitize_text_field($config['end_field'] ?? ''),
            'output_field' => sanitize_text_field($config['output_field'] ?? ''),
        ];
        
        if ($this->save_settings($settings)) {
            pac_vdm_debug_log('Year expander config saved', $settings['year_expander']);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get read-only fields for a CCT
     *
     * @param string $cct_slug CCT slug
     * @return array Array of field slugs that should be read-only
     */
    public function get_readonly_fields($cct_slug) {
        $mappings = $this->get_mappings_for_cct($cct_slug, true);
        $readonly_fields = [];
        
        foreach ($mappings as $mapping) {
            if (isset($mapping['ui_behavior']) && $mapping['ui_behavior'] === 'readonly') {
                if (!empty($mapping['destination_field'])) {
                    $readonly_fields[] = $mapping['destination_field'];
                }
            }
        }
        
        return array_unique($readonly_fields);
    }
    
    /**
     * Get hidden fields for a CCT
     *
     * @param string $cct_slug CCT slug
     * @return array Array of field slugs that should be hidden
     */
    public function get_hidden_fields($cct_slug) {
        $mappings = $this->get_mappings_for_cct($cct_slug, true);
        $hidden_fields = [];
        
        foreach ($mappings as $mapping) {
            if (isset($mapping['ui_behavior']) && $mapping['ui_behavior'] === 'hidden') {
                if (!empty($mapping['destination_field'])) {
                    $hidden_fields[] = $mapping['destination_field'];
                }
            }
        }
        
        return array_unique($hidden_fields);
    }
    
    /**
     * Get all CCTs that have active mappings
     *
     * @return array Array of CCT slugs
     */
    public function get_mapped_ccts() {
        $mappings = $this->get_mappings(true);
        $ccts = [];
        
        foreach ($mappings as $mapping) {
            if (!empty($mapping['target_cct'])) {
                $ccts[] = $mapping['target_cct'];
            }
        }
        
        return array_unique($ccts);
    }
    
    /**
     * Validate mapping data
     *
     * @param array $mapping Mapping data to validate
     * @return true|WP_Error
     */
    public function validate_mapping($mapping) {
        $errors = new WP_Error();
        
        if (empty($mapping['target_cct'])) {
            $errors->add('missing_target_cct', __('Target CCT is required.', 'pac-vehicle-data-manager'));
        }
        
        if (empty($mapping['trigger_relation'])) {
            $errors->add('missing_relation', __('Trigger relation is required.', 'pac-vehicle-data-manager'));
        }
        
        if (empty($mapping['source_field'])) {
            $errors->add('missing_source_field', __('Source field is required.', 'pac-vehicle-data-manager'));
        }
        
        if (empty($mapping['destination_field'])) {
            $errors->add('missing_destination_field', __('Destination field is required.', 'pac-vehicle-data-manager'));
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        return true;
    }
}

