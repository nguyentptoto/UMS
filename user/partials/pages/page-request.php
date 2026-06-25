<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$default_target = ! empty( $teammates ) ? $teammates[0] : $profile;
foreach ( $teammates as $teammate ) {
    if ( (int) $teammate['user_id'] === (int) $profile['user_id'] ) {
        $default_target = $teammate;
        break;
    }
}

$category_tree   = isset( $category_tree ) && is_array( $category_tree ) ? $category_tree : array();
$inventory_items = isset( $inventory_items ) && is_array( $inventory_items ) ? $inventory_items : array();

$render_request_item_row = function ( $index, $is_template = false ) use ( $category_tree, $inventory_items ) {
    $prefix      = 'request_items[' . $index . ']';
    $row_classes = 'ums-request-item';
    if ( $is_template ) {
        $row_classes .= ' is-template';
    }
    ?>
    <div class="<?php echo esc_attr( $row_classes ); ?>" data-ums-request-item>
        <div class="ums-request-item-head">
            <strong>Dòng đồng phục</strong>
            <button type="button" class="ums-user-link-button" data-ums-remove-item>Xóa dòng</button>
        </div>

        <div class="ums-user-request-form">
            <label>
                <span>Loại đồng phục</span>
                <select name="<?php echo esc_attr( $prefix ); ?>[parent_category_id]" data-ums-parent-category required>
                    <option value="">Chọn danh mục cha</option>
                    <?php foreach ( $category_tree as $parent ) : ?>
                        <option value="<?php echo esc_attr( $parent['category_id'] ); ?>">
                            <?php echo esc_html( $parent['category_name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Loại quần áo/giày</span>
                <select name="<?php echo esc_attr( $prefix ); ?>[category_id]" data-ums-child-category required>
                    <option value="">Chọn danh mục con</option>
                    <?php foreach ( $category_tree as $parent ) : ?>
                        <?php foreach ( $parent['children'] as $child ) : ?>
                            <option
                                value="<?php echo esc_attr( $child['category_id'] ); ?>"
                                data-parent-id="<?php echo esc_attr( $parent['category_id'] ); ?>"
                                hidden
                            >
                                <?php echo esc_html( $child['category_name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Size</span>
                <select name="<?php echo esc_attr( $prefix ); ?>[size]" data-ums-size-select required>
                    <option value="">Chọn size</option>
                    <?php foreach ( $inventory_items as $item ) : ?>
                        <?php
                        $size_label = $item['size'];
                        if ( ! empty( $item['item_variant'] ) ) {
                            $size_label .= ' - ' . $item['item_variant'];
                        }
                        $size_label .= ' - Tồn ' . (int) $item['stock_qty'];
                        ?>
                        <option
                            value="<?php echo esc_attr( $item['size'] ); ?>"
                            data-category-id="<?php echo esc_attr( $item['category_id'] ); ?>"
                            data-inventory-id="<?php echo esc_attr( $item['item_id'] ); ?>"
                            data-price="<?php echo esc_attr( number_format( (float) $item['base_price'], 0, '.', '' ) ); ?>"
                            data-variant="<?php echo esc_attr( $item['item_variant'] ); ?>"
                            hidden
                        >
                            <?php echo esc_html( $size_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>SL</span>
                <input type="number" name="<?php echo esc_attr( $prefix ); ?>[quantity]" min="1" step="1" value="1" data-ums-quantity-input required>
            </label>

            <label>
                <span>Giá</span>
                <input type="text" name="<?php echo esc_attr( $prefix ); ?>[price]" data-ums-inventory-field="price" inputmode="decimal" placeholder="Tự tính theo số lượng" readonly>
            </label>

            <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[inventory_item_id]" data-ums-inventory-field="inventory_id">
            <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[item_variant]" data-ums-inventory-field="variant">
            <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[unit_price]" data-ums-inventory-field="unit_price">
        </div>
    </div>
    <?php
};
?>

<section class="ums-page-title">
    <div>
        <h1>Phiếu yêu cầu cấp đồng phục</h1>
        <p>Thông tin người nhận, vật tư yêu cầu, lý do cấp phát và phương thức thanh toán nếu phát sinh đền bù.</p>
    </div>
    <span class="ums-user-badge">Bản giao diện</span>
</section>

<form class="ums-request-page" data-ums-user-form>
    <section class="ums-user-panel">
        <div class="ums-user-panel-head">
            <div>
                <h3>Thông tin CNV nhận đồng phục</h3>
                <p>Danh sách người nhận được giới hạn theo phòng ban của tài khoản đang đăng nhập.</p>
            </div>
        </div>

        <div class="ums-user-request-form">
            <label>
                <span>Chọn CNV nhận đồ</span>
                <select name="target_user_id" data-ums-target-select>
                    <?php foreach ( $teammates as $teammate ) : ?>
                        <option
                            value="<?php echo esc_attr( $teammate['user_id'] ); ?>"
                            data-employee-code="<?php echo esc_attr( $teammate['employee_code'] ); ?>"
                            data-full-name="<?php echo esc_attr( $teammate['full_name'] ); ?>"
                            data-department="<?php echo esc_attr( $teammate['department'] ); ?>"
                            data-date-joined="<?php echo esc_attr( ! empty( $teammate['date_joined'] ) ? mysql2date( 'd/m/Y', $teammate['date_joined'] ) : '' ); ?>"
                            <?php selected( (int) $teammate['user_id'], (int) $default_target['user_id'] ); ?>
                        >
                            <?php echo esc_html( $teammate['employee_code'] . ' - ' . $teammate['full_name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Mã nhân viên</span>
                <input type="text" name="employee_code" value="<?php echo esc_attr( $default_target['employee_code'] ); ?>" data-ums-target-field="employee_code" readonly>
            </label>

            <label>
                <span>Tên CNV</span>
                <input type="text" name="full_name" value="<?php echo esc_attr( $default_target['full_name'] ); ?>" data-ums-target-field="full_name" readonly>
            </label>

            <label>
                <span>Phòng / Bộ phận làm việc</span>
                <input type="text" name="department" value="<?php echo esc_attr( $default_target['department'] ); ?>" data-ums-target-field="department" readonly>
            </label>

            <label>
                <span>Ngày vào Công ty</span>
                <input type="text" name="date_joined" value="<?php echo esc_attr( ! empty( $default_target['date_joined'] ) ? mysql2date( 'd/m/Y', $default_target['date_joined'] ) : '' ); ?>" data-ums-target-field="date_joined" readonly>
            </label>
        </div>
    </section>

    <section class="ums-user-panel">
        <div class="ums-user-panel-head">
            <div>
                <h3>Thông tin đồng phục / vật tư</h3>
                <p>Chọn loại đồng phục, loại quần áo/giày và size; giá sẽ tự tính theo đơn giá hệ thống và số lượng.</p>
            </div>
            <button type="button" class="ums-user-button ums-user-button-light" data-ums-add-item>Thêm đồng phục</button>
        </div>

        <?php if ( empty( $category_tree ) || empty( $inventory_items ) ) : ?>
            <div class="ums-user-empty-inline">
                Chưa có danh mục sản phẩm hoặc sản phẩm tồn kho khả dụng để tạo yêu cầu.
            </div>
        <?php endif; ?>

        <div class="ums-request-items" data-ums-request-items>
            <?php $render_request_item_row( 0 ); ?>
        </div>

        <template data-ums-request-item-template>
            <?php $render_request_item_row( '__INDEX__', true ); ?>
        </template>
    </section>

    <section class="ums-user-panel">
        <div class="ums-user-panel-head">
            <div>
                <h3>Lý do yêu cầu cấp phát</h3>
                <p>Chọn đúng nhóm lý do để hệ thống xác định nghĩa vụ thanh toán hoặc giải trình.</p>
            </div>
        </div>

        <div class="ums-reason-list" data-ums-reason-group>
            <label class="ums-reason-option">
                <input type="radio" name="reason_type" value="1" checked>
                <span>
                    <strong>Lý do 1</strong>
                    Do thay đổi vị trí công việc: chuyển công việc, bộ phận, vị trí, làm việc ngoài trời...
                </span>
            </label>

            <label class="ums-reason-option">
                <input type="radio" name="reason_type" value="2">
                <span>
                    <strong>Lý do 2</strong>
                    Đồng phục rách/hỏng/bẩn do nguyên nhân trực tiếp từ việc thực thi công việc đảm nhiệm.
                </span>
            </label>

            <label class="ums-reason-option">
                <input type="radio" name="reason_type" value="3">
                <span>
                    <strong>Lý do 3</strong>
                    Đồng phục mất/hỏng/rách do lỗi CNV, nguyên nhân không vì thực hiện công việc, hoặc yêu cầu cấp ngoài thời gian định mức sử dụng theo quy định.
                </span>
            </label>
        </div>

        <label class="ums-user-field-block">
            <span>Ghi rõ lý do chi tiết</span>
            <textarea name="reason_detail" rows="4" data-ums-reason-detail placeholder="Ví dụ: do men hồ, sự cố công việc, chuyển vị trí làm việc ngoài trời..."></textarea>
        </label>

        <div class="ums-payment-panel" data-ums-payment-panel hidden>
            <div class="ums-payment-context">
                <p>Trong trường hợp xin cấp đồng phục mới do đồng phục mất/hỏng/rách do lỗi CNV hoặc do nguyên nhân không vì thực hiện công việc hoặc yêu cầu cấp đồng phục ngoài thời gian định mức sử dụng theo quy định, CNV đồng ý lựa chọn một trong hai hình thức thanh toán sau:</p>
                <ul>
                    <li>(1) Thanh toán qua lương tháng phát sinh.</li>
                    <li>(2) Trực tiếp thanh toán cho Công ty bằng tiền mặt hoặc chuyển khoản.</li>
                </ul>
                <strong>Điều khoản ràng buộc đi kèm:</strong>
                <ul>
                    <li>Trường hợp CNV lựa chọn phương thức (2): Việc thanh toán được thực hiện trong thời hạn 30 ngày kể từ ngày được cấp phát đồng phục, nếu không thanh toán đúng thời hạn, việc thanh toán sẽ được chuyển sang phương thức (1).</li>
                    <li>Trường hợp lương tháng phát sinh thấp hơn chi phí đồng phục mà CNV phải thanh toán, CNV đồng ý thanh toán phần chênh lệch bằng tiền mặt hoặc chuyển khoản cho Công ty trong thời hạn 30 ngày kể từ ngày được cấp phát đồng phục.</li>
                </ul>
            </div>

            <span class="ums-user-label">Phương thức thanh toán chi phí</span>
            <div class="ums-payment-options">
                <label>
                    <input type="radio" name="payment_method" value="salary">
                    <span>Hình thức 1: Thanh toán qua lương tháng phát sinh.</span>
                </label>
                <label>
                    <input type="radio" name="payment_method" value="direct">
                    <span>Hình thức 2: Trực tiếp thanh toán cho Công ty bằng tiền mặt hoặc chuyển khoản.</span>
                </label>
            </div>
        </div>
    </section>

    <div class="ums-user-actions">
        <button type="button" class="ums-user-button" data-ums-submit-approval>Gửi duyệt</button>
        <p class="ums-user-muted" data-ums-user-message>Phiếu sẽ được gửi vào luồng duyệt sau khi bổ sung lớp dữ liệu phiếu yêu cầu.</p>
    </div>
</form>
