<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$detail_request = isset( $detail_request ) && is_array( $detail_request ) ? $detail_request : null;

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

$payment_label = function ( $payment_method ) {
	if ( (int) $payment_method === 1 ) {
		return 'Thanh toán qua lương tháng phát sinh';
	}

	if ( (int) $payment_method === 2 ) {
		return 'Trực tiếp thanh toán bằng tiền mặt hoặc chuyển khoản';
	}

	return 'Không áp dụng';
};

if ( ! $detail_request ) :
	?>
	<section class="ums-page-title">
		<div>
			<h1>Chi tiết phiếu</h1>
			<p>Không tìm thấy phiếu hoặc bạn không có quyền xem phiếu này.</p>
		</div>
	</section>

	<section class="ums-user-panel">
		<a class="ums-user-button ums-user-button-light" href="<?php echo esc_url( add_query_arg( 'ums_page', 'my-requests', $portal_url ) ); ?>">Quay lại</a>
	</section>
	<?php
	return;
endif;

$target_profile = UMS_DB_User::get_by_wp_user_id( (int) $detail_request['target_user_id'] );
$creator_user   = get_userdata( (int) $detail_request['creator_id'] );
$details        = isset( $detail_request['details'] ) && is_array( $detail_request['details'] ) ? $detail_request['details'] : array();
$total_quantity = 0;
$total_price    = 0;

foreach ( $details as $detail ) {
	$total_quantity += (int) $detail['quantity'];
	$total_price    += (float) $detail['price_at_request'];
}
?>

<section class="ums-page-title">
	<div>
		<h1>Chi tiết phiếu #<?php echo esc_html( $detail_request['request_id'] ); ?></h1>
		<p>Thông tin phiếu yêu cầu cấp đồng phục đã gửi vào luồng duyệt.</p>
	</div>
	<span class="ums-user-badge"><?php echo esc_html( $status_label( $detail_request['current_status'] ) ); ?></span>
</section>

<div class="ums-request-page ums-request-detail-page">
<section class="ums-user-panel">
	<div class="ums-user-panel-head">
		<div>
			<h3>Thông tin phiếu</h3>
			<p>Người tạo, người nhận và trạng thái hiện tại của phiếu.</p>
		</div>
	</div>

	<div class="ums-user-request-form ums-request-detail-profile-grid">
		<label>
			<span>Mã phiếu</span>
			<input type="text" value="#<?php echo esc_attr( $detail_request['request_id'] ); ?>" readonly>
		</label>
		<label>
			<span>Người tạo</span>
			<input type="text" value="<?php echo esc_attr( $creator_user ? $creator_user->user_login : '' ); ?>" readonly>
		</label>
		<label>
			<span>Trạng thái</span>
			<input type="text" value="<?php echo esc_attr( $status_label( $detail_request['current_status'] ) ); ?>" readonly>
		</label>
		<label>
			<span>Ngày tạo</span>
			<input type="text" value="<?php echo esc_attr( mysql2date( 'd/m/Y H:i', $detail_request['created_at'] ) ); ?>" readonly>
		</label>
		<label>
			<span>Loại phiếu</span>
			<input type="text" value="<?php echo esc_attr( 'Yêu cầu cấp đồng phục' ); ?>" readonly>
		</label>
	</div>
</section>

<section class="ums-user-panel">
	<div class="ums-user-panel-head">
		<div>
			<h3>Thông tin CNV nhận đồng phục</h3>
			<p>Dữ liệu nhân sự tại thời điểm xem phiếu.</p>
		</div>
	</div>

	<div class="ums-user-request-form ums-request-detail-profile-grid">
		<label>
			<span>Mã nhân viên</span>
			<input type="text" value="<?php echo esc_attr( $target_profile ? $target_profile['employee_code'] : '' ); ?>" readonly>
		</label>
		<label>
			<span>Tên CNV</span>
			<input type="text" value="<?php echo esc_attr( $target_profile ? $target_profile['full_name'] : '' ); ?>" readonly>
		</label>
		<label>
			<span>Phòng / Bộ phận</span>
			<input type="text" value="<?php echo esc_attr( $target_profile ? $target_profile['department'] : '' ); ?>" readonly>
		</label>
		<label>
			<span>Ngày vào Công ty</span>
			<input type="text" value="<?php echo esc_attr( $target_profile && ! empty( $target_profile['date_joined'] ) ? mysql2date( 'd/m/Y', $target_profile['date_joined'] ) : '' ); ?>" readonly>
		</label>
		<label>
			<span>Tổng SL</span>
			<input type="text" value="<?php echo esc_attr( $total_quantity ); ?>" readonly>
		</label>
		<label>
			<span>Tổng giá</span>
			<input type="text" value="<?php echo esc_attr( number_format( $total_price, 0, '.', ',' ) ); ?>" readonly>
		</label>
	</div>
</section>

