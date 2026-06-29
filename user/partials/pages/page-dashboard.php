<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$dashboard_stats = isset( $dashboard_stats ) && is_array( $dashboard_stats ) ? $dashboard_stats : array();
$dashboard_stats = wp_parse_args(
    $dashboard_stats,
    array(
        'created_total'    => 0,
        'created_pending'  => 0,
        'created_done'     => 0,
        'created_rejected' => 0,
        'waiting_approval' => 0,
    )
);
?>

<section class="ums-page-title">
    <div>
        <h1>Tổng quan</h1>
        <p>Theo dõi nhanh hồ sơ, trạng thái tài khoản và thông tin cấp phát.</p>
    </div>
</section>

<section class="ums-user-metrics" aria-label="Thông tin nhanh">
    <div>
        <span>Nhà máy</span>
        <strong><?php echo esc_html( $profile['factory_location'] ); ?></strong>
    </div>
    <div>
        <span>Ngày vào</span>
        <strong><?php echo esc_html( mysql2date( 'd/m/Y', $profile['date_joined'] ) ); ?></strong>
    </div>
    <div>
        <span>Hợp đồng</span>
        <strong><?php echo esc_html( $profile['contract_type'] ); ?></strong>
    </div>
    <div>
        <span>Trạng thái</span>
        <strong><?php echo empty( $profile['resignation_date'] ) ? 'Đang làm việc' : 'Đã nộp đơn nghỉ'; ?></strong>
    </div>
</section>

<section class="ums-user-metrics ums-user-stat-metrics" aria-label="Thống kê phiếu">
    <div>
        <span>Phiếu đã tạo</span>
        <strong><?php echo esc_html( number_format_i18n( (int) $dashboard_stats['created_total'] ) ); ?></strong>
    </div>
    <div>
        <span>Đang chờ duyệt</span>
        <strong><?php echo esc_html( number_format_i18n( (int) $dashboard_stats['created_pending'] ) ); ?></strong>
    </div>
    <div>
        <span>Hoàn thành</span>
        <strong><?php echo esc_html( number_format_i18n( (int) $dashboard_stats['created_done'] ) ); ?></strong>
    </div>
    <div>
        <span>Bị từ chối</span>
        <strong><?php echo esc_html( number_format_i18n( (int) $dashboard_stats['created_rejected'] ) ); ?></strong>
    </div>
    <div>
        <span>Chờ tôi duyệt</span>
        <strong><?php echo esc_html( number_format_i18n( (int) $dashboard_stats['waiting_approval'] ) ); ?></strong>
    </div>
</section>

<div class="ums-dashboard-grid">
    <section class="ums-user-panel">
        <h3>Hồ sơ của tôi</h3>
        <dl class="ums-user-profile-list">
            <div><dt>Mã nhân viên</dt><dd><?php echo esc_html( $profile['employee_code'] ); ?></dd></div>
            <div><dt>Phòng ban</dt><dd><?php echo esc_html( $profile['department'] ); ?></dd></div>
            <div><dt>Chức danh</dt><dd><?php echo esc_html( $profile['job_position'] ); ?></dd></div>
            <div><dt>Giới tính</dt><dd><?php echo esc_html( $profile['gender'] ); ?></dd></div>
        </dl>
    </section>

    <section class="ums-user-panel">
        <h3>Luồng duyệt hiện tại</h3>
        <?php if ( ! empty( $approval_flows ) ) : ?>
            <ol class="ums-user-flow ums-user-flow-compact">
                <?php foreach ( array_slice( $approval_flows, 0, 3 ) as $flow ) : ?>
                    <li>
                        <span><?php echo esc_html( (int) $flow['step_order'] ); ?></span>
                        <div>
                            <strong><?php echo esc_html( $flow['step_name'] ); ?></strong>
                            <p><?php echo esc_html( $flow['approver_names'] ); ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else : ?>
            <p class="ums-user-muted">Phòng ban này chưa cấu hình luồng duyệt.</p>
        <?php endif; ?>
    </section>
</div>
