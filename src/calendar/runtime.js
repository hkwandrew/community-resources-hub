import {
	bootCalendarIntegration,
	replaceCalendarNavButtonIcons,
} from './toolbar-filter.js';

const REGION_SELECTOR = '[data-wm-bci-calendar-region]';
const CONTROLLER_SELECTOR = '[data-wm-bci-controller="bci-resources"]';
const DAY_FRAME_SELECTOR = '.fc-daygrid-day-frame';
const DAY_EVENTS_SELECTOR = '.fc-daygrid-day-events';
const HARNESS_CLASS = 'fc-daygrid-event-harness';
const ABS_HARNESS_CLASS = 'fc-daygrid-event-harness-abs';
const EVENT_SELECTOR = '.fc-daygrid-event, .fc-daygrid-dot-event';
const HAS_ABSOLUTE_EVENTS_CLASS = 'wm-bci-calendar-has-abs-events';
const ABSOLUTE_EVENT_OFFSET_CLASS = 'wm-bci-calendar-abs-offset';
const EVENT_GAP_PX = 4;
const ORIGINAL_MARGIN_TOP_DATASET_KEY = 'wmBciOriginalMarginTop';
const ORIGINAL_ABSOLUTE_MARGIN_TOP_DATASET_KEY =
	'wmBciOriginalAbsoluteMarginTop';
const EMPTY_MARGIN_TOP_SENTINEL = '__wm_bci_empty_margin_top__';
const runtimeState = new WeakMap();

function fallbackWindow() {
	return typeof window === 'undefined' ? null : window;
}

function getRuntimeWindow( targetWindow ) {
	if ( ! targetWindow ) {
		targetWindow = fallbackWindow();
	}

	return targetWindow && targetWindow.document ? targetWindow : null;
}

function getState( targetWindow ) {
	let state = runtimeState.get( targetWindow );

	if ( state ) {
		return state;
	}

	state = {
		initialized: false,
		observedCalendars: new WeakSet(),
		observer: null,
		pending: false,
		tippyDefaultsApplied: false,
	};

	runtimeState.set( targetWindow, state );

	return state;
}

function calendarIntegrationState( section ) {
	if (
		! section.__crhCalendarRuntimeState ||
		'object' !== typeof section.__crhCalendarRuntimeState
	) {
		section.__crhCalendarRuntimeState = {
			selectedMembers: [],
			selectedTypes: [],
		};
	}

	return section.__crhCalendarRuntimeState;
}

function calendarRegions( root ) {
	const regions = [];

	if ( root.matches?.( REGION_SELECTOR ) ) {
		regions.push( root );
	}

	root.querySelectorAll?.( REGION_SELECTOR ).forEach( ( region ) => {
		regions.push( region );
	} );

	return regions;
}

function bootCalendarSections( root, targetWindow ) {
	calendarRegions( root ).forEach( ( region ) => {
		const section = region.closest?.( CONTROLLER_SELECTOR );

		if ( ! section ) {
			return;
		}

		if ( targetWindow ) {
			section.__crhScheduleCalendarRuntime = () => {
				requestSettledCalendarRuntime( targetWindow );
			};
		}

		bootCalendarIntegration( section, calendarIntegrationState( section ) );
	} );
}

function requestFrame( targetWindow, callback ) {
	targetWindow.setTimeout( callback, 0 );
}

function requestSettledCalendarRuntime( targetWindow ) {
	scheduleCalendarRuntime( targetWindow );
	targetWindow.setTimeout( () => {
		scheduleCalendarRuntime( targetWindow );
	}, 150 );
	targetWindow.setTimeout( () => {
		scheduleCalendarRuntime( targetWindow );
	}, 900 );
	targetWindow.setTimeout( () => {
		scheduleCalendarRuntime( targetWindow );
	}, 5000 );
}

function bindObservers( targetWindow ) {
	const state = getState( targetWindow );

	targetWindow.document
		.querySelectorAll( `${ REGION_SELECTOR } .gv-fullcalendar` )
		.forEach( ( calendar ) => {
			if ( state.observedCalendars.has( calendar ) ) {
				return;
			}

			state.observer.observe( calendar, {
				subtree: true,
				childList: true,
			} );
			state.observedCalendars.add( calendar );
		} );
}

function shouldScheduleCalendarRuntime( mutations ) {
	if ( ! mutations || ! mutations.length ) {
		return true;
	}

	return Array.prototype.some.call( mutations, ( mutation ) => {
		const target = mutation?.target;

		if ( target?.closest?.( `${ REGION_SELECTOR } .fc-toolbar` ) ) {
			return false;
		}

		return true;
	} );
}

