<?php
/**
 * Admin sanitizers trait.
 *
 * @package Functionalities\Admin
 * @since   0.16.0
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Trait for sanitizer methods.
 */
trait Admin_Sanitizers {

	/**
	 * Sanitize link management settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_link_management( $input ) : array {
		$out = array(
			'enabled'                     => ! empty( $input['enabled'] ),
			'nofollow_external'           => ! empty( $input['nofollow_external'] ),
			'exceptions'                  => '',
			'open_external_new_tab'       => ! empty( $input['open_external_new_tab'] ),
			'open_internal_new_tab'       => ! empty( $input['open_internal_new_tab'] ),
			'internal_new_tab_exceptions' => '',
			'json_preset_url'             => '',
			'enable_developer_filters'    => ! empty( $input['enable_developer_filters'] ),
		);

		if ( isset( $input['exceptions'] ) ) {
			$raw   = (string) $input['exceptions'];
			$lines = preg_split( '/\r\n|\r|\n|,/', $raw );
			$clean = array();
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( $line === '' ) {
					continue;
				}
				$clean[] = \sanitize_text_field( $line );
			}
			$out['exceptions'] = implode( "\n", $clean );
		}

		if ( isset( $input['internal_new_tab_exceptions'] ) ) {
			$raw   = (string) $input['internal_new_tab_exceptions'];
			$lines = preg_split( '/\r\n|\r|\n|,/', $raw );
			$clean = array();
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( $line === '' ) {
					continue;
				}
				$clean[] = \sanitize_text_field( $line );
			}
			$out['internal_new_tab_exceptions'] = implode( "\n", $clean );
		}

		// Sanitize JSON preset URL.
		if ( isset( $input['json_preset_url'] ) ) {
			$out['json_preset_url'] = \sanitize_text_field( (string) $input['json_preset_url'] );
		}

		return $out;
	}

	/**
	 * Sanitize block cleanup settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_block_cleanup( $input ) : array {
		return array(
			'enabled'                       => ! empty( $input['enabled'] ),
			'remove_heading_block_class'    => ! empty( $input['remove_heading_block_class'] ),
			'remove_list_block_class'       => ! empty( $input['remove_list_block_class'] ),
			'remove_image_block_class'      => ! empty( $input['remove_image_block_class'] ),
			'remove_paragraph_block_class'  => ! empty( $input['remove_paragraph_block_class'] ),
			'remove_quote_block_class'      => ! empty( $input['remove_quote_block_class'] ),
			'remove_table_block_class'      => ! empty( $input['remove_table_block_class'] ),
			'remove_separator_block_class'  => ! empty( $input['remove_separator_block_class'] ),
			'remove_group_block_class'      => ! empty( $input['remove_group_block_class'] ),
			'remove_columns_block_class'    => ! empty( $input['remove_columns_block_class'] ),
			'remove_button_block_class'     => ! empty( $input['remove_button_block_class'] ),
			'remove_cover_block_class'      => ! empty( $input['remove_cover_block_class'] ),
			'remove_media_text_block_class' => ! empty( $input['remove_media_text_block_class'] ),
			'custom_classes_to_remove'      => isset( $input['custom_classes_to_remove'] )
				? \sanitize_textarea_field( $input['custom_classes_to_remove'] )
				: '',
		);
	}

	/**
	 * Sanitize editor links settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_editor_links( $input ) : array {
		$out = array(
			'enabled'      => ! empty( $input['enabled'] ),
			'enable_limit' => ! empty( $input['enable_limit'] ),
			'post_types'   => array(),
		);
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt ) {
				$pt = sanitize_key( (string) $pt );
				if ( post_type_exists( $pt ) ) {
					$out['post_types'][] = $pt;
				}
			}
			$out['post_types'] = array_values( array_unique( $out['post_types'] ) );
		}
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = self::default_editor_link_post_types();
		}
		return $out;
	}

	/**
	 * Sanitize snippets settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_snippets( $input ) : array {
		$out = array(
			'enabled'          => ! empty( $input['enabled'] ),
			'enable_header'    => ! empty( $input['enable_header'] ),
			'header_code'      => '',
			'enable_body_open' => ! empty( $input['enable_body_open'] ),
			'body_open_code'   => '',
			'enable_footer'    => ! empty( $input['enable_footer'] ),
			'footer_code'      => '',
			'enable_ga4'       => ! empty( $input['enable_ga4'] ),
			'ga4_id'           => '',
		);

		$ga4 = isset( $input['ga4_id'] ) ? (string) $input['ga4_id'] : '';
		$ga4 = strtoupper( trim( $ga4 ) );
		if ( preg_match( '/^G-[A-Z0-9]{4,}$/', $ga4 ) ) {
			$out['ga4_id'] = $ga4;
		}

		$allowed_tags = array(
			'script'   => array( 'src' => true, 'type' => true, 'async' => true, 'defer' => true, 'crossorigin' => true, 'integrity' => true, 'data-*' => true ),
			'style'    => array( 'type' => true, 'media' => true ),
			'link'     => array( 'rel' => true, 'href' => true, 'as' => true, 'crossorigin' => true, 'media' => true, 'type' => true ),
			'meta'     => array( 'name' => true, 'content' => true, 'property' => true, 'http-equiv' => true ),
			'noscript' => array(),
			'div'      => array( 'id' => true, 'class' => true, 'style' => true ),
			'span'     => array( 'id' => true, 'class' => true, 'style' => true ),
		);

		$raw_header    = isset( $input['header_code'] ) ? (string) $input['header_code'] : '';
		$raw_body_open = isset( $input['body_open_code'] ) ? (string) $input['body_open_code'] : '';
		$raw_footer    = isset( $input['footer_code'] ) ? (string) $input['footer_code'] : '';

		if ( \current_user_can( 'unfiltered_html' ) ) {
			$out['header_code']    = $raw_header;
			$out['body_open_code'] = $raw_body_open;
			$out['footer_code']    = $raw_footer;
		} else {
			$out['header_code']    = \wp_kses( $raw_header, $allowed_tags );
			$out['body_open_code'] = \wp_kses( $raw_body_open, $allowed_tags );
			$out['footer_code']    = \wp_kses( $raw_footer, $allowed_tags );
		}
		return $out;
	}

	/**
	 * Sanitize schema settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_schema( $input ) : array {
		return array(
			'enabled'            => ! empty( $input['enabled'] ),
			'enable_site_schema' => ! empty( $input['enable_site_schema'] ),
			'site_itemtype'      => preg_replace( '/[^A-Za-z]/', '', (string) ( $input['site_itemtype'] ?? 'WebPage' ) ),
			'enable_header_part' => ! empty( $input['enable_header_part'] ),
			'enable_footer_part' => ! empty( $input['enable_footer_part'] ),
			'enable_article'     => ! empty( $input['enable_article'] ),
			'article_itemtype'   => preg_replace( '/[^A-Za-z]/', '', (string) ( $input['article_itemtype'] ?? 'Article' ) ),
			'add_headline'       => ! empty( $input['add_headline'] ),
			'add_dates'          => ! empty( $input['add_dates'] ),
			'add_author'         => ! empty( $input['add_author'] ),
			'enable_breadcrumbs' => ! empty( $input['enable_breadcrumbs'] ),
		);
	}

	/**
	 * Sanitize components settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_components( $input ) : array {
		$out = array(
			'enabled' => ! empty( $input['enabled'] ),
			'items'   => array(),
		);
		if ( isset( $input['items'] ) && is_array( $input['items'] ) ) {
			foreach ( $input['items'] as $item ) {
				$class = isset( $item['class'] ) ? trim( (string) $item['class'] ) : '';
				$css   = isset( $item['css'] ) ? trim( (string) $item['css'] ) : '';
				$name  = isset( $item['name'] ) ? trim( (string) $item['name'] ) : '';
				if ( $class === '' || $css === '' ) {
					continue;
				}
				$out['items'][] = array(
					'name'  => \sanitize_text_field( $name ),
					'class' => preg_replace( '/[^A-Za-z0-9_\-\.\s]/', '', $class ),
					'css'   => $css,
				);
			}
		}
		return $out;
	}

	/**
	 * Sanitize misc settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_misc( $input ) : array {
		$keys = array(
			'enabled',
			'disable_block_widgets',
			'load_separate_core_block_assets',
			'disable_emojis',
			'disable_embeds',
			'remove_rest_api_links_head',
			'remove_rsd_wlw_shortlink',
			'remove_generator_meta',
			'disable_xmlrpc',
			'disable_xmlrpc_pingbacks',
			'disable_feeds',
			'disable_gravatars',
			'disable_self_pingbacks',
			'remove_query_strings',
			'remove_dns_prefetch',
			'remove_recent_comments_css',
			'limit_revisions',
			'disable_dashicons_for_guests',
			'disable_heartbeat',
			'disable_admin_bar_front',
			'remove_jquery_migrate',
			'enable_prism_admin',
			'enable_textarea_fullscreen',
		);
		$out = array();
		foreach ( $keys as $k ) {
			$out[ $k ] = ! empty( $input[ $k ] );
		}
		return $out;
	}

	/**
	 * Sanitize CSS font-style value.
	 *
	 * Supports: normal, italic, oblique, oblique with angles (e.g., oblique -12deg 0deg).
	 *
	 * @since 0.14.0
	 *
	 * @param string $style The font-style value to sanitize.
	 * @return string Sanitized font-style value, defaults to 'normal' if invalid.
	 */
	protected static function sanitize_font_style( string $style ) : string {
		$style = trim( strtolower( $style ) );

		// Simple keyword values.
		if ( in_array( $style, array( 'normal', 'italic', 'oblique' ), true ) ) {
			return $style;
		}

		// Oblique with angle range (e.g., "oblique -12deg 0deg" or "oblique 14deg").
		if ( preg_match( '/^oblique\s+(-?\d+(?:\.\d+)?(?:deg|grad|rad|turn)?)(?:\s+(-?\d+(?:\.\d+)?(?:deg|grad|rad|turn)?))?$/', $style, $matches ) ) {
			$result = 'oblique ' . $matches[1];
			if ( ! empty( $matches[2] ) ) {
				$result .= ' ' . $matches[2];
			}
			return $result;
		}

		return 'normal';
	}

