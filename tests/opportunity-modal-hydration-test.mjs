/* eslint-env node */
/* global globalThis */

import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { JSDOM } from 'jsdom';

const dom = new JSDOM(
	`
	<dialog class="wm-bci-opportunity-modal">
		<div class="wm-bci-opportunity-modal__body">
			<div class="wm-bci-opportunity-modal__header">
				<div class="wm-bci-opportunity-modal__badge" data-wm-bci-modal-type-badge hidden></div>
				<h2 data-wm-bci-modal-title></h2>
			</div>
			<div data-wm-bci-modal-divider="date"></div>
			<div data-wm-bci-modal-row="date"><p data-wm-bci-modal-date></p></div>
			<div data-wm-bci-modal-divider="time"></div>
			<div data-wm-bci-modal-row="time"><p data-wm-bci-modal-time></p></div>
			<div data-wm-bci-modal-divider="organization"></div>
			<div data-wm-bci-modal-row="organization"><p data-wm-bci-modal-organization></p></div>
			<div data-wm-bci-modal-divider="location"></div>
			<div data-wm-bci-modal-row="location">
				<div class="wm-bci-opportunity-modal__location">
					<span class="wm-bci-opportunity-modal__location-badge" data-wm-bci-modal-location-mode hidden></span>
					<span class="wm-bci-opportunity-modal__location-text" data-wm-bci-modal-address></span>
				</div>
			</div>
			<div data-wm-bci-modal-divider="cost"></div>
			<div data-wm-bci-modal-row="cost"><p data-wm-bci-modal-cost></p></div>
			<div data-wm-bci-modal-divider="submitted-by"></div>
			<div data-wm-bci-modal-row="submitted-by"><p data-wm-bci-modal-submitted-by></p></div>
			<div data-wm-bci-modal-divider="about"></div>
			<div data-wm-bci-modal-row="about"><div data-wm-bci-modal-description></div></div>
			<div data-wm-bci-modal-divider="attachments"></div>
			<div data-wm-bci-modal-row="attachments">
				<div data-wm-bci-modal-attachments></div>
			</div>
			<div data-wm-bci-modal-row="actions">
				<a data-wm-bci-modal-visit href="#" hidden></a>
				<a data-wm-bci-modal-calendar href="#" hidden></a>
			</div>
		</div>
	</dialog>
	`,
	{
		pretendToBeVisual: true,
		url: 'https://example.test/opportunities/',
	}
);

const { window } = dom;
const { document } = window;

globalThis.window = window;
globalThis.document = document;

const { hydrateOpportunityModal } = await import(
	'../blocks/opportunity-hub/src/view/opportunity-modal.js'
);

const modal = document.querySelector( '.wm-bci-opportunity-modal' );
const badgeHost = modal.querySelector( '[data-wm-bci-modal-type-badge]' );
const attachmentsHost = modal.querySelector(
	'[data-wm-bci-modal-attachments]'
);
const locationBadge = modal.querySelector(
	'[data-wm-bci-modal-location-mode]'
);
const actionsRow = modal.querySelector( '[data-wm-bci-modal-row="actions"]' );
const visitLink = modal.querySelector( '[data-wm-bci-modal-visit]' );
const calendarLink = modal.querySelector( '[data-wm-bci-modal-calendar]' );

hydrateOpportunityModal( modal, {
	id: 19,
	title: 'Workshop: Let’s Go Legal',
	typeLabel: 'Workshop, Training, or Other Learning',
	typeBadgeLabel: 'Learning',
	typeSlug: 'learning',
	typeColor: '#5d1783',
	detailDateLabel: 'Jun 10 – Jul 1, 2026',
	timeRange: '9:00 AM – 12:00 PM',
	organization: 'Manzanita House',
	locationMode: 'Hybrid',
	address: '412 W Sprague Ave, Spokane',
	cost: 'Free for BCI members',
	submittedBy: 'Manzanita House',
	submittedDateLabel: 'July 13, 2026',
	isBciUpdate: true,
	description:
		'A four-session workshop series exploring trauma-informed approaches in direct service delivery.',
	infoUrl: 'https://example.test/workshop',
	addToCalendarUrl: 'https://example.test/workshop.ics',
	attachments: [
		{
			label: 'Workshop_Syllabus.pdf',
			url: 'https://example.test/workshop-syllabus.pdf',
		},
	],
} );

assert.equal(
	badgeHost.hidden,
	false,
	'Expected the type badge host to unhide when the opportunity has a type label.'
);
assert.ok(
	badgeHost.querySelector( '.wm-bci-type-badge--modal' ),
	'Expected the type badge host to render the modal badge.'
);
assert.equal(
	badgeHost.textContent,
	'LearningBCI Update',
	'Expected the modal to render the primary type and secondary BCI Update badges.'
);
assert.equal(
	badgeHost.querySelector( '.wm-bci-type-badge--bci-update' )?.style
		.backgroundColor,
	'rgb(0, 73, 102)',
	'Expected the BCI Update badge to use the approved public color.'
);
assert.equal(
	modal.querySelector( '[data-wm-bci-modal-submitted-by]' ).textContent,
	'Manzanita House — submitted July 13, 2026',
	'Expected the submitted-by value to include the submitter and submission date.'
);
assert.equal(
	locationBadge.textContent,
	'Hybrid',
	'Expected the location badge to render the location mode text.'
);
assert.equal(
	locationBadge.hidden,
	false,
	'Expected the location badge to stay visible when the location mode is present.'
);
assert.equal(
	attachmentsHost.children.length,
	1,
	'Expected one attachment chip to render for one valid attachment.'
);
assert.ok(
	attachmentsHost.querySelector(
		'.wm-bci-opportunity-modal__attachment-icon'
	),
	'Expected attachment chips to include the icon wrapper.'
);
assert.ok(
	attachmentsHost.querySelector(
		'.wm-bci-opportunity-modal__attachment-label'
	),
	'Expected attachment chips to wrap their text in a dedicated label span.'
);
assert.equal(
	actionsRow.hidden,
	false,
	'Expected the CTA row to stay visible when modal links are available.'
);
assert.equal(
	visitLink.hidden,
	false,
	'Expected the visit link to show when an info URL is present.'
);
assert.equal(
	visitLink.getAttribute( 'href' ),
	'https://example.test/workshop',
	'Expected the visit link to receive the opportunity info URL.'
);
assert.equal(
	calendarLink.hidden,
	false,
	'Expected the calendar link to show when a calendar URL is present.'
);
assert.equal(
	calendarLink.getAttribute( 'href' ),
	'https://example.test/workshop.ics',
	'Expected the calendar link to receive the add-to-calendar URL.'
);

