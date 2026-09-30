<?php
/**
 * Luu ket qua tinh so luong cap phat da chot de lam dau vao cho PR.
 */
class UMS_DB_Allocation_Calculation extends UMS_DB_Base {
	const SCHEMA_VERSION = '2.0.0';

	public static function table() {
		return self::prefix() . 'uniform_allocation_calculation_batches';
	}

	public static function detail_table() {
		return self::prefix() . 'uniform_allocation_calculation_details';
	}

	public static function ensure_schema() {
		if ( get_option( 'ums_allocation_calculation_schema_version' ) === self::SCHEMA_VERSION || ! self::is_ready() ) {
			return;
		}

		$db = self::db();
		$table = self::table();
		$columns = array(
			'factory_code'     => "VARCHAR(10) NOT NULL DEFAULT 'ALL' AFTER period_month",
			'source_type'      => "VARCHAR(30) NOT NULL DEFAULT 'excel' AFTER file_hash",
			'source_ref'       => "VARCHAR(255) NOT NULL DEFAULT '' AFTER source_type",
			'source_sheet'     => "VARCHAR(255) NOT NULL DEFAULT '' AFTER source_ref",
			'source_synced_at' => 'DATETIME NULL AFTER source_sheet',
		);
		foreach ( $columns as $column => $definition ) {
			if ( ! self::has_column( $column ) ) {
				$db->query( "ALTER TABLE $table ADD COLUMN $column $definition" );
			}
		}
		$index_exists = $db->get_var(
			$db->prepare(
				"SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s AND INDEX_NAME = 'idx_period_factory_active' LIMIT 1",
				$table
			)
		);
		if ( ! $index_exists ) {
			$db->query( "ALTER TABLE $table ADD KEY idx_period_factory_active (calculation_year, period_month, factory_code, is_active)" );
		}

		if ( self::has_column( 'factory_code' ) && self::has_column( 'source_type' ) && self::has_column( 'source_ref' ) && self::has_column( 'source_sheet' ) && self::has_column( 'source_synced_at' ) ) {
			update_option( 'ums_allocation_calculation_schema_version', self::SCHEMA_VERSION, false );
		}
	}

	public static function supports_factory_sources() {
		return self::is_ready() && self::has_column( 'factory_code' ) && self::has_column( 'source_type' );
	}

	private static function has_column( $column ) {
		return self::db()->get_var(
			self::db()->prepare( 'SHOW COLUMNS FROM ' . self::table() . ' LIKE %s', $column )
		) === $column;
	}

