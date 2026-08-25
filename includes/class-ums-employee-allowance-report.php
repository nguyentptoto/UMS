<?php
/**
 * Tính và xuất định mức theo từng CNV từ Sơ đồ tổ chức TVN.
 */
class UMS_Employee_Allowance_Report {
	const SHEET_NAME = 'Total';

	public static function sanitize_filters( $raw ) {
		$year  = isset( $raw['report_year'] ) ? absint( $raw['report_year'] ) : (int) current_time( 'Y' );
		$month = isset( $raw['report_month'] ) ? absint( $raw['report_month'] ) : 4;
		$year  = min( 2100, max( 2000, $year ) );
		$month = in_array( $month, array( 4, 9 ), true ) ? $month : 4;
		$quantity_mode = $raw['report_quantity_mode'] ?? ( $raw['quantity_mode'] ?? 'remaining' );
		$include_zero_raw = $raw['report_include_zero'] ?? ( $raw['include_zero'] ?? true );
		$allowed_prefixes = self::get_cost_center_prefix_keys();
		$cost_center_prefix = sanitize_text_field( $raw['report_cost_center_prefix'] ?? ( $raw['cost_center_prefix'] ?? '' ) );
		if ( $cost_center_prefix !== '' && ! in_array( $cost_center_prefix, $allowed_prefixes, true ) ) {
			$cost_center_prefix = '';
		}

		return array(
			'report_year'   => $year,
			'report_month'  => $month,
			'evaluation_date' => date( 'Y-m-t', strtotime( sprintf( '%04d-%02d-01', $year, $month ) ) ),
			'search'        => sanitize_text_field( $raw['report_search'] ?? ( $raw['search'] ?? '' ) ),
			'department'    => sanitize_text_field( $raw['report_department'] ?? ( $raw['department'] ?? '' ) ),
			'team'          => sanitize_text_field( $raw['report_team'] ?? ( $raw['team'] ?? '' ) ),
			'cost_center'   => sanitize_text_field( $raw['report_cost_center'] ?? ( $raw['cost_center'] ?? '' ) ),
			'cost_center_prefix' => $cost_center_prefix,
			'position'      => sanitize_text_field( $raw['report_position'] ?? ( $raw['position'] ?? '' ) ),
			'quantity_mode' => $quantity_mode === 'quota' ? 'quota' : 'remaining',
			'include_zero'  => filter_var( $include_zero_raw, FILTER_VALIDATE_BOOLEAN ),
		);
	}

	/**
	 * Phạm vi nhà máy được phép đưa vào báo cáo. Filter cho phép mở rộng sau này.
	 */
	public static function get_cost_center_prefixes() {
		$prefixes = apply_filters(
			'ums_employee_allowance_cost_center_prefixes',
			array(
				'1300' => '1300',
				'4400' => '4400',
				'4900' => '4900',
			)
		);
		return is_array( $prefixes ) && ! empty( $prefixes )
			? $prefixes
			: array( '1300' => '1300', '4400' => '4400', '4900' => '4900' );
	}

	/**
	 * PHP tự đổi key chỉ gồm chữ số thành integer; chuẩn hóa lại để so sánh POST chính xác.
	 */
	public static function get_cost_center_prefix_keys() {
		return array_map( 'strval', array_keys( self::get_cost_center_prefixes() ) );
	}

