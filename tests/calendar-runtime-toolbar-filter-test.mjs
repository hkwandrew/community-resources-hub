/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM(
	`
	<section data-wm-bci-controller="bci-resources">
		<div class="wm-bci-workflow-section__calendar" data-wm-bci-calendar-region>
			<div class="gv-fullcalendar" data-calendar_id="bci"></div>
		</div>
		<details class="wm-bci-calendar-toolbar-filter" data-wm-bci-calendar-filter-source data-wm-bci-calendar-filter-dimension="type" hidden>
			<summary class="wm-bci-calendar-toolbar-filter__trigger" data-wm-bci-calendar-filter-button>
				<span data-wm-bci-calendar-filter-label>All BCI Events</span>
			</summary>
			<div class="wm-bci-calendar-toolbar-filter__panel" data-wm-bci-calendar-filter-panel>
				<label class="wm-bci-calendar-toolbar-filter__option">
					<input type="checkbox" data-wm-bci-calendar-filter-all />
					<span>View All Events</span>
				</label>
				<label class="wm-bci-calendar-toolbar-filter__option">
					<input type="checkbox" value="learning" data-wm-bci-calendar-filter-checkbox />
					<span>Learning</span>
				</label>
			</div>
		</details>
		<details class="wm-bci-calendar-toolbar-filter" data-wm-bci-calendar-filter-source data-wm-bci-calendar-filter-dimension="member" hidden>
			<summary class="wm-bci-calendar-toolbar-filter__trigger" data-wm-bci-calendar-filter-button>
				<span data-wm-bci-calendar-filter-label>All Members</span>
			</summary>
			<div class="wm-bci-calendar-toolbar-filter__panel" data-wm-bci-calendar-filter-panel>
				<label class="wm-bci-calendar-toolbar-filter__option">
					<input type="checkbox" data-wm-bci-calendar-member-filter-all />
					<span>View All Members</span>
				</label>
				<label class="wm-bci-calendar-toolbar-filter__option">
					<input type="checkbox" value="member-one" data-wm-bci-calendar-member-filter-checkbox />
					<span>Member One</span>
				</label>
			</div>
		</details>
		<button type="button" data-wm-bci-calendar-clear-filters hidden>Clear</button>
	</section>
	`,
	{
		pretendToBeVisual: true,
		url: 'https://example.test/bci-resources/',
	}
);

const { window } = dom;
const { document } = window;

