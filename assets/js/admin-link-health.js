/* Live progress uses bounded, nonce-protected batches and never overlaps its own workers. */
(function () {
	'use strict';
	const panel = document.querySelector('[data-link-health]');
	if (!panel || typeof functionalitiesLinkHealth === 'undefined') return;
	const config = functionalitiesLinkHealth;
	const root = panel.closest('.functionalities-module');
	const report = root.querySelector('[data-link-report]');
	const filters = JSON.parse(root.querySelector('[data-link-filters]')?.dataset?.filters || '{}');
	const label = panel.querySelector('[data-link-label]');
	const summary = panel.querySelector('[data-link-summary]');
	const meter = panel.querySelector('[data-link-meter]');
	const spinner = panel.querySelector('[data-link-spinner]');
	const error = panel.querySelector('[data-link-error]');
	let state = JSON.parse(panel.dataset.progress);
	let epoch = 0;
	let worker = false;
	let action = false;
	let polling = false;
	let timer = null;
	let suspended = false;
	let autoDrive = true;
	let actionError = false;
	let editing = false;

	function render() {
		const active = state.status === 'running' || state.status === 'stopping';
		if (label.textContent !== state.label) label.textContent = state.label;
		summary.textContent = state.summary;
		meter.value = state.percent;
		spinner.classList.toggle('is-active', state.phase === 'checking' || state.phase === 'stopping' || worker);
		panel.dataset.phase = worker && state.status === 'running' && state.phase !== 'stopping' ? 'checking' : state.phase;
		panel.querySelector('[data-link-control="start"]').hidden = active || state.status === 'stopped';
		panel.querySelector('[data-link-control="resume"]').hidden = state.status !== 'stopped' && state.phase !== 'error' && !(state.status === 'running' && !autoDrive);
		panel.querySelector('[data-link-control="stop"]').hidden = !active;
		panel.querySelector('[data-link-control="resume"] button').textContent = state.phase === 'error' ? config.retry : config.resume;
		root.querySelectorAll('[data-link-action] button').forEach(button => {
			const operation = button.closest('form').dataset.linkAction;
			button.disabled = operation !== 'export' && (action || state.phase === 'disabled' || (operation !== 'stop' && worker) || (operation === 'stop' && state.status === 'stopping'));
		});
	}
	function showError(message, sticky = false) {
		if (sticky) actionError = true;
		error.textContent = message;
		error.hidden = false;
	}
	function page() {
		return report?.querySelector('[data-link-page]')?.dataset.linkPage || '1';
	}
	async function request(operation, fields = {}) {
		const body = new FormData();
		Object.entries({action: 'functionalities_link_health_live', nonce: config.nonce, operation, run: state.run, report: editing ? '0' : '1', report_page: page(), ...filters, ...fields}).forEach(([key, value]) => body.append(key, value));
		const abort = new AbortController();
		const timeout = setTimeout(() => abort.abort(), 25000);
		try {
			const response = await fetch(config.ajaxUrl, {method: 'POST', credentials: 'same-origin', body, signal: abort.signal});
			const result = await response.json();
			if (!result.success) {
				const failure = new Error(result.data?.message || config.requestError);
				failure.denied = response.status === 403;
				throw failure;
			}
			return result.data;
		} finally { clearTimeout(timeout); }
	}
	function accept(data) {
		state = data.progress;
		if (!editing && report && typeof data.html === 'string' && report.innerHTML !== data.html) report.innerHTML = data.html;
		if (!actionError) error.hidden = true;
		render();
	}
	function schedule(delay = (state.status === 'running' || state.status === 'stopping' ? 2000 : 15000)) {
		clearTimeout(timer);
		if (!suspended && document.visibilityState !== 'hidden') timer = setTimeout(tick, delay);
	}
	async function tick() {
		if (polling || worker || action || suspended || document.visibilityState === 'hidden') return;
		polling = true;
		const revision = epoch;
		try {
			const data = await request('status');
			if (revision === epoch) {
				accept(data);
				if (state.status === 'running' && state.phase === 'waiting' && !worker && autoDrive) runBatch();
			}
		} catch (failure) {
			if (revision === epoch) {
				suspended = !!failure.denied;
				showError(failure.denied ? failure.message : config.connectionError);
			}
		} finally { polling = false; schedule(); }
	}
	async function runBatch() {
		if (worker || action || !autoDrive || state.status !== 'running' || suspended || document.visibilityState === 'hidden') return;
		worker = true;
		const revision = epoch;
		state.label = config.checking;
		render();
		try {
			const data = await request('batch');
			if (revision === epoch) accept(data);
		} catch (failure) {
			if (revision === epoch) {
				suspended = !!failure.denied;
				showError(failure.denied ? failure.message : config.connectionError);
			}
		} finally { worker = false; render(); schedule(1000); }
	}
	root.addEventListener('submit', async event => {
		const form = event.target.closest('[data-link-action]');
		if (!form || form.dataset.linkAction === 'export') return;
		event.preventDefault();
		if (action) return;
		const operation = form.dataset.linkAction;
		if (worker && operation !== 'stop') return;
		const revision = ++epoch;
		action = true;
		clearTimeout(timer);
		error.hidden = true;
		actionError = false;
		if (operation === 'stop') {
			autoDrive = false;
			state.label = config.stopping;
			state.phase = 'stopping';
		} else if (operation === 'start' || operation === 'resume') {
			state.label = config.checking;
			state.phase = 'checking';
		}
		render();
		try {
			const fields = Object.fromEntries(new FormData(form));
			delete fields.action;
			delete fields.operation;
			delete fields.run;
			const data = await request(operation, fields);
			if (revision === epoch) {
				suspended = false;
				if (operation === 'start' || operation === 'resume') autoDrive = true;
				accept(data);
			}
		} catch (failure) {
			if (revision === epoch) { suspended = !!failure.denied; showError(failure.message || config.requestError, true); }
		} finally { action = false; render(); schedule(1000); }
	});
	document.addEventListener('visibilitychange', () => {
		if (document.visibilityState === 'hidden') clearTimeout(timer);
		else schedule(0);
	});
	root.addEventListener('functionalities:link-editor', event => {
		editing = !!event.detail.open;
		if (!editing && event.detail.view?.progress) {
			epoch++;
			accept(event.detail.view);
		}
		if (!editing) schedule(0);
	});
	render();
	schedule(0);
})();
