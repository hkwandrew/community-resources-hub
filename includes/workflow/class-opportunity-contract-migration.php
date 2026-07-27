<?php
/**
 * Explicit Opportunity Hub form and entry contract migration.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Config\Provisioner;
use WatersMeet\CommunityResourcesHub\ContentModel\Schema;
use WatersMeet\CommunityResourcesHub\ContentModel\Taxonomy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plans and applies the one-time split opportunity contract.
 */
final class OpportunityContractMigration {

	const VERSION          = 'opportunity-contract-v2';
	const COMPLETED_OPTION = 'community_resources_hub_opportunity_contract_migration_completed';
	const LOCK_OPTION      = 'community_resources_hub_opportunity_contract_migration_lock';

	/** @var Config */
	private $config;

	/** @var Provisioner */
	private $provisioner;

	/** @var OpportunityRepository */
	private $repository;

	/** @var Taxonomy */
	private $taxonomy;

	/** @var FieldAccessor */
	private $fields;

	public function __construct(
		?Config $config = null,
		?Provisioner $provisioner = null,
		?OpportunityRepository $repository = null,
		?Taxonomy $taxonomy = null
	) {
		$this->config      = $config ?: new Config();
		$this->provisioner = $provisioner ?: new Provisioner( $this->config );
		$this->repository  = $repository ?: new OpportunityRepository( $this->config );
		$this->taxonomy    = $taxonomy ?: new Taxonomy();
		$this->fields      = new FieldAccessor( $this->config );
	}

