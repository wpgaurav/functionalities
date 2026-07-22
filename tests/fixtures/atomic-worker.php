<?php
/**
 * Concurrent atomic-store test worker.
 *
 * @package Functionalities\Tests
 */

define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
require_once dirname( __DIR__, 2 ) . '/includes/storage/class-atomic-json-store.php';

$path       = $argv[1];
$iterations = (int) $argv[2];

for ( $index = 0; $index < $iterations; ++$index ) {
	$result = \Functionalities\Storage\Atomic_JSON_Store::update(
		$path,
		static function ( array $data ): array {
			$data['count'] = (int) ( $data['count'] ?? 0 ) + 1;
			return $data;
		},
		array( 'count' => 0 )
	);

	if ( ! $result['success'] ) {
		exit( 1 );
	}
}
