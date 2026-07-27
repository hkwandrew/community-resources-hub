<?php
/**
 * Plugin-owned newsletter archives frontend assets.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Assets\Registry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and enqueues shared newsletter archives frontend assets.
 */
final class NewsletterArchivesAssets {

	/**
	 * Shared asset handle.
	 */
	const HANDLE = 'community-resources-hub-newsletter-archives';

	/**
	 * Enqueue the shared newsletter archives assets.
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