	/**
	 * Build a completely read-only migration plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan() {
		$errors = array();

		if ( ! class_exists( 'GFAPI' ) ) {
			$errors[] = 'Gravity Forms is not available.';
			return $this->finalize_plan( array(), array(), array(), $errors, false );
		}

		$form_id = $this->config->form_id();
		$form    = $form_id ? \GFAPI::get_form( $form_id ) : null;

		if ( ! is_array( $form ) ) {
			$errors[] = 'The configured Gravity Form could not be loaded.';
			return $this->finalize_plan( array(), array(), array(), $errors, false );
		}

		$form_plan = $this->provisioner->prepare_form_contract( $form );

		if ( is_wp_error( $form_plan ) ) {
			$errors[] = $form_plan->get_error_message();
			$form_plan = array( 'form' => $form, 'updated' => false );
		}

		$entries          = $this->active_entries( $form_id, $errors );
		$posts            = $this->source_posts( $errors );
		$expectations     = self::dataset_expectations( $entries, $this->config->approval_field_id() );
		$active_entry_ids = array();

		foreach ( $entries as $entry ) {
			$entry_id = absint( $entry['id'] ?? 0 );

			if ( $entry_id ) {
				$active_entry_ids[ $entry_id ] = true;
			}
		}

		if ( $expectations['posts'] !== count( $posts ) ) {
			$errors[] = sprintf(
				'Expected %d source-linked opportunity posts for %d active entries; found %d.',
				$expectations['posts'],
				$expectations['entries'],
				count( $posts )
			);
		}

		foreach ( $posts as $entry_id => $post_id ) {
			if ( ! isset( $active_entry_ids[ $entry_id ] ) ) {
				$errors[] = sprintf( 'Opportunity post %d links to inactive entry %d.', $post_id, $entry_id );
			}
		}

		$entry_plans = array();
		$type_counts = array(
			'Event'                                   => 0,
			'Grant / RFP'                             => 0,
			'Workshop, training, or other learning'   => 0,
			'Other'                                   => 0,
			'Resource'                                => 0,
			'Recommended Vendor'                      => 0,
		);
		$bci_count   = 0;
		$approved    = 0;
		$pending     = 0;

		foreach ( $entries as $entry ) {
			$entry_plan = $this->entry_contract( $entry );

			if ( is_wp_error( $entry_plan ) ) {
				$errors[] = $entry_plan->get_error_message();
				continue;
			}

			$entry_id = (int) $entry_plan['entry_id'];
			$status   = trim( (string) $this->fields->value( $entry, $this->config->approval_field_id() ) );

			if ( 'Approved' === $status ) {
				$approved++;
			} elseif ( 'Pending' === $status ) {
				$pending++;
			} else {
				$errors[] = sprintf( 'Entry %d has unsupported approval status "%s".', $entry_id, $status );
			}

			if ( in_array( $status, array( 'Approved', 'Pending' ), true ) && ! isset( $posts[ $entry_id ] ) ) {
				$errors[] = sprintf( 'Entry %d must be linked to one approval-aware opportunity post.', $entry_id );
			}

			$type_counts[ $entry_plan['type'] ]++;
			$bci_count += $entry_plan['is_bci_update'] ? 1 : 0;

			if ( $entry_plan['is_time_sensitive'] && '' === $entry_plan['primary_date'] ) {
				$errors[] = sprintf( 'Date-sensitive entry %d has no usable primary date.', $entry_id );
			}

			if ( '' === $this->fields->submitted_at( $entry ) ) {
				$errors[] = sprintf( 'Entry %d has an invalid date_created timestamp.', $entry_id );
			}

			$entry_plan['post_id']        = isset( $posts[ $entry_id ] ) ? (int) $posts[ $entry_id ] : 0;
			$entry_plan['approval_status'] = $status;
			$entry_plan['post_needs_sync'] = $entry_plan['post_id']
				? $this->post_needs_sync( $entry_plan['post_id'], $entry, $entry_plan )
				: false;
			$entry_plans[] = $entry_plan;
		}

		if ( $expectations['entries'] !== array_sum( $type_counts ) ) {
			$errors[] = 'Every active entry must resolve to one supported opportunity type.';
		}

		if (
			$expectations['bci_updates'] !== $bci_count
			|| $expectations['approved'] !== $approved
			|| $expectations['pending'] !== $pending
		) {
			$errors[] = sprintf(
				'Expected %d BCI Updates, %d Approved entries, and %d Pending entries; found %d/%d/%d.',
				$expectations['bci_updates'],
				$expectations['approved'],
				$expectations['pending'],
				$bci_count,
				$approved,
				$pending
			);
		}

		$tag_term      = get_term_by( 'slug', Schema::BCI_UPDATE_TAG_SLUG, Schema::OPPORTUNITY_TAG_TAXONOMY );
		$old_type_term = get_term_by( 'slug', Schema::BCI_UPDATE_TAG_SLUG, Schema::OPPORTUNITY_TYPE_TAXONOMY );
		$changes       = array(
			'form'              => ! empty( $form_plan['updated'] ),
			'entry_fields'      => array_sum( array_map( static function ( $row ) { return count( $row['field_updates'] ); }, $entry_plans ) ),
			'entries'           => count( array_filter( $entry_plans, static function ( $row ) { return ! empty( $row['field_updates'] ); } ) ),
			'posts'             => count( array_filter( $entry_plans, static function ( $row ) { return ! empty( $row['post_needs_sync'] ); } ) ),
			'create_bci_tag'    => ! $tag_term,
			'delete_old_bci_type' => (bool) $old_type_term,
		);

		$summary = array(
			'site_url'         => function_exists( 'site_url' ) ? site_url() : '',
			'home_url'         => function_exists( 'home_url' ) ? home_url() : '',
			'wordpress_version' => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
			'php_version'      => PHP_VERSION,
			'timezone'         => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : (string) get_option( 'timezone_string', 'UTC' ),
			'form_id'          => $form_id,
			'entries'          => count( $entries ),
			'posts'            => count( $posts ),
			'time_sensitive'   => $type_counts['Event'] + $type_counts['Grant / RFP'] + $type_counts['Workshop, training, or other learning'] + $type_counts['Other'],
			'non_date_sensitive' => $type_counts['Resource'] + $type_counts['Recommended Vendor'],
			'bci_updates'      => $bci_count,
			'type_counts'      => $type_counts,
		);

		return $this->finalize_plan( $summary, $changes, $entry_plans, $errors, ! empty( $form_plan['updated'] ) );
	}

	/**
	 * Apply an error-free plan under an atomic lock.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function apply() {
		if ( ! $this->acquire_lock() ) {
			return new \WP_Error( 'community_resources_hub_migration_locked', 'The opportunity-contract migration is already running.' );
		}

		try {
			$plan = $this->plan();

			if ( ! $plan['valid'] ) {
				return new \WP_Error( 'community_resources_hub_migration_preflight_failed', implode( ' ', $plan['errors'] ) );
			}

			if ( ! $this->has_changes( $plan['changes'] ) ) {
				$this->persist_completion_marker( $plan, $plan['hash'] );
				return $plan;
			}

			$form_id = $this->config->form_id();
			$form    = \GFAPI::get_form( $form_id );
			$result  = $this->provisioner->reconcile_form_contract( $form );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			foreach ( $plan['entry_plans'] as $entry_plan ) {
				foreach ( $entry_plan['field_updates'] as $field_id => $value ) {
					$updated = \GFAPI::update_entry_field( $entry_plan['entry_id'], $field_id, $value );

					if ( is_wp_error( $updated ) || false === $updated ) {
						return new \WP_Error(
							'community_resources_hub_migration_entry_update_failed',
							sprintf( 'Failed updating entry %d field %s.', $entry_plan['entry_id'], $field_id )
						);
					}
				}
			}

			$this->taxonomy->ensure_default_opportunity_tags();

			foreach ( $plan['entry_plans'] as $entry_plan ) {
				if ( ! $entry_plan['post_id'] || ! in_array( $entry_plan['approval_status'], array( 'Approved', 'Pending' ), true ) || empty( $entry_plan['post_needs_sync'] ) ) {
					continue;
				}

				$entry = \GFAPI::get_entry( $entry_plan['entry_id'] );

				if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
					return new \WP_Error( 'community_resources_hub_migration_entry_read_failed', sprintf( 'Could not reload entry %d.', $entry_plan['entry_id'] ) );
				}

				$post_id = $this->repository->upsert_from_entry( $entry, $entry_plan['approval_status'] );

				if ( (int) $entry_plan['post_id'] !== (int) $post_id ) {
					return new \WP_Error( 'community_resources_hub_migration_post_sync_failed', sprintf( 'Could not synchronize post for entry %d.', $entry_plan['entry_id'] ) );
				}
			}

			$this->delete_obsolete_primary_type( $plan['entry_plans'] );
			$this->clear_runtime_caches();

			$readback = $this->plan();

			if ( ! $readback['valid'] || $this->has_changes( $readback['changes'] ) ) {
				$message = ! $readback['valid'] ? implode( ' ', $readback['errors'] ) : 'Final readback still reports proposed changes.';
				return new \WP_Error( 'community_resources_hub_migration_readback_failed', $message );
			}

			$this->persist_completion_marker( $readback, $plan['hash'] );

			return $readback;
		} catch ( \Throwable $throwable ) {
			return new \WP_Error( 'community_resources_hub_migration_failed', $throwable->getMessage() );
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/**
	 * Pure desired contract for one entry.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function entry_contract( array $entry ) {
		$entry_id = absint( $this->fields->value( $entry, 'id' ) );
		$raw_type = trim( (string) $this->fields->value( $entry, $this->config->field( 'opportunity_type' ) ) );
		$branch   = strtolower( trim( (string) $this->fields->value( $entry, $this->config->field( 'time_sensitive' ) ) ) );

		if ( 'no' === $branch ) {
			$raw_type = trim( (string) $this->fields->value( $entry, $this->config->field( 'non_date_sensitive_type' ) ) );
		}

		$bci_manifest = self::bci_manifest();
		$is_bci       = isset( $bci_manifest[ $entry_id ] );
		$type         = '';

		if ( $is_bci ) {
			$type    = $bci_manifest[ $entry_id ]['type'];
			$raw_key = $this->type_key( $raw_type );

			if ( ! in_array( $raw_key, array( 'bci update', 'bci updates', $this->type_key( $type ) ), true ) ) {
				return new \WP_Error(
					'community_resources_hub_migration_bci_manifest_drift',
					sprintf( 'BCI manifest entry %d has unexpected type "%s".', $entry_id, $raw_type )
				);
			}
		} else {
			$type = $this->normalize_type( $entry_id, $raw_type );
		}

		if ( '' === $type ) {
			return new \WP_Error(
				'community_resources_hub_migration_unknown_type',
				sprintf( 'Entry %d has unsupported opportunity type "%s".', $entry_id, $raw_type )
			);
		}

		$is_time_sensitive = in_array( $type, array( 'Grant / RFP', 'Event', 'Workshop, training, or other learning', 'Other' ), true );
		$field_updates     = array();
		$type_field        = $this->config->field( 'opportunity_type' );
		$resource_field    = $this->config->field( 'non_date_sensitive_type' );
		$sensitive_field   = $this->config->field( 'time_sensitive' );
		$bci_field         = $this->config->field( 'bci_update' );

		if ( $is_time_sensitive ) {
			$this->propose_field_update( $entry, $field_updates, $type_field, $type );
			$this->propose_field_update( $entry, $field_updates, $sensitive_field, 'Yes' );
			$this->propose_field_update( $entry, $field_updates, $resource_field, '' );
		} else {
			$this->propose_field_update( $entry, $field_updates, $resource_field, $type );
			$this->propose_field_update( $entry, $field_updates, $sensitive_field, 'No' );
			$this->propose_field_update( $entry, $field_updates, $type_field, '' );
		}

		$this->propose_field_update( $entry, $field_updates, $bci_field, $is_bci ? 'Yes' : 'No' );

		$start_field    = $this->config->field( 'start_date' );
		$deadline_field = $this->config->field( 'grant_deadline' );
		$end_field      = $this->config->field( 'end_date' );
		$start_date     = trim( (string) $this->fields->value( $entry, $start_field ) );
		$date_repair    = '';

		if ( 220 === $entry_id && '' === $start_date ) {
			$date_repair = '2026-05-19';
		} elseif ( 125 === $entry_id && '' === $start_date ) {
			$date_repair = trim( (string) $this->fields->value( $entry, $end_field ) );
		} elseif ( 'Grant / RFP' === $type ) {
			$deadline = trim( (string) $this->fields->value( $entry, $deadline_field ) );

			if ( '' !== $deadline && $start_date !== $deadline ) {
				$date_repair = $deadline;
			}
		}

		if ( '' !== $date_repair ) {
			$this->propose_field_update( $entry, $field_updates, $start_field, $date_repair );
			$start_date = $date_repair;
		}

		$primary_date = 'Grant / RFP' === $type
			? trim( (string) $this->fields->value( $entry, $deadline_field ) )
			: $start_date;

		return array(
			'entry_id'          => $entry_id,
			'title'             => $this->fields->title( $entry ),
			'raw_type'          => $raw_type,
			'type'              => $type,
			'is_time_sensitive' => $is_time_sensitive,
			'is_bci_update'     => $is_bci,
			'primary_date'      => $primary_date,
			'date_repair'       => $date_repair,
			'field_updates'     => $field_updates,
		);
	}

	/** @return array<int,array{type:string}> */
	public static function bci_manifest() {
		return array(
			199 => array( 'type' => 'Event' ),
			220 => array( 'type' => 'Event' ),
			248 => array( 'type' => 'Event' ),
			258 => array( 'type' => 'Other' ),
			277 => array( 'type' => 'Event' ),
			298 => array( 'type' => 'Event' ),
			307 => array( 'type' => 'Other' ),
		);
	}

