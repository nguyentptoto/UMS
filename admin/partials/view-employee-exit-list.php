<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_url = admin_url( 'admin.php?page=tvn-ums-employee-exits' );
$type_labels = array(
	'probation' => 'Tập nghề / thử việc',
	'official' => 'Hợp đồng lao động',
	'labor_leasing' => 'Cho thuê lại lao động',
);
$status_labels = array(
	'pending' => 'Nhắc trả',
	'in_progress' => 'Thông báo trả còn thiếu',
	'completed' => 'Xác nhận đã trả',
);
$notification_labels = array(
	'legacy' => 'Hồ sơ cũ', 'pending' => 'Chờ gửi', 'sending' => 'Đang gửi',
	'sent' => 'Đã gửi', 'failed' => 'Gửi lỗi', 'skipped' => 'Không gửi',
);
$group_labels = array(
	'pants' => 'Quần', 'shirt' => 'Áo', 'jacket' => 'Áo khoác', 'coat' => 'Áo phao',
	'hat' => 'Mũ', 'shoes' => 'Giày', 'id_card' => 'Thẻ nhân viên', 'other' => 'Khác',
);
$grid_rows = array();
foreach ( $exit_cases as $case ) {
	$factory_code = UMS_DB_Inventory::resolve_factory_code_for_employee( $case );
	$detail_url = add_query_arg(
		array_filter(
			array(
				'exit_id' => absint( $case['exit_id'] ),
				's' => $filters['search'],
				'employee_type' => $filters['employee_type'],
				'status' => $filters['status'],
				'factory_code' => $filters['factory_code'],
			),
			function ( $value ) { return $value !== ''; }
		),
		$page_url
	);
	$grid_rows[] = array(
		'employee_no' => $case['employee_no'],
		'full_name' => $case['full_name'],
		'factory_name' => $factories[ $factory_code ] ?? $factory_code,
		'department' => $case['department'] ?: '-',
		'cost_center' => $case['cost_center'] ?: '-',
		'employee_type' => $type_labels[ $case['employee_type'] ] ?? $case['employee_type'],
		'detected_at' => mysql2date( 'd/m/Y H:i', $case['detected_at'] ),
		'status' => $status_labels[ $case['status'] ] ?? $case['status'],
		'notification_status' => '<span title="' . esc_attr( $case['notification_error'] ?? '' ) . '">' . esc_html( $notification_labels[ $case['notification_status'] ?? 'legacy' ] ?? ( $case['notification_status'] ?? '-' ) ) . '</span>',
		'actions' => '<a class="button button-small" href="' . esc_url( $detail_url ) . '">Xử lý</a>',
	);
}
$grid_columns = array(
	array( 'text' => 'Mã CNV', 'datafield' => 'employee_no', 'width' => '7%' ),
	array( 'text' => 'Họ tên', 'datafield' => 'full_name', 'width' => '10%' ),
	array( 'text' => 'Nhà máy', 'datafield' => 'factory_name', 'width' => '7%' ),
	array( 'text' => 'Bộ phận', 'datafield' => 'department', 'width' => '14%' ),
	array( 'text' => 'Cost center', 'datafield' => 'cost_center', 'width' => '8%' ),
	array( 'text' => 'Phân loại', 'datafield' => 'employee_type', 'width' => '11%' ),
	array( 'text' => 'Ngày phát hiện', 'datafield' => 'detected_at', 'width' => '10%' ),
	array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '10%' ),
	array( 'text' => 'Email lần đầu', 'datafield' => 'notification_status', 'width' => '8%', 'cellsrenderer' => 'html' ),
	array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '15%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
