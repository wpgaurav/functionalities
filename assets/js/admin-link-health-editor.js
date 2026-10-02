/* Keep source edits separate from live report rendering and require a current preview. */
(function () {
	'use strict';
	const dialog = document.querySelector('[data-link-editor]');
	if (!dialog || typeof functionalitiesLinkHealth === 'undefined') return;
	const root = dialog.closest('.functionalities-module');
	const form = dialog.querySelector('[data-link-edit-form]');
	const mode = dialog.querySelector('[data-edit-mode]');
	const destination = dialog.querySelector('[data-edit-destination]');
	const input = dialog.querySelector('[data-edit-new-url]');
	const feedback = dialog.querySelector('[data-edit-feedback]');
	const preview = dialog.querySelector('[data-edit-preview]');
	const reviewButton = dialog.querySelector('[data-edit-review]');
	const applyButton = dialog.querySelector('[data-edit-apply]');
	const cancelButton = dialog.querySelector('[data-edit-cancel]');
	const config = functionalitiesLinkHealth;
	let source = null;
	let trigger = null;
	let revision = 0;
	let reviewed = null;
	let saving = false;
	let savedView = null;
	const format = (template, data) => template.replace('%1$d', () => String(data.count)).replace('%2$s', () => data.title);
	function invalidate() {
		revision++;
		reviewed = null;
		applyButton.disabled = true;
		preview.hidden = true;
		feedback.textContent = '';
		destination.hidden = mode.value !== 'replace';
		input.required = mode.value === 'replace';
		input.disabled = mode.value !== 'replace';
	}
	function busy(value) {
		saving = value;
		mode.disabled = value;
		input.disabled = value || mode.value !== 'replace';
		reviewButton.disabled = value;
		cancelButton.disabled = value;
		applyButton.disabled = value || !reviewed;
	}
	async function request(operation, fields) {
		const body = new FormData();
		const filters = JSON.parse(root.querySelector('[data-link-filters]')?.dataset?.filters || '{}');
		const page = root.querySelector('[data-link-page]')?.dataset?.linkPage || '1';
		Object.entries({action: 'functionalities_link_health_live', nonce: config.nonce, operation, report: operation === 'apply_edit' ? '1' : '0', report_page: page, ...filters, ...fields}).forEach(([key, value]) => body.append(key, value));
		const abort = new AbortController();
		const timeout = setTimeout(() => abort.abort(), 25000);
		try {
			const response = await fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body, signal: abort.signal});
			const result = await response.json();
			if (!result.success) throw new Error(result.data?.message || config.requestError);
			if (operation === 'apply_edit') savedView = result.data;
			return result.data.edit;
		} finally { clearTimeout(timeout); }
	}
	root.addEventListener('click', event => {
		const button = event.target.closest('[data-link-edit]');
		if (!button || dialog.open) return;
		trigger = button;
		savedView = null;
		source = {post_id: button.dataset.postId, url: button.dataset.url};
		mode.value = button.dataset.linkEdit;
		input.value = '';
		dialog.querySelector('[data-edit-source]').textContent = button.dataset.source;
		dialog.querySelector('[data-edit-url]').textContent = button.dataset.url;
		invalidate();
		dialog.showModal();
		root.dispatchEvent(new CustomEvent('functionalities:link-editor', {detail: {open: true}}));
		if (mode.value === 'replace') input.focus();
		else reviewButton.focus();
	});
	mode.addEventListener('change', invalidate);
	input.addEventListener('input', invalidate);
	form.addEventListener('submit', async event => {
		event.preventDefault();
		if (saving || !form.reportValidity()) return;
		invalidate();
		const current = revision;
		feedback.textContent = config.previewing;
		reviewButton.disabled = true;
		try {
			const data = await request('preview_edit', {...source, edit_mode: mode.value, replacement: input.value});
			if (current !== revision || !dialog.open) return;
			reviewed = data;
			preview.textContent = format(data.operation === 'unlink' ? config.previewUnlink : config.previewReplace, data);
			preview.hidden = false;
			feedback.textContent = '';
			applyButton.disabled = false;
		} catch (error) {
			if (current === revision && dialog.open) feedback.textContent = error.message || config.requestError;
		} finally { reviewButton.disabled = false; }
	});
	applyButton.addEventListener('click', async () => {
		if (!reviewed || saving) return;
		const current = reviewed;
		busy(true);
		feedback.textContent = config.applying;
		try {
			const data = await request('apply_edit', {post_id: current.post_id, token: current.token});
			const notice = root.querySelector('[data-link-edit-notice]');
			notice.textContent = format(config.saved, data) + (data.refreshed ? '' : ' ' + config.rescan);
			notice.hidden = false;
			dialog.close();
		} catch (error) {
			invalidate();
			feedback.textContent = error.message || config.requestError;
		} finally { busy(false); }
	});
	cancelButton.addEventListener('click', () => dialog.close());
	dialog.addEventListener('cancel', event => { if (saving) event.preventDefault(); });
	dialog.addEventListener('close', () => {
		invalidate();
		root.dispatchEvent(new CustomEvent('functionalities:link-editor', {detail: {open: false, view: savedView}}));
		if (trigger?.isConnected) trigger.focus();
		else root.querySelector('[data-link-filters] button')?.focus();
	});
})();
