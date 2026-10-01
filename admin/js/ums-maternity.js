(function ($) {
    'use strict';
    $(function () {
        $('.ums-maternity form[data-confirm]').on('submit', function (event) {
            if (!window.confirm($(this).attr('data-confirm'))) { event.preventDefault(); }
        });
        var $table = $('#ums-maternity-table');
        if (!$table.length || !$.fn.jqxGrid) { return; }
        var rows = [];
        $table.find('tbody tr').each(function () {
            var row = {};
            $(this).children('td').each(function (index) {
                row['c' + index] = $(this).text();
                if (index === 7) { row.url = $(this).find('a').attr('href'); }
            });
            rows.push(row);
        });
        var fields = [], columns = [];
        $table.find('thead th').each(function (index) {
            fields.push({ name: 'c' + index, type: 'string' });
            columns.push({
                text: $(this).text(), datafield: 'c' + index, width: index === 1 ? 230 : 150,
                cellsrenderer: function (row, field, value) {
                    return $('<div>').css({ padding: '8px' }).text(value == null ? '' : value)[0].outerHTML;
                }
            });
        });
        fields.push({ name: 'url', type: 'string' });
        columns[7].filterable = false;
        columns[7].cellsrenderer = function (index, field, value, html, props, row) {
            return $('<div>').css({ padding: '8px' }).append($('<a>').attr('href', row.url).text('Chi tiết'))[0].outerHTML;
        };
        var $grid = $('<div>').insertBefore($table);
        $grid.jqxGrid({
            width: '100%', autoheight: true, theme: 'energyblue', pageable: true,
            pagesize: 20, pagesizeoptions: ['20', '50', '100'], sortable: true,
            filterable: true, showfilterrow: true, altrows: true,
            localization: { emptydatastring: 'Không có dữ liệu', pagergotopagestring: 'Trang:', pagershowrowsstring: 'Số dòng:', pagerrangestring: ' / ', loadtext: 'Đang tải...' },
            source: new $.jqx.dataAdapter({ datatype: 'array', localdata: rows, datafields: fields }),
            columns: columns
        });
        $table.hide();
    });
})(jQuery);
