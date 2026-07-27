<?php
/**
 * Smoke tests for BCI GravityCalendar display options.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_form_id' => 9,
);

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] )
			? $GLOBALS['crh_options'][ $option ]
			: $default;
	}
}

function crh_calendar_options_assert( $condition, $message ) {
	if ( $condition ) {
		return;
	}

	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/calendar/class-tooltip-options.php';

$config     = new WatersMeet\CommunityResourcesHub\Config\Config();
$customizer = new WatersMeet\CommunityResourcesHub\Calendar\TooltipOptions( $config );

$calendar_options = $customizer->customize_calendar_options(
	array(
		'customOption' => 'preserved',
		'eventDisplay' => 'list-item',
		'dayMaxEvents' => 2,
		'eventOrder'   => 'title',
	),
	9
);

crh_calendar_options_assert(
	4 === ( $calendar_options['dayMaxEvents'] ?? null ),
	'Expected the configured BCI calendar to display up to four events per day.'
);
crh_calendar_options_assert(
	'-duration,start,allDay,title' === ( $calendar_options['eventOrder'] ?? null ),
	'Expected longer BCI calendar events to render first so four-event rows remain compact.'
);
crh_calendar_options_assert(
	'block' === ( $calendar_options['eventDisplay'] ?? null ),
	'Expected the configured BCI calendar to preserve block event display.'
);
crh_calendar_options_assert(
	'popover' === ( $calendar_options['moreLinkClick'] ?? null ),
	'Expected excess BCI calendar events to retain the popover behavior.'
);
crh_calendar_options_assert(
	false === ( $calendar_options['fixedWeekCount'] ?? null ),
	'Expected the BCI calendar to retain its variable week count.'
);
crh_calendar_options_assert(
	'preserved' === ( $calendar_options['customOption'] ?? null ),
	'Expected unrelated FullCalendar options to remain unchanged.'
);

$unrelated_calendar_options = array(
	'customOption' => 'untouched',
	'dayMaxEvents' => 7,
	'eventOrder'   => 'title',
);

crh_calendar_options_assert(
	$unrelated_calendar_options === $customizer->customize_calendar_options( $unrelated_calendar_options, 10 ),
	'Expected calendars for unrelated forms to remain unchanged.'
);

echo "Calendar tooltip options test passed.\n";
