<?php
/**
 * Progressive Web App Module.
 *
 * Makes WordPress sites installable as standalone apps with offline support,
 * service worker caching, custom install prompts, and full Web App Manifest.
 *
 * @package    Functionalities
 * @subpackage Features
 * @since      1.1.0
 *
 * ## Filters
 *
 * ### functionalities_pwa_manifest
 * Filters the manifest array before JSON output.
 *
 * @since 1.1.0
 * @param array $manifest The manifest data.
 *
 * ### functionalities_pwa_enabled
 * Controls whether PWA output is active.
 *
 * @since 1.1.0
 * @param bool $enabled Whether PWA is enabled.
 *
 * ### functionalities_pwa_service_worker_config
 * Filters the service worker configuration.
 *
 * @since 1.1.0
 * @param array $config Service worker config array.
 *
 * ## Actions
 *
 * ### functionalities_pwa_manifest_output
 * Fires after manifest JSON is sent.
 *
 * @since 1.1.0
 *
 * ### functionalities_pwa_sw_output
 * Fires after service worker JS is sent.
 *
 * @since 1.1.0
 */

namespace Functionalities\Features;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PWA {

	const REWRITE_VERSION = '1.0.0';

	private static $options = null;

	/**
	 * Initialize the PWA module.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function init() : void {
		self::register_routes();
		\add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		\add_action( 'template_redirect', array( __CLASS__, 'handle_endpoints' ) );
		\add_action( 'update_option_functionalities_pwa', array( __CLASS__, 'on_option_update' ), 10, 2 );

		if ( ! self::is_enabled() ) {
			return;
		}

		\add_action( 'wp_head', array( __CLASS__, 'output_head_tags' ), 1 );
		\add_action( 'wp_footer', array( __CLASS__, 'output_prompt_css' ), 98 );
		\add_action( 'wp_footer', array( __CLASS__, 'output_install_prompt' ), 99 );
		\add_action( 'wp_footer', array( __CLASS__, 'output_frontend_js' ), 100 );
	}

	/**
	 * Get module options with defaults.
	 *
	 * @since 1.1.0
	 * @return array Options array.
	 */
	public static function get_options() : array {
		if ( null !== self::$options ) {
			return self::$options;
		}

		$defaults = array(
			'enabled'              => false,
			'app_name'             => '',
			'short_name'           => '',
			'description'          => '',
			'start_url'            => '/',
			'scope'                => '/',
			'display'              => 'standalone',
			'orientation'          => 'any',
			'categories'           => '',
			'theme_color'          => '#4f46e5',
			'background_color'     => '#ffffff',
			'icon_512'             => '',
			'icon_192'             => '',
			'maskable_icon_512'    => '',
			'maskable_icon_192'    => '',
			'install_prompt'       => false,
			'prompt_title'         => '',
			'prompt_text'          => '',
			'prompt_button'        => '',
			'prompt_dismiss'       => '',
			'prompt_position'      => 'bottom',
			'prompt_style'         => 'banner',
			'prompt_frequency'     => 14,
			'cache_version'        => 'v1',
			'precache_urls'        => '',
			'display_override'     => false,
			'edge_side_panel'      => false,
			'launch_handler'       => '',
			'share_target_enabled' => false,
			'share_target_action'  => '',
			'share_target_method'  => 'GET',
			'advanced_manifest'    => '',
			'shortcuts'            => array(),
			'screenshots'          => array(),
			'rewrite_version'      => '',
		);

		$opts = (array) \get_option( 'functionalities_pwa', $defaults );
		self::$options = array_merge( $defaults, $opts );

		return self::$options;
	}

	/**
	 * Check if PWA is enabled.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	private static function is_enabled() : bool {
		$opts = self::get_options();

		return (bool) \apply_filters( 'functionalities_pwa_enabled', ! empty( $opts['enabled'] ) );
	}

	// -------------------------------------------------------------------------
	// Routes
	// -------------------------------------------------------------------------

	/**
	 * Register rewrite rules for manifest, service worker, and offline page.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function register_routes() : void {
		\add_rewrite_rule( '^manifest\.webmanifest$', 'index.php?func_pwa_manifest=1', 'top' );
		\add_rewrite_rule( '^functionalities-sw\.js$', 'index.php?func_pwa_sw=1', 'top' );
		\add_rewrite_rule( '^functionalities-offline/?$', 'index.php?func_pwa_offline=1', 'top' );
	}

	/**
	 * Register query variables.
	 *
	 * @since 1.1.0
	 * @param array $vars Existing query vars.
	 * @return array Modified query vars.
	 */
	public static function register_query_vars( array $vars ) : array {
		$vars[] = 'func_pwa_manifest';
		$vars[] = 'func_pwa_sw';
		$vars[] = 'func_pwa_offline';
		$vars[] = 'func_pwa_share';

		return $vars;
	}

