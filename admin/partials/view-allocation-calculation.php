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

		<?php if ( ! $allocation_calculation_ready ) : ?>
			<div class="notice notice-error inline"><p>Cơ sở dữ liệu chưa hỗ trợ kết quả cấp phát theo nhà máy. Hãy cập nhật phần SQL tương ứng trong <code>ums.sql</code>.</p></div>
		<?php endif; ?>

		<?php if ( has_action( 'ums_render_allocation_sheet_controls' ) ) {
			do_action( 'ums_render_allocation_sheet_controls', $selected_factory_code, $allocation_calculation_ready );
		} else { ?>
			<div class="notice notice-warning inline"><p>Chưa kích hoạt UMS Google Sheets Connector. Có thể tiếp tục import Excel bên dưới.</p></div>
		<?php } ?>

		<details class="ums-allocation-excel-fallback">
			<summary>Import Excel dự phòng</summary>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
				<?php wp_nonce_field( 'ums_preview_allocation_calculation' ); ?>
				<input type="hidden" name="action" value="ums_preview_allocation_calculation">
				<label>Nhà máy <select name="factory_code"><?php foreach ( $factories as $factory_code => $factory_name ) : ?><option value="<?php echo esc_attr( $factory_code ); ?>" <?php selected( $selected_factory_code, $factory_code ); ?>><?php echo esc_html( $factory_name ); ?></option><?php endforeach; ?></select></label>
				<label>Năm cấp <input type="number" name="allocation_year" value="<?php echo esc_attr( current_time( 'Y' ) ); ?>" min="2000" max="2100" required></label>
				<label>Kỳ cấp <select name="allocation_month"><option value="4">Tháng 4</option><option value="9" selected>Tháng 9</option></select></label>
				<input type="file" name="ums_allocation_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
				<button type="submit" class="button">Kiểm tra file Excel</button>
			</form>
		</details>
	</div>

	<?php if ( is_array( $allocation_preview ) ) : ?>
		<div class="ums-panel ums-allocation-calculation-preview" id="ums-allocation-preview">
			<h2>Kết quả tính số lượng cấp phát: <?php echo esc_html( $allocation_preview['file_name'] ); ?></h2>
			<?php $source_preview = is_array( $allocation_preview['source_preview'] ?? null ) ? $allocation_preview['source_preview'] : array(); ?>
			<?php if ( empty( $allocation_preview['errors'] ) ) : ?>
				<p><?php echo esc_html( sprintf(
					'%s · Kỳ T%d/%d: %d CNV, đăng ký %s, được cấp %s qua %d dòng; %d cảnh báo.',
					$factories[ $allocation_preview['factory_code'] ?? '' ] ?? 'Dữ liệu cũ/chưa tách nhà máy',
					$allocation_preview['month'],
					$allocation_preview['year'],
					$allocation_preview['employee_count'],
					number_format_i18n( $allocation_preview['requested_quantity'] ?? 0 ),
					number_format_i18n( $allocation_preview['total_quantity'] ),
					count( $allocation_preview['details'] ),
					count( $allocation_preview['warnings'] ?? array() )
				) ); ?></p>
			<?php else : ?>
				<p>Chưa tính số lượng cấp phát vì cấu trúc cột chưa khớp. Kiểm tra lỗi và dữ liệu nguồn bên dưới.</p>
			<?php endif; ?>
			<?php if ( ! empty( $source_preview['rows'] ) ) : ?>
				<p>Đã đọc <?php echo esc_html( number_format_i18n( count( $source_preview['rows'] ) ) ); ?> dòng nguồn. Dữ liệu chỉ được lưu tạm để kiểm tra; chưa chốt vào PR.</p>
			<?php endif; ?>

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

			<?php if ( ! empty( $source_preview['rows'] ) ) : ?>
				<?php
				$source_columns = array(
					array( 'text' => 'Dòng Sheet', 'datafield' => 'source_row', 'width' => 100, 'cellsalign' => 'right' ),
				);
				$source_letters = array();
				foreach ( (array) ( $source_preview['headers'] ?? array() ) as $letter => $heading ) {
					if ( ! preg_match( '/^[A-Z]+$/', (string) $letter ) ) {
						continue;
					}
					$source_letters[] = $letter;
					$source_columns[] = array(
						'text' => $letter . ' - ' . ( trim( (string) $heading ) !== '' ? $heading : '(trống)' ),
						'datafield' => $letter,
						'width' => 220,
					);
				}
				$source_grid_rows = array();
				foreach ( $source_preview['rows'] as $source_row_number => $source_row ) {
					$grid_row = array( 'source_row' => absint( $source_row_number ) );
					foreach ( $source_letters as $letter ) {
						$grid_row[ $letter ] = (string) ( $source_row[ $letter ] ?? '' );
					}
					$source_grid_rows[] = $grid_row;
				}
				?>
				<h3>Dữ liệu đăng ký từ nguồn</h3>
				<div
					id="ums-allocation-source-grid"
					class="ums-jqx-grid"
					data-rows="<?php echo esc_attr( wp_json_encode( $source_grid_rows ) ); ?>"
					data-columns="<?php echo esc_attr( wp_json_encode( $source_columns ) ); ?>"
				></div>
			<?php endif; ?>

			<?php if ( empty( $allocation_preview['errors'] ) && ! empty( $allocation_preview['details'] ) ) : ?>
				<?php
				$allocation_summary = array();
				$allocation_detail_rows = array();
				foreach ( $allocation_preview['details'] as $detail ) {
					$key = absint( $detail['item_id'] );
					if ( ! isset( $allocation_summary[ $key ] ) ) {
						$allocation_summary[ $key ] = array( 'product' => $detail['product'], 'size' => $detail['size'], 'requested' => 0, 'quantity' => 0 );
					}
					$allocation_summary[ $key ]['requested'] += absint( $detail['requested_quantity'] ?? $detail['quantity'] );
					$allocation_summary[ $key ]['quantity']  += absint( $detail['quantity'] );
					$allocation_detail_rows[] = array(
						'source_row' => absint( $detail['source_row'] ),
						'employee_no' => (string) $detail['employee_no'],
						'full_name' => (string) $detail['full_name'],
						'product' => (string) $detail['product'],
						'size' => (string) $detail['size'],
						'requested' => absint( $detail['requested_quantity'] ),
						'quota' => absint( $detail['quota'] ),
						'remaining' => absint( $detail['remaining'] ),
						'quantity' => absint( $detail['quantity'] ),
					);
				}
				?>
				<h3>Chi tiết số lượng được tính</h3>
				<div
					id="ums-allocation-detail-grid"
					class="ums-jqx-grid"
					data-rows="<?php echo esc_attr( wp_json_encode( $allocation_detail_rows ) ); ?>"
					data-columns="<?php echo esc_attr( wp_json_encode( array(
						array( 'text' => 'Dòng Sheet', 'datafield' => 'source_row', 'width' => 95 ),
						array( 'text' => 'Mã CNV', 'datafield' => 'employee_no', 'width' => 120 ),
						array( 'text' => 'Họ tên', 'datafield' => 'full_name', 'width' => 190 ),
						array( 'text' => 'Sản phẩm', 'datafield' => 'product', 'width' => 220 ),
						array( 'text' => 'Size', 'datafield' => 'size', 'width' => 80 ),
						array( 'text' => 'SL đăng ký', 'datafield' => 'requested', 'width' => 105 ),
						array( 'text' => 'Định mức', 'datafield' => 'quota', 'width' => 100 ),
						array( 'text' => 'Còn được cấp', 'datafield' => 'remaining', 'width' => 120 ),
						array( 'text' => 'SL cấp phát', 'datafield' => 'quantity', 'width' => 110 ),
					) ) ); ?>"
				></div>
				<h3>Tổng hợp theo sản phẩm</h3>
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
