<?php
/**
 * Plugin-owned approved opportunity payload service.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\ContentModel\Schema;
use WatersMeet\CommunityResourcesHub\Workflow\OpportunityIcsExporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds front-end payloads from approved opportunity posts.
 */
final class ApprovedOpportunityService {

	const CACHE_KEY       = 'community_resources_hub_approved_opportunities';
	const CACHE_TTL       = 300;
	const QUERY_PAGE_SIZE = 100;

	/**
	 * Whether cache invalidation hooks have been registered.
	 *
	 * @var bool
	 */
	private static $cache_invalidation_registered = false;

	/**
	 * Member service.
	 *
	 * @var MemberDirectoryService
	 */
	private $members;

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	public function __construct( ?MemberDirectoryService $members = null, ?Config $config = null ) {
		$this->config  = $config ?: new Config();
		$this->members = $members ?: new MemberDirectoryService( $this->config );
	}

	/**
	 * Register cache invalidation hooks for approved opportunity payloads.
	 *
	 * @return void
	 */
	public static function register_cache_invalidation() {
		if ( self::$cache_invalidation_registered ) {
			return;
		}

		$config     = new Config();
		$taxonomies = array(
			$config->opportunity_type_taxonomy(),
			$config->opportunity_tag_taxonomy(),
		);

		add_action( 'save_post_' . $config->opportunity_post_type(), array( __CLASS__, 'flush_cache' ), 10, 3 );
		add_action( 'save_post_' . $config->member_post_type(), array( __CLASS__, 'flush_cache' ), 10, 3 );
		add_action( 'deleted_post', array( __CLASS__, 'flush_cache_for_post' ), 10, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'flush_cache_for_post' ), 10, 1 );
		add_action( 'clean_post_cache', array( __CLASS__, 'flush_cache_for_post' ), 10, 2 );
		foreach ( $taxonomies as $taxonomy ) {
			add_action( 'created_' . $taxonomy, array( __CLASS__, 'flush_cache_for_term' ), 10, 3 );
			add_action( 'edited_' . $taxonomy, array( __CLASS__, 'flush_cache_for_term' ), 10, 3 );
			add_action( 'delete_' . $taxonomy, array( __CLASS__, 'flush_cache_for_term' ), 10, 3 );
		}

