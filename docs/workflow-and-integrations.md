<!-- markdownlint-disable MD013 -->

# Workflow and Integrations

This document covers the non-trivial submission and publishing workflow that connects Gravity Forms, the plugin-owned opportunity CPT, GravityCalendar, secure review links, ICS export, and optional Google sync.

## Dependency Stack

| Integration | Required for | Owner |
| --- | --- | --- |
| Advanced Custom Fields Pro | Settings UI and content-model field groups | `includes/config/`, `includes/content-model/` |
| Gravity Forms | Submission intake and field mapping | `includes/config/class-provisioner.php`, `includes/workflow/` |
| GravityCalendar | Calendar feed publishing and embed/runtime behavior | `includes/config/class-provisioner.php`, `includes/calendar/` |
| Google Apps Script endpoint | Optional approved-opportunity sync | `includes/workflow/class-google-sync-manager.php`, `integrations/google-apps-script/` |

## Workflow Overview

```text
Gravity Forms submission
  -> EntryBridge
  -> OpportunityRepository upsert
  -> approval status resolved
  -> opportunity type normalized and assigned
  -> secure review links or admin review
  -> approved opportunity published
  -> optional Google sync
  -> Opportunity Hub + calendar + ICS export
```

## Provisioning: Form and Feed Ownership

[`Provisioner`](../includes/config/class-provisioner.php) owns the create-or-adopt flow for third-party dependencies the plugin needs.

### Canonical resources

- Gravity Form title: `BCI Community Opportunity Submission`
- GravityCalendar add-on slug: `gravityview-calendar`
- persisted shortcode format: `[gravitycalendar id="..."]`

### Provisioning behavior

The provisioner will try to:

1. reuse the configured form/feed IDs if valid
2. reuse a saved shortcode's feed ID if valid
3. adopt an existing form or feed by expected name
4. create the missing form/feed if nothing valid exists

New forms use the split opportunity contract. Existing forms are changed only through the explicit, preflighted WP-CLI migration; normal `init` and dry-run requests do not rewrite Gravity Forms. Reconciliation preserves unrelated fields, notifications, confirmations, custom properties, and form settings.

After success it persists:

- `options_wm_bci_form_id`
- `options_wm_bci_calendar_feed_id`
- `options_wm_bci_calendar_feed_name`
- `options_wm_bci_calendar_shortcode`

## Field Mapping

The plugin stores field-map settings in the `Field Mapping` tab, but the semantic source of truth is [`SettingsSchema::field_map_keys()`](../includes/config/class-settings-schema.php).

### Semantic keys

| Key | Meaning |
| --- | --- |
| `time_sensitive` | Required Yes/No branch question |
| `opportunity_type` | Type selector field |
| `non_date_sensitive_type` | Resource/Recommended Vendor selector field |
| `bci_update` | Required secondary BCI Update question |
| `submitter_name` | Name of submitter |
| `title` | Opportunity title |
| `organization` | Organization label |
| `start_date` | Primary start date |
| `grant_deadline` | Grant / RFP deadline |
| `end_date` | End date |
| `start_time` | Start time |
| `end_time` | End time |
| `cost` | Cost label |
| `address` | Address/location text |
| `location_mode` | Virtual/in-person label |
| `description` | Long description |
| `info_url` | External information URL |
| `file_upload` | Uploaded file field |
| `approval_status` | Pending/Approved/Rejected field |

The defaults in [`SettingsSchema::field_map_defaults()`](../includes/config/class-settings-schema.php) match the canonical provisioned form shape.

## Entry Mirroring

[`EntryBridge`](../includes/workflow/class-entry-bridge.php) registers a `gform_entry_post_save_{form_id}` hook for the configured form.

### What happens on save

1. the bridge verifies the saved form ID matches the configured form
2. grant/RFP deadlines are mirrored into the start-date field used by GravityCalendar
3. approval status is resolved:
   - preserve explicit non-pending states
   - auto-approve configured WordPress users
   - otherwise default to `Pending`
4. the repository upserts the `bci_opportunity` post
5. if the post is approved and Google sync is configured, sync runs immediately

## Opportunity Persistence

[`OpportunityRepository`](../includes/workflow/class-opportunity-repository.php) is the canonical persistence owner.

It:

- finds the oldest canonical post for a source entry ID
- inserts or updates the opportunity post
- maps plugin semantic fields into post meta
- updates `approved_at`
- stores Gravity Forms `date_created` as UTC `submitted_at`
- assigns the normalized opportunity-type taxonomy term
- adds or removes only the `bci-update` opportunity tag, preserving unrelated tags
- maps approval state to WordPress post status:
  - `Approved` -> `publish`
  - `Pending` -> `pending`
  - everything else -> `draft`

## Review Links

[`ReviewHandler`](../includes/workflow/class-review-handler.php) serves secure approve/reject links through `admin-post.php`.

### Link contract

The request includes:

- `entry`
- `status`
- `expires`
- `signature`

### Validation behavior

The handler rejects links that are:

- missing required parts
- expired
- signed incorrectly
- pointing at an opportunity the repository cannot resolve

### On success

It:

