import { initAll } from '../../src/member-directory/index.js';

if ( 'undefined' !== typeof window && window.document ) {
	const boot = () => {
		initAll();
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot, { once: true } );
	} else {
		boot();
	}

	window.addEventListener( 'load', boot, { once: true } );
}
