<?php
/**
 * Standalone UMS User Portal template.
 *
 * This template intentionally does not call get_header() or get_footer()
 * so the active theme cannot render its logo, menu, footer widgets, or layout.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_user_logged_in() ) {
    wp_safe_redirect( wp_login_url( get_permalink() ) );
    exit;
}

$page_title = get_the_title();
UMS_User::enqueue_portal_assets();
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $page_title ? $page_title : 'UMS User Portal' ); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'ums-standalone-body' ); ?>>
    <?php if ( function_exists( 'wp_body_open' ) ) { wp_body_open(); } ?>
    <div id="ums-standalone-root">
        <?php echo UMS_User::render_portal_shortcode(); ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
