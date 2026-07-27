import { __, _n, sprintf } from '../shared/i18n.js';

import {
	normalizeSelectedTypeValues,
	supportsHoverablePointer,
} from '../../blocks/opportunity-hub/src/view/shared-utils.js';

const ALL_CHECKBOX_SELECTOR =
	'[data-wm-bci-calendar-filter-all], [data-wm-bci-calendar-member-filter-all]';
const OPTION_CHECKBOX_SELECTOR =
	'[data-wm-bci-calendar-filter-checkbox], [data-wm-bci-calendar-member-filter-checkbox]';

function calendarNavMaskId( calendarElement, direction ) {
	const rawCalendarId = calendarElement?.dataset?.calendar_id || 'calendar';
	const calendarId =
		String( rawCalendarId ).replace( /[^A-Za-z0-9_-]/g, '-' ) || 'calendar';

	return `wm-bci-calendar-${ calendarId }-${ direction }-mask`;
}

function calendarNavIconSvg( direction, maskId ) {
	if ( 'prev' === direction ) {
		return [
			'<svg class="wm-bci-calendar-nav-icon" xmlns="http://www.w3.org/2000/svg" width="41" height="41" viewBox="0 0 41 41" fill="none" aria-hidden="true" focusable="false">',
			'<path d="M0.5 8.5C0.5 4.08172 4.08172 0.5 8.5 0.5H40.5V40.5H8.5C4.08172 40.5 0.5 36.9183 0.5 32.5V8.5Z" fill="white"/>',
			'<path d="M0.5 8.5C0.5 4.08172 4.08172 0.5 8.5 0.5H40.5V40.5H8.5C4.08172 40.5 0.5 36.9183 0.5 32.5V8.5Z" stroke="#DCDCDD"/>',
			`<mask id="${ maskId }" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="10" y="10" width="21" height="21">`,
			'<rect x="10.5" y="10.5" width="20" height="20" fill="#D9D9D9"/>',
			'</mask>',
			`<g mask="url(#${ maskId })">`,
			'<path d="M23.8333 28.8333L15.5 20.5L23.8333 12.1667L25.3125 13.6458L18.4583 20.5L25.3125 27.3542L23.8333 28.8333Z" fill="#26282A"/>',
			'</g>',
			'</svg>',
		].join( '' );
	}

	return [
		'<svg class="wm-bci-calendar-nav-icon" xmlns="http://www.w3.org/2000/svg" width="41" height="41" viewBox="0 0 41 41" fill="none" aria-hidden="true" focusable="false">',
		'<path d="M0.5 0.5H32.5C36.9183 0.5 40.5 4.08172 40.5 8.5V32.5C40.5 36.9183 36.9183 40.5 32.5 40.5H0.5V0.5Z" fill="white"/>',
		'<path d="M0.5 0.5H32.5C36.9183 0.5 40.5 4.08172 40.5 8.5V32.5C40.5 36.9183 36.9183 40.5 32.5 40.5H0.5V0.5Z" stroke="#DCDCDD"/>',
		`<mask id="${ maskId }" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="10" y="10" width="21" height="21">`,
		'<rect x="10.5" y="10.5" width="20" height="20" fill="#D9D9D9"/>',
		'</mask>',
		`<g mask="url(#${ maskId })">`,
		'<path d="M17.1823 28.8333L15.7031 27.3542L22.5573 20.5L15.7031 13.6458L17.1823 12.1667L25.5156 20.5L17.1823 28.8333Z" fill="#26282A"/>',
		'</g>',
		'</svg>',
	].join( '' );
}

function calendarFilterDimension( wrapper ) {
	return 'member' === wrapper?.dataset?.wmBciCalendarFilterDimension
		? 'member'
		: 'type';
}

function selectedValuesOptionKey( dimension ) {
	return 'member' === dimension
		? 'wmBciSelectedMemberValues'
		: 'wmBciSelectedTypeValues';
}

function selectedValuesStateKey( dimension ) {
	return 'member' === dimension ? 'selectedMembers' : 'selectedTypes';
}

