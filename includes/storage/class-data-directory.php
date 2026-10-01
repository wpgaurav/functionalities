<?php
/**
 * Private, guarded file storage and retryable legacy migration.
 *
 * @package Functionalities\Storage
 */

namespace Functionalities\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Resolve each site's storage without exposing JSON over HTTP. */
class Data_Directory {
	const KEY_OPTION = 'functionalities_data_key';

	/**
	 * Resolved paths by blog and base directory.
	 *
	 * @var array
	 */
	private static $paths = array();

	/**
	 * Migration and directory errors by context.
	 *
	 * @var array
	 */
	private static $errors = array();

	/** Return the storage base directory.
	 *
	 * @return string
	 */
	public static function base(): string {
		return rtrim( (string) \apply_filters( 'functionalities_data_base_dir', WP_CONTENT_DIR . '/functionalities' ), '/' );
	}

	/** Identify the current blog and storage base.
	 *
	 * @return string
	 */
	private static function context(): string {
		return ( \function_exists( 'get_current_blog_id' ) ? \get_current_blog_id() : 1 ) . ':' . self::base();
	}

	/** Return an empty path on failure so callers cannot create fresh, split data. */
	public static function path(): string {
		$context = self::context();
		if ( isset( self::$paths[ $context ] ) ) {
			return self::$paths[ $context ];
		}
		self::$errors[ $context ] = array();
		$base                     = self::base();
		if ( ! is_dir( $base ) && ! \wp_mkdir_p( $base ) ) {
			self::$errors[ $context ][] = 'directory_failed';
			return '';
		}
		$key = self::key();
		if ( '' === $key ) {
			return '';
		}
		$path = $base . '/' . $key;
		if ( ! is_dir( $path ) && ! \wp_mkdir_p( $path ) ) {
			self::$errors[ $context ][] = 'directory_failed';
			return '';
		}
		self::harden( $base );
		self::harden( $path );
		// A directory lock prevents simultaneous requests from observing a partial
		// migration. Individual file locks also synchronize with older writers.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$lock = fopen( $base . '/.migration-' . $key . '.lock', 'c+' );
		if ( false === $lock ) {
			self::$errors[ $context ][] = 'migration_lock_failed';
			return '';
		}
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				self::$errors[ $context ][] = 'migration_lock_failed';
				return '';
			}
			self::migrate_legacy_files( $base, $path );
			if ( ! empty( self::$errors[ $context ] ) ) {
				return '';
			}
			self::$paths[ $context ] = $path;
			return $path;
		} finally {
			flock( $lock, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $lock );
		}
	}

	/** Return guarded PHP filenames for JSON data, or an empty string on failure. */
	public static function file( string $relative ): string {
		if ( ! preg_match( '#^[a-zA-Z0-9_./-]+$#', $relative ) || in_array( '..', explode( '/', $relative ), true ) ) {
			return '';
		}
		$path = self::path();
		if ( '' === $path ) {
			return '';
		}
		if ( '.json' === substr( $relative, -5 ) ) {
			$relative .= '.php';
		}
		return $path . '/' . ltrim( $relative, '/' );
	}

	/** Generate the option under a filesystem lock to avoid orphan directories. */
	public static function key(): string {
		$key = (string) \get_option( self::KEY_OPTION, '' );
		if ( preg_match( '/^[A-Za-z0-9]{8,64}$/', $key ) ) {
			return $key;
		}
		$base = self::base();
		if ( ! is_dir( $base ) && ! \wp_mkdir_p( $base ) ) {
			self::$errors[ self::context() ][] = 'directory_failed';
			return '';
		}
		$blog = \function_exists( 'get_current_blog_id' ) ? \get_current_blog_id() : 1;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$lock = fopen( $base . '/.directory-key-' . $blog . '.lock', 'c+' );
		if ( false === $lock ) {
			self::$errors[ self::context() ][] = 'key_lock_failed';
			return '';
		}
		try {
			if ( ! flock( $lock, LOCK_EX ) ) {
				self::$errors[ self::context() ][] = 'key_lock_failed';
				return '';
			}
			// Re-read after waiting: another request may have created the key.
			\wp_cache_delete( self::KEY_OPTION, 'options' );
			\wp_cache_delete( 'alloptions', 'options' );
			\wp_cache_delete( 'notoptions', 'options' );
			$key = (string) \get_option( self::KEY_OPTION, '' );
			if ( ! preg_match( '/^[A-Za-z0-9]{8,64}$/', $key ) ) {
				$key = \wp_generate_password( 20, false, false );
				if ( ! \add_option( self::KEY_OPTION, $key, '', true ) ) {
					\update_option( self::KEY_OPTION, $key, true );
				}
				if ( $key !== (string) \get_option( self::KEY_OPTION, '' ) ) {
					self::$errors[ self::context() ][] = 'key_save_failed';
					return '';
				}
			}
			return $key;
		} finally {
			flock( $lock, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			fclose( $lock );
		}
	}

	/** Write secondary web-server protections; PHP guards protect every payload. */
	public static function harden( string $directory ): void {
		if ( ! is_dir( $directory ) ) {
			return;
		}
		$files = array(
			'index.php'  => "<?php http_response_code(404); exit;\n",
			'.htaccess'  => "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n",
			'web.config' => '<?xml version="1.0" encoding="UTF-8"?><configuration><system.webServer><authorization><deny users="*" /></authorization></system.webServer></configuration>',
		);
		foreach ( $files as $name => $contents ) {
			$target = $directory . '/' . $name;
			if ( ! file_exists( $target ) ) {
				// Native local writes match the storage backend and avoid credential prompts.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				file_put_contents( $target, $contents, LOCK_EX );
			}
		}
	}

	/** Migrate both the original public location and earlier private raw JSON. */
	private static function migrate_legacy_files( string $base, string $private ): void {
		$directories = array( $private );
		if ( ! \function_exists( 'is_multisite' ) || ! \is_multisite() || \is_main_site() ) {
			array_unshift( $directories, $base );
		}
		foreach ( array_unique( $directories ) as $directory ) {
			foreach ( array( 'redirects.json', '404-log.json', 'redirect-buffer.json' ) as $name ) {
				$from = $directory . '/' . $name;
				if ( file_exists( $from ) ) {
					$result = Atomic_JSON_Store::migrate( $from, $private . '/' . $name . '.php' );
					if ( ! $result['success'] ) {
						self::$errors[ self::context() ][ $name ] = $result['error'];
					}
				}
			}
			foreach ( glob( $directory . '/tasks/*.json' ) ?: array() as $from ) {
				$target = $private . '/tasks/' . basename( $from ) . '.php';
				$result = Atomic_JSON_Store::migrate( $from, $target );
				if ( ! $result['success'] ) {
					self::$errors[ self::context() ][ 'tasks/' . basename( $from ) ] = $result['error'];
				}
			}
		}
		if ( is_dir( $private . '/tasks' ) ) {
			self::harden( $private . '/tasks' );
		}
	}

	/** Return errors for the current site.
	 *
	 * @return array
	 */
	public static function get_errors(): array {
		return self::$errors[ self::context() ] ?? array();
	}

	/** Create a harmless guarded canary and resolve its HTTP URL.
	 *
	 * @return string
	 */
	public static function probe_url(): string {
		$path = self::path();
		if ( '' === $path ) {
			return '';
		}
		$canary = $path . '/privacy-probe.json.php';
		if ( ! file_exists( $canary ) ) {
			$result = Atomic_JSON_Store::write( $canary, array( 'functionalities_private_canary' => true ) );
			if ( ! $result['success'] ) {
				self::$errors[ self::context() ][] = $result['error'];
				return '';
			}
		}
		$content = rtrim( WP_CONTENT_DIR, '/' ) . '/';
		$url     = 0 === strpos( $canary, $content ) ? \content_url( substr( $canary, strlen( $content ) ) ) : '';
		return (string) \apply_filters( 'functionalities_data_probe_url', $url, $canary );
	}

	/** Reset request-local site paths, for tests and explicit retry. */
	public static function flush(): void {
		self::$paths  = array();
		self::$errors = array();
	}
}
