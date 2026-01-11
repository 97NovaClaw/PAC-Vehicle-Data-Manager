<?php
/**
 * Data Flattener Engine
 *
 * Handles data inheritance between related CCTs via JetEngine Relations
 * Supports both PULL (child gets parent data) and PUSH (parent updates children)
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Data Flattener Class
 */
class PAC_VDM_Data_Flattener {
    
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
     * Register hooks for data flattening
     */
    public function register_hooks() {
        // PULL: Hook into item-to-update filter to inject parent data before save
        add_filter(
            'jet-engine/custom-content-types/item-to-update',
            [$this, 'process_pull_data'],
            15, // After year expander (priority 10)
            3
        );
        
        // PUSH: Register post-save hooks for each mapped CCT
        $this->register_push_hooks();
        
        pac_vdm_debug_log('Data Flattener hooks registered');
    }
    
    /**
     * Register PUSH hooks for CCTs that need to update children
     */
    private function register_push_hooks() {
        $mappings = $this->config_manager->get_mappings(true);
        $processed_ccts = [];
        
        foreach ($mappings as $mapping) {
            // Only register for 'push' or 'both' direction
            if (!in_array($mapping['direction'] ?? 'pull', ['push', 'both'])) {
                continue;
            }
            
            // Get the parent CCT from the relation
            $relation = $this->discovery->get_relation($mapping['trigger_relation']);
            if (!$relation) {
                continue;
            }
            
            $parsed = $this->discovery->parse_relation_object($relation['parent_object']);
            if ($parsed['type'] !== 'cct') {
                continue;
            }
            
            $parent_cct_slug = $parsed['slug'];
            
            // Avoid duplicate hook registration
            if (in_array($parent_cct_slug, $processed_ccts)) {
                continue;
            }
            
            $processed_ccts[] = $parent_cct_slug;
            
            // Register the updated-item hook for this parent CCT
            add_action(
                "jet-engine/custom-content-types/updated-item/{$parent_cct_slug}",
                function($item, $prev_item, $handler) use ($parent_cct_slug) {
                    $this->process_push_data($item, $parent_cct_slug);
                },
                10,
                3
            );
            
            pac_vdm_debug_log("Registered PUSH hook for CCT: {$parent_cct_slug}");
        }
    }
    
