/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM(
	`
	<section data-wm-bci-calendar-region>
		<div class="gv-fullcalendar">
			<div class="fc-toolbar">
				<div class="fc-toolbar-chunk"></div>
			</div>
		</div>
	</section>
	`,
	{
		pretendToBeVisual: true,
		url: 'https://example.test/bci-resources/',
	}
);

const { window } = dom;
const { document } = window;
const observations = [];
let disconnectCount = 0;
let mutationCallback = null;
let runtimeTimeoutRequests = 0;
const nativeSetTimeout = window.setTimeout.bind( window );

globalThis.window = window;
globalThis.document = document;

window.MutationObserver = class {
	constructor( callback ) {
		mutationCallback = callback;
	}

	observe( target, options ) {
		observations.push( { options, target } );
	}

	disconnect() {
		disconnectCount += 1;
	}
};
window.setTimeout = ( callback, delay = 0, ...args ) => {
	runtimeTimeoutRequests += 1;
	return nativeSetTimeout( callback, delay, ...args );
};

const flushTimers = () =>
	new Promise( ( resolve ) => {
		nativeSetTimeout( resolve, 10 );
	} );

const { initBciCalendarRuntime } = await import( '../src/calendar/runtime.js' );

initBciCalendarRuntime( window );
await flushTimers();

assert.ok( observations.length > 0, 'Expected the calendar runtime to observe the rendered calendar.' );

assert.deepEqual(
	observations[ 0 ].options,
	{
		childList: true,
		subtree: true,
	},
	'Expected calendar runtime mutation observation not to watch style or class attributes.'
);

runtimeTimeoutRequests = 0;
mutationCallback( [
	{
		target: document.querySelector( '.fc-toolbar-chunk' ),
	},
] );

assert.equal(
	runtimeTimeoutRequests,
	0,
	'Expected toolbar-only mutations not to schedule another calendar runtime pass.'
);

mutationCallback( [
	{
		target: document.querySelector( '.gv-fullcalendar' ),
	},
] );

assert.equal(
	runtimeTimeoutRequests,
	1,
	'Expected non-toolbar calendar mutations to keep scheduling runtime sync.'
);

await flushTimers();

assert.equal(
	disconnectCount,
	0,
	'Expected calendar runtime mutation handling not to disconnect the observer around runtime sync.'
);

window.close();
delete globalThis.window;
delete globalThis.document;

console.log( 'Calendar runtime observer safety test passed.' );
