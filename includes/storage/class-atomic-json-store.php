<?php
/**
 * Atomic JSON file storage.
 *
 * @package Functionalities\Storage
 */

namespace Functionalities\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and mutate JSON files without exposing partially written data.
 */
class Atomic_JSON_Store {

	/**
	 * Read a JSON file under a shared lock.
	 *
	 * @param string $path    Absolute file path.
	 * @param array  $default Default data when the file does not exist.
	 * @return array Result with success, data, error, and exists keys.
	 */
	public static function read( string $path, array $default = array() ): array {
		$lock = self::open_lock( $path );
		if ( false === $lock ) {
			return self::result( false, $default, 'lock_open_failed', file_exists( $path ) );
		}

		if ( ! flock( $lock, LOCK_SH ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the native flock lifecycle.
			fclose( $lock );
			return self::result( false, $default, 'lock_failed', file_exists( $path ) );
		}

		$result = self::read_unlocked( $path, $default );
		flock( $lock, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the native flock lifecycle.
		fclose( $lock );

		return $result;
	}

	/**
	 * Replace a JSON file atomically.
	 *
	 * @param string $path Absolute file path.
	 * @param array  $data Data to write.
	 * @return array Result with success, data, error, and exists keys.
	 */
	public static function write( string $path, array $data ): array {
		return self::update(
			$path,
			static function () use ( $data ) {
				return $data;
			},
			array()
		);
	}

	/**
	 * Atomically perform a locked read-modify-write operation.
	 *
	 * The callback receives the current array and a boolean indicating whether
	 * the file existed. Returning a non-array aborts the update.
	 *
	 * @param string   $path     Absolute file path.
	 * @param callable $mutator  Data mutator.
	 * @param array    $default  Default data when the file does not exist.
	 * @return array Result with success, data, error, and exists keys.
	 */
	public static function update( string $path, callable $mutator, array $default = array() ): array {
		if ( ! self::ensure_directory( dirname( $path ) ) ) {
			return self::result( false, $default, 'directory_failed', file_exists( $path ) );
		}

		$lock = self::open_lock( $path );
		if ( false === $lock ) {
			return self::result( false, $default, 'lock_open_failed', file_exists( $path ) );
		}

		if ( ! flock( $lock, LOCK_EX ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the native flock lifecycle.
			fclose( $lock );
			return self::result( false, $default, 'lock_failed', file_exists( $path ) );
		}

		$current = self::read_unlocked( $path, $default );
		if ( ! $current['success'] ) {
			flock( $lock, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the native flock lifecycle.
			fclose( $lock );
			return $current;
		}

		$next = call_user_func( $mutator, $current['data'], $current['exists'] );
		if ( ! is_array( $next ) ) {
			flock( $lock, LOCK_UN );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the native flock lifecycle.
			fclose( $lock );
			return self::result( false, $current['data'], 'update_aborted', $current['exists'] );
		}

		$result = self::write_unlocked( $path, $next );
		flock( $lock, LOCK_UN );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Completes the native flock lifecycle.
		fclose( $lock );

		return $result;
	}

	/**
	 * Read without acquiring a lock.
	 *
	 * @param string $path    Absolute file path.
	 * @param array  $default Default data.
	 * @return array
	 */
	private static function read_unlocked( string $path, array $default ): array {
		if ( ! file_exists( $path ) ) {
			return self::result( true, $default, '', false );
		}

		// Direct local reads are required here because the lock protects this exact file.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$json = file_get_contents( $path );
		if ( false === $json ) {
			return self::result( false, $default, 'read_failed', true );
		}

		$data = json_decode( $json, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			return self::result( false, $default, 'invalid_json', true );
		}

		return self::result( true, $data, '', true );
	}

	/**
	 * Write to a same-directory temporary file and atomically rename it.
	 *
	 * @param string $path Absolute file path.
	 * @param array  $data Data to write.
	 * @return array
	 */
	private static function write_unlocked( string $path, array $data ): array {
		$permissions = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
		if ( file_exists( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fileperms -- Required to preserve permissions across atomic replacement.
			$existing_permissions = fileperms( $path );
			if ( false !== $existing_permissions ) {
				$permissions = $existing_permissions & 0777;
			}
		}
		if ( function_exists( 'wp_json_encode' ) ) {
			$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		} else {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fallback is used only by isolated tests without WordPress loaded.
			$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		if ( false === $json ) {
			return self::result( false, $data, 'encode_failed', file_exists( $path ) );
		}

		// tempnam() in the destination directory keeps rename() atomic.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_tempnam
		$temp = tempnam( dirname( $path ), '.functionalities-' );
		if ( false === $temp ) {
			return self::result( false, $data, 'temp_file_failed', file_exists( $path ) );
		}

		// Direct writes are intentional: WP_Filesystem does not expose locking or atomic rename semantics.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$bytes = file_put_contents( $temp, $json, LOCK_EX );
		if ( false === $bytes || strlen( $json ) !== $bytes ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $temp );
			return self::result( false, $data, 'write_failed', file_exists( $path ) );
		}

		// Verify the temporary file before it can replace known-good data.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$verification = file_get_contents( $temp );
		json_decode( false === $verification ? '' : $verification, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $temp );
			return self::result( false, $data, 'verification_failed', file_exists( $path ) );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_chmod -- Preserve the destination file mode across replacement.
		chmod( $temp, $permissions );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
		if ( ! rename( $temp, $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
			unlink( $temp );
			return self::result( false, $data, 'rename_failed', file_exists( $path ) );
		}

		return self::result( true, $data, '', true );
	}

	/**
	 * Open the sidecar lock file.
	 *
	 * @param string $path Data file path.
	 * @return resource|false
	 */
	private static function open_lock( string $path ) {
		if ( ! self::ensure_directory( dirname( $path ) ) ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		return fopen( $path . '.lock', 'c+' );
	}

	/**
	 * Ensure a directory exists.
	 *
	 * @param string $directory Directory path.
	 * @return bool
	 */
	private static function ensure_directory( string $directory ): bool {
		if ( is_dir( $directory ) ) {
			return true;
		}

		if ( function_exists( 'wp_mkdir_p' ) ) {
			return wp_mkdir_p( $directory );
		}

		// Test/runtime fallback when WordPress is not loaded.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		return mkdir( $directory, 0755, true );
	}

	/**
	 * Build a consistent operation result.
	 *
	 * @param bool   $success Whether the operation succeeded.
	 * @param array  $data    Current data.
	 * @param string $error   Machine-readable error code.
	 * @param bool   $exists  Whether the data file exists.
	 * @return array
	 */
	private static function result( bool $success, array $data, string $error, bool $exists ): array {
		return array(
			'success' => $success,
			'data'    => $data,
			'error'   => $error,
			'exists'  => $exists,
		);
	}
}
