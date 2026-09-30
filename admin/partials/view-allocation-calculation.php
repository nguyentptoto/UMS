<?php
/**
 * Giao diện tính số lượng cấp phát định kỳ từ Google Sheet hoặc Excel dự phòng.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap ums-admin-wrap ums-allocation-page">
	<h1 class="wp-heading-inline">UMS - Tính số lượng cấp phát</h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ums-panel" id="ums-allocation-calculation">
		<h2>Tính số lượng cấp phát</h2>
		<p>Chọn nhà máy và kỳ cấp để đọc đúng Google Sheet đã cấu hình. Hệ thống áp dụng định mức, giới hạn số đăng ký vượt mức và tạo bản xem trước trước khi chốt cho PR; thao tác này không xuất hoặc trừ tồn kho.</p>

		<?php if ( ! $allocation_calculation_ready ) : ?>
			<div class="notice notice-error inline"><p>Cơ sở dữ liệu chưa hỗ trợ kết quả cấp phát theo nhà máy. Hãy cập nhật phần SQL tương ứng trong <code>ums.sql</code>.</p></div>
		<?php endif; ?>

		<form class="ums-inline-form" id="ums-allocation-sheet-form">
			<label>Nhà máy
				<select name="factory_code" id="ums-allocation-factory">
					<?php foreach ( $factories as $factory_code => $factory_name ) : ?>
						<option value="<?php echo esc_attr( $factory_code ); ?>" <?php selected( $selected_factory_code, $factory_code ); ?>><?php echo esc_html( $factory_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Năm cấp <input type="number" name="allocation_year" id="ums-allocation-year" value="<?php echo esc_attr( current_time( 'Y' ) ); ?>" min="2000" max="2100" required></label>
			<label>Kỳ cấp
				<select name="allocation_month" id="ums-allocation-month">
					<option value="4">Tháng 4</option>
					<option value="9" selected>Tháng 9</option>
				</select>
			</label>
			<button
				type="button"
				class="button button-primary"
				id="ums-start-allocation-sheet-sync"
				data-apps-script-url="<?php echo esc_attr( $allocation_sheet_apps_script_url ); ?>"
				data-rest-endpoint="<?php echo esc_attr( $allocation_sheet_rest_endpoint ); ?>"
				data-sync-token="<?php echo esc_attr( $allocation_sheet_sync_token ); ?>"
				data-sources="<?php echo esc_attr( wp_json_encode( $allocation_sheet_sources ) ); ?>"
				<?php disabled( ! $allocation_calculation_ready || $allocation_sheet_apps_script_url === '' ); ?>
			>Đọc dữ liệu từ Google Sheet</button>
		</form>

		<?php if ( $allocation_sheet_apps_script_url === '' ) : ?>
			<div class="notice notice-warning inline"><p>Chưa cấu hình Apps Script Web App URL cấp phát. Mở mục <strong>Cấu hình 6 nguồn Google Sheet</strong> bên dưới để thiết lập.</p></div>
		<?php endif; ?>

		<div class="ums-sync-log" id="ums-allocation-sheet-log" aria-live="polite">
			<div class="ums-sync-log-line">Sẵn sàng đọc tab <strong><?php echo esc_html( UMS_Allocation_Sheet_Sync::DEFAULT_SHEET_NAME ); ?></strong> theo nhà máy và kỳ đã chọn.</div>
		</div>

		<details class="ums-allocation-source-settings">
			<summary>Cấu hình 6 nguồn Google Sheet</summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ums_save_allocation_sheet_sources' ); ?>
				<input type="hidden" name="action" value="ums_save_allocation_sheet_sources">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ums-allocation-apps-script-url">Apps Script Web App URL cấp phát</label></th>
						<td>
							<input type="url" id="ums-allocation-apps-script-url" name="allocation_apps_script_url" class="large-text code" value="<?php echo esc_attr( $allocation_sheet_apps_script_url ); ?>" placeholder="https://script.google.com/macros/s/.../exec">
							<p class="description">URL này chỉ dùng cho sáu Sheet cấp phát, tách riêng với URL đồng bộ Sơ đồ tổ chức.</p>
						</td>
					</tr>
					<tr><th scope="row">REST Endpoint cấp phát</th><td><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $allocation_sheet_rest_endpoint ); ?>"></td></tr>
					<tr><th scope="row">X-Sync-Token</th><td><input type="text" class="large-text code" readonly value="<?php echo esc_attr( $allocation_sheet_sync_token ); ?>"></td></tr>
				</table>

				<table class="widefat striped">
					<thead><tr><th>Nhà máy</th><th>Kỳ</th><th>Link Google Sheet</th><th>Tên tab</th></tr></thead>
					<tbody>
					<?php foreach ( $factories as $factory_code => $factory_name ) : ?>
						<?php foreach ( array( 4, 9 ) as $period_month ) : ?>
							<?php $source = $allocation_sheet_sources[ $factory_code ][ $period_month ]; ?>
							<tr>
								<td><?php echo esc_html( $factory_name ); ?></td>
								<td>T<?php echo esc_html( $period_month ); ?></td>
								<td><input type="url" class="large-text code" name="allocation_sheet_sources[<?php echo esc_attr( $factory_code ); ?>][<?php echo esc_attr( $period_month ); ?>][url]" value="<?php echo esc_attr( $source['url'] ); ?>" placeholder="https://docs.google.com/spreadsheets/d/..."></td>
								<td><input type="text" name="allocation_sheet_sources[<?php echo esc_attr( $factory_code ); ?>][<?php echo esc_attr( $period_month ); ?>][sheet_name]" value="<?php echo esc_attr( $source['sheet_name'] ); ?>"></td>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( 'Lưu nguồn Google Sheet' ); ?>
			</form>
		</details>

		<details class="ums-allocation-excel-fallback">
			<summary>Import Excel dự phòng</summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
				<?php wp_nonce_field( 'ums_preview_allocation_calculation' ); ?>
				<input type="hidden" name="action" value="ums_preview_allocation_calculation">
				<input type="hidden" name="factory_code" value="<?php echo esc_attr( $selected_factory_code ); ?>">
				<label>Năm cấp <input type="number" name="allocation_year" value="<?php echo esc_attr( current_time( 'Y' ) ); ?>" min="2000" max="2100" required></label>
				<label>Kỳ cấp <select name="allocation_month"><option value="4">Tháng 4</option><option value="9" selected>Tháng 9</option></select></label>
				<input type="file" name="ums_allocation_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
				<button type="submit" class="button">Kiểm tra file Excel</button>
			</form>
		</details>
	</div>

	<?php if ( is_array( $allocation_preview ) ) : ?>
		<div class="ums-panel ums-allocation-calculation-preview">
			<h2>Kết quả tính số lượng cấp phát: <?php echo esc_html( $allocation_preview['file_name'] ); ?></h2>
			<p><?php echo esc_html( sprintf(
				'%s · Kỳ T%d/%d: %d CNV, đăng ký %s, được cấp %s qua %d dòng; %d lỗi và %d cảnh báo.',
				$factories[ $allocation_preview['factory_code'] ?? '' ] ?? 'Dữ liệu cũ/chưa tách nhà máy',
				$allocation_preview['month'],
				$allocation_preview['year'],
				$allocation_preview['employee_count'],
				number_format_i18n( $allocation_preview['requested_quantity'] ?? 0 ),
				number_format_i18n( $allocation_preview['total_quantity'] ),
				count( $allocation_preview['details'] ),
				count( $allocation_preview['errors'] ),
				count( $allocation_preview['warnings'] ?? array() )
			) ); ?></p>

			<?php if ( ! empty( $allocation_preview['errors'] ) ) : ?>
				<div class="notice notice-error inline"><p><strong>Không thể hoàn tất phép tính:</strong></p></div>
				<div class="ums-table-scroll" style="max-height:520px">
					<table class="widefat striped"><thead><tr><th>STT</th><th>Nội dung lỗi</th></tr></thead><tbody>
					<?php foreach ( $allocation_preview['errors'] as $index => $error ) : ?>
						<tr><td><?php echo esc_html( $index + 1 ); ?></td><td><?php echo esc_html( $error ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $allocation_preview['warnings'] ) ) : ?>
				<div class="notice notice-warning inline"><p><strong>Cảnh báo và điều chỉnh tự động:</strong> Các dòng hợp lệ khác vẫn được tính.</p></div>
				<div class="ums-table-scroll" style="max-height:520px">
					<table class="widefat striped"><thead><tr><th>STT</th><th>Nội dung cảnh báo</th></tr></thead><tbody>
					<?php foreach ( $allocation_preview['warnings'] as $index => $warning ) : ?>
						<tr><td><?php echo esc_html( $index + 1 ); ?></td><td><?php echo esc_html( $warning ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>

			<?php if ( empty( $allocation_preview['errors'] ) && ! empty( $allocation_preview['details'] ) ) : ?>
				<?php
				$allocation_summary = array();
				foreach ( $allocation_preview['details'] as $detail ) {
					$key = absint( $detail['item_id'] );
					if ( ! isset( $allocation_summary[ $key ] ) ) {
						$allocation_summary[ $key ] = array( 'product' => $detail['product'], 'size' => $detail['size'], 'requested' => 0, 'quantity' => 0 );
					}
					$allocation_summary[ $key ]['requested'] += absint( $detail['requested_quantity'] ?? $detail['quantity'] );
					$allocation_summary[ $key ]['quantity']  += absint( $detail['quantity'] );
				}
				?>
				<div class="ums-table-scroll" style="max-height:520px">
					<table class="widefat striped"><thead><tr><th>Loại sản phẩm</th><th>Size</th><th>SL đăng ký</th><th>SL cấp phát</th></tr></thead><tbody>
					<?php foreach ( $allocation_summary as $summary_row ) : ?>
						<tr><td><?php echo esc_html( $summary_row['product'] ); ?></td><td><?php echo esc_html( $summary_row['size'] ); ?></td><td><?php echo esc_html( number_format_i18n( $summary_row['requested'] ) ); ?></td><td><?php echo esc_html( number_format_i18n( $summary_row['quantity'] ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</div>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ums_save_allocation_calculation' ); ?>
					<input type="hidden" name="action" value="ums_save_allocation_calculation">
					<input type="hidden" name="allocation_preview_token" value="<?php echo esc_attr( $allocation_preview_token ); ?>">
					<p class="submit"><button type="submit" class="button button-primary" <?php disabled( ! $allocation_calculation_ready ); ?>>Chốt kết quả tính cho PR</button></p>
				</form>
			<?php elseif ( empty( $allocation_preview['errors'] ) ) : ?>
				<div class="notice notice-info inline"><p>Không có dòng cấp phát hợp lệ để chốt kết quả.</p></div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
