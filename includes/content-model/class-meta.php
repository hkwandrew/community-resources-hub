<?php
/**
 * Plugin-owned BCI post-meta registration.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the BCI post-meta schema in PHP.
 */
final class Meta {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_meta' ), 5 );
	}

	/**
	 * Register member and opportunity meta keys.
	 *
	 * @return void
	 */
	public function register_post_meta() {
		foreach ( Schema::member_meta_definitions() as $meta_key => $args ) {
			register_post_meta( Schema::MEMBER_POST_TYPE, $meta_key, $args );
		}

		foreach ( Schema::opportunity_meta_definitions() as $meta_key => $args ) {
			register_post_meta( Schema::OPPORTUNITY_POST_TYPE, $meta_key, $args );
		}
	}
}
