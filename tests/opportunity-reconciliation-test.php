<?php
/**
 * Regression checks for legacy opportunity reconciliation.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_form_id'                   => 5,
	'options_wm_bci_approval_field_id'         => '22',
	'options_wm_bci_field_map_opportunity_type'=> '1',
	'options_wm_bci_field_map_submitter_name'  => '3',
	'options_wm_bci_field_map_title'           => '4',
	'options_wm_bci_field_map_organization'    => '5',
	'options_wm_bci_field_map_start_date'      => '6',
	'options_wm_bci_field_map_grant_deadline'  => '9',
	'options_wm_bci_field_map_end_date'        => '10',
	'options_wm_bci_field_map_start_time'      => '12',
	'options_wm_bci_field_map_end_time'        => '21',
	'options_wm_bci_field_map_cost'            => '14',
	'options_wm_bci_field_map_address'         => '15',
	'options_wm_bci_field_map_location_mode'   => '16',
	'options_wm_bci_field_map_description'     => '17',
	'options_wm_bci_field_map_info_url'        => '18',
	'options_wm_bci_field_map_file_upload'     => '19',
	'options_wm_bci_field_map_approval_status' => '22',
);
$GLOBALS['crh_posts'] = array(
	101 => array(
		'ID'          => 101,
		'post_type'   => 'bci_opportunity',
		'post_status' => 'publish',
		'post_date'   => '2026-06-23 08:27:29',
		'post_title'  => 'Legacy canonical duplicate',
		'post_content'=> 'Old content',
	),
	102 => array(
		'ID'          => 102,
		'post_type'   => 'bci_opportunity',
		'post_status' => 'publish',
		'post_date'   => '2026-06-25 04:13:12',
		'post_title'  => 'Legacy newer duplicate',
		'post_content'=> 'Newer duplicate content',
	),
	103 => array(
		'ID'          => 103,
		'post_type'   => 'bci_opportunity',
		'post_status' => 'publish',
		'post_date'   => '2026-06-24 09:00:00',
		'post_title'  => 'Source-less unresolved row',
		'post_content'=> 'Needs review',
	),
	104 => array(
		'ID'          => 104,
		'post_type'   => 'bci_opportunity',
		'post_status' => 'auto-draft',
		'post_date'   => '2026-06-26 09:00:00',
		'post_title'  => 'Auto Draft Placeholder',
		'post_content'=> '',
	),
);
$GLOBALS['crh_post_meta'] = array(
	101 => array(
		'wm_bci_source_entry_id' => '501',
		'wm_bci_approval_status' => 'Approved',
		'wm_bci_opportunity_type'=> 'Event',
	),
	102 => array(
		'wm_bci_source_entry_id' => '501',
		'wm_bci_approval_status' => 'Approved',
		'wm_bci_opportunity_type'=> 'Resource',
		'wm_bci_google_sync_status' => 'synced',
	),
	103 => array(
		'wm_bci_source_entry_id' => '',
		'wm_bci_approval_status' => 'Approved',
		'wm_bci_opportunity_type'=> 'Other',
	),
);
$GLOBALS['crh_term_relationships'] = array(
	101 => array( 17 ),
	102 => array( 18 ),
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
		'name'     => 'Resource',
		'slug'     => 'resource',
		'taxonomy' => 'opportunity-type',
	),
);
$GLOBALS['crh_term_meta'] = array(
	17 => array(
		'alias' => 'Events',
		'color' => '#c2385a',
	),
	18 => array(
		'alias' => 'Resources',
		'color' => '#418359',
	),
);
$GLOBALS['crh_updated_posts']   = array();
$GLOBALS['crh_inserted_posts']  = array();
$GLOBALS['crh_updated_meta']    = array();
$GLOBALS['crh_deleted_meta']    = array();
$GLOBALS['crh_set_terms_calls'] = array();
$GLOBALS['crh_trashed_posts']   = array();
$GLOBALS['crh_registered_actions'] = array();

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

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return strip_tags( (string) $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $html ) {
		return preg_replace( '#<script[^>]*>.*?</script>#is', '', (string) $html );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url, $protocols = null ) {
		return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return array_key_exists( $option, $GLOBALS['crh_options'] ) ? $GLOBALS['crh_options'][ $option ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		$GLOBALS['crh_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ) {
		$GLOBALS['crh_options'][ $option ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		unset( $GLOBALS['crh_options'][ $option ] );
		return true;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		$matches = array();

		foreach ( $GLOBALS['crh_posts'] as $post_id => $post ) {
			if ( isset( $args['post_type'] ) && $post['post_type'] !== $args['post_type'] ) {
				continue;
			}

			if ( isset( $args['post_status'] ) && 'any' !== $args['post_status'] && $post['post_status'] !== $args['post_status'] ) {
				continue;
			}

			if ( ! empty( $args['meta_key'] ) ) {
				$meta_value = $GLOBALS['crh_post_meta'][ $post_id ][ $args['meta_key'] ] ?? '';

				if ( (string) $meta_value !== (string) ( $args['meta_value'] ?? '' ) ) {
					continue;
				}
			}

			$matches[] = $post_id;
		}

		if ( isset( $args['orderby']['date'], $args['orderby']['ID'] ) ) {
			usort(
				$matches,
				static function ( $left, $right ) {
					$left_date  = $GLOBALS['crh_posts'][ $left ]['post_date'] ?? '';
					$right_date = $GLOBALS['crh_posts'][ $right ]['post_date'] ?? '';

					if ( $left_date === $right_date ) {
						return $left <=> $right;
					}

					return strcmp( $left_date, $right_date );
				}
			);
		}

		if ( isset( $args['orderby']['date'] ) && 'DESC' === strtoupper( (string) $args['orderby']['date'] ) ) {
			usort(
				$matches,
				static function ( $left, $right ) {
					$left_date  = $GLOBALS['crh_posts'][ $left ]['post_date'] ?? '';
					$right_date = $GLOBALS['crh_posts'][ $right ]['post_date'] ?? '';

					if ( $left_date === $right_date ) {
						return $right <=> $left;
					}

					return strcmp( $right_date, $left_date );
				}
			);
		}

		if ( ! empty( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
			return array_slice( $matches, 0, (int) $args['posts_per_page'] );
		}

		return $matches;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( $post_id ) {
		return isset( $GLOBALS['crh_posts'][ $post_id ] ) ? (object) $GLOBALS['crh_posts'][ $post_id ] : null;
	}
}

if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( $field, $post_id, $context = 'display' ) {
		return $GLOBALS['crh_posts'][ $post_id ][ $field ] ?? '';
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( $post_id ) {
		return $GLOBALS['crh_posts'][ $post_id ]['post_title'] ?? '';
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		$post_id = absint( $postarr['ID'] ?? 0 );
		$GLOBALS['crh_updated_posts'][] = $postarr;
		$GLOBALS['crh_posts'][ $post_id ] = array_merge( $GLOBALS['crh_posts'][ $post_id ] ?? array(), $postarr );
		return $post_id;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		$post_id = 999;
		$postarr['ID'] = $post_id;
		$GLOBALS['crh_inserted_posts'][] = $postarr;
		$GLOBALS['crh_posts'][ $post_id ] = $postarr + array(
			'post_type'   => 'bci_opportunity',
			'post_status' => 'publish',
			'post_date'   => '2026-06-26 00:00:00',
		);
		return $post_id;
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

		if ( 'names' === ( $args['fields'] ?? '' ) ) {
			return array_values(
				array_map(
					static function ( $term_id ) {
						return $GLOBALS['crh_terms'][ $term_id ]['name'] ?? '';
					},
					$term_ids
				)
			);
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

if ( ! function_exists( 'wp_trash_post' ) ) {
	function wp_trash_post( $post_id ) {
		$GLOBALS['crh_trashed_posts'][] = $post_id;

		if ( isset( $GLOBALS['crh_posts'][ $post_id ] ) ) {
			$GLOBALS['crh_posts'][ $post_id ]['post_status'] = 'trash';
		}

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
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

if ( ! function_exists( 'get_term_meta' ) ) {
	function get_term_meta( $term_id, $meta_key, $single = false ) {
		return $GLOBALS['crh_term_meta'][ $term_id ][ $meta_key ] ?? '';
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! class_exists( 'GFAPI' ) ) {
	class GFAPI {
		public static function get_entry( $entry_id ) {
			$entries = array(
				501 => array(
					'id' => 501,
					'1'  => 'Event',
					'3'  => 'Submitter',
					'4'  => 'Refreshed canonical title',
					'5'  => 'Org',
					'6'  => '2026-07-04',
					'17' => 'Canonical content refreshed from entry',
					'18' => 'https://example.com/event',
					'22' => 'Approved',
				),
			);

			return $entries[ $entry_id ] ?? array();
		}

		public static function get_entries( $form_id, $search_criteria = array(), $sorting = null, $paging = null, &$total_count = 0 ) {
			$total_count = 1;

			return array(
				array(
					'id' => 777,
					'1'  => 'Resource',
					'3'  => 'Submitter 777',
					'4'  => 'Imported approved entry',
					'5'  => 'Imported org',
					'6'  => '2026-07-05',
					'17' => 'Imported description',
					'18' => 'https://example.com/imported',
					'22' => 'Approved',
				),
			);
		}
	}
}

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-repository.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-reconciliation.php';

$config       = new WatersMeet\CommunityResourcesHub\Config\Config();
$repository   = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityRepository( $config );
$service      = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityReconciliation( $config, $repository );
$summary      = $service->reconcile();
$summary_opt  = $GLOBALS['crh_options'][ WatersMeet\CommunityResourcesHub\Workflow\OpportunityReconciliation::SUMMARY_OPTION ] ?? array();

if ( ! in_array( 102, $GLOBALS['crh_trashed_posts'], true ) ) {
	fwrite( STDERR, "Expected duplicate non-canonical opportunity posts to be moved to Trash.\n" );
	exit( 1 );
}

if ( 'Refreshed canonical title' !== ( $GLOBALS['crh_posts'][101]['post_title'] ?? '' ) ) {
	fwrite( STDERR, "Expected the canonical post title to be refreshed from the live Gravity Forms entry.\n" );
	exit( 1 );
}

if ( 'Event' !== ( $GLOBALS['crh_post_meta'][101]['wm_bci_opportunity_type'] ?? '' ) ) {
	fwrite( STDERR, "Expected the canonical post type meta to normalize to the taxonomy term name.\n" );
	exit( 1 );
}

if ( empty( $GLOBALS['crh_inserted_posts'] ) || 'Imported approved entry' !== ( $GLOBALS['crh_inserted_posts'][0]['post_title'] ?? '' ) ) {
	fwrite( STDERR, "Expected approved GF entries missing from the database to be imported.\n" );
	exit( 1 );
}

if ( 1 !== (int) ( $summary['duplicates_trashed'] ?? 0 ) || 1 !== (int) ( $summary['imported_entries'] ?? 0 ) ) {
	fwrite( STDERR, "Expected reconciliation summary counts for trashed duplicates and imported entries.\n" );
	exit( 1 );
}

if ( 1 !== (int) ( $summary['unresolved_posts'] ?? 0 ) || 1 !== (int) ( $summary_opt['unresolved_posts'] ?? 0 ) ) {
	fwrite( STDERR, "Expected source-less legacy opportunities to be surfaced as unresolved.\n" );
	exit( 1 );
}

echo "Opportunity reconciliation test passed.\n";
