/**
 * Admin JavaScript
 *
 * Handles admin page interactions for PAC Vehicle Data Manager
 *
 * @package PAC_Vehicle_Data_Manager
 */

(function($) {
    'use strict';
    
    const PacVdmAdmin = {
        
        config: null,
        mappingIndex: 0,
        
        /**
         * Initialize
         */
        init: function() {
            if (typeof pacVdmAdmin === 'undefined') {
                console.error('[PAC VDM Admin] Configuration not found');
                return;
            }
            
            this.config = pacVdmAdmin;
            
            console.log('[PAC VDM Admin] Initializing...', this.config);
            
            this.bindEvents();
            this.initTabs();
            this.loadExistingMappings();
            this.loadYearExpanderSettings();
            
            console.log('[PAC VDM Admin] Initialization complete');
        },
        
        /**
         * Bind event handlers
         * 
         * FIXED: Using delegated event handlers for ALL buttons to ensure they work
         * even when tab content is loaded dynamically or hidden initially
         */
        bindEvents: function() {
            // Tab switching
            $(document).on('click', '.pac-vdm-settings .nav-tab', this.handleTabClick.bind(this));
            
            // Setup Wizard - DELEGATED event handlers (content may not be in DOM yet)
            $(document).on('click', '#save-cct-mapping-btn', this.saveCctMapping.bind(this));
            $(document).on('click', '#refresh-field-status-btn', this.refreshFieldStatus.bind(this));
            $(document).on('click', '.create-relation-btn', this.createRelation.bind(this));
            $(document).on('click', '#create-all-relations-btn', this.createAllRelations.bind(this));
            $(document).on('click', '#auto-create-mappings-btn', this.autoCreateMappings.bind(this));
            $(document).on('click', '.bulk-sync-btn', this.startBulkSync.bind(this));
            
            // Mappings - DELEGATED
            $(document).on('click', '#add-mapping-btn', this.addMappingRow.bind(this));
            $(document).on('click', '#save-mappings-btn', this.saveMappings.bind(this));
            $(document).on('click', '.delete-mapping-btn', this.deleteMappingRow.bind(this));
            $(document).on('change', '.target-cct-select', this.handleTargetCctChange.bind(this));
            $(document).on('change', '.trigger-relation-select', this.handleRelationChange.bind(this));
            
            // Year Expander - DELEGATED
            $(document).on('change', '#year-target-cct', this.handleYearCctChange.bind(this));
            $(document).on('click', '#save-year-expander-btn', this.saveYearExpander.bind(this));
            
            // Debug - DELEGATED
            $(document).on('click', '#save-debug-settings-btn', this.saveDebugSettings.bind(this));
            $(document).on('click', '#view-log-btn, #refresh-log-btn', this.viewLog.bind(this));
            $(document).on('click', '#clear-log-btn', this.clearLog.bind(this));
        },
        
        /**
         * Initialize tabs
         */
        initTabs: function() {
            // Load from hash if present
            if (window.location.hash) {
                const hash = window.location.hash.substring(1);
                const $tab = $(`.nav-tab[data-tab="${hash}"]`);
                if ($tab.length) {
                    $tab.trigger('click');
                }
            }
        },
        
        /**
         * Handle tab click
         */
        handleTabClick: function(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const tabId = $tab.data('tab');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show corresponding content
            $('.tab-content').removeClass('active');
            $(`#tab-${tabId}`).addClass('active');
            
            // Update URL hash
            window.location.hash = tabId;
        },
        
        /**
         * Load existing mappings from config
         */
        loadExistingMappings: function() {
            const mappings = this.config.mappings || [];
            
            if (mappings.length === 0) {
                return;
            }
            
            // Hide "no mappings" message
            $('.no-mappings-row').hide();
            
            // Add each mapping
            mappings.forEach((mapping, index) => {
                this.addMappingRow(null, mapping);
            });
        },
        
        /**
         * Add a mapping row
         */
        addMappingRow: function(e, existingMapping) {
            if (e) e.preventDefault();
            
            // Hide "no mappings" message
            $('.no-mappings-row').hide();
            
            this.mappingIndex++;
            const index = this.mappingIndex;
            const id = existingMapping ? existingMapping.id : 'map_' + this.generateUUID();
            
            // Get template and replace placeholders
            let template = $('#mapping-row-template').html();
            template = template.replace(/\{\{index\}\}/g, index);
            template = template.replace(/\{\{id\}\}/g, id);
            
            const $row = $(template);
            
            // Populate CCT dropdown
            const $cctSelect = $row.find('.target-cct-select');
            this.populateCctDropdown($cctSelect, existingMapping ? existingMapping.target_cct : '');
            
            // If existing mapping, load the cascade
            if (existingMapping) {
                $row.attr('data-mapping-id', existingMapping.id);
                
                // Set direction and UI behavior
                $row.find('.direction-select').val(existingMapping.direction || 'pull');
                $row.find('.ui-behavior-select').val(existingMapping.ui_behavior || 'readonly');
                
                // Load relations for this CCT, then set values
                this.loadRelationsForCct(existingMapping.target_cct, $row, existingMapping);
            }
            
            $('#mappings-tbody').append($row);
        },
        
        /**
         * Delete a mapping row
         */
        deleteMappingRow: function(e) {
            e.preventDefault();
            
            if (!confirm(this.config.i18n.confirm_delete)) {
                return;
            }
            
            const $row = $(e.currentTarget).closest('.mapping-row');
            $row.fadeOut(200, function() {
                $(this).remove();
                
                // Show "no mappings" if table is empty
                if ($('#mappings-tbody .mapping-row').length === 0) {
                    $('.no-mappings-row').show();
                }
            });
        },
        
        /**
         * Handle target CCT change
         */
        handleTargetCctChange: function(e) {
            const $select = $(e.currentTarget);
            const $row = $select.closest('.mapping-row');
            const cctSlug = $select.val();
            
            // Reset dependent fields
            $row.find('.trigger-relation-select').prop('disabled', true).html(
                `<option value="">${this.config.i18n.loading}</option>`
            );
            $row.find('.source-field-select, .destination-field-select').prop('disabled', true).html(
                `<option value="">${this.config.i18n.select_field}</option>`
            );
            
            if (!cctSlug) {
                $row.find('.trigger-relation-select').html(
                    `<option value="">${this.config.i18n.select_relation}</option>`
                );
                return;
            }
            
            this.loadRelationsForCct(cctSlug, $row);
        },
        
        /**
         * Load relations for a CCT via AJAX
         */
        loadRelationsForCct: function(cctSlug, $row, existingMapping) {
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_get_cct_relations',
                    nonce: this.config.nonce,
                    cct_slug: cctSlug
                },
                success: (response) => {
                    if (response.success) {
                        const $relationSelect = $row.find('.trigger-relation-select');
                        $relationSelect.html(`<option value="">${this.config.i18n.select_relation}</option>`);
                        
                        if (response.data.relations.length === 0) {
                            $relationSelect.html(`<option value="">${this.config.i18n.no_relations}</option>`);
                        } else {
                            response.data.relations.forEach(relation => {
                                const selected = existingMapping && existingMapping.trigger_relation == relation.id ? 'selected' : '';
                                $relationSelect.append(
                                    `<option value="${relation.id}" ${selected}>${relation.name}</option>`
                                );
                            });
                            $relationSelect.prop('disabled', false);
                            
                            // If existing mapping, trigger relation change to load fields
                            if (existingMapping && existingMapping.trigger_relation) {
                                $relationSelect.trigger('change', [existingMapping]);
                            }
                        }
                        
                        // Also load destination fields (for target CCT)
                        this.loadDestinationFields(cctSlug, $row, existingMapping);
                    }
                }
            });
        },
        
        /**
         * Handle relation change - load source (parent) fields
         */
        handleRelationChange: function(e, existingMapping) {
            const $select = $(e.currentTarget);
            const $row = $select.closest('.mapping-row');
            const relationId = $select.val();
            
            if (!relationId) {
                $row.find('.source-field-select').prop('disabled', true).html(
                    `<option value="">${this.config.i18n.select_field}</option>`
                );
                return;
            }
            
            // Load parent CCT fields
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_get_parent_fields',
                    nonce: this.config.nonce,
                    relation_id: relationId
                },
                success: (response) => {
                    if (response.success) {
                        const $sourceSelect = $row.find('.source-field-select');
                        $sourceSelect.html(`<option value="">${this.config.i18n.select_field}</option>`);
                        
                        response.data.fields.forEach(field => {
                            const selected = existingMapping && existingMapping.source_field === field.name ? 'selected' : '';
                            $sourceSelect.append(
                                `<option value="${field.name}" ${selected}>${field.title} (${field.name})</option>`
                            );
                        });
                        
                        $sourceSelect.prop('disabled', false);
                    }
                }
            });
        },
        
        /**
         * Load destination fields for target CCT
         */
        loadDestinationFields: function(cctSlug, $row, existingMapping) {
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_get_cct_fields',
                    nonce: this.config.nonce,
                    cct_slug: cctSlug
                },
                success: (response) => {
                    if (response.success) {
                        const $destSelect = $row.find('.destination-field-select');
                        $destSelect.html(`<option value="">${this.config.i18n.select_field}</option>`);
                        
                        response.data.fields.forEach(field => {
                            const selected = existingMapping && existingMapping.destination_field === field.name ? 'selected' : '';
                            $destSelect.append(
                                `<option value="${field.name}" ${selected}>${field.title} (${field.name})</option>`
                            );
                        });
                        
                        $destSelect.prop('disabled', false);
                    }
                }
            });
        },
        
        /**
         * Populate CCT dropdown
         */
        populateCctDropdown: function($select, selectedValue) {
            $select.html(`<option value="">${this.config.i18n.select_cct}</option>`);
            
            this.config.ccts.forEach(cct => {
                const selected = selectedValue === cct.slug ? 'selected' : '';
                $select.append(
                    `<option value="${cct.slug}" ${selected}>${cct.name} (${cct.slug})</option>`
                );
            });
        },
        
        /**
         * Save all mappings
         */
        saveMappings: function(e) {
            e.preventDefault();
            
            const $btn = $('#save-mappings-btn');
            const $spinner = $('#save-mappings-spinner');
            const $message = $('#save-mappings-message');
            
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            // Gather mappings data
            const mappings = [];
            
            $('#mappings-tbody .mapping-row').each(function() {
                const $row = $(this);
                
                mappings.push({
                    id: $row.attr('data-mapping-id'),
                    target_cct: $row.find('.target-cct-select').val(),
                    trigger_relation: $row.find('.trigger-relation-select').val(),
                    source_field: $row.find('.source-field-select').val(),
                    destination_field: $row.find('.destination-field-select').val(),
                    direction: $row.find('.direction-select').val(),
                    ui_behavior: $row.find('.ui-behavior-select').val(),
                    enabled: true
                });
            });
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_save_mappings',
                    nonce: this.config.nonce,
                    mappings: mappings
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        // Update local config
                        this.config.mappings = response.data.mappings;
                    } else {
                        $message.text(response.data.message || this.config.i18n.save_error).addClass('error');
                    }
                },
                error: () => {
                    $message.text(this.config.i18n.save_error).addClass('error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    
                    setTimeout(() => $message.fadeOut(), 5000);
                }
            });
        },
        
        /**
         * Load Year Expander settings
         */
        loadYearExpanderSettings: function() {
            const settings = this.config.year_expander || {};
            
            // Populate CCT dropdown
            const $cctSelect = $('#year-target-cct');
            $cctSelect.html(`<option value="">${this.config.i18n.select_cct}</option>`);
            
            this.config.ccts.forEach(cct => {
                const selected = settings.target_cct === cct.slug ? 'selected' : '';
                $cctSelect.append(
                    `<option value="${cct.slug}" ${selected}>${cct.name} (${cct.slug})</option>`
                );
            });
            
            // Set enabled checkbox
            $('#year-expander-enabled').prop('checked', settings.enabled);
            
            // Load fields if CCT is selected
            if (settings.target_cct) {
                this.loadYearExpanderFields(settings.target_cct, settings);
            }
        },
        
        /**
         * Handle Year Expander CCT change
         */
        handleYearCctChange: function(e) {
            const cctSlug = $(e.currentTarget).val();
            
            if (!cctSlug) {
                $('#year-start-field, #year-end-field, #year-output-field')
                    .prop('disabled', true)
                    .html(`<option value="">${this.config.i18n.select_field}</option>`);
                return;
            }
            
            this.loadYearExpanderFields(cctSlug);
        },
        
        /**
         * Load Year Expander fields
         */
        loadYearExpanderFields: function(cctSlug, settings) {
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_get_cct_fields',
                    nonce: this.config.nonce,
                    cct_slug: cctSlug
                },
                success: (response) => {
                    if (response.success) {
                        ['start', 'end', 'output'].forEach(type => {
                            const $select = $(`#year-${type}-field`);
                            const selectedVal = settings ? settings[`${type}_field`] : '';
                            
                            $select.html(`<option value="">${this.config.i18n.select_field}</option>`);
                            
                            response.data.fields.forEach(field => {
                                const selected = selectedVal === field.name ? 'selected' : '';
                                $select.append(
                                    `<option value="${field.name}" ${selected}>${field.title} (${field.name})</option>`
                                );
                            });
                            
                            $select.prop('disabled', false);
                        });
                    }
                }
            });
        },
        
        /**
         * Save Year Expander settings
         */
        saveYearExpander: function(e) {
            e.preventDefault();
            
            const $btn = $('#save-year-expander-btn');
            const $spinner = $('#year-expander-spinner');
            const $message = $('#year-expander-message');
            
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_save_year_expander',
                    nonce: this.config.nonce,
                    enabled: $('#year-expander-enabled').is(':checked') ? 1 : 0,
                    target_cct: $('#year-target-cct').val(),
                    start_field: $('#year-start-field').val(),
                    end_field: $('#year-end-field').val(),
                    output_field: $('#year-output-field').val()
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                    } else {
                        $message.text(response.data.message || this.config.i18n.save_error).addClass('error');
                    }
                },
                error: () => {
                    $message.text(this.config.i18n.save_error).addClass('error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    
                    setTimeout(() => $message.fadeOut(), 5000);
                }
            });
        },
        
        /**
         * Save debug settings
         */
        saveDebugSettings: function(e) {
            e.preventDefault();
            
            const $btn = $('#save-debug-settings-btn');
            const $spinner = $('#debug-settings-spinner');
            const $message = $('#debug-settings-message');
            
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_save_debug_settings',
                    nonce: this.config.nonce,
                    enable_php_logging: $('#enable-php-logging').is(':checked') ? 1 : 0,
                    enable_js_console: $('#enable-js-console').is(':checked') ? 1 : 0,
                    enable_admin_notices: $('#enable-admin-notices').is(':checked') ? 1 : 0
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                    } else {
                        $message.text(response.data.message || this.config.i18n.save_error).addClass('error');
                    }
                },
                error: () => {
                    $message.text(this.config.i18n.save_error).addClass('error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                    
                    setTimeout(() => $message.fadeOut(), 5000);
                }
            });
        },
        
        /**
         * View debug log
         */
        viewLog: function(e) {
            e.preventDefault();
            
            const $spinner = $('#log-spinner');
            const $message = $('#log-message');
            
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_view_log',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $('#log-contents').text(response.data.contents || 'Log is empty.');
                        $('#log-size').text(response.data.size);
                        $('#log-viewer').slideDown();
                    } else {
                        $message.text(response.data.message).addClass('error');
                    }
                },
                error: () => {
                    $message.text('Error loading log').addClass('error');
                },
                complete: () => {
                    $spinner.removeClass('is-active');
                }
            });
        },
        
        /**
         * Clear debug log
         */
        clearLog: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the debug log?')) {
                return;
            }
            
            const $spinner = $('#log-spinner');
            const $message = $('#log-message');
            
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_clear_log',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        $('#log-contents').text('');
                        $('#log-size').text('0 bytes');
                    } else {
                        $message.text(response.data.message).addClass('error');
                    }
                },
                error: () => {
                    $message.text('Error clearing log').addClass('error');
                },
                complete: () => {
                    $spinner.removeClass('is-active');
                    
                    setTimeout(() => $message.fadeOut(), 3000);
                }
            });
        },
        
        /**
         * Save CCT role mapping
         */
        saveCctMapping: function(e) {
            e.preventDefault();
            
            console.log('[PAC VDM Admin] Save CCT Mapping clicked');
            
            const $btn = $('#save-cct-mapping-btn');
            const $spinner = $('#cct-mapping-spinner');
            const $message = $('#cct-mapping-message');
            
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            // Collect CCT mapping data
            const ccts = {};
            $('.cct-role-select').each(function() {
                const role = $(this).data('role');
                const slug = $(this).val();
                if (slug) {
                    ccts[role] = slug;
                }
            });
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_save_cct_mapping',
                    nonce: this.config.nonce,
                    ccts: ccts
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        // Reload the page to refresh status
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        $message.text(response.data.message || 'Error saving mapping').addClass('error');
                    }
                },
                error: () => {
                    $message.text('Error saving mapping').addClass('error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },
        
        /**
         * Refresh field status (AJAX reload of status table)
         */
        refreshFieldStatus: function(e) {
            e.preventDefault();
            
            console.log('[PAC VDM Admin] Refresh field status clicked');
            
            const $btn = $('#refresh-field-status-btn');
            const $spinner = $('#cct-mapping-spinner');
            const $message = $('#cct-mapping-message');
            
            $btn.prop('disabled', true);
            $btn.find('.dashicons').addClass('spin');
            $spinner.addClass('is-active');
            $message.text('Refreshing...').removeClass('success error');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_get_setup_status',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $message.text('Status refreshed!').addClass('success');
                        // Reload the page to show updated status
                        setTimeout(() => window.location.reload(), 800);
                    } else {
                        $message.text('Error refreshing status').addClass('error');
                    }
                },
                error: () => {
                    $message.text('Error refreshing status').addClass('error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('spin');
                    $spinner.removeClass('is-active');
                    
                    setTimeout(() => $message.fadeOut(), 3000);
                }
            });
        },
        
        /**
         * Create a single relation
         */
        createRelation: function(e) {
            e.preventDefault();
            
            console.log('[PAC VDM Admin] Create relation clicked');
            
            const $btn = $(e.currentTarget);
            const parent = $btn.data('parent');
            const child = $btn.data('child');
            const name = $btn.data('name');
            
            $btn.prop('disabled', true);
            $btn.find('.dashicons').removeClass('dashicons-admin-links').addClass('dashicons-update spin');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_create_relation',
                    nonce: this.config.nonce,
                    parent_slug: parent,
                    child_slug: child,
                    name: name
                },
                success: (response) => {
                    if (response.success) {
                        // Reload to show updated status
                        window.location.reload();
                    } else {
                        alert(response.data.message || 'Error creating relation');
                        $btn.prop('disabled', false);
                        $btn.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-admin-links');
                    }
                },
                error: () => {
                    alert('Error creating relation');
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('dashicons-update spin').addClass('dashicons-admin-links');
                }
            });
        },
        
        /**
         * Create all missing relations
         */
        createAllRelations: function(e) {
            e.preventDefault();
            
            console.log('[PAC VDM Admin] Create all relations clicked');
            
            const $btn = $('#create-all-relations-btn');
            const $spinner = $('#relations-spinner');
            const $message = $('#relations-message');
            
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_create_all_relations',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        // Reload to show updated status
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        $message.text(response.data.message || 'Error creating relations').addClass('error');
                    }
                },
                error: () => {
                    $message.text('Error creating relations').addClass('error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },
        
        /**
         * Auto-create all field mappings
         */
        autoCreateMappings: function(e) {
            e.preventDefault();
            
            console.log('[PAC VDM Admin] Auto-create mappings clicked');
            
            const $btn = $('#auto-create-mappings-btn');
            const $spinner = $('#auto-mappings-spinner');
            const $message = $('#auto-mappings-message');
            
            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $message.text('').removeClass('success error');
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_auto_create_mappings',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        $message.text(response.data.message).addClass('success');
                        // Update local mappings config
                        if (response.data.mappings) {
                            this.config.mappings = response.data.mappings;
                        }
                        if (response.data.year_expander) {
                            this.config.year_expander = response.data.year_expander;
                        }
                        // Notify user
                        $message.append(' <a href="#mappings" class="button button-small">View Mappings</a>');
                    } else {
                        $message.text(response.data.message || 'Error creating mappings').addClass('error');
                    }
                },
                error: () => {
                    $message.text('Error creating mappings').addClass('error');
                },
                complete: () => {
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                }
            });
        },
        
        /**
         * Start bulk sync for a CCT
         */
        startBulkSync: function(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const cctSlug = $btn.data('cct');
            const totalCount = parseInt($btn.data('count'));
            
            if (!confirm('Sync ' + totalCount + ' existing items? This will update all items with parent data, year ranges, and config names.')) {
                return;
            }
            
            console.log('[PAC VDM Admin] Starting bulk sync for: ' + cctSlug);
            
            // Disable all sync buttons
            $('.bulk-sync-btn').prop('disabled', true);
            
            // Show progress
            $('#bulk-sync-progress').addClass('active');
            $('#sync-progress-bar').css('width', '0%').text('0%');
            $('#sync-progress-text').text('Starting sync...');
            
            // Start batch processing
            this.processBulkSyncBatch(cctSlug, 0, totalCount);
        },
        
        /**
         * Process a batch of items
         */
        processBulkSyncBatch: function(cctSlug, offset, totalCount) {
            const self = this;
            
            $.ajax({
                url: this.config.ajax_url,
                type: 'POST',
                data: {
                    action: 'pac_vdm_bulk_sync_batch',
                    nonce: this.config.nonce,
                    cct_slug: cctSlug,
                    offset: offset
                },
                success: (response) => {
                    if (response.success) {
                        const result = response.data;
                        const processed = offset + result.processed;
                        const percentComplete = Math.round((processed / totalCount) * 100);
                        
                        // Update progress
                        $('#sync-progress-bar').css('width', percentComplete + '%').text(percentComplete + '%');
                        $('#sync-progress-text').text(
                            'Processed ' + processed + ' of ' + totalCount + ' items (' + 
                            result.success + ' success, ' + result.errors + ' errors)'
                        );
                        
                        console.log('[PAC VDM Bulk Sync] Batch complete', result);
                        
                        // If there are more items, process next batch
                        if (result.has_more) {
                            setTimeout(() => {
                                self.processBulkSyncBatch(cctSlug, result.next_offset, totalCount);
                            }, 500); // Small delay to prevent overwhelming server
                        } else {
                            // All done!
                            $('#sync-progress-text').html(
                                '<strong style="color: #46b450;">✓ Sync complete!</strong> ' +
                                'Processed ' + processed + ' items (' + result.success + ' success, ' + result.errors + ' errors)'
                            );
                            
                            // Update status in table
                            const $row = $('[data-cct="' + cctSlug + '"]');
                            $row.find('.sync-status-cell').html(
                                '<span style="color: #46b450;"><span class="dashicons dashicons-yes-alt"></span> Synced</span>'
                            );
                            
                            // Re-enable buttons
                            setTimeout(() => {
                                $('.bulk-sync-btn').prop('disabled', false);
                                $('#bulk-sync-progress').removeClass('active');
                            }, 3000);
                        }
                    } else {
                        alert('Error: ' + (response.data.message || 'Unknown error'));
                        $('.bulk-sync-btn').prop('disabled', false);
                        $('#bulk-sync-progress').removeClass('active');
                    }
                },
                error: () => {
                    alert('AJAX error during bulk sync');
                    $('.bulk-sync-btn').prop('disabled', false);
                    $('#bulk-sync-progress').removeClass('active');
                }
            });
        },
        
        /**
         * Generate UUID for new mappings
         */
        generateUUID: function() {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
                var r = Math.random() * 16 | 0,
                    v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        PacVdmAdmin.init();
    });
    
})(jQuery);

