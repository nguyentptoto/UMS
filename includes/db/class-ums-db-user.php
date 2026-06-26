<?php
/**
 * Lớp chuyên trách xử lý dữ liệu hồ sơ nhân sự
 */
class UMS_DB_User extends UMS_DB_Base {

    const GENDERS = array( 'Nam', 'Nữ' );

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
        $table       = self::table();
        $users_table = self::db()->users;

        $defaults = array(
            'search'           => '',
            'department'       => '',
            'position'         => '',
            'factory_location' => '',
            'contract_type'    => '',
            'status'           => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['search'] !== '' ) {
            $like    = '%' . self::db()->esc_like( $args['search'] ) . '%';
            $where[] = '(profiles.employee_code LIKE %s OR profiles.full_name LIKE %s OR profiles.job_position LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $args['department'] !== '' ) {
            $where[]  = 'profiles.department = %s';
            $params[] = $args['department'];
        }

        if ( $args['position'] !== '' ) {
            $where[]  = 'profiles.job_position = %s';
            $params[] = $args['position'];
        }

        if ( $args['factory_location'] !== '' ) {
            $where[]  = 'profiles.factory_location = %s';
            $params[] = $args['factory_location'];
        }

        if ( $args['contract_type'] !== '' ) {
            $where[]  = 'profiles.contract_type = %s';
            $params[] = $args['contract_type'];
        }

        if ( $args['status'] === 'active' ) {
            $where[] = 'profiles.resignation_date IS NULL';
        } elseif ( $args['status'] === 'resigned' ) {
            $where[] = 'profiles.resignation_date IS NOT NULL';
        }

        $sql = "
            SELECT profiles.*, wp_users.user_login, wp_users.user_status
            FROM $table profiles
            LEFT JOIN $users_table wp_users ON profiles.user_id = wp_users.ID
            WHERE " . implode( ' AND ', $where ) . '
            ORDER BY profiles.date_joined DESC, profiles.full_name ASC';

        if ( ! empty( $params ) ) {
            $sql = self::db()->prepare( $sql, $params );
        }

