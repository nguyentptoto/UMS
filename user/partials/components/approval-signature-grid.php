<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$signature_flows   = isset( $approval_flows ) && is_array( $approval_flows ) ? $approval_flows : array();
$signature_request = isset( $signature_request ) && is_array( $signature_request ) ? $signature_request : null;
$signature_profile = isset( $signature_profile ) && is_array( $signature_profile ) ? $signature_profile : ( isset( $profile ) && is_array( $profile ) ? $profile : array() );
$signature_logs    = array();

if ( $signature_request && ! empty( $signature_request['request_id'] ) ) {
	$signature_logs = UMS_DB_Request::get_logs( (int) $signature_request['request_id'] );
}

$latest_logs = array();
foreach ( $signature_logs as $log ) {
	$latest_logs[ (int) $log['step_order'] ] = $log;
}

$approval_steps = array_values(
	array_filter(
		$signature_flows,
		function ( $flow ) {
			return (int) $flow['step_order'] > 1;
		}
	)
);

$current_step = 0;
if ( $signature_request && ! empty( $signature_request['current_status'] ) && preg_match( '/^pending_step_(\d+)$/', (string) $signature_request['current_status'], $matches ) ) {
	$current_step = absint( $matches[1] );
}

$creator_label = '';
if ( ! empty( $signature_profile['employee_code'] ) || ! empty( $signature_profile['full_name'] ) ) {
	$creator_label = trim( ( $signature_profile['employee_code'] ?? '' ) . ' - ' . ( $signature_profile['full_name'] ?? '' ), ' -' );
}

if ( $signature_request && ! empty( $signature_request['creator_id'] ) ) {
	$creator_user = get_userdata( (int) $signature_request['creator_id'] );
	if ( $creator_user ) {
		$creator_profile = UMS_DB_User::get_by_wp_user_id( (int) $creator_user->ID );
		if ( $creator_profile ) {
			$creator_label = trim( $creator_profile['employee_code'] . ' - ' . $creator_profile['full_name'], ' -' );
		} else {
			$creator_label = $creator_user->display_name ?: $creator_user->user_login;
		}
	}
}

$format_action = function ( $action ) {
	$labels = array(
		'submitted' => 'Đã gửi',
		'edited'    => 'Đã cập nhật',
		'approved'  => 'Đã duyệt',
		'rejected'  => 'Từ chối',
	);

	return $labels[ $action ] ?? $action;
};

$render_cell = function ( $step_order ) use ( $latest_logs, $current_step, $format_action ) {
	$log = isset( $latest_logs[ $step_order ] ) ? $latest_logs[ $step_order ] : null;
	if ( $log ) {
		$name = trim( ( $log['display_name'] ?: $log['user_login'] ) );
		?>
		<div class="ums-approval-signature-status is-<?php echo esc_attr( $log['action'] ); ?>">
			<strong><?php echo esc_html( $format_action( $log['action'] ) ); ?></strong>
			<?php if ( $name !== '' ) : ?>
				<span><?php echo esc_html( $name ); ?></span>
			<?php endif; ?>
			<small><?php echo esc_html( mysql2date( 'd/m/Y H:i', $log['action_date'] ) ); ?></small>
			<?php if ( ! empty( $log['comment'] ) ) : ?>
				<em><?php echo esc_html( $log['comment'] ); ?></em>
			<?php endif; ?>
		</div>
		<?php
		return;
	}

	if ( (int) $step_order === (int) $current_step ) {
		echo '<span class="ums-approval-signature-pending">Đang chờ duyệt</span>';
		return;
	}

	echo '<span class="ums-approval-signature-empty">Chưa đến bước</span>';
};
?>

<section class="ums-user-panel ums-approval-signature-panel">
	<div class="ums-user-panel-head">
		<div>
			<h3>Thông tin phê duyệt</h3>
			<p>Các ô dưới đây ghi nhận người yêu cầu và kết quả duyệt theo từng bước trong luồng của phòng ban.</p>
		</div>
	</div>

	<div class="ums-approval-signature-wrap">
		<table class="ums-approval-signature-table">
			<thead>
				<tr>
					<th>Người yêu cầu</th>
					<?php if ( ! empty( $approval_steps ) ) : ?>
						<?php foreach ( $approval_steps as $flow ) : ?>
							<th><?php echo esc_html( $flow['step_name'] ); ?></th>
						<?php endforeach; ?>
					<?php else : ?>
						<th>Chưa cấu hình bước duyệt</th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<div class="ums-approval-signature-status is-submitted">
							<strong><?php echo esc_html( $signature_request ? 'Đã gửi' : 'Người tạo phiếu' ); ?></strong>
							<?php if ( $creator_label !== '' ) : ?>
								<span><?php echo esc_html( $creator_label ); ?></span>
							<?php endif; ?>
							<?php if ( $signature_request && ! empty( $signature_request['created_at'] ) ) : ?>
								<small><?php echo esc_html( mysql2date( 'd/m/Y H:i', $signature_request['created_at'] ) ); ?></small>
							<?php endif; ?>
						</div>
					</td>
					<?php if ( ! empty( $approval_steps ) ) : ?>
						<?php foreach ( $approval_steps as $flow ) : ?>
							<td><?php $render_cell( (int) $flow['step_order'] ); ?></td>
						<?php endforeach; ?>
					<?php else : ?>
						<td><span class="ums-approval-signature-empty">Chưa có dữ liệu</span></td>
					<?php endif; ?>
				</tr>
			</tbody>
		</table>
	</div>
</section>
