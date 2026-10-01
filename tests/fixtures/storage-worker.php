<?php
require dirname( __DIR__ ) . '/bootstrap.php';
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/functionalities-content-test' );
}
require dirname( __DIR__, 2 ) . '/includes/storage/class-atomic-json-store.php';
require dirname( __DIR__, 2 ) . '/includes/storage/class-data-directory.php';
require dirname( __DIR__, 2 ) . '/includes/features/class-redirect-manager.php';
$GLOBALS['functionalities_test_filter_values']['functionalities_data_base_dir'] = $argv[2];
$GLOBALS['functionalities_test_filter_values']['functionalities_redirect_buffer_threshold'] = 100000;
$GLOBALS['functionalities_test_options']['functionalities_data_key'] = 'testprivatekey12345';
if ( 'events' === $argv[1] ) {
	$method = new ReflectionMethod( 'Functionalities\\Features\\Redirect_Manager', 'buffer_event' );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}
	for ( $i = 0; $i < (int) $argv[3]; ++$i ) {
		$method->invoke( null, 'hits', 'r_test' );
		$method->invoke( null, 'not_found', '/missing', array( 'origin' => 'https://referrer.test' ) );
		if ( 0 === $i % 7 ) {
			\Functionalities\Features\Redirect_Manager::flush_buffer();
		}
	}
} elseif ( 'update' === $argv[1] ) {
	\Functionalities\Storage\Atomic_JSON_Store::update(
		$argv[3],
		static function ( array $data ) use ( $argv ) {
			file_put_contents( $argv[4], 'ready' );
			usleep( 250000 );
			$data['tasks'][0]['text'] = 'Concurrent update';
			return $data;
		}
	);
}
