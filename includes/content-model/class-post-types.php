<?php
/**
 * Plugin-owned BCI post-type registration.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the BCI post types in PHP.
 */
final class PostTypes {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_post_types' ), 4 );
	}

	/**
	 * Register the BCI post types.
	 *
	 * @return void
	 */
	public function register_post_types() {
		if ( ! post_type_exists( Schema::MEMBER_POST_TYPE ) ) {
			register_post_type( Schema::MEMBER_POST_TYPE, Schema::member_post_type_args() );
		}

		if ( ! post_type_exists( Schema::OPPORTUNITY_POST_TYPE ) ) {
			register_post_type( Schema::OPPORTUNITY_POST_TYPE, Schema::opportunity_post_type_args() );
		}
	}
}
