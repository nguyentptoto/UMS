<?php
/**
 * Admin composer for the uniform distribution schedule email.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$compose = is_array( $draft ) ? $draft : array();
$default_period = ( (int) current_time( 'n' ) >= 9 ? '2.' : '1.' ) . current_time( 'Y' );
$to_text = $compose['to_input'] ?? implode( "\n", $recipients['to'] ?? array() );
$cc_text = $compose['cc_input'] ?? implode( "\n", $recipients['cc'] ?? array() );
$schedule_rows = $compose['schedule_input'] ?? array( array( 'time' => '', 'department' => '' ) );
if ( ! $schedule_rows ) {
	$schedule_rows = array( array( 'time' => '', 'department' => '' ) );
}
?>

<div class="wrap ums-admin-wrap ums-distribution-email-page">
	<h1 class="wp-heading-inline">UMS - Gửi lịch cấp phát đồng phục</h1>
	<hr class="wp-header-end">

	<?php if ( ! empty( $notice ) ) : ?>
		<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
	<?php endif; ?>

	<div class="ums-panel">
		<h2>Người nhận</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="ums-inline-form">
			<?php wp_nonce_field( 'ums_import_distribution_recipients' ); ?>
			<input type="hidden" name="action" value="ums_import_distribution_recipients">
			<label>File To / CC
				<input type="file" name="ums_distribution_recipients_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
			</label>
			<button type="submit" class="button">Đọc danh sách</button>
		</form>
		<p class="description">File XLSX: To ở cột A, CC ở cột B, tiêu đề tại dòng 1. Có thể nhập nhiều email trong một ô, mỗi email một dòng.</p>
		<?php if ( ! empty( $recipients['to'] ) || ! empty( $recipients['cc'] ) ) : ?>
			<p><strong><?php echo esc_html( count( $recipients['to'] ) ); ?> To · <?php echo esc_html( count( $recipients['cc'] ) ); ?> CC</strong></p>
		<?php endif; ?>
	</div>

	<div class="ums-panel">
		<h2>Nội dung email</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ums-distribution-compose-form">
			<?php wp_nonce_field( 'ums_preview_distribution_email' ); ?>
			<input type="hidden" name="action" value="ums_preview_distribution_email">

			<div class="ums-email-recipient-grid">
				<label>To *
					<textarea name="ums_distribution_email[to]" rows="7" required><?php echo esc_textarea( $to_text ); ?></textarea>
				</label>
				<label>CC
					<textarea name="ums_distribution_email[cc]" rows="7"><?php echo esc_textarea( $cc_text ); ?></textarea>
				</label>
			</div>

			<div class="ums-email-fields-grid">
				<label>Tiêu đề email
					<input type="text" name="ums_distribution_email[subject]" value="<?php echo esc_attr( $compose['subject_input'] ?? '' ); ?>" placeholder="LỊCH CẤP PHÁT ĐỒNG PHỤC ĐỢT 2.2026">
				</label>
				<label>Đợt cấp *
					<input type="text" name="ums_distribution_email[period]" value="<?php echo esc_attr( $compose['period'] ?? $default_period ); ?>" placeholder="2.2026" required>
				</label>
				<label>Ngày cấp *
					<input type="date" name="ums_distribution_email[distribution_date]" value="<?php echo esc_attr( $compose['distribution_date'] ?? '' ); ?>" required>
				</label>
				<label>Link dữ liệu cấp phát *
					<input type="url" name="ums_distribution_email[data_link]" value="<?php echo esc_attr( $compose['data_link'] ?? '' ); ?>" placeholder="https://docs.google.com/spreadsheets/d/..." required>
				</label>
			</div>

			<div class="ums-email-schedule-heading">
				<h3>Thời gian theo bộ phận</h3>
				<button type="button" class="button" id="ums-email-add-slot"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> Thêm khung giờ</button>
			</div>
			<div class="ums-table-scroll">
				<table class="widefat striped ums-email-schedule-table" id="ums-email-schedule-table">
					<thead><tr><th>Thời gian</th><th>Bộ phận</th><th class="ums-email-remove-column"><span class="screen-reader-text">Xóa</span></th></tr></thead>
					<tbody>
					<?php foreach ( $schedule_rows as $index => $row ) : ?>
						<tr>
							<td><input type="text" name="ums_distribution_email[schedule][<?php echo esc_attr( $index ); ?>][time]" value="<?php echo esc_attr( $row['time'] ?? '' ); ?>" placeholder="9:00 - 10:00"></td>
							<td><input type="text" name="ums_distribution_email[schedule][<?php echo esc_attr( $index ); ?>][department]" value="<?php echo esc_attr( $row['department'] ?? '' ); ?>" placeholder="Multi, HP"></td>
							<td><button type="button" class="button-link ums-email-remove-slot" title="Xóa khung giờ" aria-label="Xóa khung giờ"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="submit"><button type="submit" class="button button-primary">Xem trước email</button></p>
		</form>
	</div>

	<?php if ( is_array( $draft ) ) : ?>
		<div class="ums-panel" id="ums-distribution-email-preview">
			<h2>Xem trước email</h2>
			<?php if ( ! empty( $draft['errors'] ) ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( implode( ' ', $draft['errors'] ) ); ?></p></div>
			<?php else : ?>
				<p><strong>Tiêu đề:</strong> <?php echo esc_html( $draft['subject'] ); ?></p>
				<p><strong>To:</strong> <?php echo esc_html( implode( ', ', $draft['to'] ) ); ?></p>
				<p><strong>CC:</strong> <?php echo esc_html( $draft['cc'] ? implode( ', ', $draft['cc'] ) : '-' ); ?></p>
				<div class="ums-email-preview-body"><?php echo wp_kses_post( UMS_Distribution_Email::build_html( $draft ) ); ?></div>
				<p class="description" id="ums-distribution-preview-stale" hidden>Nội dung đã thay đổi. Hãy xem trước lại trước khi gửi.</p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ums-distribution-send-form">
					<?php wp_nonce_field( 'ums_send_distribution_email' ); ?>
					<input type="hidden" name="action" value="ums_send_distribution_email">
					<input type="hidden" name="draft_token" value="<?php echo esc_attr( $draft_token ); ?>">
					<p class="submit"><button type="submit" class="button button-primary">Gửi email</button></p>
				</form>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>
