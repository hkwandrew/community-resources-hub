<?php
/**
 * Plugin-owned member directory frontend assets.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Assets\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and enqueues shared member-directory frontend assets.
 */
final class MemberDirectoryAssets {

	/**
	 * Shared asset handle.
	 */
	const HANDLE = 'community-resources-hub-member-directory';

	/**
	 * Enqueue the shared member-directory assets.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! class_exists( Registry::class ) ) {
			require_once (
				defined( 'COMMUNITY_RESOURCES_HUB_DIR' )
					? \COMMUNITY_RESOURCES_HUB_DIR . 'includes/assets/class-registry.php'
					: dirname( __DIR__, 2 ) . '/includes/assets/class-registry.php'
			);
		}

		Registry::register_asset_handles();

		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( self::HANDLE );
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}

	/**
	 * Register asset handles when available.
	 *
	 * @return void
	 */
	public static function register_asset_handles() {
		if ( ! class_exists( Registry::class ) ) {
			require_once (
				defined( 'COMMUNITY_RESOURCES_HUB_DIR' )
					? \COMMUNITY_RESOURCES_HUB_DIR . 'includes/assets/class-registry.php'
					: dirname( __DIR__, 2 ) . '/includes/assets/class-registry.php'
			);
		}

		Registry::register_asset_handles();
	}
}
