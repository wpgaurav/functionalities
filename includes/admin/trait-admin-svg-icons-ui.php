<?php
/**
 * Admin SVG Icons UI responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_SVG_Icons_UI {

	/**
	 * Render the SVG Icons custom page.
	 *
	 * @param array $module Module data.
	 * @return void
	 */
	public static function render_module_svg_icons( array $module ): void {
		$opts  = self::get_svg_icons_options();
		$icons = isset( $opts['icons'] ) && is_array( $opts['icons'] ) ? $opts['icons'] : array();
		$nonce = \wp_create_nonce( 'functionalities_svg_icons' );

		// Handle enable/disable toggle.
		if ( isset( $_POST['functionalities_svg_icons_toggle'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'functionalities_svg_icons_toggle' ) ) {
			$opts['enabled'] = ! empty( $_POST['enabled'] );
			\update_option( 'functionalities_svg_icons', $opts, false );
			echo '<div class="notice notice-success is-dismissible"><p>' . \esc_html__( 'Settings saved.', 'functionalities' ) . '</p></div>';
		}
		?>
		<div class="wrap functionalities-module func-svg-icons-admin">
			<?php Admin_UI::render_header( $module['title'], $module['description'], 'svg-icons' ); ?>

			<nav class="functionalities-breadcrumb">
				<a href="<?php echo \esc_url( \admin_url( 'admin.php?page=functionalities' ) ); ?>">
					<?php echo \esc_html__( 'Functionalities', 'functionalities' ); ?>
				</a>
				<span class="separator">›</span>
				<span class="current"><?php echo \esc_html( $module['title'] ); ?></span>
			</nav>

			<!-- Enable/Disable Toggle -->
			<form method="post" style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:16px;margin-bottom:20px;">
				<?php \wp_nonce_field( 'functionalities_svg_icons_toggle' ); ?>
				<input type="hidden" name="functionalities_svg_icons_toggle" value="1" />
				<label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
					<input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $opts['enabled'] ) ); ?> onchange="this.form.submit()" />
					<strong><?php echo \esc_html__( 'Enable SVG Icons', 'functionalities' ); ?></strong>
					<span style="color:#646970;font-size:13px;"><?php echo \esc_html__( 'Allow inserting custom SVG icons in the block editor', 'functionalities' ); ?></span>
				</label>
			</form>

			<!-- Documentation -->
			<div class="functionalities-module-docs" style="margin-bottom:20px;padding-top:0;border-top:0;">
				<?php
				$docs = Module_Docs::get( 'svg-icons' );
				if ( ! empty( $docs['features'] ) ) {
					$list = '<ul>';
					foreach ( $docs['features'] as $feature ) {
						$list .= '<li>' . \esc_html( $feature ) . '</li>';
					}
					$list .= '</ul>';
					Admin_UI::render_docs_section( \__( 'What This Module Does', 'functionalities' ), $list, 'info' );
				}
				if ( ! empty( $docs['usage'] ) ) {
					Admin_UI::render_docs_section( \__( 'How to Use', 'functionalities' ), '<p>' . \esc_html( $docs['usage'] ) . '</p>', 'usage' );
				}
				if ( ! empty( $docs['hooks'] ) ) {
					$hooks_html = '<dl class="functionalities-hooks-list">';
					foreach ( $docs['hooks'] as $hook ) {
						$hooks_html .= '<dt><code>' . \esc_html( $hook['name'] ) . '</code></dt>';
						$hooks_html .= '<dd>' . \esc_html( $hook['description'] ) . '</dd>';
					}
					$hooks_html .= '</dl>';
					Admin_UI::render_docs_section( \__( 'Developer Hooks', 'functionalities' ), $hooks_html, 'developer' );
				}
				?>
			</div>

			<!-- Add Icon Form -->
			<div class="func-svg-add-form">
				<h3><?php echo \esc_html__( 'Add New Icon', 'functionalities' ); ?></h3>
				<div class="func-svg-form-row">
					<label for="icon-slug"><?php echo \esc_html__( 'Slug (unique identifier)', 'functionalities' ); ?></label>
					<input type="text" id="icon-slug" class="regular-text" placeholder="my-icon" pattern="[a-z0-9-]+" title="<?php echo \esc_attr__( 'Lowercase letters, numbers, and hyphens only', 'functionalities' ); ?>" />
					<p class="description"><?php echo \esc_html__( 'Lowercase letters, numbers, and hyphens only. This will be used to reference the icon.', 'functionalities' ); ?></p>
				</div>
				<div class="func-svg-form-row">
					<label for="icon-name"><?php echo \esc_html__( 'Display Name', 'functionalities' ); ?></label>
					<input type="text" id="icon-name" class="regular-text" placeholder="My Icon" />
				</div>
				<div class="func-svg-form-row">
					<label for="icon-svg"><?php echo \esc_html__( 'SVG Code', 'functionalities' ); ?></label>
					<textarea id="icon-svg" class="large-text code" rows="6" placeholder="<svg viewBox=&quot;0 0 24 24&quot;>...</svg>"></textarea>
					<p class="description"><?php echo \esc_html__( 'Paste your SVG code. It will be sanitized to remove any potentially harmful content.', 'functionalities' ); ?></p>
				</div>
				<div class="func-svg-form-row">
					<div id="icon-preview" class="func-svg-preview-area" style="display:none;">
						<span><?php echo \esc_html__( 'Preview:', 'functionalities' ); ?></span>
						<div class="func-svg-preview-box" id="preview-box"></div>
					</div>
				</div>
				<div class="func-svg-form-row">
					<button type="button" id="save-icon-btn" class="button button-primary"><?php echo \esc_html__( 'Save Icon', 'functionalities' ); ?></button>
					<span id="save-status" style="margin-left:10px;"></span>
				</div>
			</div>

			<!-- Icons List -->
			<h2><?php echo \esc_html__( 'Your Icons', 'functionalities' ); ?> <span style="color:#646970;font-weight:normal;">(<?php echo count( $icons ); ?>)</span></h2>

			<?php if ( empty( $icons ) ) : ?>
				<div class="func-svg-empty">
					<span class="dashicons dashicons-flag"></span>
					<p><?php echo \esc_html__( 'No icons yet. Add your first icon above.', 'functionalities' ); ?></p>
				</div>
			<?php else : ?>
				<div class="func-svg-icons-grid" id="icons-list">
					<?php foreach ( $icons as $slug => $icon ) : ?>
						<div class="func-svg-icon-card" data-slug="<?php echo \esc_attr( $slug ); ?>">
							<div class="func-svg-icon-preview">
								<?php
								echo \wp_kses( $icon['svg'], self::get_svg_kses_allowed() );
								?>
							</div>
							<div class="func-svg-icon-info">
								<p class="func-svg-icon-name"><?php echo \esc_html( $icon['name'] ); ?></p>
								<p class="func-svg-icon-slug"><?php echo \esc_html( $slug ); ?></p>
							</div>
							<div class="func-svg-icon-actions">
								<button type="button" class="button button-small copy-shortcode" data-slug="<?php echo \esc_attr( $slug ); ?>" title="<?php echo \esc_attr__( 'Copy shortcode', 'functionalities' ); ?>">
									<span class="dashicons dashicons-clipboard"></span>
								</button>
								<button type="button" class="button button-small delete-icon" data-slug="<?php echo \esc_attr( $slug ); ?>" title="<?php echo \esc_attr__( 'Delete', 'functionalities' ); ?>">
									<span class="dashicons dashicons-trash"></span>
								</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<style>
		.func-svg-icons-admin .func-svg-add-form { background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; margin-bottom: 20px; }
		.func-svg-icons-admin .func-svg-add-form h3 { margin-top: 0; margin-bottom: 16px; }
		.func-svg-icons-admin .func-svg-form-row { margin-bottom: 16px; }
		.func-svg-icons-admin .func-svg-form-row:last-child { margin-bottom: 0; }
		.func-svg-icons-admin .func-svg-form-row label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
		.func-svg-icons-admin .func-svg-form-row textarea { min-height: 120px; font-family: monospace; font-size: 12px; }
		.func-svg-icons-admin .func-svg-preview-area { display: flex; align-items: center; gap: 16px; padding: 16px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; }
		.func-svg-icons-admin .func-svg-preview-box { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; background: #f6f7f7; border-radius: 4px; }
		.func-svg-icons-admin .func-svg-preview-box svg { max-width: 32px; max-height: 32px; }
		/* Icons Grid */
		.func-svg-icons-admin .func-svg-icons-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; margin-top: 16px; }
		.func-svg-icons-admin .func-svg-icon-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; padding: 16px; text-align: center; transition: all 0.2s ease; }
		.func-svg-icons-admin .func-svg-icon-card:hover { border-color: #2271b1; box-shadow: 0 2px 8px rgba(0,0,0,0.08); transform: translateY(-2px); }
		.func-svg-icons-admin .func-svg-icon-preview { width: 64px; height: 64px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; background: #f6f7f7; border-radius: 8px; }
		.func-svg-icons-admin .func-svg-icon-preview svg { width: 40px; height: 40px; fill: currentColor; color: #1d2327; }
		.func-svg-icons-admin .func-svg-icon-info { margin-bottom: 12px; }
		.func-svg-icons-admin .func-svg-icon-name { font-weight: 600; font-size: 14px; color: #1d2327; margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.func-svg-icons-admin .func-svg-icon-slug { font-size: 11px; color: #646970; font-family: monospace; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
		.func-svg-icons-admin .func-svg-icon-actions { display: flex; justify-content: center; gap: 8px; }
		.func-svg-icons-admin .func-svg-icon-actions .button .dashicons { font-size: 16px; width: 16px; height: 16px; vertical-align: text-bottom; }
		.func-svg-icons-admin .func-svg-empty { text-align: center; padding: 40px 20px; background: #f6f7f7; border: 1px dashed #c3c4c7; border-radius: 4px; color: #646970; }
		.func-svg-icons-admin .func-svg-empty .dashicons { font-size: 48px; width: 48px; height: 48px; color: #c3c4c7; margin-bottom: 12px; display: block; }
		</style>

		<script>
		jQuery(function($) {
			var ajaxUrl = '<?php echo \esc_js( \admin_url( 'admin-ajax.php' ) ); ?>';
			var nonce = '<?php echo \esc_js( $nonce ); ?>';

			// Preview SVG on input.
			$('#icon-svg').on('input', function() {
				var svg = $(this).val();
				if (svg.indexOf('<svg') !== -1) {
					$('#preview-box').html(svg);
					$('#icon-preview').show();
				} else {
					$('#icon-preview').hide();
				}
			});

			// Save icon.
			$('#save-icon-btn').on('click', function() {
				var $btn = $(this);
				var slug = $('#icon-slug').val().toLowerCase().replace(/[^a-z0-9-]/g, '');
				var name = $('#icon-name').val() || slug;
				var svg = $('#icon-svg').val();

				if (!slug) {
					$('#save-status').text('<?php echo \esc_js( \__( 'Slug is required.', 'functionalities' ) ); ?>').css('color', '#d63638');
					return;
				}
				if (!svg || svg.indexOf('<svg') === -1) {
					$('#save-status').text('<?php echo \esc_js( \__( 'Valid SVG code is required.', 'functionalities' ) ); ?>').css('color', '#d63638');
					return;
				}

				$btn.prop('disabled', true);
				$('#save-status').text('<?php echo \esc_js( \__( 'Saving...', 'functionalities' ) ); ?>').css('color', '#646970');

				$.post(ajaxUrl, {
					action: 'functionalities_svg_icon_save',
					nonce: nonce,
					slug: slug,
					name: name,
					svg: svg
				}, function(response) {
					$btn.prop('disabled', false);
					if (response.success) {
						$('#save-status').text('<?php echo \esc_js( \__( 'Icon saved!', 'functionalities' ) ); ?>').css('color', '#00a32a');
						// Clear form.
						$('#icon-slug').val('');
						$('#icon-name').val('');
						$('#icon-svg').val('');
						$('#icon-preview').hide();
						// Reload page to show new icon.
						setTimeout(function() { location.reload(); }, 500);
					} else {
						$('#save-status').text(response.data?.message || '<?php echo \esc_js( \__( 'Error saving icon.', 'functionalities' ) ); ?>').css('color', '#d63638');
					}
				}).fail(function() {
					$btn.prop('disabled', false);
					$('#save-status').text('<?php echo \esc_js( \__( 'Network error.', 'functionalities' ) ); ?>').css('color', '#d63638');
				});
			});

			// Delete icon.
			$(document).on('click', '.delete-icon', function() {
				var slug = $(this).data('slug');
				if (!confirm('<?php echo \esc_js( \__( 'Delete this icon?', 'functionalities' ) ); ?>')) {
					return;
				}
				$.post(ajaxUrl, {
					action: 'functionalities_svg_icon_delete',
					nonce: nonce,
					slug: slug
				}, function(response) {
					if (response.success) {
						$('.func-svg-icon-card[data-slug="' + slug + '"]').fadeOut(function() { $(this).remove(); });
					} else {
						alert(response.data?.message || '<?php echo \esc_js( \__( 'Error deleting icon.', 'functionalities' ) ); ?>');
					}
				});
			});

			// Copy shortcode.
			$(document).on('click', '.copy-shortcode', function() {
				var slug = $(this).data('slug');
				var shortcode = '[func_icon name="' + slug + '"]';
				var $btn = $(this);
				var $icon = $btn.find('.dashicons');

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(shortcode).then(function() {
						$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
						$btn.css('color', '#00a32a');
						setTimeout(function() {
							$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
							$btn.css('color', '');
						}, 2000);
					});
				} else {
					// Fallback for older browsers.
					var textArea = document.createElement("textarea");
					textArea.value = shortcode;
					document.body.appendChild(textArea);
					textArea.select();
					document.execCommand("copy");
					document.body.removeChild(textArea);
					$icon.removeClass('dashicons-clipboard').addClass('dashicons-yes');
					$btn.css('color', '#00a32a');
					setTimeout(function() {
						$icon.removeClass('dashicons-yes').addClass('dashicons-clipboard');
						$btn.css('color', '');
					}, 2000);
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Get allowed HTML for wp_kses SVG output.
	 *
	 * Mirrors the allowed elements and attributes from SVG_Icons::sanitize_svg()
	 * to ensure output escaping preserves all valid SVG content.
	 *
	 * @since 1.0.1
	 *
	 * @return array Allowed HTML array for wp_kses.
	 */
	private static function get_svg_kses_allowed(): array {
		$common_attrs = array(
			'id'                => true,
			'class'             => true,
			'style'             => true,
			'fill'              => true,
			'fill-opacity'      => true,
			'fill-rule'         => true,
			'stroke'            => true,
			'stroke-width'      => true,
			'stroke-linecap'    => true,
			'stroke-linejoin'   => true,
			'stroke-dasharray'  => true,
			'stroke-dashoffset' => true,
			'stroke-opacity'    => true,
			'opacity'           => true,
			'transform'         => true,
			'clip-path'         => true,
			'clip-rule'         => true,
			'mask'              => true,
		);

		return array(
			'svg'            => array_merge(
				$common_attrs,
				array(
					'xmlns'               => true,
					'xmlns:xlink'         => true,
					'viewbox'             => true,
					'width'               => true,
					'height'              => true,
					'aria-hidden'         => true,
					'role'                => true,
					'focusable'           => true,
					'preserveaspectratio' => true,
					'version'             => true,
					'xml:space'           => true,
					'enable-background'   => true,
				)
			),
			'g'              => $common_attrs,
			'path'           => array_merge( $common_attrs, array( 'd' => true ) ),
			'circle'         => array_merge(
				$common_attrs,
				array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				)
			),
			'ellipse'        => array_merge(
				$common_attrs,
				array(
					'cx' => true,
					'cy' => true,
					'rx' => true,
					'ry' => true,
				)
			),
			'rect'           => array_merge(
				$common_attrs,
				array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
					'ry'     => true,
				)
			),
			'line'           => array_merge(
				$common_attrs,
				array(
					'x1' => true,
					'y1' => true,
					'x2' => true,
					'y2' => true,
				)
			),
			'polyline'       => array_merge( $common_attrs, array( 'points' => true ) ),
			'polygon'        => array_merge( $common_attrs, array( 'points' => true ) ),
			'defs'           => array( 'id' => true ),
			'clippath'       => array( 'id' => true ),
			'mask'           => array( 'id' => true ),
			'use'            => array(
				'xlink:href' => true,
				'href'       => true,
				'x'          => true,
				'y'          => true,
				'width'      => true,
				'height'     => true,
			),
			'symbol'         => array(
				'id'                  => true,
				'viewbox'             => true,
				'preserveaspectratio' => true,
			),
			'title'          => array(),
			'desc'           => array(),
			'lineargradient' => array(
				'id'                => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
				'spreadmethod'      => true,
				'x1'                => true,
				'y1'                => true,
				'x2'                => true,
				'y2'                => true,
			),
			'radialgradient' => array(
				'id'                => true,
				'gradientunits'     => true,
				'gradienttransform' => true,
				'spreadmethod'      => true,
				'cx'                => true,
				'cy'                => true,
				'r'                 => true,
				'fx'                => true,
				'fy'                => true,
			),
			'stop'           => array(
				'offset'       => true,
				'stop-color'   => true,
				'stop-opacity' => true,
				'style'        => true,
			),
		);
	}

	// -------------------------------------------------------------------------
	// PWA Field Renderers
	// -------------------------------------------------------------------------
}
