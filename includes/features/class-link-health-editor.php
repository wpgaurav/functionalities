<?php
/**
 * Previewed, source-specific edits for Link Health.
 *
 * @package Functionalities\Features
 */
namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Edit only reviewed anchors, with an atomic content comparison before saving. */
class Link_Health_Editor {
	/** Read the current database row rather than an earlier request-local post cache. */
	private static function source( int $post_id ) {
		if ( ! \current_user_can( 'manage_options' ) || ! \current_user_can( 'edit_post', $post_id ) || ! \Functionalities\Core\Module_Registry::is_enabled( 'link-health' ) ) {
			return new \WP_Error( 'forbidden', \__( 'You need permission to edit this source post.', 'functionalities' ) );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fresh source is required for optimistic concurrency.
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
		$post = $row instanceof \WP_Post ? $row : ( $row ? new \WP_Post( $row ) : null );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || 'publish' !== $post->post_status || '' !== $post->post_password ) {
			return new \WP_Error( 'invalid_source', \__( 'This source is no longer a public post or page.', 'functionalities' ) );
		}
		if ( function_exists( 'wp_check_post_lock' ) && \wp_check_post_lock( $post_id ) ) {
			return new \WP_Error( 'post_locked', \__( 'Another user is editing this post. Try again after they finish.', 'functionalities' ) );
		}
		return $post;
	}

	/** Accept absolute HTTP(S) destinations only; previewing never requests the URL. */
	private static function valid_destination( string $url ): bool {
		$parts = \wp_parse_url( $url );
		return strlen( $url ) <= 2048 && ! preg_match( '/[\x00-\x20\x7f]/', $url ) && is_array( $parts ) && in_array( strtolower( $parts['scheme'] ?? '' ), array( 'http', 'https' ), true ) && ! empty( $parts['host'] ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
	}

	/** A quick HTML edit must not disagree with a block's separately serialized settings. */
	private static function block_settings_reference( array $blocks, string $base, string $from ): bool {
		$contains = static function ( $values ) use ( &$contains, $base, $from ): bool {
			foreach ( (array) $values as $key => $value ) {
				if ( is_array( $value ) && $contains( $value ) ) {
					return true; }
				if ( ! is_string( $value ) || '' === $value ) {
					continue; }
				$is_url = preg_match( '~^(?:https?:)?//|^[/?#]~i', $value ) || preg_match( '/(?:url|href|link|src)$/i', (string) $key );
				if ( $is_url && Link_Health::normalize_url( $value, $base ) === $from ) {
					return true; }
				if ( false !== strpos( $value, '<' ) && in_array( $from, Link_Health::extract_links( $value, $base ), true ) ) {
					return true; }
			}
			return false;
		};
		foreach ( $blocks as $block ) {
			if ( $contains( $block['attrs'] ?? array() ) || self::block_settings_reference( $block['innerBlocks'] ?? array(), $base, $from ) ) {
				return true; }
		}
		return false;
	}

	/** Change only anchor tokens; all surrounding content remains byte-for-byte intact. */
	public static function transform( string $content, string $base, string $from, string $operation, string $to = '' ) {
		if ( ! in_array( $operation, array( 'replace', 'unlink' ), true ) || ( 'replace' === $operation && ! self::valid_destination( $to ) ) ) {
			return new \WP_Error( 'invalid_destination', \__( 'Enter a complete http:// or https:// URL without credentials or spaces.', 'functionalities' ) );
		}
		if ( false !== strpos( $content, '<!-- wp:' ) && self::block_settings_reference( \parse_blocks( $content ), $base, $from ) ) {
			return new \WP_Error( 'block_settings', \__( 'This URL is also stored in block settings. Edit it in the post editor to keep the block consistent.', 'functionalities' ) );
		}
		$parser  = new class( $content ) extends \WP_HTML_Tag_Processor {
			/** Isolate the core 6.3/6.5 bookmark span difference in one adapter. */
			public function token_span(): array {
				if ( ! $this->set_bookmark( 'functionalities_edit' ) ) {
					return array();
				}
				$span = $this->bookmarks['functionalities_edit'];
				$this->release_bookmark( 'functionalities_edit' );
				return array( $span->start, isset( $span->length ) ? $span->length : $span->end - $span->start + 1 );
			}
		};
		$patches = array();
		$stack   = array();
		$count   = 0;
		while ( $parser->next_tag(
			array(
				'tag_name'    => 'A',
				'tag_closers' => 'visit',
			)
		) ) {
			$span = $parser->token_span();
			$tag  = $span ? substr( $content, $span[0], $span[1] ) : '';
			if ( '' === $tag || '<' !== $tag[0] || '>' !== substr( $tag, -1 ) ) {
				return new \WP_Error( 'unsupported_markup', \__( 'This link markup needs to be edited in the post editor.', 'functionalities' ) );
			}
			if ( $parser->is_tag_closer() ) {
				$opening = array_pop( $stack );
				if ( $opening && $opening['unwrap'] ) {
					$patches[] = array( $opening['span'][0], $opening['span'][1], '' );
					$patches[] = array( $span[0], $span[1], '' );
				}
				continue;
			}
			$href    = $parser->get_attribute( 'href' );
			$matches = is_string( $href ) && Link_Health::normalize_url( $href, $base ) === $from;
			$button  = in_array( 'wp-block-button__link', preg_split( '/[\t\n\f\r ]+/', (string) $parser->get_attribute( 'class' ) ), true );
			if ( 'unlink' === $operation && $stack && ( $matches || in_array( true, array_column( $stack, 'matched' ), true ) ) ) {
				return new \WP_Error( 'ambiguous_markup', \__( 'Nested links need to be edited in the post editor.', 'functionalities' ) );
			}
			$stack[] = array(
				'span'    => $span,
				'matched' => $matches,
				'unwrap'  => $matches && 'unlink' === $operation && ! $button,
			);
			if ( ! $matches ) {
				continue;
			}
			++$count;
			if ( 'unlink' === $operation && ! $button ) {
				continue;
			}
			$edit = new \WP_HTML_Tag_Processor( $tag );
			$edit->next_tag( 'A' );
			if ( 'replace' === $operation ) {
				$destination = $to;
				if ( false === strpos( $to, '#' ) && false !== strpos( $href, '#' ) ) {
					$destination .= substr( $href, strpos( $href, '#' ) );
				}
				$edit->set_attribute( 'href', $destination );
			} else {
				foreach ( array( 'href', 'target', 'rel', 'download' ) as $attribute ) {
					$edit->remove_attribute( $attribute );
				}
			}
			$patches[] = array( $span[0], $span[1], $edit->get_updated_html() );
		}
		if ( 'unlink' === $operation && in_array( true, array_column( $stack, 'matched' ), true ) ) {
			return new \WP_Error( 'unclosed_link', \__( 'An unclosed link needs to be edited in the post editor.', 'functionalities' ) );
		}
		usort(
			$patches,
			static function ( $a, $b ) {
				return $b[0] <=> $a[0];
			}
		);
		$updated = $content;
		foreach ( $patches as $patch ) {
			$updated = substr_replace( $updated, $patch[2], $patch[0], $patch[1] );
		}
		if ( ! $count || $updated === $content ) {
			return new \WP_Error( 'no_change', \__( 'No matching links would change in this post.', 'functionalities' ) );
		}
		return array(
			'content' => $updated,
			'count'   => $count,
		);
	}

	private static function key( int $post_id ): string {
		return Link_Health::CACHE_PREFIX . 'edit_' . \get_current_user_id() . '_' . $post_id;
	}

	/** Bind the reviewed operation to the current actor and source fingerprint. */
	public static function preview( int $post_id, string $from, string $operation, string $to = '' ) {
		$post = self::source( $post_id );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}
		$change = self::transform( $post->post_content, \get_permalink( $post_id ), $from, $operation, trim( $to ) );
		if ( \is_wp_error( $change ) ) {
			return $change;
		}
		$token = \wp_generate_uuid4();
		$data  = array(
			'token'     => $token,
			'hash'      => Link_Health::content_hash( $post ),
			'from'      => $from,
			'to'        => trim( $to ),
			'operation' => $operation,
		);
		\set_transient( self::key( $post_id ), $data, 10 * MINUTE_IN_SECONDS );
		return array(
			'token'     => $token,
			'post_id'   => $post_id,
			'title'     => $post->post_title,
			'count'     => $change['count'],
			'operation' => $operation,
			'from'      => $from,
			'to'        => trim( $to ),
		);
	}

