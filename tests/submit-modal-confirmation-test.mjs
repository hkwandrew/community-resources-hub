/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

import {
	initSubmitModal,
	replaceSubmitModalIntroWithConfirmation,
} from '../blocks/opportunity-hub/src/view/submit-modal.js';

const dom = new JSDOM( `
	<div data-wm-bci-submit-modal>
		<div class="wm-bci-submit-modal__header">
			<p class="wm-bci-submit-modal__intro">Original form introduction.</p>
		</div>
		<div class="wm-bci-submit-modal__form">
			<div id="gform_confirmation_wrapper_5" class="gform_confirmation_wrapper">
				<div id="gform_confirmation_message_5" class="gform_confirmation_message">
					<strong>Thank you.</strong> Your opportunity was submitted.
				</div>
			</div>
		</div>
	</div>
` );

const dialog = dom.window.document.querySelector(
	'[data-wm-bci-submit-modal]'
);
const header = dialog.querySelector( '.wm-bci-submit-modal__header' );
const form = dialog.querySelector( '.wm-bci-submit-modal__form' );
const confirmation = dialog.querySelector( '#gform_confirmation_wrapper_5' );

assert.equal(
	replaceSubmitModalIntroWithConfirmation( dialog, 5 ),
	true,
	'Expected the Gravity Forms confirmation to replace the submit-modal intro.'
);
assert.equal(
	header.querySelector( '.wm-bci-submit-modal__intro' ),
	confirmation,
	'Expected the confirmation wrapper to occupy the intro position.'
);
assert.equal(
	header.querySelector( '.wm-bci-submit-modal__intro strong' )?.textContent,
	'Thank you.',
	'Expected confirmation markup to be preserved when it replaces the intro.'
);
assert.equal(
	form.querySelector( '#gform_confirmation_wrapper_5' ),
	null,
	'Expected the thank-you message not to remain duplicated in the form area.'
);
assert.equal(
	dialog.textContent.includes( 'Original form introduction.' ),
	false,
	'Expected the original intro copy to be removed after submission.'
);

const eventDom = new JSDOM( `
	<section>
		<div data-wm-bci-submit-modal>
			<div class="wm-bci-submit-modal__header">
				<p class="wm-bci-submit-modal__intro">Original form introduction.</p>
			</div>
			<div class="wm-bci-submit-modal__form">
				<div id="gform_confirmation_wrapper_5" class="gform_confirmation_wrapper">
					<div id="gform_confirmation_message_5" class="gform_confirmation_message">
						Thank you. Your opportunity was submitted.
					</div>
				</div>
			</div>
		</div>
	</section>
` );
const eventSection = eventDom.window.document.querySelector( 'section' );
const eventDialog = eventSection.querySelector( '[data-wm-bci-submit-modal]' );
let confirmationLoadedHandler = null;

eventDom.window.jQuery = () => ( {
	on: ( eventNames, handler ) => {
		if ( eventNames.includes( 'gform_confirmation_loaded' ) ) {
			confirmationLoadedHandler = handler;
		}
	},
} );

initSubmitModal( eventSection );

assert.equal(
	typeof confirmationLoadedHandler,
	'function',
	'Expected submit-modal initialization to bind the Gravity Forms confirmation event.'
);

confirmationLoadedHandler( { type: 'gform_confirmation_loaded' }, 5 );

assert.equal(
	eventDialog
		.querySelector( '.wm-bci-submit-modal__intro' )
		?.textContent.trim(),
	'Thank you. Your opportunity was submitted.',
	'Expected the Gravity Forms confirmation event to replace the modal intro.'
);

console.log( 'Submit modal confirmation test passed.' );
