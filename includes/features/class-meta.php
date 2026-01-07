<?php
/**
 * Meta Improvements Module - Copyright, Dublin Core, and SEO Plugin Integration.
 *
 * Provides comprehensive metadata output including copyright, Dublin Core (DCMI),
 * licensing options, and integrations with major SEO plugins for enhanced Schema.org
 * copyright data.
 *
 * @package Functionalities\Features
 * @since 0.5.0
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Meta class for handling copyright, Dublin Core, and SEO plugin integrations.
 *
 * Features:
 * - Copyright & ownership meta tags
 * - Dublin Core (DCMI) metadata
 * - Per-post license selection (Creative Commons + All Rights Reserved)
 * - Schema.org copyright integration with multiple SEO plugins
 * - Performance-optimized plugin detection (cached)
 *
 * Supported SEO Plugins:
 * - Rank Math
 * - Yoast SEO
 * - The SEO Framework
 * - SEOPress
 * - All in One SEO (AIOSEO)
 */
class Meta {

	/**
	 * Cached SEO plugin detection result.
	 *
	 * @var string|null
	 */
	private static $detected_seo_plugin = null;

	/**
	 * Available license types and their data.
	 *
	 * @var array
	 */
	private static $licenses = array();

	/**
	 * Initialize the module.
	 *
	 * @return void
	 */
	public static function init() : void {
		$options = self::get_options();

		// Only initialize if module is enabled.
		if ( empty( $options['enabled'] ) ) {
			return;
		}

		// Initialize licenses data.
		self::init_licenses();

		// Meta output in wp_head.
		if ( ! empty( $options['enable_copyright_meta'] ) || ! empty( $options['enable_dublin_core'] ) ) {
			\add_action( 'wp_head', array( __CLASS__, 'output_meta_tags' ), 5 );
		}

		// License metabox for posts.
		if ( ! empty( $options['enable_license_metabox'] ) ) {
			\add_action( 'add_meta_boxes', array( __CLASS__, 'add_license_metabox' ) );
			\add_action( 'save_post', array( __CLASS__, 'save_license_meta' ), 10, 2 );
		}

		// SEO plugin integrations (only if enabled).
		if ( ! empty( $options['enable_schema_integration'] ) ) {
			self::init_seo_integrations();
		}
	}

