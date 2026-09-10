<?php
/**
 * Luu ket qua tinh so luong cap phat da chot de lam dau vao cho PR.
 */
class UMS_DB_Allocation_Calculation extends UMS_DB_Base {
	public static function table() {
		return self::prefix() . 'uniform_allocation_calculation_batches';
	}

	public static function detail_table() {
		return self::prefix() . 'uniform_allocation_calculation_details';
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
				'file_name'        => sanitize_file_name( $preview['file_name'] ),
				'file_hash'        => sanitize_text_field( $preview['file_hash'] ),
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
			array( '%d', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%s' )
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
				'UPDATE ' . self::table() . ' SET is_active = 0 WHERE calculation_year = %d AND period_month = %d AND batch_id <> %d AND is_active = 1',
				absint( $preview['year'] ), absint( $preview['month'] ), $batch_id
			)
		);
		if ( false === $deactivated ) {
			$db->query( 'ROLLBACK' );
			return new WP_Error( 'allocation_deactivate_failed', $db->last_error ?: 'Không thể thay thế kết quả tính cũ.' );
		}

		$db->query( 'COMMIT' );
		return array( 'batch_id' => $batch_id, 'detail_count' => count( $preview['details'] ), 'total' => absint( $preview['total_quantity'] ) );
	}

	public static function get_active_totals( $year, $period_month ) {
		if ( ! self::is_ready() ) {
			return array();
		}
		$rows = self::db()->get_results(
			self::db()->prepare(
				'SELECT details.item_id, SUM(details.allocated_quantity) AS total_quantity
				FROM ' . self::detail_table() . ' details
				INNER JOIN ' . self::table() . ' batches ON batches.batch_id = details.batch_id
				WHERE batches.calculation_year = %d AND batches.period_month = %d AND batches.is_active = 1
				GROUP BY details.item_id',
				absint( $year ), absint( $period_month )
			),
			ARRAY_A
		);
		$totals = array();
		foreach ( $rows as $row ) {
			$totals[ absint( $row['item_id'] ) ] = max( 0, (int) $row['total_quantity'] );
		}
		return $totals;
	}

	public static function get_active_batch( $year, $period_month ) {
		if ( ! self::is_ready() ) {
			return null;
		}
		return self::db()->get_row(
			self::db()->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE calculation_year = %d AND period_month = %d AND is_active = 1 ORDER BY batch_id DESC LIMIT 1',
				absint( $year ), absint( $period_month )
			),
			ARRAY_A
		);
	}
}
