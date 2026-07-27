<!-- markdownlint-disable MD013 -->

# Admin and Operations

This guide is for site operators, implementers, and developers who need to understand the plugin's wp-admin surfaces and the supported maintenance actions.

## Admin Surfaces

The plugin registers a top-level admin experience named `Community Resources Hub` with slug `bci-hub`.

| Surface | Owner | Notes |
| --- | --- | --- |
| Top-level menu | [`SettingsSchema::options_page_args()`](../includes/config/class-settings-schema.php) | ACF options page for the plugin |
| `Settings` submenu | [`AcfSettings::register_admin_submenus()`](../includes/config/class-acf-settings.php) | Anchors the options page under its own menu |
| `BCI Members` | [`Schema::MEMBER_POST_TYPE`](../includes/content-model/class-schema.php) | CPT UI appears under the Hub menu |
| `Add New BCI Member` | [`AcfSettings::register_admin_submenus()`](../includes/config/class-acf-settings.php) | Direct create shortcut |
| `BCI Opportunities` | [`Schema::OPPORTUNITY_POST_TYPE`](../includes/content-model/class-schema.php) | CPT UI appears under the Hub menu |
| `Add New BCI Opportunity` | [`AcfSettings::register_admin_submenus()`](../includes/config/class-acf-settings.php) | Direct create shortcut |
| `Opportunity Types` | [`AcfSettings::register_admin_submenus()`](../includes/config/class-acf-settings.php) | Taxonomy editor for aliases, colors, and thumbnails |
| `Opportunity Tags` | [`AcfSettings::register_admin_submenus()`](../includes/config/class-acf-settings.php) | Secondary opportunity classifications such as BCI Update |

## Settings Tabs

The canonical tab order and field definitions live in [`includes/config/class-settings-schema.php`](../includes/config/class-settings-schema.php).

| Tab | What it controls |
| --- | --- |
| `Workflow Setup` | Gravity Forms form ID, approval field ID, notification name |
| `Approvals` | Review recipients and auto-approved WordPress users |
| `Publishing` | Resources page slug, GravityCalendar feed name/ID, fallback shortcode |
| `Video Slider` | Classic Video Slider wrapper copy and repeater rows |
| `Newsletter Archives` | Classic Newsletter Archives wrapper copy and repeater rows |
| `Google Sheets Sync` | Optional Apps Script endpoint URL and shared secret |
| `Field Mapping` | Gravity Forms field IDs for semantic opportunity data |

## First-Time Setup Checklist

1. Activate the plugin.
2. Confirm Advanced Custom Fields Pro is active.
3. Confirm Gravity Forms is active.
4. Confirm GravityCalendar is active if the site expects the calendar surface.
5. Open `Community Resources Hub > Settings`.
6. Review the health notice at the top of the page.
7. If the provisioning button appears, click `Create or adopt BCI Hub resources`.
8. Confirm the saved Form ID, Calendar Feed ID, and Calendar Shortcode are populated.
9. Confirm the `Calendar Page Slug` matches the intended public resources page.
10. Review `Field Mapping` to ensure the configured Gravity Form still matches the site's actual field IDs.

## Health Checks and Notices

[`HealthChecks`](../includes/config/class-health-checks.php) renders admin notices for users with `manage_options`.

It checks for:

- missing ACF Pro functions
- missing Gravity Forms runtime
- missing GravityCalendar shortcode support
- blank or invalid form/feed/shortcode settings
- incomplete field mapping
- pending or unresolved legacy opportunity reconciliation
- provisioning/reconciliation/member-restore result notices stored in transients

The goal is to fail loudly in wp-admin before public surfaces quietly degrade.

## Provisioning: Create or Adopt BCI Hub Resources

The provisioning action is owned by [`Provisioner`](../includes/config/class-provisioner.php).

What it does:

- creates or adopts the canonical Gravity Form titled `BCI Community Opportunity Submission`
- creates or adopts a GravityCalendar feed for that form
- persists:
  - `options_wm_bci_form_id`
  - `options_wm_bci_calendar_feed_id`
  - `options_wm_bci_calendar_feed_name`
  - `options_wm_bci_calendar_shortcode`
- seeds form/feed defaults that the workflow and calendar layer expect

The action is available only when:

- the current user has `manage_options`
- Gravity Forms is loaded
- GravityCalendar can be detected through its add-on class or shortcode

## Managing BCI Members

### Member content ownership

- Members are stored as the `bci_member` post type.
- Plugin-owned member fields are registered through the member ACF field group in [`AcfPostFields`](../includes/content-model/class-acf-post-fields.php).
- Public output is built from [`MemberDirectoryService`](../includes/frontend/class-member-directory-service.php).

### Operational notes

- Published member posts become eligible for the Member Directory payload.
- The member directory cache is automatically flushed on save/delete/trash.
- Hero image helper exports live under [`assets/uploads/member-grid-headers/`](../assets/uploads/member-grid-headers/README.md).

## Managing BCI Opportunities

### Opportunity content ownership

- Opportunities are stored as the `bci_opportunity` post type.
- The plugin mirrors Gravity Forms entries into this CPT, but admins can also inspect/edit the stored posts directly.
- Opportunity type editing is intentionally handled by the custom metabox in [`OpportunityEditor`](../includes/content-model/class-opportunity-editor.php), not by a generic ACF post field.

### List-table features

The plugin adds:

- date filter
- member filter
- opportunity-type filter
- source-entry/reconciliation diagnostics columns
- a persistent `Reconcile legacy BCI opportunities` action

### Approval behavior

