<?php
/**
 * Plugin-owned BCI workflow configuration.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Config;

use WatersMeet\CommunityResourcesHub\ContentModel\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime config authority for the BCI workflow.
 */
final class Config {

	/**
	 * @var array<string,mixed>
	 */
	private $cache = array();

	/**
	 * Plugin text domain.
	 *
	 * @return string
	 */
	public function text_domain() {
		return 'community-resources-hub';
	}

	/**
	 * Configured Gravity Forms form ID.
	 *
	 * @return int
	 */
	public function form_id() {
		return absint( $this->option( 'wm_bci_form_id' ) );
	}

	/**
	 * Configured Gravity Forms approval field ID.
	 *
	 * @return string
	 */
	public function approval_field_id() {
		return $this->text_option( 'wm_bci_approval_field_id' );
	}

	/**
	 * Gravity Forms notification name to customize.
	 *
	 * @return string
	 */
	public function notification_name() {
		return $this->text_option( 'wm_bci_notification_name' );
	}

	/**
	 * Approval notification recipients.
	 *
	 * @return string
	 */
	public function approval_notification_recipients() {
		return self::normalize_recipient_list( (string) $this->option( 'wm_bci_approval_notification_recipients' ) );
	}

	/**
	 * Gravity Forms field map.
	 *
	 * @return array<string,string>
	 */
	public function field_map() {
		$field_map = array();

		foreach ( SettingsSchema::field_map_keys() as $key ) {
			$field_map[ $key ] = $this->text_option( 'wm_bci_field_map_' . $key );
		}

		return $field_map;
	}

	/**
	 * Gravity Forms field ID by semantic key.
	 *
	 * @param string $key Semantic key.
	 * @return string
	 */
	public function field( $key ) {
		$map = $this->field_map();

		return $map[ $key ] ?? '';
	}

	/**
	 * WordPress users whose GF submissions should be auto-approved.
	 *
	 * @return array<int,int>
	 */
	public function auto_approved_user_ids() {
		return self::normalize_user_ids( $this->option( 'wm_bci_auto_approved_user_ids' ) );
	}

	/**
	 * Configured BCI resources page slug.
	 *
	 * @return string
	 */
	public function calendar_page_slug() {
		return sanitize_title( $this->text_option( 'wm_bci_calendar_page_slug' ) );
	}

	/**
	 * Configured GravityCalendar feed name.
	 *
	 * @return string
	 */
	public function calendar_feed_name() {
		return $this->text_option( 'wm_bci_calendar_feed_name' );
	}

	/**
	 * Configured GravityCalendar feed ID.
	 *
	 * @return int
	 */
	public function calendar_feed_id() {
		return absint( $this->option( 'wm_bci_calendar_feed_id' ) );
	}

	/**
	 * Raw saved GravityCalendar shortcode source.
	 *
	 * @return string
	 */
	public function calendar_shortcode_source() {
		return $this->text_option( 'wm_bci_calendar_shortcode' );
	}

	/**
	 * Sanitized saved GravityCalendar shortcode.
	 *
	 * @return string
	 */
	public function calendar_shortcode() {
		return SettingsSchema::sanitize_gravitycalendar_shortcode( $this->calendar_shortcode_source() );
	}

	/**
	 * Saved BCI Hub Video Slider wrapper and slide config for classic usage.
	 *
	 * @return array{eyebrow:string,title:string,intro:string,slides:array<int,array<string,mixed>>}
	 */
	public function video_slider_context() {
		return array(
			'eyebrow' => $this->video_slider_eyebrow(),
			'title'   => $this->video_slider_title(),
			'intro'   => $this->video_slider_intro(),
			'slides'  => $this->video_slider_slides(),
		);
	}

	/**
	 * Saved BCI Hub Video Slider eyebrow.
	 *
	 * @return string
	 */
	public function video_slider_eyebrow() {
		$value = sanitize_text_field( trim( (string) $this->acf_option_value( 'wm_bci_video_slider_eyebrow' ) ) );

		if ( '' === $value ) {
			$value = sanitize_text_field( trim( (string) SettingsSchema::default_for( 'wm_bci_video_slider_eyebrow' ) ) );
		}

		return $value;
	}