        return self::db()->get_results( $sql, ARRAY_A );
    }

    /**
     * Lấy chi tiết một hồ sơ nhân sự theo mã hồ sơ.
     */
    public static function get_by_id( $profile_id ) {
        $table       = self::table();
        $users_table = self::db()->users;
        $sql         = self::db()->prepare(
            "
            SELECT profiles.*, wp_users.user_login, wp_users.user_status
            FROM $table profiles
            LEFT JOIN $users_table wp_users ON profiles.user_id = wp_users.ID
            WHERE profiles.profile_id = %d
            ",
            absint( $profile_id )
        );
        return self::db()->get_row( $sql, ARRAY_A );
    }

    /**
     * Lấy hồ sơ nhân sự theo tài khoản WordPress đang đăng nhập.
     */
    public static function get_by_wp_user_id( $user_id ) {
        $table       = self::table();
        $users_table = self::db()->users;
        $sql         = self::db()->prepare(
            "
            SELECT profiles.*, wp_users.user_login, wp_users.user_status
            FROM $table profiles
            LEFT JOIN $users_table wp_users ON profiles.user_id = wp_users.ID
            WHERE profiles.user_id = %d
            ",
            absint( $user_id )
        );

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

    /**
     * Render các trường UMS mở rộng trong màn hình User Profile chuẩn của WordPress.
     *
     * Dữ liệu select không hardcode tại HTML mà lấy từ helper dynamic catalog.
     *
     * @param WP_User $user User đang được xem/sửa.
     */
    public static function render_custom_user_fields( $user ) {
        if ( ! $user instanceof WP_User ) {
            return;
        }

        $profile      = self::get_ums_user_profile( $user->ID );
        $departments  = function_exists( 'ums_get_departments' ) ? ums_get_departments() : array();
        $positions    = function_exists( 'ums_get_job_positions' ) ? ums_get_job_positions() : array();
        ?>
        <h2>Thông tin nhân sự UMS</h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="ums_employee_code">Mã nhân viên</label></th>
                <td>
                    <input
                        type="text"
                        name="ums_employee_code"
                        id="ums_employee_code"
                        value="<?php echo esc_attr( $profile->employee_code ); ?>"
                        class="regular-text"
                    >
                </td>
            </tr>

            <tr>
                <th><label for="ums_date_joined">Ngày vào Công ty</label></th>
                <td>
                    <input
                        type="date"
                        name="ums_date_joined"
                        id="ums_date_joined"
                        value="<?php echo esc_attr( $profile->date_joined ); ?>"
                    >
                </td>
            </tr>

            <tr>
                <th><label for="ums_department">Bộ phận</label></th>
                <td>
                    <select name="ums_department" id="ums_department">
                        <option value="">Chọn bộ phận</option>
                        <?php foreach ( $departments as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $profile->department, $key ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label for="ums_job_position">Chức vụ</label></th>
                <td>
                    <select name="ums_job_position" id="ums_job_position">
                        <option value="">Chọn chức vụ</option>
                        <?php foreach ( $positions as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $profile->job_position, $key ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>

            <tr>
                <th>Trạng thái đặc biệt</th>
                <td>
                    <label for="ums_is_maternity">
                        <input
                            type="checkbox"
                            name="ums_is_maternity"
                            id="ums_is_maternity"
                            value="1"
                            <?php checked( $profile->is_maternity, 1 ); ?>
                        >
                        Thai sản
                    </label>
                    <br>

                    <label for="ums_has_resigned">
                        <input
                            type="checkbox"
                            name="ums_has_resigned"
                            id="ums_has_resigned"
                            value="1"
                            <?php checked( $profile->has_resigned, 1 ); ?>
                        >
                        Đã nộp đơn nghỉ việc
                    </label>
                    <br>

                    <label for="ums_is_outdoor_worker">
                        <input
                            type="checkbox"
                            name="ums_is_outdoor_worker"
                            id="ums_is_outdoor_worker"
                            value="1"
                            <?php checked( $profile->is_outdoor_worker, 1 ); ?>
                        >
                        Làm việc ngoài trời
                    </label>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Lưu các trường UMS mở rộng vào wp_usermeta.
     *
     * @param int $user_id ID tài khoản WordPress.
     */
    public static function save_custom_user_fields( $user_id ) {
        $user_id = absint( $user_id );
        if ( $user_id <= 0 || ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }

        $text_fields = array(
            'ums_employee_code',
            'ums_date_joined',
            'ums_department',
            'ums_job_position',
        );

        foreach ( $text_fields as $field ) {
            $value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
            update_user_meta( $user_id, $field, $value );
        }

        $checkbox_fields = array(
            'ums_is_maternity',
            'ums_has_resigned',
            'ums_is_outdoor_worker',
        );

        foreach ( $checkbox_fields as $field ) {
            update_user_meta( $user_id, $field, ! empty( $_POST[ $field ] ) ? 1 : 0 );
        }

        return true;
    }

    /**
     * Lấy nhanh hồ sơ UMS từ wp_usermeta để các module định mức/duyệt dùng chung.
     *
     * @param int $user_id ID tài khoản WordPress.
     * @return stdClass
     */
    public static function get_ums_user_profile( $user_id ) {
        $user_id = absint( $user_id );

        return (object) array(
            'user_id'           => $user_id,
            'employee_code'     => (string) get_user_meta( $user_id, 'ums_employee_code', true ),
            'date_joined'       => (string) get_user_meta( $user_id, 'ums_date_joined', true ),
            'department'        => (string) get_user_meta( $user_id, 'ums_department', true ),
            'job_position'      => (string) get_user_meta( $user_id, 'ums_job_position', true ),
            'is_maternity'      => (int) get_user_meta( $user_id, 'ums_is_maternity', true ),
            'has_resigned'      => (int) get_user_meta( $user_id, 'ums_has_resigned', true ),
            'is_outdoor_worker' => (int) get_user_meta( $user_id, 'ums_is_outdoor_worker', true ),
        );
    }
}

add_action( 'show_user_profile', array( 'UMS_DB_User', 'render_custom_user_fields' ) );
add_action( 'edit_user_profile', array( 'UMS_DB_User', 'render_custom_user_fields' ) );
add_action( 'personal_options_update', array( 'UMS_DB_User', 'save_custom_user_fields' ) );
add_action( 'edit_user_profile_update', array( 'UMS_DB_User', 'save_custom_user_fields' ) );

if ( ! function_exists( 'ums_save_custom_user_fields' ) ) {
    /**
     * Functional wrapper theo yêu cầu module.
     *
     * @param int $user_id ID tài khoản WordPress.
     * @return bool
     */
    function ums_save_custom_user_fields( $user_id ) {
        return UMS_DB_User::save_custom_user_fields( $user_id );
    }
}

if ( ! function_exists( 'get_ums_user_profile' ) ) {
    /**
     * Functional helper tập trung để lấy profile UMS từ wp_usermeta.
     *
     * @param int $user_id ID tài khoản WordPress.
     * @return stdClass
     */
    function get_ums_user_profile( $user_id ) {
        return UMS_DB_User::get_ums_user_profile( $user_id );
    }
}
