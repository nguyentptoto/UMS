<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
		<?php if ( $apps_script_url === '' ) : ?>
			<div class="notice notice-warning inline">
				<p>Chưa cấu hình Google Apps Script Web App URL. Hãy cấu hình tại menu <strong>Đồng bộ Sheet</strong> trước.</p>
			</div>
		<?php endif; ?>

		<div class="ums-sync-log" id="ums-sheet-sync-log" aria-live="polite">
			<div class="ums-sync-log-line">Sẵn sàng đồng bộ sơ đồ tổ chức từ Sheet Danh sách CNV.</div>
		</div>
