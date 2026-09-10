<?php
/**
 * Detects leavers and calculates/records uniform returns from actual stock issues.
 */
class UMS_Employee_Exit_Manager {

	const SHOE_LIFETIME_YEARS = 2;

	public static function finalize_organization_sync( $sync_token, $detected_at ) {
		if ( ! UMS_DB_Organization::supports_employment_status() || ! UMS_DB_Employee_Exit::is_ready() ) {
			return new WP_Error( 'employee_exit_schema_missing', 'Database chưa có cấu trúc quản lý CNV nghỉ việc. Hãy chạy phần UPDATE trong ums.sql trước khi đồng bộ.' );
		}

		$employees = UMS_DB_Organization::get_active_not_in_sync( $sync_token );
		if ( empty( $employees ) ) {
			return array( 'left' => 0, 'cases_created' => 0 );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$created = 0;
		foreach ( $employees as $employee ) {
			$result = self::create_case( $employee, $detected_at );
			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}
			$created += $result ? 1 : 0;
		}

		$marked = UMS_DB_Organization::mark_not_in_sync_as_left( $sync_token, $detected_at );
		if ( $marked === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_mark_failed', UMS_DB_Organization::get_last_error() );
		}
		$wpdb->query( 'COMMIT' );

		foreach ( $employees as $employee ) {
			self::set_wp_user_status( $employee['employee_no'], 1 );
		}
		return array( 'left' => (int) $marked, 'cases_created' => $created );
	}

