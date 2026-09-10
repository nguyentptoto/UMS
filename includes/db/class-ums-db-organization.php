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
			'employment_status' => 'active',
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
			'first_contract_date',
			'previous_position',
			'email',
			'factory',
			'source_updated_at',
			'synced_at',
			'employment_status',
			'left_detected_at',
		);
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'employee_no';
		$order   = strtoupper( (string) $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$page    = max( 1, absint( $args['page'] ) );
		$limit   = max( 10, min( 100, absint( $args['per_page'] ) ) );
		$offset  = ( $page - 1 ) * $limit;
		$table   = self::table();

		$sql = "SELECT source_id, sheet_stt, source_version, employee_no, full_name, division, department, section, team,
			position, cost_center, date_joined, first_contract_date, previous_position, email, factory, source_created_at, source_updated_at, synced_at,
			employment_status, last_seen_at, left_detected_at
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
		$allowed = array( 'division', 'department', 'section', 'team', 'position', 'cost_center', 'factory' );
		if ( ! in_array( $column, $allowed, true ) ) {
			return array();
		}

		$table = self::table();
		return self::db()->get_col( "SELECT DISTINCT $column FROM $table WHERE $column <> '' AND employment_status = 'active' ORDER BY $column ASC" );
	}

	/**
	 * Danh sách người nhận dùng tại form xuất kho chủ động.
	 */
	public static function get_recipient_options() {
		if ( ! self::table_exists() ) {
			return array();
		}

		return self::db()->get_results(
			'SELECT employee_no, full_name, department, team, position, date_joined, first_contract_date
			FROM ' . self::table() . "
			WHERE employee_no <> '' AND employment_status = 'active'
			ORDER BY employee_no ASC",
			ARRAY_A
		);
	}

	/**
	 * Lấy nhân sự phục vụ báo cáo định mức, không áp dụng phân trang giao diện.
	 */
	public static function get_for_allowance_export( $args = array() ) {
		if ( ! self::table_exists() ) {
			return array();
		}

		$args = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'department'  => '',
				'team'        => '',
				'cost_center' => '',
				'cost_center_prefixes' => array(),
				'position'    => '',
			)
		);
		$where  = array( "employee_no <> ''", "employment_status = 'active'" );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like = '%' . self::db()->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(employee_no LIKE %s OR full_name LIKE %s OR email LIKE %s)';
			$params  = array_merge( $params, array( $like, $like, $like ) );
		}

		foreach ( array( 'department', 'team', 'cost_center', 'position' ) as $field ) {
			if ( $args[ $field ] !== '' ) {
				$where[]  = "$field = %s";
				$params[] = sanitize_text_field( $args[ $field ] );
			}
		}

		$prefixes = array_values(
			array_filter(
				array_map(
					function ( $prefix ) {
						return preg_replace( '/[^0-9A-Za-z_-]/', '', (string) $prefix );
					},
					(array) $args['cost_center_prefixes']
				)
			)
		);
		if ( ! empty( $prefixes ) ) {
			$prefix_conditions = array();
			foreach ( $prefixes as $prefix ) {
				$prefix_conditions[] = 'cost_center LIKE %s';
				$params[] = self::db()->esc_like( $prefix ) . '%';
			}
			$where[] = '(' . implode( ' OR ', $prefix_conditions ) . ')';
		}

		$sql = 'SELECT employee_no, full_name, department, team, position, cost_center, date_joined, first_contract_date, email, factory
			FROM ' . self::table() . '
			WHERE ' . implode( ' AND ', $where ) . '
			ORDER BY employee_no ASC';
		if ( ! empty( $params ) ) {
			$sql = self::db()->prepare( $sql, $params );
		}

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_last_synced_at() {
		return self::db()->get_var( 'SELECT MAX(synced_at) FROM ' . self::table() );
	}

	/**
	 * Lấy dữ liệu tổ chức mới nhất của một nhân viên theo mã nhân viên.
	 */
	public static function get_by_employee_no( $employee_no ) {
		$employee_no = trim( sanitize_text_field( (string) $employee_no ) );
		if ( $employee_no === '' || ! self::table_exists() ) {
			return null;
		}

		return self::db()->get_row(
			self::db()->prepare(
				"SELECT * FROM " . self::table() . " WHERE employee_no = %s AND employment_status = 'active' LIMIT 1",
				$employee_no
			),
			ARRAY_A
		);
	}

	/**
	 * Lay nhieu nhan su trong mot truy van, lap chi muc theo ma nhan vien viet hoa.
	 */
	public static function get_by_employee_nos( $employee_nos ) {
		$employee_nos = array_values( array_unique( array_filter( array_map( 'trim', (array) $employee_nos ) ) ) );
		if ( empty( $employee_nos ) || ! self::table_exists() ) {
			return array();
		}

		$result = array();
		foreach ( array_chunk( $employee_nos, 250 ) as $batch ) {
			$placeholders = implode( ',', array_fill( 0, count( $batch ), '%s' ) );
			$rows = self::db()->get_results(
				self::db()->prepare(
					'SELECT employee_no, full_name, department, team, position, cost_center, date_joined, first_contract_date, email, factory
					FROM ' . self::table() . " WHERE employment_status = 'active' AND employee_no IN ($placeholders)",
					$batch
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$result[ strtoupper( trim( (string) $row['employee_no'] ) ) ] = $row;
			}
		}
		return $result;
	}

	/**
	 * Lấy nhân sự tổ chức gắn với một tài khoản WordPress.
	 *
	 * Mã nhân viên trong usermeta là nguồn liên kết chính. user_login chỉ là
	 * phương án dự phòng cho các tài khoản được tạo trước cơ chế đồng bộ Sheet.
	 */
	public static function get_by_wp_user_id( $user_id, $employee_no = '' ) {
		$user_id     = absint( $user_id );
		$employee_no = trim( sanitize_text_field( (string) $employee_no ) );

		if ( $employee_no === '' && $user_id > 0 ) {
			$employee_no = trim( (string) get_user_meta( $user_id, 'ums_employee_code', true ) );
		}

		if ( $employee_no === '' && $user_id > 0 ) {
			$user = get_userdata( $user_id );
			$employee_no = $user instanceof WP_User ? trim( (string) $user->user_login ) : '';
		}

		return $employee_no !== '' ? self::get_by_employee_no( $employee_no ) : null;
	}

	public static function upsert_batch( $rows, $sync_token, $synced_at ) {
		if ( empty( $rows ) ) {
			return 0;
		}

		$placeholders = array();
		$params       = array();

		foreach ( $rows as $row ) {
			$placeholders[] = '(%d,%d,%d,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)';
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
			$params[] = $row['first_contract_date'];
			$params[] = $row['previous_position'];
			$params[] = $row['email'];
			$params[] = $row['factory'];
			$params[] = $row['time_create'];
			$params[] = $row['time_update'];
			$params[] = $synced_at;
			$params[] = $sync_token;
			$params[] = 'active';
			$params[] = $synced_at;
		}

		$table = self::table();
		$sql = "INSERT INTO $table
			(source_id, sheet_stt, source_version, employee_no, full_name, division, department, section, team, position,
			cost_center, date_joined, first_contract_date, previous_position, email, factory, source_created_at, source_updated_at, synced_at, sync_token,
			employment_status, last_seen_at)
			VALUES " . implode( ',', $placeholders ) . '
			ON DUPLICATE KEY UPDATE
			sheet_stt = VALUES(sheet_stt), source_version = VALUES(source_version), employee_no = VALUES(employee_no), full_name = VALUES(full_name),
			division = VALUES(division), department = VALUES(department), section = VALUES(section), team = VALUES(team),
			position = VALUES(position), cost_center = VALUES(cost_center), date_joined = VALUES(date_joined), first_contract_date = VALUES(first_contract_date),
			previous_position = VALUES(previous_position), email = VALUES(email), factory = VALUES(factory),
			source_created_at = VALUES(source_created_at), source_updated_at = VALUES(source_updated_at),
			synced_at = VALUES(synced_at), sync_token = VALUES(sync_token), employment_status = VALUES(employment_status),
			last_seen_at = VALUES(last_seen_at), left_detected_at = NULL';

		return self::db()->query( self::db()->prepare( $sql, $params ) );
	}

	public static function get_active_not_in_sync( $sync_token ) {
		return self::db()->get_results(
			self::db()->prepare(
				"SELECT * FROM " . self::table() . " WHERE employment_status = 'active' AND sync_token <> %s ORDER BY employee_no ASC",
				$sync_token
			),
			ARRAY_A
		);
	}

	public static function mark_not_in_sync_as_left( $sync_token, $detected_at ) {
		return self::db()->query(
			self::db()->prepare(
				"UPDATE " . self::table() . " SET employment_status = 'left', left_detected_at = %s WHERE employment_status = 'active' AND sync_token <> %s",
				$detected_at,
				$sync_token
			)
		);
	}

	public static function supports_employment_status() {
		if ( ! self::table_exists() ) {
			return false;
		}
		$column = self::db()->get_var( "SHOW COLUMNS FROM " . self::table() . " LIKE 'employment_status'" );
		return $column === 'employment_status';
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
				'employment_status' => 'active',
			)
		);
		$where  = array( '1=1' );
		$params = array();

		if ( $args['employment_status'] !== '' && $args['employment_status'] !== 'all' ) {
			$where[]  = 'employment_status = %s';
			$params[] = sanitize_key( $args['employment_status'] );
		}

		if ( $args['search'] !== '' ) {
			$like = '%' . self::db()->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(employee_no LIKE %s OR full_name LIKE %s OR email LIKE %s OR department LIKE %s OR team LIKE %s OR position LIKE %s OR previous_position LIKE %s OR cost_center LIKE %s)';
			$params = array_merge( $params, array_fill( 0, 8, $like ) );
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
