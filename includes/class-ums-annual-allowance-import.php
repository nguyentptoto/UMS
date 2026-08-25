<?php
/**
 * Phân tích và import ma trận định mức cấp phát từ Excel.
 */
class UMS_Annual_Allowance_Import {
	const PREVIEW_TRANSIENT_PREFIX = 'ums_allowance_preview_';
	const PREVIEW_TTL              = 3600;

	private static $annual_sheet_configs = array(
		'Phát T4' => array(
			'scope' => 'annual', 'header' => 1, 'start' => 2, 'month' => 4,
		),
		'Phát T9' => array(
			'scope' => 'annual', 'header' => 1, 'start' => 2, 'month' => 9,
		),
	);

	/**
	 * Các sheet CNV mới sử dụng tiêu đề sản phẩm động theo từng ma trận.
	 */
	private static $newcomer_sheet_configs = array(
		'New commer' => array(
			'scope' => 'newcomer', 'header' => 2, 'start' => 3, 'months' => 'all',
			'period_field' => 'employment_period', 'priority' => 200,
		),
		'Phát T9 - CNV mới' => array(
			'scope' => 'newcomer_september', 'header' => 2, 'start' => 3, 'month' => 9,
			'period_field' => 'employment_period', 'priority' => 300,
		),
		'ĐM T9 CNVM vào 1.1-31.3' => array(
			'scope' => 'newcomer_september_override', 'header' => 1, 'start' => 2, 'month' => 9,
			'period' => array( '01-01', '03-31' ), 'priority' => 400,
		),
		'ĐM Quần Áo Mũ T9 CNVM 1.4-31.7' => array(
			'scope' => 'newcomer_september_override', 'header' => 1, 'start' => 2, 'month' => 9,
			'period' => array( '04-01', '07-31' ), 'priority' => 400,
		),
		'ĐM Quần Áo Mũ T9 CNVM 1.8-31.8' => array(
			'scope' => 'newcomer_september_override', 'header' => 1, 'start' => 2, 'month' => 9,
			'period' => array( '08-01', '08-31' ), 'priority' => 400,
		),
		'ĐM Quần Áo Mũ T9 CNVM 1.9-31.12' => array(
			'scope' => 'newcomer_september_override', 'header' => 1, 'start' => 2, 'month' => 9,
			'period' => array( '09-01', '12-31' ), 'priority' => 400,
		),
	);

