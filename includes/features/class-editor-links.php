<?php
/**
 * Editor Link Suggestions limiting.
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor_Links {
	public static function init() : void {
		$opts = self::get_options();
		if ( empty( $opts['enable_limit'] ) ) {
			return;
		}
		\add_filter( 'wp_link_query_args', [ __CLASS__, 'filter_wp_link_query_args' ] );
		\add_filter( 'rest_search_query', [ __CLASS__, 'filter_rest_search_query' ], 10, 2 );
		\add_filter( 'rest_post_query', [ __CLASS__, 'filter_rest_post_query' ], 10, 2 );
	}

	protected static function get_options() : array {
		$defaults = [ 'enable_limit' => false, 'post_types' => [] ];
		$opts = (array) \get_option( 'functionalities_editor_links', $defaults );
		return array_merge( $defaults, $opts );
	}

	public static function filter_wp_link_query_args( array $query ) : array {
		$opts = self::get_options();
		$allowed = (array) ( $opts['post_types'] ?? [] );
		if ( ! empty( $allowed ) ) {
			$query['post_type'] = $allowed;
		}
		return $query;
	}

	public static function filter_rest_search_query( array $prepared_args, $request ) : array {
		$opts = self::get_options();
		$allowed = (array) ( $opts['post_types'] ?? [] );
		if ( empty( $allowed ) ) { return $prepared_args; }
		$type = isset( $prepared_args['type'] ) ? $prepared_args['type'] : $request->get_param( 'type' );
		if ( $type === 'post' || $type === null ) {
			$prepared_args['subtype'] = $allowed;
		}
		return $prepared_args;
	}

	public static function filter_rest_post_query( array $args, $request ) : array {
		$opts = self::get_options();
		$allowed = (array) ( $opts['post_types'] ?? [] );
		if ( ! empty( $allowed ) ) {
			$args['post_type'] = $allowed;
		}
		return $args;
	}
}
