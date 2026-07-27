<?php
/**
 * Plugin-owned BCI member directory renderer.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Config\Config;
use WatersMeet\CommunityResourcesHub\Support\RenderSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin-owned BCI member directory surface.
 */
final class MemberDirectoryRenderer {

	/**
	 * Member data service.
	 *
	 * @var MemberDirectoryService
	 */
	private $members;

	/**
	 * Saved Video Slider rows, or null to read the current BCI Hub config.
	 *
	 * @var array<int,array<string,mixed>>|null
	 */
	private $video_slides;

	/**
	 * Video Slider renderer used to resolve matching slide fragments.
	 *
	 * @var VideoSliderRenderer|null
	 */
	private $video_slider_renderer;

	/**
	 * Constructor.
	 *
	 * @param MemberDirectoryService|null $members Member data service.
	 * @param array<int,array<string,mixed>>|null $video_slides Saved Video Slider rows.
	 */
	public function __construct( ?MemberDirectoryService $members = null, ?array $video_slides = null ) {
		$this->members      = $members ?: new MemberDirectoryService();
		$this->video_slides = $video_slides;
	}

	/**
	 * Render member directory markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public function render( array $context = array() ) {
		if ( ! class_exists( RenderSupport::class ) ) {
			require_once dirname( __DIR__ ) . '/support/class-render-support.php';
		}

		MemberDirectoryAssets::enqueue();

		$members = $this->members->all();

		foreach ( $members as $index => $member ) {
			$video_url      = $member['videoUrl'] ?? '';
			$spotlight_href = $this->video_slider_fragment_href( $video_url );

			if ( '' === $spotlight_href && is_scalar( $video_url ) && '' !== trim( (string) $video_url ) ) {
				$video_slider = $this->video_slider_renderer();

				if ( '' !== $video_slider->youtube_video_id( $video_url ) ) {
					$spotlight_href = $video_slider->fragment_href_for_video_url(
						$video_url,
						$this->video_slider_slides()
					);
				}
			}

			$members[ $index ]['spotlightHref'] = $spotlight_href;
		}

		$payload = str_replace( '</script', '<\\/script', (string) wp_json_encode( array( 'memberDirectory' => $members ) ) );
		$eyebrow = $this->context_string( $context, 'eyebrow', __( 'BCI Members', 'community-resources-hub' ) );
		$title   = $this->context_string( $context, 'title', __( 'Meet our partners in the Building Connections Initiative', 'community-resources-hub' ) );
		$anchor  = sanitize_title( $this->context_string( $context, 'anchor' ) );
		$modal_id = RenderSupport::unique_id( 'wm-bci-member-modal-' );
		$wrapper_attributes = RenderSupport::wrapper_attributes(
			array(
				'id'                         => $anchor,
				'class'                      => 'wm-bci-member-directory',
				'data-wm-bci-controller'     => 'bci-member-directory',
			)
		);

		ob_start();
		?>
		<div <?php echo $wrapper_attributes; ?>>
			<section class="wm-bci-member-directory__section">
				<div class="wm-bci-member-directory__header">
					<p class="wm-bci-member-directory__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<h2 class="wm-bci-member-directory__title"><?php echo esc_html( $title ); ?></h2>
				</div>

				<?php if ( ! empty( $members ) ) : ?>
					<div class="wm-bci-member-directory__grid" data-wm-bci-member-grid>
						<?php foreach ( $members as $member ) : ?>
							<?php
							$hero_classes = array( 'wm-bci-member-card__hero' );

							if ( empty( $member['heroImageUrl'] ) ) {
								$hero_classes[] = 'is-empty';
							} else {
								$hero_classes[] = 'has-image';
							}

							$share_url      = RenderSupport::modal_share_url( $member, 'bci-member' );
							$spotlight_href = $member['spotlightHref'];
							?>
							<article class="wm-bci-member-card" data-wm-bci-member-card data-member-id="<?php echo esc_attr( (string) $member['id'] ); ?>">
								<div class="wm-bci-member-card__surface">
									<div class="<?php echo esc_attr( implode( ' ', $hero_classes ) ); ?>"<?php echo empty( $member['heroImageUrl'] ) ? '' : ' style="--wm-bci-member-hero-image:url(\'' . esc_url( $member['heroImageUrl'] ) . '\');"'; ?>>
									</div>
									<div class="wm-bci-member-card__body">
										<div class="wm-bci-member-card__content">
											<h3 class="wm-bci-member-card__title"><?php echo esc_html( $member['title'] ); ?></h3>
											<?php if ( ! empty( $member['summary'] ) ) : ?>
												<p class="wm-bci-member-card__summary"><?php echo esc_html( $member['summary'] ); ?></p>
											<?php endif; ?>
										</div>
										<div class="wm-bci-member-card__actions">
											<button
												type="button"
												class="button bg-color-blue wm-bci-member-card__button"
												data-wm-bci-member-open
												data-member-id="<?php echo esc_attr( (string) $member['id'] ); ?>"
												<?php echo '' !== $share_url ? 'data-wm-bci-member-share-url="' . esc_attr( $share_url ) . '"' : ''; ?>
												aria-controls="<?php echo esc_attr( $modal_id ); ?>"
											>
												<span class="button-text"><?php echo esc_html__( 'Learn More', 'community-resources-hub' ); ?></span>
												<span class="svg-wrapper">
													<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" fill="none">
														<path d="M1.07692 10L0 8.92308L7.38462 1.53846H0.769231V0H10V9.23077H8.46154V2.61538L1.07692 10Z" fill="#004966"/>
													</svg>
												</span>
											</button>
											<?php if ( '' !== $spotlight_href ) : ?>
												<a class="wm-bci-member-card__spotlight" href="<?php echo esc_url( $spotlight_href ); ?>">
													<span><?php echo esc_html__( 'Spotlight Video', 'community-resources-hub' ); ?></span>
													<span class="wm-bci-member-card__spotlight-icon" aria-hidden="true"><?php echo $this->play_circle( 20 ); ?></span>
												</a>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</article>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<div class="wm-bci-member-directory__empty" data-wm-bci-member-empty>
						<p class="wm-bci-member-directory__empty-title"><?php echo esc_html__( 'Member profiles are coming soon.', 'community-resources-hub' ); ?></p>
					</div>
				<?php endif; ?>
			</section>

			<dialog id="<?php echo esc_attr( $modal_id ); ?>" class="crh-dialog wm-bci-member-modal" data-wm-bci-member-modal aria-label="<?php echo esc_attr__( 'Member details', 'community-resources-hub' ); ?>">
				<?php echo RenderSupport::dialog_close_button(); ?>
				<div class="wm-bci-member-modal__hero" data-wm-bci-member-modal-hero>
					<img class="wm-bci-member-modal__logo" data-wm-bci-member-modal-logo alt="" hidden />
				</div>
				<div class="wm-bci-member-modal__body">
					<h2 class="wm-bci-member-modal__title" data-wm-bci-member-modal-title></h2>
					<div class="wm-bci-member-modal__connect" data-wm-bci-member-modal-connect>
						<p class="wm-bci-member-modal__connect-label"><?php echo esc_html__( 'Connect with us:', 'community-resources-hub' ); ?></p>
						<div class="wm-bci-member-modal__connect-main">
							<div class="wm-bci-member-modal__socials" data-wm-bci-member-modal-socials data-wm-bci-member-modal-icon-base="<?php echo esc_url( $this->member_image_asset_url() ); ?>"></div>
							<a class="wm-bci-member-modal__video" data-wm-bci-member-modal-video href="#" hidden>
								<span class="wm-bci-member-modal__video-label"><?php echo esc_html__( 'Watch Our Spotlight Video', 'community-resources-hub' ); ?></span>
								<span class="wm-bci-member-modal__video-icon" aria-hidden="true"><img src="<?php echo esc_url( $this->member_image_asset_url( 'bci-member-modal-play.png' ) ); ?>" alt="" width="40" height="40" /></span>
							</a>
						</div>
					</div>
					<div class="wm-bci-member-modal__section" data-wm-bci-member-modal-row="overview"><h3 class="wm-bci-member-modal__section-title"><?php echo esc_html__( 'Overview', 'community-resources-hub' ); ?></h3><div class="wm-bci-member-modal__copy" data-wm-bci-member-modal-overview></div></div>
					<div class="wm-bci-member-modal__meta-grid">
						<div class="wm-bci-member-modal__meta-row" data-wm-bci-member-modal-row="community-served"><p class="wm-bci-member-modal__meta-label"><?php echo esc_html__( 'Community Served', 'community-resources-hub' ); ?></p><p class="wm-bci-member-modal__meta-value" data-wm-bci-member-modal-community-served></p></div>
						<div class="wm-bci-member-modal__meta-row" data-wm-bci-member-modal-row="founded"><p class="wm-bci-member-modal__meta-label"><?php echo esc_html__( 'Founded', 'community-resources-hub' ); ?></p><p class="wm-bci-member-modal__meta-value" data-wm-bci-member-modal-founded></p></div>
						<div class="wm-bci-member-modal__meta-row" data-wm-bci-member-modal-row="email"><p class="wm-bci-member-modal__meta-label"><?php echo esc_html__( 'Email', 'community-resources-hub' ); ?></p><a class="wm-bci-member-modal__meta-link wm-bci-member-modal__meta-link--email" data-wm-bci-member-modal-email href="#"><span class="wm-bci-member-modal__meta-icon" aria-hidden="true"><?php echo $this->mail_icon(); ?></span><span data-wm-bci-member-modal-email-text></span></a></div>
						<div class="wm-bci-member-modal__meta-row" data-wm-bci-member-modal-row="website"><p class="wm-bci-member-modal__meta-label"><?php echo esc_html__( 'Website', 'community-resources-hub' ); ?></p><a class="wm-bci-member-modal__meta-link wm-bci-member-modal__meta-link--website" data-wm-bci-member-modal-website href="#" target="_blank" rel="noopener noreferrer"><span class="wm-bci-member-modal__meta-icon" aria-hidden="true"><?php echo $this->website_icon(); ?></span><span data-wm-bci-member-modal-website-text></span></a></div>
						<div class="wm-bci-member-modal__meta-row" data-wm-bci-member-modal-row="main-office"><p class="wm-bci-member-modal__meta-label"><?php echo esc_html__( 'Main Office', 'community-resources-hub' ); ?></p><p class="wm-bci-member-modal__meta-value" data-wm-bci-member-modal-main-office></p></div>
						<div class="wm-bci-member-modal__meta-row" data-wm-bci-member-modal-row="phone"><p class="wm-bci-member-modal__meta-label"><?php echo esc_html__( 'Phone', 'community-resources-hub' ); ?></p><p class="wm-bci-member-modal__meta-value" data-wm-bci-member-modal-phone></p></div>
					</div>
					<div class="wm-bci-member-modal__divider" data-wm-bci-member-modal-divider="attachments"></div>
					<div class="wm-bci-member-modal__section" data-wm-bci-member-modal-row="attachments"><h3 class="wm-bci-member-modal__section-title"><?php echo esc_html__( 'Attachment', 'community-resources-hub' ); ?></h3><div class="wm-bci-member-modal__attachments" data-wm-bci-member-modal-attachments></div></div>
					<div class="wm-bci-member-modal__divider" data-wm-bci-member-modal-divider="programs"></div>
					<div class="wm-bci-member-modal__section" data-wm-bci-member-modal-row="programs"><h3 class="wm-bci-member-modal__section-title"><?php echo esc_html__( 'Programs', 'community-resources-hub' ); ?></h3><div class="wm-bci-member-modal__programs" data-wm-bci-member-modal-programs></div></div>
					<div class="wm-bci-member-modal__actions" data-wm-bci-member-modal-actions hidden>
						<a class="button bg-color-blue wm-bci-member-modal__action wm-bci-member-modal__action--website" data-wm-bci-member-modal-action-website href="#" target="_blank" rel="noopener noreferrer" hidden>
							<span class="button-text"><?php echo esc_html__( 'Visit Website', 'community-resources-hub' ); ?></span>
							<?php echo RenderSupport::button_arrow_icon(); ?>
						</a>
						<a class="button bg-color-blue wm-bci-member-modal__action wm-bci-member-modal__action--video" data-wm-bci-member-modal-action-video href="#" hidden>
							<span class="button-text"><?php echo esc_html__( 'Watch Our Spotlight Video', 'community-resources-hub' ); ?></span>
							<?php echo RenderSupport::spotlight_video_icon(); ?>
						</a>
					</div>
				</div>
			</dialog>
			<script type="application/json" data-wm-bci-member-directory-payload><?php echo $payload; ?></script>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Play icon.
	 *
	 * @param int $size Pixel size.
	 * @return string
	 */
	private function play_circle( $size ) {
		return sprintf(
			'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><mask id="mask0_2224_5969" style="mask-type:alpha" maskUnits="userSpaceOnUse" x="0" y="0" width="20" height="20"><rect width="20" height="20" fill="#D9D9D9"/></mask><g mask="url(#mask0_2224_5969)"><path d="M7.91797 13.7501L13.7513 10.0001L7.91797 6.25008V13.7501ZM10.0013 18.3334C8.84852 18.3334 7.76519 18.1147 6.7513 17.6772C5.73741 17.2397 4.85547 16.6459 4.10547 15.8959C3.35547 15.1459 2.76172 14.264 2.32422 13.2501C1.88672 12.2362 1.66797 11.1529 1.66797 10.0001C1.66797 8.8473 1.88672 7.76397 2.32422 6.75008C2.76172 5.73619 3.35547 4.85425 4.10547 4.10425C4.85547 3.35425 5.73741 2.7605 6.7513 2.323C7.76519 1.8855 8.84852 1.66675 10.0013 1.66675C11.1541 1.66675 12.2374 1.8855 13.2513 2.323C14.2652 2.7605 15.1471 3.35425 15.8971 4.10425C16.6471 4.85425 17.2409 5.73619 17.6784 6.75008C18.1159 7.76397 18.3346 8.8473 18.3346 10.0001C18.3346 11.1529 18.1159 12.2362 17.6784 13.2501C17.2409 14.264 16.6471 15.1459 15.8971 15.8959C15.1471 16.6459 14.2652 17.2397 13.2513 17.6772C12.2374 18.1147 11.1541 18.3334 10.0013 18.3334ZM10.0013 16.6667C11.8624 16.6667 13.4388 16.0209 14.7305 14.7292C16.0221 13.4376 16.668 11.8612 16.668 10.0001C16.668 8.13897 16.0221 6.56258 14.7305 5.27091C13.4388 3.97925 11.8624 3.33341 10.0013 3.33341C8.14019 3.33341 6.5638 3.97925 5.27214 5.27091C3.98047 6.56258 3.33464 8.13897 3.33464 10.0001C3.33464 11.8612 3.98047 13.4376 5.27214 14.7292C6.5638 16.0209 8.14019 16.6667 10.0013 16.6667Z" fill="#004966"/></g></svg>',
			(int) $size
		);
	}

