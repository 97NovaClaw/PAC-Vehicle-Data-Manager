# PAC Vehicle Data Manager - Build Plan

## Executive Summary

**Plugin Name:** PAC Vehicle Data Manager  
**Slug:** `pac-vehicle-data-manager`  
**Prefix:** `pac_vdm_`  
**Version:** 1.0.0  

**Core Function:** Automates data inheritance between related CCTs, enforces data integrity via read-only fields, auto-generates utility data (year ranges), and supports bidirectional data sync.

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                     PAC VEHICLE DATA MANAGER                        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐         │
│  │  Discovery   │    │   Config     │    │  Admin Page  │         │
│  │   Engine     │◄──►│   Manager    │◄──►│   Handler    │         │
│  └──────────────┘    └──────────────┘    └──────────────┘         │
│         │                   │                    │                 │
│         ▼                   ▼                    ▼                 │
│  ┌─────────────────────────────────────────────────────────┐      │
│  │                    DATA PROCESSING LAYER                 │      │
│  ├─────────────────────────────────────────────────────────┤      │
│  │                                                         │      │
│  │  ┌──────────────┐    ┌──────────────┐                  │      │
│  │  │    Year      │    │    Data      │                  │      │
│  │  │  Expander    │    │  Flattener   │                  │      │
│  │  │  (Internal)  │    │  (Relations) │                  │      │
│  │  └──────────────┘    └──────────────┘                  │      │
│  │         │                   │                           │      │
│  │         └───────┬───────────┘                           │      │
│  │                 ▼                                       │      │
│  │     JetEngine CCT Hooks:                               │      │
│  │     • jet-engine/custom-content-types/item-to-update   │      │
│  │     • jet-engine/custom-content-types/created-item     │      │
│  │     • jet-engine/custom-content-types/updated-item     │      │
│  │                                                         │      │
│  └─────────────────────────────────────────────────────────┘      │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────┐      │
│  │                    UI ENFORCEMENT LAYER                  │      │
│  ├─────────────────────────────────────────────────────────┤      │
│  │                                                         │      │
│  │  ┌──────────────────────────────────────────────────┐  │      │
│  │  │           Read-Only Field Enforcer (JS)          │  │      │
│  │  │  • Detects CCT edit page via URL params          │  │      │
│  │  │  • Waits for Vue.js form render (MutationObserver)│  │      │
│  │  │  • Applies readonly + visual styling             │  │      │
│  │  └──────────────────────────────────────────────────┘  │      │
│  │                                                         │      │
│  └─────────────────────────────────────────────────────────┘      │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## File Structure

```
PAC Vehicle Data Manager/
├── pac-vehicle-data-manager.php        # Main plugin file
├── uninstall.php                        # Clean uninstall handler
├── debug.txt                            # Debug log (created on demand)
│
├── includes/
│   ├── helpers/
│   │   └── debug.php                    # Debug logging functions
│   │
│   ├── class-plugin.php                 # Main singleton orchestrator
│   ├── class-discovery.php              # CCT & Relation discovery
│   ├── class-config-manager.php         # Settings CRUD
│   ├── class-admin-page.php             # Admin menu & AJAX handlers
│   ├── class-year-expander.php          # Year range generator
│   ├── class-data-flattener.php         # Relation data inheritance
│   └── class-field-locker.php           # Read-only field coordinator
│
├── assets/
│   ├── css/
│   │   └── admin.css                    # Admin page styles
│   │
│   └── js/
│       ├── admin.js                     # Admin page interactions
│       └── field-locker.js              # CCT field locking script
│
└── templates/
    └── admin/
        ├── settings-page.php            # Main admin page
        ├── mappings-tab.php             # Field mappings table
        ├── year-expander-tab.php        # Year expander config
        └── debug-tab.php                # Debug controls
```

---

## Module Specifications

### Module A: Discovery Engine (`class-discovery.php`)

**Purpose:** Detects JetEngine CCTs and Relations for dropdown population.

**Key Methods:**
- `get_all_ccts()` - Returns all registered CCTs
- `get_cct($slug)` - Get single CCT details  
- `get_all_relations()` - Returns all JetEngine relations
- `get_relations_for_cct($slug, $position)` - Get relations where CCT is parent/child
- `get_cct_fields($slug)` - Get field definitions for a CCT
- `parse_relation_object($object_string)` - Parse "cct::slug" format

**Dependencies:** JetEngine CCT Module, Relations Module

---

### Module B: Config Manager (`class-config-manager.php`)

**Purpose:** Handles CRUD operations for mapping configurations stored in `wp_options`.

**Database Storage:**
```php
// Option: pac_vdm_mappings
[
    'mappings' => [
        [
            'id' => 'map_1',
            'target_cct' => 'service_guide',
            'trigger_relation' => 12,
            'source_field' => 'make_id',
            'destination_field' => 'make_id', 
            'ui_behavior' => 'readonly', // or 'hidden'
            'enabled' => true,
        ],
        // ... more mappings
    ],
    'year_expander' => [
        'enabled' => true,
        'target_cct' => 'vehicle_config',
        'start_field' => 'year_start',
        'end_field' => 'year_end',
        'output_field' => 'year_range_list',
    ],
]
```

