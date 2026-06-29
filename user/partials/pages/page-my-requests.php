<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$approval_requests  = isset( $approval_requests ) && is_array( $approval_requests ) ? $approval_requests : array();
$completed_requests = isset( $completed_requests ) && is_array( $completed_requests ) ? $completed_requests : array();

$status_label = function ( $status ) {
	if ( $status === 'completed' ) {
		return 'Hoàn thành';
	}

	if ( $status === 'rejected' ) {
		return 'Từ chối';
	}

	if ( preg_match( '/^pending_step_(\d+)$/', (string) $status, $matches ) ) {
		return 'Chờ duyệt bước ' . absint( $matches[1] );
	}

	return $status;
};

$view_link = function ( $request_id ) use ( $portal_url ) {
	return '<a class="ums-user-link-button ums-user-link-button-view" href="' . esc_url( add_query_arg( array( 'ums_page' => 'request-detail', 'request_id' => absint( $request_id ) ), $portal_url ) ) . '">Xem chi tiết</a>';
};

$approval_action = function ( $request ) use ( $portal_url, $view_link ) {
	$request_id = absint( $request['request_id'] );
	$html       = $view_link( $request_id );
	$html      .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ums-inline-form">';
	$html      .= wp_nonce_field( 'ums_approve_uniform_request_' . $request_id, '_wpnonce', true, false );
	$html      .= '<input type="hidden" name="action" value="ums_approve_uniform_request">';
	$html      .= '<input type="hidden" name="request_id" value="' . esc_attr( $request_id ) . '">';
	$html      .= '<input type="hidden" name="portal_url" value="' . esc_url( $portal_url ) . '">';
	$html      .= '<button type="submit" class="ums-user-link-button">Duyệt</button>';
	$html      .= '</form>';
	$html      .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ums-inline-form" data-ums-reject-form>';
	$html      .= wp_nonce_field( 'ums_reject_uniform_request_' . $request_id, '_wpnonce', true, false );
	$html      .= '<input type="hidden" name="action" value="ums_reject_uniform_request">';
	$html      .= '<input type="hidden" name="request_id" value="' . esc_attr( $request_id ) . '">';
	$html      .= '<input type="hidden" name="portal_url" value="' . esc_url( $portal_url ) . '">';
	$html      .= '<input type="hidden" name="reject_reason" value="" data-ums-reject-reason>';
	$html      .= '<button type="submit" class="ums-user-link-button ums-user-link-button-danger" data-ums-reject-button>Từ chối</button>';
	$html      .= '</form>';
	return $html;
};

$owner_action = function ( $request ) use ( $portal_url, $view_link ) {
	$request_id = absint( $request['request_id'] );
	$html       = $view_link( $request_id );

	if ( in_array( (string) $request['current_status'], array( 'pending_step_2', 'rejected' ), true ) ) {
		$html .= '<a class="ums-user-link-button" href="' . esc_url( add_query_arg( array( 'ums_page' => 'request', 'edit_request_id' => $request_id ), $portal_url ) ) . '">Sửa</a>';
		$html .= '<a class="ums-user-link-button" href="' . esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ums_delete_uniform_request', 'request_id' => $request_id, 'portal_url' => $portal_url ), admin_url( 'admin-post.php' ) ), 'ums_delete_uniform_request_' . $request_id ) ) . '" onclick="return window.confirm(\'Xóa phiếu #' . esc_js( $request_id ) . '?\');">Xóa</a>';
	}

	return $html;
};

$grid_columns = array(
	array( 'text' => 'STT', 'datafield' => 'stt', 'width' => 70 ),
	array( 'text' => 'Người nhận', 'datafield' => 'target_name', 'minwidth' => 220 ),
	array( 'text' => 'Phòng ban', 'datafield' => 'department', 'width' => 180 ),
	array( 'text' => 'Trạng thái', 'datafield' => 'status', 'width' => 150 ),
	array( 'text' => 'Ngày tạo', 'datafield' => 'created_at', 'width' => 150 ),
	array( 'text' => 'Thao tác', 'datafield' => 'actions', 'width' => 230, 'cellsrenderer' => 'html' ),
);

$build_grid_rows = function ( $requests, $mode = 'owner' ) use ( $status_label, $owner_action, $approval_action, $view_link ) {
	$rows = array();

	foreach ( $requests as $index => $request ) {
		$request_id = absint( $request['request_id'] );
		$action_mode = isset( $request['_ums_action_mode'] ) ? (string) $request['_ums_action_mode'] : $mode;
		$rows[]     = array(
			'stt'         => $index + 1,
			'request_id'  => $request_id,
			'target_name' => trim( $request['target_employee_code'] . ' - ' . $request['target_full_name'], ' -' ),
			'department'  => $request['target_department'],
			'status'      => $status_label( $request['current_status'] ),
			'created_at'  => mysql2date( 'd/m/Y H:i', $request['created_at'] ),
			'actions'     => $action_mode === 'approval' ? $approval_action( $request ) : ( $action_mode === 'owner' ? $owner_action( $request ) : $view_link( $request_id ) ),
		);
	}

	return $rows;
};

$approval_grid_rows  = $build_grid_rows( $approval_requests, 'approval' );
$completed_grid_rows = $build_grid_rows( $completed_requests, 'owner' );
?>

<section class="ums-page-title">
	<div>
		<h1>Phiếu của tôi</h1>
		<p>Theo dõi phiếu đang chờ duyệt và phiếu đã hoàn thành.</p>
	</div>
</section>

<section class="ums-user-panel">
	<div class="ums-user-jqx-tabs" data-ums-jqx-tabs>
		<ul>
			<li>Chờ duyệt</li>
			<li>Hoàn thành</li>
		</ul>
		<div>
			<div class="ums-user-tab-copy">
				Phiếu bạn tạo đang chờ duyệt hoặc phiếu đã đến bước duyệt của bạn.
			</div>
			<div
				id="ums-approval-request-grid"
				class="ums-user-jqx-grid ums-jqx-grid"
				data-rows="<?php echo esc_attr( wp_json_encode( $approval_grid_rows ) ); ?>"
				data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
			></div>
		</div>
		<div>
			<div class="ums-user-tab-copy">
				Phiếu đã đi hết luồng duyệt hoặc đã bị từ chối.
			</div>
			<div
				id="ums-completed-request-grid"
				class="ums-user-jqx-grid ums-jqx-grid"
				data-rows="<?php echo esc_attr( wp_json_encode( $completed_grid_rows ) ); ?>"
				data-columns="<?php echo esc_attr( wp_json_encode( $grid_columns ) ); ?>"
			></div>
		</div>
	</div>
</section>