	/** Save only the exact reviewed content; concurrent saves make the comparison fail. */
	public static function apply( int $post_id, string $token ) {
		$post = self::source( $post_id );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}
		$review = \get_transient( self::key( $post_id ) );
		if ( ! is_array( $review ) || ! hash_equals( $review['token'], $token ) || Link_Health::content_hash( $post ) !== $review['hash'] ) {
			return new \WP_Error( 'stale_preview', \__( 'The preview expired or this post changed. Preview the edit again.', 'functionalities' ) );
		}
		$change = self::transform( $post->post_content, \get_permalink( $post_id ), $review['from'], $review['operation'], $review['to'] );
		if ( \is_wp_error( $change ) ) {
			return $change;
		}
		if ( function_exists( 'wp_save_post_revision' ) ) {
			\wp_save_post_revision( $post_id );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Atomic byte comparison prevents overwriting a concurrent save and avoids re-filtering unrelated block/script content.
		$saved = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_content = %s, post_modified = %s, post_modified_gmt = %s WHERE ID = %d AND BINARY post_content = %s AND BINARY post_name = %s AND post_parent = %d AND post_type = %s AND post_status = 'publish' AND post_password = ''", $change['content'], \current_time( 'mysql' ), \current_time( 'mysql', true ), $post_id, $post->post_content, $post->post_name, $post->post_parent, $post->post_type ) );
		if ( 1 !== $saved ) {
			return new \WP_Error( 'save_conflict', \__( 'The post changed or could not be saved. Preview the edit again.', 'functionalities' ) );
		}
		\delete_transient( self::key( $post_id ) );
		\update_post_meta( $post_id, '_edit_last', \get_current_user_id() );
		\clean_post_cache( $post_id );
		$after = \get_post( $post_id );
		\do_action( 'post_updated', $post_id, $after, $post );
		\do_action( 'save_post_' . $post->post_type, $post_id, $after, true );
		\do_action( 'save_post', $post_id, $after, true );
		\do_action( 'wp_insert_post', $post_id, $after, true );
		if ( function_exists( 'wp_after_insert_post' ) ) {
			\wp_after_insert_post( $post_id, true, $post );
		}
		$refreshed = Link_Health::refresh_after_edit( $post_id, $review['hash'] );
		return array(
			'post_id'   => $post_id,
			'title'     => $post->post_title,
			'count'     => $change['count'],
			'operation' => $review['operation'],
			'refreshed' => $refreshed,
		);
	}
}
