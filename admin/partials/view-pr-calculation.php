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

	<?php if ( ! $table_ready ) : ?>
		<div class="notice notice-error inline"><p>Chưa có master mã SAP. Hãy import đầy đủ cấu trúc trong <code>ums.sql</code> và dữ liệu tại menu Mã SAP đồng phục.</p></div>
	<?php endif; ?>

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
						<option value="4">Tháng 4</option>
						<option value="9">Tháng 9</option>
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
