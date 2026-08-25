<?php
/**
 * Bộ lọc xuất thẳng định mức theo từng CNV.
 */
$allowed_cost_center_prefixes = array_keys( UMS_Employee_Allowance_Report::get_cost_center_prefixes() );
$report_cost_centers = array_values(
	array_filter(
		$organization_cost_centers,
		function ( $cost_center ) use ( $allowed_cost_center_prefixes ) {
			foreach ( $allowed_cost_center_prefixes as $prefix ) {
				if ( strpos( (string) $cost_center, (string) $prefix ) === 0 ) {
					return true;
				}
			}
			return false;
		}
	)
);
?>

<div class="ums-panel" id="ums-employee-allowance-report">
	<h2>Xuất định mức theo từng CNV</h2>
	<p class="description">Chọn phạm vi cần xuất. Hệ thống tạo trực tiếp file Excel từ Sơ đồ tổ chức TVN và rule định mức, không thực hiện bước xem trước.</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-filter-bar">
		<?php wp_nonce_field( 'ums_export_employee_allowances' ); ?>
		<input type="hidden" name="action" value="ums_export_employee_allowances">

		<label>
			<span>Năm</span>
			<input type="number" name="report_year" value="<?php echo esc_attr( $allowance_report_filters['report_year'] ); ?>" min="2000" max="2100" required style="width:100px;">
		</label>
		<label>
			<span>Kỳ cấp phát</span>
			<select name="report_month">
				<option value="4" <?php selected( $allowance_report_filters['report_month'], 4 ); ?>>Tháng 4</option>
				<option value="9" <?php selected( $allowance_report_filters['report_month'], 9 ); ?>>Tháng 9</option>
			</select>
		</label>
		<label>
			<span>Số lượng xuất</span>
			<select name="report_quantity_mode">
				<option value="remaining" <?php selected( $allowance_report_filters['quantity_mode'], 'remaining' ); ?>>Còn được nhận</option>
				<option value="quota" <?php selected( $allowance_report_filters['quantity_mode'], 'quota' ); ?>>Định mức gốc</option>
			</select>
		</label>
		<label>
			<span>Tìm CNV</span>
			<input type="search" name="report_search" value="<?php echo esc_attr( $allowance_report_filters['search'] ); ?>" placeholder="Mã NV, họ tên hoặc email">
		</label>
		<label>
			<span>Phòng</span>
			<select name="report_department">
				<option value="">Tất cả phòng</option>
				<?php foreach ( $organization_departments as $department ) : ?>
					<option value="<?php echo esc_attr( $department ); ?>" <?php selected( $allowance_report_filters['department'], $department ); ?>><?php echo esc_html( $department ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span>Nhóm</span>
			<select name="report_team">
				<option value="">Tất cả nhóm</option>
				<?php foreach ( $organization_teams as $team ) : ?>
					<option value="<?php echo esc_attr( $team ); ?>" <?php selected( $allowance_report_filters['team'], $team ); ?>><?php echo esc_html( $team ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span>Đầu Cost center</span>
			<select name="report_cost_center_prefix">
				<option value="">1300, 4400 và 4900</option>
				<?php foreach ( UMS_Employee_Allowance_Report::get_cost_center_prefixes() as $prefix => $prefix_label ) : ?>
					<option value="<?php echo esc_attr( $prefix ); ?>" <?php selected( $allowance_report_filters['cost_center_prefix'], $prefix ); ?>><?php echo esc_html( $prefix_label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span>Cost center</span>
			<select name="report_cost_center">
				<option value="">Tất cả cost center</option>
				<?php foreach ( $report_cost_centers as $cost_center ) : ?>
					<option value="<?php echo esc_attr( $cost_center ); ?>" <?php selected( $allowance_report_filters['cost_center'], $cost_center ); ?>><?php echo esc_html( $cost_center ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span>Vị trí</span>
			<select name="report_position">
				<option value="">Tất cả vị trí</option>
				<?php foreach ( $organization_positions as $position_code ) : ?>
					<option value="<?php echo esc_attr( $position_code ); ?>" <?php selected( $allowance_report_filters['position'], $position_code ); ?>><?php echo esc_html( $position_code ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label class="ums-inline-checkbox">
			<input type="hidden" name="report_include_zero" value="0">
			<input type="checkbox" name="report_include_zero" value="1" <?php checked( $allowance_report_filters['include_zero'] ); ?>>
			<span>Gồm CNV có định mức bằng 0</span>
		</label>
		<button type="submit" class="button button-primary">Xuất file Excel</button>
	</form>
</div>
