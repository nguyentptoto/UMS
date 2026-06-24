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
$grid_rows  = array();

foreach ( $inventory as $item ) {
    $stock_qty = (int) $item['stock_qty'];
    $edit_url = add_query_arg(
        array(
            'page'         => 'tvn-ums-inventory',
            'edit_item_id' => absint( $item['item_id'] ),
        ),
        admin_url( 'admin.php' )
    );
    $delete_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'  => 'ums_delete_inventory_item',
                'item_id' => absint( $item['item_id'] ),
            ),
            admin_url( 'admin-post.php' )
        ),
        'ums_delete_inventory_item_' . absint( $item['item_id'] )
    );

    if ( $stock_qty <= 0 ) {
        $stock_status = 'Hết hàng';
    } elseif ( $stock_qty <= 10 ) {
        $stock_status = 'Tồn thấp';
    } else {
        $stock_status = 'Còn hàng';
    }

    $grid_rows[] = array(
        'parent_category_name' => $item['parent_category_name'] ?: '-',
        'category_name'        => $item['category_name'] ?: $item['item_type'],
        'item_variant'         => $item['item_variant'] ?: '-',
        'size'                 => $item['size'],
        'color_code'           => $item['color_code'],
        'stock_qty'            => $stock_qty,
        'stock_status'         => $stock_status,
        'base_price'           => number_format_i18n( (float) $item['base_price'], 0 ),
        'actions'              => '<a href="' . esc_url( $edit_url . '#ums-inventory-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa sản phẩm ' . esc_attr( $item['category_name'] ?: $item['item_type'] ) . ' ' . esc_attr( $item['item_variant'] ) . '?">Xóa</a>',
    );
}

$grid_columns = array(
    array( 'text' => 'Danh mục cha', 'datafield' => 'parent_category_name', 'width' => '16%' ),
    array( 'text' => 'Danh mục con', 'datafield' => 'category_name', 'width' => '17%' ),
    array( 'text' => 'Biến thể', 'datafield' => 'item_variant', 'width' => '14%' ),
    array( 'text' => 'Size', 'datafield' => 'size', 'width' => '9%' ),
    array( 'text' => 'Màu/Mã màu', 'datafield' => 'color_code', 'width' => '12%' ),
    array( 'text' => 'Tồn kho', 'datafield' => 'stock_qty', 'width' => '9%', 'cellsalign' => 'right' ),
    array( 'text' => 'Trạng thái', 'datafield' => 'stock_status', 'width' => '10%' ),
    array( 'text' => 'Đơn giá', 'datafield' => 'base_price', 'width' => '10%', 'cellsalign' => 'right' ),
    array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '12%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Sản phẩm & Tổng kho</h1>
    <a href="<?php echo esc_url( $page_url . '#ums-inventory-form' ); ?>" class="page-title-action">Thêm sản phẩm mới</a>
    <hr class="wp-header-end">

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2>Danh mục sản phẩm & tổng kho</h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-ums-inventory">

            <label>
                <span class="screen-reader-text">Tìm sản phẩm</span>
                <input
                    type="search"
                    name="s"
                    value="<?php echo esc_attr( $filters['search'] ); ?>"
                    placeholder="Tìm danh mục, biến thể, size, màu"
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
                <span class="screen-reader-text">Lọc danh mục con</span>
                <select name="category_id">
                    <option value="">Tất cả danh mục con</option>
                    <?php foreach ( $child_categories as $category ) : ?>
                        <option value="<?php echo esc_attr( $category['category_id'] ); ?>" <?php selected( $filters['category_id'], (string) $category['category_id'] ); ?>>
                            <?php echo esc_html( $category['parent_name'] . ' / ' . $category['category_name'] ); ?>
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

        <div
            id="ums-inventory-grid"
            class="ums-jqx-grid"
            data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
            data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
        ></div>
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
                    <span>Danh mục con <b>*</b></span>
                    <select name="ums_inventory[category_id]" required>
                        <option value="">Chọn danh mục con</option>
                        <?php foreach ( $child_categories as $category ) : ?>
                            <option value="<?php echo esc_attr( $category['category_id'] ); ?>" <?php selected( (int) $form_values['category_id'], (int) $category['category_id'] ); ?>>
                                <?php echo esc_html( $category['parent_name'] . ' / ' . $category['category_name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( empty( $child_categories ) ) : ?>
                        <p class="description">
                            Hãy tạo danh mục cha-con tại
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tvn-ums-product-categories' ) ); ?>">Danh mục SP</a>
                            trước khi thêm sản phẩm.
                        </p>
                    <?php endif; ?>
                </label>

                <label>
                    <span>Biến thể</span>
                    <input type="text" name="ums_inventory[item_variant]" value="<?php echo esc_attr( $form_values['item_variant'] ); ?>" placeholder="Cộc tay, dài tay, TS5511...">
                </label>

                <label>
                    <span>Size <b>*</b></span>
                    <input type="text" name="ums_inventory[size]" value="<?php echo esc_attr( $form_values['size'] ); ?>" required>
                </label>

                <label>
                    <span>Màu/Mã màu</span>
                    <input type="text" name="ums_inventory[color_code]" value="<?php echo esc_attr( $form_values['color_code'] ); ?>">
                </label>

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