	/**
	 * Initialize license definitions.
	 *
	 * @return void
	 */
	private static function init_licenses() : void {
		$options   = self::get_options();
		$site_name = \get_bloginfo( 'name' );
		$home_url  = \home_url( '/' );

		// Allow customization via filter.
		self::$licenses = \apply_filters( 'functionalities_meta_licenses', array(
			'all-rights-reserved' => array(
				'rights'    => \__( 'All Rights Reserved', 'functionalities' ),
				'dc_rights' => sprintf(
					/* translators: %1$s: year, %2$s: site name */
					\__( '© %1$s %2$s. All rights reserved. No reproduction without permission.', 'functionalities' ),
					gmdate( 'Y' ),
					$site_name
				),
				'url'       => ! empty( $options['default_license_url'] ) ? $options['default_license_url'] : $home_url . 'disclaimer/',
				'name'      => \__( 'All Rights Reserved', 'functionalities' ),
			),
			'cc-by'               => array(
				'rights'    => \__( 'Creative Commons Attribution 4.0', 'functionalities' ),
				'dc_rights' => \__( 'This work is licensed under CC BY 4.0', 'functionalities' ),
				'url'       => 'https://creativecommons.org/licenses/by/4.0/',
				'name'      => 'CC BY 4.0',
			),
			'cc-by-sa'            => array(
				'rights'    => \__( 'Creative Commons Attribution-ShareAlike 4.0', 'functionalities' ),
				'dc_rights' => \__( 'This work is licensed under CC BY-SA 4.0', 'functionalities' ),
				'url'       => 'https://creativecommons.org/licenses/by-sa/4.0/',
				'name'      => 'CC BY-SA 4.0',
			),
			'cc-by-nc'            => array(
				'rights'    => \__( 'Creative Commons Attribution-NonCommercial 4.0', 'functionalities' ),
				'dc_rights' => \__( 'This work is licensed under CC BY-NC 4.0', 'functionalities' ),
				'url'       => 'https://creativecommons.org/licenses/by-nc/4.0/',
				'name'      => 'CC BY-NC 4.0',
			),
			'cc-by-nc-sa'         => array(
				'rights'    => \__( 'Creative Commons Attribution-NonCommercial-ShareAlike 4.0', 'functionalities' ),
				'dc_rights' => \__( 'This work is licensed under CC BY-NC-SA 4.0', 'functionalities' ),
				'url'       => 'https://creativecommons.org/licenses/by-nc-sa/4.0/',
				'name'      => 'CC BY-NC-SA 4.0',
			),
			'cc-by-nd'            => array(
				'rights'    => \__( 'Creative Commons Attribution-NoDerivatives 4.0', 'functionalities' ),
				'dc_rights' => \__( 'This work is licensed under CC BY-ND 4.0', 'functionalities' ),
				'url'       => 'https://creativecommons.org/licenses/by-nd/4.0/',
				'name'      => 'CC BY-ND 4.0',
			),
			'cc-by-nc-nd'         => array(
				'rights'    => \__( 'Creative Commons Attribution-NonCommercial-NoDerivatives 4.0', 'functionalities' ),
				'dc_rights' => \__( 'This work is licensed under CC BY-NC-ND 4.0', 'functionalities' ),
				'url'       => 'https://creativecommons.org/licenses/by-nc-nd/4.0/',
				'name'      => 'CC BY-NC-ND 4.0',
			),
			'cc0'                 => array(
				'rights'    => \__( 'CC0 1.0 Universal (Public Domain)', 'functionalities' ),
				'dc_rights' => \__( 'This work is dedicated to the public domain under CC0 1.0', 'functionalities' ),
				'url'       => 'https://creativecommons.org/publicdomain/zero/1.0/',
				'name'      => 'CC0 1.0',
			),
		) );
	}

	/**
	 * Detect active SEO plugin.
	 *
	 * Uses static caching for performance - only checks once per request.
	 *
	 * @return string Plugin identifier: 'rank-math', 'yoast', 'seo-framework', 'seopress', 'aioseo', or 'none'.
	 */
	public static function detect_seo_plugin() : string {
		// Return cached result if available.
		if ( self::$detected_seo_plugin !== null ) {
			return self::$detected_seo_plugin;
		}

		// Check for active SEO plugins (ordered by market share).
		if ( class_exists( 'RankMath' ) || defined( 'RANK_MATH_VERSION' ) ) {
			self::$detected_seo_plugin = 'rank-math';
		} elseif ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' ) ) {
			self::$detected_seo_plugin = 'yoast';
		} elseif ( defined( 'THE_SEO_FRAMEWORK_VERSION' ) || function_exists( 'the_seo_framework' ) ) {
			self::$detected_seo_plugin = 'seo-framework';
		} elseif ( defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' ) ) {
			self::$detected_seo_plugin = 'seopress';
		} elseif ( defined( 'AIOSEO_VERSION' ) || class_exists( 'AIOSEO\\Plugin\\AIOSEO' ) ) {
			self::$detected_seo_plugin = 'aioseo';
		} else {
			self::$detected_seo_plugin = 'none';
		}

