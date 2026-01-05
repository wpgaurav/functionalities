# Functionalities (WordPress Plugin)

A modular site-specific plugin to organize common features with simple toggles. Refactored with modern WordPress coding standards and a beautiful module-based dashboard.

## Features

### Core
- Safe plugin bootstrap with constants
- **NEW:** Module-based admin interface with dashboard cards
- **NEW:** URL parameter navigation (no more separate submenu pages)
- **NEW:** Improved WordPress Coding Standards compliance
- **NEW:** Comprehensive PHPDoc documentation

### Modules

**Link Management** (Complete GT Nofollow Manager)
- ✅ Automatic `rel="nofollow"` for external links with smart exception handling
- ✅ Applies to content, widgets, and comments (high priority filter at 999)
- ✅ Exception lists supporting full URLs, domains, or partial matches
- ✅ JSON preset file support for bulk exception loading
- ✅ Database update tool for bulk nofollow addition to existing posts
- ✅ Developer filters for programmatic customization
- ✅ Legacy GT Nofollow Manager filter compatibility (gtnf_*)
- ✅ Open external/internal links in new tab with fine-grained control
- ✅ Pattern-based domain matching
- ✅ Zero frontend footprint (no CSS/JS)
- Navigate to: `?page=functionalities&module=link-management`

**Block Cleanup**
- Strip common wp-block classes from frontend output (`.wp-block-heading`, `.wp-block-list`, `.wp-block-image`)
- Cleaner HTML markup
- Navigate to: `?page=functionalities&module=block-cleanup`

**Editor Link Suggestions**
- Limit link suggestions to selected post types in the block editor
- Reduce clutter in link search dialogs
- Navigate to: `?page=functionalities&module=editor-links`

**Miscellaneous (Bloat Control)**
- Disable emojis scripts/styles
- Disable embeds (oEmbed)
- Remove REST API and oEmbed discovery links from `<head>`
- Remove RSD, WLWManifest, and shortlink tags
- Remove WordPress version meta (generator)
- Disable XML-RPC
- Disable RSS/Atom feeds
- Disable Dashicons for non-logged-in users
- Disable Heartbeat API
- Disable admin bar on frontend
- Remove jQuery Migrate
- Load core block styles separately (per-block CSS)
- Disable block-based widget editor
- Enable PrismJS on admin screens
- Enable fullscreen toggle for backend textareas
- Navigate to: `?page=functionalities&module=misc`

**Header & Footer**
- Google Analytics 4 integration (just enter Measurement ID)
- Custom header code injection (scripts, styles, meta)
- Custom footer code injection
- Safe sanitization with `wp_kses` for non-superadmins
- Navigate to: `?page=functionalities&module=snippets`

**Schema Settings**
- Add microdata (itemscope/itemtype) to `<html>` tag
- Optional WPHeader and WPFooter microdata
- Article microdata with customizable itemtype
- Automatic headline, dates, and author properties
- Navigate to: `?page=functionalities&module=schema`

**Components**
- Define reusable CSS components as selector + CSS rules
- Auto-enqueue site-wide
- Default components: cards, buttons, badges, chips, alerts, avatars, grids, accordions, etc.
- Expandable accordion interface for managing components
- Navigate to: `?page=functionalities&module=components`

**Fonts**
- Register custom font families with @font-face
- WOFF2 and WOFF support
- Variable fonts support
- Font-display control (swap, auto, block, fallback, optional)
- Navigate to: `?page=functionalities&module=fonts`

**Icons**
- Replace Font Awesome with SVG `<use>` icons
- Remove Font Awesome assets to reduce page weight
- Custom SVG sprite URL
- Configurable class-to-symbol mappings
- Navigate to: `?page=functionalities&module=icons`

**Content Regression Detection** *(New in 0.9.0)*
- Detect structural regressions when posts are updated
- Internal link drop detection with configurable thresholds
- Word count regression alerts for significant content reduction
- Heading structure analysis for accessibility (H1, skipped levels)
- Rolling snapshot storage for historical comparison
- Block editor integration with pre-publish warnings
- Navigate to: `?page=functionalities&module=content-regression`

