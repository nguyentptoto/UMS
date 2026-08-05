<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<header class="ums-portal-topbar">
    <div class="ums-portal-brand">
        <span>UMS</span>
        <div>
            <strong>Uniform Management</strong>
            <small><?php echo esc_html( $profile['employee_code'] . ' - ' . $profile['full_name'] ); ?></small>
        </div>
    </div>

    <nav class="ums-portal-nav" aria-label="UMS user navigation">
        <?php foreach ( $portal_pages as $page_key => $page ) : ?>
            <?php if ( isset( $page['nav'] ) && $page['nav'] === false ) : ?>
                <?php continue; ?>
            <?php endif; ?>
            <a
                class="<?php echo $current_page === $page_key ? 'is-active' : ''; ?>"
                href="<?php echo esc_url( add_query_arg( 'ums_page', $page_key, $portal_url ) ); ?>"
            >
                <?php echo esc_html( $page['label'] ); ?>
            </a>
        <?php endforeach; ?>
        <button
            class="ums-theme-toggle"
            type="button"
            data-ums-theme-toggle
            data-ums-theme-state="light"
            aria-label="Bật chế độ tối"
            aria-pressed="false"
            title="Bật chế độ tối"
        ></button>
    </nav>
</header>
