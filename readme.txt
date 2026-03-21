=== Dynamic Functionalities ===
Contributors: gauravtiwari
Donate link: https://gauravtiwari.org/donate/
Tags: performance, security, seo, redirection, cleanup
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace 5+ plugins with one lightweight toolkit. 16 modules for performance, security, SEO, redirects, and content management.

== Description ==

### Replace your plugin stack with one lightweight toolkit

Dynamic Functionalities replaces the stack of single-purpose plugins most WordPress sites depend on. Instead of installing separate plugins for performance cleanup, redirect management, login security, schema markup, external link control, and code snippets, you get 16 purpose-built modules in one package that loads less code than most individual plugins.

Every module is independent. Enable what you need, disable what you don't. Disabled modules load zero code.

= Why Not Just Use Separate Plugins? =

A typical WordPress site runs 5-10 utility plugins that each load their own CSS, JS, options, and database queries on every page load. Dynamic Functionalities consolidates these into a single plugin with shared infrastructure:

* **One autoloader** instead of 16 separate plugin bootstraps
* **Shared options caching** across all modules (static properties, not repeated DB calls)
* **Zero frontend assets** unless a module explicitly requires them
* **Single admin menu** instead of scattered settings pages

= What It Replaces =

Here's what you can deactivate after installing Dynamic Functionalities:

* **Redirection / Safe Redirect Manager / 301 Redirects** — The Redirect Manager module handles 301, 302, 307, and 308 redirects with file-based storage (no database bloat)
* **Limit Login Attempts Reloaded / WP Limit Login / Login LockDown** — Login Security module covers login attempt limiting, lockout durations, XML-RPC blocking, and login error hiding
* **External Links / WP External Links** — Link Management module automates nofollow, new tab behavior, and exception lists with JSON preset support
* **Schema Pro / Schema & Structured Data** — Schema Settings module adds microdata with itemscope/itemtype support and BreadcrumbList JSON-LD
* **Insert Headers and Footers / WPCode** — Header & Footer Snippets module handles GA4 integration and custom code injection
* **Asset CleanUp / Perfmatters** — Performance & Cleanup module disables emojis, embeds, REST API links, XML-RPC, feeds, Gravatars, heartbeat, and more
* **SVG Support / Safe SVG** — SVG Icons module lets you upload and insert SVG icons inline in the block editor
* **Use Any Font / Custom Fonts** — Fonts module registers custom font families with @font-face, WOFF2/WOFF, and variable font support
* **PWA for WP / Super Progressive Web Apps** — Progressive Web App module makes your site installable with service worker support

= Modules That Don't Have Alternatives =

Some modules solve problems no other free plugin addresses:

* **Content Integrity** — Monitors posts for structural regressions on update: dropped internal links, word count drops, heading structure changes. Catches accidental content loss before it goes live.
* **Assumption Detection** — Watches for technical assumptions that silently break: schema collisions from conflicting plugins, duplicate analytics tags, redundant font loading, missing expected elements.
* **Components** — Define reusable CSS components as selector + rules pairs. Auto-enqueued site-wide without a page builder or theme dependency.
* **Task Manager** — File-based project management inside WordPress admin. No external service, no database tables, no SaaS subscription.
* **Block Cleanup** — Strips wp-block classes from frontend HTML for sites that don't need them. Cleaner markup, smaller DOM.
* **Editor Link Suggestions** — Limits the block editor link autocomplete to specific post types. Stops irrelevant suggestions from cluttering the link picker.

= Performance First =

* **Modular & lazy loaded** — Only active modules run code
* **Static property caching** — Options are read once per request, not on every hook
* **Fast-exit content filters** — strpos() checks before any regex or DOM parsing
* **Transient caching** — Heavy operations (JSON parsing, file I/O) are cached
* **No frontend bloat** — No CSS or JS loaded unless a module explicitly needs it

= Developer Friendly =

* Clean namespaced codebase: `Functionalities\Features\*`, `Functionalities\Admin\*`
* All hooks prefixed with `functionalities_` for safe filtering
* Every module exposes filters for customization
* PSR-4-like autoloader with zero dependencies
* GPL-2.0-or-later — fork it, extend it, contribute back

= Documentation & Support =

