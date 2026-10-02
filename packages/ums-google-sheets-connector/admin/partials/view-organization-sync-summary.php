<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
				<?php if ( ! empty( $cron_result['ended_at'] ) ) : ?>
					<p class="description">
						Lần đồng bộ Sheet gần nhất:
						<?php echo esc_html( mysql2date( 'd/m/Y H:i:s', $cron_result['ended_at'] ) ); ?>
						· Đã nhận <?php echo esc_html( number_format_i18n( $cron_result['total'] ?? 0 ) ); ?> nhân sự từ Google Sheet
					</p>
				<?php endif; ?>
