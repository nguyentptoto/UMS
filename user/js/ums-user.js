(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-ums-user-preview]');
        if (!button) {
            return;
        }

        var form = button.closest('[data-ums-user-form]');
        if (!form) {
            return;
        }

        var checkedItems = form.querySelectorAll('input[name="items[]"]:checked').length;
        var message = form.querySelector('[data-ums-user-message]');

        form.classList.add('ums-user-previewing');

        if (message) {
            message.textContent = checkedItems > 0
                ? 'Đã chọn ' + checkedItems + ' sản phẩm. Bước tiếp theo sẽ lưu phiếu vào module yêu cầu.'
                : 'Bạn chưa chọn sản phẩm nào.';
        }
    });
})();
