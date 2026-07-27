<?php
/**
 * Plugin-owned Opportunities Hub renderer.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Assets\Registry;
use WatersMeet\CommunityResourcesHub\Calendar\RuntimeAssets;
use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Config\SettingsSchema;
use WatersMeet\CommunityResourcesHub\Support\RenderSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared server-render owner for BCI opportunities/resources surfaces.
 */
final class OpportunityHubRenderer {

	private const CARD_TYPE_SLUGS          = array( 'resource', 'recommended-vendor' );
	private const CALENDAR_TYPE_SLUGS      = array( 'event', 'grant-rfp', 'learning', 'other' );
	private const BCI_UPDATE_BADGE_COLOR = '#004966';

	/**
	 * Workflow config.
	 *
	 * @var Config
	 */
	private $config;

	/**
	 * Member directory service.
	 *
	 * @var MemberDirectoryService
	 */
	private $members;

	/**
	 * Opportunity data service.
	 *
	 * @var ApprovedOpportunityService
	 */
	private $opportunities;

	/**
	 * Constructor.
	 *
	 * @param ApprovedOpportunityService|null $opportunities Opportunity service.
	 * @param MemberDirectoryService|null     $members Member service.
	 * @param Config|null                     $config Config service.
	 */
	public function __construct( ?ApprovedOpportunityService $opportunities = null, ?MemberDirectoryService $members = null, ?Config $config = null ) {
		$plugin_root = dirname( __DIR__, 2 ) . '/';

		if ( ! class_exists( Config::class ) ) {
			require_once $plugin_root . 'includes/content-model/class-schema.php';
			require_once $plugin_root . 'includes/config/class-settings-schema.php';
			require_once $plugin_root . 'includes/config/class-config.php';
		}

		if ( ! class_exists( MemberDirectoryService::class ) ) {
			require_once $plugin_root . 'includes/frontend/class-member-directory-service.php';
		}

		if ( ! class_exists( ApprovedOpportunityService::class ) ) {
			require_once $plugin_root . 'includes/workflow/class-opportunity-ics-exporter.php';
			require_once $plugin_root . 'includes/frontend/class-approved-opportunity-service.php';
		}

		$this->config        = $config ?: new Config();
		$this->members       = $members ?: new MemberDirectoryService( $this->config );
		$this->opportunities = $opportunities ?: new ApprovedOpportunityService( $this->members, $this->config );
	}

