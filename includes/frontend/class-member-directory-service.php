<?php
/**
 * Plugin-owned BCI member directory data service.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\ContentModel\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds member directory payloads from the plugin-owned member CPT.
 */
final class MemberDirectoryService {

	const CACHE_KEY                    = 'community_resources_hub_member_directory';
	const CACHE_TTL                    = 300;
	const QUERY_PAGE_SIZE              = 100;
	const WATERS_MEET_FILTER_SLUG      = 'waters-meet';
	const WATERS_MEET_FILTER_LABEL     = 'Waters Meet';
	const WATERS_MEET_ORGANIZATION_SLUGS = array(
		'waters-meet',
		'waters-meet-action-fund',
		'waters-meet-foundation',
	);

	/**
	 * Whether cache invalidation hooks have been registered.
	 *
	 * @var bool
	 */
	private static $cache_invalidation_registered = false;

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	public function __construct( ?Config $config = null ) {
		$this->config = $config ?: new Config();
	}

	/**
	 * Register cache invalidation hooks for member payloads.
	 *
	 * @return void
	 */
	public static function register_cache_invalidation() {
		if ( self::$cache_invalidation_registered ) {
			return;
		}

		$config    = new Config();
		$post_type = $config->member_post_type();

		add_action( 'save_post_' . $post_type, array( __CLASS__, 'flush_cache' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache_for_post' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'flush_cache_for_post' ), 10, 1 );
		add_action( 'clean_post_cache', array( __CLASS__, 'flush_cache_for_post' ), 10, 2 );

		self::$cache_invalidation_registered = true;
	}

	/**
	 * Flush computed member-directory payload cache.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post object.
	 * @param bool          $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public static function flush_cache( $post_id = 0, $post = null, $update = false ) {
		delete_transient( self::CACHE_KEY );
	}

	/**
	 * Flush member cache only when a BCI member changes.
	 *
	 * @param int        $post_id Post ID.
	 * @param \WP_Post|null $post Post object.
	 * @return void
	 */
	public static function flush_cache_for_post( $post_id, $post = null ) {
		$post_type = is_object( $post ) && isset( $post->post_type ) ? (string) $post->post_type : get_post_type( $post_id );
		$config    = new Config();

		if ( $config->member_post_type() === $post_type ) {
			self::flush_cache();
		}
	}

	/**
	 * Published member payloads.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function all() {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$post_ids = $this->query_member_ids();
		$members = array();

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );

			if ( ! $post_id ) {
				continue;
			}

			$title = $this->title( $post_id );
			$slug  = sanitize_title( $title );

			$members[] = array(
				'id'                  => $post_id,
				'title'               => $title,
				'slug'                => $slug,
				'shareSlug'           => $this->share_slug( $slug, $post_id ),
				'aliases'             => $this->aliases( $post_id ),
				'summary'             => $this->summary( $post_id ),
				'overview'            => trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ),
				'overviewHtml'        => $this->overview_html( $post_id ),
				'communityServed'     => $this->meta( $post_id, 'community_served' ),
				'foundedYear'         => $this->meta( $post_id, 'founded_year' ),
				'contactEmail'        => $this->meta( $post_id, 'contact_email' ),
				'websiteUrl'          => $this->meta( $post_id, 'website_url' ),
				'phone'               => $this->meta( $post_id, 'phone' ),
				'mainOffice'          => $this->meta( $post_id, 'main_office' ),
				'socialLinks'         => $this->social_links( $post_id ),
				'programsHtml'        => $this->wysiwyg_meta( $post_id, 'programs' ),
				'attachments'         => $this->attachments( $post_id ),
				'videoUrl'            => $this->meta( $post_id, 'video_url' ),
				'videoLabel'          => $this->meta( $post_id, 'video_label' ),
				'profileUrl'          => get_permalink( $post_id ),
				'logoUrl'             => $this->media_meta( $post_id, 'logo_url' ),
				'heroImageUrl'        => $this->media_meta( $post_id, 'hero_image_url' ),
				'heroBackgroundColor' => $this->hex_color_meta( $post_id, 'hero_background_color' ),
			);
		}

		set_transient( self::CACHE_KEY, $members, self::CACHE_TTL );

		return $members;
	}

	/**
	 * Published member IDs, paged to avoid unbounded queries.
	 *
	 * @return array<int,int>
	 */
	private function query_member_ids() {
		$post_ids = array();
		$page     = 1;

		do {
			$batch = get_posts(
				array(
					'post_type'      => $this->config->member_post_type(),
					'post_status'    => 'publish',
					'posts_per_page' => self::QUERY_PAGE_SIZE,
					'paged'          => $page,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'orderby'        => array(
						'menu_order' => 'ASC',
						'title'      => 'ASC',
					),
				)
			);

			$batch = is_array( $batch ) ? array_map( 'absint', $batch ) : array();

			foreach ( $batch as $post_id ) {
				if ( $post_id ) {
					$post_ids[] = $post_id;
				}
			}

			$page++;
		} while ( count( $batch ) === self::QUERY_PAGE_SIZE );

		return $post_ids;
	}

	/**
	 * Match an organization name to a BCI member title or alias.
	 *
	 * @param string $organization Organization label.
	 * @return array<string,mixed>|null
	 */
	public function match_organization( $organization ) {
		$needle = $this->match_key( $organization );

		if ( '' === $needle ) {
			return null;
		}

		foreach ( $this->all() as $member ) {
			$candidates = array_merge( array( $member['title'] ), is_array( $member['aliases'] ) ? $member['aliases'] : array() );

			foreach ( $candidates as $candidate ) {
				if ( $needle === $this->match_key( $candidate ) ) {
					return $member;
				}
			}
		}

		return null;
	}

	/**
	 * Public member-filter identity for an opportunity organization.
	 *
	 * Waters Meet and its related organization labels intentionally share one
	 * synthetic filter identity. Other organizations keep the identity supplied
	 * by the member-directory match.
	 *
	 * @param string $organization Organization label from the opportunity.
	 * @param string $member_slug Existing matched member slug.
	 * @param string $member_label Existing matched member label.
	 * @return array{slug:string,label:string}
	 */
	public function opportunity_member_identity( $organization, $member_slug = '', $member_label = '' ) {
		$organization_slug = sanitize_title( (string) $organization );

		if ( in_array( $organization_slug, self::WATERS_MEET_ORGANIZATION_SLUGS, true ) ) {
			return array(
				'slug'  => self::WATERS_MEET_FILTER_SLUG,
				'label' => __( 'Waters Meet', 'community-resources-hub' ),
			);
		}

		return array(
			'slug'  => sanitize_title( (string) $member_slug ),
			'label' => trim( wp_strip_all_tags( (string) $member_label ) ),
		);
	}

	/**
	 * Raw post title for JSON payloads before renderer escaping.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function title( $post_id ) {
		return trim( wp_strip_all_tags( get_post_field( 'post_title', $post_id, 'raw' ) ) );
	}

	/**
	 * Scalar meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $semantic_key Semantic key.
	 * @return string
	 */
	private function meta( $post_id, $semantic_key ) {
		return trim( (string) get_post_meta( $post_id, $this->config->member_field_name( $semantic_key ), true ) );
	}

	/**
	 * Sanitized hex color meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $semantic_key Semantic key.
	 * @return string
	 */
	private function hex_color_meta( $post_id, $semantic_key ) {
		return Schema::sanitize_hex_color( get_post_meta( $post_id, $this->config->member_field_name( $semantic_key ), true ) );
	}

	/**
	 * Media meta that may be stored as a URL, attachment ID, or image-like array.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $semantic_key Semantic key.
	 * @return string
	 */
	private function media_meta( $post_id, $semantic_key ) {
		$value = get_post_meta( $post_id, $this->config->member_field_name( $semantic_key ), true );

		if ( is_array( $value ) ) {
			$url = '';

			if ( ! empty( $value['url'] ) ) {
				$url = esc_url_raw( (string) $value['url'] );
			}

			if ( '' !== $url ) {
				return $url;
			}

			$attachment_id = absint( $value['ID'] ?? $value['id'] ?? 0 );
			if ( $attachment_id ) {
				return $this->attachment_url( $attachment_id );
			}

			return '';
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		if ( is_numeric( $value ) ) {
			return $this->attachment_url( absint( $value ) );
		}

		return esc_url_raw( $value );
	}

	/**
	 * Attachment URL for BCI member media.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private function attachment_url( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || ! function_exists( 'wp_get_attachment_image_url' ) ) {
			return '';
		}

		return (string) wp_get_attachment_image_url( $attachment_id, 'full' );
	}

	/**
	 * Alias meta.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,string>
	 */
	private function aliases( $post_id ) {
		$value = get_post_meta( $post_id, $this->config->member_field_name( 'aliases' ), true );

		if ( is_array( $value ) ) {
			return array_values(
				array_filter(
					array_map(
						static function ( $alias ) {
							return sanitize_text_field( (string) $alias );
						},
						$value
					)
				)
			);
		}

		$lines   = preg_split( '/\r\n|\r|\n/', (string) $value );
		$aliases = array();

		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$alias = sanitize_text_field( (string) $line );

			if ( '' !== $alias ) {
				$aliases[] = $alias;
			}
		}

		return $aliases;
	}

	/**
	 * Social links from the ACF repeater schema.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{platform:string,url:string,label:string}>
	 */
	private function social_links( $post_id ) {
		$field_name = $this->config->member_field_name( 'social_links' );
		$value      = get_post_meta( $post_id, $field_name, true );
		$rows       = $this->social_link_rows( $post_id, $field_name, $value );
		$links      = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$platform = $this->social_platform_label( $row['social_platform'] ?? '' );
			$url      = esc_url_raw( (string) ( $row['url'] ?? '' ) );
			$label    = sanitize_text_field( (string) ( $row['label'] ?? '' ) );

			if ( '' === $platform || '' === $url ) {
				continue;
			}

			$links[] = array(
				'platform' => $platform,
				'url'      => $url,
				'label'    => $label,
			);
		}

		return $links;
	}

	/**
	 * Raw social-link rows from either imported arrays or ACF's row meta format.
	 *
	 * @param int         $post_id Post ID.
	 * @param string      $field_name Field name.
	 * @param mixed       $value Raw value.
	 * @return array<int,array<string,mixed>>
	 */
	private function social_link_rows( $post_id, $field_name, $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( ! is_numeric( $value ) ) {
			return array();
		}

		$count = absint( $value );
		$rows  = array();

		for ( $index = 0; $index < $count; $index++ ) {
			$prefix = $field_name . '_' . $index;
			$rows[] = array(
				'social_platform' => get_post_meta( $post_id, $prefix . '_social_platform', true ),
				'url'             => get_post_meta( $post_id, $prefix . '_url', true ),
				'label'           => get_post_meta( $post_id, $prefix . '_label', true ),
			);
		}

		return $rows;
	}

	/**
	 * Human-readable social platform label from the ACF select value.
	 *
	 * @param string $value Raw platform value.
	 * @return string
	 */
	private function social_platform_label( $value ) {
		$value = sanitize_text_field( (string) $value );
		$parts = array_map( 'trim', explode( '|', $value, 2 ) );
		$label = $parts[1] ?? $parts[0] ?? '';

		return sanitize_text_field( $label );
	}

	/**
	 * Sanitized member WYSIWYG meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $semantic_key Semantic key.
	 * @return string
	 */
	private function wysiwyg_meta( $post_id, $semantic_key ) {
		$value = get_post_meta( $post_id, $this->config->member_field_name( $semantic_key ), true );

		if ( is_array( $value ) ) {
			return '';
		}

		return $this->format_wysiwyg_html( (string) $value );
	}

	/**
	 * Sanitized, formatted member overview HTML from the classic editor body.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function overview_html( $post_id ) {
		return $this->format_wysiwyg_html( (string) get_post_field( 'post_content', $post_id, 'raw' ) );
	}

	/**
	 * Format and sanitize WYSIWYG HTML for modal payloads.
	 *
	 * @param string $value Raw editor value.
	 * @return string
	 */
	private function format_wysiwyg_html( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'apply_filters' ) ) {
			$value = (string) apply_filters( 'the_content', $value );
		} elseif ( function_exists( 'wpautop' ) ) {
			$value = (string) wpautop( $value );
		}

