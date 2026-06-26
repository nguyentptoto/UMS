<?php
/**
 * Inventory movement history view.
 *
 * Variables: $movements, $filters.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$page_url = admin_url( 'admin.php?page=tvn-ums-inventory-movements' );
$type_labels = array(
    'in'          => 'Nhập kho',
    'out'         => 'Xuất kho',
    'adjust'      => 'Điều chỉnh',
    'request_out' => 'Yêu cầu xuất',
);
$grid_rows = array();

foreach ( $movements as $movement ) {
    $item_name = trim( ( $movement['parent_category_name'] ?: '' ) . ' / ' . ( $movement['category_name'] ?: '' ), ' /' );
    $variant = trim( (string) $movement['item_variant'] );
    if ( $variant !== '' ) {
        $item_name .= ' - ' . $variant;
    }

    $grid_rows[] = array(
        'created_at'     => mysql2date( 'd/m/Y H:i', $movement['created_at'] ),
        'movement_type'  => isset( $type_labels[ $movement['movement_type'] ] ) ? $type_labels[ $movement['movement_type'] ] : $movement['movement_type'],
        'request_id'     => ! empty( $movement['request_id'] ) ? '#' . (int) $movement['request_id'] : '-',
        'item_name'      => $item_name !== '' ? $item_name : 'Sản phẩm #' . (int) $movement['item_id'],
        'size'           => $movement['size'] ?: '-',
        'quantity'       => (int) $movement['quantity'],
        'before_qty'     => $movement['before_qty'] !== null ? (int) $movement['before_qty'] : '-',
        'after_qty'      => $movement['after_qty'] !== null ? (int) $movement['after_qty'] : '-',
        'unit_price'     => number_format_i18n( (float) $movement['unit_price'], 0 ),
        'total_price'    => number_format_i18n( (float) $movement['total_price'], 0 ),
        'actor_login'    => $movement['actor_login'] ?: '-',
        'target_login'   => $movement['target_login'] ?: '-',
        'note'           => $movement['note'] ?: '-',
    );
}

$grid_columns = array(
    array( 'text' => 'Thời gian', 'datafield' => 'created_at', 'width' => '11%' ),
    array( 'text' => 'Loại', 'datafield' => 'movement_type', 'width' => '9%' ),
    array( 'text' => 'Phiếu', 'datafield' => 'request_id', 'width' => '7%' ),
    array( 'text' => 'Sản phẩm', 'datafield' => 'item_name', 'width' => '22%' ),
    array( 'text' => 'Size', 'datafield' => 'size', 'width' => '7%' ),
    array( 'text' => 'SL', 'datafield' => 'quantity', 'width' => '6%', 'cellsalign' => 'right' ),
    array( 'text' => 'Trước', 'datafield' => 'before_qty', 'width' => '6%', 'cellsalign' => 'right' ),
    array( 'text' => 'Sau', 'datafield' => 'after_qty', 'width' => '6%', 'cellsalign' => 'right' ),
    array( 'text' => 'Đơn giá', 'datafield' => 'unit_price', 'width' => '8%', 'cellsalign' => 'right' ),
    array( 'text' => 'Thành tiền', 'datafield' => 'total_price', 'width' => '9%', 'cellsalign' => 'right' ),
    array( 'text' => 'Người thao tác', 'datafield' => 'actor_login', 'width' => '10%' ),
    array( 'text' => 'Người nhận', 'datafield' => 'target_login', 'width' => '10%' ),
    array( 'text' => 'Ghi chú', 'datafield' => 'note', 'width' => '20%' ),
);
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Lịch sử nhập xuất kho</h1>
    <hr class="wp-header-end">

    <div class="ums-panel">
        <h2>Lọc lịch sử kho</h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-ums-inventory-movements">

            <label>
                <span class="screen-reader-text">Tìm kiếm</span>
                <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Tìm sản phẩm, user, ghi chú">
            </label>

            <label>
                <span class="screen-reader-text">Loại phát sinh</span>
                <select name="movement_type">
                    <option value="">Tất cả loại</option>
                    <?php foreach ( $type_labels as $type => $label ) : ?>
                        <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['movement_type'], $type ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="screen-reader-text">Từ ngày</span>
                <input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ); ?>">
            </label>

            <label>
                <span class="screen-reader-text">Đến ngày</span>
                <input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ); ?>">
            </label>

            <button type="submit" class="button">Lọc</button>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
        </form>
    </div>

    <div class="ums-panel">
        <h2>Chi tiết nhập xuất kho</h2>
        <div
            id="ums-inventory-movement-grid"
            class="ums-jqx-grid"
            data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
            data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
        ></div>
    </div>
</div>
