<?php
/**
 * Hub settings controls for Google Sheet sync recovery.
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
 * Adds protected, explicit recovery actions to the ACF Hub settings page.
 */
final class GoogleSyncAdminPanel {

	const ACTION                  = 'wm_bci_google_sync_operation';
	const NONCE_ACTION            = 'wm_bci_google_sync_operation';
	const NONCE_NAME              = 'wm_bci_google_sync_nonce';
	const NOTICE_TRANSIENT_PREFIX = 'community_resources_hub_google_sync_notice_';

	/**
	 * Workflow configuration.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Backfill service.
	 *
	 * @var object
	 */
	private $backfill;

	/**
	 * Set admin panel dependencies.
	 *
	 * @param Config $config Workflow configuration.
	 * @param object $backfill Backfill service.
	 */
	public function __construct( Config $config, $backfill ) {
		$this->config   = $config;
		$this->backfill = $backfill;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'acf/input/admin_head', array( $this, 'register_meta_box' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
	}

	/**
	 * Add the recovery panel only to this plugin's ACF options page.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page routing.

		if ( SettingsSchema::OPTIONS_PAGE_SLUG !== $page ) {
			return;
		}

		add_meta_box(
			'wm-bci-google-sync-recovery',
			esc_html__( 'Google Sheets Sync', 'community-resources-hub' ),
			array( $this, 'render_panel' ),
			'acf_options_page',
			'side',
			'high'
		);
	}

	/**
	 * Render status and actions inside ACF's existing options-page form.
	 *
	 * @return void
	 */
	public function render_panel() {
		$counts     = $this->backfill->status_counts();
		$job        = $this->backfill->job();
		$failure    = $this->backfill->latest_failure();
		$configured = $this->config->is_google_sync_configured();
		$job_status = sanitize_key( (string) ( $job['status'] ?? '' ) );
		$active     = in_array( $job_status, array( 'queued', 'running' ), true );
		$has_job    = ! empty( $job['id'] ) || '' !== $job_status;
		$unsynced   = absint( $counts['unsynced'] ?? 0 );

		$this->render_notice();

		echo '<p>' . esc_html__( 'New approvals sync immediately. Bulk recovery runs only when started here.', 'community-resources-hub' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Configuration:', 'community-resources-hub' ) . '</strong> ';
		echo $configured
			? esc_html__( 'Configured', 'community-resources-hub' )
			: esc_html__( 'Not configured', 'community-resources-hub' );
		echo '</p>';

		$this->render_counts( $counts );
		$this->render_job( $job );
		$this->render_latest_failure( $failure );

		$action_url = add_query_arg( array( 'action' => self::ACTION ), admin_url( 'admin-post.php' ) );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		echo '<p class="submit">';
		$this->render_button(
			$action_url,
			'sync_one',
			__( 'Sync One Entry', 'community-resources-hub' ),
			'button button-primary',
			! $configured || $active || 0 === $unsynced
		);
		echo ' ';
		$this->render_button(
			$action_url,
			'start_backfill',
			__( 'Start Backfill', 'community-resources-hub' ),
			'button',
			! $configured || $active || $has_job || 0 === $unsynced
		);
		echo '</p><p>';
		$this->render_button(
			$action_url,
			'resume',
			__( 'Resume', 'community-resources-hub' ),
			'button',
			! $configured || 'paused' !== $job_status
		);
		echo ' ';
		$this->render_button(
			$action_url,
			'retry_remaining',
			__( 'Retry Remaining', 'community-resources-hub' ),
			'button',
			! $configured || $active || ! $has_job || 0 === $unsynced
		);
		echo '</p>';
	}

	/**
	 * Handle one allow-listed recovery operation.
	 *
	 * @return void
	 */
	public function handle_action() {
		if ( ! current_user_can( SettingsSchema::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage Google Sheet sync.', 'community-resources-hub' ),
				esc_html__( 'Forbidden', 'community-resources-hub' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$operation = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';

		switch ( $operation ) {
			case 'sync_one':
				$result = $this->backfill->sync_one();
				$this->set_single_notice( $result );
				break;

			case 'start_backfill':
				$this->set_job_notice( $this->backfill->start_backfill(), __( 'Google Sheet backfill queued.', 'community-resources-hub' ) );
				break;

			case 'resume':
				$this->set_job_notice( $this->backfill->resume(), __( 'Google Sheet backfill resumed.', 'community-resources-hub' ) );
				break;

			case 'retry_remaining':
				$this->set_job_notice( $this->backfill->retry_remaining(), __( 'Remaining Google Sheet rows queued for retry.', 'community-resources-hub' ) );
				break;

			default:
				wp_die(
					esc_html__( 'Unknown Google Sheet sync operation.', 'community-resources-hub' ),
					esc_html__( 'Invalid request', 'community-resources-hub' ),
					array( 'response' => 400 )
				);
		}

		wp_safe_redirect( $this->settings_url() );
		exit;
	}

	/**
	 * Render admin-facing status counts.
	 *
	 * @param array<string,int> $counts Status counts.
	 * @return void
	 */
	private function render_counts( array $counts ) {
		$labels = array(
			'approved' => __( 'Approved', 'community-resources-hub' ),
			'synced'   => __( 'Synced', 'community-resources-hub' ),
			'pending'  => __( 'Pending', 'community-resources-hub' ),
			'failed'   => __( 'Failed', 'community-resources-hub' ),
			'skipped'  => __( 'Skipped', 'community-resources-hub' ),
			'unsynced' => __( 'Unsynced', 'community-resources-hub' ),
		);

		echo '<table class="widefat striped"><tbody>';

		foreach ( $labels as $key => $label ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>' . esc_html( number_format_i18n( absint( $counts[ $key ] ?? 0 ) ) ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Render saved backfill progress.
	 *
	 * @param array<string,mixed> $job Job state.
	 * @return void
	 */
	private function render_job( array $job ) {
		$status = sanitize_key( (string) ( $job['status'] ?? '' ) );

		if ( '' === $status ) {
			return;
		}

		printf(
			'<p><strong>%1$s</strong> %2$s<br><span class="description">%3$s</span></p>',
			esc_html__( 'Backfill:', 'community-resources-hub' ),
			esc_html( ucfirst( $status ) ),
			esc_html(
				sprintf(
					/* translators: 1: processed count, 2: total count, 3: synced count, 4: failed count, 5: skipped count. */
					__( '%1$d of %2$d processed. Synced: %3$d. Failed: %4$d. Skipped: %5$d.', 'community-resources-hub' ),
					absint( $job['cursor'] ?? 0 ),
					absint( $job['total'] ?? 0 ),
					absint( $job['synced'] ?? 0 ),
					absint( $job['failed'] ?? 0 ),
					absint( $job['skipped'] ?? 0 )
				)
			)
		);

		if ( ! empty( $job['pause_reason'] ) ) {
			echo '<p><strong>' . esc_html__( 'Paused:', 'community-resources-hub' ) . '</strong> ' . esc_html( (string) $job['pause_reason'] ) . '</p>';
		}
	}

	/**
	 * Render the most recent failed opportunity.
	 *
	 * @param array<string,mixed> $failure Latest failure.
	 * @return void
	 */
	private function render_latest_failure( array $failure ) {
		$post_id = absint( $failure['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return;
		}

		$title = get_the_title( $post_id );
		$link  = get_edit_post_link( $post_id, '' );

		if ( ! $title ) {
			$title = sprintf(
				/* translators: %d: opportunity post ID. */
				__( 'Opportunity #%d', 'community-resources-hub' ),
				$post_id
			);
		}

		echo '<p><strong>' . esc_html__( 'Latest failure:', 'community-resources-hub' ) . '</strong><br>';

		if ( $link ) {
			echo '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a><br>';
		}

		echo '<span class="description">' . esc_html( (string) ( $failure['error'] ?? '' ) ) . '</span></p>';
	}

	/**
	 * Render one operation button inside the existing ACF form.
	 *
	 * @param string $action_url Admin-post action URL.
	 * @param string $operation Operation identifier.
	 * @param string $label Button label.
	 * @param string $button_class Button CSS classes.
	 * @param bool   $disabled Whether the button is disabled.
	 * @return void
	 */
	private function render_button( $action_url, $operation, $label, $button_class, $disabled ) {
		printf(
			'<button type="submit" class="%1$s" name="operation" value="%2$s" formaction="%3$s" formmethod="post"%4$s>%5$s</button>',
			esc_attr( $button_class ),
			esc_attr( $operation ),
			esc_url( $action_url ),
			$disabled ? ' disabled="disabled"' : '',
			esc_html( $label )
		);
	}

	/**
	 * Persist feedback for one direct sync attempt.
	 *
	 * @param array<string,mixed> $result Single-sync result.
	 * @return void
	 */
	private function set_single_notice( array $result ) {
		if ( ! empty( $result['success'] ) ) {
			$this->set_notice(
				'success',
				sprintf(
					/* translators: %d: opportunity post ID. */
					__( 'Opportunity #%d synced to Google Sheets.', 'community-resources-hub' ),
					absint( $result['post_id'] ?? 0 )
				)
			);
			return;
		}

		$message = trim( (string) ( $result['error'] ?? '' ) );
		$this->set_notice( 'error', '' !== $message ? $message : __( 'The opportunity could not be synced.', 'community-resources-hub' ) );
	}

	/**
	 * Persist feedback for a bulk job operation.
	 *
	 * @param array<string,mixed> $job Job state.
	 * @param string              $message Success message.
	 * @return void
	 */
	private function set_job_notice( array $job, $message ) {
		$status = sanitize_key( (string) ( $job['status'] ?? '' ) );

		if ( '' === $status ) {
			$this->set_notice( 'error', __( 'No resumable Google Sheet job is available.', 'community-resources-hub' ) );
			return;
		}

		if ( 'complete' === $status && 0 === absint( $job['total'] ?? 0 ) ) {
			$this->set_notice( 'success', __( 'All approved opportunities are already synced.', 'community-resources-hub' ) );
			return;
		}

		$this->set_notice(
			'success',
			sprintf(
				/* translators: 1: operation message, 2: total job rows. */
				__( '%1$s Total rows: %2$d.', 'community-resources-hub' ),
				$message,
				absint( $job['total'] ?? 0 )
			)
		);
	}

	/**
	 * Persist a current-user operation notice.
	 *
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_notice( $type, $message ) {
		set_transient(
			$this->notice_key(),
			array(
				'type'    => 'error' === $type ? 'error' : 'success',
				'message' => substr( sanitize_text_field( (string) $message ), 0, 500 ),
			),
			5 * MINUTE_IN_SECONDS
		);
	}

	/**
	 * Render and consume current-user feedback.
	 *
	 * @return void
	 */
	private function render_notice() {
		$notice = get_transient( $this->notice_key() );

		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $this->notice_key() );
		$notice_class = 'error' === ( $notice['type'] ?? '' ) ? 'notice notice-error inline' : 'notice notice-success inline';

		echo '<div class="' . esc_attr( $notice_class ) . '"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
	}

	/**
	 * Current-user notice transient key.
	 *
	 * @return string
	 */
	private function notice_key() {
		return self::NOTICE_TRANSIENT_PREFIX . absint( get_current_user_id() );
	}

	/**
	 * Hub settings return URL.
	 *
	 * @return string
	 */
	private function settings_url() {
		return add_query_arg( array( 'page' => SettingsSchema::OPTIONS_PAGE_SLUG ), admin_url( 'admin.php' ) );
	}
}
