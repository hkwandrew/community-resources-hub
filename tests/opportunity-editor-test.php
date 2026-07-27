<?php
/**
 * Regression checks for the plugin-owned opportunity editor metabox and list columns.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_post_meta'] = array(
	55 => array(
		'wm_bci_source_entry_id' => '123',
		'wm_bci_approval_status' => 'Approved',
		'wm_bci_start_date'      => '2026-07-05',
		'wm_bci_opportunity_type'=> 'Event',
	),
	61 => array(
		'wm_bci_member_aliases' => "AICC\nNative Project",
	),
);
$GLOBALS['crh_posts'] = array(
	61 => array(
		'ID'          => 61,
		'post_type'   => 'bci_member',
		'post_status' => 'publish',
		'post_title'  => 'American Indian Community Center',
	),
);
$GLOBALS['crh_terms'] = array(
	17 => array(
		'term_id'  => 17,
		'name'     => 'Event',
		'slug'     => 'event',
		'taxonomy' => 'opportunity-type',
	),
	18 => array(
		'term_id'  => 18,
		'name'     => 'Grant / RFP',
		'slug'     => 'grant-rfp',
		'taxonomy' => 'opportunity-type',
	),
);
$GLOBALS['crh_term_meta'] = array(
	17 => array(
		'alias' => 'Events',
		'color' => '#c2385a',
	),
	18 => array(
		'alias' => 'Grant/RFP',
		'color' => '#d9a242',
	),
);
$GLOBALS['crh_term_relationships'] = array(
	55 => array( 17 ),
);
$GLOBALS['crh_added_meta_boxes'] = array();
$GLOBALS['crh_registered_actions'] = array();
$GLOBALS['crh_registered_filters'] = array();
$GLOBALS['crh_set_terms_calls'] = array();
$GLOBALS['crh_updated_meta'] = array();
$GLOBALS['crh_deleted_meta'] = array();

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['crh_registered_actions'][] = array( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['crh_registered_filters'][] = array( $hook, $callback, $priority, $accepted_args );
	}
}

if ( ! function_exists( 'add_meta_box' ) ) {
	function add_meta_box( $id, $title, $callback, $screen, $context = 'advanced', $priority = 'default', $callback_args = null ) {
		$GLOBALS['crh_added_meta_boxes'][] = array(
			'id'       => $id,
			'title'    => $title,
			'screen'   => $screen,
			'context'  => $context,
			'priority' => $priority,
		);
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

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( trim( (string) $title ) );
		$title = preg_replace( '/[^a-z0-9]+/', '-', $title );
		return trim( (string) $title, '-' );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		return (string) $selected === (string) $current ? 'selected="selected"' : '';
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '', $scheme = 'admin' ) {
		return 'https://example.com/wp-admin/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( $action = -1 ) {
		return 'nonce-value';
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $display = true ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-value">';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null ) {
		echo '<button type="submit" name="' . esc_attr( (string) $name ) . '">' . esc_html( (string) $text ) . '</button>';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( $nonce, $action ) {
		return 'nonce-value' === $nonce;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability, $post_id = 0 ) {
		return true;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $meta_key = '', $single = false ) {
		return $GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$post_type = $args['post_type'] ?? '';
		$ids       = array();

		foreach ( $GLOBALS['crh_posts'] as $post_id => $post ) {
			if ( '' !== $post_type && ( $post['post_type'] ?? '' ) !== $post_type ) {
				continue;
			}

			$ids[] = $post_id;
		}

		return $ids;
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id, $context = 'display' ) {
		return $GLOBALS['crh_posts'][ $post_id ][ $field ] ?? '';
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return true;
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] = $meta_value;
		$GLOBALS['crh_updated_meta'][] = array( $post_id, $meta_key, $meta_value );
		return true;
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
		unset( $GLOBALS['crh_post_meta'][ $post_id ][ $meta_key ] );
		$GLOBALS['crh_deleted_meta'][] = array( $post_id, $meta_key );
		return true;
	}
}

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
		$GLOBALS['crh_term_relationships'][ $post_id ] = array_map( 'absint', $terms );
		$GLOBALS['crh_set_terms_calls'][] = array(
			'post_id'  => $post_id,
			'terms'    => $terms,
			'taxonomy' => $taxonomy,
		);
		return $terms;
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( $post_id, $taxonomy, $args = array() ) {
		$term_ids = $GLOBALS['crh_term_relationships'][ $post_id ] ?? array();

		if ( 'ids' === ( $args['fields'] ?? '' ) ) {
			return $term_ids;
		}

		$terms = array();

		foreach ( $term_ids as $term_id ) {
			if ( isset( $GLOBALS['crh_terms'][ $term_id ] ) ) {
				$terms[] = (object) $GLOBALS['crh_terms'][ $term_id ];
			}
		}

		return $terms;
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = array() ) {
		$taxonomy = $args['taxonomy'] ?? '';
		$terms    = array();

		foreach ( $GLOBALS['crh_terms'] as $term ) {
			if ( '' !== $taxonomy && ( $term['taxonomy'] ?? '' ) !== $taxonomy ) {
				continue;
			}

			$terms[] = (object) $term;
		}

		return $terms;
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $meta_key, $single = false ) {
		return $GLOBALS['crh_term_meta'][ $term_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( $format, $timestamp = null, $timezone = null ) {
		return gmdate( $format, (int) $timestamp );
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-opportunity-editor.php';

$editor = new WatersMeet\CommunityResourcesHub\ContentModel\OpportunityEditor(
	new WatersMeet\CommunityResourcesHub\Config\Config()
);

$editor->register();

$registered_action_hooks = array_map(
	static function ( $action ) {
		return $action[0] ?? '';
	},
	$GLOBALS['crh_registered_actions']
);

if ( ! in_array( 'pre_get_posts', $registered_action_hooks, true ) ) {
	fwrite( STDERR, "Expected the opportunity editor to register admin query filtering.\n" );
	exit( 1 );
}

$editor->register_meta_box();

if (
	empty( $GLOBALS['crh_added_meta_boxes'] )
	|| 'crh_bci_opportunity_details' !== ( $GLOBALS['crh_added_meta_boxes'][0]['id'] ?? '' )
	|| 'high' !== ( $GLOBALS['crh_added_meta_boxes'][0]['priority'] ?? '' )
) {
	fwrite( STDERR, "Expected the opportunity editor metabox to be registered at high priority.\n" );
	exit( 1 );
}

$columns = $editor->filter_columns(
	array(
		'cb'    => '<input type="checkbox">',
		'title' => 'Title',
		'date'  => 'Date',
	)
);

if ( ! isset( $columns['crh_bci_source_entry_id'], $columns['crh_bci_reconciliation_state'] ) ) {
	fwrite( STDERR, "Expected BCI source-entry and reconciliation columns in the opportunity list table.\n" );
	exit( 1 );
}

ob_start();
$editor->render_meta_box( (object) array( 'ID' => 55 ) );
$markup = ob_get_clean();

if (
	false === strpos( $markup, 'Source Entry ID' )
	|| false === strpos( $markup, 'Approval Status' )
	|| false === strpos( $markup, 'Opportunity Type' )
	|| false === strpos( $markup, 'wm_bci_opportunity_type_term_id' )
) {
	fwrite( STDERR, "Expected the editor metabox to render source-entry, approval, and type controls.\n" );
	exit( 1 );
}

ob_start();
$editor->render_reconciliation_action( 'bci_opportunity', 'top' );
$toolbar_markup = ob_get_clean();

if (
	false === strpos( $toolbar_markup, 'wm_bci_reconcile_opportunities' )
	|| false === strpos( $toolbar_markup, '_wpnonce=nonce-value' )
	|| false === strpos( $toolbar_markup, 'Reconcile Legacy Opportunities' )
) {
	fwrite( STDERR, "Expected a persistent legacy opportunity reconciliation action on the opportunity list screen.\n" );
	exit( 1 );
}

ob_start();
$editor->render_list_filters( 'bci_opportunity', 'top' );
$filter_markup = ob_get_clean();

foreach (
	array(
		'crh_bci_date_filter',
		'crh_bci_member_filter',
		'crh_bci_type_filter',
		'American Indian Community Center',
		'Grant / RFP',
	) as $expected_fragment
) {
	if ( false === strpos( $filter_markup, $expected_fragment ) ) {
		fwrite( STDERR, "Expected the opportunity list filters to include {$expected_fragment}.\n" );
		exit( 1 );
	}
}

$_POST = array(
	'crh_bci_opportunity_editor_nonce' => 'nonce-value',
	'wm_bci_opportunity_type_term_id'  => '18',
);

$editor->save_meta_box( 55, (object) array( 'ID' => 55, 'post_type' => 'bci_opportunity' ) );

if (
	empty( $GLOBALS['crh_set_terms_calls'] )
	|| 18 !== (int) ( $GLOBALS['crh_set_terms_calls'][0]['terms'][0] ?? 0 )
) {
	fwrite( STDERR, "Expected the opportunity editor metabox save handler to update the taxonomy term.\n" );
	exit( 1 );
}

if ( 'Grant / RFP' !== ( $GLOBALS['crh_post_meta'][55]['wm_bci_opportunity_type'] ?? '' ) ) {
	fwrite( STDERR, "Expected the opportunity editor metabox save handler to normalize the saved type meta to the term name.\n" );
	exit( 1 );
}

class CrhOpportunityEditorQueryStub {
	public $vars = array(
		'post_type' => 'bci_opportunity',
	);

	public function is_main_query() {
		return true;
	}

	public function get( $key ) {
		return $this->vars[ $key ] ?? '';
	}

	public function set( $key, $value ) {
		$this->vars[ $key ] = $value;
	}
}

$_GET = array(
	'crh_bci_date_filter'   => 'upcoming',
	'crh_bci_member_filter' => 'american-indian-community-center',
	'crh_bci_type_filter'   => '18',
);

$query = new CrhOpportunityEditorQueryStub();
$editor->filter_admin_query( $query );

$meta_query = $query->vars['meta_query'] ?? array();
$tax_query  = $query->vars['tax_query'] ?? array();

if ( empty( $meta_query ) || empty( $tax_query ) ) {
	fwrite( STDERR, "Expected selected opportunity list filters to add meta and taxonomy query clauses.\n" );
	exit( 1 );
}

$serialized_meta_query = json_encode( $meta_query );
$serialized_tax_query  = json_encode( $tax_query );

if (
	false === strpos( $serialized_meta_query, 'wm_bci_start_date' )
	|| false === strpos( $serialized_meta_query, 'wm_bci_grant_deadline' )
	|| false === strpos( $serialized_meta_query, 'wm_bci_organization' )
	|| false === strpos( $serialized_meta_query, 'American Indian Community Center' )
	|| false === strpos( $serialized_meta_query, 'AICC' )
) {
	fwrite( STDERR, "Expected date and member filters to constrain opportunity meta values.\n" );
	exit( 1 );
}

if ( false === strpos( $serialized_tax_query, 'opportunity-type' ) || false === strpos( $serialized_tax_query, '18' ) ) {
	fwrite( STDERR, "Expected the selected opportunity type filter to constrain the opportunity-type taxonomy.\n" );
	exit( 1 );
}

echo "Opportunity editor metabox test passed.\n";
