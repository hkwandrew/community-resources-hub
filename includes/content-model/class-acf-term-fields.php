<?php
/**
 * Plugin-owned ACF opportunity-type term fields.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin-owned ACF field group for opportunity-type display config.
 */
final class AcfTermFields {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'acf/init', array( $this, 'register_field_group' ) );
	}

	/**
	 * Register the local ACF term field group when ACF is available.
	 *
	 * @return void
	 */
	public function register_field_group() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( Schema::opportunity_type_field_group() );
	}
}
