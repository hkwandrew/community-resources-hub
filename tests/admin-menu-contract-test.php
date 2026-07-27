<?php
/**
 * Smoke tests for plugin-owned wp-admin menu ownership.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

$GLOBALS['crh_admin_submenus'] = array();

if ( ! function_exists( 'add_submenu_page' ) ) {
	function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
		$GLOBALS['crh_admin_submenus'][] = array(
			'parent_slug' => $parent_slug,
			'page_title'  => $page_title,
			'menu_title'  => $menu_title,
			'capability'  => $capability,
			'menu_slug'   => $menu_slug,
			'callback'    => $callback,
			'position'    => $position,
		);

		return $menu_slug;
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-acf-settings.php';

$options_page_args = WatersMeet\CommunityResourcesHub\Config\SettingsSchema::options_page_args();

if ( 'Community Resources Hub' !== ( $options_page_args['page_title'] ?? '' ) ) {
	fwrite( STDERR, "Expected the admin page title to be Community Resources Hub.\n" );
	exit( 1 );
}

if ( 'Community Resources Hub' !== ( $options_page_args['menu_title'] ?? '' ) ) {
	fwrite( STDERR, "Expected the top-level admin menu title to be Community Resources Hub.\n" );
	exit( 1 );
}

if ( WatersMeet\CommunityResourcesHub\Config\SettingsSchema::OPTIONS_PAGE_SLUG !== ( $options_page_args['menu_slug'] ?? '' ) ) {
	fwrite( STDERR, "Expected the admin menu slug to stay stable.\n" );
	exit( 1 );
}

if ( false !== ( $options_page_args['redirect'] ?? null ) ) {
	fwrite( STDERR, "Expected the Hub settings page not to redirect away from its own settings.\n" );
	exit( 1 );
}

$field_group = WatersMeet\CommunityResourcesHub\Config\SettingsSchema::field_group();

if ( 'Community Resources Hub Settings' !== ( $field_group['title'] ?? '' ) ) {
	fwrite( STDERR, "Expected the settings field group title to use the Community Resources Hub name.\n" );
	exit( 1 );
}

$member_post_type_args      = WatersMeet\CommunityResourcesHub\ContentModel\Schema::member_post_type_args();
$opportunity_post_type_args = WatersMeet\CommunityResourcesHub\ContentModel\Schema::opportunity_post_type_args();

if ( WatersMeet\CommunityResourcesHub\Config\SettingsSchema::OPTIONS_PAGE_SLUG !== ( $member_post_type_args['show_in_menu'] ?? null ) ) {
	fwrite( STDERR, "Expected BCI Members to be registered under the Community Resources Hub menu.\n" );
	exit( 1 );
}

if ( WatersMeet\CommunityResourcesHub\Config\SettingsSchema::OPTIONS_PAGE_SLUG !== ( $opportunity_post_type_args['show_in_menu'] ?? null ) ) {
	fwrite( STDERR, "Expected BCI Opportunities to be registered under the Community Resources Hub menu.\n" );
	exit( 1 );
}

( new WatersMeet\CommunityResourcesHub\Config\AcfSettings() )->register_admin_submenus();

$submenus_by_slug = array();

foreach ( $GLOBALS['crh_admin_submenus'] as $submenu ) {
	$submenus_by_slug[ $submenu['menu_slug'] ] = $submenu;
}

foreach (
	array(
		WatersMeet\CommunityResourcesHub\Config\SettingsSchema::OPTIONS_PAGE_SLUG => array(
			'menu_title' => 'Settings',
			'capability' => WatersMeet\CommunityResourcesHub\Config\SettingsSchema::CAPABILITY,
			'position'   => 0,
		),
		'post-new.php?post_type=bci_member' => array(
			'menu_title' => 'Add New BCI Member',
			'capability' => 'edit_posts',
			'position'   => 2,
		),
		'post-new.php?post_type=bci_opportunity' => array(
			'menu_title' => 'Add New BCI Opportunity',
			'capability' => 'edit_posts',
			'position'   => 4,
		),
		'edit-tags.php?taxonomy=opportunity-type&post_type=bci_opportunity' => array(
			'menu_title' => 'Opportunity Types',
			'capability' => 'manage_categories',
			'position'   => 5,
		),
		'edit-tags.php?taxonomy=opportunity-tag&post_type=bci_opportunity' => array(
			'menu_title' => 'Opportunity Tags',
			'capability' => 'manage_categories',
			'position'   => 6,
		),
	) as $menu_slug => $expected
) {
	if ( empty( $submenus_by_slug[ $menu_slug ] ) ) {
		fwrite( STDERR, "Expected {$menu_slug} to be registered as a Hub submenu.\n" );
		exit( 1 );
	}

	if ( WatersMeet\CommunityResourcesHub\Config\SettingsSchema::OPTIONS_PAGE_SLUG !== ( $submenus_by_slug[ $menu_slug ]['parent_slug'] ?? '' ) ) {
		fwrite( STDERR, "Expected {$menu_slug} to be registered under the Community Resources Hub parent menu.\n" );
		exit( 1 );
	}

	foreach ( $expected as $key => $value ) {
		if ( $value !== ( $submenus_by_slug[ $menu_slug ][ $key ] ?? null ) ) {
			fwrite( STDERR, "Expected {$menu_slug} {$key} to be {$value}.\n" );
			exit( 1 );
		}
	}
}
