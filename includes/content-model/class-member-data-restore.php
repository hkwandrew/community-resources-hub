<?php
/**
 * Admin-only BCI member data restore tools.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

use WatersMeet\CommunityResourcesHub\Config\SettingsSchema;
use WatersMeet\CommunityResourcesHub\FrontEnd\ApprovedOpportunityService;
use WatersMeet\CommunityResourcesHub\FrontEnd\MemberDirectoryService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restores known BCI member fields that were lost during the member CPT migration.
 */
final class MemberDataRestore {

	const ACTION           = 'wm_bci_restore_member_data';
	const NONCE_ACTION     = 'wm_bci_restore_member_data';
	const NONCE_NAME       = 'wm_bci_restore_member_data_nonce';
	const RESULT_TRANSIENT = 'community_resources_hub_member_data_restore_notice';
	const SUMMARY_OPTION   = 'community_resources_hub_member_data_restore_summary';

	/**
	 * Register admin-only restore hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'restrict_manage_posts', array( $this, 'render_restore_action' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_manual_restore' ) );
	}

	/**
	 * Render the manual restore action on the BCI member list table.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $which     Table-nav location.
	 * @return void
	 */
	public function render_restore_action( $post_type = '', $which = '' ) {
		if ( Schema::MEMBER_POST_TYPE !== (string) $post_type || ( '' !== $which && 'top' !== (string) $which ) ) {
			return;
		}

		if ( function_exists( 'current_user_can' ) && ! current_user_can( SettingsSchema::CAPABILITY ) ) {
			return;
		}

		$url = admin_url( 'admin-post.php?action=' . self::ACTION );
		?>
		<div class="alignleft actions">
			<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME, false ); ?>
			<button
				type="submit"
				class="button"
				name="action"
				value="<?php echo esc_attr( self::ACTION ); ?>"
				formaction="<?php echo esc_url( $url ); ?>"
				formmethod="post"
				style="margin-left:8px;"
			>
				<?php echo esc_html( __( 'Restore BCI Member Data', 'community-resources-hub' ) ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Handle a manual admin-triggered restore run.
	 *
	 * @return void
	 */
	public function handle_manual_restore() {
		if ( ! current_user_can( SettingsSchema::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to restore BCI member data.', 'community-resources-hub' ) );
		}

		$request_method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) );

		if ( 'POST' !== $request_method ) {
			wp_die( esc_html( __( 'Use the BCI member list-table restore button to run this action.', 'community-resources-hub' ) ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME );

		$summary = $this->restore();

		if ( function_exists( 'set_transient' ) ) {
			set_transient(
				self::RESULT_TRANSIENT,
				array(
					'type'    => empty( $summary['missing_titles'] ) ? 'success' : 'warning',
					'message' => $this->result_message( $summary ),
				),
				MINUTE_IN_SECONDS
			);
		}

		$redirect = wp_get_referer();

		if ( ! is_string( $redirect ) || '' === $redirect ) {
			$redirect = admin_url( 'edit.php?post_type=' . Schema::MEMBER_POST_TYPE );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Restore cataloged member data by matching published member titles.
	 *
	 * @return array<string,mixed>
	 */
	public function restore() {
		$summary = array(
			'catalog_members'        => 0,
			'matched_members'        => 0,
			'missing_members'        => 0,
			'missing_titles'         => array(),
			'social_members'         => 0,
			'social_rows'            => 0,
			'content_fields_updated' => 0,
			'content_fields_skipped' => 0,
			'image_fields_updated'   => 0,
			'image_fields_skipped'   => 0,
			'completed_at'           => gmdate( 'c' ),
		);

		$posts = $this->member_posts_by_key();

		foreach ( $this->restore_catalog() as $title => $data ) {
			$summary['catalog_members']++;

			$key = $this->match_key( $title );

			if ( empty( $posts[ $key ] ) ) {
				$summary['missing_members']++;
				$summary['missing_titles'][] = $title;
				continue;
			}

			$post_id = absint( $posts[ $key ] );

			if ( ! $post_id ) {
				continue;
			}

			$summary['matched_members']++;

			$content = $this->sanitize_post_content( $data['content'] ?? '' );

			if ( '' !== $content ) {
				if ( $this->restore_post_content( $post_id, $content ) ) {
					$summary['content_fields_updated']++;
				} else {
					$summary['content_fields_skipped']++;
				}
			}

			$social_rows = $this->normalize_social_rows( $data['social'] ?? array() );

			if ( ! empty( $social_rows ) ) {
				$this->update_social_rows( $post_id, $social_rows );
				$summary['social_members']++;
				$summary['social_rows'] += count( $social_rows );
			}

			foreach ( array( 'logo_url' => 'logo_id', 'hero_image_url' => 'hero_id' ) as $semantic_key => $catalog_key ) {
				$attachment_id = absint( $data[ $catalog_key ] ?? 0 );

				if ( ! $attachment_id ) {
					continue;
				}

				if ( ! $this->attachment_is_usable_image( $attachment_id ) ) {
					$summary['image_fields_skipped']++;
					continue;
				}

				$field_name    = Schema::member_field_name( $semantic_key );
				$current_value = absint( get_post_meta( $post_id, $field_name, true ) );

				if ( $current_value === $attachment_id ) {
					continue;
				}

				update_post_meta( $post_id, $field_name, $attachment_id );
				update_post_meta( $post_id, '_' . $field_name, 'field_crh_bci_member_' . $semantic_key );
				$summary['image_fields_updated']++;
			}
		}

		update_option( self::SUMMARY_OPTION, $summary, false );
		$this->flush_runtime_caches();

		return $summary;
	}

	/**
	 * Current BCI member posts keyed by normalized title.
	 *
	 * @return array<string,int>
	 */
	private function member_posts_by_key() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => Schema::MEMBER_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$by_key = array();

		foreach ( is_array( $posts ) ? $posts : array() as $post ) {
			$post_id = absint( $post->ID ?? 0 );

			if ( ! $post_id ) {
				continue;
			}

			$title = isset( $post->post_title ) ? (string) $post->post_title : (string) get_post_field( 'post_title', $post_id, 'raw' );
			$key   = $this->match_key( $title );

			if ( '' !== $key ) {
				$by_key[ $key ] = $post_id;
			}
		}

		return $by_key;
	}

	/**
	 * Persist social rows in ACF repeater meta format.
	 *
	 * @param int                                  $post_id Post ID.
	 * @param array<int,array<string,string>> $rows    Social rows.
	 * @return void
	 */
	private function update_social_rows( $post_id, array $rows ) {
		$field_name = Schema::member_field_name( 'social_links' );
		$old_count  = absint( get_post_meta( $post_id, $field_name, true ) );

		update_post_meta( $post_id, $field_name, count( $rows ) );
		update_post_meta( $post_id, '_' . $field_name, 'field_crh_bci_member_social_links' );

		foreach ( $rows as $index => $row ) {
			$prefix = $field_name . '_' . absint( $index );

			$this->update_social_subfield( $post_id, $prefix, 'social_platform', $row['social_platform'] ?? '' );
			$this->update_social_subfield( $post_id, $prefix, 'url', $row['url'] ?? '' );
			$this->update_social_subfield( $post_id, $prefix, 'label', $row['label'] ?? '' );
		}

		for ( $index = count( $rows ); $index < $old_count; $index++ ) {
			$prefix = $field_name . '_' . absint( $index );

			$this->delete_social_subfield( $post_id, $prefix, 'social_platform' );
			$this->delete_social_subfield( $post_id, $prefix, 'url' );
			$this->delete_social_subfield( $post_id, $prefix, 'label' );
		}
	}

	/**
	 * Persist one social repeater subfield and its ACF field reference.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $prefix  Row meta prefix.
	 * @param string $name    Subfield name.
	 * @param string $value   Subfield value.
	 * @return void
	 */
	private function update_social_subfield( $post_id, $prefix, $name, $value ) {
		$key = $prefix . '_' . $name;
		$field_keys = array(
			'social_platform' => 'field_crh_bci_member_social_platform',
			'url'             => 'field_crh_bci_member_social_url',
			'label'           => 'field_crh_bci_member_social_label',
		);

		update_post_meta( $post_id, $key, $value );
		update_post_meta( $post_id, '_' . $key, $field_keys[ $name ] ?? '' );
	}

	/**
	 * Delete one stale social repeater subfield and its ACF field reference.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $prefix  Row meta prefix.
	 * @param string $name    Subfield name.
	 * @return void
	 */
	private function delete_social_subfield( $post_id, $prefix, $name ) {
		$key = $prefix . '_' . $name;

		delete_post_meta( $post_id, $key );
		delete_post_meta( $post_id, '_' . $key );
	}

	/**
	 * Restore classic editor body content when the destination is still blank.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content Catalog body content.
	 * @return bool
	 */
	private function restore_post_content( $post_id, $content ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || '' === $content || ! function_exists( 'wp_update_post' ) ) {
			return false;
		}

		$current_content = function_exists( 'get_post_field' ) ? (string) get_post_field( 'post_content', $post_id, 'raw' ) : '';

		if ( ! $this->post_content_is_empty( $current_content ) ) {
			return false;
		}

		$result = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			),
			true
		);

		if ( function_exists( 'is_wp_error' ) && is_wp_error( $result ) ) {
			return false;
		}

		return $post_id === absint( $result );
	}

