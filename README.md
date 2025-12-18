# Functionalities (WordPress Plugin)

A modular site-specific plugin to organize common features with simple toggles. Refactored with modern WordPress coding standards and a beautiful module-based dashboard.

## Features

### Core
- Safe plugin bootstrap with constants
- Example shortcode: `[functionalities_hello name="World"]`
- Asset enqueues for CSS/JS
- **NEW:** Module-based admin interface with dashboard cards
- **NEW:** URL parameter navigation (no more separate submenu pages)
- **NEW:** Improved WordPress Coding Standards compliance
- **NEW:** Comprehensive PHPDoc documentation

### Modules

**Link Management** (GT Nofollow Manager Features)
- Automatic `rel="nofollow"` for external links with smart exception handling
- Open external/internal links in new tab with fine-grained control
- Exception lists supporting full URLs, domains, or partial matches
- Pattern-based domain matching
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
4. Optional: Add the shortcode to a post or page: `[functionalities_hello name="Alice"]`.

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

## GT Nofollow Manager Features

The Link Management module includes all essential features found in GT Nofollow Manager:

- ✅ Automatic nofollow for external links
- ✅ Smart exception system (URLs, domains, partial matches)
- ✅ Open links in new tab (external and internal)
- ✅ Fine-grained control with exception lists
- ✅ Pattern-based domain matching
- ✅ Proper `rel="noopener"` for security
- ✅ Internal link new-tab exceptions
- ✅ DOM-based content filtering

## Localization

- Text domain: `functionalities`
- Language files path: `languages/`
- All strings wrapped in translation functions

## License

GPL-2.0-or-later

## Changelog

### Version 0.3.0 (Current)
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
