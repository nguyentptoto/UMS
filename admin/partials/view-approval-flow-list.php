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
$approver_map = array();

foreach ( $approvers as $approver ) {
    $approver_map[ (int) $approver['profile_id'] ] = $approver;
}

$format_department_label = function( $department ) {
	if ( isset( $department['department_id'] ) && (int) $department['department_id'] === 0 ) {
		return 'Tất cả phòng ban (mẫu chung)';
	}
    $code = isset( $department['department_code'] ) ? trim( (string) $department['department_code'] ) : '';
    $name = isset( $department['department_name'] ) ? trim( (string) $department['department_name'] ) : '';

    if ( $code !== '' && $name !== '' ) {
		if ( strpos( $code, 'org-' ) === 0 ) {
			return $name;
		}
        return $code . ' - ' . $name;
    }

    return $name !== '' ? $name : ( $code !== '' ? $code : '-' );
};

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
	$resolver_type = isset( $flow['resolver_type'] ) ? $flow['resolver_type'] : 'specific';
	if ( $resolver_type === 'position' ) {
		$positions = json_decode( (string) $flow['approver_positions'], true );
		$positions = is_array( $positions ) ? $positions : array();
		$scope = ! empty( $flow['resolver_department'] ) ? $flow['resolver_department'] : 'phòng ban của phiếu';
		if ( ! empty( $flow['resolver_factory'] ) ) {
			$scope .= ' / ' . $flow['resolver_factory'];
		}
		$approver_labels[] = implode( ' → ', $positions ) . ' (' . $scope . ')';
	} else {
		foreach ( $approver_ids as $approver_id ) {
			if ( isset( $approver_map[ $approver_id ] ) ) {
				$approver = $approver_map[ $approver_id ];
				$approver_labels[] = trim( $approver['employee_code'] . ' - ' . $approver['full_name'] );
			} else {
				$approver_labels[] = 'Hồ sơ #' . $approver_id . ' (không còn trên Sơ đồ tổ chức)';
			}
		}
	}

    $grid_rows[] = array(
        'department_name' => $format_department_label( $flow ),
        'step_order'      => (int) $flow['step_order'],
        'step_group'      => 'Bước ' . (int) $flow['step_order'] . ' - ' . $flow['step_name'],
        'step_name'       => $flow['step_name'],
		'resolver_type'   => $resolver_type === 'position' ? 'Theo chức danh' : 'Người cụ thể',
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
	array( 'text' => 'Cách xác định', 'datafield' => 'resolver_type', 'width' => '12%' ),
    array( 'text' => 'Người duyệt / vai trò', 'datafield' => 'approver', 'width' => '28%' ),
    array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '10%' ),
    array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '10%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
$grid_groups = array( 'department_name', 'step_group' );

