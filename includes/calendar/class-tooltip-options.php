<?php
/**
 * GravityCalendar tooltip and filter options for BCI opportunities.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Calendar;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Workflow\FieldAccessor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds BCI-specific FullCalendar options.
 */
final class TooltipOptions {

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
		add_filter( 'gravityview/calendar/options', array( $this, 'customize_calendar_options' ), 10, 3 );
		add_filter( 'gravityview/calendar/extra_options', array( $this, 'customize_tooltip_options' ), 10, 3 );
	}

	/**
	 * Customize FullCalendar options for the configured BCI form.
	 *
	 * @param array $calendar_options FullCalendar options.
	 * @param int   $form_id Gravity Forms form ID.
	 * @return array
	 */
	public function customize_calendar_options( array $calendar_options, $form_id ) {
		if ( $this->config->form_id() !== (int) $form_id ) {
			return $calendar_options;
		}

		$calendar_options['eventDisplay']   = 'block';
		$calendar_options['dayMaxEvents']   = 4;
		$calendar_options['eventOrder']     = '-duration,start,allDay,title';
		$calendar_options['moreLinkClick']  = 'popover';
		$calendar_options['fixedWeekCount'] = false;

		return $calendar_options;
	}

	/**
	 * Customize tooltip and calendar extra options for the configured BCI form.
	 *
	 * @param array $extra_options Extra GravityCalendar options.
	 * @param int   $form_id Gravity Forms form ID.
	 * @return array
	 */
	public function customize_tooltip_options( array $extra_options, $form_id ) {
		if ( $this->config->form_id() !== (int) $form_id ) {
			return $extra_options;
		}

		$tooltip_options = isset( $extra_options['tooltip_options'] ) && is_array( $extra_options['tooltip_options'] )
			? $extra_options['tooltip_options']
			: array();

		$tooltip_options = array_merge(
			$tooltip_options,
			array(
				'placement' => 'bottom-start',
				'maxWidth'  => 340,
				'offset'    => array( 0, 10 ),
			)
		);

		$tooltip_options['popperOptions'] = array_merge(
			isset( $tooltip_options['popperOptions'] ) && is_array( $tooltip_options['popperOptions'] )
				? $tooltip_options['popperOptions']
				: array(),
			array(
				'strategy'  => 'fixed',
				'modifiers' => array(
					array(
						'name'    => 'flip',
						'options' => array(
							'fallbackPlacements' => array( 'right-start', 'left-start', 'top-start' ),
						),
					),
					array(
						'name'    => 'preventOverflow',
						'options' => array(
							'altAxis' => true,
							'tether'  => false,
							'padding' => 16,
						),
					),
				),
			)
		);

		$extra_options['tooltip_options']       = $tooltip_options;
		$extra_options['wmBciDefaultTypeLabel'] = __( 'All BCI Events', 'community-resources-hub' );
		$extra_options['wmBciTypeChoices']      = $this->type_choices();

		return $extra_options;
	}

	/**
	 * Get normalized type choices for the configured opportunity-type field.
	 *
	 * @return array<int,array{value:string,label:string,slug:string}>
	 */
	private function type_choices() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return array();
		}

		$form = \GFAPI::get_form( $this->config->form_id() );

		if ( ! is_array( $form ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return array();
		}

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

		$fields     = new FieldAccessor( $this->config );
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

			$value = '' !== $value ? $value : $label;
			$label = $fields->opportunity_type_label_from_value( $value );
			$slug  = $fields->opportunity_type_slug_from_value( $value );

			if ( '' === $label || '' === $slug ) {
				continue;
			}

			$normalized[ $slug ] = array(
				'value' => $slug,
				'label' => $label,
				'slug'  => $slug,
			);
		}

		if ( $this->field_supports_other_choice( $field ) ) {
			$normalized['other'] = array(
				'value' => 'other',
				'label' => __( 'Other', 'community-resources-hub' ),
				'slug'  => 'other',
			);
		}

		return array_values( $normalized );
	}

	/**
	 * Locate the configured opportunity-type field.
	 *
	 * @param array<string,mixed> $form Gravity Forms form.
	 * @return array|object|null
	 */
	private function opportunity_type_field( array $form ) {
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
	 * Whether the GF field supports the other-choice option.
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
}
