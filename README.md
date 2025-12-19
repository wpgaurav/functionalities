# Functionalities (WordPress Plugin)

A modular site-specific plugin to organize common features with simple toggles. Refactored with modern WordPress coding standards and a beautiful module-based dashboard.

## Features

### Core
- Safe plugin bootstrap with constants
- Asset enqueues for CSS/JS
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
│   │   ├── admin.css      # Admin dashboard styles
│   │   └── style.css       # Frontend styles
│   └── js/
│       ├── admin.js        # Admin dashboard scripts
│       └── main.js         # Frontend scripts
├── includes/
│   ├── admin/
│   │   └── class-admin.php # Admin interface (refactored)
│   ├── features/
│   │   ├── class-link-management.php
│   │   ├── class-block-cleanup.php
│   │   ├── class-editor-links.php
│   │   ├── class-misc.php
│   │   ├── class-snippets.php
│   │   ├── class-schema.php
│   │   ├── class-components.php
│   │   ├── class-fonts.php
│   │   └── class-icons.php
│   └── class-functionalities-loader.php
├── languages/
└── functionalities.php     # Main plugin file
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

### Version 0.8.0 (Current)
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
