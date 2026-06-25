<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="ums-user-shell">
    <?php include UMS_PLUGIN_DIR . 'user/partials/layout/header.php'; ?>

    <main class="ums-user-page" aria-live="polite">
        <?php if ( ! empty( $portal_notice ) ) : ?>
            <div class="ums-user-notice ums-user-notice-<?php echo esc_attr( $portal_notice['type'] ); ?>">
                <?php echo esc_html( $portal_notice['message'] ); ?>
            </div>
        <?php endif; ?>

        <?php include $page_template; ?>
    </main>

    <?php include UMS_PLUGIN_DIR . 'user/partials/layout/footer.php'; ?>
</div>