**Assumption Detection** *(New in 0.9.0)*
- Monitor when technical assumptions stop being true
- Schema collision detection (multiple JSON-LD sources)
- Analytics duplication detection (GA4, GTM, Facebook Pixel)
- Font redundancy detection (same font from multiple sources)
- Inline CSS growth tracking (performance debt monitoring)
- Dashboard UI with acknowledge/ignore actions
- Navigate to: `?page=functionalities&module=assumption-detection`

**Task Manager** *(New in 0.9.9)*
- Simple task tracking for content and development workflows
- Organize and prioritize tasks within WordPress admin
- Navigate to: `?page=functionalities&module=task-manager`

**Redirect Manager** *(New in 0.10.0)*
- Manage URL redirects directly from WordPress admin
- Support for 301 and 302 redirects
- Easy-to-use interface for redirect management
- Navigate to: `?page=functionalities&module=redirect-manager`

**Login Security** *(New in 0.10.0)*
- Enhanced login protection features
- Limit login attempts
- Additional security measures for WordPress login
- Navigate to: `?page=functionalities&module=login-security`

## Installation

1. Copy this folder `functionalities/` into your WordPress `wp-content/plugins/` directory.
2. In wp-admin, go to **Plugins** and activate **Functionalities**.
3. Navigate to **Functionalities** in the admin menu to access the module dashboard.

## Usage

### Accessing Modules

All modules are accessed through a unified dashboard at `wp-admin/admin.php?page=functionalities`.

**Dashboard View:**
- Beautiful card-based interface
- Each module displays icon, title, and description
- Click "Configure" to access module settings

**Module View:**
- Breadcrumb navigation back to dashboard
- Standard WordPress settings form
- All changes saved via Settings API

## Development

### File Structure
```
functionalities/
├── assets/
│   ├── css/
│   │   ├── admin.css              # Admin dashboard styles
│   │   ├── admin-ui.css           # Admin UI component styles
│   │   └── content-regression.css # Regression warning styles
│   └── js/
│       ├── admin.js               # Admin dashboard scripts
│       ├── admin-ui.js            # Admin UI scripts
│       └── content-regression.js  # Block editor integration
├── includes/
│   ├── admin/
│   │   ├── class-admin.php        # Admin interface
│   │   ├── class-admin-ui.php     # Reusable UI helpers
│   │   └── class-module-docs.php  # Centralized documentation
│   ├── features/
│   │   ├── class-assumption-detection.php
│   │   ├── class-block-cleanup.php
│   │   ├── class-components.php
│   │   ├── class-content-regression.php
│   │   ├── class-editor-links.php
│   │   ├── class-fonts.php
│   │   ├── class-icons.php
│   │   ├── class-link-management.php
│   │   ├── class-login-security.php
│   │   ├── class-meta.php
│   │   ├── class-misc.php
│   │   ├── class-redirect-manager.php
│   │   ├── class-schema.php
│   │   ├── class-snippets.php
│   │   └── class-task-manager.php
│   └── class-github-updater.php
├── languages/
└── functionalities.php            # Main plugin file
```

### Coding Standards

This plugin follows WordPress Coding Standards:
- ✅ PHPDoc documentation for all methods
- ✅ Named callback functions (no inline anonymous functions in settings)
- ✅ Proper escaping and sanitization
- ✅ Type declarations
- ✅ Proper spacing and formatting
- ✅ Use of array() syntax for WordPress compatibility

### Adding New Modules

1. Create a new feature class in `includes/features/class-your-module.php`
2. Add module definition in `Admin::define_modules()`
3. Register settings in `Admin::register_settings()`
4. Initialize in `functionalities.php`

Example:
```php
'your-module' => array(
    'title'       => __( 'Your Module', 'functionalities' ),
    'description' => __( 'Brief description', 'functionalities' ),
    'icon'        => 'dashicons-admin-generic',
),
```

### Notes

- Some PHP/WP functions may appear undefined in static analysis outside WordPress. They work at runtime within WordPress.
- Admin interface uses URL parameters (`?page=functionalities&module=link-management`) instead of separate submenu pages
- All settings are registered via WordPress Settings API for security and consistency

