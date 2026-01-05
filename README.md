# Functionalities

A modular site-specific plugin that organizes common WordPress features with simple toggles. Built with modern WordPress coding standards and a clean module-based dashboard.

**Version:** 0.10.4  
**License:** GPL-2.0-or-later  
**Text Domain:** `functionalities`

## Installation

1. Copy the `functionalities/` folder into `wp-content/plugins/`
2. Activate in wp-admin under **Plugins**
3. Navigate to **Functionalities** in the admin menu

All modules are accessed through a unified dashboard at `wp-admin/admin.php?page=functionalities`. Click any module card to configure its settings.

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

### Miscellaneous (Bloat Control)

Fine-grained control over WordPress default behaviors:

- Disable emojis scripts/styles
- Disable embeds (oEmbed)
- Remove REST API and oEmbed discovery links
- Remove RSD, WLWManifest, shortlink tags
- Remove WordPress version meta
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

### Icons

Replace Font Awesome with SVG `<use>` icons:

- Remove Font Awesome assets to reduce page weight
- Custom SVG sprite URL
- Configurable class-to-symbol mappings

**Navigate to:** `?page=functionalities&module=icons`

---

### Content Regression Detection

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

Simple task tracking for content and development workflows within WordPress admin.

**Navigate to:** `?page=functionalities&module=task-manager`

---

### Redirect Manager

Manage URL redirects directly from WordPress admin. Supports 301 and 302 redirects.

**Navigate to:** `?page=functionalities&module=redirect-manager`

---

### Login Security

Enhanced login protection with limited login attempts and additional security measures.

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

## File Structure

```
functionalities/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   ├── admin-ui.css
│   │   └── content-regression.css
│   └── js/
│       ├── admin.js
│       ├── admin-ui.js
│       └── content-regression.js
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
└── functionalities.php
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

### 0.10.4 (Current)
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