	/**
	 * Render the Opportunities Hub surface.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public function render( array $context = array() ) {
		if ( ! class_exists( RenderSupport::class ) ) {
			require_once dirname( __DIR__ ) . '/support/class-render-support.php';
		}

		if ( class_exists( Registry::class ) ) {
			Registry::enqueue_opportunity_hub_assets();
		}

		if ( class_exists( RuntimeAssets::class ) ) {
			RuntimeAssets::enqueue();
		}

		$all_opportunities       = $this->opportunities->all();
		$opportunities           = $this->opportunities_for_types( $all_opportunities, self::CARD_TYPE_SLUGS );
		$calendar_opportunities  = $this->opportunities_for_types( $all_opportunities, self::CALENDAR_TYPE_SLUGS );
		$member_filters          = $this->member_filters( $this->members->all(), $opportunities );
		$calendar_members        = $this->member_filters( $this->members->all(), $calendar_opportunities );
		$type_filters            = $this->card_type_filters( $opportunities );
		$calendar_type_filters   = $this->calendar_type_filters( $calendar_opportunities );
		$member_filter_columns   = empty( $member_filters )
			? array()
			: array_chunk( $member_filters, (int) ceil( count( $member_filters ) / 2 ) );
		$calendar_member_columns = empty( $calendar_members )
			? array()
			: array_chunk( $calendar_members, (int) ceil( count( $calendar_members ) / 2 ) );
		$anchor                 = sanitize_title( $this->context_string( $context, array( 'anchor' ) ) );
		$block_id               = '' !== $anchor ? $anchor : RenderSupport::unique_id( 'crh-opportunity-hub-' );
		$batch_size             = 9;
		$intro_content          = $this->context_string( $context, array( 'intro_content', 'introContent' ) );
		$intro_column_width     = $this->normalize_enum( $this->context_string( $context, array( 'intro_column_width', 'introColumnWidth' ), 'two-thirds' ), array( 'one-third', 'one-half', 'two-thirds', 'full' ), 'two-thirds' );
		$anchor_content         = $this->context_string( $context, array( 'anchor_content', 'anchorContent' ) );
		$anchor_column_width    = $this->normalize_enum( $this->context_string( $context, array( 'anchor_column_width', 'anchorColumnWidth' ), 'full' ), array( 'one-third', 'one-half', 'two-thirds', 'full' ), 'full' );
		$submit_modal_intro     = $this->context_string(
			$context,
			array( 'submit_modal_intro', 'submitModalIntro' ),
			__( 'Once reviewed by the Waters Meet team, they will be available here on the BCI calendar and below in the resources directory.', 'community-resources-hub' )
		);
		$calendar_html        = $this->resolve_calendar_html( $context );
		$form_shortcode       = $this->resolve_form_shortcode( $context );
		$has_calendar         = '' !== trim( $calendar_html );
		$has_form             = '' !== trim( $form_shortcode );
		$payload_json         = str_replace(
			'</script',
			'<\\/script',
			(string) wp_json_encode(
					array(
						'opportunities' => $opportunities,
						'members'       => $member_filters,
						'types'         => $type_filters,
					)
			)
		);
		$resource_type_thumbnails = array();
		$resource_type_colors     = array();

		foreach ( $this->config->calendar_event_types() as $event_type ) {
			$type         = isset( $event_type['type'] ) ? (string) $event_type['type'] : '';
			$thumbnail_id = isset( $event_type['thumbnail'] ) ? absint( $event_type['thumbnail'] ) : 0;
			$color        = isset( $event_type['color'] ) ? (string) $event_type['color'] : '';
			$slugs        = array_filter(
				array_map(
					'sanitize_title',
					array_merge(
						array( $type ),
						isset( $event_type['source_values'] ) && is_array( $event_type['source_values'] ) ? $event_type['source_values'] : array()
					)
				)
			);

			if ( empty( $slugs ) ) {
				continue;
			}

			$thumbnail_url = function_exists( 'wp_get_attachment_image_url' )
				? wp_get_attachment_image_url( $thumbnail_id, 'large' )
				: '';

			if ( $thumbnail_url ) {
				foreach ( $slugs as $slug ) {
					$resource_type_thumbnails[ $slug ] = array(
						'url' => esc_url( $thumbnail_url ),
					);
				}
			}

			if ( '' !== $color ) {
				foreach ( $slugs as $slug ) {
					$resource_type_colors[ $slug ] = $color;
				}
			}
		}

		$initial_opportunity_count = count( $opportunities );

		$render_type_badge = static function ( $label, $slug, $context_key, $color = '' ) {
			if ( '' === $label ) {
				return '';
			}

			$style = '';
			$color = trim( (string) $color );

			if ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
				$style = ' style="background-color: ' . esc_attr( strtolower( $color ) ) . ';"';
			}

			return sprintf(
				'<span class="wm-bci-type-badge wm-bci-type-badge--%1$s wm-bci-type-badge--%2$s"%3$s>%4$s</span>',
				esc_attr( $slug ),
				esc_attr( $context_key ),
				$style,
				esc_html( $label )
			);
		};

		$card_meta_rows = static function ( array $opportunity ) {
			$rows = array();
			$candidates = array(
				array( 'label' => __( 'Type', 'community-resources-hub' ), 'value' => isset( $opportunity['typeLabel'] ) ? (string) $opportunity['typeLabel'] : '' ),
				array( 'label' => __( 'Organization', 'community-resources-hub' ), 'value' => isset( $opportunity['organization'] ) ? (string) $opportunity['organization'] : '' ),
			);

			foreach ( $candidates as $candidate ) {
				if ( '' === $candidate['value'] ) {
					continue;
				}

				$rows[] = $candidate;
			}

			return $rows;
		};

		$render_card_media = static function ( $slug, $color = '' ) use ( $resource_type_thumbnails ) {
			if ( ! empty( $resource_type_thumbnails[ $slug ]['url'] ) ) {
				return sprintf(
					'<img class="wm-bci-opportunity-card__image" src="%1$s" alt="" loading="lazy" decoding="async" />',
					esc_url( $resource_type_thumbnails[ $slug ]['url'] )
				);
			}

			$style = '';
			$color = trim( (string) $color );

			if ( preg_match( '/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $color ) ) {
				$style = ' style="--wm-bci-opportunity-card-accent: ' . esc_attr( strtolower( $color ) ) . ';"';
			}

			return sprintf(
				'<span class="wm-bci-opportunity-card__placeholder"%1$s><span class="wm-bci-opportunity-card__placeholder-icon">%2$s</span></span>',
				$style,
				RenderSupport::opportunity_placeholder_icon()
			);
		};

		$wrapper_attributes = RenderSupport::wrapper_attributes(
			array(
					'id'                                  => $block_id,
					'class'                               => 'crh-opportunity-hub',
					'data-wm-bci-controller'              => 'bci-resources',
					'data-wm-bci-opportunity-batch-size' => (string) $batch_size,
				)
		);

		ob_start();
		?>
		<section <?php echo $wrapper_attributes; ?>>
			<div class="crh-opportunity-hub__inner">
				<?php if ( '' !== trim( $intro_content ) || '' !== trim( $anchor_content ) ) : ?>
					<div class="crh-opportunity-hub__intro-grid">
						<?php if ( '' !== trim( $intro_content ) ) : ?>
							<div class="crh-opportunity-hub__column crh-opportunity-hub__column--<?php echo esc_attr( $intro_column_width ); ?>">
								<div class="crh-opportunity-hub__richtext">
									<?php echo wp_kses_post( $intro_content ); ?>
								</div>
							</div>
						<?php endif; ?>

						<?php if ( '' !== trim( $anchor_content ) ) : ?>
							<div class="crh-opportunity-hub__column crh-opportunity-hub__column--<?php echo esc_attr( $anchor_column_width ); ?>">
								<div class="crh-opportunity-hub__richtext">
									<?php echo wp_kses_post( $anchor_content ); ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $has_form ) : ?>
					<div class="crh-opportunity-hub__actions">
						<button
							type="button"
							class="button bg-color-blue crh-opportunity-hub__submit-trigger"
							data-crh-submit-open
							aria-controls="<?php echo esc_attr( $block_id . '-submit' ); ?>"
						>
							<span class="button-text"><?php echo esc_html__( 'Share something', 'community-resources-hub' ); ?></span>
							<?php echo RenderSupport::open_form_icon(); ?>
						</button>
					</div>
				<?php endif; ?>

				<?php if ( $has_calendar || $has_form ) : ?>
					<div class="wm-bci-workflow-section__calendar-modal-layer" data-wm-bci-submit-modal-layer>
						<?php if ( $has_calendar ) : ?>
							<div class="crh-opportunity-hub__calendar wm-bci-workflow-section__calendar" data-wm-bci-calendar-region>
								<?php echo $calendar_html; ?>
							</div>
						<?php endif; ?>

						<?php if ( $has_form ) : ?>
							<div class="wm-bci-submit-modal-overlay" data-wm-bci-submit-modal-overlay hidden></div>
							<div
								id="<?php echo esc_attr( $block_id . '-submit' ); ?>"
								class="wm-bci-submit-modal"
								data-wm-bci-submit-modal
								role="dialog"
								aria-modal="true"
								aria-label="<?php echo esc_attr__( 'Submit a resource, opportunity, or event', 'community-resources-hub' ); ?>"
								hidden
							>
								<?php echo RenderSupport::dialog_close_button(); ?>
								<div class="wm-bci-submit-modal__body">
									<div class="wm-bci-submit-modal__header">
										<h2 class="wm-bci-submit-modal__title"><?php echo esc_html__( 'Submit a resource, opportunity, or event', 'community-resources-hub' ); ?></h2>
										<?php if ( '' !== $submit_modal_intro ) : ?>
											<p class="wm-bci-submit-modal__intro"><?php echo esc_html( $submit_modal_intro ); ?></p>
										<?php endif; ?>
									</div>

									<div
										class="wm-bci-submit-modal__form"
										data-wm-bci-time-sensitive-field-id="<?php echo esc_attr( $this->config->field( 'time_sensitive' ) ); ?>"
										data-wm-bci-description-field-id="<?php echo esc_attr( $this->config->field( 'description' ) ); ?>"
										data-wm-bci-description-label-time-sensitive="<?php echo esc_attr__( 'Provide a short description of this opportunity:', 'community-resources-hub' ); ?>"
										data-wm-bci-description-label-non-date="<?php echo esc_attr__( 'Provide a short description:', 'community-resources-hub' ); ?>"
									>
										<?php echo do_shortcode( $form_shortcode ); ?>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $has_calendar ) : ?>
					<details class="wm-bci-calendar-toolbar-filter" data-wm-bci-calendar-filter-source data-wm-bci-calendar-filter-dimension="type" hidden>
						<summary
							class="wm-bci-calendar-toolbar-filter__trigger"
							data-wm-bci-calendar-filter-button
							aria-haspopup="true"
						>
							<span data-wm-bci-calendar-filter-label><?php echo esc_html__( 'All BCI Events', 'community-resources-hub' ); ?></span>
							<?php echo $this->filter_chevron(); ?>
						</summary>
						<div
							class="wm-bci-calendar-toolbar-filter__panel"
							data-wm-bci-calendar-filter-panel
							role="group"
							aria-label="<?php echo esc_attr__( 'Filter BCI events by type', 'community-resources-hub' ); ?>"
						>
							<label class="wm-bci-calendar-toolbar-filter__option">
								<input type="checkbox" class="wm-bci-calendar-toolbar-filter__checkbox" data-wm-bci-calendar-filter-all />
								<span><?php echo esc_html__( 'View All Events', 'community-resources-hub' ); ?></span>
							</label>
							<?php foreach ( $calendar_type_filters as $type_filter ) : ?>
								<label class="wm-bci-calendar-toolbar-filter__option">
									<input
										type="checkbox"
										class="wm-bci-calendar-toolbar-filter__checkbox"
										value="<?php echo esc_attr( $type_filter['slug'] ); ?>"
										data-wm-bci-calendar-filter-checkbox
									/>
									<span><?php echo esc_html( $this->counted_filter_label( $type_filter['label'], $type_filter['count'] ) ); ?></span>
								</label>
							<?php endforeach; ?>
						</div>
					</details>

					<?php if ( ! empty( $calendar_members ) ) : ?>
						<details class="wm-bci-calendar-toolbar-filter" data-wm-bci-calendar-filter-source data-wm-bci-calendar-filter-dimension="member" hidden>
							<summary
								class="wm-bci-calendar-toolbar-filter__trigger"
								data-wm-bci-calendar-filter-button
								aria-haspopup="true"
							>
								<span data-wm-bci-calendar-filter-label><?php echo esc_html__( 'All Members', 'community-resources-hub' ); ?></span>
								<?php echo $this->filter_chevron(); ?>
							</summary>
							<div
								class="wm-bci-calendar-toolbar-filter__panel wm-bci-calendar-toolbar-filter__member-panel"
								data-wm-bci-calendar-filter-panel
								role="group"
								aria-label="<?php echo esc_attr__( 'Filter BCI events by member', 'community-resources-hub' ); ?>"
							>
								<label class="wm-bci-calendar-toolbar-filter__option">
									<input type="checkbox" class="wm-bci-calendar-toolbar-filter__checkbox" data-wm-bci-calendar-member-filter-all />
									<span><?php echo esc_html__( 'View All Members', 'community-resources-hub' ); ?></span>
								</label>
								<div class="wm-bci-calendar-toolbar-filter__member-columns">
									<?php foreach ( $calendar_member_columns as $column_index => $calendar_member_column ) : ?>
										<div
											class="wm-bci-calendar-toolbar-filter__member-column"
											data-wm-bci-calendar-member-column="<?php echo esc_attr( (string) ( $column_index + 1 ) ); ?>"
										>
											<?php foreach ( $calendar_member_column as $member ) : ?>
												<label class="wm-bci-calendar-toolbar-filter__option">
													<input
														type="checkbox"
														class="wm-bci-calendar-toolbar-filter__checkbox"
															value="<?php echo esc_attr( $member['slug'] ); ?>"
															data-wm-bci-calendar-member-filter-checkbox
														/>
														<span><?php echo esc_html( $this->member_filter_label( $member ) ); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</details>
					<?php endif; ?>

					<button
						type="button"
						class="wm-bci-calendar-toolbar-clear"
						data-wm-bci-calendar-clear-filters
						aria-label="<?php echo esc_attr__( 'Clear filters', 'community-resources-hub' ); ?>"
						hidden
					><?php echo esc_html__( 'Clear', 'community-resources-hub' ); ?></button>
				<?php endif; ?>

				<section class="wm-bci-opportunities" data-wm-bci-opportunities data-wm-bci-opportunity-batch-size="<?php echo esc_attr( (string) $batch_size ); ?>">
					<div class="wm-bci-opportunities__header">
						<div class="wm-bci-opportunities__heading">
							<p class="wm-bci-opportunities__eyebrow"><?php echo esc_html__( 'Community Resources', 'community-resources-hub' ); ?></p>
						<h2 class="wm-bci-opportunities__title"><?php echo esc_html__( 'Search Resources and Recommended Vendors', 'community-resources-hub' ); ?></h2>
						</div>

						<div class="wm-bci-opportunities__filters">
							<span class="wm-bci-opportunities__filters-label"><?php echo esc_html__( 'Filter by:', 'community-resources-hub' ); ?></span>
							<div class="wm-bci-opportunities__filters-group">
								<details class="wm-bci-opportunities__filter-dropdown" data-wm-bci-opportunity-filter-dropdown="type">
									<summary
										class="wm-bci-opportunities__filter-trigger"
										data-wm-bci-type-filter-button
										aria-haspopup="true"
										aria-controls="<?php echo esc_attr( $block_id . '-type-filter' ); ?>"
									>
										<span class="wm-bci-opportunities__filter-label" data-wm-bci-type-filter-label><?php echo esc_html__( 'All Types', 'community-resources-hub' ); ?></span>
										<?php echo $this->filter_chevron(); ?>
									</summary>
									<div
										class="wm-bci-opportunities__filter-panel"
										id="<?php echo esc_attr( $block_id . '-type-filter' ); ?>"
										data-wm-bci-type-filter-panel
										role="group"
										aria-label="<?php echo esc_attr__( 'Filter opportunities by type', 'community-resources-hub' ); ?>"
									>
										<label class="wm-bci-opportunities__filter-option">
											<input
												type="checkbox"
												class="wm-bci-opportunities__filter-choice"
												data-wm-bci-type-filter-all
												checked
											/>
											<span><?php echo esc_html__( 'All Types', 'community-resources-hub' ); ?></span>
										</label>
										<?php foreach ( $type_filters as $type_filter ) : ?>
											<label class="wm-bci-opportunities__filter-option">
												<input
													type="checkbox"
													value="<?php echo esc_attr( $type_filter['slug'] ); ?>"
														class="wm-bci-opportunities__filter-choice"
														data-wm-bci-type-filter-input
													/>
													<span><?php echo esc_html( $this->counted_filter_label( $type_filter['label'], $type_filter['count'] ) ); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
								</details>

								<?php if ( ! empty( $member_filters ) ) : ?>
									<details
										class="wm-bci-opportunities__filter-dropdown wm-bci-opportunities__member-filter"
										data-wm-bci-opportunity-filter-dropdown="member"
										data-wm-bci-member-filter
									>
										<summary
											class="wm-bci-opportunities__filter-trigger"
											data-wm-bci-member-filter-button
											aria-haspopup="true"
											aria-controls="<?php echo esc_attr( $block_id . '-member-filter' ); ?>"
										>
											<span class="wm-bci-opportunities__filter-label" data-wm-bci-member-filter-label><?php echo esc_html__( 'All Members', 'community-resources-hub' ); ?></span>
											<?php echo $this->filter_chevron(); ?>
										</summary>
										<div
											class="wm-bci-opportunities__filter-panel wm-bci-opportunities__member-panel"
											id="<?php echo esc_attr( $block_id . '-member-filter' ); ?>"
											data-wm-bci-member-filter-panel
											role="group"
											aria-label="<?php echo esc_attr__( 'Filter opportunities by member', 'community-resources-hub' ); ?>"
										>
											<div class="wm-bci-opportunities__member-options">
												<?php foreach ( $member_filter_columns as $column_index => $member_filter_column ) : ?>
													<div
														class="wm-bci-opportunities__member-column"
														data-wm-bci-member-column="<?php echo esc_attr( (string) ( $column_index + 1 ) ); ?>"
													>
														<?php foreach ( $member_filter_column as $member ) : ?>
															<label class="wm-bci-opportunities__member-option">
																<input type="checkbox" value="<?php echo esc_attr( $member['slug'] ); ?>" data-wm-bci-member-checkbox />
																<span><?php echo esc_html( $this->member_filter_label( $member ) ); ?></span>
															</label>
														<?php endforeach; ?>
													</div>
												<?php endforeach; ?>
											</div>
										</div>
									</details>
								<?php endif; ?>

								<button
									type="button"
									class="wm-bci-opportunities__clear-filters"
									data-wm-bci-clear-filters
									aria-label="<?php echo esc_attr__( 'Clear filters', 'community-resources-hub' ); ?>"
									hidden
								><?php echo esc_html__( 'Clear', 'community-resources-hub' ); ?></button>
							</div>
						</div>
					</div>

					<div class="wm-bci-opportunities__grid<?php echo 0 === $initial_opportunity_count ? ' is-hidden' : ''; ?>" data-wm-bci-opportunity-grid>
						<?php $initial_visible_count = 0; ?>
						<?php foreach ( $opportunities as $opportunity ) : ?>
							<?php
							$opportunity_id       = (int) ( $opportunity['id'] ?? 0 );
							$title                = isset( $opportunity['title'] ) ? (string) $opportunity['title'] : '';
							$type_label           = isset( $opportunity['typeLabel'] ) ? (string) $opportunity['typeLabel'] : '';
							$type_badge_label     = isset( $opportunity['typeBadgeLabel'] ) && '' !== trim( (string) $opportunity['typeBadgeLabel'] )
								? (string) $opportunity['typeBadgeLabel']
								: $type_label;
							$type_slug            = isset( $opportunity['typeSlug'] ) ? (string) $opportunity['typeSlug'] : 'other';
							$type_color           = isset( $opportunity['typeColor'] ) ? (string) $opportunity['typeColor'] : ( $resource_type_colors[ $type_slug ] ?? '' );
							$member_slug          = $this->opportunity_member_filter_slug( $opportunity );
							$meta_rows            = $card_meta_rows( $opportunity );
							$is_initially_visible = $initial_visible_count < $batch_size;
							$is_initially_hidden  = ! $is_initially_visible;
							$share_url            = RenderSupport::modal_share_url( $opportunity, 'bci-opportunity' );

							if ( $is_initially_visible ) {
								$initial_visible_count++;
							}
							?>
							<article
								class="wm-bci-opportunity-card"
								data-wm-bci-opportunity-card
								data-opportunity-id="<?php echo esc_attr( (string) $opportunity_id ); ?>"
								data-member-slug="<?php echo esc_attr( $member_slug ); ?>"
								data-type-slug="<?php echo esc_attr( $type_slug ); ?>"
								<?php echo $is_initially_hidden ? ' hidden' : ''; ?>
							>
								<div class="wm-bci-opportunity-card__surface">
									<div class="wm-bci-opportunity-card__media wm-bci-opportunity-card__media--<?php echo esc_attr( $type_slug ); ?>">
										<?php echo $render_card_media( $type_slug, $type_color ); ?>
									</div>
									<div class="wm-bci-opportunity-card__body">
										<div class="wm-bci-opportunity-card__badges">
											<?php echo $render_type_badge( $type_badge_label, $type_slug, 'card', $type_color ); ?>
											<?php if ( ! empty( $opportunity['isBciUpdate'] ) ) : ?>
												<?php echo $render_type_badge( __( 'BCI Update', 'community-resources-hub' ), 'bci-update', 'card', self::BCI_UPDATE_BADGE_COLOR ); ?>
											<?php endif; ?>
										</div>
										<h3 class="wm-bci-opportunity-card__title"><?php echo esc_html( $title ); ?></h3>
										<div class="wm-bci-opportunity-card__meta">
											<?php foreach ( $meta_rows as $meta_row ) : ?>
												<p class="wm-bci-opportunity-card__meta-row">
													<strong><?php echo esc_html( $meta_row['label'] ); ?>:</strong>
													<span><?php echo esc_html( $meta_row['value'] ); ?></span>
												</p>
											<?php endforeach; ?>
										</div>
										<button
											type="button"
											class="button bg-color-blue wm-bci-opportunity-card__button"
											data-wm-bci-opportunity-open
											data-opportunity-id="<?php echo esc_attr( (string) $opportunity_id ); ?>"
											<?php echo '' !== $share_url ? 'data-wm-bci-opportunity-share-url="' . esc_attr( $share_url ) . '"' : ''; ?>
											aria-controls="<?php echo esc_attr( $block_id . '-opportunity' ); ?>"
										>
											<span class="button-text"><?php echo esc_html__( 'View Full Details', 'community-resources-hub' ); ?></span>
											<?php echo RenderSupport::button_arrow_icon(); ?>
										</button>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>

					<div class="wm-bci-opportunities__load-more-wrap<?php echo $initial_opportunity_count > $batch_size ? '' : ' is-hidden'; ?>" data-wm-bci-load-more-wrap>
						<button type="button" class="button bg-color-blue wm-bci-opportunities__load-more" data-wm-bci-load-more>
							<span class="button-text"><?php echo esc_html__( 'Load More', 'community-resources-hub' ); ?></span>
							<?php echo RenderSupport::button_arrow_icon(); ?>
						</button>
					</div>

					<div class="wm-bci-opportunities__empty<?php echo 0 === $initial_opportunity_count ? '' : ' is-hidden'; ?>" data-wm-bci-opportunity-empty>
						<p class="wm-bci-opportunities__empty-title"><?php echo esc_html__( 'No resources or recommended vendors match these filters.', 'community-resources-hub' ); ?></p>
						<p class="wm-bci-opportunities__empty-copy"><?php echo esc_html__( 'Adjust the member or type filters to see more resources and recommended vendors.', 'community-resources-hub' ); ?></p>
					</div>
				</section>

				<dialog
					id="<?php echo esc_attr( $block_id . '-opportunity' ); ?>"
					class="crh-dialog wm-bci-opportunity-modal"
					data-wm-bci-opportunity-modal
					aria-label="<?php echo esc_attr__( 'Opportunity details', 'community-resources-hub' ); ?>"
				>
					<?php echo RenderSupport::dialog_close_button(); ?>
					<div class="wm-bci-opportunity-modal__body">
						<div class="wm-bci-opportunity-modal__header">
							<div class="wm-bci-opportunity-modal__badge" data-wm-bci-modal-type-badge hidden></div>
							<h2 class="wm-bci-opportunity-modal__title" data-wm-bci-modal-title></h2>
						</div>
						<div class="wm-bci-opportunity-modal__divider"></div>
						<div class="wm-bci-opportunity-modal__meta-grid">
							<div class="wm-bci-opportunity-modal__meta-row" data-wm-bci-modal-row="date">
								<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'Date', 'community-resources-hub' ); ?></p>
								<p class="wm-bci-opportunity-modal__meta-value" data-wm-bci-modal-date></p>
							</div>
							<div class="wm-bci-opportunity-modal__meta-row" data-wm-bci-modal-row="time">
								<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'Time', 'community-resources-hub' ); ?></p>
								<p class="wm-bci-opportunity-modal__meta-value" data-wm-bci-modal-time></p>
							</div>
							<div class="wm-bci-opportunity-modal__meta-row" data-wm-bci-modal-row="organization">
								<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'Organization', 'community-resources-hub' ); ?></p>
								<p class="wm-bci-opportunity-modal__meta-value" data-wm-bci-modal-organization></p>
							</div>
							<div class="wm-bci-opportunity-modal__meta-row" data-wm-bci-modal-row="location">
								<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'Location', 'community-resources-hub' ); ?></p>
								<div class="wm-bci-opportunity-modal__location">
									<span class="wm-bci-opportunity-modal__location-badge" data-wm-bci-modal-location-mode hidden></span>
									<span class="wm-bci-opportunity-modal__location-text" data-wm-bci-modal-address></span>
								</div>
							</div>
							<div class="wm-bci-opportunity-modal__meta-row" data-wm-bci-modal-row="cost">
								<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'Cost', 'community-resources-hub' ); ?></p>
								<p class="wm-bci-opportunity-modal__meta-value" data-wm-bci-modal-cost></p>
							</div>
							<div class="wm-bci-opportunity-modal__meta-row" data-wm-bci-modal-row="submitted-by">
								<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'Submitted by', 'community-resources-hub' ); ?></p>
								<p class="wm-bci-opportunity-modal__meta-value" data-wm-bci-modal-submitted-by></p>
							</div>
						</div>
						<div class="wm-bci-opportunity-modal__divider" data-wm-bci-modal-divider="about"></div>
						<div class="wm-bci-opportunity-modal__section" data-wm-bci-modal-row="about">
							<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'About this opportunity', 'community-resources-hub' ); ?></p>
							<div class="wm-bci-opportunity-modal__description" data-wm-bci-modal-description></div>
						</div>
						<div class="wm-bci-opportunity-modal__divider" data-wm-bci-modal-divider="attachments"></div>
						<div class="wm-bci-opportunity-modal__section" data-wm-bci-modal-row="attachments">
							<p class="wm-bci-opportunity-modal__meta-label"><?php echo esc_html__( 'Attachments', 'community-resources-hub' ); ?></p>
							<div class="wm-bci-opportunity-modal__attachments" data-wm-bci-modal-attachments></div>
						</div>
						<div class="wm-bci-opportunity-modal__actions" data-wm-bci-modal-row="actions">
							<a class="button bg-color-blue wm-bci-opportunity-modal__action" data-wm-bci-modal-visit target="_blank" rel="noopener noreferrer" hidden>
								<span class="button-text"><?php echo esc_html__( 'Visit Website', 'community-resources-hub' ); ?></span>
								<?php echo RenderSupport::button_arrow_icon(); ?>
							</a>
							<a class="button bg-color-blue wm-bci-opportunity-modal__action" data-wm-bci-modal-calendar hidden>
								<span class="button-text"><?php echo esc_html__( 'Add to Calendar', 'community-resources-hub' ); ?></span>
								<?php echo RenderSupport::calendar_icon(); ?>
							</a>
						</div>
					</div>
				</dialog>

				<script type="application/json" data-wm-bci-opportunities-payload><?php echo $payload_json; ?></script>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * Resolve the calendar HTML from either a direct value or shortcode source.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	private function resolve_calendar_html( array $context ) {
		$calendar_html = $this->context_string( $context, array( 'calendar_html' ) );

		if ( '' !== $calendar_html ) {
			return wp_kses_post( $calendar_html );
		}

		$calendar_source = $this->context_string( $context, array( 'calendar_shortcode', 'calendarShortcode' ) );

		if ( '' === $calendar_source ) {
			$calendar_source = $this->config->calendar_shortcode();
		}

		$calendar_shortcode = $this->normalize_gravitycalendar_shortcode( $calendar_source );

		if ( '' === $calendar_shortcode ) {
			return '';
		}

		if ( function_exists( 'shortcode_exists' ) && ! shortcode_exists( 'gravitycalendar' ) ) {
			return '';
		}

		return do_shortcode( $calendar_shortcode );
	}

	/**
	 * Normalize a calendar shortcode source to the only shortcode this renderer accepts.
	 *
	 * @param string $source Raw shortcode source.
	 * @return string
	 */
	private function normalize_gravitycalendar_shortcode( $source ) {
		$source = trim( (string) $source );

		if ( '' === $source || ! function_exists( 'do_shortcode' ) ) {
			return '';
		}

		if ( ! SettingsSchema::is_gravitycalendar_shortcode( $source ) ) {
			return '';
		}

		return $source;
	}

