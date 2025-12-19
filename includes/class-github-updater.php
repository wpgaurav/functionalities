<?php
/**
 * GitHub Updater - Enables plugin updates directly from GitHub releases.
 *
 * This class hooks into WordPress's plugin update system to check for
 * new releases on GitHub and allows one-click updates from the dashboard.
 *
 * @package Functionalities
 * @since 0.5.0
 */

namespace Functionalities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub Updater class for handling plugin updates from GitHub releases.
 *
 * Features:
 * - Checks GitHub releases API for new versions
 * - Caches results to avoid API rate limits
 * - Integrates with WordPress update UI
 * - Supports private repositories with access tokens
 * - Shows changelog from release notes
 */
class GitHub_Updater {

	/**
	 * Plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin slug (directory/file.php).
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * GitHub repository owner.
	 *
	 * @var string
	 */
	private $github_owner;

	/**
	 * GitHub repository name.
	 *
	 * @var string
	 */
	private $github_repo;

	/**
	 * GitHub access token for private repos (optional).
	 *
	 * @var string
	 */
	private $access_token;

	/**
	 * Current plugin version.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Cache key for storing release data.
	 *
	 * @var string
	 */
	private $cache_key;

	/**
	 * Cache duration in seconds (default: 6 hours).
	 *
	 * @var int
	 */
	private $cache_duration = 21600;

	/**
	 * Cached release data.
	 *
	 * @var object|null
	 */
	private $release_data = null;

	/**
	 * Initialize the updater.
	 *
	 * @param array $config {
	 *     Configuration options.
	 *
	 *     @type string $plugin_file     Full path to the main plugin file.
	 *     @type string $github_owner    GitHub repository owner/organization.
	 *     @type string $github_repo     GitHub repository name.
	 *     @type string $access_token    Optional. GitHub personal access token for private repos.
	 *     @type int    $cache_duration  Optional. Cache duration in seconds. Default 21600 (6 hours).
	 * }
	 */
	public function __construct( array $config ) {
		$this->plugin_file     = $config['plugin_file'];
		$this->plugin_slug     = \plugin_basename( $config['plugin_file'] );
		$this->github_owner    = $config['github_owner'];
		$this->github_repo     = $config['github_repo'];
		$this->access_token    = $config['access_token'] ?? '';
		$this->cache_duration  = $config['cache_duration'] ?? 21600;
		$this->cache_key       = 'functionalities_github_update_' . md5( $this->plugin_slug );

		// Get current version from plugin headers.
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugin_data = \get_plugin_data( $this->plugin_file );
		$this->current_version = $plugin_data['Version'];
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() : void {
		// Check for updates.
		\add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );

		// Provide plugin information for the update details popup.
		\add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

		// Ensure proper source during update.
		\add_filter( 'upgrader_source_selection', array( $this, 'fix_source_dir' ), 10, 4 );

		// Add "Check for updates" link to plugin actions.
		\add_filter( 'plugin_action_links_' . $this->plugin_slug, array( $this, 'add_check_update_link' ) );

		// Handle manual update check.
		\add_action( 'admin_init', array( $this, 'handle_manual_check' ) );

		// Show admin notice after manual check.
		\add_action( 'admin_notices', array( $this, 'show_update_notice' ) );
	}