function selectedValuesForRecord( calendarRecord, dimension ) {
	return normalizeSelectedTypeValues(
		calendarRecord?.extraOptions?.[
			selectedValuesOptionKey( dimension )
		] || []
	);
}

function setSelectedValuesForRecord(
	calendarRecord,
	dimension,
	selectedValues
) {
	if ( ! calendarRecord?.extraOptions ) {
		return [];
	}

	const nextSelectedValues = normalizeSelectedTypeValues( selectedValues );
	calendarRecord.extraOptions[ selectedValuesOptionKey( dimension ) ] =
		nextSelectedValues;

	return nextSelectedValues;
}

function calendarFilterLabel( selectedValues, defaultLabel, dimension ) {
	if ( ! selectedValues.length ) {
		return defaultLabel;
	}

	if ( 'member' === dimension ) {
		return sprintf(
			/* translators: %d: selected member count. */
			_n(
				'%d Member',
				'%d Members',
				selectedValues.length,
				'community-resources-hub'
			),
			selectedValues.length
		);
	}

	return sprintf(
		/* translators: %d: selected event type count. */
		_n(
			'%d Event Type',
			'%d Event Types',
			selectedValues.length,
			'community-resources-hub'
		),
		selectedValues.length
	);
}

function syncToolbarFilterUi( wrapper, selectedValues ) {
	const dimension = calendarFilterDimension( wrapper );
	const selectedValueSet = new Set( selectedValues );
	const label = wrapper.querySelector(
		'[data-wm-bci-calendar-filter-label]'
	);
	const allCheckbox = wrapper.querySelector( ALL_CHECKBOX_SELECTOR );
	const typeCheckboxes = wrapper.querySelectorAll( OPTION_CHECKBOX_SELECTOR );
	const defaultLabel =
		wrapper.__crhCalendarDefaultLabel ||
		( 'member' === dimension
			? __( 'All Members', 'community-resources-hub' )
			: __( 'All BCI Events', 'community-resources-hub' ) );
	const nextLabel = calendarFilterLabel(
		selectedValues,
		defaultLabel,
		dimension
	);

	if ( label && label.textContent !== nextLabel ) {
		label.textContent = nextLabel;
	}

	if ( allCheckbox ) {
		allCheckbox.checked = ! selectedValues.length;
	}

	typeCheckboxes.forEach( ( checkbox ) => {
		checkbox.checked = selectedValueSet.has( checkbox.value );
	} );
}

function syncCalendarClearFiltersButton( clearFilters, state ) {
	if ( ! clearFilters || ! state ) {
		return;
	}

	const shouldHide =
		! state.selectedTypes.length && ! state.selectedMembers.length;

	if ( clearFilters.hidden !== shouldHide ) {
		clearFilters.hidden = shouldHide;
	}
}

function closeToolbarFilter( wrapper ) {
	if ( ! wrapper ) {
		return;
	}

	wrapper.open = false;
	delete wrapper.__crhManualHoverClose;
	wrapper
		.querySelector( '[data-wm-bci-calendar-filter-button]' )
		?.setAttribute( 'aria-expanded', 'false' );
	wrapper
		.querySelector( '[data-wm-bci-calendar-filter-panel]' )
		?.setAttribute( 'aria-hidden', 'true' );
}

function describeEventTarget( target ) {
	if ( ! target || 'object' !== typeof target ) {
		return null;
	}

	const tagName =
		'string' === typeof target.tagName ? target.tagName.toLowerCase() : '';
	const className =
		'string' === typeof target.className ? target.className : '';
	const value = 'value' in target ? String( target.value || '' ) : '';

	return {
		tagName,
		className,
		value,
	};
}

function logCalendarFilterEvent() {
	return null;
}

