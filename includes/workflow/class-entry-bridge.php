<?php
/**
 * Gravity Forms to BCI opportunity bridge.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mirrors Gravity Forms submissions into plugin-owned opportunities.
 */
final class EntryBridge {

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
	 * Google sync manager.
	 *
	 * @var GoogleSyncManager|null
	 */
	private $sync;

	/**
	 * Field accessors.
	 *
	 * @var FieldAccessor
	 */
	private $fields;

	public function __construct( Config $config, OpportunityRepository $repository, ?GoogleSyncManager $sync = null ) {
		$this->config     = $config;
		$this->repository = $repository;
		$this->sync       = $sync;
		$this->fields     = new FieldAccessor( $config );
	}

	/**
	 * Register GF hooks.
	 */
	public function register() {
		if ( ! $this->config->form_id() ) {
			return;
		}

		add_filter( 'gform_entry_post_save_' . $this->config->form_id(), array( $this, 'sync_entry' ), 20, 2 );
	}

	/**
	 * Sync the saved GF entry into a plugin-owned opportunity.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param array<string,mixed> $form Gravity Forms form.
	 * @return array<string,mixed>
	 */
	public function sync_entry( array $entry, array $form ) {
		if ( $this->config->form_id() !== (int) $this->fields->value( $form, 'id' ) ) {
			return $entry;
		}

		$entry = $this->sync_grant_deadline_to_start_date( $entry );

		$approval_field = $this->config->approval_field_id();
		$current_status = trim( (string) $this->fields->value( $entry, $approval_field ) );
		$approval_status = self::resolve_approval_status(
			$entry,
			$current_status,
			$this->config->auto_approved_user_ids()
		);

		if ( $approval_field && $current_status !== $approval_status ) {
			$entry[ $approval_field ] = $approval_status;
			$this->update_gf_entry_field( absint( $this->fields->value( $entry, 'id' ) ), $approval_field, $approval_status );
		}

		$post_id = $this->repository->upsert_from_entry( $entry, $approval_status );

		if ( $post_id && 'Approved' === $approval_status && $this->sync ) {
			$this->sync->sync_opportunity( $post_id );
		}

		return $entry;
	}

	/**
	 * Resolve approval status without overwriting explicit final states.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param string             $current_status Current approval status.
	 * @param array<int,int>     $auto_approved_user_ids Auto-approved user IDs.
	 */
	public static function resolve_approval_status( array $entry, $current_status, array $auto_approved_user_ids ) {
		$current_status = trim( (string) $current_status );

		if ( '' !== $current_status && 'Pending' !== $current_status ) {
			return $current_status;
		}

		$created_by = absint( isset( $entry['created_by'] ) ? $entry['created_by'] : 0 );

		if ( $created_by && in_array( $created_by, $auto_approved_user_ids, true ) ) {
			return 'Approved';
		}

		return 'Pending';
	}

	/**
	 * Copy Grant/RFP deadline into the GF calendar start-date field when needed.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return array<string,mixed>
	 */
	private function sync_grant_deadline_to_start_date( array $entry ) {
		$start_field    = $this->config->field( 'start_date' );
		$deadline_field = $this->config->field( 'grant_deadline' );

		$type       = $this->fields->opportunity_type( $entry );
		$start_date = trim( (string) $this->fields->value( $entry, $start_field ) );
		$deadline   = trim( (string) $this->fields->value( $entry, $deadline_field ) );

		if ( ! $this->config->is_grant_opportunity_type( $type ) || '' === $deadline || $start_date === $deadline ) {
			return $entry;
		}

		$entry[ $start_field ] = $deadline;
		$this->update_gf_entry_field( absint( $this->fields->value( $entry, 'id' ) ), $start_field, $deadline );

		return $entry;
	}

	/**
	 * Update a GF entry field when GF is active.
	 */
	private function update_gf_entry_field( $entry_id, $field_id, $value ) {
		if ( ! $entry_id || '' === (string) $field_id || ! class_exists( 'GFAPI' ) ) {
			return;
		}

		\GFAPI::update_entry_field( $entry_id, $field_id, $value );
	}
}
