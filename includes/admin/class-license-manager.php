<?php
/**
 * License Manager for Functionalities.
 *
 * @package Functionalities
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class License_Manager {

	const LICENSE_SERVER    = 'https://gauravtiwari.org/';
	const ITEM_ID          = 1149415;
	const OPTION_KEY       = 'func_lic_license';
	const LAST_CHECK_KEY   = 'func_lic_license_last_check';
	const UPDATE_TRANSIENT = 'func_lic_update_info';

	private static ?self $instance = null;

	public static function init(): void {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->hook();
		}
	}

	public static function instance(): self {
		self::init();
		return self::$instance;
	}

	private function hook(): void {
		\add_action( 'admin_menu', array( $this, 'add_submenu_page' ), 99 );
		\add_action( 'admin_init', array( $this, 'handle_license_actions' ) );
		\add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		\add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		\add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		\add_action( 'delete_site_transient_update_plugins', array( $this, 'clear_update_transient' ) );

		$basename = \plugin_basename( FUNCTIONALITIES_FILE );
		\add_filter( 'plugin_action_links_' . $basename, array( $this, 'plugin_action_links' ) );

		if ( ! \wp_next_scheduled( 'func_lic_verify_license' ) ) {
			\wp_schedule_event( time(), 'weekly', 'func_lic_verify_license' );
		}
		\add_action( 'func_lic_verify_license', array( $this, 'verify_remote_license' ) );
	}

	public function add_submenu_page(): void {
		\add_submenu_page(
			'functionalities',
			\__( 'License', 'functionalities' ),
			\__( 'License', 'functionalities' ),
			'manage_options',
			'func_lic-license',
			array( $this, 'render_license_page' )
		);
	}

	public function handle_license_actions(): void {
		if ( ! isset( $_POST['func_lic_license_action'] ) || ! \current_user_can( 'manage_options' ) ) {
			return;
		}
		\check_admin_referer( 'func_lic_license_nonce', 'func_lic_license_nonce' );

		$action = \sanitize_text_field( \wp_unslash( $_POST['func_lic_license_action'] ) );

		if ( 'activate' === $action ) {
			$key = \sanitize_text_field( trim( \wp_unslash( $_POST['license_key'] ?? '' ) ) );
			if ( empty( $key ) ) {
				\add_settings_error( 'func_lic_license', 'empty_key', \__( 'Please enter a license key.', 'functionalities' ), 'error' );
				return;
			}
			$result = $this->activate_license( $key );
			if ( \is_wp_error( $result ) ) {
				\add_settings_error( 'func_lic_license', 'err', $result->get_error_message(), 'error' );
			} else {
				\add_settings_error( 'func_lic_license', 'ok', \__( 'License activated successfully.', 'functionalities' ), 'success' );
			}
		} elseif ( 'deactivate' === $action ) {
			$result = $this->deactivate_license();
			if ( \is_wp_error( $result ) ) {
				\add_settings_error( 'func_lic_license', 'err', $result->get_error_message(), 'error' );
			} else {
				\add_settings_error( 'func_lic_license', 'ok', \__( 'License deactivated.', 'functionalities' ), 'success' );
			}
		}
	}

	public function activate_license( string $key ) {
		$response = $this->api_request( 'activate_license', array(
			'license_key' => $key, 'item_id' => self::ITEM_ID, 'site_url' => \home_url(),
		) );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}
		if ( empty( $response['success'] ) || 'valid' !== ( $response['status'] ?? '' ) ) {
			return new \WP_Error( 'fail', $response['message'] ?? \__( 'Activation failed.', 'functionalities' ) );
		}

		$data = array(
			'license_key' => $key, 'status' => 'valid',
			'activation_hash' => $response['activation_hash'] ?? '',
			'expiration_date' => $response['expiration_date'] ?? 'lifetime',
			'activated_at' => \current_time( 'mysql' ),
		);
		\update_option( self::OPTION_KEY, $data );
		\update_option( self::LAST_CHECK_KEY, time() );
		\delete_transient( self::UPDATE_TRANSIENT );
		return $data;
	}

	public function deactivate_license() {
		$license = $this->get_license_data();
		if ( empty( $license['license_key'] ) ) {
			return new \WP_Error( 'no_key', \__( 'No license key found.', 'functionalities' ) );
		}
		$this->api_request( 'deactivate_license', array(
			'license_key' => $license['license_key'], 'item_id' => self::ITEM_ID, 'site_url' => \home_url(),
		) );
		$default = array( 'license_key' => '', 'status' => 'inactive', 'activation_hash' => '', 'expiration_date' => '', 'activated_at' => '' );
		\update_option( self::OPTION_KEY, $default );
		\delete_option( self::LAST_CHECK_KEY );
		\delete_transient( self::UPDATE_TRANSIENT );
		return $default;
	}

	public function verify_remote_license(): void {
		$license = $this->get_license_data();
		if ( empty( $license['license_key'] ) || 'valid' !== ( $license['status'] ?? '' ) ) {
			return;
		}
		$params = array( 'item_id' => self::ITEM_ID, 'site_url' => \home_url() );
		$params[ ! empty( $license['activation_hash'] ) ? 'activation_hash' : 'license_key' ] = ! empty( $license['activation_hash'] ) ? $license['activation_hash'] : $license['license_key'];

		$response = $this->api_request( 'check_license', $params );
		if ( \is_wp_error( $response ) ) {
			return;
		}
		if ( 'valid' !== ( $response['status'] ?? 'invalid' ) ) {
			$license['status'] = $response['status'];
			\update_option( self::OPTION_KEY, $license );
		}
		\update_option( self::LAST_CHECK_KEY, time() );
	}

	public function check_for_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		$basename = \plugin_basename( FUNCTIONALITIES_FILE );
		if ( ! empty( $transient->response[ $basename ] ) ) {
			return $transient;
		}

		$license = $this->get_license_data();
		if ( empty( $license['license_key'] ) || 'valid' !== ( $license['status'] ?? '' ) ) {
			return $transient;
		}

		$info = \get_transient( self::UPDATE_TRANSIENT );
		if ( false === $info ) {
			$params = array( 'item_id' => self::ITEM_ID, 'site_url' => \home_url() );
			$params[ ! empty( $license['activation_hash'] ) ? 'activation_hash' : 'license_key' ] = ! empty( $license['activation_hash'] ) ? $license['activation_hash'] : $license['license_key'];
			$info = $this->api_request( 'get_license_version', $params );
			if ( ! \is_wp_error( $info ) ) {
				\set_transient( self::UPDATE_TRANSIENT, $info, 12 * HOUR_IN_SECONDS );
			}
		}

		if ( \is_wp_error( $info ) || empty( $info['new_version'] ) ) {
			return $transient;
		}

		$basename = \plugin_basename( FUNCTIONALITIES_FILE );
		if ( version_compare( $info['new_version'], FUNCTIONALITIES_VERSION, '>' ) ) {
			$transient->response[ $basename ] = (object) array(
				'id' => $basename, 'slug' => 'functionalities', 'plugin' => $basename,
				'new_version' => $info['new_version'], 'url' => $info['url'] ?? 'https://functionalities.dev',
				'package' => $info['package'] ?? '', 'icons' => $info['icons'] ?? array(),
				'banners' => $info['banners'] ?? array(), 'requires_php' => $info['requires_php'] ?? '7.4',
			);
		}
		return $transient;
	}

	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || 'functionalities' !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$info = \get_transient( self::UPDATE_TRANSIENT );
		if ( empty( $info ) || \is_wp_error( $info ) ) {
			return $result;
		}
		return (object) array(
			'name' => $info['name'] ?? 'Functionalities', 'slug' => 'functionalities',
			'version' => $info['new_version'] ?? '', 'author' => '<a href="https://gauravtiwari.org">Gaurav Tiwari</a>',
			'homepage' => $info['homepage'] ?? 'https://functionalities.dev',
			'download_link' => $info['package'] ?? '', 'trunk' => $info['trunk'] ?? '',
			'last_updated' => $info['last_updated'] ?? '', 'sections' => $info['sections'] ?? array(),
			'banners' => $info['banners'] ?? array(), 'icons' => $info['icons'] ?? array(),
			'requires' => $info['requires'] ?? '5.8', 'requires_php' => $info['requires_php'] ?? '7.4',
		);
	}

	public function clear_update_transient(): void {
		\delete_transient( self::UPDATE_TRANSIENT );
	}

	public function plugin_action_links( array $links ): array {
		\array_unshift( $links, \sprintf( '<a href="%s">%s</a>',
			\admin_url( 'admin.php?page=func_lic-license' ), \__( 'License', 'functionalities' ) ) );
		return $links;
	}

	public function admin_notices(): void {
		$screen = \get_current_screen();
		if ( ! $screen || 'functionalities_page_func_lic-license' === $screen->id ) {
			return;
		}
		$license = $this->get_license_data();
		if ( 'expired' === ( $license['status'] ?? '' ) && false !== strpos( $screen->id ?? '', 'functionalities' ) ) {
			\printf( '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				\esc_html__( 'Your Functionalities license has expired.', 'functionalities' ),
				\esc_url( \admin_url( 'admin.php?page=func_lic-license' ) ),
				\esc_html__( 'Manage License', 'functionalities' ) );
		}
	}

	public function render_license_page(): void {
		$license = $this->get_license_data();
		$status  = $license['status'] ?? 'inactive';
		$key     = $license['license_key'] ?? '';
		$expires = $license['expiration_date'] ?? '';

		\settings_errors( 'func_lic_license' );
		?>
		<div class="wrap">
			<h1><?php \esc_html_e( 'Functionalities License', 'functionalities' ); ?></h1>
			<div class="card" style="max-width: 600px; margin-top: 20px;">
				<h2 style="margin-top: 0;"><?php \esc_html_e( 'License Status', 'functionalities' ); ?></h2>
				<?php if ( 'valid' === $status ) : ?>
					<div style="background:#d4edda;border:1px solid #c3e6cb;padding:12px 16px;border-radius:4px;margin-bottom:16px;">
						<strong style="color:#155724;">&#10003; <?php \esc_html_e( 'License Active', 'functionalities' ); ?></strong>
						<?php if ( $expires && 'lifetime' !== $expires ) : ?>
							<?php /* translators: %s: license expiration date */ ?>
						<br><small><?php \printf( \esc_html__( 'Expires: %s', 'functionalities' ), \esc_html( $expires ) ); ?></small>
						<?php elseif ( 'lifetime' === $expires ) : ?>
							<br><small><?php \esc_html_e( 'Lifetime license', 'functionalities' ); ?></small>
						<?php endif; ?>
					</div>
					<form method="post">
						<?php \wp_nonce_field( 'func_lic_license_nonce', 'func_lic_license_nonce' ); ?>
						<input type="hidden" name="func_lic_license_action" value="deactivate">
						<p><code style="font-size:14px;padding:4px 8px;"><?php echo \esc_html( $this->mask_key( $key ) ); ?></code></p>
						<p><input type="submit" class="button" value="<?php \esc_attr_e( 'Deactivate License', 'functionalities' ); ?>"></p>
					</form>
				<?php elseif ( 'expired' === $status ) : ?>
					<div style="background:#fff3cd;border:1px solid #ffc107;padding:12px 16px;border-radius:4px;margin-bottom:16px;">
						<strong style="color:#856404;">&#9888; <?php \esc_html_e( 'License Expired', 'functionalities' ); ?></strong>
					</div>
					<p><a href="https://gauravtiwari.org/product/functionalities/" class="button button-primary" target="_blank"><?php \esc_html_e( 'Renew', 'functionalities' ); ?></a></p>
					<hr>
					<form method="post">
						<?php \wp_nonce_field( 'func_lic_license_nonce', 'func_lic_license_nonce' ); ?>
						<input type="hidden" name="func_lic_license_action" value="activate">
						<p><label for="license_key"><strong><?php \esc_html_e( 'New license key:', 'functionalities' ); ?></strong></label><br>
						<input type="text" id="license_key" name="license_key" class="regular-text" style="margin-top:4px;"></p>
						<p><input type="submit" class="button button-primary" value="<?php \esc_attr_e( 'Activate', 'functionalities' ); ?>"></p>
					</form>
				<?php else : ?>
					<p><?php \esc_html_e( 'Enter your license key to enable automatic updates.', 'functionalities' ); ?></p>
					<form method="post">
						<?php \wp_nonce_field( 'func_lic_license_nonce', 'func_lic_license_nonce' ); ?>
						<input type="hidden" name="func_lic_license_action" value="activate">
						<p><label for="license_key"><strong><?php \esc_html_e( 'License Key', 'functionalities' ); ?></strong></label><br>
						<input type="text" id="license_key" name="license_key" class="regular-text" style="margin-top:4px;"></p>
						<p><input type="submit" class="button button-primary" value="<?php \esc_attr_e( 'Activate', 'functionalities' ); ?>"></p>
					</form>
					<hr>
					<?php /* translators: %1$s: opening link tag, %2$s: closing link tag */ ?>
				<p><small><?php \printf( \esc_html__( 'Need a license? %1$sGet one here%2$s.', 'functionalities' ), '<a href="https://gauravtiwari.org/product/functionalities/" target="_blank">', '</a>' ); ?></small></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public function get_license_data(): array {
		$defaults = array( 'license_key' => '', 'status' => 'inactive', 'activation_hash' => '', 'expiration_date' => '', 'activated_at' => '' );
		$data = \get_option( self::OPTION_KEY, array() );
		return is_array( $data ) ? \wp_parse_args( $data, $defaults ) : $defaults;
	}

	public function is_valid(): bool {
		return 'valid' === ( $this->get_license_data()['status'] ?? '' );
	}

	private function api_request( string $action, array $params = array() ) {
		$url = \add_query_arg( 'fluent-cart', $action, self::LICENSE_SERVER );
		$params['current_version'] = defined( 'FUNCTIONALITIES_VERSION' ) ? FUNCTIONALITIES_VERSION : '1.0.0';
		$response = \wp_remote_post( $url, array( 'timeout' => 15, 'sslverify' => false, 'body' => $params ) );
		if ( \is_wp_error( $response ) ) {
			return new \WP_Error( 'api_error', \__( 'Could not connect to the license server.', 'functionalities' ) );
		}
		$body = json_decode( \wp_remote_retrieve_body( $response ), true );
		if ( \wp_remote_retrieve_response_code( $response ) >= 400 || empty( $body ) ) {
			return new \WP_Error( 'api_error', $body['message'] ?? \__( 'License server error.', 'functionalities' ) );
		}
		return $body;
	}

	private function mask_key( string $key ): string {
		return strlen( $key ) <= 8 ? $key : substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
	}
}
