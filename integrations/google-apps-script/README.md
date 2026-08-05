# Đồng Bộ Nhân Sự Google Sheet Bằng Popup Bridge

Tổ chức đang dùng SSO và server WordPress nội bộ, vì vậy Google Apps Script không thể chạy trigger server-to-server để gọi thẳng về UMS. Luồng đúng là Admin mở popup Web App; popup dùng phiên SSO của trình duyệt để đọc Sheet rồi `fetch()` dữ liệu về REST endpoint nội bộ của WordPress.

## 1. Cấu Hình Trong WordPress

Vào `Quản lý Đồng phục > Đồng bộ Sheet`.

Trang này hiển thị:

- `REST Endpoint`, ví dụ `http://localhost/UMS/wp-json/ums/v1/sync-users`
- `X-Sync-Token`
- ô nhập `Google Apps Script Web App URL`

Token được lưu trong `wp_options` với key `ums_sheet_sync_token` và được tạo tự động khi plugin active/init. Không cần thêm hằng số trong `wp-config.php`.

## 2. Cấu Hình Apps Script

Trong Google Sheet nguồn, mở `Extensions > Apps Script`.

Tạo 2 file:

- `Code.gs`: dùng nội dung từ `ums-user-sync.gs`
- `Index.html`: dùng nội dung từ `Index.html`

Chạy hàm `configureUmsPopupBridge()` một lần rồi sửa các Script Properties:

```text
UMS_ENDPOINT=http://localhost/UMS/wp-json/ums/v1/sync-users
UMS_SYNC_TOKEN=token-tu-trang-admin-ums
UMS_SPREADSHEET_ID=id-google-sheet
UMS_SHEET_NAME=NhanSu
```

Deploy dạng Web App:

- Execute as: `Me`
- Who has access: `Anyone within domain công ty`

Sau khi deploy, copy Web App URL vào trang `Đồng bộ Sheet` của UMS.

Nếu trình duyệt chặn popup `fetch()` trực tiếp về WordPress do chính sách mạng/mixed content, file `Index.html` sẽ tự gửi payload về trang Admin bằng `postMessage`; trang Admin sau đó POST cùng-origin vào REST endpoint. Đây là fallback để chạy ổn trong môi trường WordPress nội bộ.

## 3. Cột Google Sheet

Dòng đầu tiên là tiêu đề. Script nhận tiêu đề tiếng Việt hoặc key kỹ thuật:

| Key | Tiêu đề ví dụ | Bắt buộc khi tạo mới | Ghi chú |
|---|---|---:|---|
| `employee_code` | Mã nhân viên | Có | Khóa upsert duy nhất |
| `full_name` | Họ và tên | Có | Ghi `display_name` WordPress |
| `gender` | Giới tính | Có | `Nam` hoặc `Nữ` |
| `factory_location` | Nhà máy | Có | Mã hoặc tên danh mục đang active |
| `department` | Phòng ban | Có | Mã hoặc tên danh mục đang active |
| `job_position` | Chức danh | Có | Mã hoặc tên danh mục đang active |
| `contract_type` | Loại hợp đồng | Có | Mã hoặc tên danh mục đang active |
| `date_joined` | Ngày vào Công ty | Có | Date hoặc `YYYY-MM-DD` |
| `resignation_date` | Ngày nghỉ việc | Không | Để trống để xóa ngày |
| `transfer_date` | Ngày chuyển | Không | Để trống để xóa ngày |
| `is_maternity` | Thai sản | Không | `true`, `1`, `Có`, `x` |
| `is_outdoor_worker` | Làm việc ngoài trời | Không | `true`, `1`, `Có`, `x` |
| `account_status` | Trạng thái tài khoản | Không | `active` hoặc `inactive` |
| `email` | Email | Không | Ghi vào `wp_users.user_email` |

## 4. Cách Chạy

1. Admin đăng nhập WordPress.
2. Vào `Quản lý Đồng phục > Đồng bộ Sheet`.
3. Nhấn `Bắt đầu đồng bộ`.
4. Popup Apps Script mở ra, đọc Sheet, gửi batch về UMS và báo kết quả bằng `postMessage`.
5. UMS lưu log gần nhất vào `wp_options` key `ums_sheet_sync_last_log`.

REST endpoint trả JSON dạng:

```json
{
  "status": "success",
  "success": true,
  "count": 2,
  "created": 1,
  "updated": 1,
  "failed": 0,
  "errors": [],
  "summary": {
    "received": 2,
    "created": 1,
    "updated": 1,
    "failed": 0
  },
  "results": []
}
```
