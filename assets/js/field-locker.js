/**
 * Field Locker JavaScript
 *
 * Enforces read-only fields on JetEngine CCT edit screens
 * Uses MutationObserver to handle Vue.js dynamic rendering
 *
 * @package PAC_Vehicle_Data_Manager
 */

(function($) {
    'use strict';
    
    const PacVdmFieldLocker = {
        
        config: null,
        observer: null,
        fieldsLocked: false,
        maxAttempts: 50,
        attempts: 0,
        
        /**
         * Initialize
         */
        init: function() {
            if (typeof pacVdmFieldLocker === 'undefined') {
                console.error('[PAC VDM Field Locker] Configuration not found');
                return;
            }
            
            this.config = pacVdmFieldLocker;
            
            this.log('Initializing Field Locker', {
                cct: this.config.cct_slug,
                readonly: this.config.readonly_fields,
                hidden: this.config.hidden_fields
            });
            
            // Start watching for form render
            this.waitForForm();
        },
        
        /**
         * Wait for CCT form to render (Vue.js dynamic)
         */
        waitForForm: function() {
            // Try immediate lock first
            if (this.tryLockFields()) {
                this.log('Fields locked on immediate try');
                return;
            }
            
            // Set up MutationObserver for dynamic content
            this.setupObserver();
            
            // Also use interval as fallback
            const checkInterval = setInterval(() => {
                this.attempts++;
                
                if (this.tryLockFields()) {
                    clearInterval(checkInterval);
                    this.disconnectObserver();
                    this.log('Fields locked after ' + this.attempts + ' attempts');
                } else if (this.attempts >= this.maxAttempts) {
                    clearInterval(checkInterval);
                    this.disconnectObserver();
                    this.log('Max attempts reached, some fields may not be locked');
                }
            }, 100);
        },
        
        /**
         * Set up MutationObserver
         */
        setupObserver: function() {
            const targetNode = document.body;
            const config = { childList: true, subtree: true };
            
            this.observer = new MutationObserver((mutations) => {
                if (!this.fieldsLocked) {
                    this.tryLockFields();
                }
            });
            
            this.observer.observe(targetNode, config);
        },
        
        /**
         * Disconnect observer
         */
        disconnectObserver: function() {
            if (this.observer) {
                this.observer.disconnect();
                this.observer = null;
            }
        },
        
        /**
         * Try to lock all configured fields
         * @return {boolean} True if all fields locked
         */
        tryLockFields: function() {
            let allLocked = true;
            
            // Lock readonly fields
            if (this.config.readonly_fields && this.config.readonly_fields.length > 0) {
                this.config.readonly_fields.forEach(fieldSlug => {
                    if (!this.lockField(fieldSlug)) {
                        allLocked = false;
                    }
                });
            }
            
            // Hide hidden fields
            if (this.config.hidden_fields && this.config.hidden_fields.length > 0) {
                this.config.hidden_fields.forEach(fieldSlug => {
                    if (!this.hideField(fieldSlug)) {
                        allLocked = false;
                    }
                });
            }
            
            if (allLocked && (
                (this.config.readonly_fields && this.config.readonly_fields.length > 0) ||
                (this.config.hidden_fields && this.config.hidden_fields.length > 0)
            )) {
                this.fieldsLocked = true;
            }
            
            return this.fieldsLocked;
        },
        
        /**
         * Lock a single field
         * @param {string} fieldSlug Field slug
         * @return {boolean} True if locked
         */
        lockField: function(fieldSlug) {
            // Try multiple selectors for JetEngine CCT fields
            const selectors = [
                `[name="${fieldSlug}"]`,
                `[name="cct_data[${fieldSlug}]"]`,
                `#${fieldSlug}`,
                `[data-field="${fieldSlug}"]`,
                `.cx-vui-component[data-cx-component="${fieldSlug}"] input`,
                `.cx-vui-component[data-cx-component="${fieldSlug}"] select`,
                `.cx-vui-component[data-cx-component="${fieldSlug}"] textarea`
            ];
            
            let locked = false;
            
            selectors.forEach(selector => {
                const $fields = $(selector);
                
                if ($fields.length > 0) {
                    $fields.each((i, field) => {
                        if (!$(field).hasClass('pac-vdm-locked')) {
                            this.applyLock($(field), fieldSlug);
                            locked = true;
                        }
                    });
                }
            });
            
            // Also try to lock the component wrapper (for Vue components)
            const $component = $(`.cx-vui-component`).filter(function() {
                return $(this).find(`[name="${fieldSlug}"]`).length > 0 ||
                       $(this).find(`[name="cct_data[${fieldSlug}]"]`).length > 0;
            });
            
            if ($component.length > 0 && !$component.hasClass('pac-vdm-locked-wrapper')) {
                $component.addClass('pac-vdm-locked-wrapper');
                this.addLockedBadge($component, fieldSlug);
                locked = true;
            }
            
            return locked;
        },
        
        /**
         * Apply lock styling and attributes to field
         * @param {jQuery} $field Field element
         * @param {string} fieldSlug Field slug
         */
        applyLock: function($field, fieldSlug) {
            $field.addClass('pac-vdm-locked');
            $field.attr('readonly', true);
            $field.attr('disabled', true);
            $field.attr('title', this.config.i18n.locked_tooltip);
            
            // Handle select elements
            if ($field.is('select')) {
                $field.prop('disabled', true);
            }
            
            // Handle checkboxes
            if ($field.is('[type="checkbox"]')) {
                $field.prop('disabled', true);
            }
            
            this.log('Field locked: ' + fieldSlug);
        },
        
        /**
         * Add locked badge to field wrapper
         * @param {jQuery} $wrapper Wrapper element
         * @param {string} fieldSlug Field slug
         */
        addLockedBadge: function($wrapper, fieldSlug) {
            const $label = $wrapper.find('.cx-vui-component__label, label').first();
            
            this.log('Attempting to add badge to field: ' + fieldSlug, {
                'wrapper_found': $wrapper.length > 0,
                'label_found': $label.length > 0,
                'label_html': $label.length > 0 ? $label.html() : 'N/A'
            });
            
            if ($label.length > 0 && !$label.find('.pac-vdm-locked-badge').length) {
                const badge = `<span class="pac-vdm-locked-badge">
                    <span class="dashicons dashicons-lock"></span>
                    ${this.config.i18n.inherited_label}
                </span>`;
                
                $label.append(badge);
                
                this.log('Badge added successfully to: ' + fieldSlug);
            } else if ($label.find('.pac-vdm-locked-badge').length) {
                this.log('Badge already exists for: ' + fieldSlug);
            } else {
                this.log('Could not find label element for: ' + fieldSlug, 'WARNING');
            }
        },
        
        /**
         * Hide a field completely
         * @param {string} fieldSlug Field slug
         * @return {boolean} True if hidden
         */
        hideField: function(fieldSlug) {
            // Find the component wrapper
            const $component = $(`.cx-vui-component`).filter(function() {
                return $(this).find(`[name="${fieldSlug}"]`).length > 0 ||
                       $(this).find(`[name="cct_data[${fieldSlug}]"]`).length > 0;
            });
            
            if ($component.length > 0 && !$component.hasClass('pac-vdm-hidden-field')) {
                $component.addClass('pac-vdm-hidden-field');
                this.log('Field hidden: ' + fieldSlug);
                return true;
            }
            
            // Fallback: hide field row in table
            const $field = $(`[name="${fieldSlug}"], [name="cct_data[${fieldSlug}]"]`);
            if ($field.length > 0) {
                const $row = $field.closest('tr, .cx-vui-component');
                if (!$row.hasClass('pac-vdm-hidden-field')) {
                    $row.addClass('pac-vdm-hidden-field');
                    this.log('Field row hidden: ' + fieldSlug);
                    return true;
                }
            }
            
            return false;
        },
        
        /**
         * Log to console if debug enabled
         */
        log: function(message, data) {
            if (this.config && this.config.debug) {
                if (data) {
                    console.log('[PAC VDM Field Locker] ' + message, data);
                } else {
                    console.log('[PAC VDM Field Locker] ' + message);
                }
            }
        }
    };
    
    // Initialize when DOM is ready
    $(document).ready(function() {
        PacVdmFieldLocker.init();
    });
    
})(jQuery);

