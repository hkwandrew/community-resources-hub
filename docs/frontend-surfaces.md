<!-- markdownlint-disable MD013 -->

# Frontend Surfaces

This document covers the public/classic rendering layer: what each surface does, which classes own it, which data it consumes, and how classic themes or editors can place it.

## Surface Overview

| Surface | Renderer | Data source | Primary assets | Classic entry points |
| --- | --- | --- | --- | --- |
| Opportunity Hub | [`OpportunityHubRenderer`](../includes/frontend/class-opportunity-hub-renderer.php) | [`ApprovedOpportunityService`](../includes/frontend/class-approved-opportunity-service.php) + [`MemberDirectoryService`](../includes/frontend/class-member-directory-service.php) | `build/opportunity-hub/*` + `build/calendar/runtime.*` | `[community_resources_hub]`, `[community_opportunity_hub]`, `community_resources_hub_render_opportunity_hub()` |
| Member Directory | [`MemberDirectoryRenderer`](../includes/frontend/class-member-directory-renderer.php) | [`MemberDirectoryService`](../includes/frontend/class-member-directory-service.php) | `build/member-directory/*` | `[community_member_directory]`, `community_resources_hub_render_member_directory()` |
| Video Slider | [`VideoSliderRenderer`](../includes/frontend/class-video-slider-renderer.php) | Saved settings or direct context slides | `build/video-slider/*` | `[community_video_slider]`, `community_resources_hub_render_video_slider()` |
| Newsletter Archives | [`NewsletterArchivesRenderer`](../includes/frontend/class-newsletter-archives-renderer.php) | Saved settings or direct context cards | `build/newsletter-archives/style.css` | `[community_newsletter_archives]`, `community_resources_hub_render_newsletter_archives()` |

## Shared Frontend Contracts

### Asset registration

Shared handles are registered by [`includes/assets/class-registry.php`](../includes/assets/class-registry.php). Renderers should enqueue those handles instead of hard-coding file paths.

### Wrapper helpers and CTA parity

[`RenderSupport`](../includes/support/class-render-support.php) centralizes:

- unique ID generation
- wrapper attribute construction
- modal share URL tokens
- shared CTA icons
- dialog close-button markup

This is how plugin-owned markup stays aligned with the active theme's CTA conventions without turning the theme into the source owner of plugin behavior.

### Modal share URLs

Both the Opportunity Hub and Member Directory support shareable modal URLs:

- member query param: `?bci-member=...`
- opportunity query param: `?bci-opportunity=...`

The token preference order is:

1. `shareSlug`
2. `slug`
3. numeric `id`

The related client-side behavior lives in [`src/shared/modal-url-state.js`](../src/shared/modal-url-state.js).

## Opportunity Hub

### Member Directory rendering

The Opportunity Hub is the richest surface in the plugin. It combines:

- intro and optional anchor content
- filter UI
- approved opportunity cards
- opportunity-detail modal
- optional GravityCalendar output
- optional submission modal/form layer

### Data sources

- `ApprovedOpportunityService::all()` for approved opportunity payloads
- `MemberDirectoryService::all()` for member filter labels/lookups
- `Config` for type aliases, colors, thumbnails, calendar shortcode fallback, and form settings

### Opportunity Hub behavior

- the card grid contains only Resources and Recommended Vendors
- the card type filter starts with `All Types` and has no date filter
- the calendar contains only Events, Grants/RFPs, Learning entries, and Other date-sensitive entries
- calendar type and member filters are independent from the card-grid filters and combine with AND behavior across dimensions
- BCI Updates keep their primary category and render a secondary BCI Update badge
- modal submission credit includes the submitter name and formatted Gravity Forms submission date when available
- direct share URLs hydrate and open the matching modal item
- hidden CTA rows remove placeholder hash hrefs until real URLs exist
- calendar runtime assets are enqueued alongside the hub when needed
- the submit modal is layered over the same calendar region instead of living as a separate unrelated dialog surface

### Shortcode/context keys

The Opportunity Hub accepts both shortcode attributes and PHP context keys.

