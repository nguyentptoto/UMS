# UMS - Uniform Management System

UMS là plugin WordPress quản lý hồ sơ nhân sự, danh mục phòng ban, danh mục sản phẩm đồng phục, tổng kho và luồng duyệt cấp phát theo phòng ban.

## Nền tảng

- WordPress plugin thuần PHP.
- Dữ liệu nghiệp vụ chính lưu trong custom tables có prefix WordPress.
- Tài khoản đăng nhập dùng bảng chuẩn `wp_users`.
- Hồ sơ nhân sự lưu ở `wp_uniform_user_profiles` và liên kết với `wp_users` qua `user_id`.
- Giao diện Admin dùng WordPress Admin + jqxGrid.
- Giao diện User dùng shortcode frontend: `[ums_user_portal]`.

## Chức năng hiện có

### Admin

- Hồ sơ Nhân sự
  - Tạo, sửa, xóa hồ sơ nhân sự.
  - Khi tạo hồ sơ sẽ tạo tài khoản WordPress tương ứng.
  - `user_login` mặc định là mã nhân viên.
  - Mật khẩu mặc định khi tạo/reset: `12345678`.
  - Có thể đồng bộ hash mật khẩu từ DB nguồn bên ngoài theo `user_login`/`manv`.
  - Active/Inactive tài khoản lưu bằng `wp_users.user_status`.
  - Tài khoản inactive bị chặn đăng nhập nếu có hồ sơ UMS liên kết.

- Phòng ban
  - Quản lý mã phòng ban, tên phòng ban, trạng thái sử dụng.

- Luồng duyệt phòng ban
  - Cấu hình chuỗi duyệt động theo phòng ban.
  - Không cố định 4 cấp.
  - Một bước duyệt có thể có nhiều người duyệt, lưu trong một dòng bằng JSON `approver_profile_ids`.

- Danh mục Sản phẩm
  - Quản lý danh mục cha và danh mục con.
  - Không dùng `category_code`.
  - Bảng danh mục hiển thị theo nhóm cha/con.

- Sản phẩm & Tổng kho
  - Quản lý sản phẩm theo danh mục con, biến thể, size, màu/mã màu, tồn kho, đơn giá gốc.
  - Màu/mã màu không bắt buộc ở giao diện.
  - Đơn giá hỗ trợ số lớn và chuẩn hóa định dạng nhập.

### User Portal

Shortcode:

```text
[ums_user_portal]
```

Portal frontend hiện có:

- Kiểm tra đăng nhập.
- Kiểm tra tài khoản đã có hồ sơ UMS hay chưa.
- Chặn hiển thị nếu tài khoản UMS inactive.
- Hiển thị thông tin hồ sơ cá nhân.
- Hiển thị người nhận cùng phòng ban, đang active.
- Hiển thị sản phẩm khả dụng trong kho.
- Hiển thị luồng duyệt đang active của phòng ban.
- Có giao diện nháp tạo yêu cầu đồng phục. Phần lưu phiếu sẽ cần bổ sung lớp dữ liệu riêng cho phiếu yêu cầu.

## Cấu trúc thư mục chính

```text
UMS/
├── admin/
│   ├── class-ums-admin.php
│   ├── css/ums-admin.css
│   ├── js/ums-admin.js
│   └── partials/
├── includes/
│   ├── class-ums-helper.php
│   └── db/
│       ├── class-ums-db-base.php
│       ├── class-ums-db-installer.php
│       ├── class-ums-db-user.php
│       ├── class-ums-db-department.php
│       ├── class-ums-db-approval-flow.php
│       ├── class-ums-db-product-category.php
│       └── class-ums-db-inventory.php
├── user/
│   ├── class-ums-user.php
│   ├── css/ums-user.css
│   ├── js/ums-user.js
│   └── partials/
├── assets/
│   ├── css/
│   └── js/
├── tvn-uniform-management.php
├── ums.sql
└── Readme.md
```

## Database chính

### `wp_uniform_user_profiles`

