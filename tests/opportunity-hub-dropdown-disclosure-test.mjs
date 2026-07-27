/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

function createDom( hoverEnabled ) {
	const dom = new JSDOM(
		`
		<div data-test-outside>Outside</div>
			<section data-wm-bci-controller="bci-resources" data-wm-bci-opportunity-batch-size="9">
			<div class="wm-bci-opportunities__filters-group">
				<details class="wm-bci-opportunities__filter-dropdown wm-bci-opportunities__member-filter" data-wm-bci-opportunity-filter-dropdown="member" data-wm-bci-member-filter>
					<summary class="wm-bci-opportunities__filter-trigger" data-wm-bci-member-filter-button>
						<span class="wm-bci-opportunities__filter-label" data-wm-bci-member-filter-label>All Members</span>
					</summary>
					<div class="wm-bci-opportunities__filter-panel wm-bci-opportunities__member-panel" data-wm-bci-member-filter-panel>
						<label class="wm-bci-opportunities__member-option">
							<input type="checkbox" value="member-a" data-wm-bci-member-checkbox />
							<span>Member A</span>
						</label>
					</div>
				</details>
				<details class="wm-bci-opportunities__filter-dropdown" data-wm-bci-opportunity-filter-dropdown="type">
					<summary class="wm-bci-opportunities__filter-trigger" data-wm-bci-type-filter-button>
						<span class="wm-bci-opportunities__filter-label" data-wm-bci-type-filter-label>All Types</span>
					</summary>
					<div class="wm-bci-opportunities__filter-panel" data-wm-bci-type-filter-panel>
						<label class="wm-bci-opportunities__filter-option">
							<input type="checkbox" data-wm-bci-type-filter-all checked />
							<span>All Types</span>
						</label>
						<label class="wm-bci-opportunities__filter-option">
						<input type="checkbox" value="resource" data-wm-bci-type-filter-input />
						<span>Resources</span>
					</label>
				</div>
			</details>
			</div>
			<script type="application/json" data-wm-bci-opportunities-payload>{"opportunities":[],"members":[],"types":[],"dates":[]}</script>
			<div data-wm-bci-opportunity-empty class="is-hidden"></div>
			<div data-wm-bci-load-more-wrap></div>
		</section>
		`,
		{
			pretendToBeVisual: true,
			url: 'https://example.test/bci-resources/',
		}
	);
	const { window } = dom;
	const { document } = window;

	window.matchMedia = ( query ) => ( {
		matches: hoverEnabled && '(hover: hover) and (pointer: fine)' === query,
		media: query,
		onchange: null,
		addListener() {},
		addEventListener() {},
		dispatchEvent() {
			return true;
		},
		removeListener() {},
		removeEventListener() {},
	} );

	return {
		document,
		outside: document.querySelector( '[data-test-outside]' ),
		section: document.querySelector(
			'[data-wm-bci-controller="bci-resources"]'
		),
		window,
	};
}

function click( window, target ) {
	target.dispatchEvent(
		new window.MouseEvent( 'click', {
			bubbles: true,
			cancelable: true,
		} )
	);
}

function pointerEvent( window, target, type ) {
	target.dispatchEvent(
		new window.Event( type, {
			bubbles: false,
			cancelable: true,
		} )
	);
}

function closeDom( window ) {
	window.close();
	delete globalThis.window;
	delete globalThis.document;
}

const { initOpportunityHub } = await import(
	'../blocks/opportunity-hub/src/view/opportunity-filters.js'
);

{
	const { document, outside, section, window } = createDom( true );

	globalThis.window = window;
	globalThis.document = document;

	initOpportunityHub( section );

	const memberDropdown = section.querySelector(
		'[data-wm-bci-opportunity-filter-dropdown="member"]'
	);
	const typeDropdown = section.querySelector(
		'[data-wm-bci-opportunity-filter-dropdown="type"]'
	);
	const memberTrigger = memberDropdown.querySelector(
		'[data-wm-bci-member-filter-button]'
	);
	const typeTrigger = typeDropdown.querySelector(
		'[data-wm-bci-type-filter-button]'
	);

	pointerEvent( window, memberDropdown, 'pointerenter' );

	assert.equal(
		memberDropdown.open,
		true,
		'Expected hovering the member dropdown to open it on hover-capable devices.'
	);
	assert.equal(
		memberTrigger.getAttribute( 'aria-expanded' ),
		'true',
		'Expected opened member dropdown trigger to expose expanded state.'
	);

	pointerEvent( window, memberDropdown, 'pointerleave' );

	assert.equal(
		memberDropdown.open,
		false,
		'Expected leaving the member dropdown hover area to close it.'
	);

	pointerEvent( window, memberDropdown, 'pointerenter' );
	pointerEvent( window, typeDropdown, 'pointerenter' );

	assert.equal(
		memberDropdown.open,
		false,
		'Expected hovering a second Opportunity Hub dropdown to close the previously open sibling.'
	);
	assert.equal(
		typeDropdown.open,
		true,
		'Expected hovering the type dropdown to open it.'
	);

	click( window, outside );

	assert.equal(
		typeDropdown.open,
		false,
		'Expected clicking outside the Opportunity Hub filters to close the open dropdown.'
	);

	pointerEvent( window, typeDropdown, 'pointerenter' );
	click( window, typeTrigger );

	assert.equal(
		typeDropdown.open,
		false,
		'Expected clicking an open Opportunity Hub dropdown trigger to close it.'
	);

	pointerEvent( window, typeDropdown, 'pointerenter' );

	assert.equal(
		typeDropdown.open,
		false,
		'Expected click-closed Opportunity Hub dropdowns not to reopen immediately while still hovered.'
	);

	pointerEvent( window, typeDropdown, 'pointerleave' );
	pointerEvent( window, typeDropdown, 'pointerenter' );

	assert.equal(
		typeDropdown.open,
		true,
		'Expected Opportunity Hub dropdowns to reopen after hover leaves and re-enters.'
	);

	click( window, memberTrigger );

	assert.equal(
		typeDropdown.open,
		false,
		'Expected clicking the member dropdown trigger to close the previously open Opportunity Hub sibling.'
	);
	assert.equal(
		memberDropdown.open,
		true,
		'Expected clicking the member dropdown trigger to open it.'
	);

	closeDom( window );
}

{
	const { document, section, window } = createDom( false );

	globalThis.window = window;
	globalThis.document = document;

	initOpportunityHub( section );

	const memberDropdown = section.querySelector(
		'[data-wm-bci-opportunity-filter-dropdown="member"]'
	);
	const memberTrigger = memberDropdown.querySelector(
		'[data-wm-bci-member-filter-button]'
	);

	pointerEvent( window, memberDropdown, 'pointerenter' );

	assert.equal(
		memberDropdown.open,
		false,
		'Expected mobile/click-only Opportunity Hub dropdowns not to open on hover events.'
	);

	click( window, memberTrigger );

	assert.equal(
		memberDropdown.open,
		true,
		'Expected clicking the member dropdown trigger to open it on click-only devices.'
	);

	click( window, memberTrigger );

	assert.equal(
		memberDropdown.open,
		false,
		'Expected clicking the member dropdown trigger again to close it on click-only devices.'
	);

	closeDom( window );
}

console.log( 'Opportunity hub dropdown disclosure test passed.' );
