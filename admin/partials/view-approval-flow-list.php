<?php
/**
 * Giao diện quản lý chuỗi luồng duyệt động theo phòng ban.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_editing = ! empty( $editing_flow );
$page_url   = admin_url( 'admin.php?page=tvn-ums-approval-flows' );
$grid_rows  = array();

foreach ( $approval_flows as $flow ) {
    $edit_url = add_query_arg(
        array(
            'page'         => 'tvn-ums-approval-flows',
            'edit_flow_id' => absint( $flow['flow_id'] ),
        ),
        admin_url( 'admin.php' )
    );
    $delete_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'  => 'ums_delete_approval_flow',
                'flow_id' => absint( $flow['flow_id'] ),
            ),
            admin_url( 'admin-post.php' )
        ),
        'ums_delete_approval_flow_' . absint( $flow['flow_id'] )
    );

    $approver_ids = json_decode( $flow['approver_profile_ids'], true );
    $approver_ids = is_array( $approver_ids ) ? array_map( 'absint', $approver_ids ) : array();
    $approver_labels = array();
    foreach ( $approver_ids as $approver_id ) {
        $approver = UMS_DB_User::get_by_id( $approver_id );
        if ( $approver ) {
            $approver_labels[] = trim( $approver['employee_code'] . ' - ' . $approver['full_name'] );
        }
    }

    $grid_rows[] = array(
        'department_name' => $flow['department_name'] ?: '-',
        'step_order'      => (int) $flow['step_order'],
        'step_group'      => 'Bước ' . (int) $flow['step_order'] . ' - ' . $flow['step_name'],
        'step_name'       => $flow['step_name'],
        'approver'        => implode( ', ', $approver_labels ),
        'status'          => (int) $flow['is_active'] === 1 ? 'Đang sử dụng' : 'Ngừng sử dụng',
        'actions'         => '<a href="' . esc_url( $edit_url . '#ums-approval-flow-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa bước duyệt ' . esc_attr( $flow['step_name'] ) . '?">Xóa</a>',
    );
}

$grid_columns = array(
    array( 'text' => 'Phòng ban', 'datafield' => 'department_name', 'width' => '22%' ),
    array( 'text' => 'Thứ tự', 'datafield' => 'step_order', 'width' => '8%', 'cellsalign' => 'right' ),
    array( 'text' => 'Nhóm bước', 'datafield' => 'step_group', 'width' => '20%' ),
    array( 'text' => 'Tên bước duyệt', 'datafield' => 'step_name', 'width' => '22%' ),
    array( 'text' => 'Người duyệt', 'datafield' => 'approver', 'width' => '28%' ),
    array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '10%' ),
    array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '10%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
$grid_groups = array( 'department_name', 'step_group' );
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Luồng duyệt</h1>
    <a href="<?php echo esc_url( $page_url . '#ums-approval-flow-form' ); ?>" class="page-title-action">Thêm bước duyệt</a>
    <hr class="wp-header-end">

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2>Chuỗi luồng duyệt động</h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-ums-approval-flows">

            <label>
                <span class="screen-reader-text">Lọc phòng ban</span>
                <select name="department_id">
                    <option value="">Tất cả phòng ban</option>
                    <?php foreach ( $departments as $department ) : ?>
                        <option value="<?php echo esc_attr( $department['department_id'] ); ?>" <?php selected( $filters['department_id'], (string) $department['department_id'] ); ?>>
                            <?php echo esc_html( $department['department_name'] ); ?>
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
            id="ums-approval-flow-grid"
            class="ums-jqx-grid"
            data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
            data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
            data-groups="<?php echo esc_attr( wp_json_encode( $grid_groups ) ); ?>"
        ></div>
    </div>

    <div class="ums-panel" id="ums-approval-flow-form">
        <h2><?php echo $is_editing ? 'Cập nhật bước duyệt' : 'Thêm bước duyệt'; ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_save_approval_flow' ); ?>
            <input type="hidden" name="action" value="ums_save_approval_flow">
            <input type="hidden" name="ums_approval_flow[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
            <input type="hidden" name="ums_approval_flow[flow_id]" value="<?php echo esc_attr( $form_values['flow_id'] ); ?>">

            <div class="ums-form-grid">
                <label>
                    <span>Phòng ban <b>*</b></span>
                    <select name="ums_approval_flow[department_id]" required>
                        <option value="">Chọn phòng ban</option>
                        <?php foreach ( $departments as $department ) : ?>
                            <option value="<?php echo esc_attr( $department['department_id'] ); ?>" <?php selected( (int) $form_values['department_id'], (int) $department['department_id'] ); ?>>
                                <?php echo esc_html( $department['department_name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Thứ tự bước <b>*</b></span>
                    <input type="number" name="ums_approval_flow[step_order]" value="<?php echo esc_attr( $form_values['step_order'] ); ?>" min="1" step="1" required>
                </label>

                <label>
                    <span>Tên bước duyệt <b>*</b></span>
                    <input type="text" name="ums_approval_flow[step_name]" value="<?php echo esc_attr( $form_values['step_name'] ); ?>" placeholder="VD: Trưởng bộ phận, HCNS, Giám đốc..." required>
                </label>

                <label>
                    <span>Người duyệt <b>*</b></span>
                    <select name="ums_approval_flow[approver_profile_ids][]" multiple size="8" required>
                        <?php foreach ( $approvers as $approver ) : ?>
                            <option value="<?php echo esc_attr( $approver['profile_id'] ); ?>" <?php selected( in_array( (int) $approver['profile_id'], $form_values['approver_profile_ids'], true ) ); ?>>
                                <?php echo esc_html( $approver['employee_code'] . ' - ' . $approver['full_name'] . ' (' . $approver['department'] . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Giữ Ctrl để chọn nhiều người duyệt trong cùng một bước.</p>
                </label>
            </div>

            <fieldset class="ums-checkboxes">
                <legend>Trạng thái</legend>
                <label>
                    <input type="checkbox" name="ums_approval_flow[is_active]" value="1" <?php checked( (int) $form_values['is_active'], 1 ); ?>>
                    Đang sử dụng
                </label>
            </fieldset>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_editing ? 'Cập nhật bước duyệt' : 'Thêm bước duyệt'; ?>
                </button>
                <?php if ( $is_editing ) : ?>
                    <a href="<?php echo esc_url( $page_url . '#ums-approval-flow-form' ); ?>" class="button">Hủy sửa</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
