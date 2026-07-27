<?php
/**
 * Smoke tests for BCI GravityCalendar event metadata.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_form_id' => 9,
);
$GLOBALS['crh_terms'] = array(
	15 => array(
		'term_id'  => 15,
		'name'     => 'Workshop, Training, or Other Learning',
		'slug'     => 'learning',
		'taxonomy' => 'opportunity-type',
	),
	20 => array(
		'term_id'  => 20,
		'name'     => 'Other',
		'slug'     => 'other',
		'taxonomy' => 'opportunity-type',
	),
);
$GLOBALS['crh_term_meta'] = array(
	15 => array(
		'alias' => 'Learning',
		'color' => '#520066',
	),
	20 => array(
		'alias' => '',
		'color' => '#5c6e7a',
	),
);
$GLOBALS['crh_member_cache'] = array(
	array(
		'title'   => 'Partner Org',
		'slug'    => 'partner-org',
		'aliases' => array(),
	),
);

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

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
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

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return (string) $html;
	}
}

if ( ! function_exists( 'wpautop' ) ) {
	function wpautop( $text ) {
		return '<p>' . (string) $text . '</p>';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null ) {
		return gmdate( $format, $timestamp ?: time() );
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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] )
			? $GLOBALS['crh_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return 'community_resources_hub_member_directory' === $key
			? $GLOBALS['crh_member_cache']
			: false;
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

if ( ! function_exists( 'rgar' ) ) {
	function rgar( $array, $key, $default = null ) {
		return is_array( $array ) && array_key_exists( $key, $array ) ? $array[ $key ] : $default;
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/calendar/class-event-customizer.php';

$config     = new WatersMeet\CommunityResourcesHub\Config\Config();
$customizer = new WatersMeet\CommunityResourcesHub\Calendar\EventCustomizer( $config );
$linked_description = 'This linked event description is intentionally long so the tooltip can summarize it while a details link is present. It includes enough context for the event card, names the audience, and gives a helpful preview before sending visitors to the source website for the rest of the details. Linked final sentence should not appear when a website is available.';
$unlinked_description = 'This no-link event description is intentionally long because the tooltip is the only place visitors can read the complete details. It includes who the opportunity is for, what people can expect, where to go, why it matters, and several practical notes that would be lost if the text were summarized. No-link final sentence must remain visible.';
$form       = array(
	'id'     => 9,
	'fields' => array(
		array(
			'id'      => '1',
			'choices' => array(
				array(
					'text'  => 'Workshop, Training, or Other Learning',
					'value' => 'Workshop, Training, or Other Learning',
				),
				array(
					'text'  => 'Other',
					'value' => 'Other',
				),
			),
		),
	),
);
$events     = array(
	array(
		'event_id'      => 101,
		'title'         => 'Partner Workshop',
		'start'         => '2026-07-10',
		'url'           => 'https://example.test/events/partner-workshop/',
		'extendedProps' => array(
			'existingProp' => 'preserved',
		),
	),
	array(
		'event_id' => 102,
		'title'    => 'Closed Community Session',
		'start'    => '2026-07-11',
		'url'      => '',
	),
	array(
		'event_id' => 103,
		'title'    => 'Waters Meet Briefing',
		'start'    => '2026-07-12',
		'url'      => '',
	),
	array(
		'event_id' => 104,
		'title'    => 'Shared Infrastructure Pillar Meeting Planning',
		'start'    => '2026-07-13',
		'url'      => '',
	),
);
$entries    = array(
	array(
		'id' => 101,
		'1'  => 'Workshop, Training, or Other Learning',
		'24' => 'Yes',
		'26' => 'Yes',
		'5'  => 'Partner Org',
		'12' => '9:00 AM',
		'17' => $linked_description,
	),
	array(
		'id' => 102,
		'1'  => 'Workshop, Training, or Other Learning',
		'24' => 'Yes',
		'26' => 'No',
		'5'  => 'Partner Org',
		'12' => '10:00 AM',
		'17' => $unlinked_description,
	),
	array(
		'id' => 103,
		'1'  => 'Workshop, Training, or Other Learning',
		'24' => 'Yes',
		'26' => 'No',
		'5'  => 'Waters Meet Action Fund',
		'17' => 'Shared organization filter identity.',
	),
	array(
		'id' => 104,
		'1'  => 'Other',
		'24' => 'Yes',
		'26' => 'Yes',
		'5'  => 'Partner Org',
		'17' => 'Other BCI Update tooltip label fixture.',
	),
);

$customized = $customizer->customize( $events, $form, array(), array(), $entries );
$event      = $customized[0] ?? array();
$no_url_event = $customized[1] ?? array();
$waters_meet_event = $customized[2] ?? array();
$other_bci_update_event = $customized[3] ?? array();

if ( 'learning' !== ( $event['extendedProps']['wmBciTypeValue'] ?? '' ) ) {
	fwrite( STDERR, "Expected BCI calendar events to expose the normalized type value used by the toolbar filter.\n" );
	exit( 1 );
}

if ( 'Learning' !== ( $event['extendedProps']['wmBciTypeLabel'] ?? '' ) ) {
	fwrite( STDERR, "Expected BCI calendar events to expose the public type label.\n" );
	exit( 1 );
}

if ( 'preserved' !== ( $event['extendedProps']['existingProp'] ?? '' ) ) {
	fwrite( STDERR, "Expected existing FullCalendar extended props to be preserved.\n" );
	exit( 1 );
}

if (
	'partner-org' !== ( $event['extendedProps']['wmBciMemberSlug'] ?? '' )
	|| 'Partner Org' !== ( $event['extendedProps']['wmBciMemberLabel'] ?? '' )
) {
	fwrite( STDERR, "Expected BCI calendar events to expose their shared member-filter identity.\n" );
	exit( 1 );
}

if (
	'waters-meet' !== ( $waters_meet_event['extendedProps']['wmBciMemberSlug'] ?? '' )
	|| 'Waters Meet' !== ( $waters_meet_event['extendedProps']['wmBciMemberLabel'] ?? '' )
) {
	fwrite( STDERR, "Expected related Waters Meet organizations to use the shared calendar member identity.\n" );
	exit( 1 );
}

if (
	true !== ( $event['extendedProps']['wmBciIsBciUpdate'] ?? null )
	|| '#004966' !== ( $event['extendedProps']['wmBciUpdateColor'] ?? '' )
) {
	fwrite( STDERR, "Expected tagged calendar events to expose BCI Update metadata and its approved color.\n" );
	exit( 1 );
}

if ( false === strpos( (string) ( $event['description'] ?? '' ), 'wm-bci-type-badge--bci-update' ) || false === strpos( (string) ( $event['description'] ?? '' ), 'BCI Update' ) ) {
	fwrite( STDERR, "Expected tagged calendar tooltips to render the secondary BCI Update badge.\n" );
	exit( 1 );
}

if ( false !== strpos( (string) ( $no_url_event['description'] ?? '' ), 'wm-bci-type-badge--bci-update' ) ) {
	fwrite( STDERR, "Expected untagged calendar tooltips not to render a BCI Update badge.\n" );
	exit( 1 );
}

if (
	false === strpos( (string) ( $other_bci_update_event['description'] ?? '' ), 'wm-bci-calendar-tooltip__eyebrow">Other</span>' )
	|| false !== strpos( (string) ( $other_bci_update_event['description'] ?? '' ), 'wm-bci-calendar-tooltip__eyebrow">BCI Opportunity</span>' )
	|| false === strpos( (string) ( $other_bci_update_event['description'] ?? '' ), 'wm-bci-type-badge--bci-update' )
) {
	fwrite( STDERR, "Expected tagged Other calendar tooltips to show Other as the primary type beside the BCI Update badge.\n" );
	exit( 1 );
}

if ( false === strpos( (string) ( $event['description'] ?? '' ), 'Partner Workshop' ) || false === strpos( (string) ( $event['description'] ?? '' ), 'Partner Org' ) ) {
	fwrite( STDERR, "Expected BCI calendar events to carry tooltip markup with event details.\n" );
	exit( 1 );
}

if ( false === strpos( (string) ( $event['description'] ?? '' ), '...' ) || false !== strpos( (string) ( $event['description'] ?? '' ), 'Linked final sentence should not appear' ) ) {
	fwrite( STDERR, "Expected linked BCI calendar events to keep summarized tooltip descriptions.\n" );
	exit( 1 );
}

if ( false === strpos( (string) ( $no_url_event['description'] ?? '' ), 'No-link final sentence must remain visible' ) ) {
	fwrite( STDERR, "Expected unlinked BCI calendar events to keep the full tooltip description.\n" );
	exit( 1 );
}

if ( false !== strpos( (string) ( $no_url_event['description'] ?? '' ), 'Visit Website' ) || false !== strpos( (string) ( $no_url_event['description'] ?? '' ), '...' ) ) {
	fwrite( STDERR, "Expected unlinked BCI calendar events to avoid teaser-only tooltip behavior.\n" );
	exit( 1 );
}

$unchanged = $customizer->customize( $events, array( 'id' => 10 ), array(), array(), $entries );

if ( $events !== $unchanged ) {
	fwrite( STDERR, "Expected non-BCI GravityCalendar forms to be left unchanged.\n" );
	exit( 1 );
}

echo "Calendar event customizer metadata test passed.\n";
