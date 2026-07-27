import { bindMemberModalTriggers, hydrateMemberModal } from './member-modal.js';
import { bindDialog, closeDialog, openDialog } from '../shared/dialog.js';
import { createModalUrlController } from '../shared/modal-url-state.js';

export function initMemberDirectorySection( section ) {
	if ( ! section || section.dataset.crhMemberDirectoryInitialized ) {
		return;
	}

	const payloadNode = section.querySelector(
		'[data-wm-bci-member-directory-payload]'
	);
	const modal = section.querySelector( '[data-wm-bci-member-modal]' );
	let payload;

	if ( ! payloadNode || ! modal ) {
		return;
	}

	try {
		payload = JSON.parse( payloadNode.textContent || '{}' );
	} catch ( error ) {
		return;
	}

	bindDialog( modal );
	modal
		.querySelectorAll(
			'[data-wm-bci-member-modal-video], [data-wm-bci-member-modal-action-video]'
		)
		.forEach( ( videoLink ) => {
			videoLink.addEventListener( 'click', () => closeDialog( modal ) );
		} );
	const modalUrl = createModalUrlController( {
		ownerWindow: section.ownerDocument?.defaultView,
		paramName: 'bci-member',
		clearParams: [ 'bci-opportunity' ],
		items: Array.isArray( payload.memberDirectory )
			? payload.memberDirectory
			: [],
		modal,
		openItem: ( member, trigger = null ) => {
			if ( member ) {
				hydrateMemberModal( modal, member );
				openDialog( modal, trigger );
			}
		},
		closeItem: () => closeDialog( modal ),
	} );
	bindMemberModalTriggers( section, modal, payload, ( trigger, member ) =>
		modalUrl.openWithUrl( member, trigger )
	);
	modalUrl.syncFromUrl();

	section.dataset.crhMemberDirectoryInitialized = 'true';
}