function parsePixelValue( value ) {
	const parsed = Number.parseFloat( value );

	return Number.isFinite( parsed ) ? parsed : 0;
}

function formatPixelValue( value ) {
	const rounded = Math.round( value * 100 ) / 100;

	return `${ Object.is( rounded, -0 ) ? 0 : rounded }px`;
}

function visibleRect( element ) {
	if ( ! element ) {
		return null;
	}

	const targetWindow = element.ownerDocument?.defaultView;
	const computedStyle =
		targetWindow && 'function' === typeof targetWindow.getComputedStyle
			? targetWindow.getComputedStyle( element )
			: null;

	if (
		computedStyle &&
		( 'none' === computedStyle.display ||
			'hidden' === computedStyle.visibility ||
			'0' === computedStyle.opacity )
	) {
		return null;
	}

	const rect = element?.getBoundingClientRect?.();

	if ( ! rect || rect.width <= 0 || rect.height <= 0 ) {
		return null;
	}

	return rect;
}

function rectsIntersect( firstRect, secondRect ) {
	return (
		Math.min( firstRect.right, secondRect.right ) >
			Math.max( firstRect.left, secondRect.left ) &&
		Math.min( firstRect.bottom, secondRect.bottom ) >
			Math.max( firstRect.top, secondRect.top )
	);
}

function eventHarnesses( stack, includeAbsolute ) {
	return Array.from( stack.children ).filter( ( child ) => {
		if ( ! child.classList?.contains( HARNESS_CLASS ) ) {
			return false;
		}

		return includeAbsolute
			? child.classList.contains( ABS_HARNESS_CLASS )
			: ! child.classList.contains( ABS_HARNESS_CLASS );
	} );
}

function savedOriginalMarginTop(
	element,
	datasetKey = ORIGINAL_MARGIN_TOP_DATASET_KEY
) {
	if ( ! Object.hasOwn( element.dataset, datasetKey ) ) {
		return null;
	}

	return element.dataset[ datasetKey ];
}

function rememberOriginalMarginTop(
	element,
	datasetKey = ORIGINAL_MARGIN_TOP_DATASET_KEY
) {
	if ( null !== savedOriginalMarginTop( element, datasetKey ) ) {
		return;
	}

	element.dataset[ datasetKey ] =
		element.style.marginTop || EMPTY_MARGIN_TOP_SENTINEL;
}

function restoreOriginalMarginTop(
	element,
	datasetKey = ORIGINAL_MARGIN_TOP_DATASET_KEY
) {
	const originalMarginTop = savedOriginalMarginTop( element, datasetKey );

	if ( null === originalMarginTop ) {
		return;
	}

	if ( EMPTY_MARGIN_TOP_SENTINEL === originalMarginTop ) {
		element.style.removeProperty( 'margin-top' );
	} else {
		element.style.marginTop = originalMarginTop;
	}

	delete element.dataset[ datasetKey ];
}

function resetFlowHarnessMargins( stack ) {
	eventHarnesses( stack, false ).forEach( ( harness ) => {
		restoreOriginalMarginTop( harness );
	} );
}

function resetAbsoluteHarnessOffsets( calendar ) {
	calendar
		.querySelectorAll(
			`${ DAY_EVENTS_SELECTOR } > .${ ABS_HARNESS_CLASS }`
		)
		.forEach( ( harness ) => {
			harness.classList.remove( ABSOLUTE_EVENT_OFFSET_CLASS );
			restoreOriginalMarginTop(
				harness,
				ORIGINAL_ABSOLUTE_MARGIN_TOP_DATASET_KEY
			);
		} );
}

function harnessEventEntry( harness ) {
	const eventElement = harness.querySelector( EVENT_SELECTOR );
	const harnessRect = visibleRect( harness );
	const eventRect = visibleRect( eventElement || harness );

	if ( ! harnessRect || ! eventRect ) {
		return null;
	}

	return {
		eventRect,
		eventElement,
		harness,
		harnessRect,
	};
}

function calendarAbsoluteEventEntries( calendar ) {
	return Array.from(
		calendar.querySelectorAll(
			`${ DAY_EVENTS_SELECTOR } > .${ ABS_HARNESS_CLASS }`
		)
	)
		.map( ( harness ) => harnessEventEntry( harness ) )
		.filter( Boolean );
}

function rectsOverlapHorizontally( firstRect, secondRect ) {
	return (
		Math.min( firstRect.right, secondRect.right ) >
		Math.max( firstRect.left, secondRect.left )
	);
}

