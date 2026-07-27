<?php
/**
 * Central plugin-owned frontend asset registry.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Assets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers shared classic-theme frontend assets from the plugin.
 */
final class Registry {

	/**
	 * Opportunity Hub frontend style handle.
	 */
	const OPPORTUNITY_HUB_STYLE = 'community-resources-hub-opportunity-hub-style';

	/**
	 * Opportunity Hub frontend module handle.
	 */
	const OPPORTUNITY_HUB_MODULE = 'community-resources-hub-opportunity-hub-module';

	/**
	 * Opportunity Hub frontend fallback script handle.
	 */
	const OPPORTUNITY_HUB_SCRIPT_FALLBACK = 'community-resources-hub-opportunity-hub-module-fallback';

	/**
	 * Prevent duplicate registration.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( __CLASS__, 'register_asset_handles' ), 1 );
	}

	/**
	 * Register all plugin-owned classic frontend asset handles.
	 *
	 * @return void
	 */
	public static function register_asset_handles() {
		if ( self::$registered ) {
			return;
		}

		$plugin_dir = defined( 'COMMUNITY_RESOURCES_HUB_DIR' )
			? \COMMUNITY_RESOURCES_HUB_DIR
			: dirname( __DIR__, 2 ) . '/';
		$plugin_url = defined( 'COMMUNITY_RESOURCES_HUB_URL' )
			? \COMMUNITY_RESOURCES_HUB_URL
			: '';
		$build_dir = $plugin_dir . 'build/';
		$build_url = $plugin_url . 'build/';

		self::register_frontend_style(
			self::OPPORTUNITY_HUB_STYLE,
			$build_url . 'opportunity-hub/style.css',
			$build_dir . 'opportunity-hub/style.css'
		);
		self::register_opportunity_hub_module(
			$build_dir . 'opportunity-hub/view-module.asset.php',
			$build_url . 'opportunity-hub/view-module.js',
			$build_dir . 'opportunity-hub/view.asset.php',
			$build_url . 'opportunity-hub/view.js'
		);

		self::register_frontend_script(
			'community-resources-hub-member-directory',
			$build_dir . 'member-directory/view.asset.php',
			$build_url . 'member-directory/view.js'
		);
		self::register_frontend_style(
			'community-resources-hub-member-directory',
			$build_url . 'member-directory/style.css',
			$build_dir . 'member-directory/style.css'
		);

		self::register_frontend_script(
			'community-resources-hub-video-slider',
			$build_dir . 'video-slider/view.asset.php',
			$build_url . 'video-slider/view.js'
		);
		self::register_frontend_style(
			'community-resources-hub-video-slider',
			$build_url . 'video-slider/style.css',
			$build_dir . 'video-slider/style.css'
		);

		self::register_frontend_style(
			'community-resources-hub-newsletter-archives',
			$build_url . 'newsletter-archives/style.css',
			$build_dir . 'newsletter-archives/style.css'
		);

		self::register_frontend_script(
			'community-resources-hub-calendar-runtime',
			$build_dir . 'calendar/runtime.asset.php',
			$build_url . 'calendar/runtime.js',
			$build_dir . 'calendar/runtime.js'
		);
		self::register_frontend_style(
			'community-resources-hub-calendar-runtime',
			$build_url . 'opportunity-hub/style.css',
			$build_dir . 'opportunity-hub/style.css'
		);

		self::$registered = true;
	}

	/**
	 * Enqueue opportunity hub assets for legacy delegated surfaces.
	 *
	 * @return void
	 */
	public static function enqueue_opportunity_hub_assets() {
		self::register_asset_handles();

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( self::OPPORTUNITY_HUB_STYLE );
		}

