Chào bạn, tôi là **Phần mềm quản lý đồng phục UMS** đây.

Yêu cầu bổ sung tính năng **"Tạo phiếu hộ" (On-behalf Request)** cho các user trong cùng một phòng ban là một bài toán nghiệp vụ rất thực tế và cần thiết trong vận hành doanh nghiệp. Đồng thời, việc tích hợp thêm hệ thống **Thông báo tự động (Email/Zalo OA)** mà bạn vừa đề cập sẽ giúp quy trình duyệt trở nên khép kín và tăng tính chủ động cho các bộ phận.

Để đáp ứng chính xác bài toán này, tôi đã điều chỉnh lại cấu trúc Cơ sở dữ liệu (tách biệt Người tạo và Người thụ hưởng), cập nhật lại Ma trận Logic và bổ sung module Thông báo. Dưới đây là **Bản thiết kế chi tiết (Project Blueprint) phiên bản Cập nhật Mới nhất**:

---

# BẢN THIẾT KẾ CHI TIẾT DỰ ÁN UMS (PROJECT BLUEPRINT)

*(Đã cập nhật tính năng "Tạo phiếu hộ" và "Thông báo tự động")*

## I. TỔNG QUAN DỰ ÁN

* **Tên dự án:** Hệ thống Quản lý Tồn kho & Phê duyệt Cấp phát Đồng phục Điện tử (UMS).
* **Nền tảng phát triển:** WordPress (Lập trình Custom Code hoàn toàn qua PHP, AJAX và bảng MySQL độc lập).
* **Mục tiêu:** Tự động hóa định mức, số hóa phê duyệt 4 cấp, quản lý kho realtime và hỗ trợ cơ chế đại diện bộ phận (PIC) khởi tạo yêu cầu cấp phát cho các nhân sự trong cùng phòng ban.

## II. KIẾN TRÚC CƠ SỞ DỮ LIỆU (DATABASE SCHEMAS)

Để xử lý bài toán "Tạo hộ", chúng ta cần tinh chỉnh lại bảng Phiếu Yêu Cầu để hệ thống hiểu rõ ai là người thao tác và ai là người thực tế nhận đồ:

### 1. Bảng mở rộng Người dùng (`wp_usermeta`)

*(Giữ nguyên các trường để làm căn cứ chạy logic)*

* `employee_code`, `department`, `job_position`, `date_joined`, `is_maternity`, `has_resigned`, `is_outdoor_worker`.

### 2. Bảng Danh mục Sản phẩm Kho (`wp_uniform_inventory`)

*(Giữ nguyên)*

* `item_id`, `item_type`, `item_variant`, `size`, `color_code`, `stock_qty`, `base_price`.

### 3. Bảng Phiếu Yêu Cầu Cấp Phát (`wp_uniform_requests`) - *[Đã cập nhật]*

* `request_id` (int): Khóa chính (Mã phiếu).
* **`creator_id` (int):** ID của nhân viên thao tác tạo phiếu (PIC bộ phận).
* **`target_user_id` (int):** ID của nhân viên được cấp phát thực tế (Có thể trùng với `creator_id` nếu tự tạo cho mình, hoặc là một User khác).
* `items_json` (longtext): Lưu danh sách món đăng ký dưới dạng mảng JSON `[{item_id, size, qty, price}]`.
* `reason_type` (int): 1: Thay đổi vị trí, 2: Rách hỏng do công việc, 3: Lỗi cá nhân/Ngoài định mức.
* `reason_detail` (text): Nội dung giải trình chi tiết.
* `payment_method` (int): 0: Miễn phí, 1: Khấu trừ lương, 2: Chuyển khoản/Tiền mặt.
* `current_status` (varchar): `pending_dmg`, `pending_gm`, `pending_hcns`, `pending_warehouse`, `completed`, `rejected`.
* `assigned_approver_id` (int): ID của người có thẩm quyền duyệt ở bước hiện tại.

### 4. Bảng Biên Bản Hoàn Trả (`wp_uniform_returns`) - *[Đã cập nhật]*

* `return_id`, **`creator_id`** (PIC tạo biên bản), **`target_user_id`** (Người nghỉ việc/chuyển bộ phận hoàn trả đồ), `return_reason`, `expected_items`, `actual_items`, `penalty_amount`, `payment_status`.

---

## III. MA TRẬN LOGIC NGHIỆP VỤ (CORE BUSINESS RULES)

