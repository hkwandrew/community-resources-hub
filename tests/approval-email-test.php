<?php
/**
 * Regression checks for approval notification suppression.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_form_id'                  => 5,
	'options_wm_bci_approval_field_id'        => '22',
	'options_wm_bci_notification_name'        => 'Approval Review',
	'options_wm_bci_auto_approved_user_ids'   => array( 42 ),
);
$GLOBALS['crh_registered_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['crh_registered_filters'][] = array( $hook, $callback, $priority, $accepted_args );
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] )
			? $GLOBALS['crh_options'][ $option ]
			: $default;
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'rgar' ) ) {
	function rgar( $array, $key, $default = null ) {
		return is_array( $array ) && array_key_exists( $key, $array ) ? $array[ $key ] : $default;
	}
}

require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-approval-email.php';

function crh_approval_email_assert_same( $expected, $actual, $message ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, $message . "\n" );
		exit( 1 );
	}
}

$config         = new WatersMeet\CommunityResourcesHub\Config\Config();
$approval_email = new WatersMeet\CommunityResourcesHub\Workflow\ApprovalEmail( $config );
$approval_email->register();

$disable_hook = array_values(
	array_filter(
		$GLOBALS['crh_registered_filters'],
		static function ( $filter ) {
			return 'gform_disable_notification_5' === $filter[0];
		}
	)
);

crh_approval_email_assert_same( 1, count( $disable_hook ), 'Expected the BCI form to register approval notification suppression.' );
crh_approval_email_assert_same( $approval_email, $disable_hook[0][1][0], 'Expected the approval email owner to handle notification suppression.' );
crh_approval_email_assert_same( 'disable_for_auto_approved_submitter', $disable_hook[0][1][1], 'Expected the suppression hook to use the auto-approved submitter guard.' );
crh_approval_email_assert_same( 4, $disable_hook[0][3], 'Expected the suppression filter to receive notification, form, and entry context.' );

$approval_notification = array( 'name' => 'Approval Review' );
$other_notification    = array( 'name' => 'Submitter Confirmation' );
$bci_form              = array( 'id' => 5 );
$other_form            = array( 'id' => 6 );
$auto_approved_entry   = array( 'id' => 101, 'created_by' => '42', '22' => 'Approved' );
$pending_entry         = array( 'id' => 102, 'created_by' => '99', '22' => 'Pending' );
$allowlisted_pending   = array( 'id' => 103, 'created_by' => '42', '22' => 'Pending' );

crh_approval_email_assert_same(
	true,
	$approval_email->disable_for_auto_approved_submitter( false, $approval_notification, $bci_form, $auto_approved_entry ),
	'Expected the approval notification to be disabled for an auto-approved submitter.'
);
crh_approval_email_assert_same(
	false,
	$approval_email->disable_for_auto_approved_submitter( false, $approval_notification, $bci_form, $pending_entry ),
	'Expected the approval notification to remain enabled for a submitter who requires review.'
);
crh_approval_email_assert_same(
	false,
	$approval_email->disable_for_auto_approved_submitter( false, $approval_notification, $bci_form, $allowlisted_pending ),
	'Expected the approval notification to remain enabled when an allowlisted submission was not approved.'
);
crh_approval_email_assert_same(
	false,
	$approval_email->disable_for_auto_approved_submitter( false, $other_notification, $bci_form, $auto_approved_entry ),
	'Expected submitter-facing notifications to remain enabled for auto-approved submitters.'
);
crh_approval_email_assert_same(
	false,
	$approval_email->disable_for_auto_approved_submitter( false, $approval_notification, $other_form, $auto_approved_entry ),
	'Expected notifications for other forms to remain unchanged.'
);
crh_approval_email_assert_same(
	true,
	$approval_email->disable_for_auto_approved_submitter( true, $approval_notification, $bci_form, $pending_entry ),
	'Expected an already-disabled notification to stay disabled.'
);

echo "Approval email suppression test passed.\n";
