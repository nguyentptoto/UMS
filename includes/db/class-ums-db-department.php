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
            'group'  => '',
            'status' => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['search'] !== '' ) {
            $like    = '%' . self::db()->esc_like( $args['search'] ) . '%';
            $where[] = '(department_code LIKE %s OR department_name LIKE %s OR department_group LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $args['status'] === 'active' ) {
            $where[] = 'is_active = 1';
        } elseif ( $args['status'] === 'inactive' ) {
            $where[] = 'is_active = 0';
        }

        if ( $args['group'] !== '' ) {
            $where[]  = 'department_group = %s';
            $params[] = sanitize_text_field( $args['group'] );
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
        static $active_departments = null;

        if ( $active_departments === null ) {
            $active_departments = self::get_all( array( 'status' => 'active' ) );
        }

        return $active_departments;
    }

	/**
	 * Ensure organization departments have stable ids for approval-flow storage.
	 */
	public static function get_active_from_organization() {
		if ( ! UMS_DB_Organization::table_exists() ) {
			return self::get_active();
		}

		$organization_departments = UMS_DB_Organization::get_distinct_values( 'department' );
		$existing = self::get_all();
		$by_name  = array();
		foreach ( $existing as $department ) {
			$key = self::normalize_name( $department['department_name'] );
			if ( $key !== '' ) {
				$by_name[ $key ] = $department;
			}
		}

		foreach ( $organization_departments as $department_name ) {
			$department_name = trim( sanitize_text_field( (string) $department_name ) );
			$key = self::normalize_name( $department_name );
			if ( $key === '' || isset( $by_name[ $key ] ) ) {
				continue;
			}
			$department_code = 'org-' . substr( md5( $key ), 0, 16 );
			$inserted = self::insert(
				array(
					'department_code' => $department_code,
					'department_name' => $department_name,
					'department_group' => 'Sơ đồ tổ chức TVN',
					'is_active' => 1,
				)
			);
			if ( false !== $inserted ) {
				$by_name[ $key ] = self::get_by_code( $department_code );
			}
		}

		$result = array();
		foreach ( $organization_departments as $department_name ) {
			$key = self::normalize_name( $department_name );
			if ( isset( $by_name[ $key ] ) && (int) $by_name[ $key ]['is_active'] === 1 ) {
				$result[] = $by_name[ $key ];
			}
		}
		usort( $result, function ( $left, $right ) {
			return strnatcasecmp( $left['department_name'], $right['department_name'] );
		} );
		return $result;
	}

	private static function normalize_name( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		$value = remove_accents( $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

    /**
     * Lấy danh sách nhóm phòng ban để dùng cho bộ lọc và gợi ý nhập liệu.
     */
    public static function get_groups() {
        $table = self::table();
        return self::db()->get_col( "SELECT DISTINCT department_group FROM $table WHERE department_group <> '' ORDER BY department_group ASC" );
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
     * Lấy phòng ban theo mã duy nhất.
     */
    public static function get_by_code( $department_code ) {
        $table = self::table();
        $sql   = self::db()->prepare(
            "SELECT * FROM $table WHERE department_code = %s LIMIT 1",
            sanitize_key( $department_code )
        );

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
            'department_group'     => '%s',
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
