<?php
/**
 * Wiring checks for Google Sheet recovery lifecycle ownership.
 *
 * @package CommunityResourcesHub
 */

$root      = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/community-resources-hub.php' );
$plugin    = file_get_contents( $root . '/includes/class-plugin.php' );

if ( false === $bootstrap || false === $plugin ) {
	fwrite( STDERR, "Unable to read plugin bootstrap sources.\n" );
	exit( 1 );
}

$expectations = array(
	'Plugin bootstrap registers deactivation cleanup' => array( $bootstrap, 'register_deactivation_hook' ),
	'Workflow boot loads the backfill worker'          => array( $plugin, 'class-google-sync-backfill.php' ),
	'Workflow boot loads the Hub recovery panel'       => array( $plugin, 'class-google-sync-admin-panel.php' ),
	'Workflow boot registers the backfill worker'      => array( $plugin, '$backfill->register()' ),
	'Deactivation clears queued worker state'          => array( $plugin, 'GoogleSyncBackfill::clear_scheduled_work()' ),
	'Uninstall deletes persisted recovery state'       => array( $plugin, 'GoogleSyncBackfill::delete_state()' ),
);

foreach ( $expectations as $label => $expectation ) {
	list( $haystack, $needle ) = $expectation;

	if ( false === strpos( $haystack, $needle ) ) {
		fwrite( STDERR, $label . ".\n" );
		exit( 1 );
	}
}

fwrite( STDOUT, "Google sync bootstrap contract test passed.\n" );
