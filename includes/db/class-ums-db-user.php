<?php
/**
 * Lớp chuyên trách xử lý dữ liệu hồ sơ nhân sự
 */
class UMS_DB_User extends UMS_DB_Base {

    const GENDERS = array( 'Nam', 'Nữ' );
    const FACTORY_LOCATIONS = array( 'Đông Anh', 'Hưng Yên', 'Vĩnh Phúc' );
    const CONTRACT_TYPES = array( 'Thử việc', 'Tập nghề', 'Tập nghề biên phiên dịch', 'Thuê lại lao động', 'Hợp đồng lao động' );

    /**
     * Tên bảng thực tế trong MySQL
     */
    public static function table() {
        return self::prefix() . 'uniform_user_profiles';
    }

    /**
     * Lấy toàn bộ danh sách nhân viên
     */
    public static function get_all( $args = array() ) {
        $table = self::table();

        $defaults = array(
            'search'     => '',
            'department' => '',
            'status'     => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['search'] !== '' ) {
            $like    = '%' . self::db()->esc_like( $args['search'] ) . '%';
            $where[] = '(employee_code LIKE %s OR full_name LIKE %s OR job_position LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $args['department'] !== '' ) {
            $where[]  = 'department = %s';
            $params[] = $args['department'];
        }

        if ( $args['status'] === 'active' ) {
            $where[] = 'resignation_date IS NULL';
        } elseif ( $args['status'] === 'resigned' ) {
            $where[] = 'resignation_date IS NOT NULL';
        }

        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY date_joined DESC, full_name ASC';

        if ( ! empty( $params ) ) {
            $sql = self::db()->prepare( $sql, $params );
        }

        return self::db()->get_results( $sql, ARRAY_A );
    }

    /**
     * Lấy chi tiết một hồ sơ nhân sự theo mã hồ sơ.
     */
    public static function get_by_id( $profile_id ) {
        $table = self::table();
        $sql   = self::db()->prepare( "SELECT * FROM $table WHERE profile_id = %d", absint( $profile_id ) );
        return self::db()->get_row( $sql, ARRAY_A );
    }

    /**
     * Lấy danh sách nhân viên theo phòng ban (Phục vụ bộ lọc Tạo Phiếu Hộ)
     */
    public static function get_by_department( $department ) {
        $table = self::table();
        $sql   = self::db()->prepare( "SELECT * FROM $table WHERE department = %s AND resignation_date IS NULL", $department );
        return self::db()->get_results( $sql, ARRAY_A );
    }

    /**
     * Lấy danh sách phòng ban đang có trong hồ sơ để làm bộ lọc.
     */
    public static function get_departments() {
        $table = self::table();
        $sql   = "SELECT DISTINCT department FROM $table WHERE department <> '' ORDER BY department ASC";
        return self::db()->get_col( $sql );
    }

    /**
     * Kiểm tra mã nhân viên đã tồn tại ở hồ sơ khác hay chưa.
     */
    public static function employee_code_exists( $employee_code, $exclude_profile_id = 0 ) {
        $table = self::table();
        $sql   = self::db()->prepare(
            "SELECT COUNT(*) FROM $table WHERE employee_code = %s AND profile_id <> %d",
            $employee_code,
            absint( $exclude_profile_id )
        );

        return (int) self::db()->get_var( $sql ) > 0;
    }

    /**
     * Thêm mới một hồ sơ nhân viên
     */
    public static function insert( $data ) {
        return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
    }

    /**
     * Cập nhật thông tin/cờ trạng thái nhân viên
     */
    public static function update( $profile_id, $data ) {
        return self::db()->update( 
            self::table(), 
            $data, 
            array( 'profile_id' => absint( $profile_id ) ),
            self::formats_for( $data ),
            array( '%d' )
        );
    }

    /**
     * Xóa hồ sơ nhân viên khỏi bảng quản lý
     */
    public static function delete( $profile_id ) {
        return self::db()->delete( self::table(), array( 'profile_id' => absint( $profile_id ) ), array( '%d' ) );
    }

    /**
     * Lấy lỗi DB gần nhất để hiển thị thông báo quản trị.
     */
    public static function get_last_error() {
        return self::db()->last_error;
    }

    /**
     * Định dạng dữ liệu khi ghi DB, khớp với schema ums.sql.
     */
    private static function format_map() {
        return array(
            'profile_id'        => '%d',
            'user_id'           => '%d',
            'employee_code'     => '%s',
            'full_name'         => '%s',
            'gender'            => '%s',
            'factory_location'  => '%s',
            'department'        => '%s',
            'job_position'      => '%s',
            'contract_type'     => '%s',
            'date_joined'       => '%s',
            'resignation_date'  => '%s',
            'transfer_date'     => '%s',
            'is_maternity'      => '%d',
            'is_outdoor_worker' => '%d',
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
