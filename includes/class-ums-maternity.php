<?php
/** Maternity episodes, approved free issues and immutable receipt history. */
class UMS_Maternity extends UMS_DB_Base {
	private static $blocks = array();
	private static $issuing = false;

	public static function table() { return self::prefix() . 'uniform_maternity_episodes'; }
	public static function event_table() { return self::prefix() . 'uniform_maternity_events'; }
	public static function limits() { return array( 'dress' => 3, 'pants' => 3, 'shirt' => 3, 'jacket' => 1 ); }
	public static function labels() { return array( 'dress' => 'Váy bầu', 'pants' => 'Quần bầu', 'shirt' => 'Áo bầu', 'jacket' => 'Áo khoác bầu' ); }

	public static function ensure_schema() {
		self::ensure_request_schema();
		if ( get_option( 'ums_maternity_schema' ) === '1' ) { return; }
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$db = self::db();
		$charset = $db->get_charset_collate();
		$table = self::table();
		$events = self::event_table();
		dbDelta( "CREATE TABLE $table (
			episode_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			employee_no varchar(50) NOT NULL,
			active_employee_no varchar(50) DEFAULT NULL,
			full_name varchar(255) NOT NULL,
			factory_code varchar(10) NOT NULL,
			notified_on date NOT NULL,
			returned_on date DEFAULT NULL,
			approved_items longtext DEFAULT NULL,
			approved_by bigint(20) unsigned DEFAULT NULL,
			received_on date DEFAULT NULL,
			blocked_year int DEFAULT NULL,
			blocked_month int DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (episode_id),
			UNIQUE KEY active_employee (active_employee_no),
			KEY employee_history (employee_no, notified_on),
			KEY blocked_period (blocked_year, blocked_month)
		) ENGINE=InnoDB $charset;" );
		dbDelta( "CREATE TABLE $events (
			event_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			episode_id bigint(20) unsigned NOT NULL,
			action varchar(30) NOT NULL,
			actor_id bigint(20) unsigned NOT NULL,
			payload longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (event_id),
			KEY episode (episode_id)
		) ENGINE=InnoDB $charset;" );
		foreach ( array( $table, $events ) as $name ) {
			if ( $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $name ) ) !== $name ) { return; }
		}
		update_option( 'ums_maternity_schema', '1', false );
	}

	private static function ensure_request_schema() {
		if ( get_option( 'ums_maternity_request_schema' ) === '1' ) { return; }
		$db = self::db();
		$table = UMS_DB_Request::table();
		if ( $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return; }
		if ( $db->get_var( "SHOW COLUMNS FROM $table LIKE 'maternity_episode_id'" ) !== 'maternity_episode_id' ) {
			$db->query( "ALTER TABLE $table ADD COLUMN maternity_episode_id BIGINT(20) UNSIGNED NULL DEFAULT NULL" );
		}
		if ( $db->get_var( "SHOW COLUMNS FROM $table LIKE 'maternity_episode_id'" ) === 'maternity_episode_id' ) {
			update_option( 'ums_maternity_request_schema', '1', false );
		}
	}

	public static function date( $value ) {
		$value = (string) $value;
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value || (int) $date->format( 'Y' ) < 1900 ) {
			throw new InvalidArgumentException( 'Ngày không hợp lệ.' );
		}
		return $value;
	}

	public static function blocked_period( $received_on ) {
		$date = self::date( $received_on );
		$year = (int) substr( $date, 0, 4 );
		if ( $date <= "$year-05-01" ) { return array( $year, 4 ); }
		if ( $date <= "$year-08-30" ) { return array( $year, 9 ); }
		return array( $year + 1, 4 );
	}

	public static function product_group( $item ) {
		// Product variants, not parent categories, identify maternity clothing.
		$name = strtolower( remove_accents( (string) ( $item['item_variant'] ?? '' ) ) );
		$name = preg_replace( '/\s+/u', ' ', trim( $name ) );
		if ( ! preg_match( '/\bbau\b/', $name ) ) { return ''; }
		foreach ( array( 'jacket' => 'ao khoac', 'dress' => 'vay', 'pants' => 'quan', 'shirt' => 'ao' ) as $group => $word ) {
			if ( preg_match( '/\b' . $word . '\b/', $name ) ) { return $group; }
		}
		return '';
	}

	public static function block_map( $year, $month ) {
		$key = (int) $year . ':' . (int) $month;
		if ( ! isset( self::$blocks[ $key ] ) ) {
			$rows = self::db()->get_col( self::db()->prepare(
				'SELECT employee_no FROM ' . self::table() . ' WHERE received_on IS NOT NULL AND blocked_year = %d AND blocked_month = %d', $year, $month
			) );
			if ( self::db()->last_error ) { throw new RuntimeException( 'Không đọc được lịch sử khóa kỳ thai sản.' ); }
			self::$blocks[ $key ] = array_fill_keys( array_map( 'strtoupper', $rows ), true );
		}
		return self::$blocks[ $key ];
	}

	public static function is_blocked( $employee_no, $year, $month ) {
		return isset( self::block_map( $year, $month )[ strtoupper( trim( $employee_no ) ) ] );
	}

	public static function managed_flag( $employee_no, $fallback = 0 ) {
		$rows = self::db()->get_results( self::db()->prepare(
			'SELECT active_employee_no FROM ' . self::table() . ' WHERE employee_no = %s', $employee_no
		), ARRAY_A );
		if ( ! $rows ) { return $fallback; }
		foreach ( $rows as $row ) { if ( $row['active_employee_no'] !== null ) { return 1; } }
		return 0;
	}

	public static function get( $id, $lock = false ) {
		return self::db()->get_row( self::db()->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE episode_id = %d' . ( $lock ? ' FOR UPDATE' : '' ), $id
		), ARRAY_A );
	}

	private static function checked( $result ) {
		if ( $result === false ) { throw new RuntimeException( 'Không lưu được dữ liệu thai sản; chưa ghi nhận thay đổi.' ); }
		return $result;
	}

	private static function event( $id, $action, $actor, $payload ) {
		self::checked( self::db()->insert( self::event_table(), array(
			'episode_id' => $id, 'action' => $action, 'actor_id' => $actor,
			'payload' => wp_json_encode( $payload ), 'created_at' => current_time( 'mysql' ),
		) ) );
	}

	private static function flag( $code, $value ) {
		self::checked( self::db()->update( UMS_DB_User::table(), array( 'is_maternity' => $value ), array( 'employee_code' => $code ) ) );
	}

	public static function create( $code, $date, $actor ) {
		$code = strtoupper( trim( sanitize_text_field( $code ) ) );
		$date = self::date( $date );
		$employee = UMS_DB_Organization::get_by_employee_no( $code );
		$profile = UMS_DB_User::get_by_employee_code( $code );
		$is_female = $profile ? $profile['gender'] === 'Nữ' : strpos( $code, 'F' ) === 0;
		if ( ! $employee || ! $is_female ) { throw new RuntimeException( 'Cần chọn CNV nữ đang làm việc trong Sơ đồ tổ chức.' ); }
		if ( $date > current_time( 'Y-m-d' ) || $date < $employee['date_joined'] ) { throw new RuntimeException( 'Ngày ghi nhận phải từ ngày vào làm đến hôm nay.' ); }
		$db = self::db();
		self::checked( $db->query( 'START TRANSACTION' ) );
		try {
			$previous = $db->get_row( $db->prepare( 'SELECT * FROM ' . self::table() . ' WHERE employee_no = %s ORDER BY episode_id DESC LIMIT 1 FOR UPDATE', $code ), ARRAY_A );
			if ( $previous && ( ! $previous['returned_on'] || $date <= $previous['returned_on'] ) ) {
				throw new RuntimeException( 'CNV còn thai kỳ đang mở hoặc ngày ghi nhận trùng lịch sử thai kỳ trước.' );
			}
			$id = self::insert_episode( $employee, $date, $actor );
			self::checked( $db->query( 'COMMIT' ) );
		} catch ( Throwable $e ) { $db->query( 'ROLLBACK' ); throw $e; }
		self::sync_meta( $code, 1 );
		return $id;
	}

	private static function insert_episode( $employee, $date, $actor, $request_id = 0 ) {
		$code = strtoupper( trim( $employee['employee_no'] ) );
		self::checked( self::db()->insert( self::table(), array(
			'employee_no' => $code, 'active_employee_no' => $code, 'full_name' => $employee['full_name'],
			'factory_code' => UMS_DB_Inventory::resolve_factory_code_for_employee( $employee ),
			'notified_on' => $date, 'created_at' => current_time( 'mysql' ),
		) ) );
		$id = (int) self::db()->insert_id;
		self::flag( $code, 1 );
		self::event( $id, 'created', $actor, array( 'notified_on' => $date, 'request_id' => $request_id ) );
		return $id;
	}

	public static function after_request_commit( $code ) {
		self::$blocks = array();
		self::sync_meta( $code, self::managed_flag( $code ) );
	}

	private static function sync_meta( $code, $value ) {
		$profile = UMS_DB_User::get_by_employee_code( $code );
		if ( ! empty( $profile['user_id'] ) ) { update_user_meta( $profile['user_id'], 'ums_is_maternity', $value ); }
	}

	public static function validate_quantities( $lines ) {
		$totals = array_fill_keys( array_keys( self::limits() ), 0 );
		foreach ( $lines as $line ) {
			$group = $line['group'] ?? '';
			$qty = filter_var( $line['quantity'] ?? null, FILTER_VALIDATE_INT );
			if ( ! isset( $totals[ $group ] ) || $qty === false || $qty < 1 ) { throw new RuntimeException( 'Loại đồ bầu hoặc số lượng không hợp lệ.' ); }
			$totals[ $group ] += $qty;
			if ( $totals[ $group ] > self::limits()[ $group ] ) { throw new RuntimeException( self::labels()[ $group ] . ' vượt định mức miễn phí.' ); }
		}
		if ( array_sum( $totals ) === 0 ) { throw new RuntimeException( 'Cần có ít nhất một sản phẩm được duyệt.' ); }
	}

	public static function balance( $episode, $lock = false ) {
		$issued = array_fill_keys( array_keys( self::limits() ), 0 );
		$last_date = (string) $episode['notified_on'];
		$receipt_count = 0;
		$events = self::db()->get_results( self::db()->prepare(
			'SELECT action, payload FROM ' . self::event_table() . " WHERE episode_id = %d AND action IN ('received', 'purchased') ORDER BY event_id ASC" . ( $lock ? ' FOR UPDATE' : '' ), $episode['episode_id']
		), ARRAY_A );
		if ( self::db()->last_error || ( $episode['received_on'] && ! $events ) ) {
			throw new RuntimeException( 'Chưa đọc được đầy đủ lịch sử nhận đồ bầu; không thể xác định số lượng còn lại.' );
		}
		foreach ( $events as $event ) {
			$payload = json_decode( (string) $event['payload'], true );
			if ( $event['action'] === 'purchased' ) {
				$last_date = max( $last_date, self::date( $payload['purchased_on'] ?? '' ) );
				continue;
			}
			if ( ! is_array( $payload ) || ! isset( $payload['items'], $payload['received_on'] ) || ! is_array( $payload['items'] ) ) {
				throw new RuntimeException( 'Lịch sử nhận đồ bầu không hợp lệ; cần HCNS kiểm tra.' );
			}
			self::validate_quantities( $payload['items'] );
			$receipt_count++;
			$last_date = max( $last_date, self::date( $payload['received_on'] ) );
			foreach ( $payload['items'] as $line ) { $issued[ $line['group'] ] += (int) $line['quantity']; }
		}
		if ( $episode['received_on'] && ! $receipt_count ) { throw new RuntimeException( 'Thiếu lịch sử nhận đồ bầu miễn phí.' ); }
		$remaining = array();
		foreach ( self::limits() as $group => $limit ) { $remaining[ $group ] = max( 0, $limit - $issued[ $group ] ); }
		return array( 'issued' => $issued, 'remaining' => $remaining, 'last_received_on' => $last_date, 'receipt_count' => $receipt_count );
	}

	public static function pending_items( $episode, $lock = false ) {
		if ( empty( $episode['approved_items'] ) || empty( $episode['approved_by'] ) ) { return array(); }
		// Old episodes retained approved_items after receipt. Audit order distinguishes
		// those consumed approvals from a new, pending supplementary issue.
		$events = self::db()->get_results( self::db()->prepare(
			'SELECT action, payload FROM ' . self::event_table() . " WHERE episode_id = %d AND action IN ('approved', 'received') ORDER BY event_id DESC" . ( $lock ? ' FOR UPDATE' : '' ), $episode['episode_id']
		), ARRAY_A );
		if ( self::db()->last_error ) { throw new RuntimeException( 'Không đọc được trạng thái duyệt đồ bầu.' ); }
		foreach ( $events as $event ) {
			$payload = json_decode( $event['payload'], true );
			// A portal issue does not consume a separate HCNS approval awaiting pickup.
			if ( $event['action'] === 'received' && ! empty( $payload['request_id'] ) ) { continue; }
			return $event['action'] === 'approved' ? (array) json_decode( $episode['approved_items'], true ) : array();
		}
		return array();
	}

	private static function validate_remaining( $episode, $lines, $lock = false ) {
		self::validate_quantities( $lines );
		$balance = self::balance( $episode, $lock );
		$totals = array_fill_keys( array_keys( self::limits() ), 0 );
		foreach ( $lines as $line ) {
			$group = $line['group'];
			$totals[ $group ] += (int) $line['quantity'];
			if ( $totals[ $group ] > $balance['remaining'][ $group ] ) {
				throw new RuntimeException( sprintf( '%s chỉ còn được cấp miễn phí %d. Phần vượt định mức phải mua thêm.', self::labels()[ $group ], $balance['remaining'][ $group ] ) );
			}
		}
		return $balance;
	}

	public static function approve( $id, $raw, $actor ) {
		$lines = array();
		$approval_token = bin2hex( random_bytes( 16 ) );
		foreach ( (array) $raw as $group => $row ) {
			if ( ! is_array( $row ) || ! array_key_exists( $group, self::limits() ) ) { throw new RuntimeException( 'Dữ liệu duyệt đồ bầu không hợp lệ.' ); }
			$quantity = filter_var( $row['quantity'] ?? null, FILTER_VALIDATE_INT );
			if ( $quantity === 0 ) { continue; }
			$item = UMS_DB_Inventory::get_by_id( absint( $row['item_id'] ?? 0 ) );
			if ( ! $item || self::product_group( $item ) !== $group ) { throw new RuntimeException( 'Sản phẩm/size đồ bầu không đúng loại.' ); }
			$lines[] = array( 'group' => $group, 'item_id' => (int) $item['item_id'], 'quantity' => $quantity, 'product' => $item['item_variant'], 'size' => $item['size'], 'approval_token' => $approval_token );
		}
		self::validate_quantities( $lines );
		self::change( $id, 'approved', $actor, function ( $episode ) use ( $lines, $actor ) {
			self::validate_remaining( $episode, $lines, true );
			self::checked( self::db()->update( self::table(), array( 'approved_items' => wp_json_encode( $lines ), 'approved_by' => $actor ), array( 'episode_id' => $episode['episode_id'] ) ) );
			return $lines;
		} );
	}

	private static function change( $id, $action, $actor, $callback ) {
		$db = self::db();
		self::checked( $db->query( 'START TRANSACTION' ) );
		try {
			$episode = self::get( $id, true );
			if ( ! $episode || ! $episode['active_employee_no'] ) { throw new RuntimeException( 'Thai kỳ không tồn tại hoặc đã kết thúc.' ); }
			$payload = $callback( $episode );
			self::event( $id, $action, $actor, $payload );
			self::checked( $db->query( 'COMMIT' ) );
			self::$blocks = array();
		} catch ( Throwable $e ) { $db->query( 'ROLLBACK' ); throw $e; }
	}

	public static function receive( $id, $date, $actor, $approval_hash ) {
		$date = self::date( $date );
		self::change( $id, 'received', $actor, function ( $episode ) use ( $date, $actor, $approval_hash ) {
			$lines = self::pending_items( $episode, true );
			if ( ! $lines ) { throw new RuntimeException( 'Không có số lượng chờ nhận. Cần duyệt lần cấp bổ sung trước khi xác nhận.' ); }
			if ( ! hash_equals( hash( 'sha256', (string) $episode['approved_items'] ), (string) $approval_hash ) ) { throw new RuntimeException( 'Số lượng duyệt vừa thay đổi. Hãy tải lại hồ sơ và kiểm tra trước khi xác nhận nhận.' ); }
			if ( $date < $episode['notified_on'] || $date > current_time( 'Y-m-d' ) ) { throw new RuntimeException( 'Ngày nhận phải từ ngày ghi nhận thai sản đến hôm nay.' ); }
			$balance = self::validate_remaining( $episode, $lines, true );
			if ( $date < $balance['last_received_on'] ) { throw new RuntimeException( 'Ngày nhận bổ sung không được trước ngày nhận gần nhất.' ); }
			return self::issue_lines( $episode, $lines, $date, $actor );
		} );
	}

	/** Caller owns the transaction and holds the episode row lock. */
	private static function issue_lines( $episode, $lines, $date, $actor, $request_id = 0 ) {
		// Stable stock lock order; receipt and all movements commit together.
		usort( $lines, function ( $a, $b ) { return $a['item_id'] <=> $b['item_id']; } );
		self::$issuing = true;
		try {
			foreach ( $lines as &$line ) {
				$item = UMS_DB_Inventory::get_by_id_for_update( $line['item_id'], $episode['factory_code'] );
				if ( ! $item || self::product_group( $item ) !== $line['group'] || (int) $item['stock_qty'] < $line['quantity'] ) { throw new RuntimeException( 'Sản phẩm thay đổi hoặc kho nhà máy không đủ tồn: ' . $line['product'] ); }
				$after = (int) $item['stock_qty'] - $line['quantity'];
				self::checked( UMS_DB_Inventory::update( $line['item_id'], array( 'stock_qty' => $after ), $episode['factory_code'] ) );
				self::checked( UMS_DB_Inventory_Movement::insert( array(
					'item_id' => $line['item_id'], 'factory_code' => $episode['factory_code'], 'movement_type' => 'out',
					'request_id' => $request_id ?: null,
					'quantity' => $line['quantity'], 'before_qty' => $item['stock_qty'], 'after_qty' => $after,
					'unit_price' => $item['base_price'], 'total_price' => $item['base_price'] * $line['quantity'],
					'actor_user_id' => $actor, 'target_employee_no' => $episode['employee_no'],
					'note' => 'Cấp miễn phí thai kỳ #' . $episode['episode_id'] . '; thực nhận ' . $date,
				) ) );
				$line['movement_id'] = (int) self::db()->insert_id;
			}
			unset( $line );
		} finally { self::$issuing = false; }
		// Only the first free receipt establishes the blocked periodic cycle.
		list( $year, $month ) = $episode['received_on']
			? array( (int) $episode['blocked_year'], (int) $episode['blocked_month'] )
			: self::blocked_period( $date );
		$update = array(
			'received_on' => $episode['received_on'] ?: $date, 'blocked_year' => $year, 'blocked_month' => $month,
		);
		if ( ! $request_id ) { $update['approved_items'] = null; $update['approved_by'] = null; }
		self::checked( self::db()->update( self::table(), $update, array( 'episode_id' => $episode['episode_id'] ) ) );
		return array( 'received_on' => $date, 'blocked_year' => $year, 'blocked_month' => $month, 'items' => $lines, 'request_id' => $request_id, 'factory_code' => $episode['factory_code'] );
	}

	/** Read-only at submission; run again with an episode lock on final approval. */
	private static function request_context( $code, $details, $bound_id = null, $submitted_on = '', $lock = false, $free = true ) {
		$code = strtoupper( trim( (string) $code ) );
		$employee = UMS_DB_Organization::get_by_employee_no( $code );
		$profile = UMS_DB_User::get_by_employee_code( $code );
		if ( ! $employee || ! $profile || $profile['gender'] !== 'Nữ' ) { throw new RuntimeException( 'Phiếu đồ bầu chỉ áp dụng cho CNV nữ đang làm việc.' ); }
		$lines = array();
		foreach ( $details as $detail ) {
			$item = UMS_DB_Inventory::get_by_id( $detail['item_id'] );
			$group = $item ? self::product_group( $item ) : '';
			if ( $group === '' ) { throw new RuntimeException( 'Không xác định được loại đồ bầu trong phiếu.' ); }
			$quantity = filter_var( $detail['quantity'], FILTER_VALIDATE_INT );
			if ( $quantity === false || $quantity < 1 ) { throw new RuntimeException( 'Số lượng đồ bầu không hợp lệ.' ); }
			$lines[] = array( 'group' => $group, 'item_id' => (int) $item['item_id'], 'quantity' => $detail['quantity'], 'product' => $item['item_variant'], 'size' => $item['size'] );
		}
		if ( $free ) { self::validate_quantities( $lines ); }
		$episode = self::db()->get_row( self::db()->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE employee_no = %s ORDER BY episode_id DESC LIMIT 1' . ( $lock ? ' FOR UPDATE' : '' ), $code
		), ARRAY_A );
		if ( self::db()->last_error ) { throw new RuntimeException( 'Không đọc được hồ sơ thai kỳ.' ); }
		$closed_on = self::db()->get_var( self::db()->prepare( 'SELECT returned_on FROM ' . self::table() . ' WHERE employee_no = %s AND returned_on IS NOT NULL ORDER BY returned_on DESC LIMIT 1' . ( $lock ? ' FOR UPDATE' : '' ), $code ) );
		if ( self::db()->last_error ) { throw new RuntimeException( 'Không đọc được lịch sử kết thúc thai kỳ.' ); }
		$submitted_on = self::date( $submitted_on ?: current_time( 'Y-m-d' ) );
		if ( $closed_on && $submitted_on <= $closed_on ) { throw new RuntimeException( 'Thai kỳ của phiếu này đã kết thúc. Cần tạo phiếu mới cho thai kỳ mới.' ); }
		if ( $episode && $episode['returned_on'] ) { $episode = null; }
		if ( $bound_id && ( ! $episode || (int) $episode['episode_id'] !== (int) $bound_id ) ) { throw new RuntimeException( 'Phiếu không còn thuộc thai kỳ đang mở; không được tự chuyển sang thai kỳ khác.' ); }
		if ( $episode && $free ) {
			// Reserve independently approved HCNS quantities, so another request cannot take them.
			// Locking reads see the latest committed receipts even under REPEATABLE READ.
			self::validate_remaining( $episode, array_merge( $lines, self::pending_items( $episode, $lock ) ), $lock );
		}
		return array( 'employee' => $employee, 'episode' => $episode, 'lines' => $lines, 'episode_id' => $episode ? (int) $episode['episode_id'] : 0 );
	}

	/** Called only by complete_approved_request inside its existing transaction. */
	public static function fulfil_request( $request, $details, $actor ) {
		$employee = UMS_DB_Organization::get_by_wp_user_id( (int) $request['target_user_id'] );
		if ( ! $employee ) { throw new RuntimeException( 'Không tìm thấy CNV nhận đồ trong Sơ đồ tổ chức.' ); }
		$paid = self::is_paid_request( $request['reason_type'], $request['payment_method'] );
		if ( $paid ) {
			$error = self::validate_paid_request( $details, $request['reason_type'], $request['payment_method'] );
			if ( $error !== '' ) { throw new RuntimeException( $error ); }
		} elseif ( ! in_array( (int) $request['reason_type'], array( 1, 2 ), true ) ) {
			throw new RuntimeException( 'Đồ bầu không được tính tạm ứng kỳ định kỳ.' );
		}
		$context = self::request_context( $employee['employee_no'], $details, $request['maternity_episode_id'] ?? 0, substr( $request['created_at'], 0, 10 ), true, ! $paid );
		$date = current_time( 'Y-m-d' );
		$episode = $context['episode'];
		if ( ! $episode ) {
			$id = self::insert_episode( $context['employee'], $date, $actor, $request['request_id'] );
			$episode = self::get( $id, true );
		}
		if ( $date < self::balance( $episode, true )['last_received_on'] ) { throw new RuntimeException( 'Ngày cấp phát trước lần nhận gần nhất.' ); }
		$episode['factory_code'] = UMS_DB_Inventory::resolve_factory_code_for_employee( $context['employee'] );
		if ( $paid ) {
			// Paid stock movements remain in the request transaction, outside the free ledger.
			self::event( $episode['episode_id'], 'purchased', $actor, array( 'request_id' => (int) $request['request_id'], 'purchased_on' => $date, 'items' => $context['lines'], 'factory_code' => $episode['factory_code'] ) );
		} else {
			$payload = self::issue_lines( $episode, $context['lines'], $date, $actor, (int) $request['request_id'] );
			self::event( $episode['episode_id'], 'received', $actor, $payload );
		}
		self::flag( $employee['employee_no'], 1 );
		self::checked( self::db()->update( UMS_DB_Request::table(), array( 'maternity_episode_id' => $episode['episode_id'] ), array( 'request_id' => $request['request_id'] ) ) );
		return $employee['employee_no'];
	}

	public static function maternity_details( $details ) {
		return array_values( array_filter( $details, function ( $detail ) {
			$item = UMS_DB_Inventory::get_by_id( $detail['item_id'] );
			return $item && self::product_group( $item ) !== '';
		} ) );
	}

	public static function is_paid_request( $reason, $payment ) {
		return (int) $reason === 3 && in_array( (int) $payment, array( 1, 2 ), true );
	}

	/** Existing reason/payment controls determine purchase versus free maternity issue. */
	public static function validate_request( $code, $details, $reason, $payment ) {
		$maternity = self::maternity_details( $details );
		if ( ! $maternity ) { return null; }
		if ( self::is_paid_request( $reason, $payment ) ) {
			$error = self::validate_paid_request( $maternity, $reason, $payment );
			if ( $error !== '' ) { throw new RuntimeException( $error ); }
			return self::request_context( $code, $maternity, null, '', false, false );
		}
		if ( ! in_array( (int) $reason, array( 1, 2 ), true ) ) { throw new RuntimeException( 'Đồ bầu không áp dụng tạm ứng kỳ định kỳ. Cấp trong định mức chọn lý do 1 hoặc 2; mua thêm chọn thanh toán qua lương hoặc trực tiếp.' ); }
		return self::request_context( $code, $maternity );
	}

	public static function finish( $id, $date, $actor ) {
		$date = self::date( $date );
		$code = '';
		self::change( $id, 'returned', $actor, function ( $episode ) use ( $date, &$code ) {
			$balance = self::balance( $episode, true );
			if ( $date < $balance['last_received_on'] || $date > current_time( 'Y-m-d' ) ) { throw new RuntimeException( 'Ngày trở lại làm việc không hợp lệ.' ); }
			self::checked( self::db()->update( self::table(), array( 'returned_on' => $date, 'active_employee_no' => null ), array( 'episode_id' => $episode['episode_id'] ) ) );
			$code = $episode['employee_no'];
			self::flag( $code, 0 );
			return array( 'returned_on' => $date );
		} );
		self::sync_meta( $code, 0 );
	}

	public static function validate_paid_request( $details, $reason, $payment ) {
		foreach ( $details as $line ) {
			$item = UMS_DB_Inventory::get_by_id( $line['item_id'] );
			if ( ! $item || self::product_group( $item ) === '' ) { continue; }
			if ( (int) $reason !== 3 || ! in_array( (int) $payment, array( 1, 2 ), true ) ) {
				return 'Đồ bầu trong định mức áp dụng lý do 1 hoặc 2. Mua thêm chọn lý do 3 và thanh toán qua lương hoặc trực tiếp, không tạm ứng.';
			}
			if ( (float) $item['base_price'] <= 0 || abs( (float) $line['price_at_request'] - (float) $item['base_price'] * (int) $line['quantity'] ) > 0.01 ) {
				return 'Giá đồ bầu chưa được cập nhật hoặc đã thay đổi. Cần dùng đủ 100% đơn giá hiện hành do phòng Mua cung cấp.';
			}
		}
		return '';
	}

	public static function allow_movement( $data ) {
		if ( self::$issuing || ( $data['movement_type'] ?? '' ) !== 'out' || ( empty( $data['target_employee_no'] ) && empty( $data['target_user_id'] ) ) ) { return true; }
		$item = UMS_DB_Inventory::get_by_id( $data['item_id'] );
		if ( ! $item || self::product_group( $item ) === '' ) { return true; }
		$request = empty( $data['request_id'] ) ? null : UMS_DB_Request::get_by_id( $data['request_id'] );
		return $request && self::validate_paid_request( array( array( 'item_id' => $data['item_id'], 'quantity' => $data['quantity'], 'price_at_request' => $data['total_price'] ) ), $request['reason_type'], $request['payment_method'] ) === '';
	}
}
