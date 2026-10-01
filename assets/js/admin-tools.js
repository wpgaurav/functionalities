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
	var includeCode = root.querySelector('[data-tools-include-code]');
	var revision = 0;
	var reviewed = null;

	function invalidate() {
		revision++;
		reviewed = null;
		applyButton.disabled = true;
	}
	includeCode.addEventListener('change', invalidate);

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
		invalidate();
		documentText = '';
		previewButton.disabled = true;
		if (!fileInput.files.length) { return; }
		var selected = revision;
		fileInput.files[0].text().then(function (text) {
			if (selected !== revision) { return; }
			documentText = text;
			previewButton.disabled = false;
			applyButton.disabled = true;
		});
	});

	previewButton.addEventListener('click', function () {
		var selected = revision;
		var payload = { document: documentText, include_code: includeCode.checked ? '1' : '' };
		applyButton.disabled = true;
		request('functionalities_settings_preview', payload).then(function (response) {
			if (selected !== revision) { return; }
			show(response.data);
			reviewed = response.success ? { revision: selected, payload: payload } : null;
			applyButton.disabled = !response.success;
		}).catch(function () {
			if (selected === revision) { invalidate(); show({ message: functionalitiesTools.requestFailed }); }
		});
	});

	applyButton.addEventListener('click', function () {
		if (!reviewed || reviewed.revision !== revision || applyButton.disabled) { return; }
		var selected = revision;
		var payload = reviewed.payload;
		applyButton.disabled = true;
		request('functionalities_settings_import', payload).then(function (response) {
			show(response.data);
			if (selected !== revision) { return; }
			if (response.success) { invalidate(); } else { applyButton.disabled = false; }
		}).catch(function () {
			if (selected === revision) { applyButton.disabled = false; show({ message: functionalitiesTools.requestFailed }); }
		});
	});

	root.querySelector('[data-tools-diagnostics]').addEventListener('click', function () {
		request('functionalities_diagnostics').then(function (response) {
			if (response.success) { download(response.data.filename, response.data.content); } else { show(response.data); }
		});
	});
}());
