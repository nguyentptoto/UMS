<?php
/**
 * Giao diện quản lý danh mục loại hợp đồng.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_editing = ! empty( $editing_contract_type );
$page_url   = admin_url( 'admin.php?page=tvn-ums-contract-types' );
$grid_rows  = array();

foreach ( $contract_types as $contract_type ) {
	$edit_url = add_query_arg(
		array(
			'page'                  => 'tvn-ums-contract-types',
			'edit_contract_type_id' => absint( $contract_type['contract_type_id'] ),
		),
		admin_url( 'admin.php' )
	);
	$delete_url = wp_nonce_url(
		add_query_arg(
			array(
				'action'           => 'ums_delete_contract_type',
				'contract_type_id' => absint( $contract_type['contract_type_id'] ),
			),
			admin_url( 'admin-post.php' )
		),
		'ums_delete_contract_type_' . absint( $contract_type['contract_type_id'] )
	);

	$grid_rows[] = array(
		'contract_type_code' => $contract_type['contract_type_code'],
		'contract_type_name' => $contract_type['contract_type_name'],
		'status'             => (int) $contract_type['is_active'] === 1 ? 'Đang sử dụng' : 'Ngừng sử dụng',
		'actions'            => '<a href="' . esc_url( $edit_url . '#ums-contract-type-form' ) . '">Sửa</a> | <a href="' . esc_url( $delete_url ) . '" class="ums-delete-link" data-confirm="Xóa loại hợp đồng ' . esc_attr( $contract_type['contract_type_code'] ) . '? Chỉ nên xóa khi chưa có nhân sự thuộc loại hợp đồng này.">Xóa</a>',
	);
}

$grid_columns = array(
	array( 'text' => 'Mã hợp đồng', 'datafield' => 'contract_type_code', 'width' => '25%' ),
	array( 'text' => 'Tên loại hợp đồng', 'datafield' => 'contract_type_name', 'width' => '35%' ),
	array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => '20%' ),
	array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => '20%', 'filterable' => false, 'sortable' => false, 'cellsrenderer' => 'html' ),
);
?>

<div class="wrap ums-admin-wrap">
	<h1 class="wp-heading-inline">UMS - Quản lý Hợp đồng</h1>
	<a href="<?php echo esc_url( $page_url . '#ums-contract-type-form' ); ?>" class="page-title-action">Thêm loại hợp đồng mới</a>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
			<p><?php echo esc_html( $notice['message'] ); ?></p>
		</div>
	<?php endif; ?>

	<div class="ums-panel">
		<h2>Danh sách loại hợp đồng</h2>
		<form method="get" class="ums-filter-bar">
			<input type="hidden" name="page" value="tvn-ums-contract-types">
			<input type="search" name="s" value="<?php echo esc_attr( $filters['search'] ); ?>" placeholder="Tìm mã hoặc tên loại hợp đồng">
			<select name="status">
				<option value="">Tất cả trạng thái</option>
				<option value="active" <?php selected( $filters['status'], 'active' ); ?>>Đang sử dụng</option>
				<option value="inactive" <?php selected( $filters['status'], 'inactive' ); ?>>Ngừng sử dụng</option>
			</select>
			<button type="submit" class="button">Lọc</button>
			<a href="<?php echo esc_url( $page_url ); ?>" class="button button-link">Xóa lọc</a>
		</form>

		<div id="ums-contract-type-grid" class="ums-jqx-grid" data-rows="<?php echo esc_attr( wp_json_encode( $grid_rows ) ); ?>" data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"></div>
	</div>

	<div class="ums-panel" id="ums-contract-type-form">
		<h2><?php echo $is_editing ? 'Cập nhật loại hợp đồng' : 'Thêm loại hợp đồng'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-profile-form">
			<?php wp_nonce_field( 'ums_save_contract_type' ); ?>
			<input type="hidden" name="action" value="ums_save_contract_type">
			<input type="hidden" name="ums_contract_type[is_edit]" value="<?php echo $is_editing ? '1' : '0'; ?>">
			<input type="hidden" name="ums_contract_type[contract_type_id]" value="<?php echo esc_attr( $form_values['contract_type_id'] ); ?>">

			<div class="ums-form-grid">
				<label>
					<span>Mã hợp đồng <b>*</b></span>
					<input type="text" name="ums_contract_type[contract_type_code]" value="<?php echo esc_attr( $form_values['contract_type_code'] ); ?>" required>
				</label>
				<label>
					<span>Tên loại hợp đồng <b>*</b></span>
					<input type="text" name="ums_contract_type[contract_type_name]" value="<?php echo esc_attr( $form_values['contract_type_name'] ); ?>" required>
				</label>
			</div>

			<fieldset class="ums-checkboxes">
				<legend>Trạng thái</legend>
				<label><input type="checkbox" name="ums_contract_type[is_active]" value="1" <?php checked( (int) $form_values['is_active'], 1 ); ?>> Đang sử dụng</label>
			</fieldset>

			<p class="submit">
				<button type="submit" class="button button-primary"><?php echo $is_editing ? 'Cập nhật loại hợp đồng' : 'Thêm loại hợp đồng'; ?></button>
				<?php if ( $is_editing ) : ?>
					<a href="<?php echo esc_url( $page_url . '#ums-contract-type-form' ); ?>" class="button">Hủy sửa</a>
				<?php endif; ?>
			</p>
		</form>
	</div>
</div>