		if ( function_exists( 'wp_add_inline_style' ) ) {
			wp_add_inline_style(
				self::OPPORTUNITY_HUB_STYLE,
				'.wm-bci-opportunity-modal__action[hidden]{display:none;}'
				. '.opportunity-hub-container+.content-block.bg-color-theme-1.has-top-wave .wave.top{margin-top:-2px;}'
				. '.opportunity-hub-container+.content-block.bg-color-theme-1.has-top-wave .wave.top svg{margin-bottom:-2px!important;}'
			);
		}

		if ( function_exists( 'wp_enqueue_script_module' ) ) {
			wp_enqueue_script_module( self::OPPORTUNITY_HUB_MODULE );
			return;
		}

		if ( function_exists( 'wp_enqueue_script' ) ) {
			wp_enqueue_script( self::OPPORTUNITY_HUB_SCRIPT_FALLBACK );
		}
	}

	/**
	 * Register one frontend style handle.
	 *
	 * @param string $handle Handle.
	 * @param string $src Style source URL.
	 * @param string $path Absolute asset path for cache busting.
	 * @return void
	 */
	private static function register_frontend_style( $handle, $src, $path = '' ) {
		if ( ! function_exists( 'wp_register_style' ) ) {
			return;
		}

		wp_register_style(
			$handle,
			$src,
			array(),
			self::asset_version( $path )
		);
	}

	/**
	 * Version local assets by filemtime so rebuilt CSS invalidates browser cache.
	 *
	 * @param string $path Absolute asset path.
	 * @return string
	 */
	private static function asset_version( $path ) {
		$path = (string) $path;

		if ( '' !== $path && is_readable( $path ) ) {
			$mtime = filemtime( $path );

			if ( false !== $mtime ) {
				return (string) $mtime;
			}
		}

		return \COMMUNITY_RESOURCES_HUB_VERSION;
	}

	/**
	 * Register one frontend script handle.
	 *
	 * @param string $handle Handle.
	 * @param string $asset_file Asset metadata file or script source URL.
	 * @param string $src Script source URL.
	 * @return void
	 */
	private static function register_frontend_script( $handle, $asset_file, $src = null, $path = '' ) {
		if ( ! function_exists( 'wp_register_script' ) ) {
			return;
		}

		if ( null === $src ) {
			$src   = $asset_file;
			$asset = array();
		} else {
			$asset = self::asset_metadata( $asset_file );
		}

		wp_register_script(
			$handle,
			$src,
			$asset['dependencies'] ?? array(),
			'' !== (string) $path
				? self::asset_version( $path )
				: ( $asset['version'] ?? \COMMUNITY_RESOURCES_HUB_VERSION ),
			true
		);

		if ( function_exists( 'wp_script_add_data' ) ) {
			wp_script_add_data( $handle, 'strategy', 'defer' );
		}
	}

	/**
	 * Register the opportunity hub module and its fallback script.
	 *
	 * @param string $module_asset_file Module asset metadata file.
	 * @param string $module_src Module source URL.
	 * @param string $fallback_asset_file Fallback script asset metadata file.
	 * @param string $fallback_src Fallback script URL.
	 * @return void
	 */
	private static function register_opportunity_hub_module( $module_asset_file, $module_src, $fallback_asset_file, $fallback_src ) {
		if ( function_exists( 'wp_register_script_module' ) ) {
			$asset = self::asset_metadata( $module_asset_file );

			wp_register_script_module(
				self::OPPORTUNITY_HUB_MODULE,
				$module_src,
				$asset['dependencies'] ?? array(),
				$asset['version'] ?? \COMMUNITY_RESOURCES_HUB_VERSION
			);
		}

		self::register_frontend_script(
			self::OPPORTUNITY_HUB_SCRIPT_FALLBACK,
			$fallback_asset_file,
			$fallback_src
		);
	}

	/**
	 * Read dependency-extraction metadata.
	 *
	 * @param string $asset_file Asset metadata file.
	 * @return array<string,mixed>
	 */
	private static function asset_metadata( $asset_file ) {
		return is_file( $asset_file ) ? require $asset_file : array();
	}
}