	/**
	 * Saved BCI Hub Video Slider title.
	 *
	 * @return string
	 */
	public function video_slider_title() {
		$value = sanitize_text_field( trim( (string) $this->acf_option_value( 'wm_bci_video_slider_title' ) ) );

		if ( '' === $value ) {
			$value = sanitize_text_field( trim( (string) SettingsSchema::default_for( 'wm_bci_video_slider_title' ) ) );
		}

		return $value;
	}

	/**
	 * Saved BCI Hub Video Slider intro.
	 *
	 * @return string
	 */
	public function video_slider_intro() {
		$value = $this->acf_option_value( 'wm_bci_video_slider_intro' );

		if ( ! is_scalar( $value ) ) {
			$value = SettingsSchema::default_for( 'wm_bci_video_slider_intro' );
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			$value = trim( (string) SettingsSchema::default_for( 'wm_bci_video_slider_intro' ) );
		}

		return function_exists( 'wp_kses_post' ) ? (string) wp_kses_post( $value ) : $value;
	}

	/**
	 * Saved BCI Hub Video Slider slides normalized to the renderer's expected shape.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function video_slider_slides() {
		$rows = SettingsSchema::sanitize_value(
			'wm_bci_video_slider_slides',
			$this->video_slider_rows_value()
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$slides = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$slides[] = array(
				'videoId'          => trim( (string) ( $row['video_id'] ?? '' ) ),
				'videoUrl'         => trim( (string) ( $row['video_url'] ?? '' ) ),
				'thumbnailId'      => absint( $row['thumbnail_id'] ?? 0 ),
				'logoId'           => absint( $row['logo_id'] ?? 0 ),
				'logoLabel'        => trim( (string) ( $row['logo_label'] ?? '' ) ),
				'slideEyebrow'     => trim( (string) ( $row['slide_eyebrow'] ?? '' ) ),
				'slideTitle'       => trim( (string) ( $row['slide_title'] ?? '' ) ),
				'slideDescription' => trim( (string) ( $row['slide_description'] ?? '' ) ),
			);
		}

		return $slides;
	}

	/**
	 * Saved BCI Hub Newsletter Archives wrapper and card config for classic usage.
	 *
	 * @return array{eyebrow:string,title:string,cards:array<int,array<string,mixed>>}
	 */
	public function newsletter_archives_context() {
		return array(
			'eyebrow' => $this->newsletter_archives_eyebrow(),
			'title'   => $this->newsletter_archives_title(),
			'cards'   => $this->newsletter_archive_cards(),
		);
	}

	/**
	 * Saved Newsletter Archives eyebrow.
	 *
	 * @return string
	 */
	public function newsletter_archives_eyebrow() {
		$value = sanitize_text_field( trim( (string) $this->acf_option_value( 'wm_bci_newsletter_archives_eyebrow' ) ) );

		if ( '' === $value ) {
			$value = sanitize_text_field( trim( (string) SettingsSchema::default_for( 'wm_bci_newsletter_archives_eyebrow' ) ) );
		}

		return $value;
	}

	/**
	 * Saved Newsletter Archives title.
	 *
	 * @return string
	 */
	public function newsletter_archives_title() {
		$value = sanitize_text_field( trim( (string) $this->acf_option_value( 'wm_bci_newsletter_archives_title' ) ) );

		if ( '' === $value ) {
			$value = sanitize_text_field( trim( (string) SettingsSchema::default_for( 'wm_bci_newsletter_archives_title' ) ) );
		}

		return $value;
	}

