<?php
/**
 * Source contracts for the submit opportunities modal.
 *
 * @package CommunityResourcesHub
 */

$plugin_root  = dirname( __DIR__ ) . '/';
$modal_script = file_get_contents( $plugin_root . 'blocks/opportunity-hub/src/view/submit-modal.js' );
$dialog_script = file_get_contents( $plugin_root . 'src/shared/dialog.js' );

if ( false === $modal_script || false === $dialog_script ) {
	fwrite( STDERR, "Expected submit modal and shared dialog sources to be readable.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, 'revealGravityFormsAjaxWrapper' ) ) {
	fwrite( STDERR, "Expected submit modal source to defensively reveal Gravity Forms AJAX wrappers.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, "dialog.querySelectorAll( '.gform_wrapper' )" ) ) {
	fwrite( STDERR, "Expected Gravity Forms wrapper reveal to stay scoped to the submit modal dialog.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, "wrapper.style.display = 'block'" ) ) {
	fwrite( STDERR, "Expected submit modal source to clear Gravity Forms' initial display:none wrapper state.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, "form.style.opacity = ''" ) ) {
	fwrite( STDERR, "Expected submit modal source to clear Gravity Forms' initial hidden form opacity.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, 'gform_post_conditional_logic' ) || false === strpos( $modal_script, 'gform/post_render' ) ) {
	fwrite( STDERR, "Expected submit modal source to wait for Gravity Forms render and conditional-logic events before revealing wrappers.\n" );
	exit( 1 );
}

if ( false !== strpos( $modal_script, 'scheduleInitialGravityFormsReveal' ) ) {
	fwrite( STDERR, "Expected submit modal source not to reveal Gravity Forms before the hidden modal is opened.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, 'triggerGravityFormsPostRender' ) || false === strpos( $modal_script, 'triggerPostRenderEvents' ) ) {
	fwrite( STDERR, "Expected submit modal source to trigger Gravity Forms post-render when the hidden form becomes visible.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, 'setSubmitModalLayerOpen' ) || false === strpos( $modal_script, 'data-wm-bci-submit-modal-overlay' ) ) {
	fwrite( STDERR, "Expected submit modal source to manage the calendar-scoped overlay state.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, 'scheduleSubmitModalScroll' ) || false === strpos( $modal_script, 'scrollIntoView' ) ) {
	fwrite( STDERR, "Expected submit modal source to scroll the calendar-centered modal into view after Gravity Forms settles.\n" );
	exit( 1 );
}

if ( false === strpos( $modal_script, 'updateSubmitModalOverlayBounds' ) || false === strpos( $modal_script, '.fc-view-harness' ) ) {
	fwrite( STDERR, "Expected submit modal source to size the overlay from the FullCalendar grid, not the toolbar.\n" );
	exit( 1 );
}

foreach ( array( '--wm-bci-submit-overlay-top', '--wm-bci-submit-overlay-height', '--wm-bci-submit-modal-top' ) as $overlay_var ) {
	if ( false === strpos( $modal_script, $overlay_var ) ) {
		fwrite( STDERR, "Expected submit modal source to write {$overlay_var} for grid-scoped overlay placement.\n" );
		exit( 1 );
	}
}

if ( false === strpos( $modal_script, 'GF_AJAX_POSTBACK' ) ) {
	fwrite( STDERR, "Expected submit modal source not to reveal on the iframe's initial about:blank load.\n" );
	exit( 1 );
}

if ( ! preg_match( '/export function openDialog\\( dialog, trigger \\) \\{(?P<body>.*?)\\n\\}/s', $modal_script, $matches ) ) {
	fwrite( STDERR, "Expected submit modal source to expose an openDialog function.\n" );
	exit( 1 );
}

if (
	false !== strpos( $matches['body'], 'scheduleGravityFormsReveal( dialog );' ) &&
	false === strpos( $matches['body'], 'crhSubmitGformReady' )
) {
	fwrite( STDERR, "Expected submit modal openDialog to reveal only after Gravity Forms reports readiness.\n" );
	exit( 1 );
}

if ( false === strpos( $matches['body'], 'scheduleGravityFormsPostRender( dialog )' ) ) {
	fwrite( STDERR, "Expected submit modal openDialog to wake Gravity Forms conditional logic before revealing the wrapper.\n" );
	exit( 1 );
}

if ( false === strpos( $dialog_script, 'dialog.hidden = false' ) || false === strpos( $dialog_script, "dialog.setAttribute( 'hidden', '' )" ) ) {
	fwrite( STDERR, "Expected shared dialog helper to open and close non-native hidden dialogs.\n" );
	exit( 1 );
}

if ( false === strpos( $dialog_script, 'const isNativeDialog' ) || false === strpos( $dialog_script, 'if ( isNativeDialog ) {' ) ) {
	fwrite( STDERR, "Expected shared dialog helper to reserve the global scroll lock for native dialogs.\n" );
	exit( 1 );
}

echo "Submit modal source contract test passed.\n";
