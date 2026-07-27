import { SUBMIT_MODAL_OTHER_PLACEHOLDER } from './shared-utils.js';
import {
	bindDialog,
	closeDialog as closeSharedDialog,
	openDialog as openSharedDialog,
} from '../../../../src/shared/dialog.js';

export function configureSubmitModalOtherChoice( dialog ) {
	const otherControl = dialog?.querySelector?.( '.gchoice_other_control' );

	if ( ! otherControl ) {
		return false;
	}

	otherControl.placeholder = SUBMIT_MODAL_OTHER_PLACEHOLDER;
	otherControl.setAttribute( 'aria-label', SUBMIT_MODAL_OTHER_PLACEHOLDER );

	if ( otherControl.disabled && 'Other' === otherControl.value ) {
		otherControl.value = '';
	}

	return true;
}

export function revealGravityFormsAjaxWrapper( dialog ) {
	if ( ! dialog?.querySelectorAll ) {
		return false;
	}

	let revealed = false;

	dialog.querySelectorAll( '.gform_wrapper' ).forEach( ( wrapper ) => {
		if ( 'none' === wrapper.style?.display ) {
			wrapper.style.display = 'block';
			revealed = true;
		}

		wrapper.querySelectorAll( 'form[id^="gform_"]' ).forEach( ( form ) => {
			if ( form.style?.opacity ) {
				form.style.opacity = '';
				revealed = true;
			}
		} );
	} );

	return revealed;
}

function gravityFormsView( dialog ) {
	return (
		dialog?.ownerDocument?.defaultView ||
		( 'undefined' !== typeof window ? window : null )
	);
}

function submitModalFormHost( dialog ) {
	return dialog?.querySelector?.( '.wm-bci-submit-modal__form' ) || null;
}

function submitModalField( formHost, fieldId ) {
	const normalizedFieldId = String( fieldId || '' ).trim();

	if ( ! formHost?.querySelectorAll || ! /^\d+$/.test( normalizedFieldId ) ) {
		return null;
	}

	return (
		Array.from( formHost.querySelectorAll( '.gfield[id]' ) ).find(
			( field ) => field.id.endsWith( `_${ normalizedFieldId }` )
		) || null
	);
}

function replaceGravityFormsLabelCopy( label, copy ) {
	if ( ! label || ! copy ) {
		return false;
	}

	const labelText = label.querySelector?.( '.gfield_label_text' );

	if ( labelText ) {
		labelText.textContent = copy;
		return true;
	}

	const copyNode = Array.from( label.childNodes || [] ).find(
		( node ) => 3 === node.nodeType && node.nodeValue.trim()
	);

	if ( copyNode ) {
		copyNode.nodeValue = `${ copy } `;
		return true;
	}

	const textNode = label.ownerDocument?.createTextNode?.( `${ copy } ` );

	if ( ! textNode ) {
		return false;
	}

	label.insertBefore( textNode, label.firstChild );

	return true;
}

function syncSubmitModalDescriptionLabel( dialog ) {
	const formHost = submitModalFormHost( dialog );

	if ( ! formHost ) {
		return false;
	}

	const timeSensitiveField = submitModalField(
		formHost,
		formHost.dataset.wmBciTimeSensitiveFieldId
	);
	const descriptionField = submitModalField(
		formHost,
		formHost.dataset.wmBciDescriptionFieldId
	);
	const descriptionLabel =
		descriptionField?.querySelector?.( '.gfield_label' );
	const selectedValue = timeSensitiveField?.querySelector?.(
		'input[type="radio"]:checked, input[type="checkbox"]:checked'
	)?.value;
	const copy =
		'No' === selectedValue
			? formHost.dataset.wmBciDescriptionLabelNonDate
			: formHost.dataset.wmBciDescriptionLabelTimeSensitive;

	return replaceGravityFormsLabelCopy( descriptionLabel, copy );
}

function bindSubmitModalDescriptionLabel( dialog ) {
	if ( ! dialog || dialog.dataset.crhSubmitDescriptionLabelBound ) {
		return;
	}

	dialog.addEventListener( 'change', ( event ) => {
		const formHost = submitModalFormHost( dialog );
		const timeSensitiveField = submitModalField(
			formHost,
			formHost?.dataset?.wmBciTimeSensitiveFieldId
		);

		if ( timeSensitiveField?.contains?.( event.target ) ) {
			syncSubmitModalDescriptionLabel( dialog );
		}
	} );

	const view = gravityFormsView( dialog );

	if ( 'function' === typeof view?.MutationObserver ) {
		const observer = new view.MutationObserver( () => {
			syncSubmitModalDescriptionLabel( dialog );
		} );

		observer.observe( dialog, {
			childList: true,
			subtree: true,
		} );
	}

	dialog.dataset.crhSubmitDescriptionLabelBound = 'true';
	syncSubmitModalDescriptionLabel( dialog );
}