function updateToolbarFilterSelection( wrapper, selectedValues ) {
	const calendarRecord = wrapper.__crhCalendarRecord || null;
	const dimension = calendarFilterDimension( wrapper );
	const allowedValues = new Set(
		Array.from(
			wrapper.querySelectorAll( OPTION_CHECKBOX_SELECTOR ),
			( checkbox ) => checkbox.value
		)
	);
	const nextSelectedValues = normalizeSelectedTypeValues(
		selectedValues
	).filter( ( value ) => allowedValues.has( value ) );
	const state = wrapper.__crhCalendarState || null;

	setSelectedValuesForRecord( calendarRecord, dimension, nextSelectedValues );

	if ( state ) {
		state[ selectedValuesStateKey( dimension ) ] = nextSelectedValues;
	}

	applyCalendarFilter(
		calendarRecord?.instance || null,
		selectedValuesForRecord( calendarRecord, 'type' ),
		selectedValuesForRecord( calendarRecord, 'member' )
	);
	syncToolbarFilterUi( wrapper, nextSelectedValues );
	syncCalendarClearFiltersButton(
		wrapper.__crhCalendarClearFilters || null,
		state
	);
}

function handleAllCheckboxChange(
	wrapper,
	checkbox,
	event,
	checkedOverride = null,
	label = 'all-checkbox-change'
) {
	const checked =
		null === checkedOverride ? !! checkbox?.checked : !! checkedOverride;

	if ( checkbox ) {
		checkbox.checked = checked;
	}

	logCalendarFilterEvent( wrapper, label, {
		eventType: event?.type || null,
		target: describeEventTarget( event?.target ),
		checked,
	} );

	updateToolbarFilterSelection( wrapper, [] );
}

function handleTypeCheckboxChange(
	wrapper,
	checkbox,
	event,
	checkedOverride = null,
	label = 'type-checkbox-change'
) {
	const checked =
		null === checkedOverride ? !! checkbox?.checked : !! checkedOverride;

	if ( checkbox ) {
		checkbox.checked = checked;
	}

	logCalendarFilterEvent( wrapper, label, {
		eventType: event?.type || null,
		target: describeEventTarget( event?.target ),
		checked,
	} );

	const selectedValues = selectedValuesForRecord(
		wrapper.__crhCalendarRecord || null,
		calendarFilterDimension( wrapper )
	);
	const nextSelectedValues = checked
		? selectedValues.concat( checkbox.value )
		: selectedValues.filter( ( value ) => value !== checkbox.value );

	updateToolbarFilterSelection( wrapper, nextSelectedValues );
}

function bindToolbarFilterOptions( wrapper ) {
	if ( wrapper.dataset.wmBciCalendarFilterBound ) {
		return;
	}

	const allCheckbox = wrapper.querySelector( ALL_CHECKBOX_SELECTOR );
	const typeCheckboxes = wrapper.querySelectorAll( OPTION_CHECKBOX_SELECTOR );

	if ( allCheckbox ) {
		allCheckbox.addEventListener( 'change', ( event ) => {
			handleAllCheckboxChange( wrapper, allCheckbox, event );
		} );
	}

	typeCheckboxes.forEach( ( checkbox ) => {
		checkbox.addEventListener( 'change', ( event ) => {
			handleTypeCheckboxChange( wrapper, checkbox, event );
		} );
	} );

	const optionRows = wrapper.querySelectorAll(
		'.wm-bci-calendar-toolbar-filter__option'
	);

	optionRows.forEach( ( optionRow ) => {
		const checkbox = optionRow.querySelector( 'input' );

		if ( ! checkbox ) {
			return;
		}

		optionRow.addEventListener( 'mousedown', ( event ) => {
			if ( 0 !== event.button ) {
				return;
			}

			event.preventDefault();
			wrapper.__crhOptionMouseTarget = checkbox;
			logCalendarFilterEvent( wrapper, 'option-mousedown-armed', {
				eventType: event.type,
				target: describeEventTarget( event.target ),
				checked: !! checkbox.checked,
				value: checkbox.value || '',
			} );
		} );

		optionRow.addEventListener( 'mouseup', ( event ) => {
			if (
				0 !== event.button ||
				wrapper.__crhOptionMouseTarget !== checkbox
			) {
				return;
			}

			event.preventDefault();
			delete wrapper.__crhOptionMouseTarget;
			wrapper.__crhIgnoreOptionClick = checkbox;

			if ( checkbox === allCheckbox ) {
				handleAllCheckboxChange(
					wrapper,
					allCheckbox,
					event,
					true,
					'all-option-mouseup-toggle'
				);
				return;
			}

			handleTypeCheckboxChange(
				wrapper,
				checkbox,
				event,
				! checkbox.checked,
				'type-option-mouseup-toggle'
			);
		} );

		optionRow.addEventListener( 'click', ( event ) => {
			if ( wrapper.__crhIgnoreOptionClick !== checkbox ) {
				return;
			}

			delete wrapper.__crhIgnoreOptionClick;
			event.preventDefault();
			event.stopPropagation();
			logCalendarFilterEvent( wrapper, 'option-click-ignored', {
				eventType: event.type,
				target: describeEventTarget( event.target ),
				checked: !! checkbox.checked,
				value: checkbox.value || '',
			} );
		} );
	} );

	wrapper.dataset.wmBciCalendarFilterBound = 'true';
}

