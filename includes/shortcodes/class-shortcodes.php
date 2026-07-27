<?php
/**
 * Classic-editor shortcode entry points.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Shortcodes;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\FrontEnd\MemberDirectoryRenderer;
use WatersMeet\CommunityResourcesHub\FrontEnd\NewsletterArchivesRenderer;
use WatersMeet\CommunityResourcesHub\FrontEnd\OpportunityHubRenderer;
use WatersMeet\CommunityResourcesHub\FrontEnd\VideoSliderRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers classic-theme and classic-editor shortcodes.
 */
final class Shortcodes {

	/**
	 * Register shortcode hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( 'community_resources_hub', array( $this, 'render_opportunity_hub_shortcode' ) );
		add_shortcode( 'community_opportunity_hub', array( $this, 'render_opportunity_hub_shortcode' ) );
		add_shortcode( 'community_member_directory', array( $this, 'render_member_directory_shortcode' ) );
		add_shortcode( 'community_video_slider', array( $this, 'render_video_slider_shortcode' ) );
		add_shortcode( 'community_newsletter_archives', array( $this, 'render_newsletter_archives_shortcode' ) );
	}

	/**
	 * Render the opportunity hub shortcode.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @param string                     $content Shortcode content.
	 * @param string                     $tag Shortcode tag.
	 * @return string
	 */
	public function render_opportunity_hub_shortcode( $atts = array(), $content = '', $tag = '' ) {
		$atts = shortcode_atts(
			array(
				'intro_content'       => '',
				'intro_column_width'  => 'two-thirds',
				'anchor_content'      => '',
				'anchor_column_width' => 'full',
				'submit_modal_intro'  => '',
				'gravity_form_id'     => 0,
				'calendar_shortcode'  => '',
				'anchor'              => '',
			),
			$this->normalize_atts( $atts ),
			$tag
		);

		$intro_content = '' !== trim( (string) $atts['intro_content'] )
			? (string) $atts['intro_content']
			: (string) $content;

		return self::render_opportunity_hub(
			array(
				'introContent'      => $intro_content,
				'introColumnWidth'  => (string) $atts['intro_column_width'],
				'anchorContent'     => (string) $atts['anchor_content'],
				'anchorColumnWidth' => (string) $atts['anchor_column_width'],
				'submitModalIntro'  => (string) $atts['submit_modal_intro'],
				'gravityFormId'     => absint( $atts['gravity_form_id'] ),
				'calendarShortcode' => (string) $atts['calendar_shortcode'],
				'anchor'            => (string) $atts['anchor'],
			)
		);
	}

	/**
	 * Render the member directory shortcode.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @param string                     $content Shortcode content.
	 * @param string                     $tag Shortcode tag.
	 * @return string
	 */
	public function render_member_directory_shortcode( $atts = array(), $content = '', $tag = '' ) {
		$atts = shortcode_atts(
			array(
				'eyebrow' => '',
				'title'   => '',
				'anchor'  => '',
			),
			$this->normalize_atts( $atts ),
			$tag
		);

		return self::render_member_directory(
			array(
				'eyebrow' => (string) $atts['eyebrow'],
				'title'   => (string) $atts['title'],
				'anchor'  => (string) $atts['anchor'],
			)
		);
	}

	/**
	 * Render the video slider shortcode.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @param string                     $content Shortcode content.
	 * @param string                     $tag Shortcode tag.
	 * @return string
	 */
	public function render_video_slider_shortcode( $atts = array(), $content = '', $tag = '' ) {
		$atts = shortcode_atts(
			array(
				'eyebrow' => '',
				'title'   => '',
				'intro'   => '',
				'slides'  => '',
				'anchor'  => '',
			),
			$this->normalize_atts( $atts ),
			$tag
		);

		$intro = '' !== trim( (string) $atts['intro'] )
			? (string) $atts['intro']
			: (string) $content;

		return self::render_video_slider(
			array(
				'eyebrow' => (string) $atts['eyebrow'],
				'title'   => (string) $atts['title'],
				'intro'   => $intro,
				'slides'  => self::json_attribute_to_array( $atts['slides'] ),
				'anchor'  => (string) $atts['anchor'],
			)
		);
	}

	/**
	 * Render the newsletter archives shortcode.
	 *
	 * @param array<string,mixed>|string $atts Shortcode attributes.
	 * @param string                     $content Shortcode content.
	 * @param string                     $tag Shortcode tag.
	 * @return string
	 */
	public function render_newsletter_archives_shortcode( $atts = array(), $content = '', $tag = '' ) {
		$atts = shortcode_atts(
			array(
				'eyebrow' => '',
				'title'   => '',
				'anchor'  => '',
			),
			$this->normalize_atts( $atts ),
			$tag
		);

		return self::render_newsletter_archives(
			array(
				'eyebrow' => (string) $atts['eyebrow'],
				'title'   => (string) $atts['title'],
				'anchor'  => (string) $atts['anchor'],
			)
		);
	}

