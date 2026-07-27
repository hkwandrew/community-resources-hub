<?php
/**
 * Public ICS export for BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves single-opportunity calendar files from approved CPT posts.
 */
final class OpportunityIcsExporter {

	const ACTION = 'wm_bci_opportunity_ics';

	/**
	 * Repository.
	 *
	 * @var OpportunityRepository
	 */
	private $repository;

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	public function __construct( OpportunityRepository $repository, ?Config $config = null ) {
		$this->repository = $repository;
		$this->config     = $config ?: new Config();
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * URL for a source GF entry ID.
	 */
	public static function url_for_entry_id( $entry_id ) {
		$entry_id = absint( $entry_id );

		if ( ! $entry_id ) {
			return '';
		}

		return add_query_arg(
			array(
				'action'   => self::ACTION,
				'entry_id' => $entry_id,
				'sig'      => self::signature( $entry_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Handle request.
	 */
	public function handle() {
		$entry_id = isset( $_GET['entry_id'] ) ? absint( wp_unslash( (string) $_GET['entry_id'] ) ) : 0;
		$sig      = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['sig'] ) ) : '';

		if ( ! $entry_id || '' === $sig || ! hash_equals( self::signature( $entry_id ), $sig ) ) {
			$this->fail_not_found();
		}

		$post_id = $this->repository->find_by_source_entry_id( $entry_id );

		if ( ! $post_id || 'Approved' !== (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'approval_status' ), true ) || ! $this->can_export( $post_id ) ) {
			$this->fail_not_found();
		}

		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="bci-opportunity-' . $entry_id . '.ics"' );
		echo $this->render_ics( $post_id, $entry_id );
		exit;
	}

	/**
	 * Whether this post has a valid primary date.
	 */
	public function can_export( $post_id ) {
		$primary_date = $this->primary_date( $post_id );

		return '' !== $primary_date && false !== strtotime( $primary_date );
	}

	/**
	 * Render ICS content.
	 */
	public function render_ics( $post_id, $entry_id = 0 ) {
		$title       = $this->escape_text( get_the_title( $post_id ) );
		$description = $this->build_description( $post_id );
		$location    = $this->escape_text( $this->meta( $post_id, 'address' ) );
		$info_url    = $this->meta( $post_id, 'info_url' );
		$uid         = sprintf( 'wm-bci-opportunity-%d@watersmeet.foundation', absint( $entry_id ? $entry_id : $post_id ) );
		$ics_lines   = array(
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Waters Meet//BCI Opportunities//EN',
			'CALSCALE:GREGORIAN',
			'BEGIN:VEVENT',
			'UID:' . $uid,
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
			'SUMMARY:' . $title,
		);

		foreach ( $this->date_lines( $post_id ) as $line ) {
			$ics_lines[] = $line;
		}

		if ( '' !== $description ) {
			$ics_lines[] = 'DESCRIPTION:' . $description;
		}

		if ( '' !== $location ) {
			$ics_lines[] = 'LOCATION:' . $location;
		}

		if ( '' !== $info_url ) {
			$ics_lines[] = 'URL:' . $this->escape_text( $info_url );
		}

		$ics_lines[] = 'END:VEVENT';
		$ics_lines[] = 'END:VCALENDAR';

		return implode( "\r\n", $ics_lines ) . "\r\n";
	}

	/**
	 * Signature for a source GF entry ID.
	 */
	private static function signature( $entry_id ) {
		return hash_hmac( 'sha256', (string) absint( $entry_id ), wp_salt() );
	}

	/**
	 * Date lines.
	 *
	 * @return array<int,string>
	 */
	private function date_lines( $post_id ) {
		$primary_date = $this->primary_date( $post_id );
		$end_date     = $this->meta( $post_id, 'end_date' );
		$start_time   = $this->meta( $post_id, 'start_time' );
		$end_time     = $this->meta( $post_id, 'end_time' );
		$start_stamp  = '' !== $start_time ? strtotime( $primary_date . ' ' . $start_time ) : false;
		$end_stamp    = false;

		if ( '' !== $end_time ) {
			$end_stamp = strtotime( ( '' !== $end_date ? $end_date : $primary_date ) . ' ' . $end_time );
		} elseif ( '' !== $end_date ) {
			$end_stamp = strtotime( $end_date );
		}

		if ( false === $start_stamp ) {
			$start_day = strtotime( $primary_date );
			$end_day   = '' !== $end_date ? strtotime( $end_date ) : $start_day;

			if ( false === $start_day ) {
				return array();
			}

			if ( false === $end_day ) {
				$end_day = $start_day;
			}

			return array(
				'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', $start_day ),
				'DTEND;VALUE=DATE:' . gmdate( 'Ymd', strtotime( '+1 day', $end_day ) ),
			);
		}

		if ( false === $end_stamp ) {
			$end_stamp = strtotime( '+1 hour', $start_stamp );
		} elseif ( '' !== $end_date && '' === $end_time ) {
			$end_stamp = strtotime( '+1 day', $end_stamp );
		}

		return array(
			'DTSTART:' . gmdate( 'Ymd\THis', $start_stamp ),
			'DTEND:' . gmdate( 'Ymd\THis', $end_stamp ),
		);
	}

	/**
	 * Primary date follows the Grant/RFP deadline rule.
	 */
	private function primary_date( $post_id ) {
		if ( $this->config->is_grant_opportunity_type( $this->meta( $post_id, 'opportunity_type' ) ) ) {
			return $this->meta( $post_id, 'grant_deadline' );
		}

		return $this->meta( $post_id, 'start_date' );
	}

	/**
	 * Description field.
	 */
	private function build_description( $post_id ) {
		$parts       = array();
		$description = trim( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ) );
		$info_url    = $this->meta( $post_id, 'info_url' );

		if ( '' !== $description ) {
			$parts[] = $description;
		}

		if ( '' !== $info_url ) {
			$parts[] = sprintf(
				/* translators: %s: opportunity information URL. */
				__( 'More information: %s', 'community-resources-hub' ),
				$info_url
			);
		}

		return $this->escape_text( implode( "\n\n", $parts ) );
	}

	/**
	 * Scalar meta.
	 */
	private function meta( $post_id, $semantic_key ) {
		return trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( $semantic_key ), true ) );
	}

	/**
	 * Escape ICS text.
	 */
	private function escape_text( $value ) {
		$value = str_replace( '\\', '\\\\', (string) $value );
		$value = str_replace( ';', '\;', $value );
		$value = str_replace( ',', '\,', $value );
		$value = preg_replace( "/\r\n|\r|\n/", '\\n', $value );

		return trim( (string) $value );
	}

	/**
	 * Return a 404.
	 */
	private function fail_not_found() {
		header( 'HTTP/1.1 404 Not Found' );
		exit;
	}
}