	/**
	 * Saved Newsletter Archives cards normalized to the renderer's expected shape.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function newsletter_archive_cards() {
		$rows = SettingsSchema::sanitize_value(
			'wm_bci_newsletter_archive_cards',
			$this->newsletter_archive_rows_value()
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$cards = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$cards[] = array(
				'issueLabel'  => trim( (string) ( $row['issue_label'] ?? '' ) ),
				'title'       => trim( (string) ( $row['title'] ?? '' ) ),
				'url'         => trim( (string) ( $row['url'] ?? '' ) ),
				'imagePreset' => trim( (string) ( $row['image_preset'] ?? '' ) ),
			);
		}

		return $cards;
	}

	/**
	 * Read the saved Video Slider rows from ACF or from split fallback options.
	 *
	 * @return mixed
	 */
	private function video_slider_rows_value() {
		if ( $this->can_read_acf_option_values() ) {
			$rows = get_field( 'wm_bci_video_slider_slides', 'option' );

			if ( is_array( $rows ) ) {
				return $rows;
			}

			$rows = get_field( 'wm_bci_video_slider_slides', 'options' );

			if ( is_array( $rows ) ) {
				return $rows;
			}
		}

		$rows = $this->option( 'wm_bci_video_slider_slides' );

		if ( is_array( $rows ) && ! empty( $rows ) ) {
			return $rows;
		}

		return $this->video_slider_rows_from_split_options();
	}

	/**
	 * Read the saved Newsletter Archives rows from ACF or from split fallback options.
	 *
	 * @return mixed
	 */
	private function newsletter_archive_rows_value() {
		if ( $this->can_read_acf_option_values() ) {
			$rows = get_field( 'wm_bci_newsletter_archive_cards', 'option' );

			if ( is_array( $rows ) ) {
				return $rows;
			}

			$rows = get_field( 'wm_bci_newsletter_archive_cards', 'options' );

			if ( is_array( $rows ) ) {
				return $rows;
			}
		}

		$rows = $this->option( 'wm_bci_newsletter_archive_cards' );

		if ( is_array( $rows ) && ! empty( $rows ) ) {
			return $rows;
		}

		return $this->newsletter_archive_rows_from_split_options();
	}

	/**
	 * Plugin-owned BCI member post type slug.
	 *
	 * @return string
	 */
	public function member_post_type() {
		return Schema::MEMBER_POST_TYPE;
	}

	/**
	 * Plugin-owned BCI opportunity post type slug.
	 *
	 * @return string
	 */
	public function opportunity_post_type() {
		return Schema::OPPORTUNITY_POST_TYPE;
	}

	/**
	 * Plugin-owned BCI opportunity type taxonomy slug.
	 *
	 * @return string
	 */
	public function opportunity_type_taxonomy() {
		return Schema::OPPORTUNITY_TYPE_TAXONOMY;
	}

	/**
	 * Plugin-owned BCI opportunity tag taxonomy slug.
	 *
	 * @return string
	 */
	public function opportunity_tag_taxonomy() {
		return Schema::OPPORTUNITY_TAG_TAXONOMY;
	}

	/**
	 * Member field name by semantic key.
	 *
	 * @param string $key Semantic key.
	 * @return string
	 */
	public function member_field_name( $key ) {
		return Schema::member_field_name( $key );
	}

	/**
	 * Opportunity field name by semantic key.
	 *
	 * @param string $key Semantic key.
	 * @return string
	 */
	public function opportunity_field_name( $key ) {
		return Schema::opportunity_field_name( $key );
	}

	/**
	 * Whether the provided type resolves to the Grant / RFP taxonomy term.
	 *
	 * @param string $raw_type Raw type value.
	 * @return bool
	 */
	public function is_grant_opportunity_type( $raw_type ) {
		return 'grant-rfp' === $this->calendar_event_type_slug( $raw_type );
	}

