<?php
/**
 * Resumable Google Sheet sync recovery for approved BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Processes explicit Google Sheet backfills in small, restartable batches.
 */
final class GoogleSyncBackfill {

	const CRON_HOOK             = 'wm_bci_google_sync_backfill_batch';
	const JOB_OPTION            = 'community_resources_hub_google_sync_backfill_job';
	const LOCK_OPTION           = 'community_resources_hub_google_sync_backfill_lock';
	const BATCH_SIZE            = 2;
	const LOCK_TTL              = 120;
	const ERROR_PAUSE_THRESHOLD = 3;

	/**
	 * Workflow configuration.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Single-opportunity sync service.
	 *
	 * @var object
	 */
	private $sync;

	/**
	 * Set workflow dependencies.
	 *
	 * @param Config $config Workflow configuration.
	 * @param object $sync Single-opportunity sync service.
	 */
	public function __construct( Config $config, $sync ) {
		$this->config = $config;
		$this->sync   = $sync;
	}

	/**
	 * Register the background worker.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::CRON_HOOK, array( $this, 'process_batch' ) );
	}

	/**
	 * Published approved opportunity IDs in deterministic oldest-first order.
	 *
	 * @return array<int,int>
	 */
	public function approved_post_ids() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'              => $this->config->opportunity_post_type(),
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'meta_key'               => $this->config->opportunity_field_name( 'approval_status' ),
				'meta_value'             => 'Approved',
				'orderby'                => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
				'no_found_rows'          => true,
				'suppress_filters'       => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
			)
		);

		if ( ! is_array( $post_ids ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
	}

	/**
	 * Approved opportunity IDs that have not reached the current success state.
	 *
	 * @return array<int,int>
	 */
	public function eligible_post_ids() {
		return array_values(
			array_filter(
				$this->approved_post_ids(),
				function ( $post_id ) {
					return 'synced' !== $this->sync_meta( $post_id, 'google_sync_status' );
				}
			)
		);
	}

	/**
	 * Admin-facing counts for approved sync states.
	 *
	 * @return array<string,int>
	 */
	public function status_counts() {
		$counts = array(
			'approved' => 0,
			'synced'   => 0,
			'pending'  => 0,
			'failed'   => 0,
			'skipped'  => 0,
			'unsynced' => 0,
		);

		foreach ( $this->approved_post_ids() as $post_id ) {
			$status = $this->sync_meta( $post_id, 'google_sync_status' );

			++$counts['approved'];

			if ( 'synced' === $status ) {
				++$counts['synced'];
				continue;
			}

			++$counts['unsynced'];

			if ( 'pending' === $status ) {
				++$counts['pending'];
			} elseif ( 'error' === $status ) {
				++$counts['failed'];
			} elseif ( 'skipped' === $status ) {
				++$counts['skipped'];
			}
		}

		return $counts;
	}

	/**
	 * Most recently attempted explicit sync failure.
	 *
	 * @return array<string,mixed>
	 */
	public function latest_failure() {
		$latest = array();

		foreach ( $this->approved_post_ids() as $post_id ) {
			if ( 'error' !== $this->sync_meta( $post_id, 'google_sync_status' ) ) {
				continue;
			}

			$attempted_at = $this->sync_meta( $post_id, 'google_sync_attempted_at' );

			if ( ! empty( $latest ) && strcmp( $attempted_at, (string) $latest['attempted_at'] ) < 0 ) {
				continue;
			}

			$latest = array(
				'post_id'      => $post_id,
				'attempted_at' => $attempted_at,
				'error'        => $this->sync_meta( $post_id, 'google_sync_error' ),
			);
		}

		return $latest;
	}

	/**
	 * Sync the newest eligible opportunity without creating a bulk job.
	 *
	 * @return array<string,mixed>
	 */
	public function sync_one() {
		if ( ! $this->config->is_google_sync_configured() ) {
			return $this->single_result( 0, false, 'skipped', __( 'Google sync is not configured.', 'community-resources-hub' ) );
		}

		$post_ids = $this->eligible_post_ids();
		$post_id  = empty( $post_ids ) ? 0 : absint( end( $post_ids ) );

		if ( ! $post_id ) {
			return $this->single_result( 0, false, '', __( 'No approved opportunities are awaiting sync.', 'community-resources-hub' ) );
		}

		$success = (bool) $this->sync->sync_opportunity( $post_id );
		$status  = $this->sync_meta( $post_id, 'google_sync_status' );
		$error   = $this->sync_meta( $post_id, 'google_sync_error' );

		return $this->single_result( $post_id, $success && 'synced' === $status, $status, $error );
	}

	/**
	 * Start a new snapshot unless an active job already exists.
	 *
	 * @param bool $replace_existing Replace any persisted job state.
	 * @return array<string,mixed>
	 */
	public function start_backfill( $replace_existing = false ) {
		$current = $this->job();

		if ( ! $replace_existing && in_array( (string) ( $current['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
			return $current;
		}

		self::clear_scheduled_work();

		$post_ids = $this->eligible_post_ids();
		$now      = gmdate( 'c' );
		$job      = array(
			'id'                      => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'wm-bci-', true ),
			'status'                  => empty( $post_ids ) ? 'complete' : 'queued',
			'post_ids'                => $post_ids,
			'cursor'                  => 0,
			'total'                   => count( $post_ids ),
			'attempted'               => 0,
			'synced'                  => 0,
			'failed'                  => 0,
			'skipped'                 => 0,
			'consecutive_error_count' => 0,
			'consecutive_error'       => '',
			'last_error'              => '',
			'pause_reason'            => '',
			'started_at'              => $now,
			'updated_at'              => $now,
			'paused_at'               => '',
			'completed_at'            => empty( $post_ids ) ? $now : '',
		);

		$this->save_job( $job );

		if ( ! empty( $post_ids ) ) {
			$this->schedule_next_batch();
		}

		return $job;
	}

	/**
	 * Replace prior progress with a fresh snapshot of every remaining row.
	 *
	 * @return array<string,mixed>
	 */
	public function retry_remaining() {
		$current = $this->job();

		if ( in_array( (string) ( $current['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
			return $current;
		}

		return $this->start_backfill( true );
	}

	/**
	 * Resume a paused snapshot from its saved cursor.
	 *
	 * @return array<string,mixed>
	 */
	public function resume() {
		$job = $this->job();

		if ( 'paused' !== (string) ( $job['status'] ?? '' ) ) {
			return $job;
		}

		$job['status']                  = 'queued';
		$job['consecutive_error_count'] = 0;
		$job['consecutive_error']       = '';
		$job['pause_reason']            = '';
		$job['paused_at']               = '';
		$job['updated_at']              = gmdate( 'c' );

		$this->save_job( $job );
		$this->schedule_next_batch();

		return $job;
	}

	/**
	 * Process one bounded cron batch.
	 *
	 * @return void
	 */
	public function process_batch() {
		$job = $this->job();

		if ( ! in_array( (string) ( $job['status'] ?? '' ), array( 'queued', 'running' ), true ) || ! $this->acquire_lock() ) {
			return;
		}

		try {
			$job = $this->job();

			if ( ! in_array( (string) ( $job['status'] ?? '' ), array( 'queued', 'running' ), true ) ) {
				return;
			}

			if ( ! $this->config->is_google_sync_configured() ) {
				$this->pause_job( $job, __( 'Google sync is not configured.', 'community-resources-hub' ) );
				return;
			}

			$job['status']     = 'running';
			$job['updated_at'] = gmdate( 'c' );
			$this->save_job( $job );

			$processed = 0;

			while ( $processed < self::BATCH_SIZE && (int) $job['cursor'] < (int) $job['total'] ) {
				$post_id = absint( $job['post_ids'][ (int) $job['cursor'] ] ?? 0 );
				++$job['cursor'];
				++$processed;

				if ( ! $this->is_still_eligible( $post_id ) ) {
					++$job['skipped'];
					$this->reset_consecutive_error( $job );
					$job['updated_at'] = gmdate( 'c' );
					$this->save_job( $job );
					continue;
				}

				++$job['attempted'];
				$success = (bool) $this->sync->sync_opportunity( $post_id );
				$status  = $this->sync_meta( $post_id, 'google_sync_status' );

				if ( $success && 'synced' === $status ) {
					++$job['synced'];
					$this->reset_consecutive_error( $job );
				} elseif ( 'skipped' === $status ) {
					++$job['skipped'];
					$this->reset_consecutive_error( $job );
				} else {
					++$job['failed'];
					$error = $this->sync_meta( $post_id, 'google_sync_error' );

					if ( '' === $error ) {
						$error = __( 'Google sync failed without an error message.', 'community-resources-hub' );
					}

					$this->record_error( $job, $error );
				}

				$job['updated_at'] = gmdate( 'c' );
				$this->save_job( $job );

				if ( (int) $job['consecutive_error_count'] >= self::ERROR_PAUSE_THRESHOLD ) {
					$this->pause_job( $job, $job['last_error'] );
					return;
				}
			}

			if ( (int) $job['cursor'] >= (int) $job['total'] ) {
				$job['status']       = 'complete';
				$job['completed_at'] = gmdate( 'c' );
				$job['updated_at']   = $job['completed_at'];
				$this->save_job( $job );
				self::clear_scheduled_work();
				return;
			}

			$this->schedule_next_batch();
		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Persisted job state.
	 *
	 * @return array<string,mixed>
	 */
	public function job() {
		$job = get_option( self::JOB_OPTION, array() );

		return is_array( $job ) ? $job : array();
	}

	/**
	 * Clear worker scheduling and locks while preserving resumable job state.
	 *
	 * @return void
	 */
	public static function clear_scheduled_work() {
		if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		}

		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Remove all plugin-owned backfill state.
	 *
	 * @return void
	 */
	public static function delete_state() {
		self::clear_scheduled_work();
		delete_option( self::JOB_OPTION );
	}

	/**
	 * Build a normalized one-entry operation result.
	 *
	 * @param int    $post_id Opportunity post ID.
	 * @param bool   $success Whether sync succeeded.
	 * @param string $status Persisted status.
	 * @param string $error Persisted error.
	 * @return array<string,mixed>
	 */
	private function single_result( $post_id, $success, $status, $error ) {
		return array(
			'post_id' => absint( $post_id ),
			'success' => (bool) $success,
			'status'  => (string) $status,
			'error'   => substr( sanitize_text_field( (string) $error ), 0, 500 ),
		);
	}

	/**
	 * Confirm that a snapshotted post still qualifies for export.
	 *
	 * @param int $post_id Opportunity post ID.
	 * @return bool
	 */
	private function is_still_eligible( $post_id ) {
		if ( ! $post_id || ! function_exists( 'get_post_status' ) || 'publish' !== get_post_status( $post_id ) ) {
			return false;
		}

		if ( 'Approved' !== $this->sync_meta( $post_id, 'approval_status' ) ) {
			return false;
		}

		return 'synced' !== $this->sync_meta( $post_id, 'google_sync_status' );
	}

	/**
	 * Persist one failure and its consecutive-error state.
	 *
	 * @param array<string,mixed> $job Job state.
	 * @param string              $error Current error.
	 * @return void
	 */
	private function record_error( array &$job, $error ) {
		$error = substr( sanitize_text_field( (string) $error ), 0, 500 );

		if ( $error === (string) $job['consecutive_error'] ) {
			++$job['consecutive_error_count'];
		} else {
			$job['consecutive_error']       = $error;
			$job['consecutive_error_count'] = 1;
		}

		$job['last_error'] = $error;
	}

	/**
	 * Reset consecutive-error state after a non-failure.
	 *
	 * @param array<string,mixed> $job Job state.
	 * @return void
	 */
	private function reset_consecutive_error( array &$job ) {
		$job['consecutive_error_count'] = 0;
		$job['consecutive_error']       = '';
	}

	/**
	 * Pause a job and remove its scheduled worker.
	 *
	 * @param array<string,mixed> $job Job state.
	 * @param string              $reason Pause reason.
	 * @return void
	 */
	private function pause_job( array $job, $reason ) {
		$job['status']       = 'paused';
		$job['last_error']   = substr( sanitize_text_field( (string) $reason ), 0, 500 );
		$job['pause_reason'] = $job['last_error'];
		$job['paused_at']    = gmdate( 'c' );
		$job['updated_at']   = $job['paused_at'];

		$this->save_job( $job );
		self::clear_scheduled_work();
	}

	/**
	 * Schedule a single follow-up worker if none is queued.
	 *
	 * @return void
	 */
	private function schedule_next_batch() {
		if ( ! function_exists( 'wp_schedule_single_event' ) || ( function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( self::CRON_HOOK ) ) ) {
			return;
		}

		wp_schedule_single_event( time() + 5, self::CRON_HOOK );
	}

	/**
	 * Persist job state without autoloading it.
	 *
	 * @param array<string,mixed> $job Job state.
	 * @return void
	 */
	private function save_job( array $job ) {
		if ( null === get_option( self::JOB_OPTION, null ) ) {
			add_option( self::JOB_OPTION, $job, '', false );
			return;
		}

		update_option( self::JOB_OPTION, $job, false );
	}

	/**
	 * Acquire an expiring Options API lock.
	 *
	 * @return bool
	 */
	private function acquire_lock() {
		$expires_at = time() + self::LOCK_TTL;

		if ( add_option( self::LOCK_OPTION, $expires_at, '', false ) ) {
			return true;
		}

		if ( (int) get_option( self::LOCK_OPTION, 0 ) >= time() ) {
			return false;
		}

		delete_option( self::LOCK_OPTION );

		return add_option( self::LOCK_OPTION, $expires_at, '', false );
	}

	/**
	 * Release the Options API lock.
	 *
	 * @return void
	 */
	private function release_lock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Read one workflow meta value.
	 *
	 * @param int    $post_id Opportunity post ID.
	 * @param string $semantic_key Semantic meta key.
	 * @return string
	 */
	private function sync_meta( $post_id, $semantic_key ) {
		return trim( (string) get_post_meta( absint( $post_id ), $this->config->opportunity_field_name( $semantic_key ), true ) );
	}
}
