=== Dynamic Functionalities ===
Contributors: gauravtiwari
Donate link: https://gauravtiwari.org/donate/
Tags: performance, security, seo, redirection, cleanup
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.6.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Replace 5+ plugins with one lightweight toolkit. 16 modules for performance, security, SEO, redirects, and content management.

== Description ==

Dynamic Functionalities replaces the stack of single-purpose plugins most WordPress sites depend on. Instead of installing separate plugins for performance cleanup, redirect management, login security, schema markup, external link control, and code snippets, you get 16 purpose-built modules in one package that loads less code than most individual plugins.

Every module is independent. Enable what you need, disable what you don't. Disabled modules load zero code — no hooks, no files, no queries.

Full documentation lives at [functionalities.dev](https://functionalities.dev/), including a [module reference](https://functionalities.dev/modules), a [getting started guide](https://functionalities.dev/docs/getting-started), and a [complete hook reference](https://functionalities.dev/docs/hooks).

= New in 1.6.0 =

A security and correctness release, tested on WordPress 7.1.

* **Abilities API permissions tightened.** Each WordPress 7 ability now carries its own permission callback and rejects undeclared input. Previously a shared callback widened to `edit_post` whenever a request carried a `post_id`, so anyone who could edit a single post could reach administrator-only operations.
* **Data files moved somewhere private.** Redirects, the 404 log, and task notes now live in a folder with a random name and are no longer readable at a guessable URL. Existing files migrate automatically.
* **Snippets reach visitors intact.** Header and footer code is filtered once at save time against the author's capability instead of being re-filtered against each visitor's, which used to mangle `&&` and comparison operators for logged-out readers.
* **Content filters use the WordPress HTML API.** Link Management, Block Cleanup, and Schema edit attributes in place instead of reserializing through DOMDocument. Pages built with Vue, Alpine, or mustache templates are processed correctly rather than skipped.

Existing settings, hooks, admin URLs, and data files carry over untouched. The full list is in the changelog below.

= Why Not Just Use Separate Plugins? =

A typical WordPress site runs 5-10 utility plugins that each load their own CSS, JS, options, and database queries on every page load. Dynamic Functionalities consolidates these into a single plugin with shared infrastructure:

* **One autoloader** instead of 16 separate plugin bootstraps
* **Shared options caching** across all modules (static properties, not repeated DB calls)
* **Zero frontend assets** unless a module explicitly requires them
* **Single admin menu** instead of scattered settings pages

= What It Replaces =

Here's what you can deactivate after installing Dynamic Functionalities:

* **Redirection / Safe Redirect Manager / 301 Redirects** — The [Redirect Manager](https://functionalities.dev/docs/redirect-manager) module handles 301, 302, 307, and 308 redirects with file-based storage (no database bloat)
* **Limit Login Attempts Reloaded / WP Limit Login / Login LockDown** — [Login Security](https://functionalities.dev/docs/login-security) module covers login attempt limiting, lockout durations, XML-RPC blocking, and login error hiding
* **External Links / WP External Links** — [Link Management](https://functionalities.dev/docs/link-management) module automates nofollow, new tab behavior, and exception lists with JSON preset support
* **Schema Pro / Schema & Structured Data** — [Schema Settings](https://functionalities.dev/docs/schema) module adds microdata with itemscope/itemtype support and BreadcrumbList JSON-LD
* **Insert Headers and Footers / WPCode** — [Header & Footer Snippets](https://functionalities.dev/docs/snippets) module handles GA4 integration and custom code injection
* **Asset CleanUp / Perfmatters** — [Performance & Cleanup](https://functionalities.dev/docs/performance) module disables emojis, embeds, REST API links, XML-RPC, feeds, Gravatars, heartbeat, and more
* **SVG Support / Safe SVG** — [SVG Icons](https://functionalities.dev/docs/svg-icons) module lets you upload and insert SVG icons inline in the block editor
* **Use Any Font / Custom Fonts** — Fonts module registers custom font families with @font-face, WOFF2/WOFF, variable font support, and Bricks Builder integration
* **PWA for WP / Super Progressive Web Apps** — Progressive Web App module makes your site installable with service worker support

= Modules That Don't Have Alternatives =

Some modules solve problems no other free plugin addresses:

* **[Content Integrity](https://functionalities.dev/docs/content-regression)** — Monitors posts for structural regressions on update: dropped internal links, word count drops, heading structure changes. Catches accidental content loss before it goes live.
* **[Assumption Detection](https://functionalities.dev/docs/assumption-detection)** — Watches for technical assumptions that silently break: schema collisions from conflicting plugins, duplicate analytics tags, redundant font loading, missing expected elements.
* **Components** — Define reusable CSS components as selector + rules pairs. Auto-enqueued site-wide without a page builder or theme dependency.
* **[Task Manager](https://functionalities.dev/docs/task-manager)** — File-based project management inside WordPress admin. No external service, no database tables, no SaaS subscription.
* **[Block Cleanup](https://functionalities.dev/docs/block-cleanup)** — Strips wp-block classes from frontend HTML for sites that don't need them. Cleaner markup, smaller DOM.
* **Editor Link Suggestions** — Limits the block editor link autocomplete to specific post types. Stops irrelevant suggestions from cluttering the link picker.

= Performance First =

* **Modular & lazy loaded** — Only active modules run code. A front-end request with every module disabled loads zero feature files.
* **Static property caching** — Options are read once per request, not on every hook
* **Fast-exit content filters** — strpos() checks run before any parsing happens
* **WordPress HTML API** — Content filters edit attributes in place instead of reserializing the document, so markup comes out the way you wrote it
* **Transient caching** — Heavy operations (JSON parsing, file I/O) are cached
* **No frontend bloat** — No CSS or JS loaded unless a module explicitly needs it

= Developer Friendly =

* Clean namespaced codebase: `Functionalities\Features\*`, `Functionalities\Admin\*`
* All hooks prefixed with `functionalities_` for safe filtering — see the [hook reference](https://functionalities.dev/docs/hooks)
* Every module exposes filters for customization, documented in the [API reference](https://functionalities.dev/docs/api-reference)
* WordPress 7 Abilities API operations, each behind its own capability check
* PSR-4-like autoloader with zero dependencies
* GPL-2.0-or-later — fork it, extend it, contribute back

= Documentation & Support =

* [functionalities.dev](https://functionalities.dev/) — Documentation home
* [Getting started](https://functionalities.dev/docs/getting-started) — Install, enable your first module, and verify it
* [Module reference](https://functionalities.dev/modules) — What each of the 16 modules does
* [Dashboard guide](https://functionalities.dev/docs/dashboard) — Working with the module dashboard
* [Hooks](https://functionalities.dev/docs/hooks) and [API reference](https://functionalities.dev/docs/api-reference) — For developers extending the plugin
* [FAQ](https://functionalities.dev/faq) — Common questions answered in more depth than this page
* [Downloads](https://functionalities.dev/download) — Current and previous releases
* [Training](https://gauravtiwari.org/course/functionalities-training/) — Step-by-step module walkthroughs
* [GitHub Issues](https://github.com/wpgaurav/functionalities/issues) — Bug reports and feature requests
* [WordPress.org Support](https://wordpress.org/support/plugin/functionalities/) — Community support forum

== Installation ==

1. Upload the `functionalities` folder to `/wp-content/plugins/`
2. Activate through **Plugins > Installed Plugins**
3. Go to **Functionalities** in the admin sidebar
4. Enable the modules you need from the dashboard

Each module card shows what it does. Click **Configure** to access its settings. Modules you don't enable load no code at all.

For a walkthrough with screenshots, see [Getting started](https://functionalities.dev/docs/getting-started) and the [dashboard guide](https://functionalities.dev/docs/dashboard).

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

Yes. All modules work with classic themes, block themes, Elementor, Bricks Builder, GenerateBlocks, and other page builders. The Fonts module has native Bricks Builder integration — custom fonts appear in the Bricks typography picker and load inside the builder canvas.

= Is the plugin compatible with Rank Math, Yoast, or other SEO plugins? =

Yes. The Meta & Copyright module detects active SEO plugins and adjusts its behavior to avoid duplicate meta tags. Schema Settings works alongside SEO plugin schemas without conflicts.

= How are redirects stored? =

In a JSON file, not database tables, so redirects load fast and don't bloat `wp_options` or leave custom tables behind after uninstall.

Since 1.6.0 that file lives in a folder with a randomly generated name under `wp-content/`, alongside `.htaccess`, `web.config`, and index files that block direct access and directory listing. A Site Health check confirms over HTTP that the folder really is unreachable on your host, and tells you what to add to your server config if it isn't. See the [Redirect Manager docs](https://functionalities.dev/docs/redirect-manager).

= Can I migrate redirects from another plugin? =

Yes. Export your existing redirects to CSV and import them. The importer recognizes the common column names other plugins use, validates the whole file before writing anything, and flags duplicates, wildcards, loops, and chains. Runs are all-or-nothing, and you can preview the result as a dry run first. Manual entry supports 301, 302, 307, and 308.

= Which WordPress versions does it support? =

WordPress 6.3 and later, on PHP 7.4 and later. 1.6.0 is tested on WordPress 7.1. WordPress 7 features — the Abilities API, DataViews workspaces, block bindings, and Command Palette actions — are feature-detected, so the plugin runs the same on 6.3 without them.

= Does the plugin send any data anywhere? =

No. Nothing is phoned home, and there is no telemetry. Two features make outbound requests, both under your control: Link Management fetches a JSON exception list only if you configure a URL for it, and AI explanations are strictly opt-in and only send a finding an administrator explicitly submits. The diagnostics download excludes task content, redirects, users, secrets, and site URLs.

= Where does the plugin store its files? =

Generated CSS goes to `wp-content/uploads/functionalities/`. Redirects, the 404 log, and task notes go in a private folder with a random name under `wp-content/functionalities/`, protected from direct access and from directory listing. Everything else is a WordPress option.

= What happens if I deactivate the plugin? =

All settings are preserved in the database. Reactivate anytime and everything is restored.

= How do I completely remove all plugin data? =

Before uninstalling, go to the Functionalities dashboard and check **"Delete all plugin data when uninstalling"** under Data Management. This removes all options, post metadata, transients, and files created by the plugin. Without this checkbox, only the generated CSS file is removed — your settings are preserved in case you reinstall.

== Screenshots ==

1. Dashboard overview with module cards
2. Content Integrity module
3. Assumption Detection module

== Changelog ==

= 1.6.0 =
* Security: Abilities API operations now use a permission callback per ability and reject undeclared input properties. A shared callback previously widened to `edit_post` whenever the request carried a `post_id`, so any user who could edit one post could toggle modules, create redirects, create tasks, and trigger scans.
* Security: Redirects, the bounded 404 log, and Task Manager projects moved to a private folder with a random name under `wp-content/functionalities/`. Existing files are migrated automatically. Apache, IIS, and directory-listing rules are written alongside them, and a new Site Health check confirms over HTTP that the folder really is unreachable.
* Fixed: Header and footer snippets are no longer re-filtered against the *visitor's* capability at output time. Anonymous visitors were receiving mangled code — `&&` became `&amp;&amp;` and comparison operators were eaten as tags — while the logged-in administrator saw the snippet work. Filtering now happens once, at save time, against the author's capability.
* Fixed: A JSON exception preset served from a URL is fetched at most once per cache window instead of on every page load. The cache clears whenever the module settings change, a post or page is edited, or the theme changes, and the last good list is kept when a fetch fails.
* Fixed: The bulk nofollow tool pages through posts with an ID cursor and now finishes on sites with more than 100 matches. It previously returned the same first batch on every run.
* Improved: Link Management, Block Cleanup, and Schema use the WordPress HTML API instead of DOMDocument. Attributes are edited in place, so Vue, Alpine, and mustache syntax survive untouched and the JS-framework skip guard added in 1.4.3 and 1.4.4 is gone. Content that used to be skipped is now processed correctly.
* Improved: Redirect hits and 404 aggregates are buffered and written in batches rather than rewriting the whole JSON file under an exclusive lock on every request.
* Improved: Redirects run at `parse_request`, before WordPress queries the database for a page it is about to discard. WordPress's own entry points are never redirected.
* Improved: The Content Integrity column on the posts list reads a result cached at save time instead of rendering and parsing every row on every page load.
* Improved: The SVG icon library is stored without autoloading, so full SVG markup no longer loads on every request.
* Improved: The service worker skips wp-admin, the login page, REST responses, cross-origin requests, and anything marked no-store or private; caps the runtime cache; and precaches URLs individually so one stale entry cannot stop it installing. The manifest now includes an `id`.
* Improved: Login Security adds per-username throttling, an IP allowlist, an unlock button on the lockout log, and a warning when every recent lockout shares one address, which is the signature of a site behind a CDN.
* Improved: Prism.js is bundled with the plugin instead of being loaded from a third-party CDN.
* Improved: Performance & Cleanup makes the revision limit configurable, and disabling Heartbeat now applies to the frontend only unless the new admin option is also enabled, so autosave and post locking keep working.
* Improved: Content Integrity and Assumption Detection gained the filters their documentation promised, and the module documentation now lists hook names that exist. Nineteen documented hooks were never fired.
* Fixed: Settings export no longer redacts the GA4 measurement ID as if it were custom code.
* Fixed: Core icons get the same definition-ID prefixing as custom icons, so two gradient icons on one page no longer collide.
* Fixed: Saving PWA settings flushes rewrite rules once instead of twice.
* Fixed: Disabling feeds falls back to a message only when a redirect is genuinely impossible, making the documented message filter reachable.
* Changed: The translation template is generated from the source. It was a one-string placeholder.
* Changed: `src/` and `docs/` are excluded from the distribution, and `build.sh` now uses the same exclude list as the release workflow so a local build and a tagged release cannot drift.
* Changed: Tested up to WordPress 7.1.

= 1.5.0 =
* Added: WordPress 7 Abilities API operations for module status, privacy-safe diagnostics, assumption scans, content-integrity checks, redirect import previews, redirect creation, task creation, module toggles, and opt-in AI explanations.
* Added: DataViews and DataForm workspaces for Redirect Manager, its bounded 404 activity, and Task Manager with searchable, sortable, filterable tables and modern creation forms while retaining the classic interfaces as fallbacks.
* Added: Command Palette actions for opening Functionalities screens and running an Assumption Detection scan.
* Added: Explicitly opt-in AI explanations powered by the WordPress AI Client. Only a finding submitted by an administrator is sent to the configured provider.
* Improved: SVG Icon block upgraded to Block API v3 with WordPress Core Icon source support, two-way Core Icon transforms, pattern-override-ready content attributes, block bindings, and an Icon Callout pattern.
* Fixed: Content Integrity now uses the current `wp.editor` plugin-sidebar components on WordPress 7 while retaining the legacy fallback.
* Fixed: Custom font declarations are emitted with their preloads so first-paint typography does not shift when async theme styles arrive.
* Changed: Minimum WordPress version is now 6.3 so the SVG Icon block can use Block API v3 consistently. WordPress 7-only integrations remain feature-detected.

= 1.4.8 =
* Improved: SVG Icon block now uses block metadata, lazy paginated icon loading, native block supports, px/em/rem sizing, original-color and monochrome modes, accessible labels, missing-icon recovery, and keyboard-friendly selection.
* Security: SVG sanitization now requires a real SVG root, restricts styles and local references, blocks external href values, and prefixes definition IDs to prevent collisions.
* Fixed: Fresh PWA settings now register rewrite endpoints immediately, and the offline application shell returns a cacheable success response so service-worker precaching can complete.
* Added: True lazy module registry. A frontend request with all modules disabled loads no feature class files; enabling one module loads only that feature and shared dependencies.
* Added: Versioned settings export/import with dry-run differences, module validation, default custom-code redaction, and an explicit code opt-in.
* Added: Privacy-conscious diagnostics download with software versions, enabled modules, writable-path status, and rewrite-rule health. Task content, redirects, users, secrets, and site URLs are excluded.
* Added: Redirect CSV import/export with common column aliases, all-or-nothing dry runs, and duplicate, wildcard, loop, and chain validation.
* Added: Opt-in bounded 404 monitor with retention, row caps, path exclusions, bot/admin/API filtering, referrer-origin-only storage, purge, ignore, and redirect-prefill actions.
* Added: Assumption Detection Site Health status, configurable scheduled scans, stale/failed scan distinction, and opt-in deduplicated email summaries.
* Added: Content Integrity snapshot differences for links, headings, H1s, and word count, plus bounded audit metadata for baseline actions.
* Added: Pull-request CI across PHP 7.4 through 8.5, WordPress Coding Standards, PHPUnit coverage, JavaScript/shell checks, version consistency, and distribution assertions.
* Changed: Admin bootstrap is now a small router with dedicated module, portability, and Site Health controllers.
* Fixed: Task Manager and Redirect Manager JSON updates now use locking, verified same-directory temporary files, and atomic replacement to prevent lost concurrent writes.
* Fixed: Invalid JSON and storage failures preserve the last known file and surface an actionable admin error instead of silently appearing empty.
* Fixed: SVG Icons is disabled on fresh installs, matching the explicit-activation policy used by every module.

= 1.4.7 =
* Added: When "Assign fonts to body text and headings" is enabled, the block editor canvas now receives explicit `.editor-styles-wrapper` font-family rules (the assigned family plus a system-font fallback) for body and headings, so the editor matches the front end even when the theme.json typography assignment doesn't reach the iframe.
* Fixed: Custom fonts now render inside the block editor canvas. The editor is an iframe (WP 6.3+/7.x) that ignores src-less inline styles, so `@font-face` is now injected through the editor `styles` setting — the same channel the Font Library and `add_editor_style()` use.
* Fixed: Variable-font weight ranges with an out-of-spec low bound (e.g. `1 900`) are normalized to `100 900`. WordPress was silently dropping these faces — and their entry in the editor font picker — when validating theme.json.
* Fixed: Components module CSS now reaches the block editor canvas reliably via the editor `styles` setting. The previous inline fallback could not cross into the WP 7 iframe when the generated CSS file was unavailable.
* Changed: Removed the redundant `admin_head` font print. It reached only the parent admin document, never the editor iframe, and is superseded by the editor `styles` channel.
* Housekeeping: Documented a single source-of-truth matrix for the font-loading paths and removed stale per-file `@version` docblocks that had drifted from the plugin version.

= 1.4.6 =
* Added: Character range (`unicode-range`) support per font in the Fonts module — limit which characters trigger a font download for faster page loads
* Added: Quick-pick presets in the admin UI for common subsets (Latin, Latin Extended, Greek, Cyrillic, Vietnamese, Punctuation/Symbols)
* Added: `unicode-range` is also emitted into the theme.json `fontFace` data layer so it propagates to the block editor
* Security: Login Security no longer trusts `X-Forwarded-For` / `Client-IP` headers by default — these were spoofable on direct connections, allowing IP-based lockouts to be evaded or weaponized. Sites behind a trusted reverse proxy or CDN can opt in via the new "Trust Proxy Headers" setting.
* Security: Login Security now validates client IPs through `FILTER_VALIDATE_IP` when proxy headers are in use, dropping malformed values rather than hashing them into transient keys.
* Fixed: Block Cleanup XPath query now safely escapes class names via a proper XPath 1.0 string-literal builder (`addcslashes` was the wrong escape function and silently failed on classes containing quotes).
* Fixed: Snippets `kses_with_styles()` placeholder collision — `<style>` extraction now uses a per-call random token so a snippet body containing the literal placeholder string can no longer corrupt the output.
* Fixed: Fonts module admin badge now reflects the Style field (free-text input) instead of looking for a `<select>` that doesn't exist.
* Fixed: Fonts module options static cache is invalidated automatically on `update_option_functionalities_fonts`, preventing stale font lists when the option is updated mid-request.
* Fixed: Task Manager AJAX handlers (Export, Delete, etc.) now register whenever in admin, so existing projects remain manageable even when the module is toggled off.
* Fixed: Task Manager card layout — widget badge now sits above the action row, so Open/Export/Delete align consistently across cards.
* Fixed: Help & Support buttons now have higher CSS specificity to defeat WP 7.0's button reset.

= 1.4.5 =
* Added: WOFF and WOFF2 font file uploads now supported in the WordPress media library
* Security: Font uploads validated via binary magic-byte signatures to prevent malicious file uploads

= 1.4.4 =
* Fixed: Schema module `filter_article()` now skips content with Vue/Alpine.js directives — prevents DOMDocument from corrupting JS framework templates
* Fixed: Block Cleanup module `filter_content_cleanup()` now skips content with JS framework directives
* Refactored: Extracted Vue-safe DOMDocument guard into shared `Has_Dom_Parser` trait used by Link Management, Schema, and Block Cleanup
* All three `the_content` filters that use DOMDocument (priorities 12, 14, 999) are now protected against JS framework corruption

= 1.4.3 =
* Fixed: Link Management `process_content()` now skips HTML containing Vue.js directives (`v-cloak`, `v-if`, `v-show`, `:class`, `@click`, `{{ }}`)
* Fixed: DOMDocument re-parsing was corrupting Vue/React template syntax in themes like MyListing, causing explore pages to flash and disappear
* Improved: Early-exit check prevents unnecessary DOM parsing on content with JavaScript framework directives

= 1.4.2 =
* Fixed: `wp_kses` now preserves `data-*` attributes on `<script>`, `<style>`, and `<link>` tags in Header & Footer snippets
* Fixed: `async`, `defer`, `nomodule`, `id`, `nonce`, `crossorigin`, and `as` attributes no longer stripped from snippet tags for non-admin users
* Fixed: Unified allowed-tags list between snippet output and save sanitization to prevent attribute drift
* Fixed: README.md version was outdated (still showed 1.4.0)

= 1.4.1 =
* Added: Opt-in "Delete all plugin data when uninstalling" checkbox on the dashboard — removes all options, post metadata, transients, and files on uninstall
* Fixed: Replaced all direct file_put_contents calls with WP_Filesystem API across Task Manager, Redirect Manager, and JSON file creation
* Fixed: Extracted duplicate CSS sanitization into a shared trait used by Components and Fonts modules
* Fixed: Removed sslverify => false from loopback HTTP requests in Assumption Detection
* Fixed: Disabled debug console logging in SVG Icons editor script
* Fixed: Removed dead code in admin UI script

= 1.4.0 =
* Added: Bricks Builder font integration — custom fonts appear in Bricks typography picker and load inside the builder canvas
* Added: PWA module prefills app name, short name, description, and icons from WordPress Settings and Site Icon
* Improved: Task Manager UI redesign — external CSS, card-based project grid, improved modals, hover task actions, polished column view
* Improved: Task Manager consistent spacing across all sections

= 1.3.3 =
* Improved: Snippets UI — collapsible cards, type badges (CSS/JS/Meta), inline label editing, icon buttons
* Fixed: Template index replacement no longer corrupts textarea attributes
* Fixed: kses_with_styles handles empty style tags and regex failures gracefully

= 1.3.2 =
* Added: Snippets repeater — multiple independently-toggleable code snippets per location (header, body open, footer)
* Fixed: CSS inside `<style>` tags no longer stripped by `wp_kses()` for non-admin users
* Removed: Legacy GT Nofollow Manager references and `gtnf_*` filter hooks from Link Management
* Improved: Auto-migration from single-string snippet format to repeater arrays

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

= 1.6.0 =
Security release. Fixes an Abilities API permission flaw that let any user who could edit a post reach administrator-only operations, moves redirect and task data into a private folder, and stops header/footer snippets being mangled for logged-out visitors. Also replaces DOMDocument with the WordPress HTML API in three content filters, so pages using Vue or Alpine are processed correctly instead of skipped. Existing settings, hooks, admin URLs, and data files are migrated automatically.

= 1.4.8 =
Adds the upgraded SVG Icon block, safe settings portability, CSV redirects, an opt-in 404 monitor, native Site Health signals, true lazy module loading, atomic file storage, and pull-request quality gates. Existing option names, block names, shortcode syntax, admin URLs, hooks, and JSON formats remain compatible.

= 1.4.5 =
Enables WOFF/WOFF2 font uploads in the media library with magic-byte validation for security.

= 1.4.4 =
Extends Vue/Alpine.js protection to Schema and Block Cleanup modules. All DOMDocument-based content filters now skip JS framework content.

= 1.4.3 =
Fixes Link Management breaking pages that use Vue.js (MyListing explore page, etc.). DOMDocument no longer corrupts Vue/React template directives.

= 1.4.2 =
Fixes `data-*` and other attributes being stripped from script/style/link tags in Header & Footer snippets for non-admin users.

= 1.4.1 =
Code quality and plugin review compliance: WP_Filesystem for all file writes, comprehensive uninstall cleanup (opt-in), shared CSS sanitization trait, and minor fixes.

= 1.4.0 =
Bricks Builder font support. Task Manager redesign: cleaner card-based UI, external CSS, improved modals, hover actions on tasks, and polished column view.

= 1.3.3 =
Snippets UI polish: collapsible cards with type badges, smoother interactions, and bug fixes.

= 1.3.2 =
Header & Footer snippets now support multiple code blocks per location with individual toggles. Fixes CSS output for non-admin users. Removes legacy GT Nofollow Manager compatibility.

= 1.3.1 =
Link Management now works with ACF fields and custom templates via process_content() helper. Redirect Manager fixes query string matching and adds loop detection.

= 1.3.0 =
WordPress 7 compatibility: editor CSS now loads inside the iframed block editor. Fixes version tag stripping in admin.

= 1.2.0 =
All modules now require explicit activation. After updating, visit Functionalities settings and enable the modules you use.

= 1.1.0 =
All features are now free and open source. 16 modules for performance, security, SEO, and content management.
