<?php
/**
 * Smoke tests for plugin-owned ACF post field groups.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

$GLOBALS['crh_acf_groups'] = array();

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	function acf_add_local_field_group( array $group ) {
		$GLOBALS['crh_acf_groups'][] = $group;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['crh_post_meta'][ $post_id ][ $key ] ?? null;
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-acf-post-fields.php';

$fields = new WatersMeet\CommunityResourcesHub\ContentModel\AcfPostFields();
$fields->register_field_groups();

if ( 2 !== count( $GLOBALS['crh_acf_groups'] ) ) {
	fwrite( STDERR, "Expected exactly two ACF post field groups.\n" );
	exit( 1 );
}

$groups_by_key = array();

foreach ( $GLOBALS['crh_acf_groups'] as $group ) {
	$groups_by_key[ $group['key'] ] = $group;
}

foreach ( array( 'group_crh_bci_member_fields', 'group_crh_bci_opportunity_fields' ) as $group_key ) {
	if ( empty( $groups_by_key[ $group_key ] ) ) {
		fwrite( STDERR, "Missing expected field group {$group_key}.\n" );
		exit( 1 );
	}
}

$member_group = $groups_by_key['group_crh_bci_member_fields'];
$member_location = $member_group['location'][0][0] ?? array();

if ( 'post_type' !== ( $member_location['param'] ?? '' ) || 'bci_member' !== ( $member_location['value'] ?? '' ) ) {
	fwrite( STDERR, "Expected BCI member field group to target the bci_member post type.\n" );
	exit( 1 );
}

$opportunity_group = $groups_by_key['group_crh_bci_opportunity_fields'];
$opportunity_location = $opportunity_group['location'][0][0] ?? array();

if ( 'post_type' !== ( $opportunity_location['param'] ?? '' ) || 'bci_opportunity' !== ( $opportunity_location['value'] ?? '' ) ) {
	fwrite( STDERR, "Expected BCI opportunity field group to target the bci_opportunity post type.\n" );
	exit( 1 );
}

$member_field_names = array_column( $member_group['fields'], 'name' );
$member_fields_by_name = array();

foreach ( $member_group['fields'] as $field ) {
	if ( isset( $field['name'] ) ) {
		$member_fields_by_name[ $field['name'] ] = $field;
	}
}

foreach ( array( 'wm_bci_member_logo_url', 'wm_bci_member_hero_background_color', 'wm_bci_member_social_links', 'wm_bci_member_programs', 'wm_bci_member_attachments' ) as $field_name ) {
	if ( ! in_array( $field_name, $member_field_names, true ) ) {
		fwrite( STDERR, "Missing expected member field {$field_name}.\n" );
		exit( 1 );
	}
}

$hero_background_field = $member_fields_by_name['wm_bci_member_hero_background_color'] ?? array();

if ( 'color_picker' !== ( $hero_background_field['type'] ?? '' ) ) {
	fwrite( STDERR, "Expected member hero background color to use an ACF color picker.\n" );
	exit( 1 );
}

if ( 1 !== (int) ( $hero_background_field['show_custom_palette'] ?? 0 ) || 0 !== (int) ( $hero_background_field['show_color_wheel'] ?? 1 ) ) {
	fwrite( STDERR, "Expected member hero background color to use only the curated palette.\n" );
	exit( 1 );
}

$hero_background_palette = strtolower( str_replace( ' ', '', (string) ( $hero_background_field['palette_colors'] ?? '' ) ) );

foreach ( array( '#133358', '#5c6e7a', '#004966', '#d9a242', '#c2385a', '#520066', '#418359' ) as $expected_color ) {
	if ( false === strpos( $hero_background_palette, strtolower( $expected_color ) ) ) {
		fwrite( STDERR, "Expected member hero background palette to include {$expected_color}.\n" );
		exit( 1 );
	}
}

$opportunity_field_names = array_column( $opportunity_group['fields'], 'name' );

foreach ( $opportunity_group['fields'] as $field ) {
	if ( isset( $field['name'] ) ) {
		$opportunity_fields_by_name[ $field['name'] ] = $field;
	}
}

foreach ( array( 'wm_bci_approval_status', 'wm_bci_start_date', 'wm_bci_file_upload' ) as $field_name ) {
	if ( ! in_array( $field_name, $opportunity_field_names, true ) ) {
		fwrite( STDERR, "Missing expected opportunity field {$field_name}.\n" );
		exit( 1 );
	}
}

if ( in_array( 'wm_bci_opportunity_type', $opportunity_field_names, true ) ) {
	fwrite( STDERR, "Expected Opportunity Type to be removed from the ACF opportunity field group in favor of the dedicated editor metabox.\n" );
	exit( 1 );
}

$member_meta_definitions = WatersMeet\CommunityResourcesHub\ContentModel\Schema::member_meta_definitions();

if ( 'string' !== ( $member_meta_definitions['wm_bci_member_aliases']['type'] ?? '' ) ) {
	fwrite( STDERR, "Expected member aliases meta to match the textarea string field contract.\n" );
	exit( 1 );
}

$hero_background_meta = $member_meta_definitions['wm_bci_member_hero_background_color'] ?? array();

if ( 'string' !== ( $hero_background_meta['type'] ?? '' ) || ! is_callable( $hero_background_meta['sanitize_callback'] ?? null ) ) {
	fwrite( STDERR, "Expected member hero background color meta to register a sanitized string contract.\n" );
	exit( 1 );
}

if ( '#133358' !== call_user_func( $hero_background_meta['sanitize_callback'], '#133358' ) ) {
	fwrite( STDERR, "Expected member hero background color sanitizer to preserve valid hex colors.\n" );
	exit( 1 );
}

if ( '' !== call_user_func( $hero_background_meta['sanitize_callback'], 'background:url(https://example.test/x)' ) ) {
	fwrite( STDERR, "Expected member hero background color sanitizer to reject non-hex CSS values.\n" );
	exit( 1 );
}

$GLOBALS['crh_post_meta'][123] = array(
	'wm_bci_member_aliases' => array(
		'AICC',
		'American Indian Community Center',
	),
);

$normalized_aliases = $fields->normalize_member_acf_value(
	null,
	123,
	array(
		'name' => 'wm_bci_member_aliases',
	)
);

if ( "AICC\nAmerican Indian Community Center" !== $normalized_aliases ) {
	fwrite( STDERR, "Expected legacy array aliases to normalize to textarea lines before ACF renders the field.\n" );
	exit( 1 );
}

$GLOBALS['crh_post_meta'][124] = array(
	'wm_bci_member_aliases' => 'AICC',
);

if ( null !== $fields->normalize_member_acf_value( null, 124, array( 'name' => 'wm_bci_member_aliases' ) ) ) {
	fwrite( STDERR, "Expected string aliases to stay on ACF's normal load path.\n" );
	exit( 1 );
}

$GLOBALS['crh_post_meta'][125] = array(
	'wm_bci_member_social_links' => array(
		array(
			'social_platform' => 'instagram | Instagram',
			'url'             => 'https://example.com/instagram',
			'label'           => '@example',
		),
		array(
			'platform' => 'linked-in | LinkedIn',
			'url'      => 'https://example.com/linkedin',
			'label'    => 'Example',
		),
		array(
			'social_platform' => 'youtube|YouTube',
			'url'             => '@aiccspokane',
			'label'           => '',
		),
		array(
			'social_platform' => 'facebook|Facebook',
			'url'             => 'http://@indiancenter610',
			'label'           => '',
		),
		array(
			'social_platform' => '',
			'url'             => 'https://www.instagram.com/catspokane/',
			'label'           => '@catspokane',
		),
		array(
			'social_platform' => '',
			'url'             => '@unknownhandle',
			'label'           => '@unknownhandle',
		),
	),
);

$normalized_social_links = $fields->normalize_member_acf_value(
	null,
	125,
	array(
		'name'       => 'wm_bci_member_social_links',
		'sub_fields' => array(
			array(
				'key'  => 'field_crh_bci_member_social_platform',
				'name' => 'social_platform',
			),
			array(
				'key'  => 'field_crh_bci_member_social_url',
				'name' => 'url',
			),
			array(
				'key'  => 'field_crh_bci_member_social_label',
				'name' => 'label',
			),
		),
	)
);

$expected_social_links = array(
	array(
		'field_crh_bci_member_social_platform' => 'instagram|Instagram',
		'field_crh_bci_member_social_url'      => 'https://example.com/instagram',
		'field_crh_bci_member_social_label'    => '@example',
	),
	array(
		'field_crh_bci_member_social_platform' => 'linkedin|LinkedIn',
		'field_crh_bci_member_social_url'      => 'https://example.com/linkedin',
		'field_crh_bci_member_social_label'    => 'Example',
	),
	array(
		'field_crh_bci_member_social_platform' => 'youtube|YouTube',
		'field_crh_bci_member_social_url'      => 'https://www.youtube.com/@aiccspokane',
		'field_crh_bci_member_social_label'    => '@aiccspokane',
	),
	array(
		'field_crh_bci_member_social_platform' => 'facebook|Facebook',
		'field_crh_bci_member_social_url'      => 'https://www.facebook.com/indiancenter610/',
		'field_crh_bci_member_social_label'    => '@indiancenter610',
	),
	array(
		'field_crh_bci_member_social_platform' => 'instagram|Instagram',
		'field_crh_bci_member_social_url'      => 'https://www.instagram.com/catspokane/',
		'field_crh_bci_member_social_label'    => '@catspokane',
	),
);

if ( $expected_social_links !== $normalized_social_links ) {
	fwrite( STDERR, "Expected legacy social-link arrays to normalize to current ACF repeater rows.\n" );
	exit( 1 );
}

echo "ACF post field group smoke test passed.\n";
