<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ums-user-shell">
    <section class="ums-user-empty">
        <h2>UMS Portal đang gặp lỗi</h2>
        <p>Hệ thống chưa tải được giao diện người dùng. Vui lòng gửi thông báo này cho quản trị viên.</p>
        <?php if ( current_user_can( 'manage_options' ) && ! empty( $message ) ) : ?>
            <p class="ums-user-muted"><?php echo esc_html( $message ); ?></p>
        <?php endif; ?>
    </section>
</div>
