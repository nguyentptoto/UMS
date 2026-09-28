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
$editing_request = isset( $editing_request ) && is_array( $editing_request ) ? $editing_request : null;
$editing_details = $editing_request && ! empty( $editing_request['details'] ) && is_array( $editing_request['details'] ) ? $editing_request['details'] : array();

$get_inventory_product_data = static function ( $item ) {
    $category_id = isset( $item['category_id'] ) ? (int) $item['category_id'] : 0;
    $variant     = isset( $item['item_variant'] ) ? trim( (string) $item['item_variant'] ) : '';
    $label       = $variant;

    if ( $label === '' ) {
        $label = ! empty( $item['category_name'] ) ? trim( (string) $item['category_name'] ) : trim( (string) ( $item['item_type'] ?? '' ) );
    }

    return array(
        'key'         => $category_id . ':' . md5( $variant !== '' ? $variant : $label ),
        'category_id' => $category_id,
        'parent_id'   => ! empty( $item['parent_category_id'] ) ? (int) $item['parent_category_id'] : $category_id,
        'variant'     => $variant,
        'label'       => $label,
    );
};

$inventory_products = array();
foreach ( $inventory_items as $inventory_item ) {
    $product = $get_inventory_product_data( $inventory_item );
    if ( $product['category_id'] > 0 && $product['label'] !== '' ) {
        $inventory_products[ $product['key'] ] = $product;
    }
}

uasort(
    $inventory_products,
    static function ( $left, $right ) {
        return strnatcasecmp( $left['label'], $right['label'] );
    }
);

$selected_target_user_id = $editing_request ? (int) $editing_request['target_user_id'] : (int) $default_target['user_id'];
foreach ( $teammates as $teammate ) {
    if ( (int) $teammate['user_id'] === $selected_target_user_id ) {
        $default_target = $teammate;
        break;
    }
}

