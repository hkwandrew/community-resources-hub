<?php
/**
 * Plugin-owned ACF post fields for BCI content types.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers local ACF field groups for BCI member and opportunity edit screens.
 */
final class AcfPostFields {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'acf/init', array( $this, 'register_field_groups' ) );
		add_filter( 'acf/pre_load_value', array( $this, 'normalize_member_acf_value' ), 10, 3 );
	}

	/**
	 * Register local field groups when ACF is available.
	 *
	 * @return void
	 */
	public function register_field_groups() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( $this->member_field_group() );
		acf_add_local_field_group( $this->opportunity_field_group() );
	}

	/**
	 * Normalize legacy member values before ACF field renderers see them.
	 *
	 * @param mixed               $value   Preloaded value.
	 * @param int|string          $post_id Post ID.
	 * @param array<string,mixed> $field   ACF field.
	 * @return mixed
	 */
	public function normalize_member_acf_value( $value, $post_id, array $field ) {
		if ( null !== $value ) {
			return $value;
		}

		$field_name = isset( $field['name'] ) ? (string) $field['name'] : '';

		if ( Schema::member_field_name( 'aliases' ) === $field_name ) {
			$raw_value = get_post_meta( $post_id, $field_name, true );

			return is_array( $raw_value ) ? $this->text_lines( $raw_value ) : $value;
		}

		if ( Schema::member_field_name( 'social_links' ) === $field_name ) {
			$raw_value = get_post_meta( $post_id, $field_name, true );

			return is_array( $raw_value ) ? $this->social_link_acf_rows( $raw_value, $field ) : $value;
		}

		return $value;
	}

	/**
	 * Textarea-safe lines from legacy array-shaped values.
	 *
	 * @param array<int|string,mixed> $values Raw values.
	 * @return string
	 */
	private function text_lines( array $values ) {
		$lines = array();

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				$nested = $this->text_lines( $value );

				foreach ( preg_split( '/\r\n|\r|\n/', $nested ) ?: array() as $line ) {
					$line = $this->sanitize_text( $line );

					if ( '' !== $line ) {
						$lines[] = $line;
					}
				}

				continue;
			}

			$line = $this->sanitize_text( $value );

			if ( '' !== $line ) {
				$lines[] = $line;
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * ACF repeater rows from legacy array-shaped social links.
	 *
	 * @param array<int,array<string,mixed>> $rows  Legacy rows.
	 * @param array<string,mixed>            $field ACF field.
	 * @return array<int,array<string,string>>
	 */
	private function social_link_acf_rows( array $rows, array $field ) {
		$subfield_keys = $this->social_link_subfield_keys( $field );
		$acf_rows      = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$raw_url  = $row['url'] ?? $row['href'] ?? '';
			$platform = $this->normalize_social_platform( $row['social_platform'] ?? $row['platform'] ?? '' );
			$url      = $this->normalize_social_url( $raw_url, $platform );
			$label    = $this->sanitize_text( $row['label'] ?? $row['title'] ?? '' );

			if ( '' === $platform ) {
				$platform = $this->social_platform_from_url( $url );
			}

			if ( '' === $label && $this->looks_like_social_handle( $raw_url ) ) {
				$label = $this->social_handle_label( $raw_url );
			}

			if ( '' === $platform || '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
				continue;
			}

			$acf_rows[] = array(
				$subfield_keys['social_platform'] => $platform,
				$subfield_keys['url']             => $url,
				$subfield_keys['label']           => $label,
			);
		}

		return $acf_rows;
	}

	/**
	 * Current ACF subfield keys by semantic row key.
	 *
	 * @param array<string,mixed> $field ACF field.
	 * @return array{social_platform:string,url:string,label:string}
	 */
	private function social_link_subfield_keys( array $field ) {
		$keys = array(
			'social_platform' => 'social_platform',
			'url'             => 'url',
			'label'           => 'label',
		);

		foreach ( $field['sub_fields'] ?? array() as $sub_field ) {
			if ( ! is_array( $sub_field ) ) {
				continue;
			}

			$name = isset( $sub_field['name'] ) ? (string) $sub_field['name'] : '';
			$key  = isset( $sub_field['key'] ) ? (string) $sub_field['key'] : '';

			if ( '' !== $name && '' !== $key && array_key_exists( $name, $keys ) ) {
				$keys[ $name ] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Current social platform select value from older display labels.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function normalize_social_platform( $value ) {
		$value = $this->sanitize_text( $value );

		if ( '' === $value ) {
			return '';
		}

		$parts = array_map( 'trim', explode( '|', $value, 2 ) );
		$slug  = $this->slug( $parts[0] ?? $value );

		$choices = array(
			'facebook'  => 'facebook|Facebook',
			'instagram' => 'instagram|Instagram',
			'linkedin'  => 'linkedin|LinkedIn',
			'linked-in' => 'linkedin|LinkedIn',
			'tiktok'    => 'tiktok|TikTok',
			'tik-tok'   => 'tiktok|TikTok',
			'twitter'   => 'twitter|X / Twitter',
			'x'         => 'twitter|X / Twitter',
			'x-twitter' => 'twitter|X / Twitter',
			'youtube'   => 'youtube|YouTube',
			'you-tube'  => 'youtube|YouTube',
			'website'   => 'website|Website',
		);

		if ( isset( $choices[ $slug ] ) ) {
			return $choices[ $slug ];
		}

		if ( isset( $parts[1] ) && '' !== $parts[1] ) {
			return $this->sanitize_text( $slug . '|' . $parts[1] );
		}

		return $value;
	}

	/**
	 * Current social URL from full URLs or legacy handle-only values.
	 *
	 * @param mixed  $value    Raw URL or handle.
	 * @param string $platform Current social platform select value.
	 * @return string
	 */
	private function normalize_social_url( $value, $platform ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '#^https?://@(.+)$#i', $value, $matches ) ) {
			$value = '@' . ltrim( $matches[1], '@' );
		}

		if ( preg_match( '#^https?://#i', $value ) ) {
			return $this->sanitize_url( $value );
		}

		if ( preg_match( '#^www\.#i', $value ) ) {
			return $this->sanitize_url( 'https://' . $value );
		}

		$platform_slug = $this->social_platform_slug( $platform );
		$handle        = trim( $value );

		if ( '@' === substr( $handle, 0, 1 ) ) {
			$handle = substr( $handle, 1 );
		}

		$handle = trim( $handle, "/ \t\n\r\0\x0B" );

		if ( '' === $handle || '' === $platform_slug ) {
			return $this->sanitize_url( $value );
		}

		switch ( $platform_slug ) {
			case 'facebook':
				return $this->sanitize_url( 'https://www.facebook.com/' . $handle . '/' );
			case 'instagram':
				return $this->sanitize_url( 'https://www.instagram.com/' . $handle . '/' );
			case 'linkedin':
				if ( 0 === strpos( $handle, 'company/' ) || 0 === strpos( $handle, 'in/' ) ) {
					return $this->sanitize_url( 'https://www.linkedin.com/' . $handle . '/' );
				}

				return $this->sanitize_url( 'https://www.linkedin.com/company/' . $handle . '/' );
			case 'tiktok':
				return $this->sanitize_url( 'https://www.tiktok.com/@' . $handle );
			case 'twitter':
				return $this->sanitize_url( 'https://x.com/' . $handle );
			case 'youtube':
				return $this->sanitize_url( 'https://www.youtube.com/@' . $handle );
		}

		return $this->sanitize_url( $value );
	}

	/**
	 * Current social platform select value inferred from a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function social_platform_from_url( $url ) {
		$host = (string) parse_url( $url, PHP_URL_HOST );
		$host = strtolower( preg_replace( '/^www\./', '', $host ) );

		if ( '' === $host ) {
			return '';
		}

		if ( false !== strpos( $host, 'facebook.com' ) ) {
			return 'facebook|Facebook';
		}

		if ( false !== strpos( $host, 'instagram.com' ) ) {
			return 'instagram|Instagram';
		}

		if ( false !== strpos( $host, 'linkedin.com' ) ) {
			return 'linkedin|LinkedIn';
		}

		if ( false !== strpos( $host, 'tiktok.com' ) ) {
			return 'tiktok|TikTok';
		}

		if ( false !== strpos( $host, 'twitter.com' ) || false !== strpos( $host, 'x.com' ) ) {
			return 'twitter|X / Twitter';
		}

		if ( false !== strpos( $host, 'youtube.com' ) || false !== strpos( $host, 'youtu.be' ) ) {
			return 'youtube|YouTube';
		}

		return '';
	}

	/**
	 * Platform slug from a current social platform select value.
	 *
	 * @param string $platform Current social platform select value.
	 * @return string
	 */
	private function social_platform_slug( $platform ) {
		$parts = array_map( 'trim', explode( '|', $platform, 2 ) );

		return $this->slug( $parts[0] ?? '' );
	}

	/**
	 * Whether a raw value looks like an old social handle.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function looks_like_social_handle( $value ) {
		$value = trim( (string) $value );

		return preg_match( '#^(?:https?://)?@[^/\s]+$#i', $value ) === 1;
	}

	/**
	 * Display label from an old handle-only social value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function social_handle_label( $value ) {
		$value = trim( (string) $value );

		if ( preg_match( '#^https?://@(.+)$#i', $value, $matches ) ) {
			$value = '@' . trim( $matches[1], "/ \t\n\r\0\x0B" );
		}

		return $this->sanitize_text( $value );
	}

	/**
	 * Sanitized plain text.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_text( $value ) {
		if ( function_exists( 'sanitize_text_field' ) ) {
			return sanitize_text_field( (string) $value );
		}

		return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
	}

	/**
	 * Sanitized URL.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_url( $value ) {
		if ( function_exists( 'esc_url_raw' ) ) {
			return esc_url_raw( (string) $value );
		}

		return trim( (string) $value );
	}

	/**
	 * Small local slug helper for social platform labels.
	 *
	 * @param string $value Raw label.
	 * @return string
	 */
	private function slug( $value ) {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );

		return trim( (string) $value, '-' );
	}

	/**
	 * BCI member fields.
	 *
	 * @return array<string,mixed>
	 */
	private function member_field_group() {
		return $this->field_group(
			'group_crh_bci_member_fields',
			__( 'BCI Member Fields', 'community-resources-hub' ),
			Schema::MEMBER_POST_TYPE,
			array(
				$this->field( 'field_crh_bci_member_aliases', __( 'Aliases', 'community-resources-hub' ), Schema::member_field_name( 'aliases' ), 'textarea', array(
					'instructions' => __( 'Optional alternate organization names, one per line.', 'community-resources-hub' ),
					'rows'         => 3,
				) ),
				$this->field( 'field_crh_bci_member_community_served', __( 'Community Served', 'community-resources-hub' ), Schema::member_field_name( 'community_served' ), 'textarea', array( 'rows' => 3 ) ),
				$this->field( 'field_crh_bci_member_founded_year', __( 'Founded Year', 'community-resources-hub' ), Schema::member_field_name( 'founded_year' ) ),
				$this->field( 'field_crh_bci_member_contact_email', __( 'Contact Email', 'community-resources-hub' ), Schema::member_field_name( 'contact_email' ), 'email' ),
				$this->field( 'field_crh_bci_member_website_url', __( 'Website URL', 'community-resources-hub' ), Schema::member_field_name( 'website_url' ), 'url' ),
				$this->field( 'field_crh_bci_member_phone', __( 'Phone', 'community-resources-hub' ), Schema::member_field_name( 'phone' ) ),
				$this->field( 'field_crh_bci_member_main_office', __( 'Main Office', 'community-resources-hub' ), Schema::member_field_name( 'main_office' ), 'textarea', array( 'rows' => 3 ) ),
				$this->field( 'field_crh_bci_member_social_links', __( 'Social Links', 'community-resources-hub' ), Schema::member_field_name( 'social_links' ), 'repeater', array(
					'layout'       => 'row',
					'button_label' => __( 'Add Social Link', 'community-resources-hub' ),
					'sub_fields'   => array(
						$this->field( 'field_crh_bci_member_social_platform', __( 'Platform', 'community-resources-hub' ), 'social_platform', 'select', array(
							'choices'       => array(
								'facebook|Facebook'   => __( 'Facebook', 'community-resources-hub' ),
								'instagram|Instagram' => __( 'Instagram', 'community-resources-hub' ),
								'linkedin|LinkedIn'   => __( 'LinkedIn', 'community-resources-hub' ),
								'tiktok|TikTok'       => __( 'TikTok', 'community-resources-hub' ),
								'twitter|X / Twitter' => __( 'X / Twitter', 'community-resources-hub' ),
								'youtube|YouTube'     => __( 'YouTube', 'community-resources-hub' ),
								'website|Website'     => __( 'Website', 'community-resources-hub' ),
							),
							'default_value' => false,
							'allow_null'    => 1,
						) ),
						$this->field( 'field_crh_bci_member_social_url', __( 'URL', 'community-resources-hub' ), 'url', 'url' ),
						$this->field( 'field_crh_bci_member_social_label', __( 'Label', 'community-resources-hub' ), 'label' ),
					),
				) ),
				$this->field( 'field_crh_bci_member_programs', __( 'Programs', 'community-resources-hub' ), Schema::member_field_name( 'programs' ), 'wysiwyg', array(
					'media_upload' => 0,
					'tabs'         => 'all',
					'toolbar'      => 'full',
				) ),
				$this->field( 'field_crh_bci_member_attachments', __( 'Attachment URLs', 'community-resources-hub' ), Schema::member_field_name( 'attachments' ), 'textarea', array(
					'instructions' => __( 'One attachment URL per line.', 'community-resources-hub' ),
					'rows'         => 3,
				) ),
				$this->field( 'field_crh_bci_member_video_url', __( 'Video URL', 'community-resources-hub' ), Schema::member_field_name( 'video_url' ), 'url' ),
				$this->field( 'field_crh_bci_member_video_label', __( 'Video Label', 'community-resources-hub' ), Schema::member_field_name( 'video_label' ) ),
				$this->image_field( 'field_crh_bci_member_logo_url', __( 'Logo', 'community-resources-hub' ), Schema::member_field_name( 'logo_url' ) ),
				$this->image_field( 'field_crh_bci_member_hero_image_url', __( 'Hero Image', 'community-resources-hub' ), Schema::member_field_name( 'hero_image_url' ) ),
				$this->field( 'field_crh_bci_member_hero_background_color', __( 'Hero Background Color', 'community-resources-hub' ), Schema::member_field_name( 'hero_background_color' ), 'color_picker', array(
					'enable_opacity'        => 0,
					'return_format'         => 'string',
					'show_custom_palette'   => 1,
					'palette_colors'        => '#133358, #0F332F, #F6F6F6, #013B8E, #F57B22, #E6F8FF, #EF5052, #005B8A, #5F2466, #011442, #F8D20E, #B1C6C4, #000000, #F0F1F9, #EA2C28, #B20B0D, #29242D, #FFFFD5',
					'show_color_wheel'      => 0,
					'custom_palette_source' => '',
				) ),
			)
		);
	}

	/**
	 * BCI opportunity fields.
	 *
	 * @return array<string,mixed>
	 */
	private function opportunity_field_group() {
		return $this->field_group(
			'group_crh_bci_opportunity_fields',
			__( 'BCI Opportunity Fields', 'community-resources-hub' ),
			Schema::OPPORTUNITY_POST_TYPE,
			array(
				$this->field( 'field_crh_bci_opportunity_source_entry_id', __( 'Source Entry ID', 'community-resources-hub' ), Schema::opportunity_field_name( 'source_entry_id' ), 'number' ),
				$this->field( 'field_crh_bci_opportunity_approval_status', __( 'Approval Status', 'community-resources-hub' ), Schema::opportunity_field_name( 'approval_status' ), 'select', array(
					'choices'       => array(
						'Pending'  => __( 'Pending', 'community-resources-hub' ),
						'Approved' => __( 'Approved', 'community-resources-hub' ),
						'Rejected' => __( 'Rejected', 'community-resources-hub' ),
					),
					'default_value' => 'Pending',
					'allow_null'    => 0,
				) ),
				$this->field( 'field_crh_bci_opportunity_approved_at', __( 'Approved At', 'community-resources-hub' ), Schema::opportunity_field_name( 'approved_at' ), 'text', array(
					'instructions' => __( 'Set automatically when an opportunity is approved.', 'community-resources-hub' ),
				) ),
				$this->field( 'field_crh_bci_opportunity_submitter_name', __( 'Submitter Name', 'community-resources-hub' ), Schema::opportunity_field_name( 'submitter_name' ) ),
				$this->field( 'field_crh_bci_opportunity_organization', __( 'Organization', 'community-resources-hub' ), Schema::opportunity_field_name( 'organization' ) ),
				$this->date_field( 'field_crh_bci_opportunity_start_date', __( 'Start Date', 'community-resources-hub' ), Schema::opportunity_field_name( 'start_date' ) ),
				$this->date_field( 'field_crh_bci_opportunity_grant_deadline', __( 'Grant Deadline', 'community-resources-hub' ), Schema::opportunity_field_name( 'grant_deadline' ) ),
				$this->date_field( 'field_crh_bci_opportunity_end_date', __( 'End Date', 'community-resources-hub' ), Schema::opportunity_field_name( 'end_date' ) ),
				$this->time_field( 'field_crh_bci_opportunity_start_time', __( 'Start Time', 'community-resources-hub' ), Schema::opportunity_field_name( 'start_time' ) ),
				$this->time_field( 'field_crh_bci_opportunity_end_time', __( 'End Time', 'community-resources-hub' ), Schema::opportunity_field_name( 'end_time' ) ),
				$this->field( 'field_crh_bci_opportunity_location_mode', __( 'Location Mode', 'community-resources-hub' ), Schema::opportunity_field_name( 'location_mode' ) ),
				$this->field( 'field_crh_bci_opportunity_address', __( 'Address', 'community-resources-hub' ), Schema::opportunity_field_name( 'address' ), 'textarea', array( 'rows' => 3 ) ),
				$this->field( 'field_crh_bci_opportunity_cost', __( 'Cost', 'community-resources-hub' ), Schema::opportunity_field_name( 'cost' ) ),
				$this->field( 'field_crh_bci_opportunity_info_url', __( 'Info URL', 'community-resources-hub' ), Schema::opportunity_field_name( 'info_url' ), 'url' ),
				$this->field( 'field_crh_bci_opportunity_file_upload', __( 'Attachment URLs', 'community-resources-hub' ), Schema::opportunity_field_name( 'file_upload' ), 'textarea', array(
					'instructions' => __( 'One attachment URL per line.', 'community-resources-hub' ),
					'rows'         => 3,
				) ),
				$this->field( 'field_crh_bci_opportunity_google_sync_status', __( 'Google Sync Status', 'community-resources-hub' ), Schema::opportunity_field_name( 'google_sync_status' ) ),
				$this->field( 'field_crh_bci_opportunity_google_sync_attempted_at', __( 'Google Sync Attempted At', 'community-resources-hub' ), Schema::opportunity_field_name( 'google_sync_attempted_at' ) ),
				$this->field( 'field_crh_bci_opportunity_google_sync_synced_at', __( 'Google Sync Synced At', 'community-resources-hub' ), Schema::opportunity_field_name( 'google_sync_synced_at' ) ),
				$this->field( 'field_crh_bci_opportunity_google_sync_error', __( 'Google Sync Error', 'community-resources-hub' ), Schema::opportunity_field_name( 'google_sync_error' ), 'textarea', array( 'rows' => 2 ) ),
			)
		);
	}

	/**
	 * Shared local field group shape.
	 *
	 * @param string             $key       Field group key.
	 * @param string             $title     Field group title.
	 * @param string             $post_type Post type slug.
	 * @param array<int,array<string,mixed>> $fields Field definitions.
	 * @return array<string,mixed>
	 */
	private function field_group( $key, $title, $post_type, array $fields ) {
		return array(
			'key'                   => $key,
			'title'                 => $title,
			'fields'                => $fields,
			'location'              => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => $post_type,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 1,
		);
	}

	/**
	 * Shared ACF field shape.
	 *
	 * @param string              $key   Field key.
	 * @param string              $label Field label.
	 * @param string              $name  Field name.
	 * @param string              $type  Field type.
	 * @param array<string,mixed> $args  Field overrides.
	 * @return array<string,mixed>
	 */
	private function field( $key, $label, $name, $type = 'text', array $args = array() ) {
		return array_merge(
			array(
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
				'default_value'     => '',
				'placeholder'       => '',
			),
			$args
		);
	}

	/**
	 * Shared date field.
	 *
	 * @param string $key Field key.
	 * @param string $label Field label.
	 * @param string $name Field name.
	 * @return array<string,mixed>
	 */
	private function date_field( $key, $label, $name ) {
		return $this->field(
			$key,
			$label,
			$name,
			'date_picker',
			array(
				'display_format' => 'F j, Y',
				'return_format'  => 'Y-m-d',
				'first_day'      => 0,
			)
		);
	}

	/**
	 * Shared time field.
	 *
	 * @param string $key Field key.
	 * @param string $label Field label.
	 * @param string $name Field name.
	 * @return array<string,mixed>
	 */
	private function time_field( $key, $label, $name ) {
		return $this->field(
			$key,
			$label,
			$name,
			'time_picker',
			array(
				'display_format' => 'g:i a',
				'return_format'  => 'g:i a',
			)
		);
	}

	/**
	 * Shared image field.
	 *
	 * @param string $key Field key.
	 * @param string $label Field label.
	 * @param string $name Field name.
	 * @return array<string,mixed>
	 */
	private function image_field( $key, $label, $name ) {
		return $this->field(
			$key,
			$label,
			$name,
			'image',
			array(
				'return_format' => 'id',
				'preview_size'  => 'medium',
				'library'       => 'all',
			)
		);
	}
}