	/**
	 * Resolve the form shortcode from either a direct value or gravity form ID.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	private function resolve_form_shortcode( array $context ) {
		$form_shortcode = $this->context_string( $context, array( 'form_shortcode' ) );

		if ( '' !== $form_shortcode ) {
			return $this->normalize_gravityform_shortcode( $form_shortcode );
		}

		$form_id = absint( $context['gravity_form_id'] ?? $context['gravityFormId'] ?? 0 );

		if ( $form_id < 1 ) {
			$form_id = $this->config->form_id();
		}

		if ( $form_id < 1 ) {
			return '';
		}

		if ( ! function_exists( 'do_shortcode' ) || ( function_exists( 'shortcode_exists' ) && ! shortcode_exists( 'gravityform' ) ) ) {
			return '';
		}

		return '[gravityform id=' . $form_id . ' title=false description=false ajax=true tabindex=10]';
	}

	/**
	 * Normalize a form shortcode source to the only shortcode this renderer accepts.
	 *
	 * @param string $source Raw shortcode source.
	 * @return string
	 */
	private function normalize_gravityform_shortcode( $source ) {
		$source = trim( (string) $source );

		if ( '' === $source || ! function_exists( 'do_shortcode' ) ) {
			return '';
		}

		if ( function_exists( 'shortcode_exists' ) && ! shortcode_exists( 'gravityform' ) ) {
			return '';
		}

		if ( ! preg_match( '/^\[gravityform(?:\s[^\]]*)?\/?\]$/i', $source ) ) {
			return '';
		}

		return $source;
	}

