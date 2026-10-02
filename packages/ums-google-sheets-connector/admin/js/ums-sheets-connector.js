(function ($) {
    'use strict';

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

        function startSheetSync() {
            if ($button.prop('disabled')) {
                appendLog('Dong bo tu dong khong chay vi nut dong bo dang bi khoa.', 'warning');
                return;
            }

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
        }

        $button.on('click', function () {
            startSheetSync();
        });

        if (String($button.attr('data-auto-start') || '') === '1') {
            appendLog('Che do tu dong: trang se bat dau dong bo sau khi tai xong.', 'info');
            window.setTimeout(startSheetSync, 1200);
        }
    }

	function initializeAllocationSheetSync() {
		var $button = $('#ums-start-allocation-sheet-sync');
		var $log = $('#ums-allocation-sheet-log');
		var activePopup = null;
		var posting = false;
		var statusPoll = null;
		var statusCheckInFlight = false;
		var syncId = '';

		if (!$button.length || !$log.length) {
			return;
		}

		function appendLog(message, type) {
			$('<div/>', {
				class: 'ums-sync-log-line' + (type ? ' ums-sync-log-line-' + type : ''),
				text: '[' + new Date().toLocaleTimeString() + '] ' + message
			}).appendTo($log);
			$log.scrollTop($log.prop('scrollHeight'));
		}

		function stopStatusPoll() {
			if (statusPoll) {
				window.clearInterval(statusPoll);
				statusPoll = null;
			}
		}

		function restoreButton() {
			stopStatusPoll();
			posting = false;
			$button.prop('disabled', false).text('Đọc dữ liệu từ Google Sheet');
		}

		function startStatusPoll() {
			var deadline = Date.now() + 10 * 60 * 1000;
			function checkStatus() {
				if (statusCheckInFlight) {
					return;
				}
				if (Date.now() > deadline) {
					appendLog('Hết thời gian chờ bản xem trước. Hãy kiểm tra popup rồi thử đọc lại.', 'error');
					restoreButton();
					return;
				}
				statusCheckInFlight = true;
				$.post(umsSheetsConnector.ajaxUrl, {
					action: 'ums_allocation_sync_status',
					security: umsSheetsConnector.allocationSyncNonce,
					client_sync_id: syncId
				}).done(function (response) {
					if (response.success && response.data && response.data.preview_url) {
						stopStatusPoll();
						window.location.href = response.data.preview_url;
					} else if (response.success && response.data && response.data.error) {
						appendLog(response.data.error, 'error');
						restoreButton();
					}
				}).always(function () {
					statusCheckInFlight = false;
				});
			}
			statusPoll = window.setInterval(checkStatus, 2000);
			checkStatus();
		}

		function postFromAdmin(payload, batchSize) {
			var rows = payload && Array.isArray(payload.rows) ? payload.rows : [];
			var size = parseInt(batchSize, 10) || 200;
			var endpoint = String($button.attr('data-rest-endpoint') || '').trim();
			var token = String($button.attr('data-sync-token') || '').trim();
			var previewUrl = '';

			if (!rows.length || !endpoint || !token) {
				appendLog('Không nhận được dữ liệu đăng ký hợp lệ từ Google Sheet.', 'error');
				restoreButton();
				return;
			}

			posting = true;
			function sendBatch(offset) {
				var batch = rows.slice(offset, offset + size);
				var body = $.extend({}, payload, {
					rows: batch,
					batch_offset: offset,
					batch_size: batch.length,
					finalize: offset + batch.length >= rows.length
				});
				appendLog('Đang gửi dòng ' + (offset + 1) + '-' + (offset + batch.length) + ' về UMS...', 'info');
				return window.fetch(endpoint, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json; charset=utf-8', 'X-Sync-Token': token },
					body: JSON.stringify(body)
				}).then(function (response) {
					return response.text().then(function (text) {
						var decoded;
						try { decoded = JSON.parse(text); } catch (error) {
							throw new Error('UMS trả về dữ liệu không hợp lệ. HTTP ' + response.status + ': ' + text);
						}
						if (response.status < 200 || response.status >= 300) {
							throw new Error(decoded.message || ('UMS từ chối dữ liệu. HTTP ' + response.status));
						}
						previewUrl = decoded.preview_url || previewUrl;
						return offset + batch.length < rows.length ? sendBatch(offset + batch.length) : decoded;
					});
				});
			}

			sendBatch(0).then(function () {
				appendLog('Đã đọc xong Google Sheet. Đang mở kết quả kiểm tra...', 'success');
				if (previewUrl) {
					stopStatusPoll();
					window.location.href = previewUrl;
					return;
				}
				restoreButton();
			}).catch(function (error) {
				appendLog(error.message || String(error), 'error');
				restoreButton();
			});
		}

		window.addEventListener('message', function (event) {
			var data = event.data || {};
			if (!data || data.source !== 'ums-sheet-sync' || (activePopup && event.source && event.source !== activePopup)) {
				return;
			}
			if (data.action === 'admin-post' && data.mode === 'allocation') {
				appendLog('Đang chuyển dữ liệu qua kết nối nội bộ UMS...', 'warning');
				postFromAdmin(data.payload || {}, data.batchSize || 200);
				return;
			}
			if (data.message) {
				appendLog(data.message, data.status || 'info');
			}
			if (data.done && data.payload && data.payload.preview_url) {
				stopStatusPoll();
				window.location.href = data.payload.preview_url;
			} else if (data.done && !posting) {
				restoreButton();
			}
		});

		$button.on('click', function () {
			var factoryCode = String($('#ums-allocation-factory').val() || '');
			var month = String($('#ums-allocation-month').val() || '');
			var year = String($('#ums-allocation-year').val() || '');
			var sources = $button.attr('data-sources') || '{}';
			try { sources = JSON.parse(sources); } catch (error) { sources = {}; }
			var source = sources[factoryCode] && sources[factoryCode][month] ? sources[factoryCode][month] : null;
			var appsScriptUrl = String($button.attr('data-apps-script-url') || '').trim();
			if (!source || !source.spreadsheet_id) {
				window.alert('Chưa cấu hình link Google Sheet cho nhà máy và kỳ đã chọn.');
				return;
			}
			if (!appsScriptUrl) {
				window.alert('Chưa cấu hình Google Apps Script Web App URL.');
				return;
			}

			$log.empty();
			appendLog('Đang mở Google Sheet đã cấu hình...', 'info');
			syncId = String($button.attr('data-sync-session') || '') + '_' + Date.now().toString(36);
			var query = $.param({
				ums_module: 'tvn_allocation',
				mode: 'allocation',
				spreadsheet_id: source.spreadsheet_id,
				sheet_name: source.sheet_name,
				factory_code: factoryCode,
				period_month: month,
				calculation_year: year,
				client_sync_id: syncId
			});
			activePopup = window.open(appsScriptUrl + (appsScriptUrl.indexOf('?') >= 0 ? '&' : '?') + query, 'umsAllocationSheetPopup', 'width=860,height=720,menubar=no,toolbar=no,location=yes,status=yes,scrollbars=yes,resizable=yes');
			if (!activePopup) {
				appendLog('Trình duyệt đã chặn popup. Hãy cho phép popup cho trang UMS.', 'error');
				return;
			}
			$button.prop('disabled', true).text('Đang đọc Google Sheet...');
			startStatusPoll();
			var popupCheck = window.setInterval(function () {
				if (activePopup && activePopup.closed) {
					window.clearInterval(popupCheck);
					activePopup = null;
					if (!posting && !statusPoll) {
						restoreButton();
					}
				}
			}, 1000);
		});
	}


    $(function () {
        initializeSheetSyncBridge();
        initializeAllocationSheetSync();
    });
})(jQuery);
