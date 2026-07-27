<!-- markdownlint-disable MD013 -->

# Community Resources Hub

Community Resources Hub is the plugin-owned BCI application layer for the Waters Meet site. It owns the `Community Resources Hub` admin menu, the BCI content model, the Gravity Forms approval workflow, the classic/frontend renderers, and the GravityCalendar runtime glue that powers the public BCI resources experience.

## At a Glance

| Item                   | Value                                                     |
| ---------------------- | --------------------------------------------------------- |
| Plugin file            | `community-resources-hub.php`                           |
| Version                | `0.1.8`                                                 |
| Requires WordPress     | `6.5+`                                                  |
| Requires PHP           | `7.4+`                                                  |
| Top-level admin slug   | `bci-hub`                                               |
| Core dependencies      | Advanced Custom Fields Pro, Gravity Forms                 |
| Conditional dependency | GravityCalendar for calendar publishing/embed behavior    |
| Optional integration   | Google Apps Script endpoint for approved-opportunity sync |

## Documentation Map

| Document                                                      | Covers                                                                                          |
| ------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| [Architecture](docs/architecture.md)                           | Bootstrap order, subsystem boundaries, activation/uninstall, caching, ownership rules           |
| [Admin and Operations](docs/admin-and-operations.md)           | Admin menu, settings tabs, setup flow, provisioning, health checks, recovery tools              |
| [Content Model](docs/content-model.md)                         | CPTs, taxonomy, meta keys, ACF field groups, editor/list-table behavior                         |
| [Frontend Surfaces](docs/frontend-surfaces.md)                 | Opportunity Hub, Member Directory, Video Slider, Newsletter Archives, shortcodes, template tags |
| [Workflow and Integrations](docs/workflow-and-integrations.md) | Gravity Forms mirroring, review links, Google sync, ICS export, GravityCalendar enrichment      |
| [Development](docs/development.md)                             | Source-vs-build ownership, scripts, test inventory, safe edit workflow                          |

## What This Plugin Owns

- The top-level `Community Resources Hub` / `BCI Hub` admin surface and its ACF-backed settings screen.
- The `bci_member` and `bci_opportunity` post types plus the `opportunity-type` taxonomy.
- Plugin-owned ACF field groups for BCI members, BCI opportunities, opportunity-type term meta, and Hub settings.
- The Gravity Forms submission bridge that mirrors form entries into plugin-owned opportunity posts.
- Secure approval/rejection links, approved-opportunity publishing, legacy reconciliation, and optional Google sync.
- The classic/frontend renderers for the Opportunity Hub, Member Directory, Video Slider, and Newsletter Archives.
- Shared calendar runtime assets plus GravityCalendar event filtering, colors, tooltips, and ICS downloads.

## Quick Start for Site Operators

1. Activate the plugin and confirm the `Community Resources Hub` admin menu appears.
2. Open `Community Resources Hub > Settings` and resolve any health-check notices.
3. If Gravity Forms and GravityCalendar are active, use `Create or adopt BCI Hub resources` to provision or adopt the canonical form/feed pair.
4. Review the settings tabs in this order: `Workflow Setup`, `Approvals`, `Publishing`, `Video Slider`, `Newsletter Archives`, `Google Sheets Sync`, `Field Mapping`.
5. Manage members under `BCI Members`, opportunities under `BCI Opportunities`, primary presentation under `Opportunity Types`, and secondary classifications under `Opportunity Tags`.
6. Verify the public resources page on the configured slug after major settings or content-model changes.

Detailed operator guidance lives in [Admin and Operations](docs/admin-and-operations.md).

## Quick Start for Developers

1. Read [Architecture](docs/architecture.md) and [Development](docs/development.md) before changing source owners.
2. Edit source-owned files only:
   - PHP/runtime owners in `includes/`
   - block/view sources in `blocks/`
   - shared runtime sources in `src/`
3. Rebuild generated assets only when source changes require it:

```bash
yarn build
```

1. Run the targeted smoke/regression scripts for the surface you touched:

```bash
php tests/admin-menu-contract-test.php
node tests/modal-url-state-test.mjs
```

The full build/test workflow is documented in [Development](docs/development.md).

## Public Entry Points

### Shortcodes

All shortcode entry points share the same renderer classes that the PHP template-tag APIs use. Each shortcode accepts an optional `anchor` attribute that becomes the root wrapper `id`.