	public static function is_ready() {
		foreach ( array( self::table(), self::detail_table() ) as $table ) {
			if ( self::db()->get_var( self::db()->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return false;
			}
		}
		return true;
	}

	public static function save_snapshot( $preview, $user_id ) {
		if ( ! self::is_ready() ) {
			return new WP_Error( 'allocation_schema_missing', 'Database chưa có bảng lưu kết quả tính số lượng cấp phát.' );
		}

		$db = self::db();
		$db->query( 'START TRANSACTION' );
		$inserted = $db->insert(
			self::table(),
			array(
				'calculation_year' => absint( $preview['year'] ),
				'period_month'     => absint( $preview['month'] ),
				'factory_code'     => self::normalize_batch_factory( $preview['factory_code'] ?? '' ),
				'file_name'        => sanitize_file_name( $preview['file_name'] ),
				'file_hash'        => sanitize_text_field( $preview['file_hash'] ),
				'source_type'      => sanitize_key( (string) ( $preview['source_type'] ?? 'excel' ) ),
				'source_ref'       => sanitize_text_field( (string) ( $preview['source_ref'] ?? '' ) ),
				'source_sheet'     => sanitize_text_field( (string) ( $preview['source_sheet'] ?? '' ) ),
				'source_synced_at' => self::normalize_datetime( $preview['source_synced_at'] ?? '' ),
				'employee_count'   => absint( $preview['employee_count'] ),
				'detail_count'     => count( $preview['details'] ),
				'requested_qty'    => absint( $preview['requested_quantity'] ?? 0 ),
				'allocated_qty'    => absint( $preview['total_quantity'] ),
				'warning_count'    => count( $preview['warnings'] ?? array() ),
				'warnings_log'     => wp_json_encode( $preview['warnings'] ?? array(), JSON_UNESCAPED_UNICODE ),
				'is_active'        => 1,
				'calculated_by'    => absint( $user_id ),
				'created_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s' )
		);
		if ( false === $inserted ) {
			$db->query( 'ROLLBACK' );
			return new WP_Error( 'allocation_batch_insert_failed', $db->last_error ?: 'Không lưu được phiên tính số lượng cấp phát.' );
		}

		$batch_id = absint( $db->insert_id );
		foreach ( $preview['details'] as $detail ) {
			$ok = $db->insert(
				self::detail_table(),
				array(
					'batch_id'           => $batch_id,
					'source_row'         => absint( $detail['source_row'] ),
					'employee_no'        => sanitize_text_field( $detail['employee_no'] ),
					'item_id'            => absint( $detail['item_id'] ),
					'requested_quantity' => absint( $detail['requested_quantity'] ?? $detail['quantity'] ),
					'allocated_quantity' => absint( $detail['quantity'] ),
				),
				array( '%d', '%d', '%s', '%d', '%d', '%d' )
			);
			if ( false === $ok ) {
				$db->query( 'ROLLBACK' );
				return new WP_Error( 'allocation_detail_insert_failed', $db->last_error ?: 'Không lưu được chi tiết tính số lượng cấp phát.' );
			}
		}

		$deactivated = $db->query(
			$db->prepare(
				'UPDATE ' . self::table() . ' SET is_active = 0 WHERE calculation_year = %d AND period_month = %d AND factory_code = %s AND batch_id <> %d AND is_active = 1',
				absint( $preview['year'] ), absint( $preview['month'] ), self::normalize_batch_factory( $preview['factory_code'] ?? '' ), $batch_id
			)
		);
		if ( false === $deactivated ) {
			$db->query( 'ROLLBACK' );
			return new WP_Error( 'allocation_deactivate_failed', $db->last_error ?: 'Không thể thay thế kết quả tính cũ.' );
		}

		$db->query( 'COMMIT' );
		return array( 'batch_id' => $batch_id, 'detail_count' => count( $preview['details'] ), 'total' => absint( $preview['total_quantity'] ) );
	}

	public static function get_active_totals( $year, $period_month, $factory_code = '' ) {
		if ( ! self::is_ready() ) {
			return array();
		}
		$factory_code = $factory_code !== '' ? UMS_DB_Inventory::normalize_factory_code( $factory_code ) : '';
		$batch = self::get_active_batch( $year, $period_month, $factory_code );
		if ( ! $batch ) {
			return array();
		}
		$organization_join = '';
		$factory_where = '';
		if ( $factory_code !== '' && (string) ( $batch['factory_code'] ?? 'ALL' ) === 'ALL' ) {
			$organization_join = ' LEFT JOIN ' . UMS_DB_Organization::table() . ' organization ON organization.employee_no = details.employee_no';
			if ( $factory_code === 'DA' ) {
				$factory_where = " AND LEFT(REPLACE(organization.cost_center, '-', ''), 4) = '1300'";
			} elseif ( $factory_code === 'VP' ) {
				$factory_where = " AND LEFT(REPLACE(organization.cost_center, '-', ''), 4) = '4900'";
			} else {
				$factory_where = " AND (organization.employee_no IS NULL OR LEFT(REPLACE(organization.cost_center, '-', ''), 4) NOT IN ('1300', '4900'))";
			}
		}
		$rows = self::db()->get_results(
			self::db()->prepare(
				'SELECT details.item_id, SUM(details.allocated_quantity) AS total_quantity
				FROM ' . self::detail_table() . ' details
				INNER JOIN ' . self::table() . ' batches ON batches.batch_id = details.batch_id
				' . $organization_join . '
				WHERE batches.batch_id = %d AND batches.is_active = 1
				' . $factory_where . '
				GROUP BY details.item_id',
				absint( $batch['batch_id'] )
			),
			ARRAY_A
		);
		$totals = array();
		foreach ( $rows as $row ) {
			$totals[ absint( $row['item_id'] ) ] = max( 0, (int) $row['total_quantity'] );
		}
		return $totals;
	}

	public static function get_employee_allocated_items( $employee_no, $until_date = '' ) {
		if ( ! self::is_ready() ) {
			return array();
		}

		$employee_no = trim( sanitize_text_field( (string) $employee_no ) );
		if ( $employee_no === '' ) {
			return array();
		}

		$params = array( $employee_no );
		$where  = array(
			'details.employee_no = %s',
			'batches.is_active = 1',
			'details.allocated_quantity > 0',
		);
		$until_date = trim( sanitize_text_field( (string) $until_date ) );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $until_date ) ) {
			$where[]  = 'batches.created_at <= %s';
			$params[] = $until_date . ' 23:59:59';
		}

