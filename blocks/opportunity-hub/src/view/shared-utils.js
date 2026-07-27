import { __ } from '../../../../src/shared/i18n.js';

export const DEFAULT_OPPORTUNITY_BATCH_SIZE = 9;
export const SUBMIT_MODAL_OTHER_PLACEHOLDER = __(
	'Please provide further details on what kind of opportunity it is.',
	'community-resources-hub'
);

export function setText( root, selector, value ) {
	const node = root?.querySelector?.( selector );

	if ( node ) {
		node.textContent = value || '';
	}
}

export function toggleModalRow( root, key, visible ) {
	const row = root?.querySelector?.( `[data-wm-bci-modal-row="${ key }"]` );
	const divider = root?.querySelector?.(
		`[data-wm-bci-modal-divider="${ key }"]`
	);

	if ( row ) {
		row.hidden = ! visible;
	}

	if ( divider ) {
		divider.hidden = ! visible;
	}
}

export function normalizeSelectedTypeValues( selectedTypes ) {
	if ( Array.isArray( selectedTypes ) ) {
		return Array.from(
			new Set( selectedTypes.map( String ).filter( Boolean ) )
		);
	}

	return selectedTypes ? [ String( selectedTypes ) ] : [];
}

export function supportsHoverablePointer( root ) {
	const ownerWindow =
		root?.ownerDocument?.defaultView ||
		root?.defaultView ||
		root?.window ||
		null;

	if ( ! ownerWindow?.matchMedia ) {
		return false;
	}

	try {
		return ownerWindow.matchMedia( '(hover: hover) and (pointer: fine)' )
			.matches;
	} catch ( error ) {
		return false;
	}
}

export function findOpportunity( payload, opportunityId ) {
	return (
		( payload?.opportunities || [] ).find(
			( item ) => String( item.id ) === String( opportunityId )
		) || null
	);
}

export function findMember( payload, memberId ) {
	let members = [];

	if ( Array.isArray( payload?.memberDirectory ) ) {
		members = payload.memberDirectory;
	} else if ( Array.isArray( payload?.members ) ) {
		members = payload.members;
	}

	return (
		members.find( ( item ) => String( item.id ) === String( memberId ) ) ||
		null
	);
}
