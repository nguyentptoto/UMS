<?php
/**
 * Data access for employee exit cases and uniform returns.
 */
class UMS_DB_Employee_Exit extends UMS_DB_Base {

	public static function case_table() {
		return self::prefix() . 'uniform_employee_exit_cases';
	}

	public static function item_table() {
		return self::prefix() . 'uniform_employee_exit_items';
	}

	public static function is_ready() {
		foreach ( array( self::case_table(), self::item_table() ) as $table ) {
			$table_name = str_replace( '`', '``', $table );
			$suppress   = self::db()->suppress_errors( true );
			self::db()->query( "SELECT 1 FROM `$table_name` LIMIT 1" );
			$exists = self::db()->last_error === '';
			self::db()->suppress_errors( $suppress );
			if ( ! $exists ) {
				return false;
			}
		}
		$case_columns = self::db()->get_col( 'SHOW COLUMNS FROM ' . self::case_table(), 0 );
		$required = array(
			'email', 'factory', 'notification_status', 'notification_sent_at', 'notification_attempted_at', 'notification_error',
		);
		if ( array_diff( $required, $case_columns ) ) {
			return false;
		}
		return true;
	}

	public static function get_page( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array( 'search' => '', 'status' => '', 'employee_type' => '', 'page' => 1, 'per_page' => 50 )
		);
		list( $where, $params ) = self::build_where( $args );
		$limit  = max( 10, min( 100, absint( $args['per_page'] ) ) );
		$offset = ( max( 1, absint( $args['page'] ) ) - 1 ) * $limit;
		$params[] = $limit;
		$params[] = $offset;