	/**
	 * Handle custom endpoints.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function handle_endpoints() : void {
		if ( ! self::is_enabled() ) {
			return;
		}

		if ( \get_query_var( 'func_pwa_manifest' ) ) {
			self::output_manifest();
		}

		if ( \get_query_var( 'func_pwa_sw' ) ) {
			self::output_service_worker();
		}

		if ( \get_query_var( 'func_pwa_offline' ) ) {
			self::output_offline_page();
		}

		if ( \get_query_var( 'func_pwa_share' ) ) {
			self::handle_share_target();
		}
	}

	// -------------------------------------------------------------------------
	// Manifest
	// -------------------------------------------------------------------------

	/**
	 * Output the Web App Manifest as JSON.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function output_manifest() : void {
		$manifest = self::build_manifest();

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		\status_header( 200 );
		\nocache_headers();
		header( 'Content-Type: application/manifest+json; charset=utf-8' );

		echo \wp_json_encode( $manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );

		\do_action( 'functionalities_pwa_manifest_output' );
		exit;
	}

	/**
	 * Build the manifest data array.
	 *
	 * @since 1.1.0
	 * @return array Manifest data.
	 */
	private static function build_manifest() : array {
		$opts      = self::get_options();
		$site_name = \get_bloginfo( 'name' );
		$site_desc = \get_bloginfo( 'description' );

		$manifest = array(
			'name'             => ! empty( $opts['app_name'] ) ? $opts['app_name'] : $site_name,
			'short_name'       => ! empty( $opts['short_name'] ) ? $opts['short_name'] : $site_name,
			'description'      => ! empty( $opts['description'] ) ? $opts['description'] : $site_desc,
			'start_url'        => ! empty( $opts['start_url'] ) ? $opts['start_url'] : \home_url( '/' ),
			'scope'            => ! empty( $opts['scope'] ) ? $opts['scope'] : \home_url( '/' ),
			'display'          => ! empty( $opts['display'] ) ? $opts['display'] : 'standalone',
			'background_color' => ! empty( $opts['background_color'] ) ? $opts['background_color'] : '#ffffff',
			'theme_color'      => ! empty( $opts['theme_color'] ) ? $opts['theme_color'] : '#4f46e5',
			'orientation'      => ! empty( $opts['orientation'] ) ? $opts['orientation'] : 'any',
		);

		$categories = self::split_list( $opts['categories'] ?? '' );
		if ( ! empty( $categories ) ) {
			$manifest['categories'] = $categories;
		}

		$icons = self::get_manifest_icons();
		if ( ! empty( $icons ) ) {
			$manifest['icons'] = $icons;
		}

		$shortcuts = self::get_manifest_shortcuts();
		if ( ! empty( $shortcuts ) ) {
			$manifest['shortcuts'] = $shortcuts;
		}

		$screenshots = self::get_manifest_screenshots();
		if ( ! empty( $screenshots ) ) {
			$manifest['screenshots'] = $screenshots;
		}

		if ( ! empty( $opts['display_override'] ) ) {
			$manifest['display_override'] = array( 'window-controls-overlay', $manifest['display'] );
		}

		if ( ! empty( $opts['edge_side_panel'] ) ) {
			$manifest['edge_side_panel'] = array( 'preferred_width' => 400 );
		}

		$launch = $opts['launch_handler'] ?? '';
		if ( $launch && in_array( $launch, array( 'auto', 'focus-existing', 'navigate-new' ), true ) ) {
			$manifest['launch_handler'] = array( 'client_mode' => $launch );
		}

		if ( ! empty( $opts['share_target_enabled'] ) ) {
			$manifest['share_target'] = self::build_share_target();
		}

		$advanced = trim( (string) ( $opts['advanced_manifest'] ?? '' ) );
		if ( $advanced !== '' ) {
			$decoded = json_decode( $advanced, true );
			if ( is_array( $decoded ) ) {
				$manifest = array_merge( $manifest, $decoded );
			}
		}

		return (array) \apply_filters( 'functionalities_pwa_manifest', $manifest );
	}

