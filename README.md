# Functionalities

A modular site-specific plugin that organizes common WordPress features with simple toggles. Built with modern WordPress coding standards and a clean module-based dashboard. Optimized for performance with lazy-loading, static property caching, and intelligent transients.

**Version:** 0.14.4  
**License:** GPL-2.0-or-later  
**Text Domain:** `functionalities`

## Installation

1. **Download:** Get the latest production-ready ZIP file from [GitHub Releases](https://github.com/wpgaurav/functionalities/releases).
2. **Upload:** In your WordPress admin, go to **Plugins > Add New > Upload Plugin** and select the downloaded file.
3. **Activate:** Activate the plugin through the **Plugins** menu in WordPress.
4. **Setup:** Navigate to the new **Functionalities** menu item to enable and configure your modules.

*Alternatively, you can manually copy the `functionalities/` folder into `wp-content/plugins/`.*

All modules are accessed through a unified dashboard at `wp-admin/admin.php?page=functionalities`. Click any module card to configure its settings.

Learn more on [Functionalities Site](https://functionalities.dev)
---

## Performance & Footprint

This plugin is built with a "Performance First" philosophy. Unlike many all-in-one plugins that slow down your site, Functionalities is designed to be as lightweight as possible:

- **Modular & Lazy Loaded:** Using a custom autoloader, the plugin only loads the code required for active modules. If a feature is disabled, its code is never even included in memory.
- **Minimized Database Load:** All module settings are cached in static properties. This ensures that `get_option()` is called at most once per module per request, regardless of how many times a feature is accessed.
- **Zero Frontend Bloat:** Most modules are "Zero Footprint" on the frontend, meaning they load no CSS or JS unless explicitly required (like the Components or Fonts modules).
- **Intelligent Filtering:** Content filters (`the_content`, etc.) use `strpos()` fast-exit checks. If the specific markers or tags for a feature aren't present in your content, the plugin exits immediately without running expensive regular expressions or DOM parsing.
- **Efficient HTML Processing:** We use targeted regex for lightweight tasks (like Schema injection) and only resort to `DOMDocument` when structural manipulation is strictly necessary, ensuring maximum speed.
- **Aggressive Caching:** Heavy operations—such as reading JSON exception lists, calculating file hashes, or managing redirects—are cached using WordPress Transients or versioned options to minimize Disk I/O.

---

## Performance & Footprint

This plugin is built with a "Performance First" philosophy. Unlike many all-in-one plugins that slow down your site, Functionalities is designed to be as lightweight as possible:

- **Modular & Lazy Loaded:** Using a custom autoloader, the plugin only loads the code required for active modules. If a feature is disabled, its code is never even included in memory.
- **Minimized Database Load:** All module settings are cached in static properties. This ensures that `get_option()` is called at most once per module per request, regardless of how many times a feature is accessed.
- **Zero Frontend Bloat:** Most modules are "Zero Footprint" on the frontend, meaning they load no CSS or JS unless explicitly required (like the Components or Fonts modules).
- **Intelligent Filtering:** Content filters (`the_content`, etc.) use `strpos()` fast-exit checks. If the specific markers or tags for a feature aren't present in your content, the plugin exits immediately without running expensive regular expressions or DOM parsing.
- **Efficient HTML Processing:** We use targeted regex for lightweight tasks (like Schema injection) and only resort to `DOMDocument` when structural manipulation is strictly necessary, ensuring maximum speed.
- **Aggressive Caching:** Heavy operations—such as reading JSON exception lists, calculating file hashes, or managing redirects—are cached using WordPress Transients or versioned options to minimize Disk I/O.

---

## Modules

### Link Management

Complete external link control with nofollow automation.

**Features:**
- Automatic `rel="nofollow"` for external links (priority 999)
- Applies to content, widgets, and comments
- Exception lists: full URLs, domains, or partial matches
- JSON preset file support for bulk exceptions
- Database update tool for bulk nofollow addition
- Open external/internal links in new tab
- Pattern-based domain matching
- Zero frontend footprint (no CSS/JS)

**Navigate to:** `?page=functionalities&module=link-management`

---

### Block Cleanup

Strip common wp-block classes from frontend output (`.wp-block-heading`, `.wp-block-list`, `.wp-block-image`). Cleaner HTML markup without the bloat.

**Navigate to:** `?page=functionalities&module=block-cleanup`

---

### Editor Link Suggestions

Limit link suggestions to selected post types in the block editor. Reduces clutter in link search dialogs.

**Navigate to:** `?page=functionalities&module=editor-links`

---

### Performance & Cleanup

Fine-grained control over WordPress default behaviors and performance tweaks:

- Disable emojis scripts/styles
- Disable embeds (oEmbed)
- Remove REST API and oEmbed discovery links
- Remove RSD, WLWManifest, shortlink tags
- Remove WordPress version meta
- Disable XML-RPC (complete or pingbacks only)
- Disable RSS/Atom feeds
- Disable Gravatars
- Disable self-pingbacks
- Remove query strings from static resources
- Remove DNS prefetch
- Remove Recent Comments inline CSS
- Limit post revisions
- Disable Dashicons for non-logged-in users
- Disable Heartbeat API
- Disable admin bar on frontend
- Remove jQuery Migrate
- Load core block styles separately (per-block CSS)
- Disable block-based widget editor
- Enable PrismJS on admin screens
- Enable fullscreen toggle for backend textareas

**Navigate to:** `?page=functionalities&module=misc`

---

### Header & Footer Snippets

- Google Analytics 4 integration (just enter Measurement ID)
- Custom header code injection
- Custom footer code injection
- Safe sanitization with `wp_kses` for non-superadmins

**Navigate to:** `?page=functionalities&module=snippets`

---

### Schema Settings

Add microdata to your site's HTML:

- `itemscope`/`itemtype` on `<html>` tag
- Optional WPHeader and WPFooter microdata
- Article microdata with customizable itemtype
- Automatic headline, dates, and author properties

**Navigate to:** `?page=functionalities&module=schema`

---

### Components

Define reusable CSS components as selector + CSS rules. Auto-enqueued site-wide.

Default components include: cards, buttons, badges, chips, alerts, avatars, grids, accordions.

**Navigate to:** `?page=functionalities&module=components`

---

### Fonts

Register custom font families with @font-face:

- WOFF2 and WOFF support
- Variable fonts support
- Font-display control (swap, auto, block, fallback, optional)

**Navigate to:** `?page=functionalities&module=fonts`

---

### Meta & Copyright

Copyright, Dublin Core, licensing, and SEO plugin integration.

**Features:**
- Automatic copyright meta tags and Dublin Core metadata
- Creative Commons licensing integration
- Standalone Schema.org output for copyright/license information
- Integration with popular SEO plugins for unified metadata

**Navigate to:** `?page=functionalities&module=meta`

---

### SVG Icons

Upload custom SVG icons and insert them inline in the block editor.

**Features:**
- Custom SVG icon library with secure sanitization
- Inline insertion via RichText toolbar (inherits surrounding font size)
- Standalone SVG Icon block with alignment, size, and color controls
- Zero frontend footprint when no icons are used

**Navigate to:** `?page=functionalities&module=svg-icons`

---

### GitHub Updates

Receive plugin updates directly from GitHub releases—a clever trick that brings the convenience of the WordPress.org directory to your custom site-specific plugin. Once enabled, updates are delivered directly to your WordPress dashboard just like any other plugin.

**Features:**
- Automatic update checks against GitHub repository releases
- Native WordPress update notifications and one-click upgrades
- Supports private repositories via access tokens
- Configurable cache duration for update checks

**Navigate to:** `?page=functionalities&module=updates`

---

### Content Integrity

Detect structural regressions when posts are updated.

**Internal Link Drop Detection:**
- Warns when internal links are accidentally removed
- Configurable percentage threshold (default: 30%)
- Configurable absolute threshold (default: 3 links)
- Option to exclude nofollow links

**Word Count Regression:**
- Alerts when content is shortened significantly
- Configurable drop percentage (default: 35%)
- Minimum content age requirement (default: 30 days)
- Shortcode exclusion support

**Heading Structure Analysis:**
- Missing H1 detection
- Multiple H1 detection
- Skipped heading level detection (e.g., H2 → H4)

Also includes: rolling snapshot storage, admin column for regression status, block editor integration with pre-publish warnings.

**Navigate to:** `?page=functionalities&module=content-regression`

---

### Assumption Detection

Monitor when technical assumptions stop being true. Philosophy: "This used to be true. Now it isn't."

**Detects:**
- Schema collisions (multiple JSON-LD sources)
- Analytics duplication (GA4, GTM, Facebook Pixel)
- Font redundancy (same font from multiple sources)
- Inline CSS growth (performance debt monitoring)

Dashboard UI with acknowledge/ignore actions for each detected issue.

**Navigate to:** `?page=functionalities&module=assumption-detection`

---

### Task Manager

Simple, file-based project task management for content and development workflows within WordPress admin.

**Features:**
- Track tasks directly in the WordPress dashboard
- Stored in a portable JSON file for version control friendliness
- Organized by status and priority

**Navigate to:** `?page=functionalities&module=task-manager`

---

### Redirect Manager

Manage URL redirects directly from WordPress admin with high-performance file-based storage.

**Features:**
- Supports 301, 302, 307, and 308 redirects
- File-based JSON storage for zero database overhead during redirects
- Integrated hit counter for tracking redirect usage
- Normalized path matching

**Navigate to:** `?page=functionalities&module=redirect-manager`

---

### Login Security

Enhanced login protection and security measures for your WordPress site.

**Features:**
- Limit login attempts to prevent brute force attacks
- Configurable lockout durations
- Disable XML-RPC authentication and application passwords
- Hide detailed login errors to prevent user enumeration
- Custom login page logo and background styling

**Navigate to:** `?page=functionalities&module=login-security`

---

## Link Management: Developer Reference

### JSON Preset Format

Create `exception-urls.json` in your theme or plugin directory:

```json
{
  "urls": [
    "https://example.com/trusted-page",
    "https://partner-site.com",
    "https://another-trusted-site.com/blog"
  ]
}
```

Priority order: Custom path → Developer filter → Child theme → Parent theme → Plugin default

### Developer Filters

```php
// Add exception domains
add_filter( 'functionalities_exception_domains', function( $domains ) {
    $domains[] = 'trusted-site.com';
    return $domains;
});

// Add exception URLs
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
```

### Database Update Tool

Navigate to **Link Management** and scroll to "Database Update Tool":

1. Enter the URL you want to add nofollow to
2. Click "Update Database"
3. Confirm the operation
4. Results show how many posts were updated

**Caution:** This directly modifies post content in the database.

---

## SVG Icons: Developer Reference

### Shortcode

You can render any icon from your library using the `[func_icon]` shortcode.

```text
[func_icon name="car" class="my-custom-class"]
```

**Attributes:**
- `name` (required): The slug of the icon as defined in the SVG Icons library.
- `class` (optional): Additional CSS classes to add to the `<svg>` element.

### Developer Filters

```php
// Disable the SVG Icons module via code
add_filter( 'functionalities_svg_icons_enabled', '__return_false' );

// Filter the list of available icons
add_filter( 'functionalities_svg_icons_list', function( $icons ) {
    // Modify $icons array
    return $icons;
});

// Filter sanitized SVG content before it is saved to the database
add_filter( 'functionalities_svg_icons_sanitize', function( $svg, $slug ) {
    return $svg;
}, 10, 2 );
```

---

## File Structure

```
functionalities/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── admin-ui.css
│   │   ├── content-regression.css
│   │   └── svg-icons-editor.css
│   └── js/
│       ├── admin.js
│       ├── admin-ui.js
│       ├── content-regression.js
│       └── svg-icons-editor.js
├── includes/
│   ├── admin/
│   │   ├── class-admin.php
│   │   ├── class-admin-ui.php
│   │   └── class-module-docs.php
│   ├── features/
│   │   ├── class-assumption-detection.php
│   │   ├── class-block-cleanup.php
│   │   ├── class-components.php
│   │   ├── class-content-regression.php
│   │   ├── class-editor-links.php
│   │   ├── class-fonts.php
│   │   ├── class-link-management.php
│   │   ├── class-login-security.php
│   │   ├── class-meta.php
│   │   ├── class-misc.php
│   │   ├── class-redirect-manager.php
│   │   ├── class-schema.php
│   │   ├── class-snippets.php
│   │   ├── class-svg-icons.php
│   │   └── class-task-manager.php
│   └── class-github-updater.php
├── languages/
├── exception-urls.json.sample
├── functionalities.php
├── index.php
└── uninstall.php
```

---

## Adding New Modules

1. Create a feature class in `includes/features/class-your-module.php`
2. Add module definition in `Admin::define_modules()`
3. Register settings in `Admin::register_settings()`
4. Initialize in `functionalities.php`

Example module definition:

```php
'your-module' => array(
    'title'       => __( 'Your Module', 'functionalities' ),
    'description' => __( 'Brief description', 'functionalities' ),
    'icon'        => 'dashicons-admin-generic',
),
```

---

## Changelog

### 0.14.4 (Current)
- **Fixed**: Removed `contenteditable` attribute from SVG icon placeholder.
- **Fixed**: Simplified icon insertion logic in editor.

### 0.14.3
- **Fixed**: SVG icon closing tag issue - now uses visible bullet placeholder hidden by CSS.

### 0.14.2
- **Fixed**: SVG icon tags now properly close in Gutenberg editor using zero-width space technique.
- **Changed**: SVG icons now use `<i>` tag instead of `<span>` (standard for icons, better Gutenberg compatibility).
- **Improved**: Backward compatibility maintained for legacy `<span>` icon tags.

### 0.14.1
- **Fixed**: SVG icons now render correctly when multiple icons are in one paragraph.
- **Fixed**: SVG icon span tags are now properly closed, resolving "Block contains unexpected or invalid content" errors in the editor.
- **Fixed**: Link Management JSON file exceptions now correctly exclude URLs from nofollow on the frontend.
- **Improved**: Task Manager responsive grid layout adapts to different device sizes.

### 0.14.0
- **Fonts Module UI Overhaul**: Completely rebuilt the Fonts management interface with a modern card-based design.
  - Dynamic add/remove functionality for unlimited custom fonts
  - WordPress Media Uploader integration for WOFF2/WOFF file uploads
  - Expandable cards with Edit/Delete actions and live preview badges
  - Real-time card title and badge updates as you configure each font
  - Empty state with helpful instructions for new users
- Changed: Components module now defaults to disabled on new installations.

### 0.13.0
- Added new features to **Performance & Cleanup** module:
  - Disable Gravatars site-wide
  - Disable self-pingbacks
  - Granular XML-RPC control (disable only pingbacks)
  - Remove query strings from static resources (`?ver=`)
  - Remove DNS prefetch links
  - Remove Recent Comments inline CSS
  - Limit post revisions to 10
- Added `wp_body_open` support to **Snippets** module for easier script placement.
- Added `BreadcrumbList` JSON-LD support to **Schema** module.
- Added font preloading option to **Fonts** module for better LCP.
- Improved **Link Management** JSON source handling (supports flat arrays and additional validation).
- Performance optimizations and code refinement across all modules.

### 0.12.2
- Improved: Task Manager UX with drag-and-drop reordering, filtering, and bulk actions.
- Added: Search bar and priority/status filters to Task Manager project view.
- Added: Clickable tags in Task Manager for instant filtering.
- Added: Keyboard shortcuts (Cmd/Ctrl + Enter) for adding and editing tasks.
- Improved: Task Manager UI with drag handles and auto-focus on entry.

### 0.12.1
- Fixed: Issue where JSON exception files using a flat array format were not correctly parsed in Link Management.
- Fixed: Improved robustness of JSON preset loading with better path validation and fallbacks.
- Improved: Link Management no longer caches empty exception results in transients, allowing for quicker recovery if a remote file is temporarily missing or invalid.

### 0.12.0
- Performance: Implemented a custom autoloader for lazy-loading module files.
- Performance: Added static property caching for options across all modules to minimize `get_option` calls.
- Performance: Replaced expensive `DOMDocument` parsing in Schema module with optimized regex.
- Performance: Added `strpos` fast-exit checks to all content filters (`the_content`, `widget_text`, etc.).
- Performance: Implemented transient-based caching for JSON presets in Link Management and Redirect Manager.
- Performance: Optimized Components module to use versioning in options, avoiding repeated disk I/O for file hash checks.
- Improved: Robustness of exception parsing in Link Management.

### 0.11.8
- Fixed: Issue where inline SVG icons were not rendering on the frontend if text was incorrectly placed inside the icon span in the editor.
- Improved: Robustness of icon replacement logic to handle non-empty placeholder spans.
- Improved: Icon insertion logic in the editor to further prevent text merging into atomic icons.

### 0.11.7
- Fixed: Issue where user was unable to type after inserting an inline SVG icon in the block editor.
- Improved: SVG icon format is now registered as an atomic object for better editor stability.

### 0.11.0
- Added: SVG Icons module with block and inline support.
- Registered `functionalities/svg-icon-block` block.
- Registered `functionalities/svg-icon` RichText format.

### 0.10.4
- Fixed translation loading warning for WordPress 6.7+

### 0.10.3
- Article schema now only added to `<article>` tag

### 0.10.0
- Added Redirect Manager module
- Added Login Security module
- GitHub Actions workflow for automated release builds

### 0.9.9
- Added Task Manager module

### 0.9.8
- Removed unused frontend assets (no CSS/JS loaded on frontend by default)

### 0.9.1
- Link Management: JSON preset file picker via Media Library
- "Create JSON" button for theme directory
- External URL support with security warnings
- Expandable code snippets with copy buttons

### 0.9.0
- Added Content Regression Detection module
- Added Assumption Detection module
- Admin UI improvements with documentation accordions
- New Admin_UI and Module_Docs helper classes

### 0.8.0
- Comprehensive inline documentation for all modules
- Added 50+ hooks and filters for extensibility

### 0.7.0
- Complete redesign of Components module UI
- Grid-based card layout with live CSS previews
- Pagination (24 components per page)

### 0.6.0
- Added GitHub Updates module for automatic updates from releases

### 0.5.0
- Added Meta & Copyright module with SEO plugin integration
- Dublin Core metadata and Creative Commons licensing

### 0.4.0
- Complete GT Nofollow Manager integration
- JSON preset support, database update tool, developer filters

### 0.3.0
- Refactored to module-based dashboard
- URL parameter navigation (removed separate submenus)
