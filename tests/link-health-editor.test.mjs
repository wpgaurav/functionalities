import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../assets/js/admin-link-health-editor.js', import.meta.url), 'utf8');
const flush = () => new Promise(resolve => setImmediate(resolve));
function harness() {
	const nodes = {};
	for (const name of ['form','mode','destination','new-url','feedback','preview','review','apply','cancel','source','url']) nodes[name] = {listeners: {}, disabled: name === 'apply', hidden: true, value: '', textContent: '', focus(){}, addEventListener(type, fn){this.listeners[type]=fn;}, reportValidity(){return true;}};
	const notice = {hidden: true};
	const root = {listeners: {}, events: [], querySelector(){return notice;}, addEventListener(type, fn){this.listeners[type]=fn;}, dispatchEvent(event){this.events.push(event.detail.open);}};
	const dialog = {open:false,listeners:{},closest(){return root;},querySelector(selector){return nodes[selector==='[data-link-edit-form]'?'form':selector.match(/data-edit-([a-z-]+)/)[1]];},addEventListener(type,fn){this.listeners[type]=fn;},showModal(){this.open=true;},close(){this.open=false;this.listeners.close();}};
	const requests=[];
	vm.runInNewContext(script,{document:{querySelector(){return dialog;}},AbortController,setTimeout,clearTimeout,CustomEvent:class{constructor(type,opts){this.detail=opts.detail;}},functionalitiesLinkHealth:{ajaxUrl:'/ajax',nonce:'nonce',previewing:'Previewing',applying:'Saving',previewReplace:'Replace %1$d links in %2$s',previewUnlink:'Unlink %1$d links in %2$s',saved:'Updated %1$d links in %2$s',rescan:'Rescan',requestError:'Failed'},
		FormData:class{constructor(){this.values={};}append(k,v){this.values[k]=v;}},fetch(url,options){return new Promise(resolve=>requests.push({fields:options.body.values,reply(edit,success=true){resolve({json:async()=>({success,data:success?{edit}:{message:'Conflict'}})});}}));}});
	const trigger={dataset:{postId:'10',url:'https://example.test/old',source:'Source',linkEdit:'replace'},isConnected:true,focus(){},closest(){return this;}};
	return {nodes,dialog,root,notice,requests,open(mode='replace'){trigger.dataset.linkEdit=mode;root.listeners.click({target:trigger});},async preview(){nodes.form.listeners.submit({preventDefault(){}});await flush();},async reply(index,success=true){requests[index].reply({token:'reviewed-token',post_id:10,count:2,title:'Source',operation:requests[index].fields.edit_mode||'replace',refreshed:true},success);await flush();}};
}

test('editing the destination invalidates a preview, including a late response',async()=>{
	const h=harness();h.open();h.nodes['new-url'].value='https://new.test/one';await h.preview();
	h.nodes['new-url'].value='https://new.test/two';h.nodes['new-url'].listeners.input();
	await h.reply(0);assert.equal(h.nodes.apply.disabled,true);assert.equal(h.nodes.preview.hidden,true);
	await h.preview();await h.reply(1);assert.equal(h.nodes.apply.disabled,false);
	h.nodes['new-url'].listeners.input();assert.equal(h.nodes.apply.disabled,true);
});

test('unlink previews require no destination and apply uses only the reviewed token',async()=>{
	const h=harness();h.open('unlink');assert.equal(h.nodes.destination.hidden,true);assert.equal(h.nodes['new-url'].required,false);assert.equal(h.nodes['new-url'].disabled,true);
	await h.preview();await h.reply(0);assert.match(h.nodes.preview.textContent,/Unlink 2 links/);
	h.nodes.apply.listeners.click();await flush();
	assert.equal(h.requests[1].fields.operation,'apply_edit');assert.equal(h.requests[1].fields.token,'reviewed-token');assert.equal(h.requests[1].fields.replacement,undefined);
	await h.reply(1);assert.equal(h.dialog.open,false);assert.equal(h.notice.hidden,false);assert.deepEqual(h.root.events,[true,false]);
});

test('cancelled previews cannot enable Apply in a newly opened dialog and failed saves require a new preview',async()=>{
	const h=harness();h.open();await h.preview();h.dialog.close();h.open('unlink');await h.reply(0);assert.equal(h.nodes.apply.disabled,true);
	await h.preview();await h.reply(1);h.nodes.apply.listeners.click();await flush();await h.reply(2,false);
	assert.equal(h.nodes.apply.disabled,true);assert.equal(h.nodes.feedback.textContent,'Conflict');assert.equal(h.dialog.open,true);
});

test('preview titles are literal text even when they contain replacement-string syntax',async()=>{
	const h=harness();h.open('unlink');await h.preview();
	h.requests[0].reply({token:'token',post_id:10,count:1,title:'Literal $& title',operation:'unlink'});await flush();
	assert.equal(h.nodes.preview.textContent,'Unlink 1 links in Literal $& title');
});
