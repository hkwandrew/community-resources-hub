<?php
/**
 * Regression checks for canonical opportunity repository lookups.
 *
 * @package CommunityResourcesHub
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['crh_options'] = array(
	'options_wm_bci_form_id'                         => 5,
	'options_wm_bci_approval_field_id'               => '22',
	'options_wm_bci_field_map_time_sensitive'        => '24',
	'options_wm_bci_field_map_opportunity_type'      => '1',
	'options_wm_bci_field_map_non_date_sensitive_type' => '25',
	'options_wm_bci_field_map_bci_update'            => '26',
	'options_wm_bci_field_map_submitter_name'        => '3',
	'options_wm_bci_field_map_title'                 => '4',
	'options_wm_bci_field_map_organization'          => '5',
	'options_wm_bci_field_map_start_date'            => '6',
	'options_wm_bci_field_map_grant_deadline'        => '9',
	'options_wm_bci_field_map_end_date'              => '10',
	'options_wm_bci_field_map_start_time'            => '12',
	'options_wm_bci_field_map_end_time'              => '21',
	'options_wm_bci_field_map_cost'                  => '14',
	'options_wm_bci_field_map_address'               => '15',
	'options_wm_bci_field_map_location_mode'         => '16',
	'options_wm_bci_field_map_description'           => '17',
	'options_wm_bci_field_map_info_url'              => '18',
	'options_wm_bci_field_map_file_upload'           => '19',
	'options_wm_bci_field_map_approval_status'       => '22',
	'options_wm_bci_calendar_page_slug'              => 'bci-resources',
	'options_wm_bci_calendar_feed_name'              => 'BCI Feed',
	'options_wm_bci_calendar_shortcode'              => '[gravitycalendar id="7"]',
	'options_wm_bci_calendar_feed_id'                => 7,
);
$GLOBALS['crh_posts'] = array(
	20 => array(
		'ID'         => 20,
		'post_type'  => 'bci_opportunity',
		'post_status'=> 'publish',
		'post_date'  => '2026-06-25 04:13:00',
		'post_title' => 'Newer duplicate',
	),
	10 => array(
		'ID'         => 10,
		'post_type'  => 'bci_opportunity',
		'post_status'=> 'publish',
		'post_date'  => '2026-06-23 08:27:00',
		'post_title' => 'Older canonical',
	),
);
$GLOBALS['crh_post_meta'] = array(
	10 => array(
		'wm_bci_source_entry_id' => '500',
		'wm_bci_approval_status' => 'Approved',
	),
	20 => array(
		'wm_bci_source_entry_id' => '500',
		'wm_bci_approval_status' => 'Approved',
	),
);
$GLOBALS['crh_updated_posts']   = array();
$GLOBALS['crh_inserted_posts']  = array();
$GLOBALS['crh_updated_meta']    = array();
$GLOBALS['crh_deleted_meta']    = array();
$GLOBALS['crh_set_terms_calls'] = array();
$GLOBALS['crh_add_terms_calls'] = array();
$GLOBALS['crh_remove_terms_calls'] = array();
$GLOBALS['crh_terms']           = array(
	17 => array(
		'term_id'  => 17,
		'name'     => 'Workshop, Training, or Other Learning',
		'slug'     => 'learning',
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
		'alias' => 'Learning',
		'color' => '#520066',
	),
);

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
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

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = array() ) {
		if ( empty( $args['meta_key'] ) || 'wm_bci_source_entry_id' !== $args['meta_key'] ) {
			return array();
		}

		$matches = array();

		foreach ( $GLOBALS['crh_post_meta'] as $post_id => $meta ) {
			if ( (string) ( $meta['wm_bci_source_entry_id'] ?? '' ) !== (string) ( $args['meta_value'] ?? '' ) ) {
				continue;
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

		if ( ! empty( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
			return array_slice( $matches, 0, (int) $args['posts_per_page'] );
		}

		return $matches;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		$GLOBALS['crh_updated_posts'][] = $postarr;
		$post_id = absint( $postarr['ID'] ?? 0 );
		$GLOBALS['crh_posts'][ $post_id ] = array_merge( $GLOBALS['crh_posts'][ $post_id ] ?? array(), $postarr );
		return $post_id;
	}
}

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
		$GLOBALS['crh_inserted_posts'][] = $postarr;
		return 999;
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
		$GLOBALS['crh_set_terms_calls'][] = array(
			'post_id'  => $post_id,
			'terms'    => $terms,
			'taxonomy' => $taxonomy,
		);
		return $terms;
	}
}

if ( ! function_exists( 'wp_add_object_terms' ) ) {
	function wp_add_object_terms( $object_id, $terms, $taxonomy ) {
		$GLOBALS['crh_add_terms_calls'][] = array( $object_id, $terms, $taxonomy );
		return array( $terms );
	}
}

if ( ! function_exists( 'wp_remove_object_terms' ) ) {
	function wp_remove_object_terms( $object_id, $terms, $taxonomy ) {
		$GLOBALS['crh_remove_terms_calls'][] = array( $object_id, $terms, $taxonomy );
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

require_once dirname( __DIR__ ) . '/includes/content-model/class-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-settings-schema.php';
require_once dirname( __DIR__ ) . '/includes/config/class-config.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-field-accessor.php';
require_once dirname( __DIR__ ) . '/includes/workflow/class-opportunity-repository.php';

$repository = new WatersMeet\CommunityResourcesHub\Workflow\OpportunityRepository(
	new WatersMeet\CommunityResourcesHub\Config\Config()
);

$post_id = $repository->upsert_from_entry(
	array(
		'id'           => 500,
		'date_created' => '2026-07-13 05:30:00',
		'1'            => 'Learning',
		'3'            => 'Submitter',
		'4'            => 'Updated opportunity title',
		'5'            => 'Organization',
		'6'            => '2026-07-01',
		'17'           => 'Fresh description',
		'18'           => 'https://example.com/info',
	),
	'Approved'
);

if ( 10 !== $post_id ) {
	fwrite( STDERR, "Expected upsert_from_entry() to update the oldest canonical source-entry post.\n" );
	exit( 1 );
}

if ( 1 !== count( $GLOBALS['crh_updated_posts'] ) || 10 !== (int) ( $GLOBALS['crh_updated_posts'][0]['ID'] ?? 0 ) ) {
	fwrite( STDERR, "Expected only the oldest duplicate to be updated.\n" );
	exit( 1 );
}

if ( ! empty( $GLOBALS['crh_inserted_posts'] ) ) {
	fwrite( STDERR, "Expected no new post to be inserted for a duplicate source entry.\n" );
	exit( 1 );
}

if ( 'Workshop, Training, or Other Learning' !== ( $GLOBALS['crh_post_meta'][10]['wm_bci_opportunity_type'] ?? '' ) ) {
	fwrite( STDERR, "Expected opportunity type meta to be normalized to the full taxonomy term name.\n" );
	exit( 1 );
}

if ( '2026-07-13 05:30:00' !== ( $GLOBALS['crh_post_meta'][10]['wm_bci_submitted_at'] ?? '' ) ) {
	fwrite( STDERR, "Expected the Gravity Forms creation timestamp to be stored as UTC submitted_at meta.\n" );
	exit( 1 );
}

if (
	empty( $GLOBALS['crh_set_terms_calls'] )
	|| 10 !== (int) ( $GLOBALS['crh_set_terms_calls'][0]['post_id'] ?? 0 )
	|| array( 17 ) !== array_values( $GLOBALS['crh_set_terms_calls'][0]['terms'] ?? array() )
) {
	fwrite( STDERR, "Expected the canonical post to receive the normalized opportunity-type term assignment.\n" );
	exit( 1 );
}

if (
	array( 10, 'bci-update', 'opportunity-tag' ) !== ( $GLOBALS['crh_remove_terms_calls'][0] ?? array() )
	|| ! empty( $GLOBALS['crh_add_terms_calls'] )
) {
	fwrite( STDERR, "Expected a non-BCI entry to remove only the BCI Update tag.\n" );
	exit( 1 );
}

$repository->upsert_from_entry(
	array(
		'id'           => 500,
		'date_created' => '2026-07-13 05:30:00',
		'24'           => 'No',
		'1'            => 'Grant / RFP',
		'25'           => 'Resource',
		'26'           => 'Yes',
		'4'            => 'Branch-aware resource',
	),
	'Approved'
);

if ( 'Resource' !== ( $GLOBALS['crh_post_meta'][10]['wm_bci_opportunity_type'] ?? '' ) ) {
	fwrite( STDERR, "Expected a No branch to ignore stale field 1 and persist field 25.\n" );
	exit( 1 );
}

if ( array( 10, 'bci-update', 'opportunity-tag' ) !== ( $GLOBALS['crh_add_terms_calls'][0] ?? array() ) ) {
	fwrite( STDERR, "Expected a BCI Update answer to append only the BCI Update tag.\n" );
	exit( 1 );
}

echo "Opportunity repository canonical lookup test passed.\n";