- `Approved` opportunities publish publicly.
- `Pending` opportunities stay in `pending`.
- `Rejected` opportunities fall back to `draft`.

## Opportunity Types

The `Opportunity Types` screen controls the public display contract used by both the frontend and GravityCalendar.

Each term can carry:

- canonical name
- slug
- alias used for badges/filter labels
- color used in cards/calendar UI
- thumbnail attachment used by opportunity surfaces

Changes here affect:

- Opportunity Hub badges and filters
- calendar filter options
- calendar event colors
- frontend resource thumbnails

## Opportunity Contract Migration

Preview the existing-form and entry migration with no writes:

```bash
wp community-resources-hub migrate-opportunity-contract
```

After taking a database backup and reviewing an error-free plan, apply and verify it explicitly:

```bash
wp community-resources-hub migrate-opportunity-contract --apply
wp community-resources-hub migrate-opportunity-contract
```

The final dry run should report no proposed form, entry, post, taxonomy, or term-deletion changes. The migration updates the Gravity Form branch fields, normalizes legacy types, mirrors calendar dates, synchronizes approved opportunity posts, creates the BCI Update tag, and removes the obsolete BCI Update primary type only after its relationships reach zero.

The Waters Meet production snapshot also contains one Event exception: Gravity Forms entry 125 has no start date but has an end date of March 21, 2026. The migration explicitly copies that existing end date into the start-date field so the date-sensitive entry remains eligible for GravityCalendar; the original end date is preserved.

## Recovery and Maintenance Actions

### Google Sheet sync recovery

Owner: [`GoogleSyncBackfill`](../includes/workflow/class-google-sync-backfill.php) and [`GoogleSyncAdminPanel`](../includes/workflow/class-google-sync-admin-panel.php)

The **Google Sheets Sync** panel appears in the sidebar of **Community Resources Hub > Settings**. It reports configuration state, approved/synced/pending/failed/skipped/unsynced counts, saved job progress, and the latest failed opportunity.

Actions are explicit:

- **Sync One Entry** sends the newest eligible approved opportunity and is the required first deployment check.
- **Start Backfill** snapshots all currently unsynced approved opportunities when no prior job exists.
- **Resume** continues a paused snapshot from its saved cursor.
- **Retry Remaining** creates a fresh snapshot of every item still not marked `synced`.

The worker handles two rows per WP-Cron run. It pauses when configuration is missing or three consecutive rows return the same error. Saving Google settings never starts a backfill.

For a personal local-test deployment, configure and run these controls only on the local site. Do not copy its endpoint or shared secret into staging.

### Legacy opportunity reconciliation

Owner: [`OpportunityReconciliation`](../includes/workflow/class-opportunity-reconciliation.php)

Use this when:

- the install has older BCI opportunity posts that predate the current workflow
- duplicate posts exist for the same source entry
- approved Gravity Forms entries are missing from the CPT layer

What it does:

- groups existing opportunities by source-entry ID
- refreshes canonical posts from live GF data when possible
- trashes duplicate non-canonical posts
- imports approved entries missing from the CPT
- surfaces unresolved source-less posts in a persisted summary

### Member data restore

Owner: [`MemberDataRestore`](../includes/content-model/class-member-data-restore.php)

Use this when known member fields were lost during migration and the canonical recovery catalog still matches the live site.

Where it appears:

- top actions row of the `BCI Members` list table

What it restores:

- post content
- social-link repeater rows
- logo and hero-image attachment IDs

Important constraint:

- This is an admin-triggered repair tool. The task is not complete until the restored values are verified on the actual wp-admin surface that editors use.

## Common Operational Flows

### New install or major rebuild

1. Activate plugin.
2. Resolve dependency notices.
3. Provision/adopt form and feed.
4. Confirm settings tabs.
5. Reconcile legacy opportunities if prompted.
6. Load the public resources page and confirm calendar/renderers output.

### Editor adds a new member

1. Create a `BCI Member`.
2. Fill the plugin-owned ACF fields.
3. Publish the post.
4. Confirm it appears in the Member Directory and modal payload.

### Editor submits or reviews a new opportunity

1. Submission enters through the configured Gravity Form.
2. The plugin mirrors it into `bci_opportunity`.
3. Review links or admin edits move the approval state.
4. Once approved, the opportunity appears in the public hub and can be exported to ICS.

## Troubleshooting

| Symptom | Check first |
| --- | --- |
| Settings page is missing | Is ACF Pro active, and did the plugin activate cleanly? |
| Calendar is empty | Are GravityCalendar and the saved shortcode/feed ID configured? |
| Public opportunity cards are missing | Are opportunities `Approved` and published? Did reconciliation/field mapping break? |
| Opportunity type badge/filter text looks wrong | Inspect the `Opportunity Types` taxonomy terms and alias/color meta |
| Member directory is empty | Confirm published `bci_member` posts and the member cache invalidation path |
| Video Slider or Newsletter Archives renders nothing | Check the saved repeater rows in the corresponding settings tab |
| Member fields disappeared after migration work | Use the `Restore BCI Member Data` action, then verify results in wp-admin |
| Approved opportunity is missing from Google Sheets | Check the Google Sheets Sync panel, use Sync One Entry, and review the latest saved error before starting a backfill |

## Runtime Verification Expectations

For admin-facing changes, source inspection is not enough. Verify on the actual admin/runtime surface whenever feasible:

- settings saves should round-trip through ACF
- provisioning should persist IDs/shortcode values
- reconciliation should produce a clear summary and refresh public data
- member restore should visibly repopulate the intended editor fields
- a Google sync test should produce exactly one row, persist `synced`, and return `duplicate` without another row when retried
