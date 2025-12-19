<?php
/**
 * Core Loader for the Functionalities Plugin.
 *
 * Handles the initialization of frontend assets including stylesheets and scripts.
 * This class serves as the main entry point for frontend functionality.
 *
 * @package    Functionalities
 * @subpackage Core
 * @since      0.1.0
 * @version    0.8.0
 */

namespace Functionalities;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Loader class for managing frontend assets.
 *
 * Responsible for:
 * - Registering and enqueuing frontend CSS stylesheets
 * - Registering and enqueuing frontend JavaScript files
 * - Providing filters for asset customization
 *
 * ## Filters
 *
 * ### functionalities_enqueue_style
 * Controls whether the main stylesheet is enqueued.
 *
 * @since 0.8.0
 * @param bool $enqueue Whether to enqueue the style. Default true.
 *
 * @example
 * // Disable the main stylesheet on specific pages
 * add_filter( 'functionalities_enqueue_style', function( $enqueue ) {
 *     if ( is_page( 'no-styles' ) ) {
 *         return false;
 *     }
 *     return $enqueue;
 * } );
 *
 * ### functionalities_enqueue_script
 * Controls whether the main script is enqueued.
 *
 * @since 0.8.0
 * @param bool $enqueue Whether to enqueue the script. Default true.
 *
 * @example
 * // Disable the main script entirely
 * add_filter( 'functionalities_enqueue_script', '__return_false' );
 *
 * ### functionalities_style_dependencies
 * Filters the style dependencies array.
 *
 * @since 0.8.0
 * @param array $deps Array of style handle dependencies. Default empty array.
 *
 * ### functionalities_script_dependencies
 * Filters the script dependencies array.
 *
 * @since 0.8.0
 * @param array $deps Array of script handle dependencies. Default empty array.
 *
 * ## Actions
 *
 * ### functionalities_before_enqueue_assets
 * Fires before assets are enqueued.
 *
 * @since 0.8.0
 *
 * ### functionalities_after_enqueue_assets
 * Fires after assets are enqueued.
 *
 * @since 0.8.0
 *
 * @since 0.1.0
 */
class Loader {

	/**
	 * Initialize the loader and register hooks.
	 *
	 * Hooks into WordPress to enqueue frontend assets at the appropriate time.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public static function init() : void {
		\add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Register and enqueue frontend assets.
	 *
	 * Registers both the main stylesheet and script, then conditionally
	 * enqueues them based on filter values.
	 *
	 * @since 0.1.0
	 * @since 0.8.0 Added filters for controlling asset enqueuing.
	 *
	 * @return void
	 */
	public static function enqueue_assets() : void {
		/**
		 * Fires before frontend assets are enqueued.
		 *
		 * Use this action to register additional assets or modify global state
		 * before the main plugin assets are loaded.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_before_enqueue_assets' );

		/**
		 * Filters the stylesheet dependencies.
		 *
		 * @since 0.8.0
		 *
		 * @param array $deps Array of registered stylesheet handles this stylesheet depends on.
		 */
		$style_deps = \apply_filters( 'functionalities_style_dependencies', array() );

		/**
		 * Filters the script dependencies.
		 *
		 * @since 0.8.0
		 *
		 * @param array $deps Array of registered script handles this script depends on.
		 */
		$script_deps = \apply_filters( 'functionalities_script_dependencies', array() );

		// Register the main stylesheet.
		\wp_register_style(
			'functionalities-style',
			FUNCTIONALITIES_URL . 'assets/css/style.css',
			$style_deps,
			FUNCTIONALITIES_VERSION
		);

		// Register the main script.
		\wp_register_script(
			'functionalities-script',
			FUNCTIONALITIES_URL . 'assets/js/main.js',
			$script_deps,
			FUNCTIONALITIES_VERSION,
			true // Load in footer.
		);

		/**
		 * Filters whether to enqueue the main stylesheet.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enqueue Whether to enqueue the stylesheet. Default true.
		 */
		if ( \apply_filters( 'functionalities_enqueue_style', true ) ) {
			\wp_enqueue_style( 'functionalities-style' );
		}

		/**
		 * Filters whether to enqueue the main script.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enqueue Whether to enqueue the script. Default true.
		 */
		if ( \apply_filters( 'functionalities_enqueue_script', true ) ) {
			\wp_enqueue_script( 'functionalities-script' );
		}

		/**
		 * Fires after frontend assets are enqueued.
		 *
		 * Use this action to enqueue additional assets that depend on the
		 * main plugin assets.
		 *
		 * @since 0.8.0
		 */
		\do_action( 'functionalities_after_enqueue_assets' );
	}
}