| Key | Purpose |
| --- | --- |
| `intro_content` / `introContent` | Primary intro copy |
| `intro_column_width` / `introColumnWidth` | Intro layout width |
| `anchor_content` / `anchorContent` | Secondary content area |
| `anchor_column_width` / `anchorColumnWidth` | Secondary content width |
| `submit_modal_intro` / `submitModalIntro` | Submit-modal intro text |
| `gravity_form_id` / `gravityFormId` | Explicit form override |
| `calendar_shortcode` / `calendarShortcode` | Explicit GravityCalendar shortcode override |
| `anchor` | Root wrapper ID |

When no calendar/form override is passed, the renderer falls back to saved BCI Hub settings.

## Member Directory

### What it renders

- grid of member cards
- dialog/modal for full member detail
- JSON payload script tag for the client runtime

### Data shape

Each member payload includes:

- title, slug, share slug
- summary and overview
- aliases
- community/founded/contact details
- social links
- programs HTML
- attachments
- video URL
- logo URL
- hero image URL

### Notable behavior

- share URLs open the modal directly
- CTA markup follows the theme button contract
- the renderer avoids synthetic placeholder-crests and instead expects real media/content
- empty state is explicit when no published members exist

## Video Slider

### Video Slider source of truth

The Video Slider can render from:

1. direct shortcode/PHP context, or
2. saved settings in the `Video Slider` tab

If no normalized slides exist, the renderer returns empty output.

### Input normalization

Each slide is normalized from plugin settings or direct context into:

- YouTube video ID / URL
- `youtube-nocookie` embed URL
- thumbnail attachment ID
- logo attachment ID
- label, eyebrow, title, description
- stable anchor target

### Video Slider behavior

- multiple slides enable loop-peek ordering behavior
- if a slide has no title, the renderer falls back to logo label or a generic title
- if a slide has no eyebrow, it falls back to `The Rooted in Community series`

## Newsletter Archives

### Newsletter Archives source of truth

Like the Video Slider, Newsletter Archives can render from:

1. direct shortcode/PHP context, or
2. saved settings in the `Newsletter Archives` tab

### Card contract

Each valid card needs:

- title
- URL

Optional fields:

- issue label
- bundled image preset
- direct attachment image (legacy/direct-context fallback)

### Bundled image preset contract

The primary contract is a preset image key chosen from [`SettingsSchema::newsletter_archive_image_presets()`](../includes/config/class-settings-schema.php). Those map to bundled images under:

- [`assets/images/newsletter-archives/`](../assets/images/newsletter-archives/)

### Newsletter Archives behavior

- the CTA button owns the link; the full card is not turned into one giant anchor
- preset images intentionally override legacy attachment IDs when both are present in mixed saved data
- cards with unsafe or blank URLs are skipped

## Public PHP API Summary

### Shortcodes

| Shortcode | Surface |
| --- | --- |
| `[community_resources_hub]` | Opportunity Hub |
| `[community_opportunity_hub]` | Opportunity Hub |
| `[community_member_directory]` | Member Directory |
| `[community_video_slider]` | Video Slider |
| `[community_newsletter_archives]` | Newsletter Archives |

### Render-returning functions

| Function | Surface |
| --- | --- |
| `community_resources_hub_render_opportunity_hub()` | Opportunity Hub |
| `community_resources_hub_render_member_directory()` | Member Directory |
| `community_resources_hub_render_video_slider()` | Video Slider |
| `community_resources_hub_render_newsletter_archives()` | Newsletter Archives |

### Echoing helpers

| Function | Surface |
| --- | --- |
| `community_resources_hub_the_opportunity_hub()` | Opportunity Hub |
| `community_resources_hub_the_member_directory()` | Member Directory |
| `community_resources_hub_the_video_slider()` | Video Slider |
| `community_resources_hub_the_newsletter_archives()` | Newsletter Archives |

## Frontend Troubleshooting

| Symptom | Check first |
| --- | --- |
| Surface renders but interactions do not work | Was the relevant built asset registered and enqueued from `build/`? |
| Surface renders empty | Did the underlying service/context produce usable data? |
| Direct modal URLs do nothing | Inspect the share slug/token and `modal-url-state` runtime |
| Opportunity filters look wrong | Inspect approved opportunity payloads and opportunity-type taxonomy aliases/colors |
| Video Slider outputs nothing | Check that at least one slide normalized to a valid video + thumbnail pair |
| Newsletter cards disappear | Check for missing/unsafe URLs or blank titles |