## WordPress Coding Standards Improvements

### What Was Fixed

1. **Modular Architecture**
   - Refactored from multiple submenu pages to single page with module parameters
   - Cleaner URL structure: `?page=functionalities&module=MODULE_NAME`
   - Centralized module management

2. **Code Quality**
   - Extracted all inline anonymous functions to named methods
   - Added comprehensive PHPDoc blocks
   - Improved method organization and naming
   - Better separation of concerns

3. **Security**
   - Proper input sanitization with `sanitize_key()`
   - Consistent use of `wp_kses()` for code snippets
   - Capability checks on all admin pages
   - Nonce verification via Settings API

4. **User Experience**
   - Beautiful dashboard with module cards
   - Dashicons for visual clarity
   - Breadcrumb navigation
   - Responsive grid layout
   - Hover effects and transitions

## GT Nofollow Manager - Complete Feature Parity

The Link Management module is a **complete implementation** of GT Nofollow Manager with additional enhancements:

### Core Features
- ✅ Automatic nofollow for external links (high priority 999)
- ✅ Applies to `the_content`, `widget_text`, and `comment_text`
- ✅ Smart exception system (URLs, domains, partial matches)
- ✅ Open links in new tab (external and internal)
- ✅ Fine-grained control with exception lists
- ✅ Pattern-based domain matching
- ✅ Proper `rel="noopener"` for security
- ✅ Internal link new-tab exceptions
- ✅ DOM-based content filtering
- ✅ Zero frontend footprint

### Advanced Features
- ✅ **JSON Preset Support**: Load exception URLs from JSON file
- ✅ **Database Update Tool**: Bulk add nofollow to specific URLs across all posts
- ✅ **Developer Filters**: Programmatic exception customization
- ✅ **Legacy Compatibility**: Supports original `gtnf_*` filter names

### JSON Preset Format

Create a file `exception-urls.json` in the plugin directory:

```json
{
  "urls": [
    "https://example.com/trusted-page",
    "https://partner-site.com",
    "https://another-trusted-site.com/blog"
  ]
}
```

### Developer Filters

```php
// Add exception domains (new)
add_filter( 'functionalities_exception_domains', function( $domains ) {
    $domains[] = 'trusted-site.com';
    return $domains;
});

// Add exception URLs (new)
add_filter( 'functionalities_exception_urls', function( $urls ) {
    $urls[] = 'https://example.com/page';
    return $urls;
});

// Custom JSON file path
add_filter( 'functionalities_json_preset_path', function( $path ) {
    return get_stylesheet_directory() . '/my-exceptions.json';
});

// Legacy GT Nofollow Manager compatibility
add_filter( 'gtnf_exception_domains', function( $domains ) {
    $domains[] = 'legacy-trusted.com';
    return $domains;
});

add_filter( 'gtnf_exception_urls', function( $urls ) {
    $urls[] = 'https://legacy-example.com';
    return $urls;
});
```

### Database Update Tool

Navigate to **Functionalities → Link Management** and scroll to the "Database Update Tool" section:

1. Enter the URL you want to add nofollow to
2. Click "Update Database"
3. Confirm the operation
4. The tool will scan all posts and add `rel="nofollow"` to matching links
5. Results show how many posts were updated

**Use with caution!** This directly modifies post content in the database.

## Localization

- Text domain: `functionalities`
- Language files path: `languages/`
- All strings wrapped in translation functions

## License

GPL-2.0-or-later

## Changelog

### Version 0.10.4 (Current)
- **FIX:** Translation loading triggered too early warning (WordPress 6.7+)
- Improved compatibility with latest WordPress standards

### Version 0.10.3
- **IMPROVED:** Article schema only added to `<article>` tag, skip fallback
- Better semantic schema markup

### Version 0.10.2
- Reordered Task Manager to top of admin menu
- Minor UI improvements