	/**
	 * Derive relational expectations from the active entries being migrated.
	 *
	 * @param array<int,array<string,mixed>> $entries Active Gravity Forms entries.
	 * @param string                         $approval_field_id Approval field ID.
	 * @return array{entries:int,posts:int,approved:int,pending:int,bci_updates:int}
	 */
	public static function dataset_expectations( array $entries, $approval_field_id = '22' ) {
		$entry_ids = array();
		$approved  = 0;
		$pending   = 0;

		foreach ( $entries as $entry ) {
			$entry_id = absint( $entry['id'] ?? 0 );

			if ( $entry_id ) {
				$entry_ids[ $entry_id ] = true;
			}

			$status = trim( (string) ( $entry[ (string) $approval_field_id ] ?? '' ) );

			if ( 'Approved' === $status ) {
				$approved++;
			} elseif ( 'Pending' === $status ) {
				$pending++;
			}
		}

		$entry_count = count( $entries );

		return array(
			'entries'     => $entry_count,
			'posts'       => $approved + $pending,
			'approved'    => $approved,
			'pending'     => $pending,
			'bci_updates' => count( array_intersect_key( self::bci_manifest(), $entry_ids ) ),
		);
	}

	/** @return array<int,array{raw:string,type:string}> */
	public static function legacy_normalization_manifest() {
		return array(
			124 => array( 'raw' => 'Other Opportunities Section', 'type' => 'Other' ),
			200 => array( 'raw' => 'Volunteer and Tabling Opportunity', 'type' => 'Resource' ),
			216 => array( 'raw' => 'Other Resources', 'type' => 'Resource' ),
			223 => array( 'raw' => 'Other Resources', 'type' => 'Resource' ),
			224 => array( 'raw' => 'Other Resources', 'type' => 'Resource' ),
			227 => array( 'raw' => 'Other Resources', 'type' => 'Resource' ),
			265 => array( 'raw' => 'Other Resources', 'type' => 'Resource' ),
			266 => array( 'raw' => 'Other Resources', 'type' => 'Resource' ),
			297 => array( 'raw' => 'Other resorces', 'type' => 'Resource' ),
			301 => array( 'raw' => 'Other resources', 'type' => 'Resource' ),
			333 => array( 'raw' => 'Paid Fellowship', 'type' => 'Resource' ),
			347 => array( 'raw' => 'Other resources', 'type' => 'Resource' ),
		);
	}

