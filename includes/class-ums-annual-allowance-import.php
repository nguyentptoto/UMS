<?php
/**
 * Phân tích và import template định mức cấp phát từ Excel.
 */
class UMS_Annual_Allowance_Import {
	const PREVIEW_TRANSIENT_PREFIX = 'ums_allowance_preview_';
	const PREVIEW_TTL = 3600;

	private static $sheet_configs = array(
		'Phát T4' => array(
			'scope' => 'annual', 'header' => 1, 'start' => 2, 'month' => 4,
		),
		'Phát T9' => array(
			'scope' => 'annual', 'header' => 1, 'start' => 2, 'month' => 9,
		),
	);

	/**
	 * Ma trận sản phẩm cố định E:AC dùng chung cho Phát T4 và Phát T9.
	 */
	public static function get_product_columns() {
		return array(
			'E'  => 'Áo phông cộc tay',
			'F'  => 'Quần CN',
			'G'  => 'Áo XLNT',
			'H'  => 'Áo phông tím cộc tay',
			'I'  => 'Áo kỹ thuật',
			'J'  => 'Quần kỹ thuật',
			'K'  => 'Áo khoác kỹ thuật',
			'L'  => 'Áo khoác CN',
			'M'  => 'Áo phao',
			'N'  => 'Mũ hồng',
			'O'  => 'Mũ phối trắng',
			'P'  => 'Mũ phối ghi',
			'Q'  => 'Mũ phối hồng',
			'R'  => 'Mũ phối tím',
			'S'  => 'Mũ phối xanh biển',
			'T'  => 'Mũ phối xi măng',
			'U'  => 'Mũ phối trắng ngà',
			'V'  => 'Mũ phối xanh lá',
			'W'  => 'Mũ Xanh lá',
			'X'  => 'Mũ xanh biển',
			'Y'  => 'Mũ đỏ',
			'Z'  => 'Giầy KPR O-775',
			'AA' => 'Giầy KPR O-010',
			'AB' => 'Giầy Simon TS5511',
			'AC' => 'Giầy Simon TS7011',
		);
	}

