(function ($) {
    'use strict';

    $(function () {
        $(document).on('click', '.ums-delete-link', function (event) {
            var message = $(this).data('confirm') || 'Bạn có chắc muốn xóa hồ sơ này?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });

        $(document).on('click', '.ums-sync-password-button', function (event) {
            event.preventDefault();

            var $button = $(this);
            var userIds = $button.data('user-ids') || [];
            var userId = parseInt($button.data('user-id'), 10) || 0;

            if (typeof userIds === 'string') {
                try {
                    userIds = JSON.parse(userIds || '[]');
                } catch (error) {
                    userIds = [];
                }
            }

            if (!Array.isArray(userIds) || userIds.length === 0) {
                userIds = userId ? [userId] : [];
            }

            if (!userIds.length || !window.umsAdmin) {
                window.alert('Không tìm thấy tài khoản WordPress cần đồng bộ.');
                return;
            }

            if (!window.confirm('Đồng bộ mật khẩu cho ' + userIds.length + ' tài khoản đang hiển thị? Nếu DB nguồn lỗi, hệ thống sẽ đặt mật khẩu mặc định.')) {
                return;
            }

            $button.prop('disabled', true).text('Đang đồng bộ...');

            $.post(window.umsAdmin.ajaxUrl, {
                action: 'ums_sync_user_password',
                security: window.umsAdmin.passwordSyncNonce,
                user_ids: userIds
            }).done(function (response) {
                var message = response && response.data && response.data.message
                    ? response.data.message
                    : 'Đã đồng bộ mật khẩu.';
                window.alert(message);
            }).fail(function (xhr) {
                var response = xhr.responseJSON || {};
                var message = response.data && response.data.message
                    ? response.data.message
                    : 'Không đồng bộ được mật khẩu.';
                window.alert(message);
            }).always(function () {
                $button.prop('disabled', false).text('Đồng bộ mật khẩu');
            });
        });

        $('.ums-jqx-grid').each(function () {
            var $grid = $(this);
            var rows = $grid.data('rows') || [];
            var columns = $grid.data('columns') || [];
            var groups = $grid.data('groups') || [];

            if (typeof rows === 'string') {
                rows = JSON.parse(rows || '[]');
            }

            if (typeof columns === 'string') {
                columns = JSON.parse(columns || '[]');
            }

            if (typeof groups === 'string') {
                groups = JSON.parse(groups || '[]');
            }

            var source = {
                datatype: 'array',
                localdata: rows
            };
            var dataAdapter = new $.jqx.dataAdapter(source);

            columns = columns.map(function (column) {
                if (column.cellsrenderer === 'html') {
                    column.cellsrenderer = function (row, datafield, value) {
                        return '<div class="ums-jqx-cell-html">' + (value || '') + '</div>';
                    };
                }

                return column;
            });

            $grid.jqxGrid({
                width: '100%',
                autoheight: true,
                source: dataAdapter,
                columns: columns,
                theme: 'fluent',
                pageable: true,
                pagesize: 20,
                pagesizeoptions: ['10', '20', '50', '100'],
                sortable: true,
                filterable: true,
                showfilterrow: true,
                columnsresize: true,
                altrows: true,
                groupable: groups.length > 0,
                groups: groups,
                enablebrowserselection: true,
                localization: {
                    emptydatastring: 'Không có dữ liệu',
                    filterstringcomparisonoperators: ['rỗng', 'không rỗng', 'chứa', 'chứa - phân biệt hoa thường', 'không chứa', 'không chứa - phân biệt hoa thường', 'bắt đầu bằng', 'bắt đầu bằng - phân biệt hoa thường', 'kết thúc bằng', 'kết thúc bằng - phân biệt hoa thường', 'bằng', 'bằng - phân biệt hoa thường', 'null', 'không null'],
                    filterselectstring: 'Chọn bộ lọc',
                    loadtext: 'Đang tải...',
                    pagergotopagestring: 'Trang:',
                    pagershowrowsstring: 'Số dòng:',
                    pagerrangestring: ' / '
                }
            });
        });
    });
})(jQuery);