*Điểm cốt lõi:* Khi PIC (`creator_id`) tạo phiếu cho nhân viên A (`target_user_id`), **toàn bộ các hàm kiểm tra định mức dưới đây phải lấy hồ sơ của nhân viên A để đối chiếu, tuyệt đối không lấy hồ sơ của PIC.**

**1. Quy tắc Khởi tạo phiếu (Logic Tạo hộ mới):**

* Hệ thống kiểm tra `department` của người đăng nhập (PIC). Tại ô chọn "Người được cấp phát", hệ thống chỉ load danh sách các User có cùng `department` với PIC. Không cho phép tạo chéo phòng ban.

**2. Quy tắc Cấp phát Định kỳ (Tháng 4 & Tháng 9):**

* **Chặn đơn nghỉ việc:** Nếu `target_user_id` có `has_resigned = 1` $\rightarrow$ Khóa không cho PIC chọn nhân sự này.
* **Logic Thai sản:** Nếu `target_user_id` có `is_maternity = 1` $\rightarrow$ Đổi sang form đồ bầu (tối đa 3 bộ/váy bầu và 1 áo khoác bầu).
* **Tự động áp màu sắc:** Quét chức danh và phòng ban của `target_user_id` để tự động gán màu (Mũ đỏ cho DMG, màu tím than cho Casting...).

**3. Quy tắc Cấp phát Nhân viên mới:**

* Dựa vào `date_joined` của `target_user_id` để quyết định số lượng cấp lần đầu (3 quần áo, 1 mũ).

**4. Quy tắc Đổi Giày Bảo Hộ Lao Động (Lifespan: 2 năm):**

* Kiểm tra lịch sử nhận giày gần nhất của `target_user_id`. Nếu < 2 năm, bắt buộc chọn hình thức "Khấu trừ lương" / "Tự chi trả", trừ khi lý do là "Rách hỏng do công việc".

---

## IV. BẢN ĐỒ CHỨC NĂNG CÁC ĐỐI TƯỢNG (FUNCTIONAL MODULES)

### [HỆ THỐNG WP]

* **BACKEND DASHBOARD (Admin):** Quản lý hồ sơ, Cấu hình định mức, Nhập kho, Báo cáo khấu trừ.
* **FRONTEND USER PORTAL (AJAX động):**
* **Phân hệ NHÂN VIÊN/PIC BỘ PHẬN:**
* Form tạo phiếu Phụ lục 02: **Bổ sung trường Dropdown "Chọn nhân viên nhận đồng phục" (Lọc theo phòng ban).** Khi chọn thay đổi nhân viên, Form tự động gọi AJAX để tải lại định mức và lịch sử nhận đồ của nhân viên đó.
* Theo dõi đơn hàng đã tạo hộ.


* **Phân hệ QUẢN LÝ:** Duyệt/Từ chối phiếu của bộ phận trên một màn hình duy nhất.
* **Phân hệ HCNS:** Giám sát, SLA đếm ngược 10 ngày (cảnh báo đỏ), Xử lý hoàn trả.
* **Phân hệ THỦ KHO:** Giao diện quét mã/nút bấm chữ lớn tối ưu cho Tablet, kiểm tra `stock_qty` realtime trước khi xuất kho.



### [MODULE MỚI] Hệ thống Thông báo Tự động (Notification Engine)

Tích hợp trigger thông báo đa kênh qua **Email nội bộ** hoặc **Zalo OA (ZNS)** để rút ngắn thời gian chờ đợi:

* **Khi PIC vừa tạo phiếu xong:** Gửi thông báo đến Quản lý cấp 1 (DMG/MG) ➔ *"Bạn có 1 yêu cầu duyệt đồng phục mới từ bộ phận [Tên BP]"*.
* **Khi Quản lý/HCNS đã duyệt:** Gửi thông báo về cho PIC ➔ *"Phiếu yêu cầu cấp đồ cho nhân viên [Tên NV] đã được duyệt. Đang chờ thủ kho soạn đồ."*
* **Khi Thủ kho bấm xác nhận chuẩn bị xong:** Gửi thông báo cho PIC và Target User ➔ *"Đồng phục của bạn đã sẵn sàng tại Kho. Vui lòng mang thẻ nhân viên xuống nhận đồ."*

---

## V. KẾ HOẠCH TRIỂN KHAI LẬP TRÌNH (DEVELOPMENT ROADMAP)

