<?php
/**
 * Regression checks for branch-aware GravityCalendar start-date mirroring.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_entry_bridge_updates'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		return array(
			array(
				'term_id'  => 16,
				'name'     => 'Grant / RFP',
				'slug'     => 'grant-rfp',
				'taxonomy' => 'opportunity-type',
			),
		);
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $key, $single = false ) {
		return 'alias' === $key ? 'Grant/RFP' : '';
	}
}

class GFAPI {
	public static function update_entry_field( $entry_id, $field_id, $value ) {
		$GLOBALS['crh_entry_bridge_updates'][] = array( $entry_id, (string) $field_id, $value );
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-repository.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-entry-bridge.php';

$config     = new WatersMeet\CommunityResourcesHub\Config\Config();
$repository = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityRepository( $config );
$bridge     = new WatersMeet\CommunityResourcesHub\Workflow\EntryBridge( $config, $repository );
$method     = new ReflectionMethod( $bridge, 'sync_grant_deadline_to_start_date' );

$resource = $method->invoke(
	$bridge,
	array(
		'id' => 10,
		'24' => 'No',
		'1'  => 'Grant / RFP',
		'25' => 'Resource',
		'6'  => '',
		'9'  => '2026-08-01',
	)
);

if ( '' !== ( $resource['6'] ?? '' ) || ! empty( $GLOBALS['crh_entry_bridge_updates'] ) ) {
	fwrite( STDERR, "Expected stale hidden Grant data not to alter a non-date-sensitive entry.\n" );
	exit( 1 );
}

$grant = $method->invoke(
	$bridge,
	array(
		'id' => 11,
		'24' => 'Yes',
		'1'  => 'Grant / RFP',
		'6'  => '',
		'9'  => '2026-08-02',
	)
);

if (
	'2026-08-02' !== ( $grant['6'] ?? '' )
	|| array( 11, '6', '2026-08-02' ) !== ( $GLOBALS['crh_entry_bridge_updates'][0] ?? array() )
) {
	fwrite( STDERR, "Expected the Grant deadline to populate GravityCalendar's start-date field.\n" );
	exit( 1 );
}

$changed_grant = $method->invoke(
	$bridge,
	array(
		'id' => 12,
		'24' => 'Yes',
		'1'  => 'Grant / RFP',
		'6'  => '2026-08-01',
		'9'  => '2026-08-03',
	)
);

if (
	'2026-08-03' !== ( $changed_grant['6'] ?? '' )
	|| array( 12, '6', '2026-08-03' ) !== ( $GLOBALS['crh_entry_bridge_updates'][1] ?? array() )
) {
	fwrite( STDERR, "Expected an edited Grant deadline to keep GravityCalendar's mirrored date current.\n" );
	exit( 1 );
}

echo "Entry bridge opportunity-contract test passed.\n";