	/**
	 * Resolve a context string by trying multiple keys.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @param array<int,string>   $keys Candidate keys.
	 * @param string              $default Default value.
	 * @return string
	 */
	private function context_string( array $context, array $keys, $default = '' ) {
		foreach ( $keys as $key ) {
			if ( ! array_key_exists( $key, $context ) ) {
				continue;
			}

			$value = $this->normalize_context_string( $context[ $key ] );

			if ( '' !== $value ) {
				return $value;
			}
		}

		return $this->normalize_context_string( $default );
	}

	/**
	 * Normalize a public context value to a printable string.
	 *
	 * @param mixed $value Raw context value.
	 * @return string
	 */
	private function normalize_context_string( $value ) {
		if ( is_array( $value ) || is_resource( $value ) ) {
			return '';
		}

		if ( is_object( $value ) && ! method_exists( $value, '__toString' ) ) {
			return '';
		}

		return trim( (string) $value );
	}

	/**
	 * Keep only opportunities belonging to one presentation surface.
	 *
	 * @param array<int,array<string,mixed>> $opportunities Approved opportunities.
	 * @param array<int,string>              $type_slugs Allowed type slugs.
	 * @return array<int,array<string,mixed>>
	 */
	private function opportunities_for_types( array $opportunities, array $type_slugs ) {
		return array_values(
			array_filter(
				$opportunities,
				static function ( array $opportunity ) use ( $type_slugs ) {
					return in_array( (string) ( $opportunity['typeSlug'] ?? '' ), $type_slugs, true );
				}
			)
		);
	}

