<?php
/**
 * Miscellaneous Module.
 *
 * Provides various WordPress bloat removal and performance optimization
 * features through a simple toggle-based interface.
 *
 * @package    Functionalities
 * @subpackage Features
 * @since      0.2.0
 * @version    0.8.0
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Misc class for WordPress bloat control and performance tweaks.
 *
 * Offers granular control over various WordPress features that may not
 * be needed for all sites, allowing administrators to selectively
 * disable them for improved performance and reduced attack surface.
 *
 * ## Features
 *
 * - Disable block widgets (use classic widgets)
 * - Load separate core block assets (WP 6.5+)
 * - Disable WordPress emojis
 * - Disable oEmbed functionality
 * - Remove REST API links from head
 * - Remove RSD, WLW, and shortlink from head
 * - Remove generator meta tag
 * - Disable XML-RPC
 * - Disable RSS feeds
 * - Disable dashicons for guests
 * - Disable Heartbeat API
 * - Hide admin bar on frontend
 * - Remove jQuery Migrate
 * - Enable Prism.js in admin
 * - Enable textarea fullscreen mode
 *
 * ## Filters
 *
 * ### functionalities_misc_option_{$key}
 * Filters individual misc option values.
 *
 * @since 0.8.0
 * @param bool $value The option value.
 *
 * @example
 * // Force disable emojis regardless of setting
 * add_filter( 'functionalities_misc_option_disable_emojis', '__return_true' );
 *
 * ### functionalities_misc_feeds_disabled_message
 * Filters the message shown when feeds are disabled.
 *
 * @since 0.8.0
 * @param string $message The disabled feeds message.
 *
 * ### functionalities_misc_prism_theme_url
 * Filters the Prism.js theme CSS URL.
 *
 * @since 0.8.0
 * @param string $url The theme URL.
 *
 * @example
 * // Use a different Prism theme
 * add_filter( 'functionalities_misc_prism_theme_url', function( $url ) {
 *     return 'https://unpkg.com/prismjs@1.29.0/themes/prism-tomorrow.min.css';
 * } );
 *
 * ## Actions
 *
 * ### functionalities_misc_init
 * Fires after misc module options have been processed.
 *
 * @since 0.8.0
 * @param array $opts The processed options array.
 *
 * @since 0.2.0
 */
class Misc {