| Shortcode                           | Surface             | Notes                                                                                              |
| ----------------------------------- | ------------------- | -------------------------------------------------------------------------------------------------- |
| `[community_resources_hub]`       | Opportunity Hub     | Alias of`[community_opportunity_hub]`                                                            |
| `[community_opportunity_hub]`     | Opportunity Hub     | Supports context overrides for intro, anchor content, modal intro, form ID, and calendar shortcode |
| `[community_member_directory]`    | Member Directory    | Supports`eyebrow`, `title`, and `anchor`                                                     |
| `[community_video_slider]`        | Video Slider        | Falls back to saved BCI Hub settings when slides are not passed directly                           |
| `[community_newsletter_archives]` | Newsletter Archives | Falls back to saved BCI Hub settings when cards are not passed directly                            |

### Template tags

Render-returning functions:

```php
community_resources_hub_render_opportunity_hub( array $context = array() );
community_resources_hub_render_member_directory( array $context = array() );
community_resources_hub_render_video_slider( array $context = array() );
community_resources_hub_render_newsletter_archives( array $context = array() );
```

Echoing helpers:

```php
community_resources_hub_the_opportunity_hub( array $context = array() );
community_resources_hub_the_member_directory( array $context = array() );
community_resources_hub_the_video_slider( array $context = array() );
community_resources_hub_the_newsletter_archives( array $context = array() );
```

See [Frontend Surfaces](docs/frontend-surfaces.md) for supported context keys and renderer behavior.

## Repository Map

| Path                                 | Role                                                                                      |
| ------------------------------------ | ----------------------------------------------------------------------------------------- |
| `community-resources-hub.php`      | Plugin header, constants, activation/uninstall hooks, bootstrap handoff                   |
| `includes/class-plugin.php`        | Runtime boot order and subsystem registration                                             |
| `includes/config/`                 | Settings schema, ACF options UI, provisioning, health checks, runtime config authority    |
| `includes/content-model/`          | CPT/taxonomy registration, ACF post/term fields, editor metaboxes, member restore tooling |
| `includes/workflow/`               | Gravity Forms mirroring, review links, reconciliation, ICS export, Google sync            |
| `integrations/google-apps-script/` | Credential-free Apps Script receiver source and deployment guide                          |
| `includes/calendar/`               | GravityCalendar filtering, event customization, runtime asset enqueueing                  |
| `includes/frontend/`               | Data services and renderer owners for public/classic surfaces                             |
| `includes/shortcodes/`             | Classic editor entry points                                                               |
| `includes/template-tags.php`       | Theme-facing PHP API                                                                      |
| `includes/assets/`                 | Shared frontend asset registration handles                                                |
| `blocks/`                          | Build-owned view entry points and SCSS for shipped frontend surfaces                      |
| `src/`                             | Shared JavaScript runtime code used by built assets                                       |
| `build/`                           | Generated assets consumed at runtime; never edit directly                                 |
| `assets/`                          | Static images, legacy/static CSS, and curated upload helpers                              |
| `tests/`                           | Direct-run PHP and Node smoke/regression scripts                                          |

## Build and Verification Commands

| Command           | Purpose                                                       |
| ----------------- | ------------------------------------------------------------- |
| `yarn build`    | Rebuild SCSS and JavaScript into`build/`                    |
| `yarn start`    | Watch SCSS and JavaScript sources during active frontend work |
| `yarn lint:js`  | Lint JS view/runtime sources                                  |
| `yarn lint:css` | Lint SCSS sources                                             |

Development/testing details and broader command examples live in [Development](docs/development.md).

## Ownership Rules

- Do not edit `build/` by hand. Change `blocks/`, `src/`, or PHP owners and rebuild only when needed.
- The canonical settings names, defaults, and ACF tab layout live in `includes/config/class-settings-schema.php`.
- The canonical content-model names and default opportunity types live in `includes/content-model/class-schema.php`.
- Shared frontend handles are registered in `includes/assets/class-registry.php`; renderer classes should consume those handles instead of inventing new runtime paths.
- Theme-level visual systems still belong to the active Waters Meet theme. This plugin consumes theme button/icon conventions but does not own the site-wide design system.

## Related Source Notes

- Member header image exports used during member-content rollout live under `assets/uploads/member-grid-headers/`; see `assets/uploads/member-grid-headers/README.md`.
- The current README is intentionally the hub. Subsystem detail should go into `docs/*.md` instead of expanding this file into a monolith.
