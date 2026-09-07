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