	/**
	 * Normalize only approved legacy and current values.
	 */
	private function normalize_type( $entry_id, $raw_type ) {
		$key      = $this->type_key( $raw_type );
		$manifest = self::legacy_normalization_manifest();

		if ( isset( $manifest[ $entry_id ] ) ) {
			$expected = $manifest[ $entry_id ];

			if ( in_array( $key, array( $this->type_key( $expected['raw'] ), $this->type_key( $expected['type'] ) ), true ) ) {
				return $expected['type'];
			}

			return '';
		}

		$map = array(
			'event'                                    => 'Event',
			'grant / rfp'                              => 'Grant / RFP',
			'grant/rfp'                                => 'Grant / RFP',
			'learning'                                 => 'Workshop, training, or other learning',
			'workshop, training, or other learning'    => 'Workshop, training, or other learning',
			'workshop, training or other learning'     => 'Workshop, training, or other learning',
			'other'                                    => 'Other',
			'resource'                                 => 'Resource',
			'recommended vendor'                      => 'Recommended Vendor',
		);

		return $map[ $key ] ?? '';
	}

	/** Normalize a type value for exact migration comparisons. */
	private function type_key( $value ) {
		return strtolower( preg_replace( '/\s+/', ' ', trim( (string) $value ) ) );
	}

