/**
 * Functionalities Admin JavaScript
 *
 * @package Functionalities
 */

(function($) {
	'use strict';

	/**
	 * Initialize on document ready.
	 */
	$(document).ready(function() {
		initAccordions();
		initModuleCards();
		initRunDetection();
		initMediaUploads();
		initColorPickers();
	});

	/**
	 * Initialize accordion functionality.
	 */
	function initAccordions() {
		const $root = $('#fc-accordions');

		if (!$root.length) {
			return;
		}

		$root.on('click', '.fc-acc__hdr', function() {
			const $acc = $(this).closest('.fc-acc');
			$acc.toggleClass('is-open');
		});
	}

	/**
	 * Add hover effects and analytics to module cards.
	 */
	function initModuleCards() {
		$('.functionalities-module-card').on('mouseenter', function() {
			$(this).addClass('active');
		}).on('mouseleave', function() {
			$(this).removeClass('active');
		});
	}

	/**
	 * Initialize Run Detection Now button.
	 */
	function initRunDetection() {
		const $button = $('#functionalities-run-detection');

		if (!$button.length || typeof functionalitiesAdmin === 'undefined') {
			return;
		}

		$button.on('click', function(e) {
			e.preventDefault();

			const $btn = $(this);
			const originalText = $btn.text();

			// Disable button and show loading state.
			$btn.prop('disabled', true).text(functionalitiesAdmin.runningText);

			$.ajax({
				url: functionalitiesAdmin.ajaxUrl,
				type: 'POST',
				data: {
					action: 'functionalities_run_detection',
					nonce: functionalitiesAdmin.runDetectionNonce
				},
				success: function(response) {
					if (response.success) {
						// Show success message.
						alert(response.data.message);
						// Reload the page to show updated results.
						location.reload();
					} else {
						alert(response.data.message || 'Detection failed.');
					}
				},
				error: function() {
					alert('An error occurred while running detection.');
				},
				complete: function() {
					// Re-enable button.
					$btn.prop('disabled', false).text(functionalitiesAdmin.runDetectionText);
				}
			});
		});
	}

	/**
	 * Initialize media upload buttons.
	 */
	function initMediaUploads() {
		$(document).on('click', '.func-upload-btn', function(e) {
			e.preventDefault();
			var $btn = $(this);
			var $target = $($btn.data('target'));
			var frame = wp.media({
				title: 'Select Image',
				multiple: false,
				library: { type: 'image' }
			});
			frame.on('select', function() {
				var attachment = frame.state().get('selection').first().toJSON();
				$target.val(attachment.url);
			});
			frame.open();
		});
	}

	/**
	 * Initialize color picker fields.
	 */
	function initColorPickers() {
		if ($.fn.wpColorPicker) {
			$('.func-color-field').wpColorPicker();
		}
	}

})(jQuery);
