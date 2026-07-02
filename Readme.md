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
- Đồng bộ mật khẩu từ DB ngoài vào `wp_users.user_pass`

Hồ sơ nhân sự liên kết với tài khoản WordPress qua `wp_uniform_user_profiles.user_id`. Khi tạo hồ sơ, hệ thống tạo tài khoản WordPress tương ứng, `user_login` mặc định là mã nhân viên và mật khẩu mặc định là `12345678`.

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
|       |-- view-position-list.php
|       |-- view-product-category-list.php
|       `-- view-user-list.php
|-- assets/
|-- includes/
|   |-- class-ums-helper.php
|   |-- class-ums-password-sync.php
|   `-- db/
|       |-- class-ums-db-annual-allowance.php
|       |-- class-ums-db-approval-flow.php
|       |-- class-ums-db-base.php
|       |-- class-ums-db-contract-type.php
|       |-- class-ums-db-department.php
|       |-- class-ums-db-factory-location.php
|       |-- class-ums-db-inventory.php
|       |-- class-ums-db-inventory-movement.php
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
