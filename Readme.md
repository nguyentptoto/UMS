# UMS - Uniform Management System

UMS là plugin WordPress quản lý hồ sơ nhân sự, phòng ban, chức danh, nhà máy, hợp đồng, danh mục sản phẩm đồng phục, tổng kho, định mức cấp phát hằng năm, phiếu yêu cầu cấp phát và luồng duyệt động theo phòng ban.

## Nguyên Tắc Database

Toàn bộ cấu trúc bảng nằm trong `ums.sql`.

Plugin không tự tạo bảng, không tự migrate bảng, không chạy `CREATE TABLE`, `ALTER TABLE` hoặc `dbDelta`. Trước khi dùng plugin, hãy import thủ công `ums.sql` vào database WordPress.

File `ums.sql` hiện dùng prefix mặc định `wp_`. Nếu site dùng prefix khác, cần đổi prefix trong file SQL trước khi import.

Các bảng nghiệp vụ chính:

- `wp_uniform_departments`
- `wp_uniform_positions`
- `wp_uniform_factory_locations`
- `wp_uniform_contract_types`
- `wp_uniform_department_approval_flows`
- `wp_uniform_user_profiles`
- `wp_uniform_product_categories`
- `wp_uniform_inventory`
- `wp_uniform_annual_allowance_rules`
- `wp_uniform_inventory_movements`
- `wp_uniform_requests`
- `wp_uniform_request_details`
- `wp_uniform_approval_logs`
- `wp_uniform_returns`
- `wp_uniform_organization_employees`

## Chức Năng Admin

- Quản lý Hồ sơ Nhân sự
- Quản lý Phòng ban
- Quản lý Chức danh
- Quản lý Nhà máy
- Quản lý Hợp đồng
- Quản lý Luồng duyệt phòng ban
- Quản lý Danh mục sản phẩm cha/con
- Quản lý Sản phẩm & Tổng kho
- Xuất kho chủ động từ Admin
- Xem lịch sử nhập/xuất/điều chỉnh kho chi tiết
- Quản lý Định mức cấp phát hàng năm
- Quản lý và đồng bộ Sơ đồ tổ chức TVN
- Đồng bộ mật khẩu từ DB ngoài vào `wp_users.user_pass`

Hồ sơ nhân sự liên kết với tài khoản WordPress qua `wp_uniform_user_profiles.user_id`. Khi tạo hồ sơ, hệ thống tạo tài khoản WordPress tương ứng, `user_login` mặc định là mã nhân viên và mật khẩu mặc định là `12345678`.

Trang `Quản lý Phòng ban` có trường `department_group` để nhiều phòng ban cùng thuộc một nhóm. Danh sách hỗ trợ lọc theo nhóm và form gợi ý các nhóm đã có.

Import CSV UTF-8 sử dụng các cột `department_code`, `department_name`, `department_group`, `is_active`. Import upsert theo mã phòng ban: mã mới được tạo, mã đã tồn tại được cập nhật. File CSV cũ không có `department_group` vẫn được chấp nhận và giữ nguyên nhóm của phòng ban hiện tại. Khi tên phòng ban thay đổi, hồ sơ nhân sự liên quan được đồng bộ sang tên mới trong cùng transaction. Giao diện có nút tải CSV mẫu.

Với database đã cài UMS trước thay đổi này, chạy thủ công:

```sql
ALTER TABLE `wp_uniform_departments`
    ADD COLUMN `department_group` VARCHAR(150) NOT NULL DEFAULT '' AFTER `department_name`,
    ADD KEY `idx_department_group` (`department_group`);
```

## Định Mức Cấp Phát Hàng Năm

Admin quản lý tại menu `Định mức năm`.

Bảng dữ liệu: `wp_uniform_annual_allowance_rules`.

Một rule định mức gồm:

