<?php
/**
 * BCI approval notification email customization.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds review links and a BCI summary to the configured GF admin notification.
 */
final class ApprovalEmail {

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		if ( ! $this->config->form_id() ) {
			return;
		}

		add_filter( 'gform_disable_notification_' . $this->config->form_id(), array( $this, 'disable_for_auto_approved_submitter' ), 10, 4 );
		add_filter( 'gform_notification_' . $this->config->form_id(), array( $this, 'customize' ), 10, 3 );
	}

	/**
	 * Skip the approval-review notification when the submitter is auto-approved.
	 *
	 * @param bool                $is_disabled Whether Gravity Forms already disabled the notification.
	 * @param array<string,mixed> $notification Notification config.
	 * @param array<string,mixed> $form Form data.
	 * @param array<string,mixed> $entry Entry data.
	 * @return bool
	 */
	public function disable_for_auto_approved_submitter( $is_disabled, array $notification, array $form, array $entry ) {
		if (
			$is_disabled
			|| $this->config->form_id() !== (int) rgar( $form, 'id' )
			|| $this->config->notification_name() !== (string) rgar( $notification, 'name' )
		) {
			return (bool) $is_disabled;
		}

		$approval_status = trim( (string) rgar( $entry, $this->config->approval_field_id() ) );
		$created_by      = absint( rgar( $entry, 'created_by' ) );

		return 'Approved' === $approval_status
			&& $created_by
			&& in_array( $created_by, $this->config->auto_approved_user_ids(), true );
	}

	/**
	 * @param array<string,mixed> $notification Notification config.
	 * @param array<string,mixed> $form Form data.
	 * @param array<string,mixed> $entry Entry data.
	 * @return array<string,mixed>
	 */
	public function customize( array $notification, array $form, array $entry ) {
		if (
			$this->config->form_id() !== (int) rgar( $form, 'id' )
			|| $this->config->notification_name() !== (string) rgar( $notification, 'name' )
		) {
			return $notification;
		}

		$fields = new FieldAccessor( $this->config );
		$to     = $this->config->approval_notification_recipients();

		if ( '' !== $to ) {
			$notification['toType']  = 'email';
			$notification['to']      = $to;
			$notification['toField'] = '';
			$notification['routing'] = null;
		}

		$notification['subject']           = sprintf(
			/* translators: %s: opportunity title. */
			__( 'Review needed: %s', 'community-resources-hub' ),
			$fields->title( $entry )
		);
		$notification['message']           = $this->message( $entry, $fields );
		$notification['message_format']    = 'html';
		$notification['disableAutoformat'] = true;

		return $notification;
	}

	/**
	 * Build email body.
	 *
	 * @param array<string,mixed> $entry Entry data.
	 */
	private function message( array $entry, FieldAccessor $fields ) {
		$summary_rows = array(
			__( 'Opportunity', 'community-resources-hub' )  => $fields->title( $entry ),
			__( 'Type', 'community-resources-hub' )         => $fields->legacy_opportunity_type( $fields->opportunity_type( $entry ) ),
			__( 'Submitter', 'community-resources-hub' )    => $fields->submitter_name( $entry ),
			__( 'Organization', 'community-resources-hub' ) => $fields->organization( $entry ),
			__( 'Date', 'community-resources-hub' )         => $fields->primary_date_value( $entry ),
			__( 'Time', 'community-resources-hub' )         => $fields->time_range( $entry ),
			__( 'Location', 'community-resources-hub' )     => $fields->address( $entry ),
			__( 'Cost', 'community-resources-hub' )         => $fields->cost( $entry ),
		);
		$list_items   = array();
		$review_url   = new ReviewUrl( $this->config );
		$entry_id     = (int) rgar( $entry, 'id' );
		$description  = $fields->description( $entry );
		$info_url     = $fields->info_url( $entry );
		$file_url     = $fields->file_upload( $entry );

		foreach ( $summary_rows as $label => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}

			$list_items[] = sprintf(
				'<li><strong>%1$s:</strong> %2$s</li>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		$message  = '<p>' . esc_html__( 'A new BCI community opportunity submission needs review.', 'community-resources-hub' ) . '</p>';
		$message .= '<ul>' . implode( '', $list_items ) . '</ul>';

		if ( '' !== $description ) {
			$message .= '<p><strong>' . esc_html__( 'Description', 'community-resources-hub' ) . '</strong><br>' . nl2br( esc_html( $description ) ) . '</p>';
		}

		if ( '' !== $info_url ) {
			$message .= sprintf( '<p><strong>%2$s:</strong> <a href="%1$s">%1$s</a></p>', esc_url( $info_url ), esc_html__( 'More information', 'community-resources-hub' ) );
		}

		if ( '' !== $file_url ) {
			$message .= sprintf( '<p><strong>%2$s:</strong> <a href="%1$s">%1$s</a></p>', esc_url( $file_url ), esc_html__( 'Attachment', 'community-resources-hub' ) );
		}

		$message .= '<p><strong>' . esc_html__( 'Review actions', 'community-resources-hub' ) . '</strong></p>';
		$message .= '<ul>';
		$message .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $review_url->generate( $entry_id, 'approved' ) ), esc_html__( 'Approve submission', 'community-resources-hub' ) );
		$message .= sprintf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $review_url->generate( $entry_id, 'rejected' ) ), esc_html__( 'Reject submission', 'community-resources-hub' ) );
		$message .= sprintf(
			'<li><a href="%1$s">%2$s</a></li>',
			esc_url(
				add_query_arg(
					array(
						'page' => 'gf_entries',
						'view' => 'entry',
						'id'   => $this->config->form_id(),
						'lid'  => $entry_id,
					),
					admin_url( 'admin.php' )
				)
			),
			esc_html__( 'View entry in Gravity Forms', 'community-resources-hub' )
		);
		$message .= '</ul>';
		$message .= '<p>' . esc_html__( 'Review links expire in 7 days.', 'community-resources-hub' ) . '</p>';

		return $message;
	}
}
