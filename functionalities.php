<?php
/**
 * Plugin Name: Functionalities
 * Plugin URI: https://example.com/
 * Description: Modular site-specific plugin with modern dashboard, comprehensive link management, and WordPress coding standards compliance.
 * Version: 0.3.0
 * Author: Your Name
 * Author URI: https://example.com/
 * License: GPL-2.0-or-later
 * Text Domain: functionalities
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define constants.
if ( ! defined( 'FUNCTIONALITIES_VERSION' ) ) {
	define( 'FUNCTIONALITIES_VERSION', '0.3.0' );
}
if ( ! defined( 'FUNCTIONALITIES_FILE' ) ) {
	define( 'FUNCTIONALITIES_FILE', __FILE__ );
}
if ( ! defined( 'FUNCTIONALITIES_DIR' ) ) {
	define( 'FUNCTIONALITIES_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'FUNCTIONALITIES_URL' ) ) {
	define( 'FUNCTIONALITIES_URL', plugin_dir_url( __FILE__ ) );
}

// Autoload includes.
require_once FUNCTIONALITIES_DIR . 'includes/class-functionalities-loader.php';
require_once FUNCTIONALITIES_DIR . 'includes/admin/class-admin.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-link-management.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-block-cleanup.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-editor-links.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-misc.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-snippets.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-schema.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-components.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-fonts.php';
require_once FUNCTIONALITIES_DIR . 'includes/features/class-icons.php';

// Initialize plugin.
\add_action( 'plugins_loaded', function() {
	\load_plugin_textdomain( 'functionalities', false, dirname( \plugin_basename( __FILE__ ) ) . '/languages' );
	\Functionalities\Loader::init();
	\Functionalities\Admin\Admin::init();
	\Functionalities\Features\Link_Management::init();
	\Functionalities\Features\Block_Cleanup::init();
	\Functionalities\Features\Editor_Links::init();
	\Functionalities\Features\Misc::init();
	\Functionalities\Features\Snippets::init();
	\Functionalities\Features\Schema::init();
	\Functionalities\Features\Components::init();
	\Functionalities\Features\Fonts::init();
	\Functionalities\Features\Icons::init();
}, 10 );

// Activation/Deactivation hooks.
\register_activation_hook( __FILE__, function () {
	// If you add rewrites or CPTs on init, you may flush here.
	if ( function_exists( 'flush_rewrite_rules' ) ) {
		\flush_rewrite_rules();
	}
} );

// Quick Settings link on the Plugins screen.
\add_filter( 'plugin_action_links_' . \plugin_basename( __FILE__ ), function( array $links ) : array {
	$url = \admin_url( 'admin.php?page=functionalities' );
	$links[] = '<a href="' . \esc_url( $url ) . '">' . \esc_html__( 'Settings', 'functionalities' ) . '</a>';
	return $links;
} );

\register_deactivation_hook( __FILE__, function () {
	if ( function_exists( 'flush_rewrite_rules' ) ) {
		\flush_rewrite_rules();
	}
} );
