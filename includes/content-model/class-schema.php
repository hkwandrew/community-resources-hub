<?php
/**
 * Plugin-owned BCI content-model schema.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\ContentModel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical schema and labels for the BCI content model.
 */
final class Schema {

	const MEMBER_POST_TYPE = 'bci_member';
	const OPPORTUNITY_POST_TYPE = 'bci_opportunity';
	const OPPORTUNITY_TYPE_TAXONOMY = 'opportunity-type';
	const OPPORTUNITY_TAG_TAXONOMY = 'opportunity-tag';
	const BCI_UPDATE_TAG_SLUG = 'bci-update';

	const OPPORTUNITY_TYPE_FIELD_GROUP_KEY = 'group_6a3a8bb80e703';
	const OPPORTUNITY_TYPE_ALIAS_FIELD_KEY = 'field_6a3d3c2a56177';
	const OPPORTUNITY_TYPE_COLOR_FIELD_KEY = 'field_6a3d4095d12fc';
	const OPPORTUNITY_TYPE_THUMBNAIL_FIELD_KEY = 'field_6a3d41119a3d7';

	/**
	 * Semantic member field map.
	 *
	 * @return array<string,string>
	 */
	public static function member_field_map() {
		return array(
			'aliases'               => 'wm_bci_member_aliases',
			'community_served'      => 'wm_bci_member_community_served',
			'founded_year'          => 'wm_bci_member_founded_year',
			'contact_email'         => 'wm_bci_member_contact_email',
			'website_url'           => 'wm_bci_member_website_url',
			'phone'                 => 'wm_bci_member_phone',
			'main_office'           => 'wm_bci_member_main_office',
			'social_links'          => 'wm_bci_member_social_links',
			'programs'              => 'wm_bci_member_programs',
			'attachments'           => 'wm_bci_member_attachments',
			'video_url'             => 'wm_bci_member_video_url',
			'video_label'           => 'wm_bci_member_video_label',
			'logo_url'              => 'wm_bci_member_logo_url',
			'hero_image_url'        => 'wm_bci_member_hero_image_url',
			'hero_background_color' => 'wm_bci_member_hero_background_color',
		);
	}

	/**
	 * Semantic opportunity field map.
	 *
	 * @return array<string,string>
	 */
	public static function opportunity_field_map() {
		return array(
			'source_entry_id'          => 'wm_bci_source_entry_id',
			'approval_status'          => 'wm_bci_approval_status',
			'approved_at'              => 'wm_bci_approved_at',
			'submitted_at'             => 'wm_bci_submitted_at',
			'opportunity_type'         => 'wm_bci_opportunity_type',
			'submitter_name'           => 'wm_bci_submitter_name',
			'organization'             => 'wm_bci_organization',
			'start_date'               => 'wm_bci_start_date',
			'grant_deadline'           => 'wm_bci_grant_deadline',
			'end_date'                 => 'wm_bci_end_date',
			'start_time'               => 'wm_bci_start_time',
			'end_time'                 => 'wm_bci_end_time',
			'location_mode'            => 'wm_bci_location_mode',
			'address'                  => 'wm_bci_address',
			'cost'                     => 'wm_bci_cost',
			'info_url'                 => 'wm_bci_info_url',
			'file_upload'              => 'wm_bci_file_upload',
			'google_sync_status'       => 'wm_bci_google_sync_status',
			'google_sync_attempted_at' => 'wm_bci_google_sync_attempted_at',
			'google_sync_synced_at'    => 'wm_bci_google_sync_synced_at',
			'google_sync_error'        => 'wm_bci_google_sync_error',
		);
	}

	/**
	 * Member meta key by semantic key.
	 *
	 * @param string $key Semantic key.
	 * @return string
	 */
	public static function member_field_name( $key ) {
		$map = self::member_field_map();

		return $map[ $key ] ?? '';
	}

