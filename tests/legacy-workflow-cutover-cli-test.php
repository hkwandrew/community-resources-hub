<?php
/**
 * Regression contract for the explicit legacy workflow cutover CLI.
 *
 * @package CommunityResourcesHub
 */

namespace WP_CLI\Utils {
	function get_flag_value( $assoc_args, $key, $default = false ) {
		return array_key_exists( $key, $assoc_args ) ? $assoc_args[ $key ] : $default;
	}
}

namespace {
	$command_file = dirname( __DIR__ ) . '/includes/cli/class-legacy-workflow-cutover-command.php';

	if ( ! is_file( $command_file ) ) {
		fwrite( STDERR, "Expected the LegacyWorkflowCutover WP-CLI command source file.\n" );
		exit( 1 );
	}

	class WP_CLI {
		public static $commands = array();
		public static $output = array();

		public static function add_command( $name, $callable ) {
			self::$commands[ $name ] = $callable;
			return true;
		}

		public static function line( $message ) {
			self::$output[] = (string) $message;
		}

		public static function warning( $message ) {
			self::$output[] = 'WARNING: ' . (string) $message;
		}

		public static function success( $message ) {
			self::$output[] = 'SUCCESS: ' . (string) $message;
		}

		public static function error( $message ) {
			throw new \RuntimeException( (string) $message );
		}
	}

	require_once __DIR__ . '/legacy-workflow-cutover-test.php';
	require_once $command_file;

	WP_CLI::$commands = array();
	WP_CLI::$output   = array();
	$secret           = crh_cutover_fixture();
	$before           = crh_cutover_snapshot();

	WatersMeet\CommunityResourcesHub\Cli\LegacyWorkflowCutoverCommand::register();
	$command = WP_CLI::$commands['community-resources-hub migrate-legacy-workflow'] ?? null;

	crh_cutover_assert( $command instanceof WatersMeet\CommunityResourcesHub\Cli\LegacyWorkflowCutoverCommand, 'Expected the exact community-resources-hub migrate-legacy-workflow command.' );
	$command( array(), array() );

	$output = implode( "\n", WP_CLI::$output );
	crh_cutover_assert( $before === crh_cutover_snapshot(), 'Expected the CLI default invocation to remain a read-only dry run.' );
	crh_cutover_assert( false === strpos( $output, $secret ), 'Expected the Google shared secret never to appear in CLI output.' );
	crh_cutover_assert( false !== strpos( strtolower( $output ), 'dry run' ), 'Expected CLI output to identify the default operation as a dry run.' );
	crh_cutover_assert( 0 === $GLOBALS['crh_cutover_http_calls'], 'Expected the CLI dry run to make no HTTP requests.' );

	WP_CLI::$output = array();
	$missing_hash_error = '';

	try {
		$command( array(), array( 'apply' => true ) );
	} catch ( RuntimeException $exception ) {
		$missing_hash_error = $exception->getMessage();
	}

	crh_cutover_assert( false !== strpos( strtolower( $missing_hash_error ), 'plan hash' ), 'Expected CLI apply to refuse writes without the dry-run plan hash.' );
	crh_cutover_assert( $before === crh_cutover_snapshot(), 'Expected a hashless CLI apply to remain write-free.' );

	$source = file_get_contents( $command_file );
	crh_cutover_assert( false !== strpos( $source, "get_flag_value( \$assoc_args, 'apply', false )" ), 'Expected cutover writes to require an explicit --apply flag.' );
	crh_cutover_assert( false !== strpos( $source, "get_flag_value( \$assoc_args, 'plan-hash', '' )" ), 'Expected cutover apply to require --plan-hash from the dry run.' );

	echo "Legacy workflow cutover CLI test passed.\n";
}