**Key Methods:**
- `get_all_mappings()` - Get all field mappings
- `save_mapping($data)` - Create/update a mapping
- `delete_mapping($id)` - Remove a mapping
- `get_year_expander_config()` - Get year expander settings
- `save_year_expander_config($data)` - Save year expander settings

---

### Module C: Admin Page (`class-admin-page.php`)

**Purpose:** Admin menu, settings page rendering, AJAX handlers.

**Menu Structure:**
```
├── PAC Vehicle Data (Top Level)
│   └── Mappings (Main Page - settings-page.php)
```

**Tab Structure:**
1. **Mappings Tab** - Repeater table for field mappings
2. **Year Expander Tab** - Year range generator config
3. **Debug Tab** - Logging controls & log viewer

**AJAX Endpoints:**
- `pac_vdm_save_mappings` - Save all mappings
- `pac_vdm_delete_mapping` - Delete single mapping
- `pac_vdm_get_cct_relations` - Get relations for selected CCT (auto-filter)
- `pac_vdm_get_cct_fields` - Get fields for selected CCT
- `pac_vdm_save_year_expander` - Save year expander config
- `pac_vdm_save_debug_settings` - Save debug toggles
- `pac_vdm_view_log` - Fetch debug log contents
- `pac_vdm_clear_log` - Clear debug log

---

### Module D: Year Expander (`class-year-expander.php`)

**Purpose:** Generates year arrays from start/end range.

**Trigger:** Hook into `jet-engine/custom-content-types/item-to-update` filter.

**Logic Flow:**
```php
// Input: year_start = 2018, year_end = 2023
// Process:
$years = [];
for ($i = $start; $i <= $end; $i++) {
    $years[] = $i;
}
// Output: [2018, 2019, 2020, 2021, 2022, 2023]
// Stored as serialized PHP array (JetEngine compatible)
```

**Key Methods:**
- `should_process($cct_slug)` - Check if this CCT has expander enabled
- `expand_year_range($item_data)` - Generate and inject year array
- `register_hooks()` - Attach to CCT save hooks

---

### Module E: Data Flattener (`class-data-flattener.php`)

**Purpose:** Copies field values between related CCTs via JetEngine Relations.

**Trigger Hooks:**
- `jet-engine/custom-content-types/item-to-update` (FILTER) - Inject data before save
- `jet-engine/custom-content-types/updated-item/{slug}` (ACTION) - Push to children after save

**Data Flow - PULL (Child saves, gets Parent data):**
```
1. Service Guide being saved
2. Find relation: Config → Service Guide
3. Get related Config item ID
4. Fetch Config.make_id value
5. Inject into Service Guide.make_id before save
```

**Data Flow - PUSH (Parent saves, updates Children):**
```
1. Vehicle Config being saved  
2. Find relation: Config → Service Guide
3. Get all related Service Guide IDs
4. For each child: Update child.make_id = parent.make_id
```

**Key Methods:**
- `register_hooks()` - Attach to configured CCT hooks
- `pull_parent_data($item, $cct_slug)` - Fetch & inject parent data
- `push_to_children($item, $cct_slug)` - Update child records
- `get_related_parent_item($item_id, $relation_id)` - Find parent via relation
- `get_related_child_items($item_id, $relation_id)` - Find children via relation

---

### Module F: Field Locker (`class-field-locker.php` + `field-locker.js`)

**Purpose:** Enforces read-only fields on CCT edit screens.

**PHP Coordinator:**
- Detects CCT edit page via `admin_enqueue_scripts`
- Queries config for read-only fields
- Localizes field list to JS

**JavaScript Logic:**
```javascript
// 1. Wait for JetEngine Vue form to render
const observer = new MutationObserver((mutations) => {
    if (document.querySelector('[name="field_slug"]')) {
        lockFields();
        observer.disconnect();
    }
});

// 2. Lock each configured field
function lockFields() {
    config.readOnlyFields.forEach(fieldSlug => {
        const $field = document.querySelector(`[name="${fieldSlug}"]`);
        if ($field) {
            $field.setAttribute('readonly', true);
            $field.classList.add('pac-vdm-locked');
            // Add visual indicator
            insertLockedBadge($field);
        }
    });
}
```

**Visual Styling:**
```css
.pac-vdm-locked {
    background: #f0f0f1 !important;
    opacity: 0.7;
    cursor: not-allowed;
}
.pac-vdm-locked-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    background: #e0e0e0;
    border-radius: 3px;
    font-size: 11px;
    color: #666;
}
```

---

## Database Schema

