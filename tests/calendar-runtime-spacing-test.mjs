/* eslint-env node */
/* global globalThis */
/* eslint-disable no-console */

import assert from 'node:assert/strict';

function rect( left, top, width, height ) {
	const bounds = {};

	Object.defineProperties( bounds, {
		bottom: { value: top + height },
		height: { value: height },
		left: { value: left },
		right: { value: left + width },
		top: { value: top },
		width: { value: width },
	} );

	return bounds;
}

function classList( ...initialClasses ) {
	const classes = new Set( initialClasses );

	return {
		add( className ) {
			classes.add( className );
		},
		contains( className ) {
			return classes.has( className );
		},
		remove( className ) {
			classes.delete( className );
		},
	};
}

function style() {
	return {
		marginTop: '',
		removeProperty( property ) {
			if ( 'margin-top' === property ) {
				this.marginTop = '';
			}
		},
	};
}

function element( classes, bounds ) {
	return {
		children: [],
		classList: classList( ...classes ),
		dataset: {},
		getBoundingClientRect() {
			return bounds;
		},
		ownerDocument: null,
		querySelector() {
			return null;
		},
		querySelectorAll() {
			return [];
		},
		style: style(),
	};
}

const absoluteEvent = element(
	[ 'fc-daygrid-event' ],
	rect( 100, 40, 100, 24 )
);
const absoluteHarness = element(
	[ 'fc-daygrid-event-harness', 'fc-daygrid-event-harness-abs' ],
	rect( 100, 40, 100, 24 )
);
absoluteHarness.querySelector = () => absoluteEvent;

const lateAbsoluteEvent = element(
	[ 'fc-daygrid-event' ],
	rect( 100, 90, 100, 24 )
);
const lateAbsoluteHarness = element(
	[ 'fc-daygrid-event-harness', 'fc-daygrid-event-harness-abs' ],
	rect( 100, 90, 100, 24 )
);
lateAbsoluteHarness.querySelector = () => lateAbsoluteEvent;

const flowEvent = element( [ 'fc-daygrid-event' ], rect( 100, 144, 100, 24 ) );
const flowHarness = element(
	[ 'fc-daygrid-event-harness' ],
	rect( 100, 144, 100, 24 )
);
flowHarness.querySelector = () => flowEvent;

const eventStack = element(
	[ 'fc-daygrid-day-events' ],
	rect( 100, 40, 100, 128 )
);
eventStack.children = [ absoluteHarness, lateAbsoluteHarness, flowHarness ];

const dayFrame = element(
	[ 'fc-daygrid-day-frame' ],
	rect( 100, 0, 100, 200 )
);
dayFrame.querySelector = ( selector ) =>
	'.fc-daygrid-day-events' === selector ? eventStack : null;

const section = {
	querySelector( selector ) {
		return '.gv-fullcalendar' === selector ? calendar : null;
	},
};
const region = {
	closest( selector ) {
		return '[data-wm-bci-controller="bci-resources"]' === selector
			? section
			: null;
	},
};
const calendar = {
	closest( selector ) {
		return '[data-wm-bci-controller="bci-resources"]' === selector
			? section
			: null;
	},
	querySelector() {
		return null;
	},
	querySelectorAll( selector ) {
		if (
			'.fc-daygrid-day-events > .fc-daygrid-event-harness-abs' ===
			selector
		) {
			return [ absoluteHarness, lateAbsoluteHarness ];
		}

		if ( '.fc-daygrid-day-frame' === selector ) {
			return [ dayFrame ];
		}

		return [];
	},
};

const root = {
	defaultView: null,
	querySelectorAll( selector ) {
		if ( '[data-wm-bci-calendar-region]' === selector ) {
			return [ region ];
		}

		if ( '[data-wm-bci-calendar-region] .gv-fullcalendar' === selector ) {
			return [ calendar ];
		}

		return [];
	},
};
const runtimeWindow = {
	document: root,
	getComputedStyle( target ) {
		return {
			display: target.style.display || 'block',
			opacity: '1',
			visibility: 'visible',
		};
	},
};

root.defaultView = runtimeWindow;
[
	absoluteEvent,
	absoluteHarness,
	lateAbsoluteEvent,
	lateAbsoluteHarness,
	flowEvent,
	flowHarness,
	eventStack,
	dayFrame,
].forEach( ( target ) => {
	target.ownerDocument = {
		defaultView: runtimeWindow,
	};
} );

globalThis.window = runtimeWindow;

const { normalizeCalendarRuntime } = await import(
	'../src/calendar/runtime.js'
);

normalizeCalendarRuntime( root );

assert.equal(
	lateAbsoluteHarness.style.marginTop,
	'-22px',
	'Expected a later spanning event to move into the first free lane instead of retaining a phantom absolute-event slot.'
);
assert.equal(
	flowHarness.style.marginTop,
	'-48px',
	'Expected the shared runtime to close the phantom event slot after a spanning event inside the opportunity hub.'
);
assert.equal(
	dayFrame.classList.contains( 'wm-bci-calendar-has-abs-events' ),
	true,
	'Expected the opportunity-hub day frame to be marked when a spanning event crosses it.'
);

delete globalThis.window;

console.log( 'Calendar runtime spacing test passed.' );