function normalizeAbsoluteEventRows( entries, calendar ) {
	const rowEntries = new Map();

	entries.forEach( ( entry ) => {
		const row = entry.harness.closest?.( 'tr' ) || calendar;

		if ( ! rowEntries.has( row ) ) {
			rowEntries.set( row, [] );
		}

		rowEntries.get( row ).push( entry );
	} );

	rowEntries.forEach( ( entriesInRow ) => {
		const baseTop = Math.min(
			...entriesInRow.map( ( entry ) => entry.eventRect.top )
		);
		const placedEntries = [];

		entriesInRow
			.slice()
			.sort( ( firstEntry, secondEntry ) => {
				const topDifference =
					firstEntry.eventRect.top - secondEntry.eventRect.top;

				return 0 !== topDifference
					? topDifference
					: firstEntry.eventRect.left - secondEntry.eventRect.left;
			} )
			.forEach( ( entry ) => {
				let targetTop = baseTop;

				placedEntries.forEach( ( placedEntry ) => {
					if (
						rectsOverlapHorizontally(
							entry.eventRect,
							placedEntry.eventRect
						)
					) {
						targetTop = Math.max(
							targetTop,
							placedEntry.eventRect.bottom + EVENT_GAP_PX
						);
					}
				} );

				const nextOffset = targetTop - entry.eventRect.top;

				if (
					Number.isFinite( nextOffset ) &&
					Math.abs( nextOffset ) >= 0.5
				) {
					const originalMarginTop = parsePixelValue(
						entry.harness.style.marginTop
					);

					rememberOriginalMarginTop(
						entry.harness,
						ORIGINAL_ABSOLUTE_MARGIN_TOP_DATASET_KEY
					);
					entry.harness.style.marginTop = formatPixelValue(
						originalMarginTop + nextOffset
					);
					entry.harness.classList.add( ABSOLUTE_EVENT_OFFSET_CLASS );
				}

				entry.eventRect = {
					bottom: entry.eventRect.bottom + nextOffset,
					height: entry.eventRect.height,
					left: entry.eventRect.left,
					right: entry.eventRect.right,
					top: entry.eventRect.top + nextOffset,
					width: entry.eventRect.width,
				};
				placedEntries.push( entry );
			} );
	} );
}

function normalizeFlowEntryGapStack( entries, initialBottom ) {
	let cumulativeShift = 0;
	let previousBottom = initialBottom;

	entries
		.slice()
		.sort( ( firstEntry, secondEntry ) => {
			return firstEntry.eventRect.top - secondEntry.eventRect.top;
		} )
		.forEach( ( entry ) => {
			const originalMarginTop = parsePixelValue(
				entry.harness.style.marginTop
			);
			const adjustedTop = entry.eventRect.top + cumulativeShift;
			const adjustedBottom = entry.eventRect.bottom + cumulativeShift;
			const naturalGap = adjustedTop - previousBottom;
			const nextOffset = EVENT_GAP_PX - naturalGap;

			if (
				Number.isFinite( nextOffset ) &&
				Math.abs( nextOffset ) >= 0.5
			) {
				rememberOriginalMarginTop( entry.harness );
				entry.harness.style.marginTop = formatPixelValue(
					originalMarginTop + nextOffset
				);
			}

			cumulativeShift += nextOffset;
			previousBottom = adjustedBottom + nextOffset;
		} );

	return previousBottom;
}

function normalizeCalendarEventSpacing( calendar ) {
	resetAbsoluteHarnessOffsets( calendar );
	const absoluteEntries = calendarAbsoluteEventEntries( calendar );

	normalizeAbsoluteEventRows( absoluteEntries, calendar );

	calendar.querySelectorAll( DAY_FRAME_SELECTOR ).forEach( ( dayFrame ) => {
		const dayRect = visibleRect( dayFrame );
		const stack = dayFrame.querySelector( DAY_EVENTS_SELECTOR );
		const flowHarnesses = stack ? eventHarnesses( stack, false ) : [];

		if ( stack ) {
			resetFlowHarnessMargins( stack );
		}

		dayFrame.classList.remove( HAS_ABSOLUTE_EVENTS_CLASS );

		if ( ! dayRect || ! flowHarnesses.length ) {
			return;
		}

		const crossingAbsoluteEntries = absoluteEntries.filter( ( entry ) =>
			rectsIntersect( entry.eventRect, dayRect )
		);

		if ( ! crossingAbsoluteEntries.length ) {
			return;
		}

		dayFrame.classList.add( HAS_ABSOLUTE_EVENTS_CLASS );

		const flowEntries = flowHarnesses
			.map( ( harness ) => harnessEventEntry( harness ) )
			.filter( Boolean );

		if ( ! flowEntries.length ) {
			return;
		}

		const lowestAbsoluteBottom = Math.max(
			...crossingAbsoluteEntries.map(
				( entry ) => entry.eventRect.bottom
			)
		);

		normalizeFlowEntryGapStack( flowEntries, lowestAbsoluteBottom );
	} );
}