Lưu hồ sơ nhân sự, không lưu mật khẩu hoặc trạng thái tài khoản trùng với WordPress.

Các trường chính:

- `profile_id`
- `user_id`
- `employee_code`
- `full_name`
- `gender`
- `factory_location`
- `department`
- `job_position`
- `contract_type`
- `date_joined`
- `resignation_date`
- `transfer_date`
- `is_maternity`
- `is_outdoor_worker`

### `wp_uniform_departments`

Danh mục phòng ban:

- `department_id`
- `department_code`
- `department_name`
- `is_active`

### `wp_uniform_department_approval_flows`

Luồng duyệt động theo phòng ban:

- `flow_id`
- `department_id`
- `step_order`
- `step_name`
- `approver_profile_ids`
- `is_active`

### `wp_uniform_product_categories`

Danh mục sản phẩm cha/con:

- `category_id`
- `parent_id`
- `category_name`
- `is_active`

### `wp_uniform_inventory`

Sản phẩm và tổng kho:

- `item_id`
- `category_id`
- `item_type`
- `item_variant`
- `size`
- `color_code`
- `stock_qty`
- `base_price`

## Luồng tài khoản nhân sự

1. Admin tạo hồ sơ nhân sự.
2. Plugin tạo tài khoản trong `wp_users`.
3. `wp_uniform_user_profiles.user_id` lưu ID tài khoản WordPress.
4. Khi cập nhật hồ sơ:
   - Họ tên đồng bộ sang `display_name`.
   - Mã nhân viên đồng bộ sang `user_login` nếu chưa bị trùng.
   - Active/Inactive đồng bộ sang `user_status`.
   - Reset password đặt lại mật khẩu thành `12345678`.

## Đồng bộ mật khẩu từ DB ngoài

Admin có quyền `promote_users` có thể bấm nút `Đồng bộ mật khẩu` trên thanh lọc của bảng Hồ sơ Nhân sự. Nút này xử lý toàn bộ tài khoản đang nằm trong kết quả lọc hiện tại.

Logic đồng bộ:

1. Lấy `wp_users.user_login` của tài khoản đang chọn.
2. Kết nối DB nguồn.
3. Query:

```sql
SELECT password FROM users WHERE manv = ? AND active = 1 LIMIT 1
```

4. Ghi giá trị `password` lấy được vào `wp_users.user_pass`.
5. Nếu không kết nối được DB nguồn, không tìm thấy bản ghi active, hoặc hash nguồn không hợp lệ, hệ thống đặt mật khẩu mặc định `12345678`.
6. Lưu meta:
   - `ums_password_synced_at`
   - `ums_password_synced_by`

Cấu hình DB nguồn có thể đặt bằng hằng số trong `wp-config.php`:

```php
define( 'UMS_PASSWORD_SYNC_DB_HOST', '172.30.134.15' );
define( 'UMS_PASSWORD_SYNC_DB_USER', 'Tvnsoft' );
define( 'UMS_PASSWORD_SYNC_DB_PASSWORD', 'your-password' );
define( 'UMS_PASSWORD_SYNC_DB_NAME', 'tvnias' );
```

Nếu không khai báo hằng số, plugin dùng default host/user/database như trên và password rỗng.

## Cài đặt User Portal

1. Tạo một page WordPress, ví dụ `UMS Portal`.
2. Chèn shortcode:

```text
[ums_user_portal]
```

3. Gán quyền đăng nhập cho user đã được tạo từ Hồ sơ Nhân sự.

## Ghi chú phát triển tiếp

- Cần bổ sung lớp dữ liệu cho phiếu yêu cầu cấp phát để User Portal lưu form tạo yêu cầu.
- Cần bổ sung bảng/lớp xử lý lịch sử duyệt thực tế nếu muốn chuyển luồng duyệt từ cấu hình sang xử lý phiếu.
- Có thể mở rộng thông báo email/Zalo sau khi module phiếu yêu cầu hoàn chỉnh.