	/**
	 * Build the icons array for the manifest.
	 *
	 * @since 1.1.0
	 * @return array Icons array.
	 */
	private static function get_manifest_icons() : array {
		$opts  = self::get_options();
		$icons = array();
		$seen  = array();

		$sizes = array(
			array( 'key' => 'icon_512', 'size' => '512x512', 'purpose' => 'any' ),
			array( 'key' => 'icon_192', 'size' => '192x192', 'purpose' => 'any' ),
			array( 'key' => 'maskable_icon_512', 'size' => '512x512', 'purpose' => 'maskable' ),
			array( 'key' => 'maskable_icon_192', 'size' => '192x192', 'purpose' => 'maskable' ),
		);

		foreach ( $sizes as $def ) {
			$url = self::get_icon_url( $opts[ $def['key'] ] ?? '' );
			if ( empty( $url ) ) {
				continue;
			}

			$dedupe = $def['size'] . '-' . $def['purpose'];
			if ( isset( $seen[ $dedupe ] ) ) {
				continue;
			}
			$seen[ $dedupe ] = true;

			$icons[] = array(
				'src'     => $url,
				'sizes'   => $def['size'],
				'type'    => self::get_icon_mime( $url ),
				'purpose' => $def['purpose'],
			);
		}

		if ( empty( $icons ) ) {
			$site_icon = \get_site_icon_url( 512 );
			if ( $site_icon ) {
				$icons[] = array(
					'src'     => $site_icon,
					'sizes'   => '512x512',
					'type'    => self::get_icon_mime( $site_icon ),
					'purpose' => 'any',
				);
			}
		}

		return $icons;
	}

	/**
	 * Resolve an icon value (attachment ID or URL) to a URL.
	 *
	 * @since 1.1.0
	 * @param mixed $icon Attachment ID or URL.
	 * @return string URL or empty string.
	 */
	private static function get_icon_url( $icon ) : string {
		if ( empty( $icon ) ) {
			return '';
		}

		if ( is_numeric( $icon ) ) {
			$url = \wp_get_attachment_image_url( (int) $icon, 'full' );
			return $url ? $url : '';
		}

		return (string) $icon;
	}

	/**
	 * Get MIME type from file URL extension.
	 *
	 * @since 1.1.0
	 * @param string $url File URL.
	 * @return string MIME type.
	 */
	private static function get_icon_mime( string $url ) : string {
		$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
		$map = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'svg'  => 'image/svg+xml',
			'webp' => 'image/webp',
			'ico'  => 'image/x-icon',
		);

