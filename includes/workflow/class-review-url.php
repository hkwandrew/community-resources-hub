<?php
/**
 * Signed BCI review URLs.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates and verifies HMAC-signed review URLs.
 */
final class ReviewUrl {

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
	 * Generate a review link for a source GF entry.
	 */
	public function generate( $entry_id, $status ) {
		$expires = time() + WEEK_IN_SECONDS;

		return add_query_arg(
			array(
				'action'    => 'wm_bci_review',
				'entry'     => absint( $entry_id ),
				'status'    => sanitize_key( $status ),
				'expires'   => $expires,
				'signature' => $this->signature( $entry_id, $status, $expires ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Verify a provided review link signature.
	 */
	public function verify( $entry_id, $status, $expires, $provided ) {
		return hash_equals( $this->signature( $entry_id, $status, $expires ), (string) $provided );
	}

	/**
	 * Build the signature used by plugin-owned review links.
	 */
	public function signature( $entry_id, $status, $expires ) {
		$payload = implode(
			'|',
			array(
				'bci-review',
				$this->config->form_id(),
				absint( $entry_id ),
				sanitize_key( $status ),
				absint( $expires ),
			)
		);

		return hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
	}
}
