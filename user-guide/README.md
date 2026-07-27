<!-- markdownlint-disable MD013 -->

# Community Resources Hub User Guide

This guide is for the WordPress administrators and content editors who manage the Community Resources Hub. It explains the everyday tasks available in WordPress.

## What the Hub Manages

The Community Resources Hub brings the following BCI content into one place:

- BCI member profiles and the public Member Directory
- Community opportunities, events, grants, resources, and updates
- Opportunity review and approval
- The public Opportunity Hub and calendar
- Spotlight videos
- Newsletter archive cards

Most tasks begin in the **Community Resources Hub** menu in the WordPress dashboard.

## Before You Begin

You need a WordPress account with permission to edit content. Some setup and recovery tools are available only to administrators.

If you do not see **Community Resources Hub** in the dashboard menu, ask a site administrator to confirm that:

- the plugin is active;
- your account has the necessary permissions; and
- Advanced Custom Fields Pro and Gravity Forms are active.

GravityCalendar must also be active when the site uses the calendar view.

## Quick Start

1. Sign in to WordPress.
2. Open **Community Resources Hub**.
3. Choose the area you want to manage: **BCI Members**, **BCI Opportunities**, **Opportunity Types**, or **Settings**.
4. Make and save your changes.
5. Check the public BCI resources page to confirm the result.

## Dashboard Menu

| Menu item | Use it to |
| --- | --- |
| **Settings** | Configure approvals, publishing, videos, newsletters, and integrations |
| **BCI Members** | View, search, edit, and publish member profiles |
| **Add New BCI Member** | Create a member profile |
| **BCI Opportunities** | Review, filter, edit, and publish opportunities |
| **Add New BCI Opportunity** | Create an opportunity manually |
| **Opportunity Types** | Manage the labels, colors, and images used for opportunity categories |

The menu items you see depend on your WordPress permissions.

## Managing BCI Members

Published BCI Members appear in the public Member Directory and can also be used by the Opportunity Hub's member filter.

### Add a member

1. Go to **Community Resources Hub > Add New BCI Member**.
2. Enter the organization's name in the title field.
3. Add the main overview in the WordPress content editor.
4. Complete the relevant **BCI Member Fields**.
5. Add a logo and hero image when available.
6. Select **Publish**.
7. Open the public Member Directory and check the card and full-detail window.

### Member fields

| Field group | What to enter |
| --- | --- |
| **Aliases** | Alternate organization names, one per line |
| **Community Served** | A short description of the community the organization serves |
| **Founded Year** | The year the organization was founded |
| **Contact Email**, **Website URL**, **Phone** | Public contact information |
| **Main Office** | Main office or service location |
| **Social Links** | Platform, full URL, and an optional display label |
| **Programs** | Program information shown in the member details |
| **Attachment URLs** | One public attachment URL per line |
| **Video URL** and **Video Label** | An optional spotlight video and its link label |
| **Logo** | The organization logo |
| **Hero Image** and **Hero Background Color** | The large image and background color used by the member presentation |

Only include information that is approved for public display. Use full URLs beginning with `https://` for websites, social links, videos, and attachments.

### Edit or remove a member

1. Go to **Community Resources Hub > BCI Members**.
2. Select the member's title.
3. Make the change and select **Update**.

Move a member to Draft or Trash when it should no longer appear publicly. After any change, check the public Member Directory.

## Managing BCI Opportunities

Opportunities may arrive through the public submission form or be added manually in WordPress.

### Review submitted opportunities

1. Go to **Community Resources Hub > BCI Opportunities**.
2. Use the date, member, and opportunity-type filters to narrow the list.
3. Open the opportunity and review its title, organization, dates, type, links, and attachments.
4. Approve or reject it through the configured review workflow. The secure review links in the notification email are the preferred method because they keep the opportunity and its source submission aligned.
5. Confirm approved content on the public Opportunity Hub and calendar.

Review emails may include secure **Approve** and **Reject** links. If a review link has expired, open the opportunity in WordPress and review it there. When the opportunity came from Gravity Forms, also confirm that its source entry shows the same approval status.

### Add an opportunity manually

Use this screen for an opportunity that did not arrive through the public submission form. Editing the corresponding Gravity Forms entry is the safer choice for a submitted opportunity because the Hub mirrors that source entry.

1. Go to **Community Resources Hub > Add New BCI Opportunity**.
2. Enter the opportunity title.
3. Complete the applicable fields, including organization, dates, time, location, cost, information URL, and attachments.
4. Choose an **Opportunity Type** in the **Opportunity Details** panel.
5. Set the **Approval Status**.
6. Match the WordPress publication state to the approval: publish Approved items, leave Pending items pending, and keep Rejected items as drafts.
7. Confirm that an approved opportunity appears on the public page.

For public display, an opportunity must be both **Approved** and published. Pending or rejected opportunities are not shown publicly.

### Approval statuses

| Status | Meaning |
| --- | --- |
| **Pending** | Waiting for review; not public |
| **Approved** | Eligible for the public Opportunity Hub and calendar |
| **Rejected** | Not approved for public display |

Some approved WordPress users can be configured as **Auto-Approved Submitters**. Their future submissions are approved automatically.

### Dates and grants

- Use **Start Date** for events and most opportunities.
- Use **Grant Deadline** for grants and RFPs.
- Add an **End Date** only when the opportunity spans a range.
- Add start and end times when timing is important to attendees.

## Managing Opportunity Types

Opportunity Types control how categories appear in cards, filters, and the calendar.

1. Go to **Community Resources Hub > Opportunity Types**.
2. Select an existing type or add a new one.
3. Set the name and, when needed, an **Alias**, **Color**, and **Thumbnail**.
4. Save the type.
5. Check the Opportunity Hub and calendar.

