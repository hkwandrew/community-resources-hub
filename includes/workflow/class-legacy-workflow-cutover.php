<?php
/**
 * Explicit production cutover from the legacy BCI workflow plugin.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Config\SettingsSchema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plans and applies a no-network adoption of the legacy workflow state.
 */
final class LegacyWorkflowCutover {

	const VERSION          = 'legacy-workflow-cutover-v1';
	const LEGACY_OPTION    = 'wm_bci_workflow';
	const COMPLETED_OPTION = 'community_resources_hub_legacy_workflow_cutover_state';
	const LOCK_OPTION      = 'community_resources_hub_legacy_workflow_cutover_lock';

	const FORM_ID = 5;
	const FEED_ID = 3;

	const RECONCILIATION_SUMMARY_OPTION   = 'community_resources_hub_opportunity_reconciliation_summary';
	const RECONCILIATION_PENDING_OPTION   = 'community_resources_hub_opportunity_reconciliation_pending_at';
	const RECONCILIATION_COMPLETED_OPTION = 'community_resources_hub_opportunity_reconciliation_completed_at';
	const RECONCILIATION_LOCK_OPTION      = 'community_resources_hub_opportunity_reconciliation_running_at';
	const GOOGLE_BACKFILL_JOB_OPTION      = 'community_resources_hub_google_sync_backfill_job';
	const GOOGLE_BACKFILL_LOCK_OPTION     = 'community_resources_hub_google_sync_backfill_lock';

	const LEGACY_APPROVED_AT_META = 'waters_meet_bci_approved_at';
	const LEGACY_SYNC_STATUS_META = 'waters_meet_bci_google_sync_status';
	const LEGACY_ATTEMPTED_AT_META = 'waters_meet_bci_google_sync_attempted_at';
	const LEGACY_SYNCED_AT_META   = 'waters_meet_bci_google_sync_synced_at';
	const LEGACY_SYNC_ERROR_META  = 'waters_meet_bci_google_sync_error';

	/** @var Config */
	private $config;

	/** @var OpportunityRepository */
	private $repository;

	/** @var FieldAccessor */
	private $fields;

	public function __construct( ?Config $config = null, ?OpportunityRepository $repository = null ) {
		$this->config     = $config ?: new Config();
		$this->repository = $repository ?: new OpportunityRepository( $this->config );
		$this->fields     = new FieldAccessor( $this->config );
	}

	/**
	 * Whether automatic provisioning/reconciliation must wait for the cutover.
	 *
	 * The legacy option is intentionally retained after cutover, so the marker is
	 * the authority that releases the guard.
	 *
	 * @return bool
	 */
	public static function should_defer_automatic_reconciliation() {
		$legacy = get_option( self::LEGACY_OPTION, null );

		if ( null === $legacy ) {
			return false;
		}

		$marker = get_option( self::COMPLETED_OPTION, null );

		return ! is_array( $marker ) || self::VERSION !== (string) ( $marker['version'] ?? '' );
	}

