<?php
/**
 * Giao diện quản lý danh mục chức danh.
 *
 * Các biến được chuẩn bị từ UMS_Admin::render_position_page():
 * $positions, $filters, $editing_position, $form_values, $notice.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing = ! empty( $editing_position );
$page_url   = admin_url( 'admin.php?page=tvn-ums-positions' );
$grid_rows  = array();

foreach ( $positions as $position ) {
	$edit_url = add_query_arg(
		array(
			'page'             => 'tvn-ums-positions',
			'edit_position_id' => absint( $position['position_id'] ),
		),
		admin_url( 'admin.php' )
	);
	$delete_url = wp_nonce_url(
		add_query_arg(
			array(
				'action'      => 'ums_delete_position',
				'position_id' => absint( $position['position_id'] ),
			),
			admin_url( 'admin-post.php' )
		),
		'ums_delete_position_' . absint( $position['position_id'] )
	);

	$grid_rows[] = array(
		'position_code' => $position['position_code'],
		'position_name' => $position['position_name'],
		'status'        => (int) $position['is_active'] === 1 ? 'Đang sử dụng' : 'Ngừng sử dụng',
		'actions'       => '<a href="' . esc_url( $edit_url . '#ums-position-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa chức danh ' . esc_attr( $position['position_code'] ) . '? Chỉ nên xóa khi chưa có nhân sự thuộc chức danh này.">Xóa</a>',
	);
}

$grid_columns = array(
	array( 'text' => 'Mã chức danh', 'datafield' => 'position_code', 'width' => '25%' ),
	array( 'text' => 'Tên chức danh', 'datafield' => 'position_name', 'width' => '35%' ),
	array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '20%' ),
	array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '20%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
?>

<div class="wrap ums-admin-wrap">
	<h1 class="wp-heading-inline">UMS - Quản lý Chức danh</h1>
	<a href="<?php echo esc_url( $page_url . '#ums-position-form' ); ?>" class="page-title-action">Thêm chức danh mới</a>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ums-panel">
		<h2>Danh sách chức danh</h2>
		<form method="get" class="ums-filter-bar">
			<input type="hidden" name="page" value="tvn-ums-positions">

			<label>
				<span class="screen-reader-text">Tìm chức danh</span>
				<input
					type="search"
					name="s"
					value="<?php echo esc_attr( $filters['search'] ); ?>"
					placeholder="Tìm mã hoặc tên chức danh"
				>
			</label>

			<label>
				<span class="screen-reader-text">Lọc trạng thái</span>
				<select name="status">
					<option value="">Tất cả trạng thái</option>
					<option value="active" <?php selected( $filters['status'], 'active' ); ?>>Đang sử dụng</option>
					<option value="inactive" <?php selected( $filters['status'], 'inactive' ); ?>>Ngừng sử dụng</option>
				</select>
			</label>

			<button type="submit" class="button">Lọc</button>
			<a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
		</form>

		<div
			id="ums-position-grid"
			class="ums-jqx-grid"
			data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>"
			data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
		></div>
	</div>

	<div class="ums-panel" id="ums-position-form">
		<h2><?php echo $is_editing ? 'Cập nhật chức danh' : 'Thêm chức danh'; ?></h2>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
			<?php wp_nonce_field( 'ums_save_position' ); ?>
			<input type="hidden" name="action" value="ums_save_position">
			<input type="hidden" name="ums_position[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
			<input type="hidden" name="ums_position[position_id]" value="<?php echo esc_attr( $form_values['position_id'] ); ?>">

			<div class="ums-form-grid">
				<label>
					<span>Mã chức danh <b>*</b></span>
					<input type="text" name="ums_position[position_code]" value="<?php echo esc_attr( $form_values['position_code'] ); ?>" required>
				</label>

				<label>
					<span>Tên chức danh <b>*</b></span>
					<input type="text" name="ums_position[position_name]" value="<?php echo esc_attr( $form_values['position_name'] ); ?>" required>
				</label>
			</div>

			<fieldset class="ums-checkboxes">
				<legend>Trạng thái</legend>
				<label>
					<input type="checkbox" name="ums_position[is_active]" value="1" <?php checked( (int) $form_values['is_active'], 1 ); ?>>
					Đang sử dụng
				</label>
			</fieldset>

			<p class="submit">
				<button type="submit" class="button button-primary">
					<?php echo $is_editing ? 'Cập nhật chức danh' : 'Thêm chức danh'; ?>
				</button>
				<?php if ( $is_editing ) : ?>
					<a href="<?php echo esc_url( $page_url . '#ums-position-form' ); ?>" class="button">Hủy sửa</a>
				<?php endif; ?>
			</p>
		</form>
	</div>
</div>