	/**
	 * Exact non-date-sensitive card filter contract.
	 *
	 * @param array<int,array<string,mixed>> $opportunities Card opportunities.
	 * @return array<int,array{label:string,slug:string,count:int}>
	 */
	private function card_type_filters( array $opportunities ) {
		return $this->type_filters_with_counts(
			array(
				array( 'label' => __( 'Resources', 'community-resources-hub' ), 'slug' => 'resource' ),
				array( 'label' => __( 'Recommended Vendors', 'community-resources-hub' ), 'slug' => 'recommended-vendor' ),
			),
			$opportunities
		);
	}

	/**
	 * Exact date-sensitive Calendar filter contract.
	 *
	 * @param array<int,array<string,mixed>> $opportunities Calendar opportunities.
	 * @return array<int,array{label:string,slug:string,count:int}>
	 */
	private function calendar_type_filters( array $opportunities ) {
		return $this->type_filters_with_counts(
			array(
				array( 'label' => __( 'Events', 'community-resources-hub' ), 'slug' => 'event' ),
				array( 'label' => __( 'Grants', 'community-resources-hub' ), 'slug' => 'grant-rfp' ),
				array( 'label' => __( 'Learning', 'community-resources-hub' ), 'slug' => 'learning' ),
				array( 'label' => __( 'Other', 'community-resources-hub' ), 'slug' => 'other' ),
			),
			$opportunities
		);
	}

