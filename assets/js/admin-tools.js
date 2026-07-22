(function () {
	'use strict';

	var root = document.querySelector('[data-functionalities-tools]');
	if (!root || typeof functionalitiesTools === 'undefined') {
		return;
	}

	var fileInput = root.querySelector('[data-tools-file]');
	var previewButton = root.querySelector('[data-tools-preview]');
	var applyButton = root.querySelector('[data-tools-apply]');
	var result = root.querySelector('[data-tools-result]');
	var documentText = '';

	function request(action, data) {
		var body = new FormData();
		body.append('action', action);
		body.append('nonce', functionalitiesTools.nonce);
		Object.keys(data || {}).forEach(function (key) {
			var value = data[key];
			if (Array.isArray(value)) {
				value.forEach(function (item) { body.append(key + '[]', item); });
			} else {
				body.append(key, value);
			}
		});
		return fetch(functionalitiesTools.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (response) { return response.json(); });
	}

	function show(data) {
		result.hidden = false;
		result.textContent = JSON.stringify(data, null, 2);
	}

	function download(filename, content) {
		var link = document.createElement('a');
		link.href = URL.createObjectURL(new Blob([content], { type: 'application/json' }));
		link.download = filename;
		link.click();
		URL.revokeObjectURL(link.href);
	}

	root.querySelector('[data-tools-export]').addEventListener('click', function () {
		var modules = Array.prototype.map.call(root.querySelectorAll('[data-tools-module]:checked'), function (input) { return input.value; });
		request('functionalities_settings_export', { modules: modules, include_code: root.querySelector('[data-tools-include-code]').checked ? '1' : '' }).then(function (response) {
			if (response.success) { download(response.data.filename, response.data.content); } else { show(response.data); }
		});
	});

	fileInput.addEventListener('change', function () {
		if (!fileInput.files.length) { return; }
		fileInput.files[0].text().then(function (text) {
			documentText = text;
			previewButton.disabled = false;
			applyButton.disabled = true;
		});
	});

	previewButton.addEventListener('click', function () {
		request('functionalities_settings_preview', { document: documentText, include_code: root.querySelector('[data-tools-include-code]').checked ? '1' : '' }).then(function (response) {
			show(response.data);
			applyButton.disabled = !response.success;
		});
	});

	applyButton.addEventListener('click', function () {
		request('functionalities_settings_import', { document: documentText, include_code: root.querySelector('[data-tools-include-code]').checked ? '1' : '' }).then(function (response) {
			show(response.data);
			applyButton.disabled = response.success;
		});
	});

	root.querySelector('[data-tools-diagnostics]').addEventListener('click', function () {
		request('functionalities_diagnostics').then(function (response) {
			if (response.success) { download(response.data.filename, response.data.content); } else { show(response.data); }
		});
	});
}());
