<?php
/**
 * Giao diện quản lý hồ sơ nhân sự phía Admin.
 *
 * Các biến được chuẩn bị từ UMS_Admin::render_user_list_page():
 * $users, $filters, $departments, $editing_user, $form_values, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_editing = ! empty( $editing_user );
$page_url   = admin_url( 'admin.php?page=tvn-uniform-management' );
$grid_rows  = array();
$sync_user_ids = array();

foreach ( $users as $user ) {
    $account_status = ! empty( $user['user_status'] ) ? 'inactive' : 'active';
    if ( ! empty( $user['user_id'] ) ) {
        $sync_user_ids[] = absint( $user['user_id'] );
    }
    $edit_url = add_query_arg(
        array(
            'page'            => 'tvn-uniform-management',
            'edit_profile_id' => absint( $user['profile_id'] ),
        ),
        admin_url( 'admin.php' )
    );
    $delete_url = wp_nonce_url(
        add_query_arg(
            array(
                'action'     => 'ums_delete_user_profile',
                'profile_id' => absint( $user['profile_id'] ),
            ),
            admin_url( 'admin-post.php' )
        ),
        'ums_delete_user_profile_' . absint( $user['profile_id'] )
    );

    $grid_rows[] = array(
        'user_id'          => absint( $user['user_id'] ),
        'employee_code'    => $user['employee_code'],
        'full_name'        => $user['full_name'],
        'gender'           => $user['gender'],
        'department'       => $user['department'],
        'job_position'     => $user['job_position'],
        'factory_location' => $user['factory_location'],
        'contract_type'    => $user['contract_type'],
        'date_joined'      => mysql2date( 'd/m/Y', $user['date_joined'] ),
        'account_status'   => $account_status === 'inactive' ? 'Inactive' : 'Active',
        'status'           => empty( $user['resignation_date'] ) ? 'Đang làm việc' : 'Nghỉ từ ' . mysql2date( 'd/m/Y', $user['resignation_date'] ),
        'actions'          => '<a href="' . esc_url( $edit_url . '#ums-profile-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa hồ sơ ' . esc_attr( $user['employee_code'] ) . '?">Xóa</a>',
    );
}

$grid_columns = array(
    array( 'text' => 'Mã NV', 'datafield' => 'employee_code', 'width' => '10%' ),
    array( 'text' => 'Họ và tên', 'datafield' => 'full_name', 'width' => '16%' ),
    array( 'text' => 'Giới tính', 'datafield' => 'gender', 'width' => '8%' ),
    array( 'text' => 'Phòng ban', 'datafield' => 'department', 'width' => '12%' ),
    array( 'text' => 'Chức danh', 'datafield' => 'job_position', 'width' => '12%' ),
    array( 'text' => 'Nhà máy', 'datafield' => 'factory_location', 'width' => '10%' ),
    array( 'text' => 'Hợp đồng', 'datafield' => 'contract_type', 'width' => '12%' ),
    array( 'text' => 'Ngày vào', 'datafield' => 'date_joined', 'width' => '9%' ),
    array( 'text' => 'Tài khoản', 'datafield' => 'account_status', 'width' => '9%' ),
    array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '11%' ),
    array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '10%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Hồ sơ Nhân sự</h1>
    <a href="<?php echo esc_url( $page_url . '#ums-profile-form' ); ?>" class="page-title-action">Thêm nhân viên mới</a>
    <hr class="wp-header-end">

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2>Danh sách hồ sơ</h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-uniform-management">

            <label>
                <span class="screen-reader-text">Tìm nhân sự</span>
                <input
                    type="search"
                    name="s"
                    value="<?php echo esc_attr( $filters['search'] ); ?>"
                    placeholder="Tìm mã NV, họ tên, chức danh"
                >
            </label>

            <label>
                <span class="screen-reader-text">Lọc phòng ban</span>
                <select name="department">
                    <option value="">Tất cả phòng ban</option>
                    <?php foreach ( $departments as $department ) : ?>
                        <option value="<?php echo esc_attr( $department ); ?>" <?php selected( $filters['department'], $department ); ?>>
                            <?php echo esc_html( $department ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="screen-reader-text">Lọc trạng thái</span>
                <select name="status">
                    <option value="">Tất cả trạng thái</option>
                    <option value="active" <?php selected( $filters['status'], 'active' ); ?>>Đang làm việc</option>
                    <option value="resigned" <?php selected( $filters['status'], 'resigned' ); ?>>Đã nghỉ việc</option>
                </select>
            </label>

            <button type="submit" class="button">Lọc</button>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
            <button
                type="button"
                class="button ums-sync-password-button"
                data-user-ids="<?php echo esc_attr( wp_json_encode( array_values( array_unique( $sync_user_ids ) ) ) ); ?>"
            >
                Đồng bộ mật khẩu
            </button>
        </form>

        <div
            id="ums-user-grid"
            class="ums-jqx-grid"
            data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
            data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
        ></div>
    </div>

    <div class="ums-panel" id="ums-profile-form">
        <h2><?php echo $is_editing ? 'Cập nhật hồ sơ nhân sự' : 'Thêm hồ sơ nhân sự'; ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_save_user_profile' ); ?>
            <input type="hidden" name="action" value="ums_save_user_profile">
            <input type="hidden" name="ums_profile[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">

            <div class="ums-form-grid">
                <input type="hidden" name="ums_profile[profile_id]" value="<?php echo esc_attr( $form_values['profile_id'] ); ?>">

                <label>
                    <span>Mã nhân viên <b>*</b></span>
                    <input type="text" name="ums_profile[employee_code]" value="<?php echo esc_attr( $form_values['employee_code'] ); ?>" required>
                </label>

                <label>
                    <span>Họ và tên <b>*</b></span>
                    <input type="text" name="ums_profile[full_name]" value="<?php echo esc_attr( $form_values['full_name'] ); ?>" required>
                </label>

                <label>
                    <span>Giới tính <b>*</b></span>
                    <select name="ums_profile[gender]" required>
                        <?php foreach ( UMS_DB_User::GENDERS as $gender ) : ?>
                            <option value="<?php echo esc_attr( $gender ); ?>" <?php selected( $form_values['gender'], $gender ); ?>>
                                <?php echo esc_html( $gender ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Nhà máy <b>*</b></span>
                    <?php if ( ! empty( $factory_location_options ) ) : ?>
                        <select name="ums_profile[factory_location]" required>
                            <option value="">Chọn nhà máy</option>
                            <?php foreach ( $factory_location_options as $factory_location_option ) : ?>
                                <option
                                    value="<?php echo esc_attr( $factory_location_option['factory_location_name'] ); ?>"
                                    <?php selected( $form_values['factory_location'], $factory_location_option['factory_location_name'] ); ?>
                                >
                                    <?php echo esc_html( $factory_location_option['factory_location_name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input type="text" name="ums_profile[factory_location]" value="<?php echo esc_attr( $form_values['factory_location'] ); ?>" required>
                        <p class="description">Chưa có danh mục nhà máy. Hãy thêm dữ liệu vào bảng <code>wp_uniform_factory_locations</code>.</p>
                    <?php endif; ?>
                </label>

                <label>
                    <span>Phòng ban <b>*</b></span>
                    <?php if ( ! empty( $department_options ) ) : ?>
                        <select name="ums_profile[department]" required>
                            <option value="">Chọn phòng ban</option>
                            <?php foreach ( $department_options as $department_option ) : ?>
                                <option
                                    value="<?php echo esc_attr( $department_option['department_name'] ); ?>"
                                    <?php selected( $form_values['department'], $department_option['department_name'] ); ?>
                                >
                                    <?php echo esc_html( $department_option['department_name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input type="text" name="ums_profile[department]" value="<?php echo esc_attr( $form_values['department'] ); ?>" required>
                        <p class="description">
                            Chưa có danh mục phòng ban. Có thể thêm tại
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tvn-ums-departments' ) ); ?>">Quản lý Phòng ban</a>.
                        </p>
                    <?php endif; ?>
                </label>

                <label>
                    <span>Chức danh <b>*</b></span>
                    <?php if ( ! empty( $position_options ) ) : ?>
                        <select name="ums_profile[job_position]" required>
                            <option value="">Chọn chức danh</option>
                            <?php foreach ( $position_options as $position_option ) : ?>
                                <option
                                    value="<?php echo esc_attr( $position_option['position_name'] ); ?>"
                                    <?php selected( $form_values['job_position'], $position_option['position_name'] ); ?>
                                >
                                    <?php echo esc_html( $position_option['position_name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input type="text" name="ums_profile[job_position]" value="<?php echo esc_attr( $form_values['job_position'] ); ?>" required>
                        <p class="description">
                            Chưa có danh mục chức danh. Có thể thêm tại
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tvn-ums-positions' ) ); ?>">Quản lý Chức danh</a>.
                        </p>
                    <?php endif; ?>
                </label>

                <label>
                    <span>Loại hợp đồng <b>*</b></span>
                    <?php if ( ! empty( $contract_type_options ) ) : ?>
                        <select name="ums_profile[contract_type]" required>
                            <option value="">Chọn loại hợp đồng</option>
                            <?php foreach ( $contract_type_options as $contract_type_option ) : ?>
                                <option
                                    value="<?php echo esc_attr( $contract_type_option['contract_type_name'] ); ?>"
                                    <?php selected( $form_values['contract_type'], $contract_type_option['contract_type_name'] ); ?>
                                >
                                    <?php echo esc_html( $contract_type_option['contract_type_name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else : ?>
                        <input type="text" name="ums_profile[contract_type]" value="<?php echo esc_attr( $form_values['contract_type'] ); ?>" required>
                        <p class="description">Chưa có danh mục loại hợp đồng. Hãy thêm dữ liệu vào bảng <code>wp_uniform_contract_types</code>.</p>
                    <?php endif; ?>
                </label>

                <label>
                    <span>Ngày vào công ty <b>*</b></span>
                    <input type="date" name="ums_profile[date_joined]" value="<?php echo esc_attr( $form_values['date_joined'] ); ?>" required>
                </label>

                <label>
                    <span>Ngày nộp đơn nghỉ</span>
                    <input type="date" name="ums_profile[resignation_date]" value="<?php echo esc_attr( $form_values['resignation_date'] ); ?>">
                </label>

                <label>
                    <span>Ngày chuyển bộ phận</span>
                    <input type="date" name="ums_profile[transfer_date]" value="<?php echo esc_attr( $form_values['transfer_date'] ); ?>">
                </label>

                <label>
                    <span>Trạng thái tài khoản <b>*</b></span>
                    <select name="ums_profile[account_status]" required>
                        <option value="active" <?php selected( $form_values['account_status'], 'active' ); ?>>Active</option>
                        <option value="inactive" <?php selected( $form_values['account_status'], 'inactive' ); ?>>Inactive</option>
                    </select>
                </label>
            </div>

            <fieldset class="ums-checkboxes">
                <legend>Trạng thái nghiệp vụ</legend>
                <label>
                    <input type="checkbox" name="ums_profile[is_maternity]" value="1" <?php checked( (int) $form_values['is_maternity'], 1 ); ?>>
                    Thai sản
                </label>
                <label>
                    <input type="checkbox" name="ums_profile[is_outdoor_worker]" value="1" <?php checked( (int) $form_values['is_outdoor_worker'], 1 ); ?>>
                    Làm việc ngoài trời
                </label>
                <?php if ( $is_editing ) : ?>
                    <label>
                        <input type="checkbox" name="ums_profile[reset_password]" value="1">
                        Đặt lại mật khẩu mặc định 12345678
                    </label>
                <?php else : ?>
                    <p class="description">Mật khẩu mặc định khi tạo tài khoản: 12345678</p>
                <?php endif; ?>
            </fieldset>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_editing ? 'Cập nhật hồ sơ' : 'Thêm hồ sơ'; ?>
                </button>
                <?php if ( $is_editing ) : ?>
                    <a href="<?php echo esc_url( $page_url . '#ums-profile-form' ); ?>" class="button">Hủy sửa</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
