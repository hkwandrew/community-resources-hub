<?php
/**
 * Plugin-owned BCI opportunity editor UI.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

use WatersMeet\CommunityResourcesHub\Config\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the primary opportunity type editor UI and list-table diagnostics.
 */
final class OpportunityEditor {

	const META_BOX_ID = 'crh_bci_opportunity_details';
	const NONCE_NAME  = 'crh_bci_opportunity_editor_nonce';
	const NONCE_ACTION = 'crh_bci_opportunity_editor_save';
	const RECONCILIATION_ACTION = 'wm_bci_reconcile_opportunities';
	const RECONCILIATION_NONCE_ACTION = 'wm_bci_reconcile_opportunities';
	const DATE_FILTER_PARAM = 'crh_bci_date_filter';
	const MEMBER_FILTER_PARAM = 'crh_bci_member_filter';
	const TYPE_FILTER_PARAM = 'crh_bci_type_filter';

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Cached source-entry groups for list-table rendering.
	 *
	 * @var array<int,array<int,int>>
	 */
	private $source_entry_groups = array();

	public function __construct( ?Config $config = null ) {
		$this->config = $config ?: new Config();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . Schema::OPPORTUNITY_POST_TYPE, array( $this, 'register_meta_box' ) );
		add_action( 'save_post_' . Schema::OPPORTUNITY_POST_TYPE, array( $this, 'save_meta_box' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_list_filters' ), 9, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'render_reconciliation_action' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'filter_admin_query' ) );
		add_filter( 'manage_edit-' . Schema::OPPORTUNITY_POST_TYPE . '_columns', array( $this, 'filter_columns' ) );
		add_action( 'manage_' . Schema::OPPORTUNITY_POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
	}

	/**
	 * Register the dedicated opportunity details metabox.
	 *
	 * @return void
	 */
	public function register_meta_box() {
		add_meta_box(
			self::META_BOX_ID,
			__( 'Opportunity Details', 'community-resources-hub' ),
			array( $this, 'render_meta_box' ),
			Schema::OPPORTUNITY_POST_TYPE,
			'normal',
			'high'
		);
	}

	/**
	 * Render the editor metabox.
	 *
	 * @param \WP_Post|object $post Post object.
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$post_id         = absint( $post->ID ?? 0 );
		$source_entry_id = trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'source_entry_id' ), true ) );
		$approval_status = trim( (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'approval_status' ), true ) );
		$primary_date    = $this->primary_date_value( $post_id );
		$current_term_id = $this->current_term_id( $post_id );
		$terms           = function_exists( 'get_terms' )
			? get_terms(
				array(
					'taxonomy'   => $this->config->opportunity_type_taxonomy(),
					'hide_empty' => false,
				)
			)
			: array();

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		?>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php echo esc_html( __( 'Source Entry ID', 'community-resources-hub' ) ); ?></th>
					<td><?php echo '' !== $source_entry_id ? esc_html( $source_entry_id ) : esc_html( __( 'Missing source entry ID', 'community-resources-hub' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( __( 'Approval Status', 'community-resources-hub' ) ); ?></th>
					<td><?php echo '' !== $approval_status ? esc_html( $approval_status ) : esc_html( __( 'Unknown', 'community-resources-hub' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( __( 'Primary Date', 'community-resources-hub' ) ); ?></th>
					<td><?php echo '' !== $primary_date ? esc_html( $primary_date ) : esc_html( __( 'None', 'community-resources-hub' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm_bci_opportunity_type_term_id"><?php echo esc_html( __( 'Opportunity Type', 'community-resources-hub' ) ); ?></label></th>
					<td>
						<select id="wm_bci_opportunity_type_term_id" name="wm_bci_opportunity_type_term_id">
							<option value="0"><?php echo esc_html( __( 'Select an opportunity type', 'community-resources-hub' ) ); ?></option>
							<?php foreach ( is_array( $terms ) ? $terms : array() as $term ) : ?>
								<?php
								$term_id = absint( is_object( $term ) ? ( $term->term_id ?? 0 ) : ( $term['term_id'] ?? 0 ) );
								$name    = is_object( $term ) ? (string) ( $term->name ?? '' ) : (string) ( $term['name'] ?? '' );
								?>
								<option value="<?php echo esc_attr( (string) $term_id ); ?>" <?php echo selected( $current_term_id, $term_id, false ); ?>>
									<?php echo esc_html( $name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Save the editor metabox.
	 *
	 * @param int             $post_id Post ID.
	 * @param \WP_Post|object $post Post object.
	 * @return void
	 */
	public function save_meta_box( $post_id, $post ) {
		$post_id = absint( $post_id );

		if ( ! $post_id || Schema::OPPORTUNITY_POST_TYPE !== (string) ( $post->post_type ?? '' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( empty( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$term_id     = isset( $_POST['wm_bci_opportunity_type_term_id'] ) ? absint( wp_unslash( $_POST['wm_bci_opportunity_type_term_id'] ) ) : 0;
		$type_config = $this->config->opportunity_type_config( (string) $term_id );

		if ( $term_id > 0 && ! empty( $type_config['term_id'] ) ) {
			wp_set_post_terms(
				$post_id,
				array( absint( $type_config['term_id'] ) ),
				$this->config->opportunity_type_taxonomy(),
				false
			);
			update_post_meta( $post_id, $this->config->opportunity_field_name( 'opportunity_type' ), (string) $type_config['name'] );
			return;
		}

		wp_set_post_terms( $post_id, array(), $this->config->opportunity_type_taxonomy(), false );
		delete_post_meta( $post_id, $this->config->opportunity_field_name( 'opportunity_type' ) );
	}

	/**
	 * Render date, member, and opportunity type filters on the opportunity list screen.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $which     Table-nav location.
	 * @return void
	 */
	public function render_list_filters( $post_type = '', $which = '' ) {
		if ( Schema::OPPORTUNITY_POST_TYPE !== (string) $post_type || ( '' !== $which && 'top' !== (string) $which ) ) {
			return;
		}

		$selected_date   = $this->request_value( self::DATE_FILTER_PARAM );
		$selected_member = sanitize_title( $this->request_value( self::MEMBER_FILTER_PARAM ) );
		$selected_type   = absint( $this->request_value( self::TYPE_FILTER_PARAM ) );
		?>
		<label class="screen-reader-text" for="filter-by-bci-date"><?php echo esc_html( __( 'Filter by BCI date', 'community-resources-hub' ) ); ?></label>
		<select id="filter-by-bci-date" name="<?php echo esc_attr( self::DATE_FILTER_PARAM ); ?>">
			<?php foreach ( $this->date_filter_options() as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php echo selected( $selected_date, $value, false ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="filter-by-bci-member"><?php echo esc_html( __( 'Filter by BCI member', 'community-resources-hub' ) ); ?></label>
		<select id="filter-by-bci-member" name="<?php echo esc_attr( self::MEMBER_FILTER_PARAM ); ?>">
			<option value=""><?php echo esc_html( __( 'All BCI Members', 'community-resources-hub' ) ); ?></option>
			<?php foreach ( $this->member_filter_options() as $member ) : ?>
				<option value="<?php echo esc_attr( $member['slug'] ); ?>" <?php echo selected( $selected_member, $member['slug'], false ); ?>>
					<?php echo esc_html( $member['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<label class="screen-reader-text" for="filter-by-bci-opportunity-type"><?php echo esc_html( __( 'Filter by opportunity type', 'community-resources-hub' ) ); ?></label>
		<select id="filter-by-bci-opportunity-type" name="<?php echo esc_attr( self::TYPE_FILTER_PARAM ); ?>">
			<option value="0"><?php echo esc_html( __( 'All Opportunity Types', 'community-resources-hub' ) ); ?></option>
			<?php foreach ( $this->opportunity_type_options() as $term ) : ?>
				<option value="<?php echo esc_attr( (string) $term['term_id'] ); ?>" <?php echo selected( $selected_type, $term['term_id'], false ); ?>>
					<?php echo esc_html( $term['label'] ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Apply selected list-screen filters to the main BCI opportunity admin query.
	 *
	 * @param \WP_Query|object $query Query object.
	 * @return void
	 */
	public function filter_admin_query( $query ) {
		if ( ! $this->should_filter_admin_query( $query ) ) {
			return;
		}

		$meta_query = $this->query_clauses( $query->get( 'meta_query' ) );
		$tax_query  = $this->query_clauses( $query->get( 'tax_query' ) );
		$date_clause = $this->date_filter_meta_clause( $this->request_value( self::DATE_FILTER_PARAM ) );

		if ( ! empty( $date_clause ) ) {
			$meta_query[] = $date_clause;
		}

		$member_values = $this->member_filter_values( sanitize_title( $this->request_value( self::MEMBER_FILTER_PARAM ) ) );

		if ( ! empty( $member_values ) ) {
			$meta_query[] = array(
				'key'     => $this->config->opportunity_field_name( 'organization' ),
				'value'   => array_values( $member_values ),
				'compare' => 'IN',
			);
		}

		$type_term_id = absint( $this->request_value( self::TYPE_FILTER_PARAM ) );

		if ( $type_term_id > 0 ) {
			$tax_query[] = array(
				'taxonomy' => $this->config->opportunity_type_taxonomy(),
				'field'    => 'term_id',
				'terms'    => array( $type_term_id ),
			);
		}

		if ( ! empty( $meta_query ) ) {
			$query->set( 'meta_query', $meta_query );
		}

		if ( ! empty( $tax_query ) ) {
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Render the always-available legacy reconciliation action on the list screen.
	 *
	 * @param string $post_type Post type slug.
	 * @param string $which     Table-nav location.
	 * @return void
	 */
	public function render_reconciliation_action( $post_type = '', $which = '' ) {
		if ( Schema::OPPORTUNITY_POST_TYPE !== (string) $post_type || ( '' !== $which && 'top' !== (string) $which ) ) {
			return;
		}

		$url = admin_url(
			'admin-post.php?action=' . self::RECONCILIATION_ACTION
			. '&_wpnonce=' . rawurlencode( wp_create_nonce( self::RECONCILIATION_NONCE_ACTION ) )
		);
		?>
		<div class="alignleft actions">
			<a class="button" href="<?php echo esc_attr( $url ); ?>" style="margin-left:8px;">
				<?php echo esc_html( __( 'Reconcile Legacy Opportunities', 'community-resources-hub' ) ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Add source-entry and reconciliation columns to the list table.
	 *
	 * @param array<string,string> $columns Existing columns.
	 * @return array<string,string>
	 */
	public function filter_columns( array $columns ) {
		$updated = array();

		foreach ( $columns as $key => $label ) {
			$updated[ $key ] = $label;

			if ( 'title' === $key ) {
				$updated['crh_bci_source_entry_id']       = __( 'Source Entry ID', 'community-resources-hub' );
				$updated['crh_bci_reconciliation_state']  = __( 'Reconciliation', 'community-resources-hub' );
			}
		}

		return $updated;
	}

	/**
	 * Render one custom list-table column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( $column, $post_id ) {
		$post_id         = absint( $post_id );
		$source_entry_id = absint( get_post_meta( $post_id, $this->config->opportunity_field_name( 'source_entry_id' ), true ) );

		if ( 'crh_bci_source_entry_id' === $column ) {
			echo $source_entry_id > 0 ? esc_html( (string) $source_entry_id ) : '&mdash;';
			return;
		}

		if ( 'crh_bci_reconciliation_state' !== $column ) {
			return;
		}

		if ( $source_entry_id < 1 ) {
			echo esc_html( __( 'Unresolved', 'community-resources-hub' ) );
			return;
		}

		$group        = $this->source_entry_group( $source_entry_id );
		$canonical_id = ! empty( $group ) ? absint( $group[0] ) : 0;

		if ( $canonical_id && $canonical_id !== $post_id ) {
			echo esc_html( __( 'Duplicate', 'community-resources-hub' ) );
			return;
		}

		if ( count( $group ) > 1 ) {
			echo esc_html( __( 'Canonical (duplicates found)', 'community-resources-hub' ) );
			return;
		}

		echo esc_html( __( 'Canonical', 'community-resources-hub' ) );
	}

	/**
	 * Canonical source-entry group for one entry ID.
	 *
	 * @param int $source_entry_id Entry ID.
	 * @return array<int,int>
	 */
	private function source_entry_group( $source_entry_id ) {
		if ( isset( $this->source_entry_groups[ $source_entry_id ] ) ) {
			return $this->source_entry_groups[ $source_entry_id ];
		}

		$posts = get_posts(
			array(
				'post_type'      => $this->config->opportunity_post_type(),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => $this->config->opportunity_field_name( 'source_entry_id' ),
				'meta_value'     => $source_entry_id,
				'orderby'        => array(
					'date' => 'ASC',
					'ID'   => 'ASC',
				),
			)
		);

		$this->source_entry_groups[ $source_entry_id ] = is_array( $posts ) ? array_map( 'absint', $posts ) : array();

		return $this->source_entry_groups[ $source_entry_id ];
	}

	/**
	 * Current selected term ID for the opportunity type editor.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	private function current_term_id( $post_id ) {
		if ( function_exists( 'wp_get_post_terms' ) ) {
			$term_ids = wp_get_post_terms(
				$post_id,
				$this->config->opportunity_type_taxonomy(),
				array(
					'fields' => 'ids',
				)
			);

			if ( is_array( $term_ids ) && ! empty( $term_ids ) ) {
				return absint( $term_ids[0] );
			}
		}

		$type_config = $this->config->opportunity_type_config(
			(string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'opportunity_type' ), true )
		);

		return absint( $type_config['term_id'] ?? 0 );
	}

	/**
	 * Whether a query belongs to the main BCI opportunity admin list table.
	 *
	 * @param \WP_Query|object $query Query object.
	 * @return bool
	 */
	private function should_filter_admin_query( $query ) {
		if ( ! is_object( $query ) || ! method_exists( $query, 'get' ) || ! method_exists( $query, 'set' ) ) {
			return false;
		}

		if ( function_exists( 'is_admin' ) && ! is_admin() ) {
			return false;
		}

		if ( method_exists( $query, 'is_main_query' ) && ! $query->is_main_query() ) {
			return false;
		}

		$post_type = $query->get( 'post_type' );

		if ( is_array( $post_type ) ) {
			return in_array( Schema::OPPORTUNITY_POST_TYPE, $post_type, true );
		}

		return Schema::OPPORTUNITY_POST_TYPE === (string) $post_type;
	}

	/**
	 * Date filter options for the opportunity list table.
	 *
	 * @return array<string,string>
	 */
	private function date_filter_options() {
		return array(
			''             => __( 'All BCI Dates', 'community-resources-hub' ),
			'upcoming'     => __( 'Upcoming Dates', 'community-resources-hub' ),
			'past'         => __( 'Past Dates', 'community-resources-hub' ),
			'this-month'   => __( 'This Month', 'community-resources-hub' ),
			'next-30-days' => __( 'Next 30 Days', 'community-resources-hub' ),
		);
	}

	/**
	 * Opportunity-type options for the opportunity list table.
	 *
	 * @return array<int,array{term_id:int,label:string}>
	 */
	private function opportunity_type_options() {
		if ( ! function_exists( 'get_terms' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => $this->config->opportunity_type_taxonomy(),
				'hide_empty' => false,
			)
		);

		$options = array();

		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$term_id = absint( is_object( $term ) ? ( $term->term_id ?? 0 ) : ( $term['term_id'] ?? 0 ) );
			$label   = $this->plain_text( is_object( $term ) ? ( $term->name ?? '' ) : ( $term['name'] ?? '' ) );

			if ( $term_id && '' !== $label ) {
				$options[] = array(
					'term_id' => $term_id,
					'label'   => $label,
				);
			}
		}

		return $options;
	}

	/**
	 * Member options for the opportunity list table.
	 *
	 * @return array<int,array{slug:string,label:string,values:array<int,string>}>
	 */
	private function member_filter_options() {
		if ( ! function_exists( 'get_posts' ) ) {
			return array();
		}

		$post_ids = get_posts(
			array(
				'post_type'      => Schema::MEMBER_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$options = array();

		foreach ( is_array( $post_ids ) ? $post_ids : array() as $post_id ) {
			$post_id = absint( $post_id );
			$label   = $this->plain_text( get_post_field( 'post_title', $post_id, 'raw' ) );

			if ( ! $post_id || '' === $label ) {
				continue;
			}

			$values = array_values(
				array_unique(
					array_filter(
						array_merge( array( $label ), $this->member_aliases( $post_id ) )
					)
				)
			);

			$options[] = array(
				'slug'   => sanitize_title( $label ),
				'label'  => $label,
				'values' => $values,
			);
		}

		return $options;
	}

	/**
	 * Organization meta values that map one selected member to opportunities.
	 *
	 * @param string $member_slug Selected member slug.
	 * @return array<int,string>
	 */
	private function member_filter_values( $member_slug ) {
		if ( '' === $member_slug ) {
			return array();
		}

		foreach ( $this->member_filter_options() as $member ) {
			if ( $member_slug === $member['slug'] ) {
				return $member['values'];
			}
		}

		return array();
	}

	/**
	 * Alias lines for one BCI member.
	 *
	 * @param int $post_id Member post ID.
	 * @return array<int,string>
	 */
	private function member_aliases( $post_id ) {
		$value = get_post_meta( $post_id, $this->config->member_field_name( 'aliases' ), true );

		if ( is_array( $value ) ) {
			return array_map( array( $this, 'plain_text' ), $value );
		}

		$lines = preg_split( '/\r\n|\r|\n/', (string) $value );

		return array_map( array( $this, 'plain_text' ), is_array( $lines ) ? $lines : array() );
	}

	/**
	 * Build a date meta query clause from the selected admin filter.
	 *
	 * @param string $selected_date Selected filter value.
	 * @return array<string,mixed>
	 */
	private function date_filter_meta_clause( $selected_date ) {
		if ( ! array_key_exists( $selected_date, $this->date_filter_options() ) || '' === $selected_date ) {
			return array();
		}

		$today_timestamp = function_exists( 'current_time' ) ? (int) current_time( 'timestamp' ) : time();
		$today           = $this->format_date( $today_timestamp );
		$start_field     = $this->config->opportunity_field_name( 'start_date' );
		$deadline_field  = $this->config->opportunity_field_name( 'grant_deadline' );
		$compare         = '>=';
		$value           = $today;

		if ( 'past' === $selected_date ) {
			$compare = '<';
		} elseif ( 'this-month' === $selected_date ) {
			$month_start_timestamp = strtotime( gmdate( 'Y-m-01 00:00:00', $today_timestamp ) );
			$next_month_timestamp  = strtotime( '+1 month', false === $month_start_timestamp ? $today_timestamp : $month_start_timestamp );
			$month_end_timestamp   = strtotime( '-1 day', false === $next_month_timestamp ? $today_timestamp : $next_month_timestamp );
			$compare               = 'BETWEEN';
			$value                 = array(
				$this->format_date( false === $month_start_timestamp ? $today_timestamp : $month_start_timestamp ),
				$this->format_date( false === $month_end_timestamp ? $today_timestamp : $month_end_timestamp ),
			);
		} elseif ( 'next-30-days' === $selected_date ) {
			$compare = 'BETWEEN';
			$value   = array(
				$today,
				$this->format_date( strtotime( '+30 days', $today_timestamp ) ),
			);
		}

		return array(
			'relation' => 'OR',
			array(
				'key'     => $start_field,
				'value'   => $value,
				'compare' => $compare,
				'type'    => 'DATE',
			),
			array(
				'key'     => $deadline_field,
				'value'   => $value,
				'compare' => $compare,
				'type'    => 'DATE',
			),
		);
	}

	/**
	 * Normalize existing query clauses to an appendable array.
	 *
	 * @param mixed $clauses Existing query clauses.
	 * @return array<int|string,mixed>
	 */
	private function query_clauses( $clauses ) {
		return is_array( $clauses ) ? $clauses : array();
	}

	/**
	 * One scalar request value.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private function request_value( $key ) {
		if ( ! isset( $_GET[ $key ] ) || is_array( $_GET[ $key ] ) ) {
			return '';
		}

		$value = function_exists( 'wp_unslash' ) ? wp_unslash( $_GET[ $key ] ) : $_GET[ $key ];

		return sanitize_text_field( $value );
	}

	/**
	 * Safe plain text from a stored label.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function plain_text( $value ) {
		$value = (string) $value;
		$value = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( $value ) : strip_tags( $value );

		return trim( $value );
	}

	/**
	 * Format a timestamp as Y-m-d in the current WP timezone when available.
	 *
	 * @param int|false $timestamp Timestamp.
	 * @return string
	 */
	private function format_date( $timestamp ) {
		$timestamp = false === $timestamp ? time() : (int) $timestamp;

		return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d', $timestamp ) : gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Primary date label for the opportunity edit UI.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	private function primary_date_value( $post_id ) {
		$type_value = (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'opportunity_type' ), true );
		$date_value = $this->config->is_grant_opportunity_type( $type_value )
			? (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'grant_deadline' ), true )
			: (string) get_post_meta( $post_id, $this->config->opportunity_field_name( 'start_date' ), true );

		$timestamp = strtotime( $date_value );

		return false === $timestamp ? trim( $date_value ) : wp_date( 'F j, Y', $timestamp );
	}
}
