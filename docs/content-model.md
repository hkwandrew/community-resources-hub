<!-- markdownlint-disable MD013 -->

# Content Model

This document describes the plugin-owned CPTs, taxonomy, meta schema, and editor contracts.

## Canonical Owners

| Concern | Owner |
| --- | --- |
| Schema constants and defaults | [`includes/content-model/class-schema.php`](../includes/content-model/class-schema.php) |
| Post-type registration | [`includes/content-model/class-post-types.php`](../includes/content-model/class-post-types.php) |
| Taxonomy registration and term-meta seeding | [`includes/content-model/class-taxonomy.php`](../includes/content-model/class-taxonomy.php) |
| ACF post field groups | [`includes/content-model/class-acf-post-fields.php`](../includes/content-model/class-acf-post-fields.php) |
| ACF term fields | [`includes/content-model/class-acf-term-fields.php`](../includes/content-model/class-acf-term-fields.php) |
| Opportunity admin metabox/list-table UI | [`includes/content-model/class-opportunity-editor.php`](../includes/content-model/class-opportunity-editor.php) |
| Member recovery tooling | [`includes/content-model/class-member-data-restore.php`](../includes/content-model/class-member-data-restore.php) |

## Post Types

### `bci_member`

| Property | Value |
| --- | --- |
| Public | Yes |
| REST | Yes |
| Menu parent | `bci-hub` |
| Supported core fields | `title`, `editor`, `thumbnail`, `custom-fields` |
| Archive | No |

Use `bci_member` for partner/member profiles that feed the Member Directory and related organization lookups.

### `bci_opportunity`

| Property | Value |
| --- | --- |
| Public | No |
| REST | No |
| Menu parent | `bci-hub` |
| Supported core fields | `title`, `thumbnail`, `custom-fields` |
| Archive | No |
| Taxonomies | `opportunity-type`, `opportunity-tag` |

`bci_opportunity` is the plugin-owned persistence layer for mirrored Gravity Forms submissions and approved resources/events.

## Taxonomy: `opportunity-type`

The taxonomy is seeded and normalized by [`Taxonomy`](../includes/content-model/class-taxonomy.php). It is more than a label bucket: the frontend and calendar layer both rely on its term meta.

### Default types

| Name | Slug | Alias | Color | Legacy term ID |
| --- | --- | --- | --- | --- |
| Workshop, Training, or Other Learning | `learning` | `Learning` | `#520066` | `15` |
| Grant / RFP | `grant-rfp` | `Grant/RFP` | `#d9a242` | `16` |
| Event | `event` | `Events` | `#c2385a` | `17` |
| Resource | `resource` | `Resources` | `#418359` | `18` |
| Recommended Vendor | `recommended-vendor` | `Recommended Vendors` | `#7e5f8e` | _(none)_ |
| Other | `other` | _(blank)_ | `#5c6e7a` | `20` |

Resources and Recommended Vendors are non-date-sensitive entries and appear in the card grid. Events, Grants/RFPs, Learning entries, and Other date-sensitive entries appear in the calendar.

## Taxonomy: `opportunity-tag`

`opportunity-tag` is an admin-visible, non-hierarchical secondary classification. The default `bci-update` term identifies a BCI Update without replacing the entry's primary type. Public cards, modals, and calendar tooltips render this tag as a `#004966` secondary badge.

### Term meta

| Meta key | Purpose |
| --- | --- |
| `alias` | Frontend badge/filter label when different from the canonical term name |
| `color` | Frontend card/calendar color |
| `thumbnail` | Attachment ID for resource-type imagery |

### Legacy normalization

On first sync, the plugin can:

- seed missing default terms
- backfill taxonomy assignments on existing opportunities
- normalize legacy numeric type values in post meta to the canonical term name

## Member Meta Schema

The member semantic map is defined in [`Schema::member_field_map()`](../includes/content-model/class-schema.php).