	/**
	 * Render the opportunity hub from PHP.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public static function render_opportunity_hub( array $context = array() ) {
		$renderer = new OpportunityHubRenderer();

		return $renderer->render( $context );
	}

	/**
	 * Render the member directory from PHP.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public static function render_member_directory( array $context = array() ) {
		$renderer = new MemberDirectoryRenderer();

		return $renderer->render( $context );
	}

	/**
	 * Render the video slider from PHP.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public static function render_video_slider( array $context = array() ) {
		$renderer = new VideoSliderRenderer();

		return $renderer->render( self::resolve_video_slider_context( $context ) );
	}

	/**
	 * Render the newsletter archives from PHP.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public static function render_newsletter_archives( array $context = array() ) {
		$renderer = new NewsletterArchivesRenderer();

		return $renderer->render( self::resolve_newsletter_archives_context( $context ) );
	}

	/**
	 * Normalize shortcode attributes into an array.
	 *
	 * @param array<string,mixed>|string $atts Raw attributes.
	 * @return array<string,mixed>
	 */
	private function normalize_atts( $atts ) {
		return is_array( $atts ) ? $atts : array();
	}

	/**
	 * Decode a JSON shortcode attribute into an array.
	 *
	 * @param mixed $value JSON value.
	 * @return array<int,mixed>
	 */
	private static function json_attribute_to_array( $value ) {
		$value = html_entity_decode( trim( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Resolve classic Video Slider context, falling back to saved BCI Hub config when no slides are provided.
	 *
	 * @param array<string,mixed> $context Raw render context.
	 * @return array<string,mixed>
	 */
	private static function resolve_video_slider_context( array $context ) {
		$resolved = array(
			'eyebrow' => self::string_context_value( $context, 'eyebrow' ),
			'title'   => self::string_context_value( $context, 'title' ),
			'intro'   => self::string_context_value( $context, 'intro' ),
			'slides'  => isset( $context['slides'] ) && is_array( $context['slides'] ) ? $context['slides'] : array(),
			'anchor'  => self::string_context_value( $context, 'anchor' ),
		);

		if ( ! empty( $resolved['slides'] ) ) {
			return $resolved;
		}

		$config   = new Config();
		$defaults = $config->video_slider_context();

		$resolved['slides'] = isset( $defaults['slides'] ) && is_array( $defaults['slides'] ) ? $defaults['slides'] : array();

		if ( '' === trim( $resolved['eyebrow'] ) ) {
			$resolved['eyebrow'] = is_scalar( $defaults['eyebrow'] ?? null ) ? (string) $defaults['eyebrow'] : '';
		}

		if ( '' === trim( $resolved['title'] ) ) {
			$resolved['title'] = is_scalar( $defaults['title'] ?? null ) ? (string) $defaults['title'] : '';
		}

		if ( '' === trim( $resolved['intro'] ) ) {
			$resolved['intro'] = is_scalar( $defaults['intro'] ?? null ) ? (string) $defaults['intro'] : '';
		}

		return $resolved;
	}

	/**
	 * Resolve classic Newsletter Archives context, falling back to saved BCI Hub config when no cards are provided.
	 *
	 * @param array<string,mixed> $context Raw render context.
	 * @return array<string,mixed>
	 */
	private static function resolve_newsletter_archives_context( array $context ) {
		$resolved = array(
			'eyebrow' => self::string_context_value( $context, 'eyebrow' ),
			'title'   => self::string_context_value( $context, 'title' ),
			'cards'   => isset( $context['cards'] ) && is_array( $context['cards'] ) ? $context['cards'] : array(),
			'anchor'  => self::string_context_value( $context, 'anchor' ),
		);

		if ( ! empty( $resolved['cards'] ) ) {
			return $resolved;
		}

		$config   = new Config();
		$defaults = $config->newsletter_archives_context();

		$resolved['cards'] = isset( $defaults['cards'] ) && is_array( $defaults['cards'] ) ? $defaults['cards'] : array();

		if ( '' === trim( $resolved['eyebrow'] ) ) {
			$resolved['eyebrow'] = is_scalar( $defaults['eyebrow'] ?? null ) ? (string) $defaults['eyebrow'] : '';
		}

		if ( '' === trim( $resolved['title'] ) ) {
			$resolved['title'] = is_scalar( $defaults['title'] ?? null ) ? (string) $defaults['title'] : '';
		}

		return $resolved;
	}

	/**
	 * Normalize one scalar context value to a safe string.
	 *
	 * @param array<string,mixed> $context Raw render context.
	 * @param string              $key Context key.
	 * @return string
	 */
	private static function string_context_value( array $context, $key ) {
		if ( ! array_key_exists( $key, $context ) ) {
			return '';
		}

		$value = $context[ $key ];

		return is_scalar( $value ) ? (string) $value : '';
	}
}
