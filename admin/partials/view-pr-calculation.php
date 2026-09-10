<?php
/**
 * Admin view: tính số lượng và xuất file PR.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ums-admin-wrap ums-pr-page">
	<h1 class="wp-heading-inline">UMS - Tính số lượng PR</h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $table_ready ) : ?>
		<div class="notice notice-error inline"><p>Chưa có đầy đủ master mã SAP hoặc bảng kết quả tính số lượng cấp phát. Hãy cập nhật <code>ums.sql</code>, import master SAP và chốt kết quả cấp phát trước khi lập PR.</p></div>
	<?php endif; ?>

	<div class="ums-panel">
		<h2>SL cấp phát đã chốt</h2>
		<?php if ( empty( $allocation_batches ) ) : ?>
			<div class="notice notice-info inline"><p>Chưa có kết quả số lượng cấp phát nào được chốt. Hãy thực hiện tại trang Sản phẩm &amp; Kho trước khi tính PR.</p></div>
		<?php else : ?>
			<form method="get" class="ums-filter-bar">
				<input type="hidden" name="page" value="tvn-ums-pr-calculation">
				<label>
					<span>Kết quả đang xem</span>
					<select name="allocation_batch_id" onchange="this.form.submit()">
						<?php foreach ( $allocation_batches as $batch ) : ?>
							<option value="<?php echo esc_attr( $batch['batch_id'] ); ?>" <?php selected( $selected_batch_id, $batch['batch_id'] ); ?>>
								<?php echo esc_html( sprintf( 'T%d/%d - %s', $batch['period_month'], $batch['calculation_year'], $batch['file_name'] ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<noscript><button type="submit" class="button">Xem</button></noscript>
			</form>

			<?php if ( $selected_batch ) : ?>
				<p>
					<strong><?php echo esc_html( sprintf( 'Kỳ T%d/%d', $selected_batch['period_month'], $selected_batch['calculation_year'] ) ); ?></strong>
					<?php echo esc_html( sprintf(
						' | File: %s | %s CNV | Đăng ký: %s | Cấp phát: %s | Chốt lúc: %s%s',
						$selected_batch['file_name'],
						number_format_i18n( $selected_batch['employee_count'] ),
						number_format_i18n( $selected_batch['requested_qty'] ),
						number_format_i18n( $selected_batch['allocated_qty'] ),
						$selected_batch['created_at'],
						! empty( $selected_batch['calculated_by_login'] ) ? ' bởi ' . $selected_batch['calculated_by_login'] : ''
					) ); ?>
				</p>
				<div class="ums-table-scroll" style="max-height:520px">
					<table class="widefat striped">
						<thead><tr><th>Loại đồng phục lên PR</th><th>Size</th><th>Số CNV</th><th>SL đăng ký</th><th>SL cấp phát</th></tr></thead>
						<tbody>
						<?php foreach ( $allocation_summary_rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['item_variant'] ?: 'Sản phẩm kho #' . $row['item_id'] ); ?></td>
								<td><?php echo esc_html( $row['size'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['employee_count'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['requested_quantity'] ) ); ?></td>
								<td><strong><?php echo esc_html( number_format_i18n( $row['allocated_quantity'] ) ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( empty( $allocation_summary_rows ) ) : ?>
							<tr><td colspan="5">Bản chốt chưa có dòng cấp phát.</td></tr>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<div class="ums-panel">
		<h2>Dữ liệu lập PR</h2>
		<form id="ums-pr-calculation-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'ums_export_pr' ); ?>
			<input type="hidden" name="action" value="ums_export_pr">
			<input type="hidden" name="pr_security" value="<?php echo esc_attr( wp_create_nonce( 'ums_pr_calculation' ) ); ?>">

			<div class="ums-pr-form-grid">
				<label>
					<span>File số lượng đặt dự phòng *</span>
					<input type="file" name="reserve_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
				</label>
				<label>
					<span>Năm lập PR *</span>
					<input type="number" name="pr_year" min="2000" max="2100" value="<?php echo esc_attr( $default_year ); ?>" required>
				</label>
				<label>
					<span>Kỳ lập PR *</span>
					<select name="period_month" required>
						<option value="4" <?php selected( $default_month, 4 ); ?>>Tháng 4</option>
						<option value="9" <?php selected( $default_month, 9 ); ?>>Tháng 9</option>
					</select>
				</label>
				<label>
					<span>Ngày giao hàng *</span>
					<input type="date" name="delivery_date" required>
				</label>
				<label>
					<span>Requesting section</span>
					<input type="text" name="requesting_section" maxlength="100">
				</label>
				<label>
					<span>Using section / Cost center</span>
					<input type="text" name="using_cost_center" maxlength="100">
				</label>
			</div>

			<div class="ums-pr-actions">
				<button type="button" id="ums-calculate-pr" class="button button-primary" <?php disabled( ! $table_ready ); ?>>Tính số lượng PR</button>
				<button type="submit" id="ums-export-pr" class="button" disabled>Xuất file PR</button>
				<span id="ums-pr-status" aria-live="polite"></span>
			</div>
		</form>
	</div>

	<div id="ums-pr-result-panel" class="ums-panel" hidden>
		<h2>Kết quả tính PR</h2>
		<div id="ums-pr-summary" class="ums-pr-summary"></div>
		<div id="ums-pr-messages"></div>
		<div id="ums-pr-result-grid"></div>
	</div>
</div>
