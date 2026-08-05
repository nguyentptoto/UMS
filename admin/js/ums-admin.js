(function ($) {
    'use strict';

    function initializeApprovalStepOrder() {
        var selector = 'input[name="ums_approval_flow[step_order]"]';
        var $inputs = $(selector);

        $inputs.attr({ min: 1, step: 1 });

        $inputs.on('input', function () {
            var value = $(this).val();

            if (value !== '' && parseInt(value, 10) < 1) {
                $(this).val(1);
            }
        });

        $inputs.on('blur change', function () {
            var value = parseInt($(this).val(), 10);

            if (!Number.isFinite(value) || value < 1) {
                $(this).val(1);
            }
        });
    }

    function initializeAnnualAllowanceApplyFields() {
        $('[data-ums-annual-apply-type]').each(function () {
            var $applyType = $(this);
            var $form = $applyType.closest('form');
            var $fields = $form.find('[data-ums-annual-apply-field]');

            function updateApplyFields() {
                var selectedType = $applyType.val();

                $fields.each(function () {
                    var $field = $(this);
                    var isActive = $field.attr('data-ums-annual-apply-field') === selectedType;

                    $field.prop('hidden', !isActive);
                    $field.find('select')
                        .prop('disabled', !isActive)
                        .prop('required', isActive);
                });
            }

            $applyType.on('change', updateApplyFields);
            updateApplyFields();
        });
    }

    function initializeOrganizationGrid() {
        $('.ums-jqx-remote-grid').each(function () {
            var $grid = $(this);
            var columns = $grid.data('columns') || [];
            var fields = $grid.data('fields') || [];
            var filters = $grid.data('filters') || {};

            if (typeof columns === 'string') {
                columns = JSON.parse(columns || '[]');
            }
            if (typeof fields === 'string') {
                fields = JSON.parse(fields || '[]');
            }
            if (typeof filters === 'string') {
                filters = JSON.parse(filters || '{}');
            }

            var source = {
                datatype: 'json',
                datafields: fields,
                id: 'source_id',
                url: $grid.attr('data-url'),
                type: 'POST',
                root: 'rows',
                cache: false,
                data: $.extend({}, filters, {
                    action: 'ums_get_organization_employees',
                    security: $grid.attr('data-nonce')
                }),
                beforeprocessing: function (data) {
                    source.totalrecords = parseInt(data.total, 10) || 0;
                },
                sort: function () {
                    $grid.jqxGrid('updatebounddata', 'sort');
                }
            };
            var dataAdapter = new $.jqx.dataAdapter(source);

            $grid.jqxGrid({
                width: '100%',
                autoheight: true,
                source: dataAdapter,
                columns: columns,
                theme: 'energyblue',
                pageable: true,
                virtualmode: true,
                rendergridrows: function (params) {
                    return params.data;
                },
                pagesize: 20,
                pagesizeoptions: ['10', '20', '50', '100'],
                sortable: true,
                filterable: false,
                columnsresize: true,
                altrows: true,
                enablebrowserselection: true,
                localization: {
                    emptydatastring: 'Không có dữ liệu',
                    loadtext: 'Đang tải...',
                    pagergotopagestring: 'Trang:',
                    pagershowrowsstring: 'Số dòng:',
                    pagerrangestring: ' / '
                }
            });
        });
    }

    function initializeSheetSyncBridge() {
        var $button = $('#ums-start-sheet-sync');
        var $log = $('#ums-sheet-sync-log');
        var activePopup = null;
        var bridgePosting = false;

        if (!$button.length || !$log.length) {
            return;
        }

        function appendLog(message, type) {
            var cssClass = 'ums-sync-log-line';

            if (type) {
                cssClass += ' ums-sync-log-line-' + type;
            }

            $('<div/>', {
                class: cssClass,
                text: '[' + new Date().toLocaleTimeString() + '] ' + message
            }).appendTo($log);

            $log.scrollTop($log.prop('scrollHeight'));
        }

        function postPayloadFromAdmin(payload, batchSize, mode) {
            var endpoint = String($button.attr('data-rest-endpoint') || '').trim();
            var token = String($button.attr('data-sync-token') || '').trim();
            var isOrganization = mode === 'organization';
            var rows = payload && Array.isArray(payload.rows) ? payload.rows : [];
            var users = payload && Array.isArray(payload.users) ? payload.users : [];
            var items = isOrganization ? (rows.length ? rows : users) : users;
            var size = parseInt(batchSize, 10) || 200;
            var syncToken = 'sheet' + String(Date.now()) + String(Math.floor(Math.random() * 100000));
            var total = {
                count: 0,
                created: 0,
                updated: 0,
                failed: 0,
                deleted: 0,
                errors: []
            };

            if (!endpoint || !token || !items.length) {
                appendLog('Không đủ dữ liệu để gửi fallback từ trang Admin.', 'error');
                $button.prop('disabled', false).text('Bắt đầu đồng bộ');
                return;
            }

            bridgePosting = true;
            $button.prop('disabled', true).text('Đang đồng bộ...');

            function sendBatch(offset) {
                var batch = items.slice(offset, offset + size);
                var body = $.extend({}, payload, {
                    batch_offset: offset,
                    batch_size: batch.length,
                    sync_token: syncToken,
                    finalize: offset + size >= items.length
                });

                if (isOrganization) {
                    delete body.users;
                    body.rows = batch;
                } else {
                    body.users = batch;
                }

                appendLog('Admin bridge gửi batch ' + (offset + 1) + '-' + (offset + batch.length) + '...', 'info');

                return window.fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json; charset=utf-8',
                        'X-Sync-Token': token
                    },
                    body: JSON.stringify(body)
                }).then(function (response) {
                    return response.text().then(function (text) {
                        var decoded;

                        try {
                            decoded = JSON.parse(text);
                        } catch (error) {
                            throw new Error('WordPress trả về dữ liệu không phải JSON. HTTP ' + response.status + ': ' + text);
                        }

                        if (response.status < 200 || response.status >= 300) {
                            throw new Error('WordPress từ chối batch. HTTP ' + response.status + ': ' + text);
                        }

                        total.count += Number(decoded.count || (decoded.summary && decoded.summary.received) || 0);
                        total.created += Number(decoded.created || (decoded.summary && decoded.summary.created) || 0);
                        total.updated += Number(decoded.updated || (decoded.summary && decoded.summary.updated) || 0);
                        total.failed += Number(decoded.failed || (decoded.summary && decoded.summary.failed) || 0);
                        total.deleted += Number(decoded.deleted || 0);
                        total.errors = total.errors.concat(decoded.errors || []);

                        if (offset + size < items.length) {
                            return sendBatch(offset + size);
                        }

                        return total;
                    });
                });
            }

            sendBatch(0).then(function (summary) {
                appendLog('Hoàn tất đồng bộ qua Admin bridge.', summary.failed > 0 ? 'warning' : 'success');
                appendLog(JSON.stringify(summary), summary.failed > 0 ? 'warning' : 'success');
            }).catch(function (error) {
                appendLog(error.message || String(error), 'error');
            }).finally(function () {
                bridgePosting = false;
                $button.prop('disabled', false).text('Bắt đầu đồng bộ');
            });
        }

        window.addEventListener('message', function (event) {
            var data = event.data || {};

            if (!data || data.source !== 'ums-sheet-sync') {
                return;
            }

            if (activePopup && event.source && event.source !== activePopup) {
                return;
            }

            if (data.action === 'admin-post') {
                appendLog('Popup yêu cầu chuyển sang Admin bridge.', 'warning');
                postPayloadFromAdmin(data.payload || {}, data.batchSize || 200, data.mode || String($button.attr('data-sync-mode') || 'users'));
                return;
            }

            if (data.message) {
                appendLog(data.message, data.status || 'info');
            }

            if (data.payload) {
                appendLog(JSON.stringify(data.payload), data.status || 'info');
            }

            if (data.done) {
                $button.prop('disabled', false).text('Bắt đầu đồng bộ');
            }
        });

        $button.on('click', function () {
            var appsScriptUrl = String($button.attr('data-apps-script-url') || '').trim();
            var syncMode = String($button.attr('data-sync-mode') || 'users').trim() || 'users';

            if (!appsScriptUrl) {
                window.alert('Vui lòng cấu hình Google Apps Script Web App URL trước khi đồng bộ.');
                return;
            }

            $log.empty();
            appendLog('Đang mở popup Google Apps Script...', 'info');

            var separator = appsScriptUrl.indexOf('?') >= 0 ? '&' : '?';
            var popupUrl = appsScriptUrl + separator + 'mode=' + encodeURIComponent(syncMode) + '&ums_module=tvn_org';
            activePopup = window.open(popupUrl, 'umsSheetSyncPopup', 'width=860,height=720,menubar=no,toolbar=no,location=yes,status=yes,scrollbars=yes,resizable=yes');
            if (!activePopup) {
                appendLog('Trình duyệt đã chặn popup. Hãy cho phép popup cho trang Admin này.', 'error');
                return;
            }

            $button.prop('disabled', true).text('Đang đồng bộ...');

            var popupCheck = window.setInterval(function () {
                if (activePopup && activePopup.closed) {
                    window.clearInterval(popupCheck);
                    if (!bridgePosting) {
                        $button.prop('disabled', false).text('Bắt đầu đồng bộ');
                    }
                    activePopup = null;
                }
            }, 1000);
        });
    }

    $(function () {
        initializeApprovalStepOrder();
        initializeAnnualAllowanceApplyFields();
        initializeOrganizationGrid();
        initializeSheetSyncBridge();

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
                theme: 'energyblue',
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