		self::$cache_invalidation_registered = true;
	}

	/**
	 * Flush computed approved-opportunity payload cache.
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
	 * Flush opportunity cache when opportunities or members change.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post Post object.
	 * @return void
	 */
	public static function flush_cache_for_post( $post_id, $post = null ) {
		$post_type = is_object( $post ) && isset( $post->post_type ) ? (string) $post->post_type : get_post_type( $post_id );
		$config    = new Config();

		if ( in_array( $post_type, array( $config->opportunity_post_type(), $config->member_post_type() ), true ) ) {
			self::flush_cache();
		}
	}

	/**
	 * Flush opportunity cache when opportunity-type terms change.
	 *
	 * @param int   $term_id Term ID.
	 * @param int   $tt_id   Term taxonomy ID.
	 * @param mixed $args    Dynamic taxonomy-hook arguments.
	 * @return void
	 */
	public static function flush_cache_for_term( $term_id, $tt_id = 0, $args = array() ) {
		self::flush_cache();
	}

	/**
	 * Approved opportunities.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function all() {
		$cached = get_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$post_ids = $this->query_approved_post_ids();
		$items = array();

		foreach ( is_array( $post_ids ) ? $post_ids : array() as $post_id ) {
			$post_id = absint( $post_id );

			if ( ! $post_id ) {
				continue;
			}

			$items[] = $this->map_post( $post_id );
		}

		usort( $items, array( $this, 'compare_items' ) );

		set_transient( self::CACHE_KEY, $items, self::CACHE_TTL );

		return $items;
	}

	/**
	 * Approved opportunity IDs, paged to avoid unbounded queries.
	 *
	 * @return array<int,int>
	 */
	private function query_approved_post_ids() {
		$post_ids = array();
		$page     = 1;

		do {
			$batch = get_posts(
				array(
					'post_type'      => $this->config->opportunity_post_type(),
					'post_status'    => 'publish',
					'posts_per_page' => self::QUERY_PAGE_SIZE,
					'paged'          => $page,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => $this->config->opportunity_field_name( 'approval_status' ),
					'meta_value'     => 'Approved',
					'orderby'        => array(
						'date' => 'DESC',
						'ID'   => 'DESC',
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
	 * Map one opportunity.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	private function map_post( $post_id ) {
		$organization    = $this->meta( $post_id, 'organization' );
		$submitter_name  = $this->meta( $post_id, 'submitter_name' );
		$member          = $this->members->match_organization( $organization );
		$type_value      = $this->meta( $post_id, 'opportunity_type' );
		$type_config     = $this->config->opportunity_type_config( $type_value );
		$type_name       = '' !== $type_config['name'] ? $type_config['name'] : $type_config['label'];
		$type_badge      = '' !== $type_config['label'] ? $type_config['label'] : $type_name;
		$primary_date    = $this->primary_date( $post_id, $type_value );
		$title           = $this->title( $post_id );
		$slug            = sanitize_title( $title );
		$member_identity = $this->members->opportunity_member_identity(
			$organization,
			is_array( $member ) ? (string) $member['slug'] : '',
			is_array( $member ) ? (string) $member['title'] : ''
		);

		return array(
			'id'                 => $post_id,
			'title'              => $title,
			'slug'               => $slug,
			'shareSlug'          => $this->share_slug( $slug, $post_id ),
			'typeLabel'          => $type_name,
			'typeBadgeLabel'     => $type_badge,
			'typeSlug'           => $type_config['slug'],
			'typeColor'          => $type_config['color'],
			'isEvergreen'        => 'recommended-vendor' === $type_config['slug'],
			'isBciUpdate'        => $this->is_bci_update( $post_id ),
			'organization'       => $organization,
			'submittedBy'        => $submitter_name,
			'submittedDateLabel' => $this->submitted_date_label( $this->meta( $post_id, 'submitted_at' ) ),
			'primaryDate'        => $primary_date,
			'primaryDateLabel'   => $this->date_label( $primary_date, 'F j, Y' ),
			'endDate'            => $this->meta( $post_id, 'end_date' ),
			'detailDateLabel'    => $this->detail_date_label( $primary_date, $this->meta( $post_id, 'end_date' ) ),
			'timeRange'          => $this->time_range( $post_id ),
			'locationMode'       => $this->meta( $post_id, 'location_mode' ),
			'address'            => $this->meta( $post_id, 'address' ),
			'cost'               => $this->meta( $post_id, 'cost' ),
			'description'        => $this->description( $post_id ),
			'infoUrl'            => esc_url( $this->meta( $post_id, 'info_url' ) ),
			'attachments'        => $this->attachments( $post_id ),
			'memberSlug'         => $member_identity['slug'],
			'memberLabel'        => $member_identity['label'],
			'addToCalendarUrl'   => $this->calendar_url( $post_id ),
		);
	}

	/**
	 * Whether an opportunity carries the public BCI Update tag.
	 *
	 * @param int $post_id Opportunity post ID.
	 * @return bool
	 */
	private function is_bci_update( $post_id ) {
		return function_exists( 'has_term' ) && has_term(
			Schema::BCI_UPDATE_TAG_SLUG,
			$this->config->opportunity_tag_taxonomy(),
			$post_id
		);
	}

	/**
	 * Public date label for the UTC Gravity Forms creation timestamp.
	 *
	 * @param string $submitted_at Stored UTC timestamp.
	 * @return string
	 */
	private function submitted_date_label( $submitted_at ) {
		$submitted_at = trim( (string) $submitted_at );

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $submitted_at ) ) {
			return '';
		}

		$timezone = new \DateTimeZone( 'UTC' );
		$date     = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $submitted_at, $timezone );

		if ( false === $date || $submitted_at !== $date->format( 'Y-m-d H:i:s' ) ) {
			return '';
		}

		$display_timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : $timezone;

		return wp_date( 'F j, Y', $date->getTimestamp(), $display_timezone );
	}

	/**
	 * Scalar meta.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $semantic_key Semantic key.
	 * @return string
	 */
	private function meta( $post_id, $semantic_key ) {
		return trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( $semantic_key ), true ) );
	}

	/**
	 * Opportunity title text.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function title( $post_id ) {
		$title = get_post_field( 'post_title', $post_id, 'raw' );

		if ( '' === trim( (string) $title ) ) {
			$title = get_the_title( $post_id );
		}

		return $this->plain_text( $title );
	}

	/**
	 * Opportunity description text.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function description( $post_id ) {
		return $this->plain_text( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
	}

	/**
	 * Plain text normalized for JSON-driven modal rendering.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function plain_text( $value ) {
		return html_entity_decode( trim( (string) $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Primary date.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type Raw type.
	 * @return string
	 */
	private function primary_date( $post_id, $type ) {
		if ( $this->config->is_grant_opportunity_type( $type ) ) {
			return $this->meta( $post_id, 'grant_deadline' );
		}

		return $this->meta( $post_id, 'start_date' );
	}

	/**
	 * Formatted date.
	 *
	 * @param string $date Date string.
	 * @param string $format Date format.
	 * @return string
	 */
	private function date_label( $date, $format ) {
		$calendar_date = $this->calendar_date( $date );

		return false === $calendar_date ? '' : $this->format_calendar_date( $calendar_date, $format );
	}

	/**
	 * Parse a stored date-only value without converting it between timezones.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return \DateTimeImmutable|false
	 */
	private function calendar_date( $date ) {
		$date = trim( (string) $date );

		if ( 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return false;
		}

		$calendar_date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );

		return false !== $calendar_date && $date === $calendar_date->format( 'Y-m-d' ) ? $calendar_date : false;
	}

	/**
	 * Format a calendar date in its parsing timezone.
	 *
	 * @param \DateTimeImmutable $calendar_date Parsed calendar date.
	 * @param string             $format Date format.
	 * @return string
	 */
	private function format_calendar_date( \DateTimeImmutable $calendar_date, $format ) {
		return wp_date( $format, $calendar_date->getTimestamp(), $calendar_date->getTimezone() );
	}

	/**
	 * Detail date label.
	 *
	 * @param string $start Start date.
	 * @param string $end End date.
	 * @return string
	 */
	private function detail_date_label( $start, $end ) {
		$start_date = $this->calendar_date( $start );
		$end_date   = $this->calendar_date( $end );

		if ( false === $start_date ) {
			return '';
		}

		if ( false === $end_date || $end_date < $start_date || $start_date->format( 'Y-m-d' ) === $end_date->format( 'Y-m-d' ) ) {
			return $this->format_calendar_date( $start_date, 'F j, Y' );
		}

		if ( $start_date->format( 'Y' ) === $end_date->format( 'Y' ) ) {
			if ( $start_date->format( 'm' ) === $end_date->format( 'm' ) ) {
				return $this->format_calendar_date( $start_date, 'M j' ) . ' - ' . $this->format_calendar_date( $end_date, 'j, Y' );
			}

			return $this->format_calendar_date( $start_date, 'M j' ) . ' - ' . $this->format_calendar_date( $end_date, 'M j, Y' );
		}

		return $this->format_calendar_date( $start_date, 'M j, Y' ) . ' - ' . $this->format_calendar_date( $end_date, 'M j, Y' );
	}

	/**
	 * Time range.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function time_range( $post_id ) {
		$start = $this->meta( $post_id, 'start_time' );
		$end   = $this->meta( $post_id, 'end_time' );

		if ( '' !== $start && '' !== $end ) {
			return $start . ' - ' . $end;
		}

		return '' !== $start ? $start : $end;
	}

	/**
	 * Attachment payload.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{url:string,label:string}>
	 */
	private function attachments( $post_id ) {
		$lines       = $this->attachment_values( $this->meta( $post_id, 'file_upload' ) );
		$attachments = array();

		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			if ( is_array( $line ) ) {
				$line = isset( $line['url'] ) ? $line['url'] : '';
			}

			$url = esc_url_raw( trim( (string) $line ) );

			if ( '' === $url ) {
				continue;
			}

			$path  = (string) parse_url( $url, PHP_URL_PATH );
			$label = basename( $path );

			$attachments[] = array(
				'url'   => $url,
				'label' => '' !== $label ? rawurldecode( $label ) : $url,
			);
		}

		return $attachments;
	}

	/**
	 * Uploaded file URL values from stored post meta.
	 *
	 * @param string $value Raw value.
	 * @return array<int,mixed>
	 */
	private function attachment_values( $value ) {
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
	 * Calendar URL compatible with the legacy GF-backed exporter when possible.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function calendar_url( $post_id ) {
		$entry_id = absint( get_post_meta( $post_id, $this->config->opportunity_field_name( 'source_entry_id' ), true ) );

		if ( ! $entry_id ) {
			return '';
		}

		return OpportunityIcsExporter::url_for_entry_id( $entry_id );
	}

	/**
	 * Sort dated opportunities chronologically, followed by undated items.
	 *
	 * @param array<string,mixed> $left Left item.
	 * @param array<string,mixed> $right Right item.
	 * @return int
	 */
	private function compare_items( array $left, array $right ) {
		$left_date  = $this->calendar_date( $left['primaryDate'] ?? '' );
		$right_date = $this->calendar_date( $right['primaryDate'] ?? '' );

		if ( false !== $left_date && false !== $right_date ) {
			$date_order = strcmp( $left_date->format( 'Y-m-d' ), $right_date->format( 'Y-m-d' ) );

			if ( 0 !== $date_order ) {
				return $date_order;
			}
		} elseif ( false !== $left_date ) {
			return -1;
		} elseif ( false !== $right_date ) {
			return 1;
		}

		$title_order = strcasecmp( (string) ( $left['title'] ?? '' ), (string) ( $right['title'] ?? '' ) );

		if ( 0 !== $title_order ) {
			return $title_order;
		}

		return absint( $left['id'] ?? 0 ) <=> absint( $right['id'] ?? 0 );
	}

	/**
	 * Unique public token for in-page opportunity detail URLs.
	 *
	 * @param string $slug Opportunity slug.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	private function share_slug( $slug, $post_id ) {
		$post_id = absint( $post_id );
		$slug    = sanitize_title( $slug );

		return '' !== $slug ? $slug . '-' . $post_id : (string) $post_id;
	}
}
