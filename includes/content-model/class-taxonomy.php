<?php
/**
 * Plugin-owned BCI taxonomy registration.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the BCI taxonomy and term meta in PHP.
 */
final class Taxonomy {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_taxonomy' ), 4 );
		add_action( 'init', array( $this, 'register_term_meta' ), 4 );
		add_action( 'init', array( $this, 'maybe_seed_default_terms' ), 5 );
		add_action( 'init', array( $this, 'maybe_sync_existing_opportunity_types' ), 6 );
	}

	/**
	 * Register the plugin-owned opportunity taxonomies.
	 *
	 * @return void
	 */
	public function register_taxonomy() {
		if ( ! taxonomy_exists( Schema::OPPORTUNITY_TYPE_TAXONOMY ) ) {
			register_taxonomy(
				Schema::OPPORTUNITY_TYPE_TAXONOMY,
				array( Schema::OPPORTUNITY_POST_TYPE ),
				Schema::opportunity_type_taxonomy_args()
			);
		}

		if ( ! taxonomy_exists( Schema::OPPORTUNITY_TAG_TAXONOMY ) ) {
			register_taxonomy(
				Schema::OPPORTUNITY_TAG_TAXONOMY,
				array( Schema::OPPORTUNITY_POST_TYPE ),
				Schema::opportunity_tag_taxonomy_args()
			);
		}
	}

	/**
	 * Register opportunity-type term meta.
	 *
	 * @return void
	 */
	public function register_term_meta() {
		foreach ( Schema::opportunity_type_term_meta_definitions() as $meta_key => $args ) {
			register_term_meta( Schema::OPPORTUNITY_TYPE_TAXONOMY, $meta_key, $args );
		}
	}

	/**
	 * Seed the plugin's default opportunity taxonomy terms when they are missing.
	 *
	 * @return void
	 */
	public function maybe_seed_default_terms() {
		if ( ! function_exists( 'term_exists' ) || ! function_exists( 'wp_insert_term' ) ) {
			return;
		}

		$this->seed_terms( Schema::OPPORTUNITY_TYPE_TAXONOMY, Schema::default_opportunity_types(), true );
	}

	/**
	 * Ensure the default opportunity tags during an explicit write operation.
	 *
	 * Normal init intentionally does not create these terms so migration dry runs
	 * remain read-only.
	 *
	 * @return void
	 */
	public function ensure_default_opportunity_tags() {
		if ( ! function_exists( 'term_exists' ) || ! function_exists( 'wp_insert_term' ) ) {
			return;
		}

		$this->seed_terms( Schema::OPPORTUNITY_TAG_TAXONOMY, Schema::default_opportunity_tags(), false );
	}

	/**
	 * Seed a set of taxonomy terms.
	 *
	 * @param string                   $taxonomy Taxonomy slug.
	 * @param array<int,array<string,mixed>> $definitions Term definitions.
	 * @param bool                     $seed_display_meta Whether display meta belongs to these terms.
	 * @return void
	 */
	private function seed_terms( $taxonomy, array $definitions, $seed_display_meta ) {
		foreach ( $definitions as $definition ) {
			$slug = (string) $definition['slug'];
			$name = (string) $definition['name'];

			$term = term_exists( $slug, $taxonomy );

			if ( ! $term ) {
				$term = term_exists( $name, $taxonomy );
			}

			if ( ! $term ) {
				$term = wp_insert_term(
					$name,
					$taxonomy,
					array(
						'slug' => $slug,
					)
				);
			}

			if ( ( function_exists( 'is_wp_error' ) && is_wp_error( $term ) ) || empty( $term ) ) {
				continue;
			}

			$term_id = is_array( $term )
				? absint( $term['term_id'] ?? $term['term_taxonomy_id'] ?? 0 )
				: absint( $term );

			if ( ! $seed_display_meta || ! $term_id || ! function_exists( 'get_term_meta' ) || ! function_exists( 'update_term_meta' ) ) {
				continue;
			}

			if ( ! $this->term_meta_exists( $term_id, 'alias' ) && '' !== (string) ( $definition['alias'] ?? '' ) ) {
				update_term_meta( $term_id, 'alias', (string) $definition['alias'] );
			}

			if ( '' === trim( (string) get_term_meta( $term_id, 'color', true ) ) && '' !== (string) ( $definition['color'] ?? '' ) ) {
				update_term_meta( $term_id, 'color', (string) $definition['color'] );
			}
		}
	}

	/**
	 * Backfill taxonomy assignments for existing opportunities and normalize legacy numeric type meta.
	 *
	 * @return void
	 */
	public function maybe_sync_existing_opportunity_types() {
		if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post_meta' ) || ! function_exists( 'wp_set_post_terms' ) ) {
			return;
		}

		$normalized_at = function_exists( 'get_option' ) ? get_option( 'community_resources_hub_opportunity_type_sync_completed_at', '' ) : '';

		if ( '' !== trim( (string) $normalized_at ) ) {
			return;
		}

		if ( ! class_exists( '\WatersMeet\CommunityResourcesHub\Config\Config' ) ) {
			require_once dirname( __DIR__ ) . '/config/class-settings-schema.php';
			require_once dirname( __DIR__ ) . '/config/class-config.php';
		}

		$config  = new \WatersMeet\CommunityResourcesHub\Config\Config();
		$post_ids = get_posts(
			array(
				'post_type'      => Schema::OPPORTUNITY_POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( is_array( $post_ids ) ? $post_ids : array() as $post_id ) {
			$post_id = absint( $post_id );

			if ( ! $post_id ) {
				continue;
			}

			$raw_type = trim( (string) get_post_meta( $post_id, Schema::opportunity_field_name( 'opportunity_type' ), true ) );

			if ( '' === $raw_type ) {
				continue;
			}

			$type_config = $config->opportunity_type_config( $raw_type );

			if ( empty( $type_config['term_id'] ) ) {
				continue;
			}

			wp_set_post_terms(
				$post_id,
				array( absint( $type_config['term_id'] ) ),
				Schema::OPPORTUNITY_TYPE_TAXONOMY,
				false
			);

			if ( ctype_digit( $raw_type ) && ! empty( $type_config['name'] ) && function_exists( 'update_post_meta' ) ) {
				update_post_meta(
					$post_id,
					Schema::opportunity_field_name( 'opportunity_type' ),
					(string) $type_config['name']
				);
			}
		}

		if ( function_exists( 'update_option' ) ) {
			update_option( 'community_resources_hub_opportunity_type_sync_completed_at', gmdate( 'c' ), false );
		}
	}

	/**
	 * Whether the term meta key already exists, even when the stored value is blank.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private function term_meta_exists( $term_id, $meta_key ) {
		return $term_id
			&& function_exists( 'metadata_exists' )
			&& metadata_exists( 'term', $term_id, $meta_key );
	}
}
