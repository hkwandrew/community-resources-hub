<?php
/**
 * Plugin-owned calendar runtime asset registration and enqueueing.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Calendar;

use WatersMeet\CommunityResourcesHub\Assets\Registry;
use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and enqueues shared calendar runtime assets.
 */
final class RuntimeAssets {

	/**
	 * Script handle.
	 */
	const SCRIPT_HANDLE = 'community-resources-hub-calendar-runtime';

	/**
	 * Style handle.
	 */
	const STYLE_HANDLE = 'community-resources-hub-calendar-runtime';

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Prevent duplicate registration.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Constructor.
	 *
	 * @param Config $config Workflow config.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_resources_page_assets' ) );
	}

	/**
	 * Register the shared runtime assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		if ( ! class_exists( Registry::class ) ) {
			require_once (
				defined( 'COMMUNITY_RESOURCES_HUB_DIR' )
					? \COMMUNITY_RESOURCES_HUB_DIR . 'includes/assets/class-registry.php'
					: dirname( __DIR__, 2 ) . '/includes/assets/class-registry.php'
			);
		}

		Registry::register_asset_handles();
	}

	/**
	 * Enqueue the shared runtime assets for legacy resources pages.
	 *
	 * @return void
	 */
	public function enqueue_resources_page_assets() {
		if ( ! $this->is_resources_page() ) {
			return;
		}

		if ( ! class_exists( Registry::class ) ) {
			require_once (
				defined( 'COMMUNITY_RESOURCES_HUB_DIR' )
					? \COMMUNITY_RESOURCES_HUB_DIR . 'includes/assets/class-registry.php'
					: dirname( __DIR__, 2 ) . '/includes/assets/class-registry.php'
			);
		}

		Registry::enqueue_opportunity_hub_assets();
		self::enqueue();
	}

	/**
	 * Enqueue the shared runtime assets.
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
			wp_enqueue_script( self::SCRIPT_HANDLE );
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}

	/**
	 * Register the script and style handles if WordPress supports it.
	 *
	 * @return void
	 */
	private static function register_asset_handles() {
		if ( ! class_exists( Registry::class ) ) {
			require_once (
				defined( 'COMMUNITY_RESOURCES_HUB_DIR' )
					? \COMMUNITY_RESOURCES_HUB_DIR . 'includes/assets/class-registry.php'
					: dirname( __DIR__, 2 ) . '/includes/assets/class-registry.php'
			);
		}

		Registry::register_asset_handles();
	}

	/**
	 * Whether the current request is the configured resources page.
	 *
	 * @return bool
	 */
	private function is_resources_page() {
		$slug = $this->config->calendar_page_slug();

		if ( '' === $slug ) {
			return false;
		}

		if ( function_exists( 'is_page' ) && is_page( $slug ) ) {
			return true;
		}

		global $post;

		return is_object( $post )
			&& isset( $post->post_name )
			&& $slug === (string) $post->post_name;
	}
}