	/**
	 * Add static counts to a surface's fixed type-filter contract.
	 *
	 * @param array<int,array{label:string,slug:string}> $filters Filter definitions.
	 * @param array<int,array<string,mixed>>             $opportunities Surface opportunities.
	 * @return array<int,array{label:string,slug:string,count:int}>
	 */
	private function type_filters_with_counts( array $filters, array $opportunities ) {
		$counts = array();

		foreach ( $opportunities as $opportunity ) {
			$slug = (string) ( $opportunity['typeSlug'] ?? '' );

			if ( '' !== $slug ) {
				$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
			}
		}

		foreach ( $filters as &$filter ) {
			$filter['count'] = $counts[ $filter['slug'] ] ?? 0;
		}
		unset( $filter );

		return $filters;
	}

	/**
	 * A filter option label with its positive static result count.
	 *
	 * @param string $label Filter label.
	 * @param int    $count Static surface count.
	 * @return string
	 */
	private function counted_filter_label( $label, $count ) {
		$label = (string) $label;
		$count = absint( $count );

		if ( $count < 1 ) {
			return $label;
		}

		return sprintf(
			/* translators: 1: filter label, 2: number of matching opportunities. */
			__( '%1$s (%2$d)', 'community-resources-hub' ),
			$label,
			$count
		);
	}

