<?php
/**
 * Data layer for assigning special-work allowance matrices to employees.
 */
class UMS_DB_Special_Work_Assignment extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_special_work_assignments';
	}

	public static function table_exists() {
		$table = self::table();
		return self::db()->get_var( self::db()->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public static function get_for_employee( $employee_no, $period_month ) {
		$employee_no = trim( sanitize_text_field( (string) $employee_no ) );
		$period_month = absint( $period_month );
		if ( $employee_no === '' || ! in_array( $period_month, array( 4, 9 ), true ) || ! self::table_exists() ) {
			return null;
		}

		return self::db()->get_row(
			self::db()->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE employee_no = %s AND period_month = %d AND is_active = 1 LIMIT 1',
				$employee_no,
				$period_month
			),
			ARRAY_A
		);
	}

	public static function get_active_map( $employee_numbers, $period_month ) {
		$period_month = absint( $period_month );
		$employee_numbers = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $employee_no ) {
							return strtoupper( trim( sanitize_text_field( (string) $employee_no ) ) );
						},
						(array) $employee_numbers
					)
				)
			)
		);
		if ( empty( $employee_numbers ) || ! in_array( $period_month, array( 4, 9 ), true ) || ! self::table_exists() ) {
			return array();
		}

		$map = array();
		foreach ( array_chunk( $employee_numbers, 500 ) as $batch ) {
			$placeholders = implode( ',', array_fill( 0, count( $batch ), '%s' ) );
			$params       = array_merge( array( $period_month ), $batch );
			$rows         = self::db()->get_results(
				self::db()->prepare(
					'SELECT employee_no, special_work_type FROM ' . self::table()
					. " WHERE period_month = %d AND is_active = 1 AND employee_no IN ($placeholders)",
					$params
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$map[ strtoupper( trim( (string) $row['employee_no'] ) ) ] = (string) $row['special_work_type'];
			}
		}

		return $map;
	}

	public static function get_all( $limit = 500 ) {
		if ( ! self::table_exists() ) {
			return array();
		}

		$limit = max( 1, min( 5000, absint( $limit ) ) );
		$table = self::table();
		$organization_table = UMS_DB_Organization::table();
		return self::db()->get_results(
			self::db()->prepare(
				"SELECT assignment.*, organization.full_name, organization.department, organization.cost_center
				FROM $table assignment
				LEFT JOIN $organization_table organization
					ON organization.employee_no COLLATE utf8mb4_unicode_ci = assignment.employee_no COLLATE utf8mb4_unicode_ci
				ORDER BY assignment.employee_no ASC, assignment.period_month ASC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	public static function upsert( $employee_no, $period_month, $special_work_type, $user_id ) {
		$employee_no       = strtoupper( trim( sanitize_text_field( (string) $employee_no ) ) );
		$period_month       = absint( $period_month );
		$special_work_type  = sanitize_text_field( (string) $special_work_type );
		if ( $employee_no === '' || ! in_array( $period_month, array( 4, 9 ), true ) || $special_work_type === '' ) {
			return false;
		}

		$now = current_time( 'mysql' );
		$sql = self::db()->prepare(
			'INSERT INTO ' . self::table() . '
			(employee_no, period_month, special_work_type, is_active, updated_by, created_at, updated_at)
			VALUES (%s, %d, %s, 1, %d, %s, %s)
			ON DUPLICATE KEY UPDATE special_work_type = VALUES(special_work_type), is_active = 1,
			updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
			$employee_no,
			$period_month,
			$special_work_type,
			absint( $user_id ),
			$now,
			$now
		);
		return false !== self::db()->query( $sql );
	}

	public static function delete( $assignment_id ) {
		if ( ! self::table_exists() ) {
			return false;
		}
		return self::db()->delete( self::table(), array( 'assignment_id' => absint( $assignment_id ) ), array( '%d' ) );
	}

	public static function delete_by_period( $period_month ) {
		$period_month = absint( $period_month );
		if ( ! self::table_exists() || ! in_array( $period_month, array( 4, 9 ), true ) ) {
			return false;
		}
		return self::db()->delete( self::table(), array( 'period_month' => $period_month ), array( '%d' ) );
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}
}
