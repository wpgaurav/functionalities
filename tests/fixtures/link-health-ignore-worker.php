<?php
/** Two processes share a metadata store and the real plugin lock. */
$base = $argv[1];
$actor = $argv[2];
define( 'WP_CONTENT_DIR', $base . '/content' );
function get_post_meta( $id, $key = '', $single = false ) {
	global $base, $actor;
	$value = is_file( $base . '/meta.json' ) ? json_decode( file_get_contents( $base . '/meta.json' ), true ) : array();
	if ( 'a' === $actor && ! is_file( $base . '/read-a' ) ) {
		file_put_contents( $base . '/read-a', 'read' );
		// Let the other process enter its mutation before the first write.
		usleep( 800000 );
	}
	return $value;
}
function update_post_meta( $id, $key, $value ) {
	global $base;
	return false !== file_put_contents( $base . '/meta.json', json_encode( $value ), LOCK_EX );
}
require dirname( __DIR__ ) . '/bootstrap.php';
require dirname( __DIR__, 2 ) . '/includes/core/class-module-registry.php';
require dirname( __DIR__, 2 ) . '/includes/storage/class-data-directory.php';
require dirname( __DIR__, 2 ) . '/includes/storage/class-atomic-json-store.php';
require dirname( __DIR__, 2 ) . '/includes/features/class-link-health.php';
$GLOBALS['functionalities_test_filter_values'] = array( 'functionalities_data_base_dir' => $base . '/private' );
$GLOBALS['functionalities_test_options'] = array( 'functionalities_data_directory_key' => 'testutilityprivate123', 'functionalities_link_health' => array( 'enabled' => true ) );
$GLOBALS['functionalities_test_options'][ Functionalities\Storage\Data_Directory::KEY_OPTION ] = 'testutilityprivate123';
$GLOBALS['functionalities_test_caps']['manage_options'] = true;
$post = new WP_Post(); $post->ID = 1; $post->post_content = '<a href="https://example.test/a">A</a><a href="https://example.test/b">B</a>';
$GLOBALS['functionalities_test_posts'][1] = $post;
$result = Functionalities\Features\Link_Health::set_ignored( 1, 'https://example.test/' . $actor, true );
echo json_encode( array( 'success' => true === $result ) );
