import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../assets/js/admin-tools.js', import.meta.url), 'utf8');
const flush = () => new Promise((resolve) => setImmediate(resolve));
function harness() {
 const selectors = ['file','preview','apply','result','export','include-code','diagnostics'];
 const nodes = Object.fromEntries(selectors.map((name) => [name, { disabled: name === 'apply', checked: false, files: [], listeners: {}, addEventListener(type, fn) { this.listeners[type] = fn; } }]));
 const pending = [];
 const root = { querySelector(selector) { return nodes[selector.match(/data-tools-([a-z-]+)/)[1]]; }, querySelectorAll() { return []; } };
 vm.runInNewContext(source, { document: { querySelector() { return root; } }, functionalitiesTools: { nonce: 'test', ajaxUrl: '/ajax' }, FormData: class { constructor() { this.values = {}; } append(k,v) { this.values[k] = v; } }, fetch(url, options) { return new Promise((resolve) => pending.push({ values: options.body.values, resolve: (value) => resolve({ json: async () => value }) })); }, URL, Blob });
 return { nodes, pending, async load(text) { nodes.file.files = [{ text: async () => text }]; nodes.file.listeners.change(); await flush(); }, async preview() { nodes.preview.listeners.click(); pending.at(-1).resolve({ success: true, data: {} }); await flush(); } };
}

test('a successful preview enables application of the reviewed payload', async () => {
 const h=harness(); await h.load('{"settings":{}}'); await h.preview(); assert.equal(h.nodes.apply.disabled,false);
});

test('changing custom code inclusion invalidates the reviewed preview', async () => {
 const h=harness(); await h.load('{"settings":{}}'); await h.preview(); h.nodes['include-code'].checked=true; h.nodes['include-code'].listeners.change?.(); assert.equal(h.nodes.apply.disabled,true);
});

test('a late preview cannot approve a different custom code policy', async () => {
 const h=harness(); await h.load('{"settings":{}}'); h.nodes.preview.listeners.click(); h.nodes['include-code'].checked=true; h.nodes['include-code'].listeners.change?.(); h.pending.at(-1).resolve({success:true,data:{}}); await flush(); assert.equal(h.nodes.apply.disabled,true);
});

test('a late preview cannot approve a replacement import file', async () => {
 const h=harness(); await h.load('first'); h.nodes.preview.listeners.click(); await h.load('second'); h.pending.at(-1).resolve({success:true,data:{}}); await flush(); assert.equal(h.nodes.apply.disabled,true);
});
