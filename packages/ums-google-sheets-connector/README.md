# UMS Google Sheets Connector

Plugin WordPress tách riêng kết nối Google Sheet khỏi nghiệp vụ UMS. Cần bản
UMS 1.1.0 đi kèm, cung cấp `UMS_SHEETS_API_VERSION` 1.x. Không mã hóa code.

## Cài Đặt / Nâng Cấp

1. Sao lưu database và thư mục plugin UMS. Chờ các phiên đồng bộ đang chạy kết thúc.
2. Cài bản UMS đã tách và plugin Connector vào hai thư mục ngang hàng:
   `wp-content/plugins/UMS/` và `wp-content/plugins/ums-google-sheets-connector/`.
   Không cài Connector vào thư mục con bên trong plugin UMS.
3. Trong WordPress > Plugins, kích hoạt UMS trước, sau đó kích hoạt
   **UMS Google Sheets Connector**. Connector sẽ tạm ngừng kèm thông báo nếu UMS
   chưa bật, API không tương thích, hoặc bộ đồng bộ cũ vẫn đang được nạp.
4. Kiểm tra menu **Đồng bộ Sheet**, nút đồng bộ trong **Sơ đồ tổ chức TVN** và
   phần đọc Sheet ở **Tính số lượng cấp phát**.
5. Chạy thử một nguồn tổ chức và các nguồn nhà máy/kỳ đang sử dụng. Kiểm tra
   tổng số dòng, kết quả phân trang, cảnh báo và bảng xem trước trước khi chốt.

Không cần nhập lại cấu hình hoặc triển khai lại Apps Script chỉ vì tách plugin.
Các file `.gs` và `.html` mẫu ở `integrations/google-apps-script/` được giữ nguyên.

## Tương Thích

- Giữ `POST /wp-json/ums/v1/sync-users`, `/sync-organization` và
  `/sync-allocation-registration`, payload, batch và `X-Sync-Token` hiện có.
- Giữ các option `ums_sheet_sync_token`, `ums_sheet_sync_apps_script_url`,
  `ums_auto_sync_bridge_token`, `ums_allocation_sheet_sources`,
  `ums_allocation_sheet_apps_script_url` và các option nhật ký cũ.
- Giữ bridge `?ums_auto_sync_bridge=1&token=...` và các tiền tố transient trạng thái
  đồng bộ/xem trước. Không đổi hoặc tạo lại token hợp lệ khi kích hoạt.
- Tổ chức và cấp phát vẫn dùng hai cấu hình Apps Script URL riêng. Cấp phát giữ
  sáu nguồn HY/DA/VP x T4/T9, tên tab mặc định `Câu trả lời biểu mẫu 1`.
- Đọc dữ liệu cấp phát chỉ chuẩn bị bản xem trước jqx trong UMS. Việc chốt kết quả,
  định mức, ứng trước, thai sản, PR và tồn kho vẫn do UMS xử lý.
- Đồng bộ nhân sự, tài khoản và xử lý nhân sự nghỉ việc giữ nguyên đường xử lý;
  Connector gọi các lớp dữ liệu/nghiệp vụ đã nạp của UMS.
- Đồng bộ mật khẩu từ database ngoài không phải kết nối Google Sheet và vẫn
  thuộc UMS. Connector gọi chức năng đó theo luồng đồng bộ nhân sự cũ.

## Bật / Tắt

Tắt Connector dừng API nhận đồng bộ, popup, polling và bridge. Dữ liệu tổ chức,
tài khoản, kho, định mức, cấu hình, token và kết quả cấp phát không bị xóa.
UMS vẫn đọc dữ liệu đã đồng bộ; import Excel có bộ chọn nhà máy độc lập.
Bật lại Connector dùng ngay cấu hình cũ. Không có uninstall hook xóa dữ liệu.
Không tắt hoặc cập nhật plugin giữa một phiên đồng bộ đang chạy.

Lịch cron kết nối database tổ chức cũ vẫn bị vô hiệu hóa như trước; plugin không
tự thêm lịch mới. Với Windows Task Scheduler, URL bridge cũ vẫn hợp lệ. File
PowerShell đã cấu hình tại máy có thể giữ nguyên; bản trong `tools/` của Connector
chỉ là mẫu chưa điền URL/token. Khi chuyển vị trí file, cập nhật Task Scheduler.

## Quay Lại

Chờ phiên đồng bộ kết thúc, tắt Connector rồi khôi phục bản UMS cũ từ bản sao lưu.
Không nạp đồng thời bộ đồng bộ tích hợp cũ và Connector. Tách plugin không đổi
schema nghiệp vụ hoặc chuyển bảng dữ liệu nên không cần nhập lại nhân sự.

## Giao Diện Kết Nối

UMS công bố API 1.x sau khi nạp xong các lớp dữ liệu và nghiệp vụ; Connector
khởi tạo ở `plugins_loaded` priority 20. Các hook giao diện thuộc UMS:

- `ums_render_allocation_sheet_controls($factory_code, $schema_ready)`
- `ums_organization_sync_summary`
- `ums_organization_sync_button($table_ready)`
- `ums_organization_sync_status`
- Filter `ums_admin_notice_messages`

Connector quản lý mã PHP đồng bộ, trang cấu hình, JavaScript popup/polling và CSS
nhật ký. Tài nguyên chung, jqx và màn hình nghiệp vụ vẫn do UMS nạp.

## Kiểm Thử

Trong repository UMS, chạy `php tools/test-sheets-connector.php compatible --core`.
Các chế độ bổ sung: `off`, `missing`, `incompatible`, `legacy`, đều hỗ trợ `--core`.
Bộ kiểm thử mô phỏng hooks/REST/options và nghiệp vụ xem trước, không kết nối Google
hoặc database thật. Cần kiểm tra thực tế WordPress/MySQL và Google SSO trước triển khai.
