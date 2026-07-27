import { findOpportunity, setText, toggleModalRow } from './shared-utils.js';

function attachmentKind( attachment ) {
	const label = `${ attachment?.label || '' }`.toLowerCase();
	const url = `${ attachment?.url || '' }`.toLowerCase();
	const value = `${ label } ${ url }`;

	if ( value.includes( '.pdf' ) ) {
		return 'pdf';
	}

	if (
		value.includes( '.png' ) ||
		value.includes( '.jpg' ) ||
		value.includes( '.jpeg' ) ||
		value.includes( '.gif' ) ||
		value.includes( '.webp' ) ||
		value.includes( '.svg' )
	) {
		return 'image';
	}

	return 'file';
}

function attachmentIconMarkup( kind ) {
	if ( 'pdf' === kind ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l5 5v13H7z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v6h6" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 17h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
	}

	if ( 'image' === kind ) {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="5" width="16" height="14" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="10" r="1.5" fill="currentColor"/><path d="M6 17l4-4 3 3 2-2 3 3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l5 5v13H7z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M14 3v6h6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
}

function renderTypeBadge( opportunity ) {
	const badgeLabel =
		opportunity?.typeBadgeLabel || opportunity?.typeLabel || '';

	if ( ! badgeLabel ) {
		return null;
	}

	const badge = document.createElement( 'span' );
	badge.className = `wm-bci-type-badge wm-bci-type-badge--${
		opportunity.typeSlug || 'other'
	} wm-bci-type-badge--modal`;
	badge.textContent = badgeLabel;

	if (
		/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/iu.test( opportunity.typeColor || '' )
	) {
		badge.style.backgroundColor = opportunity.typeColor.toLowerCase();
	}

	return badge;
}

export function hydrateOpportunityModal( modal, opportunity ) {
	if ( ! modal || ! opportunity ) {
		return;
	}

	const ownerDocument = modal.ownerDocument || document;
	const badgeHost = modal.querySelector( '[data-wm-bci-modal-type-badge]' );
	const attachmentsHost = modal.querySelector(
		'[data-wm-bci-modal-attachments]'
	);
	const visitLink = modal.querySelector( '[data-wm-bci-modal-visit]' );
	const calendarLink = modal.querySelector( '[data-wm-bci-modal-calendar]' );
	const actionsRow = modal.querySelector(
		'[data-wm-bci-modal-row="actions"]'
	);
	const locationBadge = modal.querySelector(
		'[data-wm-bci-modal-location-mode]'
	);
	let renderedAttachments = 0;
	const submittedBy = `${ opportunity.submittedBy || '' }`.trim();
	const submittedDateLabel = `${
		opportunity.submittedDateLabel || ''
	}`.trim();
	const submittedByLabel =
		submittedBy && submittedDateLabel
			? `${ submittedBy } — submitted ${ submittedDateLabel }`
			: submittedBy;

	if ( badgeHost ) {
		const badges = [ renderTypeBadge( opportunity ) ];

		if ( opportunity.isBciUpdate ) {
			badges.push(
				renderTypeBadge( {
					typeBadgeLabel: 'BCI Update',
					typeSlug: 'bci-update',
					typeColor: '#004966',
				} )
			);
		}

		const renderedBadges = badges.filter( Boolean );
		badgeHost.replaceChildren();
		badgeHost.hidden = ! renderedBadges.length;

		for ( const badge of renderedBadges ) {
			badgeHost.appendChild( badge );
		}
	}

	setText( modal, '[data-wm-bci-modal-title]', opportunity.title || '' );
	setText(
		modal,
		'[data-wm-bci-modal-date]',
		opportunity.detailDateLabel || opportunity.primaryDateLabel || ''
	);
	setText( modal, '[data-wm-bci-modal-time]', opportunity.timeRange || '' );
	setText(
		modal,
		'[data-wm-bci-modal-organization]',
		opportunity.organization || ''
	);
	setText( modal, '[data-wm-bci-modal-address]', opportunity.address || '' );
	setText( modal, '[data-wm-bci-modal-cost]', opportunity.cost || '' );
	setText( modal, '[data-wm-bci-modal-submitted-by]', submittedByLabel );
	setText(
		modal,
		'[data-wm-bci-modal-description]',
		opportunity.description || ''
	);

	if ( locationBadge ) {
		locationBadge.textContent = opportunity.locationMode || '';
		locationBadge.hidden = ! opportunity.locationMode;
	}

	if ( attachmentsHost ) {
		attachmentsHost.replaceChildren();
		( opportunity.attachments || [] ).forEach( ( attachment ) => {
			const url = `${ attachment?.url || '' }`.trim();

			if ( ! url ) {
				return;
			}

			const anchor = ownerDocument.createElement( 'a' );
			const icon = ownerDocument.createElement( 'span' );
			const label = ownerDocument.createElement( 'span' );
			anchor.className = 'wm-bci-opportunity-modal__attachment';
			anchor.href = url;
			anchor.target = '_blank';
			anchor.rel = 'noopener noreferrer';
			icon.className = 'wm-bci-opportunity-modal__attachment-icon';
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.innerHTML = attachmentIconMarkup(
				attachmentKind( attachment )
			);
			label.className = 'wm-bci-opportunity-modal__attachment-label';
			label.textContent = `${ attachment?.label || url }`.trim() || url;
			anchor.appendChild( icon );
			anchor.appendChild( label );
			attachmentsHost.appendChild( anchor );
			renderedAttachments += 1;
		} );
	}

	if ( visitLink ) {
		if ( opportunity.infoUrl ) {
			visitLink.href = opportunity.infoUrl;
			visitLink.hidden = false;
		} else {
			visitLink.hidden = true;
			visitLink.removeAttribute( 'href' );
		}
	}

	if ( calendarLink ) {
		if ( opportunity.addToCalendarUrl ) {
			calendarLink.href = opportunity.addToCalendarUrl;
			calendarLink.hidden = false;
		} else {
			calendarLink.hidden = true;
			calendarLink.removeAttribute( 'href' );
		}
	}

	if ( actionsRow ) {
		actionsRow.hidden = ! (
			opportunity.infoUrl || opportunity.addToCalendarUrl
		);
	}

	toggleModalRow(
		modal,
		'date',
		Boolean( opportunity.detailDateLabel || opportunity.primaryDateLabel )
	);
	toggleModalRow( modal, 'time', Boolean( opportunity.timeRange ) );
	toggleModalRow(
		modal,
		'organization',
		Boolean( opportunity.organization )
	);
	toggleModalRow(
		modal,
		'location',
		Boolean( opportunity.address || opportunity.locationMode )
	);
	toggleModalRow( modal, 'cost', Boolean( opportunity.cost ) );
	toggleModalRow( modal, 'submitted-by', Boolean( submittedByLabel ) );
	toggleModalRow( modal, 'about', Boolean( opportunity.description ) );
	toggleModalRow( modal, 'attachments', Boolean( renderedAttachments ) );
}

export function bindOpportunityModalTriggers(
	section,
	modal,
	payload,
	openModal = null
) {
	if ( ! section || ! modal ) {
		return;
	}

	section
		.querySelectorAll( '[data-wm-bci-opportunity-open]' )
		.forEach( ( trigger ) => {
			if ( trigger.dataset.wmBciOpportunityModalBound ) {
				return;
			}

			trigger.addEventListener( 'click', ( event ) => {
				const opportunity = findOpportunity(
					payload,
					trigger.dataset.opportunityId
				);

				if ( ! opportunity ) {
					event.preventDefault();
					return;
				}

				hydrateOpportunityModal( modal, opportunity );

				if ( typeof openModal === 'function' ) {
					event.preventDefault();
					openModal( trigger, opportunity );
				}
			} );

			trigger.dataset.wmBciOpportunityModalBound = 'true';
		} );
}
