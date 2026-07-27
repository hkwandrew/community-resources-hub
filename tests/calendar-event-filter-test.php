<?php
/**
 * Smoke tests for the Opportunity Hub GravityCalendar feed conditions.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_form_id'                    => 5,
	'options_wm_bci_calendar_feed_name'         => 'BCI Opportunities',
	'options_wm_bci_approval_field_id'          => '22',
	'options_wm_bci_field_map_time_sensitive'   => '24',
);

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['crh_registered_filter'] = compact( 'hook', 'callback', 'priority', 'accepted_args' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) {
		return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] )
			? $GLOBALS['crh_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'rgar' ) ) {
	function rgar( $array, $key, $default = null ) {
		return is_array( $array ) && array_key_exists( $key, $array ) ? $array[ $key ] : $default;
	}
}

if ( ! function_exists( 'rgars' ) ) {
	function rgars( $array, $path, $default = null ) {
		$value = $array;

		foreach ( explode( '/', (string) $path ) as $key ) {
			if ( ! is_array( $value ) || ! array_key_exists( $key, $value ) ) {
				return $default;
			}

			$value = $value[ $key ];
		}

		return $value;
	}
}

final class GV_Extension_Calendar_Feed {
	public static function get_instance() {
		return new self();
	}

	public function get_feed( $feed_id ) {
		if ( 3 !== (int) $feed_id ) {
			return array();
		}

		return array(
			'form_id' => 5,
			'meta'    => array(
				'feedName' => 'BCI Opportunities',
			),
		);
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/calendar/class-event-filter.php';

use WatersMeet\CommunityResourcesHub\Calendar\EventFilter;
use WatersMeet\CommunityResourcesHub\Config\Config;

$filter = new EventFilter( new Config() );
$filter->register();

if (
	'gk/gravitycalendar/events/filters' !== ( $GLOBALS['crh_registered_filter']['hook'] ?? '' )
	|| 4 !== ( $GLOBALS['crh_registered_filter']['accepted_args'] ?? 0 )
) {
	fwrite( STDERR, "Expected the calendar feed filter hook to retain its four-argument contract.\n" );
	exit( 1 );
}

$filtered = $filter->filter( array(), 3 );
$conditions = $filtered['conditions'] ?? array();

$expected_conditions = array(
	array(
		'key'      => '22',
		'operator' => 'is',
		'value'    => 'Approved',
	),
	array(
		'key'      => '24',
		'operator' => 'is',
		'value'    => 'Yes',
	),
);

if ( $expected_conditions !== $conditions ) {
	fwrite( STDERR, "Expected the BCI feed to require both Approved and time-sensitive Yes conditions.\n" );
	exit( 1 );
}

$idempotent = $filter->filter( $filtered, 3 );

if ( $filtered !== $idempotent ) {
	fwrite( STDERR, "Expected repeated calendar filtering not to duplicate feed conditions.\n" );
	exit( 1 );
}

$partially_configured = array(
	'conditions' => array( $expected_conditions[0] ),
);
$completed = $filter->filter( $partially_configured, 3 );

if ( $expected_conditions !== ( $completed['conditions'] ?? array() ) ) {
	fwrite( STDERR, "Expected a missing time-sensitive condition to be added even when approval is already present.\n" );
	exit( 1 );
}

$unrelated = array(
	'conditions' => array(
		array(
			'key'      => '99',
			'operator' => 'is',
			'value'    => 'Unchanged',
		),
	),
);

if ( $unrelated !== $filter->filter( $unrelated, 4 ) ) {
	fwrite( STDERR, "Expected unrelated GravityCalendar feeds to remain unchanged.\n" );
	exit( 1 );
}

echo "Calendar event filter contract test passed.\n";