	/**
	 * Build a fully read-only cutover plan.
	 *
	 * @return array<string,mixed>
	 */
	public function plan() {
		$errors = array();
		$legacy = get_option( self::LEGACY_OPTION, null );

		if ( ! is_array( $legacy ) ) {
			$errors[] = 'The legacy wm_bci_workflow option is missing or invalid.';
			$legacy   = array();
		}

		$this->validate_plugin_ownership( $errors );
		$this->validate_background_state( $errors );
		$this->validate_gravity_resources( $legacy, $errors );
		$this->validate_legacy_settings( $legacy, $errors );

		$desired_settings   = $this->desired_settings( $legacy );
		$settings_changes   = $this->settings_changes( $desired_settings, false );
		$credential_changes = $this->settings_changes( $desired_settings, true );
		$entries            = $this->active_entries( self::FORM_ID, $errors );
		$posts              = $this->source_posts( $errors );
		$active_entry_ids = array();
		$entry_plans      = array();
		$entry_fingerprints = array();
		$post_fingerprints  = array();
		$approved         = 0;
		$pending          = 0;
		$synced           = 0;
		$failed           = 0;
		$blank_sync       = 0;
		$posts_to_create  = 0;
		$posts_to_update  = 0;

		foreach ( $entries as $entry ) {
			$entry_id = absint( $entry['id'] ?? 0 );

			if ( ! $entry_id ) {
				$errors[] = 'An active Gravity Forms entry has no usable ID.';
				continue;
			}

			$active_entry_ids[ $entry_id ] = true;
			$approval = trim( (string) $this->fields->value( $entry, (string) ( $legacy['approval_field_id'] ?? '22' ) ) );

			if ( 'Approved' === $approval ) {
				$approved++;
			} elseif ( 'Pending' === $approval ) {
				$pending++;
			} else {
				$errors[] = sprintf( 'Entry %d has unsupported approval status "%s".', $entry_id, $approval );
				continue;
			}

			$history = $this->legacy_history( $entry_id, $errors );
			$entry_fingerprints[ $entry_id ] = $this->fingerprint(
				array(
					'entry'   => $entry,
					'history' => $history,
				)
			);

			if ( 'synced' === $history['sync_status'] ) {
				$synced++;
			} elseif ( 'error' === $history['sync_status'] ) {
				$failed++;
			} else {
				$blank_sync++;
			}

			$post_id           = isset( $posts[ $entry_id ] ) ? absint( $posts[ $entry_id ] ) : 0;
			$post_needs_sync   = ! $post_id || $this->post_needs_sync( $post_id, $entry, $approval, $history );

			if ( $post_id ) {
				$post_fingerprints[ $post_id ] = $this->post_fingerprint( $post_id, $entry, $approval, $history );
			}

			if ( ! $post_id ) {
				$posts_to_create++;
			} elseif ( $post_needs_sync ) {
				$posts_to_update++;
			}

			$entry_plans[] = array(
				'entry_id'        => $entry_id,
				'post_id'         => $post_id,
				'approval_status' => $approval,
				'post_status'     => 'Approved' === $approval ? 'publish' : 'pending',
				'legacy_sync'     => $history['sync_status'],
				'post_needs_sync' => $post_needs_sync,
			);
		}

		foreach ( $posts as $entry_id => $post_id ) {
			if ( ! isset( $active_entry_ids[ $entry_id ] ) ) {
				$errors[] = sprintf( 'Opportunity post %d links to inactive entry %d.', $post_id, $entry_id );
			}
		}

		$marker = get_option( self::COMPLETED_OPTION, null );
		$marker_current = is_array( $marker ) && self::VERSION === (string) ( $marker['version'] ?? '' );
		$reconciliation_change = '' === trim( (string) get_option( self::RECONCILIATION_COMPLETED_OPTION, '' ) )
			|| null !== get_option( self::RECONCILIATION_PENDING_OPTION, null );

		$summary = array(
			'site_url'               => function_exists( 'site_url' ) ? site_url() : '',
			'home_url'               => function_exists( 'home_url' ) ? home_url() : '',
			'wordpress_version'       => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : '',
			'php_version'             => PHP_VERSION,
			'timezone'                => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : (string) get_option( 'timezone_string', 'UTC' ),
			'form_id'                 => self::FORM_ID,
			'feed_id'                 => self::FEED_ID,
			'entries'                 => count( $entries ),
			'approved'                => $approved,
			'pending'                 => $pending,
			'source_linked_posts'     => count( $posts ),
			'synced'                  => $synced,
			'errors'                  => $failed,
			'blank_sync_state'        => $blank_sync,
			'auto_approved_users'      => count( Config::normalize_user_ids( $legacy['auto_approved_user_ids'] ?? array() ) ),
			'google_sync_configured'   => '' !== trim( (string) ( $legacy['google_sync_url'] ?? '' ) ) && '' !== trim( (string) ( $legacy['google_sync_secret'] ?? '' ) ),
		);
		$changes = array(
			'settings'             => count( $settings_changes ),
			'google_credentials'   => count( $credential_changes ),
			'posts_create'         => $posts_to_create,
			'posts_update'         => $posts_to_update,
			'reconciliation_state' => $reconciliation_change,
			'completion_marker'    => ! $marker_current,
		);

		if ( count( $entry_plans ) !== count( $entries ) ) {
			$errors[] = 'Every active entry must resolve to one supported approval state.';
		}

		$current_settings = array();

		foreach ( array_keys( $desired_settings ) as $field_name ) {
			$option_name = SettingsSchema::option_name( $field_name );
			$current_settings[ $option_name ] = get_option( $option_name, null );
		}

		$form = class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'get_form' ) ? \GFAPI::get_form( self::FORM_ID ) : null;
		$feed = class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'get_feed' ) ? \GFAPI::get_feed( self::FEED_ID ) : null;
		$hash_material = array(
			'version'              => self::VERSION,
			'summary'              => $summary,
			'changes'              => $changes,
			'entry_plans'          => $entry_plans,
			'errors'               => $errors,
			'legacy_fingerprint'   => $this->fingerprint( $legacy ),
			'desired_fingerprint'  => $this->fingerprint( $desired_settings ),
			'current_fingerprint'  => $this->fingerprint( $current_settings ),
			'form_fingerprint'     => $this->fingerprint( $form ),
			'feed_fingerprint'     => $this->fingerprint( $feed ),
			'entry_fingerprints'   => $entry_fingerprints,
			'post_fingerprints'    => $post_fingerprints,
		);
		$encoded = wp_json_encode( $hash_material );

		return array(
			'version'     => self::VERSION,
			'valid'       => empty( $errors ),
			'hash'        => hash( 'sha256', (string) $encoded ),
			'summary'     => $summary,
			'changes'     => $changes,
			'entry_plans' => $entry_plans,
			'errors'      => $errors,
		);
	}

	/**
	 * Apply a preflighted plan and prove a final zero-change readback.
	 *
	 * @param string $expected_plan_hash Exact hash printed by the dry run.
	 * @return array<string,mixed>|\WP_Error
	 */
	public function apply( $expected_plan_hash = '' ) {
		$expected_plan_hash = trim( (string) $expected_plan_hash );

		if ( '' === $expected_plan_hash ) {
			return new \WP_Error( 'community_resources_hub_cutover_plan_hash_required', 'Apply requires the exact dry-run plan hash.' );
		}

		$initial = $this->plan();

		if ( empty( $initial['valid'] ) ) {
			return new \WP_Error( 'community_resources_hub_cutover_preflight_failed', implode( ' ', $initial['errors'] ?? array() ) );
		}

		if ( ! hash_equals( (string) $initial['hash'], $expected_plan_hash ) ) {
			return new \WP_Error( 'community_resources_hub_cutover_plan_changed', 'The current plan hash does not match the approved dry run.' );
		}

		if ( ! $this->has_changes( $initial['changes'] ) ) {
			return $initial;
		}

		if ( ! add_option( self::LOCK_OPTION, gmdate( 'c' ), '', false ) ) {
			return new \WP_Error( 'community_resources_hub_cutover_locked', 'The legacy workflow cutover is already running.' );
		}

		try {
			$plan = $this->plan();

			if ( empty( $plan['valid'] ) ) {
				return new \WP_Error( 'community_resources_hub_cutover_preflight_failed', implode( ' ', $plan['errors'] ?? array() ) );
			}

			if ( ! hash_equals( (string) $plan['hash'], $expected_plan_hash ) ) {
				return new \WP_Error( 'community_resources_hub_cutover_plan_changed', 'The plan changed while acquiring the cutover lock.' );
			}

			$legacy = get_option( self::LEGACY_OPTION, array() );

			if ( ! is_array( $legacy ) ) {
				return new \WP_Error( 'community_resources_hub_cutover_legacy_missing', 'The legacy workflow option disappeared before apply.' );
			}

			$this->apply_settings( $this->desired_settings( $legacy ), false );

			$result = $this->import_entries( $plan['entry_plans'] );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$this->mark_reconciliation_completed( $plan['summary'] );
			$history_readback = $this->plan();

			if ( empty( $history_readback['valid'] ) || $this->has_changes_except( $history_readback['changes'], array( 'google_credentials', 'completion_marker' ) ) ) {
				$message = empty( $history_readback['valid'] )
					? implode( ' ', $history_readback['errors'] ?? array() )
					: 'Opportunity or legacy sync-state readback still reports proposed changes.';

				return new \WP_Error( 'community_resources_hub_cutover_history_readback_failed', $message );
			}

			$this->apply_settings( $this->desired_settings( $legacy ), true );
			$credential_readback = $this->plan();

			if ( empty( $credential_readback['valid'] ) || $this->has_changes_except( $credential_readback['changes'], array( 'completion_marker' ) ) ) {
				$message = empty( $credential_readback['valid'] )
					? implode( ' ', $credential_readback['errors'] ?? array() )
					: 'Credential or content readback still reports proposed changes.';

				return new \WP_Error( 'community_resources_hub_cutover_credential_readback_failed', $message );
			}

			$this->persist_completion_marker( $credential_readback, $expected_plan_hash );
			$final = $this->plan();

			if ( empty( $final['valid'] ) || $this->has_changes( $final['changes'] ) ) {
				$message = empty( $final['valid'] ) ? implode( ' ', $final['errors'] ?? array() ) : 'Final readback still reports proposed changes.';
				return new \WP_Error( 'community_resources_hub_cutover_final_readback_failed', $message );
			}

			$this->clear_runtime_caches();

			return $final;
		} catch ( \Throwable $throwable ) {
			return new \WP_Error( 'community_resources_hub_cutover_failed', $throwable->getMessage() );
		} finally {
			delete_option( self::LOCK_OPTION );
		}
	}

	/** @param array<int,string> $errors Preflight errors. */
	private function validate_plugin_ownership( array &$errors ) {
		$active_plugins = get_option( 'active_plugins', array() );
		$network_plugins = function_exists( 'get_site_option' ) ? get_site_option( 'active_sitewide_plugins', array() ) : array();
		$plugin_files    = is_array( $active_plugins ) ? array_values( $active_plugins ) : array();

		if ( is_array( $network_plugins ) ) {
			$plugin_files = array_merge( $plugin_files, array_keys( $network_plugins ) );
		}

		foreach ( $plugin_files as $plugin ) {
			if ( 0 === strpos( (string) $plugin, 'wm-bci-workflow/' ) ) {
				$errors[] = 'Deactivate wm-bci-workflow before applying the production cutover.';
				break;
			}
		}
	}

	/**
	 * @param array<string,mixed> $legacy Legacy option.
	 * @param array<int,string>   $errors Preflight errors.
	 */
	private function validate_legacy_settings( array $legacy, array &$errors ) {
		if ( '' === trim( (string) ( $legacy['google_sync_url'] ?? '' ) ) || '' === trim( (string) ( $legacy['google_sync_secret'] ?? '' ) ) ) {
			$errors[] = 'The production Google sync URL and shared secret must both be configured in the legacy workflow.';
		}

		if ( 3 !== count( Config::normalize_user_ids( $legacy['auto_approved_user_ids'] ?? array() ) ) ) {
			$errors[] = 'The legacy workflow must contain exactly three auto-approved users.';
		}
	}

	/** @param array<int,string> $errors Preflight errors. */
	private function validate_background_state( array &$errors ) {
		if ( null !== get_option( self::GOOGLE_BACKFILL_JOB_OPTION, null ) ) {
			$errors[] = 'A Google sync backfill job already exists.';
		}

		if ( null !== get_option( self::GOOGLE_BACKFILL_LOCK_OPTION, null ) ) {
			$errors[] = 'A Google sync backfill lock already exists.';
		}

		if ( null !== get_option( self::RECONCILIATION_LOCK_OPTION, null ) ) {
			$errors[] = 'An opportunity reconciliation job is already running.';
		}
	}

	/**
	 * @param array<string,mixed> $legacy Legacy option.
	 * @param array<int,string>   $errors Preflight errors.
	 */
	private function validate_gravity_resources( array $legacy, array &$errors ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			$errors[] = 'Gravity Forms is not available.';
			return;
		}

		if ( self::FORM_ID !== absint( $legacy['form_id'] ?? 0 ) ) {
			$errors[] = 'The legacy workflow must point to exact Form 5.';
		}

		$form = method_exists( 'GFAPI', 'get_form' ) ? \GFAPI::get_form( self::FORM_ID ) : false;

		if ( ! is_array( $form ) || self::FORM_ID !== absint( $form['id'] ?? 0 ) ) {
			$errors[] = 'Exact Gravity Form 5 is missing.';
		}

		$feed = method_exists( 'GFAPI', 'get_feed' ) ? \GFAPI::get_feed( self::FEED_ID ) : null;

		if (
			is_wp_error( $feed )
			|| ! is_array( $feed )
			|| self::FEED_ID !== absint( $feed['id'] ?? 0 )
			|| self::FORM_ID !== absint( $feed['form_id'] ?? 0 )
			|| 'gravityview-calendar' !== (string) ( $feed['addon_slug'] ?? '' )
			|| empty( $feed['is_active'] )
		) {
			$errors[] = 'Exact active GravityCalendar Feed 3 for Form 5 is missing.';
		}
	}

	/** @return array<string,mixed> */
	private function desired_settings( array $legacy ) {
		$legacy_map = isset( $legacy['field_map'] ) && is_array( $legacy['field_map'] ) ? $legacy['field_map'] : array();
		$settings   = array(
			'wm_bci_form_id'                          => self::FORM_ID,
			'wm_bci_approval_field_id'                => (string) ( $legacy['approval_field_id'] ?? '22' ),
			'wm_bci_notification_name'                => (string) ( $legacy['notification_name'] ?? 'Admin Notification' ),
			'wm_bci_approval_notification_recipients' => (string) ( $legacy['approval_notification_recipients'] ?? '' ),
			'wm_bci_auto_approved_user_ids'           => $legacy['auto_approved_user_ids'] ?? array(),
			'wm_bci_calendar_page_slug'               => (string) ( $legacy['calendar_page_slug'] ?? 'bci-resources' ),
			'wm_bci_calendar_feed_name'               => (string) ( $legacy['calendar_feed_name'] ?? 'BCI Community Opportunity Submission' ),
			'wm_bci_calendar_feed_id'                 => self::FEED_ID,
			'wm_bci_calendar_shortcode'               => '[gravitycalendar id="3"]',
			'wm_bci_google_sync_url'                  => (string) ( $legacy['google_sync_url'] ?? '' ),
			'wm_bci_google_sync_secret'               => (string) ( $legacy['google_sync_secret'] ?? '' ),
		);
		$explicit = array(
			'time_sensitive'          => '24',
			'non_date_sensitive_type' => '25',
			'bci_update'              => '26',
			'location_mode'           => '16',
		);

		foreach ( SettingsSchema::field_map_defaults() as $key => $default ) {
			$value = isset( $explicit[ $key ] ) ? $explicit[ $key ] : ( $legacy_map[ $key ] ?? $default );
			$settings[ 'wm_bci_field_map_' . $key ] = (string) $value;
		}

		return $settings;
	}

	/** @return array<int,string> */
	private function settings_changes( array $settings, $sensitive ) {
		$changes = array();

		foreach ( $settings as $field_name => $value ) {
			$is_sensitive = in_array( $field_name, array( 'wm_bci_google_sync_url', 'wm_bci_google_sync_secret' ), true );

			if ( (bool) $sensitive !== $is_sensitive ) {
				continue;
			}

			$desired     = $is_sensitive ? $value : SettingsSchema::sanitize_value( $field_name, $value );
			$option_name = SettingsSchema::option_name( $field_name );
			$current     = get_option( $option_name, null );

			if ( null === $current || serialize( $current ) !== serialize( $desired ) ) {
				$changes[] = $option_name;
			}
		}

		return $changes;
	}

	/** @return array<int,array<string,mixed>> */
	private function active_entries( $form_id, array &$errors ) {
		if ( ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_entries' ) ) {
			return array();
		}

		$total = 0;
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

	/** @return array<int,int> */
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
				$errors[] = sprintf( 'Entry %d links to duplicate posts %d and %d.', $entry_id, $posts[ $entry_id ], $post_id );
				continue;
			}

			$posts[ $entry_id ] = absint( $post_id );
		}

		return $posts;
	}

	/** @return array<string,string> */
	private function legacy_history( $entry_id, array &$errors ) {
		$legacy_status = trim( (string) $this->entry_meta( $entry_id, self::LEGACY_SYNC_STATUS_META ) );
		$status_map    = array(
			''        => '',
			'success' => 'synced',
			'failed'  => 'error',
		);

		if ( ! array_key_exists( $legacy_status, $status_map ) ) {
			$errors[] = sprintf( 'Entry %d has unsupported legacy Google sync status "%s".', $entry_id, $legacy_status );
		}

		return array(
			'approved_at' => (string) $this->entry_meta( $entry_id, self::LEGACY_APPROVED_AT_META ),
			'sync_status' => $status_map[ $legacy_status ] ?? '',
			'attempted_at'=> (string) $this->entry_meta( $entry_id, self::LEGACY_ATTEMPTED_AT_META ),
			'synced_at'   => (string) $this->entry_meta( $entry_id, self::LEGACY_SYNCED_AT_META ),
			'error'       => (string) $this->entry_meta( $entry_id, self::LEGACY_SYNC_ERROR_META ),
		);
	}

	/** @return mixed */
	private function entry_meta( $entry_id, $key ) {
		if ( function_exists( 'gform_get_meta' ) ) {
			return gform_get_meta( $entry_id, $key );
		}

		if ( class_exists( 'GFAPI' ) && method_exists( 'GFAPI', 'get_entry_meta' ) ) {
			return \GFAPI::get_entry_meta( $entry_id, $key );
		}

		return '';
	}

	private function post_needs_sync( $post_id, array $entry, $approval, array $history ) {
		$expected_status = 'Approved' === $approval ? 'publish' : 'pending';

		if (
			(string) get_post_field( 'post_status', $post_id, 'raw' ) !== $expected_status
			|| (string) get_post_field( 'post_title', $post_id, 'raw' ) !== sanitize_text_field( $this->fields->title( $entry ) )
			|| (string) get_post_field( 'post_content', $post_id, 'raw' ) !== wp_kses_post( trim( (string) $this->fields->value( $entry, $this->config->field( 'description' ) ) ) )
		) {
			return true;
		}

		foreach ( $this->expected_post_meta( $entry, $approval, $history ) as $key => $value ) {
			if ( (string) get_post_meta( $post_id, $key, true ) !== (string) $value ) {
				return true;
			}
		}

		return false;
	}

	/** @return string */
	private function post_fingerprint( $post_id, array $entry, $approval, array $history ) {
		$current_meta = array();

		foreach ( array_keys( $this->expected_post_meta( $entry, $approval, $history ) ) as $key ) {
			$current_meta[ $key ] = get_post_meta( $post_id, $key, true );
		}

		return $this->fingerprint(
			array(
				'post_id'      => absint( $post_id ),
				'post_status'  => get_post_field( 'post_status', $post_id, 'raw' ),
				'post_title'   => get_post_field( 'post_title', $post_id, 'raw' ),
				'post_content' => get_post_field( 'post_content', $post_id, 'raw' ),
				'meta'         => $current_meta,
			)
		);
	}

	/** @param mixed $value Value to bind to the approved plan. */
	private function fingerprint( $value ) {
		return hash( 'sha256', (string) wp_json_encode( $value ) );
	}

	/** @return array<string,mixed> */
	private function expected_post_meta( array $entry, $approval, array $history ) {
		$type        = $this->fields->opportunity_type( $entry );
		$type_config = $this->config->opportunity_type_config( $type );
		$type_value  = '' !== trim( (string) ( $type_config['name'] ?? '' ) ) ? (string) $type_config['name'] : $type;

		return array(
			$this->config->opportunity_field_name( 'source_entry_id' ) => absint( $entry['id'] ?? 0 ),
			$this->config->opportunity_field_name( 'approval_status' ) => $approval,
			$this->config->opportunity_field_name( 'submitted_at' ) => $this->fields->submitted_at( $entry ),
			$this->config->opportunity_field_name( 'opportunity_type' ) => $type_value,
			$this->config->opportunity_field_name( 'submitter_name' ) => $this->fields->submitter_name( $entry ),
			$this->config->opportunity_field_name( 'organization' ) => $this->fields->value( $entry, $this->config->field( 'organization' ) ),
			$this->config->opportunity_field_name( 'start_date' ) => $this->fields->value( $entry, $this->config->field( 'start_date' ) ),
			$this->config->opportunity_field_name( 'grant_deadline' ) => $this->fields->value( $entry, $this->config->field( 'grant_deadline' ) ),
			$this->config->opportunity_field_name( 'end_date' ) => $this->fields->value( $entry, $this->config->field( 'end_date' ) ),
			$this->config->opportunity_field_name( 'start_time' ) => $this->fields->value( $entry, $this->config->field( 'start_time' ) ),
			$this->config->opportunity_field_name( 'end_time' ) => $this->fields->value( $entry, $this->config->field( 'end_time' ) ),
			$this->config->opportunity_field_name( 'location_mode' ) => $this->fields->value( $entry, $this->config->field( 'location_mode' ) ),
			$this->config->opportunity_field_name( 'address' ) => $this->fields->address( $entry ),
			$this->config->opportunity_field_name( 'cost' ) => $this->fields->value( $entry, $this->config->field( 'cost' ) ),
			$this->config->opportunity_field_name( 'info_url' ) => esc_url_raw( (string) $this->fields->value( $entry, $this->config->field( 'info_url' ) ) ),
			$this->config->opportunity_field_name( 'file_upload' ) => $this->fields->file_upload( $entry ),
			$this->config->opportunity_field_name( 'approved_at' ) => $history['approved_at'],
			$this->config->opportunity_field_name( 'google_sync_status' ) => $history['sync_status'],
			$this->config->opportunity_field_name( 'google_sync_attempted_at' ) => $history['attempted_at'],
			$this->config->opportunity_field_name( 'google_sync_synced_at' ) => $history['synced_at'],
			$this->config->opportunity_field_name( 'google_sync_error' ) => $history['error'],
		);
	}

	private function apply_settings( array $settings, $sensitive ) {
		foreach ( $settings as $field_name => $value ) {
			$is_sensitive = in_array( $field_name, array( 'wm_bci_google_sync_url', 'wm_bci_google_sync_secret' ), true );

			if ( (bool) $sensitive !== $is_sensitive ) {
				continue;
			}

			$desired     = $is_sensitive ? $value : SettingsSchema::sanitize_value( $field_name, $value );
			$option_name = SettingsSchema::option_name( $field_name );
			$current     = get_option( $option_name, null );

			if ( null !== $current && serialize( $current ) === serialize( $desired ) ) {
				continue;
			}

			if ( null === $current ) {
				add_option( $option_name, $desired, '', false );
			} else {
				update_option( $option_name, $desired, false );
			}
		}
	}

	/** @return true|\WP_Error */
	private function import_entries( array $entry_plans ) {
		foreach ( $entry_plans as $entry_plan ) {
			if ( empty( $entry_plan['post_needs_sync'] ) ) {
				continue;
			}

			$entry_id = absint( $entry_plan['entry_id'] ?? 0 );
			$entry    = \GFAPI::get_entry( $entry_id );

			if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
				return new \WP_Error( 'community_resources_hub_cutover_entry_read_failed', sprintf( 'Could not reload entry %d.', $entry_id ) );
			}

			$post_id = $this->repository->upsert_from_entry( $entry, (string) $entry_plan['approval_status'] );

			if ( ! $post_id || ( ! empty( $entry_plan['post_id'] ) && absint( $entry_plan['post_id'] ) !== absint( $post_id ) ) ) {
				return new \WP_Error( 'community_resources_hub_cutover_post_import_failed', sprintf( 'Could not import entry %d one-to-one.', $entry_id ) );
			}

			$history_errors = array();
			$history        = $this->legacy_history( $entry_id, $history_errors );

			if ( ! empty( $history_errors ) ) {
				return new \WP_Error( 'community_resources_hub_cutover_history_invalid', implode( ' ', $history_errors ) );
			}

			$history_meta = array(
				$this->config->opportunity_field_name( 'approved_at' ) => $history['approved_at'],
				$this->config->opportunity_field_name( 'google_sync_status' ) => $history['sync_status'],
				$this->config->opportunity_field_name( 'google_sync_attempted_at' ) => $history['attempted_at'],
				$this->config->opportunity_field_name( 'google_sync_synced_at' ) => $history['synced_at'],
				$this->config->opportunity_field_name( 'google_sync_error' ) => $history['error'],
			);

			foreach ( $history_meta as $key => $value ) {
				if ( (string) get_post_meta( $post_id, $key, true ) !== (string) $value ) {
					update_post_meta( $post_id, $key, $value );
				}
			}
		}

		return true;
	}

	private function mark_reconciliation_completed( array $summary ) {
		if ( '' === trim( (string) get_option( self::RECONCILIATION_COMPLETED_OPTION, '' ) ) ) {
			$completed_at = gmdate( 'c' );
			update_option( self::RECONCILIATION_COMPLETED_OPTION, $completed_at, false );
			update_option(
				self::RECONCILIATION_SUMMARY_OPTION,
				array(
					'imported_entries' => (int) ( $summary['entries'] ?? 0 ),
					'completed_at'     => $completed_at,
					'source'           => self::VERSION,
				),
				false
			);
		}

		if ( null !== get_option( self::RECONCILIATION_PENDING_OPTION, null ) ) {
			delete_option( self::RECONCILIATION_PENDING_OPTION );
		}
	}

	private function persist_completion_marker( array $readback, $plan_hash ) {
		$marker = array(
			'version'      => self::VERSION,
			'completed_at' => gmdate( 'c' ),
			'site_url'     => function_exists( 'site_url' ) ? site_url() : '',
			'form_id'      => self::FORM_ID,
			'feed_id'      => self::FEED_ID,
			'plan_hash'    => (string) $plan_hash,
			'summary'      => $readback['summary'] ?? array(),
		);
		$current = get_option( self::COMPLETED_OPTION, null );

		if ( null === $current ) {
			add_option( self::COMPLETED_OPTION, $marker, '', false );
		} else {
			update_option( self::COMPLETED_OPTION, $marker, false );
		}
	}

	private function has_changes( array $changes ) {
		foreach ( $changes as $value ) {
			if ( ! empty( $value ) ) {
				return true;
			}
		}

		return false;
	}

	private function has_changes_except( array $changes, array $allowed ) {
		foreach ( $allowed as $key ) {
			$changes[ $key ] = 0;
		}

		return $this->has_changes( $changes );
	}

	private function clear_runtime_caches() {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'community_resources_hub_approved_opportunities' );
			delete_transient( 'community_resources_hub_member_directory' );
		}

		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}
}