### Version 0.10.0
- **NEW:** Redirect Manager module for managing URL redirects
- **NEW:** Login Security module for enhanced login protection
- **IMPROVED:** Assumption Detection module enhancements
- **NEW:** GitHub Actions workflow for automated release zip builds
- Security and performance improvements across all modules

### Version 0.9.9
- **NEW:** Task Manager module for task tracking
- **FIX:** Translation loading improvements

### Version 0.9.8
- **REMOVED:** Unused frontend assets (assets/js/main.js and assets/css/style.css)
- **REMOVED:** Loader class that enqueued unused frontend assets
- Reduced plugin footprint - no frontend CSS/JS loaded by default
- Performance improvement: eliminated unnecessary asset loading on frontend

### Version 0.9.1

#### Link Management Enhancements

**JSON Preset File Path Improvements**
- Added file picker button using WordPress Media Library
- Added "Create JSON" button to create exception-urls.json directly in your theme
- Support for external URLs (with security warnings)
- Automatic detection of theme/child-theme exception-urls.json files
- Priority order: Custom path → Developer filter → Child theme → Parent theme → Plugin default
- Visual notifications when theme JSON files are detected
- Security warning displayed for external URL usage

**Developer Filters Code Snippets**
- Added expandable code snippets section with ready-to-copy examples:
  - Add Exception Domains
  - Add Exception URLs
  - Dynamic Exceptions (role-based, conditional)
  - Custom JSON File Path
  - Legacy GT Nofollow Manager Compatibility
- One-click copy buttons for all code snippets

**Technical Improvements**
- New `get_json_content()` method supporting both local files and remote URLs
- New `is_valid_json_source()` validation helper
- AJAX handler for creating JSON files in theme directory
- Improved JSON loading with `wp_remote_get()` for external URLs
- JSON validation before file creation

### Version 0.9.0

#### New Modules

**Content Regression Detection**
- Detects structural regressions when posts are updated
- Compares each post against its own historical baseline
- **Internal Link Drop Detection**: Warns when internal links are accidentally removed
  - Configurable percentage threshold (default: 30%)
  - Configurable absolute threshold (default: 3 links)
  - Option to exclude nofollow links from detection
- **Word Count Regression**: Alerts when content is shortened significantly
  - Configurable drop percentage threshold (default: 35%)
  - Minimum content age requirement (default: 30 days)
  - Option to compare against historical average
  - Shortcode exclusion support
- **Heading Structure Analysis**: Checks accessibility issues
  - Missing H1 detection
  - Multiple H1 detection
  - Skipped heading level detection (e.g., H2 → H4)
- Rolling snapshot storage (configurable, default: 5 snapshots)
- Admin column showing regression status
- Block editor integration with pre-publish warnings
- REST API endpoint for editor integration
- Navigate to: `?page=functionalities&module=content-regression`

**Assumption Detection**
- Monitors when technical assumptions stop being true
- Philosophy: "This used to be true. Now it isn't." (Not optimization advice)
- **Schema Collision Detection**: Notices when multiple JSON-LD sources appear
  - Scans wp_head and wp_footer for JSON-LD scripts
  - Identifies conflicting schema from plugins, themes, or manual additions
  - Reports all detected sources for review
- **Analytics Duplication Detection**: Finds duplicate tracking IDs
  - Detects same GA4 Measurement ID from multiple sources
  - Detects same GTM Container ID loaded multiple times
  - Detects same Facebook Pixel ID from different plugins
  - Reports which sources are loading each ID
- **Font Redundancy Detection**: Notices same font from multiple sources
  - Scans for @font-face declarations and Google Fonts links
  - Identifies when same font family is loaded from different sources
  - Helps eliminate redundant font loading
- **Inline CSS Growth Tracking**: Monitors performance debt
  - Establishes baseline inline CSS size
  - Alerts when inline CSS exceeds threshold (configurable, default: 50KB)
  - Tracks growth over time
- Admin dashboard UI for reviewing detected assumptions
- Acknowledge/Ignore actions for each detected issue
- AJAX-powered interaction
- Navigate to: `?page=functionalities&module=assumption-detection`

#### Admin UI Improvements

