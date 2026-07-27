<?php
/**
 * Legacy workflow production-cutover WP-CLI command.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Cli;

use WatersMeet\CommunityResourcesHub\Workflow\LegacyWorkflowCutover;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dry-run-first adapter for the legacy workflow cutover.
 */
final class LegacyWorkflowCutoverCommand {

	/** @var LegacyWorkflowCutover */
	private $cutover;

	public function __construct( ?LegacyWorkflowCutover $cutover = null ) {
		$this->cutover = $cutover ?: new LegacyWorkflowCutover();
	}

	/**
	 * Preview or apply the production legacy workflow cutover.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Apply the exact preflighted cutover plan.
	 *
	 * [--plan-hash=<hash>]
	 * : Exact plan hash printed by the immediately preceding dry run. Required with --apply.
	 *
	 * ## EXAMPLES
	 *
	 *     wp community-resources-hub migrate-legacy-workflow
	 *     wp community-resources-hub migrate-legacy-workflow --apply --plan-hash=<hash>
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		$apply     = \WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );
		$plan_hash = trim( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'plan-hash', '' ) );

		if ( $apply && '' === $plan_hash ) {
			\WP_CLI::error( 'Apply requires the exact dry-run plan hash via --plan-hash.' );
			return;
		}

		try {
			$result = $apply ? $this->cutover->apply( $plan_hash ) : $this->cutover->plan();
		} catch ( \Throwable $throwable ) {
			\WP_CLI::error( $throwable->getMessage() );
			return;
		}

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
			return;
		}

		$this->render_plan( $result, $apply );

		if ( empty( $result['valid'] ) ) {
			\WP_CLI::error( implode( ' ', $result['errors'] ?? array() ) );
			return;
		}

		if ( $apply ) {
			\WP_CLI::success( 'Legacy workflow cutover applied with a verified zero-change readback.' );
		} else {
			\WP_CLI::success( 'Dry run complete. No database or Google changes were made.' );
		}
	}

	/** Register the exact plugin-owned command. */
	public static function register() {
		\WP_CLI::add_command( 'community-resources-hub migrate-legacy-workflow', new self() );
	}

	/** @param array<string,mixed> $plan Cutover plan. */
	private function render_plan( array $plan, $applied ) {
		$summary = $plan['summary'] ?? array();
		$changes = $plan['changes'] ?? array();

		\WP_CLI::line( $applied ? 'Legacy workflow cutover apply readback' : 'Legacy workflow cutover dry run' );
		\WP_CLI::line( 'Plan hash: ' . (string) ( $plan['hash'] ?? '' ) );
		\WP_CLI::line(
			sprintf(
				'Form %d; Feed %d; entries %d (%d Approved, %d Pending); source-linked posts %d.',
				(int) ( $summary['form_id'] ?? 0 ),
				(int) ( $summary['feed_id'] ?? 0 ),
				(int) ( $summary['entries'] ?? 0 ),
				(int) ( $summary['approved'] ?? 0 ),
				(int) ( $summary['pending'] ?? 0 ),
				(int) ( $summary['source_linked_posts'] ?? 0 )
			)
		);
		\WP_CLI::line(
			sprintf(
				'Legacy Google states: synced %d, error %d, blank %d; credentials configured: %s.',
				(int) ( $summary['synced'] ?? 0 ),
				(int) ( $summary['errors'] ?? 0 ),
				(int) ( $summary['blank_sync_state'] ?? 0 ),
				! empty( $summary['google_sync_configured'] ) ? 'yes' : 'no'
			)
		);
		\WP_CLI::line(
			sprintf(
				'Proposed: settings=%d, credential-fields=%d, posts-create=%d, posts-update=%d, reconciliation-state=%s, completion-marker=%s.',
				(int) ( $changes['settings'] ?? 0 ),
				(int) ( $changes['google_credentials'] ?? 0 ),
				(int) ( $changes['posts_create'] ?? 0 ),
				(int) ( $changes['posts_update'] ?? 0 ),
				! empty( $changes['reconciliation_state'] ) ? 'yes' : 'no',
				! empty( $changes['completion_marker'] ) ? 'yes' : 'no'
			)
		);

		foreach ( $plan['errors'] ?? array() as $error ) {
			\WP_CLI::warning( $error );
		}
	}
}
