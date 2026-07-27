<?php
/**
 * Plugin-owned newsletter archives renderer.
 *
 * @package CommunityResourcesHub
 */

namespace WatersMeet\CommunityResourcesHub\FrontEnd;

use WatersMeet\CommunityResourcesHub\Config\SettingsSchema;
use WatersMeet\CommunityResourcesHub\Support\RenderSupport;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the plugin-owned Newsletter Archives surface.
 */
final class NewsletterArchivesRenderer {

	/**
	 * Render newsletter archives markup.
	 *
	 * @param array<string,mixed> $context Render context.
	 * @return string
	 */
	public function render( array $context = array() ) {
		if ( ! class_exists( RenderSupport::class ) ) {
			require_once dirname( __DIR__ ) . '/support/class-render-support.php';
		}

		NewsletterArchivesAssets::enqueue();

		$cards = $this->normalize_cards( $context['cards'] ?? array() );

		if ( empty( $cards ) ) {
			return '';
		}

		$anchor   = sanitize_title( (string) ( $context['anchor'] ?? '' ) );
		$block_id = '' !== $anchor ? $anchor : RenderSupport::unique_id( 'bci-newsletter-archives-' );
		$eyebrow  = trim( (string) ( $context['eyebrow'] ?? '' ) );
		$title    = trim( (string) ( $context['title'] ?? '' ) );

		$wrapper_attributes = RenderSupport::wrapper_attributes(
			array(
				'id'                         => $block_id,
				'class'                      => 'bci-newsletter-archives',
				'data-bci-newsletter-archives' => true,
			)
		);

		ob_start();
		?>
		<section <?php echo $wrapper_attributes; ?>>
			<div class="bci-newsletter-archives__inner">
				<?php if ( '' !== $eyebrow || '' !== $title ) : ?>
					<header class="bci-newsletter-archives__header">
						<?php if ( '' !== $eyebrow ) : ?>
							<p class="bci-newsletter-archives__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $title ) : ?>
							<h2 class="bci-newsletter-archives__title"><?php echo esc_html( $title ); ?></h2>
						<?php endif; ?>
					</header>
				<?php endif; ?>

				<div class="bci-newsletter-archives__grid">
					<?php foreach ( $cards as $index => $card ) : ?>
						<?php
						$preset_url = $card['image_preset_url'];
						$has_preset = '' !== $preset_url;
						$has_image  = $has_preset || $card['image_id'];
						$link_label = sprintf(
							/* translators: %s: newsletter title. */
							__( 'Read %s', 'community-resources-hub' ),
							$card['title']
						);
						?>
						<article class="bci-newsletter-archives__card">
							<div class="bci-newsletter-archives__card-link">
								<div class="bci-newsletter-archives__media<?php echo $has_image ? ' has-image' : ''; ?>">
									<?php if ( $has_preset ) : ?>
										<img class="bci-newsletter-archives__image" src="<?php echo esc_url( $preset_url ); ?>" alt="" loading="<?php echo esc_attr( 0 === $index ? 'eager' : 'lazy' ); ?>" decoding="async" />
									<?php elseif ( $card['image_id'] ) : ?>
										<?php
										echo wp_get_attachment_image(
											$card['image_id'],
											'large',
											false,
											array(
												'class'   => 'bci-newsletter-archives__image',
												'loading' => 0 === $index ? 'eager' : 'lazy',
												'alt'     => '',
											)
										);
										?>
									<?php endif; ?>

									<?php if ( ! $has_preset ) : ?>
										<span class="bci-newsletter-archives__media-overlay" aria-hidden="true"></span>
										<img class="bci-newsletter-archives__mail-icon" src="<?php echo esc_url( $this->mail_spark_icon_url() ); ?>" alt="" aria-hidden="true" loading="lazy" decoding="async" />
									<?php endif; ?>
								</div>

								<div class="bci-newsletter-archives__card-body">
									<div class="bci-newsletter-archives__card-copy">
										<?php if ( '' !== $card['issue_label'] ) : ?>
											<p class="bci-newsletter-archives__card-eyebrow"><?php echo esc_html( $card['issue_label'] ); ?></p>
										<?php endif; ?>

										<h3 class="bci-newsletter-archives__card-title"><?php echo esc_html( $card['title'] ); ?></h3>
									</div>

									<a class="button bg-color-blue bci-newsletter-archives__button" href="<?php echo esc_url( $card['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $link_label ); ?>">
										<span class="button-text"><?php echo esc_html( __( 'Read more', 'community-resources-hub' ) ); ?></span>
										<?php echo RenderSupport::button_arrow_icon(); ?>
									</a>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Normalize raw classic/admin archive card input into render-ready cards.
	 *
	 * @param mixed $raw_cards Raw card input.
	 * @return array<int,array<string,mixed>>
	 */
	private function normalize_cards( $raw_cards ) {
		if ( ! is_array( $raw_cards ) ) {
			return array();
		}

		$cards = array();

		foreach ( $raw_cards as $raw_card ) {
			if ( ! is_array( $raw_card ) ) {
				continue;
			}

			$title = trim( (string) ( $raw_card['title'] ?? '' ) );
			$url   = esc_url_raw( trim( (string) ( $raw_card['url'] ?? '' ) ), array( 'http', 'https' ) );

			if ( '' === $title || '' === $url ) {
				continue;
			}

			$cards[] = array(
				'issue_label'      => trim( (string) ( $raw_card['issueLabel'] ?? $raw_card['issue_label'] ?? '' ) ),
				'title'            => $title,
				'url'              => $url,
				'image_preset'     => $this->image_preset_key( $raw_card['imagePreset'] ?? $raw_card['image_preset'] ?? '' ),
				'image_preset_url' => $this->image_preset_url( $raw_card['imagePreset'] ?? $raw_card['image_preset'] ?? '' ),
				'image_id'         => $this->uses_image_preset_contract( $raw_card ) ? 0 : $this->attachment_id( $raw_card['imageId'] ?? $raw_card['image_id'] ?? 0 ),
			);
		}

		return $cards;
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
	 * Whether a card is managed by the bundled preset image contract.
	 *
	 * @param array<string,mixed> $raw_card Raw card input.
	 * @return bool
	 */
	private function uses_image_preset_contract( array $raw_card ) {
		return array_key_exists( 'imagePreset', $raw_card ) || array_key_exists( 'image_preset', $raw_card );
	}

	/**
	 * Resolve a validated bundled image preset key.
	 *
	 * @param mixed $value Raw preset value.
	 * @return string
	 */
	private function image_preset_key( $value ) {
		if ( ! class_exists( SettingsSchema::class ) ) {
			require_once dirname( __DIR__ ) . '/config/class-settings-schema.php';
		}

		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9_-]/', '', $value );
		$value = is_string( $value ) ? $value : '';

		return array_key_exists( $value, SettingsSchema::newsletter_archive_image_presets() ) ? $value : '';
	}

	/**
	 * Bundled image preset URL for render output.
	 *
	 * @param mixed $value Raw preset value.
	 * @return string
	 */
	private function image_preset_url( $value ) {
		$key = $this->image_preset_key( $value );

		if ( '' === $key ) {
			return '';
		}

		$plugin_url = defined( 'COMMUNITY_RESOURCES_HUB_URL' ) ? \COMMUNITY_RESOURCES_HUB_URL : '';

		return $plugin_url . 'assets/images/newsletter-archives/' . $key . '.png';
	}

	/**
	 * Mail and sparkle mark asset used by newsletter archive media.
	 *
	 * @return string
	 */
	private function mail_spark_icon_url() {
		$plugin_url = defined( 'COMMUNITY_RESOURCES_HUB_URL' ) ? \COMMUNITY_RESOURCES_HUB_URL : '';

		return $plugin_url . 'assets/images/newsletter-archives-mail-spark.svg';
	}
}
