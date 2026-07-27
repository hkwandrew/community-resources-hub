<?php
/**
 * Regression checks for BCI member data restore tooling.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_posts'] = array(
	1467 => (object) array(
		'ID'           => 1467,
		'post_title'   => 'Shades of Motherhood Network',
		'post_type'    => 'bci_member',
		'post_content' => '',
	),
	1458 => (object) array(
		'ID'           => 1458,
		'post_title'   => 'American Indian Community Center (AICC)',
		'post_type'    => 'bci_member',
		'post_content' => '',
	),
	1451 => (object) array(
		'ID'           => 1451,
		'post_title'   => 'Asians for Collective Liberation in Spokane',
		'post_type'    => 'bci_member',
		'post_content' => '',
	),
);

$GLOBALS['crh_post_meta'] = array(
	1458 => array(
		'wm_bci_member_social_links'                   => 3,
		'wm_bci_member_social_links_0_social_platform' => 'youtube|YouTube',
		'wm_bci_member_social_links_0_url'             => 'http://@aiccspokane',
		'wm_bci_member_social_links_0_label'           => '',
		'wm_bci_member_social_links_1_social_platform' => 'facebook|Facebook',
		'wm_bci_member_social_links_1_url'             => 'http://@indiancenter610',
		'wm_bci_member_social_links_1_label'           => '',
		'wm_bci_member_social_links_2_social_platform' => 'twitter|X / Twitter',
		'wm_bci_member_social_links_2_url'             => 'https://x.com/stale',
		'wm_bci_member_social_links_2_label'           => 'Stale',
	),
);

$GLOBALS['crh_attachments'] = array(
	1117 => (object) array(
		'ID'        => 1117,
		'post_type' => 'attachment',
	),
);

$GLOBALS['crh_updated_options'] = array();
$GLOBALS['crh_updated_posts'] = array();
$GLOBALS['crh_deleted_transients'] = array();
$GLOBALS['crh_registered_actions'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $display = true ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-value">';
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ) {
		throw new RuntimeException( 'wp_die: ' . wp_strip_all_tags( (string) $message ) );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		$GLOBALS['crh_checked_nonce_arg'] = $query_arg;

		if ( 'wm_bci_restore_member_data_nonce' !== $query_arg ) {
			throw new RuntimeException( 'wrong_nonce_field:' . $query_arg );
		}

		if ( 'nonce-value' !== ( $_REQUEST[ $query_arg ] ?? '' ) ) {
			throw new RuntimeException( 'bad_restore_nonce' );
		}

		return 1;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, $post_id = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['crh_registered_actions'][] = array( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ) {
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		return array_values( $GLOBALS['crh_posts'] );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id = 0 ) {
		return $GLOBALS['crh_posts'][ $post_id ] ?? $GLOBALS['crh_attachments'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id = null, $context = 'display' ) {
		$post_id = absint( $post_id );

		return $GLOBALS['crh_posts'][ $post_id ]->{$field} ?? '';
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
		$post_id = absint( $postarr['ID'] ?? 0 );

		if ( ! $post_id || ! isset( $GLOBALS['crh_posts'][ $post_id ] ) ) {
			return 0;
		}

		if ( array_key_exists( 'post_content', $postarr ) ) {
			$GLOBALS['crh_posts'][ $post_id ]->post_content = (string) $postarr['post_content'];
		}

		$GLOBALS['crh_updated_posts'][] = $postarr;

		return $post_id;
	}
}

if ( ! function_exists( 'wp_attachment_is_image' ) ) {
	function wp_attachment_is_image( $post_id ) {
		return isset( $GLOBALS['crh_attachments'][ $post_id ] );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $meta_key = '', $single = false ) {
		return $GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
		unset( $GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] );
		return true;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['crh_updated_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		$GLOBALS['crh_deleted_transients'][] = $transient;
		return true;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	function wp_get_referer() {
		return 'https://example.com/wp-admin/edit.php?post_type=bci_member&paged=2';
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302, $x_redirect_by = 'WordPress' ) {
		throw new RuntimeException( 'redirect:' . $location );
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-member-data-restore.php';

$restore = new WatersMeet\CommunityResourcesHub\ContentModel\MemberDataRestore();
$restore->register();

$registered_hooks = array_column( $GLOBALS['crh_registered_actions'], 0 );

if ( ! in_array( 'admin_post_wm_bci_restore_member_data', $registered_hooks, true ) ) {
	fwrite( STDERR, "Expected member data restore admin action to be registered.\n" );
	exit( 1 );
}

ob_start();
$restore->render_restore_action( 'bci_member', 'top' );
$restore_action_markup = ob_get_clean();

if (
	false !== strpos( $restore_action_markup, '<a ' )
	|| false === strpos( $restore_action_markup, '<button' )
	|| false === strpos( $restore_action_markup, 'formmethod="post"' )
	|| false === strpos( $restore_action_markup, 'formaction=' )
	|| false === strpos( $restore_action_markup, 'admin-post.php' )
	|| false === strpos( $restore_action_markup, 'action=wm_bci_restore_member_data' )
	|| false !== strpos( $restore_action_markup, '_wpnonce=nonce-value' )
	|| false !== strpos( $restore_action_markup, 'name="_wpnonce"' )
	|| false === strpos( $restore_action_markup, 'name="wm_bci_restore_member_data_nonce" value="nonce-value"' )
	|| false === strpos( $restore_action_markup, 'name="action"' )
	|| false === strpos( $restore_action_markup, 'value="wm_bci_restore_member_data"' )
	|| false === strpos( $restore_action_markup, 'Restore BCI Member Data' )
) {
	fwrite( STDERR, "Expected the BCI member list-table restore action to POST with a restore-specific nonce field.\n" );
	exit( 1 );
}

$fixture_post_meta = $GLOBALS['crh_post_meta'];
$fixture_posts = array_map(
	static function ( $post ) {
		return clone $post;
	},
	$GLOBALS['crh_posts']
);

$_SERVER['REQUEST_METHOD'] = 'GET';

try {
	$restore->handle_manual_restore();
	fwrite( STDERR, "Expected direct GET member data restore requests to be rejected before nonce processing.\n" );
	exit( 1 );
} catch ( RuntimeException $exception ) {
	if ( false === strpos( $exception->getMessage(), 'wp_die:' ) ) {
		fwrite( STDERR, "Expected direct GET member data restore requests to die before nonce processing.\n" );
		exit( 1 );
	}
}

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array(
	'_wpnonce'                         => 'bulk-posts-nonce',
	'wm_bci_restore_member_data_nonce' => 'nonce-value',
);
$_REQUEST = $_POST;

try {
	$restore->handle_manual_restore();
	fwrite( STDERR, "Expected successful POST restore requests to redirect back to the member table.\n" );
	exit( 1 );
} catch ( RuntimeException $exception ) {
	if (
		'wm_bci_restore_member_data_nonce' !== ( $GLOBALS['crh_checked_nonce_arg'] ?? '' )
		|| false === strpos( $exception->getMessage(), 'redirect:https://example.com/wp-admin/edit.php?post_type=bci_member&paged=2' )
	) {
		fwrite( STDERR, "Expected POST member data restores to validate the restore-specific nonce despite the list-table bulk nonce.\n" );
		exit( 1 );
	}
}

$GLOBALS['crh_post_meta'] = $fixture_post_meta;
$GLOBALS['crh_posts'] = $fixture_posts;
$GLOBALS['crh_updated_posts'] = array();

$summary = $restore->restore();

if ( 3 !== (int) $summary['matched_members'] || 17 !== (int) $summary['missing_members'] ) {
	fwrite( STDERR, "Expected restore to match current members by title and report missing catalog members.\n" );
	exit( 1 );
}

if ( 10 !== (int) $summary['social_rows'] || 3 !== (int) $summary['social_members'] ) {
	fwrite( STDERR, "Expected restore to rewrite social rows for matched catalog members.\n" );
	exit( 1 );
}

if ( 1 !== (int) $summary['image_fields_updated'] || 3 !== (int) $summary['image_fields_skipped'] ) {
	fwrite( STDERR, "Expected restore to update only image fields whose attachments exist.\n" );
	exit( 1 );
}

if ( 3 !== (int) ( $summary['content_fields_updated'] ?? 0 ) ) {
	fwrite( STDERR, "Expected restore to fill blank classic editor content for matched members.\n" );
	exit( 1 );
}

$expected_shades_content = 'Our vision is to create a world where Black mothers and families thrive, with equitable access to compassionate care, comprehensive support, and resources that empower healthy pregnancies, births, and postpartum experiences. Through holistic programs ranging from food assistance to doula services and peer support, we aim to uplift and transform Black maternal health outcomes for future generations.';

if ( $expected_shades_content !== ( $GLOBALS['crh_posts'][1467]->post_content ?? '' ) ) {
	fwrite( STDERR, "Expected restore to recover the Shades of Motherhood Network classic editor content.\n" );
	exit( 1 );
}

$aicc_meta = $GLOBALS['crh_post_meta'][1458];

if (
	2 !== (int) ( $aicc_meta['wm_bci_member_social_links'] ?? 0 )
	|| 'facebook|Facebook' !== ( $aicc_meta['wm_bci_member_social_links_0_social_platform'] ?? '' )
	|| 'https://www.facebook.com/indiancenter610/' !== ( $aicc_meta['wm_bci_member_social_links_0_url'] ?? '' )
	|| '@indiancenter610' !== ( $aicc_meta['wm_bci_member_social_links_0_label'] ?? '' )
	|| 'field_crh_bci_member_social_platform' !== ( $aicc_meta['_wm_bci_member_social_links_0_social_platform'] ?? '' )
	|| 'field_crh_bci_member_social_url' !== ( $aicc_meta['_wm_bci_member_social_links_0_url'] ?? '' )
	|| 'field_crh_bci_member_social_label' !== ( $aicc_meta['_wm_bci_member_social_links_0_label'] ?? '' )
	|| 'youtube|YouTube' !== ( $aicc_meta['wm_bci_member_social_links_1_social_platform'] ?? '' )
	|| 'https://www.youtube.com/@aiccspokane' !== ( $aicc_meta['wm_bci_member_social_links_1_url'] ?? '' )
	|| '@aiccspokane' !== ( $aicc_meta['wm_bci_member_social_links_1_label'] ?? '' )
) {
	fwrite( STDERR, "Expected AICC's malformed social handles to be replaced with canonical social URLs.\n" );
	exit( 1 );
}

if ( isset( $aicc_meta['wm_bci_member_social_links_2_url'] ) ) {
	fwrite( STDERR, "Expected stale social rows beyond the restored count to be removed.\n" );
	exit( 1 );
}

if (
	1117 !== (int) ( $aicc_meta['wm_bci_member_logo_url'] ?? 0 )
	|| isset( $aicc_meta['wm_bci_member_hero_image_url'] )
) {
	fwrite( STDERR, "Expected AICC logo to restore only when its attachment exists and hero to stay empty when missing.\n" );
	exit( 1 );
}

$shades_meta = $GLOBALS['crh_post_meta'][1467];

if (
	3 !== (int) ( $shades_meta['wm_bci_member_social_links'] ?? 0 )
	|| 'instagram|Instagram' !== ( $shades_meta['wm_bci_member_social_links_0_social_platform'] ?? '' )
	|| 'linkedin|LinkedIn' !== ( $shades_meta['wm_bci_member_social_links_1_social_platform'] ?? '' )
	|| 'facebook|Facebook' !== ( $shades_meta['wm_bci_member_social_links_2_social_platform'] ?? '' )
) {
	fwrite( STDERR, "Expected restore to infer social platforms from catalog URLs.\n" );
	exit( 1 );
}

$acl_meta = $GLOBALS['crh_post_meta'][1451];

if ( 5 !== (int) ( $acl_meta['wm_bci_member_social_links'] ?? 0 ) ) {
	fwrite( STDERR, "Expected restore to match the remote ACL title and restore its social links.\n" );
	exit( 1 );
}

$GLOBALS['crh_updated_posts'] = array();
$second_summary = $restore->restore();

if (
	0 !== (int) ( $second_summary['content_fields_updated'] ?? 0 )
	|| 3 !== (int) ( $second_summary['content_fields_skipped'] ?? 0 )
	|| ! empty( $GLOBALS['crh_updated_posts'] )
) {
	fwrite( STDERR, "Expected a second restore run to leave existing classic editor content untouched.\n" );
	exit( 1 );
}

if ( empty( $GLOBALS['crh_updated_options'][ WatersMeet\CommunityResourcesHub\ContentModel\MemberDataRestore::SUMMARY_OPTION ] ) ) {
	fwrite( STDERR, "Expected restore summary to be persisted for admin diagnostics.\n" );
	exit( 1 );
}

echo "Member data restore test passed.\n";
