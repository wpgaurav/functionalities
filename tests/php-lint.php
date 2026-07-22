<?php
/**
 * Recursively lint production PHP files.
 *
 * @package FunctionalitiesTests
 */

$root      = dirname( __DIR__ );
$locations = array(
	$root . '/functionalities.php',
	$root . '/uninstall.php',
	$root . '/includes',
	$root . '/assets',
	$root . '/languages',
);
$files     = array();

foreach ( $locations as $location ) {
	if ( is_file( $location ) ) {
		$files[] = $location;
		continue;
	}

	$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $location ) );
	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
			$files[] = $file->getPathname();
		}
	}
}

sort( $files );

foreach ( $files as $file ) {
	$command = escapeshellarg( PHP_BINARY ) . ' -l ' . escapeshellarg( $file );
	passthru( $command, $status );
	if ( 0 !== $status ) {
		exit( $status );
	}
}

printf( "Linted %d PHP files.\n", count( $files ) );
