<?php
/**
 * Production-shaped retry contracts for the Opportunity Hub migration.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_contract_posts']        = array();
$GLOBALS['crh_contract_post_meta']    = array();
$GLOBALS['crh_contract_object_terms'] = array();
$GLOBALS['crh_contract_http_calls']   = array();
$GLOBALS['crh_contract_failures']     = array();

class WP_Error {
	private $code;
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->code    = (string) $code;
		$this->message = (string) $message;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function crh_contract_assert( $condition, $message ) {
	if ( ! $condition ) {
		$GLOBALS['crh_contract_failures'][] = (string) $message;
	}
}

function __( $text, $domain = 'default' ) {
	return $text;
}

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function absint( $value ) {
	return abs( (int) $value );
}

function get_option( $option, $default = false ) {
	return $default;
}

function sanitize_title( $value ) {
	$value = strtolower( trim( (string) $value ) );
	return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function esc_url_raw( $url, $protocols = null ) {
	return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
}

function wp_kses_post( $value ) {
	return (string) $value;
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

function get_terms( $args = array() ) {
	$terms = array();

	foreach ( WatersMeet\CommunityResourcesHub\ContentModel\Schema::default_opportunity_types() as $definition ) {
		$terms[] = array(
			'term_id' => (int) ( $definition['legacy_term_id'] ?: count( $terms ) + 100 ),
			'name'    => $definition['name'],
			'slug'    => $definition['slug'],
		);
	}

	return $terms;
}

function get_term_meta( $term_id, $key, $single = false ) {
	return '';
}

function get_post_field( $field, $post_id, $context = 'display' ) {
	return $GLOBALS['crh_contract_posts'][ $post_id ][ $field ] ?? '';
}

function get_post_meta( $post_id, $key = '', $single = false ) {
	return $GLOBALS['crh_contract_post_meta'][ $post_id ][ $key ] ?? '';
}

function update_post_meta( $post_id, $key, $value, $previous = '' ) {
	$GLOBALS['crh_contract_post_meta'][ $post_id ][ $key ] = $value;
	return true;
}

function delete_post_meta( $post_id, $key, $value = '' ) {
	unset( $GLOBALS['crh_contract_post_meta'][ $post_id ][ $key ] );
	return true;
}

function wp_get_object_terms( $post_id, $taxonomy, $args = array() ) {
	return $GLOBALS['crh_contract_object_terms'][ $post_id ][ $taxonomy ] ?? array();
}

function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
	$GLOBALS['crh_contract_object_terms'][ $post_id ][ $taxonomy ] = array_map( 'strval', (array) $terms );
	return $terms;
}

function wp_add_object_terms( $post_id, $terms, $taxonomy ) {
	return true;
}

function wp_remove_object_terms( $post_id, $terms, $taxonomy ) {
	return true;
}

function get_posts( $args = array() ) {
	$matches = array();

	foreach ( $GLOBALS['crh_contract_posts'] as $post_id => $post ) {
		if ( isset( $args['post_type'] ) && ( $post['post_type'] ?? '' ) !== $args['post_type'] ) {
			continue;
		}

		if ( ! empty( $args['post_status'] ) && 'any' !== $args['post_status'] && ( $post['post_status'] ?? '' ) !== $args['post_status'] ) {
			continue;
		}

		if ( ! empty( $args['meta_key'] ) ) {
			$actual = $GLOBALS['crh_contract_post_meta'][ $post_id ][ $args['meta_key'] ] ?? '';

			if ( (string) $actual !== (string) ( $args['meta_value'] ?? '' ) ) {
				continue;
			}
		}

		$matches[] = (int) $post_id;
	}

	sort( $matches, SORT_NUMERIC );
	return $matches;
}

function wp_update_post( $postarr, $wp_error = false ) {
	$post_id = absint( $postarr['ID'] ?? 0 );

	if ( ! $post_id || ! isset( $GLOBALS['crh_contract_posts'][ $post_id ] ) ) {
		return $wp_error ? new WP_Error( 'missing_post', 'Missing post.' ) : 0;
	}

	$GLOBALS['crh_contract_posts'][ $post_id ] = array_merge(
		$GLOBALS['crh_contract_posts'][ $post_id ],
		$postarr
	);

	return $post_id;
}

function wp_insert_post( $postarr, $wp_error = false ) {
	$post_id                                        = 900 + count( $GLOBALS['crh_contract_posts'] );
	$GLOBALS['crh_contract_posts'][ $post_id ]      = array_merge( array( 'ID' => $post_id ), $postarr );
	$GLOBALS['crh_contract_post_meta'][ $post_id ]  = array();
	return $post_id;
}

function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['crh_contract_http_calls'][] = array( $url, $args );
	return array();
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-taxonomy.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/config/class-provisioner.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-repository.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-contract-migration.php';

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\ContentModel\Schema;
use WatersMeet\CommunityResourcesHub\Workflow\FieldAccessor;
use WatersMeet\CommunityResourcesHub\Workflow\OpportunityContractMigration;
use WatersMeet\CommunityResourcesHub\Workflow\OpportunityRepository;

$expectations = OpportunityContractMigration::dataset_expectations(
	array(
		array( 'id' => 330, '22' => 'Approved' ),
		array( 'id' => 331, '22' => 'Pending' ),
		array( 'id' => 332, '22' => 'Pending' ),
		array( 'id' => 333, '22' => 'Approved' ),
	)
);

crh_contract_assert(
	array(
		'entries'     => 4,
		'posts'       => 4,
		'approved'    => 2,
		'pending'     => 2,
		'bci_updates' => 0,
	) === $expectations,
	'Expected production migration totals to derive multiple Pending entries and one linked post per active Approved or Pending entry.'
);

$migration      = new OpportunityContractMigration();
$entry_333_plan = $migration->entry_contract(
	array(
		'id'           => 333,
		'date_created' => '2026-07-01 12:00:00',
		'1'            => 'Paid Fellowship',
		'4'            => 'Paid fellowship',
		'22'           => 'Approved',
	)
);

crh_contract_assert(
	! is_wp_error( $entry_333_plan )
	&& 'Resource' === ( $entry_333_plan['type'] ?? '' )
	&& empty( $entry_333_plan['is_time_sensitive'] ),
	'Expected exact entry 333 Paid Fellowship to normalize strictly to Resource.'
);
crh_contract_assert(
	is_wp_error(
		$migration->entry_contract(
			array(
				'id' => 333,
				'1'  => 'Paid Fellowship - revised',
			)
		)
	),
	'Expected entry 333 values other than the exact approved Paid Fellowship mapping to fail preflight.'
);

$config = new Config();
$fields = new FieldAccessor( $config );

function crh_contract_entry( $entry_id, $approval_status ) {
	return array(
		'id'           => $entry_id,
		'date_created' => '2026-07-01 12:00:00',
		'1'            => 'Resource',
		'3.3'          => 'Test',
		'3.6'          => 'Submitter',
		'4'            => 'Resource ' . $entry_id,
		'17'           => 'Description ' . $entry_id,
		'22'           => $approval_status,
	);
}

function crh_contract_seed_linked_post( $post_id, array $entry, $approval_status, $post_status, Config $config, FieldAccessor $fields ) {
	$entry_id = absint( $entry['id'] ?? 0 );

	$GLOBALS['crh_contract_posts'][ $post_id ] = array(
		'ID'           => $post_id,
		'post_type'    => $config->opportunity_post_type(),
		'post_status'  => $post_status,
		'post_title'   => sanitize_text_field( $fields->title( $entry ) ),
		'post_content' => wp_kses_post( $fields->description( $entry ) ),
	);
	$GLOBALS['crh_contract_post_meta'][ $post_id ] = array(
		$config->opportunity_field_name( 'source_entry_id' ) => $entry_id,
		$config->opportunity_field_name( 'approval_status' ) => $approval_status,
		$config->opportunity_field_name( 'submitted_at' ) => $fields->submitted_at( $entry ),
		$config->opportunity_field_name( 'opportunity_type' ) => 'Resource',
		$config->opportunity_field_name( 'submitter_name' ) => $fields->submitter_name( $entry ),
		$config->opportunity_field_name( 'organization' ) => '',
		$config->opportunity_field_name( 'start_date' ) => '',
		$config->opportunity_field_name( 'grant_deadline' ) => '',
		$config->opportunity_field_name( 'end_date' ) => '',
		$config->opportunity_field_name( 'start_time' ) => '',
		$config->opportunity_field_name( 'end_time' ) => '',
		$config->opportunity_field_name( 'location_mode' ) => '',
		$config->opportunity_field_name( 'address' ) => '',
		$config->opportunity_field_name( 'cost' ) => '',
		$config->opportunity_field_name( 'info_url' ) => '',
		$config->opportunity_field_name( 'file_upload' ) => '',
	);
	$GLOBALS['crh_contract_object_terms'][ $post_id ] = array(
		$config->opportunity_type_taxonomy() => array( 'resource' ),
		$config->opportunity_tag_taxonomy()  => array(),
	);
}

$post_needs_sync = new ReflectionMethod( OpportunityContractMigration::class, 'post_needs_sync' );

$status_cases = array(
	array( 500, 'Approved', 'publish', false, 'Approved linked posts should be current only when published.' ),
	array( 501, 'Pending', 'pending', false, 'Pending linked posts should be current when their WordPress status is pending.' ),
	array( 502, 'Approved', 'pending', true, 'Approved linked posts in pending status should require synchronization.' ),
	array( 503, 'Pending', 'publish', true, 'Pending linked posts in publish status should require synchronization.' ),
);

foreach ( $status_cases as $status_case ) {
	list( $post_id, $approval_status, $post_status, $expected_sync, $message ) = $status_case;
	$entry      = crh_contract_entry( $post_id, $approval_status );
	$entry_plan = $migration->entry_contract( $entry );

	if ( is_wp_error( $entry_plan ) ) {
		crh_contract_assert( false, 'Expected the status fixture entry to resolve to Resource.' );
		continue;
	}

	$entry_plan['approval_status'] = $approval_status;
	crh_contract_seed_linked_post( $post_id, $entry, $approval_status, $post_status, $config, $fields );

	crh_contract_assert(
		$expected_sync === $post_needs_sync->invoke( $migration, $post_id, $entry, $entry_plan ),
		$message
	);
}

$pending_entry = crh_contract_entry( 501, 'Pending' );
$repository    = new OpportunityRepository( $config );
$synced_post   = $repository->upsert_from_entry( $pending_entry, 'Pending' );

crh_contract_assert( 501 === $synced_post, 'Expected a linked Pending entry to synchronize its existing post.' );
crh_contract_assert(
	'pending' === ( $GLOBALS['crh_contract_posts'][501]['post_status'] ?? '' ),
	'Expected synchronizing a Pending entry to preserve pending WordPress visibility.'
);
crh_contract_assert(
	array() === $GLOBALS['crh_contract_http_calls'],
	'Expected post synchronization for a Pending entry not to invoke Google.'
);

$migration_source = file_get_contents( dirname( __DIR__ ) . '/includes/workflow/class-opportunity-contract-migration.php' );
$syncs_planned_status = is_string( $migration_source ) && 1 === preg_match(
	'/upsert_from_entry\(\s*\$entry,\s*\$entry_plan\[[\'\"]approval_status[\'\"]\]\s*\)/',
	$migration_source
);

crh_contract_assert(
	$syncs_planned_status,
	'Expected migration apply to synchronize each changed linked post with its planned Approved or Pending status instead of hard-coding Approved.'
);

if ( ! empty( $GLOBALS['crh_contract_failures'] ) ) {
	fwrite( STDERR, implode( "\n", $GLOBALS['crh_contract_failures'] ) . "\n" );
	exit( 1 );
}

fwrite( STDOUT, "Opportunity-contract production retry test passed.\n" );
