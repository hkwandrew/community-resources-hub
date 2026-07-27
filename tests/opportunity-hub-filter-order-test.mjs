/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const payload = {
	opportunities: [
		{
			id: 'soonest-approved',
			typeSlug: 'resource',
			primaryDate: '2026-01-15',
			endDate: '',
		},
		{
			id: 'unmatched-approved',
			typeSlug: 'recommended-vendor',
			primaryDate: '2026-10-15',
			endDate: '',
		},
		{
			id: 'later-approved',
			typeSlug: 'resource',
			primaryDate: '2026-12-15',
			endDate: '',
		},
	],
	members: [],
	types: [],
};

const dom = new JSDOM(
	`
		<section data-wm-bci-controller="bci-resources" data-wm-bci-opportunity-batch-size="9">
		<span data-wm-bci-type-filter-label>All Types</span>
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
			<details data-wm-bci-calendar-filter-source data-wm-bci-calendar-filter-dimension="type">
				<input type="checkbox" value="event" data-wm-bci-calendar-filter-checkbox />
			</details>
		<script type="application/json" data-wm-bci-opportunities-payload>${ JSON.stringify(
			payload
		) }</script>
			<div data-wm-bci-opportunity-card data-opportunity-id="soonest-approved" data-type-slug="resource"></div>
			<div data-wm-bci-opportunity-card data-opportunity-id="unmatched-approved" data-type-slug="recommended-vendor"></div>
			<div data-wm-bci-opportunity-card data-opportunity-id="later-approved" data-type-slug="resource"></div>
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

globalThis.window = window;
globalThis.document = document;

const { initOpportunityHub } = await import(
	'../blocks/opportunity-hub/src/view/opportunity-filters.js'
);

const section = document.querySelector( '[data-wm-bci-controller]' );
const resource = section.querySelector(
	'[data-wm-bci-type-filter-input][value="resource"]'
);
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
updateCheckbox( resource, true );

assert.deepEqual(
	visibleOpportunityIds(),
	[ 'soonest-approved', 'later-approved' ],
	'Expected filtered BCI resource cards to preserve earliest-to-latest DOM order.'
);

updateCheckbox( calendarEvent, true );

assert.deepEqual(
	visibleOpportunityIds(),
	[ 'soonest-approved', 'later-approved' ],
	'Expected Calendar type changes not to mutate the independent card filter state.'
);
assert.equal(
	section.__crhHandleToolbarTypeChange,
	undefined,
	'Expected the grid controller not to publish a Calendar-to-card type callback.'
);

window.close();
delete globalThis.window;
delete globalThis.document;

console.log( 'Opportunity hub filter order test passed.' );
