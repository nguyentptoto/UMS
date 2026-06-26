<?php
/**
 * Data layer for inventory movement history.
 */
class UMS_DB_Inventory_Movement extends UMS_DB_Base {

    public static function table() {
        return self::prefix() . 'uniform_inventory_movements';
    }

    public static function insert( $data ) {
        $defaults = array(
            'item_id'        => 0,
            'request_id'     => null,
            'movement_type'  => 'adjust',
            'quantity'       => 0,
            'before_qty'     => null,
            'after_qty'      => null,
            'unit_price'     => 0,
            'total_price'    => 0,
            'actor_user_id'  => null,
            'target_user_id' => null,
            'note'           => '',
        );
        $data = wp_parse_args( $data, $defaults );

        return self::db()->insert(
            self::table(),
            $data,
            array( '%d', '%d', '%s', '%d', '%d', '%d', '%f', '%f', '%d', '%d', '%s' )
        );
    }

    public static function get_all( $args = array() ) {
        $table          = self::table();
        $inventory      = UMS_DB_Inventory::table();
        $category_table = UMS_DB_Product_Category::table();
        $users_table    = self::db()->users;

        $defaults = array(
            'search'        => '',
            'movement_type' => '',
            'date_from'     => '',
            'date_to'       => '',
            'limit'         => 300,
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['movement_type'] !== '' ) {
            $where[]  = 'movement.movement_type = %s';
            $params[] = sanitize_key( $args['movement_type'] );
        }

        if ( $args['date_from'] !== '' ) {
            $where[]  = 'movement.created_at >= %s';
            $params[] = sanitize_text_field( $args['date_from'] ) . ' 00:00:00';
        }

        if ( $args['date_to'] !== '' ) {
            $where[]  = 'movement.created_at <= %s';
            $params[] = sanitize_text_field( $args['date_to'] ) . ' 23:59:59';
        }

        if ( $args['search'] !== '' ) {
            $like    = '%' . self::db()->esc_like( $args['search'] ) . '%';
            $where[] = '(inventory.item_variant LIKE %s OR inventory.size LIKE %s OR child.category_name LIKE %s OR parent.category_name LIKE %s OR actor.user_login LIKE %s OR target.user_login LIKE %s OR movement.note LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $limit = max( 1, min( 1000, absint( $args['limit'] ) ) );
        $sql = "SELECT movement.*, inventory.item_variant, inventory.size, child.category_name, parent.category_name AS parent_category_name,
                actor.user_login AS actor_login, target.user_login AS target_login
            FROM $table movement
            LEFT JOIN $inventory inventory ON inventory.item_id = movement.item_id
            LEFT JOIN $category_table child ON child.category_id = inventory.category_id
            LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
            LEFT JOIN $users_table actor ON actor.ID = movement.actor_user_id
            LEFT JOIN $users_table target ON target.ID = movement.target_user_id
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY movement.created_at DESC, movement.movement_id DESC
            LIMIT %d";
        $params[] = $limit;

        return self::db()->get_results( self::db()->prepare( $sql, $params ), ARRAY_A );
    }
}
