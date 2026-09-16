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
$group_labels = array(
	'pants' => 'Quần', 'shirt' => 'Áo', 'jacket' => 'Áo khoác', 'coat' => 'Áo phao',
	'hat' => 'Mũ', 'shoes' => 'Giày', 'id_card' => 'Thẻ nhân viên', 'lanyard' => 'Dây đeo thẻ', 'other' => 'Khác',
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
			<h2>Danh sách phát hiện nghỉ việc</h2>
			<p class="description">CNV được tạo hồ sơ khi mã nhân viên không còn trong lần đồng bộ đầy đủ mới nhất của Sơ đồ tổ chức TVN.</p>
			<form method="get" class="ums-filter-bar">
				<input type="hidden" name="page" value="tvn-ums-employee-exits">
				<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Mã CNV, họ tên, bộ phận, cost center">
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

			<div class="ums-table-scroll">
				<table class="widefat striped">
					<thead><tr><th>Mã CNV</th><th>Họ tên</th><th>Bộ phận</th><th>Cost center</th><th>Phân loại</th><th>Ngày phát hiện</th><th>Trạng thái</th><th></th></tr></thead>
					<tbody>
					<?php if ( empty( $exit_cases ) ) : ?>
						<tr><td colspan="8" class="ums-empty-state">Chưa có CNV nghỉ việc phù hợp với bộ lọc.</td></tr>
					<?php else : foreach ( $exit_cases as $case ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $case['employee_no'] ); ?></strong></td>
							<td><?php echo esc_html( $case['full_name'] ); ?></td>
							<td><?php echo esc_html( $case['department'] ?: '-' ); ?></td>
							<td><?php echo esc_html( $case['cost_center'] ?: '-' ); ?></td>
							<td><?php echo esc_html( $type_labels[ $case['employee_type'] ] ?? $case['employee_type'] ); ?></td>
							<td><?php echo esc_html( mysql2date( 'd/m/Y H:i', $case['detected_at'] ) ); ?></td>
							<td><?php echo esc_html( $status_labels[ $case['status'] ] ?? $case['status'] ); ?></td>
							<td><a class="button button-small" href="<?php echo esc_url( add_query_arg( 'exit_id', $case['exit_id'], $page_url ) ); ?>">Xử lý</a></td>
						</tr>
					<?php endforeach; endif; ?>
					</tbody>
				</table>
			</div>
			<?php
			$total_pages = max( 1, (int) ceil( $exit_count / 50 ) );
			if ( $total_pages > 1 ) {
				echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( paginate_links( array(
					'base' => add_query_arg( 'paged', '%#%', remove_query_arg( 'exit_id' ) ), 'format' => '',
					'current' => $filters['page'], 'total' => $total_pages,
				) ) ) . '</div></div>';
			}
			?>
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
			foreach ( $selected_exit_items as $selected_exit_item ) {
				if ( (int) $selected_exit_item['item_id'] > 0 ) {
					$has_uniform_return_item = true;
					break;
				}
			}
			?>
			<section class="ums-panel ums-exit-detail">
				<h2>Thu hồi đồng phục: <?php echo esc_html( $selected_exit['employee_no'] . ' - ' . $selected_exit['full_name'] ); ?></h2>
				<div class="ums-exit-meta">
					<span><strong>Loại:</strong> <?php echo esc_html( $type_labels[ $selected_exit['employee_type'] ] ?? $selected_exit['employee_type'] ); ?></span>
					<span><strong>Trạng thái:</strong> <?php echo esc_html( $status_labels[ $selected_exit['status'] ] ?? $selected_exit['status'] ); ?></span>
					<span><strong>Ngày vào:</strong> <?php echo esc_html( $selected_exit['date_joined'] ? mysql2date( 'd/m/Y', $selected_exit['date_joined'] ) : '-' ); ?></span>
					<span><strong>Ngày ký HĐ đầu tiên:</strong> <?php echo esc_html( $first_contract_label ); ?></span>
					<span><strong>Vị trí:</strong> <?php echo esc_html( $selected_exit['position'] ?: '-' ); ?></span>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-inline-form ums-exit-date-form">
					<input type="hidden" name="action" value="ums_refresh_employee_exit">
					<input type="hidden" name="exit_id" value="<?php echo absint( $selected_exit['exit_id'] ); ?>">
					<?php wp_nonce_field( 'ums_refresh_employee_exit_' . $selected_exit['exit_id'] ); ?>
					<label><strong>Ngày nghỉ thực tế</strong> <input type="date" name="actual_leave_date" required value="<?php echo esc_attr( $selected_exit['actual_leave_date'] ); ?>"></label>
					<button type="submit" class="button">Tính lại nghĩa vụ hoàn trả</button>
				</form>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="ums_save_employee_exit_returns">
					<input type="hidden" name="exit_id" value="<?php echo absint( $selected_exit['exit_id'] ); ?>">
					<?php wp_nonce_field( 'ums_save_employee_exit_returns_' . $selected_exit['exit_id'] ); ?>
					<?php if ( ! $has_uniform_return_item ) : ?>
						<div class="notice notice-warning inline"><p>Không tìm thấy đồng phục đã xuất kho hoặc SL cấp phát đã chốt cho CNV này. Hệ thống chỉ hiển thị thẻ nhân viên và dây đeo thẻ.</p></div>
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