	/**
	 * Sanitize fonts settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_fonts( $input ) : array {
		$out = array(
			'enabled' => ! empty( $input['enabled'] ),
			'items'   => array(),
		);
		if ( isset( $input['items'] ) && is_array( $input['items'] ) ) {
			foreach ( $input['items'] as $it ) {
				$family       = isset( $it['family'] ) ? trim( (string) $it['family'] ) : '';
				$style        = isset( $it['style'] ) ? trim( (string) $it['style'] ) : 'normal';
				$display      = isset( $it['display'] ) ? trim( (string) $it['display'] ) : 'swap';
				$weight       = isset( $it['weight'] ) ? trim( (string) $it['weight'] ) : '';
				$weight_range = isset( $it['weight_range'] ) ? trim( (string) $it['weight_range'] ) : '';
				$is_variable  = ! empty( $it['is_variable'] );
				$preload      = ! empty( $it['preload'] );
				$woff2        = isset( $it['woff2_url'] ) ? trim( (string) $it['woff2_url'] ) : '';
				$woff         = isset( $it['woff_url'] ) ? trim( (string) $it['woff_url'] ) : '';
				if ( $family === '' || $woff2 === '' ) {
					continue;
				}
				$out['items'][] = array(
					'family'       => \sanitize_text_field( $family ),
					'style'        => self::sanitize_font_style( $style ),
					'display'      => in_array( $display, array( 'auto', 'block', 'swap', 'fallback', 'optional' ), true ) ? $display : 'swap',
					'weight'       => preg_replace( '/[^0-9]/', '', $weight ),
					'weight_range' => preg_replace( '/[^0-9\s]/', '', $weight_range ),
					'is_variable'  => (bool) $is_variable,
					'preload'      => (bool) $preload,
					'woff2_url'    => \esc_url_raw( $woff2 ),
					'woff_url'     => \esc_url_raw( $woff ),
				);
			}
		}
		return $out;
	}

	/**
	 * Sanitize Login Security settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized output.
	 */
	public static function sanitize_login_security( $input ) : array {
		return array(
			'enabled'                       => ! empty( $input['enabled'] ),
			'limit_login_attempts'          => ! empty( $input['limit_login_attempts'] ),
			'max_attempts'                  => max( 1, min( 20, (int) ( $input['max_attempts'] ?? 5 ) ) ),
			'lockout_duration'              => max( 1, min( 1440, (int) ( $input['lockout_duration'] ?? 15 ) ) ),
			'disable_xmlrpc_auth'           => ! empty( $input['disable_xmlrpc_auth'] ),
			'disable_application_passwords' => ! empty( $input['disable_application_passwords'] ),
			'hide_login_errors'             => ! empty( $input['hide_login_errors'] ),
			'custom_logo_url'               => \esc_url_raw( $input['custom_logo_url'] ?? '' ),
			'custom_background_color'       => \sanitize_hex_color( $input['custom_background_color'] ?? '' ) ?: '',
			'custom_form_background'        => \sanitize_hex_color( $input['custom_form_background'] ?? '' ) ?: '',
		);
	}

