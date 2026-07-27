import assert from 'node:assert/strict';
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import vm from 'node:vm';

const sourceUrl = new URL(
	'../integrations/google-apps-script/Code.gs',
	import.meta.url
);
const source = await fs.readFile(sourceUrl, 'utf8');

const expectedHeaders = [
	'Timestamp',
	'What kind of opportunity is this?',
	'Your name:',
	'What is the title of your community opportunity?',
	'What is the name of your organization?',
	"When is your opportunity happening? We need this to have this with at least a week's notice. The newsletter goes out on Thursdays, so anything happening before Thursday of the current week should not be included.",
	'If your opportunity has a date range, what is the end date?',
	'For events and learning opportunities, what time of day is it happening?',
	'For opportunities with a physical location, what is the address?',
	'Is there any cost?',
	'Provide a short description of this opportunity',
	'Provide a link for additional information:',
	'Please upload any relevant files here:',
	'Has this been in a newsletter?',
	'Additional Info , Instructions, and Commentary',
];

function createSheet(rows) {
	return {
		rows,
		appendRow(row) {
			this.rows.push([...row]);
		},
		getLastRow() {
			return this.rows.length;
		},
		getRange(startRow, startColumn, rowCount, columnCount) {
			const rangeRows = this.rows
				.slice(startRow - 1, startRow - 1 + rowCount)
				.map((row) => row.slice(startColumn - 1, startColumn - 1 + columnCount));
			const textValues = rangeRows.map((row) => String(row[0] ?? ''));

			return {
				getValues() {
					return rangeRows;
				},
				createTextFinder(query) {
					return {
						matchEntireCell() {
							return this;
						},
						findNext() {
							return textValues.includes(String(query)) ? {} : null;
						},
					};
				},
			};
		},
	};
}

function createRuntime(properties) {
	const approvedSheet = createSheet([[...expectedHeaders]]);
	const logSheet = createSheet([
		['Entry ID', 'Approved At', 'Sheet Row', 'Synced At'],
	]);
	const sheets = {
		'Approved Opportunities': approvedSheet,
		'_BCI Sync Log': logSheet,
	};
	let lockReleased = false;

	const context = {
		Array,
		Date,
		Error,
		JSON,
		Math,
		Object,
		RegExp,
		String,
		PropertiesService: {
			getScriptProperties() {
				return {
					getProperty(name) {
						return properties[name] ?? '';
					},
				};
			},
		},
		LockService: {
			getScriptLock() {
				return {
					tryLock() {
						return true;
					},
					releaseLock() {
						lockReleased = true;
					},
				};
			},
		},
		SpreadsheetApp: {
			openById(id) {
				assert.equal(id, 'personal-local-test-sheet');
				return {
					getSheetByName(name) {
						return sheets[name] ?? null;
					},
				};
			},
		},
		Utilities: {
			Charset: { UTF_8: 'UTF_8' },
			computeHmacSha256Signature(body, secret) {
				return [...crypto.createHmac('sha256', secret).update(body).digest()].map(
					(byte) => (byte > 127 ? byte - 256 : byte)
				);
			},
		},
		ContentService: {
			MimeType: { JSON: 'application/json' },
			createTextOutput(text) {
				return {
					text,
					setMimeType(mimeType) {
						this.mimeType = mimeType;
						return this;
					},
				};
			},
		},
	};

	vm.runInNewContext(source, context, { filename: 'Code.gs' });

	return {
		context,
		approvedSheet,
		logSheet,
		wasLockReleased: () => lockReleased,
	};
}

function signedRequest(payload, secret = 'personal-local-test-secret') {
	const body = JSON.stringify(payload);
	const signature = crypto.createHmac('sha256', secret).update(body).digest('hex');

	return {
		body,
		event: {
			postData: { contents: body },
			parameter: { signature },
		},
	};
}

function responseJson(response) {
	assert.equal(response.mimeType, 'application/json');
	return JSON.parse(response.text);
}

const properties = {
	BCI_SPREADSHEET_ID: 'personal-local-test-sheet',
	BCI_SHEET_NAME: 'Approved Opportunities',
	BCI_SHARED_SECRET: 'personal-local-test-secret',
};