The **Alias** is the shorter public label used in badges and filters. Changing a type can affect every opportunity assigned to it, so edit existing types carefully.

## Settings

Administrators can open **Community Resources Hub > Settings**. Resolve any notice shown at the top of this screen before troubleshooting individual content items.

### Workflow Setup

This tab connects the Hub to the opportunity submission form and review notification. These values normally remain unchanged after setup.

- **Form ID** identifies the Gravity Form used for submissions.
- **Approval Field ID** identifies the form field that stores Pending, Approved, or Rejected.
- **Notification Name** identifies the Gravity Forms notification used for review emails.

### Approvals

- **Approval Notification Recipients** accepts email addresses separated by commas or separate lines. Leave it blank to use the recipients already configured in Gravity Forms.
- **Auto-Approved Submitters** selects logged-in WordPress users whose future submissions should be approved automatically.

### Publishing

This tab connects the public resources page and GravityCalendar feed.

- **Calendar Page Slug** is the final part of the public resources page URL.
- **Calendar Feed Name**, **Calendar Feed ID**, and **Calendar Shortcode** identify the GravityCalendar feed used by the Hub.

Use **Create or adopt BCI Hub resources** when an administrator is setting up the canonical submission form and calendar feed. Do not manually replace working IDs unless the form or feed has intentionally changed.

### Video Slider

Use this tab to manage the Spotlight Video section.

1. Set the section **Eyebrow**, **Title**, and **Intro**.
2. Select **Add Spotlight Video**.
3. Add the YouTube **Video ID** or **Video URL**.
4. Add a **Thumbnail Image**. The logo, label, slide eyebrow, title, and description are optional.
5. Save the settings and check the public slider.

At least one row with a valid YouTube video and thumbnail image is required for the slider to appear.

### Newsletter Archives

Use this tab to add cards linking to past newsletters.

1. Set the section **Eyebrow** and **Title**.
2. Select **Add Newsletter**.
3. Enter the issue label, title, and full newsletter URL.
4. Optionally choose a card image.
5. Save the settings and test the public link.

A newsletter card needs both a title and a valid URL before it can appear.

### Google Sheets Sync

This optional integration sends approved opportunities to a configured Google Apps Script endpoint. Change the endpoint or shared secret only when instructed by the person who manages the integration.

Leaving the shared-secret field blank keeps the existing saved secret.

The sidebar panel on this settings screen shows whether approved opportunities have synced and displays the latest saved error. Use **Sync One Entry** after setup or troubleshooting. Use **Start Backfill** only after that one-entry check succeeds. A paused job can be continued with **Resume**, while **Retry Remaining** starts a fresh pass over items that are still unsynced.

Saving the settings does not start a backfill. Personal test endpoints and secrets must remain on the local site and must not be copied to staging.

### Field Mapping

Field Mapping connects Gravity Forms fields to opportunity information such as title, organization, dates, location, description, and approval status.

Do not change these values during normal content editing. Update them only when the submission form's field IDs have changed, then test a complete submission and approval.

## Adding Hub Sections to a Page

Editors can place Hub sections in a WordPress page with these shortcodes:

| Shortcode | Displays |
| --- | --- |
| `[community_opportunity_hub]` | Opportunity Hub |
| `[community_member_directory]` | Member Directory |
| `[community_video_slider]` | Spotlight Video Slider |
| `[community_newsletter_archives]` | Newsletter Archives |

Place one shortcode in the page content where the section should appear. The Video Slider and Newsletter Archives use the content saved in Hub Settings.

## What Visitors Can Do

On the public Hub, visitors can:

- filter opportunities by member, type, and date;
- open opportunity and member details;
- copy or share a direct link to an open detail window;
- add an approved opportunity to their calendar;
- filter calendar events by opportunity type;
- open spotlight videos; and
- follow links to newsletter issues.

The opportunity list starts with the **Upcoming** date filter. If no results appear, visitors can change or clear the filters to see more opportunities.

## Troubleshooting

| Problem | Check first |
| --- | --- |
| The Hub menu is missing | Confirm the plugin is active and your account has the required permissions |
| The settings screen is missing fields | Confirm Advanced Custom Fields Pro is active |
| A member is missing from the directory | Confirm the member is published and has a title |
| An opportunity is missing publicly | Confirm it is Approved, published, and has the expected date and opportunity type |
| The calendar is empty | Check the Publishing tab and confirm GravityCalendar is active |
| Submission or approval is not working | Check Workflow Setup, Approvals, and Field Mapping |
| The Video Slider is missing | Confirm at least one saved slide has a valid YouTube video and thumbnail image |
| A newsletter card is missing | Confirm the card has a title and a valid `http://` or `https://` URL |
| Filters return no results | Clear the selected member, type, or date filters |

After saving content, refresh the public page before testing again.

## Administrator Recovery Tools

Additional maintenance actions may appear in list screens or Hub Settings:

- **Reconcile Legacy Opportunities** repairs older or duplicated opportunity records and imports approved submissions that are missing from the Hub.
- **Restore BCI Member Data** restores a known catalog of member overview, social, logo, and hero-image data.
- **Google Sheets Sync recovery** tests one approved entry or runs an explicit resumable backfill.

These are recovery tools, not routine editing actions. Use them only when the related data problem has been confirmed, then review the result notice and verify the affected content in WordPress and on the public site.

## When Requesting Help

Include the following details:

- the page or dashboard screen where the problem appears;
- the member or opportunity title;
- what you expected to happen;
- what happened instead;
- a screenshot of the visible message; and
- the approximate time the issue occurred.

Do not include passwords, shared secrets, or private submission data in a support message.
