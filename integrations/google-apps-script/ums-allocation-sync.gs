/**
 * UMS periodic allocation registration bridge for HY/DA/VP and T4/T9.
 * The Spreadsheet ID and tab are supplied by the UMS configuration screen.
 */
const UMS_TVN_ALLOCATION_BATCH_SIZE = 200;

function doGet(e) {
  if (!e || !e.parameter || !e.parameter.spreadsheet_id) {
    return HtmlService.createHtmlOutput(
      '<meta charset="utf-8"><p>Web App cấp phát đã sẵn sàng. Hãy mở từ nút "Đọc dữ liệu từ Google Sheet" trong UMS.</p>'
    ).setTitle('UMS Allocation Sheet Sync');
  }
  return umsTvnAllocationDoGet(e);
}

function umsTvnAllocationDoGet(e) {
  const params = e && e.parameter ? e.parameter : {};
  const config = umsTvnAllocationGetConfig_(params);
  const data = umsTvnAllocationReadSheet_(config.spreadsheetId, config.sheetName);
  const payload = {
    source: 'google-sheet-popup-bridge',
    sync_mode: 'allocation',
    sync_token: Utilities.getUuid().replace(/-/g, ''),
    spreadsheet_id: config.spreadsheetId,
    sheet_name: config.sheetName,
    factory_code: config.factoryCode,
    period_month: config.periodMonth,
    calculation_year: config.calculationYear,
    sent_at: new Date().toISOString(),
    headers: data.headers,
    rows: data.rows
  };

  const template = HtmlService.createTemplateFromFile('UmsTvnAllocationIndex');
  template.endpoint = config.endpoint;
  template.token = config.token;
  template.payload = JSON.stringify(payload);
  template.totalRows = data.rows.length;
  template.batchSize = UMS_TVN_ALLOCATION_BATCH_SIZE;
  template.mode = 'allocation';
  template.itemKey = 'rows';

  return template.evaluate()
    .setTitle('UMS Allocation Sheet Sync')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.DEFAULT);
}

function umsTvnAllocationGetConfig_(params) {
  const properties = PropertiesService.getScriptProperties();
  const config = {
	endpoint: String(properties.getProperty('UMS_TVN_ALLOCATION_ENDPOINT') || '').trim(),
	token: String(properties.getProperty('UMS_TVN_ALLOCATION_SYNC_TOKEN') || '').trim(),
    spreadsheetId: String(params.spreadsheet_id || '').trim(),
    sheetName: String(params.sheet_name || 'Câu trả lời biểu mẫu 1').trim(),
    factoryCode: String(params.factory_code || '').trim().toUpperCase(),
    periodMonth: Number(params.period_month || 0),
    calculationYear: Number(params.calculation_year || 0)
  };

  if (!config.endpoint || !config.token || !config.spreadsheetId || !config.sheetName) {
    throw new Error('Thieu cau hinh endpoint, token, Spreadsheet ID hoac ten tab.');
  }
  if (['HY', 'DA', 'VP'].indexOf(config.factoryCode) < 0) {
    throw new Error('Ma nha may khong hop le.');
  }
  if ([4, 9].indexOf(config.periodMonth) < 0 || config.calculationYear < 2000 || config.calculationYear > 2100) {
    throw new Error('Nam hoac ky cap phat khong hop le.');
  }
  return config;
}

function umsTvnAllocationReadSheet_(spreadsheetId, sheetName) {
  const spreadsheet = SpreadsheetApp.openById(spreadsheetId);
  const sheet = spreadsheet.getSheetByName(sheetName);
  if (!sheet) {
    throw new Error('Khong tim thay tab: ' + sheetName);
  }

  const values = sheet.getDataRange().getDisplayValues();
  if (values.length < 2) {
    throw new Error('Tab ' + sheetName + ' chua co du lieu dang ky.');
  }

  const headers = umsTvnAllocationBuildLetterRow_(values[0]);
  const rows = values.slice(1)
    .filter(function(row) {
      return row.some(function(value) { return String(value || '').trim() !== ''; });
    })
    .map(umsTvnAllocationBuildLetterRow_);

  if (!rows.length) {
    throw new Error('Khong tim thay dong dang ky nao trong tab ' + sheetName + '.');
  }
  return { headers: headers, rows: rows };
}

function umsTvnAllocationBuildLetterRow_(row) {
  const item = {};
  row.forEach(function(value, index) {
    item[umsTvnAllocationColumnLetter_(index + 1)] = String(value == null ? '' : value).trim();
  });
  return item;
}

function umsTvnAllocationColumnLetter_(columnNumber) {
  let result = '';
  let number = columnNumber;
  while (number > 0) {
    const remainder = (number - 1) % 26;
    result = String.fromCharCode(65 + remainder) + result;
    number = Math.floor((number - 1) / 26);
  }
  return result;
}
