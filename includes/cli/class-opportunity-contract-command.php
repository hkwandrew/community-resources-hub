<?php
/**
 * WP-CLI adapter for the Opportunity Hub contract migration.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Cli;

use WatersMeet\CommunityResourcesHub\Workflow\OpportunityContractMigration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migrates the Opportunity Hub form, entry, and post contract.
 */
final class OpportunityContractCommand {

	/** @var OpportunityContractMigration */
	private $migration;

	public function __construct( ?OpportunityContractMigration $migration = null ) {
		$this->migration = $migration ?: new OpportunityContractMigration();
	}

	/**
	 * Preview or apply the Opportunity Hub contract migration.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Write the preflighted form, entry, taxonomy, and opportunity changes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp community-resources-hub migrate-opportunity-contract
	 *     wp community-resources-hub migrate-opportunity-contract --apply
	 *
	 * @when after_wp_load
	 *
	 * @param array<int,string>    $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		$apply = \WP_CLI\Utils\get_flag_value( $assoc_args, 'apply', false );

		try {
			$result = $apply ? $this->migration->apply() : $this->migration->plan();
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
			\WP_CLI::success( 'Opportunity contract migration applied and verified.' );
		} else {
			\WP_CLI::success( 'Dry run complete. No database changes were made.' );
		}
	}

	/**
	 * Register the exact plugin-owned command.
	 *
	 * @return void
	 */
	public static function register() {
		\WP_CLI::add_command(
			'community-resources-hub migrate-opportunity-contract',
			new self()
		);
	}

	/** @param array<string,mixed> $plan Migration plan. */
	private function render_plan( array $plan, $applied ) {
		$summary = $plan['summary'] ?? array();
		$changes = $plan['changes'] ?? array();

		\WP_CLI::line( $applied ? 'Opportunity contract apply readback' : 'Opportunity contract dry run' );
		\WP_CLI::line( 'Plan hash: ' . ( $plan['hash'] ?? '' ) );
		\WP_CLI::line(
			sprintf(
				'Site: %s (home %s); WordPress %s; PHP %s; timezone %s.',
				(string) ( $summary['site_url'] ?? '' ),
				(string) ( $summary['home_url'] ?? '' ),
				(string) ( $summary['wordpress_version'] ?? '' ),
				(string) ( $summary['php_version'] ?? '' ),
				(string) ( $summary['timezone'] ?? '' )
			)
		);
		\WP_CLI::line(
			sprintf(
				'Form %d; entries %d; posts %d; date-sensitive %d; non-date-sensitive %d; BCI Updates %d.',
				(int) ( $summary['form_id'] ?? 0 ),
				(int) ( $summary['entries'] ?? 0 ),
				(int) ( $summary['posts'] ?? 0 ),
				(int) ( $summary['time_sensitive'] ?? 0 ),
				(int) ( $summary['non_date_sensitive'] ?? 0 ),
				(int) ( $summary['bci_updates'] ?? 0 )
			)
		);
		\WP_CLI::line(
			sprintf(
				'Proposed: form=%s, entries=%d, fields=%d, posts=%d, create-tag=%s, delete-old-type=%s.',
				! empty( $changes['form'] ) ? 'yes' : 'no',
				(int) ( $changes['entries'] ?? 0 ),
				(int) ( $changes['entry_fields'] ?? 0 ),
				(int) ( $changes['posts'] ?? 0 ),
				! empty( $changes['create_bci_tag'] ) ? 'yes' : 'no',
				! empty( $changes['delete_old_bci_type'] ) ? 'yes' : 'no'
			)
		);

		$entry_rows = array();

		foreach ( $plan['entry_plans'] ?? array() as $entry_plan ) {
			if ( empty( $entry_plan['field_updates'] ) && empty( $entry_plan['post_needs_sync'] ) ) {
				continue;
			}

			$field_changes = array();

			foreach ( $entry_plan['field_updates'] as $field_id => $value ) {
				$field_changes[] = (string) $field_id . '=' . ( '' === (string) $value ? '[clear]' : (string) $value );
			}

			$entry_rows[] = array(
				'entry'       => (int) $entry_plan['entry_id'],
				'post'        => (int) $entry_plan['post_id'],
				'from'        => (string) $entry_plan['raw_type'],
				'to'          => (string) $entry_plan['type'],
				'sensitive'   => ! empty( $entry_plan['is_time_sensitive'] ) ? 'Yes' : 'No',
				'bci_update'   => ! empty( $entry_plan['is_bci_update'] ) ? 'Yes' : 'No',
				'field_changes' => implode( '; ', $field_changes ),
				'date_repair'  => (string) $entry_plan['date_repair'],
				'post_sync'    => ! empty( $entry_plan['post_needs_sync'] ) ? 'Yes' : 'No',
			);
		}

		if ( ! empty( $entry_rows ) ) {
			\WP_CLI::line( 'Per-entry proposed changes:' );
			\WP_CLI\Utils\format_items(
				'table',
				$entry_rows,
				array( 'entry', 'post', 'from', 'to', 'sensitive', 'bci_update', 'field_changes', 'date_repair', 'post_sync' )
			);
		} else {
			\WP_CLI::line( 'Per-entry proposed changes: none.' );
		}

		foreach ( $plan['errors'] ?? array() as $error ) {
			\WP_CLI::warning( $error );
		}
	}
}
