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
	public static function get( string $module ) : array {
		$docs = self::get_all();
		return $docs[ $module ] ?? array();
	}

	/**
	 * Get all module documentation configurations.
	 *
	 * @return array All module docs.
	 */
	public static function get_all() : array {
		return array(
			'link-management' => array(
				'features' => array(
					\__( 'Automatically adds rel="nofollow" to external links in post content', 'functionalities' ),
					\__( 'Opens external links in new tabs with proper security attributes', 'functionalities' ),
					\__( 'Whitelist trusted domains that should not get nofollow', 'functionalities' ),
					\__( 'Bulk update existing links in your database', 'functionalities' ),
				),
				'hooks' => array(
					array( 'name' => 'functionalities_nofollow_exceptions', 'description' => \__( 'Modify exception list', 'functionalities' ) ),
					array( 'name' => 'functionalities_link_attributes', 'description' => \__( 'Modify link attributes', 'functionalities' ) ),
					array( 'name' => 'functionalities_process_links', 'description' => \__( 'Toggle link processing', 'functionalities' ) ),
				),
			),

			'block-cleanup' => array(
				'features' => array(
					\__( 'Reduces HTML bloat by removing unnecessary block classes', 'functionalities' ),
					\__( 'Useful when your theme provides custom styling for headings, lists, images', 'functionalities' ),
					\__( 'Only affects frontend output, block editor remains unchanged', 'functionalities' ),
				),
				'hooks' => array(
					array( 'name' => 'functionalities_block_cleanup_enabled', 'description' => \__( 'Toggle cleanup globally', 'functionalities' ) ),
					array( 'name' => 'functionalities_block_cleanup_classes', 'description' => \__( 'Modify classes to remove', 'functionalities' ) ),
				),
			),

			'editor-links' => array(
				'features' => array(
					\__( 'Limits link search results to specific post types when inserting links in Gutenberg', 'functionalities' ),
					\__( 'Reduces clutter by hiding unwanted content types from search results', 'functionalities' ),
					\__( 'Works with posts, pages, and custom post types', 'functionalities' ),
				),
				'usage' => \__( 'Enable limitation, then check only the post types you want to appear when searching for links in the editor. Unchecked post types will be hidden from link search results.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_editor_links_enabled', 'description' => \__( 'Toggle feature', 'functionalities' ) ),
					array( 'name' => 'functionalities_editor_links_post_types', 'description' => \__( 'Modify allowed post types', 'functionalities' ) ),
				),
			),

			'snippets' => array(
				'features' => array(
					\__( 'Native Google Analytics 4 integration - just enter your Measurement ID', 'functionalities' ),
					\__( 'Custom header code for meta tags, scripts, styles, and tracking codes', 'functionalities' ),
					\__( 'Custom footer code for chat widgets, tracking pixels, and deferred scripts', 'functionalities' ),
					\__( 'Automatically skips admin pages, feeds, and REST API requests', 'functionalities' ),
				),
				'usage' => \__( 'Allowed tags: script, style, link, meta, noscript.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_snippets_output_enabled', 'description' => \__( 'Disable on specific pages', 'functionalities' ) ),
					array( 'name' => 'functionalities_snippets_ga4_enabled', 'description' => \__( 'Control GA4 per user/page', 'functionalities' ) ),
					array( 'name' => 'functionalities_snippets_header_code', 'description' => \__( 'Modify header code', 'functionalities' ) ),
					array( 'name' => 'functionalities_snippets_footer_code', 'description' => \__( 'Modify footer code', 'functionalities' ) ),
				),
			),

			'schema' => array(
				'features' => array(
					\__( 'Adds itemscope/itemtype to the HTML element for page-level schema', 'functionalities' ),
					\__( 'Wraps article content with Article/BlogPosting microdata', 'functionalities' ),
					\__( 'Adds structured data for headlines, dates, and authors', 'functionalities' ),
					\__( 'Marks header and footer regions with WPHeader/WPFooter types', 'functionalities' ),
				),
				'usage' => \__( 'Site types: WebPage, AboutPage, ContactPage, Blog, SearchResultsPage. Article types: Article, BlogPosting, NewsArticle.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_schema_enabled', 'description' => \__( 'Toggle all schema output', 'functionalities' ) ),
					array( 'name' => 'functionalities_schema_site_type', 'description' => \__( 'Modify site itemtype', 'functionalities' ) ),
					array( 'name' => 'functionalities_schema_article_type', 'description' => \__( 'Modify article itemtype', 'functionalities' ) ),
					array( 'name' => 'functionalities_schema_content', 'description' => \__( 'Modify wrapped content', 'functionalities' ) ),
				),
			),

			'components' => array(
				'features' => array(
					\__( 'Define CSS class names and their style rules in one place', 'functionalities' ),
					\__( 'Components are compiled into a single CSS file for optimal caching', 'functionalities' ),
					\__( 'Available on both frontend and admin pages', 'functionalities' ),
					\__( 'Includes default utility components like visually-hidden, skip-link, and marquee', 'functionalities' ),
				),
				'usage' => \__( 'Add components by entering a CSS selector (e.g., .my-button) and CSS rules (e.g., background: blue; color: white;). Use the grid below to manage your components.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_components_enabled', 'description' => \__( 'Toggle output', 'functionalities' ) ),
					array( 'name' => 'functionalities_components_items', 'description' => \__( 'Add components dynamically', 'functionalities' ) ),
					array( 'name' => 'functionalities_components_css', 'description' => \__( 'Modify generated CSS', 'functionalities' ) ),
					array( 'name' => 'functionalities_components_updated', 'description' => \__( 'Action: fires when CSS file regenerates', 'functionalities' ) ),
				),
			),

			'fonts' => array(
				'features' => array(
					\__( 'Generate @font-face CSS rules for self-hosted fonts', 'functionalities' ),
					\__( 'Support for variable fonts with weight ranges (e.g., 100 900)', 'functionalities' ),
					\__( 'WOFF2 format for modern browsers, optional WOFF fallback', 'functionalities' ),
					\__( 'Configurable font-display strategy (swap, auto, block, etc.)', 'functionalities' ),
				),
				'usage' => \__( 'Upload font files to your media library or server, then add font entries below with the family name and file URLs. Use the generated font-family name in your CSS.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_fonts_enabled', 'description' => \__( 'Toggle output', 'functionalities' ) ),
					array( 'name' => 'functionalities_fonts_items', 'description' => \__( 'Add fonts dynamically', 'functionalities' ) ),
					array( 'name' => 'functionalities_fonts_css', 'description' => \__( 'Modify generated CSS', 'functionalities' ) ),
					array( 'name' => 'functionalities_fonts_before_output', 'description' => \__( 'Action: before output', 'functionalities' ) ),
				),
			),

			'icons' => array(
				'features' => array(
					\__( 'Remove Font Awesome CSS and JavaScript files to reduce page weight', 'functionalities' ),
					\__( 'Convert Font Awesome icon markup to SVG sprite references', 'functionalities' ),
					\__( 'Works with fa, fas, far, and fab icon prefixes', 'functionalities' ),
					\__( 'Significantly improves performance while maintaining icon compatibility', 'functionalities' ),
				),
				'usage' => \__( 'You need an SVG sprite file containing your icons. Point the sprite URL to your file and the module will convert <i class="fa fa-icon"> to <svg><use href="sprite.svg#fa-icon"></use></svg>.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_icons_remove_fa_enabled', 'description' => \__( 'Control asset removal', 'functionalities' ) ),
					array( 'name' => 'functionalities_icons_convert_enabled', 'description' => \__( 'Control conversion', 'functionalities' ) ),
					array( 'name' => 'functionalities_icons_sprite_url', 'description' => \__( 'Modify sprite URL', 'functionalities' ) ),
					array( 'name' => 'functionalities_icons_fa_handles', 'description' => \__( 'Add/remove FA handles', 'functionalities' ) ),
				),
			),

			'misc' => array(
				'features' => array(
					\__( 'Remove bloat like emojis, oEmbeds, and unnecessary meta tags', 'functionalities' ),
					\__( 'Disable security concerns like XML-RPC and version disclosure', 'functionalities' ),
					\__( 'Improve performance by removing unused scripts and styles', 'functionalities' ),
					\__( 'Add useful enhancements like PrismJS and fullscreen textareas', 'functionalities' ),
				),
				'caution' => \__( 'Some options may break functionality if plugins depend on them. Test after enabling. Disable Heartbeat API with care if you use auto-save or real-time features.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_misc_options', 'description' => \__( 'Modify options before application', 'functionalities' ) ),
					array( 'name' => 'functionalities_misc_disable_emojis', 'description' => \__( 'Control emoji removal', 'functionalities' ) ),
					array( 'name' => 'functionalities_misc_disable_embeds', 'description' => \__( 'Control embed removal', 'functionalities' ) ),
				),
			),

			'meta' => array(
				'features' => array(
					\__( 'Adds copyright meta tags with configurable holder', 'functionalities' ),
					\__( 'Dublin Core (DCMI) metadata support', 'functionalities' ),
					\__( 'Per-post license selection metabox', 'functionalities' ),
					\__( 'Integration with popular SEO plugins for Schema.org output', 'functionalities' ),
				),
				'hooks' => array(
					array( 'name' => 'functionalities_meta_enabled', 'description' => \__( 'Toggle module', 'functionalities' ) ),
					array( 'name' => 'functionalities_meta_copyright_holder', 'description' => \__( 'Modify copyright holder', 'functionalities' ) ),
					array( 'name' => 'functionalities_meta_license', 'description' => \__( 'Modify license output', 'functionalities' ) ),
				),
			),

			'updates' => array(
				'features' => array(
					\__( 'Receive plugin updates directly from GitHub releases', 'functionalities' ),
					\__( 'Supports both public and private repositories', 'functionalities' ),
					\__( 'Configurable update check interval', 'functionalities' ),
					\__( 'Shows update notifications in WordPress dashboard', 'functionalities' ),
				),
				'usage' => \__( 'Enter your GitHub repository details. For private repos, create a Personal Access Token with repo scope.', 'functionalities' ),
			),

			'content-regression' => array(
				'features' => array(
					\__( 'Detects when internal links are accidentally removed', 'functionalities' ),
					\__( 'Warns when content is shortened significantly', 'functionalities' ),
					\__( 'Checks heading structure for accessibility issues', 'functionalities' ),
					\__( 'Stores snapshots to compare against historical baselines', 'functionalities' ),
				),
				'usage' => \__( 'This is content integrity, not SEO. The system answers: "Did this update accidentally damage something important?" It never scores content, never suggests fixes, and always compares a post to itself.', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_regression_enabled', 'description' => \__( 'Toggle detection', 'functionalities' ) ),
					array( 'name' => 'functionalities_regression_post_types', 'description' => \__( 'Modify enabled post types', 'functionalities' ) ),
					array( 'name' => 'functionalities_regression_warnings', 'description' => \__( 'Modify detected warnings', 'functionalities' ) ),
					array( 'name' => 'functionalities_regression_snapshot_saved', 'description' => \__( 'Action: after snapshot saved', 'functionalities' ) ),
				),
			),

			'assumption-detection' => array(
				'features' => array(
					\__( 'Detects when multiple sources output the same schema type', 'functionalities' ),
					\__( 'Warns when analytics scripts are loaded multiple times', 'functionalities' ),
					\__( 'Notices when fonts are loaded from redundant sources', 'functionalities' ),
					\__( 'Tracks inline CSS growth over time', 'functionalities' ),
				),
				'usage' => \__( 'This module notices when assumptions stop being true. It does not optimize, does not enforce best practices, and does not recommend plugins. It simply says: "This used to be true. Now it isn\'t."', 'functionalities' ),
				'hooks' => array(
					array( 'name' => 'functionalities_assumptions_enabled', 'description' => \__( 'Toggle detection', 'functionalities' ) ),
					array( 'name' => 'functionalities_assumptions_detectors', 'description' => \__( 'Modify active detectors', 'functionalities' ) ),
					array( 'name' => 'functionalities_assumptions_warnings', 'description' => \__( 'Modify detected warnings', 'functionalities' ) ),
				),
			),
		);
	}
}
