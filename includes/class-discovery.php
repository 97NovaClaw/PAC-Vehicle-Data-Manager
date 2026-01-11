<?php
/**
 * Discovery Engine
 *
 * Discovers CCTs, Fields, and Relations from JetEngine
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Discovery Engine Class
 */
class PAC_VDM_Discovery {
    
    /**
     * Cache for CCTs to avoid repeated API calls
     *
     * @var array|null
     */
    private $ccts_cache = null;
    
    /**
     * Cache for relations
     *
     * @var array|null
     */
    private $relations_cache = null;
    
    /**
     * Get all Custom Content Types from JetEngine
     *
     * @return array Array of CCT objects with slug, name, and fields
     */
    public function get_all_ccts() {
        // Return cache if available
        if ($this->ccts_cache !== null) {
            return $this->ccts_cache;
        }
        
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            pac_vdm_log_error('CCT Module not found');
            return [];
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        
        if (!$module || !isset($module->manager)) {
            pac_vdm_log_error('CCT Manager not available');
            return [];
        }
        
        $raw_ccts = $module->manager->get_content_types();
        
        if (empty($raw_ccts)) {
            pac_vdm_debug_log('No CCTs found');
            return [];
        }
        
        $ccts = [];
        
        foreach ($raw_ccts as $slug => $cct_instance) {
            // Safety check - ensure we have a valid object
            if (!is_object($cct_instance)) {
                pac_vdm_log_warning('Invalid CCT instance', ['slug' => $slug, 'type' => gettype($cct_instance)]);
                continue;
            }
            
            // Use object methods to get data
            $ccts[] = [
                'slug' => $slug,
                'name' => $cct_instance->get_arg('name') ?: $slug,
                'singular_name' => $cct_instance->get_arg('name') ?: $slug,
                'fields' => $this->get_cct_fields_from_instance($cct_instance),
                'type_id' => property_exists($cct_instance, 'type_id') ? $cct_instance->type_id : null,
            ];
        }
        
        // Cache the results
        $this->ccts_cache = $ccts;
        
        pac_vdm_debug_log('Discovered CCTs', ['count' => count($ccts), 'slugs' => wp_list_pluck($ccts, 'slug')]);
        
        return $ccts;
    }
    
    /**
     * Get a single CCT by slug
     *
     * @param string $cct_slug CCT slug
     * @return array|null CCT data or null if not found
     */
    public function get_cct($cct_slug) {
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return null;
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        
        if (!$module || !isset($module->manager)) {
            return null;
        }
        
        $cct_instance = $module->manager->get_content_types($cct_slug);
        
        if (!$cct_instance || !is_object($cct_instance)) {
            return null;
        }
        
        return [
            'slug' => $cct_slug,
            'name' => $cct_instance->get_arg('name') ?: $cct_slug,
            'singular_name' => $cct_instance->get_arg('name') ?: $cct_slug,
            'fields' => $this->get_cct_fields_from_instance($cct_instance),
            'type_id' => property_exists($cct_instance, 'type_id') ? $cct_instance->type_id : null,
        ];
    }
    