	/**
	 * A member label that omits empty counts on both filter surfaces.
	 *
	 * @param array{label:string,slug:string,count:int} $member Member filter record.
	 * @return string
	 */
	private function member_filter_label( array $member ) {
		$label = (string) ( $member['label'] ?? '' );
		$count = absint( $member['count'] ?? 0 );

		return $count > 0 ? $this->counted_filter_label( $label, $count ) : $label;
	}

	/**
	 * Member filters derived from published members and organization groups.
	 *
	 * @param array<int,array<string,mixed>> $members Members.
	 * @param array<int,array<string,mixed>> $opportunities Opportunities.
	 * @return array<int,array{label:string,slug:string,count:int}>
	 */
	private function member_filters( array $members, array $opportunities ) {
		$filters = array();
		$seen    = array();
		$counts  = array();

		foreach ( $opportunities as $opportunity ) {
			$slug = $this->opportunity_member_filter_slug( $opportunity );

			if ( '' === $slug ) {
				continue;
			}

			$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
		}

		foreach ( $members as $member ) {
			$identity = $this->members->opportunity_member_identity(
				(string) ( $member['title'] ?? '' ),
				(string) ( $member['slug'] ?? '' ),
				(string) ( $member['title'] ?? '' )
			);
			$slug     = $identity['slug'];
			$label    = $identity['label'];

			if ( '' === $slug || isset( $seen[ $slug ] ) ) {
				continue;
			}

			$seen[ $slug ] = true;
			$filters[] = array(
				'label' => '' !== $label ? $label : ucwords( str_replace( '-', ' ', $slug ) ),
				'slug'  => $slug,
				'count' => $counts[ $slug ] ?? 0,
			);
		}

		if ( ! isset( $seen[ MemberDirectoryService::WATERS_MEET_FILTER_SLUG ] ) ) {
			$filters[] = array(
				'label' => __( 'Waters Meet', 'community-resources-hub' ),
				'slug'  => MemberDirectoryService::WATERS_MEET_FILTER_SLUG,
				'count' => $counts[ MemberDirectoryService::WATERS_MEET_FILTER_SLUG ] ?? 0,
			);
		}

		usort(
			$filters,
			static function ( array $left, array $right ) {
				return strnatcasecmp( $left['label'], $right['label'] );
			}
		);

		return count( $filters ) < 2 ? array() : $filters;
	}

