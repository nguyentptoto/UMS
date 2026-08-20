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
            'target_employee_no' => '',
            'note'           => '',
        );
        $data = wp_parse_args( $data, $defaults );

        if ( $data['target_employee_no'] === '' && ! empty( $data['target_user_id'] ) ) {
            $organization = UMS_DB_Organization::get_by_wp_user_id( $data['target_user_id'] );
            if ( is_array( $organization ) && ! empty( $organization['employee_no'] ) ) {
                $data['target_employee_no'] = (string) $organization['employee_no'];
            }
        }

		$format_map = array(
			'item_id' => '%d', 'request_id' => '%d', 'movement_type' => '%s', 'quantity' => '%d',
			'before_qty' => '%d', 'after_qty' => '%d', 'unit_price' => '%f', 'total_price' => '%f',
			'actor_user_id' => '%d', 'target_user_id' => '%d', 'target_employee_no' => '%s', 'note' => '%s',
			'import_batch_id' => '%d', 'source_row' => '%d',
		);
		$formats = array();
		foreach ( array_keys( $data ) as $field ) {
			$formats[] = isset( $format_map[ $field ] ) ? $format_map[ $field ] : '%s';
		}

		return self::db()->insert( self::table(), $data, $formats );
    }

    public static function get_all( $args = array() ) {
        $table          = self::table();
        $inventory      = UMS_DB_Inventory::table();
        $category_table = UMS_DB_Product_Category::table();
        $users_table    = self::db()->users;
        $organization   = UMS_DB_Organization::table();

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
            $where[] = '(inventory.item_variant LIKE %s OR inventory.size LIKE %s OR child.category_name LIKE %s OR parent.category_name LIKE %s OR actor.user_login LIKE %s OR target.user_login LIKE %s OR movement.target_employee_no LIKE %s OR organization.full_name LIKE %s OR movement.note LIKE %s)';
            $params[] = $like;
            $params[] = $like;
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
                actor.user_login AS actor_login,
                COALESCE(NULLIF(movement.target_employee_no, ''), organization.employee_no, target.user_login) AS target_login,
                organization.full_name AS target_name
            FROM $table movement
            LEFT JOIN $inventory inventory ON inventory.item_id = movement.item_id
            LEFT JOIN $category_table child ON child.category_id = inventory.category_id
            LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
            LEFT JOIN $users_table actor ON actor.ID = movement.actor_user_id
            LEFT JOIN $users_table target ON target.ID = movement.target_user_id
            LEFT JOIN $organization organization ON organization.employee_no = movement.target_employee_no
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY movement.created_at DESC, movement.movement_id DESC
            LIMIT %d";
        $params[] = $limit;

        return self::db()->get_results( self::db()->prepare( $sql, $params ), ARRAY_A );
    }

    public static function get_manual_allowance_usage( $target_user_id, $rule, $start_date, $end_date, $target_employee_no = '' ) {
        $table          = self::table();
        $inventory      = UMS_DB_Inventory::table();
        $category_table = UMS_DB_Product_Category::table();

        $target_user_id     = absint( $target_user_id );
        $target_employee_no = trim( sanitize_text_field( (string) $target_employee_no ) );
        $where  = array(
            "movement.movement_type = 'out'",
            'movement.request_id IS NULL',
            'movement.created_at >= %s',
            'movement.created_at <= %s',
        );
        $params = array(
            sanitize_text_field( $start_date ),
            sanitize_text_field( $end_date ),
        );

        if ( $target_user_id > 0 && $target_employee_no !== '' ) {
            $where[]  = '(movement.target_user_id = %d OR movement.target_employee_no = %s)';
            $params[] = $target_user_id;
            $params[] = $target_employee_no;
        } elseif ( $target_employee_no !== '' ) {
            $where[]  = 'movement.target_employee_no = %s';
            $params[] = $target_employee_no;
        } else {
            $where[]  = 'movement.target_user_id = %d';
            $params[] = $target_user_id;
        }

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