hydrateOpportunityModal( modal, {
	id: 22,
	title: 'Calendar Only Session',
	typeLabel: 'Community Event',
	typeBadgeLabel: 'Events',
	typeSlug: 'event',
	typeColor: '#c2385a',
	isBciUpdate: true,
	detailDateLabel: 'Jul 21, 2026',
	timeRange: '9:00 AM - 12:00 PM',
	organization: 'Waters Meet',
	locationMode: 'In-person',
	address: 'TBD, United States',
	cost: 'N/A',
	submittedBy: 'Rocio',
	submittedDateLabel: 'July 10, 2026',
	description: 'Please mark your calendars. More details coming soon!',
	infoUrl: '',
	addToCalendarUrl: 'https://example.test/session.ics',
	attachments: [],
} );

assert.equal(
	badgeHost.textContent,
	'EventsBCI Update',
	'Expected BCI Update to remain a secondary badge when the primary type is an event.'
);
assert.equal(
	modal.querySelector( '[data-wm-bci-modal-submitted-by]' ).textContent,
	'Rocio — submitted July 10, 2026',
	'Expected subsequent modal hydration to refresh the combined submitter label.'
);

assert.equal(
	actionsRow.hidden,
	false,
	'Expected the CTA row to stay visible when only the calendar URL is present.'
);
assert.equal(
	visitLink.hidden,
	true,
	'Expected the visit link to hide when no info URL is present.'
);
assert.equal(
	visitLink.hasAttribute( 'href' ),
	false,
	'Expected the hidden visit link not to retain the placeholder hash href.'
);
assert.equal(
	calendarLink.hidden,
	false,
	'Expected the calendar link to stay visible when only the calendar URL is present.'
);
assert.equal(
	calendarLink.getAttribute( 'href' ),
	'https://example.test/session.ics',
	'Expected the calendar link to retain the add-to-calendar URL when no info URL is present.'
);

hydrateOpportunityModal( modal, {
	id: 20,
	title: 'Community Info Session',
	typeLabel: 'Other',
	typeBadgeLabel: '',
	typeSlug: 'other',
	typeColor: '',
	detailDateLabel: 'Jul 8, 2026',
	timeRange: '',
	organization: '',
	locationMode: '',
	address: '',
	cost: '',
	submittedBy: '',
	description: '',
	infoUrl: '',
	addToCalendarUrl: '',
	attachments: [],
} );

assert.equal(
	badgeHost.hidden,
	false,
	'Expected the type badge host to stay visible when the type name is present.'
);
assert.equal(
	badgeHost.textContent,
	'Other',
	'Expected the modal badge to fall back to the type name when no alias exists.'
);
hydrateOpportunityModal( modal, {
	id: 21,
	title: 'Untyped Opportunity',
	typeLabel: '',
	typeBadgeLabel: '',
	typeSlug: 'other',
	typeColor: '',
	detailDateLabel: 'Jul 9, 2026',
	timeRange: '',
	organization: '',
	locationMode: '',
	address: '',
	cost: '',
	submittedBy: '',
	description: '',
	infoUrl: '',
	addToCalendarUrl: '',
	attachments: [],
} );

assert.equal(
	badgeHost.hidden,
	true,
	'Expected the type badge host to hide when no type label exists.'
);
assert.equal(
	locationBadge.hidden,
	true,
	'Expected the location badge to hide when there is no location mode.'
);
assert.equal(
	modal.querySelector( '[data-wm-bci-modal-row="attachments"]' ).hidden,
	true,
	'Expected the attachments row to hide when no attachment chips render.'
);
assert.equal(
	actionsRow.hidden,
	true,
	'Expected the CTA row to hide when neither CTA URL is present.'
);
assert.equal(
	visitLink.hasAttribute( 'href' ),
	false,
	'Expected hidden visit links to remove the placeholder hash href.'
);
assert.equal(
	calendarLink.hasAttribute( 'href' ),
	false,
	'Expected hidden calendar links to remove the placeholder hash href.'
);

const opportunityHubStyle = readFileSync(
	new URL( '../blocks/opportunity-hub/style.scss', import.meta.url ),
	'utf8'
);

assert.match(
	opportunityHubStyle,
	/\.wm-bci-opportunity-modal__action\[hidden\]/u,
	'Expected hidden opportunity modal action links to remain display none in source CSS.'
);

window.close();
delete globalThis.window;
delete globalThis.document;

console.log( 'Opportunity modal hydration test passed.' );
