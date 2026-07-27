<?php
/**
 * Regression check for the exact Opportunity Hub migration CLI registration.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

class WP_CLI {
	public static $commands = array();

	public static function add_command( $name, $callable ) {
		self::$commands[ $name ] = $callable;
		return true;
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-taxonomy.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/config/class-provisioner.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-repository.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-contract-migration.php';
require_once dirname( __DIR__ ) . '/includes/cli/class-opportunity-contract-command.php';

WatersMeet\CommunityResourcesHub\Cli\OpportunityContractCommand::register();

$command = WP_CLI::$commands['community-resources-hub migrate-opportunity-contract'] ?? null;

if ( ! $command instanceof WatersMeet\CommunityResourcesHub\Cli\OpportunityContractCommand ) {
	fwrite( STDERR, "Expected the exact community-resources-hub migrate-opportunity-contract command.\n" );
	exit( 1 );
}

$source = file_get_contents( dirname( __DIR__ ) . '/includes/cli/class-opportunity-contract-command.php' );

if ( false === strpos( $source, "get_flag_value( \$assoc_args, 'apply', false )" ) ) {
	fwrite( STDERR, "Expected the CLI command to remain a read-only dry run unless --apply is explicit.\n" );
	exit( 1 );
}

if ( false === strpos( $source, 'Per-entry proposed changes:' ) || false === strpos( $source, 'WordPress %s; PHP %s; timezone %s' ) ) {
	fwrite( STDERR, "Expected dry-run output to identify the environment and enumerate proposed entry changes.\n" );
	exit( 1 );
}

echo "Opportunity-contract CLI registration test passed.\n";