- updates the opportunity meta
- updates the post status
- mirrors the approval field back into Gravity Forms
- adds a Gravity Forms note when supported
- runs Google sync when an approval moves to `Approved`

## Legacy Reconciliation

[`OpportunityReconciliation`](../includes/workflow/class-opportunity-reconciliation.php) is the migration/cleanup bridge for pre-plugin or partially migrated data.

### What it does

- schedules one-time pending reconciliation on new installs/updates
- groups existing opportunities by source-entry ID
- refreshes canonical posts from live entry data when possible
- merges or trashes duplicates
- imports approved entries that have no corresponding post
- surfaces unresolved source-less posts in a summary option

### Why it matters

This keeps the plugin-owned CPT layer authoritative even when historical data predates the current architecture.

## Google Sheets Sync

[`GoogleSyncManager`](../includes/workflow/class-google-sync-manager.php) sends approved opportunity payloads to a Google Apps Script endpoint.

### Required settings

- `wm_bci_google_sync_url`
- `wm_bci_google_sync_secret`

### Request contract

The plugin maps the source Gravity Forms entry into the established 15-column newsletter row and sends:

- `event`: `bci_entry_approved`
- `entryId` and `approvedAt`
- `headers` and the matching `row`
- source-entry admin URLs for the sync log

The exact JSON body is signed with HMAC-SHA256. The signature is sent in the `signature` query parameter because Apps Script web-app event objects do not expose arbitrary request headers. The shared secret is never included in the request body.

Apps Script content redirects are followed as a GET after the initial POST. WordPress records success only when the endpoint returns JSON with `ok: true` and a recognized `appended` or `duplicate` disposition; HTTP 200 alone is not treated as a successful sync.

### Status tracking

The plugin updates opportunity meta for:

- sync status
- attempted timestamp
- synced timestamp
- last error message

If the endpoint is not configured, the plugin marks sync as `skipped` rather than silently pretending success.

### Receiver ownership

The credential-free receiver source and deployment instructions live in [`integrations/google-apps-script/`](../integrations/google-apps-script/README.md). The receiver:

- validates the HMAC signature, event, entry ID, headers, and row width
- uses a script lock to serialize writes
- records entry IDs in `_BCI Sync Log` before treating retries as duplicates
- neutralizes form-originated spreadsheet formula prefixes
- returns only safe JSON errors and never logs secrets

### Recovery and backfill

[`GoogleSyncBackfill`](../includes/workflow/class-google-sync-backfill.php) owns explicit recovery jobs. It snapshots published Approved opportunities whose current status is not `synced`, processes two per WP-Cron run, revalidates each post before sending, and persists resumable progress in a non-autoloaded option.

Jobs pause when configuration disappears or the same remote error occurs three times consecutively. They never start when settings are merely saved. [`GoogleSyncAdminPanel`](../includes/workflow/class-google-sync-admin-panel.php) exposes **Sync One Entry**, **Start Backfill**, **Resume**, and **Retry Remaining** in the Hub settings sidebar.

For the personal local-test stack, store the personal deployment URL and secret only in the local database. Staging must not receive those values or run that backfill.

## ICS Export

[`OpportunityIcsExporter`](../includes/workflow/class-opportunity-ics-exporter.php) provides signed ICS downloads for approved opportunities.

### Important details

- it serves through `admin-post.php?action=wm_bci_opportunity_ics`
- the request includes `entry_id` and a signature
- only approved opportunities with a valid primary date can export
- grant/RFP items use the grant deadline as their primary date
- all-day and timed events are both supported

The public `Add to Calendar` CTA in the Opportunity Hub ultimately depends on this exporter.

## GravityCalendar Integration

The calendar subsystem augments GravityCalendar rather than replacing it.

### Event customization

[`EventCustomizer`](../includes/calendar/class-event-customizer.php) adds:

- tooltip markup
- normalized type labels/slugs
- type-specific color application
- `extendedProps` used by frontend filters
- member-filter identity and BCI Update metadata

[`EventFilter`](../includes/calendar/class-event-filter.php) requires both `Approved` status and `Is this a time-sensitive entry? = Yes` for the configured feed.

### Runtime assets

[`RuntimeAssets`](../includes/calendar/class-runtime-assets.php) makes sure the shared calendar runtime is registered and enqueued on the configured resources page and delegated legacy surfaces.

### Publishing settings

The publishing tab ties the workflow to the public page through:

- calendar page slug
- feed name
- feed ID
- fallback saved GravityCalendar shortcode

## Failure Modes

| Symptom | Likely owner |
| --- | --- |
| Form submission does not create/update opportunities | `EntryBridge`, field mapping, configured form ID |
| Opportunities exist but never publish | approval status field mapping, review flow, repository status mapping |
| Calendar embed is blank | saved shortcode/feed ID, GravityCalendar availability, runtime asset enqueue |
| Review link opens an error page | signature, expiry, or missing source-entry mapping |
| Google sync never fires | missing URL/secret, missing Apps Script properties, HTTP API failure, approval never reaches `Approved` |
| Google backfill pauses | configuration was removed or the same endpoint error occurred three times consecutively |
| Add to Calendar link is missing | ICS exporter signature or missing primary date |
