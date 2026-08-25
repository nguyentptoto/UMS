<?php
/**
 * Giao diện quản lý định mức cấp phát hàng năm.
 *
 * Biến được chuẩn bị từ UMS_Admin::render_annual_allowance_page():
 * $rules, $filters, $product_groups, $editing_rule, $form_values, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing = ! empty( $editing_rule );
$page_url   = admin_url( 'admin.php?page=tvn-ums-annual-allowances' );
$grid_rows_april     = array();
$grid_rows_september = array();
$grid_rows_newcomer  = array();
$grid_rows_newcomer_september = array();
$grid_rows_newcomer_shoe_april = array();
$grid_rows_newcomer_shoe_september = array();
$matrix_rows         = array();
$normalize_product = function( $value ) {
	return strtolower( remove_accents( preg_replace( '/\s+/u', ' ', trim( (string) $value ) ) ) );
};
$format_month_day = function( $value ) {
	if ( ! preg_match( '/^(\d{2})-(\d{2})$/', (string) $value, $matches ) ) {
		return '';
	}
	return $matches[2] . '/' . $matches[1];
};
$product_fields = array();
foreach ( $allowance_product_columns as $column => $product_name ) {
	$product_fields[ $normalize_product( $product_name ) ] = 'product_' . strtolower( $column );
}

foreach ( $rules as $rule ) {
	$monthly_quantities = json_decode( (string) $rule['monthly_quantities'], true );
	$monthly_quantities = is_array( $monthly_quantities ) ? $monthly_quantities : array();
	$rule_scope = isset( $rule['rule_scope'] ) ? (string) $rule['rule_scope'] : 'annual';
	if ( $rule_scope !== 'annual' ) {
		$period_start = (string) ( $rule['employment_start_md'] ?? '' );
		$period_end   = (string) ( $rule['employment_end_md'] ?? '' );
		if ( $rule_scope === 'newcomer' ) {
			$quantity = empty( $monthly_quantities ) ? 0 : max( array_map( 'absint', $monthly_quantities ) );
		} elseif ( $rule_scope === 'newcomer_shoe_april' ) {
			$quantity = absint( $monthly_quantities[4] ?? 0 );
		} else {
			$quantity = absint( $monthly_quantities[9] ?? 0 );
		}
		$scope_labels = array(
			'newcomer' => 'Cấp ban đầu',
			'newcomer_september' => 'T9 tổng quát',
			'newcomer_september_override' => 'T9 theo cost center',
			'newcomer_shoe_april' => 'Giày T4 N+1',
			'newcomer_shoe_september' => 'Giày T9 N+1',
		);
		$newcomer_row = array(
			'rule_type' => $scope_labels[ $rule_scope ] ?? $rule_scope,
			'employment_period' => $format_month_day( $period_start ) . ' - ' . $format_month_day( $period_end ),
			'department' => (string) ( $rule['department'] ?? '' ),
			'team' => (string) ( $rule['team'] ?? '' ),
			'cost_center' => (string) ( $rule['cost_center'] ?? '' ),
			'position_code' => (string) ( $rule['position_code'] ?? '' ),
			'source_product_name' => (string) ( $rule['source_product_name'] ?: $rule['item_variant'] ),
			'ums_product_name' => (string) $rule['item_variant'],
			'quantity' => $quantity,
			'status' => (int) $rule['is_active'] === 1 ? 'Đang áp dụng' : 'Ngừng áp dụng',
		);
		if ( $rule_scope === 'newcomer' ) {
			$grid_rows_newcomer[] = $newcomer_row;
		} elseif ( $rule_scope === 'newcomer_shoe_april' ) {
			$grid_rows_newcomer_shoe_april[] = $newcomer_row;
		} elseif ( $rule_scope === 'newcomer_shoe_september' ) {
			$grid_rows_newcomer_shoe_september[] = $newcomer_row;
		} else {
			$grid_rows_newcomer_september[] = $newcomer_row;
		}
		continue;
	}
	$row_key = hash( 'sha256', implode( '|', array( $rule['department'] ?? '', $rule['team'] ?? '', $rule['cost_center'] ?? '', $rule['position_code'] ?? '', $rule['is_active'] ?? 1 ) ) );
	if ( ! isset( $matrix_rows[ $row_key ] ) ) {
		$matrix_rows[ $row_key ] = array(
			'department' => (string) ( $rule['department'] ?? '' ), 'team' => (string) ( $rule['team'] ?? '' ),
			'cost_center' => (string) ( $rule['cost_center'] ?? '' ), 'position_code' => (string) ( $rule['position_code'] ?? '' ),
			'note' => (string) ( $rule['eligibility_note'] ?? '' ),
			'status' => (int) $rule['is_active'] === 1 ? 'Đang áp dụng' : 'Ngừng áp dụng',
		);
		foreach ( $allowance_product_columns as $column => $unused ) {
			$product_field = 'product_' . strtolower( $column );
			$matrix_rows[ $row_key ][ $product_field . '_april' ]     = '-';
			$matrix_rows[ $row_key ][ $product_field . '_september' ] = '-';
		}
	}
	$source_product = ! empty( $rule['source_product_name'] ) ? (string) $rule['source_product_name'] : (string) $rule['item_variant'];
	$normalized_product = $normalize_product( $source_product );
	if ( isset( $product_fields[ $normalized_product ] ) ) {
		$product_field = $product_fields[ $normalized_product ];
		$matrix_rows[ $row_key ][ $product_field . '_april' ]     = absint( $monthly_quantities[4] ?? 0 );
		$matrix_rows[ $row_key ][ $product_field . '_september' ] = absint( $monthly_quantities[9] ?? 0 );
	}
}

foreach ( $matrix_rows as $matrix_row ) {
	$april_row     = $matrix_row;
	$september_row = $matrix_row;
	foreach ( $allowance_product_columns as $column => $unused ) {
		$product_field = 'product_' . strtolower( $column );
		$april_row[ $product_field ]     = $matrix_row[ $product_field . '_april' ];
		$september_row[ $product_field ] = $matrix_row[ $product_field . '_september' ];
		unset( $april_row[ $product_field . '_april' ], $april_row[ $product_field . '_september' ] );
		unset( $september_row[ $product_field . '_april' ], $september_row[ $product_field . '_september' ] );
	}
	$grid_rows_april[]     = $april_row;
	$grid_rows_september[] = $september_row;
}

$grid_columns = array(
	array( 'text' => 'Bộ phận', 'datafield' => 'department', 'width' => 220, 'pinned' => true ),
	array( 'text' => 'Nhóm', 'datafield' => 'team', 'width' => 180, 'pinned' => true ),
	array( 'text' => 'Code center', 'datafield' => 'cost_center', 'width' => 120, 'pinned' => true ),
	array( 'text' => 'Vị trí', 'datafield' => 'position_code', 'width' => 80, 'pinned' => true ),
);
foreach ( $allowance_product_columns as $column => $product_name ) {
	$grid_columns[] = array( 'text' => $product_name, 'datafield' => 'product_' . strtolower( $column ), 'width' => 145, 'cellsalign' => 'center' );
}
$grid_columns[] = array( 'text' => 'Lưu ý', 'datafield' => 'note', 'width' => 220 );
$grid_columns[] = array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => 110 );
$newcomer_grid_columns = array(
	array( 'text' => 'Loại định mức', 'datafield' => 'rule_type', 'width' => 150, 'pinned' => true ),
	array( 'text' => 'Ngày vào công ty', 'datafield' => 'employment_period', 'width' => 145, 'pinned' => true ),
	array( 'text' => 'Bộ phận', 'datafield' => 'department', 'width' => 230 ),
	array( 'text' => 'Nhóm', 'datafield' => 'team', 'width' => 190 ),
	array( 'text' => 'Code center', 'datafield' => 'cost_center', 'width' => 125 ),
	array( 'text' => 'Vị trí', 'datafield' => 'position_code', 'width' => 80 ),
	array( 'text' => 'Sản phẩm Excel', 'datafield' => 'source_product_name', 'width' => 210 ),
	array( 'text' => 'Sản phẩm UMS', 'datafield' => 'ums_product_name', 'width' => 210 ),
	array( 'text' => 'Số lượng', 'datafield' => 'quantity', 'width' => 90, 'cellsalign' => 'center' ),
	array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => 115 ),
);
?>

<div class="wrap ums-admin-wrap">
	<h1 class="wp-heading-inline">UMS - Định mức cấp phát hàng năm</h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ums-panel" id="ums-annual-allowance-import">
		<h2>Import định mức từ Excel</h2>
		<?php if ( ! $allowance_import_ready ) : ?>
			<div class="notice notice-warning inline"><p>Database chưa được nâng cấp theo cấu trúc định mức linh hoạt trong ums.sql.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-filter-bar">
			<?php wp_nonce_field( 'ums_preview_annual_allowance_import' ); ?>
			<input type="hidden" name="action" value="ums_preview_annual_allowance_import">
			<input type="file" name="ums_allowance_import_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
			<button type="submit" class="button button-primary" <?php disabled( ! $allowance_import_ready ); ?>>Đọc và xem trước</button>
		</form>
		<p class="description">Có thể tải một hoặc nhiều sheet trong mỗi lần import. Hệ thống chỉ đọc sheet đang hiển thị, bỏ qua sheet ẩn và giữ nguyên các kỳ chưa tải lên.</p>
	</div>

	<?php if ( is_array( $import_preview ) ) : ?>
		<div class="ums-panel ums-allowance-import-preview">
			<h2>Xem trước: <?php echo esc_html( $import_preview['file_name'] ); ?></h2>
			<p>
				Tổng <?php echo esc_html( number_format_i18n( $import_preview['summary']['total'] ) ); ?> bản ghi:
				<?php echo esc_html( number_format_i18n( $import_preview['summary']['annual'] ?? 0 ) ); ?> định mức thường,
				<?php echo esc_html( number_format_i18n( $import_preview['summary']['newcomer'] ?? 0 ) ); ?> cấp ban đầu,
				<?php echo esc_html( number_format_i18n( ( $import_preview['summary']['newcomer_september'] ?? 0 ) + ( $import_preview['summary']['newcomer_september_override'] ?? 0 ) ) ); ?> cấp bổ sung T9,
				<?php echo esc_html( number_format_i18n( ( $import_preview['summary']['newcomer_shoe_april'] ?? 0 ) + ( $import_preview['summary']['newcomer_shoe_september'] ?? 0 ) ) ); ?> định mức giày N+1 và
				<?php echo esc_html( number_format_i18n( $import_preview['summary']['matrix'] ?? 0 ) ); ?> điều kiện ma trận.
			</p>
			<?php if ( ! empty( $import_preview['processed_sheets'] ) ) : ?>
				<p class="description">Sheet đã đọc: <?php echo esc_html( implode( ', ', $import_preview['processed_sheets'] ) ); ?>.</p>
			<?php endif; ?>

			<?php if ( ! empty( $import_preview['errors'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( implode( ' ', $import_preview['errors'] ) ); ?></p></div>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ums_confirm_annual_allowance_import' ); ?>
					<input type="hidden" name="action" value="ums_confirm_annual_allowance_import">
					<input type="hidden" name="preview_token" value="<?php echo esc_attr( $preview_token ); ?>">
					<table class="widefat striped">
						<thead><tr><th>Sản phẩm trong Excel</th><th>Sản phẩm trong UMS</th></tr></thead>
						<tbody>
						<?php foreach ( $import_preview['product_names'] as $source_product_name ) : ?>
							<?php
							$source_normalized = strtolower( remove_accents( preg_replace( '/\s+/u', ' ', trim( $source_product_name ) ) ) );
							$mapping_key       = hash( 'sha256', $source_product_name );
							$is_mapping_required = in_array( $source_product_name, $import_preview['used_product_names'] ?? $import_preview['product_names'], true );
							?>
							<tr>
								<td><?php echo esc_html( $source_product_name ); ?></td>
								<td>
									<select name="product_mapping[<?php echo esc_attr( $mapping_key ); ?>]" <?php echo $is_mapping_required ? 'required' : ''; ?>>
										<option value="">Chọn sản phẩm tương ứng</option>
										<?php foreach ( $product_groups as $product_group ) : ?>
											<?php
											$option_value = absint( $product_group['category_id'] ) . '|' . $product_group['item_variant'];
											$target_normalized = strtolower( remove_accents( preg_replace( '/\s+/u', ' ', trim( $product_group['item_variant'] ) ) ) );
											?>
											<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $source_normalized, $target_normalized ); ?>>
												<?php echo esc_html( $product_group['category_name'] . ' / ' . $product_group['item_variant'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="submit"><button type="submit" class="button button-primary">Xác nhận import</button></p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="ums-panel">
		<h2>Danh sách định mức cấp phát</h2>
		<p class="description">Dữ liệu Tháng 4 và Tháng 9 được tách thành hai bảng để dễ theo dõi.</p>
		<form method="get" class="ums-filter-bar">
			<input type="hidden" name="page" value="tvn-ums-annual-allowances">

			<label>
				<span class="screen-reader-text">Tìm định mức</span>
				<input
					type="search"
					name="s"
					value="<?php echo esc_attr( $filters['search'] ); ?>"
					placeholder="Tìm bộ phận, nhóm, cost center, vị trí, sản phẩm"
				>
			</label>

			<label>
				<span class="screen-reader-text">Lọc trạng thái</span>
				<select name="status">
					<option value="">Tất cả trạng thái</option>
					<option value="active" <?php selected( $filters['status'], 'active' ); ?>>Đang áp dụng</option>
					<option value="inactive" <?php selected( $filters['status'], 'inactive' ); ?>>Ngừng áp dụng</option>
				</select>
			</label>

			<button type="submit" class="button">Lọc</button>
			<a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
		</form>

		<h2>1. Định mức cấp phát Tháng 4</h2>
		<div
			id="ums-annual-allowance-grid-april"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows_april ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
		></div>

		<h2 style="margin-top:28px;">2. Định mức cấp phát Tháng 9</h2>
		<div
			id="ums-annual-allowance-grid-september"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows_september ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
		></div>

		<h2 style="margin-top:28px;">3. Định mức cấp ban đầu cho CNV mới</h2>
		<div
			id="ums-annual-allowance-grid-newcomer"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows_newcomer ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $newcomer_grid_columns ) ); ?>"
		></div>

		<h2 style="margin-top:28px;">4. Định mức bổ sung Tháng 9 cho CNV mới</h2>
		<div
			id="ums-annual-allowance-grid-newcomer-september"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows_newcomer_september ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $newcomer_grid_columns ) ); ?>"
		></div>

		<h2 style="margin-top:28px;">5. Định mức giày Tháng 4 N+1 cho CNV mới</h2>
		<div
			id="ums-annual-allowance-grid-newcomer-shoe-april"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows_newcomer_shoe_april ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $newcomer_grid_columns ) ); ?>"
		></div>

		<h2 style="margin-top:28px;">6. Định mức giày Tháng 9 N+1 cho CNV mới</h2>
		<div
			id="ums-annual-allowance-grid-newcomer-shoe-september"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows_newcomer_shoe_september ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $newcomer_grid_columns ) ); ?>"
		></div>
	</div>

	<div class="ums-panel" id="ums-annual-allowance-form">
		<h2>Thiết lập ma trận định mức</h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
			<?php wp_nonce_field( 'ums_save_annual_allowance' ); ?>
			<input type="hidden" name="action" value="ums_save_annual_allowance">
			<div class="ums-form-grid">
				<div class="ums-field-wide ums-form-grid">
					<label>
						<span>Bộ phận <b>*</b></span>
						<input type="text" name="ums_annual_allowance[department]" value="<?php echo esc_attr( $form_values['department'] ); ?>" list="ums-allowance-departments" required>
						<datalist id="ums-allowance-departments">
							<?php foreach ( $organization_departments as $department ) : ?>
								<option value="<?php echo esc_attr( $department ); ?>">
							<?php endforeach; ?>
						</datalist>
					</label>
					<label>
						<span>Nhóm</span>
						<input type="text" name="ums_annual_allowance[team]" value="<?php echo esc_attr( $form_values['team'] ); ?>" list="ums-allowance-teams" placeholder="Để trống nếu áp dụng mọi nhóm">
						<datalist id="ums-allowance-teams">
							<?php foreach ( $organization_teams as $team ) : ?>
								<option value="<?php echo esc_attr( $team ); ?>">
							<?php endforeach; ?>
						</datalist>
					</label>
					<label>
						<span>Mã cost center</span>
						<input type="text" name="ums_annual_allowance[cost_center]" value="<?php echo esc_attr( $form_values['cost_center'] ); ?>" list="ums-allowance-cost-centers">
						<datalist id="ums-allowance-cost-centers">
							<?php foreach ( $organization_cost_centers as $cost_center ) : ?>
								<option value="<?php echo esc_attr( $cost_center ); ?>">
							<?php endforeach; ?>
						</datalist>
					</label>
					<label>
						<span>Vị trí <b>*</b></span>
						<input type="text" name="ums_annual_allowance[position_code]" value="<?php echo esc_attr( $form_values['position_code'] ); ?>" list="ums-allowance-positions" placeholder="Ví dụ: WK, FM, SV" required>
						<datalist id="ums-allowance-positions">
							<?php foreach ( $organization_positions as $position_code ) : ?>
								<option value="<?php echo esc_attr( $position_code ); ?>">
							<?php endforeach; ?>
						</datalist>
					</label>
				</div>

				<label class="ums-field-wide">
					<span>Lưu ý</span>
					<input type="text" name="ums_annual_allowance[eligibility_note]" value="<?php echo esc_attr( $form_values['eligibility_note'] ); ?>">
				</label>
			</div>

			<div style="overflow-x:auto; width:100%; margin-top:16px;">
				<table class="widefat striped" style="min-width:3900px; table-layout:fixed;">
					<thead>
						<tr>
							<th style="width:120px;">Kỳ / Sản phẩm</th>
							<?php foreach ( $allowance_product_columns as $column => $product_name ) : ?>
								<th style="width:145px;"><?php echo esc_html( $column . '. ' . $product_name ); ?></th>
							<?php endforeach; ?>
						</tr>
					</thead>
					<tbody>
						<tr>
							<th>Sản phẩm UMS</th>
							<?php foreach ( $allowance_product_columns as $column => $product_name ) : ?>
								<?php
								$product_key       = hash( 'sha256', $product_name );
								$product_normalized = $normalize_product( $product_name );
								?>
								<td>
									<select name="ums_annual_allowance[product_rules][<?php echo esc_attr( $product_key ); ?>][mapping]" style="max-width:140px;">
										<option value="">Chưa ánh xạ</option>
										<?php foreach ( $product_groups as $product_group ) : ?>
											<?php
											$mapping_value = absint( $product_group['category_id'] ) . '|' . $product_group['item_variant'];
											$mapping_normalized = $normalize_product( $product_group['item_variant'] );
											?>
											<option value="<?php echo esc_attr( $mapping_value ); ?>" <?php selected( $product_normalized, $mapping_normalized ); ?>><?php echo esc_html( $product_group['item_variant'] ); ?></option>
										<?php endforeach; ?>
									</select>
								</td>
							<?php endforeach; ?>
						</tr>
						<?php foreach ( array( 4 => 'Tháng 4', 9 => 'Tháng 9' ) as $month => $month_label ) : ?>
							<tr>
								<th><?php echo esc_html( $month_label ); ?></th>
								<?php foreach ( $allowance_product_columns as $product_name ) : ?>
									<?php $product_key = hash( 'sha256', $product_name ); ?>
									<td><input type="number" name="ums_annual_allowance[product_rules][<?php echo esc_attr( $product_key ); ?>][<?php echo esc_attr( $month ); ?>]" value="0" min="0" max="10" step="1" style="width:70px;"></td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<fieldset class="ums-checkboxes">
				<legend>Trạng thái</legend>
				<label>
					<input type="checkbox" name="ums_annual_allowance[is_active]" value="1" <?php checked( (int) $form_values['is_active'], 1 ); ?>>
					Đang áp dụng
				</label>
			</fieldset>

			<p class="submit">
				<button type="submit" class="button button-primary">
					Lưu ma trận định mức
				</button>
			</p>
		</form>
	</div>
</div>
