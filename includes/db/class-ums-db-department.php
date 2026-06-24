<?php
/**
 * Lớp chuyên trách xử lý dữ liệu danh mục phòng ban.
 */
class UMS_DB_Department extends UMS_DB_Base {

    /**
     * Tên bảng thực tế trong MySQL.
     */
    public static function table() {
        return self::prefix() . 'uniform_departments';
    }

    /**
     * Lấy danh sách phòng ban.
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
            $like    = '%' . self::db()->esc_like( $args['search'] ) . '%';
            $where[] = '(department_code LIKE %s OR department_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        if ( $args['status'] === 'active' ) {
            $where[] = 'is_active = 1';
        } elseif ( $args['status'] === 'inactive' ) {
            $where[] = 'is_active = 0';
        }

        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY is_active DESC, department_name ASC';

        if ( ! empty( $params ) ) {
            $sql = self::db()->prepare( $sql, $params );
        }

        return self::db()->get_results( $sql, ARRAY_A );
    }

    /**
     * Lấy danh sách phòng ban đang hoạt động.
     */
    public static function get_active() {
        return self::get_all( array( 'status' => 'active' ) );
    }

    /**
     * Lấy chi tiết phòng ban.
     */
    public static function get_by_id( $department_id ) {
        $table = self::table();
        $sql   = self::db()->prepare( "SELECT * FROM $table WHERE department_id = %d", absint( $department_id ) );
        return self::db()->get_row( $sql, ARRAY_A );
    }

    /**
     * Kiểm tra mã phòng ban đã tồn tại ở bản ghi khác hay chưa.
     */
    public static function code_exists( $department_code, $exclude_department_id = 0 ) {
        $table = self::table();
        $sql   = self::db()->prepare(
            "SELECT COUNT(*) FROM $table WHERE department_code = %s AND department_id <> %d",
            $department_code,
            absint( $exclude_department_id )
        );

        return (int) self::db()->get_var( $sql ) > 0;
    }

    /**
     * Thêm phòng ban.
     */
    public static function insert( $data ) {
        return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
    }

    /**
     * Cập nhật phòng ban.
     */
    public static function update( $department_id, $data ) {
        return self::db()->update(
            self::table(),
            $data,
            array( 'department_id' => absint( $department_id ) ),
            self::formats_for( $data ),
            array( '%d' )
        );
    }

    /**
     * Xóa phòng ban.
     */
    public static function delete( $department_id ) {
        return self::db()->delete( self::table(), array( 'department_id' => absint( $department_id ) ), array( '%d' ) );
    }

    /**
     * Lấy lỗi DB gần nhất.
     */
    public static function get_last_error() {
        return self::db()->last_error;
    }

    private static function format_map() {
        return array(
            'department_id'        => '%d',
            'department_code'      => '%s',
            'department_name'      => '%s',
            'is_active'            => '%d',
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