function scheduleGravityFormsReveal( dialog, delay = 0 ) {
	const view = gravityFormsView( dialog );
	const reveal = () => revealGravityFormsAjaxWrapper( dialog );
	const run = () => {
		if ( delay > 0 && 'function' === typeof view?.setTimeout ) {
			view.setTimeout( reveal, delay );
			return;
		}

		reveal();
	};

	if ( 'function' === typeof view?.requestAnimationFrame ) {
		view.requestAnimationFrame( () => view.requestAnimationFrame( run ) );
		return;
	}

	if ( 'function' === typeof view?.setTimeout ) {
		view.setTimeout( run, delay );
		return;
	}

	run();
}

function gravityFormIdsForDialog( dialog ) {
	if ( ! dialog?.querySelectorAll ) {
		return new Set();
	}

	return new Set(
		Array.from(
			dialog.querySelectorAll(
				'form[id^="gform_"], .gform_wrapper[id^="gform_wrapper_"], iframe[id^="gform_ajax_frame_"]'
			)
		)
			.map( ( node ) => String( node.id || '' ).match( /(\d+)$/ )?.[ 1 ] )
			.filter( Boolean )
	);
}

function dialogContainsGravityForm( dialog, formId ) {
	const normalizedFormId = String( formId || '' );

	if ( ! normalizedFormId ) {
		return false;
	}

	return gravityFormIdsForDialog( dialog ).has( normalizedFormId );
}

function gravityFormsCurrentPage( dialog, formId ) {
	return (
		dialog?.querySelector?.( `#gform_source_page_number_${ formId }` )
			?.value || '1'
	);
}

function triggerGravityFormsPostRender( dialog ) {
	const view = gravityFormsView( dialog );
	const formIds = gravityFormIdsForDialog( dialog );

	if ( ! view || ! formIds.size ) {
		return false;
	}

	let triggered = false;

	formIds.forEach( ( formId ) => {
		const currentPage = gravityFormsCurrentPage( dialog, formId );

		if ( 'function' === typeof view.gform?.core?.triggerPostRenderEvents ) {
			view.gform.core.triggerPostRenderEvents(
				Number( formId ),
				currentPage
			);
			triggered = true;
			return;
		}

		if ( 'function' === typeof view.jQuery ) {
			view.jQuery( dialog.ownerDocument ).trigger( 'gform_post_render', [
				Number( formId ),
				currentPage,
			] );
			triggered = true;
		}
	} );

	return triggered;
}

function scheduleGravityFormsPostRender( dialog, delay = 75 ) {
	const view = gravityFormsView( dialog );
	const run = () => {
		if ( dialog?.dataset?.crhSubmitGformReady ) {
			return;
		}

		triggerGravityFormsPostRender( dialog );
	};

	if ( 'function' === typeof view?.setTimeout ) {
		view.setTimeout( run, delay );
		view.setTimeout( run, delay + 250 );
		return;
	}

	run();
}

function submitModalLayer( dialog ) {
	return dialog?.closest?.( '[data-wm-bci-submit-modal-layer]' ) || null;
}

function submitModalOverlayTarget( layer ) {
	if ( ! layer?.querySelector ) {
		return null;
	}

	for ( const selector of [
		'[data-wm-bci-calendar-region] .fc-view-harness',
		'[data-wm-bci-calendar-region] .fc-scrollgrid',
		'[data-wm-bci-calendar-region] .gv-fullcalendar',
		'[data-wm-bci-calendar-region]',
	] ) {
		const target = layer.querySelector( selector );

		if ( target ) {
			return target;
		}
	}

	return null;
}