	/**
	 * Configured display config for a submitted or saved opportunity type.
	 *
	 * @param string $raw_type Raw type value.
	 * @return array{term_id:int,label:string,slug:string,color:string,thumbnail_id:int,name:string,alias:string}
	 */
	public function opportunity_type_config( $raw_type ) {
		$raw_type = trim( (string) $raw_type );

		if ( '' === $raw_type ) {
			return array(
				'term_id'      => 0,
				'label'        => '',
				'slug'         => '',
				'color'        => '',
				'thumbnail_id' => 0,
				'name'         => '',
				'alias'        => '',
			);
		}

		$term = $this->opportunity_type_term_for_value( $raw_type );

		if ( ! $term ) {
			return array(
				'term_id'      => 0,
				'label'        => $raw_type,
				'slug'         => sanitize_title( $raw_type ),
				'color'        => '',
				'thumbnail_id' => 0,
				'name'         => $raw_type,
				'alias'        => '',
			);
		}

		$term_id = absint( $this->term_property( $term, 'term_id' ) );
		$name    = trim( (string) $this->term_property( $term, 'name' ) );
		$slug    = trim( (string) $this->term_property( $term, 'slug' ) );
		$alias   = trim( (string) $this->term_meta_value( 'alias', $term_id ) );
		$label   = '' !== $alias ? $alias : $name;
		$color   = self::normalize_hex_color( (string) $this->term_meta_value( 'color', $term_id ) );

		return array(
			'term_id'      => $term_id,
			'label'        => $label,
			'slug'         => '' !== $slug ? sanitize_title( $slug ) : sanitize_title( $label ),
			'color'        => $color,
			'thumbnail_id' => self::attachment_id_from_term_meta( $this->term_meta_value( 'thumbnail', $term_id ) ),
			'name'         => $name,
			'alias'        => $alias,
		);
	}

	/**
	 * Configured opportunity type rows.
	 *
	 * @return array<int,array{type:string,slug:string,source_values:array<int,string>,color:string,thumbnail:int,term_id:int}>
	 */
	public function calendar_event_types() {
		$types = array();

		foreach ( $this->opportunity_type_terms() as $term ) {
			$config = $this->opportunity_type_config( $this->term_property( $term, 'slug' ) );

			if ( '' === $config['label'] ) {
				continue;
			}

			$source_values = array();

			foreach ( array( $config['name'], $config['alias'], $config['slug'] ) as $value ) {
				$value = trim( (string) $value );

				if ( '' !== $value ) {
					$source_values[] = $value;
				}
			}

			$types[] = array(
				'type'          => $config['label'],
				'slug'          => $config['slug'],
				'source_values' => array_values( array_unique( $source_values ) ),
				'color'         => $config['color'],
				'thumbnail'     => $config['thumbnail_id'],
				'term_id'       => $config['term_id'],
			);
		}

		return $types;
	}

	/**
	 * Configured display label for a submitted or saved opportunity type.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	public function calendar_event_type_label( $type ) {
		return $this->opportunity_type_config( $type )['label'];
	}

	/**
	 * Configured display slug for a submitted or saved opportunity type.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	public function calendar_event_type_slug( $type ) {
		return $this->opportunity_type_config( $type )['slug'];
	}

	/**
	 * Configured color for a BCI opportunity type.
	 *
	 * @param string $type Raw type.
	 * @return string
	 */
	public function calendar_event_color( $type ) {
		return $this->opportunity_type_config( $type )['color'];
	}

	/**
	 * Configured resource thumbnail attachment ID for a BCI opportunity type.
	 *
	 * @param string $type Raw type.
	 * @return int
	 */
	public function calendar_event_thumbnail_id( $type ) {
		return $this->opportunity_type_config( $type )['thumbnail_id'];
	}

	/**
	 * Configured resource thumbnail attachment IDs by BCI opportunity type.
	 *
	 * @return array<string,int>
	 */
	public function calendar_event_thumbnails() {
		$thumbnails = array();

		foreach ( $this->calendar_event_types() as $event_type ) {
			if ( '' === $event_type['type'] || ! $event_type['thumbnail'] ) {
				continue;
			}

			$thumbnails[ $event_type['type'] ] = absint( $event_type['thumbnail'] );
		}

		return $thumbnails;
	}

