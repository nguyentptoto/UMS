<?php
/**
 * Assignment management for special-work allowance matrices.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="ums-panel" id="ums-special-work-assignments">
	<h2>Gán công việc đặc thù cho CNV</h2>
	<?php if ( ! $special_work_ready ) : ?>
		<div class="notice notice-warning inline"><p>Database chưa có bảng gán công việc đặc thù hoặc cột special_work_type.</p></div>
	<?php else : ?>
		<h3>Import danh sách CNV đặc thù</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-filter-bar">
			<?php wp_nonce_field( 'ums_preview_special_work_assignment_import' ); ?>
			<input type="hidden" name="action" value="ums_preview_special_work_assignment_import">
			<input type="file" name="ums_special_work_assignment_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
			<button type="submit" class="button button-primary">Đọc và xem trước</button>
		</form>

		<?php if ( is_array( $special_assignment_import_preview ) ) : ?>
			<?php
			$preview_periods = array_map(
				function ( $period ) {
					return 'T' . absint( $period );
				},
				$special_assignment_import_preview['processed_periods'] ?? array()
			);
			?>
			<p>
				<strong><?php echo esc_html( $special_assignment_import_preview['file_name'] ?? '' ); ?></strong>:
				<?php echo esc_html( number_format_i18n( count( $special_assignment_import_preview['rows'] ?? array() ) ) ); ?> CNV hợp lệ,
				kỳ <?php echo esc_html( implode( ', ', $preview_periods ) ); ?>.
			</p>

			<?php if ( ! empty( $special_assignment_import_preview['errors'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( implode( ' ', array_slice( $special_assignment_import_preview['errors'], 0, 10 ) ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $special_assignment_import_preview['warnings'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( implode( ' ', array_slice( $special_assignment_import_preview['warnings'], 0, 10 ) ) ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! empty( $special_assignment_import_preview['rows'] ) ) : ?>
				<div class="ums-table-scroll">
					<table class="widefat striped">
						<thead><tr><th>Sheet</th><th>Dòng</th><th>Mã NV</th><th>Họ tên TVN</th><th>Loại công việc</th><th>Bộ phận</th><th>Cost center</th></tr></thead>
						<tbody>
						<?php foreach ( $special_assignment_import_preview['rows'] as $preview_row ) : ?>
							<tr>
								<td><?php echo esc_html( $preview_row['source_sheet'] ); ?></td>
								<td><?php echo esc_html( absint( $preview_row['source_row'] ) ); ?></td>
								<td><?php echo esc_html( $preview_row['employee_no'] ); ?></td>
								<td><?php echo esc_html( $preview_row['organization_full_name'] ); ?></td>
								<td><?php echo esc_html( $preview_row['special_work_type'] ); ?></td>
								<td><?php echo esc_html( $preview_row['department'] ?: '-' ); ?></td>
								<td><?php echo esc_html( $preview_row['cost_center'] ?: '-' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( empty( $special_assignment_import_preview['errors'] ) && ! empty( $special_assignment_import_preview['rows'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ums_confirm_special_work_assignment_import' ); ?>
					<input type="hidden" name="action" value="ums_confirm_special_work_assignment_import">
					<input type="hidden" name="special_assignment_preview_token" value="<?php echo esc_attr( $special_assignment_preview_token ); ?>">
					<p class="submit"><button type="submit" class="button button-primary">Xác nhận import</button></p>
				</form>
			<?php endif; ?>
		<?php endif; ?>

		<hr>
		<h3>Gán thủ công</h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
			<?php wp_nonce_field( 'ums_save_special_work_assignment' ); ?>
			<input type="hidden" name="action" value="ums_save_special_work_assignment">
			<div class="ums-form-grid">
				<label>
					<span>Mã nhân viên <b>*</b></span>
					<input type="text" name="employee_no" list="ums-special-work-employees" required placeholder="Ví dụ: F050008">
					<datalist id="ums-special-work-employees">
						<?php foreach ( $organization_recipients as $employee ) : ?>
							<option value="<?php echo esc_attr( $employee['employee_no'] ); ?>"><?php echo esc_html( $employee['full_name'] ); ?></option>
						<?php endforeach; ?>
					</datalist>
				</label>
				<label>
					<span>Công việc đặc thù T4</span>
					<select name="special_work_type[4]">
						<option value="">Không thay đổi</option>
						<?php foreach ( $special_work_types[4] as $work_type ) : ?>
							<option value="<?php echo esc_attr( $work_type ); ?>"><?php echo esc_html( $work_type ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					<span>Công việc đặc thù T9</span>
					<select name="special_work_type[9]">
						<option value="">Không thay đổi</option>
						<?php foreach ( $special_work_types[9] as $work_type ) : ?>
							<option value="<?php echo esc_attr( $work_type ); ?>"><?php echo esc_html( $work_type ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<p class="submit"><button type="submit" class="button button-primary">Lưu công việc đặc thù</button></p>
		</form>

		<table class="widefat striped">
			<thead><tr><th>Mã NV</th><th>Họ và tên</th><th>Kỳ</th><th>Loại công việc</th><th>Bộ phận</th><th>Cost center</th><th>Quản lý</th></tr></thead>
			<tbody>
			<?php if ( empty( $special_work_assignments ) ) : ?>
				<tr><td colspan="7">Chưa có CNV nào được gán công việc đặc thù.</td></tr>
			<?php else : ?>
				<?php foreach ( $special_work_assignments as $assignment ) : ?>
					<tr>
						<td><?php echo esc_html( $assignment['employee_no'] ); ?></td>
						<td><?php echo esc_html( $assignment['full_name'] ?: '-' ); ?></td>
						<td><?php echo esc_html( 'T' . absint( $assignment['period_month'] ) ); ?></td>
						<td><?php echo esc_html( $assignment['special_work_type'] ); ?></td>
						<td><?php echo esc_html( $assignment['department'] ?: '-' ); ?></td>
						<td><?php echo esc_html( $assignment['cost_center'] ?: '-' ); ?></td>
						<td>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( 'ums_delete_special_work_assignment_' . absint( $assignment['assignment_id'] ) ); ?>
								<input type="hidden" name="action" value="ums_delete_special_work_assignment">
								<input type="hidden" name="assignment_id" value="<?php echo esc_attr( absint( $assignment['assignment_id'] ) ); ?>">
								<button type="submit" class="button button-link-delete">Xóa</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
