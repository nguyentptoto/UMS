(function ($) {
    'use strict';

    $(function () {
        var $form = $('#ums-pr-calculation-form');

        if (!$form.length) {
            return;
        }

        var $calculateButton = $('#ums-calculate-pr');
        var $exportButton = $('#ums-export-pr');
        var $status = $('#ums-pr-status');
        var $panel = $('#ums-pr-result-panel');
        var $messages = $('#ums-pr-messages');
        var $summary = $('#ums-pr-summary');
        var $grid = $('#ums-pr-result-grid');
        var numberFormat = window.Intl ? new Intl.NumberFormat('vi-VN') : null;

        function formatNumber(value) {
            var number = Number(value) || 0;

            return numberFormat ? numberFormat.format(number) : String(number);
        }

        function showPanel() {
            $panel.prop('hidden', false).show();
        }

        function renderMessages(errors, warnings) {
            $messages.empty();

            if (errors && errors.length) {
                $('<div/>', {'class': 'notice notice-error inline'})
                    .append($('<p/>').text(errors.join(' ')))
                    .appendTo($messages);
            }

            if (warnings && warnings.length) {
                $('<div/>', {'class': 'notice notice-warning inline'})
                    .append($('<p/>').text(warnings.join(' ')))
                    .appendTo($messages);
            }
        }

        function renderFallbackTable(rows) {
            var columns = [
                ['sap_code', 'Mã SAP'],
                ['item_name', 'Loại đồng phục'],
                ['size', 'Size'],
                ['periodic_qty', 'SL định kỳ'],
                ['reserve_qty', 'SL dự phòng'],
                ['stock_qty', 'Tồn kho'],
                ['final_pr_qty', 'SL PR'],
                ['base_price', 'Đơn giá'],
                ['estimated_amount', 'Thành tiền dự kiến']
            ];
            var $table = $('<table/>', {'class': 'widefat striped ums-pr-fallback-table'});
            var $headRow = $('<tr/>');
            var $body = $('<tbody/>');

            columns.forEach(function (column) {
                $('<th/>').text(column[1]).appendTo($headRow);
            });
            $table.append($('<thead/>').append($headRow));

            rows.forEach(function (row) {
                var $row = $('<tr/>');

                columns.forEach(function (column, index) {
                    var value = row[column[0]];

                    if (index >= 3) {
                        value = formatNumber(value);
                    }
                    $('<td/>').text(value === null || value === undefined ? '' : value).appendTo($row);
                });
                $body.append($row);
            });

            if (!rows.length) {
                $body.append($('<tr/>').append($('<td/>', {colspan: columns.length}).text('Không có sản phẩm cần tính.')));
            }

            $grid.empty().append($table);
        }

        function renderGrid(rows) {
            if (!$.jqx || !$.jqx.dataAdapter || typeof $grid.jqxGrid !== 'function') {
                renderFallbackTable(rows);
                return;
            }

            var source = {
                datatype: 'array',
                localdata: rows,
                datafields: [
                    {name: 'sap_code', type: 'string'},
                    {name: 'item_name', type: 'string'},
                    {name: 'size', type: 'string'},
                    {name: 'periodic_qty', type: 'number'},
                    {name: 'reserve_qty', type: 'number'},
                    {name: 'stock_qty', type: 'number'},
                    {name: 'final_pr_qty', type: 'number'},
                    {name: 'base_price', type: 'number'},
                    {name: 'estimated_amount', type: 'number'}
                ]
            };
            var options = {
                width: '100%',
                autoheight: true,
                source: new $.jqx.dataAdapter(source),
                theme: 'energyblue',
                pageable: true,
                pagesize: 20,
                pagesizeoptions: ['10', '20', '50', '100'],
                sortable: true,
                filterable: true,
                showfilterrow: true,
                columnsresize: true,
                altrows: true,
                columns: [
                    {text: 'Mã SAP', datafield: 'sap_code', width: 115},
                    {text: 'Loại đồng phục', datafield: 'item_name', minwidth: 260},
                    {text: 'Size', datafield: 'size', width: 70, cellsalign: 'center'},
                    {text: 'SL định kỳ', datafield: 'periodic_qty', width: 100, cellsalign: 'right'},
                    {text: 'SL dự phòng', datafield: 'reserve_qty', width: 105, cellsalign: 'right'},
                    {text: 'Tồn kho', datafield: 'stock_qty', width: 90, cellsalign: 'right'},
                    {text: 'SL PR', datafield: 'final_pr_qty', width: 90, cellsalign: 'right'},
                    {text: 'Đơn giá', datafield: 'base_price', width: 120, cellsalign: 'right', cellsformat: 'n0'},
                    {text: 'Thành tiền dự kiến', datafield: 'estimated_amount', width: 155, cellsalign: 'right', cellsformat: 'n0'}
                ],
                localization: {
                    emptydatastring: 'Không có sản phẩm cần tính',
                    loadtext: 'Đang tải...',
                    pagergotopagestring: 'Trang:',
                    pagershowrowsstring: 'Số dòng:',
                    pagerrangestring: ' / '
                }
            };

            try {
                $grid.jqxGrid(options);
                if ($grid.hasClass('jqx-widget')) {
                    $grid.jqxGrid('updatebounddata');
                }
            } catch (error) {
                renderFallbackTable(rows);
                renderMessages([], ['Không thể khởi tạo bảng jqx; dữ liệu đang được hiển thị bằng bảng dự phòng.']);
            }
        }

        function responseErrors(xhr) {
            var response = xhr && xhr.responseJSON ? xhr.responseJSON : {};
            var data = response.data || {};
            var errors = Array.isArray(data.errors) ? data.errors.slice() : [];

            if (!errors.length && data.message) {
                errors.push(data.message);
            }
            if (!errors.length && response.message) {
                errors.push(response.message);
            }
            if (!errors.length) {
                errors.push('Không thể tính số lượng PR. HTTP ' + (xhr.status || 0) + '.');
            }

            return errors;
        }

        $form.off('change.umsPr input.umsPr').on('change.umsPr input.umsPr', 'input, select', function () {
            $exportButton.prop('disabled', true);
            if ($panel.is(':visible')) {
                $status.text('Dữ liệu đầu vào đã thay đổi. Hãy tính lại trước khi xuất.');
            }
        });

        // Loại bỏ handler cũ trong file quản trị dùng chung để tránh gửi hai request.
        $calculateButton.off('click').on('click.umsPr', function () {
            var form = $form.get(0);

            if (form.reportValidity && !form.reportValidity()) {
                return;
            }

            showPanel();
            renderMessages([], []);
            $summary.empty();
            $grid.empty();
            $status.text('Đang đọc file và tổng hợp dữ liệu...');
            $calculateButton.prop('disabled', true).text('Đang tính...');
            $exportButton.prop('disabled', true);

            if (!window.umsAdmin || !window.umsAdmin.ajaxUrl) {
                renderMessages(['Không tải được cấu hình AJAX của UMS. Hãy tải lại trang.'], []);
                $status.text('Tính PR thất bại.');
                $calculateButton.prop('disabled', false).text('Tính số lượng PR');
                return;
            }

            var payload = new FormData(form);
            payload.set('action', 'ums_calculate_pr');
            payload.set('security', $form.find('[name="pr_security"]').val() || '');

            $.ajax({
                url: window.umsAdmin.ajaxUrl,
                method: 'POST',
                data: payload,
                dataType: 'json',
                processData: false,
                contentType: false
            }).done(function (response) {
                if (!response || !response.success || !response.data) {
                    renderMessages(['Không nhận được kết quả tính PR hợp lệ.'], []);
                    $status.text('Tính PR thất bại.');
                    return;
                }

                var data = response.data;
                var resultSummary = data.summary || {};

                renderMessages([], data.warnings || []);
                $summary.text(
                    'Định kỳ: ' + formatNumber(resultSummary.periodic_qty) +
                    ' | Dự phòng: ' + formatNumber(resultSummary.reserve_qty) +
                    ' | Tồn kho: ' + formatNumber(resultSummary.stock_qty) +
                    ' | Số lượng PR: ' + formatNumber(resultSummary.final_pr_qty) +
                    ' | Giá trị dự kiến: ' + formatNumber(resultSummary.estimated_amount) + ' VND'
                );
                renderGrid(data.rows || []);
                $exportButton.prop('disabled', !data.can_export);
                $status.text('Đã tính xong ' + formatNumber(resultSummary.row_count) + ' dòng.');
            }).fail(function (xhr) {
                renderMessages(responseErrors(xhr), []);
                $status.text('Tính PR thất bại.');
            }).always(function () {
                $calculateButton.prop('disabled', false).text('Tính số lượng PR');
            });
        });
    });
}(jQuery));
