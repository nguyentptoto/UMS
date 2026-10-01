<?php if ( ! defined( 'ABSPATH' ) ) { exit; } ?>
<div class="wrap ums-admin-wrap ums-maternity">
	<h1>UMS - Đồng phục bầu</h1>
	<?php if ( $notice ) : ?>
		<div class="notice <?php echo $notice['error'] ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
	<?php endif; ?>
	<section class="ums-maternity-section">
		<h2>Ghi nhận thai kỳ</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-maternity-fields">
			<?php self::form_fields( 'create' ); ?>
			<label>CNV nữ <select name="employee_no" class="ums-search-select" required data-placeholder="Mã nhân viên, họ tên, bộ phận"><option value=""></option>
				<?php foreach ( $employees as $employee ) : ?>
					<option value="<?php echo esc_attr( $employee['employee_no'] ); ?>"><?php echo esc_html( $employee['employee_no'] . ' - ' . $employee['full_name'] . ' | ' . $employee['department'] ); ?></option>
				<?php endforeach; ?>
			</select></label>
			<label>Ngày HCNS ghi nhận <input type="date" name="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label>
			<button class="button button-primary" type="submit">Ghi nhận thai kỳ</button>
		</form>
	</section>
	<section class="ums-maternity-section">
		<h2>Danh sách thai kỳ</h2>
		<form method="get" class="ums-filter-bar">
			<input type="hidden" name="page" value="tvn-ums-maternity">
			<label>Nhà máy <select name="factory_code"><option value="">Tất cả nhà máy</option>
			<?php foreach ( $factories as $code => $name ) : ?><option value="<?php echo esc_attr( $code ); ?>" <?php selected( $factory, $code ); ?>><?php echo esc_html( $name ); ?></option><?php endforeach; ?>
			</select></label>
			<input name="search" aria-label="Tìm CNV" placeholder="Mã CNV, họ tên" value="<?php echo esc_attr( $search ); ?>">
			<button class="button" type="submit">Lọc</button>
		</form>
		<div class="ums-table-scroll"><table id="ums-maternity-table" class="widefat striped">
			<thead><tr><th>Mã CNV</th><th>Họ tên</th><th>Nhà máy</th><th>Ghi nhận</th><th>Nhận lần đầu</th><th>Kỳ khóa</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
			<tbody><?php foreach ( $rows as $row ) : ?><tr>
				<td><?php echo esc_html( $row['employee_no'] ); ?></td><td><?php echo esc_html( $row['full_name'] ); ?></td>
				<td><?php echo esc_html( $factories[ $row['factory_code'] ] ?? $row['factory_code'] ); ?></td>
				<td><?php echo esc_html( $row['notified_on'] ); ?></td><td><?php echo esc_html( $row['received_on'] ?: '-' ); ?></td>
				<td><?php echo esc_html( $row['blocked_month'] ? 'T' . $row['blocked_month'] . '/' . $row['blocked_year'] : '-' ); ?></td>
				<td><?php echo esc_html( $row['returned_on'] ? 'Đã trở lại làm việc' : ( UMS_Maternity::pending_items( $row ) ? 'Đã duyệt, chờ nhận' : ( $row['received_on'] ? 'Đã nhận đồ' : 'Chờ duyệt' ) ) ); ?></td>
				<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'tvn-ums-maternity', 'episode_id' => $row['episode_id'] ), admin_url( 'admin.php' ) ) . '#ums-maternity-detail' ); ?>">Chi tiết</a></td>
			</tr><?php endforeach; ?></tbody>
		</table></div>
	</section>
	<?php if ( $episode ) :
		$approved = array();
		foreach ( UMS_Maternity::pending_items( $episode ) as $line ) { $approved[ $line['group'] ] = $line; }
		$balance = UMS_Maternity::balance( $episode );
		$editable = ! $episode['returned_on'] && array_sum( $balance['remaining'] ) > 0;
	?>
	<section class="ums-maternity-section" id="ums-maternity-detail">
		<h2><?php echo esc_html( $episode['employee_no'] . ' - ' . $episode['full_name'] ); ?></h2>
		<p>Kho: <strong><?php echo esc_html( $factories[ $episode['factory_code'] ] ?? $episode['factory_code'] ); ?></strong> | Thai kỳ #<?php echo esc_html( $episode['episode_id'] ); ?></p>
		<?php if ( $episode['received_on'] ) : ?><p><strong>Nhận lần đầu: <?php echo esc_html( $episode['received_on'] ); ?>. Khóa toàn bộ kỳ T<?php echo esc_html( $episode['blocked_month'] . '/' . $episode['blocked_year'] ); ?>.</strong> Số lần nhận: <?php echo esc_html( $balance['receipt_count'] ); ?>.</p><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php self::form_fields( 'approve', $episode['episode_id'] ); ?>
			<div class="ums-table-scroll"><table class="widefat striped ums-maternity-items">
				<thead><tr><th>Loại</th><th>Định mức thai kỳ</th><th>Đã nhận</th><th>Còn miễn phí</th><th>Sản phẩm / Size / Tồn kho</th><th>Duyệt lần này</th></tr></thead>
				<tbody><?php foreach ( UMS_Maternity::limits() as $group => $limit ) : $line = $approved[ $group ] ?? array(); ?>
				<tr><td><?php echo esc_html( UMS_Maternity::labels()[ $group ] ); ?></td><td><?php echo esc_html( $limit ); ?></td>
				<td><?php echo esc_html( $balance['issued'][ $group ] ); ?></td><td><?php echo esc_html( $balance['remaining'][ $group ] ); ?></td>
				<td><?php if ( $editable && $balance['remaining'][ $group ] > 0 ) : ?>
					<select name="items[<?php echo esc_attr( $group ); ?>][item_id]" class="ums-search-select"><option value="">Chọn sản phẩm / size</option>
					<?php foreach ( $products[ $group ] as $product ) : ?><option value="<?php echo esc_attr( $product['item_id'] ); ?>" <?php selected( $line['item_id'] ?? 0, $product['item_id'] ); ?>><?php echo esc_html( $product['item_variant'] . ' / ' . $product['size'] . ' / Tồn: ' . $product['stock_qty'] ); ?></option><?php endforeach; ?>
					</select>
				<?php else : echo esc_html( isset( $line['product'] ) ? $line['product'] . ' / ' . $line['size'] : '-' ); endif; ?></td>
				<td><input type="number" name="items[<?php echo esc_attr( $group ); ?>][quantity]" aria-label="<?php echo esc_attr( 'Số lượng ' . UMS_Maternity::labels()[ $group ] ); ?>" min="0" max="<?php echo esc_attr( $balance['remaining'][ $group ] ); ?>" step="1" value="<?php echo esc_attr( $line['quantity'] ?? 0 ); ?>" <?php disabled( ! $editable || $balance['remaining'][ $group ] === 0 ); ?> required></td>
				</tr><?php endforeach; ?></tbody>
			</table></div>
			<?php if ( $editable ) : ?><p><button class="button button-primary" type="submit">Duyệt số lượng</button></p><?php endif; ?>
		</form>
		<?php if ( $editable && $approved ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-maternity-fields" data-confirm="Xác nhận CNV đã nhận đủ số lượng duyệt lần này và ghi xuất kho? Kỳ khóa được tính từ lần nhận đầu tiên.">
			<?php self::form_fields( 'receive', $episode['episode_id'] ); ?>
			<input type="hidden" name="approval_hash" value="<?php echo esc_attr( hash( 'sha256', (string) $episode['approved_items'] ) ); ?>">
			<label>Ngày thực nhận <input name="date" type="date" min="<?php echo esc_attr( $balance['last_received_on'] ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label>
			<button class="button button-primary" type="submit">Xác nhận đã nhận và xuất kho</button>
		</form>
		<?php endif; ?>
		<?php if ( ! $episode['returned_on'] ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ums-maternity-fields" data-confirm="Kết thúc thai sản và ghi nhận CNV đã trở lại làm việc?">
			<?php self::form_fields( 'finish', $episode['episode_id'] ); ?>
			<label>Ngày trở lại làm việc <input name="date" type="date" min="<?php echo esc_attr( $balance['last_received_on'] ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required></label>
			<button class="button" type="submit">Kết thúc thai sản</button>
		</form>
		<?php else : ?><p>Trở lại làm việc: <?php echo esc_html( $episode['returned_on'] ); ?></p><?php endif; ?>
		<h3>Lịch sử xử lý</h3>
		<div class="ums-table-scroll"><table class="widefat striped"><thead><tr><th>Thời điểm</th><th>Thao tác</th><th>Người xử lý</th><th>Nội dung</th></tr></thead><tbody>
		<?php foreach ( $events as $event ) :
			$actor = get_userdata( $event['actor_id'] );
			$actions = array( 'created' => 'Ghi nhận thai kỳ', 'approved' => 'Duyệt số lượng', 'received' => 'Xác nhận nhận đồ', 'returned' => 'Trở lại làm việc' );
			$payload = json_decode( $event['payload'], true );
			$notes = array();
			foreach ( $event['action'] === 'approved' ? (array) $payload : (array) ( $payload['items'] ?? array() ) as $line ) { $notes[] = ( $line['product'] ?? '' ) . ' / ' . ( $line['size'] ?? '' ) . ': ' . ( $line['quantity'] ?? 0 ); }
			foreach ( array( 'notified_on', 'received_on', 'returned_on' ) as $field ) { if ( isset( $payload[ $field ] ) ) { $notes[] = $payload[ $field ]; } }
		?>
			<tr><td><?php echo esc_html( $event['created_at'] ); ?></td><td><?php echo esc_html( $actions[ $event['action'] ] ?? $event['action'] ); ?></td><td><?php echo esc_html( $actor ? $actor->display_name : '#' . $event['actor_id'] ); ?></td><td><?php echo esc_html( implode( '; ', $notes ) ); ?></td></tr>
		<?php endforeach; ?></tbody></table></div>
	</section>
	<?php endif; ?>
</div>
