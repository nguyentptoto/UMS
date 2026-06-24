(function ($) {
    'use strict';

    $(function () {
        $(document).on('click', '.ums-delete-link', function (event) {
            var message = $(this).data('confirm') || 'Bạn có chắc muốn xóa hồ sơ này?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
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