- `apply_type`: kiểu áp dụng, gồm `category` hoặc `item`.
- `category_id`: danh mục áp dụng. Nếu chọn danh mục, tất cả sản phẩm thuộc danh mục đó sẽ theo rule.
- `item_id`: sản phẩm cố định áp dụng.
- `target_type`: đối tượng áp dụng, gồm `all` hoặc `position`.
- `position_id`: chức vụ áp dụng khi `target_type = position`.
- `frequency_count`: số lần được cấp.
- `frequency_years`: số năm trong chu kỳ.
- `monthly_quantities`: JSON lưu số lượng cấp phát theo từng tháng.
- `is_active`: trạng thái áp dụng.

Ví dụ:

- Áo khoác áp dụng cho toàn bộ nhân sự.
- Tần suất `1 lần / 2 năm`.
- Tháng 4: `0`, tháng 9: `1`.

Khi user tạo hoặc sửa phiếu, hệ thống kiểm tra định mức trước khi lưu:

- Sản phẩm phải có rule định mức đang áp dụng.
- Rule có thể đến từ sản phẩm cố định hoặc danh mục cha/con.
- Rule phải phù hợp với chức vụ của CNV nhận đồng phục nếu rule áp dụng theo chức vụ.
- Tháng hiện tại phải có số lượng được phép cấp lớn hơn `0`.
- Tổng số lượng đã dùng trong tháng cộng với số lượng đang yêu cầu không được vượt định mức tháng.
- Số lần đã tạo phiếu trong chu kỳ không được vượt `frequency_count / frequency_years`.
- Phiếu bị `rejected` không tính vào định mức đã dùng.
- Khi sửa phiếu, hệ thống loại trừ chính phiếu đang sửa để tránh tính trùng.

### Import định mức từ Excel

Trang `Định mức năm` hỗ trợ import trực tiếp file `.xlsx` theo template quản lý đồng phục. Khi cài mới, import toàn bộ file `ums.sql`; bảng định mức linh hoạt và bảng `wp_uniform_allowance_import_batches` đã có sẵn trong cấu trúc hoàn chỉnh.

Form thêm/sửa thủ công bám đúng ma trận của hai sheet: Bộ phận, Nhóm, Code center, Vị trí, Sản phẩm, số lượng Tháng 4, số lượng Tháng 9, Lưu ý và Trạng thái. Khóa rule được sinh chung với dữ liệu import để tầng kiểm tra định mức xử lý thống nhất.

Danh sách định mức được trình bày bằng hai jqxGrid độc lập cho Tháng 4 và Tháng 9. Hai bảng giữ cùng các cột tổ chức và 25 cột sản phẩm E:AC, nhưng mỗi ô chỉ hiển thị số lượng của tháng tương ứng.

Chức năng xuất kho chủ động lấy người nhận trực tiếp từ Sơ đồ tổ chức TVN bằng mã nhân viên. Lịch sử kho lưu thêm `target_employee_no`, do đó Admin có thể xuất cho CNV không có tài khoản WordPress mà vẫn kiểm tra định mức, ngày vào và truy vết đúng người nhận.

Importer chỉ đọc hai sheet định mức chính và giữ cố định 25 cột sản phẩm từ `E` đến `AC`:

- `Phát T4`: định mức định kỳ tháng 4.
- `Phát T9`: định mức định kỳ tháng 9.

Điều kiện áp dụng được lấy từ Sơ đồ tổ chức TVN theo `Mã nhân viên`: phòng, nhóm, cost center, vị trí và ngày vào công ty. Đối với rule `newcomer` và `newcomer_september`, hệ thống dùng cột `date_joined` của Sơ đồ tổ chức để xác định CNV có thuộc khoảng ngày nhận việc hay không; ngày trong usermeta/hồ sơ cũ chỉ được dùng dự phòng khi dữ liệu tổ chức chưa có. Tên sản phẩm trong Excel được ánh xạ với sản phẩm UMS trước khi xác nhận import; một mapping áp dụng cho toàn bộ size của sản phẩm đó.

Quy trình import gồm hai bước:

1. Chọn Excel và bấm `Đọc và xem trước`.
2. Kiểm tra thống kê, ánh xạ toàn bộ sản phẩm rồi bấm `Xác nhận import`.

Import chạy theo batch trong transaction. Lần import mới cập nhật rule trùng khóa và vô hiệu hóa các rule thuộc lần import cũ nhưng không còn trong file mới. Các rule nhập thủ công không bị xóa.

