/**
 * Google Apps Script receiver for Community Resources Hub approvals.
 *
 * Required Script Properties:
 * - BCI_SPREADSHEET_ID
 * - BCI_SHEET_NAME
 * - BCI_SHARED_SECRET
 */

var BCI_SYNC_LOG_SHEET = '_BCI Sync Log';
var BCI_SYNC_EXPECTED_HEADERS = [
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

/**
 * Receive one signed approval payload.
 *
 * @param {Object} event Apps Script web-app event.
 * @return {GoogleAppsScript.Content.TextOutput}
 */
function doPost(event) {
	try {
		var config = bciSyncConfig();

		if (config.error) {
			return bciSyncError(config.error);
		}

		var body = event && event.postData ? String(event.postData.contents || '') : '';
		var signature = event && event.parameter ? String(event.parameter.signature || '') : '';

		if (!body) {
			return bciSyncError('Request body is missing.');
		}

		if (!bciSyncValidSignature(body, signature, config.secret)) {
			return bciSyncError('Request signature is missing or invalid.');
		}

		var payload;

		try {
			payload = JSON.parse(body);
		} catch (error) {
			return bciSyncError('Invalid JSON payload.');
		}

		var validationError = bciSyncValidatePayload(payload);

		if (validationError) {
			return bciSyncError(validationError);
		}

		var lock = LockService.getScriptLock();

		if (!lock.tryLock(10000)) {
			return bciSyncError('Google Sheet sync is busy. Retry shortly.');
		}

		try {
			var spreadsheet = SpreadsheetApp.openById(config.spreadsheetId);
			var destination = spreadsheet.getSheetByName(config.sheetName);
			var syncLog = spreadsheet.getSheetByName(BCI_SYNC_LOG_SHEET);

			if (!destination) {
				return bciSyncError('The configured destination sheet is missing.');
			}

			if (!syncLog) {
				return bciSyncError('The _BCI Sync Log sheet is missing.');
			}

			if (!bciSyncDestinationHeadersMatch(destination)) {
				return bciSyncError('Destination sheet headers do not match the BCI export contract.');
			}

			if (bciSyncEntryExists(syncLog, payload.entryId)) {
				return bciSyncSuccess('duplicate', payload.entryId);
			}

			var row = payload.row.map(bciSyncSafeCellValue);
			destination.appendRow(row);

			var sheetRow = destination.getLastRow();
			syncLog.appendRow([
				String(payload.entryId),
				String(payload.approvedAt || ''),
				sheetRow,
				new Date().toISOString(),
			]);

			return bciSyncSuccess('appended', payload.entryId);
		} finally {
			lock.releaseLock();
		}
	} catch (error) {
		return bciSyncError('Google Sheet sync failed.');
	}
}

/**
 * @return {Object}
 */
function bciSyncConfig() {
	var properties = PropertiesService.getScriptProperties();
	var spreadsheetId = String(properties.getProperty('BCI_SPREADSHEET_ID') || '').trim();
	var sheetName = String(properties.getProperty('BCI_SHEET_NAME') || '').trim();
	var secret = String(properties.getProperty('BCI_SHARED_SECRET') || '');

	if (!spreadsheetId || !sheetName || !secret) {
		return {
			error: 'Missing BCI_SPREADSHEET_ID, BCI_SHEET_NAME, or BCI_SHARED_SECRET script property.',
		};
	}

	return {
		spreadsheetId: spreadsheetId,
		sheetName: sheetName,
		secret: secret,
	};
}

/**
 * @param {string} body Raw request body.
 * @param {string} suppliedSignature Supplied hexadecimal signature.
 * @param {string} secret Shared secret.
 * @return {boolean}
 */
function bciSyncValidSignature(body, suppliedSignature, secret) {
	if (!/^[a-f0-9]{64}$/i.test(suppliedSignature)) {
		return false;
	}

	var bytes = Utilities.computeHmacSha256Signature(
		body,
		secret,
		Utilities.Charset.UTF_8
	);
	var expectedSignature = bciSyncBytesToHex(bytes);

	return bciSyncConstantTimeEquals(
		expectedSignature.toLowerCase(),
		suppliedSignature.toLowerCase()
	);
}

/**
 * @param {number[]} bytes Signed Apps Script bytes.
 * @return {string}
 */
function bciSyncBytesToHex(bytes) {
	return bytes
		.map(function (byte) {
			var value = byte < 0 ? byte + 256 : byte;
			return ('0' + value.toString(16)).slice(-2);
		})
		.join('');
}

/**
 * Compare equal-length strings without exiting on the first mismatch.
 *
 * @param {string} left First string.
 * @param {string} right Second string.
 * @return {boolean}
 */
function bciSyncConstantTimeEquals(left, right) {
	if (left.length !== right.length) {
		return false;
	}

	var mismatch = 0;

	for (var index = 0; index < left.length; index += 1) {
		mismatch |= left.charCodeAt(index) ^ right.charCodeAt(index);
	}

	return mismatch === 0;
}

/**
 * @param {Object} payload Decoded request payload.
 * @return {string}
 */
function bciSyncValidatePayload(payload) {
	if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
		return 'Invalid BCI approval payload.';
	}

	if (payload.event !== 'bci_entry_approved') {
		return 'Unsupported sync event.';
	}

	if (
		typeof payload.entryId !== 'number' ||
		!isFinite(payload.entryId) ||
		payload.entryId < 1 ||
		Math.floor(payload.entryId) !== payload.entryId
	) {
		return 'A positive integer entryId is required.';
	}

	if (!Array.isArray(payload.headers) || payload.headers.length !== BCI_SYNC_EXPECTED_HEADERS.length) {
		return 'Spreadsheet headers do not match the BCI export contract.';
	}

	for (var headerIndex = 0; headerIndex < BCI_SYNC_EXPECTED_HEADERS.length; headerIndex += 1) {
		if (payload.headers[headerIndex] !== BCI_SYNC_EXPECTED_HEADERS[headerIndex]) {
			return 'Spreadsheet headers do not match the BCI export contract.';
		}
	}

	if (!Array.isArray(payload.row) || payload.row.length !== BCI_SYNC_EXPECTED_HEADERS.length) {
		return 'Spreadsheet row width does not match the BCI export contract.';
	}

	for (var cellIndex = 0; cellIndex < payload.row.length; cellIndex += 1) {
		var cell = payload.row[cellIndex];
		var type = typeof cell;

		if (
			cell !== null &&
			type !== 'string' &&
			type !== 'number' &&
			type !== 'boolean'
		) {
			return 'Spreadsheet row values must be scalar.';
		}

		if (type === 'string' && cell.length > 50000) {
			return 'A spreadsheet cell exceeds the supported length.';
		}
	}

	if (payload.approvedAt !== undefined && typeof payload.approvedAt !== 'string') {
		return 'approvedAt must be a string.';
	}

	return '';
}

