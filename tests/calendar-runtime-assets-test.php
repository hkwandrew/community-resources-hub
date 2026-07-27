<?php
/**
 * Smoke tests for shared calendar runtime asset ownership.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'COMMUNITY_RESOURCES_HUB_DIR', dirname( __DIR__ ) . '/' );
define( 'COMMUNITY_RESOURCES_HUB_URL', 'https://example.test/wp-content/plugins/community-resources-hub/' );
define( 'COMMUNITY_RESOURCES_HUB_VERSION', '0.1.0-test' );

$GLOBALS['crh_registered_scripts'] = array();
$GLOBALS['crh_registered_styles']  = array();
$GLOBALS['crh_script_data']        = array();
$GLOBALS['crh_enqueued_scripts']   = array();
$GLOBALS['crh_enqueued_styles']    = array();
$GLOBALS['crh_inline_styles']      = array();

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src, $deps = array(), $ver = false, $args = array() ) {
		$GLOBALS['crh_registered_scripts'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
			'args' => $args,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src, $deps = array(), $ver = false ) {
		$GLOBALS['crh_registered_styles'][ $handle ] = array(
			'src'  => $src,
			'deps' => $deps,
			'ver'  => $ver,
		);

		return true;
	}
}

if ( ! function_exists( 'wp_script_add_data' ) ) {
	function wp_script_add_data( $handle, $key, $value ) {
		$GLOBALS['crh_script_data'][ $handle ][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle ) {
		$GLOBALS['crh_enqueued_scripts'][] = $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle ) {
		$GLOBALS['crh_enqueued_styles'][] = $handle;
	}
}

if ( ! function_exists( 'wp_add_inline_style' ) ) {
	function wp_add_inline_style( $handle, $data ) {
		$GLOBALS['crh_inline_styles'][ $handle ][] = (string) $data;

		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/assets/class-registry.php';
require_once dirname( __DIR__ ) . '/includes/calendar/class-runtime-assets.php';

WatersMeet\CommunityResourcesHub\Assets\Registry::register_asset_handles();
WatersMeet\CommunityResourcesHub\Assets\Registry::enqueue_opportunity_hub_assets();

$opportunity_hub_inline_css = implode(
	"\n",
	$GLOBALS['crh_inline_styles'][ WatersMeet\CommunityResourcesHub\Assets\Registry::OPPORTUNITY_HUB_STYLE ] ?? array()
);

if ( false === strpos( $opportunity_hub_inline_css, '.wm-bci-opportunity-modal__action[hidden]' ) ) {
	fwrite( STDERR, "Expected opportunity hub assets to include the hidden action runtime guard.\n" );
	exit( 1 );
}

foreach (
	array(
		'.opportunity-hub-container+.content-block.bg-color-theme-1.has-top-wave .wave.top{margin-top:-2px;}',
		'.opportunity-hub-container+.content-block.bg-color-theme-1.has-top-wave .wave.top svg{margin-bottom:-2px!important;}',
	)
	as $wave_seam_guard
) {
	if ( false === strpos( $opportunity_hub_inline_css, $wave_seam_guard ) ) {
		fwrite( STDERR, "Expected opportunity hub assets to include the wave seam runtime guard: {$wave_seam_guard}\n" );
		exit( 1 );
	}
}

$opportunity_hub_source_css = file_get_contents( dirname( __DIR__ ) . '/blocks/opportunity-hub/style.scss' );

if ( false === $opportunity_hub_source_css ) {
	fwrite( STDERR, "Expected the Opportunity Hub source stylesheet to be readable.\n" );
	exit( 1 );
}

$calendar_day_height_count = preg_match_all(
	'/--wm-bci-calendar-day-height:\s*([^;]+);/',
	$opportunity_hub_source_css,
	$calendar_day_height_matches
);

if (
	1 !== $calendar_day_height_count
	|| '12rem' !== trim( $calendar_day_height_matches[1][0] ?? '' )
) {
	fwrite( STDERR, "Expected BCI calendar day cells to retain enough height for four events and the overflow link at every breakpoint.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/\.wm-bci-workflow-section__calendar \.gv-fullcalendar \.fc-daygrid-day-frame\s*\{(?P<rules>[^}]*)\}/s',
		$opportunity_hub_source_css,
		$calendar_day_frame_rules
	)
	|| 1 !== preg_match(
		'/(?:^|\R)\s*height:\s*var\(--wm-bci-calendar-day-height\);/',
		$calendar_day_frame_rules['rules']
	)
	|| 1 !== preg_match(
		'/(?:^|\R)\s*min-height:\s*var\(--wm-bci-calendar-day-height\);/',
		$calendar_day_frame_rules['rules']
	)
) {
	fwrite( STDERR, "Expected BCI calendar weeks to retain a uniform four-event row height.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/\.opportunity-hub-container \+ \.content-block\.bg-color-theme-1\.has-top-wave \.wave\.top\s*\{(?P<rules>[^}]*)\}/s',
		$opportunity_hub_source_css,
		$wave_rules
	)
	|| false === strpos( $wave_rules['rules'], 'margin-top: -2px;' )
) {
	fwrite( STDERR, "Expected the Opportunity Hub source stylesheet to overlap the top wave boundary by two pixels.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/\.wm-bci-calendar-toolbar-filter__member-columns\s*\{(?P<rules>[^}]*)\}/s',
		$opportunity_hub_source_css,
		$calendar_member_column_rules
	)
	|| false === strpos( $calendar_member_column_rules['rules'], 'grid-template-columns: repeat(2, minmax(0, 1fr));' )
) {
	fwrite( STDERR, "Expected calendar member options to use two explicit source-owned columns on larger screens.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/\.wm-bci-opportunities__member-options\s*\{(?P<rules>[^}]*)\}/s',
		$opportunity_hub_source_css,
		$grid_member_column_rules
	)
	|| false === strpos( $grid_member_column_rules['rules'], 'grid-template-columns: repeat(2, minmax(0, 1fr));' )
	|| ! preg_match( '/\.wm-bci-opportunities__member-column\s*\{[^}]*min-width: 0;/s', $opportunity_hub_source_css )
) {
	fwrite( STDERR, "Expected grid member options to use two explicit source-owned columns on larger screens.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/\.crh-opportunity-hub button\.wm-bci-calendar-toolbar-clear,\s*\.crh-opportunity-hub button\.wm-bci-opportunities__clear-filters\s*\{(?P<rules>[^}]*)\}/s',
		$opportunity_hub_source_css,
		$clear_button_rules
	)
	|| false === strpos( $clear_button_rules['rules'], 'color: $blue !important;' )
	|| false === strpos( $clear_button_rules['rules'], 'height: auto;' )
	|| false === strpos( $clear_button_rules['rules'], 'padding: 0 !important;' )
	|| false === strpos( $clear_button_rules['rules'], 'text-decoration: underline;' )
	|| false === strpos( $clear_button_rules['rules'], 'text-transform: none;' )
) {
	fwrite( STDERR, "Expected Clear controls to override the theme button treatment with underlined blue text.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/\.crh-opportunity-hub button\.wm-bci-calendar-toolbar-clear::before,\s*\.crh-opportunity-hub button\.wm-bci-calendar-toolbar-clear::after,\s*\.crh-opportunity-hub button\.wm-bci-opportunities__clear-filters::before,\s*\.crh-opportunity-hub button\.wm-bci-opportunities__clear-filters::after\s*\{(?P<rules>[^}]*)\}/s',
		$opportunity_hub_source_css,
		$clear_button_pseudo_rules
	)
	|| false === strpos( $clear_button_pseudo_rules['rules'], 'content: none !important;' )
	|| false === strpos( $clear_button_pseudo_rules['rules'], 'display: none !important;' )
) {
	fwrite( STDERR, "Expected Clear controls to suppress the theme button decoration.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/@media print,\s*screen and \(max-width: 39\.9988em\)\s*\{(?P<rules>.*)\}\s*\.opportunity-hub-container/s',
		$opportunity_hub_source_css,
		$mobile_rules
	)
	|| ! preg_match( '/\.wm-bci-calendar-toolbar-filter__member-columns\s*\{[^}]*grid-template-columns: 1fr;/s', $mobile_rules['rules'] )
	|| ! preg_match( '/\.wm-bci-opportunities__member-options,\s*\.wm-bci-opportunities__grid\s*\{[^}]*grid-template-columns: 1fr;/s', $mobile_rules['rules'] )
) {
	fwrite( STDERR, "Expected both member filters to collapse to one column at the existing mobile breakpoint.\n" );
	exit( 1 );
}

if (
	1 !== preg_match(
		'/\.opportunity-hub-container \+ \.content-block\.bg-color-theme-1\.has-top-wave \.wave\.top svg\s*\{(?P<rules>[^}]*)\}/s',
		$opportunity_hub_source_css,
		$wave_svg_rules
	)
	|| false === strpos( $wave_svg_rules['rules'], 'margin-bottom: -2px;' )
) {
	fwrite( STDERR, "Expected the Opportunity Hub source stylesheet to overlap the SVG join by two pixels.\n" );
	exit( 1 );
}

$handle = WatersMeet\CommunityResourcesHub\Calendar\RuntimeAssets::SCRIPT_HANDLE;

if ( empty( $GLOBALS['crh_registered_scripts'][ $handle ] ) ) {
	fwrite( STDERR, "Expected calendar runtime script handle to be registered.\n" );
	exit( 1 );
}

$script = $GLOBALS['crh_registered_scripts'][ $handle ];

if ( COMMUNITY_RESOURCES_HUB_URL . 'build/calendar/runtime.js' !== $script['src'] ) {
	fwrite( STDERR, "Expected calendar runtime script to register from the built plugin asset.\n" );
	exit( 1 );
}

if ( false !== strpos( $script['src'], 'assets/js/bci-calendar-runtime.js' ) ) {
	fwrite( STDERR, "Expected calendar runtime script not to register from the legacy assets/js wrapper.\n" );
	exit( 1 );
}

$asset_file = COMMUNITY_RESOURCES_HUB_DIR . 'build/calendar/runtime.asset.php';

if ( ! is_file( $asset_file ) ) {
	fwrite( STDERR, "Expected built calendar runtime asset metadata to exist.\n" );
	exit( 1 );
}

$asset = require $asset_file;

if ( ( $asset['dependencies'] ?? array() ) !== $script['deps'] ) {
	fwrite( STDERR, "Expected calendar runtime dependencies to come from asset metadata.\n" );
	exit( 1 );
}

if ( (string) filemtime( COMMUNITY_RESOURCES_HUB_DIR . 'build/calendar/runtime.js' ) !== (string) $script['ver'] ) {
	fwrite( STDERR, "Expected calendar runtime version to come from the built runtime file mtime for cache busting.\n" );
	exit( 1 );
}

if ( 'defer' !== ( $GLOBALS['crh_script_data'][ $handle ]['strategy'] ?? null ) ) {
	fwrite( STDERR, "Expected calendar runtime script to retain deferred loading.\n" );
	exit( 1 );
}

WatersMeet\CommunityResourcesHub\Calendar\RuntimeAssets::enqueue();

if ( ! in_array( $handle, $GLOBALS['crh_enqueued_scripts'], true ) ) {
	fwrite( STDERR, "Expected RuntimeAssets::enqueue() to enqueue the calendar runtime script.\n" );
	exit( 1 );
}

if ( ! in_array( WatersMeet\CommunityResourcesHub\Calendar\RuntimeAssets::STYLE_HANDLE, $GLOBALS['crh_enqueued_styles'], true ) ) {
	fwrite( STDERR, "Expected RuntimeAssets::enqueue() to enqueue the calendar runtime style.\n" );
	exit( 1 );
}

echo "Calendar runtime asset ownership smoke test passed.\n";