<section class="ums-user-panel">
	<div class="ums-user-panel-head">
		<div>
			<h3>Thông tin đồng phục / vật tư</h3>
			<p>Các dòng vật tư trong phiếu.</p>
		</div>
	</div>

	<?php if ( empty( $details ) ) : ?>
		<p class="ums-user-muted">Phiếu chưa có dòng vật tư.</p>
	<?php else : ?>
		<div class="ums-request-items">
			<?php foreach ( $details as $detail_index => $detail ) : ?>
				<?php
				$quantity        = max( 1, (int) $detail['quantity'] );
				$total           = (float) $detail['price_at_request'];
				$unit_price      = $total / $quantity;
				$parent_category = ! empty( $detail['parent_category_name'] ) ? $detail['parent_category_name'] : $detail['item_type'];
				$child_category  = ! empty( $detail['category_name'] ) ? $detail['category_name'] : $detail['item_type'];
				$size_label      = $detail['size'];
				if ( ! empty( $detail['item_variant'] ) ) {
					$size_label .= ' - ' . $detail['item_variant'];
				}
				?>
				<div class="ums-request-item">
					<div class="ums-request-item-head">
						<strong>Dòng đồng phục <?php echo esc_html( $detail_index + 1 ); ?></strong>
					</div>

					<div class="ums-user-request-form">
						<label>
							<span>Loại đồng phục</span>
							<input type="text" value="<?php echo esc_attr( $parent_category ); ?>" readonly>
						</label>
						<label>
							<span>Loại quần áo/giày</span>
							<input type="text" value="<?php echo esc_attr( $child_category ); ?>" readonly>
						</label>
						<label>
							<span>Size</span>
							<input type="text" value="<?php echo esc_attr( $size_label ); ?>" readonly>
						</label>
						<label>
							<span>SL</span>
							<input type="number" value="<?php echo esc_attr( $quantity ); ?>" readonly>
						</label>
						<label>
							<span>Giá</span>
							<input type="text" value="<?php echo esc_attr( number_format( $total, 0, '.', '' ) ); ?>" readonly>
						</label>
						<input type="hidden" value="<?php echo esc_attr( number_format( $unit_price, 0, '.', '' ) ); ?>">
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</section>

<section class="ums-user-panel">
	<div class="ums-user-panel-head">
		<div>
			<h3>Lý do yêu cầu cấp phát</h3>
			<p>Lý do và phương thức thanh toán nếu có phát sinh đền bù.</p>
		</div>
	</div>

	<div class="ums-reason-list">
		<label class="ums-reason-option">
			<input type="radio" disabled <?php checked( (int) $detail_request['reason_type'], 1 ); ?>>
			<span>
				<strong>Lý do 1</strong>
				Do thay đổi vị trí công việc: chuyển công việc, bộ phận, vị trí, làm việc ngoài trời...
			</span>
		</label>

		<label class="ums-reason-option">
			<input type="radio" disabled <?php checked( (int) $detail_request['reason_type'], 2 ); ?>>
			<span>
				<strong>Lý do 2</strong>
				Đồng phục rách/hỏng/bẩn do nguyên nhân trực tiếp từ việc thực thi công việc đảm nhiệm.
			</span>
		</label>

		<label class="ums-reason-option">
			<input type="radio" disabled <?php checked( (int) $detail_request['reason_type'], 3 ); ?>>
			<span>
				<strong>Lý do 3</strong>
				Đồng phục mất/hỏng/rách do lỗi CNV, nguyên nhân không vì thực hiện công việc, hoặc yêu cầu cấp ngoài thời gian định mức sử dụng theo quy định.
			</span>
		</label>
	</div>

	<label class="ums-user-field-block">
		<span>Ghi rõ lý do chi tiết</span>
		<textarea rows="4" readonly><?php echo esc_textarea( $detail_request['reason_detail'] ); ?></textarea>
	</label>

	<?php if ( (int) $detail_request['reason_type'] === 3 ) : ?>
		<div class="ums-payment-panel">
			<div class="ums-payment-context">
				<p>Trong trường hợp xin cấp đồng phục mới do đồng phục mất/hỏng/rách do lỗi CNV hoặc do nguyên nhân không vì thực hiện công việc hoặc yêu cầu cấp đồng phục ngoài thời gian định mức sử dụng theo quy định, CNV đồng ý lựa chọn một trong hai hình thức thanh toán sau:</p>
				<ul>
					<li>(1) Thanh toán qua lương tháng phát sinh.</li>
					<li>(2) Trực tiếp thanh toán cho Công ty bằng tiền mặt hoặc chuyển khoản.</li>
				</ul>
				<strong>Điều khoản ràng buộc đi kèm:</strong>
				<ul>
					<li>Trường hợp CNV lựa chọn phương thức (2): Việc thanh toán được thực hiện trong thời hạn 30 ngày kể từ ngày được cấp phát đồng phục, nếu không thanh toán đúng thời hạn, việc thanh toán sẽ được chuyển sang phương thức (1).</li>
					<li>Trường hợp lương tháng phát sinh thấp hơn chi phí đồng phục mà CNV phải thanh toán, CNV đồng ý thanh toán phần chênh lệch bằng tiền mặt hoặc chuyển khoản cho Công ty trong thời hạn 30 ngày kể từ ngày được cấp phát đồng phục.</li>
				</ul>
			</div>

			<span class="ums-user-label">Phương thức thanh toán chi phí</span>
			<div class="ums-payment-options">
				<label>
					<input type="radio" disabled <?php checked( (int) $detail_request['payment_method'], 1 ); ?>>
					<span>Hình thức 1: Thanh toán qua lương tháng phát sinh.</span>
				</label>
				<label>
					<input type="radio" disabled <?php checked( (int) $detail_request['payment_method'], 2 ); ?>>
					<span>Hình thức 2: Trực tiếp thanh toán cho Công ty bằng tiền mặt hoặc chuyển khoản.</span>
				</label>
			</div>
		</div>
	<?php else : ?>
		<div class="ums-user-request-form ums-request-detail-payment">
			<label>
				<span>Phương thức thanh toán</span>
				<input type="text" value="<?php echo esc_attr( $payment_label( $detail_request['payment_method'] ) ); ?>" readonly>
			</label>
		</div>
	<?php endif; ?>
