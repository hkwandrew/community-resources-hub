<?php
/**
 * GravityCalendar workflow filtering for BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Calendar;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filters the configured GravityCalendar feed to approved calendar entries.
 */
final class EventFilter {

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param Config $config Workflow config.
	 */
	public function __construct( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'gk/gravitycalendar/events/filters', array( $this, 'filter' ), 10, 4 );
	}

	/**
	 * Restrict the configured BCI feed to approved, time-sensitive entries.
	 *
	 * @param array      $filters Feed filters.
	 * @param int|string $feed_id Feed ID.
	 * @return array
	 */
	public function filter( array $filters, $feed_id ) {
		$approval_field_id = $this->config->approval_field_id();
		$time_sensitive_id = $this->config->field( 'time_sensitive' );

		if ( ! $this->is_bci_feed( $feed_id ) ) {
			return $filters;
		}

		if ( empty( $filters['conditions'] ) || ! is_array( $filters['conditions'] ) ) {
			$filters['conditions'] = array();
		}

		$required_conditions = array(
			array(
				'key'      => $approval_field_id,
				'operator' => 'is',
				'value'    => 'Approved',
			),
			array(
				'key'      => $time_sensitive_id,
				'operator' => 'is',
				'value'    => 'Yes',
			),
		);

		foreach ( $required_conditions as $required_condition ) {
			if ( '' === (string) $required_condition['key'] || $this->has_condition( $filters['conditions'], $required_condition ) ) {
				continue;
			}

			$filters['conditions'][] = $required_condition;
		}

		return $filters;
	}

	/**
	 * Whether a feed condition already exists.
	 *
	 * @param array<int,mixed>     $conditions Existing feed conditions.
	 * @param array<string,string> $required Required condition.
	 * @return bool
	 */
	private function has_condition( array $conditions, array $required ) {
		foreach ( $conditions as $condition ) {
			if (
				is_array( $condition )
				&& isset( $condition['key'], $condition['operator'], $condition['value'] )
				&& (string) $required['key'] === (string) $condition['key']
				&& (string) $required['operator'] === (string) $condition['operator']
				&& (string) $required['value'] === (string) $condition['value']
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the feed is the configured BCI calendar feed.
	 *
	 * @param int|string $feed_id Feed ID.
	 * @return bool
	 */
	private function is_bci_feed( $feed_id ) {
		if ( ! class_exists( 'GV_Extension_Calendar_Feed' ) ) {
			return false;
		}

		$feed = \GV_Extension_Calendar_Feed::get_instance()->get_feed( $feed_id );

		if ( empty( $feed ) ) {
			return false;
		}

		return $this->config->form_id() === (int) rgar( $feed, 'form_id' )
			&& $this->config->calendar_feed_name() === (string) rgars( $feed, 'meta/feedName' );
	}
}
