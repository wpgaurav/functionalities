<?php
/**
 * Filtered Link Health report reads.
 *
 * @package Functionalities\Features
 */
namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Filter before pagination while bounding serialized report data held in memory. */
class Link_Health_Report {
	public static function filters( array $input ): array {
		$filters = array();
		foreach ( array( 'link_status', 'source_type', 'link_search' ) as $key ) {
			$filters[ $key ] = isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? \sanitize_text_field( (string) $input[ $key ] ) : '';
		}
		if ( isset( $input['link_search'] ) && is_scalar( $input['link_search'] ) ) {
			$filters['link_search'] = \wp_strip_all_tags( (string) $input['link_search'], true );
		}
		if ( ! in_array( $filters['link_status'], array( 'ok', 'broken', 'redirected', 'unknown', 'unsafe', 'ignored', 'unchecked' ), true ) ) {
			$filters['link_status'] = '';
		}
		if ( ! in_array( $filters['source_type'], array( 'post', 'page' ), true ) ) {
			$filters['source_type'] = '';
		}
		preg_match( '/^.{0,200}/us', trim( $filters['link_search'] ), $search );
		$filters['link_search'] = $search[0] ?? '';
		return $filters;
	}

	/** Effective display status includes ignored and newly introduced, unchecked links. */
	public static function status( array $row, bool $ignored = false ): string {
		if ( $ignored ) {
			return 'ignored';
		}
		return 'unknown' === ( $row['status'] ?? 'unknown' ) && empty( $row['checked'] ) ? 'unchecked' : ( $row['status'] ?? 'unknown' );
	}

	/** Read matching sources once in bounded chunks, shared by pages and CSV exports. */
	private static function sources( array $filters ): \Generator {
		global $wpdb;
		$filters = self::filters( $filters );
		$cursor  = 0;
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded keyset traversal of report metadata only.
			$sources      = $wpdb->get_results( $wpdb->prepare( "SELECT p.ID, p.post_title, p.post_type, r.meta_value AS report_data, i.meta_value AS ignored_data FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = %s LEFT JOIN {$wpdb->postmeta} i ON i.post_id = p.ID AND i.meta_key = %s WHERE p.ID > %d AND p.post_type IN ('post','page') AND p.post_status = 'publish' AND p.post_password = '' ORDER BY p.ID ASC LIMIT 25", Link_Health::META_KEY, Link_Health::IGNORE_KEY, $cursor ) );
			$source_count = count( (array) $sources );
			foreach ( (array) $sources as $source ) {
				$cursor = (int) $source->ID;
				if ( $filters['source_type'] && $source->post_type !== $filters['source_type'] ) {
					continue;
				}
				$report = \maybe_unserialize( $source->report_data );
				if ( ! is_array( $report ) ) {
					continue;
				}
				$ignored        = (array) \maybe_unserialize( $source->ignored_data ?? '' );
				$matching_title = '' !== $filters['link_search'] && false !== stripos( $source->post_title, $filters['link_search'] );
				$rows           = array();
				foreach ( (array) ( $report['rows'] ?? array() ) as $key => $row ) {
					$row['status'] = self::status( $row, in_array( $key, $ignored, true ) );
					if ( $filters['link_status'] && $row['status'] !== $filters['link_status'] ) {
						continue;
					}
					if ( '' !== $filters['link_search'] && ! $matching_title && false === stripos( $row['url'], $filters['link_search'] ) ) {
						continue;
					}
					$rows[] = $row;
				}
				if ( $rows ) {
					yield array(
						'id'        => $cursor,
						'title'     => $source->post_title,
						'hash'      => $report['hash'] ?? '',
						'complete'  => ! empty( $report['complete'] ),
						'truncated' => ! empty( $report['truncated'] ),
						'rows'      => $rows,
					);
				}
			}
		} while ( 25 === $source_count );
	}

	/** Decorate only displayed/exported sources; do not load every post to count matches. */
	private static function details( array $source ): array {
		$post = Link_Health::public_post( $source['id'] );
		return $post ? array(
			'post_id'   => $source['id'],
			'title'     => $source['title'],
			'stale'     => $source['hash'] !== Link_Health::content_hash( $post ),
			'complete'  => $source['complete'],
			'truncated' => $source['truncated'],
		) : array();
	}

	/** Stream each matching row once rather than rescanning for every fifty CSV rows. */
	public static function export_rows( array $filters = array() ): \Generator {
		foreach ( self::sources( $filters ) as $source ) {
			$details = self::details( $source );
			if ( ! $details ) {
				continue;
			}
			foreach ( $source['rows'] as $row ) {
				yield array_merge( $row, $details );
			}
		}
	}

	public static function page( int $page, array $filters ): array {
		$page   = max( 1, $page );
		$offset = ( $page - 1 ) * Link_Health::RESULTS_PER_PAGE;
		$total  = 0;
		$rows   = array();
		foreach ( self::sources( $filters ) as $source ) {
			$start  = $total;
			$total += count( $source['rows'] );
			if ( $total <= $offset || count( $rows ) >= Link_Health::RESULTS_PER_PAGE ) {
				continue;
			}
			$details = self::details( $source );
			if ( ! $details ) {
				continue;
			}
			foreach ( array_slice( $source['rows'], max( 0, $offset - $start ), Link_Health::RESULTS_PER_PAGE - count( $rows ) ) as $row ) {
				$rows[] = array_merge( $row, $details );
			}
		}
		$pages = max( 1, (int) ceil( $total / Link_Health::RESULTS_PER_PAGE ) );
		if ( $page > $pages ) {
			return self::page( $pages, $filters );
		}
		return array(
			'rows'  => $rows,
			'page'  => $page,
			'pages' => $pages,
			'total' => $total,
		);
	}
}
