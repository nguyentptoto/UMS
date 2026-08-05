# Đồng Bộ Sơ Đồ Tổ Chức TVN Từ Sheet Danh sách CNV

Nguồn dữ liệu thật là một Google Sheet duy nhất: `Danh sách CNV`.

Các cột cần đọc:

```text
STT
Mã nhân viên
Họ và tên
Phòng
Nhóm
Mã cost center
Ngày vào
Vị trí
Vị trí trước TT
```

## Cấu hình WordPress

Vào `Quản lý Đồng phục > Đồng bộ Sheet`.

Trang này hiển thị:

- `REST Endpoint`: `http://localhost/UMS/wp-json/ums/v1/sync-organization`
- `X-Sync-Token`
- ô nhập `Google Apps Script Web App URL`

Copy endpoint và token sang Script Properties của Apps Script.

## Thêm file Apps Script riêng

Sheet của bạn đã có Apps Script cho chức năng khác, vì vậy file UMS dùng prefix riêng `umsTvnOrg...` để tránh trùng tên hàm.

Tạo thêm file `.gs` mới, ví dụ `ums-organization-sync.gs`, rồi dán nội dung từ:

```text
integrations/google-apps-script/ums-organization-sync.gs
```

Tạo thêm file HTML `Index` nếu project chưa có file này, rồi dán nội dung từ:

```text
integrations/google-apps-script/Index.html
```

Nếu project đã có `Index.html` cho chức năng khác, hãy đổi tên file HTML UMS thành `UmsTvnOrgIndex.html`, rồi sửa trong `ums-organization-sync.gs` dòng:

```javascript
HtmlService.createTemplateFromFile('Index')
```

thành:

```javascript
HtmlService.createTemplateFromFile('UmsTvnOrgIndex')
```

## Script Properties

Chạy hàm `umsTvnOrgConfigure()` một lần để tạo properties mẫu, sau đó sửa lại:

```text
UMS_TVN_ORG_ENDPOINT=http://localhost/UMS/wp-json/ums/v1/sync-organization
UMS_TVN_ORG_SYNC_TOKEN=token-tu-admin-ums
UMS_TVN_ORG_SPREADSHEET_ID=id-google-sheet
UMS_TVN_ORG_SHEET_NAME=Danh sách CNV
```

## Tránh trùng doGet

Apps Script Web App chỉ có một entry `doGet(e)`. File UMS không tự khai báo `doGet` để tránh đụng chức năng sẵn có.

Nếu project chưa có `doGet`, thêm:

```javascript
function doGet(e) {
  return umsTvnOrgDoGet(e);
}
```

Nếu project đã có `doGet`, thêm nhánh router vào `doGet` hiện tại:

```javascript
function doGet(e) {
  if (e && e.parameter && e.parameter.ums_module === 'tvn_org') {
    return umsTvnOrgDoGet(e);
  }

  // Logic doGet cũ của bạn giữ nguyên bên dưới.
}
```

UMS sẽ mở Web App với query `ums_module=tvn_org`, nên nhánh router này không ảnh hưởng chức năng cũ.

## Chạy đồng bộ

1. Deploy Apps Script dạng Web App.
2. Copy Web App URL vào UMS tại `Đồng bộ Sheet`.
3. Vào `Sơ đồ tổ chức TVN`.
4. Bấm `Đồng bộ từ Google Sheet`.

Popup sẽ đọc Sheet `Danh sách CNV`, đóng gói JSON và gửi về `/wp-json/ums/v1/sync-organization`. Nếu trình duyệt chặn POST trực tiếp từ popup, payload sẽ được chuyển về trang Admin bằng `postMessage` để Admin POST cùng-origin vào UMS.