function updateSubmitModalOverlayBounds( dialog ) {
	const layer = submitModalLayer( dialog );
	const target = submitModalOverlayTarget( layer );

	if (
		! layer ||
		! target ||
		'function' !== typeof layer.getBoundingClientRect ||
		'function' !== typeof target.getBoundingClientRect
	) {
		return false;
	}

	const layerRect = layer.getBoundingClientRect();
	const targetRect = target.getBoundingClientRect();
	const top = Math.max( 0, targetRect.top - layerRect.top );
	const left = Math.max( 0, targetRect.left - layerRect.left );
	const width = Math.max(
		0,
		Math.min( targetRect.width, layerRect.width - left )
	);
	const height = Math.max(
		0,
		Math.min( targetRect.height, layerRect.height - top )
	);

	layer.style.setProperty( '--wm-bci-submit-overlay-top', `${ top }px` );
	layer.style.setProperty( '--wm-bci-submit-overlay-left', `${ left }px` );
	layer.style.setProperty( '--wm-bci-submit-overlay-width', `${ width }px` );
	layer.style.setProperty(
		'--wm-bci-submit-overlay-height',
		`${ height }px`
	);
	layer.style.setProperty(
		'--wm-bci-submit-modal-left',
		`${ left + width / 2 }px`
	);
	layer.style.setProperty(
		'--wm-bci-submit-modal-top',
		`${ top + height / 2 }px`
	);

	return true;
}

function scheduleSubmitModalOverlayBounds( dialog, delay = 0 ) {
	const view = gravityFormsView( dialog );
	const update = () => updateSubmitModalOverlayBounds( dialog );
	const run = () => {
		update();

		if ( delay > 0 && 'function' === typeof view?.setTimeout ) {
			view.setTimeout( update, delay );
		}
	};

	if ( 'function' === typeof view?.requestAnimationFrame ) {
		view.requestAnimationFrame( run );
		return;
	}

	run();
}

function scheduleSubmitModalScroll( dialog, delay = 0 ) {
	const view = gravityFormsView( dialog );
	const scroll = () => {
		if ( 'function' === typeof dialog?.scrollIntoView ) {
			dialog.scrollIntoView( {
				block: 'center',
				inline: 'nearest',
			} );
		}
	};
	const run = () => {
		if ( delay > 0 && 'function' === typeof view?.setTimeout ) {
			view.setTimeout( scroll, delay );
			return;
		}

		scroll();
	};

	if ( 'function' === typeof view?.requestAnimationFrame ) {
		view.requestAnimationFrame( run );
		return;
	}

	run();
}

function revealAfterGravityFormsReady( dialog, formId ) {
	if ( ! dialogContainsGravityForm( dialog, formId ) ) {
		return;
	}

	dialog.dataset.crhSubmitGformReady = 'true';
	scheduleGravityFormsReveal( dialog, 50 );
	scheduleSubmitModalScroll( dialog, 100 );
}

export function replaceSubmitModalIntroWithConfirmation( dialog, formId ) {
	const normalizedFormId = String( formId || '' );

	if ( ! dialog?.querySelector || ! /^\d+$/.test( normalizedFormId ) ) {
		return false;
	}

	const intro = dialog.querySelector( '.wm-bci-submit-modal__intro' );
	const confirmation = dialog.querySelector(
		`#gform_confirmation_wrapper_${ normalizedFormId }`
	);

	if ( ! intro || ! confirmation ) {
		return false;
	}

	if ( intro === confirmation ) {
		return true;
	}

	confirmation.classList.add( 'wm-bci-submit-modal__intro' );
	intro.replaceWith( confirmation );

	return true;
}

function bindGravityFormsReadyReveal( dialog ) {
	if ( ! dialog?.ownerDocument || dialog.dataset.crhSubmitGformReadyBound ) {
		return;
	}

	const ownerDocument = dialog.ownerDocument;
	const view = gravityFormsView( dialog );
	const jQuery = view?.jQuery;

	ownerDocument.addEventListener( 'gform/post_render', ( event ) => {
		const formId =
			event?.detail?.formId ||
			event?.detail?.data?.formId ||
			event?.detail?.form_id;

		syncSubmitModalDescriptionLabel( dialog );
		revealAfterGravityFormsReady( dialog, formId );
	} );

	if ( 'function' === typeof jQuery ) {
		jQuery( ownerDocument ).on(
			'gform_post_render gform_post_conditional_logic gform_page_loaded gform_confirmation_loaded',
			( event, formId ) => {
				if ( 'gform_confirmation_loaded' === event?.type ) {
					replaceSubmitModalIntroWithConfirmation( dialog, formId );
				}

				syncSubmitModalDescriptionLabel( dialog );
				revealAfterGravityFormsReady( dialog, formId );
			}
		);
	}

	dialog.dataset.crhSubmitGformReadyBound = 'true';
}