	/** @return array<int,array<string,mixed>> */
	private function active_entries( $form_id, array &$errors ) {
		$total   = 0;
		$entries = \GFAPI::get_entries(
			$form_id,
			array( 'status' => 'active' ),
			array( 'key' => 'id', 'direction' => 'ASC', 'is_numeric' => true ),
			array( 'offset' => 0, 'page_size' => 500 ),
			$total
		);

		if ( is_wp_error( $entries ) || ! is_array( $entries ) ) {
			$errors[] = 'Active Gravity Forms entries could not be loaded.';
			return array();
		}

		if ( count( $entries ) !== (int) $total ) {
			$errors[] = sprintf( 'Gravity Forms returned %d of %d active entries.', count( $entries ), $total );
		}

		return $entries;
	}

	/** @return array<int,int> Source entry ID => post ID. */
	private function source_posts( array &$errors ) {
		$post_ids = get_posts(
			array(
				'post_type'      => $this->config->opportunity_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$posts = array();

		foreach ( is_array( $post_ids ) ? $post_ids : array() as $post_id ) {
			$entry_id = absint( get_post_meta( $post_id, $this->config->opportunity_field_name( 'source_entry_id' ), true ) );

			if ( ! $entry_id ) {
				$errors[] = sprintf( 'Opportunity post %d is missing its source entry ID.', $post_id );
				continue;
			}

			if ( isset( $posts[ $entry_id ] ) ) {
				$errors[] = sprintf( 'Entries %d links to duplicate posts %d and %d.', $entry_id, $posts[ $entry_id ], $post_id );
				continue;
			}

			$posts[ $entry_id ] = absint( $post_id );
		}

		return $posts;
	}

	private function post_needs_sync( $post_id, array $entry, array $entry_plan ) {
		$type_config = $this->config->opportunity_type_config( $entry_plan['type'] );
		$type_name   = trim( (string) ( $type_config['name'] ?? '' ) );
		$type_name   = '' !== $type_name ? $type_name : (string) $entry_plan['type'];
		$start_date  = '' !== (string) $entry_plan['date_repair']
			? (string) $entry_plan['date_repair']
			: (string) $this->fields->value( $entry, $this->config->field( 'start_date' ) );
		$expected_meta = array(
			$this->config->opportunity_field_name( 'source_entry_id' ) => (string) $entry_plan['entry_id'],
			$this->config->opportunity_field_name( 'approval_status' ) => (string) $entry_plan['approval_status'],
			$this->config->opportunity_field_name( 'submitted_at' ) => $this->fields->submitted_at( $entry ),
			$this->config->opportunity_field_name( 'opportunity_type' ) => $type_name,
			$this->config->opportunity_field_name( 'submitter_name' ) => $this->fields->submitter_name( $entry ),
			$this->config->opportunity_field_name( 'organization' ) => (string) $this->fields->value( $entry, $this->config->field( 'organization' ) ),
			$this->config->opportunity_field_name( 'start_date' ) => $start_date,
			$this->config->opportunity_field_name( 'grant_deadline' ) => (string) $this->fields->value( $entry, $this->config->field( 'grant_deadline' ) ),
			$this->config->opportunity_field_name( 'end_date' ) => (string) $this->fields->value( $entry, $this->config->field( 'end_date' ) ),
			$this->config->opportunity_field_name( 'start_time' ) => (string) $this->fields->value( $entry, $this->config->field( 'start_time' ) ),
			$this->config->opportunity_field_name( 'end_time' ) => (string) $this->fields->value( $entry, $this->config->field( 'end_time' ) ),
			$this->config->opportunity_field_name( 'location_mode' ) => (string) $this->fields->value( $entry, $this->config->field( 'location_mode' ) ),
			$this->config->opportunity_field_name( 'address' ) => $this->fields->address( $entry ),
			$this->config->opportunity_field_name( 'cost' ) => (string) $this->fields->value( $entry, $this->config->field( 'cost' ) ),
			$this->config->opportunity_field_name( 'info_url' ) => esc_url_raw( (string) $this->fields->value( $entry, $this->config->field( 'info_url' ) ) ),
			$this->config->opportunity_field_name( 'file_upload' ) => $this->fields->file_upload( $entry ),
		);
		$type_terms  = wp_get_object_terms( $post_id, $this->config->opportunity_type_taxonomy(), array( 'fields' => 'slugs' ) );
		$tag_terms   = wp_get_object_terms( $post_id, $this->config->opportunity_tag_taxonomy(), array( 'fields' => 'slugs' ) );
		$type_slug   = (string) ( $type_config['slug'] ?? '' );
		$has_bci_tag = is_array( $tag_terms ) && in_array( Schema::BCI_UPDATE_TAG_SLUG, $tag_terms, true );

		$expected_status = 'Pending' === (string) $entry_plan['approval_status'] ? 'pending' : 'publish';

		if (
			(string) get_post_field( 'post_title', $post_id, 'raw' ) !== sanitize_text_field( $this->fields->title( $entry ) )
			|| (string) get_post_field( 'post_content', $post_id, 'raw' ) !== wp_kses_post( trim( (string) $this->fields->value( $entry, $this->config->field( 'description' ) ) ) )
			|| $expected_status !== (string) get_post_field( 'post_status', $post_id, 'raw' )
		) {
			return true;
		}

		foreach ( $expected_meta as $meta_key => $expected_value ) {
			if ( (string) get_post_meta( $post_id, $meta_key, true ) !== (string) $expected_value ) {
				return true;
			}
		}

		return ! is_array( $type_terms )
			|| array( $type_slug ) !== array_values( $type_terms )
			|| $has_bci_tag !== (bool) $entry_plan['is_bci_update'];
	}

	private function delete_obsolete_primary_type( array $entry_plans ) {
		$term = get_term_by( 'slug', Schema::BCI_UPDATE_TAG_SLUG, Schema::OPPORTUNITY_TYPE_TAXONOMY );

		if ( ! $term ) {
			return;
		}

		$term_id = absint( is_object( $term ) ? $term->term_id : ( $term['term_id'] ?? 0 ) );
		$objects = function_exists( 'get_objects_in_term' ) ? get_objects_in_term( $term_id, Schema::OPPORTUNITY_TYPE_TAXONOMY ) : array();
		$count   = is_array( $objects ) ? count( $objects ) : absint( is_object( $term ) ? $term->count : ( $term['count'] ?? 0 ) );

		if ( $count > 0 ) {
			throw new \RuntimeException( 'The obsolete BCI Update primary type still has relationships.' );
		}

		$posts_with_meta = get_posts(
			array(
				'post_type'      => $this->config->opportunity_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => $this->config->opportunity_field_name( 'opportunity_type' ),
				'meta_value'     => 'BCI Update',
			)
		);

		if ( ! empty( $posts_with_meta ) ) {
			throw new \RuntimeException( 'A post still stores BCI Update as its primary type.' );
		}

		foreach ( $entry_plans as $entry_plan ) {
			if ( empty( $entry_plan['is_bci_update'] ) ) {
				continue;
			}

			$entry_id = absint( $entry_plan['entry_id'] ?? 0 );
			$post_id  = absint( $entry_plan['post_id'] ?? 0 );

			if ( ! $post_id || ! has_term( Schema::BCI_UPDATE_TAG_SLUG, Schema::OPPORTUNITY_TAG_TAXONOMY, $post_id ) ) {
				throw new \RuntimeException( sprintf( 'BCI entry %d is missing its new secondary tag.', $entry_id ) );
			}
		}

		$result = wp_delete_term( $term_id, Schema::OPPORTUNITY_TYPE_TAXONOMY );

		if ( is_wp_error( $result ) || false === $result ) {
			throw new \RuntimeException( 'The obsolete BCI Update primary term could not be deleted.' );
		}
	}

	private function propose_field_update( array $entry, array &$updates, $field_id, $desired ) {
		if ( (string) $this->fields->value( $entry, $field_id ) !== (string) $desired ) {
			$updates[ (string) $field_id ] = (string) $desired;
		}
	}

	private function finalize_plan( array $summary, array $changes, array $entry_plans, array $errors, $form_updated ) {
		$hash_payload = array(
			'version'       => self::VERSION,
			'summary'       => $summary,
			'changes'       => $changes,
			'entry_changes' => array_map(
				static function ( $row ) {
					return array( $row['entry_id'], $row['type'], $row['is_bci_update'], $row['field_updates'], $row['post_needs_sync'] ?? false );
				},
				$entry_plans
			),
		);

		return array(
			'version'      => self::VERSION,
			'valid'        => empty( $errors ),
			'errors'       => array_values( array_unique( $errors ) ),
			'summary'      => $summary,
			'changes'      => $changes,
			'entry_plans'  => $entry_plans,
			'form_updated' => (bool) $form_updated,
			'hash'         => hash( 'sha256', wp_json_encode( $hash_payload ) ),
		);
	}

	private function has_changes( array $changes ) {
		foreach ( $changes as $value ) {
			if ( ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	private function acquire_lock() {
		$lock = array( 'started_at' => time(), 'version' => self::VERSION );

		if ( add_option( self::LOCK_OPTION, $lock, '', false ) ) {
			return true;
		}

		$existing = get_option( self::LOCK_OPTION, array() );

		if ( ! is_array( $existing ) || time() - absint( $existing['started_at'] ?? 0 ) < 15 * MINUTE_IN_SECONDS ) {
			return false;
		}

		delete_option( self::LOCK_OPTION );

		return add_option( self::LOCK_OPTION, $lock, '', false );
	}

	/**
	 * Persist the completion marker only after a valid zero-change readback.
	 *
	 * @param array<string,mixed> $readback Verified plan.
	 * @param string              $plan_hash Original apply plan hash.
	 * @return void
	 */
	private function persist_completion_marker( array $readback, $plan_hash ) {
		$existing = get_option( self::COMPLETED_OPTION, null );

		if (
			is_array( $existing )
			&& self::VERSION === ( $existing['version'] ?? '' )
			&& ( $existing['summary'] ?? array() ) === ( $readback['summary'] ?? array() )
		) {
			return;
		}

		$marker = array(
			'version'      => self::VERSION,
			'completed_at' => gmdate( 'c' ),
			'site_url'     => function_exists( 'site_url' ) ? site_url() : '',
			'form_id'      => $this->config->form_id(),
			'plan_hash'    => (string) $plan_hash,
			'summary'      => $readback['summary'],
		);

		if ( null === $existing ) {
			add_option( self::COMPLETED_OPTION, $marker, '', false );
			return;
		}

		update_option( self::COMPLETED_OPTION, $marker, false );
	}

	private function clear_runtime_caches() {
		delete_transient( 'community_resources_hub_approved_opportunities' );
		delete_transient( 'community_resources_hub_member_directory' );

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
