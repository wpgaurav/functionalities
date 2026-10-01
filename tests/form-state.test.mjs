import test from 'node:test';
import assert from 'node:assert/strict';
import { resetSubmittedFields } from '../src/form-state.mjs';

test( 'a successful unchanged redirect submission clears its fields', () => {
	const submitted = { from_url: '/old', to_url: '/new', type: 301 };
	assert.deepEqual( resetSubmittedFields( { ...submitted }, submitted, { from_url: '', to_url: '', type: 301 } ), { from_url: '', to_url: '', type: 301 } );
} );

test( 'editing another redirect while saving preserves the new draft', () => {
	const submitted = { from_url: '/old', to_url: '/new', type: 301 };
	const draft = { ...submitted, from_url: '/second' };
	assert.equal( resetSubmittedFields( draft, submitted, { from_url: '', to_url: '', type: 301 } ), draft );
} );

test( 'a successful task submission retains the project and clears submitted text', () => {
	const submitted = { project: 'work', text: 'Task A', notes: 'Note A' };
	assert.deepEqual( resetSubmittedFields( { ...submitted }, submitted, { text: '', notes: '' } ), { project: 'work', text: '', notes: '' } );
} );

test( 'new task notes or a new project entered during saving remain intact', () => {
	const submitted = { project: 'work', text: 'Task A', notes: 'Note A' };
	for ( const change of [ { notes: 'Note B' }, { project: 'other' }, { text: 'Task B' } ] ) {
		const draft = { ...submitted, ...change };
		assert.equal( resetSubmittedFields( draft, submitted, { text: '', notes: '' } ), draft );
	}
} );
