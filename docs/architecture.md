<!-- markdownlint-disable MD013 -->

# Architecture

This document explains how the plugin boots, how responsibilities are split across directories, and which files are authoritative for each subsystem.

## Bootstrap Sequence

The runtime starts in [`community-resources-hub.php`](../community-resources-hub.php), which defines plugin constants, loads the main plugin class, and registers activation/uninstall hooks.

```text
community-resources-hub.php
  -> Plugin::register()
     -> add_action( 'plugins_loaded', Plugin::boot )

plugins_loaded
  -> Plugin::boot()
     -> boot_config()
     -> boot_content_model()
     -> boot_workflow_layer()
     -> boot_calendar_integration()
     -> boot_frontend()
```

The boot order is intentional:

1. Config classes load first because several later subsystems depend on canonical settings and type-resolution helpers.
2. The content model loads before workflow and frontend code so CPT/taxonomy/meta owners exist before other layers query them.
3. Workflow classes load before calendar/frontend behavior so mirrored opportunity data is ready for publishing surfaces.
4. Frontend/classic entry points register last because they depend on config, content, workflow, and asset registration.

## Top-Level Runtime Owners

| Path | Primary owner | What it does |
| --- | --- | --- |
| [`community-resources-hub.php`](../community-resources-hub.php) | Plugin header/bootstrap | Defines constants and starts the runtime |
| [`includes/class-plugin.php`](../includes/class-plugin.php) | Boot coordinator | Loads and registers every subsystem |
| [`includes/config/`](../includes/config/) | Config and admin setup | Settings schema, options page, provisioning, health checks |
| [`includes/content-model/`](../includes/content-model/) | Data model | Post types, taxonomy, meta, editor tools, restore tooling |
| [`includes/workflow/`](../includes/workflow/) | Submission/publishing workflow | GF mirroring, approval links, reconciliation, ICS, Google sync |
| [`includes/calendar/`](../includes/calendar/) | GravityCalendar integration | Event filtering, event presentation, runtime assets |
| [`includes/frontend/`](../includes/frontend/) | Frontend rendering | Payload services and classic/theme-facing renderers |
| [`includes/assets/class-registry.php`](../includes/assets/class-registry.php) | Shared asset registry | Registers built CSS/JS handles for runtime consumption |
| [`includes/shortcodes/class-shortcodes.php`](../includes/shortcodes/class-shortcodes.php) | Classic editor API | Registers public shortcodes |
| [`includes/template-tags.php`](../includes/template-tags.php) | Theme PHP API | Exposes render and echo helpers |

## Subsystem Boundaries

### Config subsystem

Key owners:

- [`includes/config/class-settings-schema.php`](../includes/config/class-settings-schema.php)
- [`includes/config/class-config.php`](../includes/config/class-config.php)
- [`includes/config/class-acf-settings.php`](../includes/config/class-acf-settings.php)
- [`includes/config/class-provisioner.php`](../includes/config/class-provisioner.php)
- [`includes/config/class-health-checks.php`](../includes/config/class-health-checks.php)

Responsibilities:

- Defines every plugin-owned option name, default, sanitize rule, and ACF settings tab.
- Serves as the runtime authority for post-type names, field-map settings, opportunity-type display config, and integration endpoints.
- Owns the `Community Resources Hub` ACF options page and submenu registration.
- Can create or adopt the canonical Gravity Form and GravityCalendar feed.
- Surfaces dependency/setup problems to admins before public output silently breaks.

### Content-model subsystem

Key owners:

- [`includes/content-model/class-schema.php`](../includes/content-model/class-schema.php)
- [`includes/content-model/class-post-types.php`](../includes/content-model/class-post-types.php)
- [`includes/content-model/class-taxonomy.php`](../includes/content-model/class-taxonomy.php)
- [`includes/content-model/class-meta.php`](../includes/content-model/class-meta.php)
- [`includes/content-model/class-acf-post-fields.php`](../includes/content-model/class-acf-post-fields.php)
- [`includes/content-model/class-acf-term-fields.php`](../includes/content-model/class-acf-term-fields.php)
- [`includes/content-model/class-opportunity-editor.php`](../includes/content-model/class-opportunity-editor.php)
- [`includes/content-model/class-member-data-restore.php`](../includes/content-model/class-member-data-restore.php)

Responsibilities:

- Registers the `bci_member` and `bci_opportunity` post types.
- Registers the `opportunity-type` taxonomy plus term-meta fields such as alias, color, and thumbnail.
- Seeds the default opportunity types and backfills legacy opportunity posts into taxonomy terms.
- Defines which member/opportunity fields are stored in post meta and how they are represented.
- Provides the dedicated opportunity metabox/list-table UX instead of relying on a generic ACF field for type editing.
- Provides the admin-only member restore tool used for known migration recovery cases.

### Workflow subsystem

Key owners:

- [`includes/workflow/class-entry-bridge.php`](../includes/workflow/class-entry-bridge.php)
- [`includes/workflow/class-opportunity-repository.php`](../includes/workflow/class-opportunity-repository.php)
- [`includes/workflow/class-review-handler.php`](../includes/workflow/class-review-handler.php)
- [`includes/workflow/class-opportunity-reconciliation.php`](../includes/workflow/class-opportunity-reconciliation.php)
- [`includes/workflow/class-opportunity-ics-exporter.php`](../includes/workflow/class-opportunity-ics-exporter.php)
- [`includes/workflow/class-google-sync-manager.php`](../includes/workflow/class-google-sync-manager.php)

