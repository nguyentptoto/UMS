<?php
/**
 * Import danh sach CNV duoc gan cong viec dac thu theo tung ky T4/T9.
 */
class UMS_Special_Work_Assignment_Import {
	const PREVIEW_TRANSIENT_PREFIX = 'ums_special_work_assignment_preview_';
	const PREVIEW_TTL              = 3600;

	private static $sheets = array(
		'T4' => 4,
		'T9' => 9,
	);

	public static function analyze( $file_path, $file_name ) {
		$reader            = new UMS_XLSX_Reader( $file_path );
		$rows              = array();
		$errors            = array();
		$warnings          = array();
		$processed_periods = array();
		$seen              = array();

		foreach ( self::$sheets as $sheet_name => $period_month ) {
			if ( ! $reader->has_sheet( $sheet_name ) ) {
				continue;
			}
			$sheet_rows = $reader->read_sheet( $sheet_name );
			$has_data   = false;
			foreach ( $sheet_rows as $row_number => $source ) {
				if ( $row_number >= 2 && count( array_filter( array_map( 'trim', $source ), 'strlen' ) ) > 0 ) {
					$has_data = true;
					break;
				}
			}
			if ( ! $has_data ) {
				continue;
			}
			$processed_periods[] = $period_month;
			$layout     = self::detect_layout( $sheet_rows[1] ?? array() );
			if ( false === $layout ) {
				$errors[] = sprintf( 'Sheet %s phải có các cột MNV, Họ tên và Loại công việc.', $sheet_name );
				continue;
			}

			foreach ( $sheet_rows as $row_number => $source ) {
				if ( $row_number < 2 ) {
					continue;
				}
				$employee_no = strtoupper( self::normalize_space( $source[ $layout['employee_no'] ] ?? '' ) );
				$file_name_value = self::normalize_space( $source[ $layout['full_name'] ] ?? '' );
				$work_type   = self::normalize_space( $source[ $layout['work_type'] ] ?? '' );
				if ( $employee_no === '' && $file_name_value === '' && $work_type === '' ) {
					continue;
				}
				if ( $employee_no === '' || $work_type === '' ) {
					$errors[] = sprintf( 'Sheet %s dòng %d thiếu MNV hoặc Loại công việc.', $sheet_name, $row_number );
					continue;
				}
				$dedupe_key = $period_month . '|' . $employee_no;
				if ( isset( $seen[ $dedupe_key ] ) ) {
					$errors[] = sprintf( 'Sheet %s dòng %d trùng MNV %s với dòng %d.', $sheet_name, $row_number, $employee_no, $seen[ $dedupe_key ] );
					continue;
				}
				$seen[ $dedupe_key ] = $row_number;

				$employee = UMS_DB_Organization::get_by_employee_no( $employee_no );
				if ( ! $employee ) {
					$errors[] = sprintf( 'Sheet %s dòng %d: MNV %s không tồn tại trong Sơ đồ tổ chức TVN.', $sheet_name, $row_number, $employee_no );
					continue;
				}
				if ( ! UMS_DB_Annual_Allowance::special_work_type_matches_employee( $employee, $period_month, $work_type ) ) {
					$errors[] = sprintf(
						'Sheet %s dòng %d: loại công việc "%s" không khớp ma trận T%d của MNV %s (%s / %s).',
						$sheet_name,
						$row_number,
						$work_type,
						$period_month,
						$employee_no,
						$employee['department'] ?? '-',
						$employee['cost_center'] ?? '-'
					);
					continue;
				}

				$organization_name = self::normalize_space( $employee['full_name'] ?? '' );
				if ( $file_name_value !== '' && self::normalize_compare( $file_name_value ) !== self::normalize_compare( $organization_name ) ) {
					$warnings[] = sprintf(
						'Sheet %s dòng %d: họ tên trong file "%s" khác Sơ đồ TVN "%s"; hệ thống vẫn nhận theo MNV %s.',
						$sheet_name,
						$row_number,
						$file_name_value,
						$organization_name,
						$employee_no
					);
				}

				$rows[] = array(
					'source_sheet'          => $sheet_name,
					'source_row'            => (int) $row_number,
					'period_month'          => $period_month,
					'employee_no'           => $employee_no,
					'file_full_name'        => $file_name_value,
					'organization_full_name'=> $organization_name,
					'special_work_type'     => $work_type,
					'department'            => (string) ( $employee['department'] ?? '' ),
					'cost_center'           => (string) ( $employee['cost_center'] ?? '' ),
				);
			}
		}

		if ( empty( $processed_periods ) ) {
			$errors[] = 'Không tìm thấy sheet T4 hoặc T9 trong file Excel.';
		}

		return array(
			'file_name'         => sanitize_file_name( $file_name ),
			'file_hash'         => hash_file( 'sha256', $file_path ),
			'rows'              => $rows,
			'processed_periods' => array_values( array_unique( $processed_periods ) ),
			'errors'            => array_values( array_unique( $errors ) ),
			'warnings'          => array_values( array_unique( $warnings ) ),
		);
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::PREVIEW_TRANSIENT_PREFIX . $token, $preview, self::PREVIEW_TTL ) ) {
			throw new RuntimeException( 'Không lưu được dữ liệu xem trước danh sách công việc đặc thù.' );
		}
		return $token;
	}

	public static function get_preview( $token ) {
		return get_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function delete_preview( $token ) {
		delete_transient( self::PREVIEW_TRANSIENT_PREFIX . sanitize_key( $token ) );
	}

	public static function import( $preview, $user_id ) {
		if ( ! UMS_DB_Annual_Allowance::supports_special_work_rules() ) {
			return array( 'success' => false, 'errors' => array( 'Database chưa có cấu trúc quản lý công việc đặc thù.' ) );
		}
		if ( empty( $preview['rows'] ) || empty( $preview['processed_periods'] ) ) {
			return array( 'success' => false, 'errors' => array( 'Không có dữ liệu hợp lệ để import.' ) );
		}

		$errors = array();
		foreach ( $preview['rows'] as $row ) {
			$employee = UMS_DB_Organization::get_by_employee_no( $row['employee_no'] );
			if ( ! $employee || ! UMS_DB_Annual_Allowance::special_work_type_matches_employee( $employee, $row['period_month'], $row['special_work_type'] ) ) {
				$errors[] = sprintf( 'MNV %s hoặc loại công việc đã thay đổi sau bước xem trước.', $row['employee_no'] );
			}
		}
		if ( ! empty( $errors ) ) {
			return array( 'success' => false, 'errors' => array_values( array_unique( $errors ) ) );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $preview['processed_periods'] as $period_month ) {
			if ( false === UMS_DB_Special_Work_Assignment::delete_by_period( $period_month ) ) {
				$errors[] = sprintf( 'Không thể làm mới danh sách gán T%d.', $period_month );
				break;
			}
		}
		if ( empty( $errors ) ) {
			foreach ( $preview['rows'] as $row ) {
				if ( ! UMS_DB_Special_Work_Assignment::upsert( $row['employee_no'], $row['period_month'], $row['special_work_type'], $user_id ) ) {
					$errors[] = sprintf( 'Không lưu được MNV %s kỳ T%d.', $row['employee_no'], $row['period_month'] );
					break;
				}
			}
		}

		if ( ! empty( $errors ) ) {
			$wpdb->query( 'ROLLBACK' );
			return array( 'success' => false, 'errors' => $errors );
		}
		$wpdb->query( 'COMMIT' );
		return array(
			'success' => true,
			'imported' => count( $preview['rows'] ),
			'periods' => $preview['processed_periods'],
		);
	}

	private static function detect_layout( $headers ) {
		$layout = array( 'employee_no' => '', 'full_name' => '', 'work_type' => '' );
		$aliases = array(
			'employee_no' => array( 'mnv', 'ma nv', 'ma nhan vien' ),
			'full_name'   => array( 'ho ten', 'ho va ten' ),
			'work_type'   => array( 'loai cong viec' ),
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

	private static function normalize_compare( $value ) {
		$value = self::normalize_space( $value );
		$value = function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value;
		return strtolower( $value );
	}

	private static function normalize_space( $value ) {
		return trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
	}
}