	/**
	 * Opportunity meta key by semantic key.
	 *
	 * @param string $key Semantic key.
	 * @return string
	 */
	public static function opportunity_field_name( $key ) {
		$map = self::opportunity_field_map();

		return $map[ $key ] ?? '';
	}

	/**
	 * Plugin-owned member meta definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function member_meta_definitions() {
		return array(
			self::member_field_name( 'aliases' )               => self::textarea_meta_schema(),
			self::member_field_name( 'community_served' )      => self::textarea_meta_schema(),
			self::member_field_name( 'founded_year' )          => self::string_meta_schema(),
			self::member_field_name( 'contact_email' )         => self::string_meta_schema(),
			self::member_field_name( 'website_url' )           => self::string_meta_schema(),
			self::member_field_name( 'phone' )                 => self::string_meta_schema(),
			self::member_field_name( 'main_office' )           => self::textarea_meta_schema(),
			self::member_field_name( 'social_links' )          => self::integer_meta_schema(),
			self::member_field_name( 'programs' )              => self::html_meta_schema(),
			self::member_field_name( 'attachments' )           => self::string_meta_schema(),
			self::member_field_name( 'video_url' )             => self::string_meta_schema(),
			self::member_field_name( 'video_label' )           => self::string_meta_schema(),
			self::member_field_name( 'logo_url' )              => self::integer_meta_schema(),
			self::member_field_name( 'hero_image_url' )        => self::integer_meta_schema(),
			self::member_field_name( 'hero_background_color' ) => self::hex_color_meta_schema(),
		);
	}

	/**
	 * Sanitized CSS hex color value.
	 *
	 * @param mixed $value Raw color value.
	 * @return string
	 */
	public static function sanitize_hex_color( $value ) {
		if ( is_array( $value ) ) {
			return '';
		}

		$value = strtolower( trim( (string) $value ) );

		return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ? $value : '';
	}

	/**
	 * Plugin-owned opportunity meta definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function opportunity_meta_definitions() {
		return array(
			self::opportunity_field_name( 'source_entry_id' )          => self::integer_meta_schema( false ),
			self::opportunity_field_name( 'approval_status' )          => self::string_meta_schema( false ),
			self::opportunity_field_name( 'approved_at' )              => self::string_meta_schema( false ),
			self::opportunity_field_name( 'submitted_at' )             => self::string_meta_schema( false ),
			self::opportunity_field_name( 'google_sync_status' )       => self::string_meta_schema( false ),
			self::opportunity_field_name( 'google_sync_attempted_at' ) => self::string_meta_schema( false ),
			self::opportunity_field_name( 'google_sync_synced_at' )    => self::string_meta_schema( false ),
			self::opportunity_field_name( 'google_sync_error' )        => self::string_meta_schema( false ),
			self::opportunity_field_name( 'opportunity_type' )         => self::string_meta_schema( false ),
			self::opportunity_field_name( 'submitter_name' )           => self::string_meta_schema( false ),
			self::opportunity_field_name( 'organization' )             => self::string_meta_schema( false ),
			self::opportunity_field_name( 'start_date' )               => self::string_meta_schema( false ),
			self::opportunity_field_name( 'grant_deadline' )           => self::string_meta_schema( false ),
			self::opportunity_field_name( 'end_date' )                 => self::string_meta_schema( false ),
			self::opportunity_field_name( 'start_time' )               => self::string_meta_schema( false ),
			self::opportunity_field_name( 'end_time' )                 => self::string_meta_schema( false ),
			self::opportunity_field_name( 'location_mode' )            => self::string_meta_schema( false ),
			self::opportunity_field_name( 'address' )                  => self::textarea_meta_schema( false ),
			self::opportunity_field_name( 'cost' )                     => self::string_meta_schema( false ),
			self::opportunity_field_name( 'info_url' )                 => self::url_meta_schema( false ),
			self::opportunity_field_name( 'file_upload' )              => self::textarea_meta_schema( false ),
		);
	}

	/**
	 * Plugin-owned term meta definitions.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function opportunity_type_term_meta_definitions() {
		return array(
			'alias'     => self::string_meta_schema(),
			'color'     => self::string_meta_schema(),
			'thumbnail' => self::integer_meta_schema(),
		);
	}

	/**
	 * Current BCI member post-type args.
	 *
	 * @return array<string,mixed>
	 */
	public static function member_post_type_args() {
		return array(
			'labels'              => self::member_post_type_labels(),
			'description'         => '',
			'public'              => true,
			'hierarchical'        => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'show_ui'             => true,
			'show_in_menu'        => 'bci-hub',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'show_in_rest'        => true,
			'rest_base'           => '',
			'rest_namespace'      => 'wp/v2',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'menu_icon'           => 'dashicons-admin-post',
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'has_archive'         => false,
			'rewrite'             => array(
				'slug'       => self::MEMBER_POST_TYPE,
				'with_front' => true,
				'feeds'      => false,
				'pages'      => true,
			),
			'query_var'           => true,
			'can_export'          => true,
			'delete_with_user'    => false,
		);
	}

