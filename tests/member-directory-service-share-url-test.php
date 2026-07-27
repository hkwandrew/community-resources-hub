<?php
/**
 * Smoke test for member directory share URL payload tokens.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_transients'] = array();
$GLOBALS['crh_posts']      = array(
	77 => array(
		'ID'           => 77,
		'post_type'    => 'bci_member',
		'post_status'  => 'publish',
		'post_title'   => 'Native Project',
		'post_content' => '<h2>Member overview</h2><p>Intro with <strong>bold text</strong> and <a href="https://example.test">a link</a>.</p><ul><li>First service</li></ul><script>alert("xss")</script>',
	),
);
$GLOBALS['crh_post_meta']  = array(
	77 => array(
		'wm_bci_member_hero_background_color' => '#133358',
		'wm_bci_member_programs'              => 'Program intro with <strong>support</strong> and <a href="https://example.test/programs">a link</a>.' . "\n\n" . 'Second program paragraph.<script>alert("programs")</script>',
	),
);

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
		return preg_replace( '#<script\b[^>]*>.*?</script>#is', '', (string) $html );
	}
}

if ( ! function_exists( 'wpautop' ) ) {
	function wpautop( $text ) {
		$text = trim( (string) $text );

		if ( '' === $text ) {
			return '';
		}

		$paragraphs = preg_split( "/\n\s*\n/", $text );
		$paragraphs = is_array( $paragraphs ) ? $paragraphs : array();

		return implode(
			'',
			array_map(
				static function ( $paragraph ) {
					return '<p>' . trim( $paragraph ) . '</p>';
				},
				$paragraphs
			)
		);
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value ) {
		return 'the_content' === $hook_name ? wpautop( $value ) : $value;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['crh_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['crh_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		return array_keys( $GLOBALS['crh_posts'] );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		return $GLOBALS['crh_post_meta'][ $post_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id, $context = 'display' ) {
		return $GLOBALS['crh_posts'][ $post_id ][ $field ] ?? '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post_id ) {
		return 'https://example.test/bci_member/native-project/';
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-member-directory-service.php';

$service = new WatersMeet\CommunityResourcesHub\FrontEnd\MemberDirectoryService();
$items   = $service->all();
$member  = $items[0] ?? array();

if ( 'native-project' !== ( $member['slug'] ?? '' ) ) {
	fwrite( STDERR, "Expected member payloads to keep the title slug for filtering.\n" );
	exit( 1 );
}

if ( 'native-project-77' !== ( $member['shareSlug'] ?? '' ) ) {
	fwrite( STDERR, "Expected member payloads to include unique share slugs for modal URLs.\n" );
	exit( 1 );
}

if ( '#133358' !== ( $member['heroBackgroundColor'] ?? '' ) ) {
	fwrite( STDERR, "Expected member payloads to include sanitized hero background colors.\n" );
	exit( 1 );
}

$overview_html = $member['overviewHtml'] ?? '';

foreach ( array( '<h2>Member overview</h2>', '<strong>bold text</strong>', '<a href="https://example.test">a link</a>', '<li>First service</li>' ) as $expected_fragment ) {
	if ( false === strpos( $overview_html, $expected_fragment ) ) {
		fwrite( STDERR, "Expected member overviewHtml to retain WYSIWYG markup: {$expected_fragment}.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $overview_html, '<script' ) || false !== strpos( $overview_html, 'alert("xss")' ) ) {
	fwrite( STDERR, "Expected member overviewHtml to remove unsafe script content.\n" );
	exit( 1 );
}

$programs_html = $member['programsHtml'] ?? '';

foreach ( array( '<p>Program intro with <strong>support</strong> and <a href="https://example.test/programs">a link</a>.</p>', '<p>Second program paragraph.</p>' ) as $expected_fragment ) {
	if ( false === strpos( $programs_html, $expected_fragment ) ) {
		fwrite( STDERR, "Expected member programsHtml to retain formatted WYSIWYG markup: {$expected_fragment}.\n" );
		exit( 1 );
	}
}

if ( false !== strpos( $programs_html, '<script' ) || false !== strpos( $programs_html, 'alert("programs")' ) ) {
	fwrite( STDERR, "Expected member programsHtml to remove unsafe script content.\n" );
	exit( 1 );
}

$GLOBALS['crh_transients'] = array();
$GLOBALS['crh_post_meta'][77]['wm_bci_member_hero_background_color'] = 'background:url(https://example.test/x)';
$items = $service->all();
$member = $items[0] ?? array();

if ( '' !== ( $member['heroBackgroundColor'] ?? '' ) ) {
	fwrite( STDERR, "Expected invalid member hero background colors to be omitted from the payload.\n" );
	exit( 1 );
}

echo "Member directory share URL payload test passed.\n";
