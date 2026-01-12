<?php
/**
 * Admin Settings Page Template
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$debug_options = get_option(PAC_VDM_DEBUG_OPTION, [
    'enable_php_logging' => false,
    'enable_js_console' => false,
    'enable_admin_notices' => false,
]);
?>

<div class="wrap pac-vdm-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="notice notice-info">
        <p>
            <strong><?php _e('PAC Vehicle Data Manager', 'pac-vehicle-data-manager'); ?>:</strong>
            <?php _e('Automate data inheritance between related CCTs and enforce data integrity with read-only fields.', 'pac-vehicle-data-manager'); ?>
        </p>
    </div>
    
    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="#setup" class="nav-tab nav-tab-active" data-tab="setup">
            <span class="dashicons dashicons-admin-tools" style="font-size: 16px; line-height: 1.5;"></span>
            <?php _e('Setup Wizard', 'pac-vehicle-data-manager'); ?>
        </a>
        <a href="#mappings" class="nav-tab" data-tab="mappings">
            <?php _e('Field Mappings', 'pac-vehicle-data-manager'); ?>
        </a>
        <a href="#year-expander" class="nav-tab" data-tab="year-expander">
            <?php _e('Year Expander', 'pac-vehicle-data-manager'); ?>
        </a>
        <a href="#debug" class="nav-tab" data-tab="debug">
            <?php _e('Debug', 'pac-vehicle-data-manager'); ?>
        </a>
    </h2>
    
    <!-- Setup Tab -->
    <div id="tab-setup" class="tab-content active">
        <?php include PAC_VDM_PLUGIN_DIR . 'templates/admin/setup-tab.php'; ?>
    </div>
    
    <!-- Mappings Tab -->
    <div id="tab-mappings" class="tab-content">
        <div class="tab-header">
            <div class="description">
                <p><?php _e('Define field mappings to automatically sync data between related CCTs. When a child CCT is saved, values are pulled from its parent.', 'pac-vehicle-data-manager'); ?></p>
            </div>
            <button type="button" id="add-mapping-btn" class="button button-primary">
                <span class="dashicons dashicons-plus-alt"></span>
                <?php _e('Add Mapping', 'pac-vehicle-data-manager'); ?>
            </button>
        </div>
        
        <table class="wp-list-table widefat fixed striped" id="mappings-table">
            <thead>
                <tr>
                    <th style="width: 5%;"><?php _e('#', 'pac-vehicle-data-manager'); ?></th>
                    <th style="width: 15%;"><?php _e('Target CCT', 'pac-vehicle-data-manager'); ?></th>
                    <th style="width: 20%;"><?php _e('Trigger Relation', 'pac-vehicle-data-manager'); ?></th>
                    <th style="width: 15%;"><?php _e('Source Field', 'pac-vehicle-data-manager'); ?></th>
                    <th style="width: 15%;"><?php _e('Dest. Field', 'pac-vehicle-data-manager'); ?></th>
                    <th style="width: 10%;"><?php _e('Direction', 'pac-vehicle-data-manager'); ?></th>
                    <th style="width: 10%;"><?php _e('UI', 'pac-vehicle-data-manager'); ?></th>
                    <th style="width: 10%;"><?php _e('Actions', 'pac-vehicle-data-manager'); ?></th>
                </tr>
            </thead>
            <tbody id="mappings-tbody">
                <!-- Mappings loaded via JS -->
                <tr class="no-mappings-row">
                    <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                        <span class="dashicons dashicons-database-view" style="font-size: 48px; width: 48px; height: 48px; color: #ccc;"></span>
                        <p><?php _e('No field mappings configured yet. Click "Add Mapping" to get started.', 'pac-vehicle-data-manager'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="mappings-actions" style="margin-top: 20px;">
            <button type="button" id="save-mappings-btn" class="button button-primary button-hero">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save All Mappings', 'pac-vehicle-data-manager'); ?>
            </button>
            <span class="spinner" id="save-mappings-spinner" style="float: none; margin-left: 10px;"></span>
            <span id="save-mappings-message" style="margin-left: 10px;"></span>
        </div>
    </div>
    
    <!-- Year Expander Tab -->
    <div id="tab-year-expander" class="tab-content">
        <div class="tab-header">
            <div class="description">
                <p><?php _e('Automatically generate year arrays from start/end year fields. Useful for creating filterable year ranges in JetSmartFilters.', 'pac-vehicle-data-manager'); ?></p>
            </div>
        </div>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="year-expander-enabled"><?php _e('Enable Year Expander', 'pac-vehicle-data-manager'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="year-expander-enabled" name="year_expander_enabled">
                            <?php _e('Enable automatic year range generation', 'pac-vehicle-data-manager'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="year-target-cct"><?php _e('Target CCT', 'pac-vehicle-data-manager'); ?></label>
                    </th>
                    <td>
                        <select id="year-target-cct" name="year_target_cct" class="regular-text">
                            <option value=""><?php _e('-- Select CCT --', 'pac-vehicle-data-manager'); ?></option>
                        </select>
                        <p class="description"><?php _e('Select the CCT that contains year range fields.', 'pac-vehicle-data-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="year-start-field"><?php _e('Start Year Field', 'pac-vehicle-data-manager'); ?></label>
                    </th>
                    <td>
                        <select id="year-start-field" name="year_start_field" class="regular-text" disabled>
                            <option value=""><?php _e('-- Select Field --', 'pac-vehicle-data-manager'); ?></option>
                        </select>
                        <p class="description"><?php _e('Field containing the start year (e.g., year_start).', 'pac-vehicle-data-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="year-end-field"><?php _e('End Year Field', 'pac-vehicle-data-manager'); ?></label>
                    </th>
                    <td>
                        <select id="year-end-field" name="year_end_field" class="regular-text" disabled>
                            <option value=""><?php _e('-- Select Field --', 'pac-vehicle-data-manager'); ?></option>
                        </select>
                        <p class="description"><?php _e('Field containing the end year (e.g., year_end).', 'pac-vehicle-data-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="year-output-field"><?php _e('Output Field', 'pac-vehicle-data-manager'); ?></label>
                    </th>
                    <td>
                        <select id="year-output-field" name="year_output_field" class="regular-text" disabled>
                            <option value=""><?php _e('-- Select Field --', 'pac-vehicle-data-manager'); ?></option>
                        </select>
                        <p class="description"><?php _e('Field where the year array will be stored (as serialized PHP array).', 'pac-vehicle-data-manager'); ?></p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <div class="notice notice-info" style="margin-top: 20px;">
            <p>
                <strong><?php _e('Example:', 'pac-vehicle-data-manager'); ?></strong>
                <?php _e('If start_year = 2018 and end_year = 2023, the output will be: [2018, 2019, 2020, 2021, 2022, 2023]', 'pac-vehicle-data-manager'); ?>
            </p>
        </div>
        
        <p class="submit">
            <button type="button" id="save-year-expander-btn" class="button button-primary">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Save Year Expander Settings', 'pac-vehicle-data-manager'); ?>
            </button>
            <span class="spinner" id="year-expander-spinner" style="float: none; margin-left: 10px;"></span>
            <span id="year-expander-message" style="margin-left: 10px;"></span>
        </p>
    </div>
    
    <!-- Debug Tab -->
    <div id="tab-debug" class="tab-content">
        <?php include PAC_VDM_PLUGIN_DIR . 'templates/admin/debug-tab.php'; ?>
    </div>
</div>

<!-- Mapping Row Template -->
<script type="text/template" id="mapping-row-template">
    <tr class="mapping-row" data-mapping-id="{{id}}">
        <td class="row-number">{{index}}</td>
        <td>
            <select class="target-cct-select" name="mappings[{{index}}][target_cct]">
                <option value=""><?php _e('-- Select --', 'pac-vehicle-data-manager'); ?></option>
            </select>
        </td>
        <td>
            <select class="trigger-relation-select" name="mappings[{{index}}][trigger_relation]" disabled>
                <option value=""><?php _e('-- Select --', 'pac-vehicle-data-manager'); ?></option>
            </select>
        </td>
        <td>
            <select class="source-field-select" name="mappings[{{index}}][source_field]" disabled>
                <option value=""><?php _e('-- Select --', 'pac-vehicle-data-manager'); ?></option>
            </select>
        </td>
        <td>
            <select class="destination-field-select" name="mappings[{{index}}][destination_field]" disabled>
                <option value=""><?php _e('-- Select --', 'pac-vehicle-data-manager'); ?></option>
            </select>
        </td>
        <td>
            <select class="direction-select" name="mappings[{{index}}][direction]">
                <option value="pull"><?php _e('Pull', 'pac-vehicle-data-manager'); ?></option>
                <option value="push"><?php _e('Push', 'pac-vehicle-data-manager'); ?></option>
                <option value="both"><?php _e('Both', 'pac-vehicle-data-manager'); ?></option>
            </select>
        </td>
        <td>
            <select class="ui-behavior-select" name="mappings[{{index}}][ui_behavior]">
                <option value="readonly"><?php _e('Read-Only', 'pac-vehicle-data-manager'); ?></option>
                <option value="hidden"><?php _e('Hidden', 'pac-vehicle-data-manager'); ?></option>
            </select>
        </td>
        <td>
            <button type="button" class="button button-small delete-mapping-btn" title="<?php _e('Delete', 'pac-vehicle-data-manager'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </td>
    </tr>
</script>

