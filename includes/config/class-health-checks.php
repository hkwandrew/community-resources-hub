<?php
/**
 * Plugin-owned setup and dependency health checks.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Config;

use WatersMeet\CommunityResourcesHub\ContentModel\MemberDataRestore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reports admin-only setup issues before public surfaces render empty.
 */
final class HealthChecks {

	const RECONCILIATION_ACTION        = 'wm_bci_reconcile_opportunities';
	const RECONCILIATION_NONCE_ACTION  = 'wm_bci_reconcile_opportunities';
	const RECONCILIATION_RESULT_TRANSIENT = 'community_resources_hub_opportunity_reconciliation_notice';
	const RECONCILIATION_SUMMARY_OPTION = 'community_resources_hub_opportunity_reconciliation_summary';
	const RECONCILIATION_PENDING_OPTION = 'community_resources_hub_opportunity_reconciliation_pending_at';

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	public function __construct( ?Config $config = null ) {
		$this->config = $config ?: new Config();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Current setup/dependency issues.
	 *
	 * @return array<int,string>
	 */
	public function issues() {
		$issues = array();

		if ( ! function_exists( 'acf_add_options_page' ) || ! function_exists( 'acf_add_local_field_group' ) ) {
			$issues[] = __( 'Advanced Custom Fields Pro is required for the BCI Hub settings screen.', 'community-resources-hub' );
		}

		if ( ! class_exists( 'GFAPI' ) && ! class_exists( 'GFForms' ) && ! function_exists( 'gravity_form' ) ) {
			$issues[] = __( 'Gravity Forms is required for BCI opportunity submissions and approval syncing.', 'community-resources-hub' );
		}

		if ( function_exists( 'shortcode_exists' ) && ! shortcode_exists( 'gravitycalendar' ) ) {
			$issues[] = __( 'GravityCalendar is not available; BCI calendar embeds will stay hidden unless markup is supplied directly.', 'community-resources-hub' );
		}

		if ( $this->config->form_id() < 1 ) {
			$issues[] = __( 'Set the BCI Gravity Forms form ID.', 'community-resources-hub' );
		}

		if ( '' === $this->config->approval_field_id() ) {
			$issues[] = __( 'Set the Gravity Forms approval status field ID.', 'community-resources-hub' );
		}

		if ( '' === $this->config->notification_name() ) {
			$issues[] = __( 'Set the Gravity Forms notification name used for approval review emails.', 'community-resources-hub' );
		}

		if ( '' === $this->config->calendar_page_slug() ) {
			$issues[] = __( 'Set the BCI resources page slug.', 'community-resources-hub' );
		}

		if ( '' === $this->config->calendar_feed_name() ) {
			$issues[] = __( 'Set the GravityCalendar feed name used for BCI events.', 'community-resources-hub' );
		}

		if ( $this->config->calendar_feed_id() < 1 ) {
			$issues[] = __( 'Set the GravityCalendar feed ID used by the BCI opportunity hub.', 'community-resources-hub' );
		}

		$calendar_shortcode_source = $this->config->calendar_shortcode_source();

		if ( '' === $calendar_shortcode_source ) {
			$issues[] = __( 'Set the GravityCalendar shortcode used by the BCI opportunity hub.', 'community-resources-hub' );
		} elseif ( '' === $this->config->calendar_shortcode() ) {
			$issues[] = __( 'The saved GravityCalendar shortcode must use the [gravitycalendar ...] format.', 'community-resources-hub' );
		}

		if ( $this->has_missing_field_map_values() ) {
			$issues[] = __( 'Complete the BCI Gravity Forms field mapping.', 'community-resources-hub' );
		}

		$issues = array_merge( $issues, $this->reconciliation_issues() );

		return $issues;
	}

	/**
	 * Render admin notices for users allowed to configure the plugin.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! is_admin() || ! current_user_can( SettingsSchema::CAPABILITY ) ) {
			return;
		}

		$provisioning_notice    = $this->provisioning_notice();
		$reconciliation_notice  = $this->reconciliation_notice();
		$member_restore_notice  = $this->member_restore_notice();
		$issues                 = $this->issues();

		if ( ! empty( $provisioning_notice ) ) {
			$notice_type = 'success' === ( $provisioning_notice['type'] ?? '' ) ? 'success' : 'error';
			?>
			<div class="notice notice-<?php echo esc_attr( $notice_type ); ?>">
				<p><?php echo esc_html( $provisioning_notice['message'] ?? '' ); ?></p>
			</div>
			<?php
		}

		if ( ! empty( $reconciliation_notice ) ) {
			$notice_type = 'success' === ( $reconciliation_notice['type'] ?? '' ) ? 'success' : 'error';
			?>
			<div class="notice notice-<?php echo esc_attr( $notice_type ); ?>">
				<p><?php echo esc_html( $reconciliation_notice['message'] ?? '' ); ?></p>
			</div>
			<?php
		}

		if ( ! empty( $member_restore_notice ) ) {
			$notice_type = in_array( $member_restore_notice['type'] ?? '', array( 'success', 'warning' ), true ) ? $member_restore_notice['type'] : 'error';
			?>
			<div class="notice notice-<?php echo esc_attr( $notice_type ); ?>">
				<p><?php echo esc_html( $member_restore_notice['message'] ?? '' ); ?></p>
			</div>
			<?php
		}

		if ( empty( $issues ) ) {
			return;
		}

		$settings_url = admin_url( 'admin.php?page=' . SettingsSchema::OPTIONS_PAGE_SLUG );
		?>
		<div class="notice notice-warning">
			<p><strong><?php echo esc_html__( 'Community Resources Hub setup needs attention.', 'community-resources-hub' ); ?></strong></p>
			<ul>
				<?php foreach ( $issues as $issue ) : ?>
					<li><?php echo esc_html( $issue ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<a href="<?php echo esc_url( $settings_url ); ?>">
					<?php echo esc_html__( 'Open BCI Hub settings', 'community-resources-hub' ); ?>
				</a>
			</p>
			<?php if ( class_exists( Provisioner::class ) && Provisioner::can_provision_dependencies() ) : ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="<?php echo esc_attr( Provisioner::ACTION ); ?>">
					<?php wp_nonce_field( Provisioner::ACTION ); ?>
					<?php submit_button( esc_html__( 'Create or adopt BCI Hub resources', 'community-resources-hub' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RECONCILIATION_ACTION ); ?>">
				<?php wp_nonce_field( self::RECONCILIATION_NONCE_ACTION ); ?>
				<?php submit_button( esc_html__( 'Reconcile legacy BCI opportunities', 'community-resources-hub' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Read and clear the last provisioning notice.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private function provisioning_notice() {
		if ( ! class_exists( Provisioner::class ) || ! function_exists( 'get_transient' ) ) {
			return null;
		}

		$notice = get_transient( Provisioner::RESULT_TRANSIENT );

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( Provisioner::RESULT_TRANSIENT );
		}

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Read and clear the last opportunity reconciliation notice.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private function reconciliation_notice() {
		if ( ! function_exists( 'get_transient' ) ) {
			return null;
		}

		$notice = get_transient( self::RECONCILIATION_RESULT_TRANSIENT );

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( self::RECONCILIATION_RESULT_TRANSIENT );
		}

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Read and clear the last member data restore notice.
	 *
	 * @return array{type:string,message:string}|null
	 */
	private function member_restore_notice() {
		if ( ! class_exists( MemberDataRestore::class ) || ! function_exists( 'get_transient' ) ) {
			return null;
		}

		$notice = get_transient( MemberDataRestore::RESULT_TRANSIENT );

		if ( function_exists( 'delete_transient' ) ) {
			delete_transient( MemberDataRestore::RESULT_TRANSIENT );
		}

		return is_array( $notice ) ? $notice : null;
	}

	/**
	 * Whether any required field-map setting is empty.
	 *
	 * @return bool
	 */
	private function has_missing_field_map_values() {
		foreach ( $this->config->field_map() as $field_id ) {
			if ( '' === trim( (string) $field_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reconciliation issues based on the persisted legacy-opportunity summary.
	 *
	 * @return array<int,string>
	 */
	private function reconciliation_issues() {
		$issues = array();
		$pending = trim( (string) get_option( self::RECONCILIATION_PENDING_OPTION, '' ) );
		$summary = get_option( self::RECONCILIATION_SUMMARY_OPTION, array() );
		$summary = is_array( $summary ) ? $summary : array();

		if ( '' !== $pending ) {
			$issues[] = __( 'Run the legacy BCI opportunity reconciliation so pre-plugin opportunities are adopted into the canonical workflow.', 'community-resources-hub' );
		}

		$unresolved = absint( $summary['unresolved_posts'] ?? 0 );

		if ( $unresolved > 0 ) {
			$issues[] = sprintf(
				/* translators: %d: unresolved legacy opportunity count. */
				__( '%d legacy BCI opportunities are still missing source-entry identity and need manual review.', 'community-resources-hub' ),
				$unresolved
			);
		}

		return $issues;
	}
}
