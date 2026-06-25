(function () {
    'use strict';

    var itemIndex = 1;

    function setVisibleOption(option, visible) {
        option.hidden = !visible;
        option.disabled = !visible;
    }

    function resetSelect(select) {
        if (select) {
            select.value = '';
        }
    }

    function getItemRow(element) {
        return element ? element.closest('[data-ums-request-item]') : null;
    }

    function getItemsContainer(form) {
        return form ? form.querySelector('[data-ums-request-items]') : null;
    }

    function updateTargetFields(select) {
        var option = select.options[select.selectedIndex];
        var form = select.closest('[data-ums-user-form]');
        if (!option || !form) {
            return;
        }

        var map = {
            employee_code: option.dataset.employeeCode || '',
            full_name: option.dataset.fullName || '',
            department: option.dataset.department || '',
            date_joined: option.dataset.dateJoined || ''
        };

        Object.keys(map).forEach(function (field) {
            var input = form.querySelector('[data-ums-target-field="' + field + '"]');
            if (input) {
                input.value = map[field];
            }
        });
    }

    function updateSizeFields(select) {
        var option = select.options[select.selectedIndex];
        var row = getItemRow(select);
        if (!row) {
            return;
        }

        var price = row.querySelector('[data-ums-inventory-field="price"]');
        var variant = row.querySelector('[data-ums-inventory-field="variant"]');
        var inventoryId = row.querySelector('[data-ums-inventory-field="inventory_id"]');
        var unitPrice = row.querySelector('[data-ums-inventory-field="unit_price"]');

        if (!option || !option.value) {
            if (price) {
                price.value = '';
            }
            if (variant) {
                variant.value = '';
            }
            if (inventoryId) {
                inventoryId.value = '';
            }
            if (unitPrice) {
                unitPrice.value = '';
            }
            return;
        }

        if (variant) {
            variant.value = option.dataset.variant || '';
        }

        if (inventoryId) {
            inventoryId.value = option.dataset.inventoryId || '';
        }

        if (unitPrice) {
            unitPrice.value = option.dataset.price || '';
        }

        updateRowTotal(row);
    }

    function formatNumber(value) {
        return String(Math.round(value || 0));
    }

    function updateRowTotal(row) {
        var price = row.querySelector('[data-ums-inventory-field="price"]');
        var unitPrice = row.querySelector('[data-ums-inventory-field="unit_price"]');
        var quantity = row.querySelector('[data-ums-quantity-input]');

        if (!price || !unitPrice) {
            return;
        }

        var amount = Number(unitPrice.value || 0) * Math.max(1, Number(quantity ? quantity.value || 1 : 1));
        price.value = unitPrice.value ? formatNumber(amount) : '';
    }

    function filterSizesByCategory(row) {
        var categorySelect = row.querySelector('[data-ums-child-category]');
        var sizeSelect = row.querySelector('[data-ums-size-select]');
        var categoryId = categorySelect ? categorySelect.value : '';

        if (!sizeSelect) {
            return;
        }

        Array.prototype.forEach.call(sizeSelect.options, function (option) {
            if (!option.value) {
                setVisibleOption(option, true);
                return;
            }

            setVisibleOption(option, categoryId !== '' && option.dataset.categoryId === categoryId);
        });

        resetSelect(sizeSelect);
        updateSizeFields(sizeSelect);
    }

    function filterChildrenByParent(row) {
        var parentSelect = row.querySelector('[data-ums-parent-category]');
        var childSelect = row.querySelector('[data-ums-child-category]');
        var parentId = parentSelect ? parentSelect.value : '';

        if (!childSelect) {
            return;
        }

        Array.prototype.forEach.call(childSelect.options, function (option) {
            if (!option.value) {
                setVisibleOption(option, true);
                return;
            }

            setVisibleOption(option, parentId !== '' && option.dataset.parentId === parentId);
        });

        resetSelect(childSelect);
        filterSizesByCategory(row);
    }

    function initRequestItem(row) {
        filterChildrenByParent(row);
    }

    function updateRemoveButtons(form) {
        var rows = form.querySelectorAll('[data-ums-request-item]');
        rows.forEach(function (row) {
            var button = row.querySelector('[data-ums-remove-item]');
            if (button) {
                button.hidden = rows.length <= 1;
            }
        });
    }

    function addRequestItem(form) {
        var template = form.querySelector('[data-ums-request-item-template]');
        var container = getItemsContainer(form);

        if (!template || !container) {
            return;
        }

        var html = template.innerHTML.replace(/__INDEX__/g, String(itemIndex++));
        var holder = document.createElement('div');
        holder.innerHTML = html.trim();

        var row = holder.firstElementChild;
        if (!row) {
            return;
        }

        row.classList.remove('is-template');
        container.appendChild(row);
        initRequestItem(row);
        updateRemoveButtons(form);
    }

    function removeRequestItem(button) {
        var row = getItemRow(button);
        var form = button.closest('[data-ums-user-form]');

        if (!row || !form) {
            return;
        }

        var rows = form.querySelectorAll('[data-ums-request-item]');
        if (rows.length <= 1) {
            return;
        }

        row.remove();
        updateRemoveButtons(form);
    }

    function updateReasonState(form) {
        var checked = form.querySelector('input[name="reason_type"]:checked');
        var reason = checked ? checked.value : '1';
        var paymentPanel = form.querySelector('[data-ums-payment-panel]');
        var reasonDetail = form.querySelector('[data-ums-reason-detail]');
        var paymentInputs = form.querySelectorAll('input[name="payment_method"]');

        if (reasonDetail) {
            reasonDetail.required = reason === '2' || reason === '3';
        }

        if (paymentPanel) {
            paymentPanel.hidden = reason !== '3';
        }

        paymentInputs.forEach(function (input) {
            input.required = reason === '3';
            if (reason !== '3') {
                input.checked = false;
            }
        });
    }

    document.addEventListener('change', function (event) {
        var form = event.target.closest('[data-ums-user-form]');
        var row = getItemRow(event.target);

        if (event.target.matches('[data-ums-target-select]')) {
            updateTargetFields(event.target);
        }

        if (row && event.target.matches('[data-ums-parent-category]')) {
            filterChildrenByParent(row);
        }

        if (row && event.target.matches('[data-ums-child-category]')) {
            filterSizesByCategory(row);
        }

        if (row && event.target.matches('[data-ums-size-select]')) {
            updateSizeFields(event.target);
        }

        if (row && event.target.matches('[data-ums-quantity-input]')) {
            updateRowTotal(row);
        }

        if (form && event.target.matches('input[name="reason_type"]')) {
            updateReasonState(form);
        }
    });

    document.addEventListener('input', function (event) {
        var row = getItemRow(event.target);

        if (row && event.target.matches('[data-ums-quantity-input]')) {
            updateRowTotal(row);
        }
    });

    document.addEventListener('click', function (event) {
        var addButton = event.target.closest('[data-ums-add-item]');
        if (addButton) {
            var addForm = addButton.closest('[data-ums-user-form]');
            if (addForm) {
                addRequestItem(addForm);
            }
            return;
        }

        var removeButton = event.target.closest('[data-ums-remove-item]');
        if (removeButton) {
            removeRequestItem(removeButton);
            return;
        }

        var submitButton = event.target.closest('[data-ums-submit-approval]');
        if (!submitButton) {
            return;
        }

        var form = submitButton.closest('[data-ums-user-form]');
        if (!form) {
            return;
        }

        updateReasonState(form);

        var message = form.querySelector('[data-ums-user-message]');
        var reason = form.querySelector('input[name="reason_type"]:checked');
        var rows = form.querySelectorAll('[data-ums-request-item]');
        var totalQuantity = 0;
        var totalAmount = 0;

        rows.forEach(function (row) {
            var quantity = row.querySelector('input[name$="[quantity]"]');
            var price = row.querySelector('[data-ums-inventory-field="price"]');
            totalQuantity += quantity ? Number(quantity.value || 0) : 0;
            totalAmount += price ? Number(price.value || 0) : 0;
        });

        if (message) {
            message.textContent = 'Đã ghi nhận thao tác gửi duyệt: ' + rows.length + ' dòng đồng phục, tổng SL ' + totalQuantity + ', tổng giá ' + formatNumber(totalAmount) + ', lý do ' + (reason ? reason.value : '1') + '. Bước tiếp theo sẽ lưu phiếu vào module yêu cầu và đẩy vào luồng duyệt.';
        }
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-ums-user-form]').forEach(function (form) {
            var targetSelect = form.querySelector('[data-ums-target-select]');
            if (targetSelect) {
                updateTargetFields(targetSelect);
            }

            form.querySelectorAll('[data-ums-request-item]').forEach(initRequestItem);
            updateRemoveButtons(form);
            updateReasonState(form);
        });
    });
})();
