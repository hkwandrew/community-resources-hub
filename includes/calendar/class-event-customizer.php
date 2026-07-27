<?php
/**
 * GravityCalendar event enrichment for BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Calendar;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\FrontEnd\MemberDirectoryService;
use WatersMeet\CommunityResourcesHub\Workflow\FieldAccessor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds tooltip markup, colors, and type metadata to BCI calendar events.
 */
final class EventCustomizer {
	/**
	 * Public BCI Update badge color.
	 */
	const BCI_UPDATE_COLOR = '#004966';

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
		add_filter( 'gravityview/calendar/events', array( $this, 'customize' ), 10, 5 );
	}

	/**
	 * Customize calendar events for the configured BCI form.
	 *
	 * @param array $events Calendar events.
	 * @param array $form Gravity Forms form.
	 * @param array $feed GravityCalendar feed.
	 * @param array $field_map Field map.
	 * @param array $entries Source entries.
	 * @return array
	 */
	public function customize( array $events, array $form, array $feed, array $field_map, array $entries ) {
		if ( $this->config->form_id() !== (int) rgar( $form, 'id' ) ) {
			return $events;
		}

		$entries_by_id = array();

		foreach ( $entries as $entry ) {
			$entries_by_id[ (int) rgar( $entry, 'id' ) ] = $entry;
		}

		$fields                = new FieldAccessor( $this->config );
		$members               = $this->member_directory_service();
		$choice_map            = $this->opportunity_type_choices( $form );
		$supports_other_choice = $this->field_supports_other_choice( $this->opportunity_type_field( $form ) );

		foreach ( $events as $index => $event ) {
			$event_id = isset( $event['event_id'] ) ? (int) $event['event_id'] : 0;

			if ( ! $event_id || empty( $entries_by_id[ $event_id ] ) ) {
				continue;
			}

			$events[ $index ]['description'] = $this->tooltip_markup(
				$event,
				$entries_by_id[ $event_id ],
				$fields,
				$choice_map,
				$supports_other_choice
			);
			$events[ $index ]               = $this->apply_event_colors(
				$events[ $index ],
				$entries_by_id[ $event_id ],
				$fields,
				$choice_map,
				$supports_other_choice
			);
			$events[ $index ]               = $this->apply_type_metadata(
				$events[ $index ],
				$entries_by_id[ $event_id ],
				$fields,
				$members
			);
		}

		return $events;
	}

	/**
	 * Build calendar tooltip markup.
	 *
	 * @param array<string,mixed> $event Calendar event.
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param FieldAccessor       $fields Field accessor.
	 * @param array<string,string> $choice_map Opportunity type choices.
	 * @param bool                $supports_other_choice Whether the field supports "other".
	 * @return string
	 */
	private function tooltip_markup( array $event, array $entry, FieldAccessor $fields, array $choice_map, $supports_other_choice ) {
		$title       = trim( (string) rgar( $event, 'title' ) );
		$raw_type    = $fields->opportunity_type( $entry );
		$type        = $fields->opportunity_type_label_from_value( $raw_type );
		$eyebrow     = $this->tooltip_eyebrow( $type, $raw_type, $fields, $choice_map, $supports_other_choice );
		$date_label  = $this->date_label( $event );
		$date_name   = $this->config->is_grant_opportunity_type( $raw_type ) ? __( 'Deadline', 'community-resources-hub' ) : __( 'Date', 'community-resources-hub' );
		$time_label  = $fields->time_range( $entry );
		$location    = $fields->address( $entry );
		$org         = $fields->organization( $entry );
		$link        = esc_url_raw( trim( (string) rgar( $event, 'url' ) ) );
		$description = $this->tooltip_description( $fields->description( $entry ), '' !== $link );
		$meta_items  = array();

		if ( '' !== $type ) {
			$meta_items[] = '<li><strong>' . esc_html__( 'Type', 'community-resources-hub' ) . ':</strong> ' . esc_html( $type ) . '</li>';
		}
		if ( '' !== $date_label ) {
			$meta_items[] = '<li><strong>' . esc_html( $date_name ) . ':</strong> ' . esc_html( $date_label ) . '</li>';
		}
		if ( '' !== $time_label ) {
			$meta_items[] = '<li><strong>' . esc_html__( 'Time', 'community-resources-hub' ) . ':</strong> ' . esc_html( $time_label ) . '</li>';
		}
		if ( '' !== $org ) {
			$meta_items[] = '<li><strong>' . esc_html__( 'Organization', 'community-resources-hub' ) . ':</strong> ' . esc_html( $org ) . '</li>';
		}
		if ( '' !== $location ) {
			$meta_items[] = '<li><strong>' . esc_html__( 'Location', 'community-resources-hub' ) . ':</strong> ' . esc_html( $location ) . '</li>';
		}

		$html  = '<div class="wm-bci-calendar-tooltip">';
		$html .= '<div class="wm-bci-calendar-tooltip__header">';
		$html .= '<span class="wm-bci-calendar-tooltip__eyebrow">' . esc_html( $eyebrow ) . '</span>';
		if ( $fields->is_bci_update( $entry ) ) {
			$html .= '<span class="wm-bci-type-badge wm-bci-type-badge--bci-update wm-bci-type-badge--calendar-tooltip">' . esc_html__( 'BCI Update', 'community-resources-hub' ) . '</span>';
		}
		$html .= '<h3 class="wm-bci-calendar-tooltip__title">' . esc_html( $title ) . '</h3>';
		$html .= '</div>';

		if ( ! empty( $meta_items ) ) {
			$html .= '<ul class="wm-bci-calendar-tooltip__meta">' . implode( '', $meta_items ) . '</ul>';
		}

		if ( '' !== $description ) {
			$html .= '<div class="wm-bci-calendar-tooltip__body">' . wp_kses_post( wpautop( $description ) ) . '</div>';
		}

		if ( '' !== $link ) {
			$html .= '<p class="wm-bci-calendar-tooltip__footer"><a href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Visit Website', 'community-resources-hub' ) . '</a></p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Tooltip eyebrow label.
	 *
	 * @param string              $type Normalized label.
	 * @param string              $raw_type Raw type.
	 * @param FieldAccessor       $fields Field accessor.
	 * @param array<string,string> $choice_map Choice map.
	 * @param bool                $supports_other_choice Whether the field supports "other".
	 * @return string
	 */
	private function tooltip_eyebrow( $type, $raw_type, FieldAccessor $fields, array $choice_map, $supports_other_choice ) {
		if ( '' === $type || $this->is_other_choice_value( $raw_type, $fields, $choice_map, $supports_other_choice ) ) {
			return __( 'BCI Opportunity', 'community-resources-hub' );
		}

		return $type;
	}

	/**
	 * Apply configured event colors.
	 *
	 * @param array<string,mixed> $event Calendar event.
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param FieldAccessor       $fields Field accessor.
	 * @param array<string,string> $choice_map Choice map.
	 * @param bool                $supports_other_choice Whether the field supports "other".
	 * @return array<string,mixed>
	 */
	private function apply_event_colors( array $event, array $entry, FieldAccessor $fields, array $choice_map, $supports_other_choice ) {
		$color = $this->event_color( $entry, $fields, $choice_map, $supports_other_choice );

		if ( '' === $color ) {
			return $event;
		}

		$event['backgroundColor'] = $color;
		$event['borderColor']     = $color;
		$event['textColor']       = $this->text_color( $color );

		return $event;
	}

	/**
	 * Add normalized type metadata.
	 *
	 * @param array<string,mixed> $event Calendar event.
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param FieldAccessor          $fields Field accessor.
	 * @param MemberDirectoryService $members Member directory service.
	 * @return array<string,mixed>
	 */
	private function apply_type_metadata( array $event, array $entry, FieldAccessor $fields, MemberDirectoryService $members ) {
		$type_value      = $fields->opportunity_type( $entry );
		$type_label      = $fields->opportunity_type_label_from_value( $type_value );
		$type_slug       = $fields->opportunity_type_slug_from_value( $type_value );
		$organization    = $fields->organization( $entry );
		$member          = $members->match_organization( $organization );
		$member_identity = $members->opportunity_member_identity(
			$organization,
			(string) ( $member['slug'] ?? '' ),
			(string) ( $member['title'] ?? '' )
		);
		$is_bci_update   = $fields->is_bci_update( $entry );

		$event['extendedProps'] = isset( $event['extendedProps'] ) && is_array( $event['extendedProps'] )
			? $event['extendedProps']
			: array();

		$event['extendedProps']['wmBciTypeValue'] = $type_slug;
		$event['extendedProps']['wmBciTypeLabel'] = $type_label;
		$event['extendedProps']['wmBciTypeSlug']  = $type_slug;
		$event['extendedProps']['wmBciMemberSlug'] = (string) ( $member_identity['slug'] ?? '' );
		$event['extendedProps']['wmBciMemberLabel'] = (string) ( $member_identity['label'] ?? '' );
		$event['extendedProps']['wmBciIsBciUpdate'] = $is_bci_update;
		$event['extendedProps']['wmBciUpdateLabel'] = $is_bci_update ? __( 'BCI Update', 'community-resources-hub' ) : '';
		$event['extendedProps']['wmBciUpdateColor'] = $is_bci_update ? self::BCI_UPDATE_COLOR : '';

		$class_names = array();

		if ( isset( $event['classNames'] ) && is_array( $event['classNames'] ) ) {
			$class_names = $event['classNames'];
		} elseif ( isset( $event['className'] ) ) {
			$class_names = is_array( $event['className'] ) ? $event['className'] : array( (string) $event['className'] );
		}

		if ( '' !== $type_slug ) {
			$type_class = 'wm-bci-type-' . $type_slug;

			if ( ! in_array( $type_class, $class_names, true ) ) {
				$class_names[] = $type_class;
			}
		}

		if ( $is_bci_update && ! in_array( 'wm-bci-is-bci-update', $class_names, true ) ) {
			$class_names[] = 'wm-bci-is-bci-update';
		}

		$event['classNames'] = $class_names;

		return $event;
	}

	/**
	 * Load the shared member-filter identity owner for calendar events.
	 *
	 * @return MemberDirectoryService
	 */
	private function member_directory_service() {
		if ( ! class_exists( MemberDirectoryService::class ) ) {
			require_once (
				defined( 'COMMUNITY_RESOURCES_HUB_DIR' )
					? \COMMUNITY_RESOURCES_HUB_DIR . 'includes/frontend/class-member-directory-service.php'
					: dirname( __DIR__ ) . '/frontend/class-member-directory-service.php'
			);
		}

		return new MemberDirectoryService( $this->config );
	}

	/**
	 * Resolve the configured event color.
	 *
	 * @param array<string,mixed> $entry Gravity Forms entry.
	 * @param FieldAccessor       $fields Field accessor.
	 * @param array<string,string> $choice_map Choice map.
	 * @param bool                $supports_other_choice Whether the field supports "other".
	 * @return string
	 */
	private function event_color( array $entry, FieldAccessor $fields, array $choice_map, $supports_other_choice ) {
		$type = $fields->opportunity_type( $entry );

		if ( '' === $type ) {
			return '';
		}

		return $this->config->calendar_event_color( $type );
	}

	/**
	 * Whether the raw value is an "other" choice.
	 *
	 * @param string              $type Raw type value.
	 * @param FieldAccessor       $fields Field accessor.
	 * @param array<string,string> $choice_map Choice map.
	 * @param bool                $supports_other_choice Whether the field supports "other".
	 * @return bool
	 */
	private function is_other_choice_value( $type, FieldAccessor $fields, array $choice_map, $supports_other_choice ) {
		if ( '' === $type || ! $supports_other_choice || isset( $choice_map[ $type ] ) ) {
			return false;
		}

		return '' === $fields->form_choice_from_legacy_type( $type );
	}

	/**
	 * Map the configured opportunity type choices from the form.
	 *
	 * @param array<string,mixed> $form Gravity Forms form.
	 * @return array<string,string>
	 */
	private function opportunity_type_choices( array $form ) {
		$field = $this->opportunity_type_field( $form );

		if ( null === $field ) {
			return array();
		}

		$choices = array();

		if ( is_object( $field ) && isset( $field->choices ) && is_array( $field->choices ) ) {
			$choices = $field->choices;
		} elseif ( is_array( $field ) && isset( $field['choices'] ) && is_array( $field['choices'] ) ) {
			$choices = $field['choices'];
		}

		return $this->normalize_choice_map( $choices );
	}

	/**
	 * Locate the configured opportunity type field.
	 *
	 * @param array<string,mixed> $form Gravity Forms form.
	 * @return array|object|null
	 */
	private function opportunity_type_field( array $form ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return null;
		}

		$field_id = $this->config->field( 'opportunity_type' );

		foreach ( $form['fields'] as $field ) {
			$current_id = '';

			if ( is_object( $field ) && isset( $field->id ) ) {
				$current_id = (string) $field->id;
			} elseif ( is_array( $field ) && isset( $field['id'] ) ) {
				$current_id = (string) $field['id'];
			}

			if ( $field_id === $current_id ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Normalize GF choices to value => label.
	 *
	 * @param array<int,mixed> $choices Gravity Forms choices.
	 * @return array<string,string>
	 */
	private function normalize_choice_map( array $choices ) {
		$normalized = array();

		foreach ( $choices as $choice ) {
			$label           = '';
			$value           = '';
			$is_other_choice = false;

			if ( is_array( $choice ) ) {
				$is_other_choice = ! empty( $choice['isOtherChoice'] ) || 'gf_other_choice' === ( $choice['value'] ?? '' );
				$label           = trim( (string) ( $choice['text'] ?? '' ) );
				$value           = trim( (string) ( $choice['value'] ?? $label ) );
			} elseif ( is_object( $choice ) ) {
				$is_other_choice = ! empty( $choice->isOtherChoice ) || ( isset( $choice->value ) && 'gf_other_choice' === $choice->value );
				$label           = isset( $choice->text ) ? trim( (string) $choice->text ) : '';
				$value           = isset( $choice->value ) ? trim( (string) $choice->value ) : $label;
			}

			if ( $is_other_choice || '' === $label ) {
				continue;
			}

			$normalized[ '' !== $value ? $value : $label ] = $label;
		}

		return $normalized;
	}

	/**
	 * Whether the GF field enables the other-choice option.
	 *
	 * @param mixed $field Gravity Forms field.
	 * @return bool
	 */
	private function field_supports_other_choice( $field ) {
		if ( is_object( $field ) ) {
			return ! empty( $field->enableOtherChoice );
		}

		if ( is_array( $field ) ) {
			return ! empty( $field['enableOtherChoice'] );
		}

		return false;
	}

	/**
	 * Calculate readable text color for the configured background color.
	 *
	 * @param string $background_color Hex background color.
	 * @return string
	 */
	private function text_color( $background_color ) {
		$hex = ltrim( strtolower( (string) $background_color ), '#' );

		if ( 3 === strlen( $hex ) ) {
			$hex = sprintf( '%1$s%1$s%2$s%2$s%3$s%3$s', $hex[0], $hex[1], $hex[2] );
		}

		if ( 6 !== strlen( $hex ) ) {
			return '#ffffff';
		}

		$red       = hexdec( substr( $hex, 0, 2 ) );
		$green     = hexdec( substr( $hex, 2, 2 ) );
		$blue      = hexdec( substr( $hex, 4, 2 ) );
		$luminance = ( ( 0.299 * $red ) + ( 0.587 * $green ) + ( 0.114 * $blue ) ) / 255;

		return $luminance >= 0.6 ? '#1f1f1f' : '#ffffff';
	}

	/**
	 * Human-readable event date label.
	 *
	 * @param array<string,mixed> $event Calendar event.
	 * @return string
	 */
	private function date_label( array $event ) {
		$start = trim( (string) rgar( $event, 'start' ) );
		$end   = trim( (string) rgar( $event, 'end' ) );

		if ( '' === $start ) {
			return '';
		}

		$start_timestamp = strtotime( $start );
		$end_timestamp   = '' !== $end ? strtotime( $end ) : false;

		if ( false === $start_timestamp ) {
			return $start;
		}

		$start_label = wp_date( 'F j, Y', $start_timestamp );

		if ( false === $end_timestamp || gmdate( 'Y-m-d', $start_timestamp ) === gmdate( 'Y-m-d', $end_timestamp ) ) {
			return $start_label;
		}

		return sprintf( '%1$s to %2$s', $start_label, wp_date( 'F j, Y', $end_timestamp ) );
	}

	/**
	 * Normalize the tooltip description, trimming only when a details link exists.
	 *
	 * @param string $description Raw description.
	 * @param bool   $has_link Whether the tooltip links to full details.
	 * @return string
	 */
	private function tooltip_description( $description, $has_link ) {
		$description = (string) $description;

		if ( '' === $description ) {
			return '';
		}

		$description = wp_strip_all_tags( html_entity_decode( $description, ENT_QUOTES, 'UTF-8' ) );
		$description = preg_replace( '/\s+/', ' ', $description );
		$description = trim( (string) $description );
		$description = ltrim( $description, "\"'\xe2\x80\x9c\xe2\x80\x9d\xe2\x80\x98\xe2\x80\x99" );

		if ( '' === $description ) {
			return '';
		}

		if ( ! $has_link ) {
			return $description;
		}

		if ( strlen( $description ) <= 260 ) {
			return $description;
		}

		$excerpt = substr( $description, 0, 257 );
		$excerpt = preg_replace( '/\s+\S*$/', '', (string) $excerpt );

		return rtrim( (string) $excerpt, " ,.;:-\"'\xe2\x80\x9c\xe2\x80\x9d\xe2\x80\x98\xe2\x80\x99" ) . '...';
	}
}
