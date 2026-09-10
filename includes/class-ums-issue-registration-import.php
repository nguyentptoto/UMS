<?php
/**
 * Tinh va chot nhu cau cap phat tu file dang ky theo dinh muc UMS.
 */
class UMS_Allocation_Calculation {
	const PREVIEW_PREFIX = 'ums_allocation_calculation_preview_';
	const PREVIEW_TTL    = 2 * HOUR_IN_SECONDS;

	public static function analyze( $file_path, $file_name, $year, $month ) {
		$year  = min( 2100, max( 2000, absint( $year ) ) );
		$month = in_array( absint( $month ), array( 4, 9 ), true ) ? absint( $month ) : 9;
		$reader = new UMS_XLSX_Reader( $file_path );
		$sheet  = 'Câu trả lời biểu mẫu';
		if ( ! $reader->has_sheet( $sheet ) ) {
			throw new RuntimeException( 'Không tìm thấy sheet "Câu trả lời biểu mẫu".' );
		}

		$source_rows = $reader->read_sheet( $sheet );
		self::validate_headers( $source_rows[1] ?? array() );
		$parsed      = array();
		$errors      = array();
		$warnings    = array();
		$code_rows   = array();
		foreach ( $source_rows as $row_number => $row ) {
			if ( $row_number === 1 || self::row_is_empty( $row ) ) {
				continue;
			}
			$employee_no = strtoupper( preg_replace( '/\s+/u', '', trim( (string) ( $row['C'] ?? '' ) ) ) );
			if ( ! preg_match( '/^[A-Z][0-9]{6}$/', $employee_no ) ) {
				$warnings[] = sprintf( 'Dòng %d: Mã nhân viên "%s" sai định dạng nên dòng này không được cấp.', $row_number, $employee_no );
				continue;
			}
			$code_rows[ $employee_no ][] = $row_number;
			$parsed[] = self::parse_row( $row_number, $row, $employee_no, $warnings );
		}

		foreach ( $code_rows as $employee_no => $row_numbers ) {
			if ( count( $row_numbers ) > 1 ) {
				$warnings[] = sprintf( 'Mã nhân viên %s xuất hiện nhiều lần tại các dòng %s nên các dòng này không được cấp.', $employee_no, implode( ', ', $row_numbers ) );
			}
		}

		$employee_nos = array_keys( $code_rows );
		$requested_quantity = 0;
		foreach ( $parsed as $entry ) {
			$requested_quantity += array_sum( array_column( $entry['requests'], 'quantity' ) );
		}
		$organization = UMS_DB_Organization::get_by_employee_nos( $employee_nos );
		foreach ( $employee_nos as $employee_no ) {
			if ( ! isset( $organization[ $employee_no ] ) ) {
				$warnings[] = sprintf( 'Mã nhân viên %s không tồn tại trong Sơ đồ tổ chức TVN nên không được cấp; các CNV hợp lệ khác vẫn được xử lý.', $employee_no );
			}
		}

		$allowance_map = UMS_Employee_Allowance_Report::build_employee_allocations( $employee_nos, $year, $month );
		$inventory     = UMS_DB_Inventory::get_all();
		$inventory_by_id = array();
		foreach ( $inventory as $item ) {
			$inventory_by_id[ absint( $item['item_id'] ) ] = $item;
		}

		$details = array();
		foreach ( $parsed as $entry ) {
			$employee_no = $entry['employee_no'];
			if ( count( $code_rows[ $employee_no ] ?? array() ) !== 1 || ! isset( $allowance_map[ $employee_no ] ) ) {
				continue;
			}
			$allocations = $allowance_map[ $employee_no ]['allocations'];
			$requested_by_rule = array();
			$accepted_by_rule  = array();
			foreach ( $entry['requests'] as $request ) {
				if ( $request['quantity'] <= 0 ) {
					continue;
				}
				$allocation = self::select_allocation( $allocations, $request['group'], $request['shirt_type'], $request['quantity'] );
				if ( is_wp_error( $allocation ) ) {
					$employee = $allowance_map[ $employee_no ]['employee'];
					$warnings[] = sprintf(
						'Dòng %d, CNV %s: %s [Bộ phận: %s; Nhóm: %s; Cost center: %s; Vị trí: %s]',
						$entry['source_row'], $employee_no, $allocation->get_error_message(),
						(string) ( $employee['department'] ?? '' ), (string) ( $employee['team'] ?? '' ),
						(string) ( $employee['cost_center'] ?? '' ), (string) ( $employee['position'] ?? '' )
					);
					continue;
				}
				$item = self::select_inventory_item(
					$allocation['product']['item_ids'] ?? array(),
					$request['size'], (string) ( $allocation['product']['item_variant'] ?? '' ), $inventory_by_id
				);
				if ( is_wp_error( $item ) ) {
					$warnings[] = sprintf( 'Dòng %d, CNV %s: %s Sản phẩm này không được cấp.', $entry['source_row'], $employee_no, $item->get_error_message() );
					continue;
				}
				$rule_id = absint( $allocation['rule']['rule_id'] );
				$requested_by_rule[ $rule_id ] = ( $requested_by_rule[ $rule_id ] ?? 0 ) + $request['quantity'];
				$remaining = absint( $allocation['remaining'] );
				$available = max( 0, $remaining - absint( $accepted_by_rule[ $rule_id ] ?? 0 ) );
				$accepted  = min( absint( $request['quantity'] ), $available );
				$accepted_by_rule[ $rule_id ] = absint( $accepted_by_rule[ $rule_id ] ?? 0 ) + $accepted;
				if ( $accepted < absint( $request['quantity'] ) ) {
					$warnings[] = sprintf(
						'Dòng %d, CNV %s: "%s" đăng ký %d nhưng định mức còn lại là %d; hệ thống chỉ cấp %d.',
						$entry['source_row'], $employee_no, (string) $item['item_variant'],
						absint( $request['quantity'] ), $available, $accepted
					);
				}
				if ( $accepted <= 0 ) {
					continue;
				}
				$details[] = array(
					'source_row' => $entry['source_row'], 'employee_no' => $employee_no,
					'full_name' => (string) $allowance_map[ $employee_no ]['employee']['full_name'],
					'item_id' => absint( $item['item_id'] ), 'product' => (string) $item['item_variant'],
					'size' => (string) $item['size'], 'requested_quantity' => absint( $request['quantity'] ), 'quantity' => $accepted,
					'rule_id' => $rule_id, 'quota' => absint( $allocation['quota'] ),
					'remaining' => absint( $allocation['remaining'] ), 'exact' => ! empty( $allocation['exact'] ),
				);
			}

			foreach ( $allocations as $allocation ) {
				$rule_id   = absint( $allocation['rule']['rule_id'] );
				$requested = absint( $requested_by_rule[ $rule_id ] ?? 0 );
				$accepted  = absint( $accepted_by_rule[ $rule_id ] ?? 0 );
				$remaining = absint( $allocation['remaining'] );
				$label     = (string) $allocation['product']['item_variant'];
				if ( ! empty( $allocation['exact'] ) && $requested < $remaining ) {
					$warnings[] = sprintf(
						'Dòng %d, CNV %s: "%s" có định mức %d nhưng chỉ đăng ký %d; hệ thống cấp theo số đã đăng ký là %d.',
						$entry['source_row'], $employee_no, $label, $remaining, $requested, $accepted
					);
				}
			}
		}

		$file_hash = hash_file( 'sha256', $file_path );
		if ( empty( $details ) && empty( $errors ) ) {
			$warnings[] = 'File không có dòng cấp phát hợp lệ để chốt kết quả tính.';
		}

		return array(
			'file_name' => sanitize_file_name( $file_name ), 'file_hash' => $file_hash,
			'year' => $year, 'month' => $month, 'employee_count' => count( $employee_nos ),
			'requested_quantity' => $requested_quantity,
			'details' => $details, 'total_quantity' => array_sum( array_column( $details, 'quantity' ) ),
			'errors' => array_values( array_unique( $errors ) ), 'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	private static function parse_row( $row_number, $row, $employee_no, &$warnings ) {
		$requests = array();
		self::add_request( $requests, 'hat', '', $row['G'] ?? '', '0', $row_number, $warnings );
		self::add_request( $requests, 'shoes', '', $row['H'] ?? '', $row['I'] ?? '', $row_number, $warnings );
		self::add_request( $requests, 'pants', '', $row['K'] ?? '', $row['L'] ?? '', $row_number, $warnings );
		$shirt_qty = self::quantity( $row['N'] ?? '', $row_number, 'áo', $warnings );
		if ( $shirt_qty === 1 ) {
			self::add_request( $requests, 'shirt', (string) ( $row['P'] ?? '' ), 1, $row['O'] ?? '', $row_number, $warnings );
		} elseif ( $shirt_qty === 2 ) {
			$type = trim( (string) ( $row['R'] ?? '' ) );
			if ( strpos( self::normalize( $type ), '1 ao dai tay 1 ao coc tay' ) !== false ) {
				self::add_request( $requests, 'shirt', 'Dài tay', 1, $row['Q'] ?? '', $row_number, $warnings );
				self::add_request( $requests, 'shirt', 'Cộc tay', 1, $row['Q'] ?? '', $row_number, $warnings );
			} else {
				self::add_request( $requests, 'shirt', $type, 2, $row['Q'] ?? '', $row_number, $warnings );
			}
		} elseif ( $shirt_qty > 0 ) {
			$warnings[] = sprintf( 'Dòng %d: số lượng áo %d không có nhánh size/loại hợp lệ trong biểu mẫu nên áo không được cấp.', $row_number, $shirt_qty );
		}
		self::add_request( $requests, 'jacket', '', $row['T'] ?? '', $row['U'] ?? '', $row_number, $warnings );
		self::add_request( $requests, 'coat', '', $row['W'] ?? '', $row['X'] ?? '', $row_number, $warnings );
		return array( 'source_row' => $row_number, 'employee_no' => $employee_no, 'requests' => $requests );
	}

	private static function add_request( &$requests, $group, $shirt_type, $raw_quantity, $size, $row, &$warnings ) {
		$group_label = UMS_Employee_Allowance_Report::get_business_group_label( $group );
		$quantity = self::quantity( $raw_quantity, $row, $group_label, $warnings );
		if ( $quantity <= 0 ) {
			return;
		}
		$size = self::normalize_size( $size );
		if ( $size === '' ) {
			$warnings[] = sprintf( 'Dòng %d: %s có số lượng %d nhưng thiếu size nên sản phẩm này không được cấp.', $row, $group_label, $quantity );
			return;
		}
		$requests[] = compact( 'group', 'shirt_type', 'quantity', 'size' );
	}

	private static function quantity( $value, $row, $label, &$warnings ) {
		$value = trim( (string) $value );
		if ( $value === '' ) return 0;
		if ( ! preg_match( '/^\d+$/', $value ) ) {
			$warnings[] = sprintf( 'Dòng %d: số lượng %s "%s" không phải số nguyên không âm nên sản phẩm này không được cấp.', $row, $label, $value );
			return 0;
		}
		return absint( $value );
	}

	private static function select_allocation( $allocations, $group, $shirt_type, $quantity ) {
		$group_label = UMS_Employee_Allowance_Report::get_business_group_label( $group );
		$candidates = array_values( array_filter( $allocations, function ( $item ) use ( $group ) { return $item['group'] === $group; } ) );
		if ( count( $candidates ) > 1 && $group === 'shirt' && trim( $shirt_type ) !== '' ) {
			$type = self::normalize( $shirt_type );
			$needle = strpos( $type, 'dai tay' ) !== false ? 'dai tay' : 'coc tay';
			$filtered = array_values( array_filter( $candidates, function ( $item ) use ( $needle ) {
				return strpos( self::normalize( $item['product']['item_variant'] ), $needle ) !== false;
			} ) );
			if ( ! empty( $filtered ) ) $candidates = $filtered;
		}
		if ( count( $candidates ) > 1 ) {
			$exact_quantity = array_values( array_filter( $candidates, function ( $item ) use ( $quantity ) {
				return absint( $item['remaining'] ?? 0 ) === absint( $quantity );
			} ) );
			if ( count( $exact_quantity ) === 1 ) $candidates = $exact_quantity;
		}
		if ( count( $candidates ) > 1 ) {
			$within_quota = array_values( array_filter( $candidates, function ( $item ) use ( $quantity ) {
				return absint( $item['remaining'] ?? 0 ) >= absint( $quantity );
			} ) );
			if ( count( $within_quota ) === 1 ) $candidates = $within_quota;
		}
		if ( count( $candidates ) !== 1 ) {
			if ( empty( $candidates ) ) {
				return new WP_Error( 'allocation', sprintf( 'không có định mức còn hiệu lực cho nhóm %s trong kỳ đã chọn.', $group_label ) );
			}
			$products = array_values( array_unique( array_map( function ( $item ) {
				return (string) ( $item['product']['item_variant'] ?? '' );
			}, $candidates ) ) );
			return new WP_Error( 'allocation', sprintf(
				'có nhiều sản phẩm định mức cùng phù hợp nhóm %s và số lượng %d (%s); cần kiểm tra lại các rule bị chồng lặp.',
				$group_label, absint( $quantity ), implode( ', ', $products )
			) );
		}
		return reset( $candidates );
	}

	private static function select_inventory_item( $item_ids, $size, $product_name, $inventory ) {
		$matches = array();
		$available_sizes   = array();
		foreach ( $item_ids as $item_id ) {
			if ( ! isset( $inventory[ $item_id ] ) ) continue;
			$item = $inventory[ $item_id ];
			$available_sizes[]   = self::normalize_size( $item['size'] );
			if ( self::normalize_size( $item['size'] ) !== $size ) continue;
			$matches[] = $item;
		}
		if ( count( $matches ) !== 1 ) {
			return new WP_Error( 'size', sprintf(
				'sản phẩm định mức "%s" không có đúng một dòng size "%s" (tìm thấy %d). Size đang có: %s.',
				$product_name !== '' ? $product_name : '(chưa xác định tên)', $size, count( $matches ),
				empty( $available_sizes ) ? 'không có' : implode( ', ', array_values( array_unique( $available_sizes ) ) )
			) );
		}
		return reset( $matches );
	}

	public static function save_calculation( $preview, $actor_user_id ) {
		if ( ! empty( $preview['errors'] ) ) return array( 'success' => false, 'errors' => $preview['errors'] );
		if ( empty( $preview['details'] ) ) return array( 'success' => false, 'errors' => array( 'Không có dòng cấp phát hợp lệ để chốt.' ) );
		$result = UMS_DB_Allocation_Calculation::save_snapshot( $preview, $actor_user_id );
		if ( is_wp_error( $result ) ) return array( 'success' => false, 'errors' => array( $result->get_error_message() ) );
		return array_merge( array( 'success' => true ), $result );
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::PREVIEW_PREFIX . $token, $preview, self::PREVIEW_TTL ) ) throw new RuntimeException( 'Không lưu được dữ liệu xem trước.' );
		return $token;
	}
	public static function get_preview( $token ) { return get_transient( self::PREVIEW_PREFIX . sanitize_key( $token ) ); }
	public static function delete_preview( $token ) { delete_transient( self::PREVIEW_PREFIX . sanitize_key( $token ) ); }
	private static function normalize_size( $value ) {
		$value = strtoupper( preg_replace( '/\s+/u', '', trim( (string) $value ) ) );
		return array( '2XL' => 'XXL', '3XL' => 'XXXL' )[ $value ] ?? $value;
	}
	private static function normalize( $value ) {
		$value = strtolower( remove_accents( preg_replace( '/\s+/u', ' ', trim( (string) $value ) ) ) );
		return preg_replace( '/[^a-z0-9]+/', ' ', $value );
	}
	private static function validate_headers( $headers ) {
		$expected = array(
			'C' => 'ma nhan vien', 'G' => 'mu', 'H' => 'giay', 'K' => 'quan',
			'N' => 'ao', 'T' => 'ao khoac', 'W' => 'ao phao',
		);
		foreach ( $expected as $column => $needle ) {
			if ( strpos( self::normalize( $headers[ $column ] ?? '' ), $needle ) === false ) {
				throw new RuntimeException( sprintf( 'Cột %s không đúng cấu trúc file Google Form đăng ký.', $column ) );
			}
		}
	}
	private static function row_is_empty( $row ) { return trim( implode( '', array_map( 'strval', (array) $row ) ) ) === ''; }
}