Thứ tự ưu tiên khi kiểm tra cấp phát:

1. `newcomer_september`: cấp bù tháng 9 cho CNV vào trong năm.
2. `newcomer`: cấp ban đầu cho CNV mới.
3. `annual`: định mức định kỳ tháng 4/tháng 9.
4. Rule thủ công cũ khi nhân viên không thuộc ma trận Excel.

Một ô số lượng `0` trong ma trận có nghĩa là không được cấp sản phẩm ở kỳ tương ứng. Hệ thống không tự rơi xuống rule tổng quát khi nhân viên đã thuộc một ma trận import.

## User Portal

Tạo một page WordPress và chèn shortcode:

```text
[ums_user_portal]
```

Portal dùng layout riêng của plugin, vẫn gọi các hook chuẩn `wp_head()`, `wp_body_open()` và `wp_footer()`.

Các trang portal hiện có:

- Tổng quan
- Tạo yêu cầu
- Phiếu của tôi
- Chi tiết phiếu
- Luồng duyệt
- Hồ sơ của tôi

Module `Tạo yêu cầu` chỉ hiển thị cho user có `profile_id` nằm trong `approver_profile_ids` của bước duyệt `step_order = 1` thuộc phòng ban hiện tại. User ngoài bước 1 không thấy module này.

Admin có quyền vào portal và xem toàn bộ phiếu ở góc nhìn quản trị.

## Luồng Tạo Yêu Cầu

Khi user hợp lệ bấm `Gửi duyệt`:

1. Hệ thống kiểm tra quyền tạo phiếu theo bước 1 của luồng duyệt phòng ban.
2. Hệ thống kiểm tra định mức cấp phát hàng năm theo từng dòng đồng phục.
3. Phiếu được lưu vào `wp_uniform_requests`.
4. Trạng thái ban đầu chuyển tới bước duyệt tiếp theo sau bước 1, ví dụ `pending_step_2`. Nếu luồng chỉ có bước 1, phiếu có thể hoàn thành ngay theo logic hiện hành.
5. Chi tiết đồng phục được lưu vào `wp_uniform_request_details`.
6. Giá được tính lại từ dữ liệu kho: `base_price * quantity`.
7. Hệ thống tạo log `submitted` trong `wp_uniform_approval_logs` tại `step_order = 1`.
8. Hệ thống ghi dòng `request_out` vào `wp_uniform_inventory_movements` để Admin nhìn thấy yêu cầu xuất kho theo từng vật tư.
9. Hệ thống gửi email cho người duyệt ở bước tiếp theo nếu có.

Luồng duyệt là chuỗi động theo bảng `wp_uniform_department_approval_flows`, không cố định 4 cấp.

Người tạo phiếu ở bước 1 có thể sửa hoặc xóa phiếu khi phiếu chưa được bước tiếp theo duyệt. Phiếu bị từ chối có thể sửa hoặc xóa để gửi lại.

## Duyệt Phiếu Và Xuất Kho

Khi phiếu đi qua từng bước duyệt:

- Người duyệt tại bước hiện tại có thể duyệt hoặc từ chối.
- Khi duyệt, phiếu chuyển sang bước kế tiếp trong chuỗi luồng động.
- Khi từ chối, người duyệt phải nhập lý do từ chối.
- Khi phiếu được duyệt hoàn toàn, hệ thống ghi nhận xuất kho và trừ tồn kho tương ứng.
- Nếu tồn kho không đủ tại thời điểm hoàn tất duyệt, hệ thống không ghi nhận xuất kho và báo lỗi.

## Lịch Sử Nhập Xuất Kho

Admin xem tại menu `Lịch sử kho`.

Nguồn dữ liệu nằm ở `wp_uniform_inventory_movements`:

- `in`: nhập kho hoặc tăng tồn kho từ Admin.
- `out`: xuất kho chủ động từ Admin hoặc xuất kho sau khi phiếu được duyệt hoàn toàn.
- `adjust`: cập nhật thông tin sản phẩm/tồn kho nhưng không đổi số lượng.
- `request_out`: user gửi yêu cầu xuất kho, có liên kết `request_id`, người tạo và người nhận.

