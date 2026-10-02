<?php
/**
 * Permission-checked draft duplication.
 *
 * @package Functionalities\Features
 */
namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Duplicate native posts/pages without copying identity or publishing state. */
class Content_Tools {
	/** Register actions only when enabled. */
	public static function init(): void {
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'content-tools' ) ) {
			return;
		}
		\add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		\add_filter( 'page_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		\add_action( 'admin_post_functionalities_duplicate_content', array( __CLASS__, 'handle_duplicate' ) );
	}

	/** Return a failure when the current user cannot duplicate the source. */
	public static function check_source( int $post_id ) {
		$post = \get_post( $post_id );
		if ( ! \Functionalities\Core\Module_Registry::is_enabled( 'content-tools' ) || ! $post instanceof \WP_Post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
			return new \WP_Error( 'invalid_source', \__( 'This content cannot be duplicated.', 'functionalities' ) );
		}
		$type = \get_post_type_object( $post->post_type );
		if ( ! $type || ! \current_user_can( 'edit_post', $post_id ) || ! \current_user_can( $type->cap->create_posts ) ) {
			return new \WP_Error( 'forbidden', \__( 'You cannot create a draft from this content.', 'functionalities' ) );
		}
		return $post;
	}

	/** Add a source-specific nonce to the editor action. */
	public static function row_actions( array $actions, \WP_Post $post ): array {
		if ( ! \is_wp_error( self::check_source( (int) $post->ID ) ) ) {
			$url                                  = \wp_nonce_url( \admin_url( 'admin-post.php?action=functionalities_duplicate_content&post_id=' . (int) $post->ID ), 'functionalities_duplicate_' . (int) $post->ID );
			$actions['functionalities_duplicate'] = '<a href="' . \esc_url( $url ) . '">' . \esc_html__( 'Duplicate as draft', 'functionalities' ) . '</a>';
		}
		return $actions;
	}

	/** Duplicate one source; remove a partial draft if a copy step fails. */
	public static function duplicate_post( int $post_id ) {
		$post = self::check_source( $post_id );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}
		$taxonomies = array();
		foreach ( \get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			$terms = \wp_get_object_terms( $post_id, $taxonomy->name, array( 'fields' => 'ids' ) );
			if ( \is_wp_error( $terms ) ) {
				return $terms;
			}
			if ( $terms && ! \current_user_can( $taxonomy->cap->assign_terms ) ) {
				return new \WP_Error( 'forbidden_terms', \__( 'You cannot assign the source content taxonomies.', 'functionalities' ) );
			}
			$taxonomies[ $taxonomy->name ] = array_map( 'intval', $terms );
		}
		$meta = array();
		$keys = (array) \apply_filters( 'functionalities_content_tools_meta_keys', array( '_thumbnail_id', '_wp_page_template' ), $post );
		foreach ( array_unique( $keys ) as $key ) {
			if ( ! is_string( $key ) || in_array( $key, array( '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_attached_file', '_wp_attachment_metadata' ), true ) || 0 === strpos( $key, '_functionalities_' ) ) {
				continue;
			}
			// Native editor fields use edit_post; generic protected-meta caps deny them by default.
			if ( ! in_array( $key, array( '_thumbnail_id', '_wp_page_template' ), true ) && ! \current_user_can( 'edit_post_meta', $post_id, $key ) ) {
				return new \WP_Error( 'forbidden_meta', \__( 'You cannot copy one of the supported metadata fields.', 'functionalities' ) );
			}
			$meta[ $key ] = \get_post_meta( $post_id, $key, false );
		}
		$new_id = \wp_insert_post(
			\wp_slash(
				array(
					'post_type'      => $post->post_type,
					'post_status'    => 'draft',
					'post_author'    => \get_current_user_id(),
					'post_title'     => $post->post_title,
					'post_content'   => $post->post_content,
					'post_excerpt'   => $post->post_excerpt,
					'post_parent'    => 0,
					'menu_order'     => $post->menu_order,
					'comment_status' => $post->comment_status,
					'ping_status'    => $post->ping_status,
				)
			),
			true
		);
		if ( \is_wp_error( $new_id ) ) {
			return $new_id;
		}
		foreach ( $taxonomies as $name => $terms ) {
			$result = \wp_set_object_terms( $new_id, $terms, $name );
			if ( \is_wp_error( $result ) ) {
				\wp_delete_post( $new_id, true );
				return $result;
			}
		}
		foreach ( $meta as $key => $values ) {
			foreach ( $values as $value ) {
				if ( ( ! in_array( $key, array( '_thumbnail_id', '_wp_page_template' ), true ) && ! \current_user_can( 'edit_post_meta', $new_id, $key ) ) || ! \add_post_meta( $new_id, $key, \wp_slash( $value ) ) ) {
					\wp_delete_post( $new_id, true );
					return new \WP_Error( 'copy_failed', \__( 'Metadata could not be copied. The partial draft was removed.', 'functionalities' ) );
				}
			}
		}
		return (int) $new_id;
	}

	/** Validate the web action and open the new draft. */
	public static function handle_duplicate(): void {
		$post_id = isset( $_GET['post_id'] ) ? \absint( $_GET['post_id'] ) : 0;
		\check_admin_referer( 'functionalities_duplicate_' . $post_id );
		$result = self::duplicate_post( $post_id );
		if ( \is_wp_error( $result ) ) {
			\wp_die( \esc_html( $result->get_error_message() ), '', array( 'response' => 403 ) );
		}
		\wp_safe_redirect( \admin_url( 'post.php?post=' . $result . '&action=edit' ) );
		exit;
	}
}
