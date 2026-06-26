<?php
/**
 * Lớp chuyên trách xử lý dữ liệu danh mục loại hợp đồng.
 */
class UMS_DB_Contract_Type extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_contract_types';
	}

	public static function get_all( $args = array() ) {
		$table = self::table();
		$args  = wp_parse_args(
			$args,
			array(
				'search' => '',
				'status' => '',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like     = '%' . self::db()->esc_like( $args['search'] ) . '%';
			$where[]  = '(contract_type_code LIKE %s OR contract_type_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( $args['status'] === 'active' ) {
			$where[] = 'is_active = 1';
		} elseif ( $args['status'] === 'inactive' ) {
			$where[] = 'is_active = 0';
		}

		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY is_active DESC, contract_type_name ASC';

		if ( ! empty( $params ) ) {
			$sql = self::db()->prepare( $sql, $params );
		}

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_active() {
		return self::get_all( array( 'status' => 'active' ) );
	}

	public static function get_by_id( $contract_type_id ) {
		$table = self::table();
		$sql   = self::db()->prepare( "SELECT * FROM $table WHERE contract_type_id = %d", absint( $contract_type_id ) );
		return self::db()->get_row( $sql, ARRAY_A );
	}

	public static function code_exists( $contract_type_code, $exclude_contract_type_id = 0 ) {
		$table = self::table();
		$sql   = self::db()->prepare(
			"SELECT COUNT(*) FROM $table WHERE contract_type_code = %s AND contract_type_id <> %d",
			$contract_type_code,
			absint( $exclude_contract_type_id )
		);

		return (int) self::db()->get_var( $sql ) > 0;
	}

	public static function insert( $data ) {
		return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
	}

	public static function update( $contract_type_id, $data ) {
		return self::db()->update(
			self::table(),
			$data,
			array( 'contract_type_id' => absint( $contract_type_id ) ),
			self::formats_for( $data ),
			array( '%d' )
		);
	}

	public static function delete( $contract_type_id ) {
		return self::db()->delete( self::table(), array( 'contract_type_id' => absint( $contract_type_id ) ), array( '%d' ) );
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}

	private static function format_map() {
		return array(
			'contract_type_id'   => '%d',
			'contract_type_code' => '%s',
			'contract_type_name' => '%s',
			'is_active'          => '%d',
		);
	}

	private static function formats_for( $data ) {
		$format_map = self::format_map();
		$formats    = array();

		foreach ( array_keys( $data ) as $field ) {
			$formats[] = isset( $format_map[ $field ] ) ? $format_map[ $field ] : '%s';
		}

		return $formats;
	}
}