	/**
	 * Event color palette allowed by the workflow settings.
	 *
	 * @return array<string,string>
	 */
	public static function calendar_event_palette() {
		return array(
			'#004966' => __( 'Dark Blue', 'community-resources-hub' ),
			'#d9a242' => __( 'Gold', 'community-resources-hub' ),
			'#b34d34' => __( 'Rust', 'community-resources-hub' ),
			'#7e5f8e' => __( 'Plum', 'community-resources-hub' ),
			'#5c6e7a' => __( 'Slate', 'community-resources-hub' ),
			'#520066' => __( 'Purple', 'community-resources-hub' ),
			'#c2385a' => __( 'Rose', 'community-resources-hub' ),
			'#418359' => __( 'Green', 'community-resources-hub' ),
		);
	}

	/**
	 * Google Apps Script sync endpoint URL.
	 *
	 * @return string
	 */
	public function google_sync_url() {
		$option = $this->text_option( 'wm_bci_google_sync_url' );

		return '' !== $option ? esc_url_raw( $option ) : '';
	}

	/**
	 * Google Apps Script shared secret.
	 *
	 * @return string
	 */
	public function google_sync_secret() {
		return (string) $this->option( 'wm_bci_google_sync_secret' );
	}

	/**
	 * Whether Google sync has all required settings.
	 *
	 * @return bool
	 */
	public function is_google_sync_configured() {
		return '' !== $this->google_sync_url() && '' !== $this->google_sync_secret();
	}

	/**
	 * Supported approval status labels.
	 *
	 * @return array<string,string>
	 */
	public static function approval_statuses() {
		return array(
			'pending'  => 'Pending',
			'approved' => 'Approved',
			'rejected' => 'Rejected',
		);
	}

	/**
	 * Label for a sanitized approval status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public function status_label( $status ) {
		$statuses = self::approval_statuses();
		$key      = sanitize_key( $status );

		return $statuses[ $key ] ?? '';
	}

	/**
	 * Normalize user IDs.
	 *
	 * @param mixed $raw Raw option value.
	 * @return array<int,int>
	 */
	public static function normalize_user_ids( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$ids  = array();
		$seen = array();

		foreach ( $raw as $value ) {
			$user_id = absint( $value );

			if ( ! $user_id || isset( $seen[ $user_id ] ) ) {
				continue;
			}

			$seen[ $user_id ] = true;
			$ids[]            = $user_id;
		}

		return $ids;
	}

	/**
	 * Normalize a comma/newline-separated recipient list.
	 *
	 * @param string $raw Raw recipient list.
	 * @return string
	 */
	public static function normalize_recipient_list( $raw ) {
		$parts = preg_split( '/[\r\n,]+/', (string) $raw );

		if ( false === $parts ) {
			return '';
		}

		$valid = array();
		$seen  = array();

		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			$key   = strtolower( $email );

			if ( '' === $email || false === is_email( $email ) || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$valid[]      = $email;
		}

		return implode( ', ', $valid );
	}

	/**
	 * Plugin settings option value by field name.
	 *
	 * @param string $field_name Field name.
	 * @return mixed
	 */
	private function option( $field_name ) {
		$value = get_option( SettingsSchema::option_name( $field_name ), null );

		if ( null === $value ) {
			return SettingsSchema::default_for( $field_name );
		}

		return $value;
	}

