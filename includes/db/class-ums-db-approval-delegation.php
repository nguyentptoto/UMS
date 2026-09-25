<?php
/**
 * Data access for temporary approval-role delegations.
 */
class UMS_DB_Approval_Delegation extends UMS_DB_Base {

	const SCHEMA_VERSION = '1.1.0';

	public static function table() {
		return self::prefix() . 'uniform_approval_delegations';
	}

	/**
	 * Keep the migration local to this module so existing installations upgrade automatically.
	 */
	public static function ensure_schema() {
		if ( get_option( 'ums_approval_role_schema_version' ) === self::SCHEMA_VERSION ) {
			return;
		}

		$db         = self::db();
		$flow_table = UMS_DB_Approval_Flow::table();
		$columns    = $db->get_col( "SHOW COLUMNS FROM $flow_table", 0 );

		$flow_columns = array(
			'resolver_type'       => "VARCHAR(20) NOT NULL DEFAULT 'specific' AFTER step_name",
			'approver_positions'  => "TEXT NULL AFTER approver_profile_ids",
			'resolver_department' => "VARCHAR(150) NOT NULL DEFAULT '' AFTER approver_positions",
			'resolver_factory'    => "VARCHAR(150) NOT NULL DEFAULT '' AFTER resolver_department",
		);
		foreach ( $flow_columns as $column => $definition ) {
			if ( ! in_array( $column, $columns, true ) ) {
				$db->query( "ALTER TABLE $flow_table ADD COLUMN $column $definition" );
			}
		}

		$request_table   = UMS_DB_Request::table();
		$request_columns = $db->get_col( "SHOW COLUMNS FROM $request_table", 0 );
		if ( ! in_array( 'approval_flow_snapshot', $request_columns, true ) ) {
			$db->query( "ALTER TABLE $request_table ADD COLUMN approval_flow_snapshot LONGTEXT NULL AFTER current_status" );
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $db->get_charset_collate();
		$table           = self::table();
		$sql = "CREATE TABLE $table (
			delegation_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			delegate_profile_id BIGINT(20) UNSIGNED NOT NULL,
			role_code VARCHAR(50) NOT NULL,
			department VARCHAR(150) NOT NULL DEFAULT '',
			factory VARCHAR(150) NOT NULL DEFAULT '',
			start_date DATE NOT NULL,
			end_date DATE DEFAULT NULL,
			reason VARCHAR(255) NOT NULL DEFAULT '',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (delegation_id),
			KEY idx_delegate (delegate_profile_id),
			KEY idx_role_scope (role_code, department, factory),
			KEY idx_effective (is_active, start_date, end_date)
		) $charset_collate;";
		dbDelta( $sql );

		$installed_flow_columns = $db->get_col( "SHOW COLUMNS FROM $flow_table", 0 );
		$installed_request_columns = $db->get_col( "SHOW COLUMNS FROM $request_table", 0 );
		$table_exists = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		if (
			$db->last_error === ''
			&& empty( array_diff( array_keys( $flow_columns ), $installed_flow_columns ) )
			&& in_array( 'approval_flow_snapshot', $installed_request_columns, true )
			&& $table_exists
		) {
			update_option( 'ums_approval_role_schema_version', self::SCHEMA_VERSION, false );
		}
	}

	public static function get_all( $args = array() ) {
		$args = wp_parse_args( $args, array( 'status' => '', 'profile_id' => 0 ) );
		$where  = array( '1=1' );
		$params = array();
		if ( $args['status'] === 'active' ) {
			$where[] = 'delegation.is_active = 1';
		} elseif ( $args['status'] === 'inactive' ) {
			$where[] = 'delegation.is_active = 0';
		}
		if ( absint( $args['profile_id'] ) > 0 ) {
			$where[]  = 'delegation.delegate_profile_id = %d';
			$params[] = absint( $args['profile_id'] );
		}

		$sql = 'SELECT delegation.* FROM ' . self::table() . ' delegation WHERE ' . implode( ' AND ', $where ) .
			' ORDER BY delegation.is_active DESC, delegation.start_date DESC, delegation.delegation_id DESC';
		if ( $params ) {
			$sql = self::db()->prepare( $sql, $params );
		}
		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_by_id( $delegation_id ) {
		return self::db()->get_row(
			self::db()->prepare( 'SELECT * FROM ' . self::table() . ' WHERE delegation_id = %d', absint( $delegation_id ) ),
			ARRAY_A
		);
	}

	public static function get_active_profile_ids( $role_codes, $department = '', $factory = '', $date = '' ) {
		$role_codes = array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_role' ), (array) $role_codes ) ) ) );
		if ( empty( $role_codes ) ) {
			return array();
		}
		$date        = $date !== '' ? sanitize_text_field( $date ) : current_time( 'Y-m-d' );
		$department  = self::normalize_scope( $department );
		$factory     = self::normalize_scope( $factory );
		$placeholders = implode( ',', array_fill( 0, count( $role_codes ), '%s' ) );
		$params       = $role_codes;
		$params[]     = $date;
		$params[]     = $date;

		$rows = self::db()->get_results(
			self::db()->prepare(
				'SELECT delegate_profile_id, department, factory FROM ' . self::table() .
				" WHERE is_active = 1 AND UPPER(TRIM(role_code)) IN ($placeholders)
				AND start_date <= %s AND (end_date IS NULL OR end_date = '0000-00-00' OR end_date >= %s)",
				$params
			),
			ARRAY_A
		);

		$ids = array();
		foreach ( $rows as $row ) {
			$row_department = self::normalize_scope( $row['department'] );
			$row_factories  = self::decode_scope_values( $row['factory'] );
			if ( $row_department !== '' && $row_department !== $department ) {
				continue;
			}
			if ( ! empty( $row_factories ) && ! in_array( $factory, $row_factories, true ) ) {
				continue;
			}
			$ids[] = absint( $row['delegate_profile_id'] );
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public static function insert( $data ) {
		return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
	}

	public static function update( $delegation_id, $data ) {
		return self::db()->update( self::table(), $data, array( 'delegation_id' => absint( $delegation_id ) ), self::formats_for( $data ), array( '%d' ) );
	}

	public static function delete( $delegation_id ) {
		return self::db()->delete( self::table(), array( 'delegation_id' => absint( $delegation_id ) ), array( '%d' ) );
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}

	public static function normalize_role( $role ) {
		return strtoupper( trim( sanitize_text_field( (string) $role ) ) );
	}

	private static function normalize_scope( $value ) {
		$value = remove_accents( preg_replace( '/\s+/u', ' ', trim( (string) $value ) ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function decode_scope_values( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		$values  = is_array( $decoded ) ? $decoded : preg_split( '/[,;]+/', $value );
		return array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_scope' ), $values ) ) ) );
	}

	private static function formats_for( $data ) {
		$integers = array( 'delegate_profile_id', 'is_active' );
		return array_map( function ( $field ) use ( $integers ) {
			return in_array( $field, $integers, true ) ? '%d' : '%s';
		}, array_keys( $data ) );
	}
}