	/**
	 * Check for plugin updates.
	 *
	 * @param object $transient Update transient.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( $release && version_compare( $this->current_version, $release->version, '<' ) ) {
			$transient->response[ $this->plugin_slug ] = (object) array(
				'slug'        => dirname( $this->plugin_slug ),
				'plugin'      => $this->plugin_slug,
				'new_version' => $release->version,
				'url'         => $release->html_url,
				'package'     => $release->download_url,
				'icons'       => array(),
				'banners'     => array(),
				'tested'      => '',
				'requires_php' => '7.4',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin information for the update details popup.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin info or false.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== dirname( $this->plugin_slug ) ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		// Get plugin data for additional info.
		$plugin_data = \get_plugin_data( $this->plugin_file );

		return (object) array(
			'name'              => $plugin_data['Name'],
			'slug'              => dirname( $this->plugin_slug ),
			'version'           => $release->version,
			'author'            => $plugin_data['Author'],
			'author_profile'    => $plugin_data['AuthorURI'],
			'homepage'          => $plugin_data['PluginURI'],
			'requires'          => '5.8',
			'tested'            => '',
			'requires_php'      => '7.4',
			'downloaded'        => 0,
			'last_updated'      => $release->published_at,
			'sections'          => array(
				'description' => $plugin_data['Description'],
				'changelog'   => $this->format_changelog( $release->body ),
			),
			'download_link'     => $release->download_url,
			'banners'           => array(),
		);
	}

	/**
	 * Fix the source directory name after extraction.
	 *
	 * GitHub zip files extract to `repo-name-tag/` but we need `plugin-dir/`.
	 *
	 * @param string      $source        File source location.
	 * @param string      $remote_source Remote file source location.
	 * @param \WP_Upgrader $upgrader      WP_Upgrader instance.
	 * @param array       $hook_extra    Extra arguments passed to hooked filters.
	 * @return string|WP_Error Modified source or error.
	 */
	public function fix_source_dir( $source, $remote_source, $upgrader, $hook_extra ) {
		global $wp_filesystem;

		// Only process our plugin.
		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_slug ) {
			return $source;
		}

		// Expected directory name.
		$expected_dir = dirname( $this->plugin_slug );
		$new_source   = trailingslashit( $remote_source ) . $expected_dir . '/';

		// If source already matches, return as-is.
		if ( trailingslashit( $source ) === $new_source ) {
			return $source;
		}

		// Rename directory.
		if ( $wp_filesystem->move( $source, $new_source ) ) {
			return $new_source;
		}

