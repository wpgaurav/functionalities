<?php
/**
 * Miscellaneous bloat control features.
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Misc {
	public static function init() : void {
		$opts = self::get_options();

		if ( ! empty( $opts['disable_block_widgets'] ) ) {
			\add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
			\add_filter( 'use_widgets_block_editor', '__return_false' );
		}

		if ( ! empty( $opts['load_separate_core_block_assets'] ) ) {
			// WP 6.5+: load core block styles separately
			\add_filter( 'wp_should_load_separate_core_block_assets', '__return_true' );
		}

		if ( ! empty( $opts['disable_emojis'] ) ) {
			self::disable_emojis();
		}

		if ( ! empty( $opts['disable_embeds'] ) ) {
			self::disable_embeds();
		}

		if ( ! empty( $opts['remove_rest_api_links_head'] ) ) {
			\remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			\remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
		}

		if ( ! empty( $opts['remove_rsd_wlw_shortlink'] ) ) {
			\remove_action( 'wp_head', 'rsd_link' );
			\remove_action( 'wp_head', 'wlwmanifest_link' );
			\remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}

		if ( ! empty( $opts['remove_generator_meta'] ) ) {
			\remove_action( 'wp_head', 'wp_generator' );
			\add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( ! empty( $opts['disable_xmlrpc'] ) ) {
			\add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		if ( ! empty( $opts['disable_feeds'] ) ) {
			self::disable_feeds();
		}

		if ( ! empty( $opts['disable_dashicons_for_guests'] ) ) {
			\add_action( 'wp_enqueue_scripts', function() {
				if ( ! \is_user_logged_in() ) {
					\wp_deregister_style( 'dashicons' );
				}
			}, 9 );
		}

		if ( ! empty( $opts['disable_heartbeat'] ) ) {
			\add_action( 'init', function() {
				\wp_deregister_script( 'heartbeat' );
			}, 1 );
		}

		if ( ! empty( $opts['disable_admin_bar_front'] ) ) {
			\add_filter( 'show_admin_bar', function( $show ) {
				return \is_admin() ? $show : false;
			} );
		}

		if ( ! empty( $opts['remove_jquery_migrate'] ) ) {
			\add_action( 'wp_default_scripts', function( $scripts ) {
				if ( isset( $scripts->registered['jquery'] ) ) {
					$jq = $scripts->registered['jquery'];
					if ( isset( $jq->deps ) && is_array( $jq->deps ) ) {
						$jq->deps = array_diff( $jq->deps, [ 'jquery-migrate' ] );
					}
				}
			} );
		}

		// Admin enhancements
		if ( ! empty( $opts['enable_prism_admin'] ) ) {
			\add_action( 'admin_enqueue_scripts', function() {
				\wp_enqueue_style( 'functionalities-prism', 'https://unpkg.com/prismjs@1.29.0/themes/prism.min.css', [], FUNCTIONALITIES_VERSION );
				\wp_enqueue_script( 'functionalities-prism', 'https://unpkg.com/prismjs@1.29.0/prism.min.js', [], FUNCTIONALITIES_VERSION, true );
				\add_action( 'admin_print_footer_scripts', function(){ echo '<script>window.Prism&&Prism.highlightAll();</script>'; } );
			} );
		}

		if ( ! empty( $opts['enable_textarea_fullscreen'] ) ) {
			\add_action( 'admin_enqueue_scripts', function() {
				\wp_enqueue_style( 'functionalities-admin-ui', FUNCTIONALITIES_URL . 'assets/css/admin-ui.css', [], FUNCTIONALITIES_VERSION );
				\wp_enqueue_script( 'functionalities-admin-ui', FUNCTIONALITIES_URL . 'assets/js/admin-ui.js', [], FUNCTIONALITIES_VERSION, true );
			} );
		}
	}

	protected static function get_options() : array {
		$defaults = [
			'disable_block_widgets' => false,
			'load_separate_core_block_assets' => false,
			'disable_emojis' => false,
			'disable_embeds' => false,
			'remove_rest_api_links_head' => false,
			'remove_rsd_wlw_shortlink' => false,
			'remove_generator_meta' => false,
			'disable_xmlrpc' => false,
			'disable_feeds' => false,
			'disable_dashicons_for_guests' => false,
			'disable_heartbeat' => false,
			'disable_admin_bar_front' => false,
			'remove_jquery_migrate' => false,
			'enable_prism_admin' => false,
			'enable_textarea_fullscreen' => false,
		];
		$opts = (array) \get_option( 'functionalities_misc', $defaults );
		return array_merge( $defaults, $opts );
	}

	protected static function disable_emojis() : void {
		\remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		\remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		\remove_action( 'wp_print_styles', 'print_emoji_styles' );
		\remove_action( 'admin_print_styles', 'print_emoji_styles' );
		\remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		\remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		\remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		\add_filter( 'emoji_svg_url', '__return_false' );
		\add_filter( 'tiny_mce_plugins', function( $plugins ) {
			if ( is_array( $plugins ) ) {
				return array_diff( $plugins, [ 'wpemoji' ] );
			}
			return [];
		} );
	}

	protected static function disable_embeds() : void {
		\remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		\remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		\add_filter( 'embed_oembed_discover', '__return_false' );
		\add_filter( 'tiny_mce_plugins', function( $plugins ) {
			return is_array( $plugins ) ? array_diff( $plugins, [ 'wpembed' ] ) : [];
		} );
		\add_filter( 'rest_endpoints', function( $endpoints ) {
			unset( $endpoints['/oembed/1.0'] );
			unset( $endpoints['/oembed/1.0/embed'] );
			return $endpoints;
		} );
	}

	protected static function disable_feeds() : void {
		$callback = function() {
			// Preferred: link back to homepage
			if ( function_exists( 'wp_safe_redirect' ) ) {
				\wp_safe_redirect( \home_url( '/' ), 301 );
				exit;
			}
			\wp_die( \esc_html__( 'Feeds are disabled on this site.', 'functionalities' ) );
		};
		foreach ( [ 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom' ] as $hook ) {
			\remove_action( 'wp_head', 'feed_links_extra', 3 );
			\remove_action( 'wp_head', 'feed_links', 2 );
			\add_action( $hook, $callback, 1 );
		}
	}
}