function bindToolbarFilterTrigger( wrapper ) {
	if ( wrapper.dataset.wmBciCalendarFilterTriggerBound ) {
		return;
	}

	const button = wrapper.querySelector(
		'[data-wm-bci-calendar-filter-button]'
	);
	const panel = wrapper.querySelector(
		'[data-wm-bci-calendar-filter-panel]'
	);
	const ownerDocument = button?.ownerDocument || document;

	if ( ! button || ! panel ) {
		return;
	}

	const setPanelOpen = (
		isOpen,
		reason = 'set-panel-open',
		event = null
	) => {
		if ( isOpen ) {
			wrapper.parentElement
				?.querySelectorAll?.( '[data-wm-bci-calendar-filter]' )
				.forEach( ( sibling ) => {
					if ( sibling === wrapper || ! sibling.open ) {
						return;
					}

					sibling.open = false;
					sibling
						.querySelector( '[data-wm-bci-calendar-filter-button]' )
						?.setAttribute( 'aria-expanded', 'false' );
					sibling
						.querySelector( '[data-wm-bci-calendar-filter-panel]' )
						?.setAttribute( 'aria-hidden', 'true' );
				} );
		}

		wrapper.open = isOpen;
		button.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
		panel.setAttribute( 'aria-hidden', isOpen ? 'false' : 'true' );
		logCalendarFilterEvent( wrapper, reason, {
			eventType: event?.type || null,
			target: describeEventTarget( event?.target ),
		} );
	};

	const clearMouseToggle = (
		reason = 'mouse-toggle-cleared',
		event = null
	) => {
		delete wrapper.__crhMouseToggleArmed;
		logCalendarFilterEvent( wrapper, reason, {
			eventType: event?.type || null,
			target: describeEventTarget( event?.target ),
		} );
	};

	const markManualHoverClose = () => {
		if (
			supportsHoverablePointer( wrapper ) &&
			wrapper.__crhPointerInside
		) {
			wrapper.__crhManualHoverClose = true;
			return;
		}

		delete wrapper.__crhManualHoverClose;
	};

	wrapper.addEventListener( 'pointerenter', ( event ) => {
		wrapper.__crhPointerInside = true;

		if (
			! supportsHoverablePointer( wrapper ) ||
			wrapper.__crhManualHoverClose
		) {
			return;
		}

		setPanelOpen( true, 'hover-open', event );
	} );

	wrapper.addEventListener( 'pointerleave', ( event ) => {
		wrapper.__crhPointerInside = false;
		delete wrapper.__crhManualHoverClose;
		setPanelOpen( false, 'hover-close', event );
	} );

	ownerDocument.addEventListener( 'click', ( event ) => {
		if ( ! wrapper.open || wrapper.contains( event.target ) ) {
			return;
		}

		setPanelOpen( false, 'outside-click-close', event );
	} );

	ownerDocument.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' !== event.key || ! wrapper.open ) {
			return;
		}

		event.preventDefault();
		setPanelOpen( false, 'escape-close', event );

		if ( 'function' === typeof button.focus ) {
			button.focus();
		}
	} );

	ownerDocument.addEventListener( 'mouseup', ( event ) => {
		if (
			! wrapper.__crhMouseToggleArmed ||
			button.contains( event.target )
		) {
			return;
		}

		clearMouseToggle( 'mouse-toggle-cancelled', event );
	} );

	[ 'pointerdown', 'mousedown', 'click' ].forEach( ( eventName ) => {
		button.addEventListener( eventName, ( event ) => {
			logCalendarFilterEvent( wrapper, `trigger-${ eventName }`, {
				eventType: event.type,
				target: describeEventTarget( event.target ),
			} );
		} );
	} );

	button.addEventListener( 'mousedown', ( event ) => {
		if ( 0 !== event.button ) {
			return;
		}

		event.preventDefault();
		wrapper.__crhMouseToggleArmed = true;
		logCalendarFilterEvent( wrapper, 'trigger-mousedown-armed', {
			eventType: event.type,
			target: describeEventTarget( event.target ),
		} );

		if ( 'function' === typeof button.focus ) {
			button.focus();
		}
	} );

	button.addEventListener( 'mouseup', ( event ) => {
		if ( 0 !== event.button || ! wrapper.__crhMouseToggleArmed ) {
			return;
		}

		event.preventDefault();
		delete wrapper.__crhMouseToggleArmed;
		wrapper.__crhIgnoreNextClick = true;
		if ( wrapper.open ) {
			markManualHoverClose();
		} else {
			delete wrapper.__crhManualHoverClose;
		}
		setPanelOpen( ! wrapper.open, 'trigger-mouseup-toggle', event );
	} );

	button.addEventListener( 'click', ( event ) => {
		event.preventDefault();

		if ( wrapper.__crhIgnoreNextClick ) {
			delete wrapper.__crhIgnoreNextClick;
			logCalendarFilterEvent( wrapper, 'trigger-click-ignored', {
				eventType: event.type,
				target: describeEventTarget( event.target ),
			} );
			return;
		}

		if ( wrapper.open ) {
			markManualHoverClose();
		} else {
			delete wrapper.__crhManualHoverClose;
		}

		setPanelOpen( ! wrapper.open, 'trigger-toggle', event );
	} );

	wrapper.addEventListener( 'toggle', ( event ) => {
		logCalendarFilterEvent( wrapper, 'details-toggle', {
			eventType: event.type,
			target: describeEventTarget( event.target ),
		} );
	} );

	setPanelOpen( false, 'initial-bind' );

	wrapper.dataset.wmBciCalendarFilterTriggerBound = 'true';
}

