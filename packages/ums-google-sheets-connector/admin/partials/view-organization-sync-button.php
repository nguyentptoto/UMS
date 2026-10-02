<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
			<button
				type="button"
				class="button button-primary ums-start-popup-sync"
				id="ums-start-sheet-sync"
				data-apps-script-url="<?php echo esc_attr( $apps_script_url ); ?>"
				data-rest-endpoint="<?php echo esc_attr( $rest_endpoint ); ?>"
				data-sync-token="<?php echo esc_attr( $sync_token ); ?>"
				data-sync-mode="organization"
				data-auto-start="<?php echo $auto_start_sync ? '1' : '0'; ?>"
				<?php disabled( ! $table_ready || $apps_script_url === '' ); ?>
			>
				Đồng bộ từ Google Sheet
			</button>