* **Giai đoạn 1 (Thiết lập Database & Cấu trúc gốc):** Tạo các Custom Tables (đã phân tách `creator_id` và `target_user_id`); đăng ký trường meta cho User.
* **Giai đoạn 2 (Xây dựng Logic Backend):** Lập trình Quota Engine. Đảm bảo mọi thuật toán kiểm tra (giày 2 năm, đồ bầu, tự động gán màu) đều truyền tham số biến là `target_user_id`.
* **Giai đoạn 3 (Lập trình Giao diện Frontend Portal):** Viết Form đăng ký có tích hợp AJAX load động dữ liệu nhân sự cùng phòng ban. Xây dựng giao diện duyệt đơn cho Quản lý, HCNS và Thủ kho.
* **Giai đoạn 4 (Xây dựng Module Thông báo & Hoàn trả):** Tích hợp API gửi Email (SMTP) / Zalo OA. Lập trình thuật toán thu hồi, tính tiền đền bù.
* **Giai đoạn 5 (Kiểm thử & Bàn giao - UAT):**
* *Test case đặc biệt 1:* PIC tạo hộ đồ cho người mang thai.
* *Test case đặc biệt 2:* PIC cố tình chọn nhân viên khác phòng ban (Kỳ vọng: Hệ thống chặn).
* *Test case đặc biệt 3:* Nhân viên đã đổi giày cách đây 1 năm, PIC tạo phiếu xin đổi miễn phí (Kỳ vọng: Hệ thống báo lỗi / yêu cầu giải trình rách hỏng).