?>
<div class="wrap ums-admin-wrap ums-exit-wrap">
	<h1 class="wp-heading-inline">UMS - Quản lý CNV nghỉ việc</h1>
	<hr class="wp-header-end">

	<?php if ( $notice ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! $table_ready ) : ?>
		<div class="notice notice-error inline"><p>Database chưa có cấu trúc quản lý CNV nghỉ việc. Hãy chạy khối UPDATE tương ứng trong <code>ums.sql</code>.</p></div>
	<?php else : ?>
		<div class="ums-summary-strip">
			<span><strong><?php echo number_format_i18n( $status_counts['pending'] ?? 0 ); ?></strong> nhắc trả</span>
			<span><strong><?php echo number_format_i18n( $status_counts['in_progress'] ?? 0 ); ?></strong> thông báo trả còn thiếu</span>
			<span><strong><?php echo number_format_i18n( $status_counts['completed'] ?? 0 ); ?></strong> xác nhận đã trả</span>
		</div>

		<section class="ums-panel">
			<h2>Import CNV đã trả đồng phục</h2>
			<p class="description">Dùng template gồm Ngày trả, Mã NV, Họ tên, Áo, Quần, Áo khoác, Giày, Mũ và Thẻ nhân viên. Số lượng trong file thay thế giá trị Thực trả hiện tại và không nhập lại tồn kho.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
				<input type="hidden" name="action" value="ums_preview_employee_exit_returns">
				<input type="hidden" name="factory_code" value="<?php echo esc_attr( $filters['factory_code'] ); ?>">
				<?php wp_nonce_field( 'ums_preview_employee_exit_returns' ); ?>
				<input type="file" name="ums_employee_exit_return_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
				<button type="submit" class="button button-primary">Đọc và xem trước</button>
			</form>
		</section>

		<?php if ( is_array( $return_import_preview ) ) : ?>
			<section class="ums-panel">
				<h2>Xem trước: <?php echo esc_html( $return_import_preview['file_name'] ); ?></h2>
				<p>
					Sheet <?php echo esc_html( $return_import_preview['sheet_name'] ); ?>:
					<strong><?php echo number_format_i18n( count( $return_import_preview['rows'] ) ); ?></strong> CNV hợp lệ,
					tổng thực trả <strong><?php echo number_format_i18n( $return_import_preview['total_quantity'] ); ?></strong> sản phẩm.
				</p>
				<?php if ( ! empty( $return_import_preview['errors'] ) ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( implode( ' ', array_slice( $return_import_preview['errors'], 0, 20 ) ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( ! empty( $return_import_preview['warnings'] ) ) : ?>
					<div class="notice notice-warning inline"><p><?php echo esc_html( implode( ' ', array_slice( $return_import_preview['warnings'], 0, 20 ) ) ); ?></p></div>
				<?php endif; ?>
				<?php if ( ! empty( $return_import_preview['rows'] ) ) : ?>
					<div class="ums-table-scroll"><table class="widefat striped">
						<thead><tr><th>Dòng</th><th>Ngày trả</th><th>Mã CNV</th><th>Họ tên</th><th>Nhà máy</th><th>Áo</th><th>Quần</th><th>Áo khoác</th><th>Giày</th><th>Mũ</th><th>Thẻ NV</th><th>Tổng</th></tr></thead>
						<tbody>
						<?php foreach ( $return_import_preview['rows'] as $preview_row ) : ?>
							<tr>
								<td><?php echo absint( $preview_row['source_row'] ); ?></td>
								<td><?php echo esc_html( mysql2date( 'd/m/Y', $preview_row['return_date'] ) ); ?></td>
								<td><?php echo esc_html( $preview_row['employee_no'] ); ?></td>
								<td><?php echo esc_html( $preview_row['full_name'] ); ?></td>
								<td><?php echo esc_html( $factories[ $preview_row['factory_code'] ] ?? $preview_row['factory_code'] ); ?></td>
								<td><?php echo absint( $preview_row['quantities']['shirt'] ); ?></td>
								<td><?php echo absint( $preview_row['quantities']['pants'] ); ?></td>
								<td><?php echo absint( $preview_row['quantities']['jacket'] ); ?></td>
								<td><?php echo absint( $preview_row['quantities']['shoes'] ); ?></td>
								<td><?php echo absint( $preview_row['quantities']['hat'] ); ?></td>
								<td><?php echo absint( $preview_row['quantities']['id_card'] ); ?></td>
								<td><strong><?php echo absint( $preview_row['total_returned'] ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>
				<?php if ( empty( $return_import_preview['errors'] ) && ! empty( $return_import_preview['rows'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ums_confirm_employee_exit_returns">
						<input type="hidden" name="return_preview_token" value="<?php echo esc_attr( $return_preview_token ); ?>">
						<input type="hidden" name="factory_code" value="<?php echo esc_attr( $filters['factory_code'] ); ?>">
						<?php wp_nonce_field( 'ums_confirm_employee_exit_returns' ); ?>
						<button type="submit" class="button button-primary">Xác nhận cập nhật thực trả</button>
					</form>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<section class="ums-panel">
			<h2>Danh sách phát hiện nghỉ việc (<?php echo number_format_i18n( $exit_count ); ?>)</h2>
			<p class="description">CNV được tạo hồ sơ khi mã nhân viên không còn trong lần đồng bộ đầy đủ mới nhất của Sơ đồ tổ chức TVN.</p>
			<form method="get" class="ums-filter-bar">
				<input type="hidden" name="page" value="tvn-ums-employee-exits">
				<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Mã CNV, họ tên, bộ phận, cost center">
				<select name="factory_code">
					<option value="">Tất cả nhà máy</option>
					<?php foreach ( $factories as $factory_code => $factory_name ) : ?>
						<option value="<?php echo esc_attr( $factory_code ); ?>" <?php selected( $filters['factory_code'], $factory_code ); ?>><?php echo esc_html( $factory_name ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="employee_type">
					<option value="">Tất cả loại hợp đồng</option>
					<?php foreach ( $type_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['employee_type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="status">
					<option value="">Tất cả trạng thái</option>
					<?php foreach ( $status_labels as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $filters['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<button class="button" type="submit">Lọc</button>
				<a class="button button-link" href="<?php echo esc_url( $page_url ); ?>">Xóa lọc</a>
			</form>

			<div
				id="ums-employee-exit-grid"
				class="ums-jqx-grid"
				data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
				data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
			></div>
		</section>

		<?php if ( $selected_exit ) : ?>
			<?php
			$has_uniform_return_item = false;
			$first_contract_date = trim( (string) $selected_exit['first_contract_date'] );
			$first_contract_parts = array_map( 'intval', explode( '-', $first_contract_date ) );
			$has_valid_contract_date = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $first_contract_date )
				&& count( $first_contract_parts ) === 3
				&& $first_contract_parts[0] >= 1900
				&& checkdate( $first_contract_parts[1], $first_contract_parts[2], $first_contract_parts[0] );
			$first_contract_label = $has_valid_contract_date
				? mysql2date( 'd/m/Y', $first_contract_date )
				: '-';
			$selected_factory_code = UMS_DB_Inventory::resolve_factory_code_for_employee( $selected_exit );
			foreach ( $selected_exit_items as $selected_exit_item ) {
				if ( ! in_array( $selected_exit_item['item_group'], array( 'id_card', 'lanyard' ), true )
					&& (int) $selected_exit_item['required_quantity'] > (int) $selected_exit_item['exempt_quantity'] ) {
					$has_uniform_return_item = true;
					break;
				}
			}
			?>
			<section class="ums-panel ums-exit-detail">
				<h2>Thu hồi đồng phục: <?php echo esc_html( $selected_exit['employee_no'] . ' - ' . $selected_exit['full_name'] ); ?></h2>
				<div class="ums-exit-meta">
					<span><strong>Nhà máy:</strong> <?php echo esc_html( $factories[ $selected_factory_code ] ?? $selected_factory_code ); ?></span>
					<span><strong>Loại:</strong> <?php echo esc_html( $type_labels[ $selected_exit['employee_type'] ] ?? $selected_exit['employee_type'] ); ?></span>
					<span><strong>Trạng thái:</strong> <?php echo esc_html( $status_labels[ $selected_exit['status'] ] ?? $selected_exit['status'] ); ?></span>
					<span><strong>Ngày vào:</strong> <?php echo esc_html( $selected_exit['date_joined'] ? mysql2date( 'd/m/Y', $selected_exit['date_joined'] ) : '-' ); ?></span>
					<span><strong>Ngày ký HĐ đầu tiên:</strong> <?php echo esc_html( $first_contract_label ); ?></span>
					<span><strong>Vị trí:</strong> <?php echo esc_html( $selected_exit['position'] ?: '-' ); ?></span>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-inline-form ums-exit-date-form">
					<input type="hidden" name="action" value="ums_refresh_employee_exit">
					<input type="hidden" name="exit_id" value="<?php echo absint( $selected_exit['exit_id'] ); ?>">
					<input type="hidden" name="factory_code" value="<?php echo esc_attr( $filters['factory_code'] ); ?>">
					<?php wp_nonce_field( 'ums_refresh_employee_exit_' . $selected_exit['exit_id'] ); ?>
					<label><strong>Ngày nghỉ thực tế</strong> <input type="date" name="actual_leave_date" required value="<?php echo esc_attr( $selected_exit['actual_leave_date'] ); ?>"></label>
					<button type="submit" class="button">Tính lại nghĩa vụ hoàn trả</button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ums_save_employee_exit_returns">
					<input type="hidden" name="exit_id" value="<?php echo absint( $selected_exit['exit_id'] ); ?>">
					<input type="hidden" name="factory_code" value="<?php echo esc_attr( $filters['factory_code'] ); ?>">
					<?php wp_nonce_field( 'ums_save_employee_exit_returns_' . $selected_exit['exit_id'] ); ?>
					<?php if ( ! $has_uniform_return_item ) : ?>
						<div class="notice notice-warning inline"><p>Không tìm thấy đồng phục đã xuất kho, SL cấp phát đã chốt hoặc định mức phù hợp cho CNV này. Hệ thống chỉ hiển thị thẻ nhân viên.</p></div>
					<?php endif; ?>
					<div class="ums-table-scroll"><table class="widefat striped ums-exit-items">
						<thead><tr><th>Nhóm</th><th>Sản phẩm</th><th>Size</th><th>Đã cấp</th><th>Phải trả</th><th>Miễn trả</th><th>Thực trả</th><th>Lần cấp gần nhất / ghi chú</th></tr></thead>
						<tbody>
						<?php foreach ( $selected_exit_items as $item ) :
							$disabled = (int) $item['required_quantity'] <= (int) $item['exempt_quantity'] || $selected_exit['status'] === 'cancelled';
						?>
							<tr>
								<td><?php echo esc_html( $group_labels[ $item['item_group'] ] ?? $item['item_group'] ); ?></td>
								<td><?php echo esc_html( $item['item_name'] ); ?></td>
								<td><?php echo esc_html( $item['size'] ?: '-' ); ?></td>
								<td><?php echo absint( $item['issued_quantity'] ); ?></td>
								<td><strong><?php echo absint( $item['required_quantity'] ); ?></strong></td>
								<td><?php echo absint( $item['exempt_quantity'] ); ?></td>
								<td><input type="number" min="0" max="<?php echo esc_attr( max( $item['issued_quantity'], $item['required_quantity'] ) ); ?>" name="return_items[<?php echo absint( $item['return_item_id'] ); ?>][returned_quantity]" value="<?php echo absint( $item['returned_quantity'] ); ?>" <?php disabled( $disabled ); ?>></td>
								<td><?php echo esc_html( $item['exemption_reason'] ?: ( $item['latest_issued_at'] ? mysql2date( 'd/m/Y', $item['latest_issued_at'] ) : '-' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
					<p><label for="ums-exit-notes"><strong>Ghi chú xử lý</strong></label><br><textarea id="ums-exit-notes" name="notes" rows="3" class="large-text"><?php echo esc_textarea( $selected_exit['notes'] ); ?></textarea></p>
					<?php if ( $selected_exit['status'] !== 'cancelled' ) : ?><button type="submit" class="button button-primary">Cập nhật tình trạng hoàn trả</button><?php endif; ?>
				</form>
			</section>
		<?php endif; ?>
	<?php endif; ?>
</div>
