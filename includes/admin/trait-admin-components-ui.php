<?php
/**
 * Admin Components UI responsibilities.
 *
 * @package Functionalities\Admin
 */

namespace Functionalities\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserve the Module_Controller public API while grouping related behavior. */
trait Admin_Components_UI {

	public static function field_components_items(): void {
		$o           = self::get_components_options();
		$items       = isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : array();
		$per_page    = 24;
		$total_items = count( $items );
		$total_pages = (int) max( 1, ceil( $total_items / $per_page ) );
		?>
		<style>
			/* Components Grid Container */
			.fc-components {
				margin-bottom: 20px;
			}
			.fc-components__toolbar {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 16px;
				padding: 12px 16px;
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
			}
			.fc-components__count {
				font-size: 14px;
				color: #64748b;
			}
			.fc-components__add-btn {
				display: inline-flex;
				align-items: center;
				gap: 6px;
				padding: 8px 16px;
				background: #3b82f6;
				color: #fff;
				border: none;
				border-radius: 6px;
				font-size: 14px;
				font-weight: 500;
				cursor: pointer;
				transition: background 0.15s;
			}
			.fc-components__add-btn:hover {
				background: #2563eb;
			}

			/* Grid Layout */
			.fc-components__grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
				gap: 16px;
				margin-bottom: 20px;
			}

