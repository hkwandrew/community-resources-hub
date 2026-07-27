<?php
/**
 * Legacy opportunity reconciliation and adoption.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Config\SettingsSchema;
use WatersMeet\CommunityResourcesHub\ContentModel\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reconciles legacy opportunity rows and imports missing approved entries.
 */
final class OpportunityReconciliation {

	const ACTION          = 'wm_bci_reconcile_opportunities';
	const NONCE_ACTION    = 'wm_bci_reconcile_opportunities';
	const RESULT_TRANSIENT = 'community_resources_hub_opportunity_reconciliation_notice';
	const SUMMARY_OPTION  = 'community_resources_hub_opportunity_reconciliation_summary';
	const PENDING_OPTION  = 'community_resources_hub_opportunity_reconciliation_pending_at';
	const COMPLETED_OPTION = 'community_resources_hub_opportunity_reconciliation_completed_at';
	const LOCK_OPTION     = 'community_resources_hub_opportunity_reconciliation_running_at';
	const QUERY_PAGE_SIZE = 200;

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Opportunity repository.
	 *
	 * @var OpportunityRepository
	 */
	private $repository;

	/**
	 * Field accessors.
	 *
	 * @var FieldAccessor
	 */
	private $fields;

	public function __construct( Config $config, OpportunityRepository $repository ) {
		$this->config     = $config;
		$this->repository = $repository;
		$this->fields     = new FieldAccessor( $config );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'maybe_schedule_reconciliation' ), 15 );
		add_action( 'init', array( $this, 'maybe_run_pending' ), 16 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_manual_reconcile' ) );
	}

	/**
	 * Ensure legacy opportunity adoption runs once per install/update.
	 *
	 * @return void
	 */
	public function maybe_schedule_reconciliation() {
		if ( class_exists( LegacyWorkflowCutover::class ) && LegacyWorkflowCutover::should_defer_automatic_reconciliation() ) {
			return;
		}

		if ( '' !== trim( (string) get_option( self::COMPLETED_OPTION, '' ) ) ) {
			return;
		}

		if ( '' !== trim( (string) get_option( self::PENDING_OPTION, '' ) ) ) {
			return;
		}

		update_option( self::PENDING_OPTION, gmdate( 'c' ), false );
	}

	/**
	 * Run the pending legacy reconciliation job.
	 *
	 * @return void
	 */
	public function maybe_run_pending() {
		if ( class_exists( LegacyWorkflowCutover::class ) && LegacyWorkflowCutover::should_defer_automatic_reconciliation() ) {
			return;
		}

		if ( '' === trim( (string) get_option( self::PENDING_OPTION, '' ) ) ) {
			return;
		}

		if ( $this->is_locked() ) {
			return;
		}

		$this->acquire_lock();
		$this->reconcile();
		$this->release_lock();
	}

	/**
	 * Handle a manual admin-triggered reconciliation run.
	 *
	 * @return void
	 */
	public function handle_manual_reconcile() {
		if ( ! current_user_can( SettingsSchema::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to reconcile BCI opportunities.', 'community-resources-hub' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$this->acquire_lock();
		$summary = $this->reconcile();
		$this->release_lock();

		if ( function_exists( 'set_transient' ) ) {
			set_transient(
				self::RESULT_TRANSIENT,
				array(
					'type'    => (int) ( $summary['unresolved_posts'] ?? 0 ) > 0 ? 'error' : 'success',
					'message' => $this->result_message( $summary ),
				),
				MINUTE_IN_SECONDS
			);
		}

		$redirect = wp_get_referer();

		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . $this->config->opportunity_post_type() );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Run the core reconciliation logic and persist a summary.
	 *
	 * @return array<string,mixed>
	 */
	public function reconcile() {
		$summary = array(
			'processed_posts'   => 0,
			'processed_groups'  => 0,
			'entry_refreshed'   => 0,
			'duplicates_trashed'=> 0,
			'imported_entries'  => 0,
			'source_less_merged'=> 0,
			'unresolved_posts'  => 0,
			'unresolved_ids'    => array(),
			'completed_at'      => gmdate( 'c' ),
		);

		$post_ids = $this->existing_post_ids();
		$summary['processed_posts'] = count( $post_ids );

		$grouped = array();
		$source_less = array();

		foreach ( $post_ids as $post_id ) {
			$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;

			if ( is_object( $post ) && in_array( (string) ( $post->post_status ?? '' ), array( 'trash', 'auto-draft' ), true ) ) {
				continue;
			}

			$source_entry_id = absint( get_post_meta( $post_id, $this->config->opportunity_field_name( 'source_entry_id' ), true ) );

			if ( $source_entry_id < 1 ) {
				$source_less[] = $post_id;
				continue;
			}

			if ( ! isset( $grouped[ $source_entry_id ] ) ) {
				$grouped[ $source_entry_id ] = array();
			}

			$grouped[ $source_entry_id ][] = $post_id;
		}

		foreach ( $grouped as $source_entry_id => $group_ids ) {
			$group_ids = $this->sort_post_ids_oldest_first( $group_ids );

			if ( empty( $group_ids ) ) {
				continue;
			}

			$summary['processed_groups']++;

			$canonical_id = (int) $group_ids[0];
			$duplicates   = array_slice( $group_ids, 1 );
			$entry        = $this->source_entry( $source_entry_id );

			if ( ! empty( $entry ) ) {
				$approval_status = $this->entry_approval_status( $entry );
				$this->repository->upsert_from_entry( $entry, $approval_status );
				$summary['entry_refreshed']++;
			} else {
				$this->merge_duplicate_posts( $canonical_id, $group_ids );
			}

			if ( ! empty( $duplicates ) ) {
				foreach ( $duplicates as $duplicate_id ) {
					if ( function_exists( 'wp_trash_post' ) ) {
						wp_trash_post( $duplicate_id );
						$summary['duplicates_trashed']++;
					}
				}
			}
		}

		$summary['source_less_merged'] = $this->merge_source_less_posts( $source_less, $grouped );
		$summary['unresolved_ids']     = $this->unresolved_source_less_posts( $source_less );
		$summary['unresolved_posts']   = count( $summary['unresolved_ids'] );

		foreach ( $this->approved_entries() as $entry ) {
			$entry_id = absint( $this->fields->value( $entry, 'id' ) );

			if ( ! $entry_id || $this->repository->find_by_source_entry_id( $entry_id ) ) {
				continue;
			}

			if ( $this->repository->upsert_from_entry( $entry, 'Approved' ) ) {
				$summary['imported_entries']++;
			}
		}

		update_option( self::SUMMARY_OPTION, $summary, false );
		update_option( self::COMPLETED_OPTION, $summary['completed_at'], false );
		delete_option( self::PENDING_OPTION );
		$this->flush_runtime_caches();

		return $summary;
	}

	/**
	 * Existing non-trash opportunity IDs.
	 *
	 * @return array<int,int>
	 */
	private function existing_post_ids() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'      => $this->config->opportunity_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
			)
		);

		return is_array( $post_ids ) ? array_map( 'absint', $post_ids ) : array();
	}

	/**
	 * Source entry lookup via Gravity Forms when available.
	 *
	 * @param int $source_entry_id Entry ID.
	 * @return array<string,mixed>
	 */
	private function source_entry( $source_entry_id ) {
		if ( ! $source_entry_id || ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_entry' ) ) {
			return array();
		}

		$entry = \GFAPI::get_entry( $source_entry_id );

		if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
			return array();
		}

		return $entry;
	}

	/**
	 * Reuse the old theme-era approved-entry import seam.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function approved_entries() {
		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_entries' ) ) {
			return array();
		}

		$form_id           = $this->config->form_id();
		$approval_field_id = $this->config->approval_field_id();

		if ( $form_id < 1 || '' === $approval_field_id ) {
			return array();
		}

		$entries = array();
		$total   = 0;
		$offset  = 0;

		do {
			$page = \GFAPI::get_entries(
				$form_id,
				array(
					'status'        => 'active',
					'field_filters' => array(
						array(
							'key'      => $approval_field_id,
							'operator' => 'is',
							'value'    => 'Approved',
						),
					),
				),
				array(
					'key'       => 'date_created',
					'direction' => 'ASC',
				),
				array(
					'offset'    => $offset,
					'page_size' => self::QUERY_PAGE_SIZE,
				),
				$total
			);

			if ( is_wp_error( $page ) || empty( $page ) || ! is_array( $page ) ) {
				break;
			}

			$entries = array_merge( $entries, $page );
			$offset += count( $page );
		} while ( $offset < $total );

		return $entries;
	}

	/**
	 * Derive the repository approval label for one GF entry.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return string
	 */
	private function entry_approval_status( array $entry ) {
		$field_id = $this->config->approval_field_id();
		$value    = '' !== $field_id ? $this->fields->value( $entry, $field_id ) : '';
		$status   = $this->config->status_label( $value );

		if ( '' !== $status ) {
			return $status;
		}

		return 'Approved';
	}

	/**
	 * Merge legacy duplicate data into one canonical post.
	 *
	 * @param int              $canonical_id Canonical post ID.
	 * @param array<int,int>   $group_ids    Duplicate group ordered oldest first.
	 * @return void
	 */
	private function merge_duplicate_posts( $canonical_id, array $group_ids ) {
		$group_ids = $this->sort_post_ids_newest_first( $group_ids );
		$postarr   = array(
			'ID' => $canonical_id,
		);

		$title = $this->latest_non_empty_post_field( $group_ids, 'post_title' );

		if ( '' !== $title ) {
			$postarr['post_title'] = sanitize_text_field( $title );
		}

		$content = $this->latest_non_empty_post_field( $group_ids, 'post_content' );

		if ( '' !== $content ) {
			$postarr['post_content'] = wp_kses_post( $content );
		}

		if ( count( $postarr ) > 1 && function_exists( 'wp_update_post' ) ) {
			wp_update_post( $postarr );
		}

		foreach ( Schema::opportunity_field_map() as $semantic_key => $meta_key ) {
			$value = $this->latest_non_empty_meta_value( $group_ids, $meta_key );

			if ( '' === $value ) {
				continue;
			}

			if ( 'opportunity_type' === $semantic_key ) {
				$value = $this->normalized_type_meta_value( $value );
			} elseif ( 'approval_status' === $semantic_key ) {
				$value = $this->normalized_approval_status( $value );
			} elseif ( 'info_url' === $semantic_key ) {
				$value = esc_url_raw( $value );
			}

			update_post_meta( $canonical_id, $meta_key, $value );
		}

		$term_ids = $this->latest_term_ids( $group_ids );

		if ( ! empty( $term_ids ) ) {
			wp_set_post_terms( $canonical_id, $term_ids, $this->config->opportunity_type_taxonomy(), false );
		} else {
			$this->assign_type_term_from_meta( $canonical_id );
		}

		$approval_status = (string) get_post_meta( $canonical_id, $this->config->opportunity_field_name( 'approval_status' ), true );
		$this->repository->update_post_status_for_approval( $canonical_id, $approval_status );
	}

	/**
	 * Merge source-less posts into unique canonical matches when safe.
	 *
	 * @param array<int,int>        $source_less_ids Source-less post IDs.
	 * @param array<int,array<int,int>> $source_groups Source-linked groups.
	 * @return int
	 */
	private function merge_source_less_posts( array $source_less_ids, array $source_groups ) {
		if ( empty( $source_less_ids ) || empty( $source_groups ) ) {
			return 0;
		}

		$title_map = array();
		$merged    = 0;

		foreach ( $source_groups as $group_ids ) {
			$canonical_id = isset( $group_ids[0] ) ? absint( $group_ids[0] ) : 0;

			if ( ! $canonical_id ) {
				continue;
			}

			$normalized_title = $this->normalized_title( (string) get_post_field( 'post_title', $canonical_id, 'raw' ) );

			if ( '' === $normalized_title ) {
				continue;
			}

			if ( ! isset( $title_map[ $normalized_title ] ) ) {
				$title_map[ $normalized_title ] = array();
			}

			$title_map[ $normalized_title ][] = $canonical_id;
		}

		foreach ( $source_less_ids as $post_id ) {
			$normalized_title = $this->normalized_title( (string) get_post_field( 'post_title', $post_id, 'raw' ) );

			if ( '' === $normalized_title || 1 !== count( $title_map[ $normalized_title ] ?? array() ) ) {
				continue;
			}

			$canonical_id = absint( $title_map[ $normalized_title ][0] );

			if ( ! $canonical_id ) {
				continue;
			}

			$this->merge_duplicate_posts( $canonical_id, array( $canonical_id, $post_id ) );

			if ( function_exists( 'wp_trash_post' ) ) {
				wp_trash_post( $post_id );
				$merged++;
			}
		}

		return $merged;
	}

	/**
	 * Source-less post IDs still unresolved after safe fallback matching.
	 *
	 * @param array<int,int> $source_less_ids Source-less post IDs.
	 * @return array<int,int>
	 */
	private function unresolved_source_less_posts( array $source_less_ids ) {
		$unresolved = array();

		foreach ( $source_less_ids as $post_id ) {
			$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;

			if ( ! is_object( $post ) || 'trash' === (string) ( $post->post_status ?? '' ) ) {
				continue;
			}

			$unresolved[] = absint( $post_id );
		}

		return $unresolved;
	}

	/**
	 * Latest non-empty post field from a duplicate group.
	 *
	 * @param array<int,int> $post_ids Duplicate post IDs newest first.
	 * @param string         $field    Post field name.
	 * @return string
	 */
	private function latest_non_empty_post_field( array $post_ids, $field ) {
		foreach ( $post_ids as $post_id ) {
			$value = trim( (string) get_post_field( $field, $post_id, 'raw' ) );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Latest non-empty meta value from a duplicate group.
	 *
	 * @param array<int,int> $post_ids Duplicate post IDs newest first.
	 * @param string         $meta_key Meta key.
	 * @return string
	 */
	private function latest_non_empty_meta_value( array $post_ids, $meta_key ) {
		foreach ( $post_ids as $post_id ) {
			$value = get_post_meta( $post_id, $meta_key, true );

			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$value = trim( (string) $value );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Latest assigned opportunity-type term IDs from a duplicate group.
	 *
	 * @param array<int,int> $post_ids Duplicate post IDs newest first.
	 * @return array<int,int>
	 */
	private function latest_term_ids( array $post_ids ) {
		if ( ! function_exists( 'wp_get_post_terms' ) ) {
			return array();
		}

		foreach ( $post_ids as $post_id ) {
			$term_ids = wp_get_post_terms(
				$post_id,
				$this->config->opportunity_type_taxonomy(),
				array(
					'fields' => 'ids',
				)
			);

			if ( is_array( $term_ids ) && ! empty( $term_ids ) ) {
				return array_values( array_map( 'absint', $term_ids ) );
			}
		}

		return array();
	}

	/**
	 * Normalize opportunity type meta to the canonical full term name.
	 *
	 * @param string $raw_type Raw type value.
	 * @return string
	 */
	private function normalized_type_meta_value( $raw_type ) {
		$type_config = $this->config->opportunity_type_config( $raw_type );

		if ( '' !== trim( (string) ( $type_config['name'] ?? '' ) ) ) {
			return (string) $type_config['name'];
		}

		return trim( (string) $raw_type );
	}

	/**
	 * Normalize approval labels to the configured set.
	 *
	 * @param string $raw_status Raw status.
	 * @return string
	 */
	private function normalized_approval_status( $raw_status ) {
		$status = $this->config->status_label( $raw_status );

		return '' !== $status ? $status : trim( (string) $raw_status );
	}

	/**
	 * Ensure taxonomy terms stay aligned after a legacy merge.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function assign_type_term_from_meta( $post_id ) {
		if ( ! function_exists( 'wp_set_post_terms' ) ) {
			return;
		}

		$type_value = (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'opportunity_type' ), true );
		$type_config = $this->config->opportunity_type_config( $type_value );

		if ( empty( $type_config['term_id'] ) ) {
			return;
		}

		wp_set_post_terms(
			$post_id,
			array( absint( $type_config['term_id'] ) ),
			$this->config->opportunity_type_taxonomy(),
			false
		);
	}

	/**
	 * Sort posts oldest first.
	 *
	 * @param array<int,int> $post_ids Post IDs.
	 * @return array<int,int>
	 */
	private function sort_post_ids_oldest_first( array $post_ids ) {
		usort(
			$post_ids,
			static function ( $left, $right ) {
				$left_date  = (string) get_post_field( 'post_date', $left, 'raw' );
				$right_date = (string) get_post_field( 'post_date', $right, 'raw' );

				if ( $left_date === $right_date ) {
					return $left <=> $right;
				}

				return strcmp( $left_date, $right_date );
			}
		);

		return array_values( array_map( 'absint', $post_ids ) );
	}

	/**
	 * Sort posts newest first.
	 *
	 * @param array<int,int> $post_ids Post IDs.
	 * @return array<int,int>
	 */
	private function sort_post_ids_newest_first( array $post_ids ) {
		$post_ids = $this->sort_post_ids_oldest_first( $post_ids );
		return array_reverse( $post_ids );
	}

	/**
	 * Stable title normalization for conservative source-less fallback merges.
	 *
	 * @param string $title Post title.
	 * @return string
	 */
	private function normalized_title( $title ) {
		return sanitize_title( html_entity_decode( trim( (string) $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Whether a reconciliation run is already in progress.
	 *
	 * @return bool
	 */
	private function is_locked() {
		$locked_at = trim( (string) get_option( self::LOCK_OPTION, '' ) );

		if ( '' === $locked_at ) {
			return false;
		}

		$timestamp = strtotime( $locked_at );

		return false !== $timestamp && $timestamp > ( time() - MINUTE_IN_SECONDS * 5 );
	}

	/**
	 * Acquire the reconciliation run lock.
	 *
	 * @return void
	 */
	private function acquire_lock() {
		update_option( self::LOCK_OPTION, gmdate( 'c' ), false );
	}

	/**
	 * Release the reconciliation run lock.
	 *
	 * @return void
	 */
	private function release_lock() {
		delete_option( self::LOCK_OPTION );
	}

	/**
	 * Flush cached front-end payloads after a reconciliation run.
	 *
	 * @return void
	 */
	private function flush_runtime_caches() {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'community_resources_hub_member_directory' );
			delete_transient( 'community_resources_hub_approved_opportunities' );
		}
	}

	/**
	 * Human-readable result summary for admin notices.
	 *
	 * @param array<string,mixed> $summary Summary payload.
	 * @return string
	 */
	private function result_message( array $summary ) {
		return sprintf(
			/* translators: 1: duplicates trashed, 2: entries imported, 3: unresolved legacy rows. */
			__( 'BCI opportunity reconciliation complete. %1$d duplicate posts trashed, %2$d missing approved entries imported, %3$d unresolved legacy rows still need manual review.', 'community-resources-hub' ),
			absint( $summary['duplicates_trashed'] ?? 0 ),
			absint( $summary['imported_entries'] ?? 0 ),
			absint( $summary['unresolved_posts'] ?? 0 )
		);
	}
}