		return $map[ $ext ] ?? 'image/png';
	}

	/**
	 * Build shortcuts array for the manifest.
	 *
	 * @since 1.1.0
	 * @return array Shortcuts array.
	 */
	private static function get_manifest_shortcuts() : array {
		$opts = self::get_options();
		$out  = array();

		if ( empty( $opts['shortcuts'] ) || ! is_array( $opts['shortcuts'] ) ) {
			return $out;
		}

		foreach ( $opts['shortcuts'] as $sc ) {
			$name = trim( (string) ( $sc['name'] ?? '' ) );
			$url  = trim( (string) ( $sc['url'] ?? '' ) );
			if ( $name === '' || $url === '' ) {
				continue;
			}

			$item = array(
				'name' => $name,
				'url'  => $url,
			);

			$desc = trim( (string) ( $sc['description'] ?? '' ) );
			if ( $desc !== '' ) {
				$item['description'] = $desc;
			}

			$icon_url = self::get_icon_url( $sc['icon'] ?? '' );
			if ( $icon_url ) {
				$item['icons'] = array(
					array(
						'src'   => $icon_url,
						'sizes' => '192x192',
						'type'  => self::get_icon_mime( $icon_url ),
					),
				);
			}

			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Build screenshots array for the manifest.
	 *
	 * @since 1.1.0
	 * @return array Screenshots array.
	 */
	private static function get_manifest_screenshots() : array {
		$opts = self::get_options();
		$out  = array();

		if ( empty( $opts['screenshots'] ) || ! is_array( $opts['screenshots'] ) ) {
			return $out;
		}

		foreach ( $opts['screenshots'] as $ss ) {
			$image_url = self::get_icon_url( $ss['image'] ?? '' );
			if ( empty( $image_url ) ) {
				continue;
			}

			$item = array(
				'src'  => $image_url,
				'type' => self::get_icon_mime( $image_url ),
			);

			$sizes = trim( (string) ( $ss['sizes'] ?? '' ) );
			if ( $sizes !== '' ) {
				$item['sizes'] = $sizes;
			}

			$label = trim( (string) ( $ss['label'] ?? '' ) );
			if ( $label !== '' ) {
				$item['label'] = $label;
			}

			$form = trim( (string) ( $ss['form_factor'] ?? '' ) );
			if ( $form !== '' && in_array( $form, array( 'wide', 'narrow' ), true ) ) {
				$item['form_factor'] = $form;
			}

			$out[] = $item;
		}

		return $out;
	}

	/**
	 * Build share target configuration.
	 *
	 * @since 1.1.0
	 * @return array Share target config.
	 */
	private static function build_share_target() : array {
		$opts   = self::get_options();
		$action = trim( (string) ( $opts['share_target_action'] ?? '' ) );

		if ( $action === '' ) {
			$action = \home_url( '/?func_pwa_share=1' );
		}

		$method = strtoupper( trim( (string) ( $opts['share_target_method'] ?? 'GET' ) ) );
		if ( ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
			$method = 'GET';
		}

		$target = array(
			'action' => $action,
			'method' => $method,
			'params' => array(
				'title' => 'title',
				'text'  => 'text',
				'url'   => 'url',
			),
		);

		if ( 'POST' === $method ) {
			$target['enctype'] = 'multipart/form-data';
		}

		return $target;
	}

	// -------------------------------------------------------------------------
	// Service Worker
	// -------------------------------------------------------------------------

	/**
	 * Output the service worker JavaScript.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function output_service_worker() : void {
		$opts = self::get_options();

		$config = array(
			'version'    => ! empty( $opts['cache_version'] ) ? $opts['cache_version'] : 'v1',
			'offline_url' => \home_url( '/functionalities-offline/' ),
			'precache'   => self::split_lines( $opts['precache_urls'] ?? '' ),
		);

		$config = (array) \apply_filters( 'functionalities_pwa_service_worker_config', $config );

		$precache = array_values( array_unique(
			array_merge(
				array( \home_url( '/' ), $config['offline_url'] ),
				$config['precache']
			)
		) );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		\status_header( 200 );
		\nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );

		$ver_json     = \wp_json_encode( $config['version'] );
		$offline_json = \wp_json_encode( $config['offline_url'] );
		$precache_js  = implode( ',', array_map( '\\wp_json_encode', $precache ) );

		echo <<<JS
/* Functionalities PWA Service Worker */
const CACHE_VERSION={$ver_json};
const CORE_CACHE=`func-pwa-core-\${CACHE_VERSION}`;
const RUNTIME_CACHE=`func-pwa-runtime-\${CACHE_VERSION}`;
const IMAGE_CACHE=`func-pwa-images-\${CACHE_VERSION}`;
const OFFLINE_URL={$offline_json};
const PRECACHE_URLS=[{$precache_js}];

self.addEventListener('install',e=>{
e.waitUntil(caches.open(CORE_CACHE).then(c=>c.addAll(PRECACHE_URLS)).then(()=>self.skipWaiting()));
});

self.addEventListener('activate',e=>{
e.waitUntil(Promise.all([
caches.keys().then(keys=>Promise.all(keys.filter(k=>!k.includes(CACHE_VERSION)).map(k=>caches.delete(k)))),
self.clients.claim(),
self.registration.navigationPreload?self.registration.navigationPreload.enable():Promise.resolve()
]));
});

async function cacheFirst(req){
const cache=await caches.open(IMAGE_CACHE);
const cached=await cache.match(req);
if(cached)return cached;
try{const res=await fetch(req);if(res&&res.status===200)cache.put(req,res.clone());return res;}
catch(e){return new Response('',{status:408});}
}

async function networkFirst(req){
const cache=await caches.open(RUNTIME_CACHE);
try{const res=await fetch(req);if(res&&res.status===200)cache.put(req,res.clone());return res;}
catch(e){const cached=await cache.match(req);if(cached)return cached;
if(req.mode==='navigate'){const core=await caches.open(CORE_CACHE);const off=await core.match(OFFLINE_URL);if(off)return off;}
return new Response('Offline',{status:503,headers:{'Content-Type':'text/plain'}});}
}

async function staleRevalidate(req){
const cache=await caches.open(RUNTIME_CACHE);
const cached=await cache.match(req);
const net=fetch(req).then(res=>{if(res&&res.status===200)cache.put(req,res.clone());return res;}).catch(()=>cached);
return cached||net;
}

self.addEventListener('fetch',e=>{
if(e.request.method!=='GET')return;
const dest=e.request.destination;
if(e.request.mode==='navigate'){e.respondWith(networkFirst(e.request));return;}
if(dest==='image'){e.respondWith(cacheFirst(e.request));return;}
if(dest==='style'||dest==='script'||dest==='font'){e.respondWith(staleRevalidate(e.request));return;}
e.respondWith(networkFirst(e.request));
});
JS;

		\do_action( 'functionalities_pwa_sw_output' );
		exit;
	}

	// -------------------------------------------------------------------------
	// Frontend Output
	// -------------------------------------------------------------------------

	/**
	 * Output manifest link and meta tags in wp_head.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function output_head_tags() : void {
		$opts        = self::get_options();
		$theme_color = ! empty( $opts['theme_color'] ) ? $opts['theme_color'] : '#4f46e5';

		echo '<link rel="manifest" href="' . \esc_url( \home_url( '/manifest.webmanifest' ) ) . '">' . "\n";
		echo '<meta name="theme-color" content="' . \esc_attr( $theme_color ) . '">' . "\n";
		echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
		echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";

		$icon_url = self::get_icon_url( $opts['icon_192'] ?? '' );
		if ( ! $icon_url ) {
			$icon_url = \get_site_icon_url( 192 );
		}
		if ( $icon_url ) {
			echo '<link rel="apple-touch-icon" href="' . \esc_url( $icon_url ) . '">' . "\n";
		}
	}

	/**
	 * Output install prompt CSS.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function output_prompt_css() : void {
		$opts = self::get_options();
		if ( empty( $opts['install_prompt'] ) ) {
			return;
		}

		echo '<style id="functionalities-pwa-prompt-css">';
		echo '.func-pwa-prompt{position:fixed;left:0;right:0;z-index:999999;padding:16px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;display:none}';
		echo '.func-pwa-prompt--bottom{bottom:0}';
		echo '.func-pwa-prompt--top{top:0}';
		echo '.func-pwa-prompt--modal{top:50%;left:50%;right:auto;transform:translate(-50%,-50%);max-width:420px;border-radius:14px}';
		echo '.func-pwa-prompt__content{background:#fff;color:#111827;border-radius:14px;padding:16px 20px;box-shadow:0 12px 32px rgba(15,23,42,.18);display:flex;align-items:center;gap:16px;flex-wrap:wrap;border:1px solid rgba(15,23,42,.06)}';
		echo '.func-pwa-prompt--minimal .func-pwa-prompt__content{padding:12px 16px}';
		echo '.func-pwa-prompt__text{flex:1;min-width:200px}';
		echo '.func-pwa-prompt__title{display:block;font-size:16px;font-weight:600;color:#111827;margin-bottom:4px}';
		echo '.func-pwa-prompt--minimal .func-pwa-prompt__title{font-size:14px;margin-bottom:0}';
		echo '.func-pwa-prompt__desc{font-size:14px;color:#6b7280;margin:0}';
		echo '.func-pwa-prompt--minimal .func-pwa-prompt__desc{display:none}';
		echo '.func-pwa-prompt__actions{display:flex;gap:8px}';
		echo '.func-pwa-prompt__btn{border:none;border-radius:8px;padding:10px 18px;font-size:14px;font-weight:600;cursor:pointer;transition:transform .2s,opacity .2s}';
		echo '.func-pwa-prompt__btn:hover{opacity:.92;transform:translateY(-1px)}';
		echo '.func-pwa-prompt__btn--install{background:#4f46e5;color:#fff}';
		echo '.func-pwa-prompt__btn--dismiss{background:#f1f5f9;color:#1f2937}';
		echo '.func-pwa-prompt--minimal .func-pwa-prompt__btn{padding:8px 14px;font-size:13px}';
		echo '.func-pwa-install,[data-func-pwa-install]{display:none}';
		echo '.func-pwa-installable .func-pwa-install,.func-pwa-installable [data-func-pwa-install]{display:inline-block}';
		echo '</style>' . "\n";
	}

	/**
	 * Output install prompt HTML markup.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function output_install_prompt() : void {
		$opts = self::get_options();
		if ( empty( $opts['install_prompt'] ) ) {
			return;
		}

		$position = $opts['prompt_position'] ?? 'bottom';
		$style    = $opts['prompt_style'] ?? 'banner';
		$title    = ! empty( $opts['prompt_title'] ) ? $opts['prompt_title'] : \__( 'Install this app', 'functionalities' );
		$text     = ! empty( $opts['prompt_text'] ) ? $opts['prompt_text'] : \__( 'Get a faster, full-screen experience.', 'functionalities' );
		$button   = ! empty( $opts['prompt_button'] ) ? $opts['prompt_button'] : \__( 'Install', 'functionalities' );
		$dismiss  = ! empty( $opts['prompt_dismiss'] ) ? $opts['prompt_dismiss'] : \__( 'Not now', 'functionalities' );

		?>
		<div id="func-pwa-prompt" class="func-pwa-prompt func-pwa-prompt--<?php echo \esc_attr( $position ); ?> func-pwa-prompt--<?php echo \esc_attr( $style ); ?>">
			<div class="func-pwa-prompt__content">
				<div class="func-pwa-prompt__text">
					<strong class="func-pwa-prompt__title"><?php echo \esc_html( $title ); ?></strong>
					<p class="func-pwa-prompt__desc"><?php echo \esc_html( $text ); ?></p>
				</div>
				<div class="func-pwa-prompt__actions">
					<button type="button" class="func-pwa-prompt__btn func-pwa-prompt__btn--install"><?php echo \esc_html( $button ); ?></button>
					<button type="button" class="func-pwa-prompt__btn func-pwa-prompt__btn--dismiss"><?php echo \esc_html( $dismiss ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Output frontend JavaScript for service worker registration and install prompt.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function output_frontend_js() : void {
		$opts = self::get_options();

		$config = \wp_json_encode( array(
			'sw'        => \home_url( '/functionalities-sw.js' ),
			'scope'     => ! empty( $opts['scope'] ) ? $opts['scope'] : \home_url( '/' ),
			'prompt'    => ! empty( $opts['install_prompt'] ),
			'frequency' => (int) ( $opts['prompt_frequency'] ?? 14 ),
		) );

		echo '<script id="functionalities-pwa-js">';
		echo "(function(){";
		echo "var c={$config},dp,sk='func_pwa_dismiss';";
		echo "function si(on){document.documentElement.classList.toggle('func-pwa-installable',on)}";
		echo "function pi(){if(!dp)return false;dp.prompt();dp.userChoice.then(function(){localStorage.setItem(sk,Date.now().toString());dp=null;si(false)});return true}";
		echo "if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register(c.sw,{scope:c.scope}).catch(function(){})})}";
		echo "window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();dp=e;si(true);";
		echo "if(!c.prompt)return;";
		echo "var last=localStorage.getItem(sk);if(last){var d=(Date.now()-parseInt(last,10))/(864e5);if(d<c.frequency)return}";
		echo "var p=document.getElementById('func-pwa-prompt');if(p)p.style.display='block'});";
		echo "window.addEventListener('appinstalled',function(){si(false);document.documentElement.classList.add('func-pwa-installed')});";
		echo "document.addEventListener('click',function(e){";
		echo "if(e.target.closest('.func-pwa-prompt__btn--install')){if(pi()){var p=document.getElementById('func-pwa-prompt');if(p)p.style.display='none'}return}";
		echo "if(e.target.closest('.func-pwa-install,[data-func-pwa-install]')){e.preventDefault();pi();return}";
		echo "if(e.target.closest('.func-pwa-prompt__btn--dismiss')){var p=document.getElementById('func-pwa-prompt');if(p)p.style.display='none';localStorage.setItem(sk,Date.now().toString())}";
		echo "});";
		echo "window.funcPwaInstall=pi;";
		echo "})();";
		echo '</script>' . "\n";
	}

	// -------------------------------------------------------------------------
	// Offline Page
	// -------------------------------------------------------------------------

	/**
	 * Output a self-contained offline fallback page.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function output_offline_page() : void {
		$opts       = self::get_options();
		$site_name  = \esc_html( \get_bloginfo( 'name' ) );
		$home       = \esc_url( \home_url( '/' ) );
		$theme      = \esc_attr( $opts['theme_color'] ?? '#4f46e5' );
		$cache_ver  = \esc_js( $opts['cache_version'] ?? 'v1' );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		\status_header( 503 );
		\nocache_headers();

		?><!DOCTYPE html>
<html lang="<?php echo \esc_attr( \get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo $site_name; ?> &mdash; <?php \esc_html_e( 'Offline', 'functionalities' ); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#334155;min-height:100vh;display:flex;align-items:center;justify-content:center}
.offline{text-align:center;padding:2rem;max-width:480px;width:100%}
.offline__icon{width:80px;height:80px;margin:0 auto 1.5rem}
.offline__icon svg{width:100%;height:100%}
.offline__icon .wave{animation:pulse 2s ease-in-out infinite;transform-origin:center}
.offline__icon .wave:nth-child(2){animation-delay:.2s}
.offline__icon .wave:nth-child(3){animation-delay:.4s}
@keyframes pulse{0%,100%{opacity:.3}50%{opacity:1}}
h1{font-size:1.5rem;color:#0f172a;margin-bottom:.5rem}
.offline__desc{color:#64748b;margin-bottom:2rem;line-height:1.6}
.offline__actions{display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap;margin-bottom:2rem}
.offline__btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.25rem;border-radius:.5rem;font-size:.875rem;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:transform .15s,box-shadow .15s}
.offline__btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,.1)}
.offline__btn--primary{background:<?php echo $theme; ?>;color:#fff}
.offline__btn--secondary{background:#fff;color:#334155;border:1px solid #e2e8f0}
.offline__cached{text-align:left;background:#fff;border:1px solid #e2e8f0;border-radius:.75rem;padding:1.25rem;margin-top:1.5rem;display:none}
.offline__cached h2{font-size:.875rem;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.75rem}
.offline__cached ul{list-style:none}
.offline__cached li{border-top:1px solid #f1f5f9}
.offline__cached li:first-child{border-top:0}
.offline__cached a{display:block;padding:.625rem 0;color:<?php echo $theme; ?>;text-decoration:none;font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.offline__cached a:hover{text-decoration:underline}
.offline__footer{margin-top:2rem;font-size:.75rem;color:#94a3b8}
</style>
</head>
<body>
<div class="offline">
	<div class="offline__icon">
		<svg viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
			<path class="wave" d="M40 20c-11 0-20.5 4.5-27.5 11.5a3 3 0 004.2 4.3C23 29.5 31 26 40 26s17 3.5 23.3 9.8a3 3 0 004.2-4.3C60.5 24.5 51 20 40 20z" fill="#94a3b8"/>
			<path class="wave" d="M40 34c-7.2 0-13.5 3-18 7.7a3 3 0 004.2 4.3C30 42 34.8 40 40 40s10 2 13.8 6a3 3 0 004.2-4.3C53.5 37 47.2 34 40 34z" fill="#94a3b8"/>
			<path class="wave" d="M40 48c-3.5 0-6.6 1.5-8.8 3.8a3 3 0 004.2 4.3C37 54.5 38.4 54 40 54s3 .5 4.6 2.1a3 3 0 004.2-4.3C46.6 49.5 43.5 48 40 48z" fill="#94a3b8"/>
			<line x1="12" y1="68" x2="68" y2="12" stroke="#ef4444" stroke-width="4" stroke-linecap="round"/>
		</svg>
	</div>

	<h1><?php \esc_html_e( "You're Offline", 'functionalities' ); ?></h1>
	<p class="offline__desc"><?php \esc_html_e( 'Check your internet connection and try again.', 'functionalities' ); ?></p>

	<div class="offline__actions">
		<button type="button" class="offline__btn offline__btn--primary" onclick="location.reload()">
			<?php \esc_html_e( 'Try Again', 'functionalities' ); ?>
		</button>
		<button type="button" class="offline__btn offline__btn--secondary" onclick="history.back()">
			<?php \esc_html_e( 'Go Back', 'functionalities' ); ?>
		</button>
		<a href="<?php echo $home; ?>" class="offline__btn offline__btn--secondary">
			<?php \esc_html_e( 'Homepage', 'functionalities' ); ?>
		</a>
	</div>

	<div class="offline__cached" id="func-pwa-cached">
		<h2><?php \esc_html_e( 'Available Offline', 'functionalities' ); ?></h2>
		<ul id="func-pwa-cached-list"></ul>
	</div>

	<p class="offline__footer"><?php echo $site_name; ?></p>
</div>
<script>
window.addEventListener('online',function(){location.reload()});
(function(){
if(!('caches' in window))return;
var ver='<?php echo $cache_ver; ?>';
var names=['func-pwa-runtime-'+ver,'func-pwa-core-'+ver];
var found=[];
Promise.all(names.map(function(n){return caches.open(n).then(function(c){return c.keys()}).then(function(reqs){
reqs.forEach(function(r){
var u=new URL(r.url);
if(u.pathname!=='/'&&u.pathname!=='/functionalities-offline/'&&!u.pathname.match(/\.\w{2,4}$/)){
found.push({url:r.url,path:u.pathname});
}});
}).catch(function(){})})).then(function(){
if(!found.length)return;
var ul=document.getElementById('func-pwa-cached-list');
var wrap=document.getElementById('func-pwa-cached');
found.slice(0,10).forEach(function(p){
var li=document.createElement('li');
var a=document.createElement('a');
a.href=p.url;a.textContent=decodeURIComponent(p.path.replace(/^\//,'').replace(/\/$/,'').replace(/\//g,' / '));
li.appendChild(a);ul.appendChild(li);
});
if(ul.children.length)wrap.style.display='block';
});
})();
</script>
</body>
</html>
		<?php
		exit;
	}

	// -------------------------------------------------------------------------
	// Share Target
	// -------------------------------------------------------------------------

	/**
	 * Handle incoming share target requests.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function handle_share_target() : void {
		$opts = self::get_options();
		if ( empty( $opts['share_target_enabled'] ) ) {
			\wp_safe_redirect( \home_url( '/' ) );
			exit;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Share target receives data from OS share sheet.
		$title = isset( $_REQUEST['title'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['title'] ) ) : '';
		$text  = isset( $_REQUEST['text'] ) ? \sanitize_text_field( \wp_unslash( $_REQUEST['text'] ) ) : '';
		$url   = isset( $_REQUEST['url'] ) ? \esc_url_raw( \wp_unslash( $_REQUEST['url'] ) ) : '';
		// phpcs:enable

		$query = trim( implode( ' ', array_filter( array( $title, $text, $url ) ) ) );

		if ( $query !== '' ) {
			\wp_safe_redirect( \home_url( '/?s=' . rawurlencode( $query ) ) );
		} else {
			\wp_safe_redirect( \home_url( '/' ) );
		}
		exit;
	}

	// -------------------------------------------------------------------------
	// Rewrite Management
	// -------------------------------------------------------------------------

	/**
	 * Flush rewrite rules when PWA settings are updated.
	 *
	 * @since 1.1.0
	 * @param mixed $old_value Previous option value.
	 * @param mixed $value     New option value.
	 * @return void
	 */
	public static function on_option_update( $old_value, $value ) : void {
		self::$options = null;
		self::flush_rewrites();
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function flush_rewrites() : void {
		self::register_routes();
		\flush_rewrite_rules( false );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Split a comma-separated string into an array.
	 *
	 * @param string $str Input string.
	 * @return array Trimmed, non-empty values.
	 */
	private static function split_list( string $str ) : array {
		if ( $str === '' ) {
			return array();
		}

		return array_values( array_filter( array_map( 'trim', explode( ',', $str ) ) ) );
	}

	/**
	 * Split a newline-separated string into an array.
	 *
	 * @param string $str Input string.
	 * @return array Trimmed, non-empty values.
	 */
	private static function split_lines( string $str ) : array {
		if ( trim( $str ) === '' ) {
			return array();
		}

		$lines = preg_split( '/\r\n|\r|\n/', $str );

		return array_values( array_filter( array_map( 'trim', $lines ) ) );
	}
}
