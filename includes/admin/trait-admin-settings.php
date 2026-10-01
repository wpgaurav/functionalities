<?php
/**
 * Admin Settings responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_Settings {

	public static function register_settings(): void {
		\register_setting(
			'functionalities_link_management',
			'functionalities_link_management',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_link_management' ),
				'default'           => array(
					'enabled'                     => false,
					'nofollow_external'           => false,
					'exceptions'                  => '',
					'open_external_new_tab'       => false,
					'open_internal_new_tab'       => false,
					'internal_new_tab_exceptions' => '',
				),
			)
		);

		\add_settings_section(
			'functionalities_link_management_section',
			\__( 'Link Management Settings', 'functionalities' ),
			array( __CLASS__, 'section_link_management' ),
			'functionalities_link_management'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Link Management', 'functionalities' ),
			function () {
				$o       = self::get_link_management_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_link_management[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable link management features', 'functionalities' ) . '</label>';
			},
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		\add_settings_field(
			'nofollow_external',
			\__( 'Add nofollow to external links', 'functionalities' ),
			array( __CLASS__, 'field_nofollow_external' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		\add_settings_field(
			'exceptions',
			\__( 'Exceptions', 'functionalities' ),
			array( __CLASS__, 'field_exceptions' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		// New tab options.
		\add_settings_field(
			'open_external_new_tab',
			\__( 'Open external links in new tab', 'functionalities' ),
			array( __CLASS__, 'field_open_external_new_tab' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'open_internal_new_tab',
			\__( 'Open internal links in new tab', 'functionalities' ),
			array( __CLASS__, 'field_open_internal_new_tab' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'internal_new_tab_exceptions',
			\__( 'Internal new-tab exceptions (domains)', 'functionalities' ),
			array( __CLASS__, 'field_internal_new_tab_exceptions' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		// Advanced features.
		\add_settings_field(
			'json_preset_url',
			\__( 'JSON Preset File Path', 'functionalities' ),
			array( __CLASS__, 'field_json_preset_url' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'enable_developer_filters',
			\__( 'Enable Developer Filters', 'functionalities' ),
			array( __CLASS__, 'field_enable_developer_filters' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);
		\add_settings_field(
			'database_update_tool',
			\__( 'Database Update Tool', 'functionalities' ),
			array( __CLASS__, 'field_database_update_tool' ),
			'functionalities_link_management',
			'functionalities_link_management_section'
		);

		// Block Cleanup settings
		\register_setting(
			'functionalities_block_cleanup',
			'functionalities_block_cleanup',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_block_cleanup' ),
				'default'           => array(
					'enabled'                       => false,
					'remove_heading_block_class'    => false,
					'remove_list_block_class'       => false,
					'remove_image_block_class'      => false,
					'remove_paragraph_block_class'  => false,
					'remove_quote_block_class'      => false,
					'remove_table_block_class'      => false,
					'remove_separator_block_class'  => false,
					'remove_group_block_class'      => false,
					'remove_columns_block_class'    => false,
					'remove_button_block_class'     => false,
					'remove_cover_block_class'      => false,
					'remove_media_text_block_class' => false,
					'custom_classes_to_remove'      => '',
				),
			)
		);

		\add_settings_section(
			'functionalities_block_cleanup_section',
			\__( 'Frontend Block Class Cleanup', 'functionalities' ),
			array( __CLASS__, 'section_block_cleanup' ),
			'functionalities_block_cleanup'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Block Cleanup', 'functionalities' ),
			function () {
				$o       = self::get_block_cleanup_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable block class cleanup on frontend', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);

		\add_settings_field(
			'remove_heading_block_class',
			\__( 'Headings', 'functionalities' ),
			array( __CLASS__, 'field_bc_remove_heading' ),
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_list_block_class',
			\__( 'Lists', 'functionalities' ),
			array( __CLASS__, 'field_bc_remove_list' ),
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_image_block_class',
			\__( 'Images', 'functionalities' ),
			array( __CLASS__, 'field_bc_remove_image' ),
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_paragraph_block_class',
			\__( 'Paragraphs', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_paragraph_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_paragraph_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-paragraph" from paragraph elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_quote_block_class',
			\__( 'Quotes', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_quote_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_quote_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-quote" from blockquote elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_table_block_class',
			\__( 'Tables', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_table_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_table_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-table" from table elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_separator_block_class',
			\__( 'Separators', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_separator_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_separator_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-separator" from hr/separator elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_group_block_class',
			\__( 'Groups', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_group_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_group_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-group" from group containers', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_columns_block_class',
			\__( 'Columns', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_columns_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_columns_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-columns" and "wp-block-column" from column layouts', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_button_block_class',
			\__( 'Buttons', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_button_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_button_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-button(s)" from button elements', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_cover_block_class',
			\__( 'Covers', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_cover_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_cover_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-cover" from cover blocks', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'remove_media_text_block_class',
			\__( 'Media & Text', 'functionalities' ),
			function () {
				$opts    = self::get_block_cleanup_options();
				$checked = ! empty( $opts['remove_media_text_block_class'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_block_cleanup[remove_media_text_block_class]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove "wp-block-media-text" from media-text blocks', 'functionalities' ) . '</label>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);
		\add_settings_field(
			'custom_classes_to_remove',
			\__( 'Custom Classes', 'functionalities' ),
			function () {
				$opts = self::get_block_cleanup_options();
				$val  = isset( $opts['custom_classes_to_remove'] ) ? $opts['custom_classes_to_remove'] : '';
				echo '<textarea name="functionalities_block_cleanup[custom_classes_to_remove]" rows="4" cols="40" class="large-text code">' . \esc_textarea( $val ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Enter additional CSS classes to remove from content output (one per line). Example: my-plugin-class', 'functionalities' ) . '</p>';
			},
			'functionalities_block_cleanup',
			'functionalities_block_cleanup_section'
		);

		// Editor Link Suggestions settings
		\register_setting(
			'functionalities_editor_links',
			'functionalities_editor_links',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_editor_links' ),
				'default'           => array(
					'enabled'      => false,
					'enable_limit' => false,
					'post_types'   => self::default_editor_link_post_types(),
				),
			)
		);

		\add_settings_section(
			'functionalities_editor_links_section',
			\__( 'Editor Link Suggestions', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Control which post types appear in the block editor link search suggestions.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Limits link search results to specific post types when inserting links in Gutenberg', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Reduces clutter by hiding unwanted content types from search results', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Works with posts, pages, and custom post types', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'How to Use', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Enable limitation, then check only the post types you want to appear when searching for links in the editor. Unchecked post types will be hidden from link search results.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_editor_links_enabled</code> — ' . \esc_html__( 'toggle feature', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_editor_links_post_types</code> — ' . \esc_html__( 'modify allowed post types', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_editor_links'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Editor Link Suggestions', 'functionalities' ),
			function () {
				$o       = self::get_editor_links_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_editor_links[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable editor link suggestion filtering', 'functionalities' ) . '</label>';
			},
			'functionalities_editor_links',
			'functionalities_editor_links_section'
		);

		\add_settings_field(
			'enable_limit',
			\__( 'Enable limitation', 'functionalities' ),
			array( __CLASS__, 'field_el_enable' ),
			'functionalities_editor_links',
			'functionalities_editor_links_section'
		);
		\add_settings_field(
			'post_types',
			\__( 'Allowed post types', 'functionalities' ),
			array( __CLASS__, 'field_el_post_types' ),
			'functionalities_editor_links',
			'functionalities_editor_links_section'
		);

		// Header & Footer (Snippets) settings
		\register_setting(
			'functionalities_snippets',
			'functionalities_snippets',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_snippets' ),
				'default'           => array(
					'enabled'    => false,
					'enable_ga4' => false,
					'ga4_id'     => '',
					'header'     => array(),
					'body_open'  => array(),
					'footer'     => array(),
				),
			)
		);

		\add_settings_section(
			'functionalities_snippets_section',
			\__( 'Header & Footer Code', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Insert custom code snippets into your site header and footer without editing theme files.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Native Google Analytics 4 integration - just enter your Measurement ID', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Multiple snippets per location — each independently toggleable', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Custom code for meta tags, scripts, styles, and tracking codes', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Automatically skips admin pages, feeds, and REST API requests', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Allowed Tags', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-family:monospace;font-size:12px;color:#78350f">';
				echo '&lt;script&gt;, &lt;style&gt;, &lt;link&gt;, &lt;meta&gt;, &lt;noscript&gt;';
				echo '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_output_enabled</code> — ' . \esc_html__( 'disable on specific pages', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_ga4_enabled</code> — ' . \esc_html__( 'control GA4 per user/page', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_header_code</code> — ' . \esc_html__( 'modify header code', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_snippets_footer_code</code> — ' . \esc_html__( 'modify footer code', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Actions:', 'functionalities' ) . ' <code>functionalities_before/after_header/footer_snippets</code>';
				echo '</p>';
				echo '</div>';
			},
			'functionalities_snippets'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Header & Footer', 'functionalities' ),
			function () {
				$o       = self::get_snippets_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_snippets[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable header and footer code injection', 'functionalities' ) . '</label>';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);

		\add_settings_field(
			'enable_ga4',
			\__( 'Enable Google Analytics 4', 'functionalities' ),
			function () {
				$o       = self::get_snippets_options();
				$checked = ! empty( $o['enable_ga4'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_snippets[enable_ga4]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Insert GA4 gtag in head', 'functionalities' ) . '</label>';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'ga4_id',
			\__( 'GA4 Measurement ID', 'functionalities' ),
			function () {
				$o   = self::get_snippets_options();
				$val = isset( $o['ga4_id'] ) ? (string) $o['ga4_id'] : '';
				echo '<input type="text" class="regular-text" name="functionalities_snippets[ga4_id]" value="' . \esc_attr( $val ) . '" placeholder="G-XXXXXXXXXX" />';
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'header_snippets',
			\__( 'Header Snippets', 'functionalities' ),
			function () {
				self::field_snippets_repeater( 'header', 'wp_head' );
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'body_open_snippets',
			\__( 'Body Open Snippets', 'functionalities' ),
			function () {
				self::field_snippets_repeater( 'body_open', 'wp_body_open' );
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);
		\add_settings_field(
			'footer_snippets',
			\__( 'Footer Snippets', 'functionalities' ),
			function () {
				self::field_snippets_repeater( 'footer', 'wp_footer' );
			},
			'functionalities_snippets',
			'functionalities_snippets_section'
		);

		// Schema settings
		\register_setting(
			'functionalities_schema',
			'functionalities_schema',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_schema' ),
				'default'           => array(
					'enabled'            => false,
					'enable_site_schema' => true,
					'site_itemtype'      => 'WebPage',
					'enable_header_part' => true,
					'enable_footer_part' => true,
					'enable_article'     => true,
					'article_itemtype'   => 'Article',
					'add_headline'       => true,
					'add_dates'          => true,
					'add_author'         => true,
					'enable_breadcrumbs' => false,
				),
			)
		);

		\add_settings_section(
			'functionalities_schema_section',
			\__( 'Schema Settings', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Add Schema.org microdata attributes to improve search engine understanding of your content.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Adds itemscope/itemtype to the HTML element for page-level schema', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Wraps article content with Article/BlogPosting microdata', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Adds structured data for headlines, dates, and authors', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Marks header and footer regions with WPHeader/WPFooter types', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Supported Types', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">';
				echo '<strong>' . \esc_html__( 'Site:', 'functionalities' ) . '</strong> WebPage, AboutPage, ContactPage, Blog, SearchResultsPage<br>';
				echo '<strong>' . \esc_html__( 'Article:', 'functionalities' ) . '</strong> Article, BlogPosting, NewsArticle';
				echo '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_enabled</code> — ' . \esc_html__( 'toggle all schema output', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_site_type</code> — ' . \esc_html__( 'modify site itemtype', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_article_type</code> — ' . \esc_html__( 'modify article itemtype', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_schema_content</code> — ' . \esc_html__( 'modify wrapped content', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_schema'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Schema', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable schema microdata output', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);

		\add_settings_field(
			'enable_site_schema',
			\__( 'Enable site schema (html tag)', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['enable_site_schema'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_site_schema]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemscope/itemtype to <html>', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'site_itemtype',
			\__( 'Site itemtype', 'functionalities' ),
			function () {
				$o    = self::get_schema_options();
				$val  = $o['site_itemtype'] ?? 'WebPage';
				$opts = array( 'WebPage', 'AboutPage', 'ContactPage', 'Blog', 'SearchResultsPage' );
				echo '<select name="functionalities_schema[site_itemtype]">';
				foreach ( $opts as $opt ) {
					$sel = selected( $val, $opt, false );
					echo '<option value="' . esc_attr( $opt ) . '" ' . esc_attr( $sel ) . '>' . esc_html( $opt ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_header_part',
			\__( 'Add WPHeader microdata', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['enable_header_part'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_header_part]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output a microdata hasPart for header', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_footer_part',
			\__( 'Add WPFooter microdata', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['enable_footer_part'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_footer_part]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output a microdata hasPart for footer', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_article',
			\__( 'Enable Article microdata in content', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['enable_article'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_article]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Wrap content with Article microdata on singular', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'article_itemtype',
			\__( 'Article itemtype', 'functionalities' ),
			function () {
				$o    = self::get_schema_options();
				$val  = $o['article_itemtype'] ?? 'Article';
				$opts = array( 'Article', 'BlogPosting', 'NewsArticle' );
				echo '<select name="functionalities_schema[article_itemtype]">';
				foreach ( $opts as $opt ) {
					$sel = selected( $val, $opt, false );
					echo '<option value="' . esc_attr( $opt ) . '" ' . esc_attr( $sel ) . '>' . esc_html( $opt ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'add_headline',
			\__( 'Add headline from first heading', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['add_headline'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[add_headline]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemprop="headline"', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'add_dates',
			\__( 'Add published/modified dates', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['add_dates'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[add_dates]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemprop dates to time tags', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'add_author',
			\__( 'Add author microdata', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['add_author'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[add_author]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add itemprop="author" where possible', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);
		\add_settings_field(
			'enable_breadcrumbs',
			\__( 'Enable BreadcrumbList', 'functionalities' ),
			function () {
				$o       = self::get_schema_options();
				$checked = ! empty( $o['enable_breadcrumbs'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_schema[enable_breadcrumbs]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Add BreadcrumbList JSON-LD to singular pages', 'functionalities' ) . '</label>';
			},
			'functionalities_schema',
			'functionalities_schema_section'
		);

		// Components settings
		\register_setting(
			'functionalities_components',
			'functionalities_components',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_components' ),
				'default'           => array(
					'enabled' => false,
					'items'   => self::default_components(),
				),
			)
		);

		\add_settings_section(
			'functionalities_components_section',
			\__( 'Components', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Create reusable CSS components that are automatically loaded across your entire site.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Define CSS class names and their style rules in one place', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Components are compiled into a single CSS file for optimal caching', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Available on both frontend and admin pages', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Includes default utility components like visually-hidden, skip-link, and marquee', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'How to Use', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Add components by entering a CSS selector (e.g., .my-button) and CSS rules (e.g., background: blue; color: white;). Use the grid below to manage your components.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_components_enabled</code> — ' . \esc_html__( 'toggle output', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_components_items</code> — ' . \esc_html__( 'add components dynamically', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_components_css</code> — ' . \esc_html__( 'modify generated CSS', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Action:', 'functionalities' ) . ' <code>functionalities_components_updated</code> — ' . \esc_html__( 'fires when CSS file regenerates', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_components'
		);
		\add_settings_field(
			'enabled',
			\__( 'Enable components CSS', 'functionalities' ),
			function () {
				$o       = self::get_components_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_components[enabled]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output components CSS on frontend', 'functionalities' ) . '</label>';
			},
			'functionalities_components',
			'functionalities_components_section'
		);
		\add_settings_field(
			'items',
			\__( 'Component list', 'functionalities' ),
			array( __CLASS__, 'field_components_items' ),
			'functionalities_components',
			'functionalities_components_section'
		);

		// Miscellaneous (bloat control)
		\register_setting(
			'functionalities_misc',
			'functionalities_misc',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_misc' ),
				'default'           => array(
					'enabled'                         => false,
					'disable_block_widgets'           => false,
					'load_separate_core_block_assets' => false,
					'disable_emojis'                  => false,
					'disable_embeds'                  => false,
					'remove_rest_api_links_head'      => false,
					'remove_rsd_wlw_shortlink'        => false,
					'remove_generator_meta'           => false,
					'disable_xmlrpc'                  => false,
					'disable_xmlrpc_pingbacks'        => false,
					'disable_feeds'                   => false,
					'disable_gravatars'               => false,
					'disable_self_pingbacks'          => false,
					'remove_query_strings'            => false,
					'remove_dns_prefetch'             => false,
					'remove_recent_comments_css'      => false,
					'limit_revisions'                 => false,
					'disable_dashicons_for_guests'    => false,
					'disable_heartbeat'               => false,
					'disable_admin_bar_front'         => false,
					'remove_jquery_migrate'           => false,
					'enable_prism_admin'              => false,
					'enable_textarea_fullscreen'      => false,
				),
			)
		);

		\add_settings_section(
			'functionalities_misc_section',
			\__( 'Performance & Cleanup', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Remove unnecessary WordPress features to improve performance and security.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Remove bloat like emojis, oEmbeds, and unnecessary meta tags', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Disable security concerns like XML-RPC and version disclosure', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Improve performance by removing unused scripts and styles', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Add useful enhancements like PrismJS and fullscreen textareas', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Caution', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Some options may break functionality if plugins depend on them. Test after enabling. Disable Heartbeat API with care if you use auto-save or real-time features.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_misc_options</code> — ' . \esc_html__( 'modify options before application', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_misc_disable_emojis</code> — ' . \esc_html__( 'control emoji removal', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_misc_disable_embeds</code> — ' . \esc_html__( 'control embed removal', 'functionalities' );
				echo '</p>';
				echo '</div>';
			},
			'functionalities_misc'
		);

		\add_settings_field(
			'enabled',
			\__( 'Enable Performance & Cleanup', 'functionalities' ),
			function () {
				$o       = self::get_misc_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_misc[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable performance and cleanup features', 'functionalities' ) . '</label>';
			},
			'functionalities_misc',
			'functionalities_misc_section'
		);

		self::add_misc_field( 'disable_block_widgets', \__( 'Disable block-based widget editor (use classic widgets)', 'functionalities' ) );
		self::add_misc_field( 'load_separate_core_block_assets', \__( 'Load core block styles separately (per-block CSS)', 'functionalities' ) );
		self::add_misc_field( 'disable_emojis', \__( 'Disable emojis scripts/styles', 'functionalities' ) );
		self::add_misc_field( 'disable_embeds', \__( 'Disable oEmbed scripts and endpoints', 'functionalities' ) );
		self::add_misc_field( 'remove_rest_api_links_head', \__( 'Remove REST API and oEmbed discovery links from <head>', 'functionalities' ) );
		self::add_misc_field( 'remove_rsd_wlw_shortlink', \__( 'Remove RSD, WLWManifest, and shortlink tags', 'functionalities' ) );
		self::add_misc_field( 'remove_generator_meta', \__( 'Remove WordPress version meta (generator)', 'functionalities' ) );
		self::add_misc_field( 'disable_xmlrpc', \__( 'Disable XML-RPC (complete)', 'functionalities' ) );
		self::add_misc_field( 'disable_xmlrpc_pingbacks', \__( 'Disable only XML-RPC Pingbacks', 'functionalities' ) );
		self::add_misc_field( 'disable_feeds', \__( 'Disable RSS/Atom feeds (redirect to homepage)', 'functionalities' ) );
		self::add_misc_field( 'disable_gravatars', \__( 'Disable Gravatars (site-wide)', 'functionalities' ) );
		self::add_misc_field( 'disable_self_pingbacks', \__( 'Disable self-pingbacks (pings to own site)', 'functionalities' ) );
		self::add_misc_field( 'remove_query_strings', \__( 'Remove query strings from static resources (?ver=)', 'functionalities' ) );
		self::add_misc_field( 'remove_dns_prefetch', \__( 'Remove DNS prefetch links from <head>', 'functionalities' ) );
		self::add_misc_field( 'remove_recent_comments_css', \__( 'Remove Recent Comments inline CSS', 'functionalities' ) );
		self::add_misc_field( 'limit_revisions', \__( 'Limit post revisions', 'functionalities' ) );
		\add_settings_field(
			'revisions_limit',
			\__( 'Revisions to keep', 'functionalities' ),
			function () {
				$opts = self::get_misc_options();
				$val  = isset( $opts['revisions_limit'] ) ? (int) $opts['revisions_limit'] : 10;
				echo '<input type="number" min="0" max="100" class="small-text" name="functionalities_misc[revisions_limit]" value="' . \esc_attr( $val ) . '"> ';
				echo \esc_html__( 'revisions per post. Applies when the option above is enabled. Zero keeps none.', 'functionalities' );
			},
			'functionalities_misc',
			'functionalities_misc_section'
		);
		self::add_misc_field( 'disable_dashicons_for_guests', \__( 'Disable Dashicons on frontend for non-logged-in users', 'functionalities' ) );
		self::add_misc_field( 'disable_heartbeat', \__( 'Disable Heartbeat API on the frontend', 'functionalities' ) );
		self::add_misc_field( 'disable_heartbeat_admin', \__( 'Also disable Heartbeat in wp-admin (breaks autosave and post locking)', 'functionalities' ) );
		self::add_misc_field( 'disable_admin_bar_front', \__( 'Disable admin bar on the frontend', 'functionalities' ) );
		self::add_misc_field( 'remove_jquery_migrate', \__( 'Remove jQuery Migrate from frontend', 'functionalities' ) );
		self::add_misc_field( 'enable_prism_admin', \__( 'Load PrismJS on admin screens (code highlighting where applicable)', 'functionalities' ) );
		self::add_misc_field( 'enable_textarea_fullscreen', \__( 'Enable fullscreen toggle for all backend textareas', 'functionalities' ) );

		// Fonts settings
		\register_setting(
			'functionalities_fonts',
			'functionalities_fonts',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_fonts' ),
				'default'           => array(
					'enabled' => false,
					'items'   => array(),
				),
			)
		);
		\add_settings_section(
			'functionalities_fonts_section',
			\__( 'Font Families', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Self-host custom fonts with automatic @font-face CSS generation.', 'functionalities' ) . '</p>';

				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'What This Module Does', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Generate @font-face CSS rules for self-hosted fonts', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Support for variable fonts with weight ranges (e.g., 100 900)', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'WOFF2 format for modern browsers, optional WOFF fallback', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Configurable font-display strategy (swap, auto, block, etc.)', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';

				echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'How to Use', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px">' . \esc_html__( 'Upload font files to your media library or server, then add font entries below with the family name and file URLs. Use the generated font-family name in your CSS.', 'functionalities' ) . '</p>';
				echo '</div>';

				echo '<div style="background:#eff6ff;border:1px solid #93c5fd;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px;color:#1e40af">' . \esc_html__( 'For Developers', 'functionalities' ) . '</h4>';
				echo '<p style="margin:0;font-size:13px;color:#1e3a8a">';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_fonts_enabled</code> — ' . \esc_html__( 'toggle output', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_fonts_items</code> — ' . \esc_html__( 'add fonts dynamically', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Filter:', 'functionalities' ) . ' <code>functionalities_fonts_css</code> — ' . \esc_html__( 'modify generated CSS', 'functionalities' ) . '<br>';
				echo \esc_html__( 'Action:', 'functionalities' ) . ' <code>functionalities_fonts_before_output</code>';
				echo '</p>';
				echo '</div>';
			},
			'functionalities_fonts'
		);
		\add_settings_field(
			'fonts_enabled',
			\__( 'Enable fonts output', 'functionalities' ),
			function () {
				$o       = self::get_fonts_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_fonts[enabled]" value="1" ' . esc_attr( $checked ) . '> ' . \esc_html__( 'Output @font-face CSS across site (front and admin)', 'functionalities' ) . '</label>';
			},
			'functionalities_fonts',
			'functionalities_fonts_section'
		);
		\add_settings_field(
			'fonts_items',
			\__( 'Families', 'functionalities' ),
			array( __CLASS__, 'field_fonts_items' ),
			'functionalities_fonts',
			'functionalities_fonts_section'
		);
		\add_settings_field(
			'fonts_assignments',
			\__( 'Typography Assignments', 'functionalities' ),
			array( __CLASS__, 'field_fonts_assignments' ),
			'functionalities_fonts',
			'functionalities_fonts_section'
		);

		// Login Security settings.
		\register_setting(
			'functionalities_login_security',
			'functionalities_login_security',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_login_security' ),
				'default'           => array(
					'enabled'                       => false,
					'limit_login_attempts'          => true,
					'max_attempts'                  => 5,
					'lockout_duration'              => 15,
					'disable_xmlrpc_auth'           => true,
					'disable_application_passwords' => false,
					'hide_login_errors'             => true,
					'trust_proxy_headers'           => false,
					'custom_logo_url'               => '',
					'custom_background_color'       => '',
					'custom_form_background'        => '',
				),
			)
		);
		\add_settings_section(
			'functionalities_login_security_section',
			\__( 'Login Security Settings', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Protect your login page and customize its appearance.', 'functionalities' ) . '</p>';
				echo '<div style="background:#f0fdf4;border:1px solid #86efac;border-radius:6px;padding:12px 16px;margin:12px 0">';
				echo '<h4 style="margin:0 0 8px">' . \esc_html__( 'Security Features', 'functionalities' ) . '</h4>';
				echo '<ul style="margin:0;padding-left:20px">';
				echo '<li>' . \esc_html__( 'Limit failed login attempts to prevent brute force attacks', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Disable XML-RPC authentication to block remote login attacks', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Hide specific login errors to prevent username enumeration', 'functionalities' ) . '</li>';
				echo '<li>' . \esc_html__( 'Customize the login page with your logo and colors', 'functionalities' ) . '</li>';
				echo '</ul>';
				echo '</div>';
				if ( \Functionalities\Features\Login_Security::lockouts_share_one_ip() ) {
					echo '<div class="notice notice-warning inline" style="margin:12px 0;padding:10px 14px">';
					echo '<strong>' . \esc_html__( 'Every recent lockout came from the same address.', 'functionalities' ) . '</strong> ';
					echo \esc_html__( 'That usually means this site sits behind a proxy or CDN and all visitors share one address, so an IP lockout blocks everyone. Enable "Trust Proxy Headers" if the proxy is trusted, and add your own address to the allowlist below.', 'functionalities' );
					echo '</div>';
				}

				$logs = \Functionalities\Features\Login_Security::get_lockout_log( 5 );
				if ( ! empty( $logs ) ) {
					$unlock_nonce = \wp_create_nonce( 'functionalities_login_unlock' );
					echo '<div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:12px 16px;margin:12px 0">';
					echo '<h4 style="margin:0 0 8px;color:#92400e">' . \esc_html__( 'Recent Lockouts', 'functionalities' ) . '</h4>';
					echo '<ul style="margin:0;padding:0;list-style:none;font-size:13px" id="functionalities-lockout-log">';
					foreach ( $logs as $log ) {
						echo '<li style="display:flex;align-items:center;gap:10px;padding:4px 0">';
						echo '<strong>' . \esc_html( $log['ip'] ) . '</strong> — ' . \esc_html( $log['username'] ) . ' (' . \esc_html( $log['time'] ) . ') ';
						echo '<button type="button" class="button button-small functionalities-unlock" data-ip="' . \esc_attr( $log['ip'] ) . '" data-username="' . \esc_attr( $log['username'] ) . '">' . \esc_html__( 'Unlock', 'functionalities' ) . '</button>';
						echo '</li>';
					}
					echo '</ul>';
					echo '</div>';
					printf(
						'<script>jQuery(function($){$("#functionalities-lockout-log").on("click",".functionalities-unlock",function(){var b=$(this);b.prop("disabled",true);$.post(ajaxurl,{action:"functionalities_login_unlock",nonce:%s,ip:b.data("ip"),username:b.data("username")},function(r){b.text(r.success?%s:%s);});});});</script>',
						\wp_json_encode( $unlock_nonce ),
						\wp_json_encode( \__( 'Unlocked', 'functionalities' ) ),
						\wp_json_encode( \__( 'Failed', 'functionalities' ) )
					);
				}
			},
			'functionalities_login_security'
		);
		\add_settings_field(
			'login_enabled',
			\__( 'Enable Login Security', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<label><input type="checkbox" name="functionalities_login_security[enabled]" value="1" ' . checked( ! empty( $o['enabled'] ), true, false ) . '> ' . \esc_html__( 'Enable login security features', 'functionalities' ) . '</label>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'limit_login_attempts',
			\__( 'Limit Login Attempts', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<label><input type="checkbox" name="functionalities_login_security[limit_login_attempts]" value="1" ' . checked( ! empty( $o['limit_login_attempts'] ), true, false ) . '> ' . \esc_html__( 'Block IPs after too many failed attempts', 'functionalities' ) . '</label>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'max_attempts',
			\__( 'Max Attempts', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<input type="number" name="functionalities_login_security[max_attempts]" value="' . \esc_attr( $o['max_attempts'] ?? 5 ) . '" min="1" max="20" class="small-text"> ' . \esc_html__( 'failed attempts before lockout', 'functionalities' );
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'lockout_duration',
			\__( 'Lockout Duration', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<input type="number" name="functionalities_login_security[lockout_duration]" value="' . \esc_attr( $o['lockout_duration'] ?? 15 ) . '" min="1" max="1440" class="small-text"> ' . \esc_html__( 'minutes', 'functionalities' );
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'disable_xmlrpc_auth',
			\__( 'Disable XML-RPC', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<label><input type="checkbox" name="functionalities_login_security[disable_xmlrpc_auth]" value="1" ' . checked( ! empty( $o['disable_xmlrpc_auth'] ), true, false ) . '> ' . \esc_html__( 'Disable XML-RPC authentication (recommended)', 'functionalities' ) . '</label>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'disable_application_passwords',
			\__( 'Disable App Passwords', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<label><input type="checkbox" name="functionalities_login_security[disable_application_passwords]" value="1" ' . checked( ! empty( $o['disable_application_passwords'] ), true, false ) . '> ' . \esc_html__( 'Disable WordPress Application Passwords', 'functionalities' ) . '</label>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'hide_login_errors',
			\__( 'Hide Login Errors', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<label><input type="checkbox" name="functionalities_login_security[hide_login_errors]" value="1" ' . checked( ! empty( $o['hide_login_errors'] ), true, false ) . '> ' . \esc_html__( 'Show generic error instead of specific username/password errors', 'functionalities' ) . '</label>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'trust_proxy_headers',
			\__( 'Trust Proxy Headers', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<label><input type="checkbox" name="functionalities_login_security[trust_proxy_headers]" value="1" ' . checked( ! empty( $o['trust_proxy_headers'] ), true, false ) . '> ' . \esc_html__( 'Read X-Forwarded-For only from configured trusted proxies', 'functionalities' ) . '</label>';
				echo '<p class="description">' . \esc_html__( 'Your proxy must overwrite or append the real client address to X-Forwarded-For. Untrusted peers and Client-IP headers are ignored. With this off, lockouts use the connecting address.', 'functionalities' ) . '</p>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'trusted_proxy_ips',
			\__( 'Trusted Proxy Addresses', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<textarea name="functionalities_login_security[trusted_proxy_ips]" rows="4" class="large-text code">' . \esc_textarea( $o['trusted_proxy_ips'] ?? '' ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'One proxy IP address or CIDR range per line. Leave empty to ignore forwarding headers. Include only proxies that sanitize incoming forwarding headers.', 'functionalities' ) . '</p>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'lock_usernames',
			\__( 'Throttle by Username', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<label><input type="checkbox" name="functionalities_login_security[lock_usernames]" value="1" ' . checked( ! empty( $o['lock_usernames'] ), true, false ) . '> ' . \esc_html__( 'Also lock a username after repeated failures from any address', 'functionalities' ) . '</label>';
				echo '<p class="description">' . \esc_html__( 'Catches distributed attempts against one account. Triggers at twice the attempt limit above.', 'functionalities' ) . '</p>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'allowlist_ips',
			\__( 'Never Lock These IPs', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<textarea name="functionalities_login_security[allowlist_ips]" rows="3" class="large-text code" placeholder="203.0.113.9&#10;198.51.100.*">' . \esc_textarea( $o['allowlist_ips'] ?? '' ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'One per line. A trailing * matches a prefix. Add your own address so a lockout cannot shut you out.', 'functionalities' ) . '</p>';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'custom_logo_url',
			\__( 'Custom Logo URL', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<input type="url" name="functionalities_login_security[custom_logo_url]" value="' . \esc_attr( $o['custom_logo_url'] ?? '' ) . '" class="regular-text" placeholder="https://example.com/logo.png">';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);
		\add_settings_field(
			'custom_background_color',
			\__( 'Background Color', 'functionalities' ),
			function () {
				$o = self::get_login_security_options();
				echo '<input type="text" name="functionalities_login_security[custom_background_color]" value="' . \esc_attr( $o['custom_background_color'] ?? '' ) . '" class="small-text" placeholder="#f0f0f1">';
			},
			'functionalities_login_security',
			'functionalities_login_security_section'
		);

		// Meta & Copyright settings.
		\register_setting(
			'functionalities_meta',
			'functionalities_meta',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_meta' ),
				'default'           => array(
					'enabled'                   => false,
					'enable_copyright_meta'     => true,
					'enable_dublin_core'        => true,
					'enable_license_metabox'    => true,
					'enable_schema_integration' => true,
					'default_license'           => 'all-rights-reserved',
					'default_license_url'       => '',
					'post_types'                => array( 'post' ),
					'copyright_holder_type'     => 'author',
					'custom_copyright_holder'   => '',
					'dc_language'               => '',
				),
			)
		);

		\add_settings_section(
			'functionalities_meta_section',
			\__( 'Meta & Copyright Settings', 'functionalities' ),
			array( __CLASS__, 'section_meta' ),
			'functionalities_meta'
		);

		\add_settings_field(
			'meta_enabled',
			\__( 'Enable Meta Module', 'functionalities' ),
			function () {
				$o       = self::get_meta_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable copyright, Dublin Core, and licensing features', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_copyright_meta',
			\__( 'Copyright Meta Tags', 'functionalities' ),
			function () {
				$o       = self::get_meta_options();
				$checked = ! empty( $o['enable_copyright_meta'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enable_copyright_meta]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Output copyright, author, owner, and rights meta tags', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_dublin_core',
			\__( 'Dublin Core (DCMI) Metadata', 'functionalities' ),
			function () {
				$o       = self::get_meta_options();
				$checked = ! empty( $o['enable_dublin_core'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enable_dublin_core]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Output Dublin Core metadata (DC.title, DC.creator, DC.rights, etc.)', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_license_metabox',
			\__( 'License Metabox', 'functionalities' ),
			function () {
				$o       = self::get_meta_options();
				$checked = ! empty( $o['enable_license_metabox'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_meta[enable_license_metabox]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Show license selection metabox in post editor', 'functionalities' ) . '</label>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'enable_schema_integration',
			\__( 'SEO Plugin Schema Integration', 'functionalities' ),
			function () {
				$o            = self::get_meta_options();
				$checked      = ! empty( $o['enable_schema_integration'] ) ? 'checked' : '';
				$detected     = \Functionalities\Features\Meta::detect_seo_plugin();
				$plugin_names = array(
					'rank-math'     => 'Rank Math',
					'yoast'         => 'Yoast SEO',
					'seo-framework' => 'The SEO Framework',
					'seopress'      => 'SEOPress',
					'aioseo'        => 'All in One SEO',
					'none'          => \__( 'None detected', 'functionalities' ),
				);
				echo '<label><input type="checkbox" name="functionalities_meta[enable_schema_integration]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Add copyright data to SEO plugin Schema.org output', 'functionalities' ) . '</label>';
				echo '<p class="description" style="margin-top:4px">';
				if ( $detected !== 'none' ) {
					echo '<span style="color:#059669">✓ ' . \esc_html__( 'Detected:', 'functionalities' ) . ' <strong>' . \esc_html( $plugin_names[ $detected ] ) . '</strong></span>';
				} else {
					echo \esc_html__( 'Supports: Rank Math, Yoast SEO, The SEO Framework, SEOPress, AIOSEO', 'functionalities' );
				}
				echo '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'default_license',
			\__( 'Default License', 'functionalities' ),
			function () {
				$o        = self::get_meta_options();
				$val      = isset( $o['default_license'] ) ? (string) $o['default_license'] : 'all-rights-reserved';
				$licenses = array(
					'all-rights-reserved' => \__( 'All Rights Reserved', 'functionalities' ),
					'cc-by'               => 'CC BY 4.0',
					'cc-by-sa'            => 'CC BY-SA 4.0',
					'cc-by-nc'            => 'CC BY-NC 4.0',
					'cc-by-nc-sa'         => 'CC BY-NC-SA 4.0',
					'cc-by-nd'            => 'CC BY-ND 4.0',
					'cc-by-nc-nd'         => 'CC BY-NC-ND 4.0',
					'cc0'                 => 'CC0 1.0 (Public Domain)',
				);
				echo '<select name="functionalities_meta[default_license]">';
				foreach ( $licenses as $key => $label ) {
					$sel = selected( $val, $key, false );
					echo '<option value="' . \esc_attr( $key ) . '" ' . esc_attr( $sel ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . \esc_html__( 'Default license for new posts (can be overridden per-post).', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'default_license_url',
			\__( 'Custom License URL', 'functionalities' ),
			function () {
				$o   = self::get_meta_options();
				$val = isset( $o['default_license_url'] ) ? (string) $o['default_license_url'] : '';
				echo '<input type="url" class="regular-text" name="functionalities_meta[default_license_url]" value="' . \esc_attr( $val ) . '" placeholder="https://example.com/terms/" />';
				echo '<p class="description">' . \esc_html__( 'Custom URL for "All Rights Reserved" license (e.g., your terms/disclaimer page).', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'meta_post_types',
			\__( 'Post Types', 'functionalities' ),
			function () {
				$o        = self::get_meta_options();
				$selected = isset( $o['post_types'] ) && is_array( $o['post_types'] ) ? $o['post_types'] : array( 'post' );
				$pts      = \get_post_types( array( 'public' => true ), 'objects' );
				echo '<fieldset>';
				foreach ( $pts as $name => $obj ) {
					$is_checked = in_array( $name, $selected, true ) ? 'checked' : '';
					$label      = sprintf( '%s (%s)', $obj->labels->singular_name ?? $name, $name );
					echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_meta[post_types][]" value="' . \esc_attr( $name ) . '" ' . esc_attr( $is_checked ) . '> ' . \esc_html( $label ) . '</label>';
				}
				echo '</fieldset>';
				echo '<p class="description">' . \esc_html__( 'Select post types where meta tags and license metabox should appear.', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'copyright_holder_type',
			\__( 'Copyright Holder', 'functionalities' ),
			function () {
				$o       = self::get_meta_options();
				$val     = isset( $o['copyright_holder_type'] ) ? (string) $o['copyright_holder_type'] : 'author';
				$options = array(
					'author' => \__( 'Post Author', 'functionalities' ),
					'site'   => \__( 'Site Name', 'functionalities' ),
					'custom' => \__( 'Custom Name', 'functionalities' ),
				);
				echo '<select name="functionalities_meta[copyright_holder_type]" id="meta_copyright_holder_type">';
				foreach ( $options as $key => $label ) {
					$sel = selected( $val, $key, false );
					echo '<option value="' . \esc_attr( $key ) . '" ' . esc_attr( $sel ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . \esc_html__( 'Who should be listed as the copyright holder.', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'custom_copyright_holder',
			\__( 'Custom Copyright Holder Name', 'functionalities' ),
			function () {
				$o   = self::get_meta_options();
				$val = isset( $o['custom_copyright_holder'] ) ? (string) $o['custom_copyright_holder'] : '';
				echo '<input type="text" class="regular-text" name="functionalities_meta[custom_copyright_holder]" value="' . \esc_attr( $val ) . '" placeholder="' . \esc_attr__( 'Company Name or Person', 'functionalities' ) . '" />';
				echo '<p class="description">' . \esc_html__( 'Used when "Custom Name" is selected above.', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		\add_settings_field(
			'dc_language',
			\__( 'Dublin Core Language', 'functionalities' ),
			function () {
				$o         = self::get_meta_options();
				$val       = isset( $o['dc_language'] ) ? (string) $o['dc_language'] : '';
				$site_lang = \get_bloginfo( 'language' );
				echo '<input type="text" class="small-text" name="functionalities_meta[dc_language]" value="' . \esc_attr( $val ) . '" placeholder="' . \esc_attr( $site_lang ) . '" />';
				echo '<p class="description">' . \esc_html__( 'Leave empty to use site language. Use ISO 639 codes (en, en-US, de, etc.).', 'functionalities' ) . '</p>';
			},
			'functionalities_meta',
			'functionalities_meta_section'
		);

		// Content Regression Detection settings.
		\register_setting(
			'functionalities_content_regression',
			'functionalities_content_regression',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_content_regression' ),
				'default'           => array(
					'enabled'                    => false,
					'post_types'                 => array( 'post', 'page' ),
					'link_drop_enabled'          => true,
					'link_drop_percent'          => 30,
					'link_drop_absolute'         => 3,
					'exclude_nofollow_links'     => false,
					'word_count_enabled'         => true,
					'word_count_drop_percent'    => 35,
					'word_count_min_age_days'    => 30,
					'word_count_compare_average' => false,
					'exclude_shortcodes'         => false,
					'heading_enabled'            => true,
					'detect_missing_h1'          => true,
					'detect_multiple_h1'         => true,
					'detect_skipped_levels'      => true,
					'snapshot_rolling_count'     => 5,
					'show_post_column'           => true,
				),
			)
		);

		\add_settings_section(
			'functionalities_content_regression_section',
			\__( 'Content Integrity Settings', 'functionalities' ),
			array( __CLASS__, 'section_content_regression' ),
			'functionalities_content_regression'
		);

		// Enable module.
		\add_settings_field(
			'regression_enabled',
			\__( 'Enable Content Integrity', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Detect structural regressions when posts are updated', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Post types.
		\add_settings_field(
			'regression_post_types',
			\__( 'Post Types', 'functionalities' ),
			function () {
				$o        = self::get_content_regression_options();
				$selected = isset( $o['post_types'] ) && is_array( $o['post_types'] ) ? $o['post_types'] : array( 'post', 'page' );
				$pts      = \get_post_types( array( 'public' => true ), 'objects' );
				echo '<fieldset>';
				foreach ( $pts as $name => $obj ) {
					if ( 'attachment' === $name ) {
						continue;
					}
					$is_checked = in_array( $name, $selected, true ) ? 'checked' : '';
					$label      = sprintf( '%s (%s)', $obj->labels->singular_name ?? $name, $name );
					echo '<label style="display:block; margin:2px 0;"><input type="checkbox" name="functionalities_content_regression[post_types][]" value="' . \esc_attr( $name ) . '" ' . esc_attr( $is_checked ) . '> ' . \esc_html( $label ) . '</label>';
				}
				echo '</fieldset>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Link drop detection header.
		\add_settings_field(
			'link_drop_header',
			'<strong>' . \__( 'Internal Link Detection', 'functionalities' ) . '</strong>',
			function () {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Detect when internal links are removed from content.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'link_drop_enabled',
			\__( 'Enable Link Detection', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['link_drop_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[link_drop_enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when internal links drop significantly', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'link_drop_percent',
			\__( 'Link Drop Threshold (%)', 'functionalities' ),
			function () {
				$o   = self::get_content_regression_options();
				$val = isset( $o['link_drop_percent'] ) ? (int) $o['link_drop_percent'] : 30;
				echo '<input type="number" min="1" max="100" class="small-text" name="functionalities_content_regression[link_drop_percent]" value="' . \esc_attr( $val ) . '" /> %';
				echo '<p class="description">' . \esc_html__( 'Warn if internal links drop by this percentage or more.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'link_drop_absolute',
			\__( 'Link Drop Absolute Threshold', 'functionalities' ),
			function () {
				$o   = self::get_content_regression_options();
				$val = isset( $o['link_drop_absolute'] ) ? (int) $o['link_drop_absolute'] : 3;
				echo '<input type="number" min="1" max="100" class="small-text" name="functionalities_content_regression[link_drop_absolute]" value="' . \esc_attr( $val ) . '" /> ' . \esc_html__( 'links', 'functionalities' );
				echo '<p class="description">' . \esc_html__( 'Also warn if this many links are removed (whichever triggers first).', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'exclude_nofollow_links',
			\__( 'Exclude Nofollow Links', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['exclude_nofollow_links'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[exclude_nofollow_links]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Do not count links with rel="nofollow"', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Word count detection header.
		\add_settings_field(
			'word_count_header',
			'<strong>' . \__( 'Word Count Detection', 'functionalities' ) . '</strong>',
			function () {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Detect when content is shortened significantly.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'word_count_enabled',
			\__( 'Enable Word Count Detection', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['word_count_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[word_count_enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when word count drops significantly', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'word_count_drop_percent',
			\__( 'Word Count Drop Threshold (%)', 'functionalities' ),
			function () {
				$o   = self::get_content_regression_options();
				$val = isset( $o['word_count_drop_percent'] ) ? (int) $o['word_count_drop_percent'] : 35;
				echo '<input type="number" min="1" max="100" class="small-text" name="functionalities_content_regression[word_count_drop_percent]" value="' . \esc_attr( $val ) . '" /> %';
				echo '<p class="description">' . \esc_html__( 'Warn if word count drops by this percentage.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'word_count_min_age_days',
			\__( 'Minimum Post Age (days)', 'functionalities' ),
			function () {
				$o   = self::get_content_regression_options();
				$val = isset( $o['word_count_min_age_days'] ) ? (int) $o['word_count_min_age_days'] : 30;
				echo '<input type="number" min="0" max="365" class="small-text" name="functionalities_content_regression[word_count_min_age_days]" value="' . \esc_attr( $val ) . '" /> ' . \esc_html__( 'days', 'functionalities' );
				echo '<p class="description">' . \esc_html__( 'Only check word count for posts older than this (to avoid alerts on new posts).', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'exclude_shortcodes',
			\__( 'Exclude Shortcodes', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['exclude_shortcodes'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[exclude_shortcodes]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Remove shortcode content from word count', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Heading detection header.
		\add_settings_field(
			'heading_header',
			'<strong>' . \__( 'Heading Structure Detection', 'functionalities' ) . '</strong>',
			function () {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Detect accessibility issues with heading hierarchy.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'heading_enabled',
			\__( 'Enable Heading Detection', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['heading_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[heading_enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Check heading structure for accessibility issues', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'detect_missing_h1',
			\__( 'Detect Missing H1', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['detect_missing_h1'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_missing_h1]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when no H1 heading is present', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'detect_multiple_h1',
			\__( 'Detect Multiple H1s', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['detect_multiple_h1'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_multiple_h1]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple H1 headings exist', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'detect_skipped_levels',
			\__( 'Detect Skipped Levels', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['detect_skipped_levels'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[detect_skipped_levels]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when heading levels are skipped (e.g., H2 to H4)', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Advanced settings header.
		\add_settings_field(
			'advanced_header',
			'<strong>' . \__( 'Advanced Settings', 'functionalities' ) . '</strong>',
			function () {
				echo '<p class="description" style="margin:0">' . \esc_html__( 'Configure snapshot and display options.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'snapshot_rolling_count',
			\__( 'Snapshots to Keep', 'functionalities' ),
			function () {
				$o   = self::get_content_regression_options();
				$val = isset( $o['snapshot_rolling_count'] ) ? (int) $o['snapshot_rolling_count'] : 5;
				echo '<input type="number" min="1" max="20" class="small-text" name="functionalities_content_regression[snapshot_rolling_count]" value="' . \esc_attr( $val ) . '" />';
				echo '<p class="description">' . \esc_html__( 'Number of historical snapshots to retain per post.', 'functionalities' ) . '</p>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		\add_settings_field(
			'show_post_column',
			\__( 'Show Post List Column', 'functionalities' ),
			function () {
				$o       = self::get_content_regression_options();
				$checked = ! empty( $o['show_post_column'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_content_regression[show_post_column]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Display integrity status icon in post list table', 'functionalities' ) . '</label>';
			},
			'functionalities_content_regression',
			'functionalities_content_regression_section'
		);

		// Assumption Detection settings.
		\register_setting(
			'functionalities_assumption_detection',
			'functionalities_assumption_detection',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_assumption_detection' ),
				'default'           => array(
					'enabled'                         => false,
					'detect_schema_collision'         => true,
					'detect_analytics_dupe'           => true,
					'detect_font_redundancy'          => true,
					'detect_inline_css_growth'        => true,
					'inline_css_threshold_kb'         => 50,
					'detect_jquery_conflicts'         => true,
					'detect_meta_duplication'         => true,
					'detect_rest_exposure'            => true,
					'detect_lazy_load_conflict'       => true,
					'detect_mixed_content'            => true,
					'detect_missing_security_headers' => true,
					'detect_debug_exposure'           => true,
					'detect_cron_issues'              => true,
				),
			)
		);

		\add_settings_section(
			'functionalities_assumption_detection_section',
			\__( 'Assumption Detection Settings', 'functionalities' ),
			array( __CLASS__, 'section_assumption_detection' ),
			'functionalities_assumption_detection'
		);

		\add_settings_field(
			'assumption_enabled',
			\__( 'Enable Assumption Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[enabled]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Detect when implicit site assumptions stop being true', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_schema_collision',
			\__( 'Schema Collision Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_schema_collision'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_schema_collision]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple sources output the same schema type', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_analytics_dupe',
			\__( 'Analytics Duplication Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_analytics_dupe'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_analytics_dupe]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when analytics scripts load multiple times', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_font_redundancy',
			\__( 'Font Redundancy Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_font_redundancy'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_font_redundancy]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when fonts load from multiple sources', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_inline_css_growth',
			\__( 'Inline CSS Growth Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_inline_css_growth'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_inline_css_growth]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Track inline CSS size over time', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'inline_css_threshold_kb',
			\__( 'Inline CSS Threshold (KB)', 'functionalities' ),
			function () {
				$o   = self::get_assumption_detection_options();
				$val = isset( $o['inline_css_threshold_kb'] ) ? (int) $o['inline_css_threshold_kb'] : 50;
				echo '<input type="number" min="10" max="500" class="small-text" name="functionalities_assumption_detection[inline_css_threshold_kb]" value="' . \esc_attr( $val ) . '" /> KB';
				echo '<p class="description">' . \esc_html__( 'Warn when inline CSS exceeds this size.', 'functionalities' ) . '</p>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_jquery_conflicts',
			\__( 'jQuery Conflict Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_jquery_conflicts'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_jquery_conflicts]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple jQuery versions or sources are loaded', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_meta_duplication',
			\__( 'Meta Tag Duplication Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_meta_duplication'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_meta_duplication]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when duplicate meta tags are detected (viewport, robots, OG tags)', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_rest_exposure',
			\__( 'REST API Exposure Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_rest_exposure'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_rest_exposure]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when REST API exposes user information publicly', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_lazy_load_conflict',
			\__( 'Lazy Loading Conflict Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_lazy_load_conflict'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_lazy_load_conflict]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when multiple lazy loading implementations are detected', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_mixed_content',
			\__( 'Mixed Content Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_mixed_content'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_mixed_content]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when HTTP resources are loaded on HTTPS pages', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_missing_security_headers',
			\__( 'Security Headers Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_missing_security_headers'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_missing_security_headers]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when critical security headers are missing', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_debug_exposure',
			\__( 'Debug Mode Exposure', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_debug_exposure'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_debug_exposure]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when WP_DEBUG or error display is enabled in production', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detect_cron_issues',
			\__( 'Cron Issues Detection', 'functionalities' ),
			function () {
				$o       = self::get_assumption_detection_options();
				$checked = ! empty( $o['detect_cron_issues'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_assumption_detection[detect_cron_issues]" value="1" ' . esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Warn when WP-Cron is disabled or has stuck jobs', 'functionalities' ) . '</label>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		// Explicit save button field - ensures visibility.
		\add_settings_field(
			'assumption_save_settings',
			'',
			function () {
				echo '<div class="functionalities-settings-actions" style="padding: 15px 0; border-top: 1px solid #c3c4c7; margin-top: 10px;">';
				\submit_button( \__( 'Save Settings', 'functionalities' ), 'primary', 'submit', false );
				echo ' ';
				echo '<button type="button" class="button button-secondary" id="functionalities-run-detection" style="margin-left: 10px;">';
				echo \esc_html__( 'Run Detection Now', 'functionalities' );
				echo '</button>';
				echo '</div>';
			},
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		\add_settings_field(
			'detected_assumptions',
			\__( 'Detected Assumptions', 'functionalities' ),
			array( __CLASS__, 'field_detected_assumptions' ),
			'functionalities_assumption_detection',
			'functionalities_assumption_detection_section'
		);

		// PWA settings.
		\register_setting(
			'functionalities_pwa',
			'functionalities_pwa',
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_pwa' ),
				'default'           => array(
					'enabled' => false,
				),
			)
		);

		\add_settings_section(
			'functionalities_pwa_identity',
			\__( 'App Identity', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Basic information about your progressive web app.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_enabled',
			\__( 'Enable PWA', 'functionalities' ),
			function () {
				$o       = self::get_pwa_options();
				$checked = ! empty( $o['enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable Progressive Web App features', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_app_name',
			\__( 'App Name', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[app_name]" value="' . \esc_attr( $o['app_name'] ) . '" class="regular-text">';
				echo '<p class="description">' . \esc_html__( 'Full name displayed on install. Leave blank to use site title.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_short_name',
			\__( 'Short Name', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[short_name]" value="' . \esc_attr( $o['short_name'] ) . '" class="regular-text" maxlength="12">';
				echo '<p class="description">' . \esc_html__( 'Short name for the home screen (12 chars max).', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_description',
			\__( 'Description', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<textarea name="functionalities_pwa[description]" rows="3" class="large-text">' . \esc_textarea( $o['description'] ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Shown in app stores and install dialogs. Leave blank to use site tagline.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_start_url',
			\__( 'Start URL', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[start_url]" value="' . \esc_attr( $o['start_url'] ) . '" class="regular-text" placeholder="/">';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_scope',
			\__( 'Scope', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[scope]" value="' . \esc_attr( $o['scope'] ) . '" class="regular-text" placeholder="/">';
				echo '<p class="description">' . \esc_html__( 'Navigation scope. "/" means the entire site.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_categories',
			\__( 'Categories', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[categories]" value="' . \esc_attr( $o['categories'] ) . '" class="regular-text">';
				echo '<p class="description">' . \esc_html__( 'Comma-separated W3C categories (e.g. news, education, business).', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_display',
			\__( 'Display Mode', 'functionalities' ),
			function () {
				$o     = self::get_pwa_options();
				$modes = array( 'standalone', 'fullscreen', 'minimal-ui', 'browser' );
				echo '<select name="functionalities_pwa[display]">';
				foreach ( $modes as $mode ) {
					echo '<option value="' . \esc_attr( $mode ) . '"' . \selected( $o['display'], $mode, false ) . '>' . \esc_html( $mode ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		\add_settings_field(
			'pwa_orientation',
			\__( 'Orientation', 'functionalities' ),
			function () {
				$o            = self::get_pwa_options();
				$orientations = array( 'any', 'portrait', 'landscape', 'portrait-primary', 'landscape-primary' );
				echo '<select name="functionalities_pwa[orientation]">';
				foreach ( $orientations as $orient ) {
					echo '<option value="' . \esc_attr( $orient ) . '"' . \selected( $o['orientation'], $orient, false ) . '>' . \esc_html( $orient ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_identity'
		);

		// Appearance section.
		\add_settings_section(
			'functionalities_pwa_appearance',
			\__( 'Appearance & Icons', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Theme colors and app icons. Icons must be square PNG files.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_theme_color',
			\__( 'Theme Color', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[theme_color]" value="' . \esc_attr( $o['theme_color'] ) . '" class="func-color-field" data-default-color="#4f46e5">';
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_background_color',
			\__( 'Background Color', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[background_color]" value="' . \esc_attr( $o['background_color'] ) . '" class="func-color-field" data-default-color="#ffffff">';
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_icon_512',
			\__( 'Icon 512x512', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[icon_512]', $o['icon_512'], \__( 'Primary app icon (required for installability).', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_icon_192',
			\__( 'Icon 192x192', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[icon_192]', $o['icon_192'], \__( 'Smaller icon for home screen and splash.', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_maskable_icon_512',
			\__( 'Maskable Icon 512x512', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[maskable_icon_512]', $o['maskable_icon_512'], \__( 'Maskable icon for adaptive icon support (safe zone padding recommended).', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		\add_settings_field(
			'pwa_maskable_icon_192',
			\__( 'Maskable Icon 192x192', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				self::render_media_field( 'functionalities_pwa[maskable_icon_192]', $o['maskable_icon_192'], \__( 'Smaller maskable icon.', 'functionalities' ) );
			},
			'functionalities_pwa',
			'functionalities_pwa_appearance'
		);

		// Install Prompt section.
		\add_settings_section(
			'functionalities_pwa_prompt',
			\__( 'Install Prompt', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Customize the in-page install prompt shown to visitors.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_install_prompt',
			\__( 'Show Install Prompt', 'functionalities' ),
			function () {
				$o       = self::get_pwa_options();
				$checked = ! empty( $o['install_prompt'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[install_prompt]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Display a custom install prompt to visitors', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_title',
			\__( 'Prompt Title', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_title]" value="' . \esc_attr( $o['prompt_title'] ) . '" class="regular-text" placeholder="' . \esc_attr__( 'Install App', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_text',
			\__( 'Prompt Text', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_text]" value="' . \esc_attr( $o['prompt_text'] ) . '" class="large-text" placeholder="' . \esc_attr__( 'Add this site to your home screen for quick access.', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_button',
			\__( 'Button Text', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_button]" value="' . \esc_attr( $o['prompt_button'] ) . '" class="regular-text" placeholder="' . \esc_attr__( 'Install', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_dismiss',
			\__( 'Dismiss Text', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[prompt_dismiss]" value="' . \esc_attr( $o['prompt_dismiss'] ) . '" class="regular-text" placeholder="' . \esc_attr__( 'Not now', 'functionalities' ) . '">';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_position',
			\__( 'Position', 'functionalities' ),
			function () {
				$o         = self::get_pwa_options();
				$positions = array(
					'bottom' => \__( 'Bottom', 'functionalities' ),
					'top'    => \__( 'Top', 'functionalities' ),
					'center' => \__( 'Center (modal)', 'functionalities' ),
				);
				echo '<select name="functionalities_pwa[prompt_position]">';
				foreach ( $positions as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['prompt_position'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_style',
			\__( 'Style', 'functionalities' ),
			function () {
				$o      = self::get_pwa_options();
				$styles = array(
					'banner' => \__( 'Banner', 'functionalities' ),
					'card'   => \__( 'Card', 'functionalities' ),
				);
				echo '<select name="functionalities_pwa[prompt_style]">';
				foreach ( $styles as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['prompt_style'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		\add_settings_field(
			'pwa_prompt_frequency',
			\__( 'Re-show After (days)', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="number" name="functionalities_pwa[prompt_frequency]" value="' . \esc_attr( $o['prompt_frequency'] ) . '" min="1" max="365" class="small-text">';
				echo '<p class="description">' . \esc_html__( 'Days to wait before showing prompt again after dismissal.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_prompt'
		);

		// Offline & Caching section.
		\add_settings_section(
			'functionalities_pwa_caching',
			\__( 'Offline & Caching', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Service worker caching and offline behavior.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_cache_version',
			\__( 'Cache Version', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<input type="text" name="functionalities_pwa[cache_version]" value="' . \esc_attr( $o['cache_version'] ) . '" class="small-text" placeholder="v1">';
				echo '<p class="description">' . \esc_html__( 'Increment to force cache refresh (e.g. v1, v2).', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_caching'
		);

		\add_settings_field(
			'pwa_precache_urls',
			\__( 'Precache URLs', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<textarea name="functionalities_pwa[precache_urls]" rows="4" class="large-text" placeholder="/about/&#10;/contact/">' . \esc_textarea( $o['precache_urls'] ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'One URL per line. These pages will be cached immediately when the service worker installs.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_caching'
		);

		// Shortcuts section.
		\add_settings_section(
			'functionalities_pwa_shortcuts',
			\__( 'Shortcuts', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'App shortcut links shown on long-press of the app icon. Up to 4 shortcuts.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_shortcuts',
			\__( 'Shortcuts', 'functionalities' ),
			array( __CLASS__, 'field_pwa_shortcuts' ),
			'functionalities_pwa',
			'functionalities_pwa_shortcuts'
		);

		// Screenshots section.
		\add_settings_section(
			'functionalities_pwa_screenshots',
			\__( 'Screenshots', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'App screenshots shown in install dialogs and app stores. Provide wide and narrow sizes.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_screenshots',
			\__( 'Screenshots', 'functionalities' ),
			array( __CLASS__, 'field_pwa_screenshots' ),
			'functionalities_pwa',
			'functionalities_pwa_screenshots'
		);

		// Advanced section.
		\add_settings_section(
			'functionalities_pwa_advanced',
			\__( 'Advanced', 'functionalities' ),
			function () {
				echo '<p>' . \esc_html__( 'Advanced PWA features. Only change these if you know what you\'re doing.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa'
		);

		\add_settings_field(
			'pwa_display_override',
			\__( 'Display Override', 'functionalities' ),
			function () {
				$o       = self::get_pwa_options();
				$checked = ! empty( $o['display_override'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[display_override]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Enable display_override with window-controls-overlay fallback chain', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_edge_side_panel',
			\__( 'Edge Side Panel', 'functionalities' ),
			function () {
				$o       = self::get_pwa_options();
				$checked = ! empty( $o['edge_side_panel'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[edge_side_panel]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Allow opening in Microsoft Edge side panel', 'functionalities' ) . '</label>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_launch_handler',
			\__( 'Launch Handler', 'functionalities' ),
			function () {
				$o        = self::get_pwa_options();
				$handlers = array(
					''                  => \__( 'Default', 'functionalities' ),
					'focus-existing'    => 'focus-existing',
					'navigate-new'      => 'navigate-new',
					'navigate-existing' => 'navigate-existing',
				);
				echo '<select name="functionalities_pwa[launch_handler]">';
				foreach ( $handlers as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['launch_handler'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select>';
				echo '<p class="description">' . \esc_html__( 'Controls behavior when the app is already open and a new navigation is triggered.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_share_target',
			\__( 'Share Target', 'functionalities' ),
			function () {
				$o       = self::get_pwa_options();
				$checked = ! empty( $o['share_target_enabled'] ) ? 'checked' : '';
				echo '<label><input type="checkbox" name="functionalities_pwa[share_target_enabled]" value="1" ' . \esc_attr( $checked ) . '> ';
				echo \esc_html__( 'Register as a Web Share Target', 'functionalities' ) . '</label>';
				echo '<div style="margin-top:10px">';
				echo '<label>' . \esc_html__( 'Action URL:', 'functionalities' ) . ' ';
				echo '<input type="text" name="functionalities_pwa[share_target_action]" value="' . \esc_attr( $o['share_target_action'] ) . '" class="regular-text" placeholder="/?shared=1"></label>';
				echo '</div>';
				$methods = array(
					'GET'  => 'GET',
					'POST' => 'POST',
				);
				echo '<div style="margin-top:5px">';
				echo '<label>' . \esc_html__( 'Method:', 'functionalities' ) . ' ';
				echo '<select name="functionalities_pwa[share_target_method]">';
				foreach ( $methods as $val => $label ) {
					echo '<option value="' . \esc_attr( $val ) . '"' . \selected( $o['share_target_method'], $val, false ) . '>' . \esc_html( $label ) . '</option>';
				}
				echo '</select></label>';
				echo '</div>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);

		\add_settings_field(
			'pwa_advanced_manifest',
			\__( 'Custom Manifest JSON', 'functionalities' ),
			function () {
				$o = self::get_pwa_options();
				echo '<textarea name="functionalities_pwa[advanced_manifest]" rows="6" class="large-text code" placeholder=\'{"key": "value"}\'>' . \esc_textarea( $o['advanced_manifest'] ) . '</textarea>';
				echo '<p class="description">' . \esc_html__( 'Raw JSON to merge into the manifest. Must be a valid JSON object.', 'functionalities' ) . '</p>';
			},
			'functionalities_pwa',
			'functionalities_pwa_advanced'
		);
	}
}
