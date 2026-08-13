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
        $sql = "SELECT movement.*, inventory.item_variant, inventory.size, child.category_name,
                parent.category_id AS parent_category_id, parent.category_name AS parent_category_name,
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

    public static function get_manual_allowance_usage( $target_user_id, $rule, $start_date, $end_date ) {
        $table          = self::table();
        $inventory      = UMS_DB_Inventory::table();
        $category_table = UMS_DB_Product_Category::table();

        $where  = array(
            'movement.target_user_id = %d',
            "movement.movement_type = 'out'",
            'movement.request_id IS NULL',
            'movement.created_at >= %s',
            'movement.created_at <= %s',
        );
        $params = array(
            absint( $target_user_id ),
            sanitize_text_field( $start_date ),
            sanitize_text_field( $end_date ),
        );

        if ( isset( $rule['apply_type'] ) && $rule['apply_type'] === 'category' ) {
            $where[]  = '(inventory.category_id = %d OR category.parent_id = %d)';
            $params[] = absint( $rule['category_id'] );
            $params[] = absint( $rule['category_id'] );
        } elseif ( isset( $rule['apply_type'] ) && $rule['apply_type'] === 'product' ) {
            $where[]  = 'inventory.category_id = %d AND inventory.item_variant = %s';
            $params[] = absint( $rule['category_id'] );
            $params[] = sanitize_text_field( $rule['item_variant'] );
        } else {
            $where[]  = 'movement.item_id = %d';
            $params[] = absint( $rule['item_id'] );
        }

        $sql = "
            SELECT COUNT(*) AS request_count, COALESCE(SUM(movement.quantity), 0) AS quantity
            FROM $table movement
            INNER JOIN $inventory inventory ON inventory.item_id = movement.item_id
            LEFT JOIN $category_table category ON category.category_id = inventory.category_id
            WHERE " . implode( ' AND ', $where );

        $row = self::db()->get_row( self::db()->prepare( $sql, $params ), ARRAY_A );

        return array(
            'request_count' => isset( $row['request_count'] ) ? absint( $row['request_count'] ) : 0,
            'quantity'      => isset( $row['quantity'] ) ? absint( $row['quantity'] ) : 0,
        );
    }

    public static function get_last_error() {
        return self::db()->last_error;
    }
}
