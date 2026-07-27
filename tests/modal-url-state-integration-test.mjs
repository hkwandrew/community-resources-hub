/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { JSDOM } from 'jsdom';

for ( const asset of [
	'bci-member-modal-linkedin.svg',
	'bci-member-modal-x.svg',
	'bci-member-modal-facebook.svg',
	'bci-member-modal-instagram.svg',
	'bci-member-modal-tiktok.svg',
	'bci-member-modal-mail.svg',
] ) {
	const svg = await readFile(
		new URL( `../assets/images/${ asset }`, import.meta.url ),
		'utf8'
	);

	assert.match( svg, /^<svg\b/u, `Expected ${ asset } to be an SVG asset.` );
	assert.match(
		svg,
		/#004966/u,
		`Expected ${ asset } to use the Figma dark-blue icon color.`
	);
}

function installDomGlobals( window ) {
	globalThis.window = window;
	globalThis.document = window.document;
}

function clearDomGlobals() {
	delete globalThis.window;
	delete globalThis.document;
}

const memberPayload = {
	memberDirectory: [
		{
			id: 7,
			title: 'Test Member',
			slug: 'test-member',
			shareSlug: 'test-member-7',
			overview: 'Member overview.',
			overviewHtml:
				'<h4>Member overview</h4><p>Intro with <strong>bold text</strong> and <a href="https://example.test">a link</a>.</p><ul><li>First service</li></ul>',
			programsHtml:
				'<h4>Programs</h4><p>Program intro with <strong>support</strong> and <a href="https://example.test/programs">a link</a>.</p><ul><li>First program</li></ul>',
			logoUrl: 'https://example.test/logo.png',
			heroBackgroundColor: '#005b8a',
			videoUrl:
				'https://example.test/building-connections-initiative/#bci-video-test-member',
			spotlightHref: '#bci-video-test-member',
			socialLinks: [
				{
					platform: 'LinkedIn',
					url: 'https://www.linkedin.com/company/example',
				},
				{
					platform: 'X / Twitter',
					url: 'https://x.com/example',
				},
				{
					platform: 'Facebook',
					url: 'https://www.facebook.com/example',
				},
				{
					platform: 'Instagram',
					url: 'https://www.instagram.com/example',
				},
				{
					platform: 'TikTok',
					url: 'https://www.tiktok.com/@example',
				},
			],
			attachments: [],
		},
		{
			id: 8,
			title: 'Member Without Color',
			slug: 'member-without-color',
			shareSlug: 'member-without-color-8',
			overview: 'Another member overview.',
			overviewHtml: '',
			programsHtml: '',
			socialLinks: [],
			attachments: [],
		},
		{
			id: 9,
			title: 'Black Hero Member',
			slug: 'black-hero-member',
			shareSlug: 'black-hero-member-9',
			overview: 'Black hero overview.',
			overviewHtml: '<p>Black hero overview.</p>',
			programsHtml: '<p>Black hero programs.</p>',
			logoUrl: 'https://example.test/black-logo.png',
			heroBackgroundColor: '#000000',
			socialLinks: [],
			attachments: [],
		},
	],
};

let memberDom = new JSDOM(
	`
	<section data-wm-bci-controller="bci-member-directory">
		<button type="button" data-wm-bci-member-open data-member-id="7">Open</button>
		<button type="button" data-wm-bci-member-open data-member-id="8">Open Second</button>
		<button type="button" data-wm-bci-member-open data-member-id="9">Open Black</button>
		<dialog data-wm-bci-member-modal hidden>
			<button type="button" data-crh-dialog-close>Close</button>
			<div data-wm-bci-member-modal-hero>
				<img data-wm-bci-member-modal-logo alt="" hidden />
			</div>
			<h2 data-wm-bci-member-modal-title></h2>
			<div data-wm-bci-member-modal-overview></div>
			<div data-wm-bci-member-modal-connect>
				<div data-wm-bci-member-modal-socials data-wm-bci-member-modal-icon-base="https://example.test/wp-content/plugins/community-resources-hub/assets/images/"></div>
				<a data-wm-bci-member-modal-video href="#" target="_blank" rel="noopener noreferrer" hidden>Watch Our Spotlight Video</a>
			</div>
			<div data-wm-bci-member-modal-actions hidden>
				<a data-wm-bci-member-modal-action-video href="#" target="_blank" rel="noopener noreferrer" hidden><span class="button-text"></span></a>
			</div>
			<div data-wm-bci-member-modal-attachments></div>
			<div data-wm-bci-member-modal-programs></div>
		</dialog>
		<script type="application/json" data-wm-bci-member-directory-payload>${ JSON.stringify(
			memberPayload
		) }</script>
	</section>
	`,
	{
		pretendToBeVisual: true,
		url: 'https://example.test/building-connections-initiative/',
	}
);

installDomGlobals( memberDom.window );

const { initMemberDirectorySection } = await import(
	'../src/member-directory/runtime.js'
);

let memberSection = document.querySelector(
	'[data-wm-bci-controller="bci-member-directory"]'
);
let memberDialog = memberSection.querySelector( '[data-wm-bci-member-modal]' );

