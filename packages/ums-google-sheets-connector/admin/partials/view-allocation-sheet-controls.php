<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
		<p>Chọn nhà máy và kỳ cấp để đọc đúng Google Sheet đã cấu hình. Hệ thống áp dụng định mức, giới hạn số đăng ký vượt mức và tạo bản xem trước trước khi chốt cho PR; thao tác này không xuất hoặc trừ tồn kho.</p>
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
				data-sync-session="<?php echo esc_attr( wp_generate_password( 24, false, false ) ); ?>"
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
