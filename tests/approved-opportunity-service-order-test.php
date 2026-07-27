<?php
/**
 * Regression checks for approved opportunity ordering and date-only labels.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

date_default_timezone_set( 'UTC' );

$GLOBALS['crh_transients'] = array();
$GLOBALS['crh_post_terms'] = array(
	102 => array(
		'opportunity-tag' => array( 'bci-update' ),
	),
);
$GLOBALS['crh_posts']      = array(
	101 => array(
		'ID'           => 101,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-06-01 09:00:00',
		'post_title'   => 'Later event',
		'post_content' => 'Later event content.',
	),
	102 => array(
		'ID'           => 102,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-06-15 09:00:00',
		'post_title'   => 'Sooner event',
		'post_content' => 'Sooner event content.',
	),
	103 => array(
		'ID'           => 103,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-06-30 09:00:00',
		'post_title'   => 'Pending opportunity',
		'post_content' => 'Pending opportunity content.',
	),
	104 => array(
		'ID'           => 104,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-06-29 09:00:00',
		'post_title'   => 'Beta grant',
		'post_content' => 'Grant content.',
	),
	105 => array(
		'ID'           => 105,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-06-28 09:00:00',
		'post_title'   => 'Alpha event',
		'post_content' => 'Alpha event content.',
	),
	106 => array(
		'ID'           => 106,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-06-27 09:00:00',
		'post_title'   => 'Same title',
		'post_content' => 'First same-title event content.',
	),
	107 => array(
		'ID'           => 107,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-06-26 09:00:00',
		'post_title'   => 'Same title',
		'post_content' => 'Second same-title event content.',
	),
	108 => array(
		'ID'           => 108,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-07-01 09:00:00',
		'post_title'   => 'Alpha undated opportunity',
		'post_content' => 'Undated opportunity content.',
	),
	109 => array(
		'ID'           => 109,
		'post_type'    => 'bci_opportunity',
		'post_status'  => 'publish',
		'post_date'    => '2026-07-02 09:00:00',
		'post_title'   => 'Zulu invalid-date opportunity',
		'post_content' => 'Invalid-date opportunity content.',
	),
);
$GLOBALS['crh_post_meta']  = array(
	101 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Event',
		'wm_bci_organization'     => 'Later Org',
		'wm_bci_start_date'       => '2026-09-01',
	),
	102 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Event',
		'wm_bci_organization'     => 'Sooner Org',
		'wm_bci_start_date'       => '2026-07-11',
		'wm_bci_end_date'         => '2026-07-12',
		'wm_bci_submitted_at'     => '2026-07-13 18:42:10',
	),
	103 => array(
		'wm_bci_approval_status'  => 'Pending',
		'wm_bci_opportunity_type' => 'Event',
		'wm_bci_organization'     => 'Pending Org',
		'wm_bci_start_date'       => '2027-01-15',
	),
	104 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Grant / RFP',
		'wm_bci_organization'     => 'Grant Org',
		'wm_bci_start_date'       => '2027-01-01',
		'wm_bci_grant_deadline'   => '2026-08-01',
	),
	105 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Event',
		'wm_bci_organization'     => 'Alpha Org',
		'wm_bci_start_date'       => '2026-08-01',
	),
	106 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Event',
		'wm_bci_organization'     => 'Same Org One',
		'wm_bci_start_date'       => '2026-08-01',
	),
	107 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Event',
		'wm_bci_organization'     => 'Same Org Two',
		'wm_bci_start_date'       => '2026-08-01',
	),
	108 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Recommended Vendor',
		'wm_bci_organization'     => 'Undated Org',
	),
	109 => array(
		'wm_bci_approval_status'  => 'Approved',
		'wm_bci_opportunity_type' => 'Event',
		'wm_bci_organization'     => 'Invalid Date Org',
		'wm_bci_start_date'       => 'not-a-date',
	),
);

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
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
		return trim( wp_strip_all_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'remove_accents' ) ) {
	function remove_accents( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return (string) $html;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return esc_url( $url );
	}
}

if ( ! function_exists( 'wp_timezone' ) ) {
	function wp_timezone() {
		return new DateTimeZone( 'America/Los_Angeles' );
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		$date = new DateTimeImmutable( '@' . (string) ( $timestamp ?: time() ) );

		return $date->setTimezone( $timezone ?: wp_timezone() )->format( $format );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		return $GLOBALS['crh_transients'][ $transient ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		$GLOBALS['crh_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['crh_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$matches = array();

		foreach ( $GLOBALS['crh_posts'] as $post_id => $post ) {
			if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) {
				continue;
			}

			if ( isset( $args['post_status'] ) && $post['post_status'] !== $args['post_status'] ) {
				continue;
			}

			if ( ! empty( $args['meta_key'] ) ) {
				$meta_value = $GLOBALS['crh_post_meta'][ $post_id ][ $args['meta_key'] ] ?? '';

				if ( (string) $meta_value !== (string) ( $args['meta_value'] ?? '' ) ) {
					continue;
				}
			}

			$matches[] = $post_id;
		}

		if ( isset( $args['orderby']['date'] ) && 'DESC' === strtoupper( (string) $args['orderby']['date'] ) ) {
			usort(
				$matches,
				static function ( $left, $right ) {
					$left_date  = $GLOBALS['crh_posts'][ $left ]['post_date'] ?? '';
					$right_date = $GLOBALS['crh_posts'][ $right ]['post_date'] ?? '';

					if ( $left_date === $right_date ) {
						return $right <=> $left;
					}

					return strcmp( $right_date, $left_date );
				}
			);
		}

		$per_page = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : 0;

		if ( $per_page > 0 ) {
			$page   = max( 1, (int) ( $args['paged'] ?? 1 ) );
			$offset = ( $page - 1 ) * $per_page;

			return array_slice( $matches, $offset, $per_page );
		}

		return $matches;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( '' === $key ) {
			return $GLOBALS['crh_post_meta'][ $post_id ] ?? array();
		}

		return $GLOBALS['crh_post_meta'][ $post_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id, $context = 'display' ) {
		return $GLOBALS['crh_posts'][ $post_id ][ $field ] ?? '';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id ) {
		return $GLOBALS['crh_posts'][ $post_id ]['post_title'] ?? '';
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		return array();
	}
}

if ( ! function_exists( 'has_term' ) ) {
	function has_term( $term, $taxonomy = '', $post = null ) {
		$post_id = is_object( $post ) ? (int) ( $post->ID ?? 0 ) : (int) $post;
		$terms   = $GLOBALS['crh_post_terms'][ $post_id ][ $taxonomy ] ?? array();

		return in_array( (string) $term, array_map( 'strval', $terms ), true );
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-member-directory-service.php';
require_once dirname( __DIR__ ) . '/includes/frontend/class-approved-opportunity-service.php';

$service = new WatersMeet\CommunityResourcesHub\FrontEnd\ApprovedOpportunityService();
$items   = $service->all();
$ids     = array_map(
	static function ( $item ) {
		return (int) $item['id'];
	},
	$items
);

if ( array( 102, 105, 104, 106, 107, 101, 108, 109 ) !== $ids ) {
	fwrite( STDERR, 'Expected approved opportunity results to sort by primary date, then title and ID, with undated items last. Got: ' . implode( ', ', $ids ) . "\n" );
	exit( 1 );
}

$items_by_id = array();

foreach ( $items as $item ) {
	$items_by_id[ (int) $item['id'] ] = $item;
}

if ( 'sooner-event-102' !== ( $items_by_id[102]['shareSlug'] ?? '' ) ) {
	fwrite( STDERR, "Expected approved opportunity payloads to include unique share slugs for modal URLs.\n" );
	exit( 1 );
}

if ( '2026-08-01' !== ( $items_by_id[104]['primaryDate'] ?? '' ) ) {
	fwrite( STDERR, "Expected Grant / RFP opportunities to sort by grant deadline rather than start date.\n" );
	exit( 1 );
}

if ( '2026-07-11' !== ( $items_by_id[102]['primaryDate'] ?? '' ) || 'July 11, 2026' !== ( $items_by_id[102]['primaryDateLabel'] ?? '' ) ) {
	fwrite( STDERR, "Expected date-only opportunity values to keep their calendar date in a non-UTC WordPress timezone.\n" );
	exit( 1 );
}

if ( 'Jul 11 - 12, 2026' !== ( $items_by_id[102]['detailDateLabel'] ?? '' ) ) {
	fwrite( STDERR, "Expected date-only opportunity ranges to avoid timezone conversion.\n" );
	exit( 1 );
}

if ( true !== ( $items_by_id[108]['isEvergreen'] ?? null ) ) {
	fwrite( STDERR, "Expected Recommended Vendor opportunities to be marked evergreen.\n" );
	exit( 1 );
}

if ( false !== ( $items_by_id[102]['isEvergreen'] ?? null ) ) {
	fwrite( STDERR, "Expected dated event opportunities not to be marked evergreen.\n" );
	exit( 1 );
}

if ( true !== ( $items_by_id[102]['isBciUpdate'] ?? null ) ) {
	fwrite( STDERR, "Expected the BCI Update tag to be exposed independently from the primary opportunity type.\n" );
	exit( 1 );
}

if ( 'July 13, 2026' !== ( $items_by_id[102]['submittedDateLabel'] ?? '' ) ) {
	fwrite( STDERR, "Expected the UTC submission timestamp to produce a stable public date label.\n" );
	exit( 1 );
}

if ( '' !== ( $items_by_id[102]['submittedBy'] ?? null ) ) {
	fwrite( STDERR, "Expected Submitted by to use the person's name only, without an organization fallback.\n" );
	exit( 1 );
}

if ( false !== ( $items_by_id[101]['isBciUpdate'] ?? null ) || '' !== ( $items_by_id[101]['submittedDateLabel'] ?? null ) ) {
	fwrite( STDERR, "Expected untagged opportunities without a submission timestamp to expose explicit empty payload values.\n" );
	exit( 1 );
}

$member_identity = ( new WatersMeet\CommunityResourcesHub\FrontEnd\MemberDirectoryService() )->opportunity_member_identity(
	'Waters Meet Action Fund',
	'',
	''
);

if ( array( 'slug' => 'waters-meet', 'label' => 'Waters Meet' ) !== $member_identity ) {
	fwrite( STDERR, "Expected Waters Meet organization variants to share the MemberDirectoryService-owned synthetic identity.\n" );
	exit( 1 );
}

$hook_error = null;
set_error_handler(
	static function( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

try {
	WatersMeet\CommunityResourcesHub\FrontEnd\ApprovedOpportunityService::flush_cache_for_term(
		23,
		23,
		array( 'slug' => 'recommended-vendor' )
	);
} catch ( Throwable $error ) {
	$hook_error = $error;
} finally {
	restore_error_handler();
}

if ( $hook_error ) {
	fwrite( STDERR, 'Expected opportunity-type cache invalidation to accept WordPress term-hook arguments without warnings: ' . $hook_error->getMessage() . "\n" );
	exit( 1 );
}

if ( array_key_exists( WatersMeet\CommunityResourcesHub\FrontEnd\ApprovedOpportunityService::CACHE_KEY, $GLOBALS['crh_transients'] ) ) {
	fwrite( STDERR, "Expected opportunity-type term hooks to flush the approved opportunity cache.\n" );
	exit( 1 );
}

echo "Approved opportunity service order and date test passed.\n";
