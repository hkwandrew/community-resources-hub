<?php
/**
 * Smoke tests for the video slider renderer and shortcode fallback.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'COMMUNITY_RESOURCES_HUB_VERSION', '0.1.0' );

$GLOBALS['crh_acf_fields']       = array();
$GLOBALS['crh_options']          = array();
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

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
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

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		$value = str_replace( "\r", '', (string) $value );
		$value = strip_tags( $value );
		$lines = array_map( 'trim', explode( "\n", $value ) );

		return trim( implode( "\n", $lines ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return preg_replace( '#<script[^>]*>.*?</script>#is', '', (string) $html );
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

if ( ! function_exists( 'get_field' ) ) {
	function get_field( $field_name, $post_id = false ) {
		$key = (string) $post_id . ':' . (string) $field_name;

		return array_key_exists( $key, $GLOBALS['crh_acf_fields'] ?? array() )
			? $GLOBALS['crh_acf_fields'][ $key ]
			: null;
	}
}

if ( ! function_exists( 'did_action' ) ) {
	function did_action( $hook_name ) {
		return 'acf/init' === $hook_name ? 1 : 0;
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

if ( ! function_exists( 'wp_register_script' ) ) {
	function wp_register_script( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false ) {
		return true;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle ) {
		$GLOBALS['crh_enqueued_scripts'][] = (string) $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle ) {
		$GLOBALS['crh_enqueued_styles'][] = (string) $handle;
	}
}

if ( ! function_exists( 'wp_get_attachment_image' ) ) {
	function wp_get_attachment_image( $attachment_id, $size = 'thumbnail', $icon = false, $attr = array() ) {
		$attributes = '';

		foreach ( $attr as $name => $value ) {
			$attributes .= sprintf(
				' %s="%s"',
				esc_attr( (string) $name ),
				esc_attr( (string) $value )
			);
		}

		return sprintf(
			'<img data-attachment-id="%d"%s />',
			absint( $attachment_id ),
			$attributes
		);
	}
}

require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/support/class-render-support.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-video-slider-assets.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-video-slider-renderer.php';
require_once dirname( __DIR__ ) . '/includes/shortcodes/class-shortcodes.php';

function crh_render_video_slider_without_warnings( $callback, $message ) {
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

function crh_set_saved_video_slider_config( array $config ) {
	$eyebrow = isset( $config['eyebrow'] ) ? $config['eyebrow'] : '';
	$title   = isset( $config['title'] ) ? $config['title'] : '';
	$intro   = isset( $config['intro'] ) ? $config['intro'] : '';
	$slides  = isset( $config['slides'] ) && is_array( $config['slides'] ) ? $config['slides'] : array();

	$GLOBALS['crh_acf_fields'] = array(
		'option:wm_bci_video_slider_eyebrow' => $eyebrow,
		'option:wm_bci_video_slider_title'   => $title,
		'option:wm_bci_video_slider_intro'   => $intro,
		'option:wm_bci_video_slider_slides'  => $slides,
	);

	$GLOBALS['crh_options'] = array(
		'options_wm_bci_video_slider_eyebrow' => $eyebrow,
		'options_wm_bci_video_slider_title'   => $title,
		'options_wm_bci_video_slider_intro'   => $intro,
		'options_wm_bci_video_slider_slides'  => $slides,
	);
}

$shortcodes = new WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes();

crh_set_saved_video_slider_config(
	array(
		'eyebrow' => 'Saved Eyebrow',
		'title'   => 'Saved Title',
		'intro'   => '<p>Saved intro</p>',
		'slides'  => array(
			array(
				'video_id'          => 'dQw4w9WgXcQ',
				'video_url'         => '',
				'thumbnail_id'      => 101,
				'logo_id'           => 201,
				'logo_label'        => 'Saved Logo',
				'slide_eyebrow'     => 'Saved Slide Eyebrow',
				'slide_title'       => 'Saved Slide Title',
				'slide_description' => 'Saved slide description.',
			),
			array(
				'video_id'          => '',
				'video_url'         => 'https://www.youtube.com/watch?v=9bZkp7q19f0',
				'thumbnail_id'      => 102,
				'logo_id'           => 202,
				'logo_label'        => 'Saved Logo Two',
				'slide_eyebrow'     => 'Saved Slide Eyebrow Two',
				'slide_title'       => 'Saved Slide Title Two',
				'slide_description' => 'Saved slide description two.',
			),
		),
	)
);

$html = crh_render_video_slider_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_video_slider_shortcode( array(), '', 'community_video_slider' );
	},
	'Expected the Video Slider shortcode to render the saved BCI Hub config without warnings.'
);

if ( false === strpos( $html, 'Saved Eyebrow' ) || false === strpos( $html, 'Saved Title' ) || false === strpos( $html, '<p>Saved intro</p>' ) ) {
	fwrite( STDERR, "Expected the Video Slider shortcode to render the saved BCI Hub wrapper content.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0' ) || false === strpos( $html, 'https://www.youtube-nocookie.com/embed/9bZkp7q19f0?rel=0' ) ) {
	fwrite( STDERR, "Expected saved YouTube IDs or URLs to become youtube-nocookie embed sources.\n" );
	exit( 1 );
}

$video_slider_renderer = new WatersMeet\CommunityResourcesHub\FrontEnd\VideoSliderRenderer();
$saved_slides          = ( new WatersMeet\CommunityResourcesHub\Config\Config() )->video_slider_slides();
$matching_fragment     = $video_slider_renderer->fragment_href_for_video_url(
	'https://youtu.be/9bZkp7q19f0?si=member-field',
	$saved_slides
);

if ( '#bci-video-saved-slide-title-two' !== $matching_fragment ) {
	fwrite( STDERR, "Expected a Member YouTube URL to resolve to the matching renderable Video Slider fragment.\n" );
	exit( 1 );
}

if ( '' !== $video_slider_renderer->fragment_href_for_video_url( 'https://www.youtube.com/watch?v=-VGOUsqEF1c', $saved_slides ) ) {
	fwrite( STDERR, "Expected an unmatched Member YouTube URL not to resolve to a dead Video Slider fragment.\n" );
	exit( 1 );
}

if ( ! in_array( 'community-resources-hub-video-slider', $GLOBALS['crh_enqueued_scripts'], true ) || ! in_array( 'community-resources-hub-video-slider', $GLOBALS['crh_enqueued_styles'], true ) ) {
	fwrite( STDERR, "Expected the Video Slider renderer to enqueue the shared frontend assets.\n" );
	exit( 1 );
}

$GLOBALS['crh_acf_fields']['option:wm_bci_video_slider_eyebrow'] = false;
$GLOBALS['crh_acf_fields']['option:wm_bci_video_slider_title']   = false;
$GLOBALS['crh_acf_fields']['option:wm_bci_video_slider_intro']   = false;
$GLOBALS['crh_acf_fields']['option:wm_bci_video_slider_slides']  = false;
$GLOBALS['crh_acf_fields']['options:wm_bci_video_slider_slides'] = false;
$GLOBALS['crh_options'] = array(
	'options_wm_bci_video_slider_eyebrow'                   => 'Split Eyebrow',
	'options_wm_bci_video_slider_title'                     => 'Split Title',
	'options_wm_bci_video_slider_intro'                     => '<p>Split intro</p>',
	'options_wm_bci_video_slider_slides'                    => array(),
	'options_wm_bci_video_slider_slides_0_video_id'         => '',
	'options_wm_bci_video_slider_slides_0_video_url'        => 'https://youtu.be/-VGOUsqEF1c?si=test',
	'options_wm_bci_video_slider_slides_0_thumbnail_id'     => 301,
	'options_wm_bci_video_slider_slides_0_logo_id'          => 401,
	'options_wm_bci_video_slider_slides_0_logo_label'       => 'Split Logo',
	'options_wm_bci_video_slider_slides_0_slide_eyebrow'    => 'Split Slide Eyebrow',
	'options_wm_bci_video_slider_slides_0_slide_title'      => 'Split Slide Title',
	'options_wm_bci_video_slider_slides_0_slide_description'=> 'Split slide description.',
);

$html = crh_render_video_slider_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_video_slider_shortcode( array(), '', 'community_video_slider' );
	},
	'Expected the Video Slider shortcode to reconstruct split ACF option rows without warnings.'
);

if ( false === strpos( $html, 'Split Title' ) || false === strpos( $html, 'https://www.youtube-nocookie.com/embed/-VGOUsqEF1c?rel=0' ) ) {
	fwrite( STDERR, "Expected the Video Slider shortcode to rebuild slides from split ACF option storage when the repeater parent value is unusable.\n" );
	exit( 1 );
}

$GLOBALS['crh_acf_fields'] = array(
	'option:wm_bci_video_slider_eyebrow' => false,
	'option:wm_bci_video_slider_title'   => false,
	'option:wm_bci_video_slider_intro'   => false,
	'option:wm_bci_video_slider_slides'  => false,
	'options:wm_bci_video_slider_slides' => false,
);
$GLOBALS['crh_options'] = array(
	'options_wm_bci_video_slider_eyebrow' => '',
	'options_wm_bci_video_slider_title'   => '',
	'options_wm_bci_video_slider_intro'   => '',
	'options_wm_bci_video_slider_slides' => array(
		array(
			'video_id'          => 'dQw4w9WgXcQ',
			'video_url'         => '',
			'thumbnail_id'      => 101,
			'logo_id'           => 201,
			'logo_label'        => 'Saved Logo',
			'slide_eyebrow'     => 'The Rooted in Community series',
			'slide_title'       => 'Saved Slide Title',
			'slide_description' => 'Saved slide description.',
		),
	),
);

$html = crh_render_video_slider_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_video_slider_shortcode( array(), '', 'community_video_slider' );
	},
	'Expected the Video Slider shortcode to fall back to the latest settings defaults without warnings.'
);

if (
	false === strpos( $html, 'Spotlight Videos' )
	|| false === strpos( $html, 'See the BCI Community in action' )
	|| false === strpos( $html, '<strong>Rooted in Community video series</strong>' )
) {
	fwrite( STDERR, "Expected the Video Slider shortcode to use the latest Spotlight Videos wrapper defaults when saved wrapper settings are blank.\n" );
	exit( 1 );
}

$html = crh_render_video_slider_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_video_slider_shortcode(
			array(
				'anchor'  => 'Hero Slider',
				'eyebrow' => 'Override Eyebrow',
				'title'   => 'Override Title',
				'intro'   => '<p>Override intro</p>',
			),
			'',
			'community_video_slider'
		);
	},
	'Expected Video Slider shortcode overrides to render without warnings.'
);

if ( false === strpos( $html, 'id="hero-slider"' ) ) {
	fwrite( STDERR, "Expected the Video Slider shortcode to preserve the override anchor.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'Override Eyebrow' ) || false === strpos( $html, 'Override Title' ) || false === strpos( $html, '<p>Override intro</p>' ) ) {
	fwrite( STDERR, "Expected the Video Slider shortcode wrapper attributes to override the saved BCI Hub config.\n" );
	exit( 1 );
}

crh_set_saved_video_slider_config(
	array(
		'eyebrow' => 'Saved Eyebrow',
		'title'   => 'Saved Title',
		'intro'   => '<p>Saved intro</p>',
		'slides'  => array(),
	)
);

$html = crh_render_video_slider_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_video_slider_shortcode( array(), '', 'community_video_slider' );
	},
	'Expected the Video Slider shortcode to tolerate missing saved slides without warnings.'
);

if ( '' !== $html ) {
	fwrite( STDERR, "Expected the Video Slider shortcode to return empty output when no saved slides exist.\n" );
	exit( 1 );
}

crh_set_saved_video_slider_config(
	array(
		'eyebrow' => 'Saved Eyebrow',
		'title'   => 'Saved Title',
		'intro'   => '<p>Saved intro</p>',
		'slides'  => array(
			array(
				'video_id'          => 'dQw4w9WgXcQ',
				'video_url'         => '',
				'thumbnail_id'      => 101,
				'logo_id'           => 201,
				'logo_label'        => 'Saved Logo',
				'slide_eyebrow'     => 'Saved Slide Eyebrow',
				'slide_title'       => 'Saved Slide Title',
				'slide_description' => 'Saved slide description.',
			),
		),
	)
);

echo "Video slider renderer smoke test passed.\n";
