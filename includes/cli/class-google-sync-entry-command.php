<?php
/**
 * Exact-entry Google sync WP-CLI command.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Cli;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Workflow\GoogleSyncManager;
use WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover;
use WatersMeet\CommunityResourcesHub\Workflow\OpportunityRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preflights and retries exactly one mapped opportunity entry.
 */
final class GoogleSyncEntryCommand {

	/** @var Config */
	private $config;

	/** @var object */
	private $sync;

	/** @var OpportunityRepository */
	private $repository;

	public function __construct( ?Config $config = null, $sync = null, ?OpportunityRepository $repository = null ) {
		$this->config     = $config ?: new Config();
		$this->sync       = $sync;
		$this->repository = $repository ?: new OpportunityRepository( $this->config );
	}

	/**
	 * Preview or retry one exact source entry.
	 *
	 * ## OPTIONS
	 *
	 * <entry-id>
	 * : Exact Gravity Forms entry ID to inspect or retry.
	 *
	 * [--apply]
	 * : Send only this entry to the configured Google workflow.
	 *
	 * ## EXAMPLES
	 *
	 *     wp community-resources-hub sync-google-entry 177
	 *     wp community-resources-hub sync-google-entry 177 --apply
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		$entry_id = absint( $args[0] ?? 0 );
		$apply    = \WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );

		if ( ! $entry_id ) {
			\WP_CLI::error( 'Provide one exact Gravity Forms entry ID.' );
			return;
		}

		$eligible = $this->eligible_entry( $entry_id );

		if ( is_wp_error( $eligible ) ) {
			\WP_CLI::error( $eligible->get_error_message() );
			return;
		}

		$post_id = absint( $eligible['post_id'] ?? 0 );
		\WP_CLI::line( sprintf( 'Entry %d maps one-to-one to opportunity post %d.', $entry_id, $post_id ) );

		if ( ! $apply ) {
			\WP_CLI::success( 'Dry run complete. No Google request was made.' );
			return;
		}

		if ( null === $this->sync ) {
			if ( ! class_exists( GoogleSyncManager::class ) ) {
				\WP_CLI::error( 'The Google sync manager is unavailable.' );
				return;
			}

			$this->sync = new GoogleSyncManager( $this->config );
		}

		$success = (bool) $this->sync->sync_opportunity( $post_id );
		$status  = trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'google_sync_status' ), true ) );

		if ( ! $success || 'synced' !== $status ) {
			$error = trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'google_sync_error' ), true ) );
			\WP_CLI::error( '' !== $error ? $error : 'The exact-entry Google retry did not reach the synced state.' );
			return;
		}

		\WP_CLI::success( sprintf( 'Entry %d synced through opportunity post %d.', $entry_id, $post_id ) );
	}

	/** Register the exact plugin-owned command. */
	public static function register() {
		\WP_CLI::add_command( 'community-resources-hub sync-google-entry', new self() );
	}

	/** @return array<string,int>|\WP_Error */
	private function eligible_entry( $entry_id ) {
		$cutover = get_option( LegacyWorkflowCutover::COMPLETED_OPTION, null );

		if ( ! is_array( $cutover ) || LegacyWorkflowCutover::VERSION !== (string) ( $cutover['version'] ?? '' ) ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_cutover_incomplete', 'Complete and verify the legacy workflow cutover before retrying Google sync.' );
		}

		if ( ! $this->config->is_google_sync_configured() ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_google_unconfigured', 'The production Google sync URL and shared secret must both be configured before retrying an entry.' );
		}

		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_entry' ) ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_missing_gf', 'Gravity Forms is not available.' );
		}

		$entry = \GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_missing', sprintf( 'Entry %d could not be loaded.', $entry_id ) );
		}

		if ( $entry_id !== absint( $entry['id'] ?? 0 ) || $this->config->form_id() !== absint( $entry['form_id'] ?? 0 ) ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_wrong_form', sprintf( 'Entry %d does not belong to the configured opportunity form.', $entry_id ) );
		}

		$approval_field = $this->config->field( 'approval_status' );

		if ( 'Approved' !== trim( (string) ( $entry[ $approval_field ] ?? '' ) ) ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_unapproved', sprintf( 'Entry %d is not Approved.', $entry_id ) );
		}

		$post_id = $this->repository->find_by_source_entry_id( $entry_id );

		if ( ! $post_id ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_unmapped', sprintf( 'Entry %d has no mapped opportunity post.', $entry_id ) );
		}

		if (
			'publish' !== (string) get_post_status( $post_id )
			|| 'Approved' !== trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'approval_status' ), true ) )
		) {
			return new \WP_Error( 'community_resources_hub_sync_entry_post_unapproved', sprintf( 'Entry %d is not linked to a published Approved opportunity.', $entry_id ) );
		}

		if ( 'synced' === trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'google_sync_status' ), true ) ) ) {
			return new \WP_Error( 'community_resources_hub_sync_entry_already_synced', sprintf( 'Entry %d is already synced.', $entry_id ) );
		}

		return array(
			'entry_id' => $entry_id,
			'post_id'  => absint( $post_id ),
		);
	}
}
