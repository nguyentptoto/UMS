/**
 * UMS TVN Organization Popup Bridge.
 *
 * File này cố ý dùng prefix umsTvnOrg để tránh trùng hàm với các script
 * đang có sẵn trong Google Sheet.
 *
 * Script Properties:
 * - UMS_TVN_ORG_ENDPOINT: http://localhost/UMS/wp-json/ums/v1/sync-organization
 * - UMS_TVN_ORG_SYNC_TOKEN: token trong UMS Admin > Đồng bộ Sheet
 * - UMS_TVN_ORG_SPREADSHEET_ID: ID Google Spreadsheet nguồn
 * - UMS_TVN_ORG_SHEET_NAME: Danh sách CNV
 */
const UMS_TVN_ORG_BATCH_SIZE = 200;

/**
 * Nếu project Apps Script của bạn chưa có doGet, có thể tạo doGet riêng:
 *
 * function doGet(e) {
 *   return umsTvnOrgDoGet(e);
 * }
 *
 * Nếu project đã có doGet cho chức năng khác, hãy gọi umsTvnOrgDoGet(e)
 * trong router doGet hiện có khi e.parameter.ums_module === 'tvn_org'.
 */
function umsTvnOrgDoGet(e) {
  const config = umsTvnOrgGetConfig_();
  const syncData = umsTvnOrgReadSheet_(config);
  const payload = {
    source: 'google-sheet-popup-bridge',
    sync_mode: 'organization',
    sync_token: Utilities.getUuid().replace(/-/g, '').substring(0, 32),
    spreadsheet_id: config.spreadsheetId,
    sheet_name: config.sheetName,
    sent_at: new Date().toISOString(),
    rows: syncData.rows
  };

  const template = HtmlService.createTemplateFromFile('UmsTvnOrgIndex');
  template.endpoint = config.endpoint;
  template.token = config.token;
  template.payload = JSON.stringify(payload);
  template.totalRows = syncData.rows.length;
  template.batchSize = UMS_TVN_ORG_BATCH_SIZE;
  template.mode = 'organization';
  template.itemKey = 'rows';

  return template.evaluate()
    .setTitle('UMS TVN Organization Sync')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.DEFAULT);
}

function umsTvnOrgConfigure() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) {
    throw new Error('Hay mo Apps Script tu Google Sheet nguon truoc khi chay umsTvnOrgConfigure.');
  }

  PropertiesService.getScriptProperties().setProperties({
    UMS_TVN_ORG_ENDPOINT: 'http://localhost/UMS/wp-json/ums/v1/sync-organization',
    UMS_TVN_ORG_SYNC_TOKEN: 'paste-token-from-ums-admin',
    UMS_TVN_ORG_SPREADSHEET_ID: spreadsheet.getId(),
    UMS_TVN_ORG_SHEET_NAME: 'Danh sách CNV'
  });
}

function umsTvnOrgGetConfig_() {
  const properties = PropertiesService.getScriptProperties();
  const config = {
    endpoint: String(properties.getProperty('UMS_TVN_ORG_ENDPOINT') || '').trim(),
    token: String(properties.getProperty('UMS_TVN_ORG_SYNC_TOKEN') || '').trim(),
    spreadsheetId: String(properties.getProperty('UMS_TVN_ORG_SPREADSHEET_ID') || '').trim(),
    sheetName: String(properties.getProperty('UMS_TVN_ORG_SHEET_NAME') || 'Danh sách CNV').trim()
  };

  Object.keys(config).forEach(function (key) {
    if (!config[key]) {
      throw new Error('Thieu cau hinh Script Property: ' + key);
    }
  });

  return config;
}

function umsTvnOrgReadSheet_(config) {
  const spreadsheet = SpreadsheetApp.openById(config.spreadsheetId);
  const sheet = spreadsheet.getSheetByName(config.sheetName);
  if (!sheet) {
    throw new Error('Khong tim thay Sheet: ' + config.sheetName);
  }

  const values = sheet.getDataRange().getValues();
  if (values.length < 2) {
    throw new Error('Sheet chua co du lieu CNV.');
  }

  const timezone = spreadsheet.getSpreadsheetTimeZone();
  const headers = values[0].map(umsTvnOrgMapHeader_);
  const rows = values.slice(1)
    .map(function (row) {
      return umsTvnOrgBuildRow_(headers, row, timezone);
    })
    .filter(function (row) {
      return row.employee_no;
    });

  if (!rows.length) {
    throw new Error('Khong tim thay dong nao co Ma nhan vien.');
  }

  return { rows: rows };
}

function umsTvnOrgBuildRow_(headers, row, timezone) {
  const item = {};

  headers.forEach(function (field, index) {
    if (!field) {
      return;
    }

    item[field] = umsTvnOrgNormalizeCell_(field, row[index], timezone);
  });

  return item;
}

function umsTvnOrgNormalizeCell_(field, value, timezone) {
  if (value instanceof Date) {
    return Utilities.formatDate(value, timezone, 'yyyy-MM-dd');
  }

  return String(value == null ? '' : value).trim();
}

function umsTvnOrgMapHeader_(header) {
  const normalized = String(header || '').trim().toLowerCase();
  const aliases = {
    stt: 'stt',
    source_id: 'source_id',
    id: 'source_id',
    'mã nhân viên': 'employee_no',
    'ma nhan vien': 'employee_no',
    'mã nv': 'employee_no',
    'ma nv': 'employee_no',
    employee_no: 'employee_no',
    emp_no: 'employee_no',
    'họ và tên': 'full_name',
    'ho va ten': 'full_name',
    full_name: 'full_name',
    fname: 'full_name',
    phòng: 'department',
    phong: 'department',
    department: 'department',
    nhóm: 'team',
    nhom: 'team',
    team: 'team',
    'mã cost center': 'cost_center',
    'ma cost center': 'cost_center',
    cost_center: 'cost_center',
    'ngày vào': 'date_joined',
    'ngay vao': 'date_joined',
    date_joined: 'date_joined',
    'vị trí': 'position',
    'vi tri': 'position',
    position: 'position',
    email: 'email',
    mail: 'email',
    'e-mail': 'email',
    'vị trí trước tt': 'previous_position',
    'vi tri truoc tt': 'previous_position',
    previous_position: 'previous_position'
  };

  return aliases[normalized] || '';
}