globalThis.window = window;
globalThis.document = document;
window.matchMedia = ( query ) => ( {
	matches: '(hover: hover) and (pointer: fine)' === query,
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

const calendarElement = document.querySelector( '.gv-fullcalendar' );
let calendarBatchRenderingCount = 0;
const eventRecords = [
	{
		display: 'auto',
		extendedProps: {
			wmBciTypeValue: 'learning',
			wmBciMemberSlug: 'member-one',
		},
		setProp( prop, value ) {
			this[ prop ] = value;
		},
	},
	{
		display: 'auto',
		extendedProps: {
			wmBciTypeValue: 'event',
			wmBciMemberSlug: 'member-one',
		},
		setProp( prop, value ) {
			this[ prop ] = value;
		},
	},
	{
		display: 'auto',
		extendedProps: {
			wmBciTypeValue: 'learning',
			wmBciMemberSlug: 'member-two',
		},
		setProp( prop, value ) {
			this[ prop ] = value;
		},
	},
];

window.gvCalendar = {
	bci: {
		extraOptions: {},
		instance: {
			el: calendarElement,
			getEvents() {
				return eventRecords;
			},
			getOption( optionName ) {
				return 'eventDisplay' === optionName ? 'block' : null;
			},
			setOption() {},
			batchRendering( callback ) {
				calendarBatchRenderingCount++;
				callback();
			},
		},
	},
};

const nextFrame = () =>
	new Promise( ( resolve ) => {
		window.requestAnimationFrame( () => {
			window.requestAnimationFrame( resolve );
		} );
	} );

const { initBciCalendarRuntime, normalizeCalendarRuntime } = await import(
	'../src/calendar/runtime.js'
);

const section = document.querySelector(
	'[data-wm-bci-controller="bci-resources"]'
);
section.__crhState = {
	selectedTypes: [ 'resource' ],
	selectedMembers: [ 'card-member' ],
};

initBciCalendarRuntime( window );
await nextFrame();

calendarElement.insertAdjacentHTML(
	'afterbegin',
	`
	<div class="fc-toolbar">
		<div class="fc-toolbar-chunk">
			<div class="fc-button-group">
				<button class="fc-prev-button" type="button">Prev</button>
				<button class="fc-next-button" type="button">Next</button>
				<button class="fc-today-button" type="button">Today</button>
			</div>
		</div>
		<div class="fc-toolbar-chunk"></div>
		<div class="fc-toolbar-chunk"></div>
	</div>
	`
);

await nextFrame();

const wrappers = document.querySelectorAll(
	'.fc-toolbar-chunk [data-wm-bci-calendar-filter]'
);
const wrapper = document.querySelector(
	'[data-wm-bci-calendar-filter-dimension="type"]'
);
const memberWrapper = document.querySelector(
	'[data-wm-bci-calendar-filter-dimension="member"]'
);
const trigger = wrapper.querySelector( '[data-wm-bci-calendar-filter-button]' );
const memberTrigger = memberWrapper.querySelector(
	'[data-wm-bci-calendar-filter-button]'
);
const clearFilters = document.querySelector(
	'[data-wm-bci-calendar-clear-filters]'
);

assert.ok(
	2 === wrappers.length,
	'Expected shared calendar runtime to move both filters into the FullCalendar toolbar.'
);
assert.equal(
	wrapper.hasAttribute( 'hidden' ),
	false,
	'Expected moved calendar filter to be visible.'
);
assert.equal(
	document.querySelector( '[data-wm-bci-calendar-filter-source]' ),
	null,
	'Expected source-only filter markers to be removed after toolbar sync.'
);

const toolbarChunk = document.querySelector( '.fc-toolbar-chunk:first-child' );
const originalButtonGroup = toolbarChunk.querySelector( '.fc-button-group' );
const toolbarFilterGroup = toolbarChunk.querySelector(
	'[data-wm-bci-calendar-filter-group]'
);
const toolbarFilterGroupLabel = toolbarFilterGroup.querySelector(
	'[data-wm-bci-calendar-filter-group-label]'
);

assert.equal(
	toolbarChunk.lastElementChild,
	toolbarFilterGroup,
	'Expected one calendar filter group to be appended to the first toolbar chunk.'
);
assert.equal(
	toolbarFilterGroupLabel?.textContent.trim(),
	'Filter by:',
	'Expected the calendar toolbar filters to start with the same Filter by label as the grid filters.'
);
assert.equal(
	toolbarFilterGroupLabel?.classList.contains(
		'wm-bci-opportunities__filters-label'
	),
	true,
	'Expected the calendar Filter by label to reuse the grid filter label styling.'
);
assert.deepEqual(
	Array.from( originalButtonGroup.children ).map(
		( child ) => child.className
	),
	[ 'fc-prev-button', 'fc-next-button', 'fc-today-button' ],
	'Expected the runtime not to move or reorder Prev, Next, or Today.'
);
assert.deepEqual(
	Array.from( toolbarFilterGroup.children ).map( ( child ) => {
		if ( child.hasAttribute( 'data-wm-bci-calendar-filter-group-label' ) ) {
			return 'label';
		}

		return child.hasAttribute( 'data-wm-bci-calendar-clear-filters' )
			? 'clear'
			: child.dataset.wmBciCalendarFilterDimension;
	} ),
	[ 'label', 'type', 'member', 'clear' ],
	'Expected the Filter by label, type and member dropdowns, then Clear filters in one toolbar group.'
);
assert.equal(
	clearFilters.hidden,
	true,
	'Expected the calendar clear control to be hidden while both filters are at their defaults.'
);

normalizeCalendarRuntime( document );
await Promise.resolve();

const toolbarMutations = [];
const toolbarObserver = new window.MutationObserver( ( records ) => {
	toolbarMutations.push( ...records );
} );
toolbarObserver.observe( toolbarChunk, {
	childList: true,
	subtree: true,
} );

normalizeCalendarRuntime( document );
await Promise.resolve();

assert.equal(
	toolbarMutations.length,
	0,
	'Expected an already-synced toolbar filter not to mutate the toolbar on later runtime passes.'
);

toolbarObserver.disconnect();

wrapper.dispatchEvent(
	new window.Event( 'pointerenter', {
		bubbles: false,
		cancelable: true,
	} )
);

await nextFrame();

assert.equal(
	wrapper.open,
	true,
	'Expected hovering the event type filter to open the dropdown on hover-capable devices.'
);
assert.equal(
	trigger.getAttribute( 'aria-expanded' ),
	'true',
	'Expected hovered event type filter trigger to expose expanded state.'
);

wrapper.dispatchEvent(
	new window.Event( 'pointerleave', {
		bubbles: false,
		cancelable: true,
	} )
);

await nextFrame();

assert.equal(
	wrapper.open,
	false,
	'Expected leaving the event type filter hover area to close the dropdown.'
);

trigger.dispatchEvent(
	new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} )
);

