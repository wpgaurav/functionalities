/** Associate legacy/repeater labels and keep decorative icons out of field names. */
(function () {
	'use strict';
	let nextId = 0;
	function normalize(root) {
		root.querySelectorAll('.dashicons').forEach(icon => icon.setAttribute('aria-hidden', 'true'));
		root.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach(control => {
			if (control.labels && control.labels.length) return;
			const group = control.closest('.fc-form__group, .fc-font-form__group, .functionalities-field');
			const label = group ? group.querySelector('label') : null;
			if (label && !label.control && group.querySelectorAll('input:not([type="hidden"]), select, textarea').length === 1) {
				if (!control.id) {
					do { control.id = 'functionalities-field-' + (++nextId); }
					while (document.querySelectorAll('#' + control.id).length > 1);
				}
				label.htmlFor = control.id;
				return;
			}
			if (!control.hasAttribute('aria-label') && !control.hasAttribute('aria-labelledby')) {
				const row = control.closest('tr');
				const heading = row ? row.querySelector('th') : null;
				if (heading && row.querySelectorAll('input:not([type="hidden"]), select, textarea').length === 1) {
					control.setAttribute('aria-label', heading.textContent.trim());
				}
			}
		});
	}
	function init() {
		const roots = document.querySelectorAll('.functionalities-module, .functionalities-dashboard');
		roots.forEach(root => {
			normalize(root);
			const observer = new MutationObserver(() => normalize(root));
			observer.observe(root, {childList: true, subtree: true});
		});
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
	else init();
})();