	/**
	 * Resolve a saved member video URL to a same-page slider fragment.
	 *
	 * @param mixed $video_url Saved member video URL.
	 * @return string
	 */
	private function video_slider_fragment_href( $video_url ) {
		if ( ! is_scalar( $video_url ) ) {
			return '';
		}

		$fragment = parse_url( trim( (string) $video_url ), PHP_URL_FRAGMENT );

		if ( ! is_string( $fragment ) ) {
			return '';
		}

		$fragment = rawurldecode( trim( $fragment ) );

		if ( ! preg_match( '/^bci-video-[a-z0-9]+(?:-[a-z0-9]+)*$/', $fragment ) ) {
			return '';
		}

		return '#' . $fragment;
	}

	/**
	 * Video Slider renderer used to match Member video URLs to rendered slides.
	 *
	 * @return VideoSliderRenderer
	 */
	private function video_slider_renderer() {
		if ( ! $this->video_slider_renderer instanceof VideoSliderRenderer ) {
			if ( ! class_exists( VideoSliderRenderer::class ) ) {
				require_once __DIR__ . '/class-video-slider-renderer.php';
			}

			$this->video_slider_renderer = new VideoSliderRenderer();
		}

		return $this->video_slider_renderer;
	}

	/**
	 * Saved Video Slider rows used by the same-page member links.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function video_slider_slides() {
		if ( is_array( $this->video_slides ) ) {
			return $this->video_slides;
		}

		$slides = ( new Config() )->video_slider_slides();

		$this->video_slides = is_array( $slides ) ? $slides : array();

		return $this->video_slides;
	}

	/**
	 * Email icon.
	 *
	 * @return string
	 */
	private function mail_icon() {
		return sprintf(
			'<img src="%s" alt="" width="24" height="24" />',
			esc_url( $this->member_image_asset_url( 'bci-member-modal-mail.svg' ) )
		);
	}