Trang lịch sử hiển thị thời gian, loại phát sinh, mã phiếu, sản phẩm, size, số lượng, tồn trước/sau, đơn giá, thành tiền, người thao tác, người nhận và ghi chú.

## Đồng Bộ Mật Khẩu Ngoài

Có thể cấu hình DB nguồn bằng hằng số trong `wp-config.php`:

```php
define( 'UMS_PASSWORD_SYNC_DB_HOST', '172.30.134.15' );
define( 'UMS_PASSWORD_SYNC_DB_USER', 'Tvnsoft' );
define( 'UMS_PASSWORD_SYNC_DB_PASSWORD', 'your-password' );
define( 'UMS_PASSWORD_SYNC_DB_NAME', 'tvnias' );
```

Hash lấy từ DB nguồn được ghi trực tiếp vào `wp_users.user_pass`, không hash lại. Nếu đồng bộ thất bại, hệ thống đặt mật khẩu về mặc định `12345678` bằng hàm hash của WordPress.

## Sơ Đồ Tổ Chức TVN

Admin quản lý tại menu `Sơ đồ tổ chức TVN`. Giao diện dùng jqxGrid phân trang phía server và đọc dữ liệu từ bảng nội bộ `wp_uniform_organization_employees`.

Nút `Đồng bộ từ Google Sheet` trên trang này mở Google Apps Script Web App bằng Popup Bridge và gửi dữ liệu Sheet `Danh sách CNV` về receiver `POST /wp-json/ums/v1/sync-organization`. Dữ liệu được upsert vào bảng nội bộ theo `Mã nhân viên`; cột `STT` chỉ dùng để hiển thị. Batch cuối gửi `finalize=true` để xóa các bản ghi không còn trong Sheet.

Sau khi lưu dữ liệu sơ đồ tổ chức, hệ thống tự tạo/cập nhật tài khoản WordPress theo `employee_no` cho nhân sự có email công ty `@toto...`. `user_login` là mã nhân viên, `user_email` lấy từ cột `Email`, tên hiển thị lấy từ `Họ và tên`, mật khẩu được đồng bộ từ server password hiện tại `172.30.134.15`; nếu đồng bộ password thất bại, hệ thống dùng mật khẩu mặc định `12345678`. Các nhân sự không có email công ty vẫn được lưu trong sơ đồ tổ chức nhưng không được tạo/cập nhật tài khoản đăng nhập.

Module này không còn kết nối database ngoài. Plugin không tự tạo bảng; cần import bảng `wp_uniform_organization_employees` từ `ums.sql` trước lần đồng bộ đầu tiên.

Với database đã có bảng tổ chức từ bản cũ, chạy thủ công:

```sql
ALTER TABLE `wp_uniform_organization_employees`
    ADD COLUMN `sheet_stt` INT DEFAULT NULL AFTER `source_id`,
    ADD COLUMN `cost_center` VARCHAR(100) DEFAULT NULL AFTER `position`,
    ADD COLUMN `date_joined` DATE DEFAULT NULL AFTER `cost_center`,
    ADD COLUMN `previous_position` VARCHAR(50) DEFAULT NULL AFTER `date_joined`,
    ADD UNIQUE KEY `idx_employee_no_unique` (`employee_no`(50)),
    ADD KEY `idx_sheet_stt` (`sheet_stt`),
    ADD KEY `idx_cost_center` (`cost_center`),
    ADD KEY `idx_date_joined` (`date_joined`);
```

## Đồng Bộ Sơ Đồ Tổ Chức Từ Google Sheet

Plugin cung cấp receiver `POST /wp-json/ums/v1/sync-organization`. Endpoint xác thực bằng header `X-Sync-Token`; token được lưu trong `wp_options` và hiển thị tại Admin menu `Đồng bộ Sheet`. Sheet nguồn duy nhất là `Danh sách CNV`, gồm các cột `STT`, `Mã nhân viên`, `Họ và tên`, `Phòng`, `Nhóm`, `Mã cost center`, `Ngày vào`, `Vị trí`, `Vị trí trước TT`, `Email`.

