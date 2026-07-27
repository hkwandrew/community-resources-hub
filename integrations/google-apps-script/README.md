# Google Apps Script Receiver

This directory contains the credential-free receiver used by the optional Google Sheets integration. The deployed project owns request authentication, duplicate prevention, and appending approved opportunity rows.

## Local Personal Test Boundary

The personal deployment owned by `andrew@ajhughes.dev` is for the local WordPress site only.

- Store its `/exec` URL and shared secret only in the local WordPress database.
- Do not copy its URL, Sheet ID, or secret to staging or production.
- Do not use the work-authenticated Chrome session to authorize this project.
- Starting a backfill is always a separate, explicit WordPress admin action.

## Required Workbook Tabs

The destination spreadsheet must contain:

- `Approved Opportunities` with the 15 headers defined in [`Code.gs`](Code.gs)
- `_BCI Sync Log` with `Entry ID`, `Approved At`, `Sheet Row`, and `Synced At`

The sync log is the idempotency record. Retrying an entry ID already present there returns `duplicate` without adding another opportunity row.

## Create and Deploy the Receiver

1. Open the personal local-test spreadsheet while signed in as `andrew@ajhughes.dev`.
2. Choose **Extensions > Apps Script**.
3. Replace the default editor contents with [`Code.gs`](Code.gs).
4. Open **Project Settings > Script Properties** and add:
   - `BCI_SPREADSHEET_ID`: the ID between `/d/` and `/edit` in the spreadsheet URL
   - `BCI_SHEET_NAME`: `Approved Opportunities`
   - `BCI_SHARED_SECRET`: a newly generated high-entropy secret
5. Choose **Deploy > New deployment > Web app**.
6. Set **Execute as** to the project owner and grant access to **Anyone** so WordPress can POST without a Google session. The HMAC signature remains the authorization boundary.
7. Authorize the deployment in the personal account and copy the production `/exec` URL. Do not use the editor-only `/dev` URL.
8. In the local WordPress site, open **Community Resources Hub > Settings > Google Sheets Sync** and save the `/exec` URL plus the same shared secret.
9. Use **Sync One Entry** in the Google Sheets Sync panel before starting a backfill.

Generate the shared secret outside source control, for example:

```bash
openssl rand -hex 32
```

Never put the generated value in this directory, a support message, logs, or a commit.

## Request Contract

WordPress sends the exact JSON body used to calculate an HMAC-SHA256 signature. Apps Script receives that signature in the `signature` query parameter, validates the event and 15-column row contract, and returns JSON:

```json
{"ok":true,"disposition":"appended","entryId":302}
```

An existing entry ID returns `duplicate`. Validation or configuration failures return `ok: false` with a safe error message. Form-originated strings beginning with spreadsheet formula characters are stored as text.

## Updating a Deployment

After changing `Code.gs`:

1. copy the reviewed source into the Apps Script project;
2. choose **Deploy > Manage deployments**;
3. edit the existing web-app deployment and select a new version;
4. keep the same `/exec` URL and Script Properties; and
5. run **Sync One Entry** again before any bulk retry.
