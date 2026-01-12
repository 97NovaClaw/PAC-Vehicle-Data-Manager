<?php
/**
 * Setup Tab Template
 *
 * CCT Role Mapping and Auto-Creation Wizard
 *
 * @package PAC_Vehicle_Data_Manager
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

$cct_builder = new PAC_VDM_CCT_Builder();
$cct_roles = $cct_builder->get_cct_roles();
$mapping_status = $cct_builder->get_mapping_status();
$relations_status = $cct_builder->get_relations_status();
$discovery = new PAC_VDM_Discovery();
$available_ccts = $discovery->get_all_ccts();
?>

<div class="tab-header">
    <div class="description">
        <p><?php _e('Map your existing CCTs to the Vehicle Data Manager roles, or let the plugin create them for you with the essential fields.', 'pac-vehicle-data-manager'); ?></p>
    </div>
</div>

<h3><?php _e('Step 1: CCT Role Mapping', 'pac-vehicle-data-manager'); ?></h3>
<div class="notice notice-info inline" style="margin: 15px 0;">
    <p>
        <strong><?php _e('How this works:', 'pac-vehicle-data-manager'); ?></strong><br>
        <?php _e('1. Map each CCT to its role (Makes, Models, Vehicle Configs, Service Guides)', 'pac-vehicle-data-manager'); ?><br>
        <?php _e('2. Click "Edit CCT" to open JetEngine and add the required fields shown below', 'pac-vehicle-data-manager'); ?><br>
        <?php _e('3. Once all fields exist (green checkmarks), proceed to create relations', 'pac-vehicle-data-manager'); ?>
    </p>
</div>

<table class="wp-list-table widefat fixed striped" id="cct-mapping-table">
    <thead>
        <tr>
            <th style="width: 15%;"><?php _e('Role', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 25%;"><?php _e('Description', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 20%;"><?php _e('Mapped CCT', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 25%;"><?php _e('Fields Status', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 15%;"><?php _e('Actions', 'pac-vehicle-data-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($cct_roles as $role_key => $role): ?>
            <?php $status = $mapping_status[$role_key]; ?>
            <tr data-role="<?php echo esc_attr($role_key); ?>">
                <td>
                    <strong><?php echo esc_html($role['name']); ?></strong>
                    <?php if ($status['is_complete']): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;" title="<?php _e('Complete', 'pac-vehicle-data-manager'); ?>"></span>
                    <?php elseif ($status['cct_exists']): ?>
                        <span class="dashicons dashicons-warning" style="color: #f0b849;" title="<?php _e('Missing fields', 'pac-vehicle-data-manager'); ?>"></span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="description"><?php echo esc_html($role['description']); ?></span>
                </td>
                <td>
                    <select class="cct-role-select" data-role="<?php echo esc_attr($role_key); ?>" name="cct_mapping[<?php echo esc_attr($role_key); ?>]">
                        <option value=""><?php _e('-- Not Mapped --', 'pac-vehicle-data-manager'); ?></option>
                        <option value="__create_new__" style="font-weight: bold; color: #2271b1;">
                            ➕ <?php _e('Create New CCT', 'pac-vehicle-data-manager'); ?>
                        </option>
                        <?php foreach ($available_ccts as $cct): ?>
                            <option value="<?php echo esc_attr($cct['slug']); ?>" 
                                    <?php selected($status['mapped_slug'], $cct['slug']); ?>>
                                <?php echo esc_html($cct['name']); ?> (<?php echo esc_html($cct['slug']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="fields-status">
                    <?php if ($status['cct_exists'] && !empty($status['fields'])): ?>
                        <div class="field-badges">
                            <?php 
                            $required_count = 0;
                            $existing_count = 0;
                            foreach ($status['fields'] as $field_name => $field_status): 
                                if ($field_status['required']) $required_count++;
                                if ($field_status['exists']) $existing_count++;
                                
                                $badge_class = $field_status['exists'] ? 'field-exists' : 'field-missing';
                                $icon = $field_status['exists'] ? 'yes' : 'no';
                                $required_marker = $field_status['required'] ? ' *' : '';
                                $tooltip = $field_status['title'];
                                if ($field_status['required']) {
                                    $tooltip .= ' (REQUIRED)';
                                }
                                ?>
                                <span class="field-badge <?php echo $badge_class; ?>" 
                                      title="<?php echo esc_attr($tooltip); ?>">
                                    <span class="dashicons dashicons-<?php echo $icon; ?>"></span>
                                    <?php echo esc_html($field_name . $required_marker); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <small class="field-count" style="display: block; margin-top: 5px; color: #646970;">
                            <?php printf(
                                __('%d of %d required fields exist', 'pac-vehicle-data-manager'),
                                $existing_count,
                                $required_count
                            ); ?>
                        </small>
                    <?php elseif (!$status['cct_exists'] && !empty($status['mapped_slug'])): ?>
                        <span class="status-error">
                            <span class="dashicons dashicons-warning"></span>
                            <?php _e('CCT not found', 'pac-vehicle-data-manager'); ?>
                        </span>
                    <?php else: ?>
                        <span class="status-pending"><?php _e('Select CCT first', 'pac-vehicle-data-manager'); ?></span>
                    <?php endif; ?>
                </td>
                <td class="actions-cell">
                    <?php if ($status['cct_exists'] && !$status['is_complete']): ?>
                        <a href="<?php echo admin_url('admin.php?page=jet-engine-cpt&cct_id=' . $status['mapped_slug']); ?>" 
                           class="button button-small" 
                           target="_blank">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php _e('Add in JetEngine', 'pac-vehicle-data-manager'); ?>
                        </a>
                    <?php elseif ($status['is_complete']): ?>
                        <span class="status-complete">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Complete', 'pac-vehicle-data-manager'); ?>
                        </span>
                    <?php elseif ($status['cct_exists']): ?>
                        <a href="<?php echo admin_url('admin.php?page=jet-engine-cpt&cct_id=' . $status['mapped_slug']); ?>" 
                           class="button button-small" 
                           target="_blank">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php _e('Edit CCT', 'pac-vehicle-data-manager'); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="mapping-actions" style="margin-top: 15px;">
    <button type="button" id="save-cct-mapping-btn" class="button button-primary">
        <span class="dashicons dashicons-saved"></span>
        <?php _e('Save CCT Mapping', 'pac-vehicle-data-manager'); ?>
    </button>
    <span class="spinner" id="cct-mapping-spinner" style="float: none; margin-left: 10px;"></span>
    <span id="cct-mapping-message" style="margin-left: 10px;"></span>
</div>

<hr style="margin: 30px 0;">

<h3><?php _e('Step 2: Relations Setup', 'pac-vehicle-data-manager'); ?></h3>
<p class="description"><?php _e('These relations connect your CCTs in the proper hierarchy. The plugin will create them automatically with database tables enabled.', 'pac-vehicle-data-manager'); ?></p>

<table class="wp-list-table widefat fixed striped" id="relations-status-table">
    <thead>
        <tr>
            <th style="width: 25%;"><?php _e('Relation', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 20%;"><?php _e('Parent CCT', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 20%;"><?php _e('Child CCT', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 15%;"><?php _e('Status', 'pac-vehicle-data-manager'); ?></th>
            <th style="width: 20%;"><?php _e('Actions', 'pac-vehicle-data-manager'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($relations_status as $rel_key => $rel_status): ?>
            <tr data-relation="<?php echo esc_attr($rel_key); ?>">
                <td><strong><?php echo esc_html($rel_status['name']); ?></strong></td>
                <td>
                    <?php if (!empty($rel_status['parent_slug'])): ?>
                        <code><?php echo esc_html($rel_status['parent_slug']); ?></code>
                    <?php else: ?>
                        <span class="status-pending"><?php _e('Not mapped', 'pac-vehicle-data-manager'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($rel_status['child_slug'])): ?>
                        <code><?php echo esc_html($rel_status['child_slug']); ?></code>
                    <?php else: ?>
                        <span class="status-pending"><?php _e('Not mapped', 'pac-vehicle-data-manager'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rel_status['is_complete']): ?>
                        <span class="status-complete">
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                            <?php _e('Active', 'pac-vehicle-data-manager'); ?>
                            <?php if ($rel_status['has_db_table']): ?>
                                <br><small style="color: #46b450;"><?php _e('DB Table ✓', 'pac-vehicle-data-manager'); ?></small>
                            <?php endif; ?>
                        </span>
                    <?php elseif ($rel_status['relation_exists'] && !$rel_status['has_db_table']): ?>
                        <span class="status-warning">
                            <span class="dashicons dashicons-warning" style="color: #f0b849;"></span>
                            <?php _e('No DB Table', 'pac-vehicle-data-manager'); ?>
                        </span>
                    <?php elseif ($rel_status['is_ready']): ?>
                        <span class="status-pending">
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e('Ready to create', 'pac-vehicle-data-manager'); ?>
                        </span>
                    <?php else: ?>
                        <span class="status-pending"><?php _e('Map CCTs first', 'pac-vehicle-data-manager'); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($rel_status['is_ready'] && !$rel_status['is_complete']): ?>
                        <button type="button" class="button button-small create-relation-btn"
                                data-relation="<?php echo esc_attr($rel_key); ?>"
                                data-parent="<?php echo esc_attr($rel_status['parent_slug']); ?>"
                                data-child="<?php echo esc_attr($rel_status['child_slug']); ?>"
                                data-name="<?php echo esc_attr($rel_status['name']); ?>">
                            <span class="dashicons dashicons-admin-links"></span>
                            <?php _e('Create', 'pac-vehicle-data-manager'); ?>
                        </button>
                    <?php elseif ($rel_status['is_complete']): ?>
                        <span class="relation-id">ID: <?php echo esc_html($rel_status['relation_id']); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="relations-actions" style="margin-top: 15px;">
    <button type="button" id="create-all-relations-btn" class="button button-secondary">
        <span class="dashicons dashicons-admin-links"></span>
        <?php _e('Create All Missing Relations', 'pac-vehicle-data-manager'); ?>
    </button>
    <span class="spinner" id="relations-spinner" style="float: none; margin-left: 10px;"></span>
    <span id="relations-message" style="margin-left: 10px;"></span>
</div>

<hr style="margin: 30px 0;">

<h3><?php _e('Step 3: Auto-Configure Field Mappings', 'pac-vehicle-data-manager'); ?></h3>
<p class="description"><?php _e('Once CCTs and Relations are set up, click below to automatically create all field sync mappings and configure the Year Expander.', 'pac-vehicle-data-manager'); ?></p>

<div class="auto-config-box" style="background: #f6f7f7; padding: 20px; border-radius: 4px; margin-top: 15px;">
    <p><strong><?php _e('This will automatically:', 'pac-vehicle-data-manager'); ?></strong></p>
    <ul style="list-style: disc; margin-left: 25px;">
        <li><?php _e('Create field sync mappings for make_name, model_name, generation_code, powertrain_engine, year_range_list', 'pac-vehicle-data-manager'); ?></li>
        <li><?php _e('Configure Year Expander for Vehicle Configs CCT', 'pac-vehicle-data-manager'); ?></li>
        <li><?php _e('Set all synced fields as read-only in the UI', 'pac-vehicle-data-manager'); ?></li>
    </ul>
    
    <button type="button" id="auto-create-mappings-btn" class="button button-primary button-hero" style="margin-top: 15px;">
        <span class="dashicons dashicons-controls-repeat"></span>
        <?php _e('Auto-Configure All Mappings', 'pac-vehicle-data-manager'); ?>
    </button>
    <span class="spinner" id="auto-mappings-spinner" style="float: none; margin-left: 10px;"></span>
    <span id="auto-mappings-message" style="margin-left: 10px;"></span>
</div>

<style>
/* Setup Tab Styles */
.field-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
}

.field-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
}

.field-badge .dashicons {
    width: 14px;
    height: 14px;
    font-size: 14px;
}

.field-badge.field-exists {
    background: #d7f1e3;
    color: #1e8656;
}

.field-badge.field-missing {
    background: #fcf0f1;
    color: #d63638;
}

.status-pending {
    color: #646970;
    font-style: italic;
}

.status-complete {
    color: #1e8656;
    font-weight: 500;
}

.status-error {
    color: #d63638;
}

.status-warning {
    color: #996800;
}

.cct-role-select {
    width: 100%;
    max-width: 250px;
}

.relation-id {
    color: #646970;
    font-size: 12px;
}

#cct-mapping-table td,
#relations-status-table td {
    vertical-align: middle;
}

.actions-cell .button .dashicons {
    margin-right: 3px;
}

.auto-config-box {
    border-left: 4px solid #2271b1;
}
</style>

