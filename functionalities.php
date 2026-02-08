<?php
/**
 * Plugin Name:       Functionalities
 * Plugin URI:        https://functionalities.dev
 * Description:       All-in-one WordPress optimization toolkit. 15+ modules for performance, security, SEO, and content management.
 * Version:           1.0.1
 * Author:            Gaurav Tiwari
 * Author URI:        https://gauravtiwari.org
 * License:           GPL-2.0-or-later
 * Text Domain:       functionalities
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Update URI:        https://functionalities.dev
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Define constants.
if (!defined('FUNCTIONALITIES_VERSION')) {
	define('FUNCTIONALITIES_VERSION', '1.0.1');
}
if (!defined('FUNCTIONALITIES_FILE')) {
	define('FUNCTIONALITIES_FILE', __FILE__);
}
if (!defined('FUNCTIONALITIES_DIR')) {
	define('FUNCTIONALITIES_DIR', plugin_dir_path(__FILE__));
}
if (!defined('FUNCTIONALITIES_URL')) {
	define('FUNCTIONALITIES_URL', plugin_dir_url(__FILE__));
}
if (!defined('FUNCTIONALITIES_IS_PRO')) {
	define('FUNCTIONALITIES_IS_PRO', true);
}

// Simple autoloader for plugin classes.
spl_autoload_register(function (string $class) {
	if (strpos($class, 'Functionalities\\') !== 0) {
		return;
	}

	$parts = explode('\\', $class);
	array_shift($parts); // Remove Functionalities

	$filename = 'class-' . strtolower(str_replace('_', '-', (string) end($parts))) . '.php';
	
	$subpath = '';
	if (count($parts) > 1) {
		$subpath = strtolower((string) $parts[0]) . '/';
	}

	$file = FUNCTIONALITIES_DIR . 'includes/' . $subpath . $filename;

	if (file_exists($file)) {
		require_once $file;
	}
});

// Initialize plugin on init hook.
\add_action('init', function () {
	\Functionalities\Admin\License_Manager::init();
	\Functionalities\Admin\Admin::init();
	\Functionalities\Features\Link_Management::init();
	\Functionalities\Features\Block_Cleanup::init();
	\Functionalities\Features\Editor_Links::init();
	\Functionalities\Features\Misc::init();
	\Functionalities\Features\Snippets::init();
	\Functionalities\Features\Schema::init();
	\Functionalities\Features\Components::init();
	\Functionalities\Features\Fonts::init();
	\Functionalities\Features\Meta::init();
	\Functionalities\Features\Content_Regression::init();
	\Functionalities\Features\Assumption_Detection::init();
	\Functionalities\Features\Task_Manager::init();
	\Functionalities\Features\Redirect_Manager::init();
	\Functionalities\Features\Login_Security::init();
	\Functionalities\Features\SVG_Icons::init();
	\Functionalities\Premium\Loader::init();
}, 10);

// Activation/Deactivation hooks.
\register_activation_hook(__FILE__, function () {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	$free = 'dynamic-functionalities/dynamic-functionalities.php';
	if (\is_plugin_active($free)) {
		\deactivate_plugins($free);
		\set_transient('functionalities_free_deactivated', true, 30);
	}
	if (function_exists('flush_rewrite_rules')) {
		\flush_rewrite_rules();
	}
});

// Deactivate the free plugin if both are active (covers WP-CLI, bulk activate).
\add_action('admin_init', function () {
	$free = 'dynamic-functionalities/dynamic-functionalities.php';
	if (\is_plugin_active($free)) {
		\deactivate_plugins($free);
		\set_transient('functionalities_free_deactivated', true, 30);
	}
});

// Show a one-time notice after deactivating the free plugin.
\add_action('admin_notices', function () {
	if (!\get_transient('functionalities_free_deactivated')) {
		return;
	}
	\delete_transient('functionalities_free_deactivated');
	?>
	<div class="notice notice-info is-dismissible">
		<p><?php \esc_html_e('Dynamic Functionalities has been deactivated. Functionalities Pro includes all free features and more.', 'functionalities'); ?></p>
	</div>
	<?php
});

// Quick Settings link on the Plugins screen.
\add_filter('plugin_action_links_' . \plugin_basename(__FILE__), function (array $links): array {
	$url = \admin_url('admin.php?page=functionalities');
	$links[] = '<a href="' . \esc_url($url) . '">' . \esc_html__('Settings', 'functionalities') . '</a>';
	return $links;
});

// Add meta links on the Plugins screen (row meta).
\add_filter('plugin_row_meta', function (array $links, string $file): array {
	if (\plugin_basename(__FILE__) === $file) {
		$links[] = '<a href="https://functionalities.dev/docs" target="_blank" rel="noopener">' . \esc_html__('Documentation', 'functionalities') . '</a>';
		$links[] = '<a href="https://functionalities.dev/faq" target="_blank" rel="noopener">' . \esc_html__('FAQ', 'functionalities') . '</a>';
		$links[] = '<a href="https://github.com/wpgaurav/functionalities/issues" target="_blank" rel="noopener">' . \esc_html__('Report Issues', 'functionalities') . '</a>';
		$links[] = '<a href="https://gauravtiwari.org/contact/" target="_blank" rel="noopener">' . \esc_html__('Contact Developer', 'functionalities') . '</a>';
	}
	return $links;
}, 10, 2);

\register_deactivation_hook(__FILE__, function () {
	if (function_exists('flush_rewrite_rules')) {
		\flush_rewrite_rules();
	}
});
