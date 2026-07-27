<?php
/**
 * Regression checks for the Google Apps Script sync contract.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_form_id'                    => 5,
	'options_wm_bci_google_sync_url'            => 'https://script.google.com/macros/s/test-deployment/exec',
	'options_wm_bci_google_sync_secret'         => 'test-shared-secret',
	'options_wm_bci_field_map_opportunity_type' => '1',
	'options_wm_bci_field_map_submitter_name'   => '3',
	'options_wm_bci_field_map_title'            => '4',
	'options_wm_bci_field_map_organization'     => '5',
	'options_wm_bci_field_map_start_date'       => '6',
	'options_wm_bci_field_map_grant_deadline'   => '9',
	'options_wm_bci_field_map_end_date'         => '10',
	'options_wm_bci_field_map_start_time'       => '12',
	'options_wm_bci_field_map_end_time'         => '21',
	'options_wm_bci_field_map_cost'             => '14',
	'options_wm_bci_field_map_address'          => '15',
	'options_wm_bci_field_map_location_mode'    => '16',
	'options_wm_bci_field_map_description'      => '17',
	'options_wm_bci_field_map_info_url'         => '18',
	'options_wm_bci_field_map_file_upload'      => '19',
	'options_wm_bci_field_map_approval_status'  => '22',
);

$GLOBALS['crh_post_meta'] = array(
	50 => array(
		'wm_bci_source_entry_id' => '302',
		'wm_bci_approval_status' => 'Approved',
		'wm_bci_approved_at'     => '2026-07-09T17:13:00+00:00',
	),
);

$GLOBALS['crh_entry'] = array(
	'id'           => 302,
	'form_id'      => 5,
	'date_created' => '2026-07-09 17:12:00',
	'1'            => 'Event',
	'3.3'          => 'Andrew',
	'3.6'          => 'Hughes',
	'4'            => 'Test event',
	'5'            => 'Waters Meet',
	'6'            => '2026-08-15',
	'10'           => '',
	'12'           => '1:00 PM',
	'21'           => '2:00 PM',
	'14'           => 'Free',
	'15.1'         => '123 Main St',
	'15.3'         => 'Spokane',
	'15.4'         => 'WA',
	'15.5'         => '99201',
	'15.6'         => 'United States',
	'16'           => 'In person',
	'17'           => 'A test event description.',
	'18'           => 'https://example.com/test-event',
	'19'           => '["https://example.com/one.pdf","https://example.com/two.pdf"]',
	'22'           => 'Approved',
);

$GLOBALS['crh_http_queue']    = array();
$GLOBALS['crh_http_requests'] = array();

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->message = (string) $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

class GFAPI {
	public static function get_entry( $entry_id ) {
		return (int) $entry_id === (int) ( $GLOBALS['crh_entry']['id'] ?? 0 )
			? $GLOBALS['crh_entry']
			: new WP_Error( 'not_found', 'Entry not found.' );
	}
}

function crh_fail( $message ) {
	fwrite( STDERR, $message . "\n" );
	exit( 1 );
}

function crh_assert( $condition, $message ) {
	if ( ! $condition ) {
		crh_fail( $message );
	}
}

function crh_http_response( $code, $body = '', array $headers = array() ) {
	return array(
		'response' => array( 'code' => $code ),
		'body'     => $body,
		'headers'  => array_change_key_case( $headers, CASE_LOWER ),
		'cookies'  => array(),
	);
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
		return trim( (string) $value, '-' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url, $protocols = null, $_context = 'display' ) {
		return esc_url_raw( $url, $protocols );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] ) ? $GLOBALS['crh_options'][ $option ] : $default;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $meta_key = '', $single = false ) {
		return $GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id = 0 ) {
		return 'Test event';
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id = null, $context = 'display' ) {
		return 'A test event description.';
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		return array(
			array(
				'term_id'  => 10,
				'name'     => 'Event',
				'slug'     => 'event',
				'taxonomy' => 'opportunity-type',
			),
		);
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $meta_key, $single = false ) {
		return 'alias' === $meta_key ? 'Events' : '';
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://c3watersmeet.wpenginepowered.com/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $args, $url = '' ) {
		$separator = false === strpos( $url, '?' ) ? '?' : '&';
		return $url . $separator . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = array() ) {
		$GLOBALS['crh_http_requests'][] = array( 'method' => 'POST', 'url' => $url, 'args' => $args );
		return array_shift( $GLOBALS['crh_http_queue'] );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) {
		$GLOBALS['crh_http_requests'][] = array( 'method' => 'GET', 'url' => $url, 'args' => $args );
		return array_shift( $GLOBALS['crh_http_queue'] );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ) {
		return (int) ( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return (string) ( $response['body'] ?? '' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	function wp_remote_retrieve_header( $response, $header ) {
		return (string) ( $response['headers'][ strtolower( $header ) ] ?? '' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_cookies' ) ) {
	function wp_remote_retrieve_cookies( $response ) {
		return $response['cookies'] ?? array();
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-google-sync-manager.php';

$config  = new WatersMeet\CommunityResourcesHub\Config\Config();
$manager = new WatersMeet\CommunityResourcesHub\Workflow\GoogleSyncManager( $config );

$GLOBALS['crh_http_queue'] = array(
	crh_http_response( 200, '{"ok":true,"disposition":"appended","entryId":302}' ),
);

crh_assert( true === $manager->sync_opportunity( 50 ), 'Expected a valid Apps Script response to mark the opportunity synced.' );
crh_assert( 1 === count( $GLOBALS['crh_http_requests'] ), 'Expected one outbound sync request.' );

$request = $GLOBALS['crh_http_requests'][0];
$body    = (string) ( $request['args']['body'] ?? '' );
$payload = json_decode( $body, true );
$query   = array();
parse_str( (string) parse_url( $request['url'], PHP_URL_QUERY ), $query );

crh_assert( 'POST' === $request['method'], 'Expected the Apps Script endpoint to receive a POST request.' );
crh_assert( isset( $query['signature'] ), 'Expected the sync URL to include the HMAC signature query parameter.' );
crh_assert( hash_hmac( 'sha256', $body, 'test-shared-secret' ) === $query['signature'], 'Expected the request signature to cover the exact JSON body.' );
crh_assert( 'bci_entry_approved' === ( $payload['event'] ?? '' ), 'Expected the established Apps Script event name.' );
crh_assert( 302 === ( $payload['entryId'] ?? 0 ), 'Expected the Gravity Forms entry ID in the sync payload.' );
crh_assert( ! isset( $payload['secret'] ), 'Expected the shared secret not to be transmitted in the request body.' );
crh_assert( 15 === count( $payload['headers'] ?? array() ), 'Expected the established 15-column spreadsheet header contract.' );
crh_assert( 15 === count( $payload['row'] ?? array() ), 'Expected the row width to match the spreadsheet headers.' );
crh_assert( '2026-07-09T17:12:00+00:00' === ( $payload['row'][0] ?? '' ), 'Expected the original submission timestamp in ISO-8601 format.' );
crh_assert( 'Events' === ( $payload['row'][1] ?? '' ), 'Expected the configured legacy opportunity type label.' );
crh_assert( 'Andrew Hughes' === ( $payload['row'][2] ?? '' ), 'Expected the mapped submitter name.' );
crh_assert( 'Test event' === ( $payload['row'][3] ?? '' ), 'Expected the mapped opportunity title.' );
crh_assert( '2026-08-15' === ( $payload['row'][5] ?? '' ), 'Expected the primary opportunity date.' );
crh_assert( '1:00 PM - 2:00 PM' === ( $payload['row'][7] ?? '' ), 'Expected the combined time range.' );
crh_assert( '123 Main St, Spokane, WA, 99201, United States' === ( $payload['row'][8] ?? '' ), 'Expected the mapped one-line address.' );
crh_assert( 'A test event description.' === ( $payload['row'][10] ?? '' ), 'Expected the form description in the spreadsheet row.' );
crh_assert( 'synced' === ( $GLOBALS['crh_post_meta'][50]['wm_bci_google_sync_status'] ?? '' ), 'Expected explicit endpoint success to persist the synced status.' );

$GLOBALS['crh_http_requests'] = array();
$GLOBALS['crh_http_queue']    = array(
	crh_http_response( 200, '{"ok":false,"error":"Missing request signature."}' ),
);

crh_assert( false === $manager->sync_opportunity( 50 ), 'Expected an Apps Script logical error to fail even when the HTTP status is 200.' );
crh_assert( 'error' === ( $GLOBALS['crh_post_meta'][50]['wm_bci_google_sync_status'] ?? '' ), 'Expected the logical endpoint error to persist an error status.' );
crh_assert( 'Missing request signature.' === ( $GLOBALS['crh_post_meta'][50]['wm_bci_google_sync_error'] ?? '' ), 'Expected the endpoint error message to be retained for diagnosis.' );

$GLOBALS['crh_http_requests'] = array();
$GLOBALS['crh_http_queue']    = array(
	crh_http_response( 302, '', array( 'Location' => 'https://script.googleusercontent.com/macros/echo?user_content_key=test' ) ),
	crh_http_response( 200, '{"ok":true,"disposition":"duplicate","entryId":302}' ),
);

crh_assert( true === $manager->sync_opportunity( 50 ), 'Expected Apps Script content redirects to resolve to an explicit success response.' );
crh_assert( array( 'POST', 'GET' ) === array_column( $GLOBALS['crh_http_requests'], 'method' ), 'Expected the initial POST redirect to be followed with a GET.' );

$GLOBALS['crh_http_requests'] = array();
$GLOBALS['crh_http_queue']    = array(
	crh_http_response( 302, '', array( 'Location' => 'http://example.com/unsafe' ) ),
);

crh_assert( false === $manager->sync_opportunity( 50 ), 'Expected a non-HTTPS redirect to fail safely.' );
crh_assert( 'error' === ( $GLOBALS['crh_post_meta'][50]['wm_bci_google_sync_status'] ?? '' ), 'Expected an unsafe redirect to persist an error status.' );
crh_assert( 'Google sync returned an unsafe redirect.' === ( $GLOBALS['crh_post_meta'][50]['wm_bci_google_sync_error'] ?? '' ), 'Expected the unsafe redirect error to remain diagnosable.' );

$GLOBALS['crh_entry']['22']     = 'Pending';
$GLOBALS['crh_http_requests']   = array();
$GLOBALS['crh_http_queue']      = array(
	crh_http_response( 200, '{"ok":true,"disposition":"appended","entryId":302}' ),
);

crh_assert( false === $manager->sync_opportunity( 50 ), 'Expected a non-approved source entry not to be exported.' );
crh_assert( 0 === count( $GLOBALS['crh_http_requests'] ), 'Expected approval validation to happen before the outbound request.' );
crh_assert( 'Only approved BCI entries can be synced.' === ( $GLOBALS['crh_post_meta'][50]['wm_bci_google_sync_error'] ?? '' ), 'Expected a clear error for an unapproved source entry.' );

fwrite( STDOUT, "Google sync manager contract test passed.\n" );
