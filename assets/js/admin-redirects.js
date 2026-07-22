(function ($) {
	'use strict';

	if (typeof functionalitiesRedirectAdmin === 'undefined') {
		return;
	}

	var config = functionalitiesRedirectAdmin;

	function message(response) {
		return response && response.data && response.data.message ? response.data.message : 'Error';
	}

	function download(filename, content, type) {
		var link = document.createElement('a');
		link.href = URL.createObjectURL(new Blob([content], { type: type }));
		link.download = filename;
		link.click();
		URL.revokeObjectURL(link.href);
	}

	$(function () {
		$('#redirect-search').on('input', function () {
			var term = $(this).val().toLowerCase();
			$('#redirects-list tr:not(.no-items)').each(function () {
				var row = $(this);
				var matches = row.find('td:eq(1)').text().toLowerCase().indexOf(term) !== -1 || row.find('td:eq(2)').text().toLowerCase().indexOf(term) !== -1;
				row.toggle(matches);
			});
		});

		$('#add-redirect-btn').on('click', function () {
			var from = $('#redirect-from').val().trim();
			var to = $('#redirect-to').val().trim();
			if (!from || !to) {
				window.alert(config.bothRequired);
				return;
			}
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_add', nonce: config.nonce, from: from, to: to, type: $('#redirect-type').val() }, function (response) {
				if (response.success) { window.location.reload(); } else { window.alert(message(response)); }
			});
		});

		$(document).on('change', '.toggle-redirect', function () {
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_toggle', nonce: config.nonce, id: $(this).closest('tr').data('id') });
		});

		$(document).on('click', '.delete-redirect', function () {
			if (!window.confirm(config.deletePrompt)) { return; }
			var row = $(this).closest('tr');
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_delete', nonce: config.nonce, id: row.data('id') }, function (response) {
				if (response.success) { row.fadeOut(function () { row.remove(); }); }
			});
		});

		$('#export-redirects-btn, #export-redirects-csv-btn').on('click', function () {
			var format = this.id.indexOf('csv') !== -1 ? 'csv' : 'json';
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_export', nonce: config.nonce, format: format }, function (response) {
				if (response.success) { download(response.data.filename, response.data.content, format === 'csv' ? 'text/csv' : 'application/json'); }
			});
		});

		$('#import-redirects-btn, #import-redirects-csv-btn').on('click', function () {
			$('#import-format').val(this.id.indexOf('csv') !== -1 ? 'csv' : 'json');
			$('#import-preview').prop('hidden', true).text('');
			$('#confirm-import').prop('disabled', true);
			$('#import-modal').css('display', 'flex');
		});
		$('#cancel-import').on('click', function () { $('#import-modal').hide(); });

		$('#preview-import').on('click', function () {
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_import', nonce: config.nonce, document: $('#import-json').val(), format: $('#import-format').val(), dry_run: 1 }, function (response) {
				$('#import-preview').prop('hidden', false).text(JSON.stringify(response.data, null, 2));
				$('#confirm-import').prop('disabled', !response.success);
			});
		});

		$('#confirm-import').on('click', function () {
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_import', nonce: config.nonce, document: $('#import-json').val(), format: $('#import-format').val() }, function (response) {
				if (response.success) { window.location.reload(); } else { window.alert(message(response)); }
			});
		});

		$('#purge-404-btn').on('click', function () {
			if (!window.confirm(config.purgePrompt)) { return; }
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_404_purge', nonce: config.nonce }, function (response) {
				if (response.success) { window.location.reload(); }
			});
		});

		$(document).on('click', '.ignore-404', function () {
			var row = $(this).closest('tr');
			$.post(config.ajaxUrl, { action: 'functionalities_redirect_404_ignore', nonce: config.nonce, path: row.data('path') }, function (response) {
				if (response.success) { row.remove(); }
			});
		});

		$(document).on('click', '.create-from-404', function () {
			$('#redirect-from').val($(this).closest('tr').data('path'));
			$('#redirect-to').trigger('focus');
			window.scrollTo({ top: $('#redirect-from').offset().top - 80, behavior: 'smooth' });
		});
	});
}(jQuery));
