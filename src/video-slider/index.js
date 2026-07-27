import { initBciVideoSliders } from './runtime.js';
import { initExternalLinks } from '../shared/external-links.js';

export function initAll( root = document ) {
	root?.querySelectorAll?.( '[data-bci-video-slider]' ).forEach(
		( slider ) => {
			initExternalLinks( slider, slider.ownerDocument?.defaultView );
		}
	);
	initBciVideoSliders( root );
}