	/**
	 * Member-filter slug for an opportunity.
	 *
	 * @param array<string,mixed> $opportunity Opportunity payload.
	 * @return string
	 */
	private function opportunity_member_filter_slug( array $opportunity ) {
		$identity = $this->members->opportunity_member_identity(
			(string) ( $opportunity['organization'] ?? '' ),
			(string) ( $opportunity['memberSlug'] ?? '' ),
			(string) ( $opportunity['memberLabel'] ?? '' )
		);

		return $identity['slug'];
	}

	/**
	 * Normalize a presentation enum.
	 *
	 * @param string            $value Raw value.
	 * @param array<int,string> $allowed Allowed values.
	 * @param string            $fallback Fallback.
	 * @return string
	 */
	private function normalize_enum( $value, array $allowed, $fallback ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	/**
	 * Chevron-down icon used by the filter disclosures.
	 *
	 * @return string
	 */
	private function chevron_down_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"><mask id="mask0_2216_9327" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="24" height="24"><rect width="24" height="24" fill="#D9D9D9"/></mask><g mask="url(#mask0_2216_9327)"><path d="M12 15.375L6 9.375L7.4 7.975L12 12.55L16.6 7.975L18 9.375L12 15.375Z" fill="#1C1E20"/></g></svg>';
	}

	/**
	 * Shared chevron markup used by the filter disclosures.
	 *
	 * @return string
	 */
	private function filter_chevron() {
		return '<span class="wm-bci-filter-chevron" aria-hidden="true">' . $this->chevron_down_icon() . '</span>';
	}
}