**Documentation Accordions**
- Converted inline colored documentation boxes to accessible `<details>/<summary>` elements
- WordPress-native styling with color-coded border accents:
  - Green border: Feature descriptions ("What This Module Does")
  - Yellow border: Usage tips and philosophy ("How to Use", "Philosophy")
  - Red border: Warnings and cautions
  - Blue border: Developer documentation ("Developer Hooks")
- Collapsible by default to reduce visual clutter
- Improved accessibility with semantic HTML

**New Helper Classes**
- `Admin_UI`: Reusable UI components for documentation rendering
  - `render_docs_section()`: Renders consistent accordion sections
  - Type parameter controls border accent color
  - Open parameter controls initial state
- `Module_Docs`: Centralized module documentation configuration
  - All module features, usage info, and hooks in one place
  - `get()`: Retrieve docs for specific module
  - `get_all()`: Retrieve all module documentation
  - Easier maintenance and consistency

#### New Files
- `includes/features/class-content-regression.php` - Content Regression Detection module
- `includes/features/class-assumption-detection.php` - Assumption Detection module
- `includes/admin/class-admin-ui.php` - Reusable UI helper class
- `includes/admin/class-module-docs.php` - Centralized documentation configuration
- `assets/js/content-regression.js` - Block editor integration for regression detection
- `assets/css/content-regression.css` - Regression warning styles

#### New Hooks and Filters

**Content Regression Detection**
- `functionalities_regression_enabled` - Toggle regression detection (filter)
- `functionalities_regression_post_types` - Modify enabled post types (filter)
- `functionalities_regression_warnings` - Modify detected warnings (filter)
- `functionalities_regression_snapshot_saved` - Fires after snapshot is saved (action)
- `functionalities_regression_link_threshold` - Customize link drop threshold (filter)
- `functionalities_regression_word_threshold` - Customize word count threshold (filter)

**Assumption Detection**
- `functionalities_assumptions_enabled` - Toggle assumption detection (filter)
- `functionalities_assumptions_detected` - Modify detected assumptions (filter)
- `functionalities_assumption_ignored` - Fires when assumption is ignored (action)
- `functionalities_assumption_acknowledged` - Fires when assumption is acknowledged (action)
- `functionalities_schema_collision_sources` - Modify schema source detection (filter)
- `functionalities_analytics_patterns` - Customize analytics detection patterns (filter)
- `functionalities_font_detection_enabled` - Toggle font redundancy detection (filter)
- `functionalities_inline_css_threshold` - Customize CSS size threshold (filter)

#### Technical Improvements
- Reduced inline styles in admin interface
- Better separation of concerns with helper classes
- Improved code organization
- Enhanced accessibility with semantic HTML
- Performance optimizations for detection algorithms

---

### Version 0.8.1
- **NEW:** User-facing documentation in admin UI for all modules
- Each module settings page now includes:
  - "What This Module Does" explanation boxes
  - "How to Use" or "Caution" guidance boxes
  - "For Developers" section with available filters and actions
- Styled documentation boxes with color-coded sections:
  - Green: Feature descriptions
  - Yellow/Amber: Usage tips and warnings
  - Blue: Developer hooks and filters
- Improved user experience for non-technical users

### Version 0.8.0
- **MAJOR:** Comprehensive inline documentation for all modules
- Added 50+ hooks and filters for developer extensibility
- Removed old zip file from repository root
- Every feature module now includes:
  - PHPDoc file headers with package info
  - Class documentation with features list
  - Method documentation with @since tags
  - Filter documentation with examples
  - Action documentation for extensibility

