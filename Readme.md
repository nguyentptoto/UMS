# UMS - Uniform Management System

UMS là plugin WordPress quản lý hồ sơ nhân sự, phòng ban, danh mục sản phẩm đồng phục, tổng kho, phiếu yêu cầu cấp phát và luồng duyệt động theo phòng ban.

## Nguyên tắc Database

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
- `wp_uniform_inventory_movements`
- `wp_uniform_requests`
- `wp_uniform_request_details`
- `wp_uniform_approval_logs`
- `wp_uniform_returns`

## Chức năng Admin

- Quản lý Hồ sơ Nhân sự
- Quản lý Phòng ban
- Quản lý Chức danh
- Quản lý Nhà máy
- Quản lý Hợp đồng
- Quản lý Luồng duyệt phòng ban
- Quản lý Danh mục sản phẩm cha/con
- Quản lý Sản phẩm & Tổng kho
- Xem Lịch sử nhập xuất kho chi tiết
- Đồng bộ mật khẩu từ DB ngoài vào `wp_users.user_pass`

Hồ sơ nhân sự liên kết với tài khoản WordPress qua `wp_uniform_user_profiles.user_id`. Khi tạo hồ sơ, hệ thống tạo tài khoản WordPress tương ứng, `user_login` mặc định là mã nhân viên và mật khẩu mặc định là `12345678`.

## User Portal

Tạo một page WordPress và chèn shortcode:

```text
[ums_user_portal]
```

Portal hiển thị độc lập bằng layout của plugin, nhưng vẫn gọi các hook chuẩn `wp_head()`, `wp_body_open()` và `wp_footer()`.

Các trang portal hiện có:

- Tổng quan
- Tạo yêu cầu
- Luồng duyệt
- Hồ sơ của tôi

Module `Tạo yêu cầu` chỉ hiển thị cho user có `profile_id` nằm trong `approver_profile_ids` của bước duyệt `step_order = 1` thuộc phòng ban hiện tại. User ngoài bước 1 không thấy module này.

## Luồng tạo yêu cầu

Khi user hợp lệ bấm `Gửi duyệt`:

1. Phiếu được lưu vào `wp_uniform_requests`.
2. Trạng thái ban đầu là `pending_step_1`.
3. Chi tiết đồng phục được lưu vào `wp_uniform_request_details`.
4. Giá được tính lại từ dữ liệu kho: `base_price * quantity`.
5. Hệ thống tạo log `submitted` trong `wp_uniform_approval_logs` tại `step_order = 1`.
6. Hệ thống ghi một dòng `request_out` vào `wp_uniform_inventory_movements` để Admin nhìn thấy yêu cầu xuất kho theo từng vật tư.

Luồng duyệt là chuỗi động theo bảng `wp_uniform_department_approval_flows`, không cố định 4 cấp.

## Lịch sử nhập xuất kho

Admin xem tại menu `Lịch sử kho`.

Nguồn dữ liệu nằm ở `wp_uniform_inventory_movements`:

- `in`: nhập kho hoặc tăng tồn kho từ Admin.
- `out`: giảm tồn kho từ Admin.
- `adjust`: cập nhật thông tin sản phẩm/tồn kho nhưng không đổi số lượng.
- `request_out`: user gửi yêu cầu xuất kho, có liên kết `request_id`, người tạo và người nhận.

Trang lịch sử hiển thị thời gian, loại phát sinh, mã phiếu, sản phẩm, size, số lượng, tồn trước/sau, đơn giá, thành tiền, người thao tác, người nhận và ghi chú.

## Đồng bộ mật khẩu ngoài

Có thể cấu hình DB nguồn bằng hằng số trong `wp-config.php`:

```php
define( 'UMS_PASSWORD_SYNC_DB_HOST', '172.30.134.15' );
define( 'UMS_PASSWORD_SYNC_DB_USER', 'Tvnsoft' );
define( 'UMS_PASSWORD_SYNC_DB_PASSWORD', 'your-password' );
define( 'UMS_PASSWORD_SYNC_DB_NAME', 'tvnias' );
```

Hash lấy từ DB nguồn được ghi trực tiếp vào `wp_users.user_pass`, không hash lại. Nếu đồng bộ thất bại, hệ thống đặt mật khẩu về mặc định `12345678` bằng hàm hash của WordPress.

## Cấu trúc thư mục chính

```text
UMS/
├── admin/
├── assets/
├── includes/
│   ├── class-ums-helper.php
│   ├── class-ums-password-sync.php
│   └── db/
│       ├── class-ums-db-base.php
│       ├── class-ums-db-user.php
│       ├── class-ums-db-department.php
│       ├── class-ums-db-position.php
│       ├── class-ums-db-factory-location.php
│       ├── class-ums-db-contract-type.php
│       ├── class-ums-db-approval-flow.php
│       ├── class-ums-db-product-category.php
│       ├── class-ums-db-inventory.php
│       ├── class-ums-db-inventory-movement.php
│       └── class-ums-db-request.php
├── user/
├── tvn-uniform-management.php
├── ums.sql
└── Readme.md
```
