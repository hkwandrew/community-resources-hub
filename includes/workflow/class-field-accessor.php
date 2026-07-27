<?php
/**
 * Gravity Forms entry field accessors for BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Workflow;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts Gravity Forms entry values into plugin-owned BCI fields.
 */
final class FieldAccessor {

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Entry title.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function title( array $entry ) {
		$title = trim( (string) $this->value( $entry, $this->config->field( 'title' ) ) );

		if ( '' !== $title ) {
			return $title;
		}

		return sprintf(
			/* translators: %d: Gravity Forms entry ID. */
			__( 'BCI Submission #%d', 'community-resources-hub' ),
			(int) $this->value( $entry, 'id' )
		);
	}

	/**
	 * Submitter name.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function submitter_name( array $entry ) {
		$field = $this->config->field( 'submitter_name' );
		$parts = array_filter(
			array(
				trim( (string) $this->value( $entry, $field . '.3' ) ),
				trim( (string) $this->value( $entry, $field . '.6' ) ),
			)
		);

		return trim( implode( ' ', $parts ) );
	}

	/**
	 * Split a full name into first and last name components.
	 *
	 * @return array{0:string,1:string}
	 */
	public function split_name( $name ) {
		$name = trim( (string) $name );

		if ( '' === $name ) {
			return array( '', '' );
		}

		$parts = preg_split( '/\s+/', $name );

		if ( false === $parts || empty( $parts ) ) {
			return array( $name, '' );
		}

		$first = array_shift( $parts );

		return array( (string) $first, implode( ' ', $parts ) );
	}

	/**
	 * Branch-aware opportunity type.
	 *
	 * Legacy entries without the time-sensitive answer continue to read field 1
	 * until the explicit opportunity-contract migration backfills the branches.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function opportunity_type( array $entry ) {
		$time_sensitive = strtolower( trim( (string) $this->value( $entry, $this->config->field( 'time_sensitive' ) ) ) );

		if ( 'no' === $time_sensitive ) {
			return trim( (string) $this->value( $entry, $this->config->field( 'non_date_sensitive_type' ) ) );
		}

		return trim( (string) $this->value( $entry, $this->config->field( 'opportunity_type' ) ) );
	}

	/**
	 * Whether the entry belongs to the date-sensitive calendar branch.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return bool
	 */
	public function is_time_sensitive( array $entry ) {
		$value = strtolower( trim( (string) $this->value( $entry, $this->config->field( 'time_sensitive' ) ) ) );

		if ( 'yes' === $value ) {
			return true;
		}

		if ( 'no' === $value ) {
			return false;
		}

		return ! in_array( $this->opportunity_type_slug( $entry ), array( 'resource', 'recommended-vendor' ), true );
	}

	/**
	 * Whether the entry carries the BCI Update secondary classification.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return bool
	 */
	public function is_bci_update( array $entry ) {
		return 'yes' === strtolower( trim( (string) $this->value( $entry, $this->config->field( 'bci_update' ) ) ) );
	}