initMemberDirectorySection( memberSection );
memberSection
	.querySelector( '[data-wm-bci-member-open]' )
	.dispatchEvent( new window.Event( 'click', { bubbles: true } ) );

assert.equal(
	window.location.search,
	'?bci-member=test-member-7',
	'Expected member profile clicks to push the member share URL.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-title]' )
		.textContent,
	'Test Member',
	'Expected member profile clicks to hydrate the member modal.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.style.getPropertyValue( '--wm-bci-member-modal-hero-background' ),
	'#005b8a',
	'Expected member profile clicks to hydrate the modal hero background color.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.style.getPropertyValue( '--wm-bci-member-modal-linework-fill' ),
	'#005180',
	'Expected member profile clicks to hydrate the modal hero linework fill from the background color.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.classList.contains( 'is-empty' ),
	false,
	'Expected members with logos not to mark the modal hero empty.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-overview] h4' )
		?.textContent,
	'Member overview',
	'Expected member modal overview hydration to retain WYSIWYG heading markup.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-overview] strong' )
		?.textContent,
	'bold text',
	'Expected member modal overview hydration to retain inline WYSIWYG markup.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-overview] a' )
		?.getAttribute( 'href' ),
	'https://example.test',
	'Expected member modal overview hydration to retain WYSIWYG links.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-overview] li' )
		?.textContent,
	'First service',
	'Expected member modal overview hydration to retain WYSIWYG list items.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-programs] h4' )
		?.textContent,
	'Programs',
	'Expected member modal Programs hydration to retain WYSIWYG heading markup.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-programs] strong' )
		?.textContent,
	'support',
	'Expected member modal Programs hydration to retain inline WYSIWYG markup.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-programs] a' )
		?.getAttribute( 'href' ),
	'https://example.test/programs',
	'Expected member modal Programs hydration to retain WYSIWYG links.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-programs] li' )
		?.textContent,
	'First program',
	'Expected member modal Programs hydration to retain WYSIWYG list items.'
);

assert.deepEqual(
	Array.from(
		memberDialog.querySelectorAll(
			'[data-wm-bci-member-modal-socials] img'
		)
	).map( ( icon ) => icon.getAttribute( 'src' ) ),
	[
		'https://example.test/wp-content/plugins/community-resources-hub/assets/images/bci-member-modal-linkedin.svg',
		'https://example.test/wp-content/plugins/community-resources-hub/assets/images/bci-member-modal-x.svg',
		'https://example.test/wp-content/plugins/community-resources-hub/assets/images/bci-member-modal-facebook.svg',
		'https://example.test/wp-content/plugins/community-resources-hub/assets/images/bci-member-modal-instagram.svg',
		'https://example.test/wp-content/plugins/community-resources-hub/assets/images/bci-member-modal-tiktok.svg',
	],
	'Expected known member social platforms to hydrate the matching Figma icon assets.'
);

for ( const selector of [
	'[data-wm-bci-member-modal-video]',
	'[data-wm-bci-member-modal-action-video]',
] ) {
	const videoControl = memberDialog.querySelector( selector );

	assert.equal(
		videoControl.getAttribute( 'href' ),
		'#bci-video-test-member',
		'Expected modal spotlight controls to use the Member Card slider fragment.'
	);
	assert.equal(
		videoControl.hasAttribute( 'target' ),
		false,
		'Expected modal spotlight controls to stay in the current tab like the Member Card control.'
	);
	assert.equal(
		videoControl.hidden,
		false,
		'Expected a normalized spotlight target to reveal the modal video control.'
	);
}

memberDialog
	.querySelector( '[data-wm-bci-member-modal-video]' )
	.dispatchEvent( new window.Event( 'click', { bubbles: true } ) );

assert.equal(
	memberDialog.hasAttribute( 'open' ),
	false,
	'Expected the modal spotlight action to close the member dialog before following the slider fragment.'
);
assert.equal(
	window.location.search,
	'',
	'Expected the modal spotlight action to clear the member share query before following the slider fragment.'
);

memberSection
	.querySelector( '[data-member-id="9"]' )
	.dispatchEvent( new window.Event( 'click', { bubbles: true } ) );

assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.style.getPropertyValue( '--wm-bci-member-modal-hero-background' ),
	'#000000',
	'Expected black member hero backgrounds to hydrate onto the modal.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.style.getPropertyValue( '--wm-bci-member-modal-linework-fill' ),
	'#191919',
	'Expected black member hero backgrounds to hydrate a visible modal linework fill.'
);

memberSection
	.querySelector( '[data-member-id="8"]' )
	.dispatchEvent( new window.Event( 'click', { bubbles: true } ) );

assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.style.getPropertyValue( '--wm-bci-member-modal-hero-background' ),
	'',
	'Expected members without hero background colors to clear the modal hero background override.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.style.getPropertyValue( '--wm-bci-member-modal-linework-fill' ),
	'',
	'Expected members without hero background colors to clear the modal hero linework fill override.'
);
assert.equal(
	memberDialog
		.querySelector( '[data-wm-bci-member-modal-hero]' )
		.classList.contains( 'is-empty' ),
	true,
	'Expected members without logos to mark the modal hero empty.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-overview]' )
		.innerHTML,
	'',
	'Expected member modal overview hydration to clear stale WYSIWYG markup.'
);
assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-programs]' )
		.innerHTML,
	'',
	'Expected member modal Programs hydration to clear stale WYSIWYG markup.'
);

