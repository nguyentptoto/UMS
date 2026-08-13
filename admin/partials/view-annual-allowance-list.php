<?php
/**
 * Giao diện quản lý định mức cấp phát hàng năm.
 *
 * Biến được chuẩn bị từ UMS_Admin::render_annual_allowance_page():
 * $rules, $filters, $inventory, $categories, $positions, $editing_rule, $form_values, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing = ! empty( $editing_rule );
$page_url   = admin_url( 'admin.php?page=tvn-ums-annual-allowances' );
$grid_rows  = array();

$build_item_label = function( $item ) {
	$label_parts = array();

	if ( ! empty( $item['parent_category_name'] ) ) {
		$label_parts[] = $item['parent_category_name'];
	}

	if ( ! empty( $item['category_name'] ) ) {
		$label_parts[] = $item['category_name'];
	} elseif ( ! empty( $item['item_type'] ) ) {
		$label_parts[] = $item['item_type'];
	}

	$label = implode( ' / ', $label_parts );

	if ( ! empty( $item['item_variant'] ) ) {
		$label .= ' - ' . $item['item_variant'];
	}

	if ( ! empty( $item['size'] ) ) {
		$label .= ' - Size ' . $item['size'];
	}

	return $label ?: 'Sản phẩm #' . absint( $item['item_id'] );
};

$category_map = array();
foreach ( $categories as $category ) {
	$category_map[ (int) $category['category_id'] ] = $category;
}

$build_category_label = function( $category ) use ( &$category_map ) {
	if ( empty( $category ) ) {
		return '';
	}

	$label     = $category['category_name'];
	$parent_id = isset( $category['parent_id'] ) ? absint( $category['parent_id'] ) : 0;

	if ( $parent_id > 0 && isset( $category_map[ $parent_id ] ) ) {
		$label = $category_map[ $parent_id ]['category_name'] . ' / ' . $label;
	}

	return $label;
};

$month_options = array();
for ( $month = 1; $month <= 12; $month++ ) {
	$month_options[ $month ] = 'Tháng ' . $month;
}

foreach ( $rules as $rule ) {
	$edit_url = add_query_arg(
		array(
			'page'         => 'tvn-ums-annual-allowances',
			'edit_rule_id' => absint( $rule['rule_id'] ),
		),
		admin_url( 'admin.php' )
	);
	$delete_url = wp_nonce_url(
		add_query_arg(
			array(
				'action'  => 'ums_delete_annual_allowance',
				'rule_id' => absint( $rule['rule_id'] ),
			),
			admin_url( 'admin-post.php' )
		),
		'ums_delete_annual_allowance_' . absint( $rule['rule_id'] )
	);
	$actions = '<a href="' . esc_url( $edit_url . '#ums-annual-allowance-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa định mức này?">Xóa</a>';
	if ( ! empty( $rule['source_batch_id'] ) ) {
		$actions = '<span>Quản lý bằng import</span>';
	}

	$monthly_quantities = json_decode( (string) $rule['monthly_quantities'], true );
	$monthly_quantities = is_array( $monthly_quantities ) ? $monthly_quantities : array();
	$month_labels       = array();

	foreach ( $month_options as $month => $month_label ) {
		$quantity = isset( $monthly_quantities[ $month ] ) ? absint( $monthly_quantities[ $month ] ) : 0;
		if ( $quantity > 0 ) {
			$month_labels[] = $month_label . ': ' . $quantity;
		}
	}

	$target_label = 'Toàn bộ';
	if ( $rule['target_type'] === 'position' ) {
		$position_text = trim( (string) ( $rule['position_code'] ?? '' ) . ' - ' . (string) ( $rule['position_name'] ?? '' ), ' -' );
		$target_label  = 'Chức vụ: ' . ( $position_text ?: '#' . absint( $rule['position_id'] ) );
	} elseif ( $rule['target_type'] === 'organization' ) {
		$target_parts = array_filter(
			array(
				$rule['department'] ?? '',
				$rule['team'] ?? '',
				$rule['cost_center'] ?? '',
				$rule['position_code'] ?? '',
			)
		);
		$target_label = implode( ' / ', $target_parts );
	}

	$apply_label = 'Sản phẩm: ' . $build_item_label( $rule );
	if ( isset( $rule['apply_type'] ) && $rule['apply_type'] === 'category' ) {
		$category_name = trim( (string) ( $rule['apply_parent_category_name'] ?? '' ) . ' / ' . (string) ( $rule['apply_category_name'] ?? '' ), ' /' );
		$apply_label   = 'Danh mục: ' . ( $category_name ?: '#' . absint( $rule['category_id'] ) );
	} elseif ( isset( $rule['apply_type'] ) && $rule['apply_type'] === 'product' ) {
		$apply_label = 'Sản phẩm: ' . ( $rule['item_variant'] ?? $rule['source_product_name'] ?? '' );
	}

	$scope_labels = array( 'annual' => 'Định kỳ', 'newcomer' => 'CNV mới', 'newcomer_september' => 'Cấp bù T9' );
	$grid_rows[] = array(
		'rule_scope'        => $scope_labels[ $rule['rule_scope'] ?? 'annual' ] ?? ( $rule['rule_scope'] ?? 'Định kỳ' ),
		'apply_label'        => $apply_label,
		'target_label'       => $target_label,
		'frequency'          => max( 1, absint( $rule['frequency_count'] ?? 1 ) ) . ' lần / ' . max( 1, absint( $rule['frequency_years'] ) ) . ' năm',
		'monthly_quantities' => empty( $month_labels ) ? '-' : implode( ', ', $month_labels ),
		'status'             => (int) $rule['is_active'] === 1 ? 'Đang áp dụng' : 'Ngừng áp dụng',
		'actions'            => $actions,
	);
}

$grid_columns = array(
	array( 'text' => 'Loại rule', 'datafield' => 'rule_scope', 'width' => '10%' ),
	array( 'text' => 'Áp dụng cho', 'datafield' => 'apply_label', 'width' => '21%' ),
	array( 'text' => 'Đối tượng', 'datafield' => 'target_label', 'width' => '17%' ),
	array( 'text' => 'Tần suất', 'datafield' => 'frequency', 'width' => '12%' ),
	array( 'text' => 'Số lượng theo tháng', 'datafield' => 'monthly_quantities', 'width' => '18%' ),
	array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '10%' ),
	array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '12%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
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
		<p class="description">Đọc các sheet Phát T4, Phát T9, New commer và Phát T9 - CNV mới. Dữ liệu chỉ được ghi sau bước xác nhận.</p>
	</div>

	<?php if ( is_array( $import_preview ) ) : ?>
		<div class="ums-panel ums-allowance-import-preview">
			<h2>Xem trước: <?php echo esc_html( $import_preview['file_name'] ); ?></h2>
			<p>
				Tổng <?php echo esc_html( number_format_i18n( $import_preview['summary']['total'] ) ); ?> rule;
				định kỳ <?php echo esc_html( number_format_i18n( $import_preview['summary']['annual'] ) ); ?>;
				CNV mới <?php echo esc_html( number_format_i18n( $import_preview['summary']['newcomer'] ) ); ?>;
				cấp bù tháng 9 <?php echo esc_html( number_format_i18n( $import_preview['summary']['newcomer_september'] ) ); ?>.
			</p>

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
							?>
							<tr>
								<td><?php echo esc_html( $source_product_name ); ?></td>
								<td>
									<select name="product_mapping[<?php echo esc_attr( $mapping_key ); ?>]" required>
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
		<h2>Danh sách định mức</h2>
		<form method="get" class="ums-filter-bar">
			<input type="hidden" name="page" value="tvn-ums-annual-allowances">

			<label>
				<span class="screen-reader-text">Tìm định mức</span>
				<input
					type="search"
					name="s"
					value="<?php echo esc_attr( $filters['search'] ); ?>"
					placeholder="Tìm danh mục, sản phẩm, biến thể, size"
				>
			</label>

			<label>
				<span class="screen-reader-text">Lọc kiểu áp dụng</span>
				<select name="apply_type">
					<option value="">Tất cả kiểu áp dụng</option>
					<option value="category" <?php selected( $filters['apply_type'], 'category' ); ?>>Theo danh mục</option>
					<option value="item" <?php selected( $filters['apply_type'], 'item' ); ?>>Theo sản phẩm</option>
					<option value="product" <?php selected( $filters['apply_type'], 'product' ); ?>>Sản phẩm mọi size (import)</option>
				</select>
			</label>

			<label>
				<span class="screen-reader-text">Lọc đối tượng</span>
				<select name="target_type">
					<option value="">Tất cả đối tượng</option>
					<option value="all" <?php selected( $filters['target_type'], 'all' ); ?>>Toàn bộ</option>
					<option value="position" <?php selected( $filters['target_type'], 'position' ); ?>>Theo chức vụ</option>
					<option value="organization" <?php selected( $filters['target_type'], 'organization' ); ?>>Theo sơ đồ tổ chức</option>
				</select>
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

		<div
			id="ums-annual-allowance-grid"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
		></div>
	</div>

	<div class="ums-panel" id="ums-annual-allowance-form">
		<h2><?php echo $is_editing ? 'Cập nhật định mức' : 'Thêm định mức'; ?></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
			<?php wp_nonce_field( 'ums_save_annual_allowance' ); ?>
			<input type="hidden" name="action" value="ums_save_annual_allowance">
			<input type="hidden" name="ums_annual_allowance[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
			<input type="hidden" name="ums_annual_allowance[rule_id]" value="<?php echo esc_attr( $form_values['rule_id'] ); ?>">

			<div class="ums-form-grid">
				<label>
					<span>Loại định mức <b>*</b></span>
					<select name="ums_annual_allowance[rule_scope]" data-ums-annual-rule-scope required>
						<option value="annual" <?php selected( $form_values['rule_scope'], 'annual' ); ?>>Định kỳ</option>
						<option value="newcomer" <?php selected( $form_values['rule_scope'], 'newcomer' ); ?>>CNV mới (New Comer)</option>
						<option value="newcomer_september" <?php selected( $form_values['rule_scope'], 'newcomer_september' ); ?>>Cấp bù tháng 9 cho CNV mới</option>
					</select>
				</label>

				<label>
					<span>Kiểu áp dụng <b>*</b></span>
					<select name="ums_annual_allowance[apply_type]" data-ums-annual-apply-type required>
						<option value="category" <?php selected( $form_values['apply_type'], 'category' ); ?>>Theo danh mục</option>
						<option value="product" <?php selected( $form_values['apply_type'], 'product' ); ?>>Theo sản phẩm (mọi size)</option>
						<option value="item" <?php selected( $form_values['apply_type'], 'item' ); ?>>Theo sản phẩm và size cố định</option>
					</select>
				</label>

				<label
					class="ums-field-wide ums-conditional-field"
					data-ums-annual-apply-field="category"
					<?php echo $form_values['apply_type'] === 'category' ? '' : 'hidden'; ?>
				>
					<span>Danh mục áp dụng</span>
					<select name="ums_annual_allowance[category_id]" <?php echo $form_values['apply_type'] === 'category' ? 'required' : 'disabled'; ?>>
						<option value="">Chọn khi kiểu áp dụng là danh mục</option>
						<?php foreach ( $categories as $category ) : ?>
							<option value="<?php echo esc_attr( $category['category_id'] ); ?>" <?php selected( (int) $form_values['category_id'], (int) $category['category_id'] ); ?>>
								<?php echo esc_html( $build_category_label( $category ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label
					class="ums-field-wide ums-conditional-field"
					data-ums-annual-apply-field="product"
					<?php echo $form_values['apply_type'] === 'product' ? '' : 'hidden'; ?>
				>
					<span>Sản phẩm áp dụng</span>
					<select name="ums_annual_allowance[product_group]" <?php echo $form_values['apply_type'] === 'product' ? 'required' : 'disabled'; ?>>
						<option value="">Chọn sản phẩm áp dụng cho toàn bộ size</option>
						<?php foreach ( $product_groups as $product_group ) : ?>
							<?php $product_value = absint( $product_group['category_id'] ) . '|' . $product_group['item_variant']; ?>
							<option value="<?php echo esc_attr( $product_value ); ?>" <?php selected( $form_values['product_group'], $product_value ); ?>>
								<?php echo esc_html( $product_group['category_name'] . ' / ' . $product_group['item_variant'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label
					class="ums-field-wide ums-conditional-field"
					data-ums-annual-apply-field="item"
					<?php echo $form_values['apply_type'] === 'item' ? '' : 'hidden'; ?>
				>
					<span>Sản phẩm cố định</span>
					<select name="ums_annual_allowance[item_id]" <?php echo $form_values['apply_type'] === 'item' ? 'required' : 'disabled'; ?>>
						<option value="">Chọn khi kiểu áp dụng là sản phẩm cố định</option>
						<?php foreach ( $inventory as $item ) : ?>
							<option value="<?php echo esc_attr( $item['item_id'] ); ?>" <?php selected( (int) $form_values['item_id'], (int) $item['item_id'] ); ?>>
								<?php echo esc_html( $build_item_label( $item ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<label>
					<span>Đối tượng áp dụng <b>*</b></span>
					<select name="ums_annual_allowance[target_type]" data-ums-annual-target-type required>
						<option value="all" <?php selected( $form_values['target_type'], 'all' ); ?>>Toàn bộ</option>
						<option value="position" <?php selected( $form_values['target_type'], 'position' ); ?>>Theo chức vụ</option>
						<option value="organization" <?php selected( $form_values['target_type'], 'organization' ); ?>>Theo sơ đồ tổ chức TVN</option>
					</select>
				</label>

				<label data-ums-annual-target-field="position" <?php echo $form_values['target_type'] === 'position' ? '' : 'hidden'; ?>>
					<span>Chức vụ áp dụng</span>
					<select name="ums_annual_allowance[position_id]" <?php echo $form_values['target_type'] === 'position' ? 'required' : 'disabled'; ?>>
						<option value="">Chọn khi đối tượng là chức vụ</option>
						<?php foreach ( $positions as $position ) : ?>
							<option value="<?php echo esc_attr( $position['position_id'] ); ?>" <?php selected( (int) $form_values['position_id'], (int) $position['position_id'] ); ?>>
								<?php echo esc_html( $position['position_code'] . ' - ' . $position['position_name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>

				<div class="ums-field-wide ums-form-grid" data-ums-annual-target-field="organization" <?php echo $form_values['target_type'] === 'organization' ? '' : 'hidden'; ?>>
					<label>
						<span>Phòng</span>
						<input type="text" name="ums_annual_allowance[department]" value="<?php echo esc_attr( $form_values['department'] ); ?>" list="ums-allowance-departments" placeholder="Để trống nếu áp dụng mọi phòng">
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
						<span>Vị trí</span>
						<input type="text" name="ums_annual_allowance[position_code]" value="<?php echo esc_attr( $form_values['position_code'] ); ?>" list="ums-allowance-positions" placeholder="Ví dụ: WK, FM, SV">
						<datalist id="ums-allowance-positions">
							<?php foreach ( $organization_positions as $position_code ) : ?>
								<option value="<?php echo esc_attr( $position_code ); ?>">
							<?php endforeach; ?>
						</datalist>
					</label>
				</div>

				<div class="ums-field-wide ums-form-grid" data-ums-annual-newcomer-fields <?php echo in_array( $form_values['rule_scope'], array( 'newcomer', 'newcomer_september' ), true ) ? '' : 'hidden'; ?>>
					<label>
						<span>Ngày vào từ <b>*</b></span>
						<input type="text" name="ums_annual_allowance[employment_start_md]" value="<?php echo esc_attr( $form_values['employment_start_md'] ); ?>" pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])" placeholder="MM-DD">
					</label>
					<label>
						<span>Ngày vào đến <b>*</b></span>
						<input type="text" name="ums_annual_allowance[employment_end_md]" value="<?php echo esc_attr( $form_values['employment_end_md'] ); ?>" pattern="(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])" placeholder="MM-DD">
					</label>
				</div>

				<label class="ums-field-wide">
					<span>Ghi chú điều kiện</span>
					<input type="text" name="ums_annual_allowance[eligibility_note]" value="<?php echo esc_attr( $form_values['eligibility_note'] ); ?>">
				</label>

				<label>
					<span>Độ ưu tiên</span>
					<input type="number" name="ums_annual_allowance[priority]" value="<?php echo esc_attr( $form_values['priority'] ); ?>" step="1" data-ums-annual-priority>
					<p class="description">Số lớn hơn được ưu tiên khi nhiều rule cùng khớp.</p>
				</label>

				<label>
					<span>Số lần <b>*</b></span>
					<input type="number" name="ums_annual_allowance[frequency_count]" value="<?php echo esc_attr( $form_values['frequency_count'] ); ?>" min="1" step="1" required>
				</label>

				<label>
					<span>Số năm <b>*</b></span>
					<input type="number" name="ums_annual_allowance[frequency_years]" value="<?php echo esc_attr( $form_values['frequency_years'] ); ?>" min="1" step="1" required data-ums-annual-frequency-years>
					<p class="description">Ví dụ: Số lần = 1, Số năm = 2 nghĩa là 1 lần / 2 năm.</p>
				</label>

				<div class="ums-field-wide">
					<span class="ums-field-label">Số lượng cấp phát theo tháng <b>*</b></span>
					<div class="ums-month-quantity-grid">
						<?php foreach ( $month_options as $month => $month_label ) : ?>
							<label>
								<span><?php echo esc_html( $month_label ); ?></span>
								<select name="ums_annual_allowance[monthly_quantities][<?php echo esc_attr( $month ); ?>]">
									<?php for ( $quantity = 0; $quantity <= 10; $quantity++ ) : ?>
										<option value="<?php echo esc_attr( $quantity ); ?>" <?php selected( (int) $form_values['monthly_quantities'][ $month ], $quantity ); ?>>
											<?php echo esc_html( $quantity ); ?>
										</option>
									<?php endfor; ?>
								</select>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
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
					<?php echo $is_editing ? 'Cập nhật định mức' : 'Thêm định mức'; ?>
				</button>
				<?php if ( $is_editing ) : ?>
					<a href="<?php echo esc_url( $page_url . '#ums-annual-allowance-form' ); ?>" class="button">Hủy sửa</a>
				<?php endif; ?>
			</p>
		</form>
	</div>
</div>
