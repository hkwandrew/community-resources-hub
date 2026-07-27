import { initBciCalendarRuntime } from '../../../../src/calendar/runtime.js';
import { initExternalLinks } from '../../../../src/shared/external-links.js';
import { initOpportunityHub } from './opportunity-filters.js';

if ( 'undefined' !== typeof window && window.document ) {
	initBciCalendarRuntime( window );
}

export function initAll( root = document ) {
	if ( ! root?.querySelectorAll ) {
		return;
	}

	root.querySelectorAll( '[data-wm-bci-controller="bci-resources"]' ).forEach(
		( section ) => {
			initExternalLinks( section, section.ownerDocument?.defaultView );
			initOpportunityHub( section );
		}
	);
}