	public static function build( $filters ) {
		$filters = self::sanitize_filters( $filters );
		if ( ! UMS_DB_Organization::table_exists() ) {
			throw new RuntimeException( 'Chưa có bảng Sơ đồ tổ chức TVN.' );
		}

		$employees = UMS_DB_Organization::get_for_allowance_export(
			array(
				'search'      => $filters['search'],
				'department'  => $filters['department'],
				'team'        => $filters['team'],
				'cost_center' => $filters['cost_center'],
				'cost_center_prefixes' => $filters['cost_center_prefix'] !== ''
					? array( $filters['cost_center_prefix'] )
					: self::get_cost_center_prefix_keys(),
				'position'    => $filters['position'],
			)
		);
		$products     = self::get_products();
		$rules        = array_values(
			array_filter(
				UMS_DB_Annual_Allowance::get_active_for_report(),
				function ( $rule ) use ( $filters ) {
					$monthly = json_decode( (string) ( $rule['monthly_quantities'] ?? '' ), true );
					return is_array( $monthly ) && absint( $monthly[ $filters['report_month'] ] ?? 0 ) > 0;
				}
			)
		);
		foreach ( $products as &$product ) {
			$product['rules'] = array_values(
				array_filter(
					$rules,
					function ( $rule ) use ( $product ) {
						return self::rule_applies_to_product( $rule, $product );
					}
				)
			);
		}
		unset( $product );
		$rule_item_ids = self::get_rule_item_ids( $products );
		$position_ids = self::get_position_ids();
		$user_ids     = $filters['quantity_mode'] === 'remaining' ? self::get_employee_user_ids( $employees ) : array();
		$usage_index  = $filters['quantity_mode'] === 'remaining'
			? self::get_usage_index( $employees, $user_ids, $rules, $filters['evaluation_date'], $rule_item_ids )
			: array();
		$rows         = array();
		$warnings     = array();
		$warning_keys = array();
		$with_quota   = 0;
		$allocation_cache = array();

		foreach ( $employees as $employee ) {
			$position_code = UMS_DB_Annual_Allowance::normalize_position_code( $employee['position'] ?? '' );
			$position_id   = isset( $position_ids[ $position_code ] ) ? $position_ids[ $position_code ] : 0;
			$context       = array(
				'department'     => (string) ( $employee['department'] ?? '' ),
				'team'           => (string) ( $employee['team'] ?? '' ),
				'cost_center'    => (string) ( $employee['cost_center'] ?? '' ),
				'position'       => $position_code,
				'date_joined'    => (string) ( $employee['date_joined'] ?? '' ),
				'evaluation_date'=> $filters['evaluation_date'],
			);
			$employee_no = trim( (string) $employee['employee_no'] );
			$totals      = array_fill_keys( array( 'hat', 'shoes', 'pants', 'shirt', 'jacket', 'coat' ), 0 );

			if ( $context['date_joined'] === '' ) {
				self::add_warning( $warnings, $warning_keys, 'missing-date-' . $employee_no, 'CNV ' . $employee_no . ' chưa có Ngày vào; các rule CNV mới sẽ không được áp dụng.' );
			}

			$allocation_key = self::get_allocation_cache_key( $context, $position_id );
			if ( ! isset( $allocation_cache[ $allocation_key ] ) ) {
				$allocation_cache[ $allocation_key ] = self::get_allocations( $products, $context, $position_id, $filters['report_month'] );
			}
			foreach ( $allocation_cache[ $allocation_key ]['warnings'] as $warning ) {
				self::add_warning( $warnings, $warning_keys, $warning['key'], $warning['message'] );
			}

			foreach ( $allocation_cache[ $allocation_key ]['allocations'] as $allocation ) {
				$rule     = $allocation['rule'];
				$quota    = $allocation['quota'];
				$quantity = $filters['quantity_mode'] === 'remaining'
					? self::get_remaining_quantity( $quota, $rule, $employee_no, $filters['evaluation_date'], $usage_index, $rule_item_ids )
					: $quota;
				$totals[ $allocation['group'] ] += max( 0, $quantity );
			}

			$total_quantity = array_sum( $totals );
			if ( $total_quantity > 0 ) {
				$with_quota++;
			}
			if ( ! $filters['include_zero'] && $total_quantity === 0 ) {
				continue;
			}

			$rows[] = array(
				'stt'          => count( $rows ) + 1,
				'employee_no'  => $employee_no,
				'full_name'    => (string) ( $employee['full_name'] ?? '' ),
				'department'   => (string) ( $employee['department'] ?? '' ),
				'cost_center'  => (string) ( $employee['cost_center'] ?? '' ),
				'hat_shoes'    => sprintf( '%d Mũ, %d Giày', $totals['hat'], $totals['shoes'] ),
				'pants_qty'    => $totals['pants'],
				'shirt_qty'    => $totals['shirt'],
				'jacket_qty'   => $totals['jacket'],
				'coat_qty'     => $totals['coat'],
				'total_qty'    => $total_quantity,
			);
		}

		return array(
			'filters'  => $filters,
			'rows'     => $rows,
			'warnings' => $warnings,
			'summary'  => array(
				'employee_count' => count( $employees ),
				'exported_count' => count( $rows ),
				'with_quota'     => $with_quota,
				'total_quantity' => array_sum( array_column( $rows, 'total_qty' ) ),
			),
		);
	}

