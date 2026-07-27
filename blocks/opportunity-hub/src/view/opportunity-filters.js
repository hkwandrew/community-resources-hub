import { __, _n, sprintf } from '../../../../src/shared/i18n.js';

import {
	DEFAULT_OPPORTUNITY_BATCH_SIZE,
	findOpportunity,
	normalizeSelectedTypeValues,
	supportsHoverablePointer,
} from './shared-utils.js';
import {
	bindOpportunityModalTriggers,
	hydrateOpportunityModal,
} from './opportunity-modal.js';
import {
	bindDialog,
	closeDialog,
	initSubmitModal,
	openDialog,
} from './submit-modal.js';
import { createModalUrlController } from '../../../../src/shared/modal-url-state.js';

const CALENDAR_SELECTOR = '.gv-fullcalendar';
const DAY_FRAME_SELECTOR = '.fc-daygrid-day-frame';
const DAY_EVENTS_SELECTOR = '.fc-daygrid-day-events';
const EVENT_SELECTOR = '.fc-daygrid-event, .fc-daygrid-dot-event';
const HARNESS_SELECTOR = '.fc-daygrid-event-harness';
const ABS_HARNESS_CLASS = 'fc-daygrid-event-harness-abs';
const HAS_ABSOLUTE_EVENTS_CLASS = 'wm-bci-calendar-has-abs-events';
const EVENT_GAP_PX = 4;
const ORIGINAL_MARGIN_DATASET_KEY = 'wmBciOpportunityHubOriginalMarginTop';
const EMPTY_MARGIN_SENTINEL = '__wm_bci_empty_margin_top__';
const CALENDAR_SPACING_DELAYS = [ 0, 150, 900, 5000 ];

