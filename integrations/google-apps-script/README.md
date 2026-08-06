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

## Vì sao popup đang mở giao diện app cũ?

Google Apps Script Web App chỉ có một entry `doGet(e)`. Sheet của bạn đã có app riêng, nên popup đang chạy `doGet(e)` cũ trong file `Mã.gs` và hiển thị giao diện quản trị đồng phục hiện có.

File UMS không tự tạo `doGet` mới để tránh đè chức năng cũ. Thay vào đó, UMS mở Web App kèm query:

```text
?ums_module=tvn_org
```

Bạn chỉ cần thêm một nhánh router vào `doGet(e)` hiện tại.

## File cần tạo thêm trong Apps Script

Tạo thêm file `.gs` mới, ví dụ:

```text
ums-organization-sync.gs
```

Dán nội dung từ:

```text
integrations/google-apps-script/ums-organization-sync.gs
```

Tạo thêm file HTML mới tên:

```text
UmsTvnOrgIndex
```

Dán nội dung từ:

```text
integrations/google-apps-script/UmsTvnOrgIndex.html
```

Các hàm UMS đều dùng prefix `umsTvnOrg...`, nên không trùng với các hàm đang có.

## Router trong doGet hiện có

Trong file `Mã.gs`, tìm hàm `doGet(e)` hiện tại và thêm nhánh này ở đầu hàm:

```javascript
function doGet(e) {
  if (e && e.parameter && e.parameter.ums_module === 'tvn_org') {
    return umsTvnOrgDoGet(e);
  }

  // Giữ nguyên logic doGet cũ của bạn bên dưới.
}
```

Nếu `doGet(e)` hiện tại đang không có tham số `e`, đổi thành:

```javascript
function doGet(e) {
  if (e && e.parameter && e.parameter.ums_module === 'tvn_org') {
    return umsTvnOrgDoGet(e);
  }

  // Logic cũ.
}
```

## Script Properties

Chạy hàm `umsTvnOrgConfigure()` một lần để tạo properties mẫu, sau đó sửa:

```text
UMS_TVN_ORG_ENDPOINT=http://localhost/UMS/wp-json/ums/v1/sync-organization
UMS_TVN_ORG_SYNC_TOKEN=token-tu-admin-ums
UMS_TVN_ORG_SPREADSHEET_ID=id-google-sheet
UMS_TVN_ORG_SHEET_NAME=Danh sách CNV
```

## Chạy đồng bộ

1. Deploy lại Apps Script Web App sau khi thêm file/router.
2. Copy Web App URL vào UMS tại `Đồng bộ Sheet`.
3. Vào `Sơ đồ tổ chức TVN`.
4. Bấm `Đồng bộ từ Google Sheet`.

Popup lúc này sẽ chạy `umsTvnOrgDoGet(e)`, không còn mở giao diện app cũ nữa.

## Chạy tự động 1 lần/ngày

Không đặt lịch GAS Time-driven Trigger để POST về UMS, vì GAS chạy trên server Google và không truy cập được IP nội bộ `172.30.134.76`. Cách tự động phù hợp là đặt lịch trên một máy nội bộ đã đăng nhập SSO Google và WordPress Admin.

URL tự chạy của UMS:

```text
http://172.30.134.76/UMS/wp-admin/admin.php?page=tvn-ums-sheet-sync&ums_auto_sync=1
```

Khi URL này mở, trang Admin tự bấm đồng bộ, mở popup Apps Script, popup đọc Sheet rồi chuyển payload về Admin bridge nếu bị chặn POST trực tiếp.

Trong repo có script mẫu:

```text
tools/ums-organization-auto-sync.ps1
```

Tạo Windows Task Scheduler:

```text
Program/script:
powershell.exe

Arguments:
-ExecutionPolicy Bypass -File "C:\xampp\htdocs\UMS\wp-content\plugins\UMS\tools\ums-organization-auto-sync.ps1"

Trigger:
Daily
```

Chrome profile dùng cho lịch phải còn phiên đăng nhập WordPress Admin và SSO Google Workspace. Nếu popup bị chặn, hãy cho phép popup cho `http://172.30.134.76`; script mẫu cũng đã chạy Chrome kèm `--disable-popup-blocking`.
