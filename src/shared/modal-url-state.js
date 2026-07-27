/**
 * Lightweight query-string state for shareable modal URLs.
 */

function ownerWindowFor( ownerWindow, modal ) {
	if ( ownerWindow?.location && ownerWindow?.history ) {
		return ownerWindow;
	}

	return (
		modal?.ownerDocument?.defaultView ||
		( 'undefined' !== typeof window ? window : null )
	);
}

function normalizeToken( value ) {
	return `${ value ?? '' }`.trim();
}

function relativeUrl( url ) {
	return `${ url.pathname }${ url.search }${ url.hash }`;
}

export function shareTokenForItem( item ) {
	if ( ! item ) {
		return '';
	}

	for ( const key of [ 'shareSlug', 'slug', 'id' ] ) {
		const token = normalizeToken( item[ key ] );

		if ( token ) {
			return token;
		}
	}

	return '';
}

export function findItemByShareToken( items, token ) {
	const normalizedToken = normalizeToken( token );

	if ( ! normalizedToken || ! Array.isArray( items ) ) {
		return null;
	}

	return (
		items.find( ( item ) => {
			return [ 'shareSlug', 'slug', 'id' ].some(
				( key ) => normalizeToken( item?.[ key ] ) === normalizedToken
			);
		} ) || null
	);
}

export function currentModalUrlToken( ownerWindow, paramName ) {
	const view = ownerWindowFor( ownerWindow );

	if ( ! view?.URL || ! view?.location ) {
		return '';
	}

	try {
		return normalizeToken(
			new view.URL( view.location.href ).searchParams.get( paramName )
		);
	} catch ( error ) {
		return '';
	}
}

export function setModalUrlToken(
	ownerWindow,
	paramName,
	token,
	{ clearParams = [], replace = false } = {}
) {
	const view = ownerWindowFor( ownerWindow );
	const normalizedToken = normalizeToken( token );

	if ( ! view?.URL || ! view?.history || ! view?.location || ! normalizedToken ) {
		return false;
	}

	const url = new view.URL( view.location.href );
	url.searchParams.set( paramName, normalizedToken );

	clearParams.forEach( ( clearParam ) => {
		if ( clearParam && clearParam !== paramName ) {
			url.searchParams.delete( clearParam );
		}
	} );

	view.history[ replace ? 'replaceState' : 'pushState' ](
		{},
		'',
		relativeUrl( url )
	);

	return true;
}

export function clearModalUrlToken(
	ownerWindow,
	paramName,
	expectedToken = '',
	{ replace = false } = {}
) {
	const view = ownerWindowFor( ownerWindow );

	if ( ! view?.URL || ! view?.history || ! view?.location ) {
		return false;
	}

	const url = new view.URL( view.location.href );
	const currentToken = normalizeToken( url.searchParams.get( paramName ) );

	if ( expectedToken && currentToken && currentToken !== expectedToken ) {
		return false;
	}

	if ( ! currentToken ) {
		return false;
	}

	url.searchParams.delete( paramName );
	view.history[ replace ? 'replaceState' : 'pushState' ](
		{},
		'',
		relativeUrl( url )
	);

	return true;
}

export function createModalUrlController( {
	ownerWindow = null,
	paramName,
	clearParams = [],
	items = [],
	modal,
	openItem,
	closeItem,
} ) {
	const view = ownerWindowFor( ownerWindow, modal );

	if ( ! view?.location || ! view?.history || ! paramName || ! modal ) {
		return {
			openWithUrl: ( item, trigger = null ) => openItem?.( item, trigger ),
			syncFromUrl: () => false,
		};
	}

	let activeToken = '';
	let syncingFromUrl = false;

	const openToken = ( token, trigger = null ) => {
		const item = findItemByShareToken( items, token );

		if ( ! item ) {
			return false;
		}

		activeToken = normalizeToken( token );
		openItem?.( item, trigger );

		return true;
	};

	const clearUrl = () => {
		if ( syncingFromUrl || ! activeToken ) {
			return;
		}

		clearModalUrlToken( view, paramName, activeToken );
		activeToken = '';
	};

	const syncFromUrl = () => {
		const token = currentModalUrlToken( view, paramName );

		syncingFromUrl = true;

		if ( token ) {
			const didOpen = openToken( token );
			syncingFromUrl = false;
			return didOpen;
		}

		if ( activeToken && 'function' === typeof closeItem ) {
			closeItem();
		}

		activeToken = '';
		syncingFromUrl = false;

		return false;
	};

	modal.addEventListener( 'close', clearUrl );
	modal.addEventListener( 'crh-dialog-close', clearUrl );
	view.addEventListener( 'popstate', syncFromUrl );

	return {
		openWithUrl( item, trigger = null ) {
			const token = shareTokenForItem( item );

			if ( token ) {
				setModalUrlToken( view, paramName, token, { clearParams } );
				activeToken = token;
			}

			openItem?.( item, trigger );
		},
		syncFromUrl,
	};
}
