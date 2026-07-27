<?php
/**
 * Regression contract for the explicit legacy workflow production cutover.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$cutover_file = dirname( __DIR__ ) . '/includes/workflow/class-legacy-workflow-cutover.php';

if ( ! is_file( $cutover_file ) ) {
	fwrite( STDERR, "Expected the LegacyWorkflowCutover service source file.\n" );
	exit( 1 );
}

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

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return trim( preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( trim( (string) $value ) ) ), '-' );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $value ) {
		return false !== strpos( (string) $value, '@' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $value ) {
		return (string) $value;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['crh_cutover_options'] )
			? $GLOBALS['crh_cutover_options'][ $name ]
			: $default;
	}
}

if ( ! function_exists( 'get_site_option' ) ) {
	function get_site_option( $name, $default = false ) {
		return array_key_exists( $name, $GLOBALS['crh_cutover_site_options'] )
			? $GLOBALS['crh_cutover_site_options'][ $name ]
			: $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $value = '', $deprecated = '', $autoload = null ) {
		if ( array_key_exists( $name, $GLOBALS['crh_cutover_options'] ) ) {
			return false;
		}

		$GLOBALS['crh_cutover_options'][ $name ] = $value;
		$GLOBALS['crh_cutover_option_writes']++;
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		$GLOBALS['crh_cutover_options'][ $name ] = $value;
		$GLOBALS['crh_cutover_option_writes']++;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		if ( ! array_key_exists( $name, $GLOBALS['crh_cutover_options'] ) ) {
			return false;
		}

		unset( $GLOBALS['crh_cutover_options'][ $name ] );
		$GLOBALS['crh_cutover_option_writes']++;
		return true;
	}
}

if ( ! function_exists( 'gform_get_meta' ) ) {
	function gform_get_meta( $entry_id, $key ) {
		return $GLOBALS['crh_cutover_entry_meta'][ absint( $entry_id ) ][ (string) $key ] ?? '';
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$matches = array();

		foreach ( $GLOBALS['crh_cutover_posts'] as $post_id => $post ) {
			if ( ! empty( $args['post_type'] ) && (string) $args['post_type'] !== (string) ( $post['post_type'] ?? '' ) ) {
				continue;
			}

			$status = (string) ( $post['post_status'] ?? '' );

			if ( ! empty( $args['post_status'] ) && 'any' !== $args['post_status'] && $status !== (string) $args['post_status'] ) {
				continue;
			}

			if ( isset( $args['meta_key'] ) ) {
				$value = $GLOBALS['crh_cutover_post_meta'][ $post_id ][ (string) $args['meta_key'] ] ?? '';

				if ( (string) $value !== (string) ( $args['meta_value'] ?? '' ) ) {
					continue;
				}
			}

			$matches[] = absint( $post_id );
		}

		sort( $matches, SORT_NUMERIC );
		return $matches;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		$post_id = ++$GLOBALS['crh_cutover_next_post_id'];
		$postarr['ID'] = $post_id;
		$postarr['post_date'] = $postarr['post_date'] ?? '2026-07-14 12:00:00';
		$GLOBALS['crh_cutover_posts'][ $post_id ] = $postarr;
		$GLOBALS['crh_cutover_post_writes']++;
		return $post_id;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr, $wp_error = false ) {
		$post_id = absint( $postarr['ID'] ?? 0 );

		if ( ! $post_id || ! isset( $GLOBALS['crh_cutover_posts'][ $post_id ] ) ) {
			return $wp_error ? new WP_Error( 'missing_post', 'Missing post.' ) : 0;
		}

		$GLOBALS['crh_cutover_posts'][ $post_id ] = array_replace( $GLOBALS['crh_cutover_posts'][ $post_id ], $postarr );
		$GLOBALS['crh_cutover_post_writes']++;
		return $post_id;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return $GLOBALS['crh_cutover_post_meta'][ absint( $post_id ) ] ?? array();
		}

		return $GLOBALS['crh_cutover_post_meta'][ absint( $post_id ) ][ (string) $key ] ?? '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['crh_cutover_post_meta'][ absint( $post_id ) ][ (string) $key ] = $value;
		$GLOBALS['crh_cutover_post_writes']++;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key ) {
		unset( $GLOBALS['crh_cutover_post_meta'][ absint( $post_id ) ][ (string) $key ] );
		$GLOBALS['crh_cutover_post_writes']++;
		return true;
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id, $context = 'display' ) {
		return $GLOBALS['crh_cutover_posts'][ absint( $post_id ) ][ (string) $field ] ?? '';
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $post_id ) {
		return (string) ( $GLOBALS['crh_cutover_posts'][ absint( $post_id ) ]['post_status'] ?? '' );
	}
}

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
		$GLOBALS['crh_cutover_post_terms'][ absint( $post_id ) ][ (string) $taxonomy ] = array_values( (array) $terms );
		$GLOBALS['crh_cutover_post_writes']++;
		return $terms;
	}
}

if ( ! function_exists( 'wp_add_object_terms' ) ) {
	function wp_add_object_terms( $post_id, $terms, $taxonomy ) {
		$current = $GLOBALS['crh_cutover_post_terms'][ absint( $post_id ) ][ (string) $taxonomy ] ?? array();
		$GLOBALS['crh_cutover_post_terms'][ absint( $post_id ) ][ (string) $taxonomy ] = array_values( array_unique( array_merge( $current, (array) $terms ) ) );
		$GLOBALS['crh_cutover_post_writes']++;
		return true;
	}
}

if ( ! function_exists( 'wp_remove_object_terms' ) ) {
	function wp_remove_object_terms( $post_id, $terms, $taxonomy ) {
		$current = $GLOBALS['crh_cutover_post_terms'][ absint( $post_id ) ][ (string) $taxonomy ] ?? array();
		$GLOBALS['crh_cutover_post_terms'][ absint( $post_id ) ][ (string) $taxonomy ] = array_values( array_diff( $current, (array) $terms ) );
		$GLOBALS['crh_cutover_post_writes']++;
		return true;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		$terms = array();
		$id    = 10;

		foreach ( WatersMeet\CommunityResourcesHub\ContentModel\Schema::default_opportunity_types() as $definition ) {
			$terms[] = (object) array(
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

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['crh_cutover_http_calls']++;
		return new WP_Error( 'network_forbidden', 'Network calls are forbidden during cutover.' );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['crh_cutover_http_calls']++;
		return new WP_Error( 'network_forbidden', 'Network calls are forbidden during cutover.' );
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $name ) {
		return true;
	}
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() {
		return true;
	}
}

if ( ! function_exists( 'site_url' ) ) {
	function site_url() {
		return 'https://watersmeet.org';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url() {
		return 'https://watersmeet.org';
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return '6.9';
	}
}

if ( ! function_exists( 'wp_timezone_string' ) ) {
	function wp_timezone_string() {
		return 'America/Los_Angeles';
	}
}

class GFAPI {
	public static $forms = array();
	public static $feeds = array();
	public static $entries = array();
	public static $add_form_calls = 0;
	public static $add_feed_calls = 0;

	public static function get_form( $form_id ) {
		return self::$forms[ absint( $form_id ) ] ?? false;
	}

	public static function get_feed( $feed_id ) {
		return self::$feeds[ absint( $feed_id ) ] ?? new WP_Error( 'not_found', 'Feed not found.' );
	}

	public static function get_entries( $form_id, $search_criteria = array(), $sorting = null, $paging = null, &$total_count = null ) {
		$entries = array_values(
			array_filter(
				self::$entries,
				static function ( $entry ) use ( $form_id, $search_criteria ) {
					return absint( $entry['form_id'] ?? 0 ) === absint( $form_id )
						&& ( empty( $search_criteria['status'] ) || (string) ( $entry['status'] ?? 'active' ) === (string) $search_criteria['status'] );
				}
			)
		);

		usort( $entries, static function ( $left, $right ) { return absint( $left['id'] ?? 0 ) <=> absint( $right['id'] ?? 0 ); } );
		$total_count = count( $entries );
		$offset      = absint( $paging['offset'] ?? 0 );
		$page_size   = absint( $paging['page_size'] ?? count( $entries ) );

		return array_slice( $entries, $offset, $page_size ?: null );
	}

	public static function get_entry( $entry_id ) {
		return self::$entries[ absint( $entry_id ) ] ?? new WP_Error( 'not_found', 'Entry not found.' );
	}

	public static function get_entry_meta( $entry_id, $key ) {
		return gform_get_meta( $entry_id, $key );
	}

	public static function add_form( $form ) {
		self::$add_form_calls++;
		return 99;
	}

	public static function add_feed( $form_id, $meta, $slug ) {
		self::$add_feed_calls++;
		return 99;
	}
}

function crh_cutover_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function crh_cutover_fixture() {
	$secret = 'production-secret-must-never-render';
	$field_map = array(
		'opportunity_type' => '1',
		'submitter_name'    => '3',
		'title'             => '4',
		'organization'      => '5',
		'start_date'        => '6',
		'grant_deadline'    => '9',
		'end_date'          => '10',
		'start_time'        => '12',
		'cost'              => '14',
		'address'           => '15',
		'location_mode'     => '16',
		'description'       => '17',
		'info_url'          => '18',
		'file_upload'       => '19',
		'end_time'          => '21',
		'approval_status'   => '22',
	);

	$GLOBALS['crh_cutover_options'] = array(
		'wm_bci_workflow' => array(
			'form_id'                          => 5,
			'approval_field_id'                => '22',
			'notification_name'                => 'Admin Notification',
			'approval_notification_recipients' => 'ops@watersmeet.org',
			'auto_approved_user_ids'           => array( 4, 7, 9 ),
			'calendar_page_slug'               => 'bci-resources',
			'calendar_feed_name'               => 'BCI Community Opportunity Submission',
			'calendar_feed_id'                 => 3,
			'calendar_shortcode'               => '[gravitycalendar id="3"]',
			'google_sync_url'                  => 'https://script.google.com/macros/s/example/exec',
			'google_sync_secret'               => $secret,
			'field_map'                        => $field_map,
			'legacy_internal_flag'              => 'must-not-copy',
		),
	);
	$GLOBALS['crh_cutover_option_writes'] = 0;
	$GLOBALS['crh_cutover_site_options']  = array();
	$GLOBALS['crh_cutover_posts']         = array();
	$GLOBALS['crh_cutover_post_meta']     = array();
	$GLOBALS['crh_cutover_post_terms']    = array();
	$GLOBALS['crh_cutover_post_writes']   = 0;
	$GLOBALS['crh_cutover_next_post_id']  = 1000;
	$GLOBALS['crh_cutover_http_calls']    = 0;
	$GLOBALS['crh_cutover_entry_meta']    = array(
		177 => array(
			'waters_meet_bci_approved_at'              => '2026-05-01T13:00:00+00:00',
			'waters_meet_bci_google_sync_status'       => 'failed',
			'waters_meet_bci_google_sync_attempted_at' => '2026-05-01T13:01:00+00:00',
			'waters_meet_bci_google_sync_synced_at'    => '',
			'waters_meet_bci_google_sync_error'        => 'Old header mismatch.',
		),
		178 => array(
			'waters_meet_bci_approved_at'              => '2026-05-02T13:00:00+00:00',
			'waters_meet_bci_google_sync_status'       => 'failed',
			'waters_meet_bci_google_sync_attempted_at' => '2026-05-02T13:01:00+00:00',
			'waters_meet_bci_google_sync_synced_at'    => '',
			'waters_meet_bci_google_sync_error'        => 'Old header mismatch.',
		),
		250 => array(
			'waters_meet_bci_approved_at'              => '2026-06-01T13:00:00+00:00',
			'waters_meet_bci_google_sync_status'       => 'failed',
			'waters_meet_bci_google_sync_attempted_at' => '2026-06-01T13:01:00+00:00',
			'waters_meet_bci_google_sync_synced_at'    => '',
			'waters_meet_bci_google_sync_error'        => 'HTTP 500.',
		),
		347 => array(
			'waters_meet_bci_approved_at'              => '2026-07-14T10:00:00+00:00',
			'waters_meet_bci_google_sync_status'       => 'success',
			'waters_meet_bci_google_sync_attempted_at' => '2026-07-14T10:01:00+00:00',
			'waters_meet_bci_google_sync_synced_at'    => '2026-07-14T10:01:05+00:00',
			'waters_meet_bci_google_sync_error'        => '',
		),
	);

	GFAPI::$forms = array(
		5 => array(
			'id'        => 5,
			'title'     => 'BCI Community Opportunity Submission',
			'is_active' => true,
			'fields'    => array(),
		),
	);
	GFAPI::$feeds = array(
		3 => array(
			'id'         => 3,
			'form_id'    => 5,
			'addon_slug' => 'gravityview-calendar',
			'is_active'  => 1,
			'meta'       => array( 'feedName' => 'BCI Community Opportunity Submission' ),
		),
	);
	GFAPI::$add_form_calls = 0;
	GFAPI::$add_feed_calls = 0;
	GFAPI::$entries = array();

	$rows = array(
		177 => array( 'Approved', 'Event' ),
		178 => array( 'Approved', 'Event' ),
		250 => array( 'Approved', 'Event' ),
		276 => array( 'Pending', 'Resource' ),
		341 => array( 'Pending', 'Event' ),
		347 => array( 'Approved', 'Event' ),
	);

	foreach ( $rows as $entry_id => $row ) {
		GFAPI::$entries[ $entry_id ] = array(
			'id'           => $entry_id,
			'form_id'      => 5,
			'status'       => 'active',
			'date_created' => '2026-07-14 10:00:00',
			'1'            => $row[1],
			'3.3'          => 'Test',
			'3.6'          => (string) $entry_id,
			'4'            => 'Entry ' . $entry_id,
			'5'            => 'Waters Meet',
			'6'            => 'Resource' === $row[1] ? '' : '2026-08-01',
			'16'           => 'In person',
			'17'           => 'Description ' . $entry_id,
			'22'           => $row[0],
		);
	}

	return $secret;
}

function crh_cutover_snapshot() {
	return serialize(
		array(
			'options'    => $GLOBALS['crh_cutover_options'],
			'forms'      => GFAPI::$forms,
			'feeds'      => GFAPI::$feeds,
			'entries'    => GFAPI::$entries,
			'entry_meta' => $GLOBALS['crh_cutover_entry_meta'],
			'posts'      => $GLOBALS['crh_cutover_posts'],
			'post_meta'  => $GLOBALS['crh_cutover_post_meta'],
			'post_terms' => $GLOBALS['crh_cutover_post_terms'],
		)
	);
}

function crh_cutover_has_changes( $value ) {
	if ( is_array( $value ) ) {
		foreach ( $value as $item ) {
			if ( crh_cutover_has_changes( $item ) ) {
				return true;
			}
		}

		return false;
	}

	return ! empty( $value );
}

function crh_cutover_post_for_entry( $entry_id ) {
	foreach ( $GLOBALS['crh_cutover_post_meta'] as $post_id => $meta ) {
		if ( (string) ( $meta['wm_bci_source_entry_id'] ?? '' ) === (string) $entry_id ) {
			return absint( $post_id );
		}
	}

	return 0;
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-taxonomy.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-repository.php';
require_once $cutover_file;
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-reconciliation.php';

$class  = WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover::class;
$secret = crh_cutover_fixture();

crh_cutover_assert( method_exists( $class, 'plan' ) && method_exists( $class, 'apply' ), 'Expected LegacyWorkflowCutover to expose plan() and apply().' );
crh_cutover_assert( method_exists( $class, 'should_defer_automatic_reconciliation' ), 'Expected one marker-aware reconciliation deferral authority.' );
crh_cutover_assert( $class::should_defer_automatic_reconciliation(), 'Expected the retained legacy option to defer automatic reconciliation until cutover completes.' );

$GLOBALS['crh_cutover_options'][ $class::COMPLETED_OPTION ] = array( 'version' => $class::VERSION );
crh_cutover_assert( ! $class::should_defer_automatic_reconciliation(), 'Expected a completed cutover marker to release automatic reconciliation even while the legacy option remains.' );
unset( $GLOBALS['crh_cutover_options'][ $class::COMPLETED_OPTION ] );

$plugin_source = file_get_contents( dirname( __DIR__ ) . '/includes/class-plugin.php' );
$reconciliation_source = file_get_contents( dirname( __DIR__ ) . '/includes/workflow/class-opportunity-reconciliation.php' );
crh_cutover_assert( false !== strpos( $plugin_source, 'LegacyWorkflowCutover::should_defer_automatic_reconciliation' ), 'Expected activation to honor the shared cutover deferral authority.' );
crh_cutover_assert( false !== strpos( $reconciliation_source, 'LegacyWorkflowCutover::should_defer_automatic_reconciliation' ), 'Expected scheduled reconciliation to honor the shared cutover deferral authority.' );

$config         = new WatersMeet\CommunityResourcesHub\Config\Config();
$repository     = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityRepository( $config );
$reconciliation = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityReconciliation( $config, $repository );
$reconciliation->maybe_schedule_reconciliation();
crh_cutover_assert( null === get_option( WatersMeet\CommunityResourcesHub\Workflow\OpportunityReconciliation::PENDING_OPTION, null ), 'Expected cutover deferral not to schedule legacy reconciliation.' );
$GLOBALS['crh_cutover_options'][ WatersMeet\CommunityResourcesHub\Workflow\OpportunityReconciliation::PENDING_OPTION ] = '2026-07-14T12:00:00+00:00';
$reconciliation->maybe_run_pending();
crh_cutover_assert( '2026-07-14T12:00:00+00:00' === get_option( WatersMeet\CommunityResourcesHub\Workflow\OpportunityReconciliation::PENDING_OPTION ), 'Expected cutover deferral not to consume a previously scheduled reconciliation.' );
unset( $GLOBALS['crh_cutover_options'][ WatersMeet\CommunityResourcesHub\Workflow\OpportunityReconciliation::PENDING_OPTION ] );

$cutover = new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover();
$before  = crh_cutover_snapshot();
$plan    = $cutover->plan();

crh_cutover_assert( ! is_wp_error( $plan ) && ! empty( $plan['valid'] ), 'Expected the exact Form 5 / Feed 3 fixture to produce a valid dry run.' );
crh_cutover_assert( $before === crh_cutover_snapshot(), 'Expected dry-run planning to make no options, form, feed, entry, post, taxonomy, or entry-meta writes.' );
crh_cutover_assert( 0 === $GLOBALS['crh_cutover_option_writes'] && 0 === $GLOBALS['crh_cutover_post_writes'], 'Expected dry-run planning to execute no write APIs, even when a write would leave the same value.' );
crh_cutover_assert( 0 === GFAPI::$add_form_calls && 0 === GFAPI::$add_feed_calls, 'Expected a valid dry run not to create fallback Gravity Forms resources.' );
crh_cutover_assert( 0 === $GLOBALS['crh_cutover_http_calls'], 'Expected dry-run planning to make no HTTP requests.' );
crh_cutover_assert( false === strpos( wp_json_encode( $plan ), $secret ), 'Expected the shared Google secret never to appear in a migration plan.' );
crh_cutover_assert( 5 === (int) ( $plan['summary']['form_id'] ?? 0 ) && 3 === (int) ( $plan['summary']['feed_id'] ?? 0 ), 'Expected dry-run identity to remain pinned to Form 5 and Feed 3.' );
crh_cutover_assert( 6 === (int) ( $plan['summary']['entries'] ?? 0 ) && 2 === (int) ( $plan['summary']['pending'] ?? 0 ), 'Expected every active entry, including both Pending entries, in the dry-run inventory.' );

$original_description = GFAPI::$entries[177]['17'];
GFAPI::$entries[177]['17'] = 'Changed after the approved dry run.';
$changed_entry_plan = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
crh_cutover_assert( $plan['hash'] !== $changed_entry_plan['hash'], 'Expected the plan hash to bind the complete source-entry snapshot.' );
GFAPI::$entries[177]['17'] = $original_description;

$original_attempted = $GLOBALS['crh_cutover_entry_meta'][177]['waters_meet_bci_google_sync_attempted_at'];
$GLOBALS['crh_cutover_entry_meta'][177]['waters_meet_bci_google_sync_attempted_at'] = '2026-07-14T12:34:56+00:00';
$changed_history_plan = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
crh_cutover_assert( $plan['hash'] !== $changed_history_plan['hash'], 'Expected the plan hash to bind every legacy history timestamp.' );
$GLOBALS['crh_cutover_entry_meta'][177]['waters_meet_bci_google_sync_attempted_at'] = $original_attempted;

$original_secret = $GLOBALS['crh_cutover_options']['wm_bci_workflow']['google_sync_secret'];
$GLOBALS['crh_cutover_options']['wm_bci_workflow']['google_sync_secret'] = '';
$missing_credentials_plan = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
crh_cutover_assert( empty( $missing_credentials_plan['valid'] ), 'Expected missing production Google credentials to fail cutover preflight.' );
$GLOBALS['crh_cutover_options']['wm_bci_workflow']['google_sync_secret'] = $original_secret;

$original_auto_users = $GLOBALS['crh_cutover_options']['wm_bci_workflow']['auto_approved_user_ids'];
$GLOBALS['crh_cutover_options']['wm_bci_workflow']['auto_approved_user_ids'] = array( 4, 7 );
$missing_auto_user_plan = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
crh_cutover_assert( empty( $missing_auto_user_plan['valid'] ), 'Expected production cutover preflight to require the three configured auto-approved users.' );
$GLOBALS['crh_cutover_options']['wm_bci_workflow']['auto_approved_user_ids'] = $original_auto_users;

$GLOBALS['crh_cutover_options']['active_plugins'] = array( 'wm-bci-workflow/wm-bci-workflow.php' );
$ownership_plan = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
crh_cutover_assert( empty( $ownership_plan['valid'] ), 'Expected preflight to refuse cutover while the legacy workflow plugin is still active.' );
unset( $GLOBALS['crh_cutover_options']['active_plugins'] );

$GLOBALS['crh_cutover_site_options']['active_sitewide_plugins'] = array( 'wm-bci-workflow/wm-bci-workflow.php' => 1 );
$network_ownership_plan = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
crh_cutover_assert( empty( $network_ownership_plan['valid'] ), 'Expected preflight to refuse cutover while the legacy workflow plugin is network-active.' );
unset( $GLOBALS['crh_cutover_site_options']['active_sitewide_plugins'] );

$GLOBALS['crh_cutover_options'][ $class::LOCK_OPTION ] = '2026-07-14T12:00:00+00:00';
$locked_plan  = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
$locked_apply = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->apply( (string) $locked_plan['hash'] );
crh_cutover_assert( is_wp_error( $locked_apply ), 'Expected the atomic execution lock to refuse a concurrent apply.' );
unset( $GLOBALS['crh_cutover_options'][ $class::LOCK_OPTION ] );

$saved_feed = GFAPI::$feeds[3];
unset( GFAPI::$feeds[3] );
$invalid_plan = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
crh_cutover_assert( is_array( $invalid_plan ) && empty( $invalid_plan['valid'] ), 'Expected a missing exact Feed 3 to fail preflight.' );
crh_cutover_assert( 0 === GFAPI::$add_form_calls && 0 === GFAPI::$add_feed_calls, 'Expected cutover preflight never to create fallback Gravity Forms resources.' );
$invalid_before = crh_cutover_snapshot();
$invalid_apply = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->apply( (string) ( $invalid_plan['hash'] ?? '' ) );
crh_cutover_assert( is_wp_error( $invalid_apply ), 'Expected apply to refuse a missing exact Form 5 / Feed 3 adoption target.' );
crh_cutover_assert( 0 === GFAPI::$add_form_calls && 0 === GFAPI::$add_feed_calls, 'Expected failed apply never to create fallback Gravity Forms resources.' );
crh_cutover_assert( $invalid_before === crh_cutover_snapshot(), 'Expected failed exact-adoption preflight not to write any cutover state.' );
GFAPI::$feeds[3] = $saved_feed;

$missing_hash_result = $cutover->apply();
crh_cutover_assert( is_wp_error( $missing_hash_result ), 'Expected apply to require the exact dry-run plan hash.' );

$result = $cutover->apply( (string) $plan['hash'] );
crh_cutover_assert( ! is_wp_error( $result ) && ! empty( $result['valid'] ), 'Expected the preflighted cutover apply to succeed.' );
crh_cutover_assert( 0 === $GLOBALS['crh_cutover_http_calls'], 'Expected cutover apply to import history without any HTTP requests.' );
crh_cutover_assert( 5 === (int) get_option( 'options_wm_bci_form_id' ), 'Expected the exact legacy Form 5 setting to be copied.' );
crh_cutover_assert( 3 === (int) get_option( 'options_wm_bci_calendar_feed_id' ), 'Expected the exact legacy Feed 3 setting to be copied.' );
crh_cutover_assert( $secret === get_option( 'options_wm_bci_google_sync_secret' ), 'Expected the Google shared secret to be copied byte-for-byte.' );
crh_cutover_assert( '24' === get_option( 'options_wm_bci_field_map_time_sensitive' ), 'Expected the new time-sensitive field to use ID 24 explicitly.' );
crh_cutover_assert( '25' === get_option( 'options_wm_bci_field_map_non_date_sensitive_type' ), 'Expected the new non-date-sensitive field to use ID 25 explicitly.' );
crh_cutover_assert( '26' === get_option( 'options_wm_bci_field_map_bci_update' ), 'Expected the new BCI Update field to use ID 26 explicitly.' );
crh_cutover_assert( '16' === get_option( 'options_wm_bci_field_map_location_mode' ), 'Expected the legacy location-mode field to remain ID 16.' );
crh_cutover_assert( null === get_option( 'options_legacy_internal_flag', null ), 'Expected non-shared legacy internals not to become split plugin options.' );
crh_cutover_assert( array_key_exists( 'wm_bci_workflow', $GLOBALS['crh_cutover_options'] ), 'Expected the source legacy option to be retained after cutover.' );

crh_cutover_assert( 6 === count( $GLOBALS['crh_cutover_posts'] ), 'Expected one status-aware post for every active entry, including Pending entries.' );

foreach ( array( 177, 178, 250, 347 ) as $entry_id ) {
	$post_id = crh_cutover_post_for_entry( $entry_id );
	crh_cutover_assert( $post_id && 'publish' === get_post_status( $post_id ), "Expected Approved entry {$entry_id} to import as published." );
}

foreach ( array( 276, 341 ) as $entry_id ) {
	$post_id = crh_cutover_post_for_entry( $entry_id );
	crh_cutover_assert( $post_id && 'pending' === get_post_status( $post_id ), "Expected Pending entry {$entry_id} to import as pending." );
	crh_cutover_assert( 'Pending' === get_post_meta( $post_id, 'wm_bci_approval_status', true ), "Expected Pending entry {$entry_id} to retain its approval label." );
	crh_cutover_assert( '' === get_post_meta( $post_id, 'wm_bci_google_sync_status', true ), "Expected Pending entry {$entry_id} not to invent Google sync state." );
}

$success_post = crh_cutover_post_for_entry( 347 );
crh_cutover_assert( '2026-07-14T10:00:00+00:00' === get_post_meta( $success_post, 'wm_bci_approved_at', true ), 'Expected the historical approved timestamp to replace any import-time timestamp.' );
crh_cutover_assert( 'synced' === get_post_meta( $success_post, 'wm_bci_google_sync_status', true ), 'Expected legacy success to map exactly to synced.' );
crh_cutover_assert( '2026-07-14T10:01:00+00:00' === get_post_meta( $success_post, 'wm_bci_google_sync_attempted_at', true ), 'Expected the legacy attempted timestamp to be retained.' );
crh_cutover_assert( '2026-07-14T10:01:05+00:00' === get_post_meta( $success_post, 'wm_bci_google_sync_synced_at', true ), 'Expected the legacy synced timestamp to be retained.' );
crh_cutover_assert( '' === get_post_meta( $success_post, 'wm_bci_google_sync_error', true ), 'Expected successful legacy sync to retain a blank error.' );

foreach ( array( 177, 178, 250 ) as $entry_id ) {
	$post_id = crh_cutover_post_for_entry( $entry_id );
	$legacy  = $GLOBALS['crh_cutover_entry_meta'][ $entry_id ];
	crh_cutover_assert( 'error' === get_post_meta( $post_id, 'wm_bci_google_sync_status', true ), "Expected failed entry {$entry_id} to map exactly to error." );
	crh_cutover_assert( $legacy['waters_meet_bci_approved_at'] === get_post_meta( $post_id, 'wm_bci_approved_at', true ), "Expected entry {$entry_id} approved timestamp to be retained." );
	crh_cutover_assert( $legacy['waters_meet_bci_google_sync_attempted_at'] === get_post_meta( $post_id, 'wm_bci_google_sync_attempted_at', true ), "Expected entry {$entry_id} attempted timestamp to be retained." );
	crh_cutover_assert( $legacy['waters_meet_bci_google_sync_error'] === get_post_meta( $post_id, 'wm_bci_google_sync_error', true ), "Expected entry {$entry_id} failure message to be retained." );
}

$post_count          = count( $GLOBALS['crh_cutover_posts'] );
$second_write_counts = array( $GLOBALS['crh_cutover_option_writes'], $GLOBALS['crh_cutover_post_writes'] );
$second_plan  = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->plan();
$second_apply = ( new WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover() )->apply( (string) $second_plan['hash'] );
crh_cutover_assert( ! is_wp_error( $second_apply ) && ! crh_cutover_has_changes( $second_apply['changes'] ?? array() ), 'Expected a second apply to return a verified zero-change readback.' );
crh_cutover_assert( $post_count === count( $GLOBALS['crh_cutover_posts'] ), 'Expected idempotent apply not to duplicate imported posts.' );
crh_cutover_assert( $second_write_counts === array( $GLOBALS['crh_cutover_option_writes'], $GLOBALS['crh_cutover_post_writes'] ), 'Expected a zero-change second apply not to execute option or post write APIs.' );
crh_cutover_assert( 0 === $GLOBALS['crh_cutover_http_calls'], 'Expected idempotent readback not to make HTTP requests.' );
crh_cutover_assert( array_key_exists( 'wm_bci_workflow', $GLOBALS['crh_cutover_options'] ), 'Expected idempotent completion not to delete the legacy source option.' );
crh_cutover_assert( is_array( get_option( $class::COMPLETED_OPTION, null ) ), 'Expected apply to persist a versioned completion marker only after verified readback.' );

echo "Legacy workflow cutover service test passed.\n";
