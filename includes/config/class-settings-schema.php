<?php
/**
 * Plugin-owned BCI settings schema.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical settings names, defaults, and ACF settings UI schema.
 */
final class SettingsSchema {

	const OPTIONS_PAGE_SLUG  = 'bci-hub';
	const OPTIONS_PAGE_TITLE = 'Community Resources Hub';
	const OPTIONS_GROUP_KEY  = 'group_wm_bci_workflow_settings';
	const CAPABILITY         = 'manage_options';

	/**
	 * Current workflow settings field names in UI order.
	 *
	 * @return array<int,string>
	 */
	public static function setting_names() {
		return array(
			'wm_bci_form_id',
			'wm_bci_approval_field_id',
			'wm_bci_notification_name',
			'wm_bci_approval_notification_recipients',
			'wm_bci_auto_approved_user_ids',
			'wm_bci_calendar_page_slug',
			'wm_bci_calendar_feed_name',
			'wm_bci_calendar_feed_id',
			'wm_bci_calendar_shortcode',
			'wm_bci_video_slider_eyebrow',
			'wm_bci_video_slider_title',
			'wm_bci_video_slider_intro',
			'wm_bci_video_slider_slides',
			'wm_bci_newsletter_archives_eyebrow',
			'wm_bci_newsletter_archives_title',
			'wm_bci_newsletter_archive_cards',
			'wm_bci_google_sync_url',
			'wm_bci_google_sync_secret',
		);
	}

	/**
	 * Current workflow settings and field-map field names.
	 *
	 * @return array<int,string>
	 */
	public static function all_setting_names() {
		return array_merge(
			self::setting_names(),
			array_map(
				static function ( $key ) {
					return 'wm_bci_field_map_' . $key;
				},
				self::field_map_keys()
			)
		);
	}

	/**
	 * Current Gravity Forms field-map keys.
	 *
	 * @return array<int,string>
	 */
	public static function field_map_keys() {
		return array(
			'time_sensitive',
			'opportunity_type',
			'non_date_sensitive_type',
			'bci_update',
			'submitter_name',
			'title',
			'organization',
			'start_date',
			'grant_deadline',
			'end_date',
			'start_time',
			'end_time',
			'cost',
			'address',
			'location_mode',
			'description',
			'info_url',
			'file_upload',
			'approval_status',
		);
	}

	/**
	 * Field-map UI defaults.
	 *
	 * @return array<string,string>
	 */
	public static function field_map_defaults() {
		return array(
			'time_sensitive'          => '24',
			'opportunity_type'        => '1',
			'non_date_sensitive_type' => '25',
			'bci_update'              => '26',
			'submitter_name'          => '3',
			'title'                   => '4',
			'organization'            => '5',
			'start_date'              => '6',
			'grant_deadline'          => '9',
			'end_date'                => '10',
			'start_time'              => '12',
			'end_time'                => '21',
			'cost'                    => '14',
			'address'                 => '15',
			'location_mode'           => '16',
			'description'             => '17',
			'info_url'                => '18',
			'file_upload'             => '19',
			'approval_status'         => '22',
		);
	}

	/**
	 * Plugin-owned settings defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		$defaults = array(
			'wm_bci_form_id'                           => 0,
			'wm_bci_approval_field_id'                 => '22',
			'wm_bci_notification_name'                 => 'Admin Notification',
			'wm_bci_approval_notification_recipients'  => '',
			'wm_bci_auto_approved_user_ids'            => array(),
			'wm_bci_calendar_page_slug'                => 'bci-resources',
			'wm_bci_calendar_feed_name'                => 'BCI Community Opportunity Submission',
			'wm_bci_calendar_feed_id'                  => 0,
			'wm_bci_calendar_shortcode'                => '',
			'wm_bci_video_slider_eyebrow'              => 'Spotlight Videos',
			'wm_bci_video_slider_title'                => 'See the BCI Community in action',
			'wm_bci_video_slider_intro'                => 'Our <strong>Rooted in Community video series</strong> goes behind the scenes with BCI partner organizations &mdash; sharing their stories, their work, and the impact they\'re making across the region.',
			'wm_bci_video_slider_slides'               => 0,
			'wm_bci_newsletter_archives_eyebrow'       => 'Newsletter Archives',
			'wm_bci_newsletter_archives_title'         => 'Access past monthly newsletters through the cards below.',
			'wm_bci_newsletter_archive_cards'          => 0,
			'wm_bci_google_sync_url'                   => '',
			'wm_bci_google_sync_secret'                => '',
		);

		foreach ( self::field_map_defaults() as $key => $value ) {
			$defaults[ 'wm_bci_field_map_' . $key ] = $value;
		}

		return $defaults;
	}

	/**
	 * Default value for a setting field.
	 *
	 * @param string $field_name Field name.
	 * @return mixed
	 */
	public static function default_for( $field_name ) {
		$defaults = self::defaults();

		return array_key_exists( $field_name, $defaults ) ? $defaults[ $field_name ] : '';
	}

