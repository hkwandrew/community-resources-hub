<?php
/**
 * Plugin-owned BCI Gravity Forms and GravityCalendar provisioning.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Config;

use WatersMeet\CommunityResourcesHub\ContentModel\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates or adopts the Gravity Forms resources required by the BCI workflow.
 */
final class Provisioner {

	const ACTION                     = 'community_resources_hub_provision_bci';
	const FORM_TITLE                 = 'BCI Community Opportunity Submission';
	const CALENDAR_ADDON_SLUG        = 'gravityview-calendar';
	const RESULT_TRANSIENT           = 'community_resources_hub_provision_result';
	const FORM_CONTRACT_STATE_OPTION = 'community_resources_hub_form_contract_state';
	const FORM_CONTRACT_VERSION      = 'opportunity-contract-v3';
	const RECOMMENDED_VENDOR_VALUE   = 'Recommended Vendor';

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
	 * Register admin provisioning action.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_admin_post' ) );
	}

	/**
	 * Whether the current runtime can provision both required third-party resources.
	 *
	 * @return bool
	 */
	public static function can_provision_dependencies() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return false;
		}

		if ( class_exists( 'GV_Extension_Calendar_Feed' ) || defined( 'GV_CALENDAR_SLUG' ) ) {
			return true;
		}

		return function_exists( 'shortcode_exists' ) && shortcode_exists( 'gravitycalendar' );
	}

	/**
	 * Admin-post callback for the setup button.
	 *
	 * @return void
	 */
	public function handle_admin_post() {
		if ( ! current_user_can( SettingsSchema::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You are not allowed to provision BCI Hub resources.', 'community-resources-hub' ),
				esc_html__( 'BCI Hub provisioning denied.', 'community-resources-hub' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::ACTION );

		$result = $this->provision();
		$notice = array(
			'type'    => 'success',
			'message' => __( 'BCI Hub resources were provisioned.', 'community-resources-hub' ),
		);

		if ( is_wp_error( $result ) ) {
			$notice = array(
				'type'    => 'error',
				'message' => $result->get_error_message(),
			);
		}

		if ( function_exists( 'set_transient' ) ) {
			set_transient( self::RESULT_TRANSIENT, $notice, MINUTE_IN_SECONDS );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . SettingsSchema::OPTIONS_PAGE_SLUG ) );
		exit;
	}

	/**
	 * Create or adopt the configured BCI form and calendar feed.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function provision() {
		if ( ! class_exists( 'GFAPI' ) ) {
			return new \WP_Error(
				'community_resources_hub_missing_gravity_forms',
				__( 'Gravity Forms is required before BCI Hub resources can be provisioned.', 'community-resources-hub' )
			);
		}

		if ( ! self::can_provision_dependencies() ) {
			return new \WP_Error(
				'community_resources_hub_missing_gravitycalendar',
				__( 'GravityCalendar is required before BCI Hub resources can be provisioned.', 'community-resources-hub' )
			);
		}

		$form = $this->resolve_form();

		if ( is_wp_error( $form ) ) {
			return $form;
		}

		$feed = $this->resolve_calendar_feed( (int) $form['id'] );

		if ( is_wp_error( $feed ) ) {
			return $feed;
		}

		$feed_name = $this->feed_meta_value( $feed['feed'], 'feedName' );

		if ( '' === $feed_name ) {
			$feed_name = $this->default_setting( 'wm_bci_calendar_feed_name' );
		}

		$this->persist_resolved_settings(
			(int) $form['id'],
			(int) $feed['id'],
			$feed_name
		);

		if ( 'created' === $form['action'] ) {
			$this->persist_form_contract_state( (int) $form['id'] );
		}

		return array(
			'form_id'              => (int) $form['id'],
			'calendar_feed_id'     => (int) $feed['id'],
			'calendar_shortcode'   => $this->calendar_shortcode( (int) $feed['id'] ),
			'form_action'          => $form['action'],
			'calendar_feed_action' => $feed['action'],
		);
	}

	/**
	 * Retained compatibility shim for integrations that called the old init hook.
	 *
	 * Form-contract writes now happen only through reconcile_form_contract(),
	 * after the data migration has completed its preflight.
	 *
	 * @return true|\WP_Error
	 */
	public function maybe_reconcile_configured_form() {
		return true;
	}

	/**
	 * Prepare the v3 submission contract without writing to Gravity Forms.
	 *
	 * @param array<string,mixed> $form Gravity Forms form object.
	 * @return array{form:array<string,mixed>,updated:bool}|\WP_Error
	 */
	public function prepare_form_contract( array $form ) {
		$fields          = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : array();
		$map             = $this->contract_field_map();
		$seen_mapped_ids = array();

		foreach ( $map as $semantic_key => $mapped_id ) {
			if ( '' === $mapped_id ) {
				return new \WP_Error(
					'community_resources_hub_form_contract_missing_mapping',
					sprintf(
						/* translators: %s: semantic field name. */
						__( 'The BCI form contract has no mapped field ID for %s.', 'community-resources-hub' ),
						$semantic_key
					)
				);
			}

			if ( isset( $seen_mapped_ids[ $mapped_id ] ) ) {
				return new \WP_Error(
					'community_resources_hub_form_contract_field_map_collision',
					sprintf(
						/* translators: 1: field ID, 2: first semantic field, 3: second semantic field. */
						__( 'Gravity Forms field ID %1$s is mapped to both %2$s and %3$s.', 'community-resources-hub' ),
						$mapped_id,
						$seen_mapped_ids[ $mapped_id ],
						$semantic_key
					)
				);
			}

			$seen_mapped_ids[ $mapped_id ] = $semantic_key;
		}

		$fields_by_id = array();

		foreach ( $fields as $field ) {
			$field_id = trim( (string) $this->form_field_value( $field, 'id', '' ) );

			if ( '' === $field_id ) {
				continue;
			}

			if ( isset( $fields_by_id[ $field_id ] ) ) {
				return new \WP_Error(
					'community_resources_hub_form_contract_duplicate_field',
					sprintf(
						/* translators: %s: Gravity Forms field ID. */
						__( 'The configured BCI form has more than one field with ID %s.', 'community-resources-hub' ),
						$field_id
					)
				);
			}

			$fields_by_id[ $field_id ] = $field;
		}

		$contract_labels   = $this->contract_field_labels();
		$new_semantic_keys = array( 'time_sensitive', 'non_date_sensitive_type', 'bci_update' );

		foreach ( $map as $semantic_key => $mapped_id ) {
			if ( isset( $fields_by_id[ $mapped_id ] ) ) {
				if ( in_array( $semantic_key, $new_semantic_keys, true ) ) {
					$existing_label = trim( (string) $this->form_field_value( $fields_by_id[ $mapped_id ], 'label', '' ) );
					$existing_type  = trim( (string) $this->form_field_value( $fields_by_id[ $mapped_id ], 'type', '' ) );

					if ( $contract_labels[ $semantic_key ] !== $existing_label || 'radio' !== $existing_type ) {
						return new \WP_Error(
							'community_resources_hub_form_contract_new_field_collision',
							sprintf(
								/* translators: 1: Gravity Forms field ID, 2: existing field label, 3: existing field type. */
								__( 'Gravity Forms field ID %1$s is already used by "%2$s" (%3$s).', 'community-resources-hub' ),
								$mapped_id,
								$existing_label,
								$existing_type
							)
						);
					}
				}

				continue;
			}

			if ( ! in_array( $semantic_key, $new_semantic_keys, true ) ) {
				return new \WP_Error(
					'community_resources_hub_form_contract_missing_field',
					sprintf(
						/* translators: %s: semantic field name. */
						__( 'The configured BCI form is missing its mapped %s field.', 'community-resources-hub' ),
						$semantic_key
					)
				);
			}
		}

		$time_sensitive_id  = $map['time_sensitive'];
		$type_id            = $map['opportunity_type'];
		$non_date_type_id   = $map['non_date_sensitive_type'];
		$bci_update_id      = $map['bci_update'];
		$yes_no_choices     = $this->yes_no_choices();
		$date_type_values   = $this->opportunity_type_choice_values();
		$non_date_values    = $this->non_date_sensitive_type_choice_values();
		$common_logic       = $this->category_selected_logic( $type_id, $non_date_type_id );
		$dated_detail_logic = $this->field_conditional_logic( $type_id, array( 'Event', 'Workshop, training, or other learning', 'Other' ) );

		$owned_properties = array(
			$time_sensitive_id => array(
				'type'              => 'radio',
				'label'             => $contract_labels['time_sensitive'],
				'isRequired'        => true,
				'choices'           => $yes_no_choices,
				'enableOtherChoice' => false,
				'defaultValue'      => '',
				'conditionalLogic'  => null,
			),
			$type_id           => array(
				'type'              => 'radio',
				'label'             => $contract_labels['opportunity_type'],
				'isRequired'        => true,
				'choices'           => $this->choices_from_values( $date_type_values ),
				'enableOtherChoice' => false,
				'conditionalLogic'  => $this->field_conditional_logic( $time_sensitive_id, array( 'Yes' ), 'all' ),
			),
			$non_date_type_id  => array(
				'type'              => 'radio',
				'label'             => $contract_labels['non_date_sensitive_type'],
				'isRequired'        => true,
				'choices'           => $this->choices_from_values( $non_date_values ),
				'enableOtherChoice' => false,
				'conditionalLogic'  => $this->field_conditional_logic( $time_sensitive_id, array( 'No' ), 'all' ),
			),
			$bci_update_id     => array(
				'type'              => 'radio',
				'label'             => $contract_labels['bci_update'],
				'isRequired'        => true,
				'choices'           => $yes_no_choices,
				'enableOtherChoice' => false,
				'conditionalLogic'  => $common_logic,
			),
		);

		foreach ( array( 'submitter_name', 'organization', 'description', 'info_url', 'file_upload' ) as $semantic_key ) {
			$owned_properties[ $map[ $semantic_key ] ] = array( 'conditionalLogic' => $common_logic );
		}

		$owned_properties[ $map['start_date'] ]     = array(
			'isRequired'       => true,
			'conditionalLogic' => $dated_detail_logic,
		);
		$owned_properties[ $map['grant_deadline'] ] = array(
			'isRequired'       => true,
			'conditionalLogic' => $this->field_conditional_logic( $type_id, array( 'Grant / RFP' ), 'all' ),
		);

		foreach ( array( 'end_date', 'start_time', 'end_time', 'cost', 'location_mode' ) as $semantic_key ) {
			$owned_properties[ $map[ $semantic_key ] ] = array( 'conditionalLogic' => $dated_detail_logic );
		}

		if ( isset( $fields_by_id['20'] ) ) {
			$owned_properties['20'] = array( 'conditionalLogic' => $dated_detail_logic );
		}

		if ( isset( $fields_by_id['23'] ) ) {
			$owned_properties['23'] = array(
				'conditionalLogic' => $this->field_conditional_logic( $type_id, $date_type_values ),
			);
		}

		foreach ( $new_semantic_keys as $semantic_key ) {
			$mapped_id = $map[ $semantic_key ];

			if ( isset( $fields_by_id[ $mapped_id ] ) ) {
				continue;
			}

			$fields_by_id[ $mapped_id ] = $this->field( (int) $mapped_id, 'radio', $contract_labels[ $semantic_key ] );
		}

		foreach ( $owned_properties as $field_id => $properties ) {
			$field = $fields_by_id[ (string) $field_id ];

			foreach ( $properties as $property => $value ) {
				$field = $this->form_field_with_value( $field, $property, $value );
			}

			$fields_by_id[ (string) $field_id ] = $field;
		}

		$ordered_fields = array();
		$leading_ids    = array( $map['title'], $time_sensitive_id, $type_id, $non_date_type_id, $bci_update_id );

		foreach ( $leading_ids as $field_id ) {
			$ordered_fields[] = $fields_by_id[ $field_id ];
			unset( $fields_by_id[ $field_id ] );
		}

		foreach ( $fields as $field ) {
			$field_id = trim( (string) $this->form_field_value( $field, 'id', '' ) );

			if ( ! isset( $fields_by_id[ $field_id ] ) ) {
				continue;
			}

			$ordered_fields[] = $fields_by_id[ $field_id ];
			unset( $fields_by_id[ $field_id ] );
		}

		foreach ( $fields_by_id as $field ) {
			$ordered_fields[] = $field;
		}

		$updated_form                = $form;
		$updated_form['fields']      = $ordered_fields;
		$updated_form['nextFieldId'] = max( 27, (int) ( $form['nextFieldId'] ?? 0 ), $this->next_field_id( $ordered_fields ) );
		$updated_form['button']      = $this->form_field_with_value(
			$form['button'] ?? array(),
			'text',
			__( 'Share this', 'community-resources-hub' )
		);

		return array(
			'form'    => $updated_form,
			'updated' => serialize( $updated_form ) !== serialize( $form ),
		);
	}

	/**
	 * Explicitly apply the prepared v3 contract to Gravity Forms.
	 *
	 * @param array<string,mixed> $form Gravity Forms form object.
	 * @return array{form:array<string,mixed>,updated:bool}|\WP_Error
	 */
	public function reconcile_form_contract( array $form ) {
		if ( ! method_exists( 'GFAPI', 'update_form' ) ) {
			return new \WP_Error(
				'community_resources_hub_form_contract_update_unavailable',
				__( 'Gravity Forms cannot update the configured BCI form in this runtime.', 'community-resources-hub' )
			);
		}

		$prepared = $this->prepare_form_contract( $form );

		if ( is_wp_error( $prepared ) || ! $prepared['updated'] ) {
			if ( ! is_wp_error( $prepared ) && ! empty( $form['id'] ) ) {
				$this->persist_form_contract_state( (int) $form['id'] );
			}

			return $prepared;
		}

		$result = \GFAPI::update_form( $prepared['form'] );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( true !== $result ) {
			return new \WP_Error(
				'community_resources_hub_form_contract_update_failed',
				__( 'Gravity Forms did not confirm the BCI form update.', 'community-resources-hub' )
			);
		}

		if ( ! empty( $prepared['form']['id'] ) ) {
			$this->persist_form_contract_state( (int) $prepared['form']['id'] );
		}

		return $prepared;
	}

	/**
	 * Mapped form fields that participate in the v3 contract.
	 *
	 * @return array<string,string>
	 */
	private function contract_field_map() {
		return array_map(
			static function ( $field_id ) {
				return trim( (string) $field_id );
			},
			$this->resolved_field_map()
		);
	}

	/**
	 * Form-contract completion marker for the current form and mapping.
	 *
	 * @param int $form_id Gravity Forms form ID.
	 * @return array{version:string,form_id:int,field_map_hash:string}
	 */
	private function form_contract_state( $form_id ) {
		return array(
			'version'        => self::FORM_CONTRACT_VERSION,
			'form_id'        => absint( $form_id ),
			'field_map_hash' => sha1( serialize( $this->contract_field_map() ) ),
		);
	}

	/**
	 * Store the current form-contract completion marker without autoloading it.
	 *
	 * @param int $form_id Gravity Forms form ID.
	 * @return void
	 */
	private function persist_form_contract_state( $form_id ) {
		$state = $this->form_contract_state( $form_id );

		if ( null === get_option( self::FORM_CONTRACT_STATE_OPTION, null ) ) {
			add_option( self::FORM_CONTRACT_STATE_OPTION, $state, '', false );
			return;
		}

		update_option( self::FORM_CONTRACT_STATE_OPTION, $state, false );
	}

	/**
	 * Read an array/object Gravity Forms property.
	 *
	 * @param mixed  $object Candidate field or choice.
	 * @param string $property Property name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	private function form_field_value( $object, $property, $default = null ) {
		if ( is_object( $object ) ) {
			return isset( $object->{$property} ) ? $object->{$property} : $default;
		}

		return is_array( $object ) && array_key_exists( $property, $object ) ? $object[ $property ] : $default;
	}

	/**
	 * Return a field/choice copy with one property changed.
	 *
	 * @param mixed  $object Candidate field or choice.
	 * @param string $property Property name.
	 * @param mixed  $value Property value.
	 * @return array<string,mixed>|object
	 */
	private function form_field_with_value( $object, $property, $value ) {
		if ( is_object( $object ) ) {
			$copy              = clone $object;
			$copy->{$property} = $value;
			return $copy;
		}

		$copy              = is_array( $object ) ? $object : array();
		$copy[ $property ] = $value;
		return $copy;
	}

	/**
	 * Resolve the configured, existing, or newly created Gravity Form.
	 *
	 * @return array{id:int,action:string,form:array<string,mixed>}|\WP_Error
	 */
	private function resolve_form() {
		$configured_form_id = $this->config->form_id();

		if ( $configured_form_id ) {
			$form = \GFAPI::get_form( $configured_form_id );

			if ( $this->is_form( $form ) ) {
				return array(
					'id'     => (int) $form['id'],
					'action' => 'configured',
					'form'   => $form,
				);
			}
		}

		$existing_form = $this->find_form_by_title( self::FORM_TITLE );

		if ( $existing_form ) {
			return array(
				'id'     => (int) $existing_form['id'],
				'action' => 'adopted',
				'form'   => $existing_form,
			);
		}

		$form_id = \GFAPI::add_form( $this->form_definition() );

		if ( is_wp_error( $form_id ) ) {
			return $form_id;
		}

		$form_id = absint( $form_id );

		if ( ! $form_id ) {
			return new \WP_Error(
				'community_resources_hub_form_create_failed',
				__( 'BCI Hub could not create the required Gravity Form.', 'community-resources-hub' )
			);
		}

		$form = \GFAPI::get_form( $form_id );

		if ( ! $this->is_form( $form ) ) {
			$form       = $this->form_definition();
			$form['id'] = $form_id;
		}

		return array(
			'id'     => $form_id,
			'action' => 'created',
			'form'   => $form,
		);
	}

	/**
	 * Resolve the configured, existing, or newly created GravityCalendar feed.
	 *
	 * @param int $form_id Gravity Forms form ID.
	 * @return array{id:int,action:string,feed:array<string,mixed>}|\WP_Error
	 */
	private function resolve_calendar_feed( $form_id ) {
		$configured_feed_id = $this->config->calendar_feed_id();

		if ( $configured_feed_id ) {
			$feed = \GFAPI::get_feed( $configured_feed_id );

			if ( $this->is_calendar_feed_for_form( $feed, $form_id ) ) {
				return $this->prepare_feed_result( $feed, $form_id, 'configured' );
			}
		}

		$shortcode_feed_id = $this->feed_id_from_shortcode( $this->config->calendar_shortcode() );

		if ( $shortcode_feed_id ) {
			$feed = \GFAPI::get_feed( $shortcode_feed_id );

			if ( $this->is_calendar_feed_for_form( $feed, $form_id ) ) {
				return $this->prepare_feed_result( $feed, $form_id, 'configured' );
			}
		}

		$existing_feed = $this->find_calendar_feed_by_name( $form_id, $this->resolved_calendar_feed_name() );

		if ( $existing_feed ) {
			return $this->prepare_feed_result( $existing_feed, $form_id, 'adopted' );
		}

		$feed_id = \GFAPI::add_feed(
			$form_id,
			$this->calendar_feed_meta(),
			self::CALENDAR_ADDON_SLUG
		);

		if ( is_wp_error( $feed_id ) ) {
			return $feed_id;
		}

		$feed_id = absint( $feed_id );

		if ( ! $feed_id ) {
			return new \WP_Error(
				'community_resources_hub_feed_create_failed',
				__( 'BCI Hub could not create the required GravityCalendar feed.', 'community-resources-hub' )
			);
		}

		$feed = \GFAPI::get_feed( $feed_id );

		if ( ! $this->is_calendar_feed_for_form( $feed, $form_id ) ) {
			$feed = array(
				'id'         => $feed_id,
				'form_id'    => $form_id,
				'addon_slug' => self::CALENDAR_ADDON_SLUG,
				'is_active'  => 1,
				'meta'       => $this->calendar_feed_meta(),
			);
		}

		return $this->prepare_feed_result( $feed, $form_id, 'created' );
	}

	/**
	 * Ensure a feed has the required BCI meta and active flag.
	 *
	 * @param array<string,mixed> $feed    Gravity Forms feed row.
	 * @param int                 $form_id Gravity Forms form ID.
	 * @param string              $action  Resolution action.
	 * @return array{id:int,action:string,feed:array<string,mixed>}|\WP_Error
	 */
	private function prepare_feed_result( array $feed, $form_id, $action ) {
		$feed_id = absint( $feed['id'] ?? 0 );
		$meta    = isset( $feed['meta'] ) && is_array( $feed['meta'] ) ? $feed['meta'] : array();
		$merged  = $this->merge_calendar_feed_meta( $meta );

		if ( method_exists( 'GFAPI', 'update_feed_property' ) ) {
			$active = \GFAPI::update_feed_property( $feed_id, 'is_active', 1 );

			if ( is_wp_error( $active ) ) {
				return $active;
			}
		}

		if ( $merged !== $meta ) {
			$updated = \GFAPI::update_feed( $feed_id, $merged, $form_id );

			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		$feed['form_id']   = $form_id;
		$feed['is_active'] = 1;
		$feed['meta']      = $merged;

		return array(
			'id'     => $feed_id,
			'action' => $action,
			'feed'   => $feed,
		);
	}

	/**
	 * Persist the resolved setup values through the plugin-owned setting names.
	 *
	 * @param int    $form_id  Gravity Forms form ID.
	 * @param int    $feed_id  GravityCalendar feed ID.
	 * @param string $feed_name GravityCalendar feed name.
	 * @return void
	 */
	private function persist_resolved_settings( $form_id, $feed_id, $feed_name ) {
		$this->upsert_setting( 'wm_bci_form_id', $form_id, true );
		$this->upsert_setting( 'wm_bci_calendar_feed_id', $feed_id, true );
		$this->upsert_setting( 'wm_bci_calendar_feed_name', $feed_name, true );
		$this->upsert_setting( 'wm_bci_calendar_shortcode', $this->calendar_shortcode( $feed_id ), true );

		foreach (
			array(
				'wm_bci_approval_field_id',
				'wm_bci_notification_name',
				'wm_bci_calendar_page_slug',
			) as $field_name
		) {
			$this->upsert_setting( $field_name, $this->default_setting( $field_name ), false );
		}

		foreach ( SettingsSchema::field_map_defaults() as $key => $field_id ) {
			$this->upsert_setting( 'wm_bci_field_map_' . $key, $field_id, false );
		}
	}

	/**
	 * Add or update one plugin-owned setting.
	 *
	 * @param string $field_name Setting field name.
	 * @param mixed  $value      Raw setting value.
	 * @param bool   $force      Whether to overwrite an existing non-empty value.
	 * @return void
	 */
	private function upsert_setting( $field_name, $value, $force ) {
		$option_name = SettingsSchema::option_name( $field_name );
		$existing    = get_option( $option_name, null );

		if ( ! $force && ! $this->is_empty_setting( $existing ) ) {
			return;
		}

		$value = SettingsSchema::sanitize_value( $field_name, $value );

		if ( null === $existing ) {
			add_option( $option_name, $value, '', false );
			return;
		}

		update_option( $option_name, $value, false );
	}

	/**
	 * Source-owned form definition for a new BCI submission form.
	 *
	 * @return array<string,mixed>
	 */
	private function form_definition() {
		return array(
			'title'          => self::FORM_TITLE,
			'description'    => __( 'BCI community opportunity submission workflow.', 'community-resources-hub' ),
			'nextFieldId'    => 27,
			'labelPlacement' => 'top_label',
			'button'         => array(
				'type' => 'text',
				'text' => __( 'Share this', 'community-resources-hub' ),
			),
			'fields'         => array(
				$this->field(
					4,
					'text',
					__( 'What is the title of your community opportunity?', 'community-resources-hub' ),
					array( 'isRequired' => true )
				),
				$this->field(
					24,
					'radio',
					__( 'Is this a time-sensitive entry?', 'community-resources-hub' ),
					array(
						'isRequired'        => true,
						'choices'           => $this->yes_no_choices(),
						'enableOtherChoice' => false,
						'defaultValue'      => '',
					)
				),
				$this->field(
					1,
					'radio',
					__( 'What type of time-sensitive entry is this?', 'community-resources-hub' ),
					array(
						'isRequired'        => true,
						'choices'           => $this->opportunity_type_choices(),
						'enableOtherChoice' => false,
						'conditionalLogic'  => $this->field_conditional_logic( 24, array( 'Yes' ), 'all' ),
					)
				),
				$this->field(
					25,
					'radio',
					__( 'What type of non-date-sensitive entry is this?', 'community-resources-hub' ),
					array(
						'isRequired'        => true,
						'choices'           => $this->choices_from_values( $this->non_date_sensitive_type_choice_values() ),
						'enableOtherChoice' => false,
						'conditionalLogic'  => $this->field_conditional_logic( 24, array( 'No' ), 'all' ),
					)
				),
				$this->field(
					26,
					'radio',
					__( 'Is this a BCI Update?', 'community-resources-hub' ),
					array(
						'isRequired'        => true,
						'choices'           => $this->yes_no_choices(),
						'enableOtherChoice' => false,
						'conditionalLogic'  => $this->category_selected_logic( 1, 25 ),
					)
				),
				$this->field(
					3,
					'name',
					__( 'Your name:', 'community-resources-hub' ),
					array(
						'isRequired'       => false,
						'conditionalLogic' => $this->category_selected_logic( 1, 25 ),
						'inputs'           => array(
							array(
								'id'    => '3.3',
								'label' => __( 'First', 'community-resources-hub' ),
							),
							array(
								'id'    => '3.6',
								'label' => __( 'Last', 'community-resources-hub' ),
							),
						),
					)
				),
				$this->field(
					5,
					'text',
					__( 'What is the name of your organization?', 'community-resources-hub' ),
					array(
						'isRequired'       => true,
						'conditionalLogic' => $this->category_selected_logic( 1, 25 ),
					)
				),
				$this->date_field(
					6,
					__( 'What is the date of your event, learning opportunity, or other time-sensitive entry?', 'community-resources-hub' ),
					$this->opportunity_type_conditional_logic( array( 'Event', 'Workshop, training, or other learning', 'Other' ) ),
					array( 'isRequired' => true )
				),
				$this->date_field(
					9,
					__( 'What is the deadline for your grant or RFP opportunity?', 'community-resources-hub' ),
					$this->opportunity_type_conditional_logic( array( 'Grant / RFP' ) ),
					array( 'isRequired' => true )
				),
				$this->date_field(
					10,
					__( 'If your event or learning opportunity has a date range, what is the end date?', 'community-resources-hub' ),
					$this->opportunity_type_conditional_logic( array( 'Event', 'Workshop, training, or other learning', 'Other' ) )
				),
				$this->field(
					20,
					'text',
					__( 'If your event or learning opportunity is recurring, please provide the sequence of dates here:', 'community-resources-hub' ),
					array(
						'conditionalLogic' => $this->opportunity_type_conditional_logic( array( 'Event', 'Workshop, training, or other learning', 'Other' ) ),
					)
				),
				$this->time_field(
					12,
					__( 'What time does your event or learning opportunity start?', 'community-resources-hub' ),
					$this->opportunity_type_conditional_logic( array( 'Event', 'Workshop, training, or other learning', 'Other' ) )
				),
				$this->time_field(
					21,
					__( 'What time does your event or learning opportunity end?', 'community-resources-hub' ),
					$this->opportunity_type_conditional_logic( array( 'Event', 'Workshop, training, or other learning', 'Other' ) )
				),
				$this->field(
					14,
					'text',
					__( 'Is there any cost for your opportunity?', 'community-resources-hub' ),
					array(
						'isRequired'       => true,
						'conditionalLogic' => $this->opportunity_type_conditional_logic( array( 'Event', 'Workshop, training, or other learning', 'Other' ) ),
					)
				),
				$this->field(
					16,
					'radio',
					__( 'Is your event or learning opportunity virtual or in-person?', 'community-resources-hub' ),
					array(
						'conditionalLogic' => $this->opportunity_type_conditional_logic( array( 'Event', 'Workshop, training, or other learning', 'Other' ) ),
						'choices'          => array(
							array(
								'text'  => __( 'Virtual', 'community-resources-hub' ),
								'value' => 'Virtual',
							),
							array(
								'text'  => __( 'In-person', 'community-resources-hub' ),
								'value' => 'In-person',
							),
							array(
								'text'  => __( 'Hybrid', 'community-resources-hub' ),
								'value' => 'Hybrid',
							),
						),
					)
				),
				$this->address_field(),
				$this->field(
					17,
					'textarea',
					__( 'Provide a short description of this opportunity:', 'community-resources-hub' ),
					array(
						'isRequired'       => true,
						'conditionalLogic' => $this->category_selected_logic( 1, 25 ),
					)
				),
				$this->field(
					18,
					'website',
					__( 'Provide a link for additional information:', 'community-resources-hub' ),
					array(
						'conditionalLogic' => $this->category_selected_logic( 1, 25 ),
					)
				),
				$this->field(
					19,
					'fileupload',
					__( 'Please attach any relevant files here:', 'community-resources-hub' ),
					array(
						'multipleFiles'     => false,
						'maxFiles'          => '',
						'allowedExtensions' => '',
						'conditionalLogic'  => $this->category_selected_logic( 1, 25 ),
					)
				),
				$this->field(
					22,
					'hidden',
					__( 'Approval Status', 'community-resources-hub' ),
					array(
						'defaultValue' => 'Pending',
					)
				),
			),
			'notifications'  => array(
				'bci_admin_notification' => array(
					'id'       => 'bci_admin_notification',
					'name'     => $this->default_setting( 'wm_bci_notification_name' ),
					'event'    => 'form_submission',
					'toType'   => 'email',
					'to'       => '{admin_email}',
					'subject'  => __( 'New BCI opportunity submission', 'community-resources-hub' ),
					'message'  => '{all_fields}',
					'isActive' => true,
				),
			),
			'confirmations'  => array(
				'default_confirmation' => array(
					'id'        => 'default_confirmation',
					'name'      => __( 'Default Confirmation', 'community-resources-hub' ),
					'isDefault' => true,
					'type'      => 'message',
					'message'   => __( 'Thank you. Your BCI opportunity submission has been received for review.', 'community-resources-hub' ),
				),
			),
		);
	}

	/**
	 * Base Gravity Forms field metadata.
	 *
	 * @param int                 $id    Field ID.
	 * @param string              $type  Field type.
	 * @param string              $label Field label.
	 * @param array<string,mixed> $extra Extra field args.
	 * @return array<string,mixed>
	 */
	private function field( $id, $type, $label, array $extra = array() ) {
		return array_merge(
			array(
				'id'          => (int) $id,
				'type'        => (string) $type,
				'label'       => (string) $label,
				'adminLabel'  => '',
				'isRequired'  => false,
				'visibility'  => 'visible',
				'description' => '',
			),
			$extra
		);
	}

	/**
	 * Date field metadata.
	 *
	 * @param int    $id    Field ID.
	 * @param string $label Field label.
	 * @param array<string,mixed> $conditional_logic Conditional logic.
	 * @param array<string,mixed> $extra             Extra field args.
	 * @return array<string,mixed>
	 */
	private function date_field( $id, $label, array $conditional_logic = array(), array $extra = array() ) {
		return $this->field(
			$id,
			'date',
			$label,
			array_merge(
				array(
					'dateType'         => 'datepicker',
					'dateFormat'       => 'ymd',
					'calendarIconType' => 'calendar',
				),
				$conditional_logic ? array( 'conditionalLogic' => $conditional_logic ) : array(),
				$extra
			)
		);
	}

	/**
	 * Time field metadata.
	 *
	 * @param int    $id    Field ID.
	 * @param string $label Field label.
	 * @return array<string,mixed>
	 */
	private function time_field( $id, $label, array $conditional_logic = array() ) {
		return $this->field(
			$id,
			'time',
			$label,
			array_merge(
				array(
					'timeFormat' => '12',
				),
				$conditional_logic ? array( 'conditionalLogic' => $conditional_logic ) : array()
			)
		);
	}

	/**
	 * Address field metadata.
	 *
	 * @return array<string,mixed>
	 */
	private function address_field() {
		return $this->field(
			15,
			'address',
			__( 'Please provide an address for in-person events', 'community-resources-hub' ),
			array(
				'conditionalLogic' => $this->field_conditional_logic( 16, array( 'In-person' ), 'all' ),
				'inputs'           => array(
					array(
						'id'    => '15.1',
						'label' => __( 'Street Address', 'community-resources-hub' ),
					),
					array(
						'id'    => '15.2',
						'label' => __( 'Address Line 2', 'community-resources-hub' ),
					),
					array(
						'id'    => '15.3',
						'label' => __( 'City', 'community-resources-hub' ),
					),
					array(
						'id'    => '15.4',
						'label' => __( 'State / Province', 'community-resources-hub' ),
					),
					array(
						'id'    => '15.5',
						'label' => __( 'ZIP / Postal Code', 'community-resources-hub' ),
					),
					array(
						'id'    => '15.6',
						'label' => __( 'Country', 'community-resources-hub' ),
					),
				),
			)
		);
	}

	/**
	 * Source-owned opportunity type choices.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function opportunity_type_choices() {
		return $this->choices_from_values( $this->opportunity_type_choice_values() );
	}

	/**
	 * User-facing opportunity type values in the same order as the submit form.
	 *
	 * @return array<int,string>
	 */
	private function opportunity_type_choice_values() {
		return array(
			'Grant / RFP',
			'Event',
			'Workshop, training, or other learning',
			'Other',
		);
	}

	/**
	 * User-facing non-date-sensitive values in submit-form order.
	 *
	 * @return array<int,string>
	 */
	private function non_date_sensitive_type_choice_values() {
		return array( 'Resource', self::RECOMMENDED_VENDOR_VALUE );
	}

	/**
	 * Convert canonical values into Gravity Forms choices.
	 *
	 * @param array<int,string> $values Choice values.
	 * @return array<int,array{text:string,value:string}>
	 */
	private function choices_from_values( array $values ) {
		return array_map(
			static function ( $value ) {
				return array(
					'text'  => $value,
					'value' => $value,
				);
			},
			$values
		);
	}

	/**
	 * Required Yes/No choices with no selected default.
	 *
	 * @return array<int,array{text:string,value:string}>
	 */
	private function yes_no_choices() {
		return $this->choices_from_values( array( 'Yes', 'No' ) );
	}

	/**
	 * Exact labels for the branch and BCI Update contract fields.
	 *
	 * @return array<string,string>
	 */
	private function contract_field_labels() {
		return array(
			'time_sensitive'          => __( 'Is this a time-sensitive entry?', 'community-resources-hub' ),
			'opportunity_type'        => __( 'What type of time-sensitive entry is this?', 'community-resources-hub' ),
			'non_date_sensitive_type' => __( 'What type of non-date-sensitive entry is this?', 'community-resources-hub' ),
			'bci_update'              => __( 'Is this a BCI Update?', 'community-resources-hub' ),
		);
	}

	/**
	 * Conditional logic shown after either category branch has a selection.
	 *
	 * @param string $date_type_id     Date-sensitive type field ID.
	 * @param string $non_date_type_id Non-date-sensitive type field ID.
	 * @return array<string,mixed>
	 */
	private function category_selected_logic( $date_type_id, $non_date_type_id ) {
		$rules = array();

		foreach ( $this->opportunity_type_choice_values() as $value ) {
			$rules[] = array(
				'fieldId'  => (string) $date_type_id,
				'operator' => 'is',
				'value'    => $value,
			);
		}

		foreach ( $this->non_date_sensitive_type_choice_values() as $value ) {
			$rules[] = array(
				'fieldId'  => (string) $non_date_type_id,
				'operator' => 'is',
				'value'    => $value,
			);
		}

		return array(
			'enabled'    => true,
			'actionType' => 'show',
			'logicType'  => 'any',
			'rules'      => $rules,
		);
	}

	/**
	 * Next available integer field ID.
	 *
	 * @param array<int,mixed> $fields Gravity Forms fields.
	 * @return int
	 */
	private function next_field_id( array $fields ) {
		$highest = 0;

		foreach ( $fields as $field ) {
			$field_id = (string) $this->form_field_value( $field, 'id', '' );

			if ( 1 === preg_match( '/^\d+$/', $field_id ) ) {
				$highest = max( $highest, (int) $field_id );
			}
		}

		return $highest + 1;
	}

	/**
	 * Conditional logic for opportunity type dependent fields.
	 *
	 * @param array<int,string> $values Opportunity type values.
	 * @param string            $logic_type Gravity Forms rule combiner.
	 * @return array<string,mixed>
	 */
	private function opportunity_type_conditional_logic( array $values, $logic_type = 'any' ) {
		return $this->field_conditional_logic( 1, $values, $logic_type );
	}

	/**
	 * Conditional logic helper.
	 *
	 * @param int               $field_id Dependent field ID.
	 * @param array<int,string> $values Match values.
	 * @param string            $logic_type Gravity Forms rule combiner.
	 * @return array<string,mixed>
	 */
	private function field_conditional_logic( $field_id, array $values, $logic_type = 'any' ) {
		$rules = array();

		foreach ( $values as $value ) {
			$rules[] = array(
				'fieldId'  => (string) $field_id,
				'operator' => 'is',
				'value'    => (string) $value,
			);
		}

		return array(
			'enabled'    => true,
			'actionType' => 'show',
			'logicType'  => $logic_type,
			'rules'      => $rules,
		);
	}

	/**
	 * Default GravityCalendar feed meta.
	 *
	 * @return array<string,mixed>
	 */
	private function calendar_feed_meta() {
		$field_map = $this->resolved_field_map();

		return array(
			'feedName'                   => $this->resolved_calendar_feed_name(),
			'is_secure'                  => false,
			'startdate'                  => $field_map['start_date'],
			'enddate'                    => $field_map['end_date'],
			'starttime'                  => $field_map['start_time'],
			'endtime'                    => $field_map['end_time'],
			'eventtitle'                 => $this->merge_tag( 'Opportunity Title', $field_map['title'] ),
			'eventdescription'           => $this->merge_tag( 'Description', $field_map['description'] ),
			'eventlocation'              => $this->merge_tag( 'Address', $field_map['address'] ),
			'eventurltype'               => 'field_select',
			'eventurl'                   => $field_map['info_url'],
			'eventcolor'                 => '#4286f5',
			'layout'                     => 'dayGridMonth',
			'sizing'                     => 'auto',
			'controls'                   => $this->default_calendar_controls(),
			'control_button_label_style' => 'short',
			'navigateToEvents'           => 'current',
			'localization'               => 'en',
			'dynamicEventsLoading'       => false,
			'iCalUrl'                    => '',
			'iCalEventColor'             => '#4286f5',
			'conditional_logic'          => null,
		);
	}

	/**
	 * Fill missing required feed meta without overwriting existing non-empty values.
	 *
	 * @param array<string,mixed> $meta Existing feed meta.
	 * @return array<string,mixed>
	 */
	private function merge_calendar_feed_meta( array $meta ) {
		$defaults = $this->calendar_feed_meta();

		foreach ( $defaults as $key => $value ) {
			if ( ! array_key_exists( $key, $meta ) || $this->is_empty_setting( $meta[ $key ] ) ) {
				$meta[ $key ] = $value;
			}
		}

		if ( ! isset( $meta['controls'] ) || ! is_array( $meta['controls'] ) ) {
			$meta['controls'] = $this->default_calendar_controls();
		} else {
			$meta['controls'] = array_replace_recursive( $this->default_calendar_controls(), $meta['controls'] );
		}

		return $meta;
	}

	/**
	 * GravityCalendar controls expected by the feed renderer.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function default_calendar_controls() {
		return array(
			'controls-top-left'      => array( 'prev', 'next', 'space', 'today' ),
			'controls-top-center'    => array( 'title' ),
			'controls-top-right'     => array( 'dayGridMonth', 'timeGridWeek', 'timeGridDay' ),
			'controls-bottom-left'   => array(),
			'controls-bottom-center' => array(),
			'controls-bottom-right'  => array(),
		);
	}

	/**
	 * Runtime field map with defaults filled for provisioning.
	 *
	 * @return array<string,string>
	 */
	private function resolved_field_map() {
		$defaults = SettingsSchema::field_map_defaults();
		$current  = $this->config->field_map();

		foreach ( $defaults as $key => $field_id ) {
			if ( ! isset( $current[ $key ] ) || '' === trim( (string) $current[ $key ] ) ) {
				$current[ $key ] = $field_id;
			}
		}

		return $current;
	}

	/**
	 * Find an existing form by exact title.
	 *
	 * @param string $title Form title.
	 * @return array<string,mixed>|null
	 */
	private function find_form_by_title( $title ) {
		foreach ( array( true, false ) as $active ) {
			$forms = \GFAPI::get_forms( $active, false );

			if ( is_wp_error( $forms ) || ! is_array( $forms ) ) {
				continue;
			}

			foreach ( $forms as $form ) {
				if ( $this->is_form( $form ) && $title === (string) ( $form['title'] ?? '' ) ) {
					return $form;
				}
			}
		}

		return null;
	}

	/**
	 * Find an existing feed by exact feed name.
	 *
	 * @param int    $form_id   Gravity Forms form ID.
	 * @param string $feed_name Feed name.
	 * @return array<string,mixed>|null
	 */
	private function find_calendar_feed_by_name( $form_id, $feed_name ) {
		$feeds = \GFAPI::get_feeds( null, $form_id, self::CALENDAR_ADDON_SLUG, null );

		if ( is_wp_error( $feeds ) || ! is_array( $feeds ) ) {
			return null;
		}

		foreach ( $feeds as $feed ) {
			if ( ! $this->is_calendar_feed_for_form( $feed, $form_id ) ) {
				continue;
			}

			if ( $feed_name === $this->feed_meta_value( $feed, 'feedName' ) ) {
				return $feed;
			}
		}

		return null;
	}

	/**
	 * Whether a Gravity Forms form row is usable.
	 *
	 * @param mixed $form Candidate form.
	 * @return bool
	 */
	private function is_form( $form ) {
		return is_array( $form ) && ! empty( $form['id'] );
	}

	/**
	 * Whether a feed belongs to the expected calendar add-on and form.
	 *
	 * @param mixed $feed    Candidate feed.
	 * @param int   $form_id Gravity Forms form ID.
	 * @return bool
	 */
	private function is_calendar_feed_for_form( $feed, $form_id ) {
		if ( ! is_array( $feed ) || empty( $feed['id'] ) ) {
			return false;
		}

		if ( ! empty( $feed['form_id'] ) && (int) $feed['form_id'] !== (int) $form_id ) {
			return false;
		}

		if ( ! empty( $feed['addon_slug'] ) && self::CALENDAR_ADDON_SLUG !== (string) $feed['addon_slug'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Read a feed meta value as text.
	 *
	 * @param array<string,mixed> $feed Feed row.
	 * @param string              $key  Meta key.
	 * @return string
	 */
	private function feed_meta_value( array $feed, $key ) {
		$meta = isset( $feed['meta'] ) && is_array( $feed['meta'] ) ? $feed['meta'] : array();

		return trim( (string) ( $meta[ $key ] ?? '' ) );
	}

	/**
	 * Extract a GravityCalendar feed ID from saved shortcode source.
	 *
	 * @param string $shortcode Shortcode source.
	 * @return int
	 */
	private function feed_id_from_shortcode( $shortcode ) {
		if ( '' === $shortcode || ! SettingsSchema::is_gravitycalendar_shortcode( $shortcode ) ) {
			return 0;
		}

		if ( 1 !== preg_match( '/\sid=(["\']?)(\d+)\1/i', $shortcode, $matches ) ) {
			return 0;
		}

		return absint( $matches[2] ?? 0 );
	}

	/**
	 * Standard shortcode for a resolved feed.
	 *
	 * @param int $feed_id Feed ID.
	 * @return string
	 */
	private function calendar_shortcode( $feed_id ) {
		return '[gravitycalendar id="' . absint( $feed_id ) . '"]';
	}

	/**
	 * Merge tag helper.
	 *
	 * @param string $label    Gravity Forms field label.
	 * @param string $field_id Gravity Forms field ID.
	 * @return string
	 */
	private function merge_tag( $label, $field_id ) {
		return '{' . $label . ':' . $field_id . '}';
	}

	/**
	 * Default setting value normalized as text.
	 *
	 * @param string $field_name Field name.
	 * @return string
	 */
	private function default_setting( $field_name ) {
		return trim( (string) SettingsSchema::default_for( $field_name ) );
	}

	/**
	 * Configured feed name with the plugin default as fallback.
	 *
	 * @return string
	 */
	private function resolved_calendar_feed_name() {
		$feed_name = $this->config->calendar_feed_name();

		return '' !== $feed_name ? $feed_name : $this->default_setting( 'wm_bci_calendar_feed_name' );
	}

	/**
	 * Whether a setting/meta value is effectively empty.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private function is_empty_setting( $value ) {
		if ( null === $value ) {
			return true;
		}

		if ( is_array( $value ) ) {
			return empty( $value );
		}

		return '' === trim( (string) $value );
	}
}