	/**
	 * Sanitize Meta settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized output.
	 */
	public static function sanitize_meta( $input ) : array {
		$out = array(
			'enabled'                 => ! empty( $input['enabled'] ),
			'enable_copyright_meta'   => ! empty( $input['enable_copyright_meta'] ),
			'enable_dublin_core'      => ! empty( $input['enable_dublin_core'] ),
			'enable_license_metabox'  => ! empty( $input['enable_license_metabox'] ),
			'enable_schema_integration' => ! empty( $input['enable_schema_integration'] ),
			'default_license'         => \sanitize_key( $input['default_license'] ?? 'all-rights-reserved' ),
			'default_license_url'     => \esc_url_raw( $input['default_license_url'] ?? '' ),
			'post_types'              => array(),
			'copyright_holder_type'   => \sanitize_key( $input['copyright_holder_type'] ?? 'author' ),
			'custom_copyright_holder' => \sanitize_text_field( $input['custom_copyright_holder'] ?? '' ),
			'dc_language'             => \sanitize_text_field( $input['dc_language'] ?? '' ),
		);

		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt ) {
				$pt = \sanitize_key( (string) $pt );
				if ( \post_type_exists( $pt ) ) {
					$out['post_types'][] = $pt;
				}
			}
		}
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = array( 'post' );
		}

		return $out;
	}

	/**
	 * Sanitize Content Regression settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized output.
	 */
	public static function sanitize_content_regression( $input ) : array {
		$out = array(
			'enabled'                    => ! empty( $input['enabled'] ),
			'post_types'                 => array(),
			'link_drop_enabled'          => ! empty( $input['link_drop_enabled'] ),
			'link_drop_percent'          => max( 1, min( 100, (int) ( $input['link_drop_percent'] ?? 30 ) ) ),
			'link_drop_absolute'         => max( 1, min( 100, (int) ( $input['link_drop_absolute'] ?? 3 ) ) ),
			'exclude_nofollow_links'     => ! empty( $input['exclude_nofollow_links'] ),
			'word_count_enabled'         => ! empty( $input['word_count_enabled'] ),
			'word_count_drop_percent'    => max( 1, min( 100, (int) ( $input['word_count_drop_percent'] ?? 35 ) ) ),
			'word_count_min_age_days'    => max( 0, min( 365, (int) ( $input['word_count_min_age_days'] ?? 30 ) ) ),
			'word_count_compare_average' => ! empty( $input['word_count_compare_average'] ),
			'exclude_shortcodes'         => ! empty( $input['exclude_shortcodes'] ),
			'heading_enabled'            => ! empty( $input['heading_enabled'] ),
			'detect_missing_h1'          => ! empty( $input['detect_missing_h1'] ),
			'detect_multiple_h1'         => ! empty( $input['detect_multiple_h1'] ),
			'detect_skipped_levels'      => ! empty( $input['detect_skipped_levels'] ),
			'snapshot_rolling_count'     => max( 1, min( 20, (int) ( $input['snapshot_rolling_count'] ?? 5 ) ) ),
			'show_post_column'           => ! empty( $input['show_post_column'] ),
		);

		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt ) {
				$pt = \sanitize_key( (string) $pt );
				if ( \post_type_exists( $pt ) ) {
					$out['post_types'][] = $pt;
				}
			}
		}
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = array( 'post', 'page' );
		}

		return $out;
	}

	/**
	 * Sanitize Assumption Detection settings.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized output.
	 */
	public static function sanitize_assumption_detection( $input ) : array {
		return array(
			'enabled'                         => ! empty( $input['enabled'] ),
			'detect_schema_collision'         => ! empty( $input['detect_schema_collision'] ),
			'detect_analytics_dupe'           => ! empty( $input['detect_analytics_dupe'] ),
			'detect_font_redundancy'          => ! empty( $input['detect_font_redundancy'] ),
			'detect_inline_css_growth'        => ! empty( $input['detect_inline_css_growth'] ),
			'inline_css_threshold_kb'         => max( 10, min( 500, (int) ( $input['inline_css_threshold_kb'] ?? 50 ) ) ),
			'detect_jquery_conflicts'         => ! empty( $input['detect_jquery_conflicts'] ),
			'detect_meta_duplication'         => ! empty( $input['detect_meta_duplication'] ),
			'detect_rest_exposure'            => ! empty( $input['detect_rest_exposure'] ),
			'detect_lazy_load_conflict'       => ! empty( $input['detect_lazy_load_conflict'] ),
			'detect_mixed_content'            => ! empty( $input['detect_mixed_content'] ),
			'detect_missing_security_headers' => ! empty( $input['detect_missing_security_headers'] ),
			'detect_debug_exposure'           => ! empty( $input['detect_debug_exposure'] ),
			'detect_cron_issues'              => ! empty( $input['detect_cron_issues'] ),
		);
	}

	/**
	 * Sanitize PWA settings.
	 *
	 * @param array $input Raw input data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_pwa( $input ) : array {
		$allowed_displays     = array( 'standalone', 'fullscreen', 'minimal-ui', 'browser' );
		$allowed_orientations = array( 'any', 'portrait', 'landscape', 'portrait-primary', 'landscape-primary' );
		$allowed_positions    = array( 'bottom', 'top', 'center' );
		$allowed_styles       = array( 'banner', 'card' );
		$allowed_methods      = array( 'GET', 'POST' );
		$allowed_handlers     = array( '', 'focus-existing', 'navigate-new', 'navigate-existing' );

		$display     = \sanitize_text_field( $input['display'] ?? 'standalone' );
		$orientation = \sanitize_text_field( $input['orientation'] ?? 'any' );
		$position    = \sanitize_text_field( $input['prompt_position'] ?? 'bottom' );
		$style       = \sanitize_text_field( $input['prompt_style'] ?? 'banner' );
		$method      = \sanitize_text_field( $input['share_target_method'] ?? 'GET' );
		$handler     = \sanitize_text_field( $input['launch_handler'] ?? '' );

		$out = array(
			'enabled'              => ! empty( $input['enabled'] ),
			'app_name'             => \sanitize_text_field( $input['app_name'] ?? '' ),
			'short_name'           => \sanitize_text_field( mb_substr( $input['short_name'] ?? '', 0, 12 ) ),
			'description'          => \sanitize_textarea_field( $input['description'] ?? '' ),
			'start_url'            => \sanitize_text_field( $input['start_url'] ?? '/' ),
			'scope'                => \sanitize_text_field( $input['scope'] ?? '/' ),
			'display'              => in_array( $display, $allowed_displays, true ) ? $display : 'standalone',
			'orientation'          => in_array( $orientation, $allowed_orientations, true ) ? $orientation : 'any',
			'categories'           => \sanitize_text_field( $input['categories'] ?? '' ),
			'theme_color'          => \sanitize_hex_color( $input['theme_color'] ?? '#4f46e5' ) ?: '#4f46e5',
			'background_color'     => \sanitize_hex_color( $input['background_color'] ?? '#ffffff' ) ?: '#ffffff',
			'icon_512'             => \esc_url_raw( $input['icon_512'] ?? '' ),
			'icon_192'             => \esc_url_raw( $input['icon_192'] ?? '' ),
			'maskable_icon_512'    => \esc_url_raw( $input['maskable_icon_512'] ?? '' ),
			'maskable_icon_192'    => \esc_url_raw( $input['maskable_icon_192'] ?? '' ),
			'install_prompt'       => ! empty( $input['install_prompt'] ),
			'prompt_title'         => \sanitize_text_field( $input['prompt_title'] ?? '' ),
			'prompt_text'          => \sanitize_text_field( $input['prompt_text'] ?? '' ),
			'prompt_button'        => \sanitize_text_field( $input['prompt_button'] ?? '' ),
			'prompt_dismiss'       => \sanitize_text_field( $input['prompt_dismiss'] ?? '' ),
			'prompt_position'      => in_array( $position, $allowed_positions, true ) ? $position : 'bottom',
			'prompt_style'         => in_array( $style, $allowed_styles, true ) ? $style : 'banner',
			'prompt_frequency'     => max( 1, min( 365, (int) ( $input['prompt_frequency'] ?? 14 ) ) ),
			'cache_version'        => \sanitize_text_field( $input['cache_version'] ?? 'v1' ),
			'precache_urls'        => '',
			'display_override'     => ! empty( $input['display_override'] ),
			'edge_side_panel'      => ! empty( $input['edge_side_panel'] ),
			'launch_handler'       => in_array( $handler, $allowed_handlers, true ) ? $handler : '',
			'share_target_enabled' => ! empty( $input['share_target_enabled'] ),
			'share_target_action'  => \sanitize_text_field( $input['share_target_action'] ?? '' ),
			'share_target_method'  => in_array( $method, $allowed_methods, true ) ? $method : 'GET',
			'advanced_manifest'    => '',
			'shortcuts'            => array(),
			'screenshots'          => array(),
			'rewrite_version'      => \Functionalities\Features\PWA::REWRITE_VERSION,
		);

		if ( isset( $input['precache_urls'] ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $input['precache_urls'] );
			$clean = array();
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( '' !== $line ) {
					$clean[] = \sanitize_text_field( $line );
				}
			}
			$out['precache_urls'] = implode( "\n", $clean );
		}

		if ( isset( $input['advanced_manifest'] ) ) {
			$raw = trim( (string) $input['advanced_manifest'] );
			if ( '' !== $raw ) {
				$decoded = json_decode( $raw, true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$out['advanced_manifest'] = \wp_json_encode( $decoded );
				}
			}
		}

		if ( ! empty( $input['shortcuts'] ) && is_array( $input['shortcuts'] ) ) {
			$shortcuts = array();
			foreach ( array_slice( $input['shortcuts'], 0, 4 ) as $sc ) {
				$name = \sanitize_text_field( $sc['name'] ?? '' );
				$url  = \sanitize_text_field( $sc['url'] ?? '' );
				if ( '' === $name || '' === $url ) {
					continue;
				}
				$shortcuts[] = array(
					'name'        => $name,
					'url'         => $url,
					'description' => \sanitize_text_field( $sc['description'] ?? '' ),
					'icon'        => \esc_url_raw( $sc['icon'] ?? '' ),
				);
			}
			$out['shortcuts'] = $shortcuts;
		}

		if ( ! empty( $input['screenshots'] ) && is_array( $input['screenshots'] ) ) {
			$screenshots = array();
			foreach ( $input['screenshots'] as $ss ) {
				$src = \esc_url_raw( $ss['src'] ?? '' );
				if ( '' === $src ) {
					continue;
				}
				$form_factor = \sanitize_text_field( $ss['form_factor'] ?? 'wide' );
				$screenshots[] = array(
					'src'         => $src,
					'sizes'       => \sanitize_text_field( $ss['sizes'] ?? '' ),
					'label'       => \sanitize_text_field( $ss['label'] ?? '' ),
					'form_factor' => in_array( $form_factor, array( 'wide', 'narrow' ), true ) ? $form_factor : 'wide',
				);
			}
			$out['screenshots'] = $screenshots;
		}

		return $out;
	}
}
