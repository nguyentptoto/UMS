<?php
/**
 * Data layer for uniform issue requests.
 */
class UMS_DB_Request extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_requests';
	}

	public static function detail_table() {
		return self::prefix() . 'uniform_request_details';
	}

	public static function log_table() {
		return self::prefix() . 'uniform_approval_logs';
	}

	public static function get_by_id( $request_id ) {
		$table = self::table();
		$sql   = self::db()->prepare( "SELECT * FROM $table WHERE request_id = %d", absint( $request_id ) );
		return self::db()->get_row( $sql, ARRAY_A );
	}

	public static function get_details( $request_id ) {
		$detail_table    = self::detail_table();
		$inventory_table = UMS_DB_Inventory::table();
		$category_table  = UMS_DB_Product_Category::table();
		$sql             = self::db()->prepare(
			"
			SELECT details.*, inventory.category_id, inventory.item_type, inventory.item_variant,
				inventory.size, inventory.base_price, category.parent_id
			FROM $detail_table details
			LEFT JOIN $inventory_table inventory ON details.item_id = inventory.item_id
			LEFT JOIN $category_table category ON inventory.category_id = category.category_id
			WHERE details.request_id = %d
			ORDER BY details.detail_id ASC
			",
			absint( $request_id )
		);

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function get_all( $args = array() ) {
		$table         = self::table();
		$users_table   = self::db()->users;
		$profile_table = self::prefix() . 'uniform_user_profiles';
		$args          = wp_parse_args(
			$args,
			array(
				'creator_id'     => '',
				'target_user_id' => '',
				'status_in'      => array(),
				'department'     => '',
				'limit'          => 200,
			)
		);

		$where  = array( '1=1' );
		$params = array();

		if ( $args['creator_id'] !== '' ) {
			$where[]  = 'requests.creator_id = %d';
			$params[] = absint( $args['creator_id'] );
		}

		if ( $args['target_user_id'] !== '' ) {
			$where[]  = 'requests.target_user_id = %d';
			$params[] = absint( $args['target_user_id'] );
		}

		if ( $args['department'] !== '' ) {
			$where[]  = 'target_profiles.department = %s';
			$params[] = sanitize_text_field( $args['department'] );
		}

		if ( ! empty( $args['status_in'] ) && is_array( $args['status_in'] ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $args['status_in'] ), '%s' ) );
			$where[]      = "requests.current_status IN ($placeholders)";
			foreach ( $args['status_in'] as $status ) {
				$params[] = sanitize_text_field( $status );
			}
		}

		$sql = "
			SELECT requests.*, target_profiles.employee_code AS target_employee_code,
				target_profiles.full_name AS target_full_name,
				target_profiles.department AS target_department,
				creator.user_login AS creator_login
			FROM $table requests
			LEFT JOIN $profile_table target_profiles ON requests.target_user_id = target_profiles.user_id
			LEFT JOIN $users_table creator ON requests.creator_id = creator.ID
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY requests.created_at DESC';

		$limit = absint( $args['limit'] );
		if ( $limit > 0 ) {
			$sql .= ' LIMIT ' . $limit;
		}

		if ( ! empty( $params ) ) {
			$sql = self::db()->prepare( $sql, $params );
		}

		return self::db()->get_results( $sql, ARRAY_A );
	}

	public static function insert_with_details( $request, $details ) {
		$wpdb = self::db();
		$wpdb->query( 'START TRANSACTION' );

		$inserted = $wpdb->insert(
			self::table(),
			$request,
			array( '%d', '%d', '%s', '%d', '%s', '%d', '%s' )
		);

		if ( ! $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$request_id = (int) $wpdb->insert_id;

		if ( ! self::replace_details( $request_id, $details ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		foreach ( $details as $detail ) {
			$quantity   = max( 1, (int) $detail['quantity'] );
			$total      = (float) $detail['price_at_request'];
			$unit_price = $quantity > 0 ? $total / $quantity : 0;

			$movement_inserted = UMS_DB_Inventory_Movement::insert(
				array(
					'item_id'        => (int) $detail['item_id'],
					'request_id'     => $request_id,
					'movement_type'  => 'request_out',
					'quantity'       => $quantity,
					'before_qty'     => null,
					'after_qty'      => null,
					'unit_price'     => $unit_price,
					'total_price'    => $total,
					'actor_user_id'  => (int) $request['creator_id'],
					'target_user_id' => (int) $request['target_user_id'],
					'note'           => 'User gửi yêu cầu xuất kho, chờ xử lý duyệt/xuất.',
				)
			);

			if ( ! $movement_inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		self::add_log( $request_id, 1, 0, 'submitted', 'Người ở bước 1 đã tạo phiếu và chuyển sang bước duyệt tiếp theo.' );
		$wpdb->query( 'COMMIT' );
		return $request_id;
	}

	public static function update_with_details( $request_id, $request, $details ) {
		$wpdb       = self::db();
		$request_id = absint( $request_id );
		$wpdb->query( 'START TRANSACTION' );

		$updated = $wpdb->update(
			self::table(),
			$request,
			array( 'request_id' => $request_id ),
			array( '%d', '%d', '%s', '%d', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( $updated === false || ! self::replace_details( $request_id, $details ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		self::add_log( $request_id, 1, 0, 'edited', 'Người tạo ở bước 1 đã chỉnh sửa phiếu khi đang chờ bước 2 duyệt.' );
		$wpdb->query( 'COMMIT' );
		return true;
	}

	private static function replace_details( $request_id, $details ) {
		$wpdb       = self::db();
		$request_id = absint( $request_id );

		$deleted = $wpdb->delete( self::detail_table(), array( 'request_id' => $request_id ), array( '%d' ) );
		if ( $deleted === false ) {
			return false;
		}

		foreach ( $details as $detail ) {
			$inserted = $wpdb->insert(
				self::detail_table(),
				array(
					'request_id'       => $request_id,
					'item_id'          => (int) $detail['item_id'],
					'quantity'         => (int) $detail['quantity'],
					'price_at_request' => (float) $detail['price_at_request'],
				),
				array( '%d', '%d', '%d', '%f' )
			);

			if ( ! $inserted ) {
				return false;
			}
		}

		return true;
	}

	public static function update_status( $request_id, $status ) {
		return self::db()->update(
			self::table(),
			array( 'current_status' => sanitize_text_field( $status ) ),
			array( 'request_id' => absint( $request_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function add_log( $request_id, $step_order, $approver_id, $action, $comment = '' ) {
		return self::db()->insert(
			self::log_table(),
			array(
				'request_id'  => absint( $request_id ),
				'step_order'  => absint( $step_order ),
				'approver_id' => absint( $approver_id ),
				'action'      => sanitize_key( $action ),
				'comment'     => sanitize_textarea_field( $comment ),
			),
			array( '%d', '%d', '%d', '%s', '%s' )
		);
	}

	public static function delete_request( $request_id ) {
		$wpdb       = self::db();
		$request_id = absint( $request_id );
		$wpdb->query( 'START TRANSACTION' );

		$wpdb->delete( self::detail_table(), array( 'request_id' => $request_id ), array( '%d' ) );
		$wpdb->delete( self::log_table(), array( 'request_id' => $request_id ), array( '%d' ) );
		$deleted = $wpdb->delete( self::table(), array( 'request_id' => $request_id ), array( '%d' ) );

		if ( $deleted === false ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$wpdb->query( 'COMMIT' );
		return true;
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}
}
