<?php
/**
 * Preview and import aggregate uniform returns for employee exit cases.
 */
class UMS_Employee_Exit_Return_Import {
	const PREVIEW_TRANSIENT_PREFIX = 'ums_employee_exit_return_preview_';
	const PREVIEW_TTL = 3600;

	private static $group_labels = array(
		'shirt' => 'Áo',
		'pants' => 'Quần',
		'jacket' => 'Áo khoác',
		'shoes' => 'Giày',
		'hat' => 'Mũ',
		'id_card' => 'Thẻ nhân viên',
	);

	public static function analyze( $file_path, $file_name ) {
		if ( ! UMS_DB_Employee_Exit::is_ready() ) {
			throw new RuntimeException( 'Database chưa có cấu trúc quản lý CNV nghỉ việc.' );
		}
		$reader = new UMS_XLSX_Reader( $file_path );
		$sheet_names = $reader->get_sheet_names();
		if ( empty( $sheet_names ) ) {
			throw new RuntimeException( 'File Excel không có sheet hiển thị.' );
		}

		$sheet_name = in_array( 'Sheet2', $sheet_names, true ) ? 'Sheet2' : reset( $sheet_names );
		$sheet = $reader->read_sheet( $sheet_name );
		$layout = self::detect_layout( $sheet[1] ?? array() );
		if ( false === $layout ) {
			throw new RuntimeException( 'Template phải có đủ các cột Ngày trả, Mã NV, Họ tên, Áo, Quần, Áo khoác, Giày, Mũ và Thẻ nhân viên.' );
		}

		$rows = array();
		$errors = array();
		$warnings = array();
		$seen = array();
		foreach ( $sheet as $row_number => $source ) {
			if ( $row_number < 2 ) {
				continue;
			}
			$employee_no = strtoupper( self::normalize_space( $source[ $layout['employee_no'] ] ?? '' ) );
			$file_full_name = self::normalize_space( $source[ $layout['full_name'] ] ?? '' );
			$return_date_raw = trim( (string) ( $source[ $layout['return_date'] ] ?? '' ) );
			$has_quantity = false;
			foreach ( self::$group_labels as $group => $label ) {
				if ( trim( (string) ( $source[ $layout[ $group ] ] ?? '' ) ) !== '' ) {
					$has_quantity = true;
					break;
				}
			}
			if ( $employee_no === '' && $file_full_name === '' && $return_date_raw === '' && ! $has_quantity ) {
				continue;
			}
			if ( $employee_no === '' ) {
				$errors[] = sprintf( 'Dòng %d: thiếu Mã NV.', $row_number );
				continue;
			}
			if ( isset( $seen[ $employee_no ] ) ) {
				$errors[] = sprintf( 'Dòng %d: Mã NV %s bị trùng với dòng %d.', $row_number, $employee_no, $seen[ $employee_no ] );
				continue;
			}
			$seen[ $employee_no ] = $row_number;

			$return_date = self::parse_date( $return_date_raw );
			if ( $return_date === '' ) {
				$errors[] = sprintf( 'Dòng %d, CNV %s: Ngày trả không hợp lệ.', $row_number, $employee_no );
				continue;
			}
			if ( $return_date > current_time( 'Y-m-d' ) ) {
				$errors[] = sprintf( 'Dòng %d, CNV %s: Ngày trả %s nằm trong tương lai.', $row_number, $employee_no, $return_date );
				continue;
			}

			$quantities = array();
			$row_invalid = false;
			foreach ( self::$group_labels as $group => $label ) {
				$raw = trim( (string) ( $source[ $layout[ $group ] ] ?? '' ) );
				if ( $raw === '' ) {
					$quantities[ $group ] = 0;
					continue;
				}
				$value = filter_var( $raw, FILTER_VALIDATE_INT );
				if ( $value === false || $value < 0 || $value > 1000 ) {
					$errors[] = sprintf( 'Dòng %d, CNV %s: cột %s phải là số nguyên từ 0 đến 1.000.', $row_number, $employee_no, $label );
					$row_invalid = true;
					continue;
				}
				$quantities[ $group ] = (int) $value;
			}
			if ( $row_invalid ) {
				continue;
			}

			$case = UMS_DB_Employee_Exit::get_latest_by_employee_no( $employee_no );
			if ( ! $case ) {
				$errors[] = sprintf( 'Dòng %d, CNV %s: không tìm thấy hồ sơ nghỉ việc.', $row_number, $employee_no );
				continue;
			}
			$ensure_result = UMS_Employee_Exit_Manager::ensure_case_uniform_items( $case['exit_id'] );
			if ( is_wp_error( $ensure_result ) ) {
				$errors[] = sprintf( 'Dòng %d, CNV %s: %s', $row_number, $employee_no, $ensure_result->get_error_message() );
				continue;
			}
			$case = UMS_DB_Employee_Exit::get_by_id( $case['exit_id'] );
			$items = UMS_DB_Employee_Exit::get_items( $case['exit_id'] );
			$limits = self::group_totals( $items, 'limit' );
			$current = self::group_totals( $items, 'returned' );
			foreach ( self::$group_labels as $group => $label ) {
				if ( $quantities[ $group ] > ( $limits[ $group ] ?? 0 ) ) {
					$errors[] = sprintf(
						'Dòng %d, CNV %s: %s thực trả %d vượt số lượng phải trả %d.',
						$row_number, $employee_no, $label, $quantities[ $group ], $limits[ $group ] ?? 0
					);
					$row_invalid = true;
				}
			}
			if ( $row_invalid ) {
				continue;
			}

			$case_name = self::normalize_space( $case['full_name'] ?? '' );
			if ( $file_full_name !== '' && self::normalize_compare( $file_full_name ) !== self::normalize_compare( $case_name ) ) {
				$warnings[] = sprintf( 'Dòng %d, CNV %s: họ tên trong file "%s" khác hồ sơ "%s"; hệ thống vẫn nhận theo Mã NV.', $row_number, $employee_no, $file_full_name, $case_name );
			}
			$rows[] = array(
				'source_row' => (int) $row_number,
				'exit_id' => (int) $case['exit_id'],
				'employee_no' => $employee_no,
				'file_full_name' => $file_full_name,
				'full_name' => $case_name,
				'return_date' => $return_date,
				'factory_code' => UMS_DB_Inventory::resolve_factory_code_for_employee( $case ),
				'quantities' => $quantities,
				'limits' => $limits,
				'current' => $current,
				'total_returned' => array_sum( $quantities ),
			);
		}

		if ( empty( $rows ) && empty( $errors ) ) {
			$errors[] = 'File chưa có dòng dữ liệu hoàn trả.';
		}
		return array(
			'file_name' => sanitize_file_name( $file_name ),
			'file_hash' => hash_file( 'sha256', $file_path ),
			'sheet_name' => $sheet_name,
			'rows' => $rows,
			'errors' => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
			'total_quantity' => array_sum( array_column( $rows, 'total_returned' ) ),
		);
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::PREVIEW_TRANSIENT_PREFIX . $token, $preview, self::PREVIEW_TTL ) ) {
			throw new RuntimeException( 'Không lưu được dữ liệu xem trước hoàn trả.' );
		}
		return $token;
	}

	public static function get_preview( $token ) {
		return get_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function delete_preview( $token ) {
		delete_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function import( $preview, $actor_user_id ) {
		if ( empty( $preview['rows'] ) ) {
			return array( 'success' => false, 'errors' => array( 'Không có dữ liệu hợp lệ để import.' ) );
		}
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$errors = array();
		foreach ( $preview['rows'] as $row ) {
			$case = UMS_DB_Employee_Exit::get_by_id( $row['exit_id'] );
			if ( ! $case || strtoupper( trim( (string) $case['employee_no'] ) ) !== $row['employee_no'] || $case['status'] === 'cancelled' ) {
				$errors[] = sprintf( 'Dòng %d, CNV %s: hồ sơ nghỉ việc đã thay đổi sau bước xem trước.', $row['source_row'], $row['employee_no'] );
				break;
			}
			$current_items = UMS_DB_Employee_Exit::get_items( $case['exit_id'] );
			$limits = self::group_totals( $current_items, 'limit' );
			$current = self::group_totals( $current_items, 'returned' );
			if ( $limits !== $row['limits'] || $current !== $row['current'] ) {
				$errors[] = sprintf( 'Dòng %d, CNV %s: nghĩa vụ hoặc số lượng thực trả đã thay đổi sau bước xem trước.', $row['source_row'], $row['employee_no'] );
				break;
			}
			foreach ( self::$group_labels as $group => $label ) {
				if ( (int) $row['quantities'][ $group ] > (int) ( $limits[ $group ] ?? 0 ) ) {
					$errors[] = sprintf( 'Dòng %d, CNV %s: nghĩa vụ %s đã thay đổi sau bước xem trước.', $row['source_row'], $row['employee_no'], $label );
					break 2;
				}
			}
			$result = UMS_Employee_Exit_Manager::apply_group_returns(
				$case['exit_id'], $row['quantities'], $row['return_date'], $actor_user_id, $preview['file_name']
			);
			if ( is_wp_error( $result ) ) {
				$errors[] = sprintf( 'Dòng %d, CNV %s: %s', $row['source_row'], $row['employee_no'], $result->get_error_message() );
				break;
			}
		}
		if ( $errors ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'errors' => $errors );
		}
		$wpdb->query( 'COMMIT' );
		return array(
			'success' => true,
			'imported' => count( $preview['rows'] ),
			'total_quantity' => (int) $preview['total_quantity'],
		);
	}

	private static function detect_layout( $headers ) {
		$layout = array_fill_keys( array_merge( array( 'return_date', 'employee_no', 'full_name' ), array_keys( self::$group_labels ) ), '' );
		$aliases = array(
			'return_date' => array( 'ngay tra' ),
			'employee_no' => array( 'ma nv', 'mnv', 'ma nhan vien' ),
			'full_name' => array( 'ho ten', 'ho va ten' ),
			'shirt' => array( 'ao' ),
			'pants' => array( 'quan' ),
			'jacket' => array( 'ao khoac' ),
			'shoes' => array( 'giay' ),
			'hat' => array( 'mu' ),
			'id_card' => array( 'the nhan vien', 'the ten' ),
		);
		foreach ( $headers as $column => $header ) {
			$normalized = self::normalize_compare( $header );
			foreach ( $aliases as $field => $field_aliases ) {
				if ( in_array( $normalized, $field_aliases, true ) ) {
					$layout[ $field ] = $column;
				}
			}
		}
		return in_array( '', $layout, true ) ? false : $layout;
	}

	private static function group_totals( $items, $mode ) {
		$totals = array_fill_keys( array_keys( self::$group_labels ), 0 );
		foreach ( $items as $item ) {
			$group = sanitize_key( (string) $item['item_group'] );
			if ( $group === 'coat' ) {
				$group = 'jacket';
			}
			if ( ! array_key_exists( $group, $totals ) ) {
				continue;
			}
			$totals[ $group ] += $mode === 'returned'
				? max( 0, (int) $item['returned_quantity'] )
				: max( 0, (int) $item['required_quantity'] - (int) $item['exempt_quantity'] );
		}
		return $totals;
	}

	private static function parse_date( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return '';
		}
		if ( is_numeric( $value ) ) {
			$serial = (float) $value;
			if ( $serial > 0 && $serial < 100000 ) {
				return gmdate( 'Y-m-d', (int) round( ( $serial - 25569 ) * DAY_IN_SECONDS ) );
			}
		}
		foreach ( array( 'd/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y' ) as $format ) {
			$date = DateTime::createFromFormat( '!' . $format, $value );
			if ( $date && $date->format( $format ) === $value ) {
				return $date->format( 'Y-m-d' );
			}
		}
		return '';
	}

	private static function normalize_compare( $value ) {
		$value = self::normalize_space( $value );
		$value = function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value;
		return strtolower( $value );
	}

	private static function normalize_space( $value ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	}
}
