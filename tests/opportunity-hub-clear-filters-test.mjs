/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const payload = {
	opportunities: [
		{ id: 'resource-one', typeSlug: 'resource' },
		{ id: 'vendor-one', typeSlug: 'recommended-vendor' },
		{ id: 'resource-two', typeSlug: 'resource' },
		{ id: 'vendor-two', typeSlug: 'recommended-vendor' },
	],
	members: [],
	types: [],
};

const dom = new JSDOM(
	`
	<section data-wm-bci-controller="bci-resources" data-wm-bci-opportunity-batch-size="2">
		<details class="wm-bci-opportunities__filter-dropdown" data-wm-bci-opportunity-filter-dropdown="type">
			<summary class="wm-bci-opportunities__filter-trigger" data-wm-bci-type-filter-button>
				<span data-wm-bci-type-filter-label>All Types</span>
			</summary>
			<div class="wm-bci-opportunities__filter-panel">
				<label>
					<input type="checkbox" data-wm-bci-type-filter-all checked />
					<span>All Types</span>
				</label>
				<label>
					<input type="checkbox" value="resource" data-wm-bci-type-filter-input />
					<span>Resources</span>
				</label>
				<label>
					<input type="checkbox" value="recommended-vendor" data-wm-bci-type-filter-input />
					<span>Recommended Vendors</span>
				</label>
			</div>
		</details>
		<details class="wm-bci-opportunities__filter-dropdown" data-wm-bci-opportunity-filter-dropdown="member">
			<summary class="wm-bci-opportunities__filter-trigger" data-wm-bci-member-filter-button>
				<span data-wm-bci-member-filter-label>All Members</span>
			</summary>
			<div class="wm-bci-opportunities__filter-panel">
				<label>
					<input type="checkbox" value="member-one" data-wm-bci-member-checkbox />
					<span>Member One</span>
				</label>
				<label>
					<input type="checkbox" value="member-two" data-wm-bci-member-checkbox />
					<span>Member Two</span>
				</label>
			</div>
		</details>
		<button type="button" data-wm-bci-clear-filters hidden>Clear</button>
		<details data-wm-bci-calendar-filter-source data-wm-bci-calendar-filter-dimension="type">
			<input type="checkbox" value="event" data-wm-bci-calendar-filter-checkbox checked />
		</details>
		<script type="application/json" data-wm-bci-opportunities-payload>${ JSON.stringify(
			payload
		) }</script>
		<div data-wm-bci-opportunity-card data-opportunity-id="resource-one" data-type-slug="resource" data-member-slug="member-one"></div>
		<div data-wm-bci-opportunity-card data-opportunity-id="vendor-one" data-type-slug="recommended-vendor" data-member-slug="member-two"></div>
		<div data-wm-bci-opportunity-card data-opportunity-id="resource-two" data-type-slug="resource" data-member-slug="member-one"></div>
		<div data-wm-bci-opportunity-card data-opportunity-id="vendor-two" data-type-slug="recommended-vendor" data-member-slug="member-two"></div>
		<div data-wm-bci-opportunity-empty class="is-hidden"></div>
		<div data-wm-bci-load-more-wrap><button type="button" data-wm-bci-load-more>Load more</button></div>
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
window.matchMedia = () => ( {
	matches: false,
	addEventListener() {},
	removeEventListener() {},
} );

const { initOpportunityHub } = await import(
	'../blocks/opportunity-hub/src/view/opportunity-filters.js'
);

const section = document.querySelector( '[data-wm-bci-controller]' );
const typeDropdown = section.querySelector(
	'[data-wm-bci-opportunity-filter-dropdown="type"]'
);
const memberDropdown = section.querySelector(
	'[data-wm-bci-opportunity-filter-dropdown="member"]'
);
const typeTrigger = section.querySelector( '[data-wm-bci-type-filter-button]' );
const memberTrigger = section.querySelector(
	'[data-wm-bci-member-filter-button]'
);
const resource = section.querySelector(
	'[data-wm-bci-type-filter-input][value="resource"]'
);
const memberOne = section.querySelector(
	'[data-wm-bci-member-checkbox][value="member-one"]'
);
const clearFilters = section.querySelector( '[data-wm-bci-clear-filters]' );
const calendarEvent = section.querySelector(
	'[data-wm-bci-calendar-filter-checkbox][value="event"]'
);

function updateCheckbox( checkbox, checked ) {
	checkbox.checked = checked;
	checkbox.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
}

function visibleOpportunityIds() {
	return Array.from(
		section.querySelectorAll( '[data-wm-bci-opportunity-card]' )
	)
		.filter( ( card ) => ! card.hidden )
		.map( ( card ) => card.dataset.opportunityId );
}

initOpportunityHub( section );

assert.equal(
	clearFilters.hidden,
	true,
	'Expected the grid clear control to be hidden while both filters are at their defaults.'
);

updateCheckbox( memberOne, true );

assert.equal(
	clearFilters.hidden,
	false,
	'Expected the grid clear control to become visible when the member filter is active.'
);

updateCheckbox( resource, true );
section.querySelector( '[data-wm-bci-load-more]' ).click();
typeTrigger.click();
memberTrigger.click();

assert.deepEqual(
	section.__crhState,
	{
		selectedMembers: [ 'member-one' ],
		selectedTypes: [ 'resource' ],
		visibleCount: 4,
	},
	'Expected the test setup to activate both grid dimensions and advance pagination.'
);
assert.equal(
	clearFilters.hidden,
	false,
	'Expected the grid clear control to remain visible when both filters are active.'
);
assert.equal(
	memberDropdown.open,
	true,
	'Expected the member dropdown to be open immediately before reset.'
);

clearFilters.click();

assert.deepEqual(
	section.__crhState,
	{
		selectedMembers: [],
		selectedTypes: [],
		visibleCount: 2,
	},
	'Expected one grid reset to clear both dimensions and restore the initial batch size.'
);
assert.deepEqual(
	visibleOpportunityIds(),
	[ 'resource-one', 'vendor-one' ],
	'Expected grid reset to restore the unfiltered card order at the initial batch size.'
);
assert.equal( resource.checked, false, 'Expected type inputs to reset.' );
assert.equal( memberOne.checked, false, 'Expected member inputs to reset.' );
assert.equal(
	section.querySelector( '[data-wm-bci-type-filter-all]' ).checked,
	true,
	'Expected the All Types input to be restored.'
);
assert.equal(
	section.querySelector( '[data-wm-bci-type-filter-label]' ).textContent,
	'All Types',
	'Expected the type trigger label to reset.'
);
assert.equal(
	section.querySelector( '[data-wm-bci-member-filter-label]' ).textContent,
	'All Members',
	'Expected the member trigger label to reset.'
);
assert.equal( typeDropdown.open, false, 'Expected type dropdown to close.' );
assert.equal(
	memberDropdown.open,
	false,
	'Expected member dropdown to close.'
);
assert.equal(
	typeTrigger.ownerDocument.activeElement,
	typeTrigger,
	'Expected reset to return focus to the type trigger.'
);
assert.equal(
	clearFilters.hidden,
	true,
	'Expected the grid clear control to hide after restoring defaults.'
);
assert.equal(
	calendarEvent.checked,
	true,
	'Expected grid reset not to mutate the independent calendar filters.'
);

window.close();
delete globalThis.window;
delete globalThis.document;

console.log( 'Opportunity hub clear filters test passed.' );
