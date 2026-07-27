<?php
/**
 * Plugin-owned ACF settings UI registration.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\Config;

use WatersMeet\CommunityResourcesHub\ContentModel\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin-owned BCI Hub ACF settings UI.
 */
final class AcfSettings {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'acf/init', array( $this, 'register_options_page' ) );
		add_action( 'acf/init', array( $this, 'register_field_group' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_submenus' ), 20 );
		add_action( 'acf/save_post', array( $this, 'normalize_video_slider_repeater_parent' ), 20 );
		add_action( 'acf/save_post', array( $this, 'normalize_newsletter_archive_repeater_parent' ), 20 );
		add_filter( 'acf/update_value/name=wm_bci_google_sync_secret', array( $this, 'preserve_existing_secret_on_blank' ), 10, 3 );

		foreach ( SettingsSchema::all_setting_names() as $field_name ) {
			add_filter( 'acf/update_value/name=' . $field_name, array( $this, 'sanitize_setting_value' ), 20, 3 );
		}
	}

	/**
	 * Register the BCI Hub options page.
	 *
	 * @return void
	 */
	public function register_options_page() {
		if ( ! function_exists( 'acf_add_options_page' ) ) {
			return;
		}

		acf_add_options_page( SettingsSchema::options_page_args() );
	}

	/**
	 * Register the BCI Hub settings field group.
	 *
	 * @return void
	 */
	public function register_field_group() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group( SettingsSchema::field_group() );
	}

	/**
	 * Register Hub child menu items that WordPress does not add for CPTs under a custom parent.
	 *
	 * @return void
	 */
	public function register_admin_submenus() {
		if ( ! function_exists( 'add_submenu_page' ) ) {
			return;
		}

		add_submenu_page(
			SettingsSchema::OPTIONS_PAGE_SLUG,
			__( 'Community Resources Hub Settings', 'community-resources-hub' ),
			__( 'Settings', 'community-resources-hub' ),
			SettingsSchema::CAPABILITY,
			SettingsSchema::OPTIONS_PAGE_SLUG,
			'',
			0
		);

		add_submenu_page(
			SettingsSchema::OPTIONS_PAGE_SLUG,
			__( 'Add New BCI Member', 'community-resources-hub' ),
			__( 'Add New BCI Member', 'community-resources-hub' ),
			'edit_posts',
			'post-new.php?post_type=' . Schema::MEMBER_POST_TYPE,
			'',
			2
		);

		add_submenu_page(
			SettingsSchema::OPTIONS_PAGE_SLUG,
			__( 'Add New BCI Opportunity', 'community-resources-hub' ),
			__( 'Add New BCI Opportunity', 'community-resources-hub' ),
			'edit_posts',
			'post-new.php?post_type=' . Schema::OPPORTUNITY_POST_TYPE,
			'',
			4
		);

		add_submenu_page(
			SettingsSchema::OPTIONS_PAGE_SLUG,
			__( 'Opportunity Types', 'community-resources-hub' ),
			__( 'Opportunity Types', 'community-resources-hub' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . Schema::OPPORTUNITY_TYPE_TAXONOMY . '&post_type=' . Schema::OPPORTUNITY_POST_TYPE,
			'',
			5
		);

		add_submenu_page(
			SettingsSchema::OPTIONS_PAGE_SLUG,
			__( 'Opportunity Tags', 'community-resources-hub' ),
			__( 'Opportunity Tags', 'community-resources-hub' ),
			'manage_categories',
			'edit-tags.php?taxonomy=' . Schema::OPPORTUNITY_TAG_TAXONOMY . '&post_type=' . Schema::OPPORTUNITY_POST_TYPE,
			'',
			6
		);
	}

	/**
	 * Preserve the currently stored Google sync secret when the settings field is left blank.
	 *
	 * @param mixed               $value   Submitted value.
	 * @param mixed               $post_id Save target.
	 * @param array<string,mixed> $field   Field config.
	 * @return mixed
	 */
	public function preserve_existing_secret_on_blank( $value, $post_id, array $field ) {
		if ( ! in_array( $post_id, array( 'options', 'option' ), true ) ) {
			return $value;
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return $value;
		}

		$stored = get_option( 'options_wm_bci_google_sync_secret', '' );

		return '' !== (string) $stored ? $stored : $value;
	}

	/**
	 * Sanitize plugin-owned ACF option values before storage.
	 *
	 * @param mixed               $value   Submitted value.
	 * @param mixed               $post_id Save target.
	 * @param array<string,mixed> $field   Field config.
	 * @return mixed
	 */
	public function sanitize_setting_value( $value, $post_id, array $field ) {
		if ( ! in_array( $post_id, array( 'options', 'option' ), true ) ) {
			return $value;
		}

		$field_name = isset( $field['name'] ) ? (string) $field['name'] : '';

		if ( '' === $field_name ) {
			return $value;
		}

		return SettingsSchema::sanitize_value( $field_name, $value );
	}

	/**
	 * Normalize the Video Slider repeater parent option to the saved row count.
	 *
	 * ACF stores repeater rows in split option keys and expects the parent option to hold the
	 * row count. If that parent is seeded as an array, ACF will save the subfields but fail to
	 * reconstruct the repeater on reads.
	 *
	 * @param mixed $post_id Save target.
	 * @return void
	 */
	public function normalize_video_slider_repeater_parent( $post_id ) {
		if ( ! in_array( $post_id, array( 'options', 'option' ), true ) ) {
			return;
		}

		if ( empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) {
			return;
		}

		$field_key = 'field_wm_bci_video_slider_slides';

		if ( ! array_key_exists( $field_key, $_POST['acf'] ) ) {
			return;
		}

		$rows  = $_POST['acf'][ $field_key ];
		$count = 0;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( $this->video_slider_posted_row_has_content( $row ) ) {
					$count++;
				}
			}
		}

		update_option( SettingsSchema::option_name( 'wm_bci_video_slider_slides' ), $count );
	}

	/**
	 * Normalize the Newsletter Archives repeater parent option to the saved row count.
	 *
	 * @param mixed $post_id Save target.
	 * @return void
	 */
	public function normalize_newsletter_archive_repeater_parent( $post_id ) {
		if ( ! in_array( $post_id, array( 'options', 'option' ), true ) ) {
			return;
		}

		if ( empty( $_POST['acf'] ) || ! is_array( $_POST['acf'] ) ) {
			return;
		}

		$field_key = 'field_wm_bci_newsletter_archive_cards';

		if ( ! array_key_exists( $field_key, $_POST['acf'] ) ) {
			return;
		}

		$rows  = $_POST['acf'][ $field_key ];
		$count = 0;

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( $this->newsletter_archive_posted_row_has_content( $row ) ) {
					$count++;
				}
			}
		}

		update_option( SettingsSchema::option_name( 'wm_bci_newsletter_archive_cards' ), $count );
		$this->delete_legacy_newsletter_archive_image_options( $rows, $count );
	}

	/**
	 * Whether a posted Video Slider row still contains any real data.
	 *
	 * @param mixed $row Raw posted row.
	 * @return bool
	 */
	private function video_slider_posted_row_has_content( $row ) {
		if ( ! is_array( $row ) ) {
			return false;
		}

		foreach ( $row as $value ) {
			if ( is_array( $value ) ) {
				if ( $this->video_slider_posted_row_has_content( $value ) ) {
					return true;
				}

				continue;
			}

			if ( is_numeric( $value ) ) {
				if ( absint( $value ) > 0 ) {
					return true;
				}

				continue;
			}

			if ( '' !== trim( (string) $value ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a posted Newsletter Archives row still contains any real data.
	 *
	 * @param mixed $row Raw posted row.
	 * @return bool
	 */
	private function newsletter_archive_posted_row_has_content( $row ) {
		return $this->video_slider_posted_row_has_content( $row );
	}

	/**
	 * Delete obsolete media-library image split options after preset-image saves.
	 *
	 * @param mixed $rows  Posted repeater rows.
	 * @param int   $count Normalized row count.
	 * @return void
	 */
	private function delete_legacy_newsletter_archive_image_options( $rows, $count ) {
		$row_count = is_array( $rows ) ? count( $rows ) : 0;
		$limit     = max(
			25,
			absint( $count ),
			$row_count,
			absint( get_option( SettingsSchema::option_name( 'wm_bci_newsletter_archive_cards' ), 0 ) )
		);

		for ( $index = 0; $index < $limit; $index++ ) {
			delete_option( 'options_wm_bci_newsletter_archive_cards_' . $index . '_image_id' );
			delete_option( '_options_wm_bci_newsletter_archive_cards_' . $index . '_image_id' );
		}
	}
}
