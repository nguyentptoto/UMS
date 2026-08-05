<?php
/**
 * Trang cấu hình Popup Bridge đồng bộ nhân sự từ Google Sheet.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$last_summary = isset( $last_log['summary'] ) && is_array( $last_log['summary'] ) ? $last_log['summary'] : array();
$last_errors  = isset( $last_log['errors'] ) && is_array( $last_log['errors'] ) ? $last_log['errors'] : array();
?>
<div class="wrap ums-admin-wrap ums-sheet-sync-page">
    <h1>UMS - Đồng bộ nhân sự từ Google Sheet</h1>

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
                    <td>
                        <input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $rest_endpoint ); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">X-Sync-Token</th>
                    <td>
                        <input type="text" class="regular-text code" readonly value="<?php echo esc_attr( $sync_token ); ?>">
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
                <p class="description">Popup sẽ dùng phiên SSO của trình duyệt để đọc Sheet, sau đó POST dữ liệu về endpoint nội bộ của UMS.</p>
            </div>
            <button
                type="button"
                class="button button-primary"
                id="ums-start-sheet-sync"
                data-apps-script-url="<?php echo esc_attr( $apps_script_url ); ?>"
                data-rest-endpoint="<?php echo esc_attr( $rest_endpoint ); ?>"
                data-sync-token="<?php echo esc_attr( $sync_token ); ?>"
            >
                Bắt đầu đồng bộ
            </button>
        </div>

        <div class="ums-sync-log" id="ums-sheet-sync-log" aria-live="polite">
            <div class="ums-sync-log-line">Sẵn sàng đồng bộ.</div>
        </div>
    </div>

    <div class="ums-panel">
        <h2>Kết quả gần nhất</h2>
        <?php if ( empty( $last_log ) ) : ?>
            <p class="description">Chưa có phiên đồng bộ nào được ghi nhận.</p>
        <?php else : ?>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>Thời gian</th>
                        <td><?php echo esc_html( isset( $last_log['synced_at'] ) ? $last_log['synced_at'] : '-' ); ?></td>
                    </tr>
                    <tr>
                        <th>Trạng thái</th>
                        <td><?php echo ! empty( $last_log['success'] ) ? 'Thành công' : 'Có lỗi'; ?></td>
                    </tr>
                    <tr>
                        <th>Nguồn</th>
                        <td><?php echo esc_html( isset( $last_log['source'] ) ? $last_log['source'] : '-' ); ?></td>
                    </tr>
                    <tr>
                        <th>Tổng hợp</th>
                        <td>
                            Nhận: <?php echo esc_html( isset( $last_summary['received'] ) ? $last_summary['received'] : 0 ); ?>,
                            tạo mới: <?php echo esc_html( isset( $last_summary['created'] ) ? $last_summary['created'] : 0 ); ?>,
                            cập nhật: <?php echo esc_html( isset( $last_summary['updated'] ) ? $last_summary['updated'] : 0 ); ?>,
                            lỗi: <?php echo esc_html( isset( $last_summary['failed'] ) ? $last_summary['failed'] : 0 ); ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if ( ! empty( $last_errors ) ) : ?>
                <h3>Dòng lỗi</h3>
                <ul class="ums-sync-error-list">
                    <?php foreach ( $last_errors as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