function appendChildIfNeeded( parent, child ) {
	if ( child.parentElement === parent ) {
		return;
	}

	parent.appendChild( child );
}

function toolbarFilterGroup( toolbarChunk ) {
	const existingGroup = Array.from( toolbarChunk.children ).find( ( child ) =>
		child.hasAttribute?.( 'data-wm-bci-calendar-filter-group' )
	);

	if ( existingGroup ) {
		return existingGroup;
	}

	const group = toolbarChunk.ownerDocument.createElement( 'div' );
	group.className = 'wm-bci-calendar-toolbar-filters';
	group.setAttribute( 'data-wm-bci-calendar-filter-group', 'true' );

	const label = toolbarChunk.ownerDocument.createElement( 'span' );
	label.className =
		'wm-bci-calendar-toolbar-filters__label wm-bci-opportunities__filters-label';
	label.setAttribute( 'data-wm-bci-calendar-filter-group-label', 'true' );
	label.textContent = __( 'Filter by:', 'community-resources-hub' );
	group.appendChild( label );

	toolbarChunk.appendChild( group );

	return group;
}

function placeToolbarFilters( toolbarChunk, wrappers, clearFilters = null ) {
	const group = toolbarFilterGroup( toolbarChunk );

	wrappers.forEach( ( wrapper ) => {
		appendChildIfNeeded( group, wrapper );
	} );

	if ( clearFilters ) {
		appendChildIfNeeded( group, clearFilters );
	}

	return group;
}

