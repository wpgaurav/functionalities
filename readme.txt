=== Functionalities ===
Contributors: developer_developer
Tags: performance, seo, schema, redirection, utilities
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.15.6
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular site-specific plugin that organizes common WordPress features with simple toggles. Built for performance.

== Description ==

Functionalities is a modular site-specific plugin that organizes common WordPress features with simple toggles. Built with modern WordPress coding standards and a clean module-based dashboard. Optimized for performance with lazy-loading, static property caching, and intelligent transients.

= Performance First Philosophy =

Unlike many all-in-one plugins that slow down your site, Functionalities is designed to be as lightweight as possible:

* **Modular & Lazy Loaded:** Only loads the code required for active modules
* **Minimized Database Load:** All module settings are cached in static properties
* **Zero Frontend Bloat:** Most modules load no CSS or JS unless explicitly required
* **Intelligent Filtering:** Content filters use fast-exit checks
* **Aggressive Caching:** Heavy operations are cached using WordPress Transients

= Available Modules =

**Link Management**
Complete external link control with nofollow automation, exception lists, JSON preset file support, and database update tools.

**Block Cleanup**
Strip common wp-block classes from frontend output for cleaner HTML markup.

**Editor Link Suggestions**
Limit link suggestions to selected post types in the block editor.

**Performance & Cleanup**
Fine-grained control over WordPress default behaviors: disable emojis, embeds, REST API discovery links, XML-RPC, feeds, Gravatars, and more.

**Header & Footer Snippets**
Google Analytics 4 integration and custom header/footer code injection.

**Schema Settings**
Add microdata to your site's HTML with itemscope/itemtype support.

**Components**
Define reusable CSS components as selector + CSS rules. Auto-enqueued site-wide.

**Fonts**
Register custom font families with @font-face, WOFF2/WOFF support, and variable fonts.

**Meta & Copyright**
Copyright, Dublin Core, licensing, and SEO plugin integration.

**SVG Icons**
Upload custom SVG icons and insert them inline in the block editor.

**Content Integrity**
Detect structural regressions when posts are updated: internal link drops, word count regression, heading structure issues.

**Assumption Detection**
Monitor when technical assumptions stop being true: schema collisions, analytics duplication, font redundancy.

**Task Manager**
Simple, file-based project task management within WordPress admin.

**Redirect Manager**
Manage URL redirects with high-performance file-based storage. Supports 301, 302, 307, and 308 redirects.

**Login Security**
Enhanced login protection: limit login attempts, configurable lockout durations, disable XML-RPC auth, hide login errors.

== Installation ==

1. Upload the `functionalities` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to the new 'Functionalities' menu item to enable and configure your modules

All modules are accessed through a unified dashboard. Click any module card to configure its settings.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No! Functionalities is built with a "Performance First" philosophy. It uses lazy-loading, static caching, and fast-exit checks to minimize any performance impact.

= Can I use only specific modules? =

Yes, each module can be enabled or disabled independently. Disabled modules don't load any code.

= Is the plugin compatible with caching plugins? =

Yes, Functionalities works well with all major caching plugins.

= Does this work with block themes? =

Yes, all features work with both classic and block themes.

== Screenshots ==

1. Main dashboard with module cards
2. Link Management settings
3. Performance & Cleanup options
4. Redirect Manager interface
5. Task Manager project view

== Changelog ==

= 0.15.5 =
* Fixed: PHPCS compliance - added proper escaping, wp_unslash(), nonce verification comments, and translators comments across all modules
* Removed: GitHub Updates module (not permitted on WordPress.org)
* Added: readme.txt for WordPress.org submission

= 0.15.4 =
* Fixed: PHPCS compliance improvements

= 0.15.3 =
* Removed: Transient cache from Link Management JSON preset loader for realtime exception updates

= 0.15.2 =
* Removed: The Debugger module, as it is no longer necessary following the transition to a more stable shortcode-based icon system

= 0.15.1 =
* Fixed: Removed automatic conversion of icon tags to shortcodes on save

= 0.15.0 =
* Changed: SVG Icons now use the [func_icon name="slug"] shortcode workaround for better stability
* Improved: Updated block editor to insert shortcodes directly into content
* Fixed: Render logic now gracefully handles unclosed icon tags on the frontend

= 0.14.0 =
* Fonts Module UI Overhaul: Completely rebuilt with modern card-based design
* Dynamic add/remove functionality for unlimited custom fonts
* WordPress Media Uploader integration for font uploads

= 0.13.0 =
* Added new features to Performance & Cleanup module
* Added wp_body_open support to Snippets module
* Added BreadcrumbList JSON-LD support to Schema module
* Added font preloading option to Fonts module

= 0.12.0 =
* Performance: Implemented custom autoloader for lazy-loading
* Performance: Added static property caching for options
* Performance: Optimized Schema module with regex instead of DOMDocument
* Performance: Added strpos fast-exit checks to all content filters

= 0.11.0 =
* Added: SVG Icons module with block and inline support

= 0.10.0 =
* Added: Redirect Manager module
* Added: Login Security module

= 0.9.9 =
* Added: Task Manager module

= 0.9.0 =
* Added: Content Regression Detection module
* Added: Assumption Detection module

== Upgrade Notice ==

= 0.15.5 =
This release includes security and code quality improvements for WordPress.org compliance. The GitHub Updates module has been removed as it's not permitted on WordPress.org.
