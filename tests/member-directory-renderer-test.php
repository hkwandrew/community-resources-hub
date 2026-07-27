<?php
/**
 * Smoke tests for the member directory renderer.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_enqueued_scripts'] = array();
$GLOBALS['crh_enqueued_styles']  = array();

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

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
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

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		if ( isset( $GLOBALS['crh_member_directory_fixture'] ) ) {
			return $GLOBALS['crh_member_directory_fixture'];
		}

		return array(
			array(
				'id'           => 7,
				'title'        => 'Test Member',
				'shareSlug'    => 'test-member-7',
				'summary'      => 'Test member summary.',
				'heroImageUrl' => 'https://example.org/member-hero.png',
				'logoUrl'      => 'https://example.org/member-logo.png',
				'contactEmail' => 'hello@example.org',
				'websiteUrl'   => 'https://example.org',
				'videoUrl'     => 'http://localhost:10104/building-connections-initiative/#bci-video-rooted-in-community-scar',
				'attachments'  => array(
					array(
						'url'   => 'https://example.org/member.pdf',
						'label' => 'Member PDF',
					),
				),
			),
			array(
				'id'           => 8,
				'title'        => 'Invalid Video Member',
				'shareSlug'    => 'invalid-video-member-8',
				'summary'      => 'Member without a valid slider target.',
				'heroImageUrl' => '',
				'logoUrl'      => '',
				'contactEmail' => '',
				'websiteUrl'   => '',
				'videoUrl'     => 'http://watersmeet-prod-6-26.local/wp-admin/post.php?post=8&action=edit#bci-video-rooted%22%20onclick%3D%22alert(1)',
				'attachments'  => array(),
			),
			array(
				'id'           => 9,
				'title'        => 'Member Without Video',
				'shareSlug'    => 'member-without-video-9',
				'summary'      => 'Member without a saved video URL.',
				'heroImageUrl' => '',
				'logoUrl'      => '',
				'contactEmail' => '',
				'websiteUrl'   => '',
				'videoUrl'     => '',
				'attachments'  => array(),
			),
		);
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle ) {
		$GLOBALS['crh_enqueued_scripts'][] = $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle ) {
		$GLOBALS['crh_enqueued_styles'][] = $handle;
	}
}

require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-member-directory-service.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-member-directory-assets.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-member-directory-renderer.php';
require_once dirname( __DIR__ ) . '/includes/shortcodes/class-shortcodes.php';

function crh_render_member_directory_without_warnings( $callback, $message ) {
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

function crh_assert_member_cta( $html, $attribute, $label ) {
	$attribute = preg_quote( (string) $attribute, '/' );
	$label     = preg_quote( (string) $label, '/' );
	$pattern   = '/<(?:a|button)\b(?=[^>]*' . $attribute . '(?:[\s=>]))[^>]*>.*?' . $label . '.*?<\/(?:a|button)>/s';

	if ( ! preg_match( $pattern, $html ) ) {
		fwrite( STDERR, 'Expected "' . str_replace( '\\', '', $label ) . "\" CTA to render with the requested data attribute.\n" );
		exit( 1 );
	}
}

$html = crh_render_member_directory_without_warnings(
	static function () {
		return WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_member_directory();
	},
	'Expected the member directory shortcode render path to render without warnings.'
);

if ( false === strpos( $html, 'class="wm-bci-member-directory"' ) ) {
	fwrite( STDERR, "Expected renderer to output the member directory wrapper.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'data-wm-bci-member-directory-payload' ) ) {
	fwrite( STDERR, "Expected renderer to output the member-directory JSON payload.\n" );
	exit( 1 );
}

if ( false !== strpos( $html, 'crh-button' ) || false !== strpos( $html, 'class="utton' ) ) {
	fwrite( STDERR, "Expected member directory CTA markup not to use recreated or misspelled button classes.\n" );
	exit( 1 );
}

if ( false !== strpos( $html, 'wm-bci-member-card__crest' ) || false !== strpos( $html, 'data-wm-bci-member-modal-crest' ) ) {
	fwrite( STDERR, "Expected member cards and modals not to render generated initials crests.\n" );
	exit( 1 );
}

if ( false !== strpos( $html, 'wm-bci-member-card__logo' ) ) {
	fwrite( STDERR, "Expected member cards not to render a separate header logo image.\n" );
	exit( 1 );
}

foreach (
	array(
		'Connect with us:',
		'data-wm-bci-member-modal-email-text',
		'data-wm-bci-member-modal-website-text',
		'data-wm-bci-member-modal-attachments',
		'data-wm-bci-member-modal-actions',
		'data-wm-bci-member-modal-action-website',
		'data-wm-bci-member-modal-action-video',
		'Attachment',
	) as $required_fragment
) {
	if ( false === strpos( $html, $required_fragment ) ) {
		fwrite( STDERR, "Expected member modal markup to include {$required_fragment}.\n" );
		exit( 1 );
	}
}

if ( ! preg_match( '/<div\b[^>]*class="wm-bci-member-modal__copy"[^>]*data-wm-bci-member-modal-overview[^>]*><\/div>/', $html ) ) {
	fwrite( STDERR, "Expected member modal overview to render a block-safe WYSIWYG container.\n" );
	exit( 1 );
}

if ( preg_match( '/<p\b[^>]*data-wm-bci-member-modal-overview/', $html ) ) {
	fwrite( STDERR, "Expected member modal overview not to use a paragraph wrapper for WYSIWYG content.\n" );
	exit( 1 );
}

crh_assert_member_cta( $html, 'data-wm-bci-member-open', 'Learn More' );
crh_assert_member_cta( $html, 'data-wm-bci-member-modal-video', 'Watch Our Spotlight Video' );
crh_assert_member_cta( $html, 'data-wm-bci-member-modal-action-website', 'Visit Website' );
crh_assert_member_cta( $html, 'data-wm-bci-member-modal-action-video', 'Watch Our Spotlight Video' );

if ( ! preg_match( '/<button\b[^>]*data-wm-bci-member-open[^>]*>.*?Learn More.*?<\/button>/s', $html ) ) {
	fwrite( STDERR, "Expected Learn More to remain a modal-opening button.\n" );
	exit( 1 );
}

if ( ! preg_match( '/(<a\b(?=[^>]*class="[^"]*\bwm-bci-member-card__spotlight\b[^"]*")(?=[^>]*href="#bci-video-rooted-in-community-scar")[^>]*>).*?Spotlight Video.*?wm-bci-member-card__spotlight-icon.*?<\/a>/s', $html, $spotlight_match ) ) {
	fwrite( STDERR, "Expected the member spotlight control to link to the normalized video-slider fragment.\n" );
	exit( 1 );
}

if ( false !== strpos( $spotlight_match[1], 'target=' ) ) {
	fwrite( STDERR, "Expected the member spotlight link to stay in the current tab.\n" );
	exit( 1 );
}

if ( 1 !== substr_count( $html, 'class="wm-bci-member-card__spotlight"' ) ) {
	fwrite( STDERR, "Expected only members with valid video-slider fragments to render spotlight links.\n" );
	exit( 1 );
}

if ( false !== strpos( $html, 'href="http://watersmeet-prod-6-26.local/wp-admin/' ) ) {
	fwrite( STDERR, "Expected invalid wp-admin video URLs not to become member-card spotlight links.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'data-wm-bci-member-share-url="?bci-member=test-member-7"' ) ) {
	fwrite( STDERR, "Expected member profile triggers to expose a unique share URL.\n" );
	exit( 1 );
}

if ( false === strpos( $html, '"spotlightHref":"#bci-video-rooted-in-community-scar"' ) ) {
	fwrite( STDERR, "Expected the member payload to expose the same normalized spotlight target used by the Member Card.\n" );
	exit( 1 );
}

$GLOBALS['crh_member_directory_fixture'] = array(
	array(
		'id'           => 10,
		'title'        => 'Direct Video Member',
		'shareSlug'    => 'direct-video-member-10',
		'summary'      => 'Member whose Video URL is the same YouTube video used by a slider card.',
		'heroImageUrl' => '',
		'logoUrl'      => '',
		'contactEmail' => '',
		'websiteUrl'   => '',
		'videoUrl'     => 'https://www.youtube.com/watch?v=-VGOUsqEF1c',
		'attachments'  => array(),
	),
	array(
		'id'           => 11,
		'title'        => 'Unmatched Video Member',
		'shareSlug'    => 'unmatched-video-member-11',
		'summary'      => 'Member whose YouTube video does not exist in the slider.',
		'heroImageUrl' => '',
		'logoUrl'      => '',
		'contactEmail' => '',
		'websiteUrl'   => '',
		'videoUrl'     => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
		'attachments'  => array(),
	),
);

$direct_video_html = crh_render_member_directory_without_warnings(
	static function () {
		$renderer = new WatersMeet\CommunityResourcesHub\FrontEnd\MemberDirectoryRenderer(
			null,
			array(
				array(
					'videoId'      => '',
					'videoUrl'     => 'https://youtu.be/-VGOUsqEF1c?si=test',
					'thumbnailId'  => 100,
					'logoId'       => 200,
					'logoLabel'    => 'Spokane Community Against Racism (SCAR)',
					'slideEyebrow' => 'The Rooted in Community series',
					'slideTitle'   => 'Rooted in Community: SCAR',
				),
			)
		);

		return $renderer->render();
	},
	'Expected member directory rendering to match a Member Video URL to the corresponding Video Slider card.'
);

unset( $GLOBALS['crh_member_directory_fixture'] );

if ( ! preg_match( '/<a\b(?=[^>]*class="[^"]*\bwm-bci-member-card__spotlight\b[^"]*")(?=[^>]*href="#bci-video-rooted-in-community-scar")[^>]*>.*?Spotlight Video.*?<\/a>/s', $direct_video_html ) ) {
	fwrite( STDERR, "Expected a Member YouTube URL to resolve to the corresponding Video Slider fragment.\n" );
	exit( 1 );
}

if ( 1 !== substr_count( $direct_video_html, 'class="wm-bci-member-card__spotlight"' ) ) {
	fwrite( STDERR, "Expected unmatched Member YouTube URLs not to render dead Spotlight Video links.\n" );
	exit( 1 );
}

if ( ! preg_match( '/data-wm-bci-member-modal-socials[^>]*data-wm-bci-member-modal-icon-base="[^"]*assets\/images\/"/', $html ) ) {
	fwrite( STDERR, "Expected the member modal social container to expose the plugin-owned Figma icon asset base.\n" );
	exit( 1 );
}

if ( ! preg_match( '/data-wm-bci-member-modal-email[^>]*>.*?<img\b[^>]*src="[^"]*bci-member-modal-mail\.svg"[^>]*>/s', $html ) ) {
	fwrite( STDERR, "Expected the member modal email row to use the Figma mail asset.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<a\b(?=[^>]*class="[^"]*\bwm-bci-member-modal__video\b[^"]*")(?=[^>]*data-wm-bci-member-modal-video)[^>]*>.*?wm-bci-member-modal__video-label.*?wm-bci-member-modal__video-icon.*?bci-member-modal-play\.png.*?<\/a>/s', $html, $modal_video_match ) ) {
	fwrite( STDERR, "Expected the primary member modal video control to use the Figma label and play-asset structure.\n" );
	exit( 1 );
}

if ( false !== strpos( $modal_video_match[0], 'target=' ) ) {
	fwrite( STDERR, "Expected the primary member modal video control to stay in the current tab like the Member Card control.\n" );
	exit( 1 );
}

$member_directory_styles = file_get_contents( dirname( __DIR__ ) . '/blocks/member-directory/style.scss' );

foreach (
	array(
		'.wm-bci-member-modal__programs > *',
		'.wm-bci-member-modal__programs h1',
		'.wm-bci-member-modal__programs p',
		'.wm-bci-member-modal__programs a',
		'.wm-bci-member-modal__programs strong',
	) as $required_style_fragment
) {
	if ( false === strpos( (string) $member_directory_styles, $required_style_fragment ) ) {
		fwrite( STDERR, "Expected member modal Programs WYSIWYG styles to include {$required_style_fragment}.\n" );
		exit( 1 );
	}
}

$html = crh_render_member_directory_without_warnings(
	static function () {
		return WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_member_directory(
			array(
				'anchor' => 'Partner Directory',
			)
		);
	},
	'Expected the member directory shortcode render path to preserve anchors without warnings.'
);

if ( false === strpos( $html, 'id="partner-directory"' ) ) {
	fwrite( STDERR, "Expected renderer to preserve the shortcode anchor.\n" );
	exit( 1 );
}

$html = crh_render_member_directory_without_warnings(
	static function () {
		return WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_member_directory(
			array(
				'eyebrow' => array( 'unexpected' ),
				'title'   => array( 'unexpected' ),
				'anchor'  => array( 'unexpected' ),
			)
		);
	},
	'Expected the member directory render path to ignore non-string context values without warnings.'
);

if ( false !== strpos( $html, '>Array<' ) ) {
	fwrite( STDERR, "Expected renderer not to print array-cast context values.\n" );
	exit( 1 );
}

echo "Member directory renderer smoke test passed.\n";
