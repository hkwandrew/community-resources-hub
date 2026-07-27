<?php
/**
 * BCI opportunity persistence.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\ContentModel\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and updates plugin-owned BCI opportunity posts.
 */
final class OpportunityRepository {

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Field accessors.
	 *
	 * @var FieldAccessor
	 */
	private $fields;

	public function __construct( Config $config ) {
		$this->config = $config;
		$this->fields = new FieldAccessor( $config );
	}

	/**
	 * Create or update an opportunity from a Gravity Forms entry.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param string             $approval_status Approval status.
	 * @return int Post ID, or 0 on failure.
	 */
	public function upsert_from_entry( array $entry, $approval_status ) {
		$entry_id = absint( $this->fields->value( $entry, 'id' ) );

		if ( ! $entry_id ) {
			return 0;
		}

		$post_id = $this->find_by_source_entry_id( $entry_id );
		$postarr = array(
			'post_type'    => $this->config->opportunity_post_type(),
			'post_status'  => $this->post_status_for_approval( $approval_status ),
			'post_title'   => sanitize_text_field( $this->fields->title( $entry ) ),
			'post_content' => wp_kses_post( trim( (string) $this->fields->value( $entry, $this->config->field( 'description' ) ) ) ),
		);

		if ( $post_id ) {
			$postarr['ID'] = $post_id;
			$result = wp_update_post( $postarr, true );
		} else {
			$result = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $result ) || ! $result ) {
			return 0;
		}

		$post_id = (int) $result;
		$this->persist_entry_meta( $post_id, $entry, $approval_status );

		return $post_id;
	}

	/**
	 * Find an opportunity by source GF entry ID.
	 */
	public function find_by_source_entry_id( $entry_id ) {
		$posts = get_posts(
			array(
				'post_type'      => $this->config->opportunity_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => $this->config->opportunity_field_name( 'source_entry_id' ),
				'meta_value'     => absint( $entry_id ),
				'orderby'        => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
			)
		);

		return empty( $posts ) ? 0 : absint( $posts[0] );
	}

	/**
	 * Update a mirrored opportunity post status to match its approval state.
	 */
	public function update_post_status_for_approval( $post_id, $approval_status ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $this->post_status_for_approval( $approval_status ),
			)
		);
	}

	/**
	 * WordPress post status for a BCI approval status.
	 */
	public function post_status_for_approval( $approval_status ) {
		if ( 'Approved' === trim( (string) $approval_status ) ) {
			return 'publish';
		}

		if ( 'Pending' === trim( (string) $approval_status ) ) {
			return 'pending';
		}

		return 'draft';
	}

	/**
	 * Persist mapped entry meta.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	private function persist_entry_meta( $post_id, array $entry, $approval_status ) {
		$entry_id    = absint( $this->fields->value( $entry, 'id' ) );
		$type        = $this->fields->opportunity_type( $entry );
		$type_config = $this->config->opportunity_type_config( $type );
		$type_value  = '' !== trim( (string) ( $type_config['name'] ?? '' ) ) ? (string) $type_config['name'] : $type;

		$meta = array(
			$this->config->opportunity_field_name( 'source_entry_id' ) => $entry_id,
			$this->config->opportunity_field_name( 'approval_status' ) => $approval_status,
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
		);

		if ( 'Approved' === $approval_status && '' === (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'approved_at' ), true ) ) {
			$meta[ $this->config->opportunity_field_name( 'approved_at' ) ] = gmdate( 'c' );
		} elseif ( 'Approved' !== $approval_status ) {
			delete_post_meta( $post_id, $this->config->opportunity_field_name( 'approved_at' ) );
		}

		foreach ( $meta as $meta_key => $value ) {
			update_post_meta( $post_id, $meta_key, $value );
		}

		$this->assign_opportunity_type_term( $post_id, $type_value );
		$this->sync_bci_update_tag( $post_id, $this->fields->is_bci_update( $entry ) );
	}

	/**
	 * Assign the matching opportunity-type term when one resolves.
	 */
	private function assign_opportunity_type_term( $post_id, $raw_type ) {
		$config = $this->config->opportunity_type_config( $raw_type );

		if ( empty( $config['term_id'] ) || ! function_exists( 'wp_set_post_terms' ) ) {
			return;
		}

		wp_set_post_terms(
			$post_id,
			array( absint( $config['term_id'] ) ),
			$this->config->opportunity_type_taxonomy(),
			false
		);
	}

	/**
	 * Add or remove only the BCI Update tag, preserving unrelated tags.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $is_bci_update Whether the entry is a BCI Update.
	 * @return void
	 */
	private function sync_bci_update_tag( $post_id, $is_bci_update ) {
		$taxonomy = $this->config->opportunity_tag_taxonomy();

		if ( $is_bci_update ) {
			if ( function_exists( 'wp_add_object_terms' ) ) {
				wp_add_object_terms( $post_id, Schema::BCI_UPDATE_TAG_SLUG, $taxonomy );
			}

			return;
		}

		if ( function_exists( 'wp_remove_object_terms' ) ) {
			wp_remove_object_terms( $post_id, Schema::BCI_UPDATE_TAG_SLUG, $taxonomy );
		}
	}
}
