<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ums-user-shell">
    <section class="ums-user-empty">
        <h2>UMS User Portal</h2>
        <p>Bạn cần đăng nhập để xem hồ sơ và tạo yêu cầu đồng phục.</p>
        <a class="ums-user-button" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Đăng nhập</a>
    </section>
</div>
