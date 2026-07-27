/* eslint-disable @wordpress/i18n-no-variables, @wordpress/i18n-text-domain */

function getI18n() {
	return 'undefined' !== typeof window ? window.wp?.i18n : null;
}

export function __( text, domain ) {
	const i18n = getI18n();

	return 'function' === typeof i18n?.__ ? i18n.__( text, domain ) : text;
}

export function _n( single, plural, count, domain ) {
	const i18n = getI18n();

	if ( 'function' === typeof i18n?._n ) {
		return i18n._n( single, plural, count, domain );
	}

	return 1 === Number( count ) ? single : plural;
}

export function sprintf( format, ...values ) {
	const i18n = getI18n();

	if ( 'function' === typeof i18n?.sprintf ) {
		return i18n.sprintf( format, ...values );
	}

	let valueIndex = 0;

	return String( format ).replace(
		/%((\d+)\$)?[sd]/g,
		( match, token, position ) => {
			const index = position ? Number( position ) - 1 : valueIndex++;
			const value = values[ index ];

			return undefined === value ? match : String( value );
		}
	);
}