    /**
     * Get fields from a CCT Factory instance
     *
     * @param object $cct_instance CCT Factory instance
     * @return array Array of field objects
     */
    private function get_cct_fields_from_instance($cct_instance) {
        $fields = [];
        
        // Try to get detailed field info first
        $args = $cct_instance->get_arg('fields');
        if (!empty($args) && is_array($args)) {
            foreach ($args as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $fields[] = [
                    'name' => isset($field['name']) ? $field['name'] : '',
                    'title' => isset($field['title']) ? $field['title'] : (isset($field['name']) ? $field['name'] : ''),
                    'type' => isset($field['type']) ? $field['type'] : 'text',
                    'options' => isset($field['options']) ? $field['options'] : [],
                ];
            }
            return $fields;
        }
        
        // Fallback: Use get_fields_list method
        if (method_exists($cct_instance, 'get_fields_list')) {
            $field_list = $cct_instance->get_fields_list();
            
            if (!empty($field_list)) {
                foreach ($field_list as $field_name => $field_label) {
                    $fields[] = [
                        'name' => $field_name,
                        'title' => $field_label,
                        'type' => 'text',
                        'options' => [],
                    ];
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Get all JetEngine relations
     *
     * @return array Array of relation objects
     */
    public function get_all_relations() {
        // Return cache if available
        if ($this->relations_cache !== null) {
            return $this->relations_cache;
        }
        
        if (!function_exists('jet_engine') || !jet_engine()->relations) {
            pac_vdm_log_error('JetEngine Relations not available');
            return [];
        }
        
        $raw_relations = jet_engine()->relations->get_active_relations();
        
        if (empty($raw_relations)) {
            pac_vdm_debug_log('No relations found');
            return [];
        }
        
        $relations = [];
        
        foreach ($raw_relations as $relation_id => $relation_obj) {
            if (!is_object($relation_obj) || !method_exists($relation_obj, 'get_args')) {
                continue;
            }
            
            $args = $relation_obj->get_args();
            
            // Generate a readable name if not set
            $name = '';
            if (!empty($args['name'])) {
                $name = $args['name'];
            } elseif (!empty($args['labels']['name'])) {
                $name = $args['labels']['name'];
            } else {
                $parent_name = $this->get_relation_object_name($args['parent_object']);
                $child_name = $this->get_relation_object_name($args['child_object']);
                $name = $parent_name . ' → ' . $child_name;
            }
            
            $relations[] = [
                'id' => $relation_id,
                'name' => $name,
                'parent_object' => isset($args['parent_object']) ? $args['parent_object'] : '',
                'child_object' => isset($args['child_object']) ? $args['child_object'] : '',
                'type' => isset($args['type']) ? $args['type'] : 'one_to_many',
                'parent_rel' => isset($args['parent_rel']) ? $args['parent_rel'] : null,
                'is_hierarchy' => !empty($args['parent_rel']),
            ];
        }
        
        // Cache the results
        $this->relations_cache = $relations;
        
        pac_vdm_debug_log('Discovered relations', [
            'count' => count($relations),
            'relation_names' => wp_list_pluck($relations, 'name')
        ]);
        
        return $relations;
    }
    
    /**
     * Clear caches
     */
    public function clear_cache() {
        $this->ccts_cache = null;
        $this->relations_cache = null;
    }
    
    /**
     * Get relations where a CCT is the CHILD (for pulling parent data)
     *
     * @param string $cct_slug CCT slug
     * @return array Array of relations where this CCT is the child
     */
    public function get_relations_as_child($cct_slug) {
        $all_relations = $this->get_all_relations();
        $child_relations = [];
        
        foreach ($all_relations as $relation) {
            if ($this->is_cct_in_relation($cct_slug, $relation['child_object'])) {
                $relation['cct_position'] = 'child';
                $child_relations[] = $relation;
            }
        }
        
        return $child_relations;
    }
    
    /**
     * Get relations where a CCT is the PARENT (for pushing to children)
     *
     * @param string $cct_slug CCT slug
     * @return array Array of relations where this CCT is the parent
     */
    public function get_relations_as_parent($cct_slug) {
        $all_relations = $this->get_all_relations();
        $parent_relations = [];
        
        foreach ($all_relations as $relation) {
            if ($this->is_cct_in_relation($cct_slug, $relation['parent_object'])) {
                $relation['cct_position'] = 'parent';
                $parent_relations[] = $relation;
            }
        }
        
        return $parent_relations;
    }
    
    /**
     * Get relations for a specific CCT (either as parent or child)
     *
     * @param string $cct_slug CCT slug
     * @param string $position 'parent', 'child', or 'both'
     * @return array Array of relations
     */
    public function get_relations_for_cct($cct_slug, $position = 'both') {
        switch ($position) {
            case 'parent':
                return $this->get_relations_as_parent($cct_slug);
            case 'child':
                return $this->get_relations_as_child($cct_slug);
            case 'both':
            default:
                return array_merge(
                    $this->get_relations_as_parent($cct_slug),
                    $this->get_relations_as_child($cct_slug)
                );
        }
    }
    
    /**
     * Check if a CCT slug matches a relation object string
     *
     * @param string $cct_slug      CCT slug to check
     * @param string $relation_obj  Relation object string from JetEngine
     * @return bool
     */
    private function is_cct_in_relation($cct_slug, $relation_obj) {
        if (!is_string($relation_obj)) {
            return false;
        }
        
        // Handle "cct::slug" format
        if (strpos($relation_obj, 'cct::') === 0) {
            $rel_cct_slug = str_replace('cct::', '', $relation_obj);
            return $rel_cct_slug === $cct_slug;
        }
        
        // Handle "terms::" and "posts::" - these are NOT CCT relations
        if (strpos($relation_obj, 'terms::') === 0 || strpos($relation_obj, 'posts::') === 0) {
            return false;
        }
        
        // Direct match (legacy format without prefix)
        return $relation_obj === $cct_slug;
    }
    
    /**
     * Parse relation object string into type and slug
     *
     * @param string $relation_obj Relation object string
     * @return array ['type' => 'cct|terms|posts', 'slug' => 'slug_name']
     */
    public function parse_relation_object($relation_obj) {
        if (!is_string($relation_obj)) {
            return ['type' => 'unknown', 'slug' => ''];
        }
        
        if (strpos($relation_obj, '::') !== false) {
            list($type, $slug) = explode('::', $relation_obj, 2);
            return [
                'type' => $type,
                'slug' => $slug,
            ];
        }
        
        // No delimiter - assume legacy CCT format
        return [
            'type' => 'cct',
            'slug' => $relation_obj,
        ];
    }
    
    /**
     * Get human-readable name for relation object
     *
     * @param string $relation_obj Relation object string
     * @return string Readable name
     */
    public function get_relation_object_name($relation_obj) {
        $parsed = $this->parse_relation_object($relation_obj);
        
        switch ($parsed['type']) {
            case 'cct':
                $cct = $this->get_cct($parsed['slug']);
                return $cct ? $cct['name'] : ucfirst(str_replace('_', ' ', $parsed['slug']));
                
            case 'terms':
                $taxonomy = get_taxonomy($parsed['slug']);
                return $taxonomy ? $taxonomy->label : ucfirst(str_replace('_', ' ', $parsed['slug']));
                
            case 'posts':
                $post_type = get_post_type_object($parsed['slug']);
                return $post_type ? $post_type->label : ucfirst(str_replace('_', ' ', $parsed['slug']));
                
            default:
                return ucfirst(str_replace('_', ' ', $parsed['slug']));
        }
    }
    
    /**
     * Get a single relation by ID
     *
     * @param int $relation_id Relation ID
     * @return array|null Relation data or null
     */
    public function get_relation($relation_id) {
        $all_relations = $this->get_all_relations();
        
        foreach ($all_relations as $relation) {
            if ($relation['id'] == $relation_id) {
                return $relation;
            }
        }
        
        return null;
    }
    
    /**
     * Get JetEngine relation object by ID
     *
     * @param int $relation_id Relation ID
     * @return object|null JetEngine relation object
     */
    public function get_jet_relation_object($relation_id) {
        if (!function_exists('jet_engine') || !jet_engine()->relations) {
            return null;
        }
        
        $relations = jet_engine()->relations->get_active_relations();
        
        return isset($relations[$relation_id]) ? $relations[$relation_id] : null;
    }
}

