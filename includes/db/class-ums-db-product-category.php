<?php
/**
 * Lớp chuyên trách xử lý dữ liệu danh mục sản phẩm cha-con.
 */
class UMS_DB_Product_Category extends UMS_DB_Base {

    public static function table() {
        return self::prefix() . 'uniform_product_categories';
    }

    public static function get_all( $args = array() ) {
        $table = self::table();

        $defaults = array(
            'search'    => '',
            'parent_id' => '',
            'status'    => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['search'] !== '' ) {
            $like    = '%' . self::db()->esc_like( $args['search'] ) . '%';
            $where[] = 'category_name LIKE %s';
            $params[] = $like;
        }

        if ( $args['parent_id'] !== '' ) {
            if ( (int) $args['parent_id'] === 0 ) {
                $where[] = 'parent_id = 0';
            } else {
                $where[]  = 'parent_id = %d';
                $params[] = absint( $args['parent_id'] );
            }
        }

        if ( $args['status'] === 'active' ) {
            $where[] = 'is_active = 1';
        } elseif ( $args['status'] === 'inactive' ) {
            $where[] = 'is_active = 0';
        }

        $sql = "SELECT * FROM $table WHERE " . implode( ' AND ', $where ) . ' ORDER BY parent_id IS NOT NULL ASC, parent_id ASC, category_name ASC';

        if ( ! empty( $params ) ) {
            $sql = self::db()->prepare( $sql, $params );
        }

        return self::db()->get_results( $sql, ARRAY_A );
    }

    public static function get_tree() {
        $items = self::get_all();
        $tree  = array();

        foreach ( $items as $item ) {
            if ( (int) $item['parent_id'] === 0 ) {
                $item['children'] = array();
                $tree[ $item['category_id'] ] = $item;
            }
        }

        foreach ( $items as $item ) {
            if ( (int) $item['parent_id'] > 0 && isset( $tree[ $item['parent_id'] ] ) ) {
                $tree[ $item['parent_id'] ]['children'][] = $item;
            }
        }

        return $tree;
    }

    public static function get_parent_categories() {
        return self::get_all( array( 'parent_id' => 0, 'status' => 'active' ) );
    }

    public static function get_child_categories() {
        $table = self::table();
        $sql   = "SELECT child.*, parent.category_name AS parent_name
            FROM $table child
            LEFT JOIN $table parent ON parent.category_id = child.parent_id
            WHERE child.parent_id > 0 AND child.is_active = 1
            ORDER BY parent.category_name ASC, child.category_name ASC";

        return self::db()->get_results( $sql, ARRAY_A );
    }

    public static function get_by_id( $category_id ) {
        $table = self::table();
        $sql   = self::db()->prepare( "SELECT * FROM $table WHERE category_id = %d", absint( $category_id ) );
        return self::db()->get_row( $sql, ARRAY_A );
    }

    public static function has_children( $category_id ) {
        $table = self::table();
        $sql   = self::db()->prepare( "SELECT COUNT(*) FROM $table WHERE parent_id = %d", absint( $category_id ) );
        return (int) self::db()->get_var( $sql ) > 0;
    }

    public static function insert( $data ) {
        return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
    }

    public static function update( $category_id, $data ) {
        return self::db()->update(
            self::table(),
            $data,
            array( 'category_id' => absint( $category_id ) ),
            self::formats_for( $data ),
            array( '%d' )
        );
    }

    public static function delete( $category_id ) {
        return self::db()->delete( self::table(), array( 'category_id' => absint( $category_id ) ), array( '%d' ) );
    }

    public static function get_last_error() {
        return self::db()->last_error;
    }

    private static function format_map() {
        return array(
            'category_id'   => '%d',
            'parent_id'     => '%d',
            'category_name' => '%s',
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