		$sql = 'SELECT * FROM ' . self::case_table() . ' WHERE ' . implode( ' AND ', $where ) .
			' ORDER BY detected_at DESC, exit_id DESC LIMIT %d OFFSET %d';
		return self::db()->get_results( self::db()->prepare( $sql, $params ), ARRAY_A );
	}

	public static function get_all( $args = array() ) {
		list( $where, $params ) = self::build_where( $args );
		$sql = 'SELECT * FROM ' . self::case_table() . ' WHERE ' . implode( ' AND ', $where ) .
			' ORDER BY detected_at DESC, exit_id DESC';
		if ( $params ) {
			$sql = self::db()->prepare( $sql, $params );
		}
		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_count( $args = array() ) {
		list( $where, $params ) = self::build_where( $args );
		$sql = 'SELECT COUNT(*) FROM ' . self::case_table() . ' WHERE ' . implode( ' AND ', $where );
		if ( $params ) {
			$sql = self::db()->prepare( $sql, $params );
		}
		return (int) self::db()->get_var( $sql );
	}

	public static function get_status_counts( $args = array() ) {
		$args['status'] = '';
		list( $where, $params ) = self::build_where( $args );
		$where = array_values( array_filter( $where, function ( $condition ) {
			return $condition !== "status <> 'cancelled'";
		} ) );
		$where[] = "status <> 'cancelled'";
		$sql = 'SELECT status, COUNT(*) AS total FROM ' . self::case_table() .
			' WHERE ' . implode( ' AND ', $where ) . ' GROUP BY status';
		if ( $params ) {
			$sql = self::db()->prepare( $sql, $params );
		}
		$rows = self::db()->get_results(
			$sql,
			ARRAY_A
		);
		$result = array( 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0 );
		foreach ( $rows as $row ) {
			$result[ $row['status'] ] = (int) $row['total'];
		}
		return $result;
	}

	public static function get_by_id( $exit_id ) {
		return self::db()->get_row(
			self::db()->prepare( 'SELECT * FROM ' . self::case_table() . ' WHERE exit_id = %d', absint( $exit_id ) ),
			ARRAY_A
		);
	}

	public static function get_open_by_employee_no( $employee_no ) {
		return self::db()->get_row(
			self::db()->prepare(
				"SELECT * FROM " . self::case_table() . " WHERE employee_no = %s AND status IN ('pending','in_progress') ORDER BY exit_id DESC LIMIT 1",
				trim( (string) $employee_no )
			),
			ARRAY_A
		);
	}

	public static function get_latest_by_employee_no( $employee_no ) {
		return self::db()->get_row(
			self::db()->prepare(
				"SELECT * FROM " . self::case_table() . " WHERE employee_no = %s AND status <> 'cancelled' ORDER BY exit_id DESC LIMIT 1",
				trim( (string) $employee_no )
			),
			ARRAY_A
		);
	}

	public static function get_items( $exit_id ) {
		return self::db()->get_results(
			self::db()->prepare(
				'SELECT * FROM ' . self::item_table() . ' WHERE exit_id = %d ORDER BY display_order ASC, return_item_id ASC',
				absint( $exit_id )
			),
			ARRAY_A
		);
	}

	public static function insert_case( $data ) {
		$formats = array_fill( 0, count( $data ), '%s' );
		$organization_source_index = array_search( 'organization_source_id', array_keys( $data ), true );
		if ( false !== $organization_source_index ) {
			$formats[ $organization_source_index ] = '%d';
		}
		$result = self::db()->insert(
			self::case_table(),
			$data,
			$formats
		);
		return $result ? (int) self::db()->insert_id : 0;
	}

	public static function get_notification_candidates( $limit = 100 ) {
		return self::db()->get_results(
			self::db()->prepare(
				"SELECT * FROM " . self::case_table() . "
				WHERE notification_status IN ('pending','failed') AND status IN ('pending','in_progress')
				ORDER BY detected_at ASC, exit_id ASC LIMIT %d",
				max( 1, min( 500, absint( $limit ) ) )
			),
			ARRAY_A
		);
	}

	public static function insert_item( $data ) {
		return self::db()->insert(
			self::item_table(),
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%d' )
		);
	}

	public static function update_case( $exit_id, $data, $formats = null ) {
		if ( $formats === null ) {
			$formats = array_fill( 0, count( $data ), '%s' );
		}
		return self::db()->update( self::case_table(), $data, array( 'exit_id' => absint( $exit_id ) ), $formats, array( '%d' ) );
	}

	public static function update_item( $return_item_id, $data, $formats = null ) {
		if ( $formats === null ) {
			$formats = array_fill( 0, count( $data ), '%d' );
		}
		return self::db()->update(
			self::item_table(),
			$data,
			array( 'return_item_id' => absint( $return_item_id ) ),
			$formats,
			array( '%d' )
		);
	}

	public static function delete_items( $exit_id ) {
		return self::db()->delete( self::item_table(), array( 'exit_id' => absint( $exit_id ) ), array( '%d' ) );
	}

	public static function cancel_open_cases( $employee_nos ) {
		$employee_nos = array_values( array_unique( array_filter( array_map( 'trim', (array) $employee_nos ) ) ) );
		if ( empty( $employee_nos ) ) {
			return 0;
		}
		$placeholders = implode( ',', array_fill( 0, count( $employee_nos ), '%s' ) );
		$params = array_merge( array( current_time( 'mysql' ) ), $employee_nos );
		return self::db()->query(
			self::db()->prepare(
				"UPDATE " . self::case_table() . " SET status = 'cancelled', updated_at = %s,
				notes = CONCAT(COALESCE(notes, ''), CASE WHEN COALESCE(notes, '') = '' THEN '' ELSE '\n' END, 'Tự động hủy vì CNV xuất hiện lại trong sơ đồ tổ chức.')
				WHERE employee_no IN ($placeholders) AND status IN ('pending','in_progress')",
				$params
			)
		);
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}

	private static function build_where( $args ) {
		$args = wp_parse_args( $args, array( 'search' => '', 'status' => '', 'employee_type' => '', 'factory_code' => '' ) );
		$where = array( '1=1' );
		$params = array();
		if ( trim( (string) $args['search'] ) !== '' ) {
			$like = '%' . self::db()->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(employee_no LIKE %s OR full_name LIKE %s OR department LIKE %s OR cost_center LIKE %s)';
			$params = array_merge( $params, array( $like, $like, $like, $like ) );
		}
		if ( $args['status'] !== '' ) {
			$where[] = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		} else {
			// Cancelled cases are retained for sync history, not as a return workflow status.
			$where[] = "status <> 'cancelled'";
		}
		if ( $args['employee_type'] !== '' ) {
			$where[] = 'employee_type = %s';
			$params[] = sanitize_key( $args['employee_type'] );
		}
		$factory_code = strtoupper( sanitize_key( (string) $args['factory_code'] ) );
		$da_condition = "(COALESCE(factory, '') LIKE %s OR COALESCE(factory, '') LIKE %s OR COALESCE(department, '') LIKE %s OR COALESCE(cost_center, '') LIKE %s)";
		$vp_condition = "(COALESCE(factory, '') LIKE %s OR COALESCE(factory, '') LIKE %s OR COALESCE(department, '') LIKE %s OR COALESCE(cost_center, '') LIKE %s)";
		$da_params = array( '%Đông Anh%', '%Dong Anh%', '%(DA)%', '1300%' );
		$vp_params = array( '%Vĩnh Phúc%', '%Vinh Phuc%', '%(VP)%', '4900%' );
		if ( $factory_code === 'DA' ) {
			$where[] = $da_condition;
			$params = array_merge( $params, $da_params );
		} elseif ( $factory_code === 'VP' ) {
			$where[] = 'NOT ' . $da_condition . ' AND ' . $vp_condition;
			$params = array_merge( $params, $da_params, $vp_params );
		} elseif ( $factory_code === 'HY' ) {
			// Keep the same fallback as inventory routing: records not identified as DA/VP belong to HY.
			$where[] = 'NOT ' . $da_condition . ' AND NOT ' . $vp_condition;
			$params = array_merge( $params, $da_params, $vp_params );
		}
		return array( $where, $params );
	}
}
