<?php
/**
 * Giao diện quản lý danh mục sản phẩm và tổng kho.
 *
 * Các biến được chuẩn bị từ UMS_Admin::render_inventory_page():
 * $inventory, $filters, $category_tree, $child_categories, $editing_item, $form_values, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_editing = ! empty( $editing_item );
$page_url   = admin_url( 'admin.php?page=tvn-ums-inventory' );
$inventory_sections = array();
$size_order = array(
    'XXS' => 10,
    'XS'  => 20,
    'S'   => 30,
    'M'   => 40,
    'L'   => 50,
    'XL'  => 60,
    'XXL' => 70,
    '2XL' => 70,
    'XXXL'=> 80,
    '3XL' => 80,
    '4XL' => 90,
    '5XL' => 100,
);

foreach ( $inventory as $item ) {
    $parent_id   = ! empty( $item['parent_category_id'] ) ? absint( $item['parent_category_id'] ) : absint( $item['category_id'] );
    $section_key = $parent_id > 0 ? 'parent-' . $parent_id : 'uncategorized';
    $section_name = ! empty( $item['parent_category_name'] ) ? $item['parent_category_name'] : ( ! empty( $item['category_name'] ) ? $item['category_name'] : 'Chưa phân loại' );
    $category_name = ! empty( $item['item_variant'] ) ? $item['item_variant'] : ( ! empty( $item['category_name'] ) ? $item['category_name'] : $item['item_type'] );
    $variant        = trim( (string) $item['item_variant'] );
    $size           = trim( (string) $item['size'] );
    $size           = $size !== '' ? $size : 'Không size';
    $row_key        = implode( '|', array( absint( $item['category_id'] ), $variant ) );

    if ( ! isset( $inventory_sections[ $section_key ] ) ) {
        $inventory_sections[ $section_key ] = array(
            'name'  => $section_name,
            'sizes' => array(),
            'rows'  => array(),
        );
    }

    if ( ! isset( $inventory_sections[ $section_key ]['rows'][ $row_key ] ) ) {
        $label = $category_name;
        $inventory_sections[ $section_key ]['rows'][ $row_key ] = array(
            'label'  => $label,
            'items'  => array(),
            'prices' => array(),
            'total'  => 0,
        );
    }

    $edit_url = add_query_arg(
        array(
            'page'         => 'tvn-ums-inventory',
            'edit_item_id' => absint( $item['item_id'] ),
        ),
        admin_url( 'admin.php' )
    );
    $stock_qty = (int) $item['stock_qty'];
    $inventory_sections[ $section_key ]['sizes'][ $size ] = true;
    $inventory_sections[ $section_key ]['rows'][ $row_key ]['items'][ $size ] = array(
        'item_id'    => absint( $item['item_id'] ),
        'stock_qty'  => $stock_qty,
        'base_price' => (float) $item['base_price'],
        'edit_url'   => $edit_url . '#ums-inventory-form',
    );
    $inventory_sections[ $section_key ]['rows'][ $row_key ]['prices'][] = (float) $item['base_price'];
    $inventory_sections[ $section_key ]['rows'][ $row_key ]['total'] += $stock_qty;
}

foreach ( $inventory_sections as &$section ) {
    $sizes = array_keys( $section['sizes'] );
    usort(
        $sizes,
        function( $left, $right ) use ( $size_order ) {
            $left_key  = strtoupper( trim( (string) $left ) );
            $right_key = strtoupper( trim( (string) $right ) );

            if ( isset( $size_order[ $left_key ], $size_order[ $right_key ] ) ) {
                return $size_order[ $left_key ] <=> $size_order[ $right_key ];
            }
            if ( is_numeric( $left_key ) && is_numeric( $right_key ) ) {
                return (float) $left_key <=> (float) $right_key;
            }
            if ( isset( $size_order[ $left_key ] ) ) {
                return -1;
            }
            if ( isset( $size_order[ $right_key ] ) ) {
                return 1;
            }

            return strnatcasecmp( $left_key, $right_key );
        }
    );
    $section['sizes'] = $sizes;
    uasort(
        $section['rows'],
        function( $left, $right ) {
            return strnatcasecmp( $left['label'], $right['label'] );
        }
    );
}
unset( $section );
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Sản phẩm & Tổng kho</h1>
    <hr class="wp-header-end">

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2>Tổng hợp tồn kho theo danh mục</h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-ums-inventory">

            <label>
                <span class="screen-reader-text">Tìm sản phẩm</span>
                <input
                    type="search"
                    name="s"
                    value="<?php echo esc_attr( $filters['search'] ); ?>"
                    placeholder="Tìm danh mục, tên sản phẩm, size"
                >
            </label>

            <label>
                <span class="screen-reader-text">Lọc danh mục cha</span>
                <select name="parent_id">
                    <option value="">Tất cả danh mục cha</option>
                    <?php foreach ( $category_tree as $parent ) : ?>
                        <option value="<?php echo esc_attr( $parent['category_id'] ); ?>" <?php selected( $filters['parent_id'], (string) $parent['category_id'] ); ?>>
                            <?php echo esc_html( $parent['category_name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="screen-reader-text">Lọc tồn kho</span>
                <select name="stock">
                    <option value="">Tất cả tồn kho</option>
                    <option value="available" <?php selected( $filters['stock'], 'available' ); ?>>Còn hàng</option>
                    <option value="low" <?php selected( $filters['stock'], 'low' ); ?>>Tồn thấp</option>
                    <option value="out" <?php selected( $filters['stock'], 'out' ); ?>>Hết hàng</option>
                </select>
            </label>

            <button type="submit" class="button">Lọc</button>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
        </form>

        <?php if ( empty( $inventory_sections ) ) : ?>
            <div class="ums-empty-state">Không có dữ liệu tồn kho phù hợp với bộ lọc.</div>
        <?php else : ?>
            <?php $section_number = 0; ?>
            <?php foreach ( $inventory_sections as $section ) : ?>
                <?php
                $section_number++;
                $section_rows         = array();
                $size_column_width    = max( 6, 38 / max( 1, count( $section['sizes'] ) ) );
                $section_columns      = array(
                    array( 'text' => 'Loại sản phẩm', 'datafield' => 'product_label', 'width' => '38%' ),
                );

                foreach ( $section['sizes'] as $size_index => $size ) {
                    $section_columns[] = array(
                        'text'          => $size,
                        'datafield'     => 'size_' . $size_index,
                        'width'         => $size_column_width . '%',
                        'cellsalign'    => 'center',
                        'filterable'    => false,
                        'cellsrenderer' => 'html',
                    );
                }

                $section_columns[] = array( 'text' => 'Tổng', 'datafield' => 'total', 'width' => '10%', 'cellsalign' => 'right' );
                $section_columns[] = array( 'text' => 'Đơn giá', 'datafield' => 'base_price', 'width' => '14%', 'cellsalign' => 'right' );

                foreach ( $section['rows'] as $row ) {
                    $prices = array_values( array_unique( array_map( 'floatval', $row['prices'] ) ) );
                    sort( $prices, SORT_NUMERIC );
                    $price_label = count( $prices ) > 1
                        ? number_format_i18n( reset( $prices ), 0 ) . ' - ' . number_format_i18n( end( $prices ), 0 )
                        : number_format_i18n( reset( $prices ), 0 );
                    $grid_row = array(
                        'product_label' => $row['label'],
                        'total'         => $row['total'],
                        'base_price'    => $price_label,
                    );
                    foreach ( $section['sizes'] as $size_index => $size ) {
                        $stock_item = isset( $row['items'][ $size ] ) ? $row['items'][ $size ] : null;
                        $grid_row[ 'size_' . $size_index ] = $stock_item
                            ? '<a href="' . esc_url( $stock_item['edit_url'] ) . '" title="Sửa size ' . esc_attr( $size ) . '">' . esc_html( $stock_item['stock_qty'] ) . '</a>'
                            : '-';
                    }

                    $section_rows[] = $grid_row;
                }
                ?>
                <h3><?php echo esc_html( $section_number . '. Tồn kho ' . $section['name'] ); ?></h3>
                <div
                    id="ums-inventory-grid-<?php echo esc_attr( $section_number ); ?>"
                    class="ums-jqx-grid"
                    data-rows="<?php echo esc_attr( wp_json_encode( $section_rows ) ); ?>"
                    data-columns="<?php echo esc_attr( wp_json_encode( $section_columns ) ); ?>"
                ></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="ums-panel" id="ums-manual-inventory-out">
        <h2>Xuất kho chủ động</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_manual_inventory_out' ); ?>
            <input type="hidden" name="action" value="ums_manual_inventory_out">

            <div class="ums-form-grid">
                <label>
                    <span>Sản phẩm <b>*</b></span>
                    <select name="ums_manual_out[item_id]" required>
                        <option value="">Chọn sản phẩm còn tồn</option>
                        <?php foreach ( $available_items as $item ) : ?>
                            <?php
                            $item_label = trim( ( $item['parent_category_name'] ?: '' ) . ' / ' . ( $item['category_name'] ?: $item['item_type'] ), ' /' );
                            if ( ! empty( $item['item_variant'] ) ) {
                                $item_label .= ' - ' . $item['item_variant'];
                            }
                            $item_label .= ' - Size ' . $item['size'] . ' - Tồn ' . (int) $item['stock_qty'];
                            ?>
                            <option value="<?php echo esc_attr( $item['item_id'] ); ?>">
                                <?php echo esc_html( $item_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Người nhận <b>*</b></span>
                    <select name="ums_manual_out[target_user_id]" required>
                        <option value="">Chọn người nhận để kiểm tra định mức</option>
                        <?php foreach ( $recipient_options as $recipient ) : ?>
                            <option value="<?php echo esc_attr( $recipient['user_id'] ); ?>">
                                <?php echo esc_html( $recipient['employee_code'] . ' - ' . $recipient['full_name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Số lượng xuất <b>*</b></span>
                    <input type="number" name="ums_manual_out[quantity]" value="1" min="1" step="1" required>
                </label>

                <label class="ums-field-wide">
                    <span>Ghi chú</span>
                    <textarea name="ums_manual_out[note]" rows="3" placeholder="Ví dụ: xuất bổ sung, cấp trực tiếp, điều chuyển nội bộ..."></textarea>
                </label>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary">Ghi nhận xuất kho</button>
            </p>
        </form>
    </div>

    <div class="ums-panel" id="ums-inventory-form">
        <h2><?php echo $is_editing ? 'Cập nhật sản phẩm & tồn kho' : 'Thêm sản phẩm & tồn kho'; ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_save_inventory_item' ); ?>
            <input type="hidden" name="action" value="ums_save_inventory_item">
            <input type="hidden" name="ums_inventory[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
            <input type="hidden" name="ums_inventory[item_id]" value="<?php echo esc_attr( $form_values['item_id'] ); ?>">

            <div class="ums-form-grid">
                <label>
                    <span>Danh mục cha <b>*</b></span>
                    <select name="ums_inventory[category_id]" required>
                        <option value="">Chọn danh mục cha</option>
                        <?php foreach ( $category_tree as $category ) : ?>
                            <option value="<?php echo esc_attr( $category['category_id'] ); ?>" <?php selected( (int) $form_values['category_id'], (int) $category['category_id'] ); ?>>
                                <?php echo esc_html( $category['category_name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( empty( $category_tree ) ) : ?>
                        <p class="description">
                            Hãy tạo danh mục cha tại
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tvn-ums-product-categories' ) ); ?>">Danh mục SP</a>
                            trước khi thêm sản phẩm.
                        </p>
                    <?php endif; ?>
                </label>

                <label>
                    <span>Tên sản phẩm <b>*</b></span>
                    <input type="text" name="ums_inventory[item_variant]" value="<?php echo esc_attr( $form_values['item_variant'] ); ?>" placeholder="Áo phông xám, giày KPR 010..." required>
                </label>

                <label>
                    <span>Size <b>*</b></span>
                    <input type="text" name="ums_inventory[size]" value="<?php echo esc_attr( $form_values['size'] ); ?>" required>
                </label>

                <input type="hidden" name="ums_inventory[color_code]" value="">

                <label>
                    <span>Số lượng tồn kho <b>*</b></span>
                    <input type="number" name="ums_inventory[stock_qty]" value="<?php echo esc_attr( $form_values['stock_qty'] ); ?>" min="0" step="1" required>
                </label>

                <label>
                    <span>Đơn giá gốc <b>*</b></span>
                    <input type="text" name="ums_inventory[base_price]" value="<?php echo esc_attr( $form_values['base_price'] ); ?>" inputmode="decimal" required>
                </label>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_editing ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm'; ?>
                </button>
                <?php if ( $is_editing ) : ?>
                    <a href="<?php echo esc_url( $page_url . '#ums-inventory-form' ); ?>" class="button">Hủy sửa</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