export function applyCalendarTooltipDefaults( targetWindow ) {
	const runtimeWindow = getRuntimeWindow( targetWindow );

	if ( ! runtimeWindow ) {
		return;
	}

	const state = getState( runtimeWindow );

	if ( state.tippyDefaultsApplied ) {
		return;
	}

	if (
		runtimeWindow.gv_calendar_tippy &&
		runtimeWindow.gv_calendar_tippy.setDefaultProps
	) {
		runtimeWindow.gv_calendar_tippy.setDefaultProps( {
			appendTo() {
				return runtimeWindow.document.body;
			},
			zIndex: 99999,
		} );
	}

	state.tippyDefaultsApplied = true;
}

export function normalizeCalendarRuntime( root ) {
	if ( ! root || ! root.querySelectorAll ) {
		return;
	}

	const runtimeWindow = getRuntimeWindow(
		root.defaultView || root.ownerDocument?.defaultView
	);

	bootCalendarSections( root, runtimeWindow );

	root.querySelectorAll( `${ REGION_SELECTOR } .gv-fullcalendar` ).forEach(
		( calendar ) => {
			normalizeCalendarEventSpacing( calendar );
			replaceCalendarNavButtonIcons( calendar );
		}
	);
}

export function scheduleCalendarRuntime( targetWindow ) {
	const runtimeWindow = getRuntimeWindow( targetWindow );

	if ( ! runtimeWindow ) {
		return;
	}

	const state = getState( runtimeWindow );

	if ( state.pending ) {
		return;
	}

	state.pending = true;

	requestFrame( runtimeWindow, () => {
		state.pending = false;
		bindObservers( runtimeWindow );
		normalizeCalendarRuntime( runtimeWindow.document );
	} );
}

export function initBciCalendarRuntime( targetWindow ) {
	const runtimeWindow = getRuntimeWindow( targetWindow );

	if ( ! runtimeWindow ) {
		return;
	}

	const state = getState( runtimeWindow );

	applyCalendarTooltipDefaults( runtimeWindow );
	bootCalendarSections( runtimeWindow.document, runtimeWindow );

	if ( ! state.observer ) {
		state.observer = new runtimeWindow.MutationObserver( ( mutations ) => {
			if ( shouldScheduleCalendarRuntime( mutations ) ) {
				scheduleCalendarRuntime( runtimeWindow );
			}
		} );
	}

	if ( ! state.initialized ) {
		const documentTarget = runtimeWindow.document;
		const schedule = () => requestSettledCalendarRuntime( runtimeWindow );
		let viewportRefreshTimeout = null;
		const scheduleViewportRefresh = () => {
			if ( null !== viewportRefreshTimeout ) {
				runtimeWindow.clearTimeout( viewportRefreshTimeout );
			}

			viewportRefreshTimeout = runtimeWindow.setTimeout( () => {
				viewportRefreshTimeout = null;
				scheduleCalendarRuntime( runtimeWindow );
			}, 80 );
		};

		if ( documentTarget.readyState === 'loading' ) {
			documentTarget.addEventListener( 'DOMContentLoaded', schedule, {
				once: true,
			} );
		}

		runtimeWindow.addEventListener( 'load', schedule, { once: true } );
		documentTarget.addEventListener( 'gform/post_render', schedule );
		documentTarget.addEventListener( 'scroll', scheduleViewportRefresh, {
			passive: true,
		} );
		runtimeWindow.addEventListener( 'resize', scheduleViewportRefresh );
		runtimeWindow.addEventListener( 'scroll', scheduleViewportRefresh, {
			passive: true,
		} );
		documentTarget.addEventListener( 'click', ( event ) => {
			if (
				event.target &&
				event.target.closest &&
				event.target.closest( `${ REGION_SELECTOR } .fc-button` )
			) {
				runtimeWindow.setTimeout( schedule, 50 );
			}
		} );

		state.initialized = true;
	}

	requestSettledCalendarRuntime( runtimeWindow );
}
