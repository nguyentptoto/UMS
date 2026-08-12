<?php
/**
 * Giao diện Sơ đồ tổ chức TVN, dữ liệu lấy từ Google Sheet "Danh sách CNV".
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$page_url = admin_url( 'admin.php?page=tvn-ums-organization' );
$auto_start_sync = isset( $_GET['ums_auto_sync'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ums_auto_sync'] ) );
$grid_columns = array(
	array( 'text' => 'STT', 'datafield' => 'sheet_stt', 'width' => 80 ),
	array( 'text' => 'Mã nhân viên', 'datafield' => 'employee_no', 'width' => 130 ),
	array( 'text' => 'Họ và tên', 'datafield' => 'full_name', 'width' => 220 ),
	array( 'text' => 'Email', 'datafield' => 'email', 'width' => 220 ),
	array( 'text' => 'Phòng', 'datafield' => 'department', 'width' => 230 ),
	array( 'text' => 'Nhóm', 'datafield' => 'team', 'width' => 230 ),
	array( 'text' => 'Mã cost center', 'datafield' => 'cost_center', 'width' => 140 ),
	array( 'text' => 'Ngày vào', 'datafield' => 'date_joined', 'width' => 115 ),
	array( 'text' => 'Vị trí', 'datafield' => 'position', 'width' => 90 ),
	array( 'text' => 'Vị trí trước TT', 'datafield' => 'previous_position', 'width' => 130 ),
	array( 'text' => 'Đồng bộ lúc', 'datafield' => 'synced_at', 'width' => 145 ),
);
$grid_fields = array(
	array( 'name' => 'source_id', 'type' => 'number' ),
	array( 'name' => 'sheet_stt', 'type' => 'number' ),
	array( 'name' => 'source_version', 'type' => 'number' ),
	array( 'name' => 'employee_no', 'type' => 'string' ),
	array( 'name' => 'full_name', 'type' => 'string' ),
	array( 'name' => 'email', 'type' => 'string' ),
	array( 'name' => 'department', 'type' => 'string' ),
	array( 'name' => 'team', 'type' => 'string' ),
	array( 'name' => 'cost_center', 'type' => 'string' ),
	array( 'name' => 'date_joined', 'type' => 'string' ),
	array( 'name' => 'position', 'type' => 'string' ),
	array( 'name' => 'previous_position', 'type' => 'string' ),
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
				<h2>Danh sách CNV</h2>
				<p class="description">
					<?php echo esc_html( number_format_i18n( $total_employees ) ); ?> nhân sự nội bộ
					<?php if ( $last_synced_at ) : ?>
						· Đồng bộ gần nhất: <?php echo esc_html( mysql2date( 'd/m/Y H:i:s', $last_synced_at ) ); ?>
					<?php endif; ?>
				</p>
				<?php if ( ! empty( $cron_result['ended_at'] ) ) : ?>
					<p class="description">
						Lần đồng bộ Sheet gần nhất:
						<?php echo esc_html( mysql2date( 'd/m/Y H:i:s', $cron_result['ended_at'] ) ); ?>
						· Đã nhận <?php echo esc_html( number_format_i18n( $cron_result['total'] ?? 0 ) ); ?> nhân sự từ Google Sheet
					</p>
				<?php endif; ?>
			</div>

			<button
				type="button"
				class="button button-primary ums-start-popup-sync"
				id="ums-start-sheet-sync"
				data-apps-script-url="<?php echo esc_attr( $apps_script_url ); ?>"
				data-rest-endpoint="<?php echo esc_attr( $rest_endpoint ); ?>"
				data-sync-token="<?php echo esc_attr( $sync_token ); ?>"
				data-sync-mode="organization"
				data-auto-start="<?php echo $auto_start_sync ? '1' : '0'; ?>"
				<?php disabled( ! $table_ready || $apps_script_url === '' ); ?>
			>
				Đồng bộ từ Google Sheet
			</button>
		</div>

		<?php if ( $apps_script_url === '' ) : ?>
			<div class="notice notice-warning inline">
				<p>Chưa cấu hình Google Apps Script Web App URL. Hãy cấu hình tại menu <strong>Đồng bộ Sheet</strong> trước.</p>
			</div>
		<?php endif; ?>

		<div class="ums-sync-log" id="ums-sheet-sync-log" aria-live="polite">
			<div class="ums-sync-log-line">Sẵn sàng đồng bộ sơ đồ tổ chức từ Sheet Danh sách CNV.</div>
		</div>

		<form method="get" class="ums-filter-bar ums-organization-filters">
			<input type="hidden" name="page" value="tvn-ums-organization">

			<label>
				<span class="screen-reader-text">Tìm nhân viên</span>
				<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Mã NV, họ tên, email, phòng, nhóm, cost center">
			</label>

			<label>
				<span class="screen-reader-text">Phòng</span>
				<select name="department">
					<option value="">Tất cả phòng</option>
					<?php foreach ( $departments as $department ) : ?>
						<option value="<?php echo esc_attr( $department ); ?>" <?php selected( $filters['department'], $department ); ?>><?php echo esc_html( $department ); ?></option>
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
