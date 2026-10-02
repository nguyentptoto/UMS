<?php
/**
 * Trang cấu hình Popup Bridge cho Google Sheet "Danh sách CNV".
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$auto_start_sync = isset( $_GET['ums_auto_sync'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['ums_auto_sync'] ) );
?>
<div class="wrap ums-admin-wrap ums-sheet-sync-page">
    <h1>UMS - Cấu hình Google Sheet Danh sách CNV</h1>

    <?php if ( ! empty( $notice ) ) : ?>
        <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
            <p><?php echo esc_html( $notice['message'] ); ?></p>
        </div>
    <?php endif; ?>

    <div class="ums-panel">
        <h2>Cấu hình Popup Bridge</h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ums_save_sheet_sync_settings' ); ?>
            <input type="hidden" name="action" value="ums_save_sheet_sync_settings">

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ums-apps-script-url">Google Apps Script Web App URL</label></th>
                    <td>
                        <input
                            type="url"
                            id="ums-apps-script-url"
                            name="apps_script_url"
                            class="regular-text code"
                            value="<?php echo esc_attr( $apps_script_url ); ?>"
                            placeholder="https://script.google.com/a/macros/..."
                        >
                        <p class="description">Deploy Apps Script dạng Web App, Execute as Me, quyền truy cập trong domain công ty.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">REST Endpoint</th>
                    <td><input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $rest_endpoint ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">X-Sync-Token</th>
                    <td><input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $sync_token ); ?>"></td>
                </tr>
                <tr>
                    <th scope="row">Auto Bridge URL</th>
                    <td>
                        <input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $bridge_url ); ?>">
                        <p class="description">Dùng URL này cho Windows Task Scheduler nếu muốn đồng bộ tự động mà không cần đăng nhập WP Admin.</p>
                    </td>
                </tr>
            </table>

            <?php submit_button( 'Lưu cấu hình' ); ?>
        </form>
    </div>

    <div class="ums-panel">
        <div class="ums-panel-heading-row">
            <div>
                <h2>Chạy đồng bộ</h2>
                <p class="description">Popup sẽ đọc Sheet Danh sách CNV và đồng bộ về Sơ đồ tổ chức TVN.</p>
            </div>
            <button
                type="button"
                class="button button-primary"
                id="ums-start-sheet-sync"
                data-apps-script-url="<?php echo esc_attr( $apps_script_url ); ?>"
                data-rest-endpoint="<?php echo esc_attr( $rest_endpoint ); ?>"
                data-sync-token="<?php echo esc_attr( $sync_token ); ?>"
                data-sync-mode="organization"
                data-auto-start="<?php echo $auto_start_sync ? '1' : '0'; ?>"
            >
                Bắt đầu đồng bộ
            </button>
        </div>

        <div class="ums-sync-log" id="ums-sheet-sync-log" aria-live="polite">
            <div class="ums-sync-log-line">Sẵn sàng đồng bộ Sheet Danh sách CNV.</div>
        </div>
    </div>
</div>