Responsibilities:

- Mirrors Gravity Forms entries into plugin-owned opportunity posts.
- Resolves approval status, including auto-approval for specific WordPress users.
- Maintains secure review links for approve/reject flows.
- Reconciles legacy opportunities into the canonical plugin-owned workflow.
- Exports signed ICS downloads for approved opportunities.
- Pushes approved opportunities to a Google Apps Script endpoint when configured.

### Calendar subsystem

Key owners:

- [`includes/calendar/class-event-filter.php`](../includes/calendar/class-event-filter.php)
- [`includes/calendar/class-event-customizer.php`](../includes/calendar/class-event-customizer.php)
- [`includes/calendar/class-tooltip-options.php`](../includes/calendar/class-tooltip-options.php)
- [`includes/calendar/class-runtime-assets.php`](../includes/calendar/class-runtime-assets.php)

Responsibilities:

- Filters/enriches GravityCalendar events for the configured BCI form.
- Adds tooltip markup, colors, type metadata, and display classes.
- Ensures the shared calendar runtime assets are available on the configured resources page and delegated legacy surfaces.

### Frontend/classic-rendering subsystem

Key owners:

- [`includes/frontend/class-opportunity-hub-renderer.php`](../includes/frontend/class-opportunity-hub-renderer.php)
- [`includes/frontend/class-member-directory-renderer.php`](../includes/frontend/class-member-directory-renderer.php)
- [`includes/frontend/class-video-slider-renderer.php`](../includes/frontend/class-video-slider-renderer.php)
- [`includes/frontend/class-newsletter-archives-renderer.php`](../includes/frontend/class-newsletter-archives-renderer.php)
- [`includes/frontend/class-approved-opportunity-service.php`](../includes/frontend/class-approved-opportunity-service.php)
- [`includes/frontend/class-member-directory-service.php`](../includes/frontend/class-member-directory-service.php)
- [`includes/support/class-render-support.php`](../includes/support/class-render-support.php)

Responsibilities:

- Builds JSON payloads from plugin-owned CPT/meta data.
- Renders classic/theme-compatible markup for all public BCI surfaces.
- Enqueues the built assets those surfaces need.
- Centralizes modal-share URLs, CTA icons, wrapper attributes, and dialog helpers.

## Activation and Uninstall

### Activation

`Plugin::activate()` does more than store a version string:

- stores `community_resources_hub_version`
- stores `community_resources_hub_installed_at` on first install
- seeds missing settings defaults from `SettingsSchema::defaults()`
- attempts best-effort BCI dependency provisioning
- marks settings seed and opportunity reconciliation timestamps
- flushes rewrite rules when WordPress exposes the helper
- clears runtime caches

### Uninstall

`Plugin::uninstall()` removes plugin-owned options and transient-backed summaries, including:

- version/install metadata
- settings options created from `SettingsSchema::option_names()`
- reconciliation summary and lock/pending markers
- member data restore summary

It also clears runtime caches after removing those options.

## Data Flow Overview

### Opportunity submission to public output

```text
Gravity Forms entry
  -> EntryBridge
  -> OpportunityRepository upsert
  -> approval status + taxonomy assignment
  -> optional Google sync when approved
  -> ApprovedOpportunityService payload
  -> OpportunityHubRenderer output
  -> calendar runtime / modal / ICS links
```

### Member content to modal output

```text
bci_member post + meta + attachments
  -> MemberDirectoryService payload
  -> MemberDirectoryRenderer grid + dialog
  -> built member-directory runtime hydrates modal interactions
```

## Asset Ownership

The plugin deliberately separates source, registration, and runtime consumption:

- Source SCSS lives under `blocks/*/style.scss`.
- Shared JS sources live under `src/`.
- Built outputs land under `build/`.
- Runtime registration happens in [`includes/assets/class-registry.php`](../includes/assets/class-registry.php).
- Renderers and calendar runtime classes enqueue registered handles instead of reaching into arbitrary file paths.

See [Development](development.md) for the exact build chain.

## Caching

Two public-data services cache their computed payloads in transients:

| Service | Cache key | Default TTL | Invalidated by |
| --- | --- | --- | --- |
| `MemberDirectoryService` | `community_resources_hub_member_directory` | 300 seconds | member saves/deletes/trash/cache clean |
| `ApprovedOpportunityService` | `community_resources_hub_approved_opportunities` | 300 seconds | opportunity saves, member saves, opportunity-type term changes |

This cache layer is part of the public contract: admin actions that repair content also flush the related transients so frontend payloads refresh without manual cache edits.

## Ownership Rules

- `build/` is generated output and should never be the source of truth.
- The plugin owns BCI content and rendering contracts, but it consumes some theme-level classes/icons for CTA parity rather than redefining them.
- The plugin can provision or adopt third-party resources, but it does not own the activation/licensing lifecycle of ACF Pro, Gravity Forms, or GravityCalendar.
- If behavior touches both classic markup and a built JS runtime, the authoritative chain is source file -> build output -> asset registry -> renderer/runtime enqueue.