* [Training Lessons](https://gauravtiwari.org/circle/course/functionalities-training/lessons) — Step-by-step module walkthroughs
* [GitHub Issues](https://github.com/wpgaurav/functionalities/issues) — Bug reports and feature requests
* [WordPress.org Support](https://wordpress.org/support/plugin/functionalities/) — Community support forum

== Installation ==

1. Upload the `functionalities` folder to `/wp-content/plugins/`
2. Activate through **Plugins > Installed Plugins**
3. Go to **Functionalities** in the admin sidebar
4. Enable the modules you need from the dashboard

Each module card shows what it does. Click **Configure** to access its settings. Modules you don't enable load no code at all.

== Frequently Asked Questions ==

= Does this plugin slow down my site? =

No. Dynamic Functionalities uses lazy-loading, static caching, and fast-exit checks across all modules. Most modules add zero frontend assets. The entire plugin loads less code than many single-purpose alternatives.

= Can I use only specific modules? =

Yes. Every module is independent. Enable only what you need. Disabled modules don't register any hooks, load any files, or run any code.

= Will this conflict with my existing plugins? =

Most modules work alongside other plugins. If you already have a redirect plugin or login limiter, disable that module in Dynamic Functionalities to avoid overlap. The Assumption Detection module actually helps you find these conflicts.

= Does it work with caching plugins? =

Yes. Tested with WP Super Cache, W3 Total Cache, LiteSpeed Cache, and FlyingPress. No special configuration needed.

= Does it work with page builders and block themes? =

Yes. All modules work with classic themes, block themes, Elementor, Bricks Builder, GenerateBlocks, and other page builders.

= Is the plugin compatible with Rank Math, Yoast, or other SEO plugins? =

Yes. The Meta & Copyright module detects active SEO plugins and adjusts its behavior to avoid duplicate meta tags. Schema Settings works alongside SEO plugin schemas without conflicts.

= How are redirects stored? =

File-based JSON storage, not database tables. This means redirects load faster and don't bloat your wp_options or create custom tables that survive uninstallation.

= Can I migrate redirects from another plugin? =

The Redirect Manager supports manual entry of 301, 302, 307, and 308 redirects. For bulk migration, export your existing redirects as CSV and add them through the interface.

= What happens if I deactivate the plugin? =

All settings are preserved in the database. Reactivate anytime and everything is restored. If you want a clean removal, use the uninstall process which removes all plugin options.

== Screenshots ==

1. Dashboard overview with module cards
2. Content Integrity module
3. Assumption Detection module

== Changelog ==

= 1.3.1 =
* Added: Public `Link_Management::process_content()` helper for applying nofollow/new-tab rules to ACF fields, shortcode output, and custom templates
* Fixed: Redirect Manager now strips query strings before matching, so `/old-page?utm=x` correctly matches `/old-page`
* Improved: Redirect Manager passes original query string through to destination URL
* Improved: Redirect Manager uses O(1) indexed lookup for exact matches instead of linear scan
* Improved: Redirect Manager defers hit counter writes to shutdown for faster redirects
* Added: Redirect loop detection at both creation time and runtime
* Fixed: Removed filler text from readme plugin alternatives list

= 1.3.0 =
* Added: WordPress 7 editor iframe compatibility for Fonts, SVG Icons, Content Regression, and Components modules
* Added: `enqueue_block_assets` handlers so editor CSS loads inside the WP 7 iframed block editor
* Fixed: Query string stripping (`remove_query_strings`) no longer strips version tags from admin/editor assets
* Tested up to WordPress 7.0

= 1.2.0 =
* Changed: All 16 modules now require explicit activation — no module runs code until enabled
* Added: Enable/disable toggle to every module settings page
* Added: Toggle forms for Task Manager, Redirect Manager, and SVG Icons custom pages
* Fixed: Redirect Manager and Task Manager file paths now set before enabled gate to prevent empty-path errors in admin

= 1.1.1 =
* Fixed: PHPCS escaping compliance for all output variables
* Fixed: WordPress.org SVN tag version mismatch
* Fixed: Short description truncation (now under 150 chars)
* Fixed: Excluded landing-page.html and LICENSE from distribution

= 1.1.0 =
* Renamed to Dynamic Functionalities for WordPress.org
* Removed: FluentCart licensing module
* Removed: Premium barriers from all modules
* All features are now free and open source
* Added: WordPress.org plugin deploy workflow
* Added: Progressive Web App module

= 1.0.0 =
* Initial release with 15+ modules
* Updated: Modern dashboard UI with improved module cards

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

= 1.3.1 =
Link Management now works with ACF fields and custom templates via process_content() helper. Redirect Manager fixes query string matching and adds loop detection.

= 1.3.0 =
WordPress 7 compatibility: editor CSS now loads inside the iframed block editor. Fixes version tag stripping in admin.

= 1.2.0 =
All modules now require explicit activation. After updating, visit Functionalities settings and enable the modules you use.

= 1.1.0 =
All features are now free and open source. 16 modules for performance, security, SEO, and content management.