	/**
	 * Bundled Figma image presets available to Newsletter Archives cards.
	 *
	 * @return array<string,string>
	 */
	public static function newsletter_archive_image_presets() {
		$presets = array();

		for ( $index = 1; $index <= 8; $index++ ) {
			$key             = 'newsletter-img-' . $index;
			$presets[ $key ] = sprintf(
				/* translators: %d: Newsletter preset image number. */
				__( 'Newsletter image %d', 'community-resources-hub' ),
				$index
			);
		}

		return $presets;
	}

	/**
	 * Stored option name for an ACF options-page field.
	 *
	 * @param string $field_name Field name.
	 * @return string
	 */
	public static function option_name( $field_name ) {
		return 'options_' . (string) $field_name;
	}

	/**
	 * Stored option names for all plugin-owned settings.
	 *
	 * @return array<int,string>
	 */
	public static function option_names() {
		return array_map(
			array( __CLASS__, 'option_name' ),
			self::all_setting_names()
		);
	}

	/**
	 * Sanitize one plugin-owned setting value.
	 *
	 * @param string $field_name Field name.
	 * @param mixed  $value Submitted value.
	 * @return mixed
	 */
	public static function sanitize_value( $field_name, $value ) {
		$field_name = (string) $field_name;

		if ( 'wm_bci_form_id' === $field_name || 'wm_bci_calendar_feed_id' === $field_name ) {
			return absint( $value );
		}

		if ( 'wm_bci_approval_notification_recipients' === $field_name ) {
			return self::sanitize_recipient_list( $value );
		}

		if ( 'wm_bci_auto_approved_user_ids' === $field_name ) {
			return self::sanitize_user_ids( $value );
		}

		if ( 'wm_bci_calendar_page_slug' === $field_name ) {
			return sanitize_title( (string) $value );
		}

		if ( 'wm_bci_calendar_shortcode' === $field_name ) {
			return self::sanitize_gravitycalendar_shortcode( $value );
		}

		if ( 'wm_bci_video_slider_intro' === $field_name ) {
			return self::sanitize_video_slider_intro( $value );
		}

		if ( 'wm_bci_video_slider_slides' === $field_name ) {
			return self::sanitize_video_slider_slides( $value );
		}

		if ( 'wm_bci_newsletter_archive_cards' === $field_name ) {
			return self::sanitize_newsletter_archive_cards( $value );
		}

		if ( 'wm_bci_google_sync_url' === $field_name ) {
			return esc_url_raw( trim( (string) $value ), array( 'http', 'https' ) );
		}

		if ( 'wm_bci_approval_field_id' === $field_name || 0 === strpos( $field_name, 'wm_bci_field_map_' ) ) {
			return self::sanitize_gravity_field_id( $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Sanitize a saved GravityCalendar shortcode source.
	 *
	 * @param mixed $source Raw shortcode source.
	 * @return string
	 */
	public static function sanitize_gravitycalendar_shortcode( $source ) {
		$source = sanitize_text_field( (string) $source );
		$source = preg_replace( '/\s+/', ' ', trim( $source ) );
		$source = is_string( $source ) ? $source : '';

		return self::is_gravitycalendar_shortcode( $source ) ? $source : '';
	}

	/**
	 * Whether a string is an accepted GravityCalendar shortcode.
	 *
	 * @param mixed $source Raw shortcode source.
	 * @return bool
	 */
	public static function is_gravitycalendar_shortcode( $source ) {
		$source = trim( (string) $source );

		return '' !== $source && 1 === preg_match( '/^\[gravitycalendar(?:\s[^\]]*)?\/?\]$/i', $source );
	}

	/**
	 * Sanitize a Gravity Forms field ID, including sub-input IDs.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_gravity_field_id( $value ) {
		$value = sanitize_text_field( trim( (string) $value ) );

		return preg_replace( '/[^A-Za-z0-9_.-]/', '', $value );
	}

	/**
	 * Normalize WordPress user IDs.
	 *
	 * @param mixed $raw Raw ACF user field value.
	 * @return array<int,int>
	 */
	private static function sanitize_user_ids( $raw ) {
		$values = is_array( $raw ) ? $raw : array();
		$ids    = array();
		$seen   = array();

		foreach ( $values as $value ) {
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
	 * Normalize approval notification recipients.
	 *
	 * @param mixed $raw Raw recipient list.
	 * @return string
	 */
	private static function sanitize_recipient_list( $raw ) {
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
	 * Sanitize the saved Video Slider intro value.
	 *
	 * @param mixed $raw Raw intro value.
	 * @return string
	 */
	private static function sanitize_video_slider_intro( $raw ) {
		$value = trim( (string) $raw );

		if ( function_exists( 'wp_kses_post' ) ) {
			return (string) wp_kses_post( $value );
		}

		if ( function_exists( 'sanitize_textarea_field' ) ) {
			return sanitize_textarea_field( $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize raw Video Slider rows from the ACF repeater.
	 *
	 * @param mixed $raw Raw repeater value.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitize_video_slider_slides( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$slides = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$sanitized = array(
				'video_id'          => self::sanitize_video_slider_video_id( $row['video_id'] ?? '' ),
				'video_url'         => esc_url_raw( trim( (string) ( $row['video_url'] ?? '' ) ), array( 'http', 'https' ) ),
				'thumbnail_id'      => absint( $row['thumbnail_id'] ?? 0 ),
				'logo_id'           => absint( $row['logo_id'] ?? 0 ),
				'logo_label'        => sanitize_text_field( (string) ( $row['logo_label'] ?? '' ) ),
				'slide_eyebrow'     => sanitize_text_field( (string) ( $row['slide_eyebrow'] ?? '' ) ),
				'slide_title'       => sanitize_text_field( (string) ( $row['slide_title'] ?? '' ) ),
				'slide_description' => function_exists( 'sanitize_textarea_field' )
					? sanitize_textarea_field( (string) ( $row['slide_description'] ?? '' ) )
					: sanitize_text_field( (string) ( $row['slide_description'] ?? '' ) ),
			);

			if ( ! self::video_slider_row_has_content( $sanitized ) ) {
				continue;
			}

			$slides[] = $sanitized;
		}

		return $slides;
	}

	/**
	 * Whether a Video Slider row still contains meaningful content after sanitization.
	 *
	 * @param array<string,mixed> $row Sanitized row.
	 * @return bool
	 */
	private static function video_slider_row_has_content( array $row ) {
		foreach ( array( 'video_id', 'video_url', 'logo_label', 'slide_eyebrow', 'slide_title', 'slide_description' ) as $key ) {
			if ( '' !== trim( (string) ( $row[ $key ] ?? '' ) ) ) {
				return true;
			}
		}

		return ! empty( $row['thumbnail_id'] ) || ! empty( $row['logo_id'] );
	}

	/**
	 * Sanitize a manually entered YouTube video ID.
	 *
	 * @param mixed $raw Raw video ID value.
	 * @return string
	 */
	private static function sanitize_video_slider_video_id( $raw ) {
		$value = preg_replace( '/[^A-Za-z0-9_-]/', '', trim( (string) $raw ) );

		return is_string( $value ) ? $value : '';
	}

	/**
	 * Sanitize raw Newsletter Archives rows from the ACF repeater.
	 *
	 * @param mixed $raw Raw repeater value.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitize_newsletter_archive_cards( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$cards = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$sanitized = array(
				'issue_label'  => sanitize_text_field( (string) ( $row['issue_label'] ?? '' ) ),
				'title'        => sanitize_text_field( (string) ( $row['title'] ?? '' ) ),
				'url'          => esc_url_raw( trim( (string) ( $row['url'] ?? '' ) ), array( 'http', 'https' ) ),
				'image_preset' => self::sanitize_newsletter_archive_image_preset( $row['image_preset'] ?? '' ),
			);

			if ( ! self::newsletter_archive_card_has_content( $sanitized ) ) {
				continue;
			}

			$cards[] = $sanitized;
		}

		return $cards;
	}

	/**
	 * Whether a Newsletter Archives row still contains meaningful content after sanitization.
	 *
	 * @param array<string,mixed> $row Sanitized row.
	 * @return bool
	 */
	private static function newsletter_archive_card_has_content( array $row ) {
		foreach ( array( 'issue_label', 'title', 'url' ) as $key ) {
			if ( '' !== trim( (string) ( $row[ $key ] ?? '' ) ) ) {
				return true;
			}
		}

		return ! empty( $row['image_preset'] );
	}

	/**
	 * Sanitize a Newsletter Archives bundled image preset key.
	 *
	 * @param mixed $raw Raw preset key.
	 * @return string
	 */
	private static function sanitize_newsletter_archive_image_preset( $raw ) {
		$value = strtolower( trim( (string) $raw ) );
		$value = preg_replace( '/[^a-z0-9_-]/', '', $value );
		$value = is_string( $value ) ? $value : '';

		return array_key_exists( $value, self::newsletter_archive_image_presets() ) ? $value : '';
	}

	/**
	 * Plugin-owned ACF options page args.
	 *
	 * @return array<string,mixed>
	 */
	public static function options_page_args() {
		return array(
			'page_title' => __( self::OPTIONS_PAGE_TITLE, 'community-resources-hub' ),
			'menu_title' => __( self::OPTIONS_PAGE_TITLE, 'community-resources-hub' ),
			'menu_slug'  => self::OPTIONS_PAGE_SLUG,
			'capability' => self::CAPABILITY,
			'icon_url'   => 'dashicons-admin-multisite',
			'position'   => 7,
			'redirect'   => false,
			'autoload'   => false,
		);
	}

	/**
	 * Plugin-owned ACF settings field group.
	 *
	 * @return array<string,mixed>
	 */
	public static function field_group() {
		$fields = array(
			self::tab( 'field_wm_bci_workflow_setup_tab', __( 'Workflow Setup', 'community-resources-hub' ) ),
			self::number_field(
				'field_wm_bci_form_id',
				__( 'Form ID', 'community-resources-hub' ),
				'wm_bci_form_id',
				array(
					'min'  => 1,
					'step' => 1,
				)
			),
			self::text_field(
				'field_wm_bci_approval_field_id',
				__( 'Approval Field ID', 'community-resources-hub' ),
				'wm_bci_approval_field_id',
				array(
					'instructions'  => __( 'Gravity Forms field ID that stores Pending, Approved, or Rejected.', 'community-resources-hub' ),
					'wrapper'       => array( 'width' => '33', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_approval_field_id' ),
				)
			),
			self::text_field(
				'field_wm_bci_notification_name',
				__( 'Notification Name', 'community-resources-hub' ),
				'wm_bci_notification_name',
				array(
					'instructions'  => __( 'Gravity Forms notification name customized for approval review links.', 'community-resources-hub' ),
					'wrapper'       => array( 'width' => '34', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_notification_name' ),
				)
			),
			self::tab( 'field_wm_bci_approvals_tab', __( 'Approvals', 'community-resources-hub' ) ),
			self::textarea_field(
				'field_wm_bci_approval_notification_recipients',
				__( 'Approval Notification Recipients', 'community-resources-hub' ),
				'wm_bci_approval_notification_recipients',
				array(
					'instructions' => __( 'Optional comma- or line-separated email list. Leave blank to use the Gravity Forms notification setting.', 'community-resources-hub' ),
					'rows'         => 4,
				)
			),
			self::user_field(
				'field_wm_bci_auto_approved_user_ids',
				__( 'Auto-Approved Submitters', 'community-resources-hub' ),
				'wm_bci_auto_approved_user_ids',
				array(
					'instructions'   => __( 'Logged-in WordPress users whose future BCI submissions should be approved automatically.', 'community-resources-hub' ),
					'return_format'  => 'id',
					'multiple'       => 1,
					'allow_null'     => 1,
				)
			),
			self::tab( 'field_wm_bci_publishing_tab', __( 'Publishing', 'community-resources-hub' ) ),
			self::text_field(
				'field_wm_bci_calendar_page_slug',
				__( 'Calendar Page Slug', 'community-resources-hub' ),
				'wm_bci_calendar_page_slug',
				array(
					'instructions'  => __( 'Page slug used for BCI resources, review redirects, and calendar publishing.', 'community-resources-hub' ),
					'wrapper'       => array( 'width' => '50', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_calendar_page_slug' ),
				)
			),
			self::text_field(
				'field_wm_bci_calendar_feed_name',
				__( 'Calendar Feed Name', 'community-resources-hub' ),
				'wm_bci_calendar_feed_name',
				array(
					'instructions'  => __( 'GravityCalendar feed name used for BCI opportunity events.', 'community-resources-hub' ),
					'wrapper'       => array( 'width' => '50', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_calendar_feed_name' ),
				)
			),
			self::number_field(
				'field_wm_bci_calendar_feed_id',
				__( 'Calendar Feed ID', 'community-resources-hub' ),
				'wm_bci_calendar_feed_id',
				array(
					'instructions'  => __( 'GravityCalendar feed ID resolved by the BCI Hub provisioning action.', 'community-resources-hub' ),
					'wrapper'       => array( 'width' => '50', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_calendar_feed_id' ),
					'min'           => 1,
					'step'          => 1,
				)
			),
			self::text_field(
				'field_wm_bci_calendar_shortcode',
				__( 'Calendar Shortcode', 'community-resources-hub' ),
				'wm_bci_calendar_shortcode',
				array(
					'instructions'  => __( 'GravityCalendar shortcode used by the opportunity hub when shortcode or template context does not provide one.', 'community-resources-hub' ),
					'wrapper'       => array( 'width' => '100', 'class' => '', 'id' => '' ),
					'placeholder'   => '[gravitycalendar ...]',
					'default_value' => self::default_for( 'wm_bci_calendar_shortcode' ),
				)
			),
			self::tab( 'field_wm_bci_video_slider_tab', __( 'Video Slider', 'community-resources-hub' ) ),
			self::text_field(
				'field_wm_bci_video_slider_eyebrow',
				__( 'Eyebrow', 'community-resources-hub' ),
				'wm_bci_video_slider_eyebrow',
				array(
					'wrapper'       => array( 'width' => '50', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_video_slider_eyebrow' ),
					'placeholder'   => 'Spotlight Videos',
				)
			),
			self::text_field(
				'field_wm_bci_video_slider_title',
				__( 'Title', 'community-resources-hub' ),
				'wm_bci_video_slider_title',
				array(
					'wrapper'       => array( 'width' => '50', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_video_slider_title' ),
					'placeholder'   => 'See the BCI Community in action',
				)
			),
			self::textarea_field(
				'field_wm_bci_video_slider_intro',
				__( 'Intro', 'community-resources-hub' ),
				'wm_bci_video_slider_intro',
				array(
					'rows'          => 4,
					'new_lines'     => 'wpautop',
					'default_value' => self::default_for( 'wm_bci_video_slider_intro' ),
				)
			),
			self::repeater_field(
				'field_wm_bci_video_slider_slides',
				__( 'Slides', 'community-resources-hub' ),
				'wm_bci_video_slider_slides',
				array(
					'button_label' => __( 'Add Spotlight Video', 'community-resources-hub' ),
					'layout'       => 'block',
					'sub_fields'   => array(
						self::text_field(
							'field_wm_bci_video_slider_slide_video_id',
							__( 'Video ID', 'community-resources-hub' ),
							'video_id',
							array(
								'wrapper' => array( 'width' => '33', 'class' => '', 'id' => '' ),
							)
						),
						self::url_field(
							'field_wm_bci_video_slider_slide_video_url',
							__( 'Video URL', 'community-resources-hub' ),
							'video_url',
							array(
								'wrapper' => array( 'width' => '67', 'class' => '', 'id' => '' ),
							)
						),
						self::image_field(
							'field_wm_bci_video_slider_slide_thumbnail_id',
							__( 'Thumbnail Image', 'community-resources-hub' ),
							'thumbnail_id',
							array(
								'wrapper' => array( 'width' => '50', 'class' => '', 'id' => '' ),
							)
						),
						self::image_field(
							'field_wm_bci_video_slider_slide_logo_id',
							__( 'Logo Image', 'community-resources-hub' ),
							'logo_id',
							array(
								'wrapper' => array( 'width' => '50', 'class' => '', 'id' => '' ),
							)
						),
						self::text_field(
							'field_wm_bci_video_slider_slide_logo_label',
							__( 'Logo Label', 'community-resources-hub' ),
							'logo_label',
							array(
								'wrapper' => array( 'width' => '50', 'class' => '', 'id' => '' ),
							)
						),
						self::text_field(
							'field_wm_bci_video_slider_slide_eyebrow',
							__( 'Slide Eyebrow', 'community-resources-hub' ),
							'slide_eyebrow',
							array(
								'wrapper'     => array( 'width' => '50', 'class' => '', 'id' => '' ),
								'placeholder' => 'The Rooted in Community series',
							)
						),
						self::text_field(
							'field_wm_bci_video_slider_slide_title',
							__( 'Slide Title', 'community-resources-hub' ),
							'slide_title',
							array(
								'wrapper' => array( 'width' => '100', 'class' => '', 'id' => '' ),
							)
						),
						self::textarea_field(
							'field_wm_bci_video_slider_slide_description',
							__( 'Slide Description', 'community-resources-hub' ),
							'slide_description',
							array(
								'rows'      => 3,
								'new_lines' => '',
							)
						),
					),
				)
			),
			self::tab( 'field_wm_bci_newsletter_archives_tab', __( 'Newsletter Archives', 'community-resources-hub' ) ),
			self::text_field(
				'field_wm_bci_newsletter_archives_eyebrow',
				__( 'Eyebrow', 'community-resources-hub' ),
				'wm_bci_newsletter_archives_eyebrow',
				array(
					'wrapper'       => array( 'width' => '50', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_newsletter_archives_eyebrow' ),
					'placeholder'   => 'Newsletter Archives',
				)
			),
			self::text_field(
				'field_wm_bci_newsletter_archives_title',
				__( 'Title', 'community-resources-hub' ),
				'wm_bci_newsletter_archives_title',
				array(
					'wrapper'       => array( 'width' => '50', 'class' => '', 'id' => '' ),
					'default_value' => self::default_for( 'wm_bci_newsletter_archives_title' ),
					'placeholder'   => 'Access past monthly newsletters through the cards below.',
				)
			),
			self::repeater_field(
				'field_wm_bci_newsletter_archive_cards',
				__( 'Archive Cards', 'community-resources-hub' ),
				'wm_bci_newsletter_archive_cards',
				array(
					'button_label' => __( 'Add Newsletter', 'community-resources-hub' ),
					'layout'       => 'block',
					'sub_fields'   => array(
						self::text_field(
							'field_wm_bci_newsletter_archive_card_issue_label',
							__( 'Issue Label', 'community-resources-hub' ),
							'issue_label',
							array(
								'wrapper'     => array( 'width' => '25', 'class' => '', 'id' => '' ),
								'placeholder' => 'May 2026',
							)
						),
						self::text_field(
							'field_wm_bci_newsletter_archive_card_title',
							__( 'Title', 'community-resources-hub' ),
							'title',
							array(
								'wrapper' => array( 'width' => '35', 'class' => '', 'id' => '' ),
							)
						),
						self::url_field(
							'field_wm_bci_newsletter_archive_card_url',
							__( 'Newsletter URL', 'community-resources-hub' ),
							'url',
							array(
								'wrapper' => array( 'width' => '40', 'class' => '', 'id' => '' ),
							)
						),
						self::select_field(
							'field_wm_bci_newsletter_archive_card_image_preset',
							__( 'Card Image', 'community-resources-hub' ),
							'image_preset',
							array(
								'instructions' => __( 'Choose one of the bundled Figma newsletter card images.', 'community-resources-hub' ),
								'wrapper'      => array( 'width' => '100', 'class' => '', 'id' => '' ),
								'choices'      => self::newsletter_archive_image_presets(),
								'allow_null'   => 1,
								'placeholder'  => __( 'Select an image', 'community-resources-hub' ),
							)
						),
					),
				)
			),
			self::tab( 'field_wm_bci_google_sync_tab', __( 'Google Sheets Sync', 'community-resources-hub' ) ),
			self::url_field(
				'field_wm_bci_google_sync_url',
				__( 'Sync Endpoint URL', 'community-resources-hub' ),
				'wm_bci_google_sync_url',
				array(
					'instructions' => __( 'Google Apps Script endpoint URL.', 'community-resources-hub' ),
					'wrapper'      => array( 'width' => '50', 'class' => '', 'id' => '' ),
				)
			),
			self::password_field(
				'field_wm_bci_google_sync_secret',
				__( 'Shared Secret', 'community-resources-hub' ),
				'wm_bci_google_sync_secret',
				array(
					'instructions' => __( 'Leave blank to keep the current saved secret.', 'community-resources-hub' ),
					'wrapper'      => array( 'width' => '50', 'class' => '', 'id' => '' ),
				)
			),
			self::tab( 'field_wm_bci_field_mapping_tab', __( 'Field Mapping', 'community-resources-hub' ) ),
		);

		$field_map_labels = array(
			'time_sensitive'          => __( 'Time-Sensitive Question Field ID', 'community-resources-hub' ),
			'opportunity_type'        => __( 'Opportunity Type Field ID', 'community-resources-hub' ),
			'non_date_sensitive_type' => __( 'Non-Date-Sensitive Type Field ID', 'community-resources-hub' ),
			'bci_update'              => __( 'BCI Update Question Field ID', 'community-resources-hub' ),
			'submitter_name'          => __( 'Submitter Name Field ID', 'community-resources-hub' ),
			'title'                   => __( 'Title Field ID', 'community-resources-hub' ),
			'organization'            => __( 'Organization Field ID', 'community-resources-hub' ),
			'start_date'              => __( 'Start Date Field ID', 'community-resources-hub' ),
			'grant_deadline'          => __( 'Grant Deadline Field ID', 'community-resources-hub' ),
			'end_date'                => __( 'End Date Field ID', 'community-resources-hub' ),
			'start_time'              => __( 'Start Time Field ID', 'community-resources-hub' ),
			'end_time'                => __( 'End Time Field ID', 'community-resources-hub' ),
			'cost'                    => __( 'Cost Field ID', 'community-resources-hub' ),
			'address'                 => __( 'Address Field ID', 'community-resources-hub' ),
			'location_mode'           => __( 'Location Mode Field ID', 'community-resources-hub' ),
			'description'             => __( 'Description Field ID', 'community-resources-hub' ),
			'info_url'                => __( 'Info URL Field ID', 'community-resources-hub' ),
			'file_upload'             => __( 'File Upload Field ID', 'community-resources-hub' ),
			'approval_status'         => __( 'Approval Status Field ID', 'community-resources-hub' ),
		);

		$field_map_keys = array(
			'time_sensitive'          => 'field_wm_bci_field_map_time_sensitive',
			'opportunity_type'        => 'field_wm_bci_field_map_opportunity_type',
			'non_date_sensitive_type' => 'field_wm_bci_field_map_non_date_sensitive_type',
			'bci_update'              => 'field_wm_bci_field_map_bci_update',
			'submitter_name'          => 'field_wm_bci_field_map_submitter_name',
			'title'                   => 'field_wm_bci_field_map_title',
			'organization'            => 'field_wm_bci_field_map_organization',
			'start_date'              => 'field_wm_bci_field_map_start_date',
			'grant_deadline'          => 'field_wm_bci_field_map_grant_deadline',
			'end_date'                => 'field_wm_bci_field_map_end_date',
			'start_time'              => 'field_wm_bci_field_map_start_time',
			'end_time'                => 'field_wm_bci_field_map_end_time',
			'cost'                    => 'field_wm_bci_field_map_cost',
			'address'                 => 'field_wm_bci_field_map_address',
			'location_mode'           => 'field_wm_bci_field_map_location_mode',
			'description'             => 'field_wm_bci_field_map_description',
			'info_url'                => 'field_wm_bci_field_map_info_url',
			'file_upload'             => 'field_wm_bci_field_map_file_upload',
			'approval_status'         => 'field_wm_bci_field_map_approval_status',
		);

		foreach ( self::field_map_keys() as $field_map_key ) {
			$fields[] = self::text_field(
				$field_map_keys[ $field_map_key ],
				$field_map_labels[ $field_map_key ],
				'wm_bci_field_map_' . $field_map_key,
				array(
					'wrapper'       => array( 'width' => '25', 'class' => '', 'id' => '' ),
					'default_value' => self::field_map_defaults()[ $field_map_key ] ?? '',
				)
			);
		}

		return array(
			'key'                   => self::OPTIONS_GROUP_KEY,
			'title'                 => __( 'Community Resources Hub Settings', 'community-resources-hub' ),
			'fields'                => $fields,
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => self::OPTIONS_PAGE_SLUG,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => '',
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 1,
			'display_title'         => '',
			'allow_ai_access'       => false,
			'ai_description'        => '',
		);
	}

	/**
	 * Base field args.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param string              $type  Field type.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function base_field( $key, $label, $name, $type, array $extra = array() ) {
		$field = array(
			'key'               => $key,
			'label'             => $label,
			'name'              => $name,
			'aria-label'        => '',
			'type'              => $type,
			'instructions'      => '',
			'required'          => 0,
			'conditional_logic' => 0,
			'wrapper'           => array(
				'width' => '',
				'class' => '',
				'id'    => '',
			),
		);

		return array_replace_recursive( $field, $extra );
	}

	/**
	 * Tab field.
	 *
	 * @param string $key   Tab key.
	 * @param string $label Tab label.
	 * @return array<string,mixed>
	 */
	private static function tab( $key, $label ) {
		return self::base_field(
			$key,
			$label,
			'',
			'tab',
			array(
				'placement' => 'top',
				'endpoint'  => 0,
				'selected'  => 0,
			)
		);
	}

	/**
	 * Text field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function text_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'text',
			array_replace_recursive(
				array(
					'default_value'     => '',
					'maxlength'         => '',
					'allow_in_bindings' => 0,
					'placeholder'       => '',
					'prepend'           => '',
					'append'            => '',
				),
				$extra
			)
		);
	}

	/**
	 * Number field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function number_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'number',
			array_replace_recursive(
				array(
					'default_value' => '',
					'min'           => '',
					'max'           => '',
					'placeholder'   => '',
					'step'          => '',
					'prepend'       => '',
					'append'        => '',
				),
				$extra
			)
		);
	}

	/**
	 * Textarea field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function textarea_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'textarea',
			array_replace_recursive(
				array(
					'default_value'     => '',
					'maxlength'         => '',
					'allow_in_bindings' => 0,
					'rows'              => 4,
					'placeholder'       => '',
					'new_lines'         => '',
				),
				$extra
			)
		);
	}

	/**
	 * User field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function user_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'user',
			array_replace_recursive(
				array(
					'role'                 => '',
					'return_format'        => 'id',
					'multiple'             => 0,
					'allow_null'           => 0,
					'allow_in_bindings'    => 0,
					'bidirectional'        => 0,
					'bidirectional_target' => array(),
				),
				$extra
			)
		);
	}

	/**
	 * URL field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function url_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'url',
			array_replace_recursive(
				array(
					'default_value'     => '',
					'allow_in_bindings' => 0,
					'placeholder'       => '',
				),
				$extra
			)
		);
	}

	/**
	 * Select field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function select_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'select',
			array_replace_recursive(
				array(
					'choices'       => array(),
					'default_value' => false,
					'allow_null'    => 0,
					'multiple'      => 0,
					'ui'            => 1,
					'ajax'          => 0,
					'return_format' => 'value',
					'placeholder'   => '',
				),
				$extra
			)
		);
	}

	/**
	 * Password field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function password_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'password',
			array_replace_recursive(
				array(
					'placeholder'       => '',
					'prepend'           => '',
					'append'            => '',
					'allow_in_bindings' => 0,
				),
				$extra
			)
		);
	}

	/**
	 * Image field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function image_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'image',
			array_replace_recursive(
				array(
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'min_width'     => '',
					'min_height'    => '',
					'min_size'      => '',
					'max_width'     => '',
					'max_height'    => '',
					'max_size'      => '',
					'mime_types'    => '',
				),
				$extra
			)
		);
	}

	/**
	 * Repeater field.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private static function repeater_field( $key, $label, $name, array $extra = array() ) {
		return self::base_field(
			$key,
			$label,
			$name,
			'repeater',
			array_replace_recursive(
				array(
					'collapsed'    => '',
					'min'          => 0,
					'max'          => 0,
					'layout'       => 'row',
					'button_label' => __( 'Add Row', 'community-resources-hub' ),
					'sub_fields'   => array(),
				),
				$extra
			)
		);
	}
}