$render_request_item_row = function ( $index, $is_template = false, $selected_detail = array() ) use ( $category_tree, $inventory_items, $inventory_products, $get_inventory_product_data ) {
    $prefix               = 'request_items[' . $index . ']';
    $row_classes          = 'ums-request-item';
    $selected_parent_id   = isset( $selected_detail['parent_id'] ) ? (int) $selected_detail['parent_id'] : 0;
    $selected_item_id     = isset( $selected_detail['item_id'] ) ? (int) $selected_detail['item_id'] : 0;
    $selected_quantity    = isset( $selected_detail['quantity'] ) ? max( 1, (int) $selected_detail['quantity'] ) : 1;
    $selected_price       = isset( $selected_detail['price_at_request'] ) ? (float) $selected_detail['price_at_request'] : 0;
    $selected_unit_price  = $selected_quantity > 0 ? $selected_price / $selected_quantity : 0;
    $selected_product_key = '';
    foreach ( $inventory_items as $inventory_item ) {
        if ( (int) $inventory_item['item_id'] === $selected_item_id ) {
            $selected_product_key = $get_inventory_product_data( $inventory_item )['key'];
            break;
        }
    }
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
                    <option value="">Chọn loại đồng phục</option>
                    <?php foreach ( $category_tree as $parent ) : ?>
                        <option value="<?php echo esc_attr( $parent['category_id'] ); ?>" <?php selected( $selected_parent_id, (int) $parent['category_id'] ); ?>>
                            <?php echo esc_html( $parent['category_name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Loại sản phẩm</span>
                <select name="<?php echo esc_attr( $prefix ); ?>[product_key]" data-ums-product-type required>
                    <option value="">Chọn loại sản phẩm</option>
                    <?php foreach ( $inventory_products as $product ) : ?>
                        <option
                            value="<?php echo esc_attr( $product['key'] ); ?>"
                            data-parent-id="<?php echo esc_attr( $product['parent_id'] ); ?>"
                            data-category-id="<?php echo esc_attr( $product['category_id'] ); ?>"
                            data-variant="<?php echo esc_attr( $product['variant'] ); ?>"
                            <?php selected( $selected_product_key, $product['key'] ); ?>
                            hidden
                        >
                            <?php echo esc_html( $product['label'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>Size</span>
                <select name="<?php echo esc_attr( $prefix ); ?>[size]" data-ums-size-select required>
                    <option value="">Chọn size</option>
                    <?php foreach ( $inventory_items as $item ) : ?>
                        <?php
                        $product_data = $get_inventory_product_data( $item );
                        $size_label   = $item['size'] . ' - Tồn ' . (int) $item['stock_qty'];
                        ?>
                        <option
                            value="<?php echo esc_attr( $item['size'] ); ?>"
                            data-product-key="<?php echo esc_attr( $product_data['key'] ); ?>"
                            data-category-id="<?php echo esc_attr( $item['category_id'] ); ?>"
                            data-inventory-id="<?php echo esc_attr( $item['item_id'] ); ?>"
                            data-price="<?php echo esc_attr( number_format( (float) $item['base_price'], 0, '.', '' ) ); ?>"
                            data-variant="<?php echo esc_attr( $item['item_variant'] ); ?>"
                            <?php selected( $selected_item_id, (int) $item['item_id'] ); ?>
                            hidden
                        >
                            <?php echo esc_html( $size_label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span>SL</span>
                <input type="number" name="<?php echo esc_attr( $prefix ); ?>[quantity]" min="1" step="1" value="<?php echo esc_attr( $selected_quantity ); ?>" data-ums-quantity-input required>
            </label>

            <label>
                <span>Giá</span>
                <input type="text" name="<?php echo esc_attr( $prefix ); ?>[price]" value="<?php echo esc_attr( $selected_price > 0 ? number_format( $selected_price, 0, '.', '' ) : '' ); ?>" data-ums-inventory-field="price" inputmode="decimal" placeholder="Tự tính theo số lượng" readonly>
            </label>

            <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[inventory_item_id]" value="<?php echo esc_attr( $selected_item_id ); ?>" data-ums-inventory-field="inventory_id">
            <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[item_variant]" value="<?php echo esc_attr( isset( $selected_detail['item_variant'] ) ? $selected_detail['item_variant'] : '' ); ?>" data-ums-inventory-field="variant">
            <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[unit_price]" value="<?php echo esc_attr( $selected_unit_price > 0 ? number_format( $selected_unit_price, 0, '.', '' ) : '' ); ?>" data-ums-inventory-field="unit_price">
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

<form class="ums-request-page" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-ums-user-form>
    <?php wp_nonce_field( 'ums_submit_uniform_request' ); ?>
    <input type="hidden" name="action" value="ums_submit_uniform_request">
    <input type="hidden" name="portal_url" value="<?php echo esc_url( $portal_url ); ?>">
    <?php if ( $editing_request ) : ?>
        <input type="hidden" name="request_id" value="<?php echo esc_attr( $editing_request['request_id'] ); ?>">
    <?php endif; ?>
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
                <p>Chọn nhóm đồng phục, loại sản phẩm và size; giá sẽ tự tính theo đơn giá hệ thống và số lượng.</p>
            </div>
            <button type="button" class="ums-user-button ums-user-button-light" data-ums-add-item>Thêm đồng phục</button>
        </div>

        <?php if ( empty( $category_tree ) || empty( $inventory_items ) ) : ?>
            <div class="ums-user-empty-inline">
                Chưa có danh mục sản phẩm hoặc sản phẩm tồn kho khả dụng để tạo yêu cầu.
            </div>
        <?php endif; ?>

        <div class="ums-request-items" data-ums-request-items>
            <?php if ( ! empty( $editing_details ) ) : ?>
                <?php foreach ( $editing_details as $detail_index => $detail ) : ?>
                    <?php $render_request_item_row( $detail_index, false, $detail ); ?>
                <?php endforeach; ?>
            <?php else : ?>
                <?php $render_request_item_row( 0 ); ?>
            <?php endif; ?>
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
                <input type="radio" name="reason_type" value="1" <?php checked( ! $editing_request || (int) $editing_request['reason_type'] === 1 ); ?>>
                <span>
                    <strong>Lý do 1</strong>
                    Do thay đổi vị trí công việc: chuyển công việc, bộ phận, vị trí, làm việc ngoài trời...
                </span>
            </label>

            <label class="ums-reason-option">
                <input type="radio" name="reason_type" value="2" <?php checked( $editing_request && (int) $editing_request['reason_type'] === 2 ); ?>>
                <span>
                    <strong>Lý do 2</strong>
                    Đồng phục rách/hỏng/bẩn do nguyên nhân trực tiếp từ việc thực thi công việc đảm nhiệm.
                </span>
            </label>

            <label class="ums-reason-option">
                <input type="radio" name="reason_type" value="3" <?php checked( $editing_request && (int) $editing_request['reason_type'] === 3 ); ?>>
                <span>
                    <strong>Lý do 3</strong>
                    Đồng phục mất/hỏng/rách do lỗi CNV, nguyên nhân không vì thực hiện công việc, hoặc yêu cầu cấp ngoài thời gian định mức sử dụng theo quy định.
                </span>
            </label>
        </div>

        <label class="ums-user-field-block">
            <span>Ghi rõ lý do chi tiết</span>
            <textarea name="reason_detail" rows="4" data-ums-reason-detail placeholder="Ví dụ: do men hồ, sự cố công việc, chuyển vị trí làm việc ngoài trời..."><?php echo esc_textarea( $editing_request ? $editing_request['reason_detail'] : '' ); ?></textarea>
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
                    <input type="radio" name="payment_method" value="salary" <?php checked( $editing_request && (int) $editing_request['payment_method'] === 1 ); ?>>
                    <span>Hình thức 1: Thanh toán qua lương tháng phát sinh.</span>
                </label>
                <label>
                    <input type="radio" name="payment_method" value="direct" <?php checked( $editing_request && (int) $editing_request['payment_method'] === 2 ); ?>>
                    <span>Hình thức 2: Trực tiếp thanh toán cho Công ty bằng tiền mặt hoặc chuyển khoản.</span>
                </label>
            </div>
        </div>
    </section>

    <?php
    $signature_request = $editing_request;
    $signature_profile = $profile;
    include UMS_PLUGIN_DIR . 'user/partials/components/approval-signature-grid.php';
    ?>

    <div class="ums-user-actions">
        <button type="submit" class="ums-user-button" data-ums-submit-approval><?php echo $editing_request ? 'Cập nhật phiếu' : 'Gửi duyệt'; ?></button>
        <p class="ums-user-muted" data-ums-user-message>Phiếu sẽ được lưu và chuyển theo đúng bước duyệt hiện tại của phòng ban.</p>
    </div>
</form>
