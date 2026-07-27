import { initMemberDirectorySection } from './runtime.js';
import { initExternalLinks } from '../shared/external-links.js';

export function initAll( root = document ) {
	if ( ! root?.querySelectorAll ) {
		return;
	}

	root.querySelectorAll(
		'[data-wm-bci-controller="bci-member-directory"]'
	).forEach( ( section ) => {
		initExternalLinks( section, section.ownerDocument?.defaultView );
		initMemberDirectorySection( section );
	} );
}
