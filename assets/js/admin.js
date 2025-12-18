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

})(jQuery);
