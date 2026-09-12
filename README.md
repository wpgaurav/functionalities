# Dynamic Functionalities

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-FFDD00?style=flat&logo=buymeacoffee&logoColor=black)](https://buymeacoffee.com/gauravtiwari)

All-in-one WordPress optimization toolkit with 16 modules for performance, security, SEO, and content management. Built with modern WordPress coding standards and a clean module-based dashboard. Optimized for performance with modular initialization, static property caching, and intelligent transients.

**Version:** 1.6.1
**Requires WordPress:** 6.3 or later (tested up to 7.1)
**Requires PHP:** 7.4 or later
**License:** GPL-2.0-or-later
**Text Domain:** `functionalities`
**Pricing:** Free

## Installation

1. **Download:** Get the latest production-ready ZIP file from [GitHub Releases](https://github.com/wpgaurav/functionalities/releases).
2. **Upload:** In your WordPress admin, go to **Plugins > Add New > Upload Plugin** and select the downloaded file.
3. **Activate:** Activate the plugin through the **Plugins** menu in WordPress.
4. **Setup:** Navigate to the new **Functionalities** menu item to enable and configure your modules.

*Alternatively, you can manually copy the `functionalities/` folder into `wp-content/plugins/`.*

All modules are accessed through a unified dashboard at `wp-admin/admin.php?page=functionalities`. Click any module card to configure its settings.

## Documentation

Full documentation is at **[functionalities.dev](https://functionalities.dev/)**:

| | |
|---|---|
| [Getting started](https://functionalities.dev/docs/getting-started) | Install, enable a module, verify it works |
| [Module reference](https://functionalities.dev/modules) | What each of the 16 modules does |
| [Dashboard](https://functionalities.dev/docs/dashboard) | Working with the module dashboard |
| [Hooks](https://functionalities.dev/docs/hooks) | Every action and filter the plugin fires |
| [API reference](https://functionalities.dev/docs/api-reference) | Extending the plugin in code |
| [FAQ](https://functionalities.dev/faq) | Common questions |
| [Downloads](https://functionalities.dev/download) | Current and previous releases |

Per-module guides: [Link Management](https://functionalities.dev/docs/link-management) · [Redirect Manager](https://functionalities.dev/docs/redirect-manager) · [Login Security](https://functionalities.dev/docs/login-security) · [Performance & Cleanup](https://functionalities.dev/docs/performance) · [Schema](https://functionalities.dev/docs/schema) · [Snippets](https://functionalities.dev/docs/snippets) · [SVG Icons](https://functionalities.dev/docs/svg-icons) · [Block Cleanup](https://functionalities.dev/docs/block-cleanup) · [Content Integrity](https://functionalities.dev/docs/content-regression) · [Assumption Detection](https://functionalities.dev/docs/assumption-detection) · [Task Manager](https://functionalities.dev/docs/task-manager)

See the [public roadmap](ROADMAP.md) for planned fixes and features.

---

## Performance & Footprint

This plugin is built with a "Performance First" philosophy. Unlike many all-in-one plugins that slow down your site, Functionalities is designed to be as lightweight as possible:

- **Modular Initialization:** Each module checks its enabled state before registering its feature hooks. Disabled modules add no frontend assets or feature behavior.
- **Minimized Database Load:** All module settings are cached in static properties. This ensures that `get_option()` is called at most once per module per request, regardless of how many times a feature is accessed.
- **Zero Frontend Bloat:** Most modules are "Zero Footprint" on the frontend, meaning they load no CSS or JS unless explicitly required (like the Components or Fonts modules).
- **Intelligent Filtering:** Content filters (`the_content`, etc.) use `strpos()` fast-exit checks. If the specific markers or tags for a feature aren't present in your content, the plugin exits immediately without running expensive parsing.
- **Efficient HTML Processing:** Since 1.6.0, Link Management, Block Cleanup, and Schema use the WordPress HTML API (`WP_HTML_Tag_Processor`) to edit attributes in place. Nothing is reserialized, so Vue, Alpine, and mustache templates survive untouched and no framework skip guard is needed.
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
- Typography assignments for body and headings via theme.json data layer
- Native Bricks Builder integration — fonts appear in the Bricks typography picker

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
- Standalone metadata-driven SVG Icon block with alignment, native text color, spacing, anchor, and custom class support
- Pixel, `em`, and `rem` sizing with bounded values
- Monochrome or original-color rendering
- Decorative or informative accessibility modes with custom labels
- Searchable, paginated, keyboard-accessible icon picker with recent icons first
- WordPress 7 Core Icon library source and two-way transforms with the Core Icon block
- Pattern overrides and block bindings for icon names and accessibility labels
- Reusable Icon Callout pattern
- Zero frontend footprint when no icons are used

**Navigate to:** `?page=functionalities&module=svg-icons`

---

### WordPress 7 Integration

WordPress 7 sites gain progressive platform integrations without adding frontend assets:

- Abilities API operations for diagnostics, module controls, redirects, tasks, assumption scans, and content checks
- DataViews and DataForm workspaces for Redirect Manager, bounded 404 activity, and Task Manager
- Command Palette shortcuts for common Functionalities actions
- Optional WordPress AI Client explanations for individual Assumption Detection and Content Integrity findings
- Core Icon library interoperability in the SVG Icon block

AI explanations are disabled by default. When enabled, the plugin sends only the finding an administrator explicitly submits to the site's configured WordPress AI provider.

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
- CSV import/export with a validated dry-run preview
- Optional bounded 404 aggregation without visitor identifiers or full referrers

**Navigate to:** `?page=functionalities&module=redirect-manager`

---

### Login Security

Enhanced login protection and security measures for your WordPress site.

**Features:**
- Limit login attempts to prevent brute force attacks
- Per-username throttling, so a distributed attempt against one account is caught
- IP allowlist, so a shared address behind a CDN cannot lock you out of your own site
- Unlock any address or username directly from the lockout log
- Configurable lockout durations
- Disable XML-RPC authentication and application passwords
- Hide detailed login errors to prevent user enumeration
- Custom login page logo and background styling

**Navigate to:** `?page=functionalities&module=login-security`

---

### Progressive Web App

Make the site installable with a web app manifest, service worker, offline fallback, and optional install prompt.

**Features:**
- Configurable app name, colors, icons, display mode, and orientation
- Offline page and versioned runtime caching
- App shortcuts, screenshots, and advanced manifest fields
- Optional install prompt and Web Share Target support
- Root-level manifest and service worker endpoints

**Navigate to:** `?page=functionalities&module=pwa`

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
│   ├── blocks/svg-icon/        Block metadata for the SVG Icon block
│   ├── css/                    admin, admin-ui, content-regression, svg-icons-editor
│   ├── js/                     admin*, content-regression, svg-icons-editor, wp7-*
│   └── vendor/prism/           Bundled Prism.js (MIT), admin syntax highlighting
├── includes/
│   ├── admin/
│   │   ├── class-admin.php                             Thin entry point
│   │   ├── class-admin-ui.php                          Shared UI helpers
│   │   ├── class-module-controller.php                 Settings + custom pages
│   │   ├── class-module-docs.php                       Per-module docs text
│   │   ├── class-settings-portability-controller.php   Export / import / diagnostics
│   │   ├── class-site-health-controller.php            Scans, schedules, exposure probe
│   │   ├── class-redirect-manager-controller.php
│   │   ├── class-svg-icons-controller.php
│   │   ├── class-task-manager-controller.php
│   │   ├── trait-admin-ajax.php
│   │   ├── trait-admin-options.php
│   │   └── trait-admin-sanitizers.php
│   ├── core/
│   │   ├── class-module-registry.php                   Module list + lazy loader
│   │   └── class-wordpress-7-integration.php           Abilities, DataViews, AI
│   ├── features/                                       One class per module (16)
│   ├── storage/
│   │   ├── class-atomic-json-store.php                 Locked, atomic JSON writes
│   │   └── class-data-directory.php                    Private data path + hardening
│   └── traits/
│       └── trait-css-sanitizer.php
├── languages/
├── src/                        Source for the WordPress 7 admin bundle (not shipped)
├── tests/                      PHPUnit suite
├── docs/                       Performance baseline notes (not shipped)
├── exception-urls-sample.json
├── functionalities.php
├── index.php
└── uninstall.php
```

---

## Adding New Modules

1. Create a feature class in `includes/features/class-your-module.php`
2. Add its definition to `Core\Module_Registry::get_definitions()`
3. Register its settings in the module controller
4. Add focused tests for defaults and any pure helpers

## Local development checks

```bash
composer install
composer lint
composer phpcs
composer test
node --check assets/js/admin.js
bash -n build.sh
./build.sh
```

The pull-request workflow runs PHP syntax checks on PHP 7.4 through 8.5, coding standards, PHPUnit, JavaScript and shell syntax, version consistency, and distribution ZIP assertions.

Example module definition:

```php
'your-module' => array(
    'title'       => __( 'Your Module', 'functionalities' ),
    'description' => __( 'Brief description', 'functionalities' ),
    'icon'        => 'dashicons-admin-generic',
),
```

## Support This Project

Functionalities is a free and open source WordPress plugin with 16 modules for performance, security, SEO and content management, each behind its own toggle. For 1.6.0 I moved Link Management, Block Cleanup and Schema to the WordPress HTML API so they edit attributes in place and leave Vue and Alpine templates alone.

If it replaced a separate redirect manager or a header and footer snippets plugin on your site, you can buy me a coffee.

<a href="https://buymeacoffee.com/gauravtiwari"><img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy me a coffee" height="50"></a>

A star on the repo helps and so does an issue that lists your WordPress and PHP versions, the module you had switched on and the steps that led to the bug.

---

See [readme.txt](readme.txt) for the full changelog.
