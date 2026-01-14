<?php
/**
 * Bulk Sync Utility
 *
 * Retroactively syncs existing CCT items after plugin installation
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Bulk Sync Class
 */
class PAC_VDM_Bulk_Sync {
    
    /**
     * Config Manager instance
     *
     * @var PAC_VDM_Config_Manager
     */
    private $config_manager;
    
    /**
     * Discovery instance
     *
     * @var PAC_VDM_Discovery
     */
    private $discovery;
    
    /**
     * Batch size for processing
     *
     * @var int
     */
    private $batch_size = 20;
    
    /**
     * Constructor
     *
     * @param PAC_VDM_Config_Manager $config_manager Config manager instance
     * @param PAC_VDM_Discovery      $discovery      Discovery instance
     */
    public function __construct($config_manager, $discovery) {
        $this->config_manager = $config_manager;
        $this->discovery = $discovery;
    }
    
    /**
     * Get sync status for all mapped CCTs
     *
     * @return array Status array
     */
    public function get_sync_status() {
        $mapped_ccts = $this->config_manager->get_mapped_ccts();
        $status = [];
        
        foreach ($mapped_ccts as $cct_slug) {
            $count = $this->get_cct_item_count($cct_slug);
            $cct = $this->discovery->get_cct($cct_slug);
            
            $status[$cct_slug] = [
                'slug' => $cct_slug,
                'name' => $cct ? $cct['name'] : ucfirst(str_replace('_', ' ', $cct_slug)),
                'item_count' => $count,
                'has_items' => $count > 0,
            ];
        }
        
        return $status;
    }
    
    /**
     * Get item count for a CCT
     *
     * @param string $cct_slug CCT slug
     * @return int Item count
     */
    public function get_cct_item_count($cct_slug) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'jet_cct_' . $cct_slug;
        
        // Check if table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        ));
        
        if (!$table_exists) {
            return 0;
        }
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
        
        return (int) $count;
    }
    
    /**
     * Sync all items in a CCT
     *
     * @param string $cct_slug CCT slug
     * @param int    $offset   Offset for batch processing
     * @param int    $limit    Limit for batch processing
     * @return array Results array
     */
    public function sync_cct_batch($cct_slug, $offset = 0, $limit = null) {
        global $wpdb;
        
        if ($limit === null) {
            $limit = $this->batch_size;
        }
        
        pac_vdm_debug_log('Starting bulk sync batch', [
            'cct_slug' => $cct_slug,
            'offset' => $offset,
            'limit' => $limit,
        ], 'critical');
        
        // Get items from database
        $table_name = $wpdb->prefix . 'jet_cct_' . $cct_slug;
        
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table_name}` LIMIT %d OFFSET %d",
            $limit,
            $offset
        ), ARRAY_A);
        
        if (empty($items)) {
            pac_vdm_debug_log('No items found for bulk sync', ['cct_slug' => $cct_slug]);
            return [
                'processed' => 0,
                'success' => 0,
                'errors' => 0,
                'has_more' => false,
            ];
        }
        
        $processed = 0;
        $success = 0;
        $errors = 0;
        
        // Get CCT handler
        if (!class_exists('\\Jet_Engine\\Modules\\Custom_Content_Types\\Module')) {
            return [
                'processed' => 0,
                'success' => 0,
                'errors' => 1,
                'error_message' => 'JetEngine CCT module not found',
                'has_more' => false,
            ];
        }
        
        $module = \Jet_Engine\Modules\Custom_Content_Types\Module::instance();
        $content_type = $module->manager->get_content_types($cct_slug);
        
        if (!$content_type) {
            return [
                'processed' => 0,
                'success' => 0,
                'errors' => 1,
                'error_message' => 'CCT not found: ' . $cct_slug,
                'has_more' => false,
            ];
        }
        
        $fields = $content_type->get_arg('fields') ?: [];
        
        foreach ($items as $item) {
            try {
                $item_id = $item['_ID'];
                
                pac_vdm_debug_log('Processing item for bulk sync', [
                    'item_id' => $item_id,
                    'cct_slug' => $cct_slug,
                ]);
                
                // Create a mock handler object
                $handler = new stdClass();
                $handler->type_slug = $cct_slug;
                $handler->content_type = $content_type;
                
                // Apply the item-to-update filter (this triggers all our modules)
                $updated_item = apply_filters(
                    'jet-engine/custom-content-types/item-to-update',
                    $item,
                    $fields,
                    $handler
                );
                
                // Build UPDATE query
                $update_data = [];
                $where = ['_ID' => $item_id];
                
                foreach ($updated_item as $key => $value) {
                    if ($key !== '_ID') {
                        // Handle serialized data
                        if (is_array($value)) {
                            $update_data[$key] = maybe_serialize($value);
                        } else {
                            $update_data[$key] = $value;
                        }
                    }
                }
                
                // Update in database
                $result = $wpdb->update(
                    $table_name,
                    $update_data,
                    $where
                );
                
                if ($result !== false) {
                    $success++;
                    pac_vdm_debug_log('Item synced successfully', ['item_id' => $item_id]);
                } else {
                    $errors++;
                    pac_vdm_debug_log('Failed to update item', [
                        'item_id' => $item_id,
                        'error' => $wpdb->last_error,
                    ], 'error');
                }
                
                $processed++;
                
            } catch (\Exception $e) {
                $errors++;
                pac_vdm_debug_log('Exception during bulk sync', [
                    'item_id' => $item['_ID'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ], 'error');
            }
        }
        
        $total_items = $this->get_cct_item_count($cct_slug);
        $has_more = ($offset + $processed) < $total_items;
        
        pac_vdm_debug_log('Bulk sync batch complete', [
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'has_more' => $has_more,
            'next_offset' => $offset + $processed,
        ], 'critical');
        
        return [
            'processed' => $processed,
            'success' => $success,
            'errors' => $errors,
            'has_more' => $has_more,
            'next_offset' => $offset + $processed,
            'total_items' => $total_items,
        ];
    }
    
    /**
     * Get all CCTs that need syncing
     *
     * @return array CCTs with item counts
     */
    public function get_syncable_ccts() {
        $sync_status = $this->get_sync_status();
        
        return array_filter($sync_status, function($cct_status) {
            return $cct_status['has_items'];
        });
    }
}

