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
            $where[] = '(inventory.item_type LIKE %s OR inventory.item_variant LIKE %s OR inventory.size LIKE %s OR child.category_name LIKE %s OR parent.category_name LIKE %s)';
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
            $where[]  = '(inventory.category_id = %d OR child.parent_id = %d)';
            $params[] = absint( $args['parent_id'] );
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
        $sql = "SELECT inventory.*, child.category_name AS category_name,
                parent.category_id AS parent_category_id, parent.category_name AS parent_category_name
            FROM $table inventory
            LEFT JOIN $category_table child ON child.category_id = inventory.category_id
            LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
            WHERE " . implode( ' AND ', $where ) . '
            ORDER BY COALESCE(parent.category_name, child.category_name) ASC, inventory.item_variant ASC, inventory.size ASC';

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
	 * Danh sách sản phẩm logic, gộp các dòng size của cùng một sản phẩm.
	 */
	public static function get_product_groups() {
		$table          = self::table();
		$category_table = UMS_DB_Product_Category::table();
		$sql = "SELECT inventory.category_id, inventory.item_variant,
			COALESCE(parent.category_name, child.category_name) AS category_name,
			COUNT(*) AS size_count
			FROM $table inventory
			LEFT JOIN $category_table child ON child.category_id = inventory.category_id
			LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
			WHERE inventory.category_id IS NOT NULL AND inventory.item_variant <> ''
			GROUP BY inventory.category_id, inventory.item_variant, category_name
			ORDER BY category_name ASC, inventory.item_variant ASC";

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function product_group_exists( $category_id, $item_variant ) {
		$sql = self::db()->prepare(
			'SELECT COUNT(*) FROM ' . self::table() . ' WHERE category_id = %d AND item_variant = %s',
			absint( $category_id ),
			sanitize_text_field( $item_variant )
		);
		return (int) self::db()->get_var( $sql ) > 0;
	}

	/**
	 * Tìm đúng một dòng kho theo sản phẩm logic và size.
	 */
	public static function get_by_product_size( $category_id, $item_variant, $size ) {
		$sql = self::db()->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE category_id = %d AND item_variant = %s AND size = %s ORDER BY item_id ASC',
			absint( $category_id ),
			sanitize_text_field( $item_variant ),
			sanitize_text_field( $size )
		);

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_product_rows( $category_id, $item_variant ) {
		$sql = self::db()->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE category_id = %d AND item_variant = %s ORDER BY item_id ASC',
			absint( $category_id ),
			sanitize_text_field( $item_variant )
		);

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_by_name_and_size( $product, $size ) {
		$sql = self::db()->prepare(
			'SELECT * FROM ' . self::table() . "
			WHERE COALESCE(NULLIF(item_variant, ''), item_type) = %s AND size = %s
			ORDER BY item_id ASC",
			sanitize_text_field( $product ),
			sanitize_text_field( $size )
		);

		return self::db()->get_results( $sql, ARRAY_A );
	}

    /**
     * Lấy chi tiết một dòng tồn kho.
     */
    public static function get_by_id( $item_id ) {
        $table = self::table();
        $category_table = UMS_DB_Product_Category::table();
        $sql   = self::db()->prepare(
            "SELECT inventory.*, child.category_name AS category_name,
                    parent.category_id AS parent_category_id, parent.category_name AS parent_category_name
            FROM $table inventory
            LEFT JOIN $category_table child ON child.category_id = inventory.category_id
            LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
            WHERE inventory.item_id = %d",
            absint( $item_id )
        );
        return self::db()->get_row( $sql, ARRAY_A );
    }

	/**
	 * Lock an inventory row while an import transaction is running.
	 */
	public static function get_by_id_for_update( $item_id ) {
		$sql = self::db()->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE item_id = %d FOR UPDATE',
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
            "SELECT COUNT(*) FROM $table WHERE category_id = %d AND item_variant = %s AND size = %s AND item_id <> %d",
            $data['category_id'],
            $data['item_variant'],
            $data['size'],
            absint( $exclude_item_id )
        );

        return (int) self::db()->get_var( $sql ) > 0;
    }

    /**
     * Thêm sản phẩm/tồn kho.
     */
    public static function insert( $data ) {
		$should_sync_price = array_key_exists( 'base_price', $data )
			&& ! empty( $data['category_id'] )
			&& ! empty( $data['item_variant'] );
		$price_info = $should_sync_price
			? self::get_product_price_info( $data['category_id'], $data['item_variant'] )
			: array( 'price' => 0.0, 'ambiguous' => false, 'row_count' => 0 );

		// Khi thêm size mới, kế thừa giá đang dùng của sản phẩm thay vì tạo giá riêng cho size.
		if ( $should_sync_price && (float) $data['base_price'] <= 0 && ! $price_info['ambiguous'] && $price_info['price'] > 0 ) {
			$data['base_price'] = $price_info['price'];
		}

		$result = self::db()->insert( self::table(), $data, self::formats_for( $data ) );
		if ( false === $result || ! $should_sync_price ) {
			return $result;
		}

		// Một giá nhập dương là giá mới của toàn sản phẩm, không phải riêng size vừa thêm.
		if ( (float) $data['base_price'] > 0 || ! $price_info['ambiguous'] ) {
			if ( false === self::update_product_price( $data['category_id'], $data['item_variant'], $data['base_price'] ) ) {
				return false;
			}
		}

		return $result;
    }

    /**
     * Cập nhật sản phẩm/tồn kho.
     */
    public static function update( $item_id, $data ) {
		$old_item = self::db()->get_row(
			self::db()->prepare( 'SELECT * FROM ' . self::table() . ' WHERE item_id = %d', absint( $item_id ) ),
			ARRAY_A
		);
		$result = self::db()->update(
            self::table(),
            $data,
            array( 'item_id' => absint( $item_id ) ),
            self::formats_for( $data ),
            array( '%d' )
        );

		if ( false === $result || ! array_key_exists( 'base_price', $data ) || ! $old_item ) {
			return $result;
		}

		$category_id = isset( $data['category_id'] ) ? absint( $data['category_id'] ) : absint( $old_item['category_id'] );
		$item_variant = isset( $data['item_variant'] ) ? sanitize_text_field( $data['item_variant'] ) : (string) $old_item['item_variant'];
		if ( false === self::update_product_price( $category_id, $item_variant, $data['base_price'] ) ) {
			return false;
		}

		return $result;
    }

	/**
	 * Đọc giá dùng chung của một sản phẩm qua toàn bộ các dòng size.
	 */
	public static function get_product_price_info( $category_id, $item_variant ) {
		$prices = self::db()->get_col(
			self::db()->prepare(
				'SELECT base_price FROM ' . self::table() . ' WHERE category_id = %d AND item_variant = %s ORDER BY item_id ASC',
				absint( $category_id ),
				sanitize_text_field( $item_variant )
			)
		);
		$positive_prices = array();
		foreach ( $prices as $price ) {
			$price = round( (float) $price, 2 );
			if ( $price > 0 ) {
				$positive_prices[ number_format( $price, 2, '.', '' ) ] = $price;
			}
		}

		return array(
			'price'     => count( $positive_prices ) === 1 ? (float) reset( $positive_prices ) : 0.0,
			'ambiguous' => count( $positive_prices ) > 1,
			'row_count' => count( $prices ),
		);
	}

	/**
	 * Đơn giá thuộc về sản phẩm; mọi size trong cùng sản phẩm phải cùng giá.
	 */
	public static function update_product_price( $category_id, $item_variant, $base_price ) {
		return self::db()->update(
			self::table(),
			array( 'base_price' => (float) $base_price ),
			array(
				'category_id'  => absint( $category_id ),
				'item_variant' => sanitize_text_field( $item_variant ),
			),
			array( '%f' ),
			array( '%d', '%s' )
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

    public static function get_last_insert_id() {
        return (int) self::db()->insert_id;
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
