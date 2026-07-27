<?php
/**
 * BCI review-link request handling.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles approve/reject links against plugin-owned opportunity posts.
 */
final class ReviewHandler {

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Opportunity repository.
	 *
	 * @var OpportunityRepository
	 */
	private $repository;

	/**
	 * Google sync manager.
	 *
	 * @var GoogleSyncManager|null
	 */
	private $sync;

	public function __construct( Config $config, OpportunityRepository $repository, ?GoogleSyncManager $sync = null ) {
		$this->config     = $config;
		$this->repository = $repository;
		$this->sync       = $sync;
	}

	/**
	 * Register admin-post handlers.
	 */
	public function register() {
		add_action( 'admin_post_wm_bci_review', array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_wm_bci_review', array( $this, 'handle' ) );
	}

	/**
	 * Handle approve/reject requests.
	 */
	public function handle() {
		$entry_id = isset( $_GET['entry'] ) ? absint( wp_unslash( $_GET['entry'] ) ) : 0;
		$status   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$expires  = isset( $_GET['expires'] ) ? absint( wp_unslash( $_GET['expires'] ) ) : 0;
		$provided = isset( $_GET['signature'] ) ? sanitize_text_field( wp_unslash( $_GET['signature'] ) ) : '';

		if ( ! $entry_id || ! $status || ! $expires || ! $provided ) {
			$this->respond( 400, __( 'Invalid review link.', 'community-resources-hub' ), __( 'This approval link is missing required information.', 'community-resources-hub' ) );
		}

		if ( time() > $expires ) {
			$this->respond( 410, __( 'Review link expired.', 'community-resources-hub' ), __( 'This approval link has expired. Open the opportunity in WordPress to review it manually.', 'community-resources-hub' ) );
		}

		$normalized_status = $this->config->status_label( $status );
		$review_url        = new ReviewUrl( $this->config );

		if ( '' === $normalized_status || ! $review_url->verify( $entry_id, $status, $expires, $provided ) ) {
			$this->respond( 403, __( 'Review link invalid.', 'community-resources-hub' ), __( 'This approval link could not be verified.', 'community-resources-hub' ) );
		}

		$post_id = $this->repository->find_by_source_entry_id( $entry_id );

		if ( ! $post_id ) {
			$this->respond( 404, __( 'Opportunity not found.', 'community-resources-hub' ), __( 'The requested BCI opportunity could not be found.', 'community-resources-hub' ) );
		}

		$this->apply_status( $post_id, $entry_id, $normalized_status );

		$this->respond(
			200,
			sprintf(
				/* translators: %s: approval status. */
				__( 'Submission %s.', 'community-resources-hub' ),
				strtolower( $normalized_status )
			),
			sprintf(
				/* translators: 1: opportunity title, 2: approval status. */
				__( 'The BCI submission "%1$s" is now marked %2$s.', 'community-resources-hub' ),
				get_the_title( $post_id ),
				$normalized_status
			),
			array(
				array(
					'label' => __( 'Edit opportunity', 'community-resources-hub' ),
					'url'   => get_edit_post_link( $post_id, '' ),
				),
				array(
					'label' => __( 'View BCI resources page', 'community-resources-hub' ),
					'url'   => home_url( '/' . $this->config->calendar_page_slug() . '/' ),
				),
			)
		);
	}

	/**
	 * Apply approval status to CPT meta and the GF mirror field.
	 */
	public function apply_status( $post_id, $entry_id, $status ) {
		update_post_meta( $post_id, $this->config->opportunity_field_name( 'approval_status' ), $status );
		$this->repository->update_post_status_for_approval( $post_id, $status );

		if ( 'Approved' === $status && '' === (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'approved_at' ), true ) ) {
			update_post_meta( $post_id, $this->config->opportunity_field_name( 'approved_at' ), gmdate( 'c' ) );
		} elseif ( 'Approved' !== $status ) {
			delete_post_meta( $post_id, $this->config->opportunity_field_name( 'approved_at' ) );
		}

		$this->mirror_gf_status( $entry_id, $status );

		if ( 'Approved' === $status && $this->sync ) {
			$this->sync->sync_opportunity( $post_id );
		}
	}

	/**
	 * Keep GravityCalendar's GF source feed in sync.
	 */
	private function mirror_gf_status( $entry_id, $status ) {
		if ( ! $entry_id || ! class_exists( 'GFAPI' ) ) {
			return;
		}

		\GFAPI::update_entry_field( absint( $entry_id ), $this->config->approval_field_id(), $status );

		if ( method_exists( 'GFAPI', 'add_note' ) ) {
			\GFAPI::add_note(
				absint( $entry_id ),
				0,
				__( 'Watersmeet BCI Approval Link', 'community-resources-hub' ),
				sprintf(
					/* translators: %s: approval status. */
					__( 'BCI opportunity approval changed to %s via secure review link.', 'community-resources-hub' ),
					$status
				)
			);
		}
	}

	/**
	 * Render a plain response page.
	 *
	 * @param array<int,array{label:string,url:string}> $links Links.
	 */
	private function respond( $status_code, $title, $message, array $links = array() ) {
		$link_markup = '';

		if ( ! empty( $links ) ) {
			$items = array();

			foreach ( $links as $link ) {
				if ( empty( $link['label'] ) || empty( $link['url'] ) ) {
					continue;
				}

				$items[] = sprintf(
					'<li><a href="%1$s">%2$s</a></li>',
					esc_url( $link['url'] ),
					esc_html( $link['label'] )
				);
			}

			if ( ! empty( $items ) ) {
				$link_markup = '<ul>' . implode( '', $items ) . '</ul>';
			}
		}

		status_header( $status_code );
		nocache_headers();

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( $title ) . '</title>';
		echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#f1f1f1;color:#222;margin:0;padding:32px;}main{max-width:720px;margin:0 auto;background:#fff;border:1px solid #dcdcde;padding:32px;box-shadow:0 1px 2px rgba(0,0,0,.04);}h1{margin:0 0 16px;font-size:28px;}p{line-height:1.6;}ul{margin:16px 0 0 20px;}a{color:#2271b1;}</style>';
		echo '</head><body><main>';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		echo '<p>' . esc_html( $message ) . '</p>';
		echo wp_kses_post( $link_markup );
		echo '</main></body></html>';
		exit;
	}
}