		return self::$detected_seo_plugin;
	}

	/**
	 * Initialize SEO plugin integrations based on detected plugin.
	 *
	 * @return void
	 */
	private static function init_seo_integrations() : void {
		$seo_plugin = self::detect_seo_plugin();

		switch ( $seo_plugin ) {
			case 'rank-math':
				\add_filter( 'rank_math/json_ld', array( __CLASS__, 'add_copyright_to_rank_math' ), 99, 2 );
				break;

			case 'yoast':
				\add_filter( 'wpseo_schema_article', array( __CLASS__, 'add_copyright_to_yoast_article' ), 99, 1 );
				\add_filter( 'wpseo_schema_webpage', array( __CLASS__, 'add_copyright_to_yoast_webpage' ), 99, 1 );
				break;

			case 'seo-framework':
				\add_filter( 'the_seo_framework_json_data', array( __CLASS__, 'add_copyright_to_seo_framework' ), 99, 1 );
				break;

			case 'seopress':
				\add_filter( 'seopress_schemas_auto_output', array( __CLASS__, 'add_copyright_to_seopress' ), 99, 1 );
				break;

			case 'aioseo':
				\add_filter( 'aioseo_schema_output', array( __CLASS__, 'add_copyright_to_aioseo' ), 99, 1 );
				break;

			case 'none':
			default:
				// Output standalone JSON-LD schema when no SEO plugin is detected.
				\add_action( 'wp_head', array( __CLASS__, 'output_standalone_schema' ), 10 );
				break;
		}
	}

	/**
	 * Output standalone JSON-LD schema when no SEO plugin is detected.
	 *
	 * This provides schema.org copyright data independently, ensuring the
	 * meta module works without requiring a third-party SEO plugin.
	 *
	 * @return void
	 */
	public static function output_standalone_schema() : void {
		$options = self::get_options();

		// Check if we should output on this post type.
		if ( ! \is_singular( $options['post_types'] ) ) {
			return;
		}

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$copyright = self::get_copyright_schema_data();
		if ( $copyright === null ) {
			return;
		}

		$holder = self::get_copyright_holder( $post );

		// Build the schema.
		$schema = array(
			'@context'        => 'https://schema.org',
			'@type'           => 'Article',
			'headline'        => \get_the_title(),
			'datePublished'   => \get_the_date( 'c' ),
			'dateModified'    => \get_the_modified_date( 'c' ),
			'copyrightYear'   => $copyright['copyrightYear'],
			'copyrightHolder' => $copyright['copyrightHolder'],
			'author'          => array(
				'@type' => $holder['type'],
				'name'  => $holder['name'],
				'url'   => $holder['url'],
			),
			'publisher'       => array(
				'@type' => 'Organization',
				'name'  => \get_bloginfo( 'name' ),
				'url'   => \home_url( '/' ),
			),
			'mainEntityOfPage' => array(
				'@type' => 'WebPage',
				'@id'   => \get_permalink(),
			),
		);

		// Add license if not all-rights-reserved.
		if ( $copyright['license'] !== null ) {
			$schema['license'] = $copyright['license'];
		}

		// Add featured image if available.
		if ( \has_post_thumbnail() ) {
			$image_id  = \get_post_thumbnail_id();
			$image_url = \wp_get_attachment_image_url( $image_id, 'full' );
			if ( $image_url ) {
				$schema['image'] = $image_url;
			}
		}

		// Allow filtering the schema.
		$schema = \apply_filters( 'functionalities_meta_standalone_schema', $schema, $post );

		// Output the JSON-LD.
		echo "\n<!-- Functionalities Schema.org (Standalone) -->\n";
		echo '<script type="application/ld+json">';
		echo \wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo '</script>';
		echo "\n<!-- /Functionalities Schema.org -->\n\n";
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
	 * @return array Module options.
	 */
	public static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'enabled'                 => false,
			'enable_copyright_meta'   => true,
			'enable_dublin_core'      => true,
			'enable_license_metabox'  => true,
			'enable_schema_integration' => true,
			'default_license'         => 'all-rights-reserved',
			'default_license_url'     => '',
			'post_types'              => array( 'post' ),
			'copyright_holder_type'   => 'author', // 'author', 'site', 'custom'.
			'custom_copyright_holder' => '',
			'dc_language'             => '',
		);
		$opts     = (array) \get_option( 'functionalities_meta', $defaults );
		self::$options = array_merge( $defaults, $opts );
		return self::$options;
	}

	/**
	 * Get license data for a given license key.
	 *
	 * @param string $license License identifier.
	 * @return array License data.
	 */
	public static function get_license_data( string $license ) : array {
		if ( empty( self::$licenses ) ) {
			self::init_licenses();
		}

		return self::$licenses[ $license ] ?? self::$licenses['all-rights-reserved'];
	}

	/**
	 * Get copyright holder information.
	 *
	 * @param \WP_Post $post Post object.
	 * @return array {
	 *     @type string $name Copyright holder name.
	 *     @type string $url  Copyright holder URL.
	 *     @type string $type Schema.org type (Person or Organization).
	 * }
	 */
	private static function get_copyright_holder( \WP_Post $post ) : array {
		$options = self::get_options();

		switch ( $options['copyright_holder_type'] ) {
			case 'site':
				return array(
					'name' => \get_bloginfo( 'name' ),
					'url'  => \home_url( '/' ),
					'type' => 'Organization',
				);

			case 'custom':
				return array(
					'name' => ! empty( $options['custom_copyright_holder'] ) ? $options['custom_copyright_holder'] : \get_bloginfo( 'name' ),
					'url'  => \home_url( '/' ),
					'type' => 'Organization',
				);

			case 'author':
			default:
				$author_name = \get_the_author_meta( 'display_name', $post->post_author );
				$author_url  = \get_the_author_meta( 'url', $post->post_author );
				if ( empty( $author_url ) ) {
					$author_url = \get_author_posts_url( $post->post_author );
				}
				return array(
					'name' => $author_name,
					'url'  => $author_url,
					'type' => 'Person',
				);
		}
	}

	/**
	 * Output copyright and Dublin Core meta tags.
	 *
	 * @return void
	 */
	public static function output_meta_tags() : void {
		$options = self::get_options();

		// Check if we should output on this post type.
		if ( ! \is_singular( $options['post_types'] ) ) {
			return;
		}

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$holder         = self::get_copyright_holder( $post );
		$site_name      = \get_bloginfo( 'name' );
		$site_url       = \home_url( '/' );
		$year_published = \get_the_date( 'Y' );
		$year_modified  = \get_the_modified_date( 'Y' );
		$date_published = \get_the_date( 'Y-m-d' );
		$date_modified  = \get_the_modified_date( 'Y-m-d' );
		$permalink      = \get_permalink();
		$title          = \get_the_title();

		// Get license type (per-post or default).
		$license      = \get_post_meta( $post->ID, '_gt_content_license', true );
		$license      = ! empty( $license ) ? $license : $options['default_license'];
		$license_data = self::get_license_data( $license );

		// Copyright year range.
		$copyright_year = ( $year_published !== $year_modified )
			? $year_published . '-' . $year_modified
			: $year_published;

		// Get language.
		$dc_language = ! empty( $options['dc_language'] ) ? $options['dc_language'] : \get_bloginfo( 'language' );

		echo "\n<!-- Functionalities Meta Module -->\n";

		// Copyright & Ownership meta tags.
		if ( ! empty( $options['enable_copyright_meta'] ) ) {
			echo '<meta name="copyright" content="' . \esc_attr( '© ' . $copyright_year . ' ' . $holder['name'] ) . '">' . "\n";
			echo '<meta name="author" content="' . \esc_attr( $holder['name'] ) . '">' . "\n";
			echo '<meta name="owner" content="' . \esc_attr( $site_name ) . '">' . "\n";
			echo '<meta name="rights" content="' . \esc_attr( $license_data['rights'] ) . '">' . "\n";
			echo '<link rel="license" href="' . \esc_url( $license_data['url'] ) . '">' . "\n";
		}

		// Dublin Core (DCMI) metadata.
		if ( ! empty( $options['enable_dublin_core'] ) ) {
			echo '<meta name="DC.title" content="' . \esc_attr( $title ) . '">' . "\n";
			echo '<meta name="DC.creator" content="' . \esc_attr( $holder['name'] ) . '">' . "\n";
			echo '<meta name="DC.contributor" content="' . \esc_attr( $holder['name'] ) . '">' . "\n";
			echo '<meta name="DC.publisher" content="' . \esc_attr( $site_name ) . '">' . "\n";
			echo '<meta name="DC.rights" content="' . \esc_attr( $license_data['dc_rights'] ) . '">' . "\n";
			echo '<meta name="DC.rightsHolder" content="' . \esc_attr( $holder['name'] ) . '">' . "\n";
			echo '<meta name="DC.date" content="' . \esc_attr( $date_published ) . '">' . "\n";
			echo '<meta name="DC.date.created" content="' . \esc_attr( $date_published ) . '">' . "\n";
			echo '<meta name="DC.date.modified" content="' . \esc_attr( $date_modified ) . '">' . "\n";
			echo '<meta name="DC.type" content="Text">' . "\n";
			echo '<meta name="DC.format" content="text/html">' . "\n";
			echo '<meta name="DC.identifier" content="' . \esc_url( $permalink ) . '">' . "\n";
			echo '<meta name="DC.language" content="' . \esc_attr( $dc_language ) . '">' . "\n";
			echo '<meta name="DC.source" content="' . \esc_url( $site_url ) . '">' . "\n";
			echo '<meta name="original-source" content="' . \esc_url( $permalink ) . '">' . "\n";
			echo '<meta name="syndication-source" content="' . \esc_url( $permalink ) . '">' . "\n";
		}

		echo "<!-- /Functionalities Meta Module -->\n\n";
	}

	/**
	 * Add license metabox to post editor.
	 *
	 * @return void
	 */
	public static function add_license_metabox() : void {
		$options    = self::get_options();
		$post_types = ! empty( $options['post_types'] ) ? $options['post_types'] : array( 'post' );

		\add_meta_box(
			'gt_license_metabox',
			\__( 'Content License', 'functionalities' ),
			array( __CLASS__, 'render_license_metabox' ),
			$post_types,
			'side',
			'default'
		);
	}

	/**
	 * Render license metabox content.
	 *
	 * @param \WP_Post $post Current post object.
	 * @return void
	 */
	public static function render_license_metabox( \WP_Post $post ) : void {
		$options        = self::get_options();
		$current        = \get_post_meta( $post->ID, '_gt_content_license', true );
		$current        = ! empty( $current ) ? $current : $options['default_license'];
		$detected_plugin = self::detect_seo_plugin();

		\wp_nonce_field( 'gt_license_nonce', 'gt_license_nonce_field' );

		if ( empty( self::$licenses ) ) {
			self::init_licenses();
		}
		?>
		<select name="gt_content_license" id="gt_content_license" style="width:100%">
			<?php foreach ( self::$licenses as $key => $data ) : ?>
				<option value="<?php echo \esc_attr( $key ); ?>" <?php \selected( $current, $key ); ?>>
					<?php echo \esc_html( $data['name'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description" style="margin-top:8px">
			<?php \esc_html_e( 'Choose content license for this post.', 'functionalities' ); ?>
		</p>
		<p class="description" style="margin-top:4px;color:#059669">
			<span class="dashicons dashicons-yes" style="font-size:14px;width:14px;height:14px"></span>
			<?php if ( $detected_plugin !== 'none' ) : ?>
				<?php
				printf(
					/* translators: %s: SEO plugin name */
					\esc_html__( 'Schema integration: %s', 'functionalities' ),
					\esc_html( self::get_plugin_display_name( $detected_plugin ) )
				);
				?>
			<?php else : ?>
				<?php \esc_html_e( 'Schema: Standalone JSON-LD', 'functionalities' ); ?>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Get human-readable plugin name.
	 *
	 * @param string $plugin Plugin identifier.
	 * @return string Display name.
	 */
	private static function get_plugin_display_name( string $plugin ) : string {
		$names = array(
			'rank-math'     => 'Rank Math',
			'yoast'         => 'Yoast SEO',
			'seo-framework' => 'The SEO Framework',
			'seopress'      => 'SEOPress',
			'aioseo'        => 'All in One SEO',
			'none'          => \__( 'None', 'functionalities' ),
		);
		return $names[ $plugin ] ?? $plugin;
	}

	/**
	 * Save license meta on post save.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public static function save_license_meta( int $post_id, \WP_Post $post ) : void {
		// Verify nonce.
		if ( ! isset( $_POST['gt_license_nonce_field'] ) ) {
			return;
		}
		if ( ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['gt_license_nonce_field'] ) ), 'gt_license_nonce' ) ) {
			return;
		}

		// Skip autosaves.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check post type.
		$options    = self::get_options();
		$post_types = ! empty( $options['post_types'] ) ? $options['post_types'] : array( 'post' );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// Save license.
		if ( isset( $_POST['gt_content_license'] ) ) {
			$license = \sanitize_key( \wp_unslash( $_POST['gt_content_license'] ) );
			// Validate against known licenses.
			if ( empty( self::$licenses ) ) {
				self::init_licenses();
			}
			if ( array_key_exists( $license, self::$licenses ) ) {
				\update_post_meta( $post_id, '_gt_content_license', $license );
			}
		}
	}

	/**
	 * Get copyright schema data for current post.
	 *
	 * Helper method used by all SEO plugin integrations.
	 *
	 * @return array|null Copyright data or null if not applicable.
	 */
	private static function get_copyright_schema_data() : ?array {
		if ( ! \is_singular() ) {
			return null;
		}

		global $post;
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		$options    = self::get_options();
		$post_types = ! empty( $options['post_types'] ) ? $options['post_types'] : array( 'post' );
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return null;
		}

		$holder  = self::get_copyright_holder( $post );
		$license = \get_post_meta( $post->ID, '_gt_content_license', true );
		$license = ! empty( $license ) ? $license : $options['default_license'];

		$license_data = self::get_license_data( $license );

		return array(
			'copyrightYear'   => (int) \get_the_date( 'Y' ),
			'copyrightHolder' => array(
				'@type' => $holder['type'],
				'name'  => $holder['name'],
				'url'   => $holder['url'],
			),
			'license'         => $license !== 'all-rights-reserved' ? $license_data['url'] : null,
		);
	}

	/**
	 * Add copyright info to Rank Math JSON-LD.
	 *
	 * @param array  $data   JSON-LD data array.
	 * @param object $jsonld JSON-LD object.
	 * @return array Modified data.
	 */
	public static function add_copyright_to_rank_math( array $data, $jsonld ) : array {
		$copyright = self::get_copyright_schema_data();
		if ( $copyright === null ) {
			return $data;
		}

		// Find Article/BlogPosting/WebPage schemas and add copyright.
		$target_types = array( 'Article', 'BlogPosting', 'NewsArticle', 'WebPage', 'TechArticle', 'ScholarlyArticle' );

		foreach ( $data as $key => $schema ) {
			if ( isset( $schema['@type'] ) && in_array( $schema['@type'], $target_types, true ) ) {
				$data[ $key ]['copyrightYear']   = $copyright['copyrightYear'];
				$data[ $key ]['copyrightHolder'] = $copyright['copyrightHolder'];

				if ( $copyright['license'] !== null ) {
					$data[ $key ]['license'] = $copyright['license'];
				}
			}
		}

		return $data;
	}

	/**
	 * Add copyright info to Yoast SEO Article schema.
	 *
	 * @param array $data Article schema data.
	 * @return array Modified data.
	 */
	public static function add_copyright_to_yoast_article( array $data ) : array {
		$copyright = self::get_copyright_schema_data();
		if ( $copyright === null ) {
			return $data;
		}

		$data['copyrightYear']   = $copyright['copyrightYear'];
		$data['copyrightHolder'] = $copyright['copyrightHolder'];

		if ( $copyright['license'] !== null ) {
			$data['license'] = $copyright['license'];
		}

		return $data;
	}

	/**
	 * Add copyright info to Yoast SEO WebPage schema.
	 *
	 * @param array $data WebPage schema data.
	 * @return array Modified data.
	 */
	public static function add_copyright_to_yoast_webpage( array $data ) : array {
		$copyright = self::get_copyright_schema_data();
		if ( $copyright === null ) {
			return $data;
		}

		// Only add to WebPage if no Article schema exists.
		if ( ! \is_singular( 'post' ) ) {
			$data['copyrightYear']   = $copyright['copyrightYear'];
			$data['copyrightHolder'] = $copyright['copyrightHolder'];

			if ( $copyright['license'] !== null ) {
				$data['license'] = $copyright['license'];
			}
		}

		return $data;
	}

	/**
	 * Add copyright info to The SEO Framework JSON data.
	 *
	 * @param array $data JSON-LD data.
	 * @return array Modified data.
	 */
	public static function add_copyright_to_seo_framework( array $data ) : array {
		$copyright = self::get_copyright_schema_data();
		if ( $copyright === null ) {
			return $data;
		}

		// The SEO Framework uses a different structure.
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			$target_types = array( 'Article', 'BlogPosting', 'NewsArticle', 'WebPage' );
			foreach ( $data['@graph'] as $key => $item ) {
				if ( isset( $item['@type'] ) && in_array( $item['@type'], $target_types, true ) ) {
					$data['@graph'][ $key ]['copyrightYear']   = $copyright['copyrightYear'];
					$data['@graph'][ $key ]['copyrightHolder'] = $copyright['copyrightHolder'];

					if ( $copyright['license'] !== null ) {
						$data['@graph'][ $key ]['license'] = $copyright['license'];
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Add copyright info to SEOPress schemas.
	 *
	 * @param array $schemas Schema data.
	 * @return array Modified schemas.
	 */
	public static function add_copyright_to_seopress( array $schemas ) : array {
		$copyright = self::get_copyright_schema_data();
		if ( $copyright === null ) {
			return $schemas;
		}

		// SEOPress outputs schemas in a specific format.
		$target_types = array( 'Article', 'BlogPosting', 'NewsArticle', 'WebPage' );

		foreach ( $schemas as $key => $schema ) {
			if ( isset( $schema['@type'] ) && in_array( $schema['@type'], $target_types, true ) ) {
				$schemas[ $key ]['copyrightYear']   = $copyright['copyrightYear'];
				$schemas[ $key ]['copyrightHolder'] = $copyright['copyrightHolder'];

				if ( $copyright['license'] !== null ) {
					$schemas[ $key ]['license'] = $copyright['license'];
				}
			}
		}

		return $schemas;
	}

	/**
	 * Add copyright info to AIOSEO schema output.
	 *
	 * @param array $data Schema data.
	 * @return array Modified data.
	 */
	public static function add_copyright_to_aioseo( array $data ) : array {
		$copyright = self::get_copyright_schema_data();
		if ( $copyright === null ) {
			return $data;
		}

		// AIOSEO uses @graph structure.
		if ( isset( $data['@graph'] ) && is_array( $data['@graph'] ) ) {
			$target_types = array( 'Article', 'BlogPosting', 'NewsArticle', 'WebPage' );
			foreach ( $data['@graph'] as $key => $item ) {
				if ( isset( $item['@type'] ) ) {
					$type = is_array( $item['@type'] ) ? $item['@type'][0] : $item['@type'];
					if ( in_array( $type, $target_types, true ) ) {
						$data['@graph'][ $key ]['copyrightYear']   = $copyright['copyrightYear'];
						$data['@graph'][ $key ]['copyrightHolder'] = $copyright['copyrightHolder'];

						if ( $copyright['license'] !== null ) {
							$data['@graph'][ $key ]['license'] = $copyright['license'];
						}
					}
				}
			}
		}

		return $data;
	}
}