		$sql = 'SELECT details.item_id, SUM(details.allocated_quantity) AS issued_quantity,
			MAX(batches.created_at) AS latest_issued_at,
			inventory.item_variant, inventory.size, child.category_name,
			parent.category_name AS parent_category_name
			FROM ' . self::detail_table() . ' details
			INNER JOIN ' . self::table() . ' batches ON batches.batch_id = details.batch_id
			INNER JOIN ' . UMS_DB_Inventory::table() . ' inventory ON inventory.item_id = details.item_id
			LEFT JOIN ' . UMS_DB_Product_Category::table() . ' child ON child.category_id = inventory.category_id
			LEFT JOIN ' . UMS_DB_Product_Category::table() . ' parent ON parent.category_id = child.parent_id
			WHERE ' . implode( ' AND ', $where ) . '
			GROUP BY details.item_id, inventory.item_variant, inventory.size, child.category_name, parent.category_name
			HAVING SUM(details.allocated_quantity) > 0';

		return self::db()->get_results( self::db()->prepare( $sql, $params ), ARRAY_A );
	}

	public static function get_active_batch( $year, $period_month, $factory_code = '' ) {
		if ( ! self::is_ready() ) {
			return null;
		}
		$where = 'calculation_year = %d AND period_month = %d AND is_active = 1';
		$params = array( absint( $year ), absint( $period_month ) );
		if ( $factory_code !== '' ) {
			$where .= ' AND factory_code = %s';
			$params[] = UMS_DB_Inventory::normalize_factory_code( $factory_code );
		}
		$batch = self::db()->get_row(
			self::db()->prepare( 'SELECT * FROM ' . self::table() . ' WHERE ' . $where . ' ORDER BY batch_id DESC LIMIT 1', $params ),
			ARRAY_A
		);
		if ( $batch || $factory_code === '' ) {
			return $batch;
		}
		return self::db()->get_row(
			self::db()->prepare(
				'SELECT * FROM ' . self::table() . " WHERE calculation_year = %d AND period_month = %d AND factory_code = 'ALL' AND is_active = 1 ORDER BY batch_id DESC LIMIT 1",
				absint( $year ), absint( $period_month )
			),
			ARRAY_A
		);
	}

	public static function get_active_batches( $limit = 20 ) {
		if ( ! self::is_ready() ) {
			return array();
		}
		$limit = max( 1, min( 100, absint( $limit ) ) );
		return self::db()->get_results(
			self::db()->prepare(
				'SELECT batches.*, actor.user_login AS calculated_by_login
				FROM ' . self::table() . ' batches
				LEFT JOIN ' . self::db()->users . ' actor ON actor.ID = batches.calculated_by
				WHERE batches.is_active = 1
				ORDER BY batches.calculation_year DESC, batches.period_month DESC, batches.batch_id DESC
				LIMIT %d',
				$limit
			),
			ARRAY_A
		);
	}

	public static function get_batch_summary_rows( $batch_id ) {
		if ( ! self::is_ready() || absint( $batch_id ) <= 0 ) {
			return array();
		}
		return self::db()->get_results(
			self::db()->prepare(
				'SELECT details.item_id, inventory.item_variant, inventory.size,
					SUM(details.requested_quantity) AS requested_quantity,
					SUM(details.allocated_quantity) AS allocated_quantity,
					COUNT(DISTINCT details.employee_no) AS employee_count
				FROM ' . self::detail_table() . ' details
				LEFT JOIN ' . UMS_DB_Inventory::table() . ' inventory ON inventory.item_id = details.item_id
				WHERE details.batch_id = %d
				GROUP BY details.item_id, inventory.item_variant, inventory.size
				ORDER BY inventory.item_variant ASC, inventory.size ASC',
				absint( $batch_id )
			),
			ARRAY_A
		);
	}

	private static function normalize_batch_factory( $factory_code ) {
		$factory_code = strtoupper( sanitize_key( (string) $factory_code ) );
		return array_key_exists( $factory_code, UMS_DB_Inventory::get_factory_options() ) ? $factory_code : 'ALL';
	}

	private static function normalize_datetime( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		return preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ? $value : current_time( 'mysql' );
	}
}
