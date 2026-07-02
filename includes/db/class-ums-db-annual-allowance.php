<?php
/**
 * Data layer for annual uniform allowance rules.
 */
class UMS_DB_Annual_Allowance extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_annual_allowance_rules';
	}

	public static function get_all( $args = array() ) {
		$table           = self::table();
		$inventory_table = UMS_DB_Inventory::table();
		$category_table  = UMS_DB_Product_Category::table();
		$position_table  = UMS_DB_Position::table();
		$args            = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'apply_type'  => '',
				'target_type' => '',
				'status'      => '',
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['apply_type'] !== '' ) {
			$where[]  = 'rules.apply_type = %s';
			$params[] = sanitize_key( $args['apply_type'] );
		}

		if ( $args['target_type'] !== '' ) {
			$where[]  = 'rules.target_type = %s';
			$params[] = sanitize_key( $args['target_type'] );
		}

		if ( $args['status'] === 'active' ) {
			$where[] = 'rules.is_active = 1';
		} elseif ( $args['status'] === 'inactive' ) {
			$where[] = 'rules.is_active = 0';
		}

		if ( $args['search'] !== '' ) {
			$like     = '%' . self::db()->esc_like( $args['search'] ) . '%';
			$where[]  = '(item_child.category_name LIKE %s OR item_parent.category_name LIKE %s OR apply_category.category_name LIKE %s OR apply_parent.category_name LIKE %s OR inventory.item_variant LIKE %s OR inventory.size LIKE %s OR position.position_name LIKE %s OR position.position_code LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$sql = "
			SELECT rules.*, inventory.item_variant, inventory.size, inventory.stock_qty,
				item_child.category_name, item_parent.category_name AS parent_category_name,
				apply_category.category_name AS apply_category_name,
				apply_parent.category_name AS apply_parent_category_name,
				position.position_code, position.position_name
			FROM $table rules
			LEFT JOIN $inventory_table inventory ON inventory.item_id = rules.item_id
			LEFT JOIN $category_table item_child ON item_child.category_id = inventory.category_id
			LEFT JOIN $category_table item_parent ON item_parent.category_id = item_child.parent_id
			LEFT JOIN $category_table apply_category ON apply_category.category_id = rules.category_id
			LEFT JOIN $category_table apply_parent ON apply_parent.category_id = apply_category.parent_id
			LEFT JOIN $position_table position ON position.position_id = rules.position_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY rules.is_active DESC, rules.apply_type ASC, apply_parent.category_name ASC, apply_category.category_name ASC, item_parent.category_name ASC, item_child.category_name ASC, inventory.item_variant ASC, inventory.size ASC';

		if ( ! empty( $params ) ) {
			$sql = self::db()->prepare( $sql, $params );
		}

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_by_id( $rule_id ) {
		$table = self::table();
		$sql   = self::db()->prepare( "SELECT * FROM $table WHERE rule_id = %d", absint( $rule_id ) );
		return self::db()->get_row( $sql, ARRAY_A );
	}

	public static function get_active_rule_for_item( $item_id, $position_id = 0 ) {
		$table           = self::table();
		$inventory_table = UMS_DB_Inventory::table();
		$category_table  = UMS_DB_Product_Category::table();
		$position_id     = absint( $position_id );

		$sql = self::db()->prepare(
			"
			SELECT rules.*, inventory.category_id AS item_category_id, child.parent_id AS item_parent_category_id,
				inventory.item_variant, inventory.size, child.category_name, parent.category_name AS parent_category_name
			FROM $table rules
			INNER JOIN $inventory_table inventory ON inventory.item_id = %d
			LEFT JOIN $category_table child ON child.category_id = inventory.category_id
			LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
			WHERE rules.is_active = 1
				AND (
					(rules.apply_type = 'item' AND rules.item_id = inventory.item_id)
					OR (rules.apply_type = 'category' AND rules.category_id IN (inventory.category_id, child.parent_id))
				)
				AND (
					rules.target_type = 'all'
					OR (rules.target_type = 'position' AND rules.position_id = %d)
				)
			ORDER BY
				CASE WHEN rules.target_type = 'position' THEN 0 ELSE 1 END ASC,
				CASE WHEN rules.apply_type = 'item' THEN 0 ELSE 1 END ASC,
				CASE WHEN rules.category_id = inventory.category_id THEN 0 ELSE 1 END ASC,
				rules.rule_id DESC
			LIMIT 1
			",
			absint( $item_id ),
			$position_id
		);

		return self::db()->get_row( $sql, ARRAY_A );
	}

	public static function insert( $data ) {
		return self::db()->insert( self::table(), $data, self::formats_for( $data ) );
	}

	public static function update( $rule_id, $data ) {
		return self::db()->update(
			self::table(),
			$data,
			array( 'rule_id' => absint( $rule_id ) ),
			self::formats_for( $data ),
			array( '%d' )
		);
	}

	public static function delete( $rule_id ) {
		return self::db()->delete( self::table(), array( 'rule_id' => absint( $rule_id ) ), array( '%d' ) );
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}

	private static function format_map() {
		return array(
			'rule_id'            => '%d',
			'apply_type'         => '%s',
			'category_id'        => '%d',
			'item_id'            => '%d',
			'target_type'        => '%s',
			'position_id'        => '%d',
			'frequency_count'    => '%d',
			'frequency_years'    => '%d',
			'monthly_quantities' => '%s',
			'is_active'          => '%d',
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
