<?php
/**
 * Public template tags for classic themes.
 *
 * @package CommunityResourcesHub
 */

use WatersMeet\CommunityResourcesHub\Shortcodes\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'community_resources_hub_render_opportunity_hub' ) ) {
	/**
	 * Render the Opportunities Hub markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	function community_resources_hub_render_opportunity_hub( array $context = array() ) {
		return Shortcodes::render_opportunity_hub( $context );
	}
}

if ( ! function_exists( 'community_resources_hub_the_opportunity_hub' ) ) {
	/**
	 * Echo the Opportunities Hub markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return void
	 */
	function community_resources_hub_the_opportunity_hub( array $context = array() ) {
		echo community_resources_hub_render_opportunity_hub( $context );
	}
}

if ( ! function_exists( 'community_resources_hub_render_member_directory' ) ) {
	/**
	 * Render the Member Directory markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	function community_resources_hub_render_member_directory( array $context = array() ) {
		return Shortcodes::render_member_directory( $context );
	}
}

if ( ! function_exists( 'community_resources_hub_the_member_directory' ) ) {
	/**
	 * Echo the Member Directory markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return void
	 */
	function community_resources_hub_the_member_directory( array $context = array() ) {
		echo community_resources_hub_render_member_directory( $context );
	}
}

if ( ! function_exists( 'community_resources_hub_render_video_slider' ) ) {
	/**
	 * Render the Video Slider markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	function community_resources_hub_render_video_slider( array $context = array() ) {
		return Shortcodes::render_video_slider( $context );
	}
}

if ( ! function_exists( 'community_resources_hub_the_video_slider' ) ) {
	/**
	 * Echo the Video Slider markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return void
	 */
	function community_resources_hub_the_video_slider( array $context = array() ) {
		echo community_resources_hub_render_video_slider( $context );
	}
}

if ( ! function_exists( 'community_resources_hub_render_newsletter_archives' ) ) {
	/**
	 * Render the Newsletter Archives markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	function community_resources_hub_render_newsletter_archives( array $context = array() ) {
		return Shortcodes::render_newsletter_archives( $context );
	}
}

if ( ! function_exists( 'community_resources_hub_the_newsletter_archives' ) ) {
	/**
	 * Echo the Newsletter Archives markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return void
	 */
	function community_resources_hub_the_newsletter_archives( array $context = array() ) {
		echo community_resources_hub_render_newsletter_archives( $context );
	}
}