	public static function restore_synced_employees( $rows ) {
		if ( ! UMS_DB_Employee_Exit::is_ready() ) {
			return;
		}
		$employee_nos = array();
		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row['emp_no'] ) ) {
				$employee_nos[] = trim( (string) $row['emp_no'] );
			}
		}
		UMS_DB_Employee_Exit::cancel_open_cases( $employee_nos );
	}

	public static function classify_employee( $employee ) {
		$employee_no = strtoupper( trim( (string) ( $employee['employee_no'] ?? $employee['emp_no'] ?? '' ) ) );
		if ( preg_match( '/^[MF]1/', $employee_no ) ) {
			return 'labor_leasing';
		}
		$contract_date = trim( (string) ( $employee['first_contract_date'] ?? '' ) );
		return $contract_date !== '' && $contract_date !== '0000-00-00' && strtotime( $contract_date ) !== false
			? 'official'
			: 'probation';
	}

	public static function refresh_case( $exit_id, $actual_leave_date ) {
		$case = UMS_DB_Employee_Exit::get_by_id( $exit_id );
		if ( ! $case || $case['status'] === 'cancelled' ) {
			return new WP_Error( 'employee_exit_invalid', 'Không tìm thấy hồ sơ nghỉ việc đang hiệu lực.' );
		}
		$actual_leave_date = self::sanitize_date( $actual_leave_date );
		if ( $actual_leave_date === '' ) {
			return new WP_Error( 'employee_exit_date_invalid', 'Ngày nghỉ việc không hợp lệ.' );
		}

		$case['actual_leave_date'] = $actual_leave_date;
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		if ( UMS_DB_Employee_Exit::update_case(
			$exit_id,
			array( 'actual_leave_date' => $actual_leave_date, 'updated_at' => current_time( 'mysql' ) ),
			array( '%s', '%s' )
		) === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_update_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$result = self::build_return_items( $case, true );
		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}
		$status = self::calculate_status( UMS_DB_Employee_Exit::get_items( $exit_id ) );
		if ( UMS_DB_Employee_Exit::update_case(
			$exit_id,
			array( 'status' => $status, 'completed_at' => $status === 'completed' ? current_time( 'mysql' ) : null ),
			array( '%s', '%s' )
		) === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_update_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$wpdb->query( 'COMMIT' );
		return true;
	}

	public static function save_returns( $exit_id, $posted_items, $notes = '' ) {
		$case = UMS_DB_Employee_Exit::get_by_id( $exit_id );
		if ( ! $case || in_array( $case['status'], array( 'cancelled' ), true ) ) {
			return new WP_Error( 'employee_exit_invalid', 'Không tìm thấy hồ sơ nghỉ việc đang hiệu lực.' );
		}

		$items = UMS_DB_Employee_Exit::get_items( $exit_id );
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $items as $item ) {
			$id = (int) $item['return_item_id'];
			$input = isset( $posted_items[ $id ] ) && is_array( $posted_items[ $id ] ) ? $posted_items[ $id ] : array();
			$returned = max( 0, absint( $input['returned_quantity'] ?? $item['returned_quantity'] ) );
			$max_return = max( (int) $item['required_quantity'], (int) $item['issued_quantity'] );
			$returned = min( $returned, $max_return );
			$reusable = max( 0, absint( $input['reusable_quantity'] ?? $item['reusable_quantity'] ) );
			$reusable = min( $reusable, $returned );
			$restocked = (int) $item['restocked_quantity'];
			if ( $reusable < $restocked ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'employee_exit_restock_reversal', 'SL nhập lại kho không thể nhỏ hơn số lượng đã được cộng kho trước đó.' );
			}

			$delta = $reusable - $restocked;
			if ( $delta > 0 && (int) $item['item_id'] > 0 ) {
				$inventory = UMS_DB_Inventory::get_by_id_for_update( $item['item_id'] );
				if ( ! $inventory ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'employee_exit_inventory_missing', 'Sản phẩm hoàn trả không còn tồn tại trong kho.' );
				}
				$before = (int) $inventory['stock_qty'];
				$after  = $before + $delta;
				if ( UMS_DB_Inventory::update( $item['item_id'], array( 'stock_qty' => $after ) ) === false ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'employee_exit_restock_failed', UMS_DB_Inventory::get_last_error() );
				}
				if ( ! UMS_DB_Inventory_Movement::insert( array(
					'item_id' => (int) $item['item_id'], 'movement_type' => 'return_in', 'quantity' => $delta,
					'before_qty' => $before, 'after_qty' => $after, 'unit_price' => (float) $inventory['base_price'],
					'total_price' => (float) $inventory['base_price'] * $delta, 'actor_user_id' => get_current_user_id(),
					'target_employee_no' => $case['employee_no'],
					'note' => sprintf( 'Thu hồi đồng phục khi nghỉ việc, hồ sơ #%d.', $exit_id ),
				) ) ) {
					$wpdb->query( 'ROLLBACK' );
					return new WP_Error( 'employee_exit_movement_failed', UMS_DB_Inventory_Movement::get_last_error() );
				}
				$restocked += $delta;
			}

			if ( UMS_DB_Employee_Exit::update_item(
				$id,
				array( 'returned_quantity' => $returned, 'reusable_quantity' => $reusable, 'restocked_quantity' => $restocked, 'updated_at' => current_time( 'mysql' ) ),
				array( '%d', '%d', '%d', '%s' )
			) === false ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'employee_exit_item_update_failed', UMS_DB_Employee_Exit::get_last_error() );
			}
		}

		$status = self::calculate_status( UMS_DB_Employee_Exit::get_items( $exit_id ) );
		$is_complete = $status === 'completed';
		$case_update = array(
			'status' => $status,
			'notes' => sanitize_textarea_field( $notes ),
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => get_current_user_id(),
			'completed_at' => $is_complete ? current_time( 'mysql' ) : null,
		);
		if ( UMS_DB_Employee_Exit::update_case( $exit_id, $case_update, array( '%s', '%s', '%s', '%d', '%s' ) ) === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_update_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$wpdb->query( 'COMMIT' );
		return $status;
	}

	private static function create_case( $employee, $detected_at ) {
		if ( UMS_DB_Employee_Exit::get_open_by_employee_no( $employee['employee_no'] ) ) {
			return 0;
		}
		$leave_date = mysql2date( 'Y-m-d', $detected_at );
		$data = array(
			'employee_no' => $employee['employee_no'], 'full_name' => $employee['full_name'],
			'department' => $employee['department'], 'team' => $employee['team'], 'cost_center' => $employee['cost_center'],
			'position' => $employee['position'], 'date_joined' => $employee['date_joined'],
			'first_contract_date' => $employee['first_contract_date'], 'employee_type' => self::classify_employee( $employee ),
			'detected_at' => $detected_at, 'actual_leave_date' => $leave_date, 'status' => 'pending', 'notes' => '',
			'organization_source_id' => absint( $employee['source_id'] ), 'created_at' => $detected_at, 'updated_at' => $detected_at,
		);
		$exit_id = UMS_DB_Employee_Exit::insert_case( $data );
		if ( $exit_id <= 0 ) {
			return new WP_Error( 'employee_exit_create_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$data['exit_id'] = $exit_id;
		$result = self::build_return_items( $data );
		return is_wp_error( $result ) ? $result : $exit_id;
	}

	private static function build_return_items( $case, $preserve_progress = false ) {
		$existing = array();
		if ( $preserve_progress ) {
			foreach ( UMS_DB_Employee_Exit::get_items( $case['exit_id'] ) as $item ) {
				$existing[ self::item_key( $item['item_id'], $item['item_group'], $item['item_name'], $item['size'] ) ] = $item;
			}
		}
		$issued = self::get_issued_items( $case['employee_no'] );
		$type   = $case['employee_type'];
		$caps   = array( 'pants' => 2, 'shirt' => 2, 'jacket' => 1, 'hat' => 1, 'shoes' => 1 );
		$remaining = $caps;
		$latest_shoe = '';
		foreach ( $issued as &$row ) {
			$row['item_group'] = UMS_Employee_Allowance_Report::get_business_group( $row );
			if ( $row['item_group'] === 'shoes' && ( $latest_shoe === '' || $row['latest_issued_at'] > $latest_shoe ) ) {
				$latest_shoe = $row['latest_issued_at'];
			}
		}
		unset( $row );
		usort( $issued, function ( $a, $b ) { return strcmp( $b['latest_issued_at'], $a['latest_issued_at'] ); } );
		$latest_shoe_date = $latest_shoe !== '' ? mysql2date( 'Y-m-d', $latest_shoe ) : '';
		$shoe_exempt = $type === 'official' && $latest_shoe_date !== ''
			&& strtotime( $latest_shoe_date ) < strtotime( '-' . self::SHOE_LIFETIME_YEARS . ' years', strtotime( $case['actual_leave_date'] ) );

		$new_items = array();
		$order = 0;
		foreach ( $issued as $row ) {
			$issued_qty = max( 0, (int) $row['issued_quantity'] );
			$group = $row['item_group'] !== '' ? $row['item_group'] : 'other';
			$required = 0;
			$exempt = 0;
			$reason = '';
			if ( $type === 'probation' ) {
				$required = $issued_qty;
			} elseif ( isset( $remaining[ $group ] ) && $remaining[ $group ] > 0 ) {
				$required = min( $issued_qty, $remaining[ $group ] );
				$remaining[ $group ] -= $required;
				if ( $group === 'shoes' && $shoe_exempt ) {
					$exempt = $required;
					$reason = 'Lần cấp giày gần nhất đã quá 02 năm tính đến ngày nghỉ việc.';
				}
			}
			$new_items[] = self::return_item_data( $case['exit_id'], $row, $group, $issued_qty, $required, $exempt, $reason, ++$order, $existing );
		}

		foreach ( array( 'id_card' => 'Thẻ tên / thẻ nhân viên', 'lanyard' => 'Dây đeo thẻ' ) as $group => $name ) {
			$row = array( 'item_id' => 0, 'item_variant' => $name, 'size' => '', 'latest_issued_at' => null );
			$new_items[] = self::return_item_data( $case['exit_id'], $row, $group, 1, 1, 0, '', ++$order, $existing );
		}

		if ( UMS_DB_Employee_Exit::delete_items( $case['exit_id'] ) === false ) {
			return new WP_Error( 'employee_exit_items_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		foreach ( $new_items as $item ) {
			if ( ! UMS_DB_Employee_Exit::insert_item( $item ) ) {
				return new WP_Error( 'employee_exit_items_failed', UMS_DB_Employee_Exit::get_last_error() );
			}
		}
		return true;
	}

	private static function return_item_data( $exit_id, $row, $group, $issued, $required, $exempt, $reason, $order, $existing ) {
		$name = trim( (string) ( $row['item_variant'] ?? '' ) );
		$size = trim( (string) ( $row['size'] ?? '' ) );
		$key  = self::item_key( $row['item_id'] ?? 0, $group, $name, $size );
		$old  = $existing[ $key ] ?? array();
		$returned = min( (int) ( $old['returned_quantity'] ?? 0 ), max( $issued, $required ) );
		$restocked = min( (int) ( $old['restocked_quantity'] ?? 0 ), $returned );
		return array(
			'exit_id' => (int) $exit_id, 'item_id' => absint( $row['item_id'] ?? 0 ), 'item_group' => $group,
			'item_name' => $name, 'size' => $size, 'issued_quantity' => $issued, 'required_quantity' => $required,
			'returned_quantity' => $returned, 'exempt_quantity' => $exempt,
			'reusable_quantity' => max( $restocked, min( (int) ( $old['reusable_quantity'] ?? 0 ), $returned ) ),
			'restocked_quantity' => $restocked, 'exemption_reason' => $reason,
			'latest_issued_at' => $row['latest_issued_at'] ?? null, 'display_order' => $order,
		);
	}

	private static function get_issued_items( $employee_no ) {
		global $wpdb;
		$user_ids = array();
		$user = get_user_by( 'login', $employee_no );
		if ( $user instanceof WP_User ) {
			$user_ids[] = (int) $user->ID;
		}
		$meta_ids = get_users( array( 'meta_key' => 'ums_employee_code', 'meta_value' => $employee_no, 'fields' => 'ids' ) );
		$user_ids = array_values( array_unique( array_merge( $user_ids, array_map( 'absint', $meta_ids ) ) ) );
		$where = 'm.target_employee_no = %s';
		$params = array( $employee_no );
		if ( $user_ids ) {
			$where .= ' OR m.target_user_id IN (' . implode( ',', array_fill( 0, count( $user_ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $user_ids );
		}
		$sql = "SELECT m.item_id, SUM(m.quantity) AS issued_quantity, MAX(m.created_at) AS latest_issued_at,
			i.item_variant, i.size, child.category_name, parent.category_name AS parent_category_name
			FROM " . UMS_DB_Inventory_Movement::table() . " m
			INNER JOIN " . UMS_DB_Inventory::table() . " i ON i.item_id = m.item_id
			LEFT JOIN " . UMS_DB_Product_Category::table() . " child ON child.category_id = i.category_id
			LEFT JOIN " . UMS_DB_Product_Category::table() . " parent ON parent.category_id = child.parent_id
			WHERE m.movement_type = 'out' AND ($where)
			GROUP BY m.item_id, i.item_variant, i.size, child.category_name, parent.category_name
			HAVING SUM(m.quantity) > 0";
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	private static function item_key( $item_id, $group, $name, $size ) {
		return absint( $item_id ) . '|' . sanitize_key( $group ) . '|' . strtolower( trim( $name ) ) . '|' . strtolower( trim( $size ) );
	}

	private static function sanitize_date( $date ) {
		$date = sanitize_text_field( (string) $date );
		$parts = array_map( 'intval', explode( '-', $date ) );
		return count( $parts ) === 3 && checkdate( $parts[1], $parts[2], $parts[0] ) ? $date : '';
	}

	private static function calculate_status( $items ) {
		$has_progress = false;
		$is_complete = true;
		foreach ( (array) $items as $item ) {
			$settled = (int) $item['returned_quantity'] + (int) $item['exempt_quantity'];
			$has_progress = $has_progress || $settled > 0;
			$is_complete  = $is_complete && $settled >= (int) $item['required_quantity'];
		}
		return $is_complete ? 'completed' : ( $has_progress ? 'in_progress' : 'pending' );
	}

	private static function set_wp_user_status( $employee_no, $status ) {
		$user = get_user_by( 'login', $employee_no );
		if ( ! $user ) {
			$users = get_users( array( 'meta_key' => 'ums_employee_code', 'meta_value' => $employee_no, 'number' => 1 ) );
			$user = $users ? reset( $users ) : null;
		}
		if ( $user instanceof WP_User ) {
			global $wpdb;
			$wpdb->update( $wpdb->users, array( 'user_status' => absint( $status ) ), array( 'ID' => $user->ID ), array( '%d' ), array( '%d' ) );
			clean_user_cache( $user->ID );
		}
	}
}
