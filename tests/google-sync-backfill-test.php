<?php
/**
 * Regression checks for resumable Google Sheet sync recovery.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options']          = array();
$GLOBALS['crh_posts']            = array();
$GLOBALS['crh_post_meta']        = array();
$GLOBALS['crh_cron']             = array();
$GLOBALS['crh_registered_hooks'] = array();
$GLOBALS['crh_uuid_counter']     = 0;

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->message = (string) $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

function crh_fail( $message ) {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

function crh_assert( $condition, $message ) {
	if ( ! $condition ) {
		crh_fail( $message );
	}
}

function crh_reset_backfill_fixture() {
	$GLOBALS['crh_options'] = array(
		'options_wm_bci_google_sync_url'    => 'https://script.google.com/macros/s/local-test/exec',
		'options_wm_bci_google_sync_secret' => 'local-test-secret',
	);

	$GLOBALS['crh_posts'] = array(
		101 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish', 'post_date' => '2026-07-01 09:00:00' ),
		102 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish', 'post_date' => '2026-07-02 09:00:00' ),
		103 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish', 'post_date' => '2026-07-03 09:00:00' ),
		104 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish', 'post_date' => '2026-07-04 09:00:00' ),
		105 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'draft', 'post_date' => '2026-07-05 09:00:00' ),
		106 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish', 'post_date' => '2026-07-06 09:00:00' ),
		107 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish', 'post_date' => '2026-07-07 09:00:00' ),
	);

	$GLOBALS['crh_post_meta'] = array(
		101 => array( 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => '' ),
		102 => array(
			'wm_bci_approval_status'          => 'Approved',
			'wm_bci_google_sync_status'       => 'error',
			'wm_bci_google_sync_error'        => 'Earlier failure.',
			'wm_bci_google_sync_attempted_at' => '2026-07-09T12:00:00+00:00',
		),
		103 => array( 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => 'synced' ),
		104 => array( 'wm_bci_approval_status' => 'Pending', 'wm_bci_google_sync_status' => '' ),
		105 => array( 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => '' ),
		106 => array( 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => 'skipped' ),
		107 => array( 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => 'pending' ),
	);

	$GLOBALS['crh_cron']             = array();
	$GLOBALS['crh_registered_hooks'] = array();
	$GLOBALS['crh_uuid_counter']     = 0;
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

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] ) ? $GLOBALS['crh_options'][ $option ] : $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
		if ( array_key_exists( $option, $GLOBALS['crh_options'] ) ) {
			return false;
		}

		$GLOBALS['crh_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['crh_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		unset( $GLOBALS['crh_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$matches = array();

		foreach ( $GLOBALS['crh_posts'] as $post_id => $post ) {
			if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) {
				continue;
			}

			if ( isset( $args['post_status'] ) && $post['post_status'] !== $args['post_status'] ) {
				continue;
			}

			if ( ! empty( $args['meta_key'] ) ) {
				$value = $GLOBALS['crh_post_meta'][ $post_id ][ $args['meta_key'] ] ?? '';

				if ( (string) $value !== (string) ( $args['meta_value'] ?? '' ) ) {
					continue;
				}
			}

			$matches[] = (int) $post_id;
		}

		usort(
			$matches,
			static function ( $left, $right ) {
				$comparison = strcmp( $GLOBALS['crh_posts'][ $left ]['post_date'], $GLOBALS['crh_posts'][ $right ]['post_date'] );
				return 0 !== $comparison ? $comparison : $left <=> $right;
			}
		);

		return $matches;
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	function get_post_status( $post_id ) {
		return $GLOBALS['crh_posts'][ $post_id ]['post_status'] ?? false;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $meta_key = '', $single = false ) {
		return $GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4() {
		$GLOBALS['crh_uuid_counter']++;
		return sprintf( '11111111-2222-4333-8444-%012d', $GLOBALS['crh_uuid_counter'] );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) {
		return $GLOBALS['crh_cron'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
		$GLOBALS['crh_cron'][ $hook ] = (int) $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array(), $wp_error = false ) {
		unset( $GLOBALS['crh_cron'][ $hook ] );
		return 1;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['crh_registered_hooks'][ $hook_name ] = $callback;
		return true;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

final class CrhFakeGoogleSync {
	public $calls = array();
	public $outcomes = array();

	public function sync_opportunity( $post_id ) {
		$post_id       = absint( $post_id );
		$this->calls[] = $post_id;
		$outcome       = $this->outcomes[ $post_id ] ?? array( 'status' => 'synced', 'error' => '' );
		$status        = (string) ( $outcome['status'] ?? 'error' );
		$error         = (string) ( $outcome['error'] ?? '' );

		update_post_meta( $post_id, 'wm_bci_google_sync_status', $status );
		update_post_meta( $post_id, 'wm_bci_google_sync_error', $error );
		update_post_meta( $post_id, 'wm_bci_google_sync_attempted_at', gmdate( 'c' ) );

		return 'synced' === $status;
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-google-sync-backfill.php';

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Workflow\GoogleSyncBackfill;

crh_reset_backfill_fixture();

$sync     = new CrhFakeGoogleSync();
$backfill = new GoogleSyncBackfill( new Config(), $sync );

$backfill->register();
crh_assert( isset( $GLOBALS['crh_registered_hooks'][ GoogleSyncBackfill::CRON_HOOK ] ), 'Expected the backfill worker to register its cron hook.' );

$counts = $backfill->status_counts();
crh_assert( 5 === $counts['approved'], 'Expected five published approved opportunities.' );
crh_assert( 1 === $counts['synced'], 'Expected one synced approved opportunity.' );
crh_assert( 1 === $counts['pending'], 'Expected one pending sync attempt.' );
crh_assert( 1 === $counts['failed'], 'Expected one failed sync attempt.' );
crh_assert( 1 === $counts['skipped'], 'Expected one skipped sync attempt.' );
crh_assert( 4 === $counts['unsynced'], 'Expected every non-synced approved opportunity to be recoverable.' );
crh_assert( array( 101, 102, 106, 107 ) === $backfill->eligible_post_ids(), 'Expected blank, error, skipped, and pending statuses in the backfill snapshot.' );

$latest_failure = $backfill->latest_failure();
crh_assert( 102 === ( $latest_failure['post_id'] ?? 0 ), 'Expected the latest explicit sync error to be reported.' );
crh_assert( 'Earlier failure.' === ( $latest_failure['error'] ?? '' ), 'Expected the persisted endpoint error to remain visible.' );

$single = $backfill->sync_one();
crh_assert( 107 === ( $single['post_id'] ?? 0 ), 'Expected Sync One Entry to choose the newest eligible approved opportunity.' );
crh_assert( true === ( $single['success'] ?? false ), 'Expected a successful one-entry sync result.' );

crh_reset_backfill_fixture();
$sync = new CrhFakeGoogleSync();
$sync->outcomes[102] = array( 'status' => 'error', 'error' => 'One-off remote failure.' );
$backfill = new GoogleSyncBackfill( new Config(), $sync );
$job = $backfill->start_backfill();

crh_assert( 'queued' === ( $job['status'] ?? '' ), 'Expected an explicit backfill start to queue work.' );
crh_assert( 4 === ( $job['total'] ?? 0 ), 'Expected the job to snapshot all currently unsynced approved opportunities.' );
crh_assert( isset( $GLOBALS['crh_cron'][ GoogleSyncBackfill::CRON_HOOK ] ), 'Expected the queued backfill to schedule a worker.' );

$active_retry = $backfill->retry_remaining();
crh_assert( $job['id'] === ( $active_retry['id'] ?? '' ), 'Expected Retry Remaining not to replace an active job or its lock boundary.' );

$backfill->process_batch();
$job = $backfill->job();
crh_assert( 2 === ( $job['cursor'] ?? 0 ), 'Expected the first worker pass to process exactly two entries.' );
crh_assert( 2 === ( $job['attempted'] ?? 0 ), 'Expected attempted progress to persist after each batch.' );
crh_assert( 1 === ( $job['synced'] ?? 0 ), 'Expected the successful row in the first batch to be counted.' );
crh_assert( 1 === ( $job['failed'] ?? 0 ), 'Expected the failed row in the first batch to be counted.' );

unset( $GLOBALS['crh_cron'][ GoogleSyncBackfill::CRON_HOOK ] );
$backfill->process_batch();
$job = $backfill->job();
crh_assert( 'complete' === ( $job['status'] ?? '' ), 'Expected the resumable job to complete after the remaining batch.' );
crh_assert( 4 === ( $job['cursor'] ?? 0 ), 'Expected the completed cursor to match the snapshot total.' );
crh_assert( 3 === ( $job['synced'] ?? 0 ), 'Expected all successful entries to be counted across batches.' );

crh_reset_backfill_fixture();
foreach ( array( 101, 102, 106, 107 ) as $post_id ) {
	$GLOBALS['crh_post_meta'][ $post_id ]['wm_bci_google_sync_status'] = '';
}

$sync = new CrhFakeGoogleSync();
foreach ( array( 101, 102, 106, 107 ) as $post_id ) {
	$sync->outcomes[ $post_id ] = array( 'status' => 'error', 'error' => 'Apps Script unavailable.' );
}

$backfill = new GoogleSyncBackfill( new Config(), $sync );
$backfill->retry_remaining();
$backfill->process_batch();
unset( $GLOBALS['crh_cron'][ GoogleSyncBackfill::CRON_HOOK ] );
$backfill->process_batch();
$job = $backfill->job();

crh_assert( 'paused' === ( $job['status'] ?? '' ), 'Expected three identical consecutive errors to pause the job.' );
crh_assert( 3 === ( $job['cursor'] ?? 0 ), 'Expected processing to stop immediately at the third repeated error.' );
crh_assert( 3 === ( $job['consecutive_error_count'] ?? 0 ), 'Expected the repeated error threshold to be persisted.' );
crh_assert( ! isset( $GLOBALS['crh_cron'][ GoogleSyncBackfill::CRON_HOOK ] ), 'Expected a paused job not to schedule another worker.' );

$resumed = $backfill->resume();
crh_assert( 'queued' === ( $resumed['status'] ?? '' ), 'Expected an explicit resume action to requeue a paused job.' );
crh_assert( 0 === ( $resumed['consecutive_error_count'] ?? -1 ), 'Expected resume to reset the repeated-error counter.' );

crh_reset_backfill_fixture();
$sync     = new CrhFakeGoogleSync();
$backfill = new GoogleSyncBackfill( new Config(), $sync );
$backfill->start_backfill();
$GLOBALS['crh_options'][ GoogleSyncBackfill::LOCK_OPTION ] = time() + 120;
$backfill->process_batch();
crh_assert( 0 === ( $backfill->job()['cursor'] ?? -1 ), 'Expected an active lock to prevent a concurrent worker from advancing the job.' );
crh_assert( array() === $sync->calls, 'Expected a locked worker not to issue remote sync requests.' );

delete_option( GoogleSyncBackfill::LOCK_OPTION );
$GLOBALS['crh_options']['options_wm_bci_google_sync_secret'] = '';
$backfill->process_batch();
$job = $backfill->job();
crh_assert( 'paused' === ( $job['status'] ?? '' ), 'Expected missing configuration to pause rather than discard the job.' );
crh_assert( 0 === ( $job['cursor'] ?? -1 ), 'Expected a configuration pause to preserve the current cursor.' );
crh_assert( 'Google sync is not configured.' === ( $job['last_error'] ?? '' ), 'Expected a clear configuration pause reason.' );

fwrite( STDOUT, "Google sync backfill contract test passed.\n" );