memberDialog
	.querySelector( '[data-crh-dialog-close]' )
	.dispatchEvent( new window.Event( 'click', { bubbles: true } ) );

assert.equal(
	window.location.search,
	'',
	'Expected closing a member profile modal to clear the member share URL.'
);

memberDom.window.close();
clearDomGlobals();

memberDom = new JSDOM(
	`
	<section data-wm-bci-controller="bci-member-directory">
		<button type="button" data-wm-bci-member-open data-member-id="7">Open</button>
		<dialog data-wm-bci-member-modal hidden>
			<h2 data-wm-bci-member-modal-title></h2>
			<div data-wm-bci-member-modal-overview></div>
			<div data-wm-bci-member-modal-socials></div>
			<div data-wm-bci-member-modal-actions hidden></div>
			<div data-wm-bci-member-modal-attachments></div>
			<div data-wm-bci-member-modal-programs></div>
		</dialog>
		<script type="application/json" data-wm-bci-member-directory-payload>${ JSON.stringify(
			memberPayload
		) }</script>
	</section>
	`,
	{
		pretendToBeVisual: true,
		url: 'https://example.test/building-connections-initiative/?bci-member=test-member-7',
	}
);

installDomGlobals( memberDom.window );
memberSection = document.querySelector(
	'[data-wm-bci-controller="bci-member-directory"]'
);
memberDialog = memberSection.querySelector( '[data-wm-bci-member-modal]' );
initMemberDirectorySection( memberSection );

assert.equal(
	memberDialog.querySelector( '[data-wm-bci-member-modal-title]' )
		.textContent,
	'Test Member',
	'Expected direct member share URLs to hydrate the matching modal on load.'
);
assert.equal(
	memberDialog.hasAttribute( 'open' ),
	true,
	'Expected direct member share URLs to open the matching modal on load.'
);

memberDom.window.close();
clearDomGlobals();

const opportunityPayload = {
	opportunities: [
		{
			id: 19,
			title: 'Test Opportunity',
			slug: 'test-opportunity',
			shareSlug: 'test-opportunity-19',
			primaryDate: '2999-01-01',
			detailDateLabel: 'January 1, 2999',
			typeLabel: 'Learning',
			typeSlug: 'learning',
			attachments: [],
		},
	],
	members: [],
	types: [],
};

const opportunityDom = new JSDOM(
	`
	<section data-wm-bci-controller="bci-resources" data-wm-bci-opportunity-batch-size="9">
		<span data-wm-bci-type-filter-label>All Types</span>
		<article data-wm-bci-opportunity-card data-opportunity-id="19" data-type-slug="learning" data-primary-date="2999-01-01">
			<button type="button" data-wm-bci-opportunity-open data-opportunity-id="19">Open</button>
		</article>
		<div data-wm-bci-opportunity-empty class="is-hidden"></div>
		<div data-wm-bci-load-more-wrap></div>
		<dialog data-wm-bci-opportunity-modal hidden>
			<button type="button" data-crh-dialog-close>Close</button>
			<h2 data-wm-bci-modal-title></h2>
			<div data-wm-bci-modal-type-badge hidden></div>
			<p data-wm-bci-modal-date></p>
			<div data-wm-bci-modal-attachments></div>
			<div data-wm-bci-modal-row="actions"></div>
		</dialog>
		<script type="application/json" data-wm-bci-opportunities-payload>${ JSON.stringify(
			opportunityPayload
		) }</script>
	</section>
	`,
	{
		pretendToBeVisual: true,
		url: 'https://example.test/bci-resources/?bci-opportunity=test-opportunity-19',
	}
);

installDomGlobals( opportunityDom.window );

const { initOpportunityHub } = await import(
	'../blocks/opportunity-hub/src/view/opportunity-filters.js'
);

const opportunitySection = document.querySelector(
	'[data-wm-bci-controller="bci-resources"]'
);
const opportunityDialog = opportunitySection.querySelector(
	'[data-wm-bci-opportunity-modal]'
);

initOpportunityHub( opportunitySection );

assert.equal(
	opportunityDialog.querySelector( '[data-wm-bci-modal-title]' ).textContent,
	'Test Opportunity',
	'Expected direct opportunity share URLs to hydrate the matching modal on load.'
);
assert.equal(
	opportunityDialog.hasAttribute( 'open' ),
	true,
	'Expected direct opportunity share URLs to open the matching modal on load.'
);

opportunityDialog
	.querySelector( '[data-crh-dialog-close]' )
	.dispatchEvent( new window.Event( 'click', { bubbles: true } ) );

assert.equal(
	window.location.search,
	'',
	'Expected closing an opportunity modal to clear the opportunity share URL.'
);

opportunityDom.window.close();
clearDomGlobals();

console.log( 'Modal URL state integration test passed.' );
