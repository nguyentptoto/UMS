<?php
/**
 * Lớp chuyên trách xử lý dữ liệu danh mục sản phẩm và tổng kho.
 */
class UMS_DB_Inventory extends UMS_DB_Base {

    /**
     * Tên bảng thực tế trong MySQL.
     */
    public static function table() {
        return self::prefix() . 'uniform_inventory';
    }

    /**
     * Lấy danh sách sản phẩm/tồn kho.
     */
    public static function get_all( $args = array() ) {
        $table = self::table();

        $defaults = array(
            'search'      => '',
            'category_id' => '',
            'parent_id'   => '',
            'stock'       => '',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['search'] !== '' ) {
            $like    = '%' . self::db()->esc_like( $args['search'] ) . '%';
            $where[] = '(inventory.item_type LIKE %s OR inventory.item_variant LIKE %s OR inventory.size LIKE %s OR inventory.color_code LIKE %s OR child.category_name LIKE %s OR parent.category_name LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ( $args['category_id'] !== '' ) {
            $where[]  = 'inventory.category_id = %d';
            $params[] = absint( $args['category_id'] );
        }

        if ( $args['parent_id'] !== '' ) {
            $where[]  = 'child.parent_id = %d';
            $params[] = absint( $args['parent_id'] );
        }

        if ( $args['stock'] === 'available' ) {
            $where[] = 'inventory.stock_qty > 0';
        } elseif ( $args['stock'] === 'out' ) {
            $where[] = 'inventory.stock_qty <= 0';
        } elseif ( $args['stock'] === 'low' ) {
            $where[] = 'inventory.stock_qty > 0 AND inventory.stock_qty <= 10';
        }

        $category_table = UMS_DB_Product_Category::table();
        $sql = "SELECT inventory.*, child.category_name AS category_name, parent.category_name AS parent_category_name
            FROM $table inventory
            LEFT JOIN $category_table child ON child.category_id = inventory.category_id
            LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
            WHERE " . implode( ' AND ', $where ) . '
            ORDER BY parent.category_name ASC, child.category_name ASC, inventory.item_variant ASC, inventory.size ASC';

        if ( ! empty( $params ) ) {
            $sql = self::db()->prepare( $sql, $params );
        }

        return self::db()->get_results( $sql, ARRAY_A );
    }

    /**
     * Lấy danh sách loại sản phẩm đang có.
     */
    public static function get_item_types() {
        $table = self::table();
        $sql   = "SELECT DISTINCT item_type FROM $table WHERE item_type <> '' ORDER BY item_type ASC";
        return self::db()->get_col( $sql );
    }

    /**
     * Lấy chi tiết một dòng tồn kho.
     */
    public static function get_by_id( $item_id ) {
        $table = self::table();
        $category_table = UMS_DB_Product_Category::table();
        $sql   = self::db()->prepare(
            "SELECT inventory.*, child.category_name AS category_name, parent.category_name AS parent_category_name
            FROM $table inventory
            LEFT JOIN $category_table child ON child.category_id = inventory.category_id
            LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
            WHERE inventory.item_id = %d",
            absint( $item_id )
        );
        return self::db()->get_row( $sql, ARRAY_A );
    }

    /**
     * Kiểm tra một biến thể sản phẩm đã tồn tại chưa.
     */
    public static function variant_exists( $data, $exclude_item_id = 0 ) {
        $table = self::table();
        $sql   = self::db()->prepare(
            "SELECT COUNT(*) FROM $table WHERE category_id = %d AND item_variant = %s AND size = %s AND color_code = %s AND item_id <> %d",
            $data['category_id'],
            $data['item_variant'],
            $data['size'],
            $data['color_code'],
            absint( $exclude_item_id )
        );

        return (int) self::db()->get_var( $sql ) > 0;
    }

    /**
     * Thêm sản phẩm/tồn kho.
     */
    public static function insert( $data ) {
        return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
    }

    /**
     * Cập nhật sản phẩm/tồn kho.
     */
    public static function update( $item_id, $data ) {
        return self::db()->update(
            self::table(),
            $data,
            array( 'item_id' => absint( $item_id ) ),
            self::formats_for( $data ),
            array( '%d' )
        );
    }

    /**
     * Xóa sản phẩm/tồn kho.
     */
    public static function delete( $item_id ) {
        return self::db()->delete( self::table(), array( 'item_id' => absint( $item_id ) ), array( '%d' ) );
    }

    public static function category_has_items( $category_id ) {
        $table = self::table();
        $sql   = self::db()->prepare( "SELECT COUNT(*) FROM $table WHERE category_id = %d", absint( $category_id ) );
        return (int) self::db()->get_var( $sql ) > 0;
    }

    /**
     * Lấy lỗi DB gần nhất.
     */
    public static function get_last_error() {
        return self::db()->last_error;
    }

    private static function format_map() {
        return array(
            'item_id'      => '%d',
            'category_id'  => '%d',
            'item_type'    => '%s',
            'item_variant' => '%s',
            'size'         => '%s',
            'color_code'   => '%s',
            'stock_qty'    => '%d',
            'base_price'   => '%f',
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
