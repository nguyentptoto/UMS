<?php
/**
 * Lớp chuyên trách xử lý dữ liệu luồng duyệt theo phòng ban.
 */
class UMS_DB_Approval_Flow extends UMS_DB_Base {

    public static function table() {
        return self::prefix() . 'uniform_department_approval_flows';
    }

    public static function get_all( $args = array() ) {
        $table            = self::table();
        $department_table = UMS_DB_Department::table();

        $defaults = array(
            'department_id' => '',
            'status' => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['department_id'] !== '' ) {
            $where[]  = 'flow.department_id = %d';
            $params[] = absint( $args['department_id'] );
        }

        if ( $args['status'] === 'active' ) {
            $where[] = 'flow.is_active = 1';
        } elseif ( $args['status'] === 'inactive' ) {
            $where[] = 'flow.is_active = 0';
        }

        $sql = "SELECT flow.*, department.department_name
            FROM $table flow
            LEFT JOIN $department_table department ON department.department_id = flow.department_id
            WHERE " . implode( ' AND ', $where ) . '
            ORDER BY department.department_name ASC, flow.step_order ASC';

        if ( ! empty( $params ) ) {
            $sql = self::db()->prepare( $sql, $params );
        }

        return self::db()->get_results( $sql, ARRAY_A );
    }

    public static function get_by_id( $flow_id ) {
        $table = self::table();
        $sql   = self::db()->prepare( "SELECT * FROM $table WHERE flow_id = %d", absint( $flow_id ) );
        return self::db()->get_row( $sql, ARRAY_A );
    }

    public static function step_order_exists( $department_id, $step_order, $exclude_flow_id = 0 ) {
        $table = self::table();
        $sql   = self::db()->prepare(
            "SELECT COUNT(*) FROM $table WHERE department_id = %d AND step_order = %d AND flow_id <> %d",
            absint( $department_id ),
            absint( $step_order ),
            absint( $exclude_flow_id )
        );

        return (int) self::db()->get_var( $sql ) > 0;
    }

    public static function insert( $data ) {
        return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
    }

    public static function update( $flow_id, $data ) {
        return self::db()->update(
            self::table(),
            $data,
            array( 'flow_id' => absint( $flow_id ) ),
            self::formats_for( $data ),
            array( '%d' )
        );
    }

    public static function delete( $flow_id ) {
        return self::db()->delete( self::table(), array( 'flow_id' => absint( $flow_id ) ), array( '%d' ) );
    }

    public static function get_last_error() {
        return self::db()->last_error;
    }

    private static function format_map() {
        return array(
            'flow_id'             => '%d',
            'department_id'       => '%d',
            'step_order'          => '%d',
            'step_name'           => '%s',
            'approver_profile_ids'=> '%s',
            'is_active'           => '%d',
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