	/**
	 * Current BCI opportunity post-type args.
	 *
	 * @return array<string,mixed>
	 */
	public static function opportunity_post_type_args() {
		return array(
			'labels'              => self::opportunity_post_type_labels(),
			'description'         => '',
			'public'              => false,
			'hierarchical'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'show_ui'             => true,
			'show_in_menu'        => 'bci-hub',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'rest_base'           => '',
			'rest_namespace'      => 'wp/v2',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'menu_icon'           => 'dashicons-admin-post',
			'capability_type'     => 'post',
			'supports'            => array( 'title', 'thumbnail', 'custom-fields' ),
			'taxonomies'          => array( self::OPPORTUNITY_TYPE_TAXONOMY, self::OPPORTUNITY_TAG_TAXONOMY ),
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'can_export'          => true,
			'delete_with_user'    => false,
		);
	}

	/**
	 * Current BCI opportunity-type taxonomy args.
	 *
	 * @return array<string,mixed>
	 */
	public static function opportunity_type_taxonomy_args() {
		return array(
			'labels'               => self::opportunity_type_taxonomy_labels(),
			'description'          => '',
			'capabilities'         => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_posts',
			),
			'public'               => false,
			'publicly_queryable'   => false,
			'hierarchical'         => false,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'show_in_nav_menus'    => false,
			'show_in_rest'         => true,
			'rest_base'            => '',
			'rest_namespace'       => 'wp/v2',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'show_tagcloud'        => false,
			'show_in_quick_edit'   => true,
			'show_admin_column'    => true,
			'rewrite'              => array(
				'slug'         => self::OPPORTUNITY_TYPE_TAXONOMY,
				'with_front'   => false,
				'hierarchical' => false,
			),
			'query_var'            => false,
			'sort'                 => true,
			'meta_box_cb'          => false,
			'meta_box_sanitize_cb' => '',
		);
	}

	/**
	 * Current BCI opportunity-tag taxonomy args.
	 *
	 * @return array<string,mixed>
	 */
	public static function opportunity_tag_taxonomy_args() {
		return array(
			'labels'               => self::opportunity_tag_taxonomy_labels(),
			'description'          => '',
			'capabilities'         => array(
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'manage_categories',
				'delete_terms' => 'manage_categories',
				'assign_terms' => 'edit_posts',
			),
			'public'               => false,
			'publicly_queryable'   => false,
			'hierarchical'         => false,
			'show_ui'              => true,
			'show_in_menu'         => true,
			'show_in_nav_menus'    => false,
			'show_in_rest'         => true,
			'rest_base'            => '',
			'rest_namespace'       => 'wp/v2',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'show_tagcloud'        => false,
			'show_in_quick_edit'   => true,
			'show_admin_column'    => true,
			'rewrite'              => false,
			'query_var'            => false,
			'sort'                 => true,
		);
	}

	/**
	 * Plugin-owned ACF field group for opportunity-type display config.
	 *
	 * @return array<string,mixed>
	 */
	public static function opportunity_type_field_group() {
		return array(
			'key'                   => self::OPPORTUNITY_TYPE_FIELD_GROUP_KEY,
			'title'                 => __( 'Opportunity Type Config', 'community-resources-hub' ),
			'fields'                => array(
				array(
					'key'               => self::OPPORTUNITY_TYPE_ALIAS_FIELD_KEY,
					'label'             => __( 'Alias', 'community-resources-hub' ),
					'name'              => 'alias',
					'aria-label'        => '',
					'type'              => 'text',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'default_value'     => '',
					'maxlength'         => '',
					'allow_in_bindings' => 0,
					'placeholder'       => __( 'optional', 'community-resources-hub' ),
					'prepend'           => '',
					'append'            => '',
				),
				array(
					'key'                   => self::OPPORTUNITY_TYPE_COLOR_FIELD_KEY,
					'label'                 => __( 'Color', 'community-resources-hub' ),
					'name'                  => 'color',
					'aria-label'            => '',
					'type'                  => 'color_picker',
					'instructions'          => '',
					'required'              => 0,
					'conditional_logic'     => 0,
					'wrapper'               => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'default_value'         => '',
					'enable_opacity'        => 0,
					'return_format'         => 'string',
					'allow_in_bindings'     => 0,
					'show_custom_palette'   => 1,
					'palette_colors'        => '#5C6e7a, #004966, #d9a242, #c2385a, #520066, #418359',
					'show_color_wheel'      => 0,
					'custom_palette_source' => '',
				),
				array(
					'key'               => self::OPPORTUNITY_TYPE_THUMBNAIL_FIELD_KEY,
					'label'             => __( 'Thumbnail', 'community-resources-hub' ),
					'name'              => 'thumbnail',
					'aria-label'        => '',
					'type'              => 'image',
					'instructions'      => '',
					'required'          => 0,
					'conditional_logic' => 0,
					'wrapper'           => array(
						'width' => '',
						'class' => '',
						'id'    => '',
					),
					'return_format'     => 'array',
					'library'           => 'all',
					'min_width'         => '',
					'min_height'        => '',
					'min_size'          => '',
					'max_width'         => '',
					'max_height'        => '',
					'max_size'          => '',
					'mime_types'        => '',
					'allow_in_bindings' => 0,
					'preview_size'      => 'thumbnail',
				),
			),
			'location'              => array(
				array(
					array(
						'param'    => 'taxonomy',
						'operator' => '==',
						'value'    => self::OPPORTUNITY_TYPE_TAXONOMY,
					),
				),
			),
			'menu_order'            => 0,
			'position'              => 'normal',
			'style'                 => 'default',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'hide_on_screen'        => array(
				'permalink',
				'the_content',
				'excerpt',
				'discussion',
				'comments',
				'revisions',
				'slug',
				'author',
				'format',
				'page_attributes',
				'featured_image',
				'categories',
				'tags',
				'send-trackbacks',
			),
			'active'                => true,
			'description'           => '',
			'show_in_rest'          => 1,
			'display_title'         => '',
			'allow_ai_access'       => false,
			'ai_description'        => '',
		);
	}

	/**
	 * Source-owned default opportunity-type terms from the original Watersmeet environment.
	 *
	 * @return array<int,array{name:string,slug:string,alias:string,color:string,legacy_term_id:int}>
	 */
	public static function default_opportunity_types() {
		return array(
			array(
				'name'           => 'Workshop, Training, or Other Learning',
				'slug'           => 'learning',
				'alias'          => 'Learning',
				'color'          => '#520066',
				'legacy_term_id' => 15,
			),
			array(
				'name'           => 'Grant / RFP',
				'slug'           => 'grant-rfp',
				'alias'          => 'Grant/RFP',
				'color'          => '#d9a242',
				'legacy_term_id' => 16,
			),
			array(
				'name'           => 'Event',
				'slug'           => 'event',
				'alias'          => 'Events',
				'color'          => '#c2385a',
				'legacy_term_id' => 17,
			),
			array(
				'name'           => 'Resource',
				'slug'           => 'resource',
				'alias'          => 'Resources',
				'color'          => '#418359',
				'legacy_term_id' => 18,
			),
			array(
				'name'           => 'Recommended Vendor',
				'slug'           => 'recommended-vendor',
				'alias'          => 'Recommended Vendors',
				'color'          => '#7e5f8e',
				'legacy_term_id' => 0,
			),
			array(
				'name'           => 'Other',
				'slug'           => 'other',
				'alias'          => '',
				'color'          => '#5c6e7a',
				'legacy_term_id' => 20,
			),
		);
	}

	/**
	 * Source-owned default opportunity tags.
	 *
	 * @return array<int,array{name:string,slug:string}>
	 */
	public static function default_opportunity_tags() {
		return array(
			array(
				'name' => 'BCI Update',
				'slug' => self::BCI_UPDATE_TAG_SLUG,
			),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function string_meta_schema( $show_in_rest = true ) {
		return array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => (bool) $show_in_rest,
			'sanitize_callback' => 'sanitize_text_field',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function textarea_meta_schema( $show_in_rest = true ) {
		return array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => (bool) $show_in_rest,
			'sanitize_callback' => 'sanitize_textarea_field',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function url_meta_schema( $show_in_rest = true ) {
		return array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => (bool) $show_in_rest,
			'sanitize_callback' => 'esc_url_raw',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function integer_meta_schema( $show_in_rest = true ) {
		return array(
			'single'            => true,
			'type'              => 'integer',
			'show_in_rest'      => (bool) $show_in_rest,
			'sanitize_callback' => 'absint',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function html_meta_schema( $show_in_rest = true ) {
		return array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => (bool) $show_in_rest,
			'sanitize_callback' => 'wp_kses_post',
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function hex_color_meta_schema( $show_in_rest = true ) {
		return array(
			'single'            => true,
			'type'              => 'string',
			'show_in_rest'      => (bool) $show_in_rest,
			'sanitize_callback' => array( __CLASS__, 'sanitize_hex_color' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function array_meta_schema( $item_type ) {
		return array(
			'single'       => true,
			'type'         => 'array',
			'show_in_rest' => array(
				'schema' => array(
					'type'  => 'array',
					'items' => array(
						'type' => $item_type,
					),
				),
			),
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function member_post_type_labels() {
		return array(
			'name'                     => __( 'BCI Members', 'community-resources-hub' ),
			'singular_name'            => __( 'BCI Member', 'community-resources-hub' ),
			'menu_name'                => __( 'BCI Members', 'community-resources-hub' ),
			'all_items'                => __( 'All BCI Members', 'community-resources-hub' ),
			'edit_item'                => __( 'Edit BCI Member', 'community-resources-hub' ),
			'view_item'                => __( 'View BCI Member', 'community-resources-hub' ),
			'view_items'               => __( 'View BCI Members', 'community-resources-hub' ),
			'add_new_item'             => __( 'Add New BCI Member', 'community-resources-hub' ),
			'add_new'                  => __( 'Add New BCI Member', 'community-resources-hub' ),
			'new_item'                 => __( 'New BCI Member', 'community-resources-hub' ),
			'parent_item_colon'        => __( 'Parent BCI Member:', 'community-resources-hub' ),
			'search_items'             => __( 'Search BCI Members', 'community-resources-hub' ),
			'not_found'                => __( 'No BCI members found', 'community-resources-hub' ),
			'not_found_in_trash'       => __( 'No BCI members found in Trash', 'community-resources-hub' ),
			'archives'                 => __( 'BCI Member Archives', 'community-resources-hub' ),
			'attributes'               => __( 'BCI Member Attributes', 'community-resources-hub' ),
			'insert_into_item'         => __( 'Insert into BCI member', 'community-resources-hub' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this BCI member', 'community-resources-hub' ),
			'filter_items_list'        => __( 'Filter BCI members list', 'community-resources-hub' ),
			'filter_by_date'           => __( 'Filter BCI members by date', 'community-resources-hub' ),
			'items_list_navigation'    => __( 'BCI Members list navigation', 'community-resources-hub' ),
			'items_list'               => __( 'BCI Members list', 'community-resources-hub' ),
			'item_published'           => __( 'BCI Member published.', 'community-resources-hub' ),
			'item_published_privately' => __( 'BCI Member published privately.', 'community-resources-hub' ),
			'item_reverted_to_draft'   => __( 'BCI Member reverted to draft.', 'community-resources-hub' ),
			'item_scheduled'           => __( 'BCI Member scheduled.', 'community-resources-hub' ),
			'item_updated'             => __( 'BCI Member updated.', 'community-resources-hub' ),
			'item_link'                => __( 'BCI Member Link', 'community-resources-hub' ),
			'item_link_description'    => __( 'A link to a BCI member.', 'community-resources-hub' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function opportunity_post_type_labels() {
		return array(
			'name'                     => __( 'BCI Opportunities', 'community-resources-hub' ),
			'singular_name'            => __( 'BCI Opportunity', 'community-resources-hub' ),
			'menu_name'                => __( 'BCI Opportunities', 'community-resources-hub' ),
			'all_items'                => __( 'All BCI Opportunities', 'community-resources-hub' ),
			'edit_item'                => __( 'Edit BCI Opportunity', 'community-resources-hub' ),
			'view_item'                => __( 'View BCI Opportunity', 'community-resources-hub' ),
			'view_items'               => __( 'View BCI Opportunities', 'community-resources-hub' ),
			'add_new_item'             => __( 'Add New BCI Opportunity', 'community-resources-hub' ),
			'add_new'                  => __( 'Add New BCI Opportunity', 'community-resources-hub' ),
			'new_item'                 => __( 'New BCI Opportunity', 'community-resources-hub' ),
			'parent_item_colon'        => __( 'Parent BCI Opportunity:', 'community-resources-hub' ),
			'search_items'             => __( 'Search BCI Opportunities', 'community-resources-hub' ),
			'not_found'                => __( 'No BCI opportunities found', 'community-resources-hub' ),
			'not_found_in_trash'       => __( 'No BCI opportunities found in Trash', 'community-resources-hub' ),
			'archives'                 => __( 'BCI Opportunity Archives', 'community-resources-hub' ),
			'attributes'               => __( 'BCI Opportunity Attributes', 'community-resources-hub' ),
			'insert_into_item'         => __( 'Insert into BCI opportunity', 'community-resources-hub' ),
			'uploaded_to_this_item'    => __( 'Uploaded to this BCI opportunity', 'community-resources-hub' ),
			'filter_items_list'        => __( 'Filter BCI opportunities list', 'community-resources-hub' ),
			'filter_by_date'           => __( 'Filter BCI opportunities by date', 'community-resources-hub' ),
			'items_list_navigation'    => __( 'BCI Opportunities list navigation', 'community-resources-hub' ),
			'items_list'               => __( 'BCI Opportunities list', 'community-resources-hub' ),
			'item_published'           => __( 'BCI Opportunity published.', 'community-resources-hub' ),
			'item_published_privately' => __( 'BCI Opportunity published privately.', 'community-resources-hub' ),
			'item_reverted_to_draft'   => __( 'BCI Opportunity reverted to draft.', 'community-resources-hub' ),
			'item_scheduled'           => __( 'BCI Opportunity scheduled.', 'community-resources-hub' ),
			'item_updated'             => __( 'BCI Opportunity updated.', 'community-resources-hub' ),
			'item_link'                => __( 'BCI Opportunity Link', 'community-resources-hub' ),
			'item_link_description'    => __( 'A link to a BCI opportunity.', 'community-resources-hub' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function opportunity_type_taxonomy_labels() {
		return array(
			'name'                       => __( 'Opportunity Types', 'community-resources-hub' ),
			'singular_name'              => __( 'Opportunity Type', 'community-resources-hub' ),
			'menu_name'                  => __( 'Opportunity Types', 'community-resources-hub' ),
			'all_items'                  => __( 'All Opportunity Types', 'community-resources-hub' ),
			'edit_item'                  => __( 'Edit Opportunity Type', 'community-resources-hub' ),
			'view_item'                  => __( 'View Opportunity Type', 'community-resources-hub' ),
			'update_item'                => __( 'Update Opportunity Type', 'community-resources-hub' ),
			'add_new_item'               => __( 'Add New Opportunity Type', 'community-resources-hub' ),
			'new_item_name'              => __( 'New Opportunity Type Name', 'community-resources-hub' ),
			'search_items'               => __( 'Search Opportunity Types', 'community-resources-hub' ),
			'popular_items'              => __( 'Popular Opportunity Types', 'community-resources-hub' ),
			'separate_items_with_commas' => __( 'Separate opportunity types with commas', 'community-resources-hub' ),
			'add_or_remove_items'        => __( 'Add or remove opportunity types', 'community-resources-hub' ),
			'choose_from_most_used'      => __( 'Choose from the most used opportunity types', 'community-resources-hub' ),
			'most_used'                  => '',
			'not_found'                  => __( 'No opportunity types found', 'community-resources-hub' ),
			'no_terms'                   => __( 'No opportunity types', 'community-resources-hub' ),
			'name_field_description'     => '',
			'slug_field_description'     => '',
			'desc_field_description'     => '',
			'items_list_navigation'      => __( 'Opportunity Types list navigation', 'community-resources-hub' ),
			'items_list'                 => __( 'Opportunity Types list', 'community-resources-hub' ),
			'back_to_items'              => __( 'Go to opportunity types', 'community-resources-hub' ),
			'item_link'                  => __( 'Opportunity Type Link', 'community-resources-hub' ),
			'item_link_description'      => __( 'A link to an opportunity type.', 'community-resources-hub' ),
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function opportunity_tag_taxonomy_labels() {
		return array(
			'name'                       => __( 'Opportunity Tags', 'community-resources-hub' ),
			'singular_name'              => __( 'Opportunity Tag', 'community-resources-hub' ),
			'menu_name'                  => __( 'Opportunity Tags', 'community-resources-hub' ),
			'all_items'                  => __( 'All Opportunity Tags', 'community-resources-hub' ),
			'edit_item'                  => __( 'Edit Opportunity Tag', 'community-resources-hub' ),
			'view_item'                  => __( 'View Opportunity Tag', 'community-resources-hub' ),
			'update_item'                => __( 'Update Opportunity Tag', 'community-resources-hub' ),
			'add_new_item'               => __( 'Add New Opportunity Tag', 'community-resources-hub' ),
			'new_item_name'              => __( 'New Opportunity Tag Name', 'community-resources-hub' ),
			'search_items'               => __( 'Search Opportunity Tags', 'community-resources-hub' ),
			'popular_items'              => __( 'Popular Opportunity Tags', 'community-resources-hub' ),
			'separate_items_with_commas' => __( 'Separate opportunity tags with commas', 'community-resources-hub' ),
			'add_or_remove_items'        => __( 'Add or remove opportunity tags', 'community-resources-hub' ),
			'choose_from_most_used'      => __( 'Choose from the most used opportunity tags', 'community-resources-hub' ),
			'not_found'                  => __( 'No opportunity tags found', 'community-resources-hub' ),
			'no_terms'                   => __( 'No opportunity tags', 'community-resources-hub' ),
			'items_list_navigation'      => __( 'Opportunity Tags list navigation', 'community-resources-hub' ),
			'items_list'                 => __( 'Opportunity Tags list', 'community-resources-hub' ),
			'back_to_items'              => __( 'Back to opportunity tags', 'community-resources-hub' ),
		);
	}
}
