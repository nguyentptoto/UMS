<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section class="ums-page-title">
    <div>
        <h1>Luồng duyệt</h1>
        <p>Chuỗi duyệt đang áp dụng cho phòng ban của bạn.</p>
    </div>
</section>

<section class="ums-user-panel">
    <?php if ( ! empty( $approval_flows ) ) : ?>
        <ol class="ums-user-flow">
            <?php foreach ( $approval_flows as $flow ) : ?>
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
