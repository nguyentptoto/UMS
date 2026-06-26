<?php
/**
 * Lớp chuyên trách xử lý dữ liệu danh mục chức danh.
 */
class UMS_DB_Position extends UMS_DB_Base {

	/**
	 * Tên bảng thực tế trong MySQL.
	 */
	public static function table() {
		return self::prefix() . 'uniform_positions';
	}

	/**
	 * Lấy danh sách chức danh.
	 */
	public static function get_all( $args = array() ) {
		$table = self::table();

		$defaults = array(
			'search' => '',
			'status' => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like     = '%' . self::db()->esc_like( $args['search'] ) . '%';
			$where[]  = '(position_code LIKE %s OR position_name LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		if ( $args['status'] === 'active' ) {
			$where[] = 'is_active = 1';
		} elseif ( $args['status'] === 'inactive' ) {
			$where[] = 'is_active = 0';
		}

		$sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY is_active DESC, position_name ASC';

		if ( ! empty( $params ) ) {
			$sql = self::db()->prepare( $sql, $params );
		}

		return self::db()->get_results( $sql, ARRAY_A );
	}

	/**
	 * Lấy danh sách chức danh đang hoạt động.
	 */
	public static function get_active() {
		return self::get_all( array( 'status' => 'active' ) );
	}

	/**
	 * Lấy chi tiết chức danh.
	 */
	public static function get_by_id( $position_id ) {
		$table = self::table();
		$sql   = self::db()->prepare( "SELECT * FROM $table WHERE position_id = %d", absint( $position_id ) );
		return self::db()->get_row( $sql, ARRAY_A );
	}

	/**
	 * Kiểm tra mã chức danh đã tồn tại ở bản ghi khác hay chưa.
	 */
	public static function code_exists( $position_code, $exclude_position_id = 0 ) {
		$table = self::table();
		$sql   = self::db()->prepare(
			"SELECT COUNT(*) FROM $table WHERE position_code = %s AND position_id <> %d",
			$position_code,
			absint( $exclude_position_id )
		);

		return (int) self::db()->get_var( $sql ) > 0;
	}

	/**
	 * Thêm chức danh.
	 */
	public static function insert( $data ) {
		return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
	}

	/**
	 * Cập nhật chức danh.
	 */
	public static function update( $position_id, $data ) {
		return self::db()->update(
			self::table(),
			$data,
			array( 'position_id' => absint( $position_id ) ),
			self::formats_for( $data ),
			array( '%d' )
		);
	}

	/**
	 * Xóa chức danh.
	 */
	public static function delete( $position_id ) {
		return self::db()->delete( self::table(), array( 'position_id' => absint( $position_id ) ), array( '%d' ) );
	}

	/**
	 * Lấy lỗi DB gần nhất.
	 */
	public static function get_last_error() {
		return self::db()->last_error;
	}

	private static function format_map() {
		return array(
			'position_id'   => '%d',
			'position_code' => '%s',
			'position_name' => '%s',
			'is_active'     => '%d',
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
