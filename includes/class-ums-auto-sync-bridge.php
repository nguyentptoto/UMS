<?php
/**
 * Public internal bridge for scheduled Google Sheet sync.
 *
 * Trang này không yêu cầu đăng nhập WordPress Admin. Bảo vệ bằng token riêng,
 * chỉ dùng trong mạng nội bộ để Task Scheduler mở mỗi ngày.
 */
class UMS_Auto_Sync_Bridge {

	const TOKEN_OPTION = 'ums_auto_sync_bridge_token';
	const QUERY_FLAG   = 'ums_auto_sync_bridge';
	const QUERY_TOKEN  = 'token';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_bridge' ), 0 );
	}

	public static function get_token() {
		$token = (string) get_option( self::TOKEN_OPTION, '' );
		if ( strlen( $token ) >= 32 ) {
			return $token;
		}

		$token = wp_generate_password( 48, false, false );
		update_option( self::TOKEN_OPTION, $token, false );

		return $token;
	}

	public static function get_bridge_url() {
		return add_query_arg(
			array(
				self::QUERY_FLAG  => '1',
				self::QUERY_TOKEN => self::get_token(),
			),
			home_url( '/' )
		);
	}

	public static function maybe_render_bridge() {
		if ( empty( $_GET[ self::QUERY_FLAG ] ) || '1' !== sanitize_text_field( wp_unslash( $_GET[ self::QUERY_FLAG ] ) ) ) {
			return;
		}

		$provided = isset( $_GET[ self::QUERY_TOKEN ] ) ? trim( sanitize_text_field( wp_unslash( $_GET[ self::QUERY_TOKEN ] ) ) ) : '';
		$expected = self::get_token();

		if ( $provided === '' || ! hash_equals( $expected, $provided ) ) {
			status_header( 403 );
			nocache_headers();
			echo 'UMS auto sync bridge token is invalid.';
			exit;
		}

		$apps_script_url = (string) get_option( 'ums_sheet_sync_apps_script_url', '' );
		$rest_endpoint   = rest_url( UMS_Organization_Sync::REST_NAMESPACE . UMS_Organization_Sync::REST_ROUTE );
		$sync_token      = UMS_Sheet_User_Sync::get_sync_token();

		nocache_headers();
		status_header( 200 );
		self::render_html( $apps_script_url, $rest_endpoint, $sync_token );
		exit;
	}

	private static function render_html( $apps_script_url, $rest_endpoint, $sync_token ) {
		$config = array(
			'appsScriptUrl' => esc_url_raw( $apps_script_url ),
			'restEndpoint'  => esc_url_raw( $rest_endpoint ),
			'syncToken'     => (string) $sync_token,
			'syncMode'      => 'organization',
			'batchSize'     => 200,
		);
		?>
<!doctype html>
<html lang="vi">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>UMS Auto Sync Bridge</title>
	<style>
		body { background: #f3f6fb; color: #162033; font: 14px/1.5 Arial, sans-serif; margin: 0; padding: 32px; }
		.ums-bridge { background: #fff; border: 1px solid #d8e0ec; border-radius: 8px; box-shadow: 0 10px 28px rgba(15, 23, 42, .08); margin: 0 auto; max-width: 920px; padding: 24px; }
		h1 { font-size: 22px; margin: 0 0 8px; }
		.status { color: #475569; margin: 0 0 16px; }
		.log { background: #0f172a; border-radius: 6px; color: #dbeafe; font-family: Consolas, Monaco, monospace; min-height: 280px; overflow: auto; padding: 14px; white-space: pre-wrap; }
		.ok { color: #15803d; }
		.error { color: #b91c1c; }
	</style>
</head>
<body>
	<main class="ums-bridge">
		<h1>UMS Auto Sync Bridge</h1>
		<p class="status" id="status">Dang chuan bi dong bo so do to chuc TVN...</p>
		<div class="log" id="log" aria-live="polite"></div>
	</main>

	<script>
		const umsBridgeConfig = <?php echo wp_json_encode( $config ); ?>;
		let activePopup = null;
		let bridgePosting = false;

		function appendLog(message, type) {
			const line = document.createElement('div');
			line.textContent = '[' + new Date().toLocaleTimeString() + '] ' + message;
			if (type) {
				line.className = type;
			}
			document.getElementById('log').appendChild(line);
		}

		function setStatus(message, type) {
			const status = document.getElementById('status');
			status.textContent = message;
			status.className = 'status' + (type ? ' ' + type : '');
		}

		function postPayloadFromBridge(payload, batchSize, mode) {
			const endpoint = String(umsBridgeConfig.restEndpoint || '').trim();
			const token = String(umsBridgeConfig.syncToken || '').trim();
			const isOrganization = mode === 'organization';
			const rows = payload && Array.isArray(payload.rows) ? payload.rows : [];
			const users = payload && Array.isArray(payload.users) ? payload.users : [];
			const items = isOrganization ? (rows.length ? rows : users) : users;
			const size = parseInt(batchSize, 10) || umsBridgeConfig.batchSize || 200;
			const syncToken = 'bridge' + String(Date.now()) + String(Math.floor(Math.random() * 100000));
			const total = { count: 0, created: 0, updated: 0, failed: 0, deleted: 0, errors: [] };

			if (!endpoint || !token || !items.length) {
				throw new Error('Khong du du lieu de bridge POST ve WordPress.');
			}

			bridgePosting = true;

			function sendBatch(offset) {
				const batch = items.slice(offset, offset + size);
				const body = Object.assign({}, payload, {
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

				appendLog('Bridge gui batch ' + (offset + 1) + '-' + (offset + batch.length) + '...', 'info');

				return fetch(endpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json; charset=utf-8',
						'X-Sync-Token': token
					},
					body: JSON.stringify(body)
				}).then(function (response) {
					return response.text().then(function (text) {
						let decoded;
						try {
							decoded = JSON.parse(text);
						} catch (error) {
							throw new Error('WordPress tra ve du lieu khong phai JSON. HTTP ' + response.status + ': ' + text);
						}

						if (response.status < 200 || response.status >= 300) {
							throw new Error('WordPress tu choi batch. HTTP ' + response.status + ': ' + text);
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

			return sendBatch(0).finally(function () {
				bridgePosting = false;
			});
		}

		window.addEventListener('message', function (event) {
			const data = event.data || {};
			if (!data || data.source !== 'ums-sheet-sync') {
				return;
			}

			if (activePopup && event.source && event.source !== activePopup) {
				return;
			}

			if (data.message) {
				appendLog(data.message, data.status || 'info');
			}

			if (data.action !== 'admin-post') {
				return;
			}

			appendLog('Nhan payload tu popup, bat dau ghi ve WordPress...', 'info');
			postPayloadFromBridge(data.payload || {}, data.batchSize || 200, data.mode || 'organization')
				.then(function (summary) {
					setStatus('Dong bo hoan tat.', summary.failed > 0 ? 'error' : 'ok');
					appendLog(JSON.stringify(summary), summary.failed > 0 ? 'error' : 'ok');
				})
				.catch(function (error) {
					setStatus('Dong bo that bai.', 'error');
					appendLog(error.message || String(error), 'error');
				});
		});

		function startBridge() {
			if (!umsBridgeConfig.appsScriptUrl) {
				setStatus('Chua cau hinh Google Apps Script Web App URL.', 'error');
				appendLog('Hay cau hinh Apps Script URL trong Admin > Dong bo Sheet.', 'error');
				return;
			}

			const separator = umsBridgeConfig.appsScriptUrl.indexOf('?') >= 0 ? '&' : '?';
			const popupUrl = umsBridgeConfig.appsScriptUrl + separator + 'mode=' + encodeURIComponent(umsBridgeConfig.syncMode) + '&ums_module=tvn_org&auto=1';
			appendLog('Dang mo popup Google Apps Script...', 'info');
			activePopup = window.open(popupUrl, 'umsSheetSyncPopup', 'width=860,height=720,menubar=no,toolbar=no,location=yes,status=yes,scrollbars=yes,resizable=yes');

			if (!activePopup) {
				setStatus('Popup bi chan.', 'error');
				appendLog('Hay cho phep popup cho site UMS hoac chay Chrome voi --disable-popup-blocking.', 'error');
				return;
			}

			const popupCheck = window.setInterval(function () {
				if (activePopup && activePopup.closed) {
					window.clearInterval(popupCheck);
					if (!bridgePosting) {
						appendLog('Popup da dong.', 'info');
					}
					activePopup = null;
				}
			}, 1000);
		}

		window.setTimeout(startBridge, 1000);
	</script>
</body>
</html>
		<?php
	}
}
