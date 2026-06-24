<?php
/**
 * Giao diện quản lý danh mục phòng ban.
 *
 * Các biến được chuẩn bị từ UMS_Admin::render_department_page():
 * $departments, $filters, $editing_department, $form_values, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_editing = ! empty( $editing_department );
$page_url   = admin_url( 'admin.php?page=tvn-ums-departments' );
$grid_rows  = array();

foreach ( $departments as $department ) {
    $edit_url = add_query_arg(
        array(
            'page'               => 'tvn-ums-departments',
            'edit_department_id' => absint( $department['department_id'] ),
        ),
        admin_url( 'admin.php' )
    );
    $delete_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'        => 'ums_delete_department',
                'department_id' => absint( $department['department_id'] ),
            ),
            admin_url( 'admin-post.php' )
        ),
        'ums_delete_department_' . absint( $department['department_id'] )
    );

    $grid_rows[] = array(
        'department_code' => $department['department_code'],
        'department_name' => $department['department_name'],
        'status'          => (int) $department['is_active'] === 1 ? 'Đang sử dụng' : 'Ngừng sử dụng',
        'actions'         => '<a href="' . esc_url( $edit_url . '#ums-department-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa phòng ban ' . esc_attr( $department['department_code'] ) . '? Chỉ nên xóa khi chưa có nhân sự thuộc phòng ban này.">Xóa</a>',
    );
}

$grid_columns = array(
    array( 'text' => 'Mã phòng ban', 'datafield' => 'department_code', 'width' => '25%' ),
    array( 'text' => 'Tên phòng ban', 'datafield' => 'department_name', 'width' => '35%' ),
    array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '20%' ),
    array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '20%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Phòng ban</h1>
    <a href="<?php echo esc_url( $page_url . '#ums-department-form' ); ?>" class="page-title-action">Thêm phòng ban mới</a>
    <hr class="wp-header-end">

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2>Danh sách phòng ban</h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-ums-departments">

            <label>
                <span class="screen-reader-text">Tìm phòng ban</span>
                <input
                    type="search"
                    name="s"
                    value="<?php echo esc_attr( $filters['search'] ); ?>"
                    placeholder="Tìm mã hoặc tên phòng ban"
                >
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
            id="ums-department-grid"
            class="ums-jqx-grid"
            data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
            data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
        ></div>
    </div>

    <div class="ums-panel" id="ums-department-form">
        <h2><?php echo $is_editing ? 'Cập nhật phòng ban' : 'Thêm phòng ban'; ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_save_department' ); ?>
            <input type="hidden" name="action" value="ums_save_department">
            <input type="hidden" name="ums_department[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
            <input type="hidden" name="ums_department[department_id]" value="<?php echo esc_attr( $form_values['department_id'] ); ?>">

            <div class="ums-form-grid">
                <label>
                    <span>Mã phòng ban <b>*</b></span>
                    <input type="text" name="ums_department[department_code]" value="<?php echo esc_attr( $form_values['department_code'] ); ?>" required>
                </label>

                <label>
                    <span>Tên phòng ban <b>*</b></span>
                    <input type="text" name="ums_department[department_name]" value="<?php echo esc_attr( $form_values['department_name'] ); ?>" required>
                </label>

            </div>

            <fieldset class="ums-checkboxes">
                <legend>Trạng thái</legend>
                <label>
                    <input type="checkbox" name="ums_department[is_active]" value="1" <?php checked( (int) $form_values['is_active'], 1 ); ?>>
                    Đang sử dụng
                </label>
            </fieldset>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_editing ? 'Cập nhật phòng ban' : 'Thêm phòng ban'; ?>
                </button>
                <?php if ( $is_editing ) : ?>
                    <a href="<?php echo esc_url( $page_url . '#ums-department-form' ); ?>" class="button">Hủy sửa</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