	/**
	 * Rebuild Video Slider rows from split ACF options when the repeater parent is unusable.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function video_slider_rows_from_split_options() {
		$rows         = array();
		$max_rows     = max( 1, $this->video_slider_split_row_limit() );
		$empty_streak = 0;

		for ( $index = 0; $index < $max_rows; $index++ ) {
			$row = array(
				'video_id'          => (string) get_option( 'options_wm_bci_video_slider_slides_' . $index . '_video_id', '' ),
				'video_url'         => (string) get_option( 'options_wm_bci_video_slider_slides_' . $index . '_video_url', '' ),
				'thumbnail_id'      => get_option( 'options_wm_bci_video_slider_slides_' . $index . '_thumbnail_id', 0 ),
				'logo_id'           => get_option( 'options_wm_bci_video_slider_slides_' . $index . '_logo_id', 0 ),
				'logo_label'        => (string) get_option( 'options_wm_bci_video_slider_slides_' . $index . '_logo_label', '' ),
				'slide_eyebrow'     => (string) get_option( 'options_wm_bci_video_slider_slides_' . $index . '_slide_eyebrow', '' ),
				'slide_title'       => (string) get_option( 'options_wm_bci_video_slider_slides_' . $index . '_slide_title', '' ),
				'slide_description' => (string) get_option( 'options_wm_bci_video_slider_slides_' . $index . '_slide_description', '' ),
			);

			if ( ! $this->video_slider_split_row_has_content( $row ) ) {
				$empty_streak++;

				if ( $empty_streak > 0 && ! empty( $rows ) ) {
					break;
				}

				continue;
			}

			$empty_streak = 0;
			$rows[]       = $row;
		}

		return $rows;
	}

	/**
	 * Rebuild Newsletter Archives rows from split ACF options when the repeater parent is unusable.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function newsletter_archive_rows_from_split_options() {
		$rows         = array();
		$max_rows     = max( 1, $this->newsletter_archive_split_row_limit() );
		$empty_streak = 0;

		for ( $index = 0; $index < $max_rows; $index++ ) {
			$row = array(
				'issue_label'  => (string) get_option( 'options_wm_bci_newsletter_archive_cards_' . $index . '_issue_label', '' ),
				'title'        => (string) get_option( 'options_wm_bci_newsletter_archive_cards_' . $index . '_title', '' ),
				'url'          => (string) get_option( 'options_wm_bci_newsletter_archive_cards_' . $index . '_url', '' ),
				'image_preset' => (string) get_option( 'options_wm_bci_newsletter_archive_cards_' . $index . '_image_preset', '' ),
			);

			if ( ! $this->newsletter_archive_split_row_has_content( $row ) ) {
				$empty_streak++;

				if ( $empty_streak > 0 && ! empty( $rows ) ) {
					break;
				}

				continue;
			}

			$empty_streak = 0;
			$rows[]       = $row;
		}

		return $rows;
	}

	/**
	 * Read an ACF-backed options-page value after ACF has initialized, with an option fallback.
	 *
	 * @param string $field_name Field name.
	 * @return mixed
	 */
	private function acf_option_value( $field_name ) {
		if ( $this->can_read_acf_option_values() ) {
			$value = get_field( $field_name, 'option' );

			if ( null !== $value && false !== $value ) {
				return $value;
			}
		}

		return $this->option( $field_name );
	}

	/**
	 * Trimmed scalar settings option value.
	 *
	 * @param string $field_name Field name.
	 * @return string
	 */
	private function text_option( $field_name ) {
		return trim( (string) $this->option( $field_name ) );
	}

	/**
	 * Maximum number of split ACF rows to probe for saved Video Slider content.
	 *
	 * @return int
	 */
	private function video_slider_split_row_limit() {
		$count = $this->option( 'wm_bci_video_slider_slides' );

		if ( is_numeric( $count ) ) {
			return max( 1, absint( $count ) );
		}

		return 25;
	}

	/**
	 * Maximum number of split ACF rows to probe for saved Newsletter Archives content.
	 *
	 * @return int
	 */
	private function newsletter_archive_split_row_limit() {
		$count = $this->option( 'wm_bci_newsletter_archive_cards' );

		if ( is_numeric( $count ) ) {
			return max( 1, absint( $count ) );
		}

		return 25;
	}

	/**
	 * Whether a rebuilt split Video Slider row still contains any meaningful content.
	 *
	 * @param array<string,mixed> $row Split row values.
	 * @return bool
	 */
	private function video_slider_split_row_has_content( array $row ) {
		foreach ( array( 'video_id', 'video_url', 'logo_label', 'slide_eyebrow', 'slide_title', 'slide_description' ) as $key ) {
			if ( '' !== trim( (string) ( $row[ $key ] ?? '' ) ) ) {
				return true;
			}
		}

		return absint( $row['thumbnail_id'] ?? 0 ) > 0 || absint( $row['logo_id'] ?? 0 ) > 0;
	}