	/**
	 * Initialize the misc module.
	 *
	 * Processes all configured options and registers appropriate
	 * WordPress hooks to implement each feature.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added action for extensibility.
	 *
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		// Disable block-based widgets.
		if ( self::is_option_enabled( $opts, 'disable_block_widgets' ) ) {
			\add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
			\add_filter( 'use_widgets_block_editor', '__return_false' );
		}

		// Load separate core block assets (WP 6.5+).
		if ( self::is_option_enabled( $opts, 'load_separate_core_block_assets' ) ) {
			\add_filter( 'wp_should_load_separate_core_block_assets', '__return_true' );
		}

		// Disable emojis.
		if ( self::is_option_enabled( $opts, 'disable_emojis' ) ) {
			self::disable_emojis();
		}

		// Disable embeds.
		if ( self::is_option_enabled( $opts, 'disable_embeds' ) ) {
			self::disable_embeds();
		}

		// Remove REST API links from head.
		if ( self::is_option_enabled( $opts, 'remove_rest_api_links_head' ) ) {
			\remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			\remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10 );
		}

		// Remove RSD, WLW, and shortlink.
		if ( self::is_option_enabled( $opts, 'remove_rsd_wlw_shortlink' ) ) {
			\remove_action( 'wp_head', 'rsd_link' );
			\remove_action( 'wp_head', 'wlwmanifest_link' );
			\remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		}

		// Remove generator meta tag.
		if ( self::is_option_enabled( $opts, 'remove_generator_meta' ) ) {
			\remove_action( 'wp_head', 'wp_generator' );
			\add_filter( 'the_generator', '__return_empty_string' );
		}

		// Disable XML-RPC.
		if ( self::is_option_enabled( $opts, 'disable_xmlrpc' ) ) {
			\add_filter( 'xmlrpc_enabled', '__return_false' );
		}

		// Disable XML-RPC Pingbacks (only if full XML-RPC is not disabled).
		if ( self::is_option_enabled( $opts, 'disable_xmlrpc_pingbacks' ) && ! self::is_option_enabled( $opts, 'disable_xmlrpc' ) ) {
			\add_filter(
				'xmlrpc_methods',
				function ( $methods ) {
					unset( $methods['pingback.ping'] );
					unset( $methods['pingback.extensions.getPingbacks'] );
					return $methods;
				}
			);
		}

		// Disable feeds.
		if ( self::is_option_enabled( $opts, 'disable_feeds' ) ) {
			self::disable_feeds();
		}

		// Disable Gravatars.
		if ( self::is_option_enabled( $opts, 'disable_gravatars' ) ) {
			\add_filter( 'get_avatar', '__return_empty_string' );
		}

		// Disable Self Pingbacks.
		if ( self::is_option_enabled( $opts, 'disable_self_pingbacks' ) ) {
			\add_action( 'pre_ping', array( __CLASS__, 'disable_self_pings' ) );
		}

		// Remove query strings from static resources.
		if ( self::is_option_enabled( $opts, 'remove_query_strings' ) ) {
			\add_filter( 'script_loader_src', array( __CLASS__, 'remove_ver_query_string' ), 15 );
			\add_filter( 'style_loader_src', array( __CLASS__, 'remove_ver_query_string' ), 15 );
		}

		// Remove DNS prefetch.
		if ( self::is_option_enabled( $opts, 'remove_dns_prefetch' ) ) {
			\add_filter(
				'wp_resource_hints',
				function ( $hints, $relation_type ) {
					return 'dns-prefetch' === $relation_type ? array() : $hints;
				},
				10,
				2
			);
		}

		// Remove recent comments inline CSS.
		if ( self::is_option_enabled( $opts, 'remove_recent_comments_css' ) ) {
			\add_action(
				'widgets_init',
				function () {
					global $wp_widget_factory;
					if ( isset( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'] ) ) {
						\remove_action( 'wp_head', array( $wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style' ) );
					}
				}
			);
		}

		// Limit revisions.
		if ( self::is_option_enabled( $opts, 'limit_revisions' ) ) {
			\add_filter(
				'wp_revisions_to_keep',
				function () {
					return 10;
				}
			);
		}

		// Disable dashicons for guests.
		if ( self::is_option_enabled( $opts, 'disable_dashicons_for_guests' ) ) {
			\add_action(
				'wp_enqueue_scripts',
				function () {
					if ( ! \is_user_logged_in() ) {
						\wp_deregister_style( 'dashicons' );
					}
				},
				9
			);
		}

		// Disable Heartbeat API.
		if ( self::is_option_enabled( $opts, 'disable_heartbeat' ) ) {
			\add_action(
				'init',
				function () {
					\wp_deregister_script( 'heartbeat' );
				},
				1
			);
		}

		// Hide admin bar on frontend.
		if ( self::is_option_enabled( $opts, 'disable_admin_bar_front' ) ) {
			\add_filter(
				'show_admin_bar',
				function ( $show ) {
					return \is_admin() ? $show : false;
				}
			);
		}

		// Remove jQuery Migrate.
		if ( self::is_option_enabled( $opts, 'remove_jquery_migrate' ) ) {
			\add_action(
				'wp_default_scripts',
				function ( $scripts ) {
					if ( isset( $scripts->registered['jquery'] ) ) {
						$jq = $scripts->registered['jquery'];
						if ( isset( $jq->deps ) && is_array( $jq->deps ) ) {
							$jq->deps = array_diff( $jq->deps, array( 'jquery-migrate' ) );
						}
					}
				}
			);
		}

		// Enable Prism.js syntax highlighting in admin.
		if ( self::is_option_enabled( $opts, 'enable_prism_admin' ) ) {
			\add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_prism' ) );
		}

		// Enable textarea fullscreen mode.
		if ( self::is_option_enabled( $opts, 'enable_textarea_fullscreen' ) ) {
			\add_action(
				'admin_enqueue_scripts',
				function () {
					\wp_enqueue_style( 'functionalities-admin-ui', FUNCTIONALITIES_URL . 'assets/css/admin-ui.css', array(), FUNCTIONALITIES_VERSION );
					\wp_enqueue_script( 'functionalities-admin-ui', FUNCTIONALITIES_URL . 'assets/js/admin-ui.js', array(), FUNCTIONALITIES_VERSION, true );
				}
			);
		}

		/**
		 * Fires after misc module has processed all options.
		 *
		 * @since 0.8.0
		 *
		 * @param array $opts The processed options array.
		 */
		\do_action( 'functionalities_misc_init', $opts );
	}

	/**
	 * Get module options with defaults.
	 *
	 * @since 0.2.0
	 *
	 * @return array {
	 *     Misc module options. All values are boolean.
	 *
	 *     @type bool $disable_block_widgets           Use classic widgets.
	 *     @type bool $load_separate_core_block_assets Split block assets.
	 *     @type bool $disable_emojis                  Remove emoji support.
	 *     @type bool $disable_embeds                  Remove oEmbed.
	 *     @type bool $remove_rest_api_links_head      No REST links in head.
	 *     @type bool $remove_rsd_wlw_shortlink        No RSD/WLW/shortlink.
	 *     @type bool $remove_generator_meta           No generator tag.
	 *     @type bool $disable_xmlrpc                  Disable XML-RPC.
	 *     @type bool $disable_feeds                   Disable RSS feeds.
	 *     @type bool $disable_dashicons_for_guests    No dashicons for guests.
	 *     @type bool $disable_heartbeat               Disable Heartbeat.
	 *     @type bool $disable_admin_bar_front         Hide frontend admin bar.
	 *     @type bool $remove_jquery_migrate           No jQuery Migrate.
	 *     @type bool $enable_prism_admin              Enable Prism.js.
	 *     @type bool $enable_textarea_fullscreen      Fullscreen textareas.
	 * }
	 */
	/**
	 * Cached options.
	 *
	 * @var array
	 */
	private static $options = null;

	/**
	 * Get module options with defaults.
	 *
	 * @since 0.2.0
	 *
	 * @return array Options array.
	 */
	protected static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
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
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Check if an option is enabled with filter support.
	 *
	 * @since 0.8.0
	 *
	 * @param array  $opts The options array.
	 * @param string $key  The option key to check.
	 * @return bool True if option is enabled.
	 */
	protected static function is_option_enabled( array $opts, string $key ) : bool {
		$value = ! empty( $opts[ $key ] );

		/**
		 * Filters individual misc option values.
		 *
		 * The dynamic portion of the hook name, `$key`, refers to the option key.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $value The option value.
		 */
		return \apply_filters( "functionalities_misc_option_{$key}", $value );
	}

	/**
	 * Enqueue Prism.js for admin syntax highlighting.
	 *
	 * @since 0.8.0
	 *
	 * @return void
	 */
	public static function enqueue_prism() : void {
		/**
		 * Filters the Prism.js theme CSS URL.
		 *
		 * @since 0.8.0
		 *
		 * @param string $url The theme URL.
		 */
		$theme_url = \apply_filters(
			'functionalities_misc_prism_theme_url',
			'https://unpkg.com/prismjs@1.29.0/themes/prism.min.css'
		);

		// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Admin-only feature using Prism.js for code highlighting. CDN used for performance.
		\wp_enqueue_style( 'functionalities-prism', $theme_url, array(), FUNCTIONALITIES_VERSION );
		// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Admin-only feature using Prism.js for code highlighting. CDN used for performance.
		\wp_enqueue_script( 'functionalities-prism', 'https://unpkg.com/prismjs@1.29.0/prism.min.js', array(), FUNCTIONALITIES_VERSION, true );

		\add_action(
			'admin_print_footer_scripts',
			function () {
				echo '<script>window.Prism&&Prism.highlightAll();</script>';
			}
		);
	}

	/**
	 * Remove version query string from scripts and styles.
	 *
	 * @since 0.13.0
	 *
	 * @param string $src The source URL.
	 * @return string The source URL without version query string.
	 */
	public static function remove_ver_query_string( string $src ) : string {
		if ( strpos( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Disable self-pingbacks.
	 *
	 * @since 0.13.0
	 *
	 * @param array $links Array of links to ping.
	 * @return void
	 */
	public static function disable_self_pings( array &$links ) : void {
		$home = home_url();
		foreach ( $links as $l => $link ) {
			if ( 0 === strpos( $link, $home ) ) {
				unset( $links[ $l ] );
			}
		}
	}

	/**
	 * Disable WordPress emoji support.
	 *
	 * Removes all emoji-related scripts, styles, and filters.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected static function disable_emojis() : void {
		\remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		\remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		\remove_action( 'wp_print_styles', 'print_emoji_styles' );
		\remove_action( 'admin_print_styles', 'print_emoji_styles' );
		\remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		\remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		\remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		\add_filter( 'emoji_svg_url', '__return_false' );
		\add_filter(
			'tiny_mce_plugins',
			function ( $plugins ) {
				if ( is_array( $plugins ) ) {
					return array_diff( $plugins, array( 'wpemoji' ) );
				}
				return array();
			}
		);
	}

	/**
	 * Disable WordPress oEmbed functionality.
	 *
	 * Removes embed discovery, scripts, and REST endpoints.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	protected static function disable_embeds() : void {
		\remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		\remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		\add_filter( 'embed_oembed_discover', '__return_false' );
		\add_filter(
			'tiny_mce_plugins',
			function ( $plugins ) {
				return is_array( $plugins ) ? array_diff( $plugins, array( 'wpembed' ) ) : array();
			}
		);
		\add_filter(
			'rest_endpoints',
			function ( $endpoints ) {
				unset( $endpoints['/oembed/1.0'] );
				unset( $endpoints['/oembed/1.0/embed'] );
				return $endpoints;
			}
		);
	}

	/**
	 * Disable RSS/Atom feeds.
	 *
	 * Redirects all feed requests to the homepage with a 301 status.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filter for disabled message.
	 *
	 * @return void
	 */
	protected static function disable_feeds() : void {
		$callback = function () {
			// Redirect to homepage.
			if ( function_exists( 'wp_safe_redirect' ) ) {
				\wp_safe_redirect( \home_url( '/' ), 301 );
				exit;
			}

			/**
			 * Filters the message shown when feeds are disabled.
			 *
			 * @since 0.8.0
			 *
			 * @param string $message The disabled feeds message.
			 */
			$message = \apply_filters(
				'functionalities_misc_feeds_disabled_message',
				\__( 'Feeds are disabled on this site.', 'functionalities' )
			);

			\wp_die( \esc_html( $message ) );
		};

		// Remove feed links from head.
		\remove_action( 'wp_head', 'feed_links_extra', 3 );
		\remove_action( 'wp_head', 'feed_links', 2 );

		// Hook into all feed actions.
		$feed_actions = array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom' );
		foreach ( $feed_actions as $action ) {
			\add_action( $action, $callback, 1 );
		}
	}
}