function visibleRect( element ) {
	if ( ! element ) {
		return null;
	}

	const view = element.ownerDocument?.defaultView;
	const style =
		view && 'function' === typeof view.getComputedStyle
			? view.getComputedStyle( element )
			: null;

	if (
		style &&
		( 'none' === style.display ||
			'hidden' === style.visibility ||
			'0' === style.opacity )
	) {
		return null;
	}

	const rect = element.getBoundingClientRect();

	if ( rect.width <= 0 || rect.height <= 0 ) {
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

function parsePixelValue( value ) {
	const parsed = Number.parseFloat( value );

	return Number.isFinite( parsed ) ? parsed : 0;
}

function formatPixelValue( value ) {
	const rounded = Math.round( value * 100 ) / 100;

	return `${ Object.is( rounded, -0 ) ? 0 : rounded }px`;
}

function rememberOriginalMargin( harness ) {
	if ( Object.hasOwn( harness.dataset, ORIGINAL_MARGIN_DATASET_KEY ) ) {
		return;
	}

	harness.dataset[ ORIGINAL_MARGIN_DATASET_KEY ] =
		harness.style.marginTop || EMPTY_MARGIN_SENTINEL;
}

function restoreOriginalMargin( harness ) {
	if ( ! Object.hasOwn( harness.dataset, ORIGINAL_MARGIN_DATASET_KEY ) ) {
		return;
	}

	const originalMargin = harness.dataset[ ORIGINAL_MARGIN_DATASET_KEY ];

	if ( EMPTY_MARGIN_SENTINEL === originalMargin ) {
		harness.style.removeProperty( 'margin-top' );
	} else {
		harness.style.marginTop = originalMargin;
	}

	delete harness.dataset[ ORIGINAL_MARGIN_DATASET_KEY ];
}

function harnessEntry( harness ) {
	const eventElement = harness.querySelector( EVENT_SELECTOR );
	const eventRect = visibleRect( eventElement || harness );

	if ( ! eventRect ) {
		return null;
	}

	return {
		eventRect,
		harness,
	};
}

function normalizeCalendarSectionSpacing( section ) {
	section.querySelectorAll( CALENDAR_SELECTOR ).forEach( ( calendar ) => {
		calendar
			.querySelectorAll(
				`${ DAY_EVENTS_SELECTOR } > ${ HARNESS_SELECTOR }:not(.${ ABS_HARNESS_CLASS })`
			)
			.forEach( restoreOriginalMargin );
		calendar
			.querySelectorAll( DAY_FRAME_SELECTOR )
			.forEach( ( dayFrame ) => {
				dayFrame.classList.remove( HAS_ABSOLUTE_EVENTS_CLASS );
			} );

		const absoluteEntries = Array.from(
			calendar.querySelectorAll(
				`${ DAY_EVENTS_SELECTOR } > .${ ABS_HARNESS_CLASS }`
			)
		)
			.map( harnessEntry )
			.filter( Boolean );

		calendar
			.querySelectorAll( DAY_FRAME_SELECTOR )
			.forEach( ( dayFrame ) => {
				const dayRect = visibleRect( dayFrame );
				const stack = dayFrame.querySelector( DAY_EVENTS_SELECTOR );

				if ( ! dayRect || ! stack ) {
					return;
				}

				const crossingAbsoluteEntries = absoluteEntries
					.filter( ( entry ) =>
						rectsIntersect( entry.eventRect, dayRect )
					)
					.sort( ( firstEntry, secondEntry ) => {
						return (
							firstEntry.eventRect.top - secondEntry.eventRect.top
						);
					} );

				if ( ! crossingAbsoluteEntries.length ) {
					return;
				}

				dayFrame.classList.add( HAS_ABSOLUTE_EVENTS_CLASS );
				const flowEntries = Array.from( stack.children )
					.filter( ( child ) => {
						return (
							child.matches?.( HARNESS_SELECTOR ) &&
							! child.classList.contains( ABS_HARNESS_CLASS )
						);
					} )
					.map( harnessEntry )
					.filter( Boolean )
					.sort( ( firstEntry, secondEntry ) => {
						return (
							firstEntry.eventRect.top - secondEntry.eventRect.top
						);
					} );

				if ( ! flowEntries.length ) {
					return;
				}

				const previousAbsoluteBottom = crossingAbsoluteEntries.reduce(
					( lowestBottom, entry ) =>
						Math.max( lowestBottom, entry.eventRect.bottom ),
					Number.NEGATIVE_INFINITY
				);
				let cumulativeShift = 0;
				let previousBottom = previousAbsoluteBottom;

				flowEntries.forEach( ( entry ) => {
					const adjustedTop = entry.eventRect.top + cumulativeShift;
					const adjustedBottom =
						entry.eventRect.bottom + cumulativeShift;
					const naturalGap = adjustedTop - previousBottom;
					const nextOffset = EVENT_GAP_PX - naturalGap;

					if (
						Number.isFinite( nextOffset ) &&
						Math.abs( nextOffset ) >= 0.5
					) {
						rememberOriginalMargin( entry.harness );
						entry.harness.style.marginTop = formatPixelValue(
							parsePixelValue( entry.harness.style.marginTop ) +
								nextOffset
						);
					}

					cumulativeShift += nextOffset;
					previousBottom = adjustedBottom + nextOffset;
				} );
			} );
	} );
}

function bindCalendarSpacingScheduler( section ) {
	if ( section.__crhCalendarSpacingScheduler ) {
		return section.__crhCalendarSpacingScheduler;
	}

	const scheduledHandles = [];
	const view = section.ownerDocument?.defaultView;
	const schedule = () => {
		while ( scheduledHandles.length ) {
			view.clearTimeout( scheduledHandles.pop() );
		}

		CALENDAR_SPACING_DELAYS.forEach( ( delay ) => {
			scheduledHandles.push(
				view.setTimeout( () => {
					section.__crhCalendarSpacingApplying = true;
					normalizeCalendarSectionSpacing( section );
					view.setTimeout( () => {
						section.__crhCalendarSpacingApplying = false;
					}, 0 );
				}, delay )
			);
		} );
	};

	const scheduleViewportRefresh = () => {
		view.clearTimeout( section.__crhCalendarSpacingViewportHandle || 0 );
		section.__crhCalendarSpacingViewportHandle = view.setTimeout( () => {
			normalizeCalendarSectionSpacing( section );
		}, 80 );
	};

	view.addEventListener( 'resize', scheduleViewportRefresh );
	view.addEventListener( 'scroll', scheduleViewportRefresh, {
		passive: true,
	} );

	const observer = new view.MutationObserver( ( mutations ) => {
		if ( section.__crhCalendarSpacingApplying ) {
			return;
		}

		const shouldSchedule = mutations.some( ( mutation ) => {
			const target = mutation?.target;

			if ( target?.closest?.( '.fc-toolbar' ) ) {
				return false;
			}

			return target?.closest?.( CALENDAR_SELECTOR );
		} );

		if ( shouldSchedule ) {
			schedule();
		}
	} );

	section.querySelectorAll( CALENDAR_SELECTOR ).forEach( ( calendar ) => {
		observer.observe( calendar, {
			attributeFilter: [ 'class', 'style' ],
			attributes: true,
			childList: true,
			subtree: true,
		} );
	} );

	section.__crhCalendarSpacingScheduler = schedule;
	section.__crhCalendarSpacingObserver = observer;

	return schedule;
}

function opportunityBatchSize( section ) {
	const parsedValue = Number.parseInt(
		section?.dataset?.wmBciOpportunityBatchSize || '',
		10
	);

	return Number.isInteger( parsedValue ) && parsedValue > 0
		? parsedValue
		: DEFAULT_OPPORTUNITY_BATCH_SIZE;
}

function parsePayload( payloadNode ) {
	if ( ! payloadNode ) {
		return {
			opportunities: [],
			members: [],
			types: [],
		};
	}

	try {
		const payload = JSON.parse( payloadNode.textContent || '{}' );

		return {
			opportunities: Array.isArray( payload?.opportunities )
				? payload.opportunities
				: [],
			members: Array.isArray( payload?.members ) ? payload.members : [],
			types: Array.isArray( payload?.types ) ? payload.types : [],
		};
	} catch ( error ) {
		return {
			opportunities: [],
			members: [],
			types: [],
		};
	}
}

function syncFilterLabels( section, state ) {
	const typeLabel = section.querySelector(
		'[data-wm-bci-type-filter-label]'
	);
	const memberLabel = section.querySelector(
		'[data-wm-bci-member-filter-label]'
	);

	if ( typeLabel ) {
		if ( ! state.selectedTypes.length ) {
			typeLabel.textContent = __(
				'All Types',
				'community-resources-hub'
			);
		} else if ( 1 === state.selectedTypes.length ) {
			const input = section.querySelector(
				`[data-wm-bci-type-filter-input][value="${ state.selectedTypes[ 0 ] }"]`
			);

			typeLabel.textContent =
				input?.parentElement?.querySelector( 'span' )?.textContent ||
				__( 'All Types', 'community-resources-hub' );
		} else {
			typeLabel.textContent = sprintf(
				/* translators: %d: selected type count. */
				_n(
					'%d Type',
					'%d Types',
					state.selectedTypes.length,
					'community-resources-hub'
				),
				state.selectedTypes.length
			);
		}
	}

	if ( memberLabel ) {
		if ( ! state.selectedMembers.length ) {
			memberLabel.textContent = __(
				'All Members',
				'community-resources-hub'
			);
		} else if ( 1 === state.selectedMembers.length ) {
			const input = section.querySelector(
				`[data-wm-bci-member-checkbox][value="${ state.selectedMembers[ 0 ] }"]`
			);

			memberLabel.textContent =
				input?.parentElement?.querySelector( 'span' )?.textContent ||
				__( 'All Members', 'community-resources-hub' );
		} else {
			memberLabel.textContent = sprintf(
				/* translators: %d: selected member count. */
				_n(
					'%d Member',
					'%d Members',
					state.selectedMembers.length,
					'community-resources-hub'
				),
				state.selectedMembers.length
			);
		}
	}
}

function syncMemberInputs( section, state ) {
	const selectedMembers = new Set( state.selectedMembers );

	section
		.querySelectorAll( '[data-wm-bci-member-checkbox]' )
		.forEach( ( input ) => {
			input.checked = selectedMembers.has( input.value );
		} );
}

function syncTypeInputs( section, state ) {
	const selectedTypes = new Set( state.selectedTypes );
	const allInput = section.querySelector( '[data-wm-bci-type-filter-all]' );

	if ( allInput ) {
		allInput.checked = ! state.selectedTypes.length;
	}

	section
		.querySelectorAll( '[data-wm-bci-type-filter-input]' )
		.forEach( ( input ) => {
			input.checked = selectedTypes.has( input.value );
		} );
}

function syncClearFiltersButton( section, state ) {
	const clearFilters = section.querySelector( '[data-wm-bci-clear-filters]' );

	if ( ! clearFilters ) {
		return;
	}

	const shouldHide =
		! state.selectedTypes.length && ! state.selectedMembers.length;

	if ( clearFilters.hidden !== shouldHide ) {
		clearFilters.hidden = shouldHide;
	}
}

function closeOpportunityFilterDropdown( dropdown ) {
	if ( ! dropdown ) {
		return;
	}

	const trigger = dropdown.querySelector(
		'.wm-bci-opportunities__filter-trigger'
	);
	const panel = dropdown.querySelector(
		'.wm-bci-opportunities__filter-panel'
	);

	dropdown.open = false;

	if ( trigger ) {
		trigger.setAttribute( 'aria-expanded', 'false' );
	}

	if ( panel ) {
		panel.setAttribute( 'aria-hidden', 'true' );
	}
}

function openOpportunityFilterDropdown( section, dropdown ) {
	if ( ! section || ! dropdown ) {
		return;
	}

	section
		.querySelectorAll( '[data-wm-bci-opportunity-filter-dropdown]' )
		.forEach( ( sibling ) => {
			if ( sibling === dropdown ) {
				return;
			}

			delete sibling.__crhManualHoverClose;
			closeOpportunityFilterDropdown( sibling );
		} );

	const trigger = dropdown.querySelector(
		'.wm-bci-opportunities__filter-trigger'
	);
	const panel = dropdown.querySelector(
		'.wm-bci-opportunities__filter-panel'
	);

	dropdown.open = true;
	delete dropdown.__crhManualHoverClose;

	if ( trigger ) {
		trigger.setAttribute( 'aria-expanded', 'true' );
	}

	if ( panel ) {
		panel.setAttribute( 'aria-hidden', 'false' );
	}
}

function bindOpportunityFilterDropdowns( section ) {
	if ( ! section || section.dataset.crhFilterDropdownsBound ) {
		return;
	}

	const ownerDocument = section.ownerDocument || document;
	const dropdowns = Array.from(
		section.querySelectorAll( '[data-wm-bci-opportunity-filter-dropdown]' )
	);

	dropdowns.forEach( ( dropdown ) => {
		const trigger = dropdown.querySelector(
			'.wm-bci-opportunities__filter-trigger'
		);

		closeOpportunityFilterDropdown( dropdown );

		if ( ! trigger ) {
			return;
		}

		dropdown.addEventListener( 'pointerenter', () => {
			dropdown.__crhPointerInside = true;

			if (
				! supportsHoverablePointer( dropdown ) ||
				dropdown.__crhManualHoverClose
			) {
				return;
			}

			openOpportunityFilterDropdown( section, dropdown );
		} );

		dropdown.addEventListener( 'pointerleave', () => {
			dropdown.__crhPointerInside = false;
			delete dropdown.__crhManualHoverClose;
			closeOpportunityFilterDropdown( dropdown );
		} );

		trigger.addEventListener( 'click', ( event ) => {
			event.preventDefault();

			if ( dropdown.open ) {
				if (
					supportsHoverablePointer( dropdown ) &&
					dropdown.__crhPointerInside
				) {
					dropdown.__crhManualHoverClose = true;
				}

				closeOpportunityFilterDropdown( dropdown );
				return;
			}

			openOpportunityFilterDropdown( section, dropdown );
		} );
	} );

	ownerDocument.addEventListener( 'click', ( event ) => {
		dropdowns.forEach( ( dropdown ) => {
			if ( ! dropdown.open || dropdown.contains( event.target ) ) {
				return;
			}

			delete dropdown.__crhManualHoverClose;
			closeOpportunityFilterDropdown( dropdown );
		} );
	} );

	section.dataset.crhFilterDropdownsBound = 'true';
}

export function applyOpportunityFilters(
	section,
	payload = section?.__crhPayload,
	state = section?.__crhState
) {
	if ( ! section || ! payload || ! state ) {
		return {
			filteredCount: 0,
			visibleCount: 0,
			batchSize: DEFAULT_OPPORTUNITY_BATCH_SIZE,
		};
	}

	const cards = Array.from(
		section.querySelectorAll( '[data-wm-bci-opportunity-card]' )
	);
	const empty = section.querySelector( '[data-wm-bci-opportunity-empty]' );
	const loadMoreWrap = section.querySelector(
		'[data-wm-bci-load-more-wrap]'
	);
	const batchSize = opportunityBatchSize( section );
	let visibleCount = 0;
	let filteredCount = 0;

	state.selectedTypes = normalizeSelectedTypeValues( state.selectedTypes );
	state.selectedMembers = Array.isArray( state.selectedMembers )
		? Array.from(
				new Set( state.selectedMembers.map( String ).filter( Boolean ) )
		  )
		: [];
	cards.forEach( ( card ) => {
		const opportunity = findOpportunity(
			payload,
			card.dataset.opportunityId
		);
		const memberVisible =
			! state.selectedMembers.length ||
			state.selectedMembers.includes( card.dataset.memberSlug || '' );
		const typeVisible =
			! state.selectedTypes.length ||
			state.selectedTypes.includes( card.dataset.typeSlug || '' );
		const shouldShow = Boolean(
			opportunity && memberVisible && typeVisible
		);

		if ( shouldShow ) {
			filteredCount++;
		}

		if ( shouldShow && visibleCount < state.visibleCount ) {
			card.hidden = false;
			visibleCount++;
		} else {
			card.hidden = true;
		}
	} );

	if ( empty ) {
		empty.classList.toggle( 'is-hidden', filteredCount > 0 );
	}

	if ( loadMoreWrap ) {
		loadMoreWrap.classList.toggle(
			'is-hidden',
			filteredCount <= state.visibleCount
		);
	}

	syncFilterLabels( section, state );
	syncMemberInputs( section, state );
	syncTypeInputs( section, state );
	syncClearFiltersButton( section, state );

	return {
		filteredCount,
		visibleCount,
		batchSize,
	};
}

export function initOpportunityHub( section ) {
	if ( ! section || section.dataset.crhInitialized ) {
		return;
	}

	const payloadNode = section.querySelector(
		'[data-wm-bci-opportunities-payload]'
	);
	const payload = parsePayload( payloadNode );
	const batchSize = opportunityBatchSize( section );
	const state = {
		selectedMembers: [],
		selectedTypes: [],
		visibleCount: batchSize,
	};
	const opportunityDialog = section.querySelector(
		'[data-wm-bci-opportunity-modal]'
	);

	section.__crhPayload = payload;
	section.__crhState = state;

	bindOpportunityFilterDropdowns( section );
	initSubmitModal( section );

	if ( opportunityDialog ) {
		bindDialog( opportunityDialog );
		const modalUrl = createModalUrlController( {
			ownerWindow: section.ownerDocument?.defaultView,
			paramName: 'bci-opportunity',
			clearParams: [ 'bci-member' ],
			items: payload.opportunities,
			modal: opportunityDialog,
			openItem: ( opportunity, trigger = null ) => {
				if ( opportunity ) {
					hydrateOpportunityModal( opportunityDialog, opportunity );
					openDialog( opportunityDialog, trigger );
				}
			},
			closeItem: () => closeDialog( opportunityDialog ),
		} );

		bindOpportunityModalTriggers(
			section,
			opportunityDialog,
			payload,
			( trigger, opportunity ) =>
				modalUrl.openWithUrl( opportunity, trigger )
		);
		modalUrl.syncFromUrl();
	}

	const memberCheckboxes = Array.from(
		section.querySelectorAll( '[data-wm-bci-member-checkbox]' )
	);
	const typeCheckboxes = Array.from(
		section.querySelectorAll( '[data-wm-bci-type-filter-input]' )
	);
	const typeAll = section.querySelector( '[data-wm-bci-type-filter-all]' );
	const clearFilters = section.querySelector( '[data-wm-bci-clear-filters]' );
	const loadMore = section.querySelector( '[data-wm-bci-load-more]' );
	const scheduleCalendarSpacing = bindCalendarSpacingScheduler( section );
	const rerender = () => applyOpportunityFilters( section, payload, state );

	section.__crhScheduleCalendarRuntime = scheduleCalendarSpacing;

	memberCheckboxes.forEach( ( checkbox ) => {
		checkbox.addEventListener( 'change', () => {
			state.selectedMembers = memberCheckboxes
				.filter( ( input ) => input.checked )
				.map( ( input ) => input.value );
			state.visibleCount = batchSize;
			rerender();
		} );
	} );

	if ( typeAll ) {
		typeAll.addEventListener( 'change', () => {
			state.selectedTypes = [];
			state.visibleCount = batchSize;
			rerender();
		} );
	}

	typeCheckboxes.forEach( ( checkbox ) => {
		checkbox.addEventListener( 'change', () => {
			state.selectedTypes = typeCheckboxes
				.filter( ( input ) => input.checked )
				.map( ( input ) => input.value );
			state.visibleCount = batchSize;
			rerender();
		} );
	} );

	if ( clearFilters ) {
		clearFilters.addEventListener( 'click', () => {
			state.selectedMembers = [];
			state.selectedTypes = [];
			state.visibleCount = batchSize;
			rerender();

			section
				.querySelectorAll( '[data-wm-bci-opportunity-filter-dropdown]' )
				.forEach( ( dropdown ) => {
					delete dropdown.__crhManualHoverClose;
					closeOpportunityFilterDropdown( dropdown );
				} );

			const typeTrigger = section.querySelector(
				'[data-wm-bci-type-filter-button]'
			);

			if ( 'function' === typeof typeTrigger?.focus ) {
				typeTrigger.focus();
			}
		} );
	}

	if ( loadMore ) {
		loadMore.addEventListener( 'click', () => {
			state.visibleCount += batchSize;
			rerender();
		} );
	}

	section.dataset.crhInitialized = 'true';
	rerender();
	scheduleCalendarSpacing();
}