	/**
	 * Whether a rebuilt split Newsletter Archives row still contains any meaningful content.
	 *
	 * @param array<string,mixed> $row Split row values.
	 * @return bool
	 */
	private function newsletter_archive_split_row_has_content( array $row ) {
		foreach ( array( 'issue_label', 'title', 'url' ) as $key ) {
			if ( '' !== trim( (string) ( $row[ $key ] ?? '' ) ) ) {
				return true;
			}
		}

		return '' !== trim( (string) ( $row['image_preset'] ?? '' ) );
	}

	/**
	 * Whether ACF option values can be read without triggering early-init notices.
	 *
	 * @return bool
	 */
	private function can_read_acf_option_values() {
		return function_exists( 'get_field' ) && ( ! function_exists( 'did_action' ) || did_action( 'acf/init' ) );
	}

	/**
	 * @return array<int,mixed>
	 */
	private function opportunity_type_terms() {
		if ( array_key_exists( 'opportunity_type_terms', $this->cache ) ) {
			return $this->cache['opportunity_type_terms'];
		}

		$terms = array();

		if ( function_exists( 'get_terms' ) ) {
			$raw_terms = get_terms(
				array(
					'taxonomy'   => $this->opportunity_type_taxonomy(),
					'hide_empty' => false,
				)
			);

			if ( is_array( $raw_terms ) ) {
				$terms = $raw_terms;
			}
		}

		$this->cache['opportunity_type_terms'] = $terms;

		return $terms;
	}

