<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<footer class="ums-portal-footer">
    <span>UMS User Portal</span>
    <span><?php echo esc_html( $profile['department'] . ' - ' . $profile['factory_location'] ); ?></span>
</footer>