function bindCalendarClearFilters(
	clearFilters,
	wrappers,
	calendarRecord,
	state
) {
	if ( ! clearFilters ) {
		return;
	}

	clearFilters.__crhCalendarFilterWrappers = wrappers;
	clearFilters.__crhCalendarRecord = calendarRecord;
	clearFilters.__crhCalendarState = state;

	if ( clearFilters.dataset.wmBciCalendarClearFiltersBound ) {
		return;
	}

	clearFilters.addEventListener( 'click', () => {
		const currentWrappers = clearFilters.__crhCalendarFilterWrappers || [];
		const currentRecord = clearFilters.__crhCalendarRecord || null;
		const currentState = clearFilters.__crhCalendarState || null;

		if ( currentState ) {
			currentState.selectedMembers = [];
			currentState.selectedTypes = [];
		}

		setSelectedValuesForRecord( currentRecord, 'member', [] );
		setSelectedValuesForRecord( currentRecord, 'type', [] );

		currentWrappers.forEach( ( wrapper ) => {
			closeToolbarFilter( wrapper );
			syncToolbarFilterUi( wrapper, [] );
		} );

		applyCalendarFilter( currentRecord?.instance || null, [], [] );

		const typeTrigger = currentWrappers
			.find(
				( wrapper ) => 'type' === calendarFilterDimension( wrapper )
			)
			?.querySelector( '[data-wm-bci-calendar-filter-button]' );

		if ( 'function' === typeof typeTrigger?.focus ) {
			typeTrigger.focus();
		}

		syncCalendarClearFiltersButton( clearFilters, currentState );
	} );

	clearFilters.dataset.wmBciCalendarClearFiltersBound = 'true';
}

export function replaceCalendarNavButtonIcons( calendarElement ) {
	if ( ! calendarElement?.querySelector ) {
		return;
	}

	[
		{
			direction: 'prev',
			label: __( 'Previous month', 'community-resources-hub' ),
			selector: '.fc-prev-button',
		},
		{
			direction: 'next',
			label: __( 'Next month', 'community-resources-hub' ),
			selector: '.fc-next-button',
		},
	].forEach( ( { direction, label, selector } ) => {
		const button = calendarElement.querySelector( selector );

		if ( ! button ) {
			return;
		}

		const buttonMarkup = String( button.innerHTML || '' );
		const hasCalendarNavIcon = buttonMarkup.includes(
			'wm-bci-calendar-nav-icon'
		);
		const hasFullCalendarIcon = buttonMarkup.includes( 'fc-icon' );

		if (
			button.dataset?.wmBciCalendarNavIcon === direction &&
			hasCalendarNavIcon &&
			! hasFullCalendarIcon
		) {
			return;
		}

		const maskId = calendarNavMaskId( calendarElement, direction );

		button.innerHTML = calendarNavIconSvg( direction, maskId );
		button.setAttribute( 'aria-label', label );
		button.setAttribute( 'title', label );

		if ( button.dataset ) {
			button.dataset.wmBciCalendarNavIcon = direction;
		}
	} );
}

export function applyCalendarFilter(
	calendar,
	selectedTypes,
	selectedMembers
) {
	const events = calendar?.getEvents ? calendar.getEvents() : [];
	const selectedTypeValues = normalizeSelectedTypeValues( selectedTypes );
	const selectedMemberValues = normalizeSelectedTypeValues( selectedMembers );
	const visibleDisplay = calendar?.getOption?.( 'eventDisplay' ) || 'auto';
	const applyUpdates = () => {
		events.forEach( ( event ) => {
			const eventType = event?.extendedProps?.wmBciTypeValue || '';
			const eventMembers = normalizeSelectedTypeValues(
				event?.extendedProps?.wmBciMemberSlugs ||
					event?.extendedProps?.wmBciMemberSlug ||
					[]
			);
			const matchesType =
				! selectedTypeValues.length ||
				selectedTypeValues.includes( eventType );
			const matchesMember =
				! selectedMemberValues.length ||
				selectedMemberValues.some( ( member ) =>
					eventMembers.includes( member )
				);
			const shouldShow = matchesType && matchesMember;
			const currentDisplay = event?.display || 'auto';

			if ( ! event?.setProp ) {
				return;
			}

			if ( shouldShow ) {
				if ( 'none' === currentDisplay ) {
					event.setProp( 'display', visibleDisplay );
				}

				return;
			}

			if ( 'none' !== currentDisplay ) {
				event.setProp( 'display', 'none' );
			}
		} );
	};

	if ( calendar?.batchRendering ) {
		calendar.batchRendering( applyUpdates );
		return;
	}

	applyUpdates();
}

