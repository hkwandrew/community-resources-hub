const initializedRoots = new WeakMap();

function runtimeWindow( root, targetWindow ) {
	if ( targetWindow?.document ) {
		return targetWindow;
	}

	return root?.ownerDocument?.defaultView || null;
}

function isExternalHttpLink( link, targetWindow ) {
	const href = link?.getAttribute?.( 'href' );
	const currentUrl = targetWindow?.location?.href;

	if ( ! href || ! currentUrl ) {
		return false;
	}

	try {
		const destination = new targetWindow.URL( href, currentUrl );
		const current = new targetWindow.URL( currentUrl );

		return (
			( 'http:' === destination.protocol ||
				'https:' === destination.protocol ) &&
			destination.origin !== current.origin
		);
	} catch {
		return false;
	}
}

function prepareExternalLink( link, targetWindow ) {
	if ( ! isExternalHttpLink( link, targetWindow ) ) {
		return;
	}

	link.setAttribute( 'target', '_blank' );
	link.relList.add( 'noopener', 'noreferrer' );
}

function prepareExternalLinksWithin( root, targetWindow ) {
	if ( root?.matches?.( 'a[href]' ) ) {
		prepareExternalLink( root, targetWindow );
	}

	root?.querySelectorAll?.( 'a[href]' ).forEach( ( link ) => {
		prepareExternalLink( link, targetWindow );
	} );
}

/**
 * Open external HTTP links inside a plugin-owned root in a safe new tab.
 *
 * Newly inserted links and links whose href changes are handled as well so
 * modal hydration and third-party calendar rendering use the same behavior.
 *
 * @param {Element|Document} root         Plugin-owned DOM root.
 * @param {Window}           targetWindow Owning browser window.
 * @return {MutationObserver|null} Active observer when supported.
 */
export function initExternalLinks( root, targetWindow = null ) {
	if ( ! root?.querySelectorAll ) {
		return null;
	}

	if ( initializedRoots.has( root ) ) {
		return initializedRoots.get( root );
	}

	const ownerWindow = runtimeWindow( root, targetWindow );

	if ( ! ownerWindow ) {
		return null;
	}

	prepareExternalLinksWithin( root, ownerWindow );

	if ( 'function' !== typeof ownerWindow.MutationObserver ) {
		return null;
	}

	const observer = new ownerWindow.MutationObserver( ( mutations ) => {
		mutations.forEach( ( mutation ) => {
			if ( 'attributes' === mutation.type ) {
				prepareExternalLink( mutation.target, ownerWindow );
				return;
			}

			mutation.addedNodes.forEach( ( node ) => {
				prepareExternalLinksWithin( node, ownerWindow );
			} );
		} );
	} );

	observer.observe( root, {
		attributes: true,
		attributeFilter: [ 'href' ],
		childList: true,
		subtree: true,
	} );
	initializedRoots.set( root, observer );

	return observer;
}
