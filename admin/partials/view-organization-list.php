<?php
/**
 * Giao diện quản lý dữ liệu sơ đồ tổ chức TVN đã đồng bộ về UMS.
 *
 * Biến: $filters, $table_ready, $total_employees, $last_synced_at,
 * $next_cron_run, $cron_result, $divisions, $departments, $factories, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_url = admin_url( 'admin.php?page=tvn-ums-organization' );
$grid_columns = array(
	array( 'text' => 'Mã NV', 'datafield' => 'employee_no', 'width' => 105 ),
	array( 'text' => 'Họ và tên', 'datafield' => 'full_name', 'width' => 190 ),
	array( 'text' => 'Khối', 'datafield' => 'division', 'width' => 190 ),
	array( 'text' => 'Phòng ban', 'datafield' => 'department', 'width' => 185 ),
	array( 'text' => 'Bộ phận', 'datafield' => 'section', 'width' => 175 ),
	array( 'text' => 'Nhóm', 'datafield' => 'team', 'width' => 160 ),
	array( 'text' => 'Chức danh', 'datafield' => 'position', 'width' => 90 ),
	array( 'text' => 'Email', 'datafield' => 'email', 'width' => 220 ),
	array( 'text' => 'Nhà máy', 'datafield' => 'factory', 'width' => 115 ),
	array( 'text' => 'Cập nhật nguồn', 'datafield' => 'source_updated_at', 'width' => 145 ),
);
$grid_fields = array(
	array( 'name' => 'source_id', 'type' => 'number' ),
	array( 'name' => 'source_version', 'type' => 'number' ),
	array( 'name' => 'employee_no', 'type' => 'string' ),
	array( 'name' => 'full_name', 'type' => 'string' ),
	array( 'name' => 'division', 'type' => 'string' ),
	array( 'name' => 'department', 'type' => 'string' ),
	array( 'name' => 'section', 'type' => 'string' ),
	array( 'name' => 'team', 'type' => 'string' ),
	array( 'name' => 'position', 'type' => 'string' ),
	array( 'name' => 'email', 'type' => 'string' ),
	array( 'name' => 'factory', 'type' => 'string' ),
	array( 'name' => 'source_created_at', 'type' => 'string' ),
	array( 'name' => 'source_updated_at', 'type' => 'string' ),
	array( 'name' => 'synced_at', 'type' => 'string' ),
);
?>

<div class="wrap ums-admin-wrap">
	<h1 class="wp-heading-inline">UMS - Sơ đồ tổ chức TVN</h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! $table_ready ) : ?>
		<div class="notice notice-error inline">
			<p>Chưa có bảng <code><?php echo esc_html( UMS_DB_Organization::table() ); ?></code>. Hãy import phần bảng Sơ đồ tổ chức trong <code>ums.sql</code>.</p>
		</div>
	<?php endif; ?>

	<div class="ums-panel">
		<div class="ums-panel-heading-row">
			<div>
				<h2>Danh sách nhân sự toàn TVN</h2>
				<p class="description">
					<?php echo esc_html( number_format_i18n( $total_employees ) ); ?> nhân sự nội bộ
					<?php if ( $last_synced_at ) : ?>
						· Đồng bộ gần nhất: <?php echo esc_html( mysql2date( 'd/m/Y H:i:s', $last_synced_at ) ); ?>
					<?php endif; ?>
					<?php if ( $next_cron_run ) : ?>
						· Tự động kế tiếp: <?php echo esc_html( wp_date( 'd/m/Y H:i:s', $next_cron_run ) ); ?>
					<?php endif; ?>
				</p>
				<?php if ( ! empty( $cron_result['ended_at'] ) ) : ?>
					<p class="description">
						Lần chạy nền gần nhất:
						<?php echo esc_html( mysql2date( 'd/m/Y H:i:s', $cron_result['ended_at'] ) ); ?>
						·
						<?php if ( isset( $cron_result['status'] ) && $cron_result['status'] === 'success' ) : ?>
							Thành công, <?php echo esc_html( number_format_i18n( $cron_result['total'] ?? 0 ) ); ?> nhân sự
						<?php else : ?>
							Thất bại: <?php echo esc_html( $cron_result['message'] ?? 'Không xác định được lỗi.' ); ?>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ums_sync_organization' ); ?>
				<input type="hidden" name="action" value="ums_sync_organization">
				<button type="submit" class="button button-primary" <?php disabled( ! $table_ready ); ?>>Đồng bộ dữ liệu</button>
			</form>
		</div>

		<form method="get" class="ums-filter-bar ums-organization-filters">
			<input type="hidden" name="page" value="tvn-ums-organization">

			<label>
				<span class="screen-reader-text">Tìm nhân viên</span>
				<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Mã NV, họ tên, email, chức danh">
			</label>

			<label>
				<span class="screen-reader-text">Khối</span>
				<select name="division">
					<option value="">Tất cả khối</option>
					<?php foreach ( $divisions as $division ) : ?>
						<option value="<?php echo esc_attr( $division ); ?>" <?php selected( $filters['division'], $division ); ?>><?php echo esc_html( $division ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span class="screen-reader-text">Phòng ban</span>
				<select name="department">
					<option value="">Tất cả phòng ban</option>
					<?php foreach ( $departments as $department ) : ?>
						<option value="<?php echo esc_attr( $department ); ?>" <?php selected( $filters['department'], $department ); ?>><?php echo esc_html( $department ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span class="screen-reader-text">Nhà máy</span>
				<select name="factory">
					<option value="">Tất cả nhà máy</option>
					<?php foreach ( $factories as $factory ) : ?>
						<option value="<?php echo esc_attr( $factory ); ?>" <?php selected( $filters['factory'], $factory ); ?>><?php echo esc_html( $factory ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<button type="submit" class="button">Lọc</button>
			<a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
		</form>

		<?php if ( $table_ready ) : ?>
			<div
				id="ums-organization-grid"
				class="ums-jqx-remote-grid"
				data-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
				data-nonce="<?php echo esc_attr( wp_create_nonce( 'ums_get_organization_employees' ) ); ?>"
				data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
				data-fields="<?php echo esc_attr( wp_json_encode( $grid_fields ) ); ?>"
				data-filters="<?php echo esc_attr( wp_json_encode( $filters ) ); ?>"
			></div>
		<?php endif; ?>
	</div>
</div>