| Semantic key | Stored meta key | Typical use |
| --- | --- | --- |
| `aliases` | `wm_bci_member_aliases` | Alternate organization names for matching/filtering |
| `community_served` | `wm_bci_member_community_served` | Public modal detail |
| `founded_year` | `wm_bci_member_founded_year` | Public modal detail |
| `contact_email` | `wm_bci_member_contact_email` | Public modal action |
| `website_url` | `wm_bci_member_website_url` | Public modal action |
| `phone` | `wm_bci_member_phone` | Public modal detail |
| `main_office` | `wm_bci_member_main_office` | Public modal detail |
| `social_links` | `wm_bci_member_social_links` | ACF repeater-backed social rows |
| `programs` | `wm_bci_member_programs` | Rich text block shown in modal |
| `attachments` | `wm_bci_member_attachments` | Attachment chips/links |
| `video_url` | `wm_bci_member_video_url` | Spotlight video CTA |
| `video_label` | `wm_bci_member_video_label` | Optional display label |
| `logo_url` | `wm_bci_member_logo_url` | Modal/card logo asset |
| `hero_image_url` | `wm_bci_member_hero_image_url` | Card/modal hero image |

## Opportunity Meta Schema

The opportunity semantic map is defined in [`Schema::opportunity_field_map()`](../includes/content-model/class-schema.php).

| Semantic key | Stored meta key | Typical use |
| --- | --- | --- |
| `source_entry_id` | `wm_bci_source_entry_id` | Canonical link back to Gravity Forms |
| `approval_status` | `wm_bci_approval_status` | Publishing state and admin diagnostics |
| `approved_at` | `wm_bci_approved_at` | Approval timestamp |
| `submitted_at` | `wm_bci_submitted_at` | Gravity Forms submission timestamp, stored in UTC |
| `opportunity_type` | `wm_bci_opportunity_type` | Stored canonical term name |
| `submitter_name` | `wm_bci_submitter_name` | Public/ops detail |
| `organization` | `wm_bci_organization` | Member matching + public label |
| `start_date` | `wm_bci_start_date` | Primary date for most types |
| `grant_deadline` | `wm_bci_grant_deadline` | Primary date for grants/RFPs |
| `end_date` | `wm_bci_end_date` | Range display and ICS end logic |
| `start_time` | `wm_bci_start_time` | Public/card/calendar timing |
| `end_time` | `wm_bci_end_time` | Public/card/calendar timing |
| `location_mode` | `wm_bci_location_mode` | Virtual/in-person style label |
| `address` | `wm_bci_address` | Public modal + ICS location |
| `cost` | `wm_bci_cost` | Public modal detail |
| `info_url` | `wm_bci_info_url` | Visit Website CTA |
| `file_upload` | `wm_bci_file_upload` | Attachment source |
| `google_sync_status` | `wm_bci_google_sync_status` | Google Apps Script sync status |
| `google_sync_attempted_at` | `wm_bci_google_sync_attempted_at` | Last sync attempt timestamp |
| `google_sync_synced_at` | `wm_bci_google_sync_synced_at` | Last successful sync timestamp |
| `google_sync_error` | `wm_bci_google_sync_error` | Last sync error message |

## ACF Field Groups

### Post field groups

The plugin registers two post field groups:

- BCI Member fields targeting `bci_member`
- BCI Opportunity fields targeting `bci_opportunity`

Important contract:

- `Opportunity Type` is intentionally not treated as a normal ACF opportunity field. The plugin owns it through the taxonomy + custom metabox flow so type state stays aligned with seeded terms, aliases, and colors.

### Term fields

The plugin also registers an ACF term field group for `opportunity-type` so editors can manage:

- alias
- color
- thumbnail

## Opportunity Editor Contract

[`OpportunityEditor`](../includes/content-model/class-opportunity-editor.php) adds admin UI beyond raw post fields.

### Metabox

The `Opportunity Details` metabox shows:

- source entry ID
- approval status
- primary date
- opportunity-type selector

Saving the metabox:

- assigns the selected taxonomy term
- normalizes saved opportunity-type meta to the term's canonical name

### List-table filters

The opportunity list screen gets filters for:

- date buckets
- member/organization
- opportunity type

This is the supported operator-facing way to audit the mirrored workflow data.

## Member Recovery and Migration Notes

[`MemberDataRestore`](../includes/content-model/class-member-data-restore.php) exists because some member fields were historically lost during migration work. It restores known catalog data by matching published member titles.

What it can repair:

- post content
- social-link repeater storage
- logo attachment IDs
- hero-image attachment IDs

Helper assets used during member rollout live under [`assets/uploads/member-grid-headers/`](../assets/uploads/member-grid-headers/README.md).

## REST and Public Data Notes

- `bci_member` is `show_in_rest => true`.
- `bci_opportunity` is intentionally not public/REST-exposed; public opportunity data is shaped through plugin-owned payload services and renderers.
- Several meta definitions are also registered for REST/schema correctness, but the plugin's public frontend contract remains renderer/service driven rather than generic API driven.
