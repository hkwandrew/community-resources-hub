<?php
/**
 * Regression checks for Google Sheet sync recovery controls in Hub settings.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_google_sync_url'    => 'https://script.google.com/macros/s/local-test/exec',
	'options_wm_bci_google_sync_secret' => 'do-not-render-this-secret',
);
$GLOBALS['crh_admin_hooks']       = array();
$GLOBALS['crh_meta_boxes']        = array();
$GLOBALS['crh_transients']        = array();
$GLOBALS['crh_current_user_can']  = true;
$GLOBALS['crh_nonce_checked']     = false;
$GLOBALS['crh_redirect']          = '';

class CrhRedirectException extends RuntimeException {}
class CrhWpDieException extends RuntimeException {}

function crh_fail( $message ) {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

function crh_assert( $condition, $message ) {
	if ( ! $condition ) {
		crh_fail( $message );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $_context = 'display' ) {
		return htmlspecialchars( esc_url_raw( $url, $protocols ), ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ) {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $number, $decimals = 0 ) {
		return number_format( (float) $number, (int) $decimals );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] ) ? $GLOBALS['crh_options'][ $option ] : $default;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['crh_admin_hooks'][ $hook_name ] = $callback;
		return true;
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( $id, $title, $callback, $screen = null, $context = 'advanced', $priority = 'default', $callback_args = null ) {
		$GLOBALS['crh_meta_boxes'][ $id ] = compact( 'id', 'title', 'callback', 'screen', 'context', 'priority' );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'http://watersmeet.local/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$separator = false === strpos( $url, '?' ) ? '?' : '&';
		return $url . $separator . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		$field = '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce">';

		if ( $display ) {
			echo $field;
		}

		return $field;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, ...$args ) {
		return (bool) $GLOBALS['crh_current_user_can'];
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$GLOBALS['crh_nonce_checked'] = array( $action, $query_arg );
		return 1;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '', $title = '', $args = array() ) {
		throw new CrhWpDieException( strip_tags( (string) $message ) );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
		$GLOBALS['crh_redirect'] = (string) $location;
		throw new CrhRedirectException( (string) $location );
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['crh_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['crh_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['crh_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id() {
		return 7;
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	function get_edit_post_link( $post_id = 0, $context = 'display' ) {
		return admin_url( 'post.php?post=' . absint( $post_id ) . '&action=edit' );
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post = 0 ) {
		return 202 === absint( $post ) ? 'Failed opportunity' : 'Opportunity';
	}
}

final class CrhFakeBackfill {
	public $operation = '';

	public function status_counts() {
		return array(
			'approved' => 12,
			'synced'   => 5,
			'pending'  => 1,
			'failed'   => 2,
			'skipped'  => 1,
			'unsynced' => 7,
		);
	}

	public function latest_failure() {
		return array(
			'post_id'      => 202,
			'attempted_at' => '2026-07-09T22:48:37+00:00',
			'error'        => 'Missing Apps Script properties.',
		);
	}

	public function job() {
		return array();
	}

	public function sync_one() {
		$this->operation = 'sync_one';
		return array( 'post_id' => 207, 'success' => true, 'status' => 'synced', 'error' => '' );
	}

	public function start_backfill() {
		$this->operation = 'start_backfill';
		return array( 'status' => 'queued', 'total' => 7, 'cursor' => 0 );
	}

	public function resume() {
		$this->operation = 'resume';
		return array( 'status' => 'queued', 'total' => 7, 'cursor' => 2 );
	}

	public function retry_remaining() {
		$this->operation = 'retry_remaining';
		return array( 'status' => 'queued', 'total' => 4, 'cursor' => 0 );
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-google-sync-admin-panel.php';

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Workflow\GoogleSyncAdminPanel;

$backfill = new CrhFakeBackfill();
$panel    = new GoogleSyncAdminPanel( new Config(), $backfill );
$panel->register();

crh_assert( isset( $GLOBALS['crh_admin_hooks']['acf/input/admin_head'] ), 'Expected the recovery panel to register on the ACF options-page metabox hook.' );
crh_assert( isset( $GLOBALS['crh_admin_hooks']['admin_post_' . GoogleSyncAdminPanel::ACTION] ), 'Expected a protected admin-post action for recovery operations.' );

$_GET['page'] = 'bci-hub';
$panel->register_meta_box();

$meta_box = $GLOBALS['crh_meta_boxes']['wm-bci-google-sync-recovery'] ?? array();
crh_assert( 'acf_options_page' === ( $meta_box['screen'] ?? '' ), 'Expected the controls inside the existing ACF options-page layout.' );
crh_assert( 'side' === ( $meta_box['context'] ?? '' ), 'Expected the recovery controls in the Hub settings sidebar.' );

ob_start();
$panel->render_panel();
$html = ob_get_clean();

crh_assert( false !== strpos( $html, 'Configured' ), 'Expected a configuration state without exposing credentials.' );
crh_assert( false === strpos( $html, 'do-not-render-this-secret' ), 'Expected the shared secret never to render in the recovery panel.' );
crh_assert( false === strpos( $html, '<form' ), 'Expected controls to reuse the ACF options-page form instead of nesting another form.' );
crh_assert( false !== strpos( $html, 'Sync One Entry' ), 'Expected the single-entry verification action.' );
crh_assert( false !== strpos( $html, 'Start Backfill' ), 'Expected an explicit bulk-start action.' );
crh_assert( false !== strpos( $html, 'Resume' ), 'Expected a resumable job action.' );
crh_assert( false !== strpos( $html, 'Retry Remaining' ), 'Expected a fresh remaining-items action.' );
crh_assert( false !== strpos( $html, 'Failed opportunity' ), 'Expected the latest failed opportunity to be identifiable.' );
crh_assert( false !== strpos( $html, 'formaction=' ), 'Expected each operation button to submit safely through admin-post.php.' );

$_POST['operation'] = 'start_backfill';

try {
	$panel->handle_action();
	crh_fail( 'Expected the successful admin action to redirect.' );
} catch ( CrhRedirectException $exception ) {
	crh_assert( 'start_backfill' === $backfill->operation, 'Expected the allow-listed start operation to invoke the backfill service.' );
	crh_assert( array( GoogleSyncAdminPanel::NONCE_ACTION, GoogleSyncAdminPanel::NONCE_NAME ) === $GLOBALS['crh_nonce_checked'], 'Expected the admin action to verify its dedicated nonce.' );
	crh_assert( false !== strpos( $GLOBALS['crh_redirect'], 'page=bci-hub' ), 'Expected recovery actions to return to Hub settings.' );
}

$notice_key = GoogleSyncAdminPanel::NOTICE_TRANSIENT_PREFIX . '7';
crh_assert( 'success' === ( $GLOBALS['crh_transients'][ $notice_key ]['type'] ?? '' ), 'Expected operation feedback to use a user-scoped success notice.' );

$GLOBALS['crh_current_user_can'] = false;
$_POST['operation']              = 'sync_one';

try {
	$panel->handle_action();
	crh_fail( 'Expected an unauthorized operation to be rejected.' );
} catch ( CrhWpDieException $exception ) {
	crh_assert( false !== strpos( $exception->getMessage(), 'not allowed' ), 'Expected a clear capability failure.' );
}

fwrite( STDOUT, "Google sync admin panel contract test passed.\n" );