const payload = {
	event: 'bci_entry_approved',
	entryId: 302,
	approvedAt: '2026-07-09T22:48:37+00:00',
	headers: expectedHeaders,
	row: [
		'2026-07-09T22:48:00+00:00',
		'Events',
		'Andrew Hughes',
		'Local sync test',
		'Waters Meet',
		'2026-08-15',
		'',
		'1:00 PM - 2:00 PM',
		'123 Main St',
		'Free',
		'\t=IMPORTXML("https://example.com", "//title")',
		'https://example.com/test',
		'',
		'',
		'',
	],
};

const runtime = createRuntime(properties);
const firstRequest = signedRequest(payload);
const firstResponse = responseJson(runtime.context.doPost(firstRequest.event));

assert.deepEqual(
	JSON.parse(JSON.stringify(firstResponse)),
	{ ok: true, disposition: 'appended', entryId: 302 }
);
assert.equal(runtime.approvedSheet.rows.length, 2);
assert.equal(
	runtime.approvedSheet.rows[1][10],
	'\'\t=IMPORTXML("https://example.com", "//title")',
	'form-originated values must not execute as Sheet formulas'
);
assert.equal(runtime.logSheet.rows.length, 2);
assert.equal(runtime.logSheet.rows[1][0], '302');
assert.equal(runtime.logSheet.rows[1][2], 2);
assert.equal(runtime.wasLockReleased(), true);

const duplicateResponse = responseJson(runtime.context.doPost(firstRequest.event));
assert.deepEqual(
	JSON.parse(JSON.stringify(duplicateResponse)),
	{ ok: true, disposition: 'duplicate', entryId: 302 }
);
assert.equal(runtime.approvedSheet.rows.length, 2);
assert.equal(runtime.logSheet.rows.length, 2);

const invalidSignature = {
	postData: { contents: firstRequest.body },
	parameter: { signature: '0'.repeat(64) },
};
const signatureResponse = responseJson(runtime.context.doPost(invalidSignature));
assert.equal(signatureResponse.ok, false);
assert.equal(signatureResponse.error, 'Request signature is missing or invalid.');
assert.equal(runtime.approvedSheet.rows.length, 2);

const wrongHeadersRequest = signedRequest({
	...payload,
	entryId: 303,
	headers: [...expectedHeaders.slice(0, 14), 'Wrong header'],
});
const headerResponse = responseJson(runtime.context.doPost(wrongHeadersRequest.event));
assert.equal(headerResponse.ok, false);
assert.equal(headerResponse.error, 'Spreadsheet headers do not match the BCI export contract.');
assert.equal(runtime.approvedSheet.rows.length, 2);

const wrongDestinationRuntime = createRuntime(properties);
wrongDestinationRuntime.approvedSheet.rows[0][0] = 'Wrong destination header';
const wrongDestinationRequest = signedRequest({ ...payload, entryId: 304 });
const wrongDestinationResponse = responseJson(
	wrongDestinationRuntime.context.doPost(wrongDestinationRequest.event)
);
assert.equal(wrongDestinationResponse.ok, false);
assert.equal(
	wrongDestinationResponse.error,
	'Destination sheet headers do not match the BCI export contract.'
);
assert.equal(wrongDestinationRuntime.approvedSheet.rows.length, 1);

const missingPropertyRuntime = createRuntime({
	BCI_SPREADSHEET_ID: '',
	BCI_SHEET_NAME: '',
	BCI_SHARED_SECRET: '',
});
const propertyResponse = responseJson(
	missingPropertyRuntime.context.doPost(firstRequest.event)
);
assert.equal(propertyResponse.ok, false);
assert.equal(
	propertyResponse.error,
	'Missing BCI_SPREADSHEET_ID, BCI_SHEET_NAME, or BCI_SHARED_SECRET script property.'
);
assert.equal(
	JSON.stringify(propertyResponse).includes('personal-local-test-secret'),
	false
);

assert.equal(source.includes('PropertiesService.getScriptProperties()'), true);
assert.equal(source.includes('LockService.getScriptLock()'), true);
assert.equal(source.includes('Logger.log'), false);

console.log('Google Apps Script receiver contract test passed.');