Do Google Workspace có SSO và WordPress chạy nội bộ, hệ thống dùng mô hình Popup Bridge thay vì GAS trigger server-to-server. Admin bấm `Đồng bộ từ Google Sheet` trong trang `Sơ đồ tổ chức TVN`, plugin mở Google Apps Script Web App bằng `window.open()`, popup đọc Sheet bằng phiên SSO trình duyệt rồi `fetch()` JSON về endpoint nội bộ của WordPress. Nếu trình duyệt chặn POST trực tiếp từ popup, popup chuyển payload về trang Admin bằng `postMessage` để Admin POST cùng-origin vào UMS.

Google Apps Script mẫu gồm `ums-organization-sync.gs` và `UmsTvnOrgIndex.html`, hướng dẫn cài đặt nằm tại `integrations/google-apps-script/`.

### Auto Sync 1 Lần/Ngày Không Cần Đăng Nhập WP Admin

Vì Google Apps Script chạy trên server Google không thể truy cập IP nội bộ `172.30.134.76`, lịch tự động cần chạy trên một máy nội bộ. Máy này cần mở được UMS và dùng Chrome profile đã đăng nhập SSO Google Workspace. WordPress Admin không cần duy trì phiên đăng nhập.

Plugin cung cấp `Auto Bridge URL` tại Admin menu `Đồng bộ Sheet`. URL có dạng:

```text
http://172.30.134.76/UMS/?ums_auto_sync_bridge=1&token=...
```

Khi URL này được mở, bridge kiểm tra token riêng, tự mở popup Apps Script, nhận dữ liệu qua `postMessage` và POST về WordPress bằng `X-Sync-Token`.

File PowerShell mẫu:

```text
tools/ums-organization-auto-sync.ps1
```

Thiết lập Windows Task Scheduler:

```text
Program/script:
powershell.exe

Arguments:
-ExecutionPolicy Bypass -File "C:\xampp\htdocs\UMS\wp-content\plugins\UMS\tools\ums-organization-auto-sync.ps1"

Trigger:
Daily, 1 time per day
```

Trước khi đặt lịch, copy `Auto Bridge URL` từ UMS Admin > `Đồng bộ Sheet` và dán vào biến `$SyncUrl` trong file PowerShell mẫu. Nên cho phép popup cho site `http://172.30.134.76` trong Chrome, hoặc dùng script mẫu vì script đã mở Chrome với `--disable-popup-blocking`.

## Cấu Trúc Thư Mục Chính

```text
UMS/
|-- admin/
|   |-- class-ums-admin.php
|   |-- css/
|   |-- js/
|   `-- partials/
|       |-- view-annual-allowance-list.php
|       |-- view-approval-flow-list.php
|       |-- view-contract-type-list.php
|       |-- view-department-list.php
|       |-- view-factory-location-list.php
|       |-- view-inventory-list.php
|       |-- view-inventory-movement-list.php
|       |-- view-organization-list.php
|       |-- view-position-list.php
|       |-- view-product-category-list.php
|       `-- view-user-list.php
|-- assets/
|-- includes/
|   |-- class-ums-helper.php
|   |-- class-ums-password-sync.php
|   |-- class-ums-organization-sync.php
|   `-- db/
|       |-- class-ums-db-annual-allowance.php
|       |-- class-ums-db-approval-flow.php
|       |-- class-ums-db-base.php
|       |-- class-ums-db-contract-type.php
|       |-- class-ums-db-department.php
|       |-- class-ums-db-factory-location.php
|       |-- class-ums-db-inventory.php
|       |-- class-ums-db-inventory-movement.php
|       |-- class-ums-db-organization.php
|       |-- class-ums-db-position.php
|       |-- class-ums-db-product-category.php
|       |-- class-ums-db-request.php
|       `-- class-ums-db-user.php
|-- user/
|   |-- class-ums-user.php
|   |-- css/
|   |-- js/
|   `-- partials/
|       |-- components/
|       |-- layout/
|       `-- pages/
|-- tvn-uniform-management.php
|-- ums.sql
`-- Readme.md
```