	/**
	 * Resolve a plugin-owned member modal image URL.
	 *
	 * @param string $filename Image filename, or an empty string for the image directory.
	 * @return string
	 */
	private function member_image_asset_url( $filename = '' ) {
		$plugin_url = defined( 'COMMUNITY_RESOURCES_HUB_URL' ) ? \COMMUNITY_RESOURCES_HUB_URL : '';

		return $plugin_url . 'assets/images/' . ltrim( (string) $filename, '/' );
	}

	/**
	 * Website icon.
	 *
	 * @return string
	 */
	private function website_icon() {
		return '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" focusable="false" aria-hidden="true"><circle cx="12" cy="12" r="8" stroke="currentColor" stroke-width="1.8"/><path d="M4.5 12h15M12 4.5c2 2.05 3 4.55 3 7.5s-1 5.45-3 7.5c-2-2.05-3-4.55-3-7.5s1-5.45 3-7.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	}

	/**
	 * Resolve a public text value from the renderer context.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @param string              $key Context key.
	 * @param string              $default Default value.
	 * @return string
	 */
	private function context_string( array $context, $key, $default = '' ) {
		$value = array_key_exists( $key, $context )
			? $this->normalize_context_string( $context[ $key ] )
			: '';

		return '' !== $value ? $value : $this->normalize_context_string( $default );
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
}