await nextFrame();

assert.equal(
	wrapper.open,
	true,
	'Expected clicking the event type filter trigger to open the dropdown.'
);
assert.equal(
	trigger.getAttribute( 'aria-expanded' ),
	'true',
	'Expected opened event type filter trigger to expose expanded state.'
);

memberTrigger.dispatchEvent(
	new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} )
);

await nextFrame();

assert.equal(
	wrapper.open,
	false,
	'Expected opening the member filter to close the sibling type filter.'
);
assert.equal(
	memberWrapper.open,
	true,
	'Expected the member filter to open independently.'
);

memberTrigger.dispatchEvent(
	new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} )
);

await nextFrame();

document.body.dispatchEvent(
	new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} )
);

await nextFrame();

assert.equal(
	wrapper.open,
	false,
	'Expected clicking outside the event type filter to close the dropdown.'
);

trigger.dispatchEvent(
	new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} )
);

await nextFrame();

assert.equal(
	wrapper.open,
	true,
	'Expected clicking the event type filter trigger to reopen the dropdown after outside-click close.'
);

trigger.dispatchEvent(
	new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} )
);

await nextFrame();

assert.equal(
	wrapper.open,
	false,
	'Expected clicking the event type filter trigger again to close the dropdown.'
);

const filterLabel = wrapper.querySelector(
	'[data-wm-bci-calendar-filter-label]'
);

