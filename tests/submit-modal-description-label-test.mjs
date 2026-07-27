/* eslint-disable import/no-extraneous-dependencies, no-console */

import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';

import {
	initSubmitModal,
	openDialog,
} from '../blocks/opportunity-hub/src/view/submit-modal.js';

const TIME_SENSITIVE_LABEL = 'Provide a short description of this opportunity:';
const NON_DATE_LABEL = 'Provide a short description:';

function formMarkup( selectedValue = '', label = NON_DATE_LABEL ) {
	const yesChecked = 'Yes' === selectedValue ? ' checked' : '';
	const noChecked = 'No' === selectedValue ? ' checked' : '';

	return `
		<div
			class="wm-bci-submit-modal__form"
			data-wm-bci-time-sensitive-field-id="24"
			data-wm-bci-description-field-id="17"
			data-wm-bci-description-label-time-sensitive="${ TIME_SENSITIVE_LABEL }"
			data-wm-bci-description-label-non-date="${ NON_DATE_LABEL }"
		>
			<form id="gform_5">
				<fieldset id="field_5_24" class="gfield">
					<legend class="gfield_label">Is this a time-sensitive entry?</legend>
					<label><input type="radio" name="input_24" value="Yes"${ yesChecked }> Yes</label>
					<label><input type="radio" name="input_24" value="No"${ noChecked }> No</label>
				</fieldset>
				<div id="field_5_17" class="gfield">
					<label class="gfield_label" for="input_5_17">${ label } <span class="gfield_required"><span aria-hidden="true">*</span><span class="screen-reader-text">Required</span></span></label>
					<textarea id="input_5_17" name="input_17"></textarea>
				</div>
				<input type="hidden" id="gform_source_page_number_5" value="1">
			</form>
		</div>
	`;
}

const dom = new JSDOM(
	`<section>
		<button type="button" data-crh-submit-open>Share an opportunity</button>
		<div data-wm-bci-submit-modal hidden>${ formMarkup() }</div>
	</section>`,
	{
		url: 'https://example.test/bci-resources/',
	}
);
const { document } = dom.window;
const section = document.querySelector( 'section' );
const dialog = section.querySelector( '[data-wm-bci-submit-modal]' );
const jQueryHandlers = new Map();

dom.window.jQuery = () => ( {
	on: ( eventNames, handler ) => {
		eventNames.split( ' ' ).forEach( ( eventName ) => {
			jQueryHandlers.set( eventName, handler );
		} );
	},
} );

const labelElement = () => dialog.querySelector( '#field_5_17 .gfield_label' );
const labelCopy = () => {
	const clone = labelElement().cloneNode( true );

	clone.querySelector( '.gfield_required' )?.remove();

	return clone.textContent.trim();
};
const requiredMarker = () => labelElement().querySelector( '.gfield_required' );
const selectTimeSensitive = ( value, dispatchChange = true ) => {
	dialog
		.querySelectorAll( '#field_5_24 input[type="radio"]' )
		.forEach( ( input ) => {
			input.checked = input.value === value;
		} );

	if ( dispatchChange ) {
		dialog
			.querySelector( `#field_5_24 input[value="${ value }"]` )
			.dispatchEvent(
				new dom.window.Event( 'change', { bubbles: true } )
			);
	}
};
const flushMutations = () =>
	new Promise( ( resolve ) => dom.window.setTimeout( resolve, 0 ) );

initSubmitModal( section );

assert.equal(
	labelCopy(),
	TIME_SENSITIVE_LABEL,
	'Expected an unset time-sensitive choice to use the existing opportunity copy on initialization.'
);

const initialRequiredMarker = requiredMarker();
selectTimeSensitive( 'No' );

assert.equal(
	labelCopy(),
	NON_DATE_LABEL,
	'Expected a delegated No selection to use the generic description copy.'
);
assert.equal(
	requiredMarker(),
	initialRequiredMarker,
	'Expected label updates to preserve the existing nested required marker.'
);
assert.equal(
	requiredMarker().textContent.trim(),
	'*Required',
	'Expected label updates to preserve all required-marker content.'
);

selectTimeSensitive( 'Yes' );
assert.equal(
	labelCopy(),
	TIME_SENSITIVE_LABEL,
	'Expected a delegated Yes selection to restore the opportunity copy.'
);

selectTimeSensitive( 'No', false );
labelElement().childNodes[ 0 ].nodeValue = TIME_SENSITIVE_LABEL;
openDialog( dialog, section.querySelector( '[data-crh-submit-open]' ) );
assert.equal(
	labelCopy(),
	NON_DATE_LABEL,
	'Expected opening the modal to resync restored Gravity Forms choices.'
);

selectTimeSensitive( 'Yes', false );
labelElement().childNodes[ 0 ].nodeValue = NON_DATE_LABEL;
document.dispatchEvent(
	new dom.window.CustomEvent( 'gform/post_render', {
		detail: { formId: 5 },
	} )
);
assert.equal(
	labelCopy(),
	TIME_SENSITIVE_LABEL,
	'Expected the native Gravity Forms post-render event to resync the label.'
);

selectTimeSensitive( 'No', false );
labelElement().childNodes[ 0 ].nodeValue = TIME_SENSITIVE_LABEL;
jQueryHandlers.get( 'gform_post_render' )( { type: 'gform_post_render' }, 5 );
assert.equal(
	labelCopy(),
	NON_DATE_LABEL,
	'Expected the legacy Gravity Forms post-render event to resync the label.'
);

selectTimeSensitive( 'Yes', false );
labelElement().childNodes[ 0 ].nodeValue = NON_DATE_LABEL;
jQueryHandlers.get( 'gform_post_conditional_logic' )(
	{ type: 'gform_post_conditional_logic' },
	5
);
assert.equal(
	labelCopy(),
	TIME_SENSITIVE_LABEL,
	'Expected Gravity Forms conditional-logic updates to resync the label.'
);

dialog.querySelector( '.wm-bci-submit-modal__form' ).outerHTML = formMarkup(
	'No',
	TIME_SENSITIVE_LABEL
);
await flushMutations();

assert.equal(
	labelCopy(),
	NON_DATE_LABEL,
	'Expected an AJAX replacement of the form host to restore the generic label for No.'
);
assert.equal(
	requiredMarker().textContent.trim(),
	'*Required',
	'Expected required markup to survive label synchronization after AJAX replacement.'
);

selectTimeSensitive( 'Yes' );
assert.equal(
	labelCopy(),
	TIME_SENSITIVE_LABEL,
	'Expected delegated changes to remain bound after AJAX replacement.'
);

dom.window.close();

console.log( 'Submit modal description label test passed.' );
