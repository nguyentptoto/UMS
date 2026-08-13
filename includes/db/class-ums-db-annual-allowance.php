<?php
/**
 * Data layer for annual uniform allowance rules.
 */
class UMS_DB_Annual_Allowance extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_annual_allowance_rules';
	}

	public static function import_table() {
		return self::prefix() . 'uniform_allowance_import_batches';
	}

	public static function supports_flexible_rules() {
		static $supported = null;
		if ( null !== $supported ) {
			return $supported;
		}
		$columns = self::db()->get_col( 'SHOW COLUMNS FROM ' . self::table() );
		$supported = in_array( 'rule_scope', $columns, true ) && in_array( 'department', $columns, true );
		return $supported;
	}

	public static function import_table_exists() {
		$table = self::import_table();
		return self::db()->get_var( self::db()->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public static function is_import_ready() {
		return self::supports_flexible_rules() && self::import_table_exists();
	}

	public static function get_all( $args = array() ) {
		$table           = self::table();
		$inventory_table = UMS_DB_Inventory::table();
		$category_table  = UMS_DB_Product_Category::table();
		$position_table  = UMS_DB_Position::table();
		$flexible_rules  = self::supports_flexible_rules();
		$args            = wp_parse_args(
			$args,
			array(
				'search'      => '',
				'apply_type'  => '',
				'target_type' => '',
				'status'      => '',
				'limit'       => 1000,
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
			$search_columns = array(
				'item_child.category_name', 'item_parent.category_name', 'apply_category.category_name',
				'apply_parent.category_name', 'inventory.item_variant', 'inventory.size',
				'position.position_name', 'position.position_code',
			);
			if ( $flexible_rules ) {
				$search_columns = array_merge( $search_columns, array( 'rules.item_variant', 'rules.source_product_name', 'rules.department', 'rules.team', 'rules.cost_center', 'rules.position_code' ) );
			}
			$where[] = '(' . implode( ' LIKE %s OR ', $search_columns ) . ' LIKE %s)';
			$params  = array_merge( $params, array_fill( 0, count( $search_columns ), $like ) );
		}

		$item_variant_select = $flexible_rules ? 'COALESCE(inventory.item_variant, rules.item_variant)' : 'inventory.item_variant';
		$position_code_select = $flexible_rules ? 'COALESCE(position.position_code, rules.position_code)' : 'position.position_code';

		$sql = "
			SELECT rules.*, $item_variant_select AS item_variant, inventory.size, inventory.stock_qty,
				item_child.category_name, item_parent.category_name AS parent_category_name,
				apply_category.category_name AS apply_category_name,
				apply_parent.category_name AS apply_parent_category_name,
				$position_code_select AS position_code, position.position_name
			FROM $table rules
			LEFT JOIN $inventory_table inventory ON inventory.item_id = rules.item_id
			LEFT JOIN $category_table item_child ON item_child.category_id = inventory.category_id
			LEFT JOIN $category_table item_parent ON item_parent.category_id = item_child.parent_id
			LEFT JOIN $category_table apply_category ON apply_category.category_id = rules.category_id
			LEFT JOIN $category_table apply_parent ON apply_parent.category_id = apply_category.parent_id
			LEFT JOIN $position_table position ON position.position_id = rules.position_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY rules.is_active DESC, rules.apply_type ASC, apply_parent.category_name ASC, apply_category.category_name ASC, item_parent.category_name ASC, item_child.category_name ASC, inventory.item_variant ASC, inventory.size ASC
			LIMIT %d';
		$params[] = max( 1, min( 5000, absint( $args['limit'] ) ) );

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

	public static function get_active_rule_for_item( $item_id, $position_id = 0, $context = array() ) {
		if ( self::supports_flexible_rules() ) {
			return self::get_flexible_rule_for_item( $item_id, $position_id, $context );
		}

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

	/**
	 * Chọn rule phù hợp nhất theo dữ liệu Sơ đồ tổ chức TVN và ngày vào công ty.
	 */
	private static function get_flexible_rule_for_item( $item_id, $position_id, $context ) {
		$table           = self::table();
		$inventory_table = UMS_DB_Inventory::table();
		$category_table  = UMS_DB_Product_Category::table();
		$context         = wp_parse_args(
			$context,
			array(
				'department'  => '',
				'team'        => '',
				'cost_center' => '',
				'position'    => '',
				'date_joined' => '',
				'evaluation_date' => current_time( 'Y-m-d' ),
			)
		);
		$item = UMS_DB_Inventory::get_by_id( $item_id );
		if ( ! $item ) {
			return null;
		}

		$department  = trim( (string) $context['department'] );
		$team        = trim( (string) $context['team'] );
		$cost_center = trim( (string) $context['cost_center'] );
		$position    = self::normalize_position_code( $context['position'] );
		$date_joined = sanitize_text_field( (string) $context['date_joined'] );
		$month_day   = preg_match( '/^\d{4}-(\d{2}-\d{2})$/', $date_joined, $matches ) ? $matches[1] : '';
		$matrix_scope = self::get_applicable_matrix_scope( $context, $department, $team, $cost_center, $position, $position_id );

		$sql = self::db()->prepare(
			"SELECT rules.*, inventory.category_id AS item_category_id, child.parent_id AS item_parent_category_id,
				inventory.item_variant AS inventory_item_variant, inventory.size, child.category_name,
				parent.category_name AS parent_category_name
			FROM $table rules
			INNER JOIN $inventory_table inventory ON inventory.item_id = %d
			LEFT JOIN $category_table child ON child.category_id = inventory.category_id
			LEFT JOIN $category_table parent ON parent.category_id = child.parent_id
			WHERE rules.is_active = 1
				AND (
					(rules.apply_type = 'item' AND rules.item_id = inventory.item_id)
					OR (rules.apply_type = 'category' AND rules.category_id IN (inventory.category_id, child.parent_id))
					OR (rules.apply_type = 'product' AND rules.category_id = inventory.category_id AND rules.item_variant = inventory.item_variant)
				)",
			absint( $item_id )
		);
		$candidates = self::db()->get_results( $sql, ARRAY_A );
		$matched    = array();

		foreach ( $candidates as $rule ) {
			if ( $matrix_scope !== '' && ( $rule['target_type'] !== 'organization' || $rule['rule_scope'] !== $matrix_scope ) ) {
				continue;
			}
			if ( ! self::organization_condition_matches( $rule, $department, $team, $cost_center, $position, $position_id ) ) {
				continue;
			}

			if ( ! self::scope_matches( $rule, $month_day, $date_joined, $context['evaluation_date'] ) ) {
				continue;
			}

			$rule['_match_score'] = self::calculate_match_score( $rule );
			$matched[]            = $rule;
		}

		if ( empty( $matched ) ) {
			return null;
		}

		usort(
			$matched,
			function( $left, $right ) {
				if ( (int) $left['_match_score'] === (int) $right['_match_score'] ) {
					return (int) $right['rule_id'] <=> (int) $left['rule_id'];
				}
				return (int) $right['_match_score'] <=> (int) $left['_match_score'];
			}
		);

		unset( $matched[0]['_match_score'] );
		return $matched[0];
	}

	/**
	 * Xác định ma trận đang chi phối nhân viên. Khi ma trận có giá trị 0 cho một
	 * sản phẩm, hệ thống không được rơi xuống rule tổng quát cũ và cấp nhầm.
	 */
	private static function get_applicable_matrix_scope( $context, $department, $team, $cost_center, $position, $position_id ) {
		static $scope_cache = array();
		$cache_key = md5( wp_json_encode( array( $department, $team, $cost_center, $position, $position_id, $context['date_joined'], $context['evaluation_date'] ) ) );
		if ( array_key_exists( $cache_key, $scope_cache ) ) {
			return $scope_cache[ $cache_key ];
		}

		$sql = self::db()->prepare(
			'SELECT DISTINCT rule_scope, target_type, position_id, department, team, cost_center, position_code,
				employment_start_md, employment_end_md, priority
			FROM ' . self::table() . "
			WHERE is_active = 1 AND target_type = 'organization'
				AND (department = '' OR department = %s)
				AND (team = '' OR team = %s)
				AND (cost_center = '' OR cost_center = %s)",
			$department,
			$team,
			$cost_center
		);
		$rows       = self::db()->get_results( $sql, ARRAY_A );
		$month_day  = preg_match( '/^\d{4}-(\d{2}-\d{2})$/', (string) $context['date_joined'], $matches ) ? $matches[1] : '';
		$best_scope = '';
		$best_score = -1;

		foreach ( $rows as $rule ) {
			if ( ! self::organization_condition_matches( $rule, $department, $team, $cost_center, $position, $position_id ) ) {
				continue;
			}
			if ( ! self::scope_matches( $rule, $month_day, $context['date_joined'], $context['evaluation_date'] ) ) {
				continue;
			}

			$score = self::calculate_match_score( $rule );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_scope = $rule['rule_scope'];
			}
		}

		$scope_cache[ $cache_key ] = $best_scope;
		return $best_scope;
	}

	private static function organization_condition_matches( $rule, $department, $team, $cost_center, $position, $position_id ) {
		if ( ! empty( $rule['department'] ) && self::normalize_text( $rule['department'] ) !== self::normalize_text( $department ) ) {
			return false;
		}
		if ( ! empty( $rule['team'] ) && self::normalize_text( $rule['team'] ) !== self::normalize_text( $team ) ) {
			return false;
		}
		if ( ! empty( $rule['cost_center'] ) && self::normalize_text( $rule['cost_center'] ) !== self::normalize_text( $cost_center ) ) {
			return false;
		}
		if ( ! empty( $rule['position_code'] ) && self::normalize_position_code( $rule['position_code'] ) !== $position ) {
			return false;
		}
		if ( $rule['target_type'] === 'position' && absint( $rule['position_id'] ) !== absint( $position_id ) ) {
			return false;
		}
		return true;
	}

	private static function scope_matches( $rule, $month_day, $date_joined, $evaluation_date ) {
		$scope = isset( $rule['rule_scope'] ) ? sanitize_key( $rule['rule_scope'] ) : 'annual';
		if ( $scope === 'annual' ) {
			return true;
		}
		if ( ! in_array( $scope, array( 'newcomer', 'newcomer_september' ), true ) || $month_day === '' || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_joined ) ) {
			return false;
		}

		$evaluation_timestamp = strtotime( $evaluation_date );
		$joined_timestamp     = strtotime( $date_joined );
		if ( ! $evaluation_timestamp || ! $joined_timestamp || $joined_timestamp > $evaluation_timestamp ) {
			return false;
		}

		$evaluation_year  = (int) date( 'Y', $evaluation_timestamp );
		$evaluation_month = (int) date( 'n', $evaluation_timestamp );
		$joined_year      = (int) date( 'Y', $joined_timestamp );
		$joined_month     = (int) date( 'n', $joined_timestamp );

		if ( $scope === 'newcomer_september' && ( $evaluation_month !== 9 || $joined_year !== $evaluation_year ) ) {
			return false;
		}
		if ( $scope === 'newcomer' ) {
			$is_current_year = $joined_year === $evaluation_year;
			$is_previous_winter_cycle = $evaluation_month <= 3 && $joined_year === $evaluation_year - 1 && $joined_month >= 9;
			if ( ! $is_current_year && ! $is_previous_winter_cycle ) {
				return false;
			}
		}

		$start = (string) $rule['employment_start_md'];
		$end   = (string) $rule['employment_end_md'];
		if ( $start === '' || $end === '' ) {
			return false;
		}

		return $start <= $end
			? $month_day >= $start && $month_day <= $end
			: $month_day >= $start || $month_day <= $end;
	}

	private static function calculate_match_score( $rule ) {
		$score = (int) ( $rule['priority'] ?? 0 );
		$score += ! empty( $rule['department'] ) ? 16 : 0;
		$score += ! empty( $rule['team'] ) ? 32 : 0;
		$score += ! empty( $rule['cost_center'] ) ? 64 : 0;
		$score += ! empty( $rule['position_code'] ) ? 8 : 0;
		$score += ( $rule['apply_type'] ?? '' ) === 'item' ? 4 : 0;
		$score += ( $rule['apply_type'] ?? '' ) === 'product' ? 2 : 0;
		$score += in_array( $rule['rule_scope'] ?? '', array( 'newcomer', 'newcomer_september' ), true ) ? 128 : 0;
		return $score;
	}

	private static function normalize_text( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	public static function normalize_position_code( $value ) {
		$value = strtoupper( preg_replace( '/\s+/u', '', trim( (string) $value ) ) );
		return $value;
	}

	/**
	 * Sinh khóa ổn định cho cả rule nhập thủ công và rule import Excel.
	 */
	public static function build_rule_key( $rule ) {
		return hash(
			'sha256',
			implode(
				'|',
				array(
					$rule['rule_scope'] ?? 'annual',
					$rule['apply_type'] ?? 'item',
					absint( $rule['category_id'] ?? 0 ),
					absint( $rule['item_id'] ?? 0 ),
					$rule['item_variant'] ?? '',
					$rule['target_type'] ?? 'all',
					absint( $rule['position_id'] ?? 0 ),
					$rule['department'] ?? '',
					$rule['team'] ?? '',
					$rule['cost_center'] ?? '',
					self::normalize_position_code( $rule['position_code'] ?? '' ),
					$rule['employment_start_md'] ?? '',
					$rule['employment_end_md'] ?? '',
				)
			)
		);
	}

	/**
	 * Tạo context kiểm tra định mức từ hồ sơ và Sơ đồ tổ chức TVN.
	 */
	public static function get_employee_context( $profile ) {
		$user_id       = absint( $profile['user_id'] ?? 0 );
		$employee_code = trim( (string) ( $profile['employee_code'] ?? '' ) );
		$organization  = UMS_DB_Organization::get_by_wp_user_id( $user_id, $employee_code );
		$organization  = is_array( $organization ) ? $organization : array();
		$employee_code = ! empty( $organization['employee_no'] )
			? trim( (string) $organization['employee_no'] )
			: $employee_code;

		$date_joined = self::normalize_context_date( $organization['date_joined'] ?? '' );
		$date_source = $date_joined !== '' ? 'organization' : '';
		if ( $date_joined === '' && $user_id > 0 ) {
			$date_joined = self::normalize_context_date( get_user_meta( $user_id, 'ums_date_joined', true ) );
			$date_source = $date_joined !== '' ? 'usermeta' : '';
		}
		if ( $date_joined === '' ) {
			$date_joined = self::normalize_context_date( $profile['date_joined'] ?? '' );
			$date_source = $date_joined !== '' ? 'legacy_profile' : '';
		}

		return array(
			'department' => ! empty( $organization['department'] ) ? $organization['department'] : ( $profile['department'] ?? '' ),
			'team' => ! empty( $organization['team'] ) ? $organization['team'] : '',
			'cost_center' => ! empty( $organization['cost_center'] ) ? $organization['cost_center'] : '',
			'position' => ! empty( $organization['position'] ) ? $organization['position'] : ( $profile['job_position'] ?? '' ),
			'date_joined' => $date_joined,
			'date_joined_source' => $date_source,
			'employee_no' => $employee_code,
			'evaluation_date' => current_time( 'Y-m-d' ),
		);
	}

	/**
	 * Chỉ nhận ngày chuẩn để việc so sánh khoảng ngày vào của CNV mới ổn định.
	 */
	private static function normalize_context_date( $value ) {
		$value = trim( (string) $value );
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', explode( '-', $value ) );
		return checkdate( $month, $day, $year ) ? $value : '';
	}

	public static function upsert_import_rule( $data ) {
		$existing_id = self::db()->get_var(
			self::db()->prepare( 'SELECT rule_id FROM ' . self::table() . ' WHERE rule_key = %s LIMIT 1', $data['rule_key'] )
		);
		if ( $existing_id ) {
			$result = self::update( $existing_id, $data );
			return array( 'action' => 'updated', 'result' => $result, 'rule_id' => (int) $existing_id );
		}

		$result = self::insert( $data );
		return array( 'action' => 'inserted', 'result' => $result, 'rule_id' => (int) self::db()->insert_id );
	}

	public static function upsert_import_rules_batch( $rules ) {
		if ( empty( $rules ) ) {
			return array( 'inserted' => 0, 'updated' => 0 );
		}

		$fields = array(
			'rule_key', 'rule_scope', 'apply_type', 'category_id', 'item_id', 'item_variant', 'source_product_name',
			'target_type', 'position_id', 'department', 'team', 'cost_center', 'position_code', 'employment_start_md',
			'employment_end_md', 'eligibility_note', 'frequency_count', 'frequency_years', 'monthly_quantities',
			'priority', 'source_batch_id', 'is_active',
		);
		$formats = array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d' );
		$keys    = array_column( $rules, 'rule_key' );
		$key_placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$existing_keys = self::db()->get_col(
			self::db()->prepare(
				'SELECT rule_key FROM ' . self::table() . " WHERE rule_key IN ($key_placeholders)",
				$keys
			)
		);
		$values_sql   = array();
		$params       = array();

		foreach ( $rules as $rule ) {
			$values_sql[] = '(' . implode( ',', $formats ) . ')';
			foreach ( $fields as $field ) {
				$params[] = $rule[ $field ];
			}
		}

		$updates = array();
		foreach ( array_slice( $fields, 1 ) as $field ) {
			$updates[] = "$field = VALUES($field)";
		}
		$sql = 'INSERT INTO ' . self::table() . ' (`' . implode( '`,`', $fields ) . '`) VALUES '
			. implode( ',', $values_sql ) . ' ON DUPLICATE KEY UPDATE ' . implode( ',', $updates );
		$result = self::db()->query( self::db()->prepare( $sql, $params ) );
		if ( false === $result ) {
			return false;
		}

		$updated = count( $existing_keys );
		return array( 'inserted' => count( $rules ) - $updated, 'updated' => $updated );
	}

	public static function create_import_batch( $data ) {
		$result = self::db()->insert(
			self::import_table(),
			$data,
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s' )
		);
		return $result === false ? 0 : (int) self::db()->insert_id;
	}

	public static function update_import_batch( $batch_id, $data ) {
		$formats = array();
		$format_map = array(
			'import_status' => '%s', 'total_rules' => '%d', 'inserted_rules' => '%d',
			'updated_rules' => '%d', 'error_count' => '%d', 'error_log' => '%s', 'completed_at' => '%s',
		);
		foreach ( array_keys( $data ) as $key ) {
			$formats[] = isset( $format_map[ $key ] ) ? $format_map[ $key ] : '%s';
		}
		return self::db()->update( self::import_table(), $data, array( 'batch_id' => absint( $batch_id ) ), $formats, array( '%d' ) );
	}

	public static function deactivate_other_import_rules( $batch_id ) {
		return self::db()->query(
			self::db()->prepare(
				'UPDATE ' . self::table() . ' SET is_active = 0 WHERE source_batch_id IS NOT NULL AND source_batch_id <> %d',
				absint( $batch_id )
			)
		);
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
			'rule_key'           => '%s',
			'rule_scope'         => '%s',
			'apply_type'         => '%s',
			'category_id'        => '%d',
			'item_id'            => '%d',
			'item_variant'       => '%s',
			'source_product_name'=> '%s',
			'target_type'        => '%s',
			'position_id'        => '%d',
			'department'         => '%s',
			'team'               => '%s',
			'cost_center'        => '%s',
			'position_code'      => '%s',
			'employment_start_md'=> '%s',
			'employment_end_md'  => '%s',
			'eligibility_note'   => '%s',
			'frequency_count'    => '%d',
			'frequency_years'    => '%d',
			'monthly_quantities' => '%s',
			'priority'           => '%d',
			'source_batch_id'    => '%d',
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
