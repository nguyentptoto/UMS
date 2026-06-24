<?php
/**
 * Giao diện quản lý danh mục sản phẩm cha-con.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_editing = ! empty( $editing_category );
$page_url   = admin_url( 'admin.php?page=tvn-ums-product-categories' );
$grid_rows  = array();
$selected_parent_name = 'Tất cả danh mục cha';

if ( (int) $filters['parent_id'] > 0 ) {
    foreach ( $parent_categories as $parent_category ) {
        if ( (int) $parent_category['category_id'] === (int) $filters['parent_id'] ) {
            $selected_parent_name = $parent_category['category_name'];
            break;
        }
    }
}

$parent_lookup = array();
foreach ( $parent_categories as $parent_category ) {
    $parent_lookup[ (int) $parent_category['category_id'] ] = $parent_category;
}

foreach ( $categories as $category ) {
    $is_parent = (int) $category['parent_id'] === 0;
    $parent_name = $is_parent
        ? $category['category_name']
        : ( isset( $parent_lookup[ (int) $category['parent_id'] ] ) ? $parent_lookup[ (int) $category['parent_id'] ]['category_name'] : 'Chưa có danh mục cha' );

    $edit_url = add_query_arg(
        array(
            'page'             => 'tvn-ums-product-categories',
            'edit_category_id' => absint( $category['category_id'] ),
        ),
        admin_url( 'admin.php' )
    );
    $delete_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'      => 'ums_delete_product_category',
                'category_id' => absint( $category['category_id'] ),
            ),
            admin_url( 'admin-post.php' )
        ),
        'ums_delete_product_category_' . absint( $category['category_id'] )
    );

    $grid_rows[] = array(
        'parent_name'   => $parent_name,
        'category_name' => $is_parent ? 'Thông tin danh mục cha' : $category['category_name'],
        'category_type' => $is_parent ? 'Danh mục cha' : 'Danh mục con',
        'status'        => (int) $category['is_active'] === 1 ? 'Đang sử dụng' : 'Ngừng sử dụng',
        'actions'       => '<a href="' . esc_url( $edit_url . '#ums-product-category-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa danh mục ' . esc_attr( $category['category_name'] ) . '?">Xóa</a>',
    );
}

$grid_columns = array(
    array( 'text' => 'Danh mục cha', 'datafield' => 'parent_name', 'width' => '25%' ),
    array( 'text' => 'Tên hiển thị trong nhóm', 'datafield' => 'category_name', 'width' => '35%' ),
    array( 'text' => 'Loại', 'datafield' => 'category_type', 'width' => '15%' ),
    array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '20%' ),
    array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '20%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
$grid_groups = array( 'parent_name' );
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Danh mục Sản phẩm</h1>
    <a href="<?php echo esc_url( $page_url . '#ums-product-category-form' ); ?>" class="page-title-action">Thêm danh mục mới</a>
    <hr class="wp-header-end">

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2><?php echo esc_html( $selected_parent_name ); ?></h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-ums-product-categories">

            <label>
                <span class="screen-reader-text">Tìm danh mục</span>
                <input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Tìm tên danh mục">
            </label>

            <label>
                <span class="screen-reader-text">Lọc danh mục cha</span>
                <select name="parent_id">
                    <option value="" <?php selected( $filters['parent_id'], '' ); ?>>Tất cả danh mục cha</option>
                    <?php foreach ( $parent_categories as $parent ) : ?>
                        <option value="<?php echo esc_attr( $parent['category_id'] ); ?>" <?php selected( $filters['parent_id'], (string) $parent['category_id'] ); ?>>
                            <?php echo esc_html( $parent['category_name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="screen-reader-text">Lọc trạng thái</span>
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active" <?php selected( $filters['status'], 'active' ); ?>>Đang sử dụng</option>
                    <option value="inactive" <?php selected( $filters['status'], 'inactive' ); ?>>Ngừng sử dụng</option>
                </select>
            </label>

            <button type="submit" class="button">Lọc</button>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
        </form>

        <div
            id="ums-product-category-grid"
            class="ums-jqx-grid"
            data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
            data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
            data-groups="<?php echo esc_attr( wp_json_encode( $grid_groups ) ); ?>"
        ></div>
    </div>

    <div class="ums-panel" id="ums-product-category-form">
        <h2><?php echo $is_editing ? 'Cập nhật danh mục sản phẩm' : 'Thêm danh mục sản phẩm'; ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_save_product_category' ); ?>
            <input type="hidden" name="action" value="ums_save_product_category">
            <input type="hidden" name="ums_product_category[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
            <input type="hidden" name="ums_product_category[category_id]" value="<?php echo esc_attr( $form_values['category_id'] ); ?>">

            <div class="ums-form-grid">
                <label>
                    <span>Danh mục cha</span>
                    <select name="ums_product_category[parent_id]">
                        <option value="">Đây là danh mục cha</option>
                        <?php foreach ( $parent_categories as $parent ) : ?>
                            <?php if ( (int) $parent['category_id'] === (int) $form_values['category_id'] ) : ?>
                                <?php continue; ?>
                            <?php endif; ?>
                            <option value="<?php echo esc_attr( $parent['category_id'] ); ?>" <?php selected( (int) $form_values['parent_id'], (int) $parent['category_id'] ); ?>>
                                <?php echo esc_html( $parent['category_name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Tên danh mục <b>*</b></span>
                    <input type="text" name="ums_product_category[category_name]" value="<?php echo esc_attr( $form_values['category_name'] ); ?>" required>
                </label>
            </div>

            <fieldset class="ums-checkboxes">
                <legend>Trạng thái</legend>
                <label>
                    <input type="checkbox" name="ums_product_category[is_active]" value="1" <?php checked( (int) $form_values['is_active'], 1 ); ?>>
                    Đang sử dụng
                </label>
            </fieldset>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_editing ? 'Cập nhật danh mục' : 'Thêm danh mục'; ?>
                </button>
                <?php if ( $is_editing ) : ?>
                    <a href="<?php echo esc_url( $page_url . '#ums-product-category-form' ); ?>" class="button">Hủy sửa</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
