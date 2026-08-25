<?php
/**
 * Bộ lọc, xem trước và xuất định mức theo từng CNV.
 */
$report_rows = is_array( $allowance_report ) ? ( $allowance_report['rows'] ?? array() ) : array();
$preview_rows = array_slice( $report_rows, 0, 500 );
$report_columns = array(
	array( 'text' => 'STT', 'datafield' => 'stt', 'width' => 65, 'cellsalign' => 'center', 'pinned' => true ),
	array( 'text' => 'Mã nhân viên', 'datafield' => 'employee_no', 'width' => 125, 'pinned' => true ),
	array( 'text' => 'Họ và tên', 'datafield' => 'full_name', 'width' => 210 ),
	array( 'text' => 'Phòng', 'datafield' => 'department', 'width' => 230 ),
	array( 'text' => 'Cost center', 'datafield' => 'cost_center', 'width' => 130 ),
	array( 'text' => 'Mũ & Giày định mức', 'datafield' => 'hat_shoes', 'width' => 170 ),
	array( 'text' => 'SL quần định mức', 'datafield' => 'pants_qty', 'width' => 135, 'cellsalign' => 'center' ),
	array( 'text' => 'SL áo phông định mức', 'datafield' => 'shirt_qty', 'width' => 155, 'cellsalign' => 'center' ),
	array( 'text' => 'SL áo khoác định mức', 'datafield' => 'jacket_qty', 'width' => 155, 'cellsalign' => 'center' ),
	array( 'text' => 'SL áo phao định mức', 'datafield' => 'coat_qty', 'width' => 150, 'cellsalign' => 'center' ),
);
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
	<p class="description">Dữ liệu nhân sự lấy từ Sơ đồ tổ chức TVN. Kết quả áp dụng rule định mức đúng kỳ T4/T9 và ngày vào Công ty.</p>

	<form method="get" class="ums-filter-bar">
		<input type="hidden" name="page" value="tvn-ums-annual-allowances">
		<input type="hidden" name="allowance_report_preview" value="1">

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
		<button type="submit" class="button button-primary">Xem trước định mức</button>
	</form>

	<?php if ( $allowance_report_error !== '' ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $allowance_report_error ); ?></p></div>
	<?php elseif ( is_array( $allowance_report ) ) : ?>
		<?php $summary = $allowance_report['summary']; ?>
		<p>
			Đã xét <strong><?php echo esc_html( number_format_i18n( $summary['employee_count'] ) ); ?></strong> CNV;
			<strong><?php echo esc_html( number_format_i18n( $summary['with_quota'] ) ); ?></strong> CNV còn/có định mức;
			tổng số lượng <strong><?php echo esc_html( number_format_i18n( $summary['total_quantity'] ) ); ?></strong>.
		</p>

		<?php if ( ! empty( $allowance_report['warnings'] ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php echo esc_html( implode( ' ', array_slice( $allowance_report['warnings'], 0, 10 ) ) ); ?>
				<?php if ( count( $allowance_report['warnings'] ) > 10 ) : ?>
					<?php echo esc_html( ' Và ' . ( count( $allowance_report['warnings'] ) - 10 ) . ' cảnh báo khác.' ); ?>
				<?php endif; ?>
			</p></div>
		<?php endif; ?>

		<div
			id="ums-employee-allowance-report-grid"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $preview_rows ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $report_columns ) ); ?>"
		></div>
		<?php if ( count( $report_rows ) > count( $preview_rows ) ) : ?>
			<p class="description">Bảng xem trước hiển thị 500 dòng đầu; file Excel vẫn xuất đầy đủ <?php echo esc_html( number_format_i18n( count( $report_rows ) ) ); ?> dòng.</p>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:16px;">
			<?php wp_nonce_field( 'ums_export_employee_allowances' ); ?>
			<input type="hidden" name="action" value="ums_export_employee_allowances">
			<?php foreach ( array( 'report_year', 'report_month', 'search', 'department', 'team', 'cost_center_prefix', 'cost_center', 'position', 'quantity_mode', 'include_zero' ) as $field ) : ?>
				<input type="hidden" name="<?php echo esc_attr( strpos( $field, 'report_' ) === 0 ? $field : 'report_' . $field ); ?>" value="<?php echo esc_attr( is_bool( $allowance_report_filters[ $field ] ) ? ( $allowance_report_filters[ $field ] ? '1' : '0' ) : $allowance_report_filters[ $field ] ); ?>">
			<?php endforeach; ?>
			<button type="submit" class="button button-primary">Xuất file Excel</button>
		</form>
	<?php endif; ?>
</div>