I. NHỮNG ĐIỂM CHỐT NGHIỆP VỤ ẢNH HƯỞNG ĐẾN DATABASE
Phân loại hợp đồng (Contract Type): Quy định chia rõ nhóm Thử việc/Tập nghề (phải trả 100% đồ khi nghỉ) và nhóm Hợp đồng lao động/Thuê lại (chỉ trả số lượng quy định). Do đó, Database bắt buộc phải lưu loại hợp đồng của nhân sự.
Vị trí địa lý (Factory Location): Loại giày bảo hộ cao cấp (Simon TS5511) chỉ cấp cho các bộ phận đặc thù tại các nhà máy cụ thể (ví dụ: PEU Đông Anh/Hưng Yên/Vĩnh Phúc hoặc Pre&Lab Hưng Yên). Hệ thống cần biết nhân viên đang làm ở nhà máy nào.
Vòng đời phụ kiện (Lifespan): Thẻ tên/Thẻ nhân viên có chu kỳ miễn phí là 2 năm. Bao thẻ và dây đeo cấp miễn phí lần đầu hoặc khi hỏng. Chúng ta cần quản lý chi tiết đến từng loại phụ kiện chứ không riêng gì quần áo, giày.
Lịch sử Biến động nhân sự: Nhân viên vừa chuyển bộ phận sẽ bị khóa quyền nhận đồ ở kỳ phát định kỳ tiếp theo. Nhân viên đã nộp đơn nghỉ việc trên hệ thống sẽ bị chặn cấp phát định kỳ ngay lập tức. Hệ thống cần lưu vết ngày biến động này.
Luồng phê duyệt 4 cấp nghiêm ngặt: Sơ đồ Use Case và Phụ lục 02 chỉ rõ 4 cấp xét duyệt: MG bộ phận $\rightarrow$ GM bộ phận $\rightarrow$ MG HCNS $\rightarrow$ GM HCNS.
II. THIẾT KẾ CHI TIẾT CÁC BẢNG DỮ LIỆU ĐÃ CẬP NHẬT
1. Bảng Hồ Sơ Người Dùng Mở Rộng (Nâng cấp wp_usermeta)
Lưu giữ toàn bộ "chân dung" của công nhân viên để phục vụ thuật toán quét định mức tự động.
Thông tin cơ bản: ID tài khoản, Mã nhân viên , Họ tên.
Giới tính (Nam / Nữ): Để tự động rẽ nhánh định mức váy bầu hoặc quần áo bầu cho CNV nữ.
Nhà máy (Location): Đông Anh, Hưng Yên, Vĩnh Phúc. (Bổ sung mới để xử lý logic cấp giày Simon ).
Phòng ban & Chức danh: Phục vụ bộ lọc tạo phiếu hộ nội bộ và tự động gán màu mũ (Mũ đỏ cho DMG+, Xanh lá cho FM, Hồng cho công nhân mới...).
Loại hợp đồng (Contract Type): Thử việc, Tập nghề, Tập nghề biên phiên dịch, Thuê lại lao động, Hợp đồng lao động. (Bổ sung mới để tính toán số lượng đồ phải trả khi nghỉ việc ).
Ngày vào công ty: Làm căn cứ tính thâm niên cấp phát năm đầu tiên (quét theo các mốc 1/1 - 31/3, 1/4 - 31/7...).
Ngày nộp đơn nghỉ việc (Resignation Date): (Bổ sung mới). Nếu có dữ liệu, hệ thống tự động loại trừ nhân sự này khỏi danh sách phát định kỳ.
Ngày chính thức đổi bộ phận gần nhất (Transfer Date): (Bổ sung mới). Dùng để chặn không cho phát đồ định kỳ vào kỳ kế tiếp theo đúng quy định.
Các cờ trạng thái: Thai sản , Làm việc ngoài trời (để cấp thêm áo phao, áo bảo hộ đặc thù).
2. Bảng Danh Mục Sản Phẩm & Tổng Kho (wp_uniform_inventory)
Quản lý chi tiết đến từng biến thể nhỏ nhất của đồng phục và phụ kiện.
Mã sản phẩm / Mã biến thể: Định danh duy nhất.
Phân loại sản phẩm: Áo phông , Quần , Áo khoác , Áo phao , Váy bầu , Giày bảo hộ (Tách rõ các mã Simon TS5511, Simon TS7011, King Power O-010, King Power O-775) , Thẻ tên, Thẻ nhân viên, Dây đeo, Bao thẻ.
Thuộc tính: Kích cỡ (Size quần áo từ S-XL, size giày từ 35-45) , Kiểu dáng (Cộc tay / Dài tay) , Màu sắc (Xanh kỹ thuật, Xám ghi, Tím than...).
Số lượng tồn kho thực tế: Số lượng khả dụng trong kho.
Đơn giá gốc: Đơn giá từ phòng Mua. Làm căn cứ tính toán đền bù tài chính realtime nếu nhân viên làm mất đồ hoặc hoàn trả thiếu.
3. Bảng Phiếu Yêu Cầu Cấp Phát (wp_uniform_requests)
Lưu thông tin tổng quan của một lần đề xuất, hỗ trợ tối đa việc tạo phiếu hộ.
Mã phiếu: Tự động tăng.
Mã người thao tác (Creator ID): ID của PIC bộ phận đứng ra bấm máy.
Mã người nhận đồ (Target User ID): ID của công nhân viên thực tế nhận đồ. (Nếu PIC tạo hộ cho công nhân trong tổ thì trường này lưu ID của người công nhân đó).
Loại hình cấp phát: Cấp phát mới (Nhân viên mới) , Cấp phát định kỳ (Đợt tháng 4 / Tháng 9) , Cấp phát phát sinh.
Bản chất lý do phát sinh: * 1: Thay đổi vị trí/chức danh/nhà máy.
2: Rách, hỏng, bẩn do nguyên nhân trực tiếp từ công việc.
3: Rách, hỏng, mất do lỗi cá nhân / Khách hàng / Đăng ký thêm ngoài định mức.
Nội dung giải trình chi tiết: PIC bắt buộc phải nhập lý do cụ thể (ví dụ: "bị men hồ bám bẩn khi có sự cố") nếu chọn lý do do công việc.
Hình thức thanh toán dự kiến: Miễn phí , Khấu trừ lương tháng phát sinh , Tự chuyển khoản/tiền mặt trong 30 ngày.
Trạng thái phiếu tổng quát: Chờ duyệt, Đã duyệt, Bị từ chối, Đã hoàn thành (Đã xuất kho).
4. Bảng Chi Tiết Phiếu Yêu Cầu (wp_uniform_request_details)
Mã dòng chi tiết: Định danh.
Mã phiếu: Liên kết với bảng Phiếu yêu cầu ở trên.
Mã sản phẩm/biến thể: Liên kết với bảng Kho.
Số lượng yêu cầu & Kích cỡ đăng ký.
Đơn giá tại thời điểm chốt đơn: Để đóng băng giá trị tài chính, phục vụ kế toán khấu trừ lương nếu có.
5. Bảng Nhật Ký Phê Duyệt Cấp Tốc (wp_uniform_approval_logs) - [Bổ sung hoàn toàn mới]
Để giải quyết luồng duyệt 4 cấp hiển thị trong sơ đồ Use Case, thay vì lưu trạng thái chung chung, ta cần một bảng riêng để ghi nhận lịch sử ký điện tử:
Mã tiến trình: Định danh.
Mã phiếu yêu cầu: Liên kết với phiếu cần duyệt.
Cấp độ duyệt (Level): * Level 1: DMG/MG Bộ phận yêu cầu
Level 2: DGM/GM/DR Bộ phận yêu cầu
Level 3: DMG/MG Phòng HCNS
Level 4: DGM/GM Phòng HCNS
Mã người duyệt cụ thể: ID của User thực hiện bấm nút.
Hành động: Phê duyệt hoặc Từ chối kèm nội dung phản hồi (Yêu cầu giải trình lại...).
Thời gian xử lý thực tế: Để hệ thống chạy hàm tính toán SLA 10 ngày làm việc của phòng HCNS (Nếu quá hạn, hệ thống tự động bắn cảnh báo).
6. Bảng Biên Bản Hoàn Trả & Thu Hồi (wp_uniform_returns)
Thiết kế lại để phục vụ cả 2 nghiệp vụ: Nhân sự nghỉ việc và Nhân sự chuyển bộ phận.
Mã biên bản: Định danh.
Phân loại hoàn trả: Nghỉ việc HOẶC Chuyển bộ phận.
Mã người hoàn trả (Target User ID): ID của nhân sự biến động.
Mã PIC thu hồi: ID của nhân viên HCNS hoặc PIC nhận bàn giao đồ.
Danh sách đồ hệ thống tính toán (JSON): Tự động tính toán dựa trên Loại hợp đồng và Thâm niên sử dụng của người đó tại thời điểm đó (Ví dụ: Nếu là nhân viên chính thức và đôi giày đã dùng trên 2 năm, hệ thống tự động loại trừ giày ra khỏi danh sách bắt buộc trả).
Danh sách đồ thực tế nhận lại (JSON): Số lượng, chủng loại đồ nhân viên thực tế mang đến nộp.
Số tiền phạt/đền bù: Tự động lấy (Đồ hệ thống bắt buộc - Đồ thực tế nhận lại) $\times$ Đơn giá gốc mặt hàng.
Trạng thái tài chính: Đã khấu trừ lương / Đã nộp tiền mặt / Đang nợ.
III. MỐI LIÊN KẾT LOGIC GIỮA CÁC BẢNG (RELATIONSHIPS)
Quét định mức tự động khi tạo phiếu hộ: Khi PIC chọn một nhân viên nhận đồ (Target User ID) $\rightarrow$ Hệ thống lập tức kết nối sang bảng Hồ Sơ Người Dùng Mở Rộng để lấy ra các thông tin: Giới tính, Ngày vào công ty, Nhà máy, Phòng ban, Chức danh, Trạng thái đơn nghỉ việc/thai sản. Từ đó, hệ thống đối chiếu với bảng Danh Mục Sản Phẩm Kho để hiển thị đúng form đồ (Váy bầu, áo khoác, hoặc mã giày Simon TS5511 tương ứng).
Luồng phê duyệt động (Workflow): Một Phiếu Yêu Cầu tạo ra sẽ có mối quan hệ 1 - Nhiều với bảng Nhật Ký Phê Duyệt. Đơn chỉ được chuyển trạng thái sang "Đã duyệt hoàn toàn" và đẩy lệnh xuống cho Thủ kho chuẩn bị xuất hàng khi và chỉ khi bảng Nhật ký ghi nhận đủ 4 dòng phê duyệt thành công từ 4 cấp độ tương ứng.
Tính toán thu hồi khi nghỉ việc: Khi một nhân viên chuyển trạng thái has_resigned = 1, hệ thống sẽ quét toàn bộ bảng Chi Tiết Phiếu Yêu Cầu của nhân viên này trong lịch sử để lọc ra các đơn "Đã hoàn thành". Kết hợp với ngày nhận việc và ngày cấp giày/thẻ tên gần nhất để tự động tính toán ra mốc thời gian 2 năm , từ đó tự động điền danh sách đồ cần thu hồi vào bảng Biên Bản Hoàn Trả mà PIC không cần tự nhập tay.