	private static function get_allocations( $products, $context, $position_id, $report_month ) {
		$allocations = array();
		$warnings    = array();
		$used_rules  = array();
		$matching_rule_ids = self::get_matching_rule_ids( $products, $context, $position_id );
		foreach ( $products as $product ) {
			$rule = self::resolve_rule( $product['rules'], $matching_rule_ids );
			if ( ! $rule || isset( $used_rules[ $rule['rule_id'] ] ) ) {
				continue;
			}
			$used_rules[ $rule['rule_id'] ] = true;
			$monthly = json_decode( (string) ( $rule['monthly_quantities'] ?? '' ), true );
			$quota   = is_array( $monthly ) ? absint( $monthly[ $report_month ] ?? 0 ) : 0;
			if ( $quota <= 0 ) {
				continue;
			}
			$group = self::resolve_export_group( $product );
			if ( $group === '' ) {
				$warnings[] = array(
					'key'     => 'unmapped-' . $product['key'],
					'message' => 'Sản phẩm "' . $product['item_variant'] . '" chưa xác định được nhóm xuất định mức.',
				);
				continue;
			}
			$allocations[] = array( 'rule' => $rule, 'quota' => $quota, 'group' => $group );
		}
		return array( 'allocations' => $allocations, 'warnings' => $warnings );
	}

	private static function get_matching_rule_ids( $products, $context, $position_id ) {
		$matching = array();
		$checked  = array();
		$month_day = preg_match( '/^\d{4}-(\d{2}-\d{2})$/', $context['date_joined'], $parts ) ? $parts[1] : '';
		foreach ( $products as $product ) {
			foreach ( $product['rules'] as $rule ) {
				$rule_id = absint( $rule['rule_id'] );
				if ( isset( $checked[ $rule_id ] ) ) {
					continue;
				}
				$checked[ $rule_id ] = true;
				if ( ! UMS_DB_Annual_Allowance::organization_condition_matches(
					$rule,
					$context['department'],
					$context['team'],
					$context['cost_center'],
					$context['position'],
					$position_id
				) ) {
					continue;
				}
				if ( UMS_DB_Annual_Allowance::scope_matches( $rule, $month_day, $context['date_joined'], $context['evaluation_date'] ) ) {
					$matching[ $rule_id ] = true;
				}
			}
		}
		return $matching;
	}

	private static function get_allocation_cache_key( $context, $position_id ) {
		$date_key = (string) $context['date_joined'];
		if ( preg_match( '/^(\d{4})-\d{2}-\d{2}$/', $date_key, $matches ) ) {
			$evaluation_year = (int) substr( (string) $context['evaluation_date'], 0, 4 );
			if ( (int) $matches[1] < $evaluation_year - 1 ) {
				$date_key = 'legacy';
			}
		}
		return hash(
			'sha256',
			implode(
				'|',
				array(
					UMS_DB_Annual_Allowance::normalize_text( $context['department'] ),
					UMS_DB_Annual_Allowance::normalize_text( $context['team'] ),
					UMS_DB_Annual_Allowance::normalize_text( $context['cost_center'] ),
					UMS_DB_Annual_Allowance::normalize_position_code( $context['position'] ),
					absint( $position_id ),
					$date_key,
					$context['evaluation_date'],
				)
			)
		);
	}

