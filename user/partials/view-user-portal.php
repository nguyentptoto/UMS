<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_user = wp_get_current_user();
$request_types = array( 'Cấp mới', 'Định kỳ', 'Phát sinh' );
?>

<div class="ums-user-shell">
    <header class="ums-user-header">
        <div>
            <p class="ums-user-kicker">UMS User Portal</p>
            <h2><?php echo esc_html( $profile['full_name'] ); ?></h2>
            <p><?php echo esc_html( $profile['employee_code'] . ' - ' . $profile['department'] . ' - ' . $profile['job_position'] ); ?></p>
        </div>
        <div class="ums-user-account">
            <span><?php echo esc_html( $current_user->user_login ); ?></span>
            <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>">Đăng xuất</a>
        </div>
    </header>

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

    <div class="ums-user-layout">
        <main class="ums-user-main">
            <section class="ums-user-panel">
                <div class="ums-user-panel-head">
                    <div>
                        <h3>Tạo yêu cầu đồng phục</h3>
                        <p>Người nhận chỉ hiển thị nhân sự active cùng phòng ban.</p>
                    </div>
                    <span class="ums-user-badge">Bản giao diện</span>
                </div>

                <form class="ums-user-request-form" data-ums-user-form>
                    <label>
                        <span>Người nhận</span>
                        <select name="target_user_id">
                            <?php foreach ( $teammates as $teammate ) : ?>
                                <option value="<?php echo esc_attr( $teammate['user_id'] ); ?>" <?php selected( (int) $teammate['user_id'], (int) $profile['user_id'] ); ?>>
                                    <?php echo esc_html( $teammate['employee_code'] . ' - ' . $teammate['full_name'] ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Loại yêu cầu</span>
                        <select name="request_type">
                            <?php foreach ( $request_types as $request_type ) : ?>
                                <option value="<?php echo esc_attr( $request_type ); ?>"><?php echo esc_html( $request_type ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="ums-user-wide">
                        <span>Lý do</span>
                        <textarea name="reason_detail" rows="4" placeholder="Nhập lý do phát sinh hoặc ghi chú cho người duyệt"></textarea>
                    </label>

                    <div class="ums-user-wide">
                        <span class="ums-user-label">Chọn sản phẩm khả dụng</span>
                        <div class="ums-user-product-list">
                            <?php if ( ! empty( $inventory_items ) ) : ?>
                                <?php foreach ( array_slice( $inventory_items, 0, 12 ) as $item ) : ?>
                                    <label class="ums-user-product">
                                        <input type="checkbox" name="items[]" value="<?php echo esc_attr( $item['item_id'] ); ?>">
                                        <span>
                                            <strong><?php echo esc_html( trim( $item['parent_category_name'] . ' / ' . $item['category_name'], ' /' ) ); ?></strong>
                                            <?php echo esc_html( $item['item_variant'] ? $item['item_variant'] . ' - Size ' . $item['size'] : 'Size ' . $item['size'] ); ?>
                                        </span>
                                        <em><?php echo esc_html( (int) $item['stock_qty'] ); ?></em>
                                    </label>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <p class="ums-user-muted">Chưa có sản phẩm khả dụng trong kho.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="ums-user-actions">
                        <button type="button" class="ums-user-button" data-ums-user-preview>Xem trước yêu cầu</button>
                        <p class="ums-user-muted" data-ums-user-message>Chức năng lưu phiếu sẽ kết nối sau khi bổ sung lớp dữ liệu phiếu yêu cầu.</p>
                    </div>
                </form>
            </section>
        </main>

        <aside class="ums-user-side">
            <section class="ums-user-panel">
                <h3>Luồng duyệt phòng ban</h3>
                <?php if ( ! empty( $approval_flows ) ) : ?>
                    <ol class="ums-user-flow">
                        <?php foreach ( $approval_flows as $flow ) : ?>
                            <li>
                                <span><?php echo esc_html( (int) $flow['step_order'] ); ?></span>
                                <div>
                                    <strong><?php echo esc_html( $flow['step_name'] ); ?></strong>
                                    <p><?php echo esc_html( self::format_approver_names( $flow['approver_profile_ids'] ) ); ?></p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else : ?>
                    <p class="ums-user-muted">Phòng ban này chưa cấu hình luồng duyệt.</p>
                <?php endif; ?>
            </section>

            <section class="ums-user-panel">
                <h3>Hồ sơ của tôi</h3>
                <dl class="ums-user-profile-list">
                    <div><dt>Giới tính</dt><dd><?php echo esc_html( $profile['gender'] ); ?></dd></div>
                    <div><dt>Thai sản</dt><dd><?php echo (int) $profile['is_maternity'] === 1 ? 'Có' : 'Không'; ?></dd></div>
                    <div><dt>Ngoài trời</dt><dd><?php echo (int) $profile['is_outdoor_worker'] === 1 ? 'Có' : 'Không'; ?></dd></div>
                    <div><dt>Chuyển bộ phận</dt><dd><?php echo ! empty( $profile['transfer_date'] ) ? esc_html( mysql2date( 'd/m/Y', $profile['transfer_date'] ) ) : 'Không có'; ?></dd></div>
                </dl>
            </section>
        </aside>
    </div>
</div>
