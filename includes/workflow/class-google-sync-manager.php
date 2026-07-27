<?php
/**
 * Plugin-owned Google sync manager for approved BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends approved opportunity payloads to the configured Google Apps Script endpoint.
 */
final class GoogleSyncManager {

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Gravity Forms field accessors.
	 *
	 * @var FieldAccessor
	 */
	private $fields;

	/**
	 * Set the workflow configuration and shared field accessors.
	 *
	 * @param Config $config Workflow configuration.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
		$this->fields = new FieldAccessor( $config );
	}

	/**
	 * Sync a single approved opportunity.
	 *
	 * @param int $post_id Opportunity post ID.
	 * @return bool Whether the remote sync completed successfully.
	 */
	public function sync_opportunity( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return false;
		}

		$this->update_sync_meta(
			$post_id,
			array(
				'google_sync_status'       => 'pending',
				'google_sync_attempted_at' => gmdate( 'c' ),
				'google_sync_error'        => '',
			)
		);

		if ( ! $this->config->is_google_sync_configured() ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'skipped',
					'google_sync_error'  => __( 'Google sync is not configured.', 'community-resources-hub' ),
				)
			);

			return false;
		}

		if ( ! function_exists( 'wp_remote_post' ) || ! function_exists( 'wp_remote_get' ) ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => __( 'WordPress HTTP API is unavailable.', 'community-resources-hub' ),
				)
			);

			return false;
		}

		$entry_id = absint( $this->meta( $post_id, 'source_entry_id' ) );

		if ( ! $entry_id || ! class_exists( 'GFAPI' ) || ! method_exists( 'GFAPI', 'get_entry' ) ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => __( 'The source Gravity Forms entry is unavailable.', 'community-resources-hub' ),
				)
			);

			return false;
		}

		$entry = \GFAPI::get_entry( $entry_id );

		if ( is_wp_error( $entry ) || ! is_array( $entry ) || $this->config->form_id() !== absint( $this->fields->value( $entry, 'form_id' ) ) ) {
			$error = is_wp_error( $entry )
				? sanitize_text_field( $entry->get_error_message() )
				: __( 'The source Gravity Forms entry is invalid.', 'community-resources-hub' );

			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => $error,
				)
			);

			return false;
		}

		if ( absint( $this->fields->value( $entry, 'id' ) ) !== $entry_id || 'Approved' !== trim( (string) $this->fields->value( $entry, $this->config->field( 'approval_status' ) ) ) ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => __( 'Only approved BCI entries can be synced.', 'community-resources-hub' ),
				)
			);

			return false;
		}

		$body = wp_json_encode( $this->payload( $post_id, $entry ) );

		if ( ! is_string( $body ) || '' === $body ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => __( 'Unable to encode Google sync payload.', 'community-resources-hub' ),
				)
			);

			return false;
		}

		$signature = hash_hmac( 'sha256', $body, $this->config->google_sync_secret() );
		$sync_url  = add_query_arg( array( 'signature' => $signature ), $this->config->google_sync_url() );
		$response  = $this->request_response(
			$sync_url,
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'headers'     => array(
					'Content-Type'            => 'application/json; charset=utf-8',
					'X-Waters-Meet-Signature' => $signature,
				),
				'body'        => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => sanitize_text_field( $response->get_error_message() ),
				)
			);

			return false;
		}

		$status_code   = absint( wp_remote_retrieve_response_code( $response ) );
		$response_body = (string) wp_remote_retrieve_body( $response );
		$response_data = json_decode( $response_body, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => sprintf(
						/* translators: %d: HTTP status code. */
						__( 'Google sync returned HTTP %d.', 'community-resources-hub' ),
						$status_code
					),
				)
			);

			return false;
		}

		if ( ! is_array( $response_data ) || empty( $response_data['ok'] ) ) {
			$error = is_array( $response_data ) && ! empty( $response_data['error'] )
				? sanitize_text_field( (string) $response_data['error'] )
				: __( 'Google sync returned an unexpected response.', 'community-resources-hub' );

			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => substr( $error, 0, 500 ),
				)
			);

			return false;
		}

		$disposition = sanitize_key( (string) ( $response_data['disposition'] ?? '' ) );

		if ( ! in_array( $disposition, array( 'appended', 'duplicate' ), true ) ) {
			$this->update_sync_meta(
				$post_id,
				array(
					'google_sync_status' => 'error',
					'google_sync_error'  => __( 'Google sync returned an unrecognized disposition.', 'community-resources-hub' ),
				)
			);

			return false;
		}

		$this->update_sync_meta(
			$post_id,
			array(
				'google_sync_status'    => 'synced',
				'google_sync_synced_at' => gmdate( 'c' ),
				'google_sync_error'     => '',
			)
		);

		return true;
	}

	/**
	 * Follow Apps Script's POST response redirect with a GET.
	 *
	 * @param string $url Request URL.
	 * @param array  $args WordPress HTTP API arguments.
	 * @param string $method Request method.
	 * @param int    $redirect_count Redirects followed so far.
	 * @return array|\WP_Error
	 */
	private function request_response( $url, array $args, $method = 'POST', $redirect_count = 0 ) {
		$args['redirection'] = 0;
		$response            = 'GET' === $method ? wp_remote_get( $url, $args ) : wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = absint( wp_remote_retrieve_response_code( $response ) );
		$location    = (string) wp_remote_retrieve_header( $response, 'location' );

		if ( $redirect_count >= 5 || ! in_array( $status_code, array( 301, 302, 303, 307, 308 ), true ) || '' === $location ) {
			return $response;
		}

		if ( 'https' !== strtolower( (string) wp_parse_url( $location, PHP_URL_SCHEME ) ) ) {
			return new \WP_Error( 'community_resources_hub_google_sync_redirect', __( 'Google sync returned an unsafe redirect.', 'community-resources-hub' ) );
		}

		return $this->request_response(
			$location,
			array(
				'timeout'     => $args['timeout'] ?? 20,
				'redirection' => 0,
				'cookies'     => wp_remote_retrieve_cookies( $response ),
			),
			'GET',
			$redirect_count + 1
		);
	}

	/**
	 * Payload sent to the Google Apps Script endpoint.
	 *
	 * @param int                 $post_id Opportunity post ID.
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return array<string,mixed>
	 */
	private function payload( $post_id, array $entry ) {
		$entry_id = absint( $this->fields->value( $entry, 'id' ) );

		return array(
			'event'            => 'bci_entry_approved',
			'entryId'          => $entry_id,
			'approvedAt'       => $this->meta( $post_id, 'approved_at' ),
			'headers'          => $this->headers(),
			'row'              => $this->row( $entry ),
			'sourceEntryUrl'   => $this->entry_admin_url( $entry_id ),
			'sourceEntriesUrl' => $this->entries_admin_url(),
		);
	}

	/**
	 * Established Google Sheet column contract.
	 *
	 * @return array<int,string>
	 */
	private function headers() {
		return array(
			'Timestamp',
			'What kind of opportunity is this?',
			'Your name:',
			'What is the title of your community opportunity?',
			'What is the name of your organization?',
			"When is your opportunity happening? We need this to have this with at least a week's notice. The newsletter goes out on Thursdays, so anything happening before Thursday of the current week should not be included.",
			'If your opportunity has a date range, what is the end date?',
			'For events and learning opportunities, what time of day is it happening?',
			'For opportunities with a physical location, what is the address?',
			'Is there any cost?',
			'Provide a short description of this opportunity',
			'Provide a link for additional information:',
			'Please upload any relevant files here:',
			'Has this been in a newsletter?',
			'Additional Info , Instructions, and Commentary',
		);
	}

	/**
	 * Map one Gravity Forms entry to the established Google Sheet columns.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return array<int,string>
	 */
	private function row( array $entry ) {
		$date_created = trim( (string) $this->fields->value( $entry, 'date_created' ) );
		$timestamp    = '';

		if ( '' !== $date_created ) {
			$created_at = strtotime( $date_created . ' UTC' );

			if ( false !== $created_at ) {
				$timestamp = gmdate( 'c', $created_at );
			}
		}

		return array(
			$timestamp,
			$this->fields->legacy_opportunity_type( $this->fields->opportunity_type( $entry ) ),
			$this->fields->submitter_name( $entry ),
			$this->fields->title( $entry ),
			$this->fields->organization( $entry ),
			$this->fields->primary_date_value( $entry ),
			$this->fields->end_date( $entry ),
			$this->fields->time_range( $entry ),
			$this->fields->address( $entry ),
			$this->fields->cost( $entry ),
			$this->fields->description( $entry ),
			esc_url_raw( trim( (string) $this->fields->value( $entry, $this->config->field( 'info_url' ) ) ) ),
			$this->fields->file_upload( $entry ),
			'',
			'',
		);
	}

	/**
	 * Admin URL for the source Gravity Forms entry.
	 *
	 * @param int $entry_id Gravity Forms entry ID.
	 * @return string
	 */
	private function entry_admin_url( $entry_id ) {
		return add_query_arg(
			array(
				'page' => 'gf_entries',
				'view' => 'entry',
				'id'   => $this->config->form_id(),
				'lid'  => absint( $entry_id ),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Admin URL for the configured Gravity Forms entries list.
	 *
	 * @return string
	 */
	private function entries_admin_url() {
		return add_query_arg(
			array(
				'page' => 'gf_entries',
				'id'   => $this->config->form_id(),
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Scalar opportunity meta by semantic key.
	 *
	 * @param int    $post_id Opportunity post ID.
	 * @param string $semantic_key Semantic meta key.
	 * @return string
	 */
	private function meta( $post_id, $semantic_key ) {
		return trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( $semantic_key ), true ) );
	}

	/**
	 * Persist sync status meta.
	 *
	 * @param int                 $post_id Opportunity post ID.
	 * @param array<string,mixed> $values Sync values keyed by semantic meta key.
	 */
	private function update_sync_meta( $post_id, array $values ) {
		foreach ( $values as $semantic_key => $value ) {
			update_post_meta( $post_id, $this->config->opportunity_field_name( $semantic_key ), $value );
		}
	}
}
