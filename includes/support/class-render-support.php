<?php
/**
 * Shared render helpers for plugin-owned BCI surfaces.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Small shared render helpers for plugin-owned BCI markup.
 */
final class RenderSupport {

	/**
	 * Prefix-scoped fallback counters when wp_unique_id() is unavailable.
	 *
	 * @var array<string,int>
	 */
	private static $fallback_counters = array();

	/**
	 * Generate a stable unique ID with a plugin-owned fallback.
	 *
	 * @param string $prefix ID prefix.
	 * @return string
	 */
	public static function unique_id( $prefix ) {
		$prefix = (string) $prefix;

		if ( function_exists( 'wp_unique_id' ) ) {
			return wp_unique_id( $prefix );
		}

		if ( ! isset( self::$fallback_counters[ $prefix ] ) ) {
			self::$fallback_counters[ $prefix ] = 0;
		}

		self::$fallback_counters[ $prefix ]++;

		return $prefix . self::$fallback_counters[ $prefix ];
	}

	/**
	 * Build root wrapper attributes for classic-theme output.
	 *
	 * @param array<string,mixed> $attributes Attribute map.
	 * @return string
	 */
	public static function wrapper_attributes( array $attributes ) {
		return self::html_attributes( $attributes );
	}

	/**
	 * Build a same-page modal share URL from an item's unique token.
	 *
	 * @param array<string,mixed> $item Item payload.
	 * @param string              $param_name Query parameter name.
	 * @return string
	 */
	public static function modal_share_url( array $item, $param_name ) {
		$param_name = trim( (string) $param_name );
		$token      = self::modal_share_token( $item );

		if ( '' === $param_name || '' === $token ) {
			return '';
		}

		return '?' . rawurlencode( $param_name ) . '=' . rawurlencode( $token );
	}

	/**
	 * Convert a simple attribute map into escaped HTML attributes.
	 *
	 * @param array<string,mixed> $attributes Attribute map.
	 * @return string
	 */
	public static function html_attributes( array $attributes ) {
		$attributes = self::normalize_html_attributes( $attributes );
		$output     = array();

		foreach ( $attributes as $name => $value ) {
			if ( true === $value ) {
				$output[] = esc_attr( (string) $name );
				continue;
			}

			$output[] = sprintf(
				'%s="%s"',
				esc_attr( (string) $name ),
				esc_attr( (string) $value )
			);
		}

		return implode( ' ', $output );
	}

	/**
	 * Theme-aligned button arrow icon used by plugin-owned CTAs.
	 *
	 * @return string
	 */
	public static function button_arrow_icon() {
		return '<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4.66669 11.3334L11.3334 4.66669" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M4.66669 4.66669H11.3334V11.3334" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * Open-form icon used by the submit trigger CTA.
	 *
	 * @return string
	 */
	public static function open_form_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M5 19V13H7V17H11V19H5ZM17 11V7H13V5H19V11H17Z" fill="#004966"/></svg>';
	}

	/**
	 * Shared calendar icon used by Add to Calendar CTAs.
	 *
	 * @return string
	 */
	public static function calendar_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" focusable="false" aria-hidden="true"><path d="M6.66667 1.66602V4.16602M13.3333 1.66602V4.16602M2.5 7.49935H17.5M4.16667 3.33268H15.8333C16.7538 3.33268 17.5 4.07887 17.5 4.99935V15.8327C17.5 16.7532 16.7538 17.4993 15.8333 17.4993H4.16667C3.24619 17.4993 2.5 16.7532 2.5 15.8327V4.99935C2.5 4.07887 3.24619 3.33268 4.16667 3.33268Z" stroke="currentColor" stroke-width="1.67" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * Spotlight video icon used by the member video CTA.
	 *
	 * @return string
	 */
	public static function spotlight_video_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="19" height="24" viewBox="0 0 19 24" fill="none"><path d="M0 23.3333V0L18.3333 11.6667L0 23.3333Z" fill="#004966"/></svg>';
	}

	/**
	 * Fallback icon for opportunity cards without configured thumbnails.
	 *
	 * @return string
	 */
	public static function opportunity_placeholder_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="76" height="76" viewBox="0 0 76 76" fill="none" focusable="false" aria-hidden="true"><circle cx="38" cy="38" r="38" fill="rgba(247,247,248,0.16)"/><path d="M24.5 25.5C24.5 24.3954 25.3954 23.5 26.5 23.5H49.5C50.6046 23.5 51.5 24.3954 51.5 25.5V50.5C51.5 51.6046 50.6046 52.5 49.5 52.5H26.5C25.3954 52.5 24.5 51.6046 24.5 50.5V25.5Z" stroke="#F7F7F8" stroke-width="2"/><path d="M31 30.5H45" stroke="#F7F7F8" stroke-width="2" stroke-linecap="round"/><path d="M31 36.5H45" stroke="#F7F7F8" stroke-width="2" stroke-linecap="round"/><path d="M31 42.5H40" stroke="#F7F7F8" stroke-width="2" stroke-linecap="round"/></svg>';
	}

	/**
	 * Shared dialog close button markup.
	 *
	 * @param string $label Accessible label.
	 * @return string
	 */
	public static function dialog_close_button( $label = 'Close dialog' ) {
		return sprintf(
			'<button type="button" class="crh-dialog__close close-button" data-crh-dialog-close aria-label="%1$s"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><mask id="mask0_1713_6722" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="#D9D9D9"/></mask><g mask="url(#mask0_1713_6722)"><path d="M6.4 19L5 17.6L10.6 12L5 6.4L6.4 5L12 10.6L17.6 5L19 6.4L13.4 12L19 17.6L17.6 19L12 13.4L6.4 19Z" fill="#004966"/></g></svg></button>',
			esc_attr( (string) $label )
		);
	}

	/**
	 * Normalize attribute values before output.
	 *
	 * @param array<string,mixed> $attributes Attribute map.
	 * @return array<string,string|bool>
	 */
	private static function normalize_html_attributes( array $attributes ) {
		$normalized = array();

		foreach ( $attributes as $name => $value ) {
			if ( null === $value || false === $value || '' === $value ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = trim( implode( ' ', array_filter( array_map( 'strval', $value ) ) ) );

				if ( '' === $value ) {
					continue;
				}
			}

			$normalized[ (string) $name ] = true === $value ? true : (string) $value;
		}

		return $normalized;
	}

	/**
	 * Resolve the preferred share token for a modal payload item.
	 *
	 * @param array<string,mixed> $item Item payload.
	 * @return string
	 */
	private static function modal_share_token( array $item ) {
		foreach ( array( 'shareSlug', 'slug', 'id' ) as $key ) {
			$token = trim( (string) ( $item[ $key ] ?? '' ) );

			if ( '' !== $token ) {
				return $token;
			}
		}

		return '';
	}
}
