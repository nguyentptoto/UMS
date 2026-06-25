<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section class="ums-page-title">
    <div>
        <h1>Hồ sơ của tôi</h1>
        <p>Thông tin nhân sự đang dùng cho các yêu cầu đồng phục.</p>
    </div>
</section>

<section class="ums-user-panel">
    <dl class="ums-user-profile-list ums-user-profile-list-wide">
        <div><dt>Mã nhân viên</dt><dd><?php echo esc_html( $profile['employee_code'] ); ?></dd></div>
        <div><dt>Họ tên</dt><dd><?php echo esc_html( $profile['full_name'] ); ?></dd></div>
        <div><dt>Tài khoản</dt><dd><?php echo esc_html( $current_user->user_login ); ?></dd></div>
        <div><dt>Giới tính</dt><dd><?php echo esc_html( $profile['gender'] ); ?></dd></div>
        <div><dt>Nhà máy</dt><dd><?php echo esc_html( $profile['factory_location'] ); ?></dd></div>
        <div><dt>Phòng ban</dt><dd><?php echo esc_html( $profile['department'] ); ?></dd></div>
        <div><dt>Chức danh</dt><dd><?php echo esc_html( $profile['job_position'] ); ?></dd></div>
        <div><dt>Hợp đồng</dt><dd><?php echo esc_html( $profile['contract_type'] ); ?></dd></div>
        <div><dt>Ngày vào</dt><dd><?php echo esc_html( mysql2date( 'd/m/Y', $profile['date_joined'] ) ); ?></dd></div>
        <div><dt>Ngày nghỉ</dt><dd><?php echo ! empty( $profile['resignation_date'] ) ? esc_html( mysql2date( 'd/m/Y', $profile['resignation_date'] ) ) : 'Không có'; ?></dd></div>
        <div><dt>Ngày chuyển bộ phận</dt><dd><?php echo ! empty( $profile['transfer_date'] ) ? esc_html( mysql2date( 'd/m/Y', $profile['transfer_date'] ) ) : 'Không có'; ?></dd></div>
        <div><dt>Thai sản</dt><dd><?php echo (int) $profile['is_maternity'] === 1 ? 'Có' : 'Không'; ?></dd></div>
        <div><dt>Làm việc ngoài trời</dt><dd><?php echo (int) $profile['is_outdoor_worker'] === 1 ? 'Có' : 'Không'; ?></dd></div>
    </dl>
</section>