	/**
	 * Định mức giày cấp ở kỳ kế tiếp cho CNV mới. Hai sheet có cùng ma trận
	 * sản phẩm nhưng khác khoảng ngày vào và tháng cấp của năm kế tiếp.
	 */
	private static $newcomer_shoe_sheet_configs = array(
		'ĐM Giày T9N+1 CNVM vào 1.9N-29.' => array(
			'scope' => 'newcomer_shoe_september', 'header' => 1, 'start' => 2, 'month' => 9,
			'period' => array( '09-01', '02-29' ), 'priority' => 500, 'product_scoped_matrix' => true,
		),
		'ĐM Giày T4N+1 CNVM vào 1.3N-31.' => array(
			'scope' => 'newcomer_shoe_april', 'header' => 1, 'start' => 2, 'month' => 4,
			'period' => array( '03-01', '08-31' ), 'priority' => 500, 'product_scoped_matrix' => true,
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
		$reader           = new UMS_XLSX_Reader( $file_path );
		$rules            = array();
		$errors           = array();
		$processed_sheets = array();
		$managed_scopes   = array();
		$replace_scopes   = array();

		$annual_present = array();
		foreach ( self::$annual_sheet_configs as $sheet_name => $config ) {
			if ( $reader->has_sheet( $sheet_name ) ) {
				$annual_present[] = $sheet_name;
			}
		}
		foreach ( self::$annual_sheet_configs as $sheet_name => $config ) {
			if ( ! $reader->has_sheet( $sheet_name ) ) {
				continue;
			}
			$config['products'] = self::get_product_columns();
			self::collect_sheet_rules( $reader->read_sheet( $sheet_name ), $sheet_name, $config, $rules, $errors );
			$processed_sheets[] = $sheet_name;
			$managed_scopes[]   = $config['scope'];
		}
		if ( count( $annual_present ) === count( self::$annual_sheet_configs ) ) {
			$replace_scopes[] = 'annual';
		}

		$override_present = array();
		foreach ( self::$newcomer_sheet_configs as $sheet_name => $config ) {
			if ( ! $reader->has_sheet( $sheet_name ) ) {
				continue;
			}
			if ( $config['scope'] === 'newcomer_september_override' ) {
				$override_present[] = $sheet_name;
			} else {
				$replace_scopes[] = $config['scope'];
			}
			self::collect_sheet_rules( $reader->read_sheet( $sheet_name ), $sheet_name, $config, $rules, $errors );
			$processed_sheets[] = $sheet_name;
			$managed_scopes[]   = $config['scope'];
		}

		if ( count( $override_present ) === 4 ) {
			$replace_scopes[] = 'newcomer_september_override';
		}

		foreach ( self::$newcomer_shoe_sheet_configs as $sheet_name => $config ) {
			if ( ! $reader->has_sheet( $sheet_name ) ) {
				continue;
			}
			$processed_sheets[] = $sheet_name;
			$managed_scopes[]   = $config['scope'];
			$replace_scopes[]   = $config['scope'];
			self::collect_sheet_rules( $reader->read_sheet( $sheet_name ), $sheet_name, $config, $rules, $errors );
		}
		if ( empty( $processed_sheets ) ) {
			$errors[] = 'Không tìm thấy sheet định mức UMS được hỗ trợ trong file Excel.';
		}

		foreach ( $rules as &$rule ) {
			if ( $rule['rule_scope'] === 'annual' && $rule['apply_type'] !== 'matrix' ) {
				$rule['frequency_count'] = max( 1, count( array_filter( $rule['monthly_quantities'] ) ) );
			}
			$rule['monthly_quantities'] = wp_json_encode( $rule['monthly_quantities'] );
		}
		unset( $rule );

		$used_product_names = array();
		foreach ( $rules as $rule ) {
			if ( in_array( $rule['apply_type'], array( 'product', 'matrix' ), true ) && $rule['source_product_name'] !== '' ) {
				$used_product_names[] = $rule['source_product_name'];
			}
		}
		$used_product_names = array_values( array_unique( $used_product_names ) );

		return array(
			'file_name'          => sanitize_file_name( $file_name ),
			'file_hash'          => hash_file( 'sha256', $file_path ),
			'rules'              => array_values( $rules ),
			'product_names'      => $used_product_names,
			'used_product_names' => $used_product_names,
			'processed_sheets'   => array_values( array_unique( $processed_sheets ) ),
			'managed_scopes'     => array_values( array_unique( $managed_scopes ) ),
			'replace_scopes'     => array_values( array_unique( $replace_scopes ) ),
			'errors'             => array_values( array_unique( $errors ) ),
			'summary'            => self::build_summary( $rules ),
		);
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		$payload = $preview;
		if ( function_exists( 'gzcompress' ) ) {
			$compressed = gzcompress( serialize( $preview ), 6 );
			if ( false !== $compressed ) {
				$payload = array(
					'format' => 'ums_allowance_gzip_v1',
					'data'   => base64_encode( $compressed ),
				);
			}
		}
		if ( ! set_transient( self::PREVIEW_TRANSIENT_PREFIX . $token, $payload, self::PREVIEW_TTL ) ) {
			throw new RuntimeException( 'Không lưu được dữ liệu xem trước. Hãy kiểm tra dung lượng database hoặc object cache.' );
		}
		return $token;
	}

	public static function get_preview( $token ) {
		$payload = get_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
		if ( ! is_array( $payload ) || ( $payload['format'] ?? '' ) !== 'ums_allowance_gzip_v1' ) {
			return $payload;
		}

		$compressed = base64_decode( (string) ( $payload['data'] ?? '' ), true );
		$serialized = false !== $compressed && function_exists( 'gzuncompress' ) ? gzuncompress( $compressed ) : false;
		if ( false === $serialized ) {
			return false;
		}

		$preview = unserialize( $serialized, array( 'allowed_classes' => false ) );
		return is_array( $preview ) ? $preview : false;
	}

	public static function delete_preview( $token ) {
		delete_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function import( $preview, $mappings, $user_id ) {
		$errors = array();
		$mapped = array();

		foreach ( $preview['used_product_names'] as $product_name ) {
			$map_key      = hash( 'sha256', $product_name );
			$mapping_raw  = isset( $mappings[ $map_key ] ) ? sanitize_text_field( $mappings[ $map_key ] ) : '';
			$parts        = explode( '|', $mapping_raw, 2 );
			$category_id  = isset( $parts[0] ) ? absint( $parts[0] ) : 0;
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

		$inserted       = 0;
		$updated        = 0;
		$prepared_rules = array();
		$staged_rules   = array();
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $preview['rules'] as $rule ) {
			if ( in_array( $rule['apply_type'], array( 'product', 'matrix' ), true ) && $rule['source_product_name'] !== '' ) {
				$product             = $mapped[ $rule['source_product_name'] ];
				$rule['category_id'] = $product['category_id'];
				$rule['item_variant'] = $product['item_variant'];
			}
			$rule['rule_key'] = UMS_DB_Annual_Allowance::build_rule_key( $rule );
			$staged_rules[]   = $rule;
		}

		$existing_rules = UMS_DB_Annual_Allowance::get_by_rule_keys( array_column( $staged_rules, 'rule_key' ) );
		foreach ( $staged_rules as $rule ) {
			$existing_rule  = $existing_rules[ $rule['rule_key'] ] ?? null;
			$managed_months = array_map( 'absint', (array) ( $rule['managed_months'] ?? array() ) );
			if ( $existing_rule && ! empty( $managed_months ) ) {
				$current_quantities = json_decode( (string) $rule['monthly_quantities'], true );
				$stored_quantities  = json_decode( (string) $existing_rule['monthly_quantities'], true );
				$current_quantities = is_array( $current_quantities ) ? $current_quantities : array();
				$stored_quantities  = is_array( $stored_quantities ) ? $stored_quantities : array();
				for ( $month = 1; $month <= 12; $month++ ) {
					if ( ! in_array( $month, $managed_months, true ) ) {
						$current_quantities[ $month ] = absint( $stored_quantities[ $month ] ?? 0 );
					}
				}
				$rule['monthly_quantities'] = wp_json_encode( $current_quantities );
			}
			if ( $rule['rule_scope'] === 'annual' && $rule['apply_type'] !== 'matrix' ) {
				$final_quantities = json_decode( (string) $rule['monthly_quantities'], true );
				$final_quantities = is_array( $final_quantities ) ? $final_quantities : array();
				$rule['frequency_count'] = max( 1, count( array_filter( array_map( 'absint', $final_quantities ) ) ) );
			}
			$rule['source_batch_id'] = $batch_id;
			unset( $rule['source_sheet'], $rule['source_row'], $rule['managed_months'] );
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

		$replace_scopes = isset( $preview['replace_scopes'] ) && is_array( $preview['replace_scopes'] ) ? $preview['replace_scopes'] : array();
		if ( empty( $errors ) && false === UMS_DB_Annual_Allowance::deactivate_other_import_rules( $batch_id, $replace_scopes ) ) {
			$errors[] = 'Không vô hiệu hóa được các rule cũ trong phạm vi vừa import.';
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
		if ( empty( $layout['department'] ) || empty( $layout['position'] ) ) {
			$errors[] = 'Sheet ' . $sheet_name . ' thiếu cột Bộ phận hoặc Vị trí.';
			return;
		}

		$products = isset( $config['products'] ) ? $config['products'] : $layout['products'];
		if ( empty( $products ) ) {
			$errors[] = 'Sheet ' . $sheet_name . ' không có cột sản phẩm.';
			return;
		}
		if ( isset( $config['products'] ) ) {
			foreach ( $products as $column => $product_name ) {
				$actual_header = self::normalize_space( $rows[ $config['header'] ][ $column ] ?? '' );
				if ( self::normalize_header( $actual_header ) !== self::normalize_header( $product_name ) ) {
					$errors[] = sprintf( 'Sheet %s cột %s phải là "%s".', $sheet_name, $column, $product_name );
				}
			}
		}

		foreach ( $rows as $row_number => $row ) {
			if ( $row_number < $config['start'] || self::normalize_space( $row[ $layout['department'] ] ?? '' ) === '' ) {
				continue;
			}

			$condition = array(
				'department' => self::normalize_space( $row[ $layout['department'] ] ),
				'team' => $layout['team'] !== '' ? self::normalize_space( $row[ $layout['team'] ] ?? '' ) : '',
				'cost_center' => $layout['cost_center'] !== '' ? self::normalize_space( $row[ $layout['cost_center'] ] ?? '' ) : '',
				'position_code' => UMS_DB_Annual_Allowance::normalize_position_code( $row[ $layout['position'] ] ?? '' ),
			);
			$period = isset( $config['period'] ) ? $config['period'] : array( '', '' );
			if ( isset( $config['period_field'] ) ) {
				$period_column = $layout[ $config['period_field'] ] ?? '';
				$period_value  = $period_column !== '' ? self::normalize_space( $row[ $period_column ] ?? '' ) : '';
				$period        = self::parse_period( $period_value );
				if ( empty( $period ) ) {
					$errors[] = sprintf( 'Sheet %s dòng %d có khoảng ngày nhận việc không hợp lệ.', $sheet_name, $row_number );
					continue;
				}
			}

			$position_codes = array_filter( array_map( 'trim', explode( ',', $condition['position_code'] ) ) );
			if ( empty( $position_codes ) ) {
				$errors[] = sprintf( 'Sheet %s dòng %d chưa có Vị trí.', $sheet_name, $row_number );
				continue;
			}

			foreach ( $position_codes as $position_code ) {
				$rule_condition                  = $condition;
				$rule_condition['position_code'] = $position_code;
				$note = $layout['note'] !== '' ? self::normalize_space( $row[ $layout['note'] ] ?? '' ) : '';

				if ( empty( $config['product_scoped_matrix'] ) ) {
					$marker_key = self::rule_collection_key( $config['scope'], $rule_condition, $period, '__matrix__' );
					if ( ! isset( $rules[ $marker_key ] ) ) {
						$rules[ $marker_key ] = self::new_rule( $rule_condition, $config, $period, '', 'matrix', $note, $sheet_name, $row_number );
					}
					self::merge_managed_months( $rules[ $marker_key ], $config );
				}

				foreach ( $products as $column => $product_name ) {
					$product_name = self::normalize_space( $product_name );
					if ( ! empty( $config['product_scoped_matrix'] ) ) {
						$marker_key = self::rule_collection_key( $config['scope'], $rule_condition, $period, '__matrix__:' . $product_name );
						if ( ! isset( $rules[ $marker_key ] ) ) {
							$rules[ $marker_key ] = self::new_rule( $rule_condition, $config, $period, $product_name, 'matrix', $note, $sheet_name, $row_number );
						}
						self::merge_managed_months( $rules[ $marker_key ], $config );
					}
					$raw_quantity = $row[ $column ] ?? '';
					if ( $raw_quantity !== '' && ! is_numeric( $raw_quantity ) ) {
						$errors[] = sprintf( 'Sheet %s dòng %d, sản phẩm "%s" phải là số.', $sheet_name, $row_number, $product_name );
						continue;
					}
					$quantity = is_numeric( $raw_quantity ) ? max( 0, (int) $raw_quantity ) : 0;
					if ( $quantity <= 0 ) {
						continue;
					}

					$key = self::rule_collection_key( $config['scope'], $rule_condition, $period, $product_name );
					if ( ! isset( $rules[ $key ] ) ) {
						$rules[ $key ] = self::new_rule( $rule_condition, $config, $period, $product_name, 'product', $note, $sheet_name, $row_number );
					}
					self::merge_managed_months( $rules[ $key ], $config );

					if ( isset( $config['months'] ) && $config['months'] === 'all' ) {
						for ( $month = 1; $month <= 12; $month++ ) {
							$rules[ $key ]['monthly_quantities'][ $month ] = $quantity;
						}
					} else {
						$rules[ $key ]['monthly_quantities'][ (int) $config['month'] ] = $quantity;
					}
				}
			}
		}
	}

	private static function new_rule( $condition, $config, $period, $product_name, $apply_type, $note, $sheet_name, $row_number ) {
		return array_merge(
			$condition,
			array(
				'rule_scope' => $config['scope'], 'apply_type' => $apply_type, 'category_id' => 0,
				'item_id' => 0, 'item_variant' => '', 'source_product_name' => $product_name,
				'target_type' => 'organization', 'position_id' => 0,
				'employment_start_md' => $period[0], 'employment_end_md' => $period[1],
				'eligibility_note' => $note, 'frequency_count' => 1, 'frequency_years' => 1,
				'monthly_quantities' => array_fill( 1, 12, 0 ),
				'priority' => isset( $config['priority'] ) ? (int) $config['priority'] : self::scope_priority( $config['scope'] ),
				'is_active' => 1, 'source_sheet' => $sheet_name, 'source_row' => $row_number,
				'managed_months' => self::get_managed_months( $config ),
			)
		);
	}

	private static function merge_managed_months( &$rule, $config ) {
		$months = array_merge( (array) ( $rule['managed_months'] ?? array() ), self::get_managed_months( $config ) );
		$rule['managed_months'] = array_values( array_unique( array_map( 'absint', $months ) ) );
	}

	private static function get_managed_months( $config ) {
		if ( isset( $config['months'] ) && $config['months'] === 'all' ) {
			return range( 1, 12 );
		}
		return isset( $config['month'] ) ? array( absint( $config['month'] ) ) : array();
	}

	private static function rule_collection_key( $scope, $condition, $period, $product_name ) {
		return implode(
			'|',
			array(
				$scope, $condition['department'], $condition['team'], $condition['cost_center'],
				$condition['position_code'], $period[0], $period[1], $product_name,
			)
		);
	}

	private static function discover_sheet_layout( $header ) {
		$layout = array(
			'department' => '', 'team' => '', 'cost_center' => '', 'position' => '',
			'note' => '', 'employment_period' => '', 'products' => array(),
		);
		$field_aliases = array(
			'department' => array( 'bo phan', 'phong' ),
			'team' => array( 'nhom', 'team' ),
			'cost_center' => array( 'code center', 'cost center', 'ma cost center' ),
			'position' => array( 'vi tri', 'chuc vu', 'chuc danh' ),
			'note' => array( 'luu y', 'ghi chu', 'ky phat dong phuc thang 04 nam do' ),
			'employment_period' => array( 'thoi gian nhan viec', 'thoi diem nhan viec', 'khoang ngay nhan viec' ),
		);

		foreach ( $header as $column => $label ) {
			$label = self::normalize_space( $label );
			if ( $label === '' ) {
				continue;
			}
			$normalized = self::normalize_header( $label );
			$matched    = false;
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

	private static function parse_period( $value ) {
		if ( ! preg_match( '/^(\d{1,2})\/(\d{1,2})\s*-\s*(\d{1,2})\/(\d{1,2})$/', trim( (string) $value ), $matches ) ) {
			return array();
		}
		$start_day   = (int) $matches[1];
		$start_month = (int) $matches[2];
		$end_day     = (int) $matches[3];
		$end_month   = (int) $matches[4];
		if ( ! checkdate( $start_month, $start_day, 2000 ) || ! checkdate( $end_month, $end_day, 2000 ) ) {
			return array();
		}
		return array( sprintf( '%02d-%02d', $start_month, $start_day ), sprintf( '%02d-%02d', $end_month, $end_day ) );
	}

	private static function normalize_header( $value ) {
		$value = function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value;
		$value = strtolower( self::normalize_space( $value ) );
		return trim( preg_replace( '/[^a-z0-9]+/', ' ', $value ) );
	}

	private static function build_summary( $rules ) {
		$summary = array(
			'annual' => 0, 'newcomer' => 0, 'newcomer_september' => 0,
			'newcomer_september_override' => 0, 'newcomer_shoe_april' => 0,
			'newcomer_shoe_september' => 0, 'matrix' => 0, 'total' => count( $rules ),
		);
		foreach ( $rules as $rule ) {
			if ( $rule['apply_type'] === 'matrix' ) {
				$summary['matrix']++;
				continue;
			}
			if ( isset( $summary[ $rule['rule_scope'] ] ) ) {
				$summary[ $rule['rule_scope'] ]++;
			}
		}
		return $summary;
	}

	private static function scope_priority( $scope ) {
		$priorities = array(
			'annual' => 100, 'newcomer' => 200, 'newcomer_september' => 300,
			'newcomer_september_override' => 400, 'newcomer_shoe_april' => 500,
			'newcomer_shoe_september' => 500,
		);
		return isset( $priorities[ $scope ] ) ? $priorities[ $scope ] : 0;
	}

	private static function normalize_space( $value ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	}
}
