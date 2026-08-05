<?php
/**
 * Tầng dữ liệu cho danh sách nhân sự thuộc sơ đồ tổ chức TVN.
 */
class UMS_DB_Organization extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_organization_employees';
	}

	public static function table_exists() {
		$db              = self::db();
		$table           = str_replace( '`', '``', self::table() );
		$suppress_errors = $db->suppress_errors( true );
		$db->query( "SELECT 1 FROM `$table` LIMIT 1" );
		$exists = $db->last_error === '';
		$db->suppress_errors( $suppress_errors );

		return $exists;
	}

	public static function get_page( $args = array() ) {
		$defaults = array(
			'search'     => '',
			'division'   => '',
			'department' => '',
			'factory'    => '',
			'page'       => 1,
			'per_page'   => 20,
			'orderby'    => 'employee_no',
			'order'      => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		list( $where, $params ) = self::build_where( $args );
		$allowed_orderby = array(
			'source_id',
			'sheet_stt',
			'source_version',
			'employee_no',
			'full_name',
			'division',
			'department',
			'section',
			'team',
			'position',
			'cost_center',
			'date_joined',
			'previous_position',
			'email',
			'factory',
			'source_updated_at',
			'synced_at',
		);
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'employee_no';
		$order   = strtoupper( (string) $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$page    = max( 1, absint( $args['page'] ) );
		$limit   = max( 10, min( 100, absint( $args['per_page'] ) ) );
		$offset  = ( $page - 1 ) * $limit;
		$table   = self::table();

		$sql = "SELECT source_id, sheet_stt, source_version, employee_no, full_name, division, department, section, team,
			position, cost_center, date_joined, previous_position, email, factory, source_created_at, source_updated_at, synced_at
			FROM $table
			WHERE " . implode( ' AND ', $where ) . "
			ORDER BY $orderby $order, source_id ASC
			LIMIT %d OFFSET %d";
		$params[] = $limit;
		$params[] = $offset;

		return self::db()->get_results( self::db()->prepare( $sql, $params ), ARRAY_A );
	}

	public static function get_count( $args = array() ) {
		list( $where, $params ) = self::build_where( $args );
		$sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );

		if ( ! empty( $params ) ) {
			$sql = self::db()->prepare( $sql, $params );
		}

		return (int) self::db()->get_var( $sql );
	}

	public static function get_distinct_values( $column ) {
		$allowed = array( 'division', 'department', 'factory' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return array();
		}

		$table = self::table();
		return self::db()->get_col( "SELECT DISTINCT $column FROM $table WHERE $column <> '' ORDER BY $column ASC" );
	}

	public static function get_last_synced_at() {
		return self::db()->get_var( 'SELECT MAX(synced_at) FROM ' . self::table() );
	}

	public static function upsert_batch( $rows, $sync_token, $synced_at ) {
		if ( empty( $rows ) ) {
			return 0;
		}

		$placeholders = array();
		$params       = array();

		foreach ( $rows as $row ) {
			$placeholders[] = '(%d,%d,%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)';
			$params[] = absint( $row['id'] );
			$params[] = absint( $row['sheet_stt'] );
			$params[] = (int) $row['version'];
			$params[] = $row['emp_no'];
			$params[] = $row['fname'];
			$params[] = $row['division'];
			$params[] = $row['department'];
			$params[] = $row['section'];
			$params[] = $row['team'];
			$params[] = $row['position'];
			$params[] = $row['cost_center'];
			$params[] = $row['date_joined'];
			$params[] = $row['previous_position'];
			$params[] = $row['email'];
			$params[] = $row['factory'];
			$params[] = $row['time_create'];
			$params[] = $row['time_update'];
			$params[] = $synced_at;
			$params[] = $sync_token;
		}

		$table = self::table();
		$sql = "INSERT INTO $table
			(source_id, sheet_stt, source_version, employee_no, full_name, division, department, section, team, position,
			cost_center, date_joined, previous_position, email, factory, source_created_at, source_updated_at, synced_at, sync_token)
			VALUES " . implode( ',', $placeholders ) . '
			ON DUPLICATE KEY UPDATE
			sheet_stt = VALUES(sheet_stt), source_version = VALUES(source_version), employee_no = VALUES(employee_no), full_name = VALUES(full_name),
			division = VALUES(division), department = VALUES(department), section = VALUES(section), team = VALUES(team),
			position = VALUES(position), cost_center = VALUES(cost_center), date_joined = VALUES(date_joined),
			previous_position = VALUES(previous_position), email = VALUES(email), factory = VALUES(factory),
			source_created_at = VALUES(source_created_at), source_updated_at = VALUES(source_updated_at),
			synced_at = VALUES(synced_at), sync_token = VALUES(sync_token)';

		return self::db()->query( self::db()->prepare( $sql, $params ) );
	}

	public static function delete_not_in_sync( $sync_token ) {
		$table = self::table();
		return self::db()->query( self::db()->prepare( "DELETE FROM $table WHERE sync_token <> %s", $sync_token ) );
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}

	private static function build_where( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'search'     => '',
				'division'   => '',
				'department' => '',
				'factory'    => '',
			)
		);
		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like = '%' . self::db()->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(employee_no LIKE %s OR full_name LIKE %s OR department LIKE %s OR team LIKE %s OR position LIKE %s OR previous_position LIKE %s OR cost_center LIKE %s)';
			$params = array_merge( $params, array_fill( 0, 7, $like ) );
		}

		foreach ( array( 'division', 'department', 'factory' ) as $field ) {
			if ( $args[ $field ] !== '' ) {
				$where[]  = "$field = %s";
				$params[] = sanitize_text_field( $args[ $field ] );
			}
		}

		return array( $where, $params );
	}
}