	/**
	 * Whether existing classic editor content is effectively empty.
	 *
	 * @param string $content Current post content.
	 * @return bool
	 */
	private function post_content_is_empty( $content ) {
		$content = trim( (string) $content );

		if ( '' === $content ) {
			return true;
		}

		$plain_text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $content, true ) : strip_tags( $content );
		$plain_text = str_replace( array( '&nbsp;', '&#160;' ), '', $plain_text );

		return '' === trim( $plain_text );
	}

	/**
	 * Normalize catalog social rows to current ACF values.
	 *
	 * @param array<int,array<string,string>> $rows Catalog rows.
	 * @return array<int,array{social_platform:string,url:string,label:string}>
	 */
	private function normalize_social_rows( array $rows ) {
		$normalized = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$url      = $this->sanitize_url( $row['url'] ?? '' );
			$platform = $this->normalize_social_platform( $row['social_platform'] ?? $row['platform'] ?? '' );
			$label    = $this->sanitize_text( $row['label'] ?? '' );

			if ( '' === $platform ) {
				$platform = $this->social_platform_from_url( $url );
			}

			if ( '' === $platform || '' === $url ) {
				continue;
			}

			$normalized[] = array(
				'social_platform' => $platform,
				'url'             => $url,
				'label'           => $label,
			);
		}

		return $normalized;
	}

	/**
	 * Whether an attachment ID can safely be used for an ACF image field.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function attachment_is_usable_image( $attachment_id ) {
		$attachment = function_exists( 'get_post' ) ? get_post( $attachment_id ) : null;

		if ( ! is_object( $attachment ) || 'attachment' !== (string) ( $attachment->post_type ?? '' ) ) {
			return false;
		}

		if ( function_exists( 'wp_attachment_is_image' ) ) {
			return (bool) wp_attachment_is_image( $attachment_id );
		}

		return true;
	}

	/**
	 * Current social platform select value from display labels.
	 *
	 * @param string $value Raw value.
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

		return $choices[ $slug ] ?? '';
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
	 * Result notice message for manual restore runs.
	 *
	 * @param array<string,mixed> $summary Restore summary.
	 * @return string
	 */
	private function result_message( array $summary ) {
		return sprintf(
			/* translators: 1: matched member count, 2: restored editor body count, 3: social row count, 4: updated image count, 5: skipped image count. */
			__( 'BCI member data restore complete. %1$d members matched, %2$d editor body fields restored, %3$d social rows restored, %4$d image fields restored, %5$d image fields skipped because the attachment was missing.', 'community-resources-hub' ),
			absint( $summary['matched_members'] ?? 0 ),
			absint( $summary['content_fields_updated'] ?? 0 ),
			absint( $summary['social_rows'] ?? 0 ),
			absint( $summary['image_fields_updated'] ?? 0 ),
			absint( $summary['image_fields_skipped'] ?? 0 )
		);
	}

	/**
	 * Flush frontend payload caches after restore runs.
	 *
	 * @return void
	 */
	private function flush_runtime_caches() {
		if ( class_exists( MemberDirectoryService::class ) ) {
			MemberDirectoryService::flush_cache();
		}

		if ( class_exists( ApprovedOpportunityService::class ) ) {
			ApprovedOpportunityService::flush_cache();
		}
	}

	/**
	 * Normalized match key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function match_key( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = str_replace( '&', ' and ', $value );
		$value = preg_replace( '/[^a-z0-9]+/', ' ', $value );

		return trim( (string) $value );
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
	 * Sanitized editor body content.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_post_content( $value ) {
		$content = trim( (string) $value );

		if ( '' === $content ) {
			return '';
		}

		if ( function_exists( 'wp_kses_post' ) ) {
			return trim( (string) wp_kses_post( $content ) );
		}

		return $content;
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
	 * Verified BCI member restore catalog from the local canonical member data.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	private function restore_catalog() {
		return array(
			"Yoyot Sp'q'n'i'" => array(
				'content' => 'We want to support and teach advocacy by uplifting empowering our people to thrive.',
				'logo_id' => 1283,
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/mmipspokane', 'label' => '@mmipspokane' ),
					array( 'url' => 'https://www.facebook.com/MMIPSpokane/', 'label' => '@MMIPSpokane' ),
				),
			),
			'Way to Justice' => array(
				'content' => 'The Way to Justice is a community non-profit legal aid organization led and created by women of color. Through direct representation, impact litigation, policy reform, and advocacy work, we address the barriers facing individuals who have been negatively impacted by our justice system.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/thewaytojustice/', 'label' => '@thewaytojustice' ),
					array( 'url' => 'https://www.linkedin.com/company/the-way-to-justice/', 'label' => 'The Way to Justice' ),
					array( 'url' => 'https://www.facebook.com/thewaytojustice/', 'label' => '@thewaytojustice' ),
				),
			),
			'Unidos Nueva Alianza Foundation' => array(
				'content' => 'Unidos Nueva Alianza Foundation (UNA), formerly known as Latinos Unidos Grant County (LUGC), is a local grassroots non-profit, community-based organization. UNA serves 8 counties that represent the Hispanic/Latinx, immigrants, refugees, agriculture/farm workers, LGBTQ+, low-income, disabled, and systemically marginalized and underserved communities. The counties served are Grant, Adams, Franklin, Benton, Yakima, Chelan, Douglas, and Okanogan. UNA builds and provides services and resources for immigrant, Latinx, and under-represented communities.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/unidosnuevaalianza/', 'label' => '@unidosnuevaalianza' ),
					array( 'url' => 'https://www.linkedin.com/in/unidos-nueva-alianza-58324a28b/', 'label' => '@unidos-nueva-alianza-58324a28b' ),
					array( 'url' => 'https://www.facebook.com/unidosnuevaalianza/', 'label' => '@unidosnuevaalianza' ),
				),
			),
			'Spectrum' => array(
				'content' => 'A drive for a safe, intersectional, intergenerational 2SLGBTQIA+ community in Eastern Washington and North Idaho is at the core of everything we do. We strive to create virtual and in-person community spaces, build a culture of care, commit to advocacy and allyship, provide education and facilitation services to organizations/individuals, and fill gaps that arise through community-centered events.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/spectrumcenterspokane/', 'label' => '@spectrumcenterspokane' ),
					array( 'url' => 'https://www.facebook.com/spectrumcenterspokane/', 'label' => '@spectrumcenterspokane' ),
				),
			),
			'Mujeres in Action' => array(
				'content' => 'MiA - Mujeres in Action is an organization that supports survivors of domestic violence and sexual assault in a culturally responsive way. We provide a variety of services to support survivors, including mental health counseling and advocacy, crisis intervention, case management, systems navigation, housing support, transitional housing, among others.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/miaspokane/', 'label' => '@miaspokane' ),
					array( 'url' => 'https://www.facebook.com/miaspokane/', 'label' => '@miaspokane' ),
					array( 'url' => 'https://www.youtube.com/@m.i.a.mujeresinaction9394', 'label' => '@m.i.a.mujeresinaction9394' ),
				),
			),
			'Manzanita House' => array(
				'content' => 'Manzanita House was birthed out of a need to fill the gaps in services for immigrants and refugees in Eastern Washington. Since 2022, we have served clients from over 110 different countries and typically serve around 1,400 clients a year. Our primary focus has always been to embrace, equip, and empower those we serve to feel welcome, stable, and connected to their community. Every immigrant deserves the opportunity to flourish and uniquely contribute to Eastern Washington\'s burgeoning vibrance and diversity. Our mission is to welcome and equip immigrants with tools, resources, and connection to achieve equity in the Inland Northwest. We provide immigration legal services, community power building, resource navigation, and trainings.',
				'logo_id' => 1103,
				'hero_id' => 1830,
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/mhspokane', 'label' => '@mhspokane' ),
					array( 'url' => 'https://www.linkedin.com/company/manzanita-house/', 'label' => '@manzanita-house' ),
					array( 'url' => 'https://www.facebook.com/mhspokane', 'label' => '@mhspokane' ),
				),
			),
			'Latinos en Spokane' => array(
				'content' => 'Our mission is to build capacity within Latino immigrant families and support the advancement of Latino community members, leaders, business-owners, and organizations in Spokane, to address the needs of the growing Latino population through inclusive community engagement, connections to local resources, and serve as catalyst for immigrant rights, social, racial, economic and environmental justice for a more equitable Spokane County.',
				'logo_id' => 1828,
				'hero_id' => 1829,
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/lespokane/', 'label' => '@lespokane' ),
					array( 'url' => 'https://www.facebook.com/groups/latinosenspokane/', 'label' => '/groups/latinosenspokane' ),
					array( 'url' => 'https://www.tiktok.com/@latinosenspokane', 'label' => '@latinosenspokane' ),
				),
			),
			'Terrain Programs' => array(
				'content' => 'Terrain is a groundbreaking nonprofit that believes everyone needs art, and that a more just and vibrant Spokane is possible through creativity, economic opportunity, and the collective action of everyone who dares to create. In addition to large-scale events highlighting hundreds of artists and attracting tens of thousands of attendees, Terrain also runs a retail storefront, a gallery space, an art-driven beautification program, a professional development program for artists, and a bi-monthly workshop series for arts-based businesses. In 2025, Terrain served 876 artists, 78,000 people attended events, and programs generated $1,356,816 in art sales and artist payments, influencing the economic empowerment of hundreds of local creatives. Event by event, program by program, artist by artist, Terrain transforms the landscape of the city by breaking down barriers and creating platforms that inspire.',
				'social'  => array(
					array( 'url' => 'https://www.linkedin.com/company/terrain-programs/', 'label' => 'Terrain Programs' ),
					array( 'url' => 'https://www.instagram.com/terrainspokane/', 'label' => '@terrainspokane' ),
					array( 'url' => 'https://www.facebook.com/terrainspokane/', 'label' => '@terrainspokane' ),
					array( 'url' => 'https://www.tiktok.com/@terrainspokane', 'label' => '@terrainspokane' ),
				),
			),
			'Tenants Union' => array(
				'content' => 'The Tenants Union fights for housing justice through education, organizing and advocacy. We win victories that change people\'s housing conditions and people\'s lives for the better.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/tenantsunion_wa_state/', 'label' => '@tenantsunion_wa_state' ),
					array( 'url' => 'https://www.facebook.com/TenantsUnion', 'label' => '@TenantsUnion' ),
					array( 'url' => 'https://www.youtube.com/@tenantsunionofwashingtonst6888', 'label' => '@tenantsunionofwashingtonst6888' ),
				),
			),
			'Spokane Tribal Network (STN)' => array(
				'content' => 'Dedicated to empowering the youth of all people through educational opportunities, cultural preservation, and community engagement. We believe that every child deserves access to resources and mentorship that will help them reach their full potential.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/stntribalfoodsovereignty/', 'label' => '@stntribalfoodsovereignty' ),
					array( 'url' => 'http://www.instagram.com/stnindigenousbirthjustice/', 'label' => '@stnindigenousbirthjustice' ),
					array( 'url' => 'https://www.facebook.com/SpokaneTribalNetwork/', 'label' => '@SpokaneTribalNetwork' ),
					array( 'url' => 'https://www.tiktok.com/@spokanetribalnetwork', 'label' => '@SpokaneTribalNetwork' ),
				),
			),
			'Shades of Motherhood Network' => array(
				'content' => 'Our vision is to create a world where Black mothers and families thrive, with equitable access to compassionate care, comprehensive support, and resources that empower healthy pregnancies, births, and postpartum experiences. Through holistic programs ranging from food assistance to doula services and peer support, we aim to uplift and transform Black maternal health outcomes for future generations.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/tsomnetwork/', 'label' => '@tsomnetwork' ),
					array( 'url' => 'https://www.linkedin.com/company/the-shades-of-motherhood-network/', 'label' => '@the-shades-of-motherhood-network' ),
					array( 'url' => 'https://www.facebook.com/tsomspokane', 'label' => '@tsomspokane' ),
				),
			),
			'SCAR Spokane' => array(
				'content' => 'Our mission is to fight systemic injustice by building a loving community where every person\'s humanity is valued and collective liberation becomes reality.',
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/scarspokane/', 'label' => '@scarspokane' ),
					array( 'url' => 'https://www.facebook.com/SCARSpokane', 'label' => '@SCARSpokane' ),
					array( 'url' => 'https://www.tiktok.com/@scarspokane', 'label' => '@scarspokane' ),
					array( 'url' => 'https://www.youtube.com/@scarspokane', 'label' => '@scarspokane' ),
				),
			),
			'Pacific Islander Community Association of WA (PICA)' => array(
				'content' => 'The Pacific Islander Community Association is a nonprofit organization by Pasifika, for Pasifika that seeks to live out the indigenous values of Native Hawaiians and Pacific Islander communities in Washington state through community organizing and speaking truth fiercely to systems of power, while providing social supports and cultural spaces for the community to gather in dignity. Founded in 2019, our mission is to establish a cultural home, center community power, and advocate to further the wellness of Native Hawaiian/Pacific Islander communities physically, culturally, socially, and spiritually.',
				'social'  => array(
					array( 'url' => 'https://www.linkedin.com/company/picawashington/', 'label' => '@picawashington' ),
					array( 'url' => 'https://www.instagram.com/picawashington', 'label' => '@picawashington' ),
					array( 'url' => 'https://www.facebook.com/PacificIslanderCommunityAssociation', 'label' => '@PacificIslanderCommunityAssociation' ),
				),
			),
			'Inchelium Language & Culture Association (ILCA)' => array(
				'content' => 'The mission of the Inchelium Language & Culture Association (ILCA) is to foster and sustain a dynamic community of Salish language speakers whose daily lives are expressed through a commitment to Lakes and Colville culture and a connection to their traditional territories. We work diligently to create new Salish Language speakers and teachers. Our teachers are trained in Second Language Acquisition techniques to ensure our language is retained at the highest levels possible.',
				'logo_id' => 1826,
				'hero_id' => 1827,
				'social'  => array(
					array( 'url' => 'https://www.facebook.com/ILCA11/', 'label' => '@ILCA11' ),
				),
			),
			'If You Could Save Just One' => array(
				'content' => 'Just One provides free, high-quality programs, system navigation, and resources that help Hillyard youth and families build power, advocate for themselves, and create community change.',
				'logo_id' => 1824,
				'hero_id' => 1825,
				'social'  => array(
					array( 'url' => 'https://www.facebook.com/justone.atriskyouth.1', 'label' => '@justone.atriskyouth.1' ),
					array( 'url' => 'https://www.instagram.com/if_just_one/', 'label' => 'if_just_one' ),
				),
			),
			'Foundation for Youth Resiliency and Engagement (FYRE)' => array(
				'content' => 'Founded in October of 2020, FYRE is a local non-profit organization serving the young people of Okanogan County. Since opening, FYRE has served hundreds of youth ages 12-24. In 2022 alone, FYRE welcomed over 700 individual youths and was visited over 3500 times.',
				'logo_id' => 1822,
				'hero_id' => 1823,
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/okfyre/', 'label' => '@okfyre' ),
					array( 'url' => 'https://www.facebook.com/okfyre', 'label' => '@okfyre' ),
					array( 'url' => 'https://www.tiktok.com/@okfyre', 'label' => '@okfyre' ),
				),
			),
			'CAT Spokane' => array(
				'content' => 'CAT Spokane is committed to removing barriers to care and providing holistic, supportive opportunities to work towards personal goals for housing, employment, health, transportation, exiting the criminal justice system, recovery from substance use disorders, and more. Our mission is to provide compassionate, holistic care to people experiencing homelessness in a safe and therapeutic environment. We focus on removing barriers to care to empower people to strive for their personal goals and live the life they choose. CAT\'s primary goals are to foster community connection, build trust, instill hope and personal empowerment for people experiencing homelessness.',
				'logo_id' => 1820,
				'hero_id' => 1821,
				'social'  => array(
					array( 'url' => 'https://www.instagram.com/catspokane/', 'label' => '@catspokane' ),
					array( 'url' => 'https://www.facebook.com/CatSpokane', 'label' => '@CatSpokane' ),
				),
			),
			'Carl Maxey Center' => array(
				'content' => 'At the Carl Maxey Center, our dedicated team is the heart of everything we do. Passionate about empowering and uplifting Spokane\'s Black/African American community, our staff brings a wealth of experience, knowledge, and care to every initiative. Each member of our team is committed to advancing equity, fostering opportunities, and creating a vibrant space where everyone feels welcome. Together, we strive to honor Carl Maxey\'s legacy by working tirelessly to inspire growth, connection, and positive change.',
				'logo_id' => 1112,
				'hero_id' => 1819,
				'social'  => array(
					array( 'url' => 'https://www.facebook.com/carlmaxeycenter/', 'label' => '@carlmaxeycenter' ),
					array( 'url' => 'https://www.instagram.com/carlmaxeycenter/', 'label' => '@carlmaxeycenter' ),
					array( 'url' => 'https://www.linkedin.com/company/thecarlmaxeycenter/', 'label' => '@thecarlmaxeycenter' ),
				),
			),
			'American Indian Community Center (AICC)' => array(
				'content' => 'The American Indian Community Center was founded in 1967 as a social gathering place for Indian and Native American people who lived in the Spokane area. Since 1967 AICC has become a comprehensive social service agency serving American Indian/Alaskan Natives and all other racial groups by providing Employment and Training Services, which attempts to match programs and resources for the individual client\'s needs. We also offer Indian Child Welfare services or families who are in danger of losing their children to Child Protective Services.',
				'logo_id' => 1117,
				'hero_id' => 1818,
				'social'  => array(
					array( 'url' => 'https://www.facebook.com/indiancenter610/', 'label' => '@indiancenter610' ),
					array( 'url' => 'https://www.youtube.com/@aiccspokane', 'label' => '@aiccspokane' ),
				),
			),
			'Asians for Collective Liberation in Spokane' => array(
				'content' => 'ACL Spokane centers the power of Asians and Asian Americans to build a just, healthy, and thriving community for all, cultivating collective care and belonging, advancing health and wellness, and strengthening civic engagement through collective action.',
				'logo_id' => 1817,
				'hero_id' => 1816,
				'social'  => array(
					array( 'url' => 'https://www.facebook.com/aclspokane/', 'label' => '@aclspokane' ),
					array( 'url' => 'https://www.instagram.com/aclspokane/', 'label' => '@aclspokane' ),
					array( 'url' => 'https://www.tiktok.com/@aclspokane', 'label' => '@aclspokane' ),
					array( 'url' => 'https://www.youtube.com/@aclspokaneYT', 'label' => '@aclspokaneYT' ),
					array( 'url' => 'https://www.linkedin.com/company/apic-spokane/', 'label' => 'Asians for Collective Liberation' ),
				),
			),
		);
	}
}