		return new \WP_Error(
			'rename_failed',
			\__( 'Unable to rename the update folder.', 'functionalities' )
		);
	}

	/**
	 * Get the latest release from GitHub.
	 *
	 * @param bool $force_refresh Force refresh cache.
	 * @return object|null Release data or null on failure.
	 */
	public function get_latest_release( bool $force_refresh = false ) : ?object {
		// Return cached data if available.
		if ( $this->release_data !== null && ! $force_refresh ) {
			return $this->release_data;
		}

		// Check transient cache.
		if ( ! $force_refresh ) {
			$cached = \get_transient( $this->cache_key );
			if ( $cached !== false ) {
				$this->release_data = $cached;
				return $cached;
			}
		}

		// Fetch from GitHub API.
		$url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			$this->github_owner,
			$this->github_repo
		);

		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress/' . \get_bloginfo( 'version' ) . '; ' . \home_url(),
			),
		);

		// Add authorization for private repos.
		if ( ! empty( $this->access_token ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->access_token;
		}

		$response = \wp_remote_get( $url, $args );

		if ( \is_wp_error( $response ) ) {
			return null;
		}

		$code = \wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			return null;
		}

		$body = \wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( ! $data || ! isset( $data->tag_name ) ) {
			return null;
		}

		// Parse version from tag (remove 'v' prefix if present).
		$version = ltrim( $data->tag_name, 'vV' );

		// Find the zip asset or use the zipball URL.
		$download_url = '';

		// First, look for a .zip asset in the release.
		if ( ! empty( $data->assets ) ) {
			foreach ( $data->assets as $asset ) {
				if ( substr( $asset->name, -4 ) === '.zip' ) {
					$download_url = $asset->browser_download_url;
					break;
				}
			}
		}

		// Fallback to zipball URL.
		if ( empty( $download_url ) ) {
			$download_url = $data->zipball_url;

			// Add access token to private repo downloads.
			if ( ! empty( $this->access_token ) ) {
				$download_url = add_query_arg( 'access_token', $this->access_token, $download_url );
			}
		}

		$release = (object) array(
			'version'      => $version,
			'tag_name'     => $data->tag_name,
			'html_url'     => $data->html_url,
			'download_url' => $download_url,
			'body'         => $data->body ?? '',
			'published_at' => $data->published_at ?? '',
			'prerelease'   => $data->prerelease ?? false,
		);

		// Cache the result.
		\set_transient( $this->cache_key, $release, $this->cache_duration );
		$this->release_data = $release;

		return $release;
	}

	/**
	 * Format the changelog from GitHub release body (Markdown).
	 *
	 * @param string $body Release body in Markdown.
	 * @return string Formatted HTML changelog.
	 */
	private function format_changelog( string $body ) : string {
		if ( empty( $body ) ) {
			return '<p>' . \esc_html__( 'No changelog available.', 'functionalities' ) . '</p>';
		}

		// Basic Markdown to HTML conversion.
		$html = \esc_html( $body );

		// Convert headers.
		$html = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $html );
		$html = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $html );
		$html = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $html );

		// Convert bold and italic.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
		$html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );

		// Convert lists.
		$html = preg_replace( '/^[\-\*] (.+)$/m', '<li>$1</li>', $html );
		$html = preg_replace( '/(<li>.*<\/li>\n?)+/', '<ul>$0</ul>', $html );

		// Convert line breaks.
		$html = nl2br( $html );

		return $html;
	}

	/**
	 * Add "Check for updates" link to plugin action links.
	 *
	 * @param array $links Existing links.
	 * @return array Modified links.
	 */
	public function add_check_update_link( array $links ) : array {
		$check_url = \wp_nonce_url(
			\admin_url( 'plugins.php?functionalities_check_update=1' ),
			'functionalities_check_update'
		);

		$links['check_update'] = sprintf(
			'<a href="%s">%s</a>',
			\esc_url( $check_url ),
			\esc_html__( 'Check for updates', 'functionalities' )
		);

		return $links;
	}

	/**
	 * Handle manual update check.
	 *
	 * @return void
	 */
	public function handle_manual_check() : void {
		if ( ! isset( $_GET['functionalities_check_update'] ) ) {
			return;
		}

		if ( ! \current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( ! \wp_verify_nonce( $_GET['_wpnonce'] ?? '', 'functionalities_check_update' ) ) {
			return;
		}

		// Force refresh cache.
		\delete_transient( $this->cache_key );
		$release = $this->get_latest_release( true );

		// Clear WordPress update cache.
		\delete_site_transient( 'update_plugins' );

		// Set transient for notice.
		if ( $release && version_compare( $this->current_version, $release->version, '<' ) ) {
			\set_transient( 'functionalities_update_available', $release->version, 60 );
		} else {
			\set_transient( 'functionalities_update_checked', true, 60 );
		}

		// Redirect back to plugins page.
		\wp_safe_redirect( \admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * Show admin notice after manual update check.
	 *
	 * @return void
	 */
	public function show_update_notice() : void {
		$screen = \get_current_screen();
		if ( ! $screen || $screen->id !== 'plugins' ) {
			return;
		}

		$new_version = \get_transient( 'functionalities_update_available' );
		if ( $new_version ) {
			\delete_transient( 'functionalities_update_available' );
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				sprintf(
					/* translators: %s: new version number */
					\esc_html__( 'Functionalities update available! Version %s is ready to install.', 'functionalities' ),
					'<strong>' . \esc_html( $new_version ) . '</strong>'
				)
			);
			return;
		}

		$checked = \get_transient( 'functionalities_update_checked' );
		if ( $checked ) {
			\delete_transient( 'functionalities_update_checked' );
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s</p></div>',
				\esc_html__( 'Functionalities is up to date!', 'functionalities' )
			);
		}
	}

	/**
	 * Clear the update cache.
	 *
	 * @return void
	 */
	public function clear_cache() : void {
		\delete_transient( $this->cache_key );
		$this->release_data = null;
	}

	/**
	 * Get current version.
	 *
	 * @return string Current version.
	 */
	public function get_current_version() : string {
		return $this->current_version;
	}

	/**
	 * Get GitHub repository URL.
	 *
	 * @return string Repository URL.
	 */
	public function get_repository_url() : string {
		return sprintf( 'https://github.com/%s/%s', $this->github_owner, $this->github_repo );
	}
}