	/**
	 * Gravity Forms creation timestamp normalized to UTC database format.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return string
	 */
	public function submitted_at( array $entry ) {
		$value = trim( (string) $this->value( $entry, 'date_created' ) );

		if ( '' === $value ) {
			return '';
		}

		try {
			$date = new \DateTimeImmutable( $value, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $exception ) {
			return '';
		}

		return $date->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Legacy newsletter type label.
	 */
	public function legacy_opportunity_type( $type ) {
		return $this->config->calendar_event_type_label( $type );
	}

	/**
	 * Modern type label from a legacy/form value.
	 */
	public function form_choice_from_legacy_type( $type ) {
		$type   = trim( (string) $type );
		$config = $this->config->opportunity_type_config( $type );

		if ( '' === $type ) {
			return '';
		}

		if ( ! empty( $config['name'] ) && $config['name'] !== $type ) {
			return (string) $config['name'];
		}

		if ( ! empty( $config['label'] ) && $config['label'] !== $type ) {
			return (string) $config['label'];
		}

		return '';
	}

	/**
	 * Organization name.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function organization( array $entry ) {
		return trim( (string) $this->value( $entry, $this->config->field( 'organization' ) ) );
	}

	/**
	 * Normalized opportunity type label.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function opportunity_type_label( array $entry ) {
		return $this->opportunity_type_label_from_value( $this->opportunity_type( $entry ) );
	}

	/**
	 * Normalized opportunity type slug.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function opportunity_type_slug( array $entry ) {
		return $this->opportunity_type_slug_from_value( $this->opportunity_type( $entry ) );
	}

	/**
	 * Normalized opportunity type label.
	 */
	public function opportunity_type_label_from_value( $raw_type ) {
		return $this->config->calendar_event_type_label( $raw_type );
	}

	/**
	 * Normalized opportunity type slug from a raw value.
	 */
	public function opportunity_type_slug_from_value( $raw_type ) {
		return $this->config->calendar_event_type_slug( $raw_type );
	}

	/**
	 * Primary workflow date.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function primary_date_value( array $entry ) {
		if ( $this->config->is_grant_opportunity_type( $this->opportunity_type( $entry ) ) ) {
			return trim( (string) $this->value( $entry, $this->config->field( 'grant_deadline' ) ) );
		}

		return trim( (string) $this->value( $entry, $this->config->field( 'start_date' ) ) );
	}

	/**
	 * Time range.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function time_range( array $entry ) {
		$start = trim( (string) $this->value( $entry, $this->config->field( 'start_time' ) ) );
		$end   = trim( (string) $this->value( $entry, $this->config->field( 'end_time' ) ) );

		if ( '' !== $start && '' !== $end ) {
			return $start . ' - ' . $end;
		}

		return '' !== $start ? $start : $end;
	}

	/**
	 * Location mode.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function location_mode( array $entry ) {
		return trim( (string) $this->value( $entry, $this->config->field( 'location_mode' ) ) );
	}

	/**
	 * Address.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function address( array $entry ) {
		$field = $this->config->field( 'address' );
		$parts = array_filter(
			array(
				trim( (string) $this->value( $entry, $field . '.1' ) ),
				trim( (string) $this->value( $entry, $field . '.2' ) ),
				trim( (string) $this->value( $entry, $field . '.3' ) ),
				trim( (string) $this->value( $entry, $field . '.4' ) ),
				trim( (string) $this->value( $entry, $field . '.5' ) ),
				trim( (string) $this->value( $entry, $field . '.6' ) ),
			)
		);

		return implode( ', ', $parts );
	}

	/**
	 * Uploaded file URLs as newline-separated text.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function file_upload( array $entry ) {
		$attachments = $this->attachments( $entry );
		$urls        = array();

		foreach ( $attachments as $attachment ) {
			if ( ! empty( $attachment['url'] ) ) {
				$urls[] = $attachment['url'];
			}
		}

		return implode( "\n", $urls );
	}

	/**
	 * Uploaded file payloads.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @return array<int,array{url:string,label:string}>
	 */
	public function attachments( array $entry ) {
		$value    = $this->value( $entry, $this->config->field( 'file_upload' ) );
		$raw_urls = array();

		if ( is_array( $value ) ) {
			$raw_urls = $value;
		} elseif ( is_string( $value ) ) {
			$trimmed = trim( $value );

			if ( '' === $trimmed ) {
				return array();
			}

			$decoded = json_decode( $trimmed, true );
			$raw_urls = is_array( $decoded ) ? $decoded : preg_split( '/\s*,\s*/', $trimmed );
		}

		$attachments = array();

		foreach ( is_array( $raw_urls ) ? $raw_urls : array() as $raw_url ) {
			$url = esc_url_raw( trim( (string) $raw_url ) );

			if ( '' === $url ) {
				continue;
			}

			$path     = (string) parse_url( $url, PHP_URL_PATH );
			$filename = basename( $path );

			$attachments[] = array(
				'url'   => $url,
				'label' => '' !== $filename ? rawurldecode( $filename ) : $url,
			);
		}

		return $attachments;
	}

	/**
	 * Opportunity description.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function description( array $entry ) {
		return trim( (string) $this->value( $entry, $this->config->field( 'description' ) ) );
	}

	/**
	 * More information URL.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function info_url( array $entry ) {
		return esc_url( trim( (string) $this->value( $entry, $this->config->field( 'info_url' ) ) ) );
	}

	/**
	 * Opportunity cost.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function cost( array $entry ) {
		return trim( (string) $this->value( $entry, $this->config->field( 'cost' ) ) );
	}

	/**
	 * End date.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 */
	public function end_date( array $entry ) {
		return trim( (string) $this->value( $entry, $this->config->field( 'end_date' ) ) );
	}

	/**
	 * Entry value helper.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param string             $key Entry key.
	 * @return mixed
	 */
	public function value( array $entry, $key ) {
		return isset( $entry[ $key ] ) ? $entry[ $key ] : '';
	}
}
