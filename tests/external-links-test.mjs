/* eslint-env node */
/* eslint-disable import/no-extraneous-dependencies */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const dom = new JSDOM(
	`
	<section data-test-root>
		<a data-external href="https://partner.example/resource">Partner</a>
		<a data-external-with-rel href="https://files.example/report.pdf" target="_self" rel="nofollow">Report</a>
		<a data-same-origin href="https://watersmeet.test/about/">About</a>
		<a data-relative href="/contact/">Contact</a>
		<a data-fragment href="#calendar">Calendar</a>
		<a data-email href="mailto:hello@watersmeet.test">Email</a>
		<a data-phone href="tel:+15095550199">Phone</a>
	</section>
	`,
	{
		url: 'https://watersmeet.test/resources/',
	}
);

const { window } = dom;
const { document } = window;
const { initExternalLinks } = await import( '../src/shared/external-links.js' );
const root = document.querySelector( '[data-test-root]' );

const observer = initExternalLinks( root, window );
const repeatedObserver = initExternalLinks( root, window );
const externalLink = root.querySelector( '[data-external]' );
const externalLinkWithRel = root.querySelector( '[data-external-with-rel]' );

assert.equal(
	repeatedObserver,
	observer,
	'Expected repeated boot calls to reuse the existing link observer.'
);

assert.equal(
	externalLink.getAttribute( 'target' ),
	'_blank',
	'Expected an external HTTP link to open in a new tab.'
);
assert.deepEqual(
	new Set( externalLink.getAttribute( 'rel' ).split( /\s+/u ) ),
	new Set( [ 'noopener', 'noreferrer' ] ),
	'Expected an external link to receive safe rel tokens.'
);
assert.equal(
	externalLinkWithRel.getAttribute( 'target' ),
	'_blank',
	'Expected an existing target to be replaced for an external link.'
);
assert.deepEqual(
	new Set( externalLinkWithRel.getAttribute( 'rel' ).split( /\s+/u ) ),
	new Set( [ 'nofollow', 'noopener', 'noreferrer' ] ),
	'Expected existing rel tokens to be preserved when safe tokens are added.'
);

for ( const selector of [
	'[data-same-origin]',
	'[data-relative]',
	'[data-fragment]',
	'[data-email]',
	'[data-phone]',
] ) {
	const link = root.querySelector( selector );

	assert.equal(
		link.hasAttribute( 'target' ),
		false,
		`Expected ${ selector } to retain its current-tab behavior.`
	);
	assert.equal(
		link.hasAttribute( 'rel' ),
		false,
		`Expected ${ selector } not to receive external-link rel tokens.`
	);
}

const dynamicLink = document.createElement( 'a' );
dynamicLink.href = 'https://events.example/register';
root.append( dynamicLink );

const changedLink = document.createElement( 'a' );
changedLink.href = '/internal/';
root.append( changedLink );
changedLink.href = 'https://community.example/join';

await new Promise( ( resolve ) => window.setTimeout( resolve, 0 ) );

for ( const link of [ dynamicLink, changedLink ] ) {
	assert.equal(
		link.getAttribute( 'target' ),
		'_blank',
		'Expected dynamically added or updated external links to open in a new tab.'
	);
	assert.deepEqual(
		new Set( link.getAttribute( 'rel' ).split( /\s+/u ) ),
		new Set( [ 'noopener', 'noreferrer' ] ),
		'Expected dynamic external links to receive safe rel tokens.'
	);
}

observer?.disconnect();
