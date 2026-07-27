<?php
/**
 * Smoke tests for plugin-owned setup health checks.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'acf_add_options_page' ) ) {
	function acf_add_options_page( array $page ) {
		return $page;
	}
}

if ( ! function_exists( 'acf_add_local_field_group' ) ) {
	function acf_add_local_field_group( array $group ) {
		return $group;
	}
}

if ( ! class_exists( 'GFAPI' ) ) {
	class GFAPI {}
}

if ( ! function_exists( 'gravity_form' ) ) {
	function gravity_form() {
		return '';
	}
}

if ( ! function_exists( 'shortcode_exists' ) ) {
	function shortcode_exists( $tag ) {
		return ! empty( $GLOBALS['crh_shortcodes'][ $tag ] );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] ?? array() )
			? $GLOBALS['crh_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title );
		return trim( (string) $title, '-' );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		$value = str_replace( "\r", '', (string) $value );
		$value = strip_tags( $value );
		$lines = array_map( 'trim', explode( "\n", $value ) );

		return trim( implode( "\n", $lines ) );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return preg_replace( '#<script[^>]*>.*?</script>#is', '', (string) $html );
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/config/class-health-checks.php';

if ( ! in_array( 'options_wm_bci_calendar_shortcode', WatersMeet\CommunityResourcesHub\Config\SettingsSchema::option_names(), true ) ) {
	fwrite( STDERR, "Expected GravityCalendar shortcode to be a plugin-owned option.\n" );
	exit( 1 );
}

foreach (
	array(
		'options_wm_bci_video_slider_eyebrow',
		'options_wm_bci_video_slider_title',
		'options_wm_bci_video_slider_intro',
		'options_wm_bci_video_slider_slides',
	) as $option_name
) {
	if ( ! in_array( $option_name, WatersMeet\CommunityResourcesHub\Config\SettingsSchema::option_names(), true ) ) {
		fwrite( STDERR, "Expected {$option_name} to be a plugin-owned option.\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'wm_bci_video_slider_eyebrow' => 'Spotlight Videos',
		'wm_bci_video_slider_title'   => 'See the BCI Community in action',
	) as $field_name => $expected_default
) {
	if ( $expected_default !== WatersMeet\CommunityResourcesHub\Config\SettingsSchema::default_for( $field_name ) ) {
		fwrite( STDERR, "Expected {$field_name} to default to the latest Spotlight Videos copy.\n" );
		exit( 1 );
	}
}

$default_video_slider_intro = WatersMeet\CommunityResourcesHub\Config\SettingsSchema::default_for( 'wm_bci_video_slider_intro' );

if (
	false === strpos( $default_video_slider_intro, '<strong>Rooted in Community video series</strong>' )
	|| false === strpos( $default_video_slider_intro, 'goes behind the scenes with BCI partner organizations' )
	|| false === strpos( $default_video_slider_intro, "impact they're making across the region." )
) {
	fwrite( STDERR, "Expected the Video Slider intro default to match the latest Rooted in Community wrapper copy.\n" );
	exit( 1 );
}

$GLOBALS['crh_options'] = array(
	'options_wm_bci_video_slider_eyebrow' => '',
	'options_wm_bci_video_slider_title'   => '',
	'options_wm_bci_video_slider_intro'   => '',
);
$video_slider_config = new WatersMeet\CommunityResourcesHub\Config\Config();
$video_slider_context = $video_slider_config->video_slider_context();

if (
	'Spotlight Videos' !== ( $video_slider_context['eyebrow'] ?? '' )
	|| 'See the BCI Community in action' !== ( $video_slider_context['title'] ?? '' )
	|| false === strpos( (string) ( $video_slider_context['intro'] ?? '' ), '<strong>Rooted in Community video series</strong>' )
) {
	fwrite( STDERR, "Expected blank saved Video Slider wrapper options to fall back to the latest schema defaults at runtime.\n" );
	exit( 1 );
}

$field_group  = WatersMeet\CommunityResourcesHub\Config\SettingsSchema::field_group();
$field_names  = array_column( $field_group['fields'], 'name' );
$field_lookup = array();

foreach ( $field_group['fields'] as $field ) {
	if ( isset( $field['name'] ) && '' !== $field['name'] ) {
		$field_lookup[ $field['name'] ] = $field;
	}
}

if ( ! in_array( 'wm_bci_calendar_shortcode', $field_names, true ) ) {
	fwrite( STDERR, "Expected GravityCalendar shortcode to appear in the BCI Hub settings field group.\n" );
	exit( 1 );
}

$video_slider_tab_exists = false;

foreach ( $field_group['fields'] as $field ) {
	if ( 'tab' === ( $field['type'] ?? '' ) && 'Video Slider' === ( $field['label'] ?? '' ) ) {
		$video_slider_tab_exists = true;
		break;
	}
}

if ( ! $video_slider_tab_exists ) {
	fwrite( STDERR, "Expected a Video Slider tab in the BCI Hub settings field group.\n" );
	exit( 1 );
}

$eyebrow_field = $field_lookup['wm_bci_video_slider_eyebrow'] ?? null;
$title_field   = $field_lookup['wm_bci_video_slider_title'] ?? null;
$slides_field = $field_lookup['wm_bci_video_slider_slides'] ?? null;

if ( ! is_array( $eyebrow_field ) || 'Spotlight Videos' !== ( $eyebrow_field['placeholder'] ?? '' ) ) {
	fwrite( STDERR, "Expected the Video Slider eyebrow field placeholder to reflect the Spotlight Videos direction.\n" );
	exit( 1 );
}

if ( ! is_array( $title_field ) || 'See the BCI Community in action' !== ( $title_field['placeholder'] ?? '' ) ) {
	fwrite( STDERR, "Expected the Video Slider title field placeholder to reflect the latest wrapper headline.\n" );
	exit( 1 );
}

if ( ! is_array( $slides_field ) || 'repeater' !== ( $slides_field['type'] ?? '' ) ) {
	fwrite( STDERR, "Expected the BCI Hub Video Slider slides field to be a repeater.\n" );
	exit( 1 );
}

if ( 'Add Spotlight Video' !== ( $slides_field['button_label'] ?? '' ) ) {
	fwrite( STDERR, "Expected the Video Slider repeater button label to match the Spotlight Videos direction.\n" );
	exit( 1 );
}

$slide_subfields = array();

foreach ( $slides_field['sub_fields'] ?? array() as $field ) {
	if ( isset( $field['name'] ) && '' !== $field['name'] ) {
		$slide_subfields[ $field['name'] ] = $field;
	}
}

foreach (
	array(
		'video_id',
		'video_url',
		'thumbnail_id',
		'logo_id',
		'logo_label',
		'slide_eyebrow',
		'slide_title',
		'slide_description',
	) as $subfield_name
) {
	if ( ! isset( $slide_subfields[ $subfield_name ] ) ) {
		fwrite( STDERR, "Expected {$subfield_name} in the BCI Hub Video Slider slides repeater.\n" );
		exit( 1 );
	}
}

if ( 'The Rooted in Community series' !== ( $slide_subfields['slide_eyebrow']['placeholder'] ?? '' ) ) {
	fwrite( STDERR, "Expected the slide eyebrow placeholder to reflect the Rooted in Community series label.\n" );
	exit( 1 );
}

foreach ( array( 'thumbnail_id', 'logo_id' ) as $image_subfield_name ) {
	$image_field = $slide_subfields[ $image_subfield_name ];

	if ( 'image' !== ( $image_field['type'] ?? '' ) || 'id' !== ( $image_field['return_format'] ?? '' ) ) {
		fwrite( STDERR, "Expected {$image_subfield_name} to be an image field returning attachment IDs.\n" );
		exit( 1 );
	}
}

if ( '[gravitycalendar id="7" title="false"]' !== WatersMeet\CommunityResourcesHub\Config\SettingsSchema::sanitize_gravitycalendar_shortcode( '[gravitycalendar id="7" title="false"]' ) ) {
	fwrite( STDERR, "Expected GravityCalendar shortcode sanitizer to preserve valid shortcode format.\n" );
	exit( 1 );
}

if ( '' !== WatersMeet\CommunityResourcesHub\Config\SettingsSchema::sanitize_gravitycalendar_shortcode( '[calendar id="7"]' ) ) {
	fwrite( STDERR, "Expected GravityCalendar shortcode sanitizer to reject non-GravityCalendar shortcode format.\n" );
	exit( 1 );
}

$sanitized_intro = WatersMeet\CommunityResourcesHub\Config\SettingsSchema::sanitize_value(
	'wm_bci_video_slider_intro',
	" <p>Video intro</p><script>alert('x')</script> "
);

if ( false === strpos( $sanitized_intro, '<p>Video intro</p>' ) || false !== strpos( $sanitized_intro, '<script' ) ) {
	fwrite( STDERR, "Expected the Video Slider intro sanitizer to preserve safe markup and strip scripts.\n" );
	exit( 1 );
}

$sanitized_slides = WatersMeet\CommunityResourcesHub\Config\SettingsSchema::sanitize_value(
	'wm_bci_video_slider_slides',
	array(
		array(
			'video_id'          => ' dQw4w9WgXcQ ',
			'video_url'         => ' https://youtu.be/9bZkp7q19f0 ',
			'thumbnail_id'      => '17',
			'logo_id'           => '22',
			'logo_label'        => '  Main Logo  ',
			'slide_eyebrow'     => '  Watch now  ',
			'slide_title'       => '  Spotlight  ',
			'slide_description' => "  First line\nSecond line  ",
		),
		'unexpected',
		array(),
	)
);

if ( ! is_array( $sanitized_slides ) || 1 !== count( $sanitized_slides ) ) {
	fwrite( STDERR, "Expected the Video Slider slides sanitizer to normalize row arrays and discard empty rows.\n" );
	exit( 1 );
}

$sanitized_slide = $sanitized_slides[0];
$expected_slide  = array(
	'video_id'          => 'dQw4w9WgXcQ',
	'video_url'         => 'https://youtu.be/9bZkp7q19f0',
	'thumbnail_id'      => 17,
	'logo_id'           => 22,
	'logo_label'        => 'Main Logo',
	'slide_eyebrow'     => 'Watch now',
	'slide_title'       => 'Spotlight',
	'slide_description' => "First line\nSecond line",
);

if ( $expected_slide !== $sanitized_slide ) {
	fwrite( STDERR, "Expected the Video Slider slides sanitizer to normalize text, URLs, IDs, and row arrays.\n" );
	exit( 1 );
}

function crh_health_issue_containing( array $issues, $needle ) {
	foreach ( $issues as $issue ) {
		if ( false !== strpos( $issue, $needle ) ) {
			return true;
		}
	}

	return false;
}

function crh_health_issues_for_calendar_shortcode( $shortcode ) {
	$GLOBALS['crh_shortcodes'] = array( 'gravitycalendar' => true );
	$GLOBALS['crh_options']    = array(
		'options_wm_bci_form_id'            => 5,
		'options_wm_bci_calendar_shortcode' => $shortcode,
	);

	$checks = new WatersMeet\CommunityResourcesHub\Config\HealthChecks(
		new WatersMeet\CommunityResourcesHub\Config\Config()
	);

	return $checks->issues();
}

$issues = crh_health_issues_for_calendar_shortcode( '' );

if ( ! crh_health_issue_containing( $issues, 'Set the GravityCalendar shortcode' ) ) {
	fwrite( STDERR, "Expected missing saved GravityCalendar shortcode health issue.\n" );
	exit( 1 );
}

$issues = crh_health_issues_for_calendar_shortcode( '[calendar id="7"]' );

if ( ! crh_health_issue_containing( $issues, 'saved GravityCalendar shortcode' ) ) {
	fwrite( STDERR, "Expected invalid saved GravityCalendar shortcode health issue.\n" );
	exit( 1 );
}

$issues = crh_health_issues_for_calendar_shortcode( '[gravitycalendar id="7" title="false"]' );

if ( crh_health_issue_containing( $issues, 'GravityCalendar shortcode' ) ) {
	fwrite( STDERR, "Expected valid saved GravityCalendar shortcode to clear shortcode health issues.\n" );
	exit( 1 );
}

$GLOBALS['crh_shortcodes'] = array( 'gravitycalendar' => true );
$GLOBALS['crh_options']    = array(
	'options_wm_bci_form_id'                                => 5,
	'options_wm_bci_approval_field_id'                      => '22',
	'options_wm_bci_notification_name'                      => 'BCI Approval',
	'options_wm_bci_calendar_page_slug'                     => 'bci-resources',
	'options_wm_bci_calendar_feed_name'                     => 'BCI Feed',
	'options_wm_bci_calendar_feed_id'                       => 7,
	'options_wm_bci_calendar_shortcode'                     => '[gravitycalendar id="7" title="false"]',
	'community_resources_hub_opportunity_reconciliation_pending_at' => '2026-07-05T12:00:00+00:00',
	'community_resources_hub_opportunity_reconciliation_summary'    => array(
		'unresolved_posts' => 2,
	),
);

$checks = new WatersMeet\CommunityResourcesHub\Config\HealthChecks(
	new WatersMeet\CommunityResourcesHub\Config\Config()
);
$issues = $checks->issues();

if ( ! crh_health_issue_containing( $issues, 'legacy BCI opportunity reconciliation' ) ) {
	fwrite( STDERR, "Expected pending legacy opportunity reconciliation health issue.\n" );
	exit( 1 );
}

if ( ! crh_health_issue_containing( $issues, 'missing source-entry identity' ) ) {
	fwrite( STDERR, "Expected unresolved legacy opportunity health issue.\n" );
	exit( 1 );
}

echo "Config health checks smoke test passed.\n";
