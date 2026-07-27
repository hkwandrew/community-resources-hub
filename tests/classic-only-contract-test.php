<?php
/**
 * Source contract tests for the classic-only plugin surface.
 *
 * @package CommunityResourcesHub
 */

$plugin_root = dirname( __DIR__ ) . '/';

$block_only_files = array(
	'blocks/opportunity-hub/block.json',
	'blocks/opportunity-hub/editor.asset.php',
	'blocks/opportunity-hub/editor.css',
	'blocks/opportunity-hub/editor.js',
	'blocks/opportunity-hub/render.php',
	'blocks/member-directory/block.json',
	'blocks/member-directory/editor.asset.php',
	'blocks/member-directory/editor.css',
	'blocks/member-directory/editor.js',
	'blocks/member-directory/render.php',
	'blocks/video-slider/block.json',
	'blocks/video-slider/editor.asset.php',
	'blocks/video-slider/editor.css',
	'blocks/video-slider/editor.js',
	'blocks/video-slider/render.php',
	'includes/blocks/class-opportunity-hub-block.php',
	'includes/blocks/class-member-directory-block.php',
	'includes/blocks/class-video-slider-block.php',
);

foreach ( $block_only_files as $relative_path ) {
	if ( is_file( $plugin_root . $relative_path ) ) {
		fwrite( STDERR, "Expected classic-only plugin cleanup to remove {$relative_path}.\n" );
		exit( 1 );
	}
}

$plugin_bootstrap = file_get_contents( $plugin_root . 'includes/class-plugin.php' );
$asset_registry   = file_get_contents( $plugin_root . 'includes/assets/class-registry.php' );
$render_support   = file_get_contents( $plugin_root . 'includes/support/class-render-support.php' );
$webpack_config   = file_get_contents( $plugin_root . 'webpack.config.js' );
$package_json     = file_get_contents( $plugin_root . 'package.json' );
$readme           = file_get_contents( $plugin_root . 'README.md' );
$settings_schema  = file_get_contents( $plugin_root . 'includes/config/class-settings-schema.php' );
$member_renderer  = file_get_contents( $plugin_root . 'includes/frontend/class-member-directory-renderer.php' );
$video_renderer   = file_get_contents( $plugin_root . 'includes/frontend/class-video-slider-renderer.php' );

if ( false === $plugin_bootstrap || false === $asset_registry || false === $render_support || false === $webpack_config || false === $package_json || false === $readme || false === $settings_schema || false === $member_renderer || false === $video_renderer ) {
	fwrite( STDERR, "Expected classic-only contract sources to be readable.\n" );
	exit( 1 );
}

foreach (
	array(
		'boot_blocks',
		'includes/blocks/class-opportunity-hub-block.php',
		'includes/blocks/class-member-directory-block.php',
		'includes/blocks/class-video-slider-block.php',
	) as $removed_plugin_fragment
) {
	if ( false !== strpos( $plugin_bootstrap, $removed_plugin_fragment ) ) {
		fwrite( STDERR, "Expected plugin bootstrap not to reference {$removed_plugin_fragment} after the classic-only cleanup.\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'OPPORTUNITY_HUB_EDITOR_SCRIPT',
		'OPPORTUNITY_HUB_EDITOR_STYLE',
		'MEMBER_DIRECTORY_EDITOR_SCRIPT',
		'MEMBER_DIRECTORY_EDITOR_STYLE',
		'VIDEO_SLIDER_EDITOR_SCRIPT',
		'VIDEO_SLIDER_EDITOR_STYLE',
		'register_editor_script',
		'register_editor_style',
	) as $removed_asset_fragment
) {
	if ( false !== strpos( $asset_registry, $removed_asset_fragment ) ) {
		fwrite( STDERR, "Expected asset registry not to reference {$removed_asset_fragment} after the classic-only cleanup.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $render_support, 'get_block_wrapper_attributes' ) ) {
	fwrite( STDERR, "Expected render support not to depend on block wrapper attributes after the classic-only cleanup.\n" );
	exit( 1 );
}

foreach (
	array(
		'editor.js',
		'@wordpress/block-editor',
		'@wordpress/blocks',
		'@wordpress/server-side-render',
		"'blocks/**/*.js'",
	) as $removed_build_fragment
) {
	if ( false !== strpos( $webpack_config, $removed_build_fragment ) || false !== strpos( $package_json, $removed_build_fragment ) ) {
		fwrite( STDERR, "Expected classic-only build sources not to reference {$removed_build_fragment} after the cleanup.\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'blocks/opportunity-hub/style.css',
		'blocks/member-directory/style.css',
		'blocks/video-slider/style.css',
	) as $removed_css_output_reference
) {
	if ( false !== strpos( $asset_registry, $removed_css_output_reference ) || false !== strpos( $package_json, $removed_css_output_reference ) ) {
		fwrite( STDERR, "Expected classic-only CSS build contract not to reference {$removed_css_output_reference}.\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'Block theme usage',
		'dynamic blocks',
		'block editor',
		'Each block renders',
	) as $removed_readme_fragment
) {
	if ( false !== strpos( $readme, $removed_readme_fragment ) ) {
		fwrite( STDERR, "Expected README not to claim block support after the classic-only cleanup.\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'settings schema' => array( $settings_schema, 'when a block or shortcode' ),
		'member renderer' => array( $member_renderer, 'saved block context' ),
		'video renderer' => array( $video_renderer, 'block/theme slide input' ),
	) as $source_label => $source_check
) {
	if ( false !== strpos( $source_check[0], $source_check[1] ) ) {
		fwrite( STDERR, "Expected {$source_label} not to retain the block-era phrase \"{$source_check[1]}\".\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'opportunity-hub/style.css',
		'member-directory/style.css',
		'video-slider/style.css',
	) as $required_css_relative_path
) {
	$required_css_output_reference = 'build/' . $required_css_relative_path;

	if ( false === strpos( $asset_registry, "\$build_url . '" . $required_css_relative_path . "'" ) || false === strpos( $asset_registry, "\$build_dir . '" . $required_css_relative_path . "'" ) || false === strpos( $package_json, $required_css_output_reference ) ) {
		fwrite( STDERR, "Expected classic-only CSS build contract to reference {$required_css_output_reference}.\n" );
		exit( 1 );
	}
}

echo "Classic-only contract test passed.\n";