filterLabel.dispatchEvent(
	new window.MouseEvent( 'mousedown', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
filterLabel.dispatchEvent(
	new window.MouseEvent( 'mouseup', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);

assert.equal(
	wrapper.open,
	true,
	'Expected mouse down/up on the event filter label to open the dropdown even if click is not fired.'
);

const learningOption = wrapper.querySelectorAll(
	'.wm-bci-calendar-toolbar-filter__option'
)[ 1 ];

learningOption.dispatchEvent(
	new window.MouseEvent( 'mousedown', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
learningOption.dispatchEvent(
	new window.MouseEvent( 'mouseup', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);

assert.equal(
	eventRecords[ 0 ].display,
	'auto',
	'Expected selected type events to remain visible.'
);
assert.equal(
	eventRecords[ 1 ].display,
	'none',
	'Expected non-selected type events to be hidden.'
);
assert.equal(
	eventRecords[ 2 ].display,
	'auto',
	'Expected OR behavior within the selected type dimension.'
);
assert.equal(
	wrapper.querySelector( '[data-wm-bci-calendar-filter-checkbox]' ).checked,
	true,
	'Expected mouse down/up on a type option row to check the matching checkbox.'
);
assert.equal(
	clearFilters.hidden,
	false,
	'Expected the calendar clear control to show when a type is selected.'
);

const memberOneOption = memberWrapper.querySelectorAll(
	'.wm-bci-calendar-toolbar-filter__option'
)[ 1 ];

memberOneOption.dispatchEvent(
	new window.MouseEvent( 'mousedown', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
memberOneOption.dispatchEvent(
	new window.MouseEvent( 'mouseup', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);

assert.equal(
	eventRecords[ 0 ].display,
	'auto',
	'Expected an event matching both active dimensions to remain visible.'
);
assert.equal(
	eventRecords[ 2 ].display,
	'none',
	'Expected type and member dimensions to combine with AND behavior.'
);
assert.deepEqual(
	section.__crhState,
	{
		selectedTypes: [ 'resource' ],
		selectedMembers: [ 'card-member' ],
	},
	'Expected calendar selections not to mutate the card-grid filter state.'
);

memberTrigger.dispatchEvent(
	new window.MouseEvent( 'click', {
		bubbles: true,
		cancelable: true,
	} )
);
await nextFrame();

const renderingCountBeforeReset = calendarBatchRenderingCount;

clearFilters.click();

assert.deepEqual(
	section.__crhCalendarRuntimeState,
	{
		selectedMembers: [],
		selectedTypes: [],
	},
	'Expected calendar reset to clear both calendar-only state dimensions atomically.'
);
assert.deepEqual(
	window.gvCalendar.bci.extraOptions.wmBciSelectedTypeValues,
	[],
	'Expected calendar reset to clear the persisted type selection.'
);
assert.deepEqual(
	window.gvCalendar.bci.extraOptions.wmBciSelectedMemberValues,
	[],
	'Expected calendar reset to clear the persisted member selection.'
);
assert.equal(
	calendarBatchRenderingCount,
	renderingCountBeforeReset + 1,
	'Expected one calendar rendering pass after both filter dimensions are cleared.'
);
assert.deepEqual(
	eventRecords.map( ( eventRecord ) => eventRecord.display ),
	[ 'auto', 'block', 'block' ],
	'Expected calendar reset to restore every previously hidden event using the configured display mode.'
);
assert.equal(
	wrapper.querySelector( '[data-wm-bci-calendar-filter-all]' ).checked,
	true,
	'Expected calendar reset to restore View All Events.'
);
assert.equal(
	memberWrapper.querySelector( '[data-wm-bci-calendar-member-filter-all]' )
		.checked,
	true,
	'Expected calendar reset to restore View All Members.'
);
assert.equal(
	wrapper.querySelector( '[data-wm-bci-calendar-filter-label]' ).textContent,
	'All BCI Events',
	'Expected calendar reset to restore the type trigger label.'
);
assert.equal(
	memberWrapper.querySelector( '[data-wm-bci-calendar-filter-label]' )
		.textContent,
	'All Members',
	'Expected calendar reset to restore the member trigger label.'
);
assert.equal( wrapper.open, false, 'Expected the type dropdown to close.' );
assert.equal(
	memberWrapper.open,
	false,
	'Expected the member dropdown to close.'
);
assert.equal(
	trigger.ownerDocument.activeElement,
	trigger,
	'Expected calendar reset to return focus to the type trigger.'
);
assert.equal(
	clearFilters.hidden,
	true,
	'Expected the calendar clear control to hide after restoring defaults.'
);
assert.deepEqual(
	section.__crhState,
	{
		selectedTypes: [ 'resource' ],
		selectedMembers: [ 'card-member' ],
	},
	'Expected calendar reset not to mutate the independent card-grid filter state.'
);

learningOption.dispatchEvent(
	new window.MouseEvent( 'mousedown', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
learningOption.dispatchEvent(
	new window.MouseEvent( 'mouseup', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
memberOneOption.dispatchEvent(
	new window.MouseEvent( 'mousedown', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
memberOneOption.dispatchEvent(
	new window.MouseEvent( 'mouseup', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);

const allEventsOption = wrapper.querySelectorAll(
	'.wm-bci-calendar-toolbar-filter__option'
)[ 0 ];

allEventsOption.dispatchEvent(
	new window.MouseEvent( 'mousedown', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
allEventsOption.dispatchEvent(
	new window.MouseEvent( 'mouseup', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);

assert.equal(
	eventRecords[ 1 ].display,
	'block',
	'Expected clearing the type filter to restore matching member events using the calendar eventDisplay mode.'
);
assert.equal(
	wrapper.querySelector( '[data-wm-bci-calendar-filter-all]' ).checked,
	true,
	'Expected clearing the type filter to restore the all-events selection.'
);
assert.equal(
	eventRecords[ 2 ].display,
	'none',
	'Expected the independent member filter to remain active after clearing event types.'
);

const allMembersOption = memberWrapper.querySelectorAll(
	'.wm-bci-calendar-toolbar-filter__option'
)[ 0 ];

allMembersOption.dispatchEvent(
	new window.MouseEvent( 'mousedown', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);
allMembersOption.dispatchEvent(
	new window.MouseEvent( 'mouseup', {
		bubbles: true,
		cancelable: true,
		button: 0,
	} )
);

assert.equal(
	eventRecords[ 2 ].display,
	'block',
	'Expected clearing both dimensions to restore all events to the configured eventDisplay mode.'
);

window.close();
delete globalThis.window;
delete globalThis.document;

console.log( 'Calendar runtime toolbar filter smoke test passed.' );
