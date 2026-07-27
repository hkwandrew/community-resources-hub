/* eslint-env node */
/* global globalThis */
/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

const {
	clearModalUrlToken,
	createModalUrlController,
	currentModalUrlToken,
	findItemByShareToken,
	setModalUrlToken,
	shareTokenForItem,
} = await import( '../src/shared/modal-url-state.js' );

const dom = new JSDOM( '<dialog></dialog>', {
	pretendToBeVisual: true,
	url: 'https://example.test/bci-resources/?existing=1',
} );

const { window } = dom;
const { document } = window;
const modal = document.querySelector( 'dialog' );
const items = [
	{
		id: 7,
		slug: 'test-member',
		shareSlug: 'test-member-7',
	},
];
let openedItem = null;
let closed = 0;

globalThis.window = window;
globalThis.document = document;

assert.equal(
	shareTokenForItem( items[ 0 ] ),
	'test-member-7',
	'Expected share token resolution to prefer unique share slugs.'
);
assert.equal(
	findItemByShareToken( items, 'test-member' )?.id,
	7,
	'Expected URL token lookup to support legacy member slugs.'
);
assert.equal(
	findItemByShareToken( items, '7' )?.id,
	7,
	'Expected URL token lookup to support ID fallback tokens.'
);

setModalUrlToken( window, 'bci-member', 'test-member-7', {
	clearParams: [ 'bci-opportunity' ],
} );
assert.equal(
	currentModalUrlToken( window, 'bci-member' ),
	'test-member-7',
	'Expected setting modal URL state to persist the active token.'
);
assert.equal(
	window.location.search,
	'?existing=1&bci-member=test-member-7',
	'Expected modal URL state to preserve unrelated query parameters.'
);
clearModalUrlToken( window, 'bci-member', 'test-member-7' );
assert.equal(
	currentModalUrlToken( window, 'bci-member' ),
	'',
	'Expected clearing modal URL state to remove the active token.'
);

const controller = createModalUrlController( {
	ownerWindow: window,
	paramName: 'bci-member',
	items,
	modal,
	openItem: ( item ) => {
		openedItem = item;
	},
	closeItem: () => {
		closed += 1;
	},
} );

controller.openWithUrl( items[ 0 ] );
assert.equal(
	currentModalUrlToken( window, 'bci-member' ),
	'test-member-7',
	'Expected controller-opened modals to push a shareable token.'
);
assert.equal(
	openedItem?.id,
	7,
	'Expected controller-opened modals to hydrate the matching item.'
);

modal.dispatchEvent( new window.CustomEvent( 'crh-dialog-close' ) );
assert.equal(
	currentModalUrlToken( window, 'bci-member' ),
	'',
	'Expected user-closed modals to clear their share token.'
);

window.history.pushState( {}, '', '/bci-resources/?bci-member=test-member-7' );
assert.equal(
	controller.syncFromUrl(),
	true,
	'Expected direct share URLs to open the matching modal item.'
);
assert.equal(
	openedItem?.shareSlug,
	'test-member-7',
	'Expected direct share URLs to hydrate by unique share slug.'
);

window.history.pushState( {}, '', '/bci-resources/?bci-member=test-member' );
assert.equal(
	controller.syncFromUrl(),
	true,
	'Expected legacy member slug URLs to open the matching modal item.'
);
modal.dispatchEvent( new window.CustomEvent( 'crh-dialog-close' ) );
assert.equal(
	currentModalUrlToken( window, 'bci-member' ),
	'',
	'Expected legacy slug modal URLs to clear after the modal closes.'
);

window.history.pushState( {}, '', '/bci-resources/?bci-member=test-member-7' );
controller.syncFromUrl();
window.history.pushState( {}, '', '/bci-resources/' );
window.dispatchEvent( new window.PopStateEvent( 'popstate' ) );
assert.equal(
	closed,
	1,
	'Expected back navigation to close the active URL-owned modal.'
);

window.close();
delete globalThis.window;
delete globalThis.document;

console.log( 'Modal URL state test passed.' );
