import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../assets/js/admin-link-health.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
const progress = (status = 'running', overrides = {}) => ({run: 'scan-a', status, phase: status === 'running' ? 'waiting' : status, label: status, summary: '2 of 10 posts · 8 links', posts: 2, total: 10, links: 8, percent: 20, ...overrides});
function harness(initial = progress()) {
	const nodes = {};
	const forms = {};
	for (const key of ['label', 'summary', 'meter', 'spinner', 'error']) nodes[key] = {hidden: true, textContent: '', classList: {active: false, toggle(name, value) { this.active = value; }}};
	for (const operation of ['start', 'resume', 'stop', 'export', 'ignore']) {
		forms[operation] = {dataset: {linkAction: operation}, fields: {action: 'functionalities_link_health', operation, post_id: '10', url: 'https://example.test/link'}, closest() { return this; }};
		forms[operation].button = {disabled: false, textContent: '', closest() { return forms[operation]; }};
		nodes[operation] = {hidden: false};
	}
	const report = {innerHTML: '', querySelector() { return {dataset: {linkPage: '2'}}; }};
	const root = {listeners: {}, querySelector() { return report; }, querySelectorAll() { return Object.values(forms).map(f => f.button); }, addEventListener(type, fn) { this.listeners[type] = fn; }};
	const panel = {dataset: {progress: JSON.stringify(initial)}, closest() { return root; }, querySelector(selector) {
		const control = selector.match(/data-link-control="(\w+)"/);
		if (control) return selector.endsWith('button') ? forms[control[1]].button : nodes[control[1]];
		return nodes[selector.match(/data-link-(\w+)/)[1]];
	}};
	const document = {visibilityState: 'visible', listeners: {}, querySelector() { return panel; }, addEventListener(type, fn) { this.listeners[type] = fn; }};
	const requests = [], timers = new Map();
	let timerId = 0;
	vm.runInNewContext(source, {document, AbortController, functionalitiesLinkHealth: {ajaxUrl: '/ajax', nonce: 'nonce', checking: 'Checking', stopping: 'Stopping', retry: 'Retry', resume: 'Resume', connectionError: 'Reconnecting', requestError: 'Failed'},
		FormData: class { constructor(form) { this.entries = Object.entries(form?.fields || {}); } append(k, v) { this.entries.push([k, v]); } [Symbol.iterator]() { return this.entries[Symbol.iterator](); } },
		setTimeout(fn, delay) { const id = ++timerId; timers.set(id, {fn, delay}); return id; }, clearTimeout(id) { timers.delete(id); },
		fetch(url, options) { return new Promise((resolve, reject) => requests.push({fields: Object.fromEntries(options.body), reject, resolve(data, success = true, status = 200) { resolve({status, json: async () => ({success, data})}); }})); }
	});
	return {nodes, forms, report, requests, document,
		async next() { const item = [...timers].find(([, value]) => value.delay < 25000); assert.ok(item, 'a progress timer must exist'); timers.delete(item[0]); item[1].fn(); await flush(); },
		async reply(index, state = progress(), html = '<table>Results</table>') { requests[index].resolve({progress: state, html}); await flush(); },
		async submit(operation) { root.listeners.submit({target: forms[operation], preventDefault() {}}); await flush(); }
	};
}

test('an open running scan advances one bounded batch and refreshes progress and rows', async () => {
	const h = harness(); await h.next();
	assert.equal(h.requests[0].fields.operation, 'status');
	await h.reply(0);
	assert.equal(h.requests[1].fields.operation, 'batch');
	assert.equal(h.requests[1].fields.run, 'scan-a');
	assert.equal(h.requests[1].fields.report_page, '2');
	assert.equal(h.nodes.spinner.classList.active, true);
	assert.equal(h.forms.stop.button.disabled, false);
	await h.next();
	assert.equal(h.requests.length, 2, 'no overlapping status request or worker');
	await h.reply(1, progress('completed', {percent: 100, summary: 'All done'}), '<table>50 checked links</table>');
	assert.equal(h.nodes.meter.value, 100);
	assert.equal(h.nodes.summary.textContent, 'All done');
	assert.equal(h.report.innerHTML, '<table>50 checked links</table>');
});

test('a late batch response cannot overwrite a stop request', async () => {
	const h = harness(); await h.next(); await h.reply(0);
	await h.submit('stop');
	assert.equal(h.requests[2].fields.operation, 'stop');
	await h.reply(2, progress('stopping'));
	await h.reply(1, progress('running', {label: 'Old response'}));
	assert.equal(h.nodes.label.textContent, 'stopping');
	await h.next(); await h.reply(3, progress('stopped'));
	assert.equal(h.nodes.resume.hidden, false);
	assert.equal(h.nodes.stop.hidden, true);
	assert.equal(h.requests.filter(r => r.fields.operation === 'batch').length, 1);
});

test('a failed stop keeps the warning visible and does not start another client batch', async () => {
	const h = harness(); await h.next(); await h.reply(0);
	await h.submit('stop');
	h.requests[2].reject(new Error('Stop request interrupted')); await flush();
	await h.reply(1); await h.next(); await h.reply(3);
	assert.equal(h.nodes.error.hidden, false);
	assert.match(h.nodes.error.textContent, /Stop request interrupted/);
	assert.equal(h.nodes.resume.hidden, false);
	assert.equal(h.requests.filter(r => r.fields.operation === 'batch').length, 1);
});

test('an existing worker is monitored without a second worker and hidden tabs pause polling', async () => {
	const h = harness(); await h.next(); await h.reply(0, progress('running', {phase: 'checking'}));
	assert.equal(h.requests.length, 1);
	h.document.visibilityState = 'hidden'; h.document.listeners.visibilitychange();
	h.document.visibilityState = 'visible'; h.document.listeners.visibilitychange();
	await h.next(); await h.reply(1, progress('stopped'));
	assert.equal(h.requests.length, 2);
});

test('expired authorization stops polling and reports the failure', async () => {
	const h = harness(); await h.next();
	h.requests[0].resolve({message: 'Please sign in'}, false, 403); await flush();
	assert.equal(h.nodes.error.textContent, 'Please sign in');
	assert.equal(h.requests.length, 1);
});
