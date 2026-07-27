<?php
/**
 * Visual contract for the Figma-aligned open-form button icon.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

require_once dirname( __DIR__ ) . '/includes/support/class-render-support.php';

$icon = WatersMeet\CommunityResourcesHub\Support\RenderSupport::open_form_icon();
$style = file_get_contents( dirname( __DIR__ ) . '/blocks/opportunity-hub/style.scss' );

if ( false === $style ) {
	fwrite( STDERR, "Expected the Opportunity Hub source stylesheet to be readable.\n" );
	exit( 1 );
}

$expected_fragments = array(
	'width="24" height="24"',
	'viewBox="0 0 24 24"',
	'd="M5 19V13H7V17H11V19H5ZM17 11V7H13V5H19V11H17Z"',
);

foreach ( $expected_fragments as $fragment ) {
	if ( false === strpos( $icon, $fragment ) ) {
		fwrite( STDERR, "Expected the open-form icon to match the 24px Figma asset: {$fragment}.\n" );
		exit( 1 );
	}
}

/**
 * Assert declarations within one source selector block.
 *
 * @param string        $source       Stylesheet source.
 * @param string        $pattern      Selector-block pattern.
 * @param array<string> $declarations Required declarations.
 * @param string        $label        Contract label.
 */
function crh_assert_style_contract( $source, $pattern, array $declarations, $label ) {
	if ( 1 !== preg_match( $pattern, $source, $matches ) ) {
		fwrite( STDERR, "Expected the open-form button stylesheet to include the {$label} selector block.\n" );
		exit( 1 );
	}

	foreach ( $declarations as $declaration ) {
		if ( false === strpos( $matches['rules'], $declaration ) ) {
			fwrite( STDERR, "Expected the open-form button {$label} rules to preserve: {$declaration}.\n" );
			exit( 1 );
		}
	}
}

crh_assert_style_contract(
	$style,
	'/\.button\.wm-bci-workflow-section__submit-trigger,\s*\.button\.crh-opportunity-hub__submit-trigger\s*\{(?P<rules>[^}]*)\}/s',
	array( 'border: 0;', 'column-gap: 8px;', 'display: inline-grid;', 'grid-template-columns: max-content 39px;', 'padding: 4px 4px 4px 1rem !important;' ),
	'layout'
);

crh_assert_style_contract(
	$style,
	'/\.button\.wm-bci-workflow-section__submit-trigger::before,\s*\.button\.crh-opportunity-hub__submit-trigger::before\s*\{(?P<rules>[^}]*)\}/s',
	array( 'height: 39px;', 'right: 4px;', 'width: 39px;' ),
	'badge'
);

crh_assert_style_contract(
	$style,
	'/\.button\.wm-bci-workflow-section__submit-trigger \.button-text,\s*\.button\.crh-opportunity-hub__submit-trigger \.button-text\s*\{(?P<rules>[^}]*)\}/s',
	array( 'margin-right: 0;' ),
	'label'
);

crh_assert_style_contract(
	$style,
	'/\.wm-bci-workflow-section__submit-trigger > svg,\s*\.crh-opportunity-hub__submit-trigger > svg\s*\{(?P<rules>[^}]*)\}/s',
	array( 'justify-self: center;', 'top: -0.72px;' ),
	'icon'
);

echo "Open-form icon size test passed.\n";