	/**
	 * @param string $raw_type Raw type value.
	 * @return array<string,mixed>|object|null
	 */
	private function opportunity_type_term_for_value( $raw_type ) {
		$raw_type = trim( (string) $raw_type );

		if ( '' === $raw_type ) {
			return null;
		}

		$numeric_type = absint( $raw_type );

		if ( $numeric_type > 0 ) {
			foreach ( $this->opportunity_type_terms() as $term ) {
				if ( $numeric_type === absint( $this->term_property( $term, 'term_id' ) ) ) {
					return $term;
				}
			}

			foreach ( Schema::default_opportunity_types() as $definition ) {
				if ( $numeric_type !== absint( $definition['legacy_term_id'] ?? 0 ) ) {
					continue;
				}

				foreach ( $this->opportunity_type_terms() as $term ) {
					if ( trim( (string) $this->term_property( $term, 'slug' ) ) === (string) $definition['slug'] ) {
						return $term;
					}
				}
			}
		}

		$type_key = self::opportunity_type_match_key( $raw_type );

		foreach ( $this->opportunity_type_terms() as $term ) {
			foreach ( $this->opportunity_type_term_match_candidates( $term ) as $candidate ) {
				if ( $type_key === self::opportunity_type_match_key( $candidate ) ) {
					return $term;
				}
			}
		}

		$type_tokens = self::opportunity_type_match_tokens( $raw_type );

		foreach ( $this->opportunity_type_terms() as $term ) {
			foreach ( $this->opportunity_type_term_match_candidates( $term ) as $candidate ) {
				$candidate_tokens = self::opportunity_type_match_tokens( $candidate );

				if ( self::opportunity_type_tokens_contain( $candidate_tokens, $type_tokens ) || self::opportunity_type_tokens_contain( $type_tokens, $candidate_tokens ) ) {
					return $term;
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string,mixed>|object $term Term object or array.
	 * @return array<int,string>
	 */
	private function opportunity_type_term_match_candidates( $term ) {
		$term_id = absint( $this->term_property( $term, 'term_id' ) );
		$name    = trim( (string) $this->term_property( $term, 'name' ) );
		$slug    = trim( (string) $this->term_property( $term, 'slug' ) );
		$alias   = trim( (string) $this->term_meta_value( 'alias', $term_id ) );
		$values  = array();

		foreach ( array( $name, $alias, $slug ) as $value ) {
			if ( '' !== $value ) {
				$values[] = $value;
			}
		}

		return array_values( array_unique( $values ) );
	}

	/**
	 * Raw term meta value.
	 *
	 * @param string $field_name Field name.
	 * @param int    $term_id    Term ID.
	 * @return mixed
	 */
	private function term_meta_value( $field_name, $term_id ) {
		if ( ! $term_id || ! function_exists( 'get_term_meta' ) ) {
			return '';
		}

		return get_term_meta( $term_id, $field_name, true );
	}

	/**
	 * Term property from arrays or objects.
	 *
	 * @param array<string,mixed>|object $term Term.
	 * @param string                     $property Property name.
	 * @return mixed
	 */
	private function term_property( $term, $property ) {
		if ( is_array( $term ) ) {
			return $term[ $property ] ?? '';
		}

		if ( is_object( $term ) && isset( $term->{$property} ) ) {
			return $term->{$property};
		}

		return '';
	}

	/**
	 * Normalize a term-meta thumbnail value to an attachment ID.
	 *
	 * @param mixed $value Term-meta value.
	 * @return int
	 */
	private static function attachment_id_from_term_meta( $value ) {
		if ( is_array( $value ) ) {
			if ( ! empty( $value['ID'] ) ) {
				return absint( $value['ID'] );
			}

			if ( ! empty( $value['id'] ) ) {
				return absint( $value['id'] );
			}
		}

		return absint( $value );
	}

	/**
	 * Normalize a hex color.
	 *
	 * @param string $color Raw color.
	 * @return string
	 */
	private static function normalize_hex_color( $color ) {
		$color   = trim( strtolower( (string) $color ) );
		$allowed = array_fill_keys( array_keys( self::calendar_event_palette() ), true );

		return isset( $allowed[ $color ] ) ? $color : '';
	}

	/**
	 * Stable match key for opportunity type values.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function opportunity_type_match_key( $value ) {
		return implode( ' ', self::opportunity_type_match_tokens( $value ) );
	}

	/**
	 * Tokenize opportunity type values for exact and containment matching.
	 *
	 * @param string $value Raw value.
	 * @return array<int,string>
	 */
	private static function opportunity_type_match_tokens( $value ) {
		$value      = strtolower( sanitize_text_field( $value ) );
		$value      = str_replace( '&', ' and ', $value );
		$value      = preg_replace( '/[^a-z0-9]+/', ' ', $value );
		$raw_tokens = preg_split( '/\s+/', trim( (string) $value ) );
		$tokens     = array();

		foreach ( is_array( $raw_tokens ) ? $raw_tokens : array() as $token ) {
			if ( in_array( $token, array( 'a', 'an', 'and', 'or', 'the' ), true ) ) {
				continue;
			}

			$token = self::opportunity_type_singular_token( $token );

			if ( '' !== $token ) {
				$tokens[] = $token;
			}
		}

		$tokens = array_values( array_unique( $tokens ) );
		sort( $tokens );

		return $tokens;
	}

	/**
	 * Basic plural normalization for editor-entered type words.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private static function opportunity_type_singular_token( $token ) {
		$token = trim( (string) $token );

		if ( strlen( $token ) > 4 && 's' === substr( $token, -1 ) && 'ss' !== substr( $token, -2 ) ) {
			return substr( $token, 0, -1 );
		}

		return $token;
	}

	/**
	 * Whether all needles are present in a token list.
	 *
	 * @param array<int,string> $haystack Tokens.
	 * @param array<int,string> $needles Tokens.
	 * @return bool
	 */
	private static function opportunity_type_tokens_contain( array $haystack, array $needles ) {
		if ( empty( $haystack ) || empty( $needles ) ) {
			return false;
		}

		return empty( array_diff( $needles, $haystack ) );
	}
}
