/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const payload = {
	opportunities: [
		{ id: 'resource', typeSlug: 'resource' },
		{ id: 'vendor', typeSlug: 'recommended-vendor' },
	],
	members: [],
	types: [],
};

const dom = new JSDOM(
	`
	<section data-wm-bci-controller="bci-resources" data-wm-bci-opportunity-batch-size="9">
		<script type="application/json" data-wm-bci-opportunities-payload>${ JSON.stringify(
			payload
		) }</script>
		<div data-wm-bci-opportunity-card data-opportunity-id="resource" data-type-slug="resource"></div>
		<div data-wm-bci-opportunity-card data-opportunity-id="vendor" data-type-slug="recommended-vendor"></div>
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

initOpportunityHub( section );

assert.equal(
	Object.hasOwn( section.__crhState, 'selectedDatePresets' ),
	false,
	'Expected card filter state to omit the retired date-filter dimension.'
);
assert.deepEqual(
	Array.from( section.querySelectorAll( '[data-wm-bci-opportunity-card]' ) )
		.filter( ( card ) => ! card.hidden )
		.map( ( card ) => card.dataset.opportunityId ),
	[ 'resource', 'vendor' ],
	'Expected all non-date-sensitive cards to render without date filtering.'
);

const filterSource = readFileSync(
	new URL(
		'../blocks/opportunity-hub/src/view/opportunity-filters.js',
		import.meta.url
	),
	'utf8'
);

assert.doesNotMatch(
	filterSource,
	/data-wm-bci-date-filter|selectedDatePresets|matchesDatePreset/u,
	'Expected the card filter implementation to remove its date-filter contract.'
);

window.close();
delete globalThis.window;
delete globalThis.document;

console.log( 'Opportunity hub retired date filter test passed.' );