</section>

<?php
$signature_request = $detail_request;
$signature_profile = $target_profile ? $target_profile : array();
include UMS_PLUGIN_DIR . 'user/partials/components/approval-signature-grid.php';
?>

<?php if ( ! empty( $detail_can_approve ) ) : ?>
	<section class="ums-user-panel">
		<div class="ums-user-panel-head">
			<div>
				<h3>Duyệt phiếu</h3>
				<p>Bạn đang là người duyệt ở bước hiện tại của phiếu này.</p>
			</div>
		</div>

		<div class="ums-detail-approval-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-inline-form">
				<?php wp_nonce_field( 'ums_approve_uniform_request_' . absint( $detail_request['request_id'] ) ); ?>
				<input type="hidden" name="action" value="ums_approve_uniform_request">
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $detail_request['request_id'] ); ?>">
				<input type="hidden" name="portal_url" value="<?php echo esc_url( $portal_url ); ?>">
				<button type="submit" class="ums-user-button">Duyệt</button>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-inline-form" data-ums-detail-reject-form>
				<?php wp_nonce_field( 'ums_reject_uniform_request_' . absint( $detail_request['request_id'] ) ); ?>
				<input type="hidden" name="action" value="ums_reject_uniform_request">
				<input type="hidden" name="request_id" value="<?php echo esc_attr( $detail_request['request_id'] ); ?>">
				<input type="hidden" name="portal_url" value="<?php echo esc_url( $portal_url ); ?>">
				<button type="button" class="ums-user-button ums-user-button-danger" data-ums-open-reject-modal>Từ chối</button>

				<div class="ums-modal" data-ums-reject-modal hidden>
					<div class="ums-modal-backdrop" data-ums-close-reject-modal></div>
					<div class="ums-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="ums-reject-modal-title-<?php echo esc_attr( $detail_request['request_id'] ); ?>">
						<div class="ums-modal-head">
							<div>
								<h3 id="ums-reject-modal-title-<?php echo esc_attr( $detail_request['request_id'] ); ?>">Từ chối phiếu</h3>
								<p>Nhập lý do để người tạo phiếu có căn cứ chỉnh sửa và gửi lại.</p>
							</div>
							<button type="button" class="ums-modal-close" data-ums-close-reject-modal aria-label="Đóng">×</button>
						</div>

						<label class="ums-user-field-block">
							<span>Lý do từ chối</span>
							<textarea name="reject_reason" rows="5" required data-ums-reject-modal-reason placeholder="Nhập lý do từ chối phiếu..."></textarea>
						</label>

						<div class="ums-modal-actions">
							<button type="button" class="ums-user-button ums-user-button-light" data-ums-close-reject-modal>Hủy</button>
							<button type="submit" class="ums-user-button ums-user-button-danger">Xác nhận từ chối</button>
						</div>
					</div>
				</div>
			</form>
		</div>
	</section>
<?php endif; ?>
</div>

<div class="ums-user-actions">
	<a class="ums-user-button ums-user-button-light" href="<?php echo esc_url( add_query_arg( 'ums_page', 'my-requests', $portal_url ) ); ?>">Quay lại danh sách</a>
	<?php if ( (int) $detail_request['creator_id'] === get_current_user_id() && in_array( (string) $detail_request['current_status'], array( 'pending_step_2', 'rejected' ), true ) ) : ?>
		<a class="ums-user-button" href="<?php echo esc_url( add_query_arg( array( 'ums_page' => 'request', 'edit_request_id' => absint( $detail_request['request_id'] ) ), $portal_url ) ); ?>">Sửa phiếu</a>
		<a
			class="ums-user-button ums-user-button-danger"
			href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'ums_delete_uniform_request', 'request_id' => absint( $detail_request['request_id'] ), 'portal_url' => $portal_url ), admin_url( 'admin-post.php' ) ), 'ums_delete_uniform_request_' . absint( $detail_request['request_id'] ) ) ); ?>"
			onclick="return window.confirm('Xóa phiếu #<?php echo esc_js( $detail_request['request_id'] ); ?>?');"
		>Xóa phiếu</a>
	<?php endif; ?>
</div>
