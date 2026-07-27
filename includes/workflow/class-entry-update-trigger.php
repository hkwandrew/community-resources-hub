<?php
/**
 * Gravity Forms entry update bridge for BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps CPT posts in sync when existing GF entries are edited manually.
 */
final class EntryUpdateTrigger {

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
	 * Sync manager.
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
	 * Register hooks.
	 */
	public function register() {
		$form_id = $this->config->form_id();

		if ( ! $form_id ) {
			return;
		}

		add_action( 'gform_post_update_entry_' . $form_id, array( $this, 'after_update' ), 10, 2 );
		add_action( 'gform_after_update_entry_' . $form_id, array( $this, 'after_manual_update' ), 10, 3 );
	}

	/**
	 * @param array<string,mixed> $entry Updated entry data.
	 * @param array<string,mixed> $original_entry Original entry data.
	 */
	public function after_update( array $entry, array $original_entry ) {
		if ( $this->config->form_id() !== (int) rgar( $entry, 'form_id' ) ) {
			return;
		}

		$entry          = $this->sync_grant_deadline_to_start_date( $entry );
		$current_status = $this->approval_status( $entry );
		$previous_status = $this->approval_status( $original_entry );
		$post_id = $this->repository->upsert_from_entry( $entry, '' !== $current_status ? $current_status : 'Pending' );

		if ( ! $post_id ) {
			return;
		}

		if ( 'Approved' !== $current_status || $previous_status === $current_status || ! $this->sync ) {
			return;
		}

		$this->sync->sync_opportunity( $post_id );
	}

	/**
	 * Keep GravityCalendar's start-date field populated for grants.
	 *
	 * @param array<string,mixed> $entry Updated entry.
	 * @return array<string,mixed>
	 */
	private function sync_grant_deadline_to_start_date( array $entry ) {
		$start_field    = $this->config->field( 'start_date' );
		$deadline_field = $this->config->field( 'grant_deadline' );
		$start_date     = trim( (string) $this->fields->value( $entry, $start_field ) );
		$deadline       = trim( (string) $this->fields->value( $entry, $deadline_field ) );

		if ( ! $this->config->is_grant_opportunity_type( $this->fields->opportunity_type( $entry ) ) || '' === $deadline || $start_date === $deadline ) {
			return $entry;
		}

		$entry[ $start_field ] = $deadline;

		if ( class_exists( 'GFAPI' ) ) {
			\GFAPI::update_entry_field( absint( $this->fields->value( $entry, 'id' ) ), $start_field, $deadline );
		}

		return $entry;
	}

	/**
	 * @param array<string,mixed> $form Form data.
	 * @param int                $entry_id Entry ID.
	 * @param array<string,mixed> $original_entry Original entry data.
	 */
	public function after_manual_update( array $form, $entry_id, array $original_entry ) {
		if ( $this->config->form_id() !== (int) rgar( $form, 'id' ) || ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$entry = \GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
			return;
		}

		$this->after_update( $entry, $original_entry );
	}

	/**
	 * Approval label from an entry.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 */
	private function approval_status( array $entry ) {
		$value    = strtolower( trim( (string) rgar( $entry, $this->config->approval_field_id() ) ) );
		$statuses = Config::approval_statuses();

		return $statuses[ $value ] ?? '';
	}
}