function isGravityFormsAjaxPostback( frame ) {
	try {
		return Boolean(
			frame?.contentDocument?.documentElement?.innerHTML?.includes(
				'GF_AJAX_POSTBACK'
			)
		);
	} catch ( error ) {
		return false;
	}
}

function bindGravityFormsAjaxFrameReveal( dialog ) {
	if (
		! dialog?.querySelectorAll ||
		dialog.dataset.crhSubmitGformRevealBound
	) {
		return;
	}

	dialog
		.querySelectorAll( 'iframe[id^="gform_ajax_frame_"]' )
		.forEach( ( frame ) => {
			frame.addEventListener( 'load', () => {
				if ( isGravityFormsAjaxPostback( frame ) ) {
					scheduleGravityFormsReveal( dialog, 50 );
				}
			} );
		} );

	dialog.dataset.crhSubmitGformRevealBound = 'true';
}

function setSubmitModalLayerOpen( dialog, isOpen ) {
	const layer = submitModalLayer( dialog );
	const overlay = layer?.querySelector?.(
		'[data-wm-bci-submit-modal-overlay]'
	);

	if ( isOpen ) {
		updateSubmitModalOverlayBounds( dialog );
	}

	layer?.classList?.toggle(
		'wm-bci-workflow-section__calendar-modal-layer--submit-open',
		isOpen
	);
	dialog?.classList?.toggle( 'wm-bci-submit-modal--is-open', isOpen );

	if ( overlay ) {
		overlay.hidden = ! isOpen;
		overlay.toggleAttribute( 'hidden', ! isOpen );
	}
}

function bindSubmitModalLayer( dialog ) {
	if ( ! dialog || dialog.dataset.crhSubmitModalLayerBound ) {
		return;
	}

	const layer = submitModalLayer( dialog );
	const overlay = layer?.querySelector?.(
		'[data-wm-bci-submit-modal-overlay]'
	);

	overlay?.addEventListener?.( 'click', () => closeDialog( dialog ) );

	const observerView = gravityFormsView( dialog );

	if ( 'function' === typeof observerView?.MutationObserver ) {
		const observer = new observerView.MutationObserver( () => {
			setSubmitModalLayerOpen(
				dialog,
				! dialog.hidden && dialog.hasAttribute( 'open' )
			);
		} );

		observer.observe( dialog, {
			attributes: true,
			attributeFilter: [ 'hidden', 'open' ],
		} );
	}

	if ( 'function' === typeof observerView?.addEventListener ) {
		observerView.addEventListener( 'resize', () => {
			if ( dialog.hasAttribute( 'open' ) ) {
				updateSubmitModalOverlayBounds( dialog );
			}
		} );
	}

	dialog.dataset.crhSubmitModalLayerBound = 'true';
}

export { bindDialog };

export function closeDialog( dialog ) {
	setSubmitModalLayerOpen( dialog, false );
	closeSharedDialog( dialog );
}

export function openDialog( dialog, trigger ) {
	configureSubmitModalOtherChoice( dialog );
	syncSubmitModalDescriptionLabel( dialog );
	openSharedDialog( dialog, trigger );
	setSubmitModalLayerOpen( dialog, true );
	scheduleSubmitModalOverlayBounds( dialog, 250 );
	scheduleGravityFormsPostRender( dialog );
	scheduleSubmitModalScroll( dialog, 350 );

	if ( dialog?.dataset?.crhSubmitGformReady ) {
		scheduleGravityFormsReveal( dialog );
	}
}

export function initSubmitModal( section ) {
	if ( ! section ) {
		return null;
	}

	const submitDialog = section.querySelector( '[data-wm-bci-submit-modal]' );

	if ( ! submitDialog ) {
		return null;
	}

	bindDialog( submitDialog );
	bindSubmitModalLayer( submitDialog );
	bindSubmitModalDescriptionLabel( submitDialog );
	bindGravityFormsReadyReveal( submitDialog );
	bindGravityFormsAjaxFrameReveal( submitDialog );

	section
		.querySelectorAll( '[data-crh-submit-open]' )
		.forEach( ( button ) => {
			if ( button.dataset.crhSubmitDialogBound ) {
				return;
			}

			button.addEventListener( 'click', () =>
				openDialog( submitDialog, button )
			);
			button.dataset.crhSubmitDialogBound = 'true';
		} );

	return submitDialog;
}
