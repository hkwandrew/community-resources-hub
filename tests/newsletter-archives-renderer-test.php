<?php
/**
 * Smoke tests for the newsletter archives renderer and classic settings fallback.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'COMMUNITY_RESOURCES_HUB_VERSION', '0.1.0' );
define( 'COMMUNITY_RESOURCES_HUB_URL', 'https://example.test/wp-content/plugins/community-resources-hub/' );

$GLOBALS['crh_acf_fields']      = array();
$GLOBALS['crh_options']         = array();
$GLOBALS['crh_enqueued_styles'] = array();
$GLOBALS['crh_registered_shortcodes'] = array();

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
		$url = trim( (string) $url );

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}

		return filter_var( $url, FILTER_SANITIZE_URL );
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

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['crh_options'][ (string) $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		unset( $GLOBALS['crh_options'][ (string) $option ] );

		return true;
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

if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( $tag, $callback ) {
		$GLOBALS['crh_registered_shortcodes'][ (string) $tag ] = $callback;
	}
}

if ( ! function_exists( 'wp_register_style' ) ) {
	function wp_register_style( $handle, $src = '', $deps = array(), $ver = false, $media = 'all' ) {
		return true;
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

function crh_find_acf_field_by_key( array $fields, $field_key ) {
	foreach ( $fields as $field ) {
		if ( ! is_array( $field ) ) {
			continue;
		}

		if ( isset( $field['key'] ) && $field_key === $field['key'] ) {
			return $field;
		}

		if ( isset( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
			$match = crh_find_acf_field_by_key( $field['sub_fields'], $field_key );

			if ( null !== $match ) {
				return $match;
			}
		}
	}

	return null;
}

require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-acf-settings.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/support/class-render-support.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-newsletter-archives-assets.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-newsletter-archives-renderer.php';
require_once dirname( __DIR__ ) . '/includes/shortcodes/class-shortcodes.php';

function crh_render_newsletter_archives_without_warnings( $callback, $message ) {
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

function crh_set_saved_newsletter_archives_config( array $config ) {
	$eyebrow = isset( $config['eyebrow'] ) ? $config['eyebrow'] : '';
	$title   = isset( $config['title'] ) ? $config['title'] : '';
	$cards   = isset( $config['cards'] ) && is_array( $config['cards'] ) ? $config['cards'] : array();

	$GLOBALS['crh_acf_fields'] = array(
		'option:wm_bci_newsletter_archives_eyebrow' => $eyebrow,
		'option:wm_bci_newsletter_archives_title'   => $title,
		'option:wm_bci_newsletter_archive_cards'    => $cards,
	);

	$GLOBALS['crh_options'] = array(
		'options_wm_bci_newsletter_archives_eyebrow' => $eyebrow,
		'options_wm_bci_newsletter_archives_title'   => $title,
		'options_wm_bci_newsletter_archive_cards'    => $cards,
	);
}

$shortcodes = new WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes();
$shortcodes->register();

if ( ! isset( $GLOBALS['crh_registered_shortcodes']['community_newsletter_archives'] ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives shortcode to be registered for editor placement.\n" );
	exit( 1 );
}

$settings_group     = WatersMeet\CommunityResourcesHub\Config\SettingsSchema::field_group();
$image_preset_field = crh_find_acf_field_by_key( $settings_group['fields'], 'field_wm_bci_newsletter_archive_card_image_preset' );
$legacy_image_field = crh_find_acf_field_by_key( $settings_group['fields'], 'field_wm_bci_newsletter_archive_card_image_id' );

if ( ! is_array( $image_preset_field ) || 'image_preset' !== ( $image_preset_field['name'] ?? '' ) || 'select' !== ( $image_preset_field['type'] ?? '' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives settings schema to expose an image preset select field.\n" );
	exit( 1 );
}

if ( is_array( $legacy_image_field ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives settings schema not to require the legacy media-library image field.\n" );
	exit( 1 );
}

if ( 8 !== count( $image_preset_field['choices'] ?? array() ) || ! isset( $image_preset_field['choices']['newsletter-img-1'], $image_preset_field['choices']['newsletter-img-8'] ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives image preset field to expose all eight bundled image choices.\n" );
	exit( 1 );
}

$GLOBALS['crh_options'] = array(
	'options_wm_bci_newsletter_archive_cards'             => 2,
	'options_wm_bci_newsletter_archive_cards_0_image_id'  => 1877,
	'_options_wm_bci_newsletter_archive_cards_0_image_id' => 'field_wm_bci_newsletter_archive_card_image_id',
	'options_wm_bci_newsletter_archive_cards_1_image_id'  => 1862,
	'_options_wm_bci_newsletter_archive_cards_1_image_id' => 'field_wm_bci_newsletter_archive_card_image_id',
);
$_POST['acf'] = array(
	'field_wm_bci_newsletter_archive_cards' => array(
		array(
			'field_wm_bci_newsletter_archive_card_issue_label'  => 'May 2026',
			'field_wm_bci_newsletter_archive_card_title'        => 'Activating the Pillar Workgroups',
			'field_wm_bci_newsletter_archive_card_url'          => 'https://example.com/may-newsletter',
			'field_wm_bci_newsletter_archive_card_image_preset' => 'newsletter-img-1',
		),
		array(
			'field_wm_bci_newsletter_archive_card_issue_label'  => 'April 2026',
			'field_wm_bci_newsletter_archive_card_title'        => 'Next Steps for Pillar Workgroups',
			'field_wm_bci_newsletter_archive_card_url'          => 'https://example.com/april-newsletter',
			'field_wm_bci_newsletter_archive_card_image_preset' => 'newsletter-img-2',
		),
	),
);

( new WatersMeet\CommunityResourcesHub\Config\AcfSettings() )->normalize_newsletter_archive_repeater_parent( 'options' );
unset( $_POST['acf'] );

if ( 2 !== $GLOBALS['crh_options']['options_wm_bci_newsletter_archive_cards'] ) {
	fwrite( STDERR, "Expected Newsletter Archives settings save to preserve the normalized repeater row count.\n" );
	exit( 1 );
}

foreach ( array( 0, 1 ) as $legacy_index ) {
	if ( isset( $GLOBALS['crh_options'][ 'options_wm_bci_newsletter_archive_cards_' . $legacy_index . '_image_id' ] ) || isset( $GLOBALS['crh_options'][ '_options_wm_bci_newsletter_archive_cards_' . $legacy_index . '_image_id' ] ) ) {
		fwrite( STDERR, "Expected Newsletter Archives settings save to delete legacy image_id split options.\n" );
		exit( 1 );
	}
}

crh_set_saved_newsletter_archives_config(
	array(
		'eyebrow' => 'Saved Newsletter Eyebrow',
		'title'   => 'Saved Newsletter Title',
		'cards'   => array(
			array(
				'issue_label' => 'May 2026',
				'title'       => 'Activating the Pillar Workgroups',
				'url'         => 'https://example.com/may-newsletter',
				'image_preset' => 'newsletter-img-1',
			),
			array(
				'issue_label' => 'Missing URL',
				'title'       => 'Incomplete card',
				'url'         => '',
				'image_preset' => 'newsletter-img-2',
			),
		),
	)
);

$html = crh_render_newsletter_archives_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_newsletter_archives_shortcode( array(), '', 'community_newsletter_archives' );
	},
	'Expected the Newsletter Archives shortcode to render saved BCI Hub config without warnings.'
);

if ( false === strpos( $html, 'Saved Newsletter Eyebrow' ) || false === strpos( $html, 'Saved Newsletter Title' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives shortcode to render saved wrapper content.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'May 2026' ) || false === strpos( $html, 'Activating the Pillar Workgroups' ) || false === strpos( $html, 'https://example.com/may-newsletter' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives shortcode to render complete saved cards.\n" );
	exit( 1 );
}

if ( preg_match( '/<a\b[^>]*class="[^"]*\bbci-newsletter-archives__card-link\b/', $html ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives card surface not to be a full-card link when a CTA is present.\n" );
	exit( 1 );
}

if ( ! preg_match( '/<a\b(?=[^>]*href="https:\/\/example\.com\/may-newsletter")(?=[^>]*target="_blank")(?=[^>]*rel="noopener noreferrer")(?=[^>]*aria-label="Read Activating the Pillar Workgroups")[^>]*>.*?Read more.*?<\/a>/s', $html ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives card CTA to own the newsletter link and open in a new tab with noopener protection.\n" );
	exit( 1 );
}

if ( false !== strpos( $html, 'Incomplete card' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives renderer to skip cards without URLs.\n" );
	exit( 1 );
}

if ( false === strpos( $html, 'assets/images/newsletter-archives/newsletter-img-1.png' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives card to render the selected bundled image preset.\n" );
	exit( 1 );
}

if ( false === strpos( $html, '>May 2026<' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives card to render the saved issue label.\n" );
	exit( 1 );
}

if ( ! in_array( 'community-resources-hub-newsletter-archives', $GLOBALS['crh_enqueued_styles'], true ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives renderer to enqueue the shared frontend style.\n" );
	exit( 1 );
}

$GLOBALS['crh_acf_fields'] = array(
	'option:wm_bci_newsletter_archives_eyebrow' => false,
	'option:wm_bci_newsletter_archives_title'   => false,
	'option:wm_bci_newsletter_archive_cards'    => false,
	'options:wm_bci_newsletter_archive_cards'   => false,
);
$GLOBALS['crh_options'] = array(
	'options_wm_bci_newsletter_archives_eyebrow'        => 'Split Newsletter Eyebrow',
	'options_wm_bci_newsletter_archives_title'          => 'Split Newsletter Title',
	'options_wm_bci_newsletter_archive_cards'           => array(),
	'options_wm_bci_newsletter_archive_cards_0_issue_label' => 'April 2026',
	'options_wm_bci_newsletter_archive_cards_0_title'       => 'Next Steps for Pillar Workgroups',
	'options_wm_bci_newsletter_archive_cards_0_url'         => 'https://example.com/april-newsletter',
	'options_wm_bci_newsletter_archive_cards_0_image_preset' => 'newsletter-img-6',
);

$html = crh_render_newsletter_archives_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_newsletter_archives_shortcode( array(), '', 'community_newsletter_archives' );
	},
	'Expected the Newsletter Archives shortcode to reconstruct split ACF option rows without warnings.'
);

if ( false === strpos( $html, 'Split Newsletter Title' ) || false === strpos( $html, 'April 2026' ) || false === strpos( $html, 'assets/images/newsletter-archives/newsletter-img-6.png' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives shortcode to rebuild cards from split ACF option storage.\n" );
	exit( 1 );
}

$html = crh_render_newsletter_archives_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_newsletter_archives_shortcode(
			array(
				'anchor'  => 'Newsletter Archive',
				'eyebrow' => 'Override Eyebrow',
				'title'   => 'Override Title',
			),
			'',
			'community_newsletter_archives'
		);
	},
	'Expected Newsletter Archives shortcode overrides to render without warnings.'
);

if ( false === strpos( $html, 'id="newsletter-archive"' ) || false === strpos( $html, 'Override Eyebrow' ) || false === strpos( $html, 'Override Title' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives shortcode to preserve wrapper overrides.\n" );
	exit( 1 );
}

$html = crh_render_newsletter_archives_without_warnings(
	static function () {
		return WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_newsletter_archives(
			array(
				'cards' => array(
					array(
						'issueLabel'  => 'Unsafe',
						'title'       => 'Unsafe URL',
						'url'         => 'javascript:alert(1)',
						'imagePreset' => '',
						'imageId'     => 0,
					),
				),
			)
		);
	},
	'Expected Newsletter Archives direct context URL validation to render without warnings.'
);

if ( '' !== $html ) {
	fwrite( STDERR, "Expected the Newsletter Archives renderer to skip direct-context cards with unsafe URLs.\n" );
	exit( 1 );
}

$html = crh_render_newsletter_archives_without_warnings(
	static function () {
		return WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_newsletter_archives(
			array(
				'cards' => array(
					array(
						'issueLabel'  => 'Mixed saved row',
						'title'       => 'Preset Should Win',
						'url'         => 'https://example.com/preset-wins',
						'imagePreset' => 'newsletter-img-2',
						'imageId'     => 505,
					),
				),
			)
		);
	},
	'Expected Newsletter Archives mixed preset and legacy image input to render without warnings.'
);

if ( false === strpos( $html, 'assets/images/newsletter-archives/newsletter-img-2.png' ) || false !== strpos( $html, 'data-attachment-id="505"' ) || false !== strpos( $html, 'bci-newsletter-archives__mail-icon' ) ) {
	fwrite( STDERR, "Expected Newsletter Archives preset images to override legacy attachment IDs in mixed saved rows.\n" );
	exit( 1 );
}

$html = crh_render_newsletter_archives_without_warnings(
	static function () {
		return WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_newsletter_archives(
			array(
				'cards' => array(
					array(
						'issueLabel'  => 'Blank preset field',
						'title'       => 'Legacy Attachment Must Not Leak',
						'url'         => 'https://example.com/no-legacy-image',
						'imagePreset' => '',
						'imageId'     => 606,
					),
				),
			)
		);
	},
	'Expected Newsletter Archives blank preset contract row to render without warnings.'
);

if ( false !== strpos( $html, 'data-attachment-id="606"' ) || false === strpos( $html, 'bci-newsletter-archives__mail-icon' ) ) {
	fwrite( STDERR, "Expected blank preset contract rows not to fall back to legacy attachment IDs.\n" );
	exit( 1 );
}

$html = crh_render_newsletter_archives_without_warnings(
	static function () {
		return WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes::render_newsletter_archives(
			array(
				'cards' => array(
					array(
						'issueLabel' => 'Attachment fallback',
						'title'      => 'Direct Context Attachment',
						'url'        => 'https://example.com/direct-context',
						'imageId'    => 404,
					),
				),
			)
		);
	},
	'Expected Newsletter Archives direct context attachment fallback to render without warnings.'
);

if ( false === strpos( $html, 'data-attachment-id="404"' ) ) {
	fwrite( STDERR, "Expected direct-context attachment images to keep the legacy attachment fallback.\n" );
	exit( 1 );
}

crh_set_saved_newsletter_archives_config(
	array(
		'eyebrow' => '',
		'title'   => '',
		'cards'   => array(
			array(
				'issue_label' => 'January 2026',
				'title'       => 'Looking forward to a New Year',
				'url'         => 'https://example.com/january-newsletter',
				'image_preset' => 'newsletter-img-8',
			),
		),
	)
);

$html = crh_render_newsletter_archives_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_newsletter_archives_shortcode( array(), '', 'community_newsletter_archives' );
	},
	'Expected Newsletter Archives shortcode defaults to render without warnings.'
);

if ( false === strpos( $html, 'Newsletter Archives' ) || false === strpos( $html, 'Access past monthly newsletters through the cards below.' ) ) {
	fwrite( STDERR, "Expected the Newsletter Archives shortcode to use the configured wrapper defaults when saved wrapper settings are blank.\n" );
	exit( 1 );
}

crh_set_saved_newsletter_archives_config(
	array(
		'eyebrow' => 'Saved Newsletter Eyebrow',
		'title'   => 'Saved Newsletter Title',
		'cards'   => array(),
	)
);

$html = crh_render_newsletter_archives_without_warnings(
	static function () use ( $shortcodes ) {
		return $shortcodes->render_newsletter_archives_shortcode( array(), '', 'community_newsletter_archives' );
	},
	'Expected the Newsletter Archives shortcode to tolerate missing saved cards without warnings.'
);

if ( '' !== $html ) {
	fwrite( STDERR, "Expected the Newsletter Archives shortcode to return empty output when no saved cards exist.\n" );
	exit( 1 );
}

echo "Newsletter archives renderer smoke test passed.\n";
