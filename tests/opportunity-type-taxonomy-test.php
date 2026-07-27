<?php
/**
 * Regression checks for default opportunity-type seeding and legacy numeric value handling.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options']            = array();
$GLOBALS['crh_terms']              = array();
$GLOBALS['crh_term_meta']          = array();
$GLOBALS['crh_registered_actions'] = array();
$GLOBALS['crh_registered_taxonomies'] = array();
$GLOBALS['crh_post_meta']          = array(
	201 => array(
		'wm_bci_opportunity_type' => '15',
	),
	202 => array(
		'wm_bci_opportunity_type' => 'Grant / RFP',
	),
);
$GLOBALS['crh_set_terms_calls']    = array();

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

if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( $taxonomy ) {
		return false;
	}
}

if ( ! function_exists( 'register_taxonomy' ) ) {
	function register_taxonomy( $taxonomy, $object_type, $args = array() ) {
		$GLOBALS['crh_registered_taxonomies'][ $taxonomy ] = array(
			'object_type' => $object_type,
			'args'        => $args,
		);
		return true;
	}
}

if ( ! function_exists( 'register_term_meta' ) ) {
	function register_term_meta( $taxonomy, $meta_key, $args = array() ) {
		return true;
	}
}

if ( ! function_exists( 'term_exists' ) ) {
	function term_exists( $term, $taxonomy = '', $parent_term = null ) {
		foreach ( $GLOBALS['crh_terms'] as $term_id => $row ) {
			if ( $taxonomy && ( $row['taxonomy'] ?? '' ) !== $taxonomy ) {
				continue;
			}

			if ( $row['slug'] === $term || $row['name'] === $term ) {
				return array(
					'term_id' => $term_id,
				);
			}
		}

		return 0;
	}
}

if ( ! function_exists( 'wp_insert_term' ) ) {
	function wp_insert_term( $term, $taxonomy, $args = array() ) {
		$term_id = count( $GLOBALS['crh_terms'] ) + 1;

		$GLOBALS['crh_terms'][ $term_id ] = array(
			'term_id'  => $term_id,
			'name'     => (string) $term,
			'slug'     => (string) ( $args['slug'] ?? '' ),
			'taxonomy' => (string) $taxonomy,
		);

		return array(
			'term_id' => $term_id,
		);
	}
}

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $meta_key, $single = false ) {
		return $GLOBALS['crh_term_meta'][ $term_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'update_term_meta' ) ) {
	function update_term_meta( $term_id, $meta_key, $meta_value, $prev_value = '' ) {
		$GLOBALS['crh_term_meta'][ $term_id ][ $meta_key ] = $meta_value;
		return true;
	}
}

if ( ! function_exists( 'metadata_exists' ) ) {
	function metadata_exists( $meta_type, $object_id, $meta_key ) {
		if ( 'term' !== $meta_type ) {
			return false;
		}

		return array_key_exists( $meta_key, $GLOBALS['crh_term_meta'][ $object_id ] ?? array() );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['crh_options'][ $option ] ?? $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['crh_options'][ $option ] = $value;
		return true;
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

			$terms[] = $term;
		}

		return $terms;
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
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		return array( 201, 202 );
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

if ( ! function_exists( 'wp_set_post_terms' ) ) {
	function wp_set_post_terms( $post_id, $terms, $taxonomy, $append = false ) {
		$GLOBALS['crh_set_terms_calls'][] = array(
			'post_id'  => $post_id,
			'terms'    => $terms,
			'taxonomy' => $taxonomy,
			'append'   => $append,
		);
		return $terms;
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/content-model/class-taxonomy.php';

$taxonomy = new WatersMeet\CommunityResourcesHub\ContentModel\Taxonomy();
$taxonomy->register_taxonomy();
$taxonomy->maybe_seed_default_terms();
$taxonomy->ensure_default_opportunity_tags();

if ( 7 !== count( $GLOBALS['crh_terms'] ) ) {
	fwrite( STDERR, "Expected six primary opportunity types and one BCI Update tag to be seeded.\n" );
	exit( 1 );
}

if (
	empty( $GLOBALS['crh_registered_taxonomies']['opportunity-type'] )
	|| empty( $GLOBALS['crh_registered_taxonomies']['opportunity-tag'] )
) {
	fwrite( STDERR, "Expected both opportunity-type and opportunity-tag taxonomies to be registered.\n" );
	exit( 1 );
}

$opportunity_post_type_args = WatersMeet\CommunityResourcesHub\ContentModel\Schema::opportunity_post_type_args();

if ( array( 'opportunity-type', 'opportunity-tag' ) !== ( $opportunity_post_type_args['taxonomies'] ?? array() ) ) {
	fwrite( STDERR, "Expected the opportunity post type to declare both plugin-owned taxonomies.\n" );
	exit( 1 );
}

$tag_args = WatersMeet\CommunityResourcesHub\ContentModel\Schema::opportunity_tag_taxonomy_args();

if ( empty( $tag_args['show_ui'] ) || empty( $tag_args['show_admin_column'] ) || ! empty( $tag_args['hierarchical'] ) ) {
	fwrite( STDERR, "Expected opportunity tags to be admin-visible and non-hierarchical.\n" );
	exit( 1 );
}

$opportunity_meta = WatersMeet\CommunityResourcesHub\ContentModel\Schema::opportunity_meta_definitions();

if ( empty( $opportunity_meta['wm_bci_submitted_at'] ) ) {
	fwrite( STDERR, "Expected submitted_at to be registered as plugin-owned opportunity meta.\n" );
	exit( 1 );
}

if (
	'esc_url_raw' !== ( $opportunity_meta['wm_bci_info_url']['sanitize_callback'] ?? '' )
	|| 'sanitize_textarea_field' !== ( $opportunity_meta['wm_bci_address']['sanitize_callback'] ?? '' )
	|| 'sanitize_textarea_field' !== ( $opportunity_meta['wm_bci_file_upload']['sanitize_callback'] ?? '' )
) {
	fwrite( STDERR, "Expected URL and multiline opportunity meta to preserve their real data shapes.\n" );
	exit( 1 );
}

$terms_by_slug = array();

foreach ( $GLOBALS['crh_terms'] as $term_id => $term ) {
	$terms_by_slug[ $term['taxonomy'] . ':' . $term['slug'] ] = $term_id;
}

foreach (
	array(
		'learning'   => array( 'alias' => 'Learning' ),
		'grant-rfp'  => array( 'alias' => 'Grant/RFP' ),
		'event'      => array( 'alias' => 'Events' ),
		'resource'           => array( 'alias' => 'Resources' ),
		'recommended-vendor' => array(
			'alias' => 'Recommended Vendors',
			'color' => '#7e5f8e',
		),
		'other'              => array( 'alias' => '' ),
	) as $slug => $expected
) {
	$term_key = 'opportunity-type:' . $slug;

	if ( empty( $terms_by_slug[ $term_key ] ) ) {
		fwrite( STDERR, "Missing seeded term for slug {$slug}.\n" );
		exit( 1 );
	}

	$term_id = $terms_by_slug[ $term_key ];

	if ( $expected['alias'] !== ( $GLOBALS['crh_term_meta'][ $term_id ]['alias'] ?? '' ) ) {
		fwrite( STDERR, "Expected alias meta for {$slug} to be seeded.\n" );
		exit( 1 );
	}

	if ( isset( $expected['color'] ) && $expected['color'] !== ( $GLOBALS['crh_term_meta'][ $term_id ]['color'] ?? '' ) ) {
		fwrite( STDERR, "Expected color meta for {$slug} to be seeded.\n" );
		exit( 1 );
	}

}

if ( empty( $terms_by_slug['opportunity-tag:bci-update'] ) ) {
	fwrite( STDERR, "Expected BCI Update to be seeded as an opportunity tag.\n" );
	exit( 1 );
}

if ( ! empty( $terms_by_slug['opportunity-type:bci-update'] ) ) {
	fwrite( STDERR, "Expected BCI Update to be removed from primary opportunity types.\n" );
	exit( 1 );
}

$config = new WatersMeet\CommunityResourcesHub\Config\Config();

if ( 'Learning' !== $config->calendar_event_type_label( '15' ) ) {
	fwrite( STDERR, "Expected legacy numeric type 15 to resolve to Learning.\n" );
	exit( 1 );
}

if ( 'grant-rfp' !== $config->calendar_event_type_slug( '16' ) ) {
	fwrite( STDERR, "Expected legacy numeric type 16 to resolve to grant-rfp.\n" );
	exit( 1 );
}

if ( 'Recommended Vendors' !== $config->calendar_event_type_label( 'Recommended Vendor' ) ) {
	fwrite( STDERR, "Expected Recommended Vendor to resolve to its public plural alias.\n" );
	exit( 1 );
}

if ( 'recommended-vendor' !== $config->calendar_event_type_slug( 'Recommended Vendor' ) ) {
	fwrite( STDERR, "Expected Recommended Vendor to resolve to the canonical taxonomy slug.\n" );
	exit( 1 );
}

$taxonomy->maybe_sync_existing_opportunity_types();

if ( 'Workshop, Training, or Other Learning' !== ( $GLOBALS['crh_post_meta'][201]['wm_bci_opportunity_type'] ?? '' ) ) {
	fwrite( STDERR, "Expected numeric legacy opportunity type meta to be normalized to the seeded term name.\n" );
	exit( 1 );
}

if ( 2 !== count( $GLOBALS['crh_set_terms_calls'] ) ) {
	fwrite( STDERR, "Expected existing opportunities to have opportunity-type terms backfilled.\n" );
	exit( 1 );
}

if ( 'opportunity-type' !== ( $GLOBALS['crh_set_terms_calls'][0]['taxonomy'] ?? '' ) ) {
	fwrite( STDERR, "Expected backfilled terms to be assigned on the opportunity-type taxonomy.\n" );
	exit( 1 );
}

if ( empty( $GLOBALS['crh_options']['community_resources_hub_opportunity_type_sync_completed_at'] ) ) {
	fwrite( STDERR, "Expected opportunity-type sync completion marker option to be written.\n" );
	exit( 1 );
}

$learning_term_id = $terms_by_slug['opportunity-type:learning'];
$GLOBALS['crh_term_meta'][ $learning_term_id ]['alias'] = '';
$taxonomy->maybe_seed_default_terms();

if ( '' !== ( $GLOBALS['crh_term_meta'][ $learning_term_id ]['alias'] ?? null ) ) {
	fwrite( STDERR, "Expected a manually cleared alias meta value to stay blank after term seeding runs again.\n" );
	exit( 1 );
}

if ( 'Workshop, Training, or Other Learning' !== $config->calendar_event_type_label( '15' ) ) {
	fwrite( STDERR, "Expected a cleared alias to make the type label fall back to the term name.\n" );
	exit( 1 );
}

echo "Opportunity-type taxonomy seed and sync test passed.\n";
