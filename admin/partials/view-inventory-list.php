<?php
/**
 * Giao diện quản lý danh mục sản phẩm và tổng kho.
 *
 * Các biến được chuẩn bị từ UMS_Admin::render_inventory_page():
 * $inventory, $filters, $category_tree, $child_categories, $editing_item, $form_values, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_editing = ! empty( $editing_item );
$page_url   = admin_url( 'admin.php?page=tvn-ums-inventory' );
$inventory_sections = array();
$size_order = array(
    'XXS' => 10,
    'XS'  => 20,
    'S'   => 30,
    'M'   => 40,
    'L'   => 50,
    'XL'  => 60,
    'XXL' => 70,
    '2XL' => 70,
    'XXXL'=> 80,
    '3XL' => 80,
    '4XL' => 90,
    '5XL' => 100,
);

foreach ( $inventory as $item ) {
    $parent_id   = ! empty( $item['parent_category_id'] ) ? absint( $item['parent_category_id'] ) : absint( $item['category_id'] );
    $section_key = $parent_id > 0 ? 'parent-' . $parent_id : 'uncategorized';
    $section_name = ! empty( $item['parent_category_name'] ) ? $item['parent_category_name'] : ( ! empty( $item['category_name'] ) ? $item['category_name'] : 'Chưa phân loại' );
    $variant        = trim( (string) ( $item['item_variant'] ?? '' ) );
    $category_name = $variant !== '' ? $variant : ( ! empty( $item['category_name'] ) ? $item['category_name'] : $item['item_type'] );
    $size           = trim( (string) $item['size'] );
    $size           = $size !== '' ? $size : 'Không size';
    $variant_key    = UMS_DB_Inventory::normalize_product_identity( $variant );
    $row_key        = implode( '|', array( absint( $item['category_id'] ), $variant_key ) );

    if ( ! isset( $inventory_sections[ $section_key ] ) ) {
        $inventory_sections[ $section_key ] = array(
            'name'  => $section_name,
            'sizes' => array(),
            'rows'  => array(),
        );
    }

    if ( ! isset( $inventory_sections[ $section_key ]['rows'][ $row_key ] ) ) {
        $label = $category_name;
        $inventory_sections[ $section_key ]['rows'][ $row_key ] = array(
            'label'  => $label,
            'items'  => array(),
            'prices' => array(),
            'total'  => 0,
        );
    }

    $edit_url = add_query_arg(
        array(
            'page'         => 'tvn-ums-inventory',
            'edit_item_id' => absint( $item['item_id'] ),
        ),
        admin_url( 'admin.php' )
    );
    $stock_qty = (int) $item['stock_qty'];
    $inventory_sections[ $section_key ]['sizes'][ $size ] = true;
    $inventory_sections[ $section_key ]['rows'][ $row_key ]['items'][ $size ] = array(
        'item_id'    => absint( $item['item_id'] ),
        'stock_qty'  => $stock_qty,
        'base_price' => (float) $item['base_price'],
        'edit_url'   => $edit_url . '#ums-inventory-form',
    );
    $inventory_sections[ $section_key ]['rows'][ $row_key ]['prices'][] = (float) $item['base_price'];
    $inventory_sections[ $section_key ]['rows'][ $row_key ]['total'] += $stock_qty;
}

foreach ( $inventory_sections as &$section ) {
    $sizes = array_keys( $section['sizes'] );
    usort(
        $sizes,
        function( $left, $right ) use ( $size_order ) {
            $left_key  = strtoupper( trim( (string) $left ) );
            $right_key = strtoupper( trim( (string) $right ) );

            if ( isset( $size_order[ $left_key ], $size_order[ $right_key ] ) ) {
                return $size_order[ $left_key ] <=> $size_order[ $right_key ];
            }
            if ( is_numeric( $left_key ) && is_numeric( $right_key ) ) {
                return (float) $left_key <=> (float) $right_key;
            }
            if ( isset( $size_order[ $left_key ] ) ) {
                return -1;
            }
            if ( isset( $size_order[ $right_key ] ) ) {
                return 1;
            }

            return strnatcasecmp( $left_key, $right_key );
        }
    );
    $section['sizes'] = $sizes;
    uasort(
        $section['rows'],
        function( $left, $right ) {
            return strnatcasecmp( $left['label'], $right['label'] );
        }
    );
}
unset( $section );
?>

<div class="wrap ums-admin-wrap">
    <h1 class="wp-heading-inline">UMS - Quản lý Sản phẩm & Tổng kho</h1>
    <hr class="wp-header-end">

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2>Tổng hợp tồn kho theo danh mục</h2>
        <form method="get" class="ums-filter-bar">
            <input type="hidden" name="page" value="tvn-ums-inventory">

            <label>
                <span class="screen-reader-text">Tìm sản phẩm</span>
                <input
                    type="search"
                    name="s"
                    value="<?php echo esc_attr( $filters['search'] ); ?>"
                    placeholder="Tìm danh mục, tên sản phẩm, size"
                >
            </label>

            <label>
                <span class="screen-reader-text">Lọc danh mục cha</span>
                <select name="parent_id">
                    <option value="">Tất cả danh mục cha</option>
                    <?php foreach ( $category_tree as $parent ) : ?>
                        <option value="<?php echo esc_attr( $parent['category_id'] ); ?>" <?php selected( $filters['parent_id'], (string) $parent['category_id'] ); ?>>
                            <?php echo esc_html( $parent['category_name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                <span class="screen-reader-text">Lọc tồn kho</span>
                <select name="stock">
                    <option value="">Tất cả tồn kho</option>
                    <option value="available" <?php selected( $filters['stock'], 'available' ); ?>>Còn hàng</option>
                    <option value="low" <?php selected( $filters['stock'], 'low' ); ?>>Tồn thấp</option>
                    <option value="out" <?php selected( $filters['stock'], 'out' ); ?>>Hết hàng</option>
                </select>
            </label>

            <button type="submit" class="button">Lọc</button>
            <a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
        </form>

        <?php if ( empty( $inventory_sections ) ) : ?>
            <div class="ums-empty-state">Không có dữ liệu tồn kho phù hợp với bộ lọc.</div>
        <?php else : ?>
            <?php $section_number = 0; ?>
            <?php foreach ( $inventory_sections as $section ) : ?>
                <?php
                $section_number++;
                $section_rows         = array();
                $size_column_width    = max( 6, 38 / max( 1, count( $section['sizes'] ) ) );
                $section_columns      = array(
                    array( 'text' => 'Loại sản phẩm', 'datafield' => 'product_label', 'width' => '38%' ),
                );

                foreach ( $section['sizes'] as $size_index => $size ) {
                    $section_columns[] = array(
                        'text'          => $size,
                        'datafield'     => 'size_' . $size_index,
                        'width'         => $size_column_width . '%',
                        'cellsalign'    => 'center',
                        'filterable'    => false,
                        'cellsrenderer' => 'html',
                    );
                }

                $section_columns[] = array( 'text' => 'Tổng', 'datafield' => 'total', 'width' => '10%', 'cellsalign' => 'right' );
                $section_columns[] = array( 'text' => 'Đơn giá', 'datafield' => 'base_price', 'width' => '14%', 'cellsalign' => 'right' );

                foreach ( $section['rows'] as $row ) {
					$positive_prices = array_values(
						array_unique(
							array_filter(
								array_map( 'floatval', $row['prices'] ),
								function ( $price ) {
									return $price > 0;
								}
							)
						)
					);
					$price_label = count( $positive_prices ) > 1
						? 'Cần chuẩn hóa'
						: number_format_i18n( empty( $positive_prices ) ? 0 : reset( $positive_prices ), 0 );
                    $grid_row = array(
                        'product_label' => $row['label'],
                        'total'         => $row['total'],
                        'base_price'    => $price_label,
                    );
                    foreach ( $section['sizes'] as $size_index => $size ) {
                        $stock_item = isset( $row['items'][ $size ] ) ? $row['items'][ $size ] : null;
                        $grid_row[ 'size_' . $size_index ] = $stock_item
                            ? '<a href="' . esc_url( $stock_item['edit_url'] ) . '" title="Sửa size ' . esc_attr( $size ) . '">' . esc_html( $stock_item['stock_qty'] ) . '</a>'
                            : '-';
                    }

                    $section_rows[] = $grid_row;
                }
                ?>
                <h3><?php echo esc_html( $section_number . '. Tồn kho ' . $section['name'] ); ?></h3>
                <div
                    id="ums-inventory-grid-<?php echo esc_attr( $section_number ); ?>"
                    class="ums-jqx-grid"
                    data-rows="<?php echo esc_attr( wp_json_encode( $section_rows ) ); ?>"
                    data-columns="<?php echo esc_attr( wp_json_encode( $section_columns ) ); ?>"
                ></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

	<div class="ums-panel" id="ums-inventory-import">
		<h2>Import nhập kho</h2>
		<p>Nhập <strong>Loại sản phẩm</strong> theo cột <strong>Loại</strong> trong master Mã SAP, hoặc theo <strong>Loại đồng phục lên PR + Size</strong>. Hệ thống chỉ cộng tồn vào sản phẩm UMS đã ánh xạ và không tự tạo sản phẩm mới.</p>

		<div class="ums-inline-actions">
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ums_download_inventory_import_template' ), 'ums_download_inventory_import_template' ) ); ?>">
				Tải template nhập kho
			</a>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
				<?php wp_nonce_field( 'ums_preview_inventory_import' ); ?>
				<input type="hidden" name="action" value="ums_preview_inventory_import">
				<input type="file" name="ums_inventory_import_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
				<button type="submit" class="button button-primary" <?php disabled( ! $inventory_import_ready ); ?>>Nhập kho</button>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ums_repair_inventory_prices' ); ?>
				<input type="hidden" name="action" value="ums_repair_inventory_prices">
				<button type="submit" class="button">Đồng bộ giá cho mọi size</button>
			</form>
		</div>

		<?php if ( ! $inventory_import_ready ) : ?>
			<div class="notice notice-warning inline"><p>Hãy cập nhật cấu trúc import kho trong <code>ums.sql</code> và import master Mã SAP trước khi sử dụng.</p></div>
		<?php endif; ?>
	</div>

	<div class="ums-panel" id="ums-newcomer-inventory-out">
		<h2>Import cấp phát ngày đầu làm việc</h2>
		<p>Đọc sheet <strong>Template_NewCommer</strong> để ghi nhận số lượng cấp phát thực tế. Chức năng này không kiểm tra hoặc giới hạn theo định mức CNV mới.</p>
		<?php if ( ! $newcomer_out_ready ) : ?>
			<div class="notice notice-error inline"><p>Database chưa có cấu trúc lưu thông tin CNV tại thời điểm cấp phát. Hãy chạy khối SQL cập nhật trong <code>ums.sql</code> trước khi sử dụng.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
			<?php wp_nonce_field( 'ums_preview_newcomer_inventory_out' ); ?>
			<input type="hidden" name="action" value="ums_preview_newcomer_inventory_out">
			<input type="file" name="ums_newcomer_out_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
			<button type="submit" class="button button-primary" <?php disabled( ! $newcomer_out_ready ); ?>>Kiểm tra cấp phát ngày đầu</button>
		</form>
	</div>

	<?php if ( is_array( $newcomer_out_preview ) ) : ?>
		<div class="ums-panel ums-newcomer-out-preview">
			<h2>Xem trước cấp phát ngày đầu: <?php echo esc_html( $newcomer_out_preview['file_name'] ); ?></h2>
			<p><?php echo esc_html( sprintf(
				'%d CNV, %d dòng cấp phát, tổng số lượng %s; %d lỗi và %d cảnh báo.',
				$newcomer_out_preview['employee_count'],
				count( $newcomer_out_preview['rows'] ), number_format_i18n( $newcomer_out_preview['total_quantity'] ),
				count( $newcomer_out_preview['errors'] ), count( $newcomer_out_preview['warnings'] )
			) ); ?></p>

			<?php if ( ! empty( $newcomer_out_preview['errors'] ) ) : ?>
				<div class="notice notice-error inline"><p><strong>Chưa thể xác nhận cấp phát:</strong></p></div>
				<div class="ums-table-scroll" style="max-height:420px">
					<table class="widefat striped"><thead><tr><th>STT</th><th>Nội dung lỗi</th></tr></thead><tbody>
					<?php foreach ( $newcomer_out_preview['errors'] as $index => $error ) : ?>
						<tr><td><?php echo esc_html( $index + 1 ); ?></td><td><?php echo esc_html( $error ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $newcomer_out_preview['warnings'] ) ) : ?>
				<div class="notice notice-warning inline"><p><strong>Cảnh báo đối chiếu thông tin:</strong></p></div>
				<div class="ums-table-scroll" style="max-height:320px">
					<table class="widefat striped"><thead><tr><th>STT</th><th>Nội dung cảnh báo</th></tr></thead><tbody>
					<?php foreach ( $newcomer_out_preview['warnings'] as $index => $warning ) : ?>
						<tr><td><?php echo esc_html( $index + 1 ); ?></td><td><?php echo esc_html( $warning ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $newcomer_out_preview['rows'] ) ) : ?>
				<div class="ums-table-scroll" style="max-height:520px">
					<table class="widefat striped">
						<thead><tr><th>Dòng Excel</th><th>Mã CNV</th><th>Họ tên</th><th>Nhóm</th><th>Sản phẩm UMS</th><th>Size</th><th>SL cấp</th><th>Tồn trước</th><th>Tồn sau</th></tr></thead>
						<tbody>
						<?php foreach ( $newcomer_out_preview['rows'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['source_row'] ); ?></td><td><?php echo esc_html( $row['employee_no'] ); ?></td>
								<td><?php echo esc_html( $row['full_name'] ); ?></td><td><?php echo esc_html( $row['group_label'] ?? $row['group'] ); ?></td>
								<td><?php echo esc_html( $row['product'] ); ?></td><td><?php echo esc_html( $row['size'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['quantity'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['before_qty'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['after_qty'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( empty( $newcomer_out_preview['errors'] ) && ! empty( $newcomer_out_preview['rows'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ums_confirm_newcomer_inventory_out' ); ?>
					<input type="hidden" name="action" value="ums_confirm_newcomer_inventory_out">
					<input type="hidden" name="newcomer_out_preview_token" value="<?php echo esc_attr( $newcomer_out_preview_token ); ?>">
					<p class="submit"><button type="submit" class="button button-primary">Xác nhận cấp phát ngày đầu</button></p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="ums-panel" id="ums-allocation-calculation">
		<h2>Tính số lượng cấp phát</h2>
		<p>File đăng ký phải giữ nguyên cấu trúc Google Form. Hệ thống áp dụng toàn bộ định mức phù hợp, tự giới hạn số đăng ký vượt mức và tổng hợp nhu cầu cho PR; thao tác này không xuất hoặc trừ tồn kho.</p>
		<?php if ( ! $allocation_calculation_ready ) : ?>
			<div class="notice notice-error inline"><p>Database chưa có bảng lưu kết quả tính số lượng cấp phát. Hãy cập nhật phần SQL tương ứng trong <code>ums.sql</code>.</p></div>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
			<?php wp_nonce_field( 'ums_preview_allocation_calculation' ); ?>
			<input type="hidden" name="action" value="ums_preview_allocation_calculation">
			<label>Năm cấp <input type="number" name="allocation_year" value="<?php echo esc_attr( current_time( 'Y' ) ); ?>" min="2000" max="2100" required></label>
			<label>Kỳ cấp
				<select name="allocation_month"><option value="4">Tháng 4</option><option value="9" selected>Tháng 9</option></select>
			</label>
			<input type="file" name="ums_allocation_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
			<button type="submit" class="button button-primary">Tính số lượng cấp phát</button>
		</form>
	</div>

	<?php if ( is_array( $allocation_preview ) ) : ?>
		<div class="ums-panel ums-allocation-calculation-preview">
			<h2>Kết quả tính số lượng cấp phát: <?php echo esc_html( $allocation_preview['file_name'] ); ?></h2>
			<p><?php echo esc_html( sprintf(
				'Kỳ T%d/%d: %d CNV, đăng ký %s, được cấp %s qua %d dòng; %d lỗi và %d cảnh báo.',
				$allocation_preview['month'], $allocation_preview['year'], $allocation_preview['employee_count'],
				number_format_i18n( $allocation_preview['requested_quantity'] ?? 0 ), number_format_i18n( $allocation_preview['total_quantity'] ),
				count( $allocation_preview['details'] ), count( $allocation_preview['errors'] ), count( $allocation_preview['warnings'] ?? array() )
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
					if ( ! isset( $allocation_summary[ $key ] ) ) $allocation_summary[ $key ] = array( 'product' => $detail['product'], 'size' => $detail['size'], 'requested' => 0, 'quantity' => 0 );
					$allocation_summary[ $key ]['requested'] += absint( $detail['requested_quantity'] ?? $detail['quantity'] );
					$allocation_summary[ $key ]['quantity'] += absint( $detail['quantity'] );
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

	<?php if ( is_array( $inventory_import_preview ) ) : ?>
		<div class="ums-panel ums-inventory-import-preview">
			<h2>Xem trước nhập kho: <?php echo esc_html( $inventory_import_preview['file_name'] ); ?></h2>
			<p><?php echo esc_html( sprintf( '%d dòng hợp lệ, tổng số lượng nhập %s. Không có sản phẩm mới được tạo từ file này.', count( $inventory_import_preview['rows'] ), number_format_i18n( $inventory_import_preview['total_quantity'] ) ) ); ?></p>

			<?php if ( ! empty( $inventory_import_preview['errors'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( implode( ' ', array_slice( $inventory_import_preview['errors'], 0, 10 ) ) ); ?></p></div>
			<?php else : ?>
				<div class="ums-table-scroll">
					<table class="widefat striped">
						<thead><tr><th>Dòng Excel</th><th>Loại trong file</th><th>Loại đồng phục lên PR</th><th>Size</th><th>Xử lý</th><th>SL nhập</th><th>Tồn trước</th><th>Tồn sau</th><th>Đơn giá áp dụng</th><th>Ghi chú</th></tr></thead>
						<tbody>
						<?php foreach ( $inventory_import_preview['rows'] as $preview_row ) : ?>
							<tr>
								<td><?php echo esc_html( $preview_row['source_row'] ); ?></td>
								<td><?php echo esc_html( $preview_row['source_product'] ); ?></td>
								<td><?php echo esc_html( $preview_row['product'] ); ?></td>
								<td><?php echo esc_html( $preview_row['size'] ); ?></td>
								<td>Cộng tồn theo Mã SAP</td>
								<td><?php echo esc_html( number_format_i18n( $preview_row['quantity'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $preview_row['before_qty'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $preview_row['after_qty'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( (float) $preview_row['unit_price'], 0 ) ); ?></td>
								<td><?php echo esc_html( $preview_row['note'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ums_confirm_inventory_import' ); ?>
					<input type="hidden" name="action" value="ums_confirm_inventory_import">
					<input type="hidden" name="inventory_preview_token" value="<?php echo esc_attr( $inventory_preview_token ); ?>">
					<p class="submit"><button type="submit" class="button button-primary">Xác nhận nhập kho</button></p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>

    <div class="ums-panel" id="ums-manual-inventory-out">
        <h2>Xuất kho chủ động</h2>
        <?php if ( empty( $recipient_options ) ) : ?>
            <div class="notice notice-warning inline"><p>Chưa có dữ liệu Sơ đồ tổ chức TVN để chọn người nhận.</p></div>
        <?php endif; ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_manual_inventory_out' ); ?>
            <input type="hidden" name="action" value="ums_manual_inventory_out">

            <div class="ums-form-grid">
                <label>
                    <span>Sản phẩm <b>*</b></span>
                    <select name="ums_manual_out[item_id]" required>
                        <option value="">Chọn sản phẩm còn tồn</option>
                        <?php foreach ( $available_items as $item ) : ?>
                            <?php
                            $item_label = trim( ( $item['parent_category_name'] ?: '' ) . ' / ' . ( $item['category_name'] ?: $item['item_type'] ), ' /' );
                            if ( ! empty( $item['item_variant'] ) ) {
                                $item_label .= ' - ' . $item['item_variant'];
                            }
                            $item_label .= ' - Size ' . $item['size'] . ' - Tồn ' . (int) $item['stock_qty'];
                            ?>
                            <option value="<?php echo esc_attr( $item['item_id'] ); ?>">
                                <?php echo esc_html( $item_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Người nhận <b>*</b></span>
                    <input
                        type="text"
                        name="ums_manual_out[target_employee_no]"
                        list="ums-organization-recipients"
                        placeholder="Nhập mã hoặc chọn CNV từ Sơ đồ tổ chức"
                        autocomplete="off"
                        required
                    >
                    <datalist id="ums-organization-recipients">
                        <?php foreach ( $recipient_options as $recipient ) : ?>
                            <option value="<?php echo esc_attr( $recipient['employee_no'] ); ?>">
                                <?php echo esc_html( $recipient['full_name'] . ' - ' . $recipient['department'] . ' - ' . $recipient['position'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </datalist>
                    <small>Người nhận được kiểm tra lại theo mã nhân viên trong Sơ đồ tổ chức trước khi xuất kho.</small>
                </label>

                <label>
                    <span>Số lượng xuất <b>*</b></span>
                    <input type="number" name="ums_manual_out[quantity]" value="1" min="1" step="1" required>
                </label>

                <label class="ums-field-wide">
                    <span>Ghi chú</span>
                    <textarea name="ums_manual_out[note]" rows="3" placeholder="Ví dụ: xuất bổ sung, cấp trực tiếp, điều chuyển nội bộ..."></textarea>
                </label>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary">Ghi nhận xuất kho</button>
            </p>
        </form>
    </div>

    <div class="ums-panel" id="ums-inventory-form">
        <h2><?php echo $is_editing ? 'Cập nhật sản phẩm & tồn kho' : 'Thêm sản phẩm & tồn kho'; ?></h2>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
            <?php wp_nonce_field( 'ums_save_inventory_item' ); ?>
            <input type="hidden" name="action" value="ums_save_inventory_item">
            <input type="hidden" name="ums_inventory[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
            <input type="hidden" name="ums_inventory[item_id]" value="<?php echo esc_attr( $form_values['item_id'] ); ?>">

            <div class="ums-form-grid">
                <label>
                    <span>Danh mục cha <b>*</b></span>
                    <select name="ums_inventory[category_id]" required>
                        <option value="">Chọn danh mục cha</option>
                        <?php foreach ( $category_tree as $category ) : ?>
                            <option value="<?php echo esc_attr( $category['category_id'] ); ?>" <?php selected( (int) $form_values['category_id'], (int) $category['category_id'] ); ?>>
                                <?php echo esc_html( $category['category_name'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( empty( $category_tree ) ) : ?>
                        <p class="description">
                            Hãy tạo danh mục cha tại
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tvn-ums-product-categories' ) ); ?>">Danh mục SP</a>
                            trước khi thêm sản phẩm.
                        </p>
                    <?php endif; ?>
                </label>

                <label>
                    <span>Tên sản phẩm <b>*</b></span>
                    <input type="text" name="ums_inventory[item_variant]" value="<?php echo esc_attr( $form_values['item_variant'] ); ?>" placeholder="Áo phông xám, giày KPR 010..." required>
                </label>

                <label>
                    <span>Size <b>*</b></span>
                    <input type="text" name="ums_inventory[size]" value="<?php echo esc_attr( $form_values['size'] ); ?>" required>
                </label>

                <input type="hidden" name="ums_inventory[color_code]" value="">

                <label>
                    <span>Số lượng tồn kho <b>*</b></span>
                    <input type="number" name="ums_inventory[stock_qty]" value="<?php echo esc_attr( $form_values['stock_qty'] ); ?>" min="0" step="1" required>
                </label>

                <label>
                    <span>Đơn giá sản phẩm <b>*</b></span>
                    <input type="text" name="ums_inventory[base_price]" value="<?php echo esc_attr( $form_values['base_price'] ); ?>" inputmode="decimal" required>
					<small>Giá này được áp dụng đồng nhất cho tất cả size của cùng sản phẩm.</small>
                </label>
            </div>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php echo $is_editing ? 'Cập nhật sản phẩm' : 'Thêm sản phẩm'; ?>
                </button>
                <?php if ( $is_editing ) : ?>
                    <a href="<?php echo esc_url( $page_url . '#ums-inventory-form' ); ?>" class="button">Hủy sửa</a>
					<?php
					$delete_item_url = wp_nonce_url(
						add_query_arg(
							array( 'action' => 'ums_delete_inventory_item', 'item_id' => absint( $form_values['item_id'] ) ),
							admin_url( 'admin-post.php' )
						),
						'ums_delete_inventory_item_' . absint( $form_values['item_id'] )
					);
					?>
					<a
						href="<?php echo esc_url( $delete_item_url ); ?>"
						class="button button-link-delete"
						onclick="return confirm('Xóa dòng sản phẩm/size này khỏi kho? Thao tác này không thể hoàn tác.');"
					>
						<span class="dashicons dashicons-trash" aria-hidden="true"></span> Xóa size này
					</a>
                <?php endif; ?>
            </p>
        </form>
    </div>
</div>
