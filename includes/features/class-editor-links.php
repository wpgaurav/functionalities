<?php
/**
 * Editor Link Suggestions Module.
 *
 * Limits the post types that appear in the WordPress block editor's link
 * suggestion dropdown, helping content creators find relevant links faster.
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
 * Editor_Links class for filtering link suggestions in the block editor.
 *
 * Controls which post types appear when users search for internal links
 * in the Gutenberg editor. This helps streamline the linking workflow
 * by excluding irrelevant post types from search results.
 *
 * ## Features
 *
 * - Filter classic editor link suggestions (wp_link_query)
 * - Filter REST API search results for block editor
 * - Filter REST API post queries
 * - Configurable post type allowlist
 *
 * ## Filters
 *
 * ### functionalities_editor_links_enabled
 * Controls whether editor link filtering is active.
 *
 * @since 0.8.0
 * @param bool $enabled Whether filtering is enabled. Default based on settings.
 *
 * @example
 * // Disable editor link filtering for administrators
 * add_filter( 'functionalities_editor_links_enabled', function( $enabled ) {
 *     return $enabled && ! current_user_can( 'manage_options' );
 * } );
 *
 * ### functionalities_editor_links_post_types
 * Filters the allowed post types for link suggestions.
 *
 * @since 0.8.0
 * @param array $post_types Array of allowed post type slugs.
 *
 * @example
 * // Dynamically add custom post types
 * add_filter( 'functionalities_editor_links_post_types', function( $types ) {
 *     $types[] = 'product';
 *     $types[] = 'documentation';
 *     return $types;
 * } );
 *
 * @since 0.2.0
 */
class Editor_Links {

	/**
	 * Initialize the editor links module.
	 *
	 * Registers filters for the classic editor link dialog and
	 * the block editor REST API endpoints when enabled.
	 *
	 * @since 0.2.0
	 *
	 * @return void
	 */
	public static function init() : void {
		$opts = self::get_options();

		// Only add filters if the feature is enabled.
		if ( empty( $opts['enable_limit'] ) ) {
			return;
		}

		// Classic editor link suggestions.
		\add_filter( 'wp_link_query_args', array( __CLASS__, 'filter_wp_link_query_args' ) );

		// Block editor REST API filters.
		\add_filter( 'rest_search_query', array( __CLASS__, 'filter_rest_search_query' ), 10, 2 );
		\add_filter( 'rest_post_query', array( __CLASS__, 'filter_rest_post_query' ), 10, 2 );
	}

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
			'enable_limit' => false,
			'post_types'   => array(),
		);
		$opts = (array) \get_option( 'functionalities_editor_links', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Get the allowed post types with filtering.
	 *
	 * Retrieves the configured post types and applies the filter
	 * for third-party customization.
	 *
	 * @since 0.8.0
	 *
	 * @return array Array of allowed post type slugs.
	 */
	protected static function get_allowed_post_types() : array {
		$opts    = self::get_options();
		$allowed = (array) ( $opts['post_types'] ?? array() );

		/**
		 * Filters the allowed post types for editor link suggestions.
		 *
		 * @since 0.8.0
		 *
		 * @param array $allowed Array of post type slugs to include in link suggestions.
		 */
		return \apply_filters( 'functionalities_editor_links_post_types', $allowed );
	}

	/**
	 * Filter classic editor link query arguments.
	 *
	 * Modifies the WP_Query arguments used by the classic editor's
	 * "Insert/edit link" dialog to only search allowed post types.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filter for enabled state.
	 *
	 * @param array $query WP_Query arguments for link search.
	 * @return array Modified query arguments.
	 */
	public static function filter_wp_link_query_args( array $query ) : array {
		/**
		 * Filters whether editor link filtering is enabled.
		 *
		 * @since 0.8.0
		 *
		 * @param bool $enabled Whether filtering should be applied.
		 */
		if ( ! \apply_filters( 'functionalities_editor_links_enabled', true ) ) {
			return $query;
		}

		$allowed = self::get_allowed_post_types();

		if ( ! empty( $allowed ) ) {
			$query['post_type'] = $allowed;
		}

		return $query;
	}

	/**
	 * Filter REST API search query for block editor.
	 *
	 * Modifies search endpoint queries to only return results
	 * from allowed post types when searching for links.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filter for enabled state.
	 *
	 * @param array            $prepared_args Prepared query arguments.
	 * @param \WP_REST_Request $request       The REST request object.
	 * @return array Modified query arguments.
	 */
	public static function filter_rest_search_query( array $prepared_args, $request ) : array {
		/** This filter is documented in class-editor-links.php */
		if ( ! \apply_filters( 'functionalities_editor_links_enabled', true ) ) {
			return $prepared_args;
		}

		$allowed = self::get_allowed_post_types();

		if ( empty( $allowed ) ) {
			return $prepared_args;
		}

		// Determine the search type from args or request.
		$type = isset( $prepared_args['type'] ) ? $prepared_args['type'] : $request->get_param( 'type' );

		// Only filter post-type searches.
		if ( $type === 'post' || $type === null ) {
			$prepared_args['subtype'] = $allowed;
		}

		return $prepared_args;
	}

	/**
	 * Filter REST API post queries.
	 *
	 * Modifies post endpoint queries to only return results
	 * from allowed post types.
	 *
	 * @since 0.2.0
	 * @since 0.8.0 Added filter for enabled state.
	 *
	 * @param array            $args    WP_Query arguments.
	 * @param \WP_REST_Request $request The REST request object.
	 * @return array Modified query arguments.
	 */
	public static function filter_rest_post_query( array $args, $request ) : array {
		/** This filter is documented in class-editor-links.php */
		if ( ! \apply_filters( 'functionalities_editor_links_enabled', true ) ) {
			return $args;
		}

		$allowed = self::get_allowed_post_types();

		if ( ! empty( $allowed ) ) {
			$args['post_type'] = $allowed;
		}

		return $args;
	}
}