/**
 * Backward-compatible type-only filtering entrypoint.
 *
 * @param {Object}        calendar      FullCalendar instance.
 * @param {Array<string>} selectedTypes Selected event type slugs.
 * @return {void}
 */
export function applyTypeFilter( calendar, selectedTypes ) {
	applyCalendarFilter( calendar, selectedTypes, [] );
}

export function getCalendarRecord( calendarElement ) {
	if ( ! calendarElement || ! window.gvCalendar ) {
		return null;
	}

	const calendarId = calendarElement.dataset.calendar_id;

	if ( ! calendarId || ! window.gvCalendar[ calendarId ] ) {
		return null;
	}

	return window.gvCalendar[ calendarId ];
}

function calendarStateForSection( section ) {
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

export function syncCalendarToolbarFilter( section ) {
	const wrappers = Array.from(
		section?.querySelectorAll?.(
			'[data-wm-bci-calendar-filter-source], [data-wm-bci-calendar-filter]'
		) || []
	).sort( ( first, second ) => {
		const order = { type: 0, member: 1 };

		return (
			order[ calendarFilterDimension( first ) ] -
			order[ calendarFilterDimension( second ) ]
		);
	} );
	const calendarElement = section?.querySelector?.( '.gv-fullcalendar' );
	const calendarRecord = getCalendarRecord( calendarElement );
	const clearFilters = section?.querySelector?.(
		'[data-wm-bci-calendar-clear-filters]'
	);
	const toolbarChunk = calendarRecord?.instance?.el?.querySelector?.(
		'.fc-toolbar .fc-toolbar-chunk:first-child'
	);

	if ( ! wrappers.length || ! toolbarChunk || ! calendarRecord?.instance ) {
		return null;
	}

	const state = calendarStateForSection( section );
	const baseId = String(
		calendarElement?.dataset?.calendar_id || section.id || 'calendar-filter'
	).replace( /[^A-Za-z0-9_-]/g, '-' );

	placeToolbarFilters( toolbarChunk, wrappers, clearFilters );

	wrappers.forEach( ( wrapper ) => {
		const dimension = calendarFilterDimension( wrapper );
		const stateKey = selectedValuesStateKey( dimension );
		const panel = wrapper.querySelector(
			'[data-wm-bci-calendar-filter-panel]'
		);
		const button = wrapper.querySelector(
			'[data-wm-bci-calendar-filter-button]'
		);
		const defaultLabel =
			wrapper.__crhCalendarDefaultLabel ||
			wrapper.querySelector( '[data-wm-bci-calendar-filter-label]' )
				?.textContent ||
			( 'member' === dimension
				? __( 'All Members', 'community-resources-hub' )
				: __( 'All BCI Events', 'community-resources-hub' ) );
		const filterId = `wm-bci-calendar-${ dimension }-filter-panel-${ baseId }`;
		const allowedValues = new Set(
			Array.from(
				wrapper.querySelectorAll( OPTION_CHECKBOX_SELECTOR ),
				( checkbox ) => checkbox.value
			)
		);
		const selectedValues = normalizeSelectedTypeValues(
			state[ stateKey ] ||
				selectedValuesForRecord( calendarRecord, dimension )
		).filter( ( value ) => allowedValues.has( value ) );

		if ( panel ) {
			panel.id = filterId;
			panel.setAttribute(
				'aria-hidden',
				wrapper.open ? 'false' : 'true'
			);
		}

		if ( button ) {
			button.setAttribute( 'aria-controls', filterId );
			button.setAttribute(
				'aria-expanded',
				wrapper.open ? 'true' : 'false'
			);
		}

		wrapper.hidden = false;
		wrapper.removeAttribute( 'hidden' );
		wrapper.removeAttribute( 'data-wm-bci-calendar-filter-source' );
		wrapper.setAttribute( 'data-wm-bci-calendar-filter', 'true' );
		wrapper.__crhCalendarDefaultLabel = defaultLabel;
		wrapper.__crhCalendarRecord = calendarRecord;
		wrapper.__crhCalendarState = state;
		wrapper.__crhCalendarClearFilters = clearFilters;
		state[ stateKey ] = selectedValues;

		setSelectedValuesForRecord( calendarRecord, dimension, selectedValues );
		bindToolbarFilterTrigger( wrapper );
		bindToolbarFilterOptions( wrapper );
		syncToolbarFilterUi( wrapper, selectedValues );
	} );

	bindCalendarClearFilters( clearFilters, wrappers, calendarRecord, state );
	syncCalendarClearFiltersButton( clearFilters, state );

	applyCalendarFilter(
		calendarRecord.instance,
		selectedValuesForRecord( calendarRecord, 'type' ),
		selectedValuesForRecord( calendarRecord, 'member' )
	);
	replaceCalendarNavButtonIcons( calendarRecord.instance.el );

	return wrappers[ 0 ];
}

export function bootCalendarIntegration( section ) {
	const calendarElement = section?.querySelector?.( '.gv-fullcalendar' );
	const calendarRecord = getCalendarRecord( calendarElement );
	const state = section ? calendarStateForSection( section ) : null;

	if ( ! calendarRecord?.instance ) {
		return null;
	}

	if (
		! calendarRecord.wmBciEnhanced &&
		calendarRecord.instance.setOption &&
		calendarRecord.instance.getOption
	) {
		const existingDatesSet =
			calendarRecord.instance.getOption( 'datesSet' );
		const existingEventsSet =
			calendarRecord.instance.getOption( 'eventsSet' );
		const existingEventDidMount =
			calendarRecord.instance.getOption( 'eventDidMount' );

		calendarRecord.instance.setOption( 'datesSet', ( ...args ) => {
			if ( 'function' === typeof existingDatesSet ) {
				existingDatesSet( ...args );
			}

			const resync = () => {
				replaceCalendarNavButtonIcons( calendarRecord.instance.el );
				syncCalendarToolbarFilter( section );

				if (
					section &&
					'function' === typeof section.__crhScheduleCalendarRuntime
				) {
					section.__crhScheduleCalendarRuntime();
				}
			};

			if ( 'function' === typeof window?.requestAnimationFrame ) {
				window.requestAnimationFrame( resync );
				return;
			}

			resync();
		} );

		calendarRecord.instance.setOption( 'eventsSet', ( ...args ) => {
			if ( 'function' === typeof existingEventsSet ) {
				existingEventsSet( ...args );
			}

			applyCalendarFilter(
				calendarRecord.instance,
				calendarRecord.extraOptions?.wmBciSelectedTypeValues ||
					state?.selectedTypes ||
					[],
				calendarRecord.extraOptions?.wmBciSelectedMemberValues ||
					state?.selectedMembers ||
					[]
			);

			if (
				section &&
				'function' === typeof section.__crhScheduleCalendarRuntime
			) {
				section.__crhScheduleCalendarRuntime();
			}
		} );

		calendarRecord.instance.setOption( 'eventDidMount', ( ...args ) => {
			if ( 'function' === typeof existingEventDidMount ) {
				existingEventDidMount( ...args );
			}

			if (
				section &&
				'function' === typeof section.__crhScheduleCalendarRuntime
			) {
				section.__crhScheduleCalendarRuntime();
			}
		} );

		calendarRecord.wmBciEnhanced = true;
	}

	syncCalendarToolbarFilter( section );
	section.dataset.crhCalendarBooted = 'true';

	return calendarRecord;
}
