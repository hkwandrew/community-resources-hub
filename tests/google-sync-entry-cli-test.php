<?php
/**
 * Exact-entry, dry-run-first Google sync WP-CLI contract.
 *
 * @package CommunityResourcesHub
 */

namespace {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );

	$GLOBALS['crh_exact_sync_options']   = array(
		'options_wm_bci_form_id'            => 5,
		'options_wm_bci_google_sync_url'    => 'https://example.test/sync',
		'options_wm_bci_google_sync_secret' => 'configured-test-secret',
		'community_resources_hub_legacy_workflow_cutover_state' => array( 'version' => 'legacy-workflow-cutover-v1' ),
	);
	$GLOBALS['crh_exact_sync_posts']     = array(
		100 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish' ),
		101 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish' ),
		102 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'pending' ),
		104 => array( 'post_type' => 'bci_opportunity', 'post_status' => 'publish' ),
	);
	$GLOBALS['crh_exact_sync_post_meta'] = array(
		100 => array( 'wm_bci_source_entry_id' => 333, 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => '' ),
		101 => array( 'wm_bci_source_entry_id' => 334, 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => 'synced' ),
		102 => array( 'wm_bci_source_entry_id' => 335, 'wm_bci_approval_status' => 'Pending', 'wm_bci_google_sync_status' => '' ),
		104 => array( 'wm_bci_source_entry_id' => 337, 'wm_bci_approval_status' => 'Approved', 'wm_bci_google_sync_status' => '' ),
	);
	$GLOBALS['crh_exact_sync_http_calls'] = array();

	class WP_Error {
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->message = (string) $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}

	class WP_CLI {
		public static $commands  = array();
		public static $errors    = array();
		public static $lines     = array();
		public static $successes = array();
		public static $warnings  = array();

		public static function add_command( $name, $callable ) {
			self::$commands[ $name ] = $callable;
			return true;
		}

		public static function error( $message ) {
			self::$errors[] = (string) $message;
		}

		public static function line( $message ) {
			self::$lines[] = (string) $message;
		}

		public static function success( $message ) {
			self::$successes[] = (string) $message;
		}

		public static function warning( $message ) {
			self::$warnings[] = (string) $message;
		}

		public static function reset_output() {
			self::$errors    = array();
			self::$lines     = array();
			self::$successes = array();
			self::$warnings  = array();
		}
	}

	function __( $text, $domain = 'default' ) {
		return $text;
	}

	function is_wp_error( $value ) {
		return $value instanceof WP_Error;
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function sanitize_title( $value ) {
		$value = strtolower( trim( (string) $value ) );
		return trim( preg_replace( '/[^a-z0-9]+/', '-', $value ), '-' );
	}

	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}

	function esc_url_raw( $value ) {
		return trim( (string) $value );
	}

	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_exact_sync_options'] )
			? $GLOBALS['crh_exact_sync_options'][ $option ]
			: $default;
	}

	function get_terms( $args = array() ) {
		return array();
	}

	function get_term_meta( $term_id, $key, $single = false ) {
		return '';
	}

	function get_posts( $args = array() ) {
		$matches = array();

		foreach ( $GLOBALS['crh_exact_sync_posts'] as $post_id => $post ) {
			if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) {
				continue;
			}

			if ( ! empty( $args['post_status'] ) && 'any' !== $args['post_status'] && $post['post_status'] !== $args['post_status'] ) {
				continue;
			}

			if ( ! empty( $args['meta_key'] ) ) {
				$actual = $GLOBALS['crh_exact_sync_post_meta'][ $post_id ][ $args['meta_key'] ] ?? '';

				if ( (string) $actual !== (string) ( $args['meta_value'] ?? '' ) ) {
					continue;
				}
			}

			$matches[] = (int) $post_id;
		}

		sort( $matches, SORT_NUMERIC );
		return $matches;
	}

	function get_post_status( $post_id ) {
		return $GLOBALS['crh_exact_sync_posts'][ $post_id ]['post_status'] ?? false;
	}

	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['crh_exact_sync_post_meta'][ $post_id ][ $key ] ?? '';
	}

	function update_post_meta( $post_id, $key, $value, $previous = '' ) {
		$GLOBALS['crh_exact_sync_post_meta'][ $post_id ][ $key ] = $value;
		return true;
	}

	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['crh_exact_sync_http_calls'][] = array( $url, $args );
		return array();
	}

	class GFAPI {
		public static $entries = array(
			333 => array( 'id' => 333, 'form_id' => 5, '22' => 'Approved' ),
			334 => array( 'id' => 334, 'form_id' => 5, '22' => 'Approved' ),
			335 => array( 'id' => 335, 'form_id' => 5, '22' => 'Pending' ),
			336 => array( 'id' => 336, 'form_id' => 5, '22' => 'Approved' ),
			337 => array( 'id' => 337, 'form_id' => 5, '22' => 'Approved' ),
		);

		public static function get_entry( $entry_id ) {
			return self::$entries[ $entry_id ] ?? new WP_Error( 'not_found', 'Entry not found.' );
		}
	}

	final class CrhExactEntrySyncFake {
		public $calls = array();

		public function sync_opportunity( $post_id ) {
			$post_id       = absint( $post_id );
			$this->calls[] = $post_id;
			update_post_meta( $post_id, 'wm_bci_google_sync_status', 'synced' );
			return true;
		}
	}
}

namespace WP_CLI\Utils {
	function get_flag_value( $assoc_args, $key, $default = false ) {
		return array_key_exists( $key, $assoc_args ) ? $assoc_args[ $key ] : $default;
	}

	function format_items( $format, $items, $fields ) {
		\WP_CLI::$lines[] = json_encode( $items );
	}
}