$delegation_rows = array();
foreach ( $delegations as $delegation ) {
	$profile_id = absint( $delegation['delegate_profile_id'] );
	$person     = isset( $approver_map[ $profile_id ] ) ? $approver_map[ $profile_id ] : null;
	$edit_url   = add_query_arg( array( 'page' => 'tvn-ums-approval-flows', 'edit_delegation_id' => absint( $delegation['delegation_id'] ) ), admin_url( 'admin.php' ) );
	$delete_url = wp_nonce_url(
		add_query_arg( array( 'action' => 'ums_delete_approval_delegation', 'delegation_id' => absint( $delegation['delegation_id'] ) ), admin_url( 'admin-post.php' ) ),
		'ums_delete_approval_delegation_' . absint( $delegation['delegation_id'] )
	);
	$stored_factories = json_decode( (string) $delegation['factory'], true );
	$stored_factories = is_array( $stored_factories ) ? $stored_factories : ( $delegation['factory'] !== '' ? array( $delegation['factory'] ) : array() );
	$delegation_rows[] = array(
		'employee'   => $person ? $person['employee_code'] . ' - ' . $person['full_name'] : 'Hồ sơ #' . $profile_id . ' (không còn hoạt động)',
		'role'       => $delegation['role_code'],
		'scope'      => ( $delegation['department'] !== '' ? $delegation['department'] : 'Tất cả phòng ban' ) . ' / ' . ( $stored_factories ? implode( ', ', $stored_factories ) : 'Tất cả nhà máy' ),
		'effective'  => $delegation['start_date'] . ' - ' . ( $delegation['end_date'] ? $delegation['end_date'] : 'Không thời hạn' ),
		'reason'     => $delegation['reason'],
		'status'     => (int) $delegation['is_active'] === 1 ? 'Đang sử dụng' : 'Ngừng sử dụng',
		'actions'    => '<a href="' . esc_url( $edit_url . '#ums-approval-delegation-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa quyền duyệt thay thế này?">Xóa</a>',
	);
}
$delegation_columns = array(
	array( 'text' => 'Người nhận quyền', 'datafield' => 'employee', 'width' => '22%' ),
	array( 'text' => 'Vai trò duyệt thay', 'datafield' => 'role', 'width' => '12%' ),
	array( 'text' => 'Phạm vi', 'datafield' => 'scope', 'width' => '21%' ),
	array( 'text' => 'Thời gian hiệu lực', 'datafield' => 'effective', 'width' => '18%' ),
	array( 'text' => 'Lý do', 'datafield' => 'reason', 'width' => '15%' ),
	array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '10%' ),
	array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '12%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Luồng duyệt</h1>
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
                            <?php echo esc_html( $format_department_label( $department ) ); ?>
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
		<p class="description">Bước 1 là bước phê duyệt đầu tiên sau khi người dùng gửi phiếu. Mẫu chung áp dụng cho mọi phòng ban và mọi nhà máy, nhưng người duyệt mặc định vẫn được giới hạn theo phòng ban và nhà máy của phiếu.</p>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_save_approval_flow' ); ?>
            <input type="hidden" name="action" value="ums_save_approval_flow">
            <input type="hidden" name="ums_approval_flow[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
            <input type="hidden" name="ums_approval_flow[flow_id]" value="<?php echo esc_attr( $form_values['flow_id'] ); ?>">

            <div class="ums-form-grid">
				<label>
					<span>Phạm vi phòng ban của mẫu <b>*</b></span>
                    <select name="ums_approval_flow[department_id]" required>
						<option value="">Chọn phạm vi</option>
						<option value="0" <?php selected( (int) $form_values['department_id'], 0 ); ?>>Tất cả phòng ban (mẫu chung)</option>
                        <?php foreach ( $departments as $department ) : ?>
                            <option value="<?php echo esc_attr( $department['department_id'] ); ?>" <?php selected( (int) $form_values['department_id'], (int) $department['department_id'] ); ?>>
                                <?php echo esc_html( $format_department_label( $department ) ); ?>
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
					<span>Cách xác định người duyệt <b>*</b></span>
					<select name="ums_approval_flow[resolver_type]" id="ums-approval-resolver-type">
						<option value="specific" <?php selected( $form_values['resolver_type'], 'specific' ); ?>>Chọn người cụ thể</option>
						<option value="position" <?php selected( $form_values['resolver_type'], 'position' ); ?>>Theo chức danh từ Sơ đồ tổ chức</option>
					</select>
				</label>

				<label data-ums-resolver-section="specific">
					<span>Người duyệt cụ thể</span>
                    <select name="ums_approval_flow[approver_profile_ids][]" multiple size="8">
                        <?php foreach ( $approvers as $approver ) : ?>
                            <option value="<?php echo esc_attr( $approver['profile_id'] ); ?>" <?php selected( in_array( (int) $approver['profile_id'], $form_values['approver_profile_ids'], true ) ); ?>>
								<?php
								echo esc_html(
									$approver['employee_code'] . ' - ' . $approver['full_name']
									. ' | ' . $approver['department']
									. ( $approver['job_position'] !== '' ? ' | ' . $approver['job_position'] : '' )
									. ( $approver['cost_center'] !== '' ? ' | ' . $approver['cost_center'] : '' )
									. ( $approver['factory'] !== '' ? ' | ' . $approver['factory'] : '' )
								);
								?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Giữ Ctrl để chọn nhiều người duyệt trong cùng một bước.</p>
                </label>

				<label data-ums-resolver-section="position">
					<span>Nhóm chức danh duyệt</span>
					<input type="text" name="ums_approval_flow[approver_positions]" value="<?php echo esc_attr( implode( ', ', $form_values['approver_positions'] ) ); ?>" list="ums-position-options" placeholder="VD: DMG, MG">
					<p class="description">Nhập theo thứ tự ưu tiên, ngăn cách bằng dấu phẩy. Ví dụ: DMG, MG hoặc DGM, GM, DR.</p>
				</label>

				<label data-ums-resolver-section="position">
					<span>Phòng ban áp dụng vai trò</span>
					<select name="ums_approval_flow[resolver_department]">
						<option value="">Phòng ban của phiếu yêu cầu</option>
						<?php foreach ( $departments as $department ) : ?>
							<option value="<?php echo esc_attr( $department['department_name'] ); ?>" <?php selected( $form_values['resolver_department'], $department['department_name'] ); ?>><?php echo esc_html( $department['department_name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>

            </div>
			<datalist id="ums-position-options">
				<?php foreach ( $position_options as $position ) : ?><option value="<?php echo esc_attr( $position ); ?>"><?php endforeach; ?>
			</datalist>

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

	<div class="ums-panel">
		<h2>Ủy quyền và người duyệt thay thế</h2>
		<p class="description">Quyền này không thay đổi chức danh chính trên Sơ đồ tổ chức. Người được chọn chỉ nhận thêm vai trò duyệt trong phạm vi và thời gian cấu hình.</p>
		<div
			id="ums-approval-delegation-grid"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $delegation_rows ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $delegation_columns ) ); ?>"
		></div>
	</div>

	<div class="ums-panel" id="ums-approval-delegation-form">
		<h2><?php echo $editing_delegation ? 'Cập nhật quyền duyệt thay thế' : 'Thêm quyền duyệt thay thế'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
			<?php wp_nonce_field( 'ums_save_approval_delegation' ); ?>
			<input type="hidden" name="action" value="ums_save_approval_delegation">
			<input type="hidden" name="ums_approval_delegation[is_edit]" value="<?php echo $editing_delegation ? '1' : '0'; ?>">
			<input type="hidden" name="ums_approval_delegation[delegation_id]" value="<?php echo esc_attr( $delegation_values['delegation_id'] ); ?>">
			<div class="ums-form-grid">
				<label>
					<span>Người nhận quyền <b>*</b></span>
					<select name="ums_approval_delegation[delegate_profile_id]" required>
						<option value="">Chọn người từ Sơ đồ tổ chức</option>
						<?php foreach ( $approvers as $approver ) : ?>
							<option value="<?php echo esc_attr( $approver['profile_id'] ); ?>" <?php selected( (int) $delegation_values['delegate_profile_id'], (int) $approver['profile_id'] ); ?>>
								<?php echo esc_html( $approver['employee_code'] . ' - ' . $approver['full_name'] . ' | ' . $approver['department'] . ' | ' . $approver['job_position'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span>Vai trò duyệt thay <b>*</b></span>
					<input type="text" name="ums_approval_delegation[role_code]" value="<?php echo esc_attr( $delegation_values['role_code'] ); ?>" list="ums-position-options" placeholder="VD: MG" required>
				</label>
				<label>
					<span>Phòng ban</span>
					<select name="ums_approval_delegation[department]">
						<option value="">Tất cả phòng ban</option>
						<?php foreach ( $departments as $department ) : ?>
							<option value="<?php echo esc_attr( $department['department_name'] ); ?>" <?php selected( $delegation_values['department'], $department['department_name'] ); ?>><?php echo esc_html( $department['department_name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span>Nhà máy</span>
					<select name="ums_approval_delegation[factories][]" multiple size="4">
						<option value="" <?php selected( empty( $delegation_values['factories'] ) ); ?>>Tất cả nhà máy</option>
						<?php foreach ( $factory_options as $factory ) : ?>
							<option value="<?php echo esc_attr( $factory ); ?>" <?php selected( in_array( $factory, $delegation_values['factories'], true ) ); ?>><?php echo esc_html( $factory ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">Giữ Ctrl để chọn nhiều nhà máy.</p>
				</label>
				<label><span>Ngày bắt đầu <b>*</b></span><input type="date" name="ums_approval_delegation[start_date]" value="<?php echo esc_attr( $delegation_values['start_date'] ); ?>" required></label>
				<label><span>Ngày kết thúc</span><input type="date" name="ums_approval_delegation[end_date]" value="<?php echo esc_attr( $delegation_values['end_date'] ); ?>"></label>
				<label><span>Lý do ủy quyền</span><input type="text" name="ums_approval_delegation[reason]" value="<?php echo esc_attr( $delegation_values['reason'] ); ?>" placeholder="VD: MG nghỉ phép"></label>
			</div>
			<fieldset class="ums-checkboxes"><legend>Trạng thái</legend><label><input type="checkbox" name="ums_approval_delegation[is_active]" value="1" <?php checked( (int) $delegation_values['is_active'], 1 ); ?>> Đang sử dụng</label></fieldset>
			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $editing_delegation ? 'Cập nhật quyền duyệt thay' : 'Thêm quyền duyệt thay'; ?></button>
				<?php if ( $editing_delegation ) : ?><a href="<?php echo esc_url( $page_url . '#ums-approval-delegation-form' ); ?>" class="button">Hủy sửa</a><?php endif; ?>
			</p>
		</form>
	</div>
</div>