	public static function analyze( $file_path, $file_name ) {
		$reader = new UMS_XLSX_Reader( $file_path );
		$rules  = array();
		$errors = array();

		foreach ( self::$sheet_configs as $sheet_name => $config ) {
			if ( ! $reader->has_sheet( $sheet_name ) ) {
				$errors[] = 'Không tìm thấy sheet bắt buộc: ' . $sheet_name;
				continue;
			}

			self::collect_sheet_rules( $reader->read_sheet( $sheet_name ), $sheet_name, $config, $rules, $errors );
		}

		foreach ( $rules as &$rule ) {
			if ( $rule['rule_scope'] === 'annual' ) {
				$rule['frequency_count'] = count( array_filter( $rule['monthly_quantities'] ) );
			}
			$rule['monthly_quantities'] = wp_json_encode( $rule['monthly_quantities'] );
		}
		unset( $rule );

		$product_names = array_values( self::get_product_columns() );
		$used_product_names = array_values( array_unique( array_column( $rules, 'source_product_name' ) ) );

		return array(
			'file_name'     => sanitize_file_name( $file_name ),
			'file_hash'     => hash_file( 'sha256', $file_path ),
			'rules'         => array_values( $rules ),
			'product_names' => $product_names,
			'used_product_names' => $used_product_names,
			'errors'        => array_values( array_unique( $errors ) ),
			'summary'       => self::build_summary( $rules ),
		);
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::PREVIEW_TRANSIENT_PREFIX . $token, $preview, self::PREVIEW_TTL ) ) {
			throw new RuntimeException( 'Không lưu được dữ liệu xem trước. Hãy kiểm tra dung lượng database hoặc object cache.' );
		}
		return $token;
	}

	public static function get_preview( $token ) {
		return get_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function delete_preview( $token ) {
		delete_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function import( $preview, $mappings, $user_id ) {
		$errors = array();
		$mapped = array();

		$required_products = isset( $preview['used_product_names'] ) && is_array( $preview['used_product_names'] )
			? $preview['used_product_names']
			: $preview['product_names'];
		foreach ( $required_products as $product_name ) {
			$map_key     = hash( 'sha256', $product_name );
			$mapping_raw = isset( $mappings[ $map_key ] ) ? sanitize_text_field( $mappings[ $map_key ] ) : '';
			$parts       = explode( '|', $mapping_raw, 2 );
			$category_id = isset( $parts[0] ) ? absint( $parts[0] ) : 0;
			$item_variant = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '';

			if ( $category_id <= 0 || $item_variant === '' || ! UMS_DB_Inventory::product_group_exists( $category_id, $item_variant ) ) {
				$errors[] = 'Chưa ánh xạ sản phẩm Excel: ' . $product_name;
				continue;
			}
			$mapped[ $product_name ] = array( 'category_id' => $category_id, 'item_variant' => $item_variant );
		}

		if ( ! empty( $errors ) ) {
			return array( 'success' => false, 'errors' => $errors );
		}

		$batch_id = UMS_DB_Annual_Allowance::create_import_batch(
			array(
				'file_name' => $preview['file_name'], 'file_hash' => $preview['file_hash'],
				'import_status' => 'processing', 'total_rules' => count( $preview['rules'] ),
				'inserted_rules' => 0, 'updated_rules' => 0, 'error_count' => 0,
				'error_log' => '', 'imported_by' => absint( $user_id ), 'created_at' => current_time( 'mysql' ),
			)
		);

		if ( ! $batch_id ) {
			return array( 'success' => false, 'errors' => array( 'Không tạo được phiên import. Hãy kiểm tra bảng import trong ums.sql.' ) );
		}

		$inserted = 0;
		$updated  = 0;
		$prepared_rules = array();
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $preview['rules'] as $rule ) {
			$product = $mapped[ $rule['source_product_name'] ];
			$rule['category_id']    = $product['category_id'];
			$rule['item_variant']    = $product['item_variant'];
			$rule['source_batch_id'] = $batch_id;
			$rule['rule_key']        = UMS_DB_Annual_Allowance::build_rule_key( $rule );
			unset( $rule['source_sheet'], $rule['source_row'] );
			$prepared_rules[] = $rule;
		}

		foreach ( array_chunk( $prepared_rules, 100 ) as $rule_batch ) {
			$result = UMS_DB_Annual_Allowance::upsert_import_rules_batch( $rule_batch );
			if ( false === $result ) {
				$errors[] = 'Không ghi được batch rule: ' . UMS_DB_Annual_Allowance::get_last_error();
				break;
			}
			$inserted += $result['inserted'];
			$updated  += $result['updated'];
		}

		if ( empty( $errors ) && false === UMS_DB_Annual_Allowance::deactivate_other_import_rules( $batch_id ) ) {
			$errors[] = 'Không vô hiệu hóa được các rule thuộc lần import cũ.';
		}

		if ( empty( $errors ) ) {
			$wpdb->query( 'COMMIT' );
		} else {
			$wpdb->query( 'ROLLBACK' );
			$inserted = 0;
			$updated  = 0;
		}

		UMS_DB_Annual_Allowance::update_import_batch(
			$batch_id,
			array(
				'import_status' => empty( $errors ) ? 'completed' : 'failed',
				'inserted_rules' => $inserted, 'updated_rules' => $updated,
				'error_count' => count( $errors ), 'error_log' => wp_json_encode( $errors, JSON_UNESCAPED_UNICODE ),
				'completed_at' => current_time( 'mysql' ),
			)
		);

		return array(
			'success' => empty( $errors ), 'batch_id' => $batch_id, 'inserted' => $inserted,
			'updated' => $updated, 'errors' => $errors,
		);
	}

	private static function collect_sheet_rules( $rows, $sheet_name, $config, &$rules, &$errors ) {
		if ( empty( $rows[ $config['header'] ] ) ) {
			$errors[] = 'Sheet ' . $sheet_name . ' không có dòng tiêu đề hợp lệ.';
			return;
		}

		$layout = self::discover_sheet_layout( $rows[ $config['header'] ] );
		if ( empty( $layout['department'] ) || empty( $layout['position'] ) || empty( $layout['products'] ) ) {
			$errors[] = 'Sheet ' . $sheet_name . ' thiếu cột Bộ phận, Vị trí hoặc các cột sản phẩm.';
			return;
		}
		$products = self::get_product_columns();
		foreach ( $products as $column => $product_name ) {
			$actual_header = self::normalize_space( $rows[ $config['header'] ][ $column ] ?? '' );
			if ( self::normalize_header( $actual_header ) !== self::normalize_header( $product_name ) ) {
				$errors[] = sprintf( 'Sheet %s cột %s phải là "%s".', $sheet_name, $column, $product_name );
			}
		}

		foreach ( $rows as $row_number => $row ) {
			if ( $row_number < $config['start'] || empty( $row[ $layout['department'] ] ) ) {
				continue;
			}

			$condition = array(
				'department' => self::normalize_space( $row[ $layout['department'] ] ),
				'team' => $layout['team'] !== '' ? self::normalize_space( $row[ $layout['team'] ] ?? '' ) : '',
				'cost_center' => $layout['cost_center'] !== '' ? self::normalize_space( $row[ $layout['cost_center'] ] ?? '' ) : '',
				'position_code' => UMS_DB_Annual_Allowance::normalize_position_code( $row[ $layout['position'] ] ?? '' ),
			);
			$period = array( '', '' );
			$position_codes = array_filter( array_map( 'trim', explode( ',', $condition['position_code'] ) ) );
			if ( empty( $position_codes ) ) {
				$position_codes = array( '' );
			}

			foreach ( $position_codes as $position_code ) {
				foreach ( $products as $column => $product_name ) {
					$quantity = isset( $row[ $column ] ) && is_numeric( $row[ $column ] ) ? max( 0, (int) $row[ $column ] ) : 0;
					if ( $quantity <= 0 ) {
						continue;
					}

					$key = implode( '|', array( $config['scope'], $condition['department'], $condition['team'], $condition['cost_center'], $position_code, $period[0], $period[1], $product_name ) );
					if ( ! isset( $rules[ $key ] ) ) {
						$rule_condition                  = $condition;
						$rule_condition['position_code'] = $position_code;
						$rules[ $key ] = array_merge(
							$rule_condition,
							array(
								'rule_scope' => $config['scope'], 'apply_type' => 'product', 'category_id' => 0,
								'item_id' => 0, 'item_variant' => '', 'source_product_name' => $product_name,
								'target_type' => 'organization', 'position_id' => 0,
								'employment_start_md' => $period[0], 'employment_end_md' => $period[1],
								'eligibility_note' => $layout['note'] !== '' ? self::normalize_space( $row[ $layout['note'] ] ?? '' ) : '',
								'frequency_count' => 1, 'frequency_years' => 1,
								'monthly_quantities' => array_fill( 1, 12, 0 ), 'priority' => self::scope_priority( $config['scope'] ),
								'is_active' => 1, 'source_sheet' => $sheet_name, 'source_row' => $row_number,
							)
						);
					}

					$rules[ $key ]['monthly_quantities'][ $config['month'] ] = $quantity;
				}
			}
		}
	}

	/**
	 * Tự nhận diện cột nghiệp vụ; mọi cột tiêu đề còn lại là sản phẩm.
	 */
	private static function discover_sheet_layout( $header ) {
		$layout = array(
			'department' => '',
			'team'        => '',
			'cost_center' => '',
			'position'    => '',
			'note'        => '',
			'products'    => array(),
		);
		$field_aliases = array(
			'department' => array( 'bo phan', 'phong' ),
			'team'        => array( 'nhom' ),
			'cost_center' => array( 'code center', 'cost center', 'ma cost center' ),
			'position'    => array( 'vi tri', 'chuc vu', 'chuc danh' ),
			'note'        => array( 'luu y', 'ghi chu' ),
		);

		foreach ( $header as $column => $label ) {
			$label = self::normalize_space( $label );
			if ( $label === '' ) {
				continue;
			}
			$normalized = self::normalize_header( $label );
			$matched = false;
			foreach ( $field_aliases as $field => $aliases ) {
				if ( in_array( $normalized, $aliases, true ) ) {
					$layout[ $field ] = $column;
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				$layout['products'][ $column ] = $label;
			}
		}

		return $layout;
	}

	private static function normalize_header( $value ) {
		$value = function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value;
		$value = strtolower( self::normalize_space( $value ) );
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', $value ) );
	}

	private static function build_summary( $rules ) {
		$summary = array( 'annual' => 0, 'newcomer' => 0, 'newcomer_september' => 0, 'total' => count( $rules ) );
		foreach ( $rules as $rule ) {
			if ( isset( $summary[ $rule['rule_scope'] ] ) ) {
				$summary[ $rule['rule_scope'] ]++;
			}
		}
		return $summary;
	}

	private static function scope_priority( $scope ) {
		return $scope === 'newcomer_september' ? 300 : ( $scope === 'newcomer' ? 200 : 100 );
	}

	private static function normalize_space( $value ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	}

}
