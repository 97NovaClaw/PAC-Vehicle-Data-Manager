# PAC Vehicle Data Manager

A WordPress plugin that automates data inheritance between related JetEngine Custom Content Types (CCTs), enforces data integrity with read-only fields, and auto-generates utility data like year ranges for filtering.

## Features

### 🔄 Field Mappings (Bidirectional Data Sync)
- **PULL** - Automatically copy field values from parent CCT to child on save
- **PUSH** - Update all child CCTs when parent field changes
- **BOTH** - Supports bidirectional sync for complete automation
- Smart relation filtering - only shows relevant relations for selected CCT

### 📅 Year Expander Module
- Automatically generates year arrays from start/end year fields
- Example: `year_start=2018`, `year_end=2023` → `[2018,2019,2020,2021,2022,2023]`
- Perfect for JetSmartFilters year range queries
- Stores as serialized PHP array (JetEngine compatible)

### 🔒 Read-Only Field Enforcer
- Prevents editing of auto-synced fields via JavaScript
- Uses MutationObserver to handle Vue.js dynamic forms
- Visual "Auto-synced" badge on locked fields
- Works with all JetEngine field types

### 🐛 Debug System
- PHP logging to `debug.txt`
- JavaScript console logging toggle
- Admin notices for sync operations
- Built-in log viewer with clear functionality

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- JetEngine 3.3.1 or higher
- JetEngine Custom Content Types module (enabled)
- JetEngine Relations module (enabled)

## Installation

1. Download the plugin
2. Upload to `/wp-content/plugins/pac-vehicle-data-manager/`
3. Activate through the 'Plugins' menu in WordPress
4. Access settings via **PAC Vehicle Data** menu in WordPress admin

## Configuration

### Field Mappings

1. Navigate to **PAC Vehicle Data** → **Field Mappings**
2. Click **Add Mapping**
3. Configure:
   - **Target CCT** - The CCT that will receive data
   - **Trigger Relation** - The relation connecting parent and child (auto-filtered)
   - **Source Field** - Field from parent CCT to copy
   - **Destination Field** - Field in child CCT to populate
   - **Direction** - Pull, Push, or Both
   - **UI Behavior** - Read-Only or Hidden
4. Click **Save All Mappings**

### Year Expander

1. Navigate to **Year Expander** tab
2. Enable the feature
3. Select:
   - **Target CCT** - CCT containing year fields
   - **Start Year Field** - Field with start year
   - **End Year Field** - Field with end year
   - **Output Field** - Field to store generated array
4. Click **Save Year Expander Settings**

## How It Works

### Data Flow - PULL (Child pulls from Parent)

```
1. User saves Service Guide CCT item
2. Plugin detects configured mapping
3. Finds related Vehicle Config via JetEngine relation
4. Fetches make_id from Vehicle Config
5. Injects make_id into Service Guide BEFORE database save
6. Field Locker makes make_id read-only in UI
```

### Data Flow - PUSH (Parent pushes to Children)

```
1. User updates Vehicle Config make_id
2. Plugin detects PUSH mapping
3. Queries all related Service Guide items
4. Updates make_id on ALL child items
5. Changes reflected immediately
```

### JetEngine Hooks Used

| Hook | Type | Purpose |
|------|------|---------|
| `jet-engine/custom-content-types/item-to-update` | Filter | Year expansion + Pull parent data |
| `jet-engine/custom-content-types/updated-item/{slug}` | Action | Push data to children |

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                  PAC VEHICLE DATA MANAGER                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐  │
│  │  Discovery   │    │   Config     │    │  Admin Page  │  │
│  │   Engine     │◄──►│   Manager    │◄──►│   Handler    │  │
│  └──────────────┘    └──────────────┘    └──────────────┘  │
│         │                   │                    │          │
│         ▼                   ▼                    ▼          │
│  ┌───────────────────────────────────────────────────────┐ │
│  │              DATA PROCESSING LAYER                    │ │
│  │                                                       │ │
│  │  ┌──────────────┐    ┌──────────────┐                │ │
│  │  │    Year      │    │    Data      │                │ │
│  │  │  Expander    │    │  Flattener   │                │ │
│  │  └──────────────┘    └──────────────┘                │ │
│  │                                                       │ │
│  │     Hooks into JetEngine CCT save lifecycle          │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │              UI ENFORCEMENT LAYER                      │ │
│  │                                                       │ │
│  │  ┌──────────────────────────────────────────────────┐ │ │
│  │  │      Read-Only Field Enforcer (JavaScript)       │ │ │
│  │  │   • MutationObserver for Vue.js forms            │ │ │
│  │  │   • Visual styling for locked fields             │ │ │
│  │  └──────────────────────────────────────────────────┘ │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## File Structure

```
pac-vehicle-data-manager/
├── pac-vehicle-data-manager.php    # Main plugin file
├── uninstall.php                    # Clean uninstall handler
├── BUILD-PLAN.md                    # Technical documentation
│
├── includes/
│   ├── helpers/
│   │   └── debug.php                # Debug logging functions
│   │
│   ├── class-plugin.php             # Main singleton
│   ├── class-discovery.php          # CCT & Relation discovery
│   ├── class-config-manager.php     # Settings management
│   ├── class-admin-page.php         # Admin UI & AJAX
│   ├── class-year-expander.php      # Year range generator
│   ├── class-data-flattener.php     # Data inheritance engine
│   └── class-field-locker.php       # Read-only coordinator
│
├── assets/
│   ├── css/
│   │   └── admin.css                # Admin styles
│   └── js/
│       ├── admin.js                 # Admin interactions
│       └── field-locker.js          # Field locking logic
│
└── templates/
    └── admin/
        ├── settings-page.php        # Main admin page
        └── debug-tab.php            # Debug UI
```

## Use Cases

### Vehicle Configuration Management
Perfect for automotive sites where vehicle data flows from Make → Model → Year → Service Guide.

**Example Mapping:**
- Target: `service_guide` CCT
- Relation: `vehicle_config → service_guide`
- Source Field: `make_id`, `model_id`, `year_range`
- Destination: Same field names
- Direction: Both (pull on child save, push on parent update)

### Year Range Filtering
Generate filterable year arrays for JetSmartFilters.

**Configuration:**
- Target CCT: `vehicle_config`
- Start: `year_start` (e.g., 2018)
- End: `year_end` (e.g., 2023)
- Output: `year_range_list` → `[2018,2019,2020,2021,2022,2023]`

## Debugging

Enable debug options in the **Debug** tab:

- **PHP Logging** - Logs all operations to `debug.txt`
- **JS Console** - Outputs field locker activity to browser console
- **Admin Notices** - Shows sync success/error messages

View logs directly in the admin panel or access `debug.txt` in the plugin folder.

## Performance

- CCT/Relation discovery is cached for efficiency
- PUSH operations use single queries where possible
- Field Locker only loads on relevant CCT edit pages
- Minimal overhead on CCT save operations

## Security

- Nonce verification on all AJAX calls
- Capability checks (`manage_options` for admin)
- All inputs sanitized before database storage
- All outputs escaped in templates

## Support

For issues, feature requests, or questions:
- GitHub Issues: https://github.com/97NovaClaw/PAC-Vehicle-Data-Manager/issues

## License

GPL v2 or later

## Credits

Developed by PAC Development

Built with reference to JetEngine's CCT and Relations modules.

