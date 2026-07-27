<?php
/**
 * Smoke tests for the opportunity hub renderer.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_terms']     = array();
$GLOBALS['crh_term_meta'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return esc_url( $url );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return esc_attr( $text );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title );
		return trim( (string) $title, '-' );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return (string) $html;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'wp_unique_id' ) ) {
	function wp_unique_id( $prefix = '' ) {
		static $id = 0;
		$id++;
		return (string) $prefix . $id;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] ?? array() )
			? $GLOBALS['crh_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'shortcode_exists' ) ) {
	function shortcode_exists( $tag ) {
		return ! empty( $GLOBALS['crh_shortcodes'][ $tag ] );
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = is_array( $atts ) ? $atts : array();
		$out  = $pairs;

		foreach ( $pairs as $name => $default ) {
			if ( array_key_exists( $name, $atts ) ) {
				$out[ $name ] = $atts[ $name ];
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) {
		$GLOBALS['crh_shortcode_calls'][] = (string) $content;

		return '<div data-rendered-shortcode="' . esc_attr( $content ) . '"></div>';
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		if ( array_key_exists( $transient, $GLOBALS['crh_transients'] ?? array() ) ) {
			return $GLOBALS['crh_transients'][ $transient ];
		}

		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		return array();
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		$taxonomy = is_array( $args ) ? (string) ( $args['taxonomy'] ?? '' ) : '';
		$terms    = array();

		foreach ( $GLOBALS['crh_terms'] as $term ) {
			if ( '' !== $taxonomy && ( $term['taxonomy'] ?? '' ) !== $taxonomy ) {
				continue;
			}

			$terms[] = $term;
		}

		return $terms;
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $meta_key, $single = false ) {
		return $GLOBALS['crh_term_meta'][ $term_id ][ $meta_key ] ?? '';
	}
}

require_once dirname( __DIR__ ) . '/includes/frontend/class-opportunity-hub-renderer.php';
require_once dirname( __DIR__ ) . '/includes/shortcodes/class-shortcodes.php';
require_once dirname( __DIR__ ) . '/includes/template-tags.php';

function crh_render_without_warnings( $callback, $message ) {
	set_error_handler(
		static function ( $severity, $error, $file, $line ) {
			if ( 0 === ( error_reporting() & $severity ) ) {
				return false;
			}

			throw new ErrorException( $error, 0, $severity, $file, $line );
		}
	);

	try {
		$result = $callback();
	} catch ( Throwable $exception ) {
		restore_error_handler();
		fwrite( STDERR, $message . ' ' . $exception->getMessage() . "\n" );
		exit( 1 );
	}

	restore_error_handler();

	return $result;
}

function crh_assert_cta( $html, $attribute, $label ) {
	$attribute = preg_quote( (string) $attribute, '/' );
	$label     = preg_quote( (string) $label, '/' );
	$pattern   = '/<(?:a|button)\b(?=[^>]*' . $attribute . '(?:[\s=>]))[^>]*>.*?' . $label . '.*?<\/(?:a|button)>/s';

	if ( ! preg_match( $pattern, $html ) ) {
		fwrite( STDERR, 'Expected "' . str_replace( '\\', '', $label ) . "\" CTA to render with the requested data attribute.\n" );
		exit( 1 );
	}
}

function crh_assert_member_filter_option( $html, $slug, $label ) {
	$slug    = preg_quote( (string) $slug, '/' );
	$label   = preg_quote( (string) $label, '/' );
	$pattern = '/<label\b[^>]*class="wm-bci-opportunities__member-option"[^>]*>\s*<input\b(?=[^>]*value="' . $slug . '")(?=[^>]*data-wm-bci-member-checkbox)[^>]*>\s*<span>' . $label . '<\/span>/s';

	if ( ! preg_match( $pattern, $html ) ) {
		fwrite( STDERR, 'Expected member filter option "' . str_replace( '\\', '', $label ) . '" for slug "' . str_replace( '\\', '', $slug ) . '".' . "\n" );
		exit( 1 );
	}
}

function crh_assert_type_filter_option( $html, $input_attribute, $slug, $label ) {
	$input_attribute = preg_quote( (string) $input_attribute, '/' );
	$slug            = preg_quote( (string) $slug, '/' );
	$label           = preg_quote( (string) $label, '/' );
	$pattern         = '/<label\b[^>]*>\s*<input\b(?=[^>]*value="' . $slug . '")(?=[^>]*' . $input_attribute . ')[^>]*>\s*<span>' . $label . '<\/span>/s';

	if ( ! preg_match( $pattern, $html ) ) {
		fwrite( STDERR, 'Expected counted type filter option "' . str_replace( '\\', '', $label ) . '" for slug "' . str_replace( '\\', '', $slug ) . '".' . "\n" );
		exit( 1 );
	}
}

function crh_assert_calendar_member_filter_option( $html, $slug, $label ) {
	$slug    = preg_quote( (string) $slug, '/' );
	$label   = preg_quote( (string) $label, '/' );
	$pattern = '/<label\b[^>]*class="wm-bci-calendar-toolbar-filter__option"[^>]*>\s*<input\b(?=[^>]*value="' . $slug . '")(?=[^>]*data-wm-bci-calendar-member-filter-checkbox)[^>]*>\s*<span>' . $label . '<\/span>/s';

	if ( ! preg_match( $pattern, $html ) ) {
		fwrite( STDERR, 'Expected calendar member option "' . str_replace( '\\', '', $label ) . '" for slug "' . str_replace( '\\', '', $slug ) . '".' . "\n" );
		exit( 1 );
	}
}

$html = WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_opportunity_hub(
	array(
		'anchor'       => 'community-resources',
		'introContent' => '<p>Community intro</p>',
	)
);

if ( false === strpos( $html, 'id="community-resources"' ) ) {
	fwrite( STDERR, "Expected renderer to preserve the shortcode anchor.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'data-wm-bci-opportunities' ) ) {
	fwrite( STDERR, "Expected renderer to output the opportunities surface.\n" );
	exit( 1 );
}

if ( false === strpos( $html, '>Search Resources and Recommended Vendors<' ) ) {
	fwrite( STDERR, "Expected the cards heading to name only resources and recommended vendors.\n" );
	exit( 1 );
}

if ( false === strpos( $html, '>No resources or recommended vendors match these filters.<' ) ) {
	fwrite( STDERR, "Expected the empty-state title to name only the card content types.\n" );
	exit( 1 );
}

foreach (
	array(
		'class="wm-bci-opportunity-modal__badge" data-wm-bci-modal-type-badge hidden',
		'class="wm-bci-opportunity-modal__location-badge" data-wm-bci-modal-location-mode hidden',
		'class="wm-bci-opportunity-modal__location-text" data-wm-bci-modal-address',
	) as $modal_markup_fragment
) {
	if ( false === strpos( $html, $modal_markup_fragment ) ) {
		fwrite( STDERR, "Expected opportunity modal markup to include {$modal_markup_fragment}.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $html, 'data-wm-bci-date-filter' ) || false !== strpos( $html, 'data-wm-bci-default-date-preset' ) ) {
	fwrite( STDERR, "Expected the non-date-sensitive card section to omit the date filter contract entirely.\n" );
	exit( 1 );
}

crh_assert_cta( $html, 'data-wm-bci-modal-visit', 'Visit Website' );
crh_assert_cta( $html, 'data-wm-bci-modal-calendar', 'Add to Calendar' );

if ( ! preg_match( '/<a\b(?=[^>]*data-wm-bci-modal-visit)(?=[^>]*hidden)(?![^>]*href=)[^>]*>/s', $html ) ) {
	fwrite( STDERR, "Expected the Visit Website modal placeholder to start hidden without a hash href.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<a\b(?=[^>]*data-wm-bci-modal-calendar)(?=[^>]*hidden)(?![^>]*href=)[^>]*>/s', $html ) ) {
	fwrite( STDERR, "Expected the Add to Calendar modal placeholder to start hidden without a hash href.\n" );
	exit( 1 );
}

$GLOBALS['crh_transients'] = array(
	'community_resources_hub_approved_opportunities' => array(
		array(
			'id'               => 19,
			'title'            => 'Test Opportunity',
			'shareSlug'        => 'test-opportunity-19',
			'typeLabel'        => 'Resource',
			'typeBadgeLabel'   => 'Resources',
			'typeSlug'         => 'resource',
			'typeColor'        => '#238a9a',
			'isBciUpdate'      => true,
			'submittedDateLabel' => 'July 13, 2026',
			'organization'     => 'Test Organization',
			'primaryDate'      => '2999-01-01',
			'primaryDateLabel' => 'January 1, 2999',
			'endDate'          => '',
			'detailDateLabel'  => 'January 1, 2999',
			'timeRange'        => '9:00 AM',
			'locationMode'     => 'Online',
			'address'          => '',
			'cost'             => 'Free',
			'description'      => 'A test opportunity.',
			'infoUrl'          => 'https://example.com',
			'attachments'      => array(),
			'memberSlug'       => 'test-organization',
			'memberLabel'      => 'Test Organization',
			'addToCalendarUrl' => 'https://example.com/calendar.ics',
		),
		array(
			'id'               => 20,
			'title'            => 'Fallback Opportunity',
			'shareSlug'        => 'fallback-opportunity-20',
			'typeLabel'        => 'Other',
			'typeBadgeLabel'   => '',
			'typeSlug'         => 'other',
			'typeColor'        => '#5c6e7a',
			'organization'     => 'Fallback Organization',
			'primaryDate'      => '2999-02-01',
			'primaryDateLabel' => 'February 1, 2999',
			'endDate'          => '',
			'detailDateLabel'  => 'February 1, 2999',
			'timeRange'        => '',
			'locationMode'     => '',
			'address'          => '',
			'cost'             => '',
			'description'      => 'A fallback opportunity.',
			'infoUrl'          => 'https://example.com/fallback',
			'attachments'      => array(),
			'memberSlug'       => 'fallback-organization',
			'memberLabel'      => 'Fallback Organization',
			'addToCalendarUrl' => '',
		),
		array(
			'id'               => 25,
			'title'            => 'Test Organization Event',
			'shareSlug'        => 'test-organization-event-25',
			'typeLabel'        => 'Event',
			'typeBadgeLabel'   => 'Events',
			'typeSlug'         => 'event',
			'typeColor'        => '#c2385a',
			'organization'     => 'Test Organization',
			'primaryDate'      => '2999-02-15',
			'primaryDateLabel' => 'February 15, 2999',
			'endDate'          => '',
			'detailDateLabel'  => 'February 15, 2999',
			'timeRange'        => '10:00 AM',
			'locationMode'     => 'Online',
			'address'          => '',
			'cost'             => 'Free',
			'description'      => 'A date-sensitive test event.',
			'infoUrl'          => 'https://example.com/event',
			'attachments'      => array(),
			'memberSlug'       => 'test-organization',
			'memberLabel'      => 'Test Organization',
			'addToCalendarUrl' => 'https://example.com/event.ics',
		),
		array(
			'id'               => 21,
			'title'            => 'Recommended Vendor Example',
			'shareSlug'        => 'recommended-vendor-example-21',
			'typeLabel'        => 'Recommended Vendor',
			'typeBadgeLabel'   => 'Recommended Vendors',
			'typeSlug'         => 'recommended-vendor',
			'typeColor'        => '#7e5f8e',
			'organization'     => 'Vendor Organization',
			'primaryDate'      => '',
			'primaryDateLabel' => '',
			'endDate'          => '',
			'detailDateLabel'  => '',
			'timeRange'        => '',
			'locationMode'     => '',
			'address'          => '',
			'cost'             => '',
			'description'      => 'An evergreen recommended vendor.',
			'infoUrl'          => 'https://example.com/vendor',
			'attachments'      => array(),
			'memberSlug'       => 'vendor-organization',
			'memberLabel'      => 'Vendor Organization',
			'addToCalendarUrl' => '',
			'isEvergreen'      => true,
		),
		array(
			'id'               => 22,
			'title'            => 'Waters Meet Resource',
			'shareSlug'        => 'waters-meet-resource-22',
			'typeLabel'        => 'Resource',
			'typeBadgeLabel'   => 'Resources',
			'typeSlug'         => 'resource',
			'typeColor'        => '#5c6e7a',
			'organization'     => 'Waters Meet',
			'primaryDate'      => '2999-03-01',
			'primaryDateLabel' => 'March 1, 2999',
			'endDate'          => '',
			'detailDateLabel'  => 'March 1, 2999',
			'memberSlug'       => '',
			'memberLabel'      => '',
		),
		array(
			'id'               => 23,
			'title'            => 'Waters Meet Action Fund Resource',
			'shareSlug'        => 'waters-meet-action-fund-resource-23',
			'typeLabel'        => 'Resource',
			'typeBadgeLabel'   => 'Resources',
			'typeSlug'         => 'resource',
			'typeColor'        => '#5c6e7a',
			'organization'     => 'Waters Meet Action Fund',
			'primaryDate'      => '2999-03-02',
			'primaryDateLabel' => 'March 2, 2999',
			'endDate'          => '',
			'detailDateLabel'  => 'March 2, 2999',
			'memberSlug'       => '',
			'memberLabel'      => '',
		),
		array(
			'id'               => 24,
			'title'            => 'Waters Meet Foundation Resource',
			'shareSlug'        => 'waters-meet-foundation-resource-24',
			'typeLabel'        => 'Resource',
			'typeBadgeLabel'   => 'Resources',
			'typeSlug'         => 'resource',
			'typeColor'        => '#5c6e7a',
			'organization'     => 'Waters Meet Foundation',
			'primaryDate'      => '2999-03-03',
			'primaryDateLabel' => 'March 3, 2999',
			'endDate'          => '',
			'detailDateLabel'  => 'March 3, 2999',
			'memberSlug'       => '',
			'memberLabel'      => '',
		),
	),
	'community_resources_hub_member_directory' => array(
		array(
			'id'    => 4,
			'title' => 'Alpha Organization',
			'slug'  => 'alpha-organization',
		),
		array(
			'id'    => 5,
			'title' => 'Beta Organization',
			'slug'  => 'beta-organization',
		),
		array(
			'id'    => 6,
			'title' => 'Gamma Organization',
			'slug'  => 'gamma-organization',
		),
		array(
			'id'    => 7,
			'title' => 'Test Organization',
			'slug'  => 'test-organization',
		),
		array(
			'id'    => 8,
			'title' => 'Zero Organization',
			'slug'  => 'zero-organization',
		),
	),
);

$html = WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_opportunity_hub(
	array(
		'calendar_html' => '<div data-test-calendar></div>',
	)
);

$grid_type_filter_position   = strpos( $html, 'data-wm-bci-type-filter-button' );
$grid_member_filter_position = strpos( $html, 'data-wm-bci-member-filter-button' );

if (
	false === $grid_type_filter_position
	|| false === $grid_member_filter_position
	|| $grid_type_filter_position >= $grid_member_filter_position
) {
	fwrite( STDERR, "Expected the Opportunity grid All Types filter to render before All Members.\n" );
	exit( 1 );
}

crh_assert_member_filter_option( $html, 'waters-meet', 'Waters Meet (3)' );
crh_assert_member_filter_option( $html, 'test-organization', 'Test Organization (1)' );
crh_assert_member_filter_option( $html, 'zero-organization', 'Zero Organization' );
crh_assert_type_filter_option( $html, 'data-wm-bci-type-filter-input', 'resource', 'Resources (4)' );
crh_assert_type_filter_option( $html, 'data-wm-bci-type-filter-input', 'recommended-vendor', 'Recommended Vendors (1)' );
crh_assert_type_filter_option( $html, 'data-wm-bci-calendar-filter-checkbox', 'event', 'Events (1)' );
crh_assert_type_filter_option( $html, 'data-wm-bci-calendar-filter-checkbox', 'grant-rfp', 'Grants' );
crh_assert_type_filter_option( $html, 'data-wm-bci-calendar-filter-checkbox', 'learning', 'Learning' );
crh_assert_type_filter_option( $html, 'data-wm-bci-calendar-filter-checkbox', 'other', 'Other (1)' );
crh_assert_calendar_member_filter_option( $html, 'test-organization', 'Test Organization (1)' );
crh_assert_calendar_member_filter_option( $html, 'zero-organization', 'Zero Organization' );

if (
	false === strpos( $html, 'data-wm-bci-calendar-clear-filters' )
	|| false === strpos( $html, 'data-wm-bci-clear-filters' )
) {
	fwrite( STDERR, "Expected both filter surfaces to render a conditional Clear control.\n" );
	exit( 1 );
}

if (
	! preg_match( '/<button\b(?=[^>]*data-wm-bci-calendar-clear-filters)(?=[^>]*hidden)[^>]*>\s*Clear\s*<\/button>/s', $html )
	|| ! preg_match( '/<button\b(?=[^>]*data-wm-bci-clear-filters)(?=[^>]*hidden)[^>]*>\s*Clear\s*<\/button>/s', $html )
) {
	fwrite( STDERR, "Expected both Clear controls to start hidden as native buttons.\n" );
	exit( 1 );
}

if ( ! preg_match_all( '/<div\b[^>]*data-wm-bci-member-column="([12])"[^>]*>(.*?)<\/div>/s', $html, $grid_member_columns, PREG_SET_ORDER ) || 2 !== count( $grid_member_columns ) ) {
	fwrite( STDERR, "Expected grid members to render in two explicit columns.\n" );
	exit( 1 );
}

$first_grid_member_column  = $grid_member_columns[0][2];
$second_grid_member_column = $grid_member_columns[1][2];

if (
	! ( strpos( $first_grid_member_column, 'Alpha Organization' ) < strpos( $first_grid_member_column, 'Beta Organization' )
		&& strpos( $first_grid_member_column, 'Beta Organization' ) < strpos( $first_grid_member_column, 'Gamma Organization' ) )
	|| false !== strpos( $first_grid_member_column, 'Test Organization (1)' )
	|| ! ( strpos( $second_grid_member_column, 'Test Organization (1)' ) < strpos( $second_grid_member_column, 'Waters Meet (3)' )
		&& strpos( $second_grid_member_column, 'Waters Meet (3)' ) < strpos( $second_grid_member_column, 'Zero Organization' ) )
) {
	fwrite( STDERR, "Expected grid member DOM order to run alphabetically down column one and then column two.\n" );
	exit( 1 );
}

if ( ! preg_match_all( '/<div\b[^>]*data-wm-bci-calendar-member-column="([12])"[^>]*>(.*?)<\/div>/s', $html, $calendar_member_columns, PREG_SET_ORDER ) || 2 !== count( $calendar_member_columns ) ) {
	fwrite( STDERR, "Expected calendar members to render in two explicit columns.\n" );
	exit( 1 );
}

$first_calendar_column  = $calendar_member_columns[0][2];
$second_calendar_column = $calendar_member_columns[1][2];

if (
	! ( strpos( $first_calendar_column, 'Alpha Organization' ) < strpos( $first_calendar_column, 'Beta Organization' )
		&& strpos( $first_calendar_column, 'Beta Organization' ) < strpos( $first_calendar_column, 'Gamma Organization' ) )
	|| false !== strpos( $first_calendar_column, 'Test Organization (1)' )
	|| ! ( strpos( $second_calendar_column, 'Test Organization (1)' ) < strpos( $second_calendar_column, 'Waters Meet' )
		&& strpos( $second_calendar_column, 'Waters Meet' ) < strpos( $second_calendar_column, 'Zero Organization' ) )
) {
	fwrite( STDERR, "Expected calendar member DOM order to run alphabetically down column one and then column two.\n" );
	exit( 1 );
}

if (
	preg_match( '/data-wm-bci-calendar-member-filter-checkbox[^>]*>.*?<span>[^<]+ \(0\)<\/span>/s', $html )
) {
	fwrite( STDERR, "Expected calendar zero-result member options to omit the count.\n" );
	exit( 1 );
}

if (
	false === strpos( $html, 'data-wm-bci-calendar-filter-dimension="type"' )
	|| false === strpos( $html, 'data-wm-bci-calendar-filter-dimension="member"' )
	|| false === strpos( $html, 'data-wm-bci-calendar-member-filter-checkbox' )
) {
	fwrite( STDERR, "Expected independent Calendar type and Member filter source markup.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<script\b[^>]*data-wm-bci-opportunities-payload[^>]*>(.*?)<\/script>/s', $html, $payload_matches ) ) {
	fwrite( STDERR, "Expected the renderer to expose the card payload.\n" );
	exit( 1 );
}

$card_payload = json_decode( html_entity_decode( $payload_matches[1], ENT_QUOTES, 'UTF-8' ), true );
$card_slugs   = array_values( array_unique( array_column( $card_payload['opportunities'] ?? array(), 'typeSlug' ) ) );

sort( $card_slugs );

if ( array( 'recommended-vendor', 'resource' ) !== $card_slugs ) {
	fwrite( STDERR, "Expected the public card payload to contain only Resource and Recommended Vendor entries.\n" );
	exit( 1 );
}

if ( preg_match( '/<label\b[^>]*class="wm-bci-opportunities__member-option"[^>]*>.*?<span>Zero Organization \(0\)<\/span>/s', $html ) ) {
	fwrite( STDERR, "Expected zero-result member options to omit the count.\n" );
	exit( 1 );
}

foreach ( array( 22, 23, 24 ) as $waters_meet_opportunity_id ) {
	if ( ! preg_match( '/<article\b(?=[^>]*data-opportunity-id="' . $waters_meet_opportunity_id . '")(?=[^>]*data-member-slug="waters-meet")[^>]*>/s', $html ) ) {
		fwrite( STDERR, "Expected every Waters Meet organization variant to use the Waters Meet member filter slug.\n" );
		exit( 1 );
	}
}

crh_assert_cta( $html, 'data-wm-bci-opportunity-open', 'View Full Details' );
crh_assert_cta( $html, 'data-wm-bci-load-more', 'Load More' );

if ( false === strpos( $html, 'data-wm-bci-opportunity-share-url="?bci-opportunity=test-opportunity-19"' ) ) {
	fwrite( STDERR, "Expected opportunity detail triggers to expose a unique share URL.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<span\b[^>]*>Resources<\/span>/', $html ) ) {
	fwrite( STDERR, "Expected resource cards to use the public type badge label.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<p class="wm-bci-opportunity-card__meta-row">\s*<strong>Type:<\/strong>\s*<span>Resource<\/span>\s*<\/p>/', $html ) ) {
	fwrite( STDERR, "Expected rendered resource cards to retain their primary Type row.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<span\b(?=[^>]*wm-bci-type-badge--bci-update)(?=[^>]*background-color: #004966)[^>]*>BCI Update<\/span>/', $html ) ) {
	fwrite( STDERR, "Expected tagged cards to render a secondary BCI Update badge in the approved color.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'class="wm-bci-opportunity-card__badges"' ) ) {
	fwrite( STDERR, "Expected each card's primary and secondary badges to share the badge group.\n" );
	exit( 1 );
}

if ( false !== strpos( $html, '<strong>Date:</strong>' ) || false !== strpos( $html, '<strong>Time:</strong>' ) ) {
	fwrite( STDERR, "Expected non-date-sensitive cards to omit date and time metadata.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<article\b(?=[^>]*data-opportunity-id="21")(?![^>]*\shidden\b)[^>]*>/s', $html ) ) {
	fwrite( STDERR, "Expected an undated Recommended Vendor card to be visible in the initial card view.\n" );
	exit( 1 );
}

if ( false !== strpos( $html, 'data-opportunity-id="20"' ) ) {
	fwrite( STDERR, "Expected date-sensitive Other entries to be absent from the card grid and card payload.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'data-wm-bci-submit-modal-layer' ) ) {
	fwrite( STDERR, "Expected calendar output to be wrapped by the submit modal layer.\n" );
	exit( 1 );
}

if ( strpos( $html, 'data-wm-bci-submit-modal-layer' ) > strpos( $html, 'data-wm-bci-calendar-region' ) ) {
	fwrite( STDERR, "Expected calendar region to render inside the submit modal layer.\n" );
	exit( 1 );
}

$GLOBALS['crh_terms'] = array(
	array(
		'term_id'  => 13,
		'name'     => 'Other',
		'slug'     => 'other',
		'taxonomy' => 'opportunity-type',
	),
	array(
		'term_id'  => 11,
		'name'     => 'Workshop, Training, or Other Learning',
		'slug'     => 'learning',
		'taxonomy' => 'opportunity-type',
	),
	array(
		'term_id'  => 12,
		'name'     => 'Community Event',
		'slug'     => 'event',
		'taxonomy' => 'opportunity-type',
	),
);

$GLOBALS['crh_term_meta'] = array(
	13 => array(
		'alias' => 'Other',
		'color' => '#5c6e7a',
	),
	11 => array(
		'alias' => 'Learning',
		'color' => '#520066',
	),
	12 => array(
		'alias' => 'Events',
		'color' => '#c2385a',
	),
);

$GLOBALS['crh_transients'] = array();

$html = WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_opportunity_hub(
	array(
		'calendar_html' => '<div class="gv-fullcalendar" data-calendar_id="bci"></div>',
	)
);

if ( false === strpos( $html, 'data-wm-bci-calendar-filter-source' ) ) {
	fwrite( STDERR, "Expected renderer to output the calendar filter source when configured event types exist.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'value="learning"' ) || false === strpos( $html, '>Learning<' ) ) {
	fwrite( STDERR, "Expected renderer to output configured Learning event type as a calendar filter option.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<div\b[^>]*data-wm-bci-calendar-filter-panel[^>]*>(.*?)<\/div>/s', $html, $calendar_filter_panel_matches ) ) {
	fwrite( STDERR, "Expected renderer to output the Calendar toolbar filter panel.\n" );
	exit( 1 );
}

$calendar_filter_panel = $calendar_filter_panel_matches[1];
$event_position        = strpos( $calendar_filter_panel, '>Events<' );
$grant_position        = strpos( $calendar_filter_panel, '>Grants<' );
$learning_position     = strpos( $calendar_filter_panel, '>Learning<' );
$other_position        = strpos( $calendar_filter_panel, '>Other<' );

if (
	false === $event_position
	|| false === $grant_position
	|| false === $learning_position
	|| false === $other_position
	|| ! ( $event_position < $grant_position && $grant_position < $learning_position && $learning_position < $other_position )
) {
	fwrite( STDERR, "Expected the Calendar toolbar type source to contain exactly Events, Grants, Learning, and Other in contract order.\n" );
	exit( 1 );
}

foreach ( array( 'resource', 'recommended-vendor', 'bci-update' ) as $forbidden_calendar_type ) {
	if ( false !== strpos( $calendar_filter_panel, 'value="' . $forbidden_calendar_type . '"' ) ) {
		fwrite( STDERR, "Expected non-date-sensitive and BCI Update values to stay out of the Calendar type filter.\n" );
		exit( 1 );
	}
}

if ( ! preg_match( '/<div\b[^>]*data-wm-bci-type-filter-panel[^>]*>(.*?)<\/div>/s', $html, $opportunity_type_filter_panel_matches ) ) {
	fwrite( STDERR, "Expected renderer to output the Opportunity grid type filter panel.\n" );
	exit( 1 );
}

$opportunity_type_filter_panel = $opportunity_type_filter_panel_matches[1];
$all_position                  = strpos( $opportunity_type_filter_panel, '>All Types<' );
$resource_position             = strpos( $opportunity_type_filter_panel, '>Resources<' );
$vendor_position               = strpos( $opportunity_type_filter_panel, '>Recommended Vendors<' );

if (
	false === $all_position
	|| false === $resource_position
	|| false === $vendor_position
	|| ! ( $all_position < $resource_position && $resource_position < $vendor_position )
) {
	fwrite( STDERR, "Expected the card type filter to list All Types, Resources, then Recommended Vendors.\n" );
	exit( 1 );
}

foreach ( array( 'event', 'grant-rfp', 'learning', 'other', 'bci-update' ) as $forbidden_card_type ) {
	if ( false !== strpos( $opportunity_type_filter_panel, 'value="' . $forbidden_card_type . '"' ) ) {
		fwrite( STDERR, "Expected date-sensitive and BCI Update values to stay out of the card type filter.\n" );
		exit( 1 );
	}
}

$GLOBALS['crh_terms']     = array();
$GLOBALS['crh_term_meta'] = array();
$GLOBALS['crh_transients'] = array();

$GLOBALS['crh_shortcodes'] = array( 'gravityform' => true );

$html = WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_opportunity_hub(
	array(
		'form_shortcode' => '[gravityform id=7 title=false description=false ajax=true]',
	)
);

crh_assert_cta( $html, 'data-crh-submit-open', 'Share something' );

if ( preg_match( '/<dialog\b[^>]*data-wm-bci-submit-modal/s', $html ) ) {
	fwrite( STDERR, "Expected submit modal not to use native dialog backdrop outside the calendar layer.\n" );
	exit( 1 );
}

foreach ( array( 'data-wm-bci-submit-modal-layer', 'data-wm-bci-submit-modal-overlay', 'role="dialog"', 'aria-modal="true"' ) as $submit_modal_fragment ) {
	if ( false === strpos( $html, $submit_modal_fragment ) ) {
		fwrite( STDERR, "Expected submit modal markup to include {$submit_modal_fragment}.\n" );
		exit( 1 );
	}
}

foreach (
	array(
		'aria-label="Submit a resource, opportunity, or event"',
		'class="wm-bci-submit-modal__title">Submit a resource, opportunity, or event</h2>',
		'class="wm-bci-submit-modal__intro">Once reviewed by the Waters Meet team, they will be available here on the BCI calendar and below in the resources directory.</p>',
		'data-wm-bci-time-sensitive-field-id="24"',
		'data-wm-bci-description-field-id="17"',
		'data-wm-bci-description-label-time-sensitive="Provide a short description of this opportunity:"',
		'data-wm-bci-description-label-non-date="Provide a short description:"',
	) as $submit_modal_copy_fragment
) {
	if ( false === strpos( $html, $submit_modal_copy_fragment ) ) {
		fwrite( STDERR, "Expected submit modal markup to include the approved copy fragment: {$submit_modal_copy_fragment}.\n" );
		exit( 1 );
	}
}

if ( ! preg_match( '/<div\b[^>]*class="wm-bci-submit-modal"[^>]*data-wm-bci-submit-modal[^>]*hidden/s', $html ) ) {
	fwrite( STDERR, "Expected submit modal to start hidden inside the calendar modal layer.\n" );
	exit( 1 );
}

$html = WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_opportunity_hub(
	array(
		'calendar_html'  => '<div data-test-calendar></div>',
		'form_shortcode' => '[gravityform id=7 title=false description=false ajax=true]',
	)
);

$layer_pos    = strpos( $html, 'data-wm-bci-submit-modal-layer' );
$calendar_pos = strpos( $html, 'data-wm-bci-calendar-region' );
$overlay_pos  = strpos( $html, 'data-wm-bci-submit-modal-overlay' );
$modal_pos    = strpos( $html, 'class="wm-bci-submit-modal"', false !== $overlay_pos ? $overlay_pos : 0 );

if ( false === $layer_pos || false === $calendar_pos || false === $overlay_pos || false === $modal_pos ) {
	fwrite( STDERR, "Expected combined calendar/form output to include layer, calendar, overlay, and modal markup.\n" );
	exit( 1 );
}

if ( ! ( $layer_pos < $calendar_pos && $calendar_pos < $overlay_pos && $overlay_pos < $modal_pos ) ) {
	fwrite( STDERR, "Expected submit modal overlay and modal to render over the calendar inside the same layer.\n" );
	exit( 1 );
}

$GLOBALS['crh_shortcodes'] = array();

$shortcodes = new WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes();
$html       = crh_render_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_opportunity_hub_shortcode(
			array(
				'anchor' => 'shortcode-opportunities',
			),
			'<p>Shortcode intro</p>',
			'community_opportunity_hub'
		);
	},
	'Expected the classic opportunity hub shortcode to render without warnings.'
);

if ( false === strpos( $html, 'id="shortcode-opportunities"' ) ) {
	fwrite( STDERR, "Expected classic shortcode render path to preserve the shortcode anchor.\n" );
	exit( 1 );
}

$html = crh_render_without_warnings(
	static function () {
		return community_resources_hub_render_opportunity_hub(
			array(
				'anchor'        => array( 'unexpected' ),
				'introContent'  => array( '<p>Unexpected array value</p>' ),
				'anchorContent' => array( '<p>Unexpected array value</p>' ),
			)
		);
	},
	'Expected the opportunity hub template tag to ignore non-string context values without warnings.'
);

if ( false !== strpos( $html, '>Array<' ) ) {
	fwrite( STDERR, "Expected template tag render path not to print array-cast context values.\n" );
	exit( 1 );
}

$GLOBALS['crh_options'] = array(
	'options_wm_bci_calendar_shortcode' => '[gravitycalendar id="7" title="false"]',
);
$GLOBALS['crh_shortcodes']      = array( 'gravitycalendar' => true );
$GLOBALS['crh_shortcode_calls'] = array();

$html = WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_opportunity_hub();

if ( ! in_array( '[gravitycalendar id="7" title="false"]', $GLOBALS['crh_shortcode_calls'], true ) ) {
	fwrite( STDERR, "Expected renderer to use the saved GravityCalendar shortcode when context is blank.\n" );
	exit( 1 );
}

$GLOBALS['crh_shortcode_calls'] = array();

$html = WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_opportunity_hub(
	array(
		'calendarShortcode' => '[gravitycalendar id="8"]',
	)
);

if ( ! in_array( '[gravitycalendar id="8"]', $GLOBALS['crh_shortcode_calls'], true ) ) {
	fwrite( STDERR, "Expected context GravityCalendar shortcode to take priority over the saved default.\n" );
	exit( 1 );
}

if ( in_array( '[gravitycalendar id="7" title="false"]', $GLOBALS['crh_shortcode_calls'], true ) ) {
	fwrite( STDERR, "Expected renderer not to use saved GravityCalendar shortcode when context provides one.\n" );
	exit( 1 );
}

echo "Opportunity hub renderer smoke test passed.\n";