		if ( function_exists( 'wp_kses_post' ) ) {
			return trim( (string) wp_kses_post( $value ) );
		}

		return trim( $value );
	}

	/**
	 * Attachment payload from member configuration.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{url:string,label:string}>
	 */
	private function attachments( $post_id ) {
		$values      = $this->attachment_values( get_post_meta( $post_id, $this->config->member_field_name( 'attachments' ), true ) );
		$attachments = array();

		foreach ( $values as $value ) {
			$label = '';

			if ( is_array( $value ) ) {
				$label = sanitize_text_field( (string) ( $value['label'] ?? $value['title'] ?? '' ) );
				$value = $value['url'] ?? $value['href'] ?? '';
			}

			$url = esc_url_raw( trim( (string) $value ) );

			if ( '' === $url ) {
				continue;
			}

			if ( '' === $label ) {
				$path  = (string) parse_url( $url, PHP_URL_PATH );
				$label = basename( $path );
			}

			$attachments[] = array(
				'url'   => $url,
				'label' => '' !== $label ? rawurldecode( $label ) : $url,
			);
		}

		return $attachments;
	}

	/**
	 * Uploaded file URL values from stored member meta.
	 *
	 * @param mixed $value Raw meta value.
	 * @return array<int,mixed>
	 */
	private function attachment_values( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		$value = trim( (string) $value );

		if ( '' === $value ) {
			return array();
		}

		$decoded = json_decode( $value, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$values = preg_split( '/(?:\r\n|\r|\n)+|\s*,\s*/', $value );

		return is_array( $values ) ? $values : array();
	}

	/**
	 * Summary from excerpt or content.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function summary( $post_id ) {
		$summary = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) );

		if ( '' !== $summary ) {
			return $summary;
		}

		$overview = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) ) );
		$words    = preg_split( '/\s+/', $overview );

		if ( ! is_array( $words ) || count( $words ) <= 22 ) {
			return $overview;
		}

		return implode( ' ', array_slice( $words, 0, 22 ) ) . '...';
	}

	/**
	 * Strict normalized match key.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function match_key( $value ) {
		return sanitize_title( remove_accents( trim( strtolower( (string) $value ) ) ) );
	}

	/**
	 * Unique public token for in-page member profile URLs.
	 *
	 * @param string $slug Member slug.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private function share_slug( $slug, $post_id ) {
		$post_id = absint( $post_id );
		$slug    = sanitize_title( $slug );

		return '' !== $slug ? $slug . '-' . $post_id : (string) $post_id;
	}
}