#### New Filters (0.8.0)
- `functionalities_enqueue_style` - Control main stylesheet loading
- `functionalities_enqueue_script` - Control main script loading
- `functionalities_block_cleanup_enabled` - Toggle block cleanup
- `functionalities_block_cleanup_content` - Filter cleaned content
- `functionalities_editor_links_enabled` - Toggle editor link filtering
- `functionalities_editor_links_post_types` - Customize allowed post types
- `functionalities_snippets_output_enabled` - Toggle snippet output
- `functionalities_snippets_ga4_enabled` - Control GA4 output
- `functionalities_snippets_header_code` - Filter header code
- `functionalities_snippets_footer_code` - Filter footer code
- `functionalities_components_enabled` - Toggle components CSS
- `functionalities_components_items` - Modify component items
- `functionalities_components_css` - Filter generated CSS
- `functionalities_fonts_enabled` - Toggle font CSS output
- `functionalities_fonts_items` - Modify font definitions
- `functionalities_fonts_css` - Filter generated @font-face CSS
- `functionalities_icons_remove_fa_enabled` - Toggle FA removal
- `functionalities_icons_convert_enabled` - Toggle FA to SVG conversion
- `functionalities_icons_sprite_url` - Customize sprite URL
- `functionalities_schema_enabled` - Toggle schema output
- `functionalities_schema_site_itemtype` - Customize site schema type
- `functionalities_schema_article_itemtype` - Customize article schema type
- `functionalities_misc_option_{$key}` - Filter individual misc options
- `functionalities_misc_prism_theme_url` - Customize Prism.js theme

#### New Actions (0.8.0)
- `functionalities_before_enqueue_assets` - Before asset enqueuing
- `functionalities_after_enqueue_assets` - After asset enqueuing
- `functionalities_before_header_snippets` - Before header output
- `functionalities_after_header_snippets` - After header output
- `functionalities_before_footer_snippets` - Before footer output
- `functionalities_after_footer_snippets` - After footer output
- `functionalities_components_before_output` - Before components CSS
- `functionalities_components_updated` - When CSS file regenerates
- `functionalities_fonts_before_output` - Before fonts CSS
- `functionalities_schema_before_buffer` - Before schema buffering
- `functionalities_misc_init` - After misc module initialization

### Version 0.7.1
- Set default GitHub repository values to `wpgaurav/functionalities`
- Simplified GitHub Updates module configuration for this plugin

### Version 0.7.0
- **MAJOR:** Complete redesign of Components module UI
- Beautiful grid-based card layout with live CSS previews
- Pagination with 24 components per page for better organization
- Inline editing with real-time preview updates
- Modern visual design with hover effects and transitions
- Add/Edit/Delete functionality with intuitive controls
- Template-based new component creation

### Version 0.6.1
- Removed example shortcode `[functionalities_hello]`
- Fixed Meta module to work without SEO plugins (standalone JSON-LD output)
- Maintained compatibility with all supported SEO plugins

### Version 0.6.0
- Added GitHub Updates module for automatic plugin updates from GitHub releases
- Support for public and private repositories
- Configurable cache duration to avoid API rate limits

### Version 0.5.0
- **NEW:** Meta & Copyright module with comprehensive SEO plugin integration
- Dublin Core (DCMI) metadata support
- Creative Commons licensing options
- Automatic schema.org copyrightYear and copyrightHolder
- SEO plugin integration: Rank Math, Yoast SEO, The SEO Framework, SEOPress, AIOSEO
- Performance-optimized with static caching

### Version 0.4.0
- **MAJOR:** Complete GT Nofollow Manager integration with full feature parity
- Added filters to `widget_text` and `comment_text` (priority 999)
- Added JSON preset file support for bulk exception loading
- Added database update tool for bulk nofollow operations
- Added developer filters (functionalities_* and gtnf_* for legacy compatibility)
- Added AJAX handler for database updates with nonce verification
- Added sample JSON file (exception-urls.json.sample)
- Enhanced Link Management admin interface with new fields
- Improved exception caching for better performance
- Added comprehensive documentation for all GT Nofollow Manager features

### Version 0.3.0
- **MAJOR:** Refactored admin interface to module-based dashboard
- **MAJOR:** Implemented URL parameter navigation (removed separate submenus)
- Improved WordPress Coding Standards compliance
- Added comprehensive PHPDoc documentation
- Extracted inline callbacks to named methods
- Created beautiful dashboard with module cards
- Added breadcrumb navigation
- Improved Link Management module (GT Nofollow Manager features)
- Enhanced security and sanitization
- Better code organization and structure

### Version 0.2.2
- Previous stable version with submenu-based navigation