### Option: `pac_vdm_settings`
```php
[
    'mappings' => [
        [
            'id' => 'map_abc123',
            'target_cct' => 'service_guide',
            'trigger_relation' => 12,
            'source_field' => 'make_id',
            'destination_field' => 'make_id',
            'direction' => 'pull', // 'pull' or 'push' or 'both'
            'ui_behavior' => 'readonly',
            'enabled' => true,
            'created_at' => '2026-01-11 10:00:00',
        ],
    ],
    'year_expander' => [
        'enabled' => true,
        'target_cct' => 'vehicle_config',
        'start_field' => 'year_start',
        'end_field' => 'year_end',
        'output_field' => 'year_range_list',
    ],
]
```

### Option: `pac_vdm_debug_options`
```php
[
    'enable_php_logging' => false,
    'enable_js_console' => false,
    'enable_admin_notices' => false,
]
```

---

## Admin UI Design

### Mappings Tab - Repeater Table

| # | Target CCT ▼ | Trigger Relation ▼ | Source Field | Dest Field | Direction ▼ | UI Behavior ▼ | Actions |
|---|--------------|-------------------|--------------|------------|-------------|---------------|---------|
| 1 | Service Guide | Config → Guide | make_id | make_id | Both | Read-Only | ✏️ 🗑️ |
| 2 | Service Guide | Config → Guide | model_id | model_id | Pull | Read-Only | ✏️ 🗑️ |

**[+ Add Mapping]** button at bottom

### Year Expander Tab

| Setting | Value |
|---------|-------|
| Target CCT | `vehicle_config` ▼ |
| Start Year Field | `year_start` ▼ |
| End Year Field | `year_end` ▼ |
| Output Field | `year_range_list` ▼ |
| Enabled | ☑️ |

**[Save Year Expander Settings]** button

### Debug Tab

*(Same pattern as Relation Injector plugin)*

---

## JetEngine Hook Integration

### Critical Hook: `item-to-update` Filter

```php
// This is the KEY hook - allows modifying data BEFORE database write
add_filter(
    'jet-engine/custom-content-types/item-to-update',
    [$this, 'process_item_data'],
    10,
    3
);

public function process_item_data($item, $fields, $handler) {
    $cct_slug = $handler->get_factory()->get_arg('slug');
    
    // 1. Year Expander
    $item = $this->year_expander->maybe_expand($item, $cct_slug);
    
    // 2. Data Flattener (Pull from parent)
    $item = $this->data_flattener->pull_parent_data($item, $cct_slug);
    
    return $item;
}
```

### Post-Save Hook: Push to Children

```php
// For push operations - run AFTER save completes
add_action(
    'jet-engine/custom-content-types/updated-item/vehicle_config',
    [$this, 'push_data_to_children'],
    10,
    3
);

public function push_data_to_children($item, $prev_item, $handler) {
    $this->data_flattener->push_to_children($item, 'vehicle_config');
}
```

---

## Error Handling Strategy

1. **Wrap all processing in try-catch** - Never crash CCT saves
2. **Log errors to debug.txt** - When debug enabled
3. **Graceful degradation** - If relation not found, skip silently
4. **Admin notices** - Show post-save summary (optional)

---

## Testing Checklist

### Year Expander
- [ ] Save Vehicle Config with year_start=2018, year_end=2023
- [ ] Verify year_range_list contains [2018,2019,2020,2021,2022,2023]
- [ ] Test edge case: start > end (should produce empty array)
- [ ] Test equal years: start = end = 2020 (should produce [2020])

### Data Flattener - Pull
- [ ] Create Service Guide related to Config
- [ ] Verify make_id copied from Config on save
- [ ] Test new item (no existing relation yet)
- [ ] Test update of existing item

### Data Flattener - Push
- [ ] Update Vehicle Config make_id
- [ ] Verify all related Service Guides updated
- [ ] Test with multiple children
- [ ] Test with no children (no errors)

### Field Locker
- [ ] Edit Service Guide with make_id mapping
- [ ] Verify make_id field is read-only
- [ ] Verify visual styling applied
- [ ] Test on new item vs existing item

### Admin UI
- [ ] Add new mapping via table
- [ ] Edit existing mapping
- [ ] Delete mapping
- [ ] Verify relation dropdown filters correctly
- [ ] Save and reload - data persists

---

## Security Considerations

1. **Nonce verification** on all AJAX calls
2. **Capability checks** (`manage_options` for admin pages)
3. **Sanitize all inputs** before database storage
4. **Escape all outputs** in templates

---

## Performance Notes

1. **Cache CCT/Relation discovery** - Avoid repeated API calls
2. **Batch child updates** - Use single query where possible
3. **Lazy load mappings** - Only query when needed
4. **Minimal JS footprint** - Only load on CCT pages

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-01-11 | Initial release |

---

## Development Order

1. ✅ Plugin scaffold + constants
2. ✅ Debug helper functions
3. ✅ Discovery Engine
4. ✅ Config Manager
5. ✅ Admin Page + Templates
6. ✅ Year Expander (hardcoded test first)
7. ✅ Data Flattener (pull logic)
8. ✅ Data Flattener (push logic)
9. ✅ Field Locker (JS)
10. ✅ Full integration testing

