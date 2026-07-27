export function openDialog( dialog, trigger ) {
	if ( ! dialog ) {
		return;
	}

	dialog.__crhTrigger = trigger || null;

	const isNativeDialog = 'function' === typeof dialog.showModal;

	if ( isNativeDialog ) {
		if ( ! dialog.open ) {
			dialog.showModal();
		}
	} else {
		dialog.hidden = false;
		dialog.removeAttribute( 'hidden' );
		dialog.setAttribute( 'open', 'open' );

		if ( ! dialog.hasAttribute( 'tabindex' ) ) {
			dialog.setAttribute( 'tabindex', '-1' );
		}

		if ( 'function' === typeof dialog.focus ) {
			dialog.focus( { preventScroll: true } );
		}
	}

	if ( isNativeDialog ) {
		document.documentElement.classList.add( 'crh-dialog-open' );
	}
}

export function closeDialog( dialog ) {
	if ( ! dialog ) {
		return;
	}

	if ( 'function' === typeof dialog.close ) {
		dialog.close();
	} else {
		dialog.removeAttribute( 'open' );
		dialog.hidden = true;
		dialog.setAttribute( 'hidden', '' );
	}

	document.documentElement.classList.remove( 'crh-dialog-open' );
	const EventConstructor =
		dialog.ownerDocument?.defaultView?.CustomEvent ||
		( 'undefined' !== typeof CustomEvent ? CustomEvent : null );

	if ( EventConstructor ) {
		dialog.dispatchEvent( new EventConstructor( 'crh-dialog-close' ) );
	}

	if (
		dialog.__crhTrigger &&
		'function' === typeof dialog.__crhTrigger.focus
	) {
		dialog.__crhTrigger.focus();
	}
}

export function bindDialog( dialog ) {
	if ( ! dialog || dialog.dataset.crhDialogBound ) {
		return;
	}

	if ( 'function' === typeof dialog.showModal ) {
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				closeDialog( dialog );
			}
		} );

		dialog.addEventListener( 'cancel', () => {
			document.documentElement.classList.remove( 'crh-dialog-open' );
		} );
	} else {
		dialog.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				closeDialog( dialog );
			}
		} );
	}

	dialog
		.querySelectorAll( '[data-crh-dialog-close]' )
		.forEach( ( button ) => {
			button.addEventListener( 'click', () => closeDialog( dialog ) );
		} );

	dialog.dataset.crhDialogBound = 'true';
}