    /**
     * Process PULL data - fetch parent data and inject into item before save
     *
     * @param array  $item    Item data being saved
     * @param array  $fields  CCT field definitions
     * @param object $handler Item handler instance
     * @return array Modified item data
     */
    public function process_pull_data($item, $fields, $handler) {
        try {
            $cct_slug = $this->get_cct_slug_from_handler($handler);
            
            if (!$cct_slug) {
                return $item;
            }
            
            // Get mappings for this CCT where direction is 'pull' or 'both'
            $mappings = $this->config_manager->get_mappings_for_cct($cct_slug, true);
            
            if (empty($mappings)) {
                return $item;
            }
            
            pac_vdm_debug_log('Data Flattener PULL processing', [
                'cct_slug' => $cct_slug,
                'mappings_count' => count($mappings),
            ]);
            
            foreach ($mappings as $mapping) {
                // Check direction
                $direction = $mapping['direction'] ?? 'pull';
                if (!in_array($direction, ['pull', 'both'])) {
                    continue;
                }
                
                $item = $this->pull_parent_field($item, $mapping);
            }
            
        } catch (Throwable $e) {
            pac_vdm_log_error('Data Flattener PULL error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
        
        return $item;
    }
    
    /**
     * Pull a single field from parent item
     *
     * @param array $item    Current item data
     * @param array $mapping Mapping configuration
     * @return array Modified item data
     */
    private function pull_parent_field($item, $mapping) {
        $relation_id = $mapping['trigger_relation'];
        $source_field = $mapping['source_field'];
        $dest_field = $mapping['destination_field'];
        
        // Get the item ID
        $item_id = isset($item['_ID']) ? intval($item['_ID']) : 0;
        
        if (!$item_id) {
            pac_vdm_debug_log('PULL: No item ID yet (new item), skipping parent data pull');
            return $item;
        }
        
        // Find parent item via relation
        $parent_item = $this->get_related_parent_item($item_id, $relation_id);
        
        if (!$parent_item) {
            pac_vdm_debug_log('PULL: No parent item found', [
                'item_id' => $item_id,
                'relation_id' => $relation_id,
            ]);
            return $item;
        }
        
        // Get value from parent
        if (!isset($parent_item[$source_field])) {
            pac_vdm_debug_log('PULL: Source field not found on parent', [
                'source_field' => $source_field,
                'parent_fields' => array_keys($parent_item),
            ]);
            return $item;
        }
        
        $parent_value = $parent_item[$source_field];
        
        pac_vdm_debug_log('PULL: Injecting parent data', [
            'source_field' => $source_field,
            'dest_field' => $dest_field,
            'value' => $parent_value,
        ]);
        
        // Inject into current item
        $item[$dest_field] = $parent_value;
        
        return $item;
    }
    
    /**
     * Process PUSH data - update children when parent is saved
     *
     * @param array  $item     Parent item data that was just saved
     * @param string $cct_slug Parent CCT slug
     */
    public function process_push_data($item, $cct_slug) {
        try {
            $item_id = isset($item['_ID']) ? intval($item['_ID']) : 0;
            
            if (!$item_id) {
                return;
            }
            
            pac_vdm_debug_log('Data Flattener PUSH processing', [
                'cct_slug' => $cct_slug,
                'item_id' => $item_id,
            ]);
            
            // Get all mappings and find those where this CCT is the parent
            $all_mappings = $this->config_manager->get_mappings(true);
            
            foreach ($all_mappings as $mapping) {
                // Check direction
                $direction = $mapping['direction'] ?? 'pull';
                if (!in_array($direction, ['push', 'both'])) {
                    continue;
                }
                
                // Get relation and verify this is the parent
                $relation = $this->discovery->get_relation($mapping['trigger_relation']);
                if (!$relation) {
                    continue;
                }
                
                $parsed = $this->discovery->parse_relation_object($relation['parent_object']);
                if ($parsed['type'] !== 'cct' || $parsed['slug'] !== $cct_slug) {
                    continue;
                }
                
                // Push to children
                $this->push_to_children($item, $mapping, $relation);
            }
            
        } catch (Throwable $e) {
            pac_vdm_log_error('Data Flattener PUSH error: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
    
    /**
     * Push field value to all child items
     *
     * @param array $parent_item Parent item data
     * @param array $mapping     Mapping configuration
     * @param array $relation    Relation data
     */
    private function push_to_children($parent_item, $mapping, $relation) {
        $parent_id = isset($parent_item['_ID']) ? intval($parent_item['_ID']) : 0;
        $source_field = $mapping['source_field'];
        $dest_field = $mapping['destination_field'];
        
        if (!$parent_id || !isset($parent_item[$source_field])) {
            return;
        }
        
        $value_to_push = $parent_item[$source_field];
        
        // Get child CCT slug
        $child_parsed = $this->discovery->parse_relation_object($relation['child_object']);
        if ($child_parsed['type'] !== 'cct') {
            pac_vdm_debug_log('PUSH: Child is not a CCT, skipping', $child_parsed);
            return;
        }
        
        $child_cct_slug = $child_parsed['slug'];
        
        // Get all related children
        $child_ids = $this->get_related_child_ids($parent_id, $relation['id']);
        
        if (empty($child_ids)) {
            pac_vdm_debug_log('PUSH: No children found', [
                'parent_id' => $parent_id,
                'relation_id' => $relation['id'],
            ]);
            return;
        }
        
        pac_vdm_debug_log('PUSH: Updating children', [
            'child_count' => count($child_ids),
            'child_cct' => $child_cct_slug,
            'field' => $dest_field,
            'value' => $value_to_push,
        ]);
        
        // Update each child
        foreach ($child_ids as $child_id) {
            $this->update_cct_field($child_cct_slug, $child_id, $dest_field, $value_to_push);
        }
    }
    
    /**
     * Get related parent item via relation
     *
     * @param int $item_id     Child item ID
     * @param int $relation_id Relation ID
     * @return array|null Parent item data or null
     */
    private function get_related_parent_item($item_id, $relation_id) {
        global $wpdb;
        
        // Query relation table
        $table = $wpdb->prefix . 'jet_rel_' . absint($relation_id);
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            pac_vdm_log_warning("Relation table does not exist: {$table}");
            return null;
        }
        
        // Get parent ID (this item is the child)
        $parent_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT parent_object_id FROM {$table} WHERE child_object_id = %d LIMIT 1",
                $item_id
            )
        );
        
        if (!$parent_id) {
            return null;
        }
        
        // Get relation to determine parent CCT
        $relation = $this->discovery->get_relation($relation_id);
        if (!$relation) {
            return null;
        }
        
        $parent_parsed = $this->discovery->parse_relation_object($relation['parent_object']);
        if ($parent_parsed['type'] !== 'cct') {
            pac_vdm_debug_log('Parent is not a CCT', $parent_parsed);
            return null;
        }
        
        // Fetch parent item data
        return $this->get_cct_item($parent_parsed['slug'], $parent_id);
    }
    
    /**
     * Get related child IDs via relation
     *
     * @param int $parent_id   Parent item ID
     * @param int $relation_id Relation ID
     * @return array Array of child IDs
     */
    private function get_related_child_ids($parent_id, $relation_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'jet_rel_' . absint($relation_id);
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }
        
        $child_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT child_object_id FROM {$table} WHERE parent_object_id = %d",
                $parent_id
            )
        );
        
        return $child_ids ?: [];
    }
    
    /**
     * Get a CCT item by slug and ID
     *
     * @param string $cct_slug CCT slug
     * @param int    $item_id  Item ID
     * @return array|null Item data or null
     */
    private function get_cct_item($cct_slug, $item_id) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return null;
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $content_type = $module->manager->get_content_types($cct_slug);
        
        if (!$content_type || !$content_type->db) {
            return null;
        }
        
        return $content_type->db->get_item($item_id);
    }
    
    /**
     * Update a single field on a CCT item
     *
     * @param string $cct_slug CCT slug
     * @param int    $item_id  Item ID
     * @param string $field    Field slug
     * @param mixed  $value    New value
     * @return bool Success
     */
    private function update_cct_field($cct_slug, $item_id, $field, $value) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return false;
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $content_type = $module->manager->get_content_types($cct_slug);
        
        if (!$content_type || !$content_type->db) {
            return false;
        }
        
        $result = $content_type->db->update(
            [$field => $value],
            ['_ID' => $item_id]
        );
        
        pac_vdm_debug_log('CCT field updated', [
            'cct_slug' => $cct_slug,
            'item_id' => $item_id,
            'field' => $field,
            'success' => $result !== false,
        ]);
        
        return $result !== false;
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
        
        if (method_exists($handler, 'get_factory')) {
            $factory = $handler->get_factory();
            if ($factory && method_exists($factory, 'get_arg')) {
                return $factory->get_arg('slug');
            }
        }
        
        if (property_exists($handler, 'factory') && is_object($handler->factory)) {
            if (method_exists($handler->factory, 'get_arg')) {
                return $handler->factory->get_arg('slug');
            }
        }
        
        return null;
    }
}

