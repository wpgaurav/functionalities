<?php
/**
 * Module documentation configurations.
 *
 * Centralizes all module documentation for the admin UI.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Module documentation class.
 */
class Module_Docs {

	/**
	 * Get documentation config for a module.
	 *
	 * @param string $module Module slug.
	 * @return array Documentation configuration.
	 */
	public static function get( string $module ): array {
		$docs = self::get_all();
		return $docs[ $module ] ?? array();
	}

	/**
	 * Get all module documentation configurations.
	 *
	 * @return array All module docs.
	 */
	public static function get_all(): array {
		return array(
			'link-management'      => array(
				'features' => array(
					\__( 'Automatically adds rel="nofollow" to external links in post content', 'functionalities' ),
					\__( 'Opens external links in new tabs with proper security attributes', 'functionalities' ),
					\__( 'Whitelist trusted domains that should not get nofollow', 'functionalities' ),
					\__( 'Bulk update existing links in your database', 'functionalities' ),
				),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_exception_domains',
						'description' => \__( 'Modify the exception domain list', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_exception_urls',
						'description' => \__( 'Modify the exception URL list', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_json_preset_path',
						'description' => \__( 'Override the JSON preset file path or URL', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_link_preset_ttl',
						'description' => \__( 'Change how long a resolved JSON preset stays cached', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_link_update_batch_limit',
						'description' => \__( 'Change the bulk database update batch size', 'functionalities' ),
					),
				),
			),

			'block-cleanup'        => array(
				'features' => array(
					\__( 'Reduces HTML bloat by removing unnecessary block classes', 'functionalities' ),
					\__( 'Useful when your theme provides custom styling for headings, lists, images', 'functionalities' ),
					\__( 'Only affects frontend output, block editor remains unchanged', 'functionalities' ),
				),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_block_cleanup_enabled',
						'description' => \__( 'Toggle cleanup globally', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_block_cleanup_classes',
						'description' => \__( 'Modify classes to remove', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_block_cleanup_content',
						'description' => \__( 'Modify content after classes are removed', 'functionalities' ),
					),
				),
			),

			'editor-links'         => array(
				'features' => array(
					\__( 'Limits link search results to specific post types when inserting links in Gutenberg', 'functionalities' ),
					\__( 'Reduces clutter by hiding unwanted content types from search results', 'functionalities' ),
					\__( 'Works with posts, pages, and custom post types', 'functionalities' ),
				),
				'usage'    => \__( 'Enable limitation, then check only the post types you want to appear when searching for links in the editor. Unchecked post types will be hidden from link search results.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_editor_links_enabled',
						'description' => \__( 'Toggle feature', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_editor_links_post_types',
						'description' => \__( 'Modify allowed post types', 'functionalities' ),
					),
				),
			),

			'snippets'             => array(
				'features' => array(
					\__( 'Native Google Analytics 4 integration - just enter your Measurement ID', 'functionalities' ),
					\__( 'Custom header code for meta tags, scripts, styles, and tracking codes', 'functionalities' ),
					\__( 'Custom footer code for chat widgets, tracking pixels, and deferred scripts', 'functionalities' ),
					\__( 'Automatically skips admin pages, feeds, and REST API requests', 'functionalities' ),
				),
				'usage'    => \__( 'Allowed tags: script, style, link, meta, noscript.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_snippets_output_enabled',
						'description' => \__( 'Disable on specific pages', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_snippets_ga4_enabled',
						'description' => \__( 'Control GA4 per user/page', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_snippets_header_code',
						'description' => \__( 'Modify header code', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_snippets_footer_code',
						'description' => \__( 'Modify footer code', 'functionalities' ),
					),
				),
			),

			'schema'               => array(
				'features' => array(
					\__( 'Adds itemscope/itemtype to the HTML element for page-level schema', 'functionalities' ),
					\__( 'Wraps article content with Article/BlogPosting microdata', 'functionalities' ),
					\__( 'Adds structured data for headlines, dates, and authors', 'functionalities' ),
					\__( 'Marks header and footer regions with WPHeader/WPFooter types', 'functionalities' ),
				),
				'usage'    => \__( 'Site types: WebPage, AboutPage, ContactPage, Blog, SearchResultsPage. Article types: Article, BlogPosting, NewsArticle.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_schema_enabled',
						'description' => \__( 'Toggle all schema output', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_schema_site_itemtype',
						'description' => \__( 'Modify site itemtype', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_schema_article_itemtype',
						'description' => \__( 'Modify article itemtype', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_schema_article_content',
						'description' => \__( 'Modify wrapped content', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_schema_language_attributes',
						'description' => \__( 'Modify the html element attributes', 'functionalities' ),
					),
				),
			),

			'components'           => array(
				'features' => array(
					\__( 'Define CSS class names and their style rules in one place', 'functionalities' ),
					\__( 'Components are compiled into a single CSS file for optimal caching', 'functionalities' ),
					\__( 'Available on both frontend and admin pages', 'functionalities' ),
					\__( 'Includes default utility components like visually-hidden, skip-link, and marquee', 'functionalities' ),
				),
				'usage'    => \__( 'Add components by entering a CSS selector (e.g., .my-button) and CSS rules (e.g., background: blue; color: white;). Use the grid below to manage your components.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_components_enabled',
						'description' => \__( 'Toggle output', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_components_items',
						'description' => \__( 'Add components dynamically', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_components_css',
						'description' => \__( 'Modify generated CSS', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_components_updated',
						'description' => \__( 'Action: fires when CSS file regenerates', 'functionalities' ),
					),
				),
			),

			'fonts'                => array(
				'features' => array(
					\__( 'Generate @font-face CSS rules for self-hosted fonts', 'functionalities' ),
					\__( 'Support for variable fonts with weight ranges (e.g., 100 900)', 'functionalities' ),
					\__( 'WOFF2 format for modern browsers, optional WOFF fallback', 'functionalities' ),
					\__( 'Configurable font-display strategy (swap, auto, block, etc.)', 'functionalities' ),
				),
				'usage'    => \__( 'Upload font files to your media library or server, then add font entries below with the family name and file URLs. Use the generated font-family name in your CSS.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_fonts_enabled',
						'description' => \__( 'Toggle output', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_fonts_items',
						'description' => \__( 'Add fonts dynamically', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_fonts_css',
						'description' => \__( 'Modify generated CSS', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_fonts_before_output',
						'description' => \__( 'Action: before output', 'functionalities' ),
					),
				),
			),

			'svg-icons'            => array(
				'features' => array(
					\__( 'Upload or paste custom SVG icons with unique namespaces', 'functionalities' ),
					\__( 'Insert icons inline in the block editor via RichText toolbar', 'functionalities' ),
					\__( 'Icons automatically inherit the font size of surrounding text', 'functionalities' ),
					\__( 'Secure SVG sanitization prevents XSS and malicious content', 'functionalities' ),
					\__( 'Zero frontend footprint - icons only render where used', 'functionalities' ),
					\__( 'Also available via shortcode: [func_icon name="icon-slug"]', 'functionalities' ),
				),
				'usage'    => \__( 'Add icons by pasting SVG code in the admin. Each icon gets a unique slug. In the block editor, use the icon button in the toolbar to insert icons inline. Icons will scale with the surrounding text.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_svg_icons_enabled',
						'description' => \__( 'Toggle feature on/off', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_svg_icons_list',
						'description' => \__( 'Filter available icons', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_svg_icons_sanitize',
						'description' => \__( 'Modify SVG before saving', 'functionalities' ),
					),
				),
			),

			'misc'                 => array(
				'features' => array(
					\__( 'Remove bloat like emojis, oEmbeds, and unnecessary meta tags', 'functionalities' ),
					\__( 'Disable security concerns like XML-RPC and version disclosure', 'functionalities' ),
					\__( 'Improve performance by removing unused scripts and styles', 'functionalities' ),
					\__( 'Add useful enhancements like PrismJS and fullscreen textareas', 'functionalities' ),
				),
				'caution'  => \__( 'Some options may break functionality if plugins depend on them. Test after enabling. Disable Heartbeat API with care if you use auto-save or real-time features.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_misc_option_{$key}',
						'description' => \__( 'Control any single toggle, for example functionalities_misc_option_disable_emojis', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_misc_init',
						'description' => \__( 'Action: fires after all toggles are processed', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_misc_feeds_disabled_message',
						'description' => \__( 'Change the disabled-feeds message', 'functionalities' ),
					),
				),
			),

			'meta'                 => array(
				'features' => array(
					\__( 'Adds copyright meta tags with configurable holder', 'functionalities' ),
					\__( 'Dublin Core (DCMI) metadata support', 'functionalities' ),
					\__( 'Per-post license selection metabox', 'functionalities' ),
					\__( 'Integration with popular SEO plugins for Schema.org output', 'functionalities' ),
				),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_meta_licenses',
						'description' => \__( 'Add or modify available licenses', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_meta_standalone_schema',
						'description' => \__( 'Modify the standalone JSON-LD output', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_module_enabled',
						'description' => \__( 'Toggle any module, receives the module slug', 'functionalities' ),
					),
				),
			),

			'content-regression'   => array(
				'features' => array(
					\__( 'Detects when internal links are accidentally removed', 'functionalities' ),
					\__( 'Warns when content is shortened significantly', 'functionalities' ),
					\__( 'Checks heading structure for accessibility issues', 'functionalities' ),
					\__( 'Stores snapshots to compare against historical baselines', 'functionalities' ),
				),
				'usage'    => \__( 'This is content integrity, not SEO. The system answers: "Did this update accidentally damage something important?" It never scores content, never suggests fixes, and always compares a post to itself.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_content_regression_post_types',
						'description' => \__( 'Modify the monitored post types', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_content_regression_warnings',
						'description' => \__( 'Modify detected warnings for a post', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_content_regression_snapshot',
						'description' => \__( 'Modify a snapshot before it is stored', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_content_regression_snapshot_saved',
						'description' => \__( 'Action: after a snapshot is saved', 'functionalities' ),
					),
				),
			),

			'assumption-detection' => array(
				'features' => array(
					\__( 'Detects when multiple sources output the same schema type', 'functionalities' ),
					\__( 'Warns when analytics scripts are loaded multiple times', 'functionalities' ),
					\__( 'Notices when fonts are loaded from redundant sources', 'functionalities' ),
					\__( 'Tracks inline CSS growth over time', 'functionalities' ),
				),
				'usage'    => \__( 'This module notices when assumptions stop being true. It does not optimize, does not enforce best practices, and does not recommend plugins. It simply says: "This used to be true. Now it isn\'t."', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_assumption_detection_warnings',
						'description' => \__( 'Modify detected findings before they are stored', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_assumption_detection_enabled',
						'description' => \__( 'Toggle individual detectors, receives the detector key', 'functionalities' ),
					),
				),
			),

			'pwa'                  => array(
				'features' => array(
					\__( 'Web App Manifest for installability on mobile and desktop', 'functionalities' ),
					\__( 'Service worker with cache-first, network-first, and stale-while-revalidate strategies', 'functionalities' ),
					\__( 'Custom offline page with cached pages list and auto-reload', 'functionalities' ),
					\__( 'Customizable install prompt with position and style options', 'functionalities' ),
					\__( 'App shortcuts, screenshots, share target, and display override support', 'functionalities' ),
				),
				'usage'    => \__( 'Enable this module to make your site installable as a standalone app. Configure icons, colors, and caching behavior. Visitors can install it from the browser or a custom prompt. The service worker handles offline access and smart caching.', 'functionalities' ),
				'hooks'    => array(
					array(
						'name'        => 'functionalities_pwa_enabled',
						'description' => \__( 'Toggle PWA output', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_pwa_manifest',
						'description' => \__( 'Filter manifest data before output', 'functionalities' ),
					),
					array(
						'name'        => 'functionalities_pwa_service_worker_config',
						'description' => \__( 'Filter service worker configuration', 'functionalities' ),
					),
				),
			),
		);
	}
}
