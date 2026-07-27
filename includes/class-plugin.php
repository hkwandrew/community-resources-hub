<?php
/**
 * Plugin bootstrap.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap.
 */
final class Plugin {

	/**
	 * Plugin root file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Prevent duplicate runtime booting.
	 *
	 * @var bool
	 */
	private $booted = false;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin root file path.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file = (string) $plugin_file;
	}

	/**
	 * Register plugin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'plugins_loaded', array( $this, 'boot' ) );
	}

	/**
	 * Runtime boot entrypoint.
	 *
	 * @return void
	 */
	public function boot() {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		load_plugin_textdomain(
			'community-resources-hub',
			false,
			dirname( COMMUNITY_RESOURCES_HUB_BASENAME ) . '/languages'
		);

		$this->boot_config();
		$this->boot_content_model();
		$this->boot_workflow_layer();
		$this->boot_calendar_integration();
		$this->boot_frontend();
		$this->boot_cli();
	}

	/**
	 * Boot the plugin-owned config subsystem.
	 *
	 * @return void
	 */
	private function boot_config() {
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-schema.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-settings-schema.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-config.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-acf-settings.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-provisioner.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-health-checks.php';

		( new Config\AcfSettings() )->register();
		( new Config\Provisioner() )->register();
		( new Config\HealthChecks() )->register();
	}

	/**
	 * Boot the plugin-owned content model.
	 *
	 * @return void
	 */
	private function boot_content_model() {
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-schema.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-post-types.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-taxonomy.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-meta.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-member-data-restore.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-opportunity-editor.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-acf-post-fields.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-acf-term-fields.php';

		( new ContentModel\PostTypes() )->register();
		( new ContentModel\Taxonomy() )->register();
		( new ContentModel\Meta() )->register();
		( new ContentModel\MemberDataRestore() )->register();
		( new ContentModel\OpportunityEditor() )->register();
		( new ContentModel\AcfPostFields() )->register();
		( new ContentModel\AcfTermFields() )->register();
	}

	/**
	 * Boot the plugin-owned workflow layer.
	 *
	 * @return void
	 */
	private function boot_workflow_layer() {
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-field-accessor.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-opportunity-repository.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-entry-bridge.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-entry-update-trigger.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-review-url.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-review-handler.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-approval-email.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-opportunity-ics-exporter.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-google-sync-manager.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-google-sync-backfill.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-google-sync-admin-panel.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-legacy-workflow-cutover.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-opportunity-reconciliation.php';

		$config     = new Config\Config();
		$repository = new Workflow\OpportunityRepository( $config );
		$sync       = new Workflow\GoogleSyncManager( $config );
		$backfill   = new Workflow\GoogleSyncBackfill( $config, $sync );

		( new Workflow\EntryBridge( $config, $repository, $sync ) )->register();
		( new Workflow\EntryUpdateTrigger( $config, $repository, $sync ) )->register();
		( new Workflow\ReviewHandler( $config, $repository, $sync ) )->register();
		( new Workflow\ApprovalEmail( $config ) )->register();
		( new Workflow\OpportunityIcsExporter( $repository, $config ) )->register();
		$backfill->register();
		( new Workflow\GoogleSyncAdminPanel( $config, $backfill ) )->register();
		( new Workflow\OpportunityReconciliation( $config, $repository ) )->register();
	}

	/**
	 * Boot the plugin-owned GravityCalendar integration.
	 *
	 * @return void
	 */
	private function boot_calendar_integration() {
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/assets/class-registry.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/calendar/class-event-filter.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/calendar/class-event-customizer.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/calendar/class-tooltip-options.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/calendar/class-runtime-assets.php';

		$config = new Config\Config();

		( new Calendar\EventFilter( $config ) )->register();
		( new Calendar\EventCustomizer( $config ) )->register();
		( new Calendar\TooltipOptions( $config ) )->register();
		( new Calendar\RuntimeAssets( $config ) )->register();
	}

	/**
	 * Boot the classic frontend integration layer.
	 *
	 * @return void
	 */
	private function boot_frontend() {
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-member-directory-service.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/support/class-render-support.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-member-directory-assets.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-member-directory-renderer.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-approved-opportunity-service.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-opportunity-hub-renderer.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-video-slider-assets.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-video-slider-renderer.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-newsletter-archives-assets.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-newsletter-archives-renderer.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/shortcodes/class-shortcodes.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/template-tags.php';

		( new Assets\Registry() )->register();
		FrontEnd\MemberDirectoryService::register_cache_invalidation();
		FrontEnd\ApprovedOpportunityService::register_cache_invalidation();
		( new Shortcodes\Shortcodes() )->register();
	}

	/**
	 * Register plugin-owned WP-CLI commands without loading them on web requests.
	 *
	 * @return void
	 */
	private function boot_cli() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! class_exists( '\WP_CLI' ) ) {
			return;
		}

		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-opportunity-contract-migration.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/cli/class-opportunity-contract-command.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/cli/class-legacy-workflow-cutover-command.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/cli/class-google-sync-entry-command.php';

		Cli\OpportunityContractCommand::register();
		Cli\LegacyWorkflowCutoverCommand::register();
		Cli\GoogleSyncEntryCommand::register();
	}

	/**
	 * Activation hook callback.
	 *
	 * @return void
	 */
	public static function activate() {
		self::load_lifecycle_dependencies();
		self::upsert_option( 'community_resources_hub_version', COMMUNITY_RESOURCES_HUB_VERSION );

		if ( false === get_option( 'community_resources_hub_installed_at', false ) ) {
			add_option( 'community_resources_hub_installed_at', gmdate( 'c' ), '', false );
		}

		self::seed_settings_defaults();

		if ( ! Workflow\LegacyWorkflowCutover::should_defer_automatic_reconciliation() ) {
			self::provision_bci_dependencies();
			self::upsert_option( Workflow\OpportunityReconciliation::PENDING_OPTION, gmdate( 'c' ) );
		}

		self::upsert_option( 'community_resources_hub_settings_seeded_at', gmdate( 'c' ) );
		self::register_lifecycle_rewrite_owners();
		self::delete_runtime_caches();

		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}
	}

	/**
	 * Deactivation hook callback.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::load_lifecycle_dependencies();
		Workflow\GoogleSyncBackfill::clear_scheduled_work();
	}

	/**
	 * Uninstall hook callback.
	 *
	 * @return void
	 */
	public static function uninstall() {
		self::load_lifecycle_dependencies();

		delete_option( 'community_resources_hub_version' );
		delete_option( 'community_resources_hub_installed_at' );
		delete_option( 'community_resources_hub_settings_seeded_at' );
		delete_option( 'community_resources_hub_opportunity_type_sync_completed_at' );
		delete_option( 'community_resources_hub_bci_provisioned_at' );
		delete_option( Config\Provisioner::FORM_CONTRACT_STATE_OPTION );
		delete_option( 'community_resources_hub_member_data_restore_summary' );
		delete_option( Workflow\OpportunityReconciliation::SUMMARY_OPTION );
		delete_option( Workflow\OpportunityReconciliation::PENDING_OPTION );
		delete_option( Workflow\OpportunityReconciliation::COMPLETED_OPTION );
		delete_option( Workflow\OpportunityReconciliation::LOCK_OPTION );
		delete_option( Workflow\OpportunityContractMigration::COMPLETED_OPTION );
		delete_option( Workflow\OpportunityContractMigration::LOCK_OPTION );
		delete_option( Workflow\LegacyWorkflowCutover::COMPLETED_OPTION );
		delete_option( Workflow\LegacyWorkflowCutover::LOCK_OPTION );
		Workflow\GoogleSyncBackfill::delete_state();

		foreach ( Config\SettingsSchema::option_names() as $option_name ) {
			delete_option( $option_name );
		}

		self::delete_runtime_caches();
	}

	/**
	 * Load files needed by activation and uninstall hooks.
	 *
	 * @return void
	 */
	private static function load_lifecycle_dependencies() {
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-schema.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-post-types.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/content-model/class-taxonomy.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-settings-schema.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-config.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/config/class-provisioner.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-field-accessor.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-opportunity-repository.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-google-sync-backfill.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-opportunity-reconciliation.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-opportunity-contract-migration.php';
		require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/workflow/class-legacy-workflow-cutover.php';
	}

	/**
	 * Seed absent ACF options with plugin defaults.
	 *
	 * @return void
	 */
	private static function seed_settings_defaults() {
		foreach ( Config\SettingsSchema::defaults() as $field_name => $default ) {
			$option_name = Config\SettingsSchema::option_name( $field_name );

			if ( null === get_option( $option_name, null ) ) {
				add_option( $option_name, $default, '', false );
			}
		}
	}

	/**
	 * Best-effort provisioning when third-party dependencies are already loaded.
	 *
	 * @return void
	 */
	private static function provision_bci_dependencies() {
		if ( ! class_exists( Config\Provisioner::class ) || ! Config\Provisioner::can_provision_dependencies() ) {
			return;
		}

		$result = ( new Config\Provisioner() )->provision();

		if ( ! is_wp_error( $result ) ) {
			self::upsert_option( 'community_resources_hub_bci_provisioned_at', gmdate( 'c' ) );
		}
	}

	/**
	 * Register rewrite-owning objects before flushing rules.
	 *
	 * @return void
	 */
	private static function register_lifecycle_rewrite_owners() {
		if ( class_exists( ContentModel\PostTypes::class ) ) {
			( new ContentModel\PostTypes() )->register_post_types();
		}

		if ( class_exists( ContentModel\Taxonomy::class ) ) {
			$taxonomy = new ContentModel\Taxonomy();
			$taxonomy->register_taxonomy();
			$taxonomy->register_term_meta();
			$taxonomy->ensure_default_opportunity_tags();
		}
	}

	/**
	 * Add or update a plugin-owned option without autoloading it.
	 *
	 * @param string $name Option name.
	 * @param mixed  $value Option value.
	 * @return void
	 */
	private static function upsert_option( $name, $value ) {
		if ( null === get_option( $name, null ) ) {
			add_option( $name, $value, '', false );
			return;
		}

		update_option( $name, $value, false );
	}

	/**
	 * Delete plugin-owned computed runtime caches.
	 *
	 * @return void
	 */
	private static function delete_runtime_caches() {
		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( 'community_resources_hub_member_directory' );
			delete_transient( 'community_resources_hub_approved_opportunities' );
		}
	}
}
