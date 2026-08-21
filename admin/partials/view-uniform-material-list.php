<?php
/**
 * Admin view for the uniform SAP material master.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_url = admin_url( 'admin.php?page=tvn-ums-uniform-materials' );
$preview_product_names = is_array( $import_preview ) && isset( $import_preview['product_names'] )
	? $import_preview['product_names']
	: ( is_array( $import_preview ) ? array_values( array_unique( array_column( $import_preview['rows'], 'product_name' ) ) ) : array() );
$grid_rows = array();
$active_count = 0;
$duplicate_count = 0;
$inactive_count = 0;

foreach ( $materials as $material ) {
	$is_active = (int) $material['is_active'] === 1;
	if ( ! $is_active ) {
		$status_label = 'Ngừng sử dụng';
		$inactive_count++;
	} elseif ( $material['mapping_status'] === 'duplicate_sap' ) {
		$status_label = 'Mã SAP trùng';
		$active_count++;
		$duplicate_count++;
	} else {
		$status_label = 'Hợp lệ';
		$active_count++;
	}

	$grid_rows[] = array(
		'source_row' => (int) $material['source_row'],
		'sap_code' => (string) $material['sap_code'],
		'item_name' => (string) $material['item_name'],
		'product_name' => (string) $material['product_name'],
		'size' => (string) $material['size'],
		'inventory_product' => ! empty( $material['inventory_product_name'] )
			? (string) $material['inventory_product_name'] . ' / ' . (string) $material['inventory_size']
			: 'Chưa liên kết',
		'mapping_status' => $status_label,
		'updated_at' => (string) $material['updated_at'],
	);
}

$grid_columns = array(
	array( 'text' => 'Dòng GA', 'datafield' => 'source_row', 'width' => '6%', 'cellsalign' => 'right' ),
	array( 'text' => 'Mã SAP', 'datafield' => 'sap_code', 'width' => '11%' ),
	array( 'text' => 'Loại', 'datafield' => 'item_name', 'width' => '22%' ),
	array( 'text' => 'Loại đồng phục lên PR', 'datafield' => 'product_name', 'width' => '17%' ),
	array( 'text' => 'Size', 'datafield' => 'size', 'width' => '7%', 'cellsalign' => 'center' ),
	array( 'text' => 'Sản phẩm UMS / Size', 'datafield' => 'inventory_product', 'width' => '18%' ),
	array( 'text' => 'Trạng thái', 'datafield' => 'mapping_status', 'width' => '10%' ),
	array( 'text' => 'Cập nhật', 'datafield' => 'updated_at', 'width' => '9%' ),
);
?>

<div class="wrap ums-admin-wrap">
	<h1 class="wp-heading-inline">UMS - Mã SAP đồng phục</h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
	<?php endif; ?>

	<div class="ums-panel">
		<h2>Import dữ liệu từ file GA</h2>
		<p>UMS chỉ đọc sheet <strong>Mã đồng phục</strong> và bốn cột: Mã đồng phục, Loại, Loại đồng phục lên PR, Size. Các sheet khác trong workbook không bị xử lý.</p>
		<?php if ( ! $table_ready ) : ?>
			<div class="notice notice-warning inline"><p>Chưa có bảng master mã SAP. Hãy import cấu trúc mới trong <code>ums.sql</code> trước.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
			<?php wp_nonce_field( 'ums_preview_uniform_material_import' ); ?>
			<input type="hidden" name="action" value="ums_preview_uniform_material_import">
			<input type="file" name="ums_uniform_material_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
			<button type="submit" class="button button-primary" <?php disabled( ! $table_ready ); ?>>Đọc và xem trước</button>
		</form>
		<?php if ( $latest_batch ) : ?>
			<p class="description">
				Lần import gần nhất: <?php echo esc_html( $latest_batch['file_name'] ); ?>,
				<?php echo esc_html( $latest_batch['completed_at'] ); ?>,
				<?php echo esc_html( number_format_i18n( $latest_batch['total_rows'] ) ); ?> dòng.
			</p>
		<?php endif; ?>
	</div>

	<?php if ( is_array( $import_preview ) ) : ?>
		<div class="ums-panel">
			<h2>Xem trước: <?php echo esc_html( $import_preview['file_name'] ); ?></h2>
			<p>
				Sheet <?php echo esc_html( trim( $import_preview['sheet_name'] ) ); ?>:
				<?php echo esc_html( number_format_i18n( $import_preview['summary']['total'] ) ); ?> dòng,
				<?php echo esc_html( number_format_i18n( $import_preview['summary']['product_groups'] ) ); ?> nhóm sản phẩm,
				<?php echo esc_html( number_format_i18n( $import_preview['summary']['duplicate_rows'] ) ); ?> dòng có mã SAP trùng.
			</p>

			<?php if ( ! empty( $import_preview['errors'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( implode( ' ', array_slice( $import_preview['errors'], 0, 10 ) ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! empty( $import_preview['warnings'] ) ) : ?>
				<div class="notice notice-warning inline"><p><?php echo esc_html( implode( ' ', array_slice( $import_preview['warnings'], 0, 10 ) ) ); ?></p></div>
			<?php endif; ?>

			<div class="ums-table-scroll ums-sap-preview-table">
				<table class="widefat striped">
					<thead><tr><th>Dòng</th><th>Mã SAP</th><th>Loại</th><th>Loại lên PR</th><th>Size</th><th>Trạng thái</th></tr></thead>
					<tbody>
					<?php foreach ( $import_preview['rows'] as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row['source_row'] ); ?></td>
							<td><?php echo esc_html( $row['sap_code'] ); ?></td>
							<td><?php echo esc_html( $row['item_name'] ); ?></td>
							<td><?php echo esc_html( $row['product_name'] ); ?></td>
							<td><?php echo esc_html( $row['size'] ); ?></td>
							<td><?php echo esc_html( $row['mapping_status'] === 'duplicate_sap' ? 'Mã SAP trùng' : 'Hợp lệ cấu trúc' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( empty( $import_preview['errors'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ums_confirm_uniform_material_import' ); ?>
					<input type="hidden" name="action" value="ums_confirm_uniform_material_import">
					<input type="hidden" name="material_preview_token" value="<?php echo esc_attr( $preview_token ); ?>">
					<h3>Ánh xạ Loại đồng phục lên PR</h3>
					<p class="description">Mỗi loại trong file GA phải trỏ tới một Tên sản phẩm UMS. Khi xác nhận, size chưa có sẽ được tạo trong kho với tồn 0 và dùng đơn giá chung của sản phẩm.</p>
					<table class="widefat striped">
						<thead><tr><th>Loại đồng phục lên PR trong GA</th><th>Tên sản phẩm hiện có trong UMS</th></tr></thead>
						<tbody>
						<?php foreach ( $preview_product_names as $source_product_name ) : ?>
							<?php
							$mapping_key = hash( 'sha256', $source_product_name );
							$source_normalized = strtolower( remove_accents( preg_replace( '/\s+/u', ' ', trim( $source_product_name ) ) ) );
							?>
							<tr>
								<td><?php echo esc_html( $source_product_name ); ?></td>
								<td>
									<select name="product_mapping[<?php echo esc_attr( $mapping_key ); ?>]" required>
										<option value="">Chọn sản phẩm tương ứng</option>
										<?php foreach ( $product_groups as $product_group ) : ?>
											<?php
											$mapping_value = absint( $product_group['category_id'] ) . '|' . $product_group['item_variant'];
											$target_normalized = strtolower( remove_accents( preg_replace( '/\s+/u', ' ', trim( $product_group['item_variant'] ) ) ) );
											?>
											<option value="<?php echo esc_attr( $mapping_value ); ?>" <?php selected( $source_normalized, $target_normalized ); ?>>
												<?php echo esc_html( $product_group['category_name'] . ' / ' . $product_group['item_variant'] ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="submit"><button type="submit" class="button button-primary">Xác nhận import master mã SAP</button></p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="ums-panel">
		<h2>Danh sách mã SAP đồng phục</h2>
		<div class="ums-summary-strip">
			<span>Đang sử dụng: <strong><?php echo esc_html( number_format_i18n( $active_count ) ); ?></strong></span>
			<span>Mã SAP trùng: <strong><?php echo esc_html( number_format_i18n( $duplicate_count ) ); ?></strong></span>
			<span>Ngừng sử dụng: <strong><?php echo esc_html( number_format_i18n( $inactive_count ) ); ?></strong></span>
		</div>

		<form method="get" class="ums-filter-bar">
			<input type="hidden" name="page" value="tvn-ums-uniform-materials">
			<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Tìm mã SAP, loại, size">
			<select name="status">
				<option value="">Tất cả trạng thái</option>
				<option value="active" <?php selected( $filters['status'], 'active' ); ?>>Đang sử dụng</option>
				<option value="duplicate_sap" <?php selected( $filters['status'], 'duplicate_sap' ); ?>>Mã SAP trùng</option>
				<option value="inactive" <?php selected( $filters['status'], 'inactive' ); ?>>Ngừng sử dụng</option>
			</select>
			<button type="submit" class="button">Lọc</button>
			<a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
		</form>

		<?php if ( ! $table_ready ) : ?>
			<div class="ums-empty-state">Chưa sẵn sàng dữ liệu master mã SAP.</div>
		<?php else : ?>
			<div class="ums-jqx-grid" data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>" data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"></div>
		<?php endif; ?>
	</div>
</div>
