<?php
/**
 * Admin options getters trait.
 *
 * @package Functionalities\Admin
 * @since   0.16.0
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for options getter methods.
 */
trait Admin_Options {

	/**
	 * Get link management options with defaults.
	 *
	 * @return array Link management options.
	 */
	public static function get_link_management_options() : array {
		$defaults = array(
			'enabled'                     => false,
			'nofollow_external'           => false,
			'exceptions'                  => '',
			'open_external_new_tab'       => false,
			'open_internal_new_tab'       => false,
			'internal_new_tab_exceptions' => '',
			'json_preset_url'             => '',
			'enable_developer_filters'    => false,
		);
		$opts = (array) \get_option( 'functionalities_link_management', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get block cleanup options with defaults.
	 *
	 * @return array Block cleanup options.
	 */
	public static function get_block_cleanup_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_block_cleanup', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get default editor link post types.
	 *
	 * @return array Default post types.
	 */
	public static function default_editor_link_post_types() : array {
		$pts      = get_post_types( array( 'public' => true ), 'objects' );
		$defaults = array();
		foreach ( $pts as $name => $obj ) {
			$is_cpt = ! ( $obj->_builtin ?? false );
			if ( $is_cpt ) {
				$defaults[] = $name;
			}
		}
		return $defaults;
	}

	/**
	 * Get editor links options with defaults.
	 *
	 * @return array Editor links options.
	 */
	public static function get_editor_links_options() : array {
		$defaults = array(
			'enabled'      => false,
			'enable_limit' => false,
			'post_types'   => self::default_editor_link_post_types(),
		);
		$opts = (array) \get_option( 'functionalities_editor_links', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get snippets options with defaults.
	 *
	 * @return array Snippets options.
	 */
	public static function get_snippets_options() : array {
		$defaults = array(
			'enabled'          => false,
			'enable_header'    => false,
			'header_code'      => '',
			'enable_body_open' => false,
			'body_open_code'   => '',
			'enable_footer'    => false,
			'footer_code'      => '',
			'enable_ga4'       => false,
			'ga4_id'           => '',
		);
		$opts = (array) \get_option( 'functionalities_snippets', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get schema options with defaults.
	 *
	 * @return array Schema options.
	 */
	public static function get_schema_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_schema', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get default components.
	 *
	 * @return array Default components.
	 */
	public static function default_components() : array {
		return array(
			array(
				'name'  => 'Card',
				'class' => '.c-card',
				'css'   => 'background:#fff;border:1px solid #e5e7eb;border-radius:12px;box-shadow:0 1px 2px rgba(0,0,0,.06);padding:1rem;',
			),
			array(
				'name'  => 'Button',
				'class' => '.c-btn',
				'css'   => 'display:inline-block;padding:.625rem 1rem;border-radius:.5rem;background:#0a7cff;color:#fff;text-decoration:none;font-weight:600;transition:background .2s;cursor:pointer;',
			),
			array(
				'name'  => 'Button (ghost)',
				'class' => '.c-btn--ghost',
				'css'   => 'background:transparent;border:1px solid currentColor;color:#0a7cff;',
			),
			array(
				'name'  => 'Accordion',
				'class' => '.c-accordion',
				'css'   => 'border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;',
			),
			array(
				'name'  => 'Accordion Item',
				'class' => '.c-accordion__item',
				'css'   => 'border-top:1px solid #e5e7eb;padding:.75rem 1rem;',
			),
			array(
				'name'  => 'Badge',
				'class' => '.c-badge',
				'css'   => 'display:inline-block;padding:.25rem .5rem;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:.75rem;font-weight:600;',
			),
			array(
				'name'  => 'Chip',
				'class' => '.c-chip',
				'css'   => 'display:inline-flex;align-items:center;gap:.5rem;padding:.25rem .5rem;border-radius:999px;background:#f3f4f6;border:1px solid #e5e7eb;',
			),
			array(
				'name'  => 'Alert',
				'class' => '.c-alert',
				'css'   => 'padding:.75rem 1rem;border-radius:.5rem;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;',
			),
			array(
				'name'  => 'Avatar',
				'class' => '.c-avatar',
				'css'   => 'display:inline-block;width:3rem;height:3rem;border-radius:999px;object-fit:cover;',
			),
			array(
				'name'  => 'Grid',
				'class' => '.c-grid',
				'css'   => 'display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;',
			),
			array(
				'name'  => 'Marquee',
				'class' => '.c-marquee',
				'css'   => 'display:block;white-space:nowrap;overflow:hidden;animation:marquee 20s linear infinite;',
			),
			array(
				'name'  => 'Tabs',
				'class' => '.c-tabs',
				'css'   => 'display:flex;gap:.5rem;border-bottom:1px solid #e5e7eb;',
			),
			array(
				'name'  => 'Card Media',
				'class' => '.c-card__media',
				'css'   => 'display:block;width:100%;height:auto;border-top-left-radius:12px;border-top-right-radius:12px;',
			),
		);
	}

	/**
	 * Get components options with defaults.
	 *
	 * @return array Components options.
	 */
	public static function get_components_options() : array {
		$defaults = array(
			'enabled' => false,
			'items'   => self::default_components(),
		);
		$opts = (array) \get_option( 'functionalities_components', $defaults );
		if ( empty( $opts['items'] ) ) {
			$opts['items'] = self::default_components();
		}
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get misc options with defaults.
	 *
	 * @return array Misc options.
	 */
	public static function get_misc_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_misc', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get fonts options with defaults.
	 *
	 * @return array Fonts options.
	 */
	public static function get_fonts_options() : array {
		$defaults = array(
			'enabled' => false,
			'items'   => array(),
		);
		$opts = (array) \get_option( 'functionalities_fonts', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get Login Security options with defaults.
	 *
	 * @return array Options.
	 */
	public static function get_login_security_options() : array {
		return \Functionalities\Features\Login_Security::get_options();
	}

	/**
	 * Get Meta options with defaults.
	 *
	 * @return array Meta options.
	 */
	public static function get_meta_options() : array {
		$defaults = array(
			'enabled'                 => false,
			'enable_copyright_meta'   => true,
			'enable_dublin_core'      => true,
			'enable_license_metabox'  => true,
			'enable_schema_integration' => true,
			'default_license'         => 'all-rights-reserved',
			'default_license_url'     => '',
			'post_types'              => array( 'post' ),
			'copyright_holder_type'   => 'author',
			'custom_copyright_holder' => '',
			'dc_language'             => '',
		);
		$opts = (array) \get_option( 'functionalities_meta', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get Content Regression options with defaults.
	 *
	 * @return array Content regression options.
	 */
	public static function get_content_regression_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_content_regression', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get Assumption Detection options with defaults.
	 *
	 * @return array Assumption detection options.
	 */
	public static function get_assumption_detection_options() : array {
		$defaults = array(
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
		);
		$opts = (array) \get_option( 'functionalities_assumption_detection', $defaults );
		return array_merge( $defaults, $opts );
	}

	/**
	 * Get SVG Icons options with defaults.
	 *
	 * @return array Options.
	 */
	public static function get_svg_icons_options() : array {
		return \Functionalities\Features\SVG_Icons::get_options();
	}

	/**
	 * Get PWA options with defaults.
	 *
	 * @return array PWA options.
	 */
	public static function get_pwa_options() : array {
		return \Functionalities\Features\PWA::get_options();
	}
}