namespace {
	$root         = dirname( __DIR__ );
	$command_file = $root . '/includes/cli/class-google-sync-entry-command.php';
	$plugin_file  = $root . '/includes/class-plugin.php';
	$failures     = array();

	$assert = static function ( $condition, $message ) use ( &$failures ) {
		if ( ! $condition ) {
			$failures[] = (string) $message;
		}
	};

	$plugin_source = file_get_contents( $plugin_file );
	$assert(
		is_string( $plugin_source ) && false !== strpos( $plugin_source, 'class-google-sync-entry-command.php' ),
		'Expected WP-CLI boot to load the exact-entry Google sync command only in CLI requests.'
	);
	$assert(
		is_string( $plugin_source ) && false !== strpos( $plugin_source, 'GoogleSyncEntryCommand::register()' ),
		'Expected WP-CLI boot to register the exact-entry Google sync command.'
	);

	if ( ! is_file( $command_file ) ) {
		$failures[] = 'Expected includes/cli/class-google-sync-entry-command.php to own the exact-entry command.';
		fwrite( STDERR, implode( "\n", $failures ) . "\n" );
		exit( 1 );
	}

	require_once $root . '/includes/content-model/class-schema.php';
	require_once $root . '/includes/config/class-settings-schema.php';
	require_once $root . '/includes/config/class-config.php';
	require_once $root . '/includes/workflow/class-field-accessor.php';
	require_once $root . '/includes/workflow/class-opportunity-repository.php';
	require_once $root . '/includes/workflow/class-legacy-workflow-cutover.php';
	require_once $command_file;

	$config     = new WatersMeet\CommunityResourcesHub\Config\Config();
	$sync       = new CrhExactEntrySyncFake();
	$repository = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityRepository( $config );
	$command    = new WatersMeet\CommunityResourcesHub\Cli\GoogleSyncEntryCommand( $config, $sync, $repository );

	WP_CLI::reset_output();
	$command( array( '333' ), array() );
	$dry_run_output = implode( ' ', array_merge( WP_CLI::$lines, WP_CLI::$successes ) );

	$assert( array() === WP_CLI::$errors, 'Expected an eligible exact-entry dry run to succeed.' );
	$assert( array() === $sync->calls, 'Expected an exact-entry dry run not to invoke the Google sync manager.' );
	$assert( array() === $GLOBALS['crh_exact_sync_http_calls'], 'Expected an exact-entry dry run to perform no HTTP requests.' );
	$assert(
		false !== strpos( $dry_run_output, '333' ) && false !== strpos( $dry_run_output, '100' ),
		'Expected dry-run output to identify the requested entry and its linked opportunity post.'
	);

	$cutover_marker = $GLOBALS['crh_exact_sync_options']['community_resources_hub_legacy_workflow_cutover_state'];
	unset( $GLOBALS['crh_exact_sync_options']['community_resources_hub_legacy_workflow_cutover_state'] );
	WP_CLI::reset_output();
	$command( array( '333' ), array( 'apply' => true ) );
	$assert( ! empty( WP_CLI::$errors ) && array() === $sync->calls, 'Expected exact-entry retry to refuse an incomplete legacy cutover.' );
	$GLOBALS['crh_exact_sync_options']['community_resources_hub_legacy_workflow_cutover_state'] = $cutover_marker;

	$google_secret = $GLOBALS['crh_exact_sync_options']['options_wm_bci_google_sync_secret'];
	$GLOBALS['crh_exact_sync_options']['options_wm_bci_google_sync_secret'] = '';
	WP_CLI::reset_output();
	$command( array( '333' ), array( 'apply' => true ) );
	$assert( ! empty( WP_CLI::$errors ) && array() === $sync->calls, 'Expected exact-entry retry to refuse missing Google credentials.' );
	$GLOBALS['crh_exact_sync_options']['options_wm_bci_google_sync_secret'] = $google_secret;

	WP_CLI::reset_output();
	$command( array( '334' ), array( 'apply' => true ) );
	$assert( ! empty( WP_CLI::$errors ), 'Expected an already-synced entry to be refused.' );
	$assert( array() === $sync->calls, 'Expected refusing an already-synced entry not to invoke Google.' );

	WP_CLI::reset_output();
	$command( array( '335' ), array( 'apply' => true ) );
	$assert( ! empty( WP_CLI::$errors ), 'Expected an unapproved entry to be refused.' );
	$assert( array() === $sync->calls, 'Expected refusing an unapproved entry not to invoke Google.' );

	WP_CLI::reset_output();
	$command( array( '336' ), array( 'apply' => true ) );
	$assert( ! empty( WP_CLI::$errors ), 'Expected an entry without a linked opportunity post to be refused.' );
	$assert( array() === $sync->calls, 'Expected refusing an unmapped entry not to invoke Google.' );

	WP_CLI::reset_output();
	$command( array( '333' ), array( 'apply' => true ) );
	$assert( array() === WP_CLI::$errors, 'Expected an eligible exact-entry apply to succeed.' );
	$assert(
		array( 100 ) === $sync->calls,
		'Expected --apply to invoke only the opportunity post linked to the one requested entry.'
	);

	WP_CLI::$commands = array();
	WatersMeet\CommunityResourcesHub\Cli\GoogleSyncEntryCommand::register();
	$registered = WP_CLI::$commands['community-resources-hub sync-google-entry'] ?? null;
	$assert(
		$registered instanceof WatersMeet\CommunityResourcesHub\Cli\GoogleSyncEntryCommand,
		'Expected the exact community-resources-hub sync-google-entry command registration.'
	);

	if ( ! empty( $failures ) ) {
		fwrite( STDERR, implode( "\n", $failures ) . "\n" );
		exit( 1 );
	}

	fwrite( STDOUT, "Google exact-entry sync CLI test passed.\n" );
}