/**
 * Prevent form-originated strings from executing as spreadsheet formulas.
 *
 * @param {*} value Raw cell value.
 * @return {string|number|boolean}
 */
function bciSyncSafeCellValue(value) {
	if (value === null || value === undefined) {
		return '';
	}

	if (typeof value !== 'string') {
		return value;
	}

	return /^[\t\r\n ]*[=+\-@]/.test(value) ? "'" + value : value;
}

/**
 * @param {GoogleAppsScript.Spreadsheet.Sheet} syncLog Dedupe log sheet.
 * @param {number} entryId Gravity Forms entry ID.
 * @return {boolean}
 */
function bciSyncEntryExists(syncLog, entryId) {
	var lastRow = syncLog.getLastRow();

	if (lastRow < 2) {
		return false;
	}

	return Boolean(
		syncLog
			.getRange(2, 1, lastRow - 1, 1)
			.createTextFinder(String(entryId))
			.matchEntireCell(true)
			.findNext()
	);
}

/**
 * @param {GoogleAppsScript.Spreadsheet.Sheet} destination Destination sheet.
 * @return {boolean}
 */
function bciSyncDestinationHeadersMatch(destination) {
	var rows = destination
		.getRange(1, 1, 1, BCI_SYNC_EXPECTED_HEADERS.length)
		.getValues();
	var headers = rows && rows[0] ? rows[0] : [];

	if (headers.length !== BCI_SYNC_EXPECTED_HEADERS.length) {
		return false;
	}

	for (var index = 0; index < BCI_SYNC_EXPECTED_HEADERS.length; index += 1) {
		if (String(headers[index]) !== BCI_SYNC_EXPECTED_HEADERS[index]) {
			return false;
		}
	}

	return true;
}

/**
 * @param {string} disposition appended or duplicate.
 * @param {number} entryId Gravity Forms entry ID.
 * @return {GoogleAppsScript.Content.TextOutput}
 */
function bciSyncSuccess(disposition, entryId) {
	return bciSyncJson({
		ok: true,
		disposition: disposition,
		entryId: entryId,
	});
}

/**
 * @param {string} message Safe error message.
 * @return {GoogleAppsScript.Content.TextOutput}
 */
function bciSyncError(message) {
	return bciSyncJson({
		ok: false,
		error: String(message || 'Google Sheet sync failed.'),
	});
}

/**
 * @param {Object} data Response data.
 * @return {GoogleAppsScript.Content.TextOutput}
 */
function bciSyncJson(data) {
	return ContentService.createTextOutput(JSON.stringify(data)).setMimeType(
		ContentService.MimeType.JSON
	);
}