			/* Component Card */
			.fc-card {
				position: relative;
				background: #fff;
				border: 1px solid #e2e8f0;
				border-radius: 12px;
				overflow: hidden;
				transition: box-shadow 0.2s, border-color 0.2s;
			}
			.fc-card:hover {
				border-color: #3b82f6;
				box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
			}
			.fc-card.is-editing {
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-card--new {
				border-style: dashed;
				border-color: #94a3b8;
				background: #f8fafc;
			}
			.fc-card--new:hover {
				border-color: #3b82f6;
				background: #eff6ff;
			}

			/* Card Preview */
			.fc-card__preview {
				height: 80px;
				padding: 12px;
				background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
				display: flex;
				align-items: center;
				justify-content: center;
				border-bottom: 1px solid #e2e8f0;
				overflow: hidden;
			}
			.fc-card__preview-box {
				max-width: 100%;
				max-height: 100%;
				font-size: 14px;
				text-align: center;
				word-break: break-word;
			}

			/* Card Content */
			.fc-card__content {
				padding: 12px 14px;
			}
			.fc-card__header {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				margin-bottom: 4px;
			}
			.fc-card__name {
				font-weight: 600;
				font-size: 14px;
				color: #1e293b;
				margin: 0;
				line-height: 1.3;
			}
			.fc-card__selector {
				font-family: ui-monospace, monospace;
				font-size: 12px;
				color: #64748b;
				background: #f1f5f9;
				padding: 2px 6px;
				border-radius: 4px;
			}
			.fc-card__actions {
				display: flex;
				gap: 4px;
				margin-top: 10px;
				padding-top: 10px;
				border-top: 1px solid #f1f5f9;
			}
			.fc-card__btn {
				flex: 1;
				padding: 6px 10px;
				font-size: 12px;
				font-weight: 500;
				border: none;
				border-radius: 4px;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-card__btn--edit {
				background: #eff6ff;
				color: #3b82f6;
			}
			.fc-card__btn--edit:hover {
				background: #dbeafe;
			}
			.fc-card__btn--delete {
				background: #fef2f2;
				color: #dc2626;
			}
			.fc-card__btn--delete:hover {
				background: #fee2e2;
			}

			/* Edit Form (inline) */
			.fc-card__form {
				display: none;
				padding: 14px;
				background: #fafafa;
				border-top: 1px solid #e2e8f0;
			}
			.fc-card.is-editing .fc-card__form {
				display: block;
			}
			.fc-card.is-editing .fc-card__actions {
				display: none;
			}
			.fc-form__group {
				margin-bottom: 12px;
			}
			.fc-form__group:last-child {
				margin-bottom: 0;
			}
			.fc-form__label {
				display: block;
				font-size: 12px;
				font-weight: 600;
				color: #475569;
				margin-bottom: 4px;
			}
			.fc-form__input {
				width: 100%;
				padding: 8px 10px;
				font-size: 13px;
				border: 1px solid #e2e8f0;
				border-radius: 6px;
				transition: border-color 0.15s, box-shadow 0.15s;
			}
			.fc-form__input:focus {
				outline: none;
				border-color: #3b82f6;
				box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
			}
			.fc-form__textarea {
				min-height: 80px;
				font-family: ui-monospace, monospace;
				font-size: 12px;
				resize: vertical;
			}
			.fc-form__actions {
				display: flex;
				gap: 8px;
				margin-top: 12px;
			}
			.fc-form__btn {
				padding: 8px 14px;
				font-size: 13px;
				font-weight: 500;
				border: none;
				border-radius: 6px;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-form__btn--save {
				background: #3b82f6;
				color: #fff;
			}
			.fc-form__btn--save:hover {
				background: #2563eb;
			}
			.fc-form__btn--cancel {
				background: #f1f5f9;
				color: #64748b;
			}
			.fc-form__btn--cancel:hover {
				background: #e2e8f0;
			}

			/* Pagination */
			.fc-pagination {
				display: flex;
				justify-content: center;
				align-items: center;
				gap: 8px;
				padding: 16px;
				background: #f8fafc;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
			}
			.fc-pagination__btn {
				display: inline-flex;
				align-items: center;
				justify-content: center;
				min-width: 36px;
				height: 36px;
				padding: 0 12px;
				font-size: 14px;
				font-weight: 500;
				color: #475569;
				background: #fff;
				border: 1px solid #e2e8f0;
				border-radius: 6px;
				cursor: pointer;
				transition: all 0.15s;
			}
			.fc-pagination__btn:hover:not(:disabled) {
				background: #f1f5f9;
				border-color: #cbd5e1;
			}
			.fc-pagination__btn:disabled {
				opacity: 0.5;
				cursor: not-allowed;
			}
			.fc-pagination__btn.is-active {
				background: #3b82f6;
				border-color: #3b82f6;
				color: #fff;
			}
			.fc-pagination__info {
				font-size: 14px;
				color: #64748b;
				margin: 0 8px;
			}

			/* New Card Special */
			.fc-card--new .fc-card__preview {
				background: transparent;
				border-bottom: none;
			}
			.fc-card--new .fc-card__preview-box {
				color: #64748b;
			}
			.fc-card--new .fc-card__content {
				text-align: center;
				padding: 20px;
			}
			.fc-card--new .fc-card__name {
				color: #64748b;
				font-weight: 500;
			}

			/* Hidden items for pagination */
			.fc-card[data-page]:not(.is-visible) {
				display: none;
			}
		</style>

		<div class="fc-components" id="fc-components">
			<div class="fc-components__toolbar">
				<span class="fc-components__count">
					<?php
					/* translators: %d: Number of components. */
					printf( \esc_html__( '%d components', 'functionalities' ), (int) $total_items );
					?>
				</span>
				<button type="button" class="fc-components__add-btn" id="fc-add-new">
					<span class="dashicons dashicons-plus-alt2" style="font-size:16px;width:16px;height:16px;"></span>
					<?php \esc_html_e( 'Add Component', 'functionalities' ); ?>
				</button>
			</div>

			<div class="fc-components__grid" id="fc-grid">
				<?php
				$i = 0;
				foreach ( $items as $item ) :
					$name       = $item['name'] ?? '';
					$class      = $item['class'] ?? '';
					$css        = $item['css'] ?? '';
					$page       = (int) floor( $i / $per_page ) + 1;
					$is_visible = ( 1 === $page ) ? 'is-visible' : '';
					?>
				<div class="fc-card <?php echo \esc_attr( $is_visible ); ?>" data-index="<?php echo (int) $i; ?>" data-page="<?php echo (int) $page; ?>">
					<div class="fc-card__preview">
						<div class="fc-card__preview-box" style="<?php echo \esc_attr( $css ); ?>">
							<?php echo \esc_html( $name ?: 'Preview' ); ?>
						</div>
					</div>
					<div class="fc-card__content">
						<div class="fc-card__header">
							<h4 class="fc-card__name"><?php echo \esc_html( $name ?: \__( 'Untitled', 'functionalities' ) ); ?></h4>
						</div>
						<code class="fc-card__selector"><?php echo \esc_html( $class ?: '.selector' ); ?></code>
						<div class="fc-card__actions">
							<button type="button" class="fc-card__btn fc-card__btn--edit"><?php \esc_html_e( 'Edit', 'functionalities' ); ?></button>
							<button type="button" class="fc-card__btn fc-card__btn--delete"><?php \esc_html_e( 'Delete', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-card__form">
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'Name', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][<?php echo (int) $i; ?>][name]" value="<?php echo \esc_attr( $name ); ?>" placeholder="<?php \esc_attr_e( 'Component Name', 'functionalities' ); ?>">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Selector', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][<?php echo (int) $i; ?>][class]" value="<?php echo \esc_attr( $class ); ?>" placeholder=".my-component">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Rules', 'functionalities' ); ?></label>
							<textarea class="fc-form__input fc-form__textarea" name="functionalities_components[items][<?php echo (int) $i; ?>][css]" placeholder="background: #fff; padding: 1rem;"><?php echo \esc_textarea( $css ); ?></textarea>
						</div>
						<div class="fc-form__actions">
							<button type="button" class="fc-form__btn fc-form__btn--save"><?php \esc_html_e( 'Done', 'functionalities' ); ?></button>
							<button type="button" class="fc-form__btn fc-form__btn--cancel"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						</div>
					</div>
				</div>
					<?php
					++$i;
				endforeach;
				?>
			</div>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="fc-pagination" id="fc-pagination">
				<button type="button" class="fc-pagination__btn" data-action="prev" <?php echo ( 1 === 1 ) ? 'disabled' : ''; ?>>
					&larr; <?php \esc_html_e( 'Prev', 'functionalities' ); ?>
				</button>
				<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
				<button type="button" class="fc-pagination__btn <?php echo ( $p === 1 ) ? 'is-active' : ''; ?>" data-page="<?php echo (int) $p; ?>">
					<?php echo (int) $p; ?>
				</button>
				<?php endfor; ?>
				<button type="button" class="fc-pagination__btn" data-action="next" <?php echo ( 1 === $total_pages ) ? 'disabled' : ''; ?>>
					<?php \esc_html_e( 'Next', 'functionalities' ); ?> &rarr;
				</button>
				<span class="fc-pagination__info">
					<?php
					/* translators: 1: current page number, 2: total pages */
					printf( \esc_html__( 'Page %1$d of %2$d', 'functionalities' ), 1, esc_html( $total_pages ) );
					?>
				</span>
			</div>
			<?php endif; ?>

			<!-- Hidden template for new components -->
			<template id="fc-card-template">
				<div class="fc-card is-editing is-visible" data-index="__INDEX__" data-page="__PAGE__">
					<div class="fc-card__preview">
						<div class="fc-card__preview-box">New Component</div>
					</div>
					<div class="fc-card__content">
						<div class="fc-card__header">
							<h4 class="fc-card__name"><?php \esc_html_e( 'New Component', 'functionalities' ); ?></h4>
						</div>
						<code class="fc-card__selector">.new-component</code>
						<div class="fc-card__actions">
							<button type="button" class="fc-card__btn fc-card__btn--edit"><?php \esc_html_e( 'Edit', 'functionalities' ); ?></button>
							<button type="button" class="fc-card__btn fc-card__btn--delete"><?php \esc_html_e( 'Delete', 'functionalities' ); ?></button>
						</div>
					</div>
					<div class="fc-card__form">
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'Name', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][__INDEX__][name]" value="" placeholder="<?php \esc_attr_e( 'Component Name', 'functionalities' ); ?>">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Selector', 'functionalities' ); ?></label>
							<input type="text" class="fc-form__input" name="functionalities_components[items][__INDEX__][class]" value="" placeholder=".my-component">
						</div>
						<div class="fc-form__group">
							<label class="fc-form__label"><?php \esc_html_e( 'CSS Rules', 'functionalities' ); ?></label>
							<textarea class="fc-form__input fc-form__textarea" name="functionalities_components[items][__INDEX__][css]" placeholder="background: #fff; padding: 1rem;"></textarea>
						</div>
						<div class="fc-form__actions">
							<button type="button" class="fc-form__btn fc-form__btn--save"><?php \esc_html_e( 'Done', 'functionalities' ); ?></button>
							<button type="button" class="fc-form__btn fc-form__btn--cancel"><?php \esc_html_e( 'Cancel', 'functionalities' ); ?></button>
						</div>
					</div>
				</div>
			</template>
		</div>

		<script>
		(function() {
			var container = document.getElementById('fc-components');
			var grid = document.getElementById('fc-grid');
			var pagination = document.getElementById('fc-pagination');
			var addBtn = document.getElementById('fc-add-new');
			var template = document.getElementById('fc-card-template');
			var currentPage = 1;
			var perPage = <?php echo (int) $per_page; ?>;
			var totalItems = <?php echo (int) $total_items; ?>;
			var nextIndex = <?php echo (int) $i; ?>;

			if (!container || !grid) return;

			// Update preview when CSS changes
			function updatePreview(card) {
				var cssInput = card.querySelector('textarea[name*="[css]"]');
				var nameInput = card.querySelector('input[name*="[name]"]');
				var previewBox = card.querySelector('.fc-card__preview-box');
				var nameDisplay = card.querySelector('.fc-card__name');
				var selectorDisplay = card.querySelector('.fc-card__selector');
				var selectorInput = card.querySelector('input[name*="[class]"]');

				if (cssInput && previewBox) {
					previewBox.style.cssText = cssInput.value;
				}
				if (nameInput && previewBox) {
					previewBox.textContent = nameInput.value || 'Preview';
				}
				if (nameInput && nameDisplay) {
					nameDisplay.textContent = nameInput.value || '<?php echo \esc_js( \__( 'Untitled', 'functionalities' ) ); ?>';
				}
				if (selectorInput && selectorDisplay) {
					selectorDisplay.textContent = selectorInput.value || '.selector';
				}
			}

			// Show page
			function showPage(page) {
				var cards = grid.querySelectorAll('.fc-card[data-page]');
				cards.forEach(function(card) {
					if (parseInt(card.dataset.page) === page) {
						card.classList.add('is-visible');
					} else {
						card.classList.remove('is-visible');
						card.classList.remove('is-editing');
					}
				});

				currentPage = page;
				updatePaginationUI();
			}

			// Update pagination buttons
			function updatePaginationUI() {
				if (!pagination) return;

				var totalPages = Math.max(1, Math.ceil(totalItems / perPage));
				var prevBtn = pagination.querySelector('[data-action="prev"]');
				var nextBtn = pagination.querySelector('[data-action="next"]');
				var pageInfo = pagination.querySelector('.fc-pagination__info');
				var pageBtns = pagination.querySelectorAll('[data-page]');

				if (prevBtn) prevBtn.disabled = (currentPage <= 1);
				if (nextBtn) nextBtn.disabled = (currentPage >= totalPages);
				if (pageInfo) pageInfo.textContent = '<?php echo \esc_js( \__( 'Page', 'functionalities' ) ); ?> ' + currentPage + ' <?php echo \esc_js( \__( 'of', 'functionalities' ) ); ?> ' + totalPages;

				pageBtns.forEach(function(btn) {
					btn.classList.toggle('is-active', parseInt(btn.dataset.page) === currentPage);
				});
			}

			// Update component count
			function updateCount() {
				var countEl = container.querySelector('.fc-components__count');
				if (countEl) {
					countEl.textContent = totalItems + ' <?php echo \esc_js( \__( 'components', 'functionalities' ) ); ?>';
				}
			}

			// Event delegation
			container.addEventListener('click', function(e) {
				var card = e.target.closest('.fc-card');

				// Edit button
				if (e.target.closest('.fc-card__btn--edit')) {
					e.preventDefault();
					if (card) card.classList.add('is-editing');
					return;
				}

				// Done/Save button
				if (e.target.closest('.fc-form__btn--save')) {
					e.preventDefault();
					if (card) {
						updatePreview(card);
						card.classList.remove('is-editing');
					}
					return;
				}

				// Cancel button
				if (e.target.closest('.fc-form__btn--cancel')) {
					e.preventDefault();
					if (card) {
						// If it's a new unsaved card with empty values, remove it
						var nameInput = card.querySelector('input[name*="[name]"]');
						var classInput = card.querySelector('input[name*="[class]"]');
						if (nameInput && !nameInput.value && classInput && !classInput.value) {
							card.remove();
							totalItems--;
							updateCount();
						} else {
							card.classList.remove('is-editing');
						}
					}
					return;
				}

				// Delete button
				if (e.target.closest('.fc-card__btn--delete')) {
					e.preventDefault();
					if (card && confirm('<?php echo \esc_js( \__( 'Delete this component?', 'functionalities' ) ); ?>')) {
						// Clear values and hide
						var inputs = card.querySelectorAll('input, textarea');
						inputs.forEach(function(input) {
							input.value = '';
							input.name = '';
						});
						card.remove();
						totalItems--;
						updateCount();
					}
					return;
				}

				// Pagination
				if (e.target.closest('.fc-pagination__btn')) {
					var btn = e.target.closest('.fc-pagination__btn');
					if (btn.disabled) return;

					if (btn.dataset.action === 'prev') {
						showPage(currentPage - 1);
					} else if (btn.dataset.action === 'next') {
						showPage(currentPage + 1);
					} else if (btn.dataset.page) {
						showPage(parseInt(btn.dataset.page));
					}
					return;
				}
			});

			// Live preview updates
			container.addEventListener('input', function(e) {
				if (e.target.matches('input, textarea')) {
					var card = e.target.closest('.fc-card');
					if (card) updatePreview(card);
				}
			});

			// Add new component
			if (addBtn && template) {
				addBtn.addEventListener('click', function() {
					var newPage = Math.ceil((totalItems + 1) / perPage);
					var html = template.innerHTML
						.replace(/__INDEX__/g, nextIndex)
						.replace(/__PAGE__/g, newPage);

					grid.insertAdjacentHTML('beforeend', html);
					totalItems++;
					nextIndex++;

					// Go to the page with the new item and focus
					showPage(newPage);
					updateCount();

					var newCard = grid.lastElementChild;
					if (newCard) {
						newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
						var nameInput = newCard.querySelector('input[name*="[name]"]');
						if (nameInput) nameInput.focus();
					}
				});
			}
		})();
		</script>

		<p class="description" style="margin-top:12px;">
			<?php \esc_html_e( 'Click "Edit" to modify a component. Changes are applied when you click "Save Changes" below. Preview shows how CSS rules are applied.', 'functionalities' ); ?>
		</p>
		<?php
	}

	// Misc helpers
}
