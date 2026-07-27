<?php
/**
 * Regression checks for the pure opportunity-contract migration manifest.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}
}

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
		$terms = array();
		$id    = 10;

		foreach ( WatersMeet\CommunityResourcesHub\ContentModel\Schema::default_opportunity_types() as $definition ) {
			$terms[] = array(
				'term_id' => $id++,
				'name'    => $definition['name'],
				'slug'    => $definition['slug'],
			);
		}

		return $terms;
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $key, $single = false ) {
		return '';
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-taxonomy.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/config/class-provisioner.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-repository.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-contract-migration.php';

$migration = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityContractMigration();
$manifest  = WatersMeet\CommunityResourcesHub\Workflow\OpportunityContractMigration::bci_manifest();

$expected_manifest = array(
	199 => array( 'type' => 'Event' ),
	220 => array( 'type' => 'Event' ),
	248 => array( 'type' => 'Event' ),
	258 => array( 'type' => 'Other' ),
	277 => array( 'type' => 'Event' ),
	298 => array( 'type' => 'Event' ),
	307 => array( 'type' => 'Other' ),
);

if ( $expected_manifest !== $manifest ) {
	fwrite( STDERR, "Expected BCI migration decisions to remain independent from environment-specific post IDs.\n" );
	exit( 1 );
}

$staging_entry_ids = array(
	94, 103, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 118, 120, 121, 122, 123, 124,
	125, 126, 127, 128, 131, 132, 133, 134, 135, 136, 137, 138, 140, 142, 143, 144, 145, 147,
	149, 150, 151, 152, 153, 154, 155, 156, 157, 158, 159, 160, 161, 162, 163, 164, 165, 166,
	167, 168, 170, 171, 172, 175, 176, 177, 178, 179, 180, 181, 182, 183, 184, 191, 192, 193,
	194, 197, 198, 199, 200, 201, 203, 204, 205, 206, 207, 208, 209, 210, 211, 212, 213, 214,
	215, 216, 220, 222, 223, 224, 226, 227, 228, 229, 230, 231, 233, 234, 236, 237, 240, 241,
	242, 243, 244, 245, 246, 248, 250, 251, 252, 255, 256, 257, 258, 259, 264, 265, 266, 267,
	268, 269, 270, 271, 276, 277, 284, 285, 286, 287, 289, 290, 297, 298, 300, 301, 302, 303,
);
$staging_entries   = array_map(
	static function ( $entry_id ) {
		return array(
			'id' => $entry_id,
			'22' => 276 === $entry_id ? 'Pending' : 'Approved',
		);
	},
	$staging_entry_ids
);
$expectations      = WatersMeet\CommunityResourcesHub\Workflow\OpportunityContractMigration::dataset_expectations( $staging_entries );

if (
	array(
		'entries'     => 144,
		'posts'       => 144,
		'approved'    => 143,
		'pending'     => 1,
		'bci_updates' => 6,
	) !== $expectations
) {
	fwrite( STDERR, "Expected migration totals to follow the structurally valid staging dataset.\n" );
	exit( 1 );
}

foreach ( $manifest as $entry_id => $definition ) {
	$plan = $migration->entry_contract(
		array(
			'id' => $entry_id,
			'1'  => 220 === $entry_id ? 'BCI Updates' : 'BCI Update',
			'4'  => 'BCI entry',
			'6'  => '',
			'9'  => '',
		)
	);

	if ( is_wp_error( $plan ) || $definition['type'] !== $plan['type'] || ! $plan['is_bci_update'] ) {
		fwrite( STDERR, "Expected every BCI manifest entry to receive its approved primary type and tag.\n" );
		exit( 1 );
	}

	if ( 'Yes' !== ( $plan['field_updates']['26'] ?? '' ) ) {
		fwrite( STDERR, "Expected BCI manifest entries to backfill field 26 to Yes.\n" );
		exit( 1 );
	}
}

$entry_220 = $migration->entry_contract( array( 'id' => 220, '1' => 'BCI Update', '6' => '', '9' => '' ) );

if ( '2026-05-19' !== ( $entry_220['field_updates']['6'] ?? '' ) ) {
	fwrite( STDERR, "Expected entry 220 to receive the approved start-date repair.\n" );
	exit( 1 );
}

$entry_125 = $migration->entry_contract( array( 'id' => 125, '1' => 'Event', '6' => '', '10' => '2026-03-21' ) );

if ( '2026-03-21' !== ( $entry_125['field_updates']['6'] ?? '' ) ) {
	fwrite( STDERR, "Expected entry 125 to copy its existing end date into the missing start date.\n" );
	exit( 1 );
}

$legacy_manifest = WatersMeet\CommunityResourcesHub\Workflow\OpportunityContractMigration::legacy_normalization_manifest();

if ( 12 !== count( $legacy_manifest ) ) {
	fwrite( STDERR, "Expected the exact twelve-entry legacy normalization manifest.\n" );
	exit( 1 );
}

foreach ( $legacy_manifest as $entry_id => $legacy_definition ) {
	$plan = $migration->entry_contract( array( 'id' => $entry_id, '1' => $legacy_definition['raw'] ) );

	if ( is_wp_error( $plan ) || $legacy_definition['type'] !== $plan['type'] ) {
		fwrite( STDERR, "Expected every approved legacy entry ID/value pair to normalize exactly.\n" );
		exit( 1 );
	}
}

$other = $migration->entry_contract( array( 'id' => 124, '1' => 'Other Opportunities Section' ) );
$volunteer = $migration->entry_contract( array( 'id' => 200, '1' => 'Volunteer and Tabling Opportunity' ) );

if ( 'Other' !== $other['type'] || ! $other['is_time_sensitive'] || 'Resource' !== $volunteer['type'] || $volunteer['is_time_sensitive'] ) {
	fwrite( STDERR, "Expected the two ID-specific free-text mappings to match the approved contract.\n" );
	exit( 1 );
}

$stale = $migration->entry_contract(
	array(
		'id' => 400,
		'24' => 'No',
		'1'  => 'Grant / RFP',
		'25' => 'Resource',
		'26' => 'No',
	)
);

if ( 'Resource' !== $stale['type'] || array( '1' => '' ) !== $stale['field_updates'] ) {
	fwrite( STDERR, "Expected a No branch to ignore and clear stale hidden field 1 only.\n" );
	exit( 1 );
}

$unknown = $migration->entry_contract( array( 'id' => 401, '1' => 'Unexpected type' ) );
$unexpected_legacy = $migration->entry_contract( array( 'id' => 402, '1' => 'Other Resources' ) );

if ( ! is_wp_error( $unknown ) || ! is_wp_error( $unexpected_legacy ) ) {
	fwrite( STDERR, "Expected unknown types to fail preflight instead of being guessed.\n" );
	exit( 1 );
}

echo "Opportunity-contract migration manifest test passed.\n";
