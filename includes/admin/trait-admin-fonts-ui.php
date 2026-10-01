<?php
/**
 * Admin Fonts UI responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_Fonts_UI {

	public static function field_fonts_items(): void {
		$o           = self::get_fonts_options();
		$items       = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : array();
		$total_items = count( $items );

		// Enqueue media uploader
		\wp_enqueue_media();
		?>
		<style>
			/* Fonts Manager Styles */
			.fc-fonts {
				max-width: 900px;
			}
			.fc-fonts__toolbar {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 16px;
				padding-bottom: 12px;
				border-bottom: 1px solid #e5e7eb;
			}
			.fc-fonts__count {
				font-size: 13px;
				color: #64748b;
			}
			.fc-fonts__add-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 16px;
				background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
				color: #fff;
				border: none;
				border-radius: 8px;
				font-size: 13px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
				box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
			}
			.fc-fonts__add-btn:hover {
				transform: translateY(-1px);
				box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
			}
			.fc-fonts__grid {
				display: grid;
				gap: 16px;
			}
			.fc-font-card {
				background: #fff;
				border: 1px solid #e5e7eb;
				border-radius: 12px;
				overflow: hidden;
				transition: all 0.2s;
			}
			.fc-font-card:hover {
				border-color: #cbd5e1;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
			}
			.fc-font-card.is-editing {
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-font-card__header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 16px 20px;
				background: linear-gradient(to bottom, #f8fafc, #f1f5f9);
				border-bottom: 1px solid #e5e7eb;
			}
			.fc-font-card__title {
				margin: 0;
				font-size: 16px;
				font-weight: 600;
				color: #1e293b;
			}
			.fc-font-card__meta {
				display: flex;
				gap: 8px;
				margin-top: 4px;
			}
			.fc-font-card__badge {
				display: inline-block;
				padding: 2px 8px;
				background: #e0f2fe;
				color: #0369a1;
				border-radius: 4px;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
			}
			.fc-font-card__badge--variable {
				background: #dcfce7;
				color: #15803d;
			}
			.fc-font-card__badge--preload {
				background: #fef3c7;
				color: #b45309;
			}
			.fc-font-card__actions {
				display: flex;
				gap: 8px;
			}
			.fc-font-card__btn {
				padding: 6px 12px;
				border: 1px solid #e5e7eb;
				border-radius: 6px;
				background: #fff;
				color: #475569;
				font-size: 12px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-font-card__btn:hover {
				background: #f8fafc;
				border-color: #cbd5e1;
			}
			.fc-font-card__btn--delete {
				color: #dc2626;
			}
			.fc-font-card__btn--delete:hover {
				background: #fef2f2;
				border-color: #fecaca;
			}
			.fc-font-card__content {
				display: none;
				padding: 20px;
			}
			.fc-font-card.is-editing .fc-font-card__content {
				display: block;
			}
			.fc-font-card.is-editing .fc-font-card__actions .fc-font-card__btn--edit {
				display: none;
			}
			.fc-font-form__row {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 16px;
				margin-bottom: 16px;
			}
			.fc-font-form__group {
				display: flex;
				flex-direction: column;
				gap: 6px;
			}
			.fc-font-form__label {
				font-size: 12px;
				font-weight: 600;
				color: #374151;
			}
			.fc-font-form__input {
				padding: 8px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				font-size: 14px;
				transition: border-color 0.15s;
			}
			.fc-font-form__input:focus {
				outline: none;
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-font-form__input--url {
				font-family: ui-monospace, monospace;
				font-size: 13px;
			}
			.fc-font-form__file-row {
				display: flex;
				gap: 8px;
				align-items: flex-start;
			}
			.fc-font-form__file-row input[type="url"] {
				flex: 1;
			}
			.fc-font-form__upload-btn {
				padding: 8px 14px;
				background: #f8fafc;
				border: 1px solid #d1d5db;
				border-radius: 6px;
				color: #475569;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				white-space: nowrap;
				transition: all 0.15s;
			}
			.fc-font-form__upload-btn:hover {
				background: #f1f5f9;
				border-color: #94a3b8;
			}
			.fc-font-form__checkbox {
				display: flex;
				align-items: center;
				gap: 8px;
				padding: 8px 0;
			}
			.fc-font-form__checkbox input {
				width: 16px;
				height: 16px;
			}
			.fc-font-form__checkbox label {
				font-size: 13px;
				color: #374151;
			}
			.fc-font-form__actions {
				display: flex;
				gap: 8px;
				padding-top: 16px;
				border-top: 1px solid #e5e7eb;
				margin-top: 8px;
			}
			.fc-font-form__btn {
				padding: 8px 16px;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-font-form__btn--done {
				background: #3b82f6;
				color: #fff;
				border: none;
			}
			.fc-font-form__btn--done:hover {
				background: #2563eb;
			}
			.fc-font-form__btn--cancel {
				background: #fff;
				border: 1px solid #d1d5db;
				color: #475569;
			}
			.fc-font-form__btn--cancel:hover {
				background: #f8fafc;
			}
			.fc-fonts__empty {
				text-align: center;
				padding: 48px 24px;
				background: #f8fafc;
				border: 2px dashed #e5e7eb;
				border-radius: 12px;
			}
			.fc-fonts__empty-icon {
				font-size: 48px;
				margin-bottom: 16px;
			}
			.fc-fonts__empty-title {
				font-size: 16px;
				font-weight: 600;
				color: #374151;
				margin: 0 0 8px;
			}
			.fc-fonts__empty-text {
				font-size: 14px;
				color: #64748b;
				margin: 0;
			}
		</style>

		<div class="fc-fonts" id="fc-fonts">
			<div class="fc-fonts__toolbar">
				<span class="fc-fonts__count" id="fc-fonts-count">
					<?php
					/* translators: %d: number of fonts */
					printf( esc_html( _n( '%d font', '%d fonts', $total_items, 'functionalities' ) ), (int) $total_items );
					?>
				</span>
				<button type="button" class="fc-fonts__add-btn" id="fc-add-font">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
					<?php esc_html_e( 'Add Font', 'functionalities' ); ?>
				</button>
			</div>

			<div class="fc-fonts__grid" id="fc-fonts-grid">
				<?php if ( empty( $items ) ) : ?>
				<div class="fc-fonts__empty" id="fc-fonts-empty">
					<div class="fc-fonts__empty-icon">🔤</div>
					<h4 class="fc-fonts__empty-title"><?php esc_html_e( 'No fonts added yet', 'functionalities' ); ?></h4>
					<p class="fc-fonts__empty-text"><?php esc_html_e( 'Click "Add Font" to start adding custom web fonts to your site.', 'functionalities' ); ?></p>
				</div>
				<?php endif; ?>

				<?php
				$i = 0;
				foreach ( $items as $it ) :
					$family        = esc_attr( $it['family'] ?? '' );
					$style         = esc_attr( $it['style'] ?? 'normal' );
					$display       = esc_attr( $it['display'] ?? 'swap' );
					$weight        = esc_attr( $it['weight'] ?? '' );
					$weight_range  = esc_attr( $it['weight_range'] ?? '' );
					$is_variable   = ! empty( $it['is_variable'] );
					$preload       = ! empty( $it['preload'] );
					$woff2         = esc_attr( $it['woff2_url'] ?? '' );
					$woff          = esc_attr( $it['woff_url'] ?? '' );
					$unicode_range = esc_attr( $it['unicode_range'] ?? '' );
					?>
				<div class="fc-font-card" data-index="<?php echo (int) $i; ?>">
					<div class="fc-font-card__header">
						<div>
							<h4 class="fc-font-card__title"><?php echo esc_html( $family ?: __( 'Untitled Font', 'functionalities' ) ); ?></h4>
							<div class="fc-font-card__meta">
								<span class="fc-font-card__badge"><?php echo esc_html( $style ); ?></span>
								<?php if ( $is_variable ) : ?>
								<span class="fc-font-card__badge fc-font-card__badge--variable"><?php esc_html_e( 'Variable', 'functionalities' ); ?></span>
								<?php endif; ?>
								<?php if ( $preload ) : ?>
								<span class="fc-font-card__badge fc-font-card__badge--preload"><?php esc_html_e( 'Preload', 'functionalities' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="fc-font-card__actions">
							<button type="button" class="fc-font-card__btn fc-font-card__btn--edit"><?php esc_html_e( 'Edit', 'functionalities' ); ?></button>
							<button type="button" class="fc-font-card__btn fc-font-card__btn--delete"><?php esc_html_e( 'Delete', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-font-card__content">
						<div class="fc-font-form__row">
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Font Family', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][family]" value="<?php echo \esc_attr( $family ); ?>" placeholder="Inter, Roboto...">
							</div>
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Style', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][style]" value="<?php echo \esc_attr( $style ); ?>" placeholder="normal, italic, oblique -12deg 0deg">
							</div>
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Display', 'functionalities' ); ?></label>
								<select class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][display]">
									<option value="swap" <?php selected( $display, 'swap' ); ?>>swap</option>
									<option value="auto" <?php selected( $display, 'auto' ); ?>>auto</option>
									<option value="block" <?php selected( $display, 'block' ); ?>>block</option>
									<option value="fallback" <?php selected( $display, 'fallback' ); ?>>fallback</option>
									<option value="optional" <?php selected( $display, 'optional' ); ?>>optional</option>
								</select>
							</div>
						</div>
						<div class="fc-font-form__row">
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Static Weight', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][weight]" value="<?php echo \esc_attr( $weight ); ?>" placeholder="400, 700...">
							</div>
							<div class="fc-font-form__group">
								<label class="fc-font-form__label"><?php esc_html_e( 'Variable Weight Range', 'functionalities' ); ?></label>
								<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][<?php echo (int) $i; ?>][weight_range]" value="<?php echo \esc_attr( $weight_range ); ?>" placeholder="100 900">
							</div>
						</div>
						<div class="fc-font-form__row">
							<div class="fc-font-form__checkbox">
								<input type="checkbox" id="fc-var-<?php echo (int) $i; ?>" name="functionalities_fonts[items][<?php echo (int) $i; ?>][is_variable]" value="1" <?php checked( $is_variable ); ?>>
								<label for="fc-var-<?php echo (int) $i; ?>"><?php esc_html_e( 'Variable font', 'functionalities' ); ?></label>
							</div>
							<div class="fc-font-form__checkbox">
								<input type="checkbox" id="fc-pre-<?php echo (int) $i; ?>" name="functionalities_fonts[items][<?php echo (int) $i; ?>][preload]" value="1" <?php checked( $preload ); ?>>
								<label for="fc-pre-<?php echo (int) $i; ?>"><?php esc_html_e( 'Preload this font', 'functionalities' ); ?></label>
							</div>
						</div>
						<div class="fc-font-form__group" style="margin-bottom: 16px;">
							<label class="fc-font-form__label"><?php esc_html_e( 'WOFF2 URL (required)', 'functionalities' ); ?></label>
							<div class="fc-font-form__file-row">
								<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][<?php echo (int) $i; ?>][woff2_url]" value="<?php echo \esc_url( $woff2 ); ?>" placeholder="https://...">
								<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff2"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
							</div>
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'WOFF URL (fallback)', 'functionalities' ); ?></label>
							<div class="fc-font-form__file-row">
								<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][<?php echo (int) $i; ?>][woff_url]" value="<?php echo \esc_url( $woff ); ?>" placeholder="https://...">
								<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
							</div>
						</div>
						<div class="fc-font-form__group" style="margin-top: 16px;">
							<label class="fc-font-form__label"><?php esc_html_e( 'Character Range (unicode-range)', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input fc-unicode-range" name="functionalities_fonts[items][<?php echo (int) $i; ?>][unicode_range]" value="<?php echo \esc_attr( $unicode_range ); ?>" placeholder="U+0000-00FF, U+0131, U+2000-206F" list="fc-unicode-presets">
							<p class="description" style="margin-top:6px;color:#64748b;font-size:12px;">
								<?php esc_html_e( 'Limit which characters trigger this font download. Leave empty to apply to all characters. Comma-separated tokens like U+26, U+0-7F, U+4??.', 'functionalities' ); ?>
							</p>
						</div>
						<div class="fc-font-form__actions">
							<button type="button" class="fc-font-form__btn fc-font-form__btn--done"><?php esc_html_e( 'Done', 'functionalities' ); ?></button>
							<button type="button" class="fc-font-form__btn fc-font-form__btn--cancel"><?php esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						</div>
					</div>
				</div>
					<?php
					++$i;
				endforeach;
				?>
			</div>
		</div>

		<!-- Template for new fonts -->
		<template id="fc-font-template">
			<div class="fc-font-card is-editing" data-index="__INDEX__">
				<div class="fc-font-card__header">
					<div>
						<h4 class="fc-font-card__title"><?php esc_html_e( 'New Font', 'functionalities' ); ?></h4>
						<div class="fc-font-card__meta">
							<span class="fc-font-card__badge">normal</span>
						</div>
					</div>
					<div class="fc-font-card__actions">
						<button type="button" class="fc-font-card__btn fc-font-card__btn--edit"><?php esc_html_e( 'Edit', 'functionalities' ); ?></button>
						<button type="button" class="fc-font-card__btn fc-font-card__btn--delete"><?php esc_html_e( 'Delete', 'functionalities' ); ?></button>
					</div>
				</div>
				<div class="fc-font-card__content">
					<div class="fc-font-form__row">
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Font Family', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][family]" value="" placeholder="Inter, Roboto...">
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Style', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][style]" value="normal" placeholder="normal, italic, oblique -12deg 0deg">
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Display', 'functionalities' ); ?></label>
							<select class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][display]">
								<option value="swap">swap</option>
								<option value="auto">auto</option>
								<option value="block">block</option>
								<option value="fallback">fallback</option>
								<option value="optional">optional</option>
							</select>
						</div>
					</div>
					<div class="fc-font-form__row">
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Static Weight', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][weight]" value="" placeholder="400, 700...">
						</div>
						<div class="fc-font-form__group">
							<label class="fc-font-form__label"><?php esc_html_e( 'Variable Weight Range', 'functionalities' ); ?></label>
							<input type="text" class="fc-font-form__input" name="functionalities_fonts[items][__INDEX__][weight_range]" value="" placeholder="100 900">
						</div>
					</div>
					<div class="fc-font-form__row">
						<div class="fc-font-form__checkbox">
							<input type="checkbox" id="fc-var-__INDEX__" name="functionalities_fonts[items][__INDEX__][is_variable]" value="1">
							<label for="fc-var-__INDEX__"><?php esc_html_e( 'Variable font', 'functionalities' ); ?></label>
						</div>
						<div class="fc-font-form__checkbox">
							<input type="checkbox" id="fc-pre-__INDEX__" name="functionalities_fonts[items][__INDEX__][preload]" value="1">
							<label for="fc-pre-__INDEX__"><?php esc_html_e( 'Preload this font', 'functionalities' ); ?></label>
						</div>
					</div>
					<div class="fc-font-form__group" style="margin-bottom: 16px;">
						<label class="fc-font-form__label"><?php esc_html_e( 'WOFF2 URL (required)', 'functionalities' ); ?></label>
						<div class="fc-font-form__file-row">
							<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][__INDEX__][woff2_url]" value="" placeholder="https://...">
							<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff2"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-font-form__group">
						<label class="fc-font-form__label"><?php esc_html_e( 'WOFF URL (fallback)', 'functionalities' ); ?></label>
						<div class="fc-font-form__file-row">
							<input type="url" class="fc-font-form__input fc-font-form__input--url fc-font-url" name="functionalities_fonts[items][__INDEX__][woff_url]" value="" placeholder="https://...">
							<button type="button" class="fc-font-form__upload-btn fc-upload-font" data-format="woff"><?php esc_html_e( 'Upload', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-font-form__group" style="margin-top: 16px;">
						<label class="fc-font-form__label"><?php esc_html_e( 'Character Range (unicode-range)', 'functionalities' ); ?></label>
						<input type="text" class="fc-font-form__input fc-unicode-range" name="functionalities_fonts[items][__INDEX__][unicode_range]" value="" placeholder="U+0000-00FF, U+0131, U+2000-206F" list="fc-unicode-presets">
						<p class="description" style="margin-top:6px;color:#64748b;font-size:12px;">
							<?php esc_html_e( 'Limit which characters trigger this font download. Leave empty to apply to all characters. Comma-separated tokens like U+26, U+0-7F, U+4??.', 'functionalities' ); ?>
						</p>
					</div>
					<div class="fc-font-form__actions">
						<button type="button" class="fc-font-form__btn fc-font-form__btn--done"><?php esc_html_e( 'Done', 'functionalities' ); ?></button>
						<button type="button" class="fc-font-form__btn fc-font-form__btn--cancel"><?php esc_html_e( 'Cancel', 'functionalities' ); ?></button>
					</div>
				</div>
			</div>
		</template>
		<datalist id="fc-unicode-presets">
			<option value="U+0000-00FF" label="<?php esc_attr_e( 'Basic Latin + Latin-1', 'functionalities' ); ?>">
			<option value="U+0100-024F, U+1E00-1EFF" label="<?php esc_attr_e( 'Latin Extended', 'functionalities' ); ?>">
			<option value="U+0370-03FF" label="<?php esc_attr_e( 'Greek', 'functionalities' ); ?>">
			<option value="U+0400-04FF, U+0500-052F" label="<?php esc_attr_e( 'Cyrillic', 'functionalities' ); ?>">
			<option value="U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+1EA0-1EF9, U+20AB" label="<?php esc_attr_e( 'Vietnamese', 'functionalities' ); ?>">
			<option value="U+2000-206F, U+2070-209F, U+20A0-20CF, U+2100-214F" label="<?php esc_attr_e( 'Punctuation, sub/super, currency, letterlike', 'functionalities' ); ?>">
		</datalist>

		<script>
		(function() {
			var container = document.getElementById('fc-fonts');
			var grid = document.getElementById('fc-fonts-grid');
			var addBtn = document.getElementById('fc-add-font');
			var template = document.getElementById('fc-font-template');
			var emptyState = document.getElementById('fc-fonts-empty');
			var countEl = document.getElementById('fc-fonts-count');
			var nextIndex = <?php echo (int) $i; ?>;
			var totalItems = <?php echo (int) $total_items; ?>;

			if (!container || !grid) return;

			// Update title from input
			function updateCardTitle(card) {
				var familyInput = card.querySelector('input[name*="[family]"]');
				var titleEl = card.querySelector('.fc-font-card__title');
				// Style is a free-text input, not a <select>. Match either to stay forward-compatible.
				var styleField = card.querySelector('input[name*="[style]"], select[name*="[style]"]');
				var varCheck = card.querySelector('input[name*="[is_variable]"]');
				var preCheck = card.querySelector('input[name*="[preload]"]');
				var metaEl = card.querySelector('.fc-font-card__meta');

				if (familyInput && titleEl) {
					titleEl.textContent = familyInput.value || '<?php echo esc_js( __( 'Untitled Font', 'functionalities' ) ); ?>';
				}

				// Update badges
				if (metaEl && styleField) {
					var styleValue = (styleField.value || 'normal').trim() || 'normal';
					// Only render the bare keyword (normal / italic / oblique) in the badge so values
					// like "oblique -12deg 0deg" don't blow up the card header.
					var styleBadge = styleValue.split(/\s+/)[0];
					var badges = '<span class="fc-font-card__badge"></span>';
					var badgeNode = document.createElement('span');
					badgeNode.className = 'fc-font-card__badge';
					badgeNode.textContent = styleBadge;
					metaEl.innerHTML = '';
					metaEl.appendChild(badgeNode);
					if (varCheck && varCheck.checked) {
						var v = document.createElement('span');
						v.className = 'fc-font-card__badge fc-font-card__badge--variable';
						v.textContent = '<?php echo esc_js( __( 'Variable', 'functionalities' ) ); ?>';
						metaEl.appendChild(v);
					}
					if (preCheck && preCheck.checked) {
						var p = document.createElement('span');
						p.className = 'fc-font-card__badge fc-font-card__badge--preload';
						p.textContent = '<?php echo esc_js( __( 'Preload', 'functionalities' ) ); ?>';
						metaEl.appendChild(p);
					}
				}
			}

			// Update count
			function updateCount() {
				var cards = grid.querySelectorAll('.fc-font-card');
				totalItems = cards.length;
				if (countEl) {
					var text = totalItems === 1 ? '<?php echo esc_js( __( '1 font', 'functionalities' ) ); ?>' : totalItems + ' <?php echo esc_js( __( 'fonts', 'functionalities' ) ); ?>';
					countEl.textContent = text;
				}
				// Toggle empty state
				if (emptyState) {
					emptyState.style.display = totalItems === 0 ? 'block' : 'none';
				}
			}

			// Event delegation
			container.addEventListener('click', function(e) {
				var card = e.target.closest('.fc-font-card');

				// Edit button
				if (e.target.closest('.fc-font-card__btn--edit')) {
					e.preventDefault();
					if (card) card.classList.add('is-editing');
					return;
				}

				// Done button
				if (e.target.closest('.fc-font-form__btn--done')) {
					e.preventDefault();
					if (card) {
						updateCardTitle(card);
						card.classList.remove('is-editing');
					}
					return;
				}

				// Cancel button
				if (e.target.closest('.fc-font-form__btn--cancel')) {
					e.preventDefault();
					if (card) {
						var familyInput = card.querySelector('input[name*="[family]"]');
						var woff2Input = card.querySelector('input[name*="[woff2_url]"]');
						// Remove if new and empty
						if (familyInput && !familyInput.value && woff2Input && !woff2Input.value) {
							card.remove();
							updateCount();
						} else {
							card.classList.remove('is-editing');
						}
					}
					return;
				}

				// Delete button
				if (e.target.closest('.fc-font-card__btn--delete')) {
					e.preventDefault();
					if (card && confirm('<?php echo esc_js( __( 'Delete this font?', 'functionalities' ) ); ?>')) {
						// Clear all inputs
						var inputs = card.querySelectorAll('input, select');
						inputs.forEach(function(input) {
							input.value = '';
							input.name = '';
						});
						card.remove();
						updateCount();
					}
					return;
				}

				// Upload button
				if (e.target.closest('.fc-upload-font')) {
					e.preventDefault();
					var btn = e.target.closest('.fc-upload-font');
					var format = btn.dataset.format;
					var urlInput = btn.parentElement.querySelector('.fc-font-url');

					var mediaUploader = wp.media({
						title: '<?php echo esc_js( __( 'Select or Upload Font File', 'functionalities' ) ); ?>',
						button: { text: '<?php echo esc_js( __( 'Use this file', 'functionalities' ) ); ?>' },
						multiple: false
					});

					mediaUploader.on('select', function() {
						var attachment = mediaUploader.state().get('selection').first().toJSON();
						if (urlInput) {
							urlInput.value = attachment.url;
						}
					});

					mediaUploader.open();
					return;
				}
			});

			// Live updates on input
			container.addEventListener('input', function(e) {
				if (e.target.matches('input, select')) {
					var card = e.target.closest('.fc-font-card');
					if (card) updateCardTitle(card);
				}
			});

			container.addEventListener('change', function(e) {
				if (e.target.matches('input[type="checkbox"], select')) {
					var card = e.target.closest('.fc-font-card');
					if (card) updateCardTitle(card);
				}
			});

			// Add new font
			if (addBtn && template) {
				addBtn.addEventListener('click', function() {
					// Hide empty state
					if (emptyState) emptyState.style.display = 'none';

					var html = template.innerHTML.replace(/__INDEX__/g, nextIndex);
					grid.insertAdjacentHTML('beforeend', html);
					nextIndex++;
					updateCount();

					var newCard = grid.lastElementChild;
					if (newCard) {
						newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
						var familyInput = newCard.querySelector('input[name*="[family]"]');
						if (familyInput) familyInput.focus();
					}
				});
			}
		})();
		</script>

		<p class="description" style="margin-top: 16px;">
			<?php esc_html_e( 'Add multiple fonts and upload WOFF2/WOFF files directly. Each font generates a separate @font-face rule. Enable "Preload" for critical fonts to improve performance.', 'functionalities' ); ?>
		</p>
		<?php
	}

	/**
	 * Render typography assignment fields for the Fonts module.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function field_fonts_assignments(): void {
		$o              = self::get_fonts_options();
		$items          = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : array();
		$assign_enabled = ! empty( $o['assign_enabled'] );
		$body_font      = $o['body_font'] ?? '';
		$heading_font   = $o['heading_font'] ?? '';
		$per_heading    = ! empty( $o['per_heading'] );
		$heading_fonts  = isset( $o['heading_fonts'] ) && is_array( $o['heading_fonts'] ) ? $o['heading_fonts'] : array();

		// Collect available font families.
		$families = array();
		foreach ( $items as $item ) {
			$family = trim( (string) ( $item['family'] ?? '' ) );
			if ( $family !== '' && ! empty( $item['woff2_url'] ) ) {
				$families[] = $family;
			}
		}
		$families = array_unique( $families );
		?>
		<fieldset>
			<label>
				<input type="checkbox" name="functionalities_fonts[assign_enabled]" value="1" <?php checked( $assign_enabled ); ?> id="fc-assign-toggle">
				<?php \esc_html_e( 'Assign fonts to body text and headings', 'functionalities' ); ?>
			</label>
			<p class="description"><?php \esc_html_e( 'When enabled, outputs CSS that sets font-family on body and heading elements at highest priority.', 'functionalities' ); ?></p>
		</fieldset>

		<div id="fc-assign-fields" style="margin-top: 16px; <?php echo $assign_enabled ? '' : 'display:none;'; ?>">
			<?php if ( empty( $families ) ) : ?>
				<p class="description" style="color: #d63638;"><?php \esc_html_e( 'Add at least one font family above before assigning fonts.', 'functionalities' ); ?></p>
			<?php else : ?>
			<table class="form-table" role="presentation" style="margin-top: 0;">
				<tr>
					<th scope="row"><label for="fc-body-font"><?php \esc_html_e( 'Body / Content Font', 'functionalities' ); ?></label></th>
					<td>
						<select name="functionalities_fonts[body_font]" id="fc-body-font">
							<option value=""><?php \esc_html_e( '— None —', 'functionalities' ); ?></option>
							<?php foreach ( $families as $f ) : ?>
								<option value="<?php echo \esc_attr( $f ); ?>" <?php selected( $body_font, $f ); ?>><?php echo \esc_html( $f ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php \esc_html_e( 'Applied to body, p, li, td, input, textarea, select, button.', 'functionalities' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="fc-heading-font"><?php \esc_html_e( 'Headings Font', 'functionalities' ); ?></label></th>
					<td>
						<select name="functionalities_fonts[heading_font]" id="fc-heading-font">
							<option value=""><?php \esc_html_e( '— None —', 'functionalities' ); ?></option>
							<?php foreach ( $families as $f ) : ?>
								<option value="<?php echo \esc_attr( $f ); ?>" <?php selected( $heading_font, $f ); ?>><?php echo \esc_html( $f ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php \esc_html_e( 'Applied to h1–h6. Override individual levels below.', 'functionalities' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php \esc_html_e( 'Per-Heading Override', 'functionalities' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="functionalities_fonts[per_heading]" value="1" <?php checked( $per_heading ); ?> id="fc-per-heading-toggle">
							<?php \esc_html_e( 'Set a different font for each heading level', 'functionalities' ); ?>
						</label>
						<div id="fc-per-heading-fields" style="margin-top: 12px; <?php echo $per_heading ? '' : 'display:none;'; ?>">
							<?php
							for ( $level = 1; $level <= 6; $level++ ) :
								$val = $heading_fonts[ 'h' . $level ] ?? '';
								?>
								<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px;">
									<label style="width: 30px; font-weight: 600; font-size: 13px;">H<?php echo (int) $level; ?></label>
									<select name="functionalities_fonts[heading_fonts][h<?php echo (int) $level; ?>]" style="min-width: 200px;">
										<option value=""><?php \esc_html_e( '— Use default heading font —', 'functionalities' ); ?></option>
										<?php foreach ( $families as $f ) : ?>
											<option value="<?php echo \esc_attr( $f ); ?>" <?php selected( $val, $f ); ?>><?php echo \esc_html( $f ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
							<?php endfor; ?>
						</div>
					</td>
				</tr>
			</table>
			<?php endif; ?>
		</div>

		<script>
		(function() {
			var toggle = document.getElementById('fc-assign-toggle');
			var fields = document.getElementById('fc-assign-fields');
			if (toggle && fields) {
				toggle.addEventListener('change', function() {
					fields.style.display = this.checked ? '' : 'none';
				});
			}
			var perToggle = document.getElementById('fc-per-heading-toggle');
			var perFields = document.getElementById('fc-per-heading-fields');
			if (perToggle && perFields) {
				perToggle.addEventListener('change', function() {
					perFields.style.display = this.checked ? '' : 'none';
				});
			}
		})();
		</script>
		<?php
	}
}
