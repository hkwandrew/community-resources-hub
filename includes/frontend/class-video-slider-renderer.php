<?php
/**
 * Plugin-owned BCI video slider renderer.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Support\RenderSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin-owned BCI video slider surface.
 */
final class VideoSliderRenderer {

	/**
	 * Render video slider markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public function render( array $context = array() ) {
		if ( ! class_exists( RenderSupport::class ) ) {
			require_once dirname( __DIR__ ) . '/support/class-render-support.php';
		}

		VideoSliderAssets::enqueue();

		$slides = $this->normalize_slides( $context['slides'] ?? array() );

		if ( empty( $slides ) ) {
			return '';
		}

		$anchor        = sanitize_title( (string) ( $context['anchor'] ?? '' ) );
		$block_id      = '' !== $anchor ? $anchor : RenderSupport::unique_id( 'bci-video-slider-' );
		$eyebrow       = trim( (string) ( $context['eyebrow'] ?? '' ) );
		$title         = trim( (string) ( $context['title'] ?? '' ) );
		$intro         = trim( (string) ( $context['intro'] ?? '' ) );
		$label         = '' !== $title ? $title : __( 'BCI video slider', 'community-resources-hub' );
		$has_loop_peek = count( $slides ) > 1;
		$wrapper_attributes = RenderSupport::wrapper_attributes(
			array(
				'id'                   => $block_id,
				'class'                => array(
					'bci-video-slider',
					$has_loop_peek ? 'has-loop-peek' : '',
				),
				'data-bci-video-slider' => true,
				'tabindex'             => '0',
				'role'                 => 'region',
				'aria-roledescription' => 'carousel',
				'aria-label'           => $label,
			)
		);

		ob_start();
		?>
		<section <?php echo $wrapper_attributes; ?>>
			<?php if ( '' !== $eyebrow || '' !== $title || '' !== $intro ) : ?>
				<div class="bci-video-slider__intro">
					<?php if ( '' !== $eyebrow ) : ?>
						<p class="bci-video-slider__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<?php endif; ?>

					<?php if ( '' !== $title ) : ?>
						<h2 class="bci-video-slider__title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>

					<?php if ( '' !== $intro ) : ?>
						<div class="bci-video-slider__copy"><?php echo wp_kses_post( $intro ); ?></div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="bci-video-slider__anchor-targets" aria-hidden="true">
				<?php foreach ( $slides as $slide ) : ?>
					<span id="<?php echo esc_attr( $slide['anchor_id'] ); ?>" class="bci-video-slider__anchor-target"></span>
				<?php endforeach; ?>
			</div>

			<div class="bci-video-slider__stage">
				<div class="bci-video-slider__track" data-bci-video-slider-track>
					<?php foreach ( $slides as $slide_index => $slide ) : ?>
						<?php
						$is_active   = 0 === $slide_index;
						$slide_id    = $block_id . '-slide-' . $slide_index;
						$play_label  = sprintf(
							/* translators: %s: video title. */
							__( 'Play %s', 'community-resources-hub' ),
							$slide['title']
						);
						$slide_order = $has_loop_peek && count( $slides ) - 1 === $slide_index ? 0 : $slide_index + 1;
						?>
						<article id="<?php echo esc_attr( $slide_id ); ?>" class="bci-video-slider__slide<?php echo $is_active ? ' is-active' : ''; ?>" data-bci-video-slider-slide aria-hidden="<?php echo $is_active ? 'false' : 'true'; ?>" style="order: <?php echo esc_attr( (string) $slide_order ); ?>;" data-bci-video-slider-anchor="<?php echo esc_attr( $slide['anchor_id'] ); ?>">
							<span class="bci-video-slider__linework" aria-hidden="true">
								<span class="bci-video-slider__linework-art"></span>
							</span>
							<div class="bci-video-slider__card">
								<div class="bci-video-slider__media">
									<button class="bci-video-slider__play" type="button" data-bci-video-slider-play aria-label="<?php echo esc_attr( $play_label ); ?>"<?php echo $is_active ? '' : ' tabindex="-1"'; ?>>
										<span class="bci-video-slider__placeholder" data-bci-video-slider-placeholder>
											<?php
											echo wp_get_attachment_image(
												$slide['thumbnail_id'],
												'xlarge',
												false,
												array(
													'class'   => 'bci-video-slider__thumbnail',
													'loading' => $is_active ? 'eager' : 'lazy',
													'alt'     => $slide['title'],
												)
											);
											?>
										</span>
										<span class="bci-video-slider__play-icon" aria-hidden="true"></span>
									</button>
									<iframe class="bci-video-slider__iframe" title="<?php echo esc_attr( sprintf( /* translators: %s: video title. */ __( 'Video: %s', 'community-resources-hub' ), $slide['title'] ) ); ?>" data-bci-video-slider-frame data-video-src="<?php echo esc_url( $slide['video_src'] ); ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" loading="lazy"></iframe>
									<button class="bci-video-slider__stop" type="button" data-bci-video-slider-stop aria-label="<?php echo esc_attr( sprintf( /* translators: %s: video title. */ __( 'Stop %s', 'community-resources-hub' ), $slide['title'] ) ); ?>" hidden>
										<span aria-hidden="true">&times;</span>
									</button>
								</div>

								<div class="bci-video-slider__content">
									<?php if ( '' !== $slide['eyebrow'] ) : ?>
										<p class="bci-video-slider__slide-eyebrow"><?php echo esc_html( $slide['eyebrow'] ); ?></p>
									<?php endif; ?>

									<h3 class="bci-video-slider__slide-title"><?php echo esc_html( $slide['title'] ); ?></h3>

									<?php if ( '' !== $slide['description'] ) : ?>
										<p class="bci-video-slider__description"><?php echo esc_html( $slide['description'] ); ?></p>
									<?php endif; ?>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>

			<div class="bci-video-slider__controls-shell">
				<div class="bci-video-slider__controls">
					<button class="bci-video-slider__arrow bci-video-slider__arrow--prev" type="button" data-bci-video-slider-prev aria-label="<?php echo esc_attr__( 'Previous video', 'community-resources-hub' ); ?>">
						<span class="bci-video-slider__arrow-icon" aria-hidden="true">
							<?php echo $this->arrow_icon(); ?>
						</span>
					</button>

					<div class="bci-video-slider__logo-nav" role="tablist" aria-label="<?php echo esc_attr__( 'Select spotlight video', 'community-resources-hub' ); ?>">
						<?php foreach ( $slides as $slide_index => $slide ) : ?>
							<?php
							$is_active  = 0 === $slide_index;
							$logo_label = '' !== $slide['logo_label'] ? $slide['logo_label'] : $slide['title'];
							?>
							<button class="bci-video-slider__logo-button<?php echo $is_active ? ' is-active' : ''; ?>" type="button" data-bci-video-slider-logo data-slide-index="<?php echo esc_attr( (string) $slide_index ); ?>" role="tab" aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $block_id . '-slide-' . $slide_index ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: video title. */ __( 'Show video: %s', 'community-resources-hub' ), $slide['title'] ) ); ?>"<?php echo $is_active ? '' : ' tabindex="-1"'; ?>>
								<?php
								if ( $slide['logo_id'] ) {
									echo wp_get_attachment_image(
										$slide['logo_id'],
										'medium',
										false,
										array(
											'class' => 'bci-video-slider__logo-image',
											'alt'   => '',
										)
									);
								}
								?>
								<span class="screen-reader-text"><?php echo esc_html( $logo_label ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>

					<button class="bci-video-slider__arrow bci-video-slider__arrow--next" type="button" data-bci-video-slider-next aria-label="<?php echo esc_attr__( 'Next video', 'community-resources-hub' ); ?>">
						<span class="bci-video-slider__arrow-icon" aria-hidden="true">
							<?php echo $this->arrow_icon(); ?>
						</span>
					</button>
				</div>
			</div>

		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Resolve a video URL to the fragment of its renderable slider row.
	 *
	 * @param mixed $video_url Raw member video URL or YouTube ID.
	 * @param mixed $raw_slides Raw Video Slider rows.
	 * @return string
	 */
	public function fragment_href_for_video_url( $video_url, $raw_slides ) {
		if ( ! is_scalar( $video_url ) || ! is_array( $raw_slides ) ) {
			return '';
		}

		$video_id = $this->youtube_video_id( $video_url );

		if ( '' === $video_id ) {
			return '';
		}

		foreach ( $this->normalize_slides( $raw_slides ) as $slide ) {
			if ( $video_id === $slide['video_id'] ) {
				return '#' . $slide['anchor_id'];
			}
		}

		return '';
	}

	/**
	 * Normalize one supported YouTube URL or direct ID.
	 *
	 * @param mixed $value Raw YouTube URL or ID.
	 * @return string
	 */
	public function youtube_video_id( $value ) {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return $this->normalize_youtube_video_id( '', $value );
	}

	/**
	 * Normalize raw classic/admin slide input into render-ready slides.
	 *
	 * @param mixed $raw_slides Raw slide input.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_slides( $raw_slides ) {
		if ( ! is_array( $raw_slides ) ) {
			return array();
		}

		$slides       = array();
		$used_anchors = array();

		foreach ( $raw_slides as $index => $raw_slide ) {
			if ( ! is_array( $raw_slide ) ) {
				continue;
			}

			$video_id     = $this->normalize_youtube_video_id(
				$raw_slide['videoId'] ?? '',
				$raw_slide['videoUrl'] ?? ''
			);
			$thumbnail_id = $this->attachment_id( $raw_slide['thumbnailId'] ?? 0 );
			$logo_id      = $this->attachment_id( $raw_slide['logoId'] ?? 0 );

			if ( '' === $video_id || 0 === $thumbnail_id ) {
				continue;
			}

			$title      = trim( (string) ( $raw_slide['slideTitle'] ?? '' ) );
			$logo_label = trim( (string) ( $raw_slide['logoLabel'] ?? '' ) );
			$eyebrow    = trim( (string) ( $raw_slide['slideEyebrow'] ?? '' ) );

			if ( '' === $title ) {
				$title = '' !== $logo_label ? $logo_label : __( 'BCI spotlight video', 'community-resources-hub' );
			}

			if ( '' === $eyebrow ) {
				$eyebrow = __( 'The Rooted in Community series', 'community-resources-hub' );
			}

			$anchor_seed = '' !== $title ? $title : ( '' !== $logo_label ? $logo_label : 'slide-' . ( (int) $index + 1 ) );
			$anchor_base = function_exists( 'sanitize_title' )
				? sanitize_title( $anchor_seed )
				: strtolower( trim( preg_replace( '/[^A-Za-z0-9]+/', '-', $anchor_seed ), '-' ) );

			if ( '' === $anchor_base ) {
				$anchor_base = 'slide-' . ( (int) $index + 1 );
			}

			$anchor_id = 'bci-video-' . $anchor_base;

			if ( isset( $used_anchors[ $anchor_id ] ) ) {
				$used_anchors[ $anchor_id ]++;
				$anchor_id .= '-' . $used_anchors[ $anchor_id ];
			} else {
				$used_anchors[ $anchor_id ] = 1;
			}

			$slides[] = array(
				'video_id'      => $video_id,
				'video_src'     => 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $video_id ) . '?rel=0',
				'thumbnail_id'  => $thumbnail_id,
				'logo_id'       => $logo_id,
				'logo_label'    => $logo_label,
				'eyebrow'       => $eyebrow,
				'title'         => $title,
				'description'   => trim( (string) ( $raw_slide['slideDescription'] ?? '' ) ),
				'anchor_id'     => $anchor_id,
				'originalIndex' => (int) $index,
			);
		}

		return $slides;
	}

	/**
	 * Resolve an attachment ID from scalar or image-array input.
	 *
	 * @param mixed $value Raw attachment value.
	 * @return int
	 */
	private function attachment_id( $value ) {
		if ( is_array( $value ) ) {
			return absint( $value['ID'] ?? $value['id'] ?? 0 );
		}

		return absint( $value );
	}

	/**
	 * Normalize a YouTube ID from either direct ID or URL input.
	 *
	 * @param mixed $video_id Raw direct ID.
	 * @param mixed $video_url Raw video URL.
	 * @return string
	 */
	private function normalize_youtube_video_id( $video_id, $video_url ) {
		$candidates = array(
			trim( (string) $video_id ),
			trim( (string) $video_url ),
		);

		foreach ( $candidates as $candidate ) {
			if ( '' === $candidate ) {
				continue;
			}

			if ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $candidate ) ) {
				return $candidate;
			}

			if ( preg_match( '~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([a-zA-Z0-9_-]{11})~', $candidate, $matches ) ) {
				return $matches[1];
			}
		}

		return '';
	}

	/**
	 * Arrow icon.
	 *
	 * @return string
	 */
	private function arrow_icon() {
		return '<svg width="20" height="20" viewBox="0 0 20 20" focusable="false"><path d="M6.57292 18.3332L5.09375 16.854L11.9479 9.99984L5.09375 3.14567L6.57292 1.6665L14.9063 9.99984L6.57292 18.3332Z" fill="currentColor"/></svg>';
	}
}