	private static function get_products() {
		$groups = array();
		foreach ( UMS_DB_Inventory::get_all() as $item ) {
			$key = absint( $item['category_id'] ) . '|' . UMS_DB_Annual_Allowance::normalize_text( $item['item_variant'] );
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'key'                  => $key,
					'category_id'          => absint( $item['category_id'] ),
					'parent_category_id'   => absint( $item['parent_category_id'] ?? 0 ),
					'category_name'        => (string) ( $item['category_name'] ?? '' ),
					'parent_category_name' => (string) ( $item['parent_category_name'] ?? '' ),
					'item_variant'         => (string) ( $item['item_variant'] ?? '' ),
					'item_ids'             => array(),
				);
			}
			$groups[ $key ]['item_ids'][] = absint( $item['item_id'] );
		}
		return array_values( $groups );
	}

	private static function resolve_rule( $rules, $matching_rule_ids ) {
		$matched = array();
		foreach ( $rules as $rule ) {
			if ( ! isset( $matching_rule_ids[ absint( $rule['rule_id'] ) ] ) ) {
				continue;
			}
			$rule['_match_score'] = UMS_DB_Annual_Allowance::calculate_match_score( $rule );
			$matched[] = $rule;
		}

		if ( empty( $matched ) ) {
			return null;
		}

		$matrix_scope = '';
		$matrix_score = -1;
		foreach ( $matched as $rule ) {
			if ( ( $rule['apply_type'] ?? '' ) === 'matrix' && ( $rule['target_type'] ?? '' ) === 'organization' && (int) $rule['_match_score'] > $matrix_score ) {
				$matrix_scope = (string) $rule['rule_scope'];
				$matrix_score = (int) $rule['_match_score'];
			}
		}
		if ( $matrix_scope !== '' ) {
			$matched = array_values(
				array_filter(
					$matched,
					function ( $rule ) use ( $matrix_scope ) {
						return ( $rule['target_type'] ?? '' ) === 'organization' && ( $rule['rule_scope'] ?? '' ) === $matrix_scope;
					}
				)
			);
		}

		usort(
			$matched,
			function ( $left, $right ) {
				return (int) $left['_match_score'] === (int) $right['_match_score']
					? (int) $right['rule_id'] <=> (int) $left['rule_id']
					: (int) $right['_match_score'] <=> (int) $left['_match_score'];
			}
		);
		unset( $matched[0]['_match_score'] );
		return $matched[0];
	}

	private static function rule_applies_to_product( $rule, $product ) {
		$type = (string) ( $rule['apply_type'] ?? '' );
		if ( $type === 'item' ) {
			return in_array( absint( $rule['item_id'] ?? 0 ), $product['item_ids'], true );
		}
		if ( $type === 'category' ) {
			$category_id = absint( $rule['category_id'] ?? 0 );
			return $category_id === $product['category_id'] || $category_id === $product['parent_category_id'];
		}
		if ( in_array( $type, array( 'product', 'matrix' ), true ) ) {
			$category_id = absint( $rule['category_id'] ?? 0 );
			$variant     = trim( (string) ( $rule['item_variant'] ?? '' ) );
			if ( $type === 'matrix' && $category_id === 0 && $variant === '' ) {
				return true;
			}
			return $category_id === $product['category_id']
				&& UMS_DB_Annual_Allowance::normalize_text( $variant ) === UMS_DB_Annual_Allowance::normalize_text( $product['item_variant'] );
		}
		return false;
	}

	private static function resolve_export_group( $product ) {
		$text = self::normalize_label(
			implode( ' ', array( $product['parent_category_name'], $product['category_name'], $product['item_variant'] ) )
		);
		$groups = apply_filters(
			'ums_employee_allowance_export_groups',
			array(
				'coat'   => array( 'ao phao' ),
				'jacket' => array( 'ao khoac' ),
				'shoes'  => array( 'giay' ),
				'hat'    => array( 'mu' ),
				'pants'  => array( 'quan' ),
				'shirt'  => array( 'ao' ),
			)
		);
		foreach ( $groups as $group => $keywords ) {
			foreach ( (array) $keywords as $keyword ) {
				if ( strpos( ' ' . $text . ' ', ' ' . self::normalize_label( $keyword ) . ' ' ) !== false ) {
					return (string) $group;
				}
			}
		}
		return '';
	}

	private static function get_remaining_quantity( $quota, $rule, $employee_no, $evaluation_date, $usage_index, $rule_item_ids ) {
		$month_start = strtotime( date( 'Y-m-01 00:00:00', strtotime( $evaluation_date ) ) );
		$month_end   = strtotime( date( 'Y-m-t 23:59:59', strtotime( $evaluation_date ) ) );
		$years       = max( 1, absint( $rule['frequency_years'] ?? 1 ) );
		$times       = max( 1, absint( $rule['frequency_count'] ?? 1 ) );
		$period_end  = strtotime( date( 'Y-m-d 23:59:59', strtotime( $evaluation_date ) ) );
		$period_start = strtotime( date( 'Y-m-d 00:00:00', strtotime( '-' . $years . ' years', strtotime( $evaluation_date ) ) ) );
		$employee_key = strtoupper( trim( (string) $employee_no ) );
		$item_ids = $rule_item_ids[ absint( $rule['rule_id'] ) ] ?? array();
		$used_quantity = 0;
		$used_events = array();

		foreach ( $item_ids as $item_id ) {
			$events = $usage_index[ $employee_key ][ $item_id ] ?? array();
			foreach ( $events as $event ) {
				if ( $event['timestamp'] >= $month_start && $event['timestamp'] <= $month_end ) {
					$used_quantity += absint( $event['quantity'] );
				}
				if ( $event['timestamp'] >= $period_start && $event['timestamp'] <= $period_end ) {
					$used_events[ $event['event_key'] ] = true;
				}
			}
		}
		$used_times = count( $used_events );
		return $used_times >= $times ? 0 : max( 0, absint( $quota ) - $used_quantity );
	}

	private static function get_rule_item_ids( $products ) {
		$map = array();
		foreach ( $products as $product ) {
			foreach ( $product['rules'] as $rule ) {
				$rule_id = absint( $rule['rule_id'] );
				if ( ! isset( $map[ $rule_id ] ) ) {
					$map[ $rule_id ] = array();
				}
				$map[ $rule_id ] = array_merge( $map[ $rule_id ], $product['item_ids'] );
			}
		}
		foreach ( $map as &$item_ids ) {
			$item_ids = array_values( array_unique( array_map( 'absint', $item_ids ) ) );
		}
		unset( $item_ids );
		return $map;
	}

	/**
	 * Đọc lịch sử một lần để báo cáo toàn công ty không tạo truy vấn cho từng CNV/rule.
	 */
	private static function get_usage_index( $employees, $user_ids, $rules, $evaluation_date, $rule_item_ids ) {
		global $wpdb;
		$max_years = 1;
		foreach ( $rules as $rule ) {
			$max_years = max( $max_years, min( 50, absint( $rule['frequency_years'] ?? 1 ) ) );
		}
		$start_date = date( 'Y-m-d 00:00:00', strtotime( '-' . $max_years . ' years', strtotime( $evaluation_date ) ) );
		$end_date   = date( 'Y-m-d 23:59:59', strtotime( $evaluation_date ) );
		$allowed_employees = array();
		$user_to_employee  = array();
		foreach ( $employees as $employee ) {
			$key = strtoupper( trim( (string) $employee['employee_no'] ) );
			$allowed_employees[ $key ] = true;
			if ( isset( $user_ids[ $key ] ) ) {
				$user_to_employee[ absint( $user_ids[ $key ] ) ] = $key;
			}
		}
		$index = array();
		$item_ids = array();
		foreach ( $rule_item_ids as $rule_items ) {
			$item_ids = array_merge( $item_ids, $rule_items );
		}
		$item_ids = array_values( array_unique( array_map( 'absint', $item_ids ) ) );
		if ( empty( $item_ids ) ) {
			return $index;
		}
		$item_placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );

		$request_rows = array();
		$user_id_values = array_keys( $user_to_employee );
		foreach ( array_chunk( $user_id_values, 250 ) as $user_id_batch ) {
			$user_placeholders = implode( ',', array_fill( 0, count( $user_id_batch ), '%d' ) );
			$request_sql = 'SELECT requests.request_id, requests.target_user_id, requests.created_at, details.item_id, details.quantity
				FROM ' . UMS_DB_Request::table() . ' requests
				INNER JOIN ' . UMS_DB_Request::detail_table() . " details ON details.request_id = requests.request_id
				WHERE requests.current_status <> 'rejected' AND requests.created_at >= %s AND requests.created_at <= %s
				AND requests.target_user_id IN ($user_placeholders)
				AND details.item_id IN ($item_placeholders)";
			$request_rows = array_merge(
				$request_rows,
				$wpdb->get_results(
					$wpdb->prepare( $request_sql, array_merge( array( $start_date, $end_date ), $user_id_batch, $item_ids ) ),
					ARRAY_A
				)
			);
		}
		foreach ( $request_rows as $row ) {
			$user_id = absint( $row['target_user_id'] );
			if ( ! isset( $user_to_employee[ $user_id ] ) ) {
				continue;
			}
			self::add_usage_event(
				$index,
				$user_to_employee[ $user_id ],
				absint( $row['item_id'] ),
				'r:' . absint( $row['request_id'] ),
				$row['created_at'],
				$row['quantity']
			);
		}

		$movement_rows = array();
		$employee_keys = array_keys( $allowed_employees );
		$movement_base = "SELECT movement_id, target_user_id, target_employee_no, created_at, item_id, quantity
			FROM " . UMS_DB_Inventory_Movement::table() . "
			WHERE movement_type = 'out' AND request_id IS NULL AND created_at >= %s AND created_at <= %s
			AND item_id IN ($item_placeholders)";
		foreach ( array_chunk( $employee_keys, 250 ) as $employee_batch ) {
			$employee_placeholders = implode( ',', array_fill( 0, count( $employee_batch ), '%s' ) );
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$movement_base . " AND target_employee_no IN ($employee_placeholders)",
					array_merge( array( $start_date, $end_date ), $item_ids, $employee_batch )
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$movement_rows[ absint( $row['movement_id'] ) ] = $row;
			}
		}
		foreach ( array_chunk( $user_id_values, 250 ) as $user_id_batch ) {
			$user_placeholders = implode( ',', array_fill( 0, count( $user_id_batch ), '%d' ) );
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					$movement_base . " AND target_user_id IN ($user_placeholders)",
					array_merge( array( $start_date, $end_date ), $item_ids, $user_id_batch )
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$movement_rows[ absint( $row['movement_id'] ) ] = $row;
			}
		}
		foreach ( $movement_rows as $row ) {
			$employee_key = strtoupper( trim( (string) $row['target_employee_no'] ) );
			if ( $employee_key === '' ) {
				$employee_key = $user_to_employee[ absint( $row['target_user_id'] ) ] ?? '';
			}
			if ( $employee_key === '' || ! isset( $allowed_employees[ $employee_key ] ) ) {
				continue;
			}
			self::add_usage_event(
				$index,
				$employee_key,
				absint( $row['item_id'] ),
				'm:' . absint( $row['movement_id'] ),
				$row['created_at'],
				$row['quantity']
			);
		}
		return $index;
	}

	private static function add_usage_event( &$index, $employee_key, $item_id, $event_key, $created_at, $quantity ) {
		$timestamp = strtotime( (string) $created_at );
		if ( ! $timestamp || $item_id <= 0 ) {
			return;
		}
		$index[ $employee_key ][ $item_id ][] = array(
			'event_key' => $event_key,
			'timestamp' => $timestamp,
			'quantity'  => absint( $quantity ),
		);
	}

	private static function get_position_ids() {
		$map = array();
		foreach ( UMS_DB_Position::get_active() as $position ) {
			$map[ UMS_DB_Annual_Allowance::normalize_position_code( $position['position_code'] ) ] = absint( $position['position_id'] );
		}
		return $map;
	}

	private static function get_employee_user_ids( $employees ) {
		global $wpdb;
		$employee_codes = array_values(
			array_unique(
				array_filter(
					array_map(
						function ( $employee ) {
							return strtoupper( trim( (string) ( $employee['employee_no'] ?? '' ) ) );
						},
						$employees
					)
				)
			)
		);
		$rows = array();
		foreach ( array_chunk( $employee_codes, 250 ) as $code_batch ) {
			$placeholders = implode( ',', array_fill( 0, count( $code_batch ), '%s' ) );
			$login_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT ID, user_login, '' AS employee_code FROM {$wpdb->users} WHERE user_login IN ($placeholders)",
					$code_batch
				),
				ARRAY_A
			);
			$meta_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT users.ID, users.user_login, employee.meta_value AS employee_code
					FROM {$wpdb->usermeta} employee
					INNER JOIN {$wpdb->users} users ON users.ID = employee.user_id
					WHERE employee.meta_key = 'ums_employee_code' AND employee.meta_value IN ($placeholders)",
					$code_batch
				),
				ARRAY_A
			);
			$rows = array_merge( $rows, $login_rows, $meta_rows );
		}
		$map = array();
		foreach ( $rows as $row ) {
			foreach ( array( $row['user_login'], $row['employee_code'] ) as $employee_no ) {
				$employee_no = strtoupper( trim( (string) $employee_no ) );
				if ( $employee_no !== '' ) {
					$map[ $employee_no ] = absint( $row['ID'] );
				}
			}
		}
		return $map;
	}

	private static function add_warning( &$warnings, &$keys, $key, $message ) {
		if ( ! isset( $keys[ $key ] ) ) {
			$keys[ $key ] = true;
			$warnings[] = $message;
		}
	}

	private static function normalize_label( $value ) {
		$value = strtolower( remove_accents( (string) $value ) );
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', $value ) );
	}

	public static function stream( $report ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'Máy chủ PHP chưa bật ZipArchive.' );
		}
		$temp_file = tempnam( get_temp_dir(), 'ums-allowance-report-' );
		if ( false === $temp_file ) {
			throw new RuntimeException( 'Không tạo được file báo cáo tạm.' );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new RuntimeException( 'Không tạo được workbook định mức.' );
		}

		$zip->addFromString( '[Content_Types].xml', self::content_types_xml() );
		$zip->addFromString( '_rels/.rels', self::root_relationships_xml() );
		$zip->addFromString( 'docProps/app.xml', self::app_properties_xml() );
		$zip->addFromString( 'docProps/core.xml', self::core_properties_xml() );
		$zip->addFromString( 'xl/workbook.xml', self::workbook_xml() );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', self::workbook_relationships_xml() );
		$zip->addFromString( 'xl/styles.xml', self::styles_xml() );
		$zip->addFromString( 'xl/worksheets/sheet1.xml', self::worksheet_xml( $report['rows'] ) );
		$zip->close();

		$filters = $report['filters'];
		$scope = $filters['cost_center_prefix'] !== ''
			? 'CC' . $filters['cost_center_prefix']
			: 'CC1300-4400-4900';
		$filename = sprintf( 'UMS-dinh-muc-CNV-%s-T%d-%d-%s.xlsx', $scope, $filters['report_month'], $filters['report_year'], gmdate( 'Ymd-His' ) );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $temp_file ) );
		readfile( $temp_file );
		unlink( $temp_file );
		exit;
	}

	private static function worksheet_xml( $rows ) {
		$headers = array( 'Stt', 'Mã nhân viên', 'Họ và tên', 'Phòng', 'Cost center', 'Mũ & Giày định mức', 'SL quần định mức', 'SL áo phông định mức', 'SL áo khoác định mức', 'SL áo phao định mức' );
		$xml_rows = array( self::xlsx_row( 1, $headers, true ) );
		foreach ( $rows as $index => $row ) {
			$xml_rows[] = self::xlsx_row(
				$index + 2,
				array( $index + 1, $row['employee_no'], $row['full_name'], $row['department'], $row['cost_center'], $row['hat_shoes'], $row['pants_qty'], $row['shirt_qty'], $row['jacket_qty'], $row['coat_qty'] ),
				false
			);
		}
		$last_row = max( 1, count( $rows ) + 1 );
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<dimension ref="A1:J' . $last_row . '"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
			. '<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="17" customWidth="1"/><col min="3" max="4" width="30" customWidth="1"/><col min="5" max="5" width="18" customWidth="1"/><col min="6" max="6" width="24" customWidth="1"/><col min="7" max="10" width="22" customWidth="1"/></cols>'
			. '<sheetData>' . implode( '', $xml_rows ) . '</sheetData><autoFilter ref="A1:J' . $last_row . '"/></worksheet>';
	}

	private static function xlsx_row( $number, $values, $header ) {
		$cells = '';
		foreach ( $values as $index => $value ) {
			$reference = self::column_name( $index + 1 ) . $number;
			$style = $header ? 1 : 2;
			if ( is_int( $value ) || is_float( $value ) ) {
				$cells .= '<c r="' . $reference . '" s="' . $style . '"><v>' . $value . '</v></c>';
			} else {
				$cells .= '<c r="' . $reference . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml( $value ) . '</t></is></c>';
			}
		}
		return '<row r="' . $number . '">' . $cells . '</row>';
	}

	private static function column_name( $number ) {
		$name = '';
		while ( $number > 0 ) {
			$number--;
			$name = chr( 65 + ( $number % 26 ) ) . $name;
			$number = intdiv( $number, 26 );
		}
		return $name;
	}

	private static function xml( $value ) {
		return htmlspecialchars( (string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	private static function content_types_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
	}

	private static function root_relationships_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
	}

	private static function workbook_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::SHEET_NAME . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
	}

	private static function workbook_relationships_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
	}

	private static function styles_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E78"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFD9E2F3"/></left><right style="thin"><color rgb="FFD9E2F3"/></right><top style="thin"><color rgb="FFD9E2F3"/></top><bottom style="thin"><color rgb="FFD9E2F3"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
	}

	private static function app_properties_xml() {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>UMS</Application></Properties>';
	}

	private static function core_properties_xml() {
		$now = gmdate( 'Y-m-d\TH:i:s\Z' );
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>UMS</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
	}
}
