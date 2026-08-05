/**
 * Google Apps Script Popup Bridge cho UMS.
 *
 * Script Properties bắt buộc:
 * - UMS_ENDPOINT: http://localhost/UMS/wp-json/ums/v1/sync-users
 * - UMS_SYNC_TOKEN: token trong trang Admin UMS > Đồng bộ Sheet
 * - UMS_SPREADSHEET_ID: ID Google Spreadsheet nguồn
 * - UMS_SHEET_NAME: tên Sheet chứa danh sách nhân sự
 *
 * Deploy Web App:
 * - Execute as: Me
 * - Who has access: Anyone within domain công ty
 */
const UMS_SYNC_BATCH_SIZE = 200;

function doGet() {
  const config = getUmsConfig_();
  const syncData = readUmsSheet_(config);

  const template = HtmlService.createTemplateFromFile('Index');
  template.endpoint = config.endpoint;
  template.token = config.token;
  template.payload = JSON.stringify({
    users: syncData.users,
    source: 'google-sheet-popup-bridge',
    spreadsheet_id: config.spreadsheetId,
    sheet_name: config.sheetName,
    sent_at: new Date().toISOString()
  });
  template.totalRows = syncData.users.length;
  template.batchSize = UMS_SYNC_BATCH_SIZE;

  return template.evaluate()
    .setTitle('UMS Sheet Sync')
    .setXFrameOptionsMode(HtmlService.XFrameOptionsMode.DEFAULT);
}

/**
 * Chạy thủ công một lần trong Apps Script editor để lưu cấu hình mẫu.
 */
function configureUmsPopupBridge() {
  const spreadsheet = SpreadsheetApp.getActiveSpreadsheet();
  if (!spreadsheet) {
    throw new Error('Hãy mở Apps Script từ Google Sheet nguồn trước khi chạy configureUmsPopupBridge.');
  }

  PropertiesService.getScriptProperties().setProperties({
    UMS_ENDPOINT: 'http://localhost/UMS/wp-json/ums/v1/sync-users',
    UMS_SYNC_TOKEN: 'paste-token-from-ums-admin',
    UMS_SPREADSHEET_ID: spreadsheet.getId(),
    UMS_SHEET_NAME: 'NhanSu'
  });
}

function getUmsConfig_() {
  const properties = PropertiesService.getScriptProperties();
  const config = {
    endpoint: String(properties.getProperty('UMS_ENDPOINT') || '').trim(),
    token: String(properties.getProperty('UMS_SYNC_TOKEN') || '').trim(),
    spreadsheetId: String(properties.getProperty('UMS_SPREADSHEET_ID') || '').trim(),
    sheetName: String(properties.getProperty('UMS_SHEET_NAME') || '').trim()
  };

  Object.keys(config).forEach(function (key) {
    if (!config[key]) {
      throw new Error('Thiếu cấu hình Script Property: ' + key);
    }
  });

  return config;
}

function readUmsSheet_(config) {
  const spreadsheet = SpreadsheetApp.openById(config.spreadsheetId);
  const sheet = spreadsheet.getSheetByName(config.sheetName);
  if (!sheet) {
    throw new Error('Không tìm thấy Sheet: ' + config.sheetName);
  }

  const values = sheet.getDataRange().getValues();
  if (values.length < 2) {
    throw new Error('Sheet chưa có dữ liệu nhân sự.');
  }

  const timezone = spreadsheet.getSpreadsheetTimeZone();
  const headers = values[0].map(mapUmsHeader_);
  const users = values.slice(1)
    .map(function (row) {
      return buildUmsUser_(headers, row, timezone);
    })
    .filter(function (user) {
      return user.employee_code;
    });

  if (!users.length) {
    throw new Error('Không tìm thấy dòng nào có Mã nhân viên.');
  }

  return { users: users };
}

function buildUmsUser_(headers, row, timezone) {
  const user = {};

  headers.forEach(function (field, index) {
    if (!field) {
      return;
    }

    user[field] = normalizeUmsCell_(field, row[index], timezone);
  });

  return user;
}

function normalizeUmsCell_(field, value, timezone) {
  if (value instanceof Date) {
    return Utilities.formatDate(value, timezone, 'yyyy-MM-dd');
  }

  if (field === 'is_maternity' || field === 'is_outdoor_worker') {
    if (typeof value === 'boolean') {
      return value;
    }

    return ['1', 'true', 'yes', 'y', 'có', 'co', 'x'].indexOf(String(value).trim().toLowerCase()) >= 0;
  }

  return String(value == null ? '' : value).trim();
}

function mapUmsHeader_(header) {
  const normalized = String(header || '').trim().toLowerCase();
  const aliases = {
    employee_code: 'employee_code',
    'mã nhân viên': 'employee_code',
    'mã nv': 'employee_code',
    'ma nv': 'employee_code',
    full_name: 'full_name',
    'họ và tên': 'full_name',
    'tên cnv': 'full_name',
    gender: 'gender',
    'giới tính': 'gender',
    factory_location: 'factory_location',
    'nhà máy': 'factory_location',
    'địa điểm nhà máy': 'factory_location',
    department: 'department',
    'phòng ban': 'department',
    'bộ phận': 'department',
    job_position: 'job_position',
    position: 'job_position',
    'chức danh': 'job_position',
    'chức vụ': 'job_position',
    contract_type: 'contract_type',
    'loại hợp đồng': 'contract_type',
    date_joined: 'date_joined',
    'ngày vào công ty': 'date_joined',
    'ngày vào': 'date_joined',
    resignation_date: 'resignation_date',
    'ngày nghỉ việc': 'resignation_date',
    transfer_date: 'transfer_date',
    'ngày chuyển': 'transfer_date',
    is_maternity: 'is_maternity',
    'thai sản': 'is_maternity',
    is_outdoor_worker: 'is_outdoor_worker',
    'làm việc ngoài trời': 'is_outdoor_worker',
    account_status: 'account_status',
    'trạng thái tài khoản': 'account_status',
    email: 'email'
  };

  return aliases[normalized] || '';
}
