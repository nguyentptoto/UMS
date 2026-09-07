<?php
/**
 * Import danh sach dang ky dong phuc da chot va xuat kho theo dinh muc UMS.
 */
class UMS_Issue_Registration_Import {
	const PREVIEW_PREFIX = 'ums_issue_registration_preview_';
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
				$errors[] = sprintf( 'Dòng %d: Mã nhân viên "%s" sai định dạng (yêu cầu 1 chữ cái và 6 chữ số).', $row_number, $employee_no );
				continue;
			}
			$code_rows[ $employee_no ][] = $row_number;
			$parsed[] = self::parse_row( $row_number, $row, $employee_no, $errors );
		}

		foreach ( $code_rows as $employee_no => $row_numbers ) {
			if ( count( $row_numbers ) > 1 ) {
				$errors[] = sprintf( 'Mã nhân viên %s xuất hiện nhiều lần tại các dòng %s.', $employee_no, implode( ', ', $row_numbers ) );
			}
		}

		$employee_nos = array_keys( $code_rows );
		$organization = UMS_DB_Organization::get_by_employee_nos( $employee_nos );
		foreach ( $employee_nos as $employee_no ) {
			if ( ! isset( $organization[ $employee_no ] ) ) {
				$errors[] = sprintf( 'Mã nhân viên %s không tồn tại trong Sơ đồ tổ chức TVN.', $employee_no );
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
			foreach ( $entry['requests'] as $request ) {
				if ( $request['quantity'] <= 0 ) {
					continue;
				}
				$allocation = self::select_allocation( $allocations, $request['group'], $request['shirt_type'], $request['quantity'] );
				if ( is_wp_error( $allocation ) ) {
					$errors[] = sprintf( 'Dòng %d, CNV %s: %s', $entry['source_row'], $employee_no, $allocation->get_error_message() );
					continue;
				}
				$item = self::select_inventory_item(
					$allocation['eligible_item_ids'] ?? $allocation['product']['item_ids'],
					$request['size'], $request['group'], $request['shirt_type'], $inventory_by_id
				);
				if ( is_wp_error( $item ) ) {
					$errors[] = sprintf( 'Dòng %d, CNV %s: %s', $entry['source_row'], $employee_no, $item->get_error_message() );
					continue;
				}
				$rule_id = absint( $allocation['rule']['rule_id'] );
				$requested_by_rule[ $rule_id ] = ( $requested_by_rule[ $rule_id ] ?? 0 ) + $request['quantity'];
				$details[] = array(
					'source_row' => $entry['source_row'], 'employee_no' => $employee_no,
					'full_name' => (string) $allowance_map[ $employee_no ]['employee']['full_name'],
					'item_id' => absint( $item['item_id'] ), 'product' => (string) $item['item_variant'],
					'size' => (string) $item['size'], 'quantity' => $request['quantity'],
					'rule_id' => $rule_id, 'quota' => absint( $allocation['quota'] ),
					'remaining' => absint( $allocation['remaining'] ), 'exact' => ! empty( $allocation['exact'] ),
				);
			}

			foreach ( $allocations as $allocation ) {
				$rule_id   = absint( $allocation['rule']['rule_id'] );
				$requested = absint( $requested_by_rule[ $rule_id ] ?? 0 );
				$remaining = absint( $allocation['remaining'] );
				$label     = (string) $allocation['product']['item_variant'];
				if ( ! empty( $allocation['exact'] ) && $requested !== $remaining ) {
					$errors[] = sprintf( 'Dòng %d, CNV %s: "%s" phải đăng ký đúng %d, hiện đăng ký %d.', $entry['source_row'], $employee_no, $label, $remaining, $requested );
				} elseif ( $requested > $remaining ) {
					$errors[] = sprintf( 'Dòng %d, CNV %s: "%s" đăng ký %d, vượt định mức còn lại %d (định mức kỳ %d).', $entry['source_row'], $employee_no, $label, $requested, $remaining, absint( $allocation['quota'] ) );
				}
			}
		}

		$stock_totals = array();
		foreach ( $details as $detail ) {
			$stock_totals[ $detail['item_id'] ] = ( $stock_totals[ $detail['item_id'] ] ?? 0 ) + $detail['quantity'];
		}
		foreach ( $stock_totals as $item_id => $required ) {
			$stock = absint( $inventory_by_id[ $item_id ]['stock_qty'] ?? 0 );
			if ( $required > $stock ) {
				$item = $inventory_by_id[ $item_id ];
				$errors[] = sprintf( 'Thiếu tồn kho "%s" size %s: cần %d, hiện có %d, thiếu %d.', $item['item_variant'], $item['size'], $required, $stock, $required - $stock );
			}
		}

		$file_hash = hash_file( 'sha256', $file_path );
		if ( get_option( 'ums_issue_registration_imported_' . sanitize_key( $file_hash ) ) ) {
			$errors[] = 'File này đã được xác nhận xuất kho trước đó; không thể nhập lại.';
		}
		if ( empty( $details ) && empty( $errors ) ) {
			$errors[] = 'File không có sản phẩm nào cần xuất kho.';
		}

		return array(
			'file_name' => sanitize_file_name( $file_name ), 'file_hash' => $file_hash,
			'year' => $year, 'month' => $month, 'employee_count' => count( $employee_nos ),
			'details' => $details, 'total_quantity' => array_sum( array_column( $details, 'quantity' ) ),
			'errors' => array_values( array_unique( $errors ) ), 'warnings' => array_values( array_unique( $warnings ) ),
		);
	}

	private static function parse_row( $row_number, $row, $employee_no, &$errors ) {
		$requests = array();
		self::add_request( $requests, 'hat', '', $row['G'] ?? '', '0', $row_number, $errors );
		self::add_request( $requests, 'shoes', '', $row['H'] ?? '', $row['I'] ?? '', $row_number, $errors );
		self::add_request( $requests, 'pants', '', $row['K'] ?? '', $row['L'] ?? '', $row_number, $errors );
		$shirt_qty = self::quantity( $row['N'] ?? '', $row_number, 'áo', $errors );
		if ( $shirt_qty === 1 ) {
			self::add_request( $requests, 'shirt', (string) ( $row['P'] ?? '' ), 1, $row['O'] ?? '', $row_number, $errors );
		} elseif ( $shirt_qty === 2 ) {
			$type = trim( (string) ( $row['R'] ?? '' ) );
			if ( strpos( self::normalize( $type ), '1 ao dai tay 1 ao coc tay' ) !== false ) {
				self::add_request( $requests, 'shirt', 'Dài tay', 1, $row['Q'] ?? '', $row_number, $errors );
				self::add_request( $requests, 'shirt', 'Cộc tay', 1, $row['Q'] ?? '', $row_number, $errors );
			} else {
				self::add_request( $requests, 'shirt', $type, 2, $row['Q'] ?? '', $row_number, $errors );
			}
		} elseif ( $shirt_qty > 0 ) {
			$errors[] = sprintf( 'Dòng %d: số lượng áo %d không có nhánh size/loại hợp lệ trong biểu mẫu.', $row_number, $shirt_qty );
		}
		self::add_request( $requests, 'jacket', '', $row['T'] ?? '', $row['U'] ?? '', $row_number, $errors );
		self::add_request( $requests, 'coat', '', $row['W'] ?? '', $row['X'] ?? '', $row_number, $errors );
		return array( 'source_row' => $row_number, 'employee_no' => $employee_no, 'requests' => $requests );
	}

	private static function add_request( &$requests, $group, $shirt_type, $raw_quantity, $size, $row, &$errors ) {
		$group_label = UMS_Employee_Allowance_Report::get_business_group_label( $group );
		$quantity = self::quantity( $raw_quantity, $row, $group_label, $errors );
		if ( $quantity <= 0 ) {
			return;
		}
		$size = self::normalize_size( $size );
		if ( $size === '' ) {
			$errors[] = sprintf( 'Dòng %d: %s có số lượng %d nhưng thiếu size.', $row, $group_label, $quantity );
			return;
		}
		$requests[] = compact( 'group', 'shirt_type', 'quantity', 'size' );
	}

	private static function quantity( $value, $row, $label, &$errors ) {
		$value = trim( (string) $value );
		if ( $value === '' ) return 0;
		if ( ! preg_match( '/^\d+$/', $value ) ) {
			$errors[] = sprintf( 'Dòng %d: số lượng %s "%s" không phải số nguyên không âm.', $row, $label, $value );
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

	private static function select_inventory_item( $item_ids, $size, $group, $shirt_type, $inventory ) {
		$matches = array();
		foreach ( $item_ids as $item_id ) {
			if ( ! isset( $inventory[ $item_id ] ) || self::normalize_size( $inventory[ $item_id ]['size'] ) !== $size ) continue;
			$item = $inventory[ $item_id ];
			if ( UMS_Employee_Allowance_Report::get_business_group( $item ) !== $group ) continue;
			if ( $group === 'shirt' && trim( $shirt_type ) !== '' ) {
				$is_long = strpos( self::normalize( $item['item_variant'] ), 'dai tay' ) !== false;
				$wants_long = strpos( self::normalize( $shirt_type ), 'dai tay' ) !== false;
				if ( $is_long !== $wants_long ) continue;
			}
			$matches[] = $item;
		}
		if ( count( $matches ) !== 1 ) {
			return new WP_Error( 'size', sprintf( 'size "%s" phải khớp đúng một dòng kho, hiện tìm thấy %d.', $size, count( $matches ) ) );
		}
		return reset( $matches );
	}

	public static function import( $preview, $actor_user_id ) {
		if ( ! empty( $preview['errors'] ) ) return array( 'success' => false, 'errors' => $preview['errors'] );
		$hash_key = 'ums_issue_registration_imported_' . sanitize_key( $preview['file_hash'] );
		if ( get_option( $hash_key ) ) return array( 'success' => false, 'errors' => array( 'File này đã được nhập xuất kho trước đó.' ) );
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$totals = array();
		foreach ( $preview['details'] as $detail ) $totals[ $detail['item_id'] ] = ( $totals[ $detail['item_id'] ] ?? 0 ) + $detail['quantity'];
		$locked = array();
		foreach ( $totals as $item_id => $required ) {
			$item = UMS_DB_Inventory::get_by_id_for_update( $item_id );
			if ( ! $item || absint( $item['stock_qty'] ) < $required ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'errors' => array( 'Tồn kho đã thay đổi sau bước xem trước. Hãy tải lại file để kiểm tra.' ) );
			}
			$locked[ $item_id ] = $item;
		}
		$current = array_map( function ( $item ) { return absint( $item['stock_qty'] ); }, $locked );
		foreach ( $preview['details'] as $detail ) {
			$item_id = absint( $detail['item_id'] );
			$before = $current[ $item_id ];
			$after  = $before - absint( $detail['quantity'] );
			if ( false === $wpdb->update( UMS_DB_Inventory::table(), array( 'stock_qty' => $after ), array( 'item_id' => $item_id ), array( '%d' ), array( '%d' ) ) ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'errors' => array( $wpdb->last_error ?: 'Không cập nhật được tồn kho.' ) );
			}
			$price = (float) $locked[ $item_id ]['base_price'];
			$ok = UMS_DB_Inventory_Movement::insert( array(
				'item_id' => $item_id, 'request_id' => null, 'movement_type' => 'out',
				'quantity' => $detail['quantity'], 'before_qty' => $before, 'after_qty' => $after,
				'unit_price' => $price, 'total_price' => $price * $detail['quantity'],
				'actor_user_id' => absint( $actor_user_id ), 'target_user_id' => null,
				'target_employee_no' => $detail['employee_no'],
				'note' => sprintf( 'Import đăng ký cấp phát T%d/%d, file %s, dòng %d.', $preview['month'], $preview['year'], $preview['file_name'], $detail['source_row'] ),
			) );
			if ( ! $ok ) {
				$wpdb->query( 'ROLLBACK' );
				return array( 'success' => false, 'errors' => array( UMS_DB_Inventory_Movement::get_last_error() ?: 'Không ghi được lịch sử xuất kho.' ) );
			}
			$current[ $item_id ] = $after;
		}
		$wpdb->query( 'COMMIT' );
		update_option( $hash_key, current_time( 'mysql' ), false );
		return array( 'success' => true, 'imported' => count( $preview['details'] ), 'total' => $preview['total_quantity'] );
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
