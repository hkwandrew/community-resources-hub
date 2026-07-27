<?php
/**
 * Plugin Name: Community Resources Hub
 * Plugin URI: https://watersmeet.org/
 * Description: Community resources and BCI functionality for Waters Meet.
 * Version: 0.1.8
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Waters Meet
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: community-resources-hub
 * Domain Path: /languages
 *
 * @package CommunityResourcesHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMMUNITY_RESOURCES_HUB_VERSION', '0.1.8' );
define( 'COMMUNITY_RESOURCES_HUB_FILE', __FILE__ );
define( 'COMMUNITY_RESOURCES_HUB_DIR', plugin_dir_path( __FILE__ ) );
define( 'COMMUNITY_RESOURCES_HUB_URL', plugin_dir_url( __FILE__ ) );
define( 'COMMUNITY_RESOURCES_HUB_BASENAME', plugin_basename( __FILE__ ) );

require_once COMMUNITY_RESOURCES_HUB_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( \WatersMeet\CommunityResourcesHub\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WatersMeet\CommunityResourcesHub\Plugin::class, 'deactivate' ) );
register_uninstall_hook( __FILE__, array( \WatersMeet\CommunityResourcesHub\Plugin::class, 'uninstall' ) );

$plugin = new \WatersMeet\CommunityResourcesHub\Plugin( COMMUNITY_RESOURCES_HUB_FILE );
$plugin->register();
