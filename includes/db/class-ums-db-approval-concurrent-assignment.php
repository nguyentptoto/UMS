<?php
/**
 * Additional organization roles held concurrently by an employee.
 */
class UMS_DB_Approval_Concurrent_Assignment extends UMS_DB_Base {

	const SCHEMA_VERSION = '1.0.0';

	public static function table() {
		return self::prefix() . 'uniform_approval_concurrent_assignments';
	}

	public static function ensure_schema() {
		if ( get_option( 'ums_approval_concurrent_schema_version' ) === self::SCHEMA_VERSION ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$db              = self::db();
		$table           = self::table();
		$charset_collate = $db->get_charset_collate();
		$sql = "CREATE TABLE $table (
			assignment_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			profile_id BIGINT(20) UNSIGNED NOT NULL,
			role_code VARCHAR(50) NOT NULL,
			departments LONGTEXT NULL,
			factories LONGTEXT NULL,
			note VARCHAR(255) NOT NULL DEFAULT '',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (assignment_id),
			KEY idx_profile (profile_id),
			KEY idx_role_active (role_code, is_active)
		) $charset_collate;";
		dbDelta( $sql );

		$table_exists = $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
		if ( $db->last_error === '' && $table_exists ) {
			update_option( 'ums_approval_concurrent_schema_version', self::SCHEMA_VERSION, false );
		}
	}

	public static function get_all( $args = array() ) {
		$args   = wp_parse_args( $args, array( 'status' => '', 'profile_id' => 0 ) );
		$where  = array( '1=1' );
		$params = array();
		if ( $args['status'] === 'active' ) {
			$where[] = 'assignment.is_active = 1';
		} elseif ( $args['status'] === 'inactive' ) {
			$where[] = 'assignment.is_active = 0';
		}
		if ( absint( $args['profile_id'] ) > 0 ) {
			$where[]  = 'assignment.profile_id = %d';
			$params[] = absint( $args['profile_id'] );
		}

		$sql = 'SELECT assignment.* FROM ' . self::table() . ' assignment WHERE ' . implode( ' AND ', $where ) .
			' ORDER BY assignment.is_active DESC, assignment.role_code ASC, assignment.assignment_id DESC';
		if ( $params ) {
			$sql = self::db()->prepare( $sql, $params );
		}
		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_by_id( $assignment_id ) {
		return self::db()->get_row(
			self::db()->prepare( 'SELECT * FROM ' . self::table() . ' WHERE assignment_id = %d', absint( $assignment_id ) ),
			ARRAY_A
		);
	}

	/**
	 * Resolve concurrent roles in the department and factory of a request.
	 */
	public static function get_active_profile_ids( $role_codes, $department = '', $factory = '' ) {
		$role_codes = array_values( array_unique( array_filter( array_map( array( 'UMS_DB_Approval_Delegation', 'normalize_role' ), (array) $role_codes ) ) ) );
		if ( empty( $role_codes ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $role_codes ), '%s' ) );
		$rows = self::db()->get_results(
			self::db()->prepare(
				'SELECT profile_id, departments, factories FROM ' . self::table() .
				" WHERE is_active = 1 AND UPPER(TRIM(role_code)) IN ($placeholders)",
				$role_codes
			),
			ARRAY_A
		);

		$department_key = self::normalize_scope( $department );
		$factory_key    = UMS_DB_Organization::normalize_approval_factory_code( $factory );
		$ids            = array();
		foreach ( $rows as $row ) {
			$departments = self::decode_scope_values( $row['departments'] );
			$factories   = array_values( array_unique( array_filter( array_map(
				array( 'UMS_DB_Organization', 'normalize_approval_factory_code' ),
				self::decode_values( $row['factories'] )
			) ) ) );
			if ( ! empty( $departments ) && ! in_array( $department_key, $departments, true ) ) {
				continue;
			}
			if ( ! empty( $factories ) && ! in_array( $factory_key, $factories, true ) ) {
				continue;
			}
			$ids[] = absint( $row['profile_id'] );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	public static function insert( $data ) {
		return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
	}

	public static function update( $assignment_id, $data ) {
		return self::db()->update( self::table(), $data, array( 'assignment_id' => absint( $assignment_id ) ), self::formats_for( $data ), array( '%d' ) );
	}

	public static function delete( $assignment_id ) {
		return self::db()->delete( self::table(), array( 'assignment_id' => absint( $assignment_id ) ), array( '%d' ) );
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}

	public static function decode_values( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return array();
		}
		$decoded = json_decode( $value, true );
		return is_array( $decoded ) ? array_values( array_filter( array_map( 'sanitize_text_field', $decoded ) ) ) : array( sanitize_text_field( $value ) );
	}

	private static function normalize_scope( $value ) {
		$value = remove_accents( preg_replace( '/\s+/u', ' ', trim( (string) $value ) ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function decode_scope_values( $value ) {
		return array_values( array_unique( array_filter( array_map( array( __CLASS__, 'normalize_scope' ), self::decode_values( $value ) ) ) ) );
	}

	private static function formats_for( $data ) {
		$integers = array( 'profile_id', 'is_active' );
		return array_map( function ( $field ) use ( $integers ) {
			return in_array( $field, $integers, true ) ? '%d' : '%s';
		}, array_keys( $data ) );
	}
}
