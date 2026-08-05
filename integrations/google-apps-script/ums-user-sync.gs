/**
 * Google Apps Script Popup Bridge cho UMS.
 *
 * Script Properties bắt buộc:
 * - UMS_ENDPOINT_USERS: http://localhost/UMS/wp-json/ums/v1/sync-users
 * - UMS_ENDPOINT_ORGANIZATION: http://localhost/UMS/wp-json/ums/v1/sync-organization
 * - UMS_SYNC_TOKEN: token trong trang Admin UMS > Đồng bộ Sheet
 * - UMS_SPREADSHEET_ID: ID Google Spreadsheet nguồn
 * - UMS_SHEET_NAME_USERS: tên Sheet chứa hồ sơ nhân sự
 * - UMS_SHEET_NAME_ORGANIZATION: tên Sheet chứa sơ đồ tổ chức TVN
 *
 * Deploy Web App:
 * - Execute as: Me
 * - Who has access: Anyone within domain công ty
 */
const UMS_SYNC_BATCH_SIZE = 200;

function doGet(e) {
  const mode = String((e && e.parameter && e.parameter.mode) || 'users').trim() === 'organization'
    ? 'organization'
    : 'users';
  const config = getUmsConfig_(mode);
  const syncData = readUmsSheet_(config, mode);
  const itemKey = mode === 'organization' ? 'rows' : 'users';
  const payload = {
    source: 'google-sheet-popup-bridge',
    sync_mode: mode,
    sync_token: Utilities.getUuid().replace(/-/g, '').substring(0, 32),
    spreadsheet_id: config.spreadsheetId,
    sheet_name: config.sheetName,
    sent_at: new Date().toISOString()
  };
  payload[itemKey] = syncData.rows;

  const template = HtmlService.createTemplateFromFile('Index');
  template.endpoint = config.endpoint;
  template.token = config.token;
  template.payload = JSON.stringify(payload);
  template.totalRows = syncData.rows.length;
  template.batchSize = UMS_SYNC_BATCH_SIZE;
  template.mode = mode;
  template.itemKey = itemKey;

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
    UMS_ENDPOINT_USERS: 'http://localhost/UMS/wp-json/ums/v1/sync-users',
    UMS_ENDPOINT_ORGANIZATION: 'http://localhost/UMS/wp-json/ums/v1/sync-organization',
    UMS_SYNC_TOKEN: 'paste-token-from-ums-admin',
    UMS_SPREADSHEET_ID: spreadsheet.getId(),
    UMS_SHEET_NAME_USERS: 'NhanSu',
    UMS_SHEET_NAME_ORGANIZATION: 'ToChucTVN'
  });
}

function getUmsConfig_(mode) {
  const properties = PropertiesService.getScriptProperties();
  const suffix = mode === 'organization' ? 'ORGANIZATION' : 'USERS';
  const config = {
    endpoint: String(properties.getProperty('UMS_ENDPOINT_' + suffix) || '').trim(),
    token: String(properties.getProperty('UMS_SYNC_TOKEN') || '').trim(),
    spreadsheetId: String(properties.getProperty('UMS_SPREADSHEET_ID') || '').trim(),
    sheetName: String(properties.getProperty('UMS_SHEET_NAME_' + suffix) || '').trim()
  };

  Object.keys(config).forEach(function (key) {
    if (!config[key]) {
      throw new Error('Thiếu cấu hình Script Property: ' + key);
    }
  });

  return config;
}

function readUmsSheet_(config, mode) {
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
  const headers = values[0].map(function (header) {
    return mode === 'organization' ? mapUmsOrganizationHeader_(header) : mapUmsUserHeader_(header);
  });
  const rows = values.slice(1)
    .map(function (row) {
      return buildUmsUser_(headers, row, timezone);
    })
    .filter(function (item) {
      return mode === 'organization' ? item.id || item.source_id || item.emp_no || item.employee_no : item.employee_code;
    });

  if (!rows.length) {
    throw new Error('Không tìm thấy dòng dữ liệu hợp lệ trong Sheet.');
  }

  return { rows: rows };
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
    if (['time_create', 'time_update', 'source_created_at', 'source_updated_at'].indexOf(field) >= 0) {
      return Utilities.formatDate(value, timezone, 'yyyy-MM-dd HH:mm:ss');
    }

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

function mapUmsUserHeader_(header) {
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

function mapUmsOrganizationHeader_(header) {
  const normalized = String(header || '').trim().toLowerCase();
  const aliases = {
    id: 'id',
    source_id: 'source_id',
    version: 'version',
    source_version: 'source_version',
    emp_no: 'emp_no',
    employee_no: 'employee_no',
    'mã nv': 'emp_no',
    'mã nhân viên': 'emp_no',
    fname: 'fname',
    full_name: 'full_name',
    'họ và tên': 'fname',
    'tên cnv': 'fname',
    division: 'division',
    'khối': 'division',
    department: 'department',
    'phòng ban': 'department',
    section: 'section',
    'bộ phận': 'section',
    team: 'team',
    'nhóm': 'team',
    position: 'position',
    'chức danh': 'position',
    email: 'email',
    factory: 'factory',
    'nhà máy': 'factory',
    time_create: 'time_create',
    source_created_at: 'source_created_at',
    'ngày tạo': 'time_create',
    time_update: 'time_update',
    source_updated_at: 'source_updated_at',
    'ngày cập nhật': 'time_update'
  };

  return aliases[normalized] || '';
}
