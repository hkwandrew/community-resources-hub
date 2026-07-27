<?php
/**
 * Smoke tests for BCI Gravity Forms / GravityCalendar provisioning.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] ?? array() )
			? $GLOBALS['crh_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value = '', $deprecated = '', $autoload = null ) {
		$GLOBALS['crh_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['crh_options'][ $option ] = $value;
		return true;
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

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( $email ) {
		return false !== filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return preg_replace( '#<script[^>]*>.*?</script>#is', '', (string) $html );
	}
}

if ( ! function_exists( 'shortcode_exists' ) ) {
	function shortcode_exists( $tag ) {
		return 'gravitycalendar' === $tag;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;

		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'GFAPI' ) ) {
	class GFAPI {
		public static $forms = array();
		public static $feeds = array();
		public static $next_form_id = 101;
		public static $next_feed_id = 201;
		public static $update_form_calls = 0;
		public static $update_form_error = false;

		public static function reset() {
			self::$forms        = array();
			self::$feeds        = array();
			self::$next_form_id = 101;
			self::$next_feed_id = 201;
			self::$update_form_calls = 0;
			self::$update_form_error = false;
		}

		public static function get_form( $form_id ) {
			return self::$forms[ (int) $form_id ] ?? false;
		}

		public static function get_forms( $active = true, $trash = false, $sort_column = 'id', $sort_dir = 'ASC' ) {
			$forms = array_values( self::$forms );

			return array_values(
				array_filter(
					$forms,
					static function ( $form ) use ( $active ) {
						return (bool) ( $form['is_active'] ?? true ) === (bool) $active;
					}
				)
			);
		}

		public static function add_form( $form_meta ) {
			$form_id = self::$next_form_id++;
			$form_meta['id'] = $form_id;
			$form_meta['is_active'] = true;
			self::$forms[ $form_id ] = $form_meta;

			return $form_id;
		}

		public static function update_form( $form, $form_id = null ) {
			self::$update_form_calls++;

			if ( self::$update_form_error ) {
				return new WP_Error( 'error_updating_form', 'Error updating form' );
			}

			$form_id = null !== $form_id ? (int) $form_id : (int) ( $form['id'] ?? 0 );

			if ( ! $form_id || empty( self::$forms[ $form_id ] ) ) {
				return new WP_Error( 'not_found', 'Form not found' );
			}

			$form['id'] = $form_id;
			self::$forms[ $form_id ] = $form;

			return true;
		}

		public static function get_feeds( $feed_ids = null, $form_ids = null, $addon_slug = null, $is_active = true ) {
			$feeds = array_values( self::$feeds );

			$feeds = array_filter(
				$feeds,
				static function ( $feed ) use ( $feed_ids, $form_ids, $addon_slug, $is_active ) {
					if ( null !== $feed_ids && (int) $feed['id'] !== (int) $feed_ids ) {
						return false;
					}

					if ( null !== $form_ids && (int) $feed['form_id'] !== (int) $form_ids ) {
						return false;
					}

					if ( null !== $addon_slug && (string) $feed['addon_slug'] !== (string) $addon_slug ) {
						return false;
					}

					if ( null !== $is_active && (bool) $feed['is_active'] !== (bool) $is_active ) {
						return false;
					}

					return true;
				}
			);

			return empty( $feeds ) ? new WP_Error( 'not_found', 'Feed not found' ) : array_values( $feeds );
		}

		public static function get_feed( $feed_id ) {
			return self::$feeds[ (int) $feed_id ] ?? new WP_Error( 'not_found', 'Feed not found' );
		}

		public static function add_feed( $form_id, $feed_meta, $addon_slug ) {
			$feed_id = self::$next_feed_id++;

			self::$feeds[ $feed_id ] = array(
				'id'         => $feed_id,
				'form_id'    => (int) $form_id,
				'addon_slug' => (string) $addon_slug,
				'is_active'  => 1,
				'meta'       => $feed_meta,
			);

			return $feed_id;
		}

		public static function update_feed( $feed_id, $feed_meta, $form_id = null ) {
			if ( empty( self::$feeds[ (int) $feed_id ] ) ) {
				return new WP_Error( 'not_found', 'Feed not found' );
			}

			self::$feeds[ (int) $feed_id ]['meta'] = $feed_meta;

			if ( null !== $form_id ) {
				self::$feeds[ (int) $feed_id ]['form_id'] = (int) $form_id;
			}

			return 1;
		}

		public static function update_feed_property( $feed_id, $property_name, $property_value ) {
			if ( empty( self::$feeds[ (int) $feed_id ] ) ) {
				return new WP_Error( 'not_found', 'Feed not found' );
			}

			self::$feeds[ (int) $feed_id ][ $property_name ] = $property_value;

			return true;
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/config/class-health-checks.php';
require_once dirname( __DIR__ ) . '/includes/config/class-provisioner.php';

function crh_provisioner_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

function crh_provisioner_issue_containing( array $issues, $needle ) {
	foreach ( $issues as $issue ) {
		if ( false !== strpos( $issue, $needle ) ) {
			return true;
		}
	}

	return false;
}

function crh_provisioner_field_value( $field, $property, $default = null ) {
	if ( is_object( $field ) ) {
		return isset( $field->{$property} ) ? $field->{$property} : $default;
	}

	return is_array( $field ) && array_key_exists( $property, $field ) ? $field[ $property ] : $default;
}

function crh_provisioner_field_with_value( $field, $property, $value ) {
	if ( is_object( $field ) ) {
		$field = clone $field;
		$field->{$property} = $value;
		return $field;
	}

	$field = is_array( $field ) ? $field : array();
	$field[ $property ] = $value;
	return $field;
}

function crh_provisioner_without_recommended_vendor( array $fields ) {
	foreach ( $fields as $index => $field ) {
		$field_id = (string) crh_provisioner_field_value( $field, 'id', '' );

		if ( '1' === $field_id ) {
			$choices = array_values(
				array_filter(
					(array) crh_provisioner_field_value( $field, 'choices', array() ),
					static function( $choice ) {
						$text  = is_object( $choice ) ? (string) ( $choice->text ?? '' ) : (string) ( $choice['text'] ?? '' );
						$value = is_object( $choice ) ? (string) ( $choice->value ?? '' ) : (string) ( $choice['value'] ?? '' );
						return 'Recommended Vendor' !== $text && 'Recommended Vendor' !== $value;
					}
				)
			);
			$field = crh_provisioner_field_with_value( $field, 'choices', $choices );
		}

		$logic = crh_provisioner_field_value( $field, 'conditionalLogic', array() );

		if ( is_array( $logic ) && isset( $logic['rules'] ) && is_array( $logic['rules'] ) ) {
			$logic['rules'] = array_values(
				array_filter(
					$logic['rules'],
					static function( $rule ) {
						return 'Recommended Vendor' !== (string) ( $rule['value'] ?? '' );
					}
				)
			);
			$field = crh_provisioner_field_with_value( $field, 'conditionalLogic', $logic );
		}

		$fields[ $index ] = $field;
	}

	return $fields;
}

function crh_provisioner_fields_by_id( array $fields ) {
	$fields_by_id = array();

	foreach ( $fields as $field ) {
		$fields_by_id[ (string) crh_provisioner_field_value( $field, 'id', '' ) ] = $field;
	}

	return $fields_by_id;
}

function crh_provisioner_choice_values( $field ) {
	return array_map(
		static function( $choice ) {
			return is_object( $choice ) ? (string) ( $choice->value ?? '' ) : (string) ( $choice['value'] ?? '' );
		},
		(array) crh_provisioner_field_value( $field, 'choices', array() )
	);
}

function crh_provisioner_logic_values( $field ) {
	$logic = crh_provisioner_field_value( $field, 'conditionalLogic', array() );

	return array_map(
		static function( $rule ) {
			return (string) ( $rule['value'] ?? '' );
		},
		(array) ( $logic['rules'] ?? array() )
	);
}

function crh_provisioner_logic_pairs( $field ) {
	$logic = crh_provisioner_field_value( $field, 'conditionalLogic', array() );

	return array_map(
		static function( $rule ) {
			return (string) ( $rule['fieldId'] ?? '' ) . '=' . (string) ( $rule['value'] ?? '' );
		},
		(array) ( $logic['rules'] ?? array() )
	);
}

$expected_date_types = array(
	'Grant / RFP',
	'Event',
	'Workshop, training, or other learning',
	'Other',
);
$expected_non_date_types = array( 'Resource', 'Recommended Vendor' );
$expected_common_pairs = array(
	'1=Grant / RFP',
	'1=Event',
	'1=Workshop, training, or other learning',
	'1=Other',
	'25=Resource',
	'25=Recommended Vendor',
);
$expected_dated_detail_values = array( 'Event', 'Workshop, training, or other learning', 'Other' );

GFAPI::reset();
$GLOBALS['crh_options'] = array();
$provisioner = new WatersMeet\CommunityResourcesHub\Config\Provisioner();
$result      = $provisioner->provision();

crh_provisioner_assert( ! is_wp_error( $result ), 'Expected provisioning to create missing resources.' );
crh_provisioner_assert( 101 === (int) $result['form_id'], 'Expected new Gravity Form ID to be returned.' );
crh_provisioner_assert( 201 === (int) $result['calendar_feed_id'], 'Expected new GravityCalendar feed ID to be returned.' );
crh_provisioner_assert( 'created' === $result['form_action'], 'Expected missing Gravity Form to be created.' );
crh_provisioner_assert( 'created' === $result['calendar_feed_action'], 'Expected missing GravityCalendar feed to be created.' );
crh_provisioner_assert( '[gravitycalendar id="201"]' === get_option( 'options_wm_bci_calendar_shortcode' ), 'Expected created feed shortcode to be persisted.' );
crh_provisioner_assert( '6' === (string) ( GFAPI::$feeds[201]['meta']['startdate'] ?? '' ), 'Expected calendar feed start date to use the plugin field map.' );
crh_provisioner_assert( '{Opportunity Title:4}' === (string) ( GFAPI::$feeds[201]['meta']['eventtitle'] ?? '' ), 'Expected calendar feed title to use the provisioned title merge tag.' );
crh_provisioner_assert( ! empty( GFAPI::$feeds[201]['meta']['controls'] ) && is_array( GFAPI::$feeds[201]['meta']['controls'] ), 'Expected calendar controls to be persisted for render safety.' );

$created_form   = GFAPI::$forms[101];
$created_fields = crh_provisioner_fields_by_id( $created_form['fields'] );
$created_ids    = array_map(
	static function( $field ) {
		return (string) crh_provisioner_field_value( $field, 'id', '' );
	},
	$created_form['fields']
);

crh_provisioner_assert( 'Share this' === (string) ( $created_form['button']['text'] ?? '' ), 'Expected new forms to use the Share this submit-button text.' );
crh_provisioner_assert( array( '4', '24', '1', '25', '26' ) === array_slice( $created_ids, 0, 5 ), 'Expected the v2 contract questions to lead the form in canonical order.' );
crh_provisioner_assert( 27 <= (int) ( $created_form['nextFieldId'] ?? 0 ), 'Expected nextFieldId to reserve IDs through field 26.' );

foreach ( WatersMeet\CommunityResourcesHub\Config\SettingsSchema::field_map_defaults() as $field_id ) {
	crh_provisioner_assert( isset( $created_fields[ (string) $field_id ] ), "Expected provisioned form to contain mapped field ID {$field_id}." );
}

crh_provisioner_assert( $expected_date_types === crh_provisioner_choice_values( $created_fields['1'] ), 'Expected field 1 to contain only date-sensitive canonical types.' );
crh_provisioner_assert( $expected_non_date_types === crh_provisioner_choice_values( $created_fields['25'] ), 'Expected field 25 to contain only Resource and Recommended Vendor.' );
crh_provisioner_assert( false === (bool) crh_provisioner_field_value( $created_fields['1'], 'enableOtherChoice', true ), 'Expected free-text Other to be disabled.' );

foreach ( array( '24', '1', '25', '26' ) as $field_id ) {
	crh_provisioner_assert( true === (bool) crh_provisioner_field_value( $created_fields[ $field_id ], 'isRequired', false ), "Expected contract field {$field_id} to be required." );
}

crh_provisioner_assert( '' === (string) crh_provisioner_field_value( $created_fields['24'], 'defaultValue', '' ), 'Expected the time-sensitive question to have no default.' );
crh_provisioner_assert( array( '24=Yes' ) === crh_provisioner_logic_pairs( $created_fields['1'] ), 'Expected field 1 to show only for time-sensitive Yes.' );
crh_provisioner_assert( array( '24=No' ) === crh_provisioner_logic_pairs( $created_fields['25'] ), 'Expected field 25 to show only for time-sensitive No.' );
crh_provisioner_assert( $expected_common_pairs === crh_provisioner_logic_pairs( $created_fields['26'] ), 'Expected the BCI Update question after either category branch.' );

foreach ( array( '3', '5', '17', '18', '19' ) as $field_id ) {
	crh_provisioner_assert( $expected_common_pairs === crh_provisioner_logic_pairs( $created_fields[ $field_id ] ), "Expected common field {$field_id} after either category branch." );
}

crh_provisioner_assert( true === (bool) crh_provisioner_field_value( $created_fields['6'], 'isRequired', false ), 'Expected start date to be required when visible.' );
crh_provisioner_assert( true === (bool) crh_provisioner_field_value( $created_fields['9'], 'isRequired', false ), 'Expected grant deadline to be required when visible.' );
crh_provisioner_assert( false === (bool) crh_provisioner_field_value( $created_fields['3'], 'isRequired', true ), 'Expected the submitter name to remain optional.' );
crh_provisioner_assert( true === (bool) crh_provisioner_field_value( $created_fields['14'], 'isRequired', false ), 'Expected the live cost requirement in a new form.' );
crh_provisioner_assert( $expected_dated_detail_values === crh_provisioner_logic_values( $created_fields['6'] ), 'Expected start date only for Event, Learning, and Other.' );
crh_provisioner_assert( array( 'Grant / RFP' ) === crh_provisioner_logic_values( $created_fields['9'] ), 'Expected grant deadline only for Grant / RFP.' );

foreach ( array( '10', '20', '12', '21', '14', '16' ) as $field_id ) {
	crh_provisioner_assert( $expected_dated_detail_values === crh_provisioner_logic_values( $created_fields[ $field_id ] ), "Expected dated detail field {$field_id} only for Event, Learning, and Other." );
}

$issues = ( new WatersMeet\CommunityResourcesHub\Config\HealthChecks() )->issues();
crh_provisioner_assert( ! crh_provisioner_issue_containing( $issues, 'Complete the BCI Gravity Forms field mapping' ), 'Expected provisioned field map defaults to clear the mapping health issue.' );

$contract_state = get_option( WatersMeet\CommunityResourcesHub\Config\Provisioner::FORM_CONTRACT_STATE_OPTION, array() );
crh_provisioner_assert( 'opportunity-contract-v3' === WatersMeet\CommunityResourcesHub\Config\Provisioner::FORM_CONTRACT_VERSION, 'Expected the form contract version to be v3.' );
crh_provisioner_assert( 'opportunity-contract-v3' === ( $contract_state['version'] ?? '' ), 'Expected a newly created form to store the v3 contract marker.' );

$legacy_fields = array();

foreach ( $created_form['fields'] as $field ) {
	$field_id = (string) crh_provisioner_field_value( $field, 'id', '' );

	if ( in_array( $field_id, array( '24', '25', '26' ), true ) ) {
		continue;
	}

	$field = (object) $field;

	if ( '1' === $field_id ) {
		$field->label             = 'What kind of opportunity is this?';
		$field->choices           = array_merge(
			array_map(
				static function( $value ) {
					return array( 'text' => $value, 'value' => $value );
				},
				array( 'Grant / RFP', 'Event', 'Workshop, training, or other learning', 'Resource', 'BCI Update' )
			),
			array()
		);
		$field->enableOtherChoice = true;
		$field->customTypeSetting = 'preserve-type-customization';
	}

	if ( '3' === $field_id ) {
		$field->isRequired     = false;
		$field->customProperty = 'preserve-submitter-customization';
	}

	$legacy_fields[] = $field;
}

$legacy_fields[] = (object) array(
	'id'               => 23,
	'type'             => 'radio',
	'label'            => 'Should visitors be able to add this item to their personal calendar?',
	'choices'          => array(
		array( 'text' => 'Show', 'value' => 'show' ),
		array( 'text' => 'Hide', 'value' => 'hide' ),
	),
	'isRequired'       => true,
	'customProperty'   => 'preserve-calendar-control',
	'conditionalLogic' => array(
		'enabled'    => true,
		'actionType' => 'show',
		'logicType'  => 'any',
		'rules'      => array( array( 'fieldId' => '1', 'operator' => 'is', 'value' => 'BCI Update' ) ),
	),
);

$custom_notifications = array(
	'custom-notification' => array( 'id' => 'custom-notification', 'name' => 'Preserve this notification' ),
);
$custom_confirmations = array(
	'custom-confirmation' => array( 'id' => 'custom-confirmation', 'name' => 'Preserve this confirmation' ),
);
$legacy_button = array(
	'type'                => 'text',
	'text'                => 'Submit your opportunity',
	'imageUrl'            => 'https://example.com/preserved-button.png',
	'conditionalLogic'    => array( 'enabled' => false ),
	'customButtonSetting' => 'preserve-this-button-setting',
);
$legacy_form = array(
	'id'            => 44,
	'title'         => 'BCI Community Opportunity Submission',
	'is_active'     => true,
	'nextFieldId'   => 24,
	'button'        => $legacy_button,
	'fields'        => $legacy_fields,
	'notifications' => $custom_notifications,
	'confirmations' => $custom_confirmations,
	'customSetting' => 'preserve-this-form-setting',
);
$legacy_snapshot = serialize( $legacy_form );

GFAPI::reset();
$GLOBALS['crh_options'] = array();
$provisioner = new WatersMeet\CommunityResourcesHub\Config\Provisioner();
$prepared    = $provisioner->prepare_form_contract( $legacy_form );

crh_provisioner_assert( ! is_wp_error( $prepared ) && ! empty( $prepared['updated'] ), 'Expected pure preparation to plan the v3 contract.' );
crh_provisioner_assert( 0 === GFAPI::$update_form_calls, 'Expected pure preparation not to write Gravity Forms.' );
crh_provisioner_assert( null === get_option( WatersMeet\CommunityResourcesHub\Config\Provisioner::FORM_CONTRACT_STATE_OPTION, null ), 'Expected pure preparation not to write a marker.' );
crh_provisioner_assert( $legacy_snapshot === serialize( $legacy_form ), 'Expected pure preparation not to mutate its input form.' );

$prepared_form   = $prepared['form'];
$prepared_fields = crh_provisioner_fields_by_id( $prepared_form['fields'] );
$expected_button = array_replace( $legacy_button, array( 'text' => 'Share this' ) );
$prepared_ids    = array_map(
	static function( $field ) {
		return (string) crh_provisioner_field_value( $field, 'id', '' );
	},
	$prepared_form['fields']
);

crh_provisioner_assert( array( '4', '24', '1', '25', '26' ) === array_slice( $prepared_ids, 0, 5 ), 'Expected reconciliation to insert contract fields in canonical order.' );
crh_provisioner_assert( 27 <= (int) $prepared_form['nextFieldId'], 'Expected reconciliation to advance nextFieldId.' );
crh_provisioner_assert( $expected_button === $prepared_form['button'], 'Expected preparation to own only the submit-button text and preserve all other button metadata.' );
crh_provisioner_assert( 'preserve-type-customization' === crh_provisioner_field_value( $prepared_fields['1'], 'customTypeSetting', '' ), 'Expected custom field 1 properties to remain intact.' );
crh_provisioner_assert( 'preserve-submitter-customization' === crh_provisioner_field_value( $prepared_fields['3'], 'customProperty', '' ), 'Expected custom field 3 properties to remain intact.' );
crh_provisioner_assert( false === (bool) crh_provisioner_field_value( $prepared_fields['3'], 'isRequired', true ), 'Expected unrelated field requirements to remain intact.' );
crh_provisioner_assert( 'preserve-calendar-control' === crh_provisioner_field_value( $prepared_fields['23'], 'customProperty', '' ), 'Expected custom field 23 properties to remain intact.' );
crh_provisioner_assert( true === (bool) crh_provisioner_field_value( $prepared_fields['23'], 'isRequired', false ), 'Expected field 23 requirement to remain intact.' );
crh_provisioner_assert( $expected_date_types === crh_provisioner_logic_values( $prepared_fields['23'] ), 'Expected field 23 to follow all four date-sensitive types without otherwise changing it.' );
crh_provisioner_assert( 'preserve-this-form-setting' === $prepared_form['customSetting'], 'Expected unrelated top-level form settings to remain intact.' );
crh_provisioner_assert( $custom_notifications === $prepared_form['notifications'], 'Expected notifications to remain intact.' );
crh_provisioner_assert( $custom_confirmations === $prepared_form['confirmations'], 'Expected confirmations to remain intact.' );

GFAPI::$forms[44] = $legacy_form;
crh_provisioner_assert( true === $provisioner->maybe_reconcile_configured_form(), 'Expected legacy init compatibility method to remain harmless.' );
crh_provisioner_assert( 0 === GFAPI::$update_form_calls, 'Expected no automatic contract write before explicit migration.' );

$applied = $provisioner->reconcile_form_contract( GFAPI::$forms[44] );
crh_provisioner_assert( ! is_wp_error( $applied ) && ! empty( $applied['updated'] ), 'Expected explicit reconciliation to apply the prepared contract.' );
crh_provisioner_assert( 1 === GFAPI::$update_form_calls, 'Expected explicit reconciliation to write once.' );
crh_provisioner_assert( $expected_button === GFAPI::$forms[44]['button'], 'Expected reconciliation to save the new text without changing other button metadata.' );

$contract_state = get_option( WatersMeet\CommunityResourcesHub\Config\Provisioner::FORM_CONTRACT_STATE_OPTION, array() );
crh_provisioner_assert( WatersMeet\CommunityResourcesHub\Config\Provisioner::FORM_CONTRACT_VERSION === ( $contract_state['version'] ?? '' ), 'Expected explicit reconciliation to store the v3 marker.' );
crh_provisioner_assert( 44 === (int) ( $contract_state['form_id'] ?? 0 ), 'Expected explicit reconciliation to store the form ID.' );
crh_provisioner_assert( '' !== (string) ( $contract_state['field_map_hash'] ?? '' ), 'Expected explicit reconciliation to store the field-map hash.' );

$second_apply = $provisioner->reconcile_form_contract( GFAPI::$forms[44] );
crh_provisioner_assert( ! is_wp_error( $second_apply ) && empty( $second_apply['updated'] ), 'Expected explicit reconciliation to be idempotent.' );
crh_provisioner_assert( 1 === GFAPI::$update_form_calls, 'Expected idempotent reconciliation not to write twice.' );
crh_provisioner_assert( $expected_button === GFAPI::$forms[44]['button'], 'Expected the idempotent second reconciliation to preserve button metadata.' );

GFAPI::reset();
$GLOBALS['crh_options'] = array();
GFAPI::$forms[44] = $legacy_form;
GFAPI::$feeds[55] = array(
	'id'         => 55,
	'form_id'    => 44,
	'addon_slug' => 'gravityview-calendar',
	'is_active'  => 1,
	'meta'       => array( 'feedName' => 'BCI Community Opportunity Submission' ),
);
$provision_result = ( new WatersMeet\CommunityResourcesHub\Config\Provisioner() )->provision();
crh_provisioner_assert( ! is_wp_error( $provision_result ) && 'adopted' === $provision_result['form_action'], 'Expected setup to adopt the existing form.' );
crh_provisioner_assert( 0 === GFAPI::$update_form_calls, 'Expected setup not to apply the migration contract to an existing form.' );
crh_provisioner_assert( $legacy_snapshot === serialize( GFAPI::$forms[44] ), 'Expected setup to leave the existing form unchanged.' );
crh_provisioner_assert( null === get_option( WatersMeet\CommunityResourcesHub\Config\Provisioner::FORM_CONTRACT_STATE_OPTION, null ), 'Expected setup not to mark an existing legacy form as migrated.' );

GFAPI::reset();
$GLOBALS['crh_options'] = array();
GFAPI::$forms[44] = $legacy_form;
GFAPI::$update_form_error = true;
$failed_apply = ( new WatersMeet\CommunityResourcesHub\Config\Provisioner() )->reconcile_form_contract( GFAPI::$forms[44] );
crh_provisioner_assert( is_wp_error( $failed_apply ), 'Expected explicit Gravity Forms update failures to be returned.' );
crh_provisioner_assert( $legacy_snapshot === serialize( GFAPI::$forms[44] ), 'Expected failed apply to leave the stored form unchanged.' );
crh_provisioner_assert( null === get_option( WatersMeet\CommunityResourcesHub\Config\Provisioner::FORM_CONTRACT_STATE_OPTION, null ), 'Expected failed apply not to write a marker.' );

$collision_form = $legacy_form;
$collision_form['fields'][] = array(
	'id'    => 24,
	'type'  => 'text',
	'label' => 'Unrelated legacy question',
);
$collision_snapshot = serialize( $collision_form );
$collision_result = ( new WatersMeet\CommunityResourcesHub\Config\Provisioner() )->prepare_form_contract( $collision_form );
crh_provisioner_assert( is_wp_error( $collision_result ), 'Expected an occupied new field ID to fail preflight.' );
crh_provisioner_assert( $collision_snapshot === serialize( $collision_form ), 'Expected collision preflight not to mutate the form.' );

$wrong_type_form = $prepared_form;
$wrong_type_fields = crh_provisioner_fields_by_id( $wrong_type_form['fields'] );
$wrong_type_fields['24'] = crh_provisioner_field_with_value( $wrong_type_fields['24'], 'type', 'text' );
$wrong_type_form['fields'] = array_values( $wrong_type_fields );
$wrong_type_result = ( new WatersMeet\CommunityResourcesHub\Config\Provisioner() )->prepare_form_contract( $wrong_type_form );
crh_provisioner_assert( is_wp_error( $wrong_type_result ), 'Expected an existing contract label with the wrong field type to fail preflight.' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_field_map_time_sensitive' => '1',
);
$mapping_collision = ( new WatersMeet\CommunityResourcesHub\Config\Provisioner() )->prepare_form_contract( $legacy_form );
crh_provisioner_assert( is_wp_error( $mapping_collision ), 'Expected duplicate semantic field mappings to fail preflight.' );

$GLOBALS['crh_options'] = array();
$missing_field_form = $legacy_form;
$missing_field_form['fields'] = array_values(
	array_filter(
		$missing_field_form['fields'],
		static function( $field ) {
			return '19' !== (string) crh_provisioner_field_value( $field, 'id', '' );
		}
	)
);
$missing_snapshot = serialize( $missing_field_form );
$missing_result = ( new WatersMeet\CommunityResourcesHub\Config\Provisioner() )->prepare_form_contract( $missing_field_form );
crh_provisioner_assert( is_wp_error( $missing_result ), 'Expected a missing mapped existing field to fail preflight.' );
crh_provisioner_assert( $missing_snapshot === serialize( $missing_field_form ), 'Expected missing-field preflight not to mutate the form.' );

echo "Provisioner smoke test passed.\n";
