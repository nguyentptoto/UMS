<?php
/**
 * Tính số lượng đề nghị mua từ nhu cầu đã duyệt, dự phòng và tồn kho hiện tại.
 */
class UMS_PR_Calculator {
	const RESERVE_SHEET = 'Sheet1';

	public static function calculate( $file_path, $year, $period_month ) {
		$year         = absint( $year );
		$period_month = absint( $period_month );

		if ( $year < 2000 || $year > 2100 ) {
			throw new InvalidArgumentException( 'Năm lập PR không hợp lệ.' );
		}
		if ( ! in_array( $period_month, array( 4, 9 ), true ) ) {
			throw new InvalidArgumentException( 'Kỳ lập PR chỉ hỗ trợ tháng 4 hoặc tháng 9.' );
		}
		if ( ! UMS_DB_Uniform_Material::is_ready() ) {
			throw new RuntimeException( 'Chưa có cấu trúc master mã SAP. Hãy import đầy đủ file ums.sql.' );
		}

		$reserve  = self::read_reserve_file( $file_path );
		$material_rows = UMS_DB_Uniform_Material::get_all(
			array(
				'status' => 'active',
				'limit'  => 10000,
			)
		);
		$materials_by_name = array();
		$materials_by_item = array();

		foreach ( $material_rows as $material ) {
			$name_key = self::normalize( $material['item_name'] );
			$materials_by_name[ $name_key ][] = $material;
			$materials_by_item[ absint( $material['inventory_item_id'] ) ][] = $material;
		}

		$errors          = $reserve['errors'];
		$reserve_by_item = array();
		foreach ( $reserve['rows'] as $row ) {
			$key     = self::normalize( $row['item_name'] );
			$matches = isset( $materials_by_name[ $key ] ) ? $materials_by_name[ $key ] : array();

			if ( count( $matches ) !== 1 ) {
				$errors[] = sprintf(
					'Dòng %d: Loại đồng phục dự phòng "%s" phải khớp đúng một dòng master SAP (hiện có %d).',
					$row['source_row'],
					$row['item_name'],
					count( $matches )
				);
				continue;
			}

			$item_id = absint( $matches[0]['inventory_item_id'] );
			$reserve_by_item[ $item_id ] = isset( $reserve_by_item[ $item_id ] )
				? $reserve_by_item[ $item_id ] + $row['quantity']
				: $row['quantity'];
		}

		$periodic_by_item = UMS_DB_Request::get_completed_demand_by_item( $year );
		foreach ( $periodic_by_item as $item_id => $quantity ) {
			if ( $quantity > 0 && empty( $materials_by_item[ absint( $item_id ) ] ) ) {
				$errors[] = sprintf( 'Sản phẩm kho #%d có nhu cầu đã duyệt nhưng chưa được ánh xạ master SAP.', $item_id );
			}
		}
		$relevant_item_ids = array_unique(
			array_merge(
				array_keys( array_filter( $periodic_by_item ) ),
				array_keys( array_filter( $reserve_by_item ) )
			)
		);
		foreach ( $relevant_item_ids as $item_id ) {
			$match_count = isset( $materials_by_item[ absint( $item_id ) ] ) ? count( $materials_by_item[ absint( $item_id ) ] ) : 0;
			if ( $match_count > 1 ) {
				$errors[] = sprintf( 'Sản phẩm kho #%d đang liên kết với %d dòng master SAP; không thể phân bổ số lượng chính xác.', $item_id, $match_count );
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'success'  => false,
				'errors'   => array_values( array_unique( $errors ) ),
				'warnings' => array(),
				'rows'     => array(),
			);
		}

		$included_item_ids = $relevant_item_ids;
		$rows     = array();
		$warnings = array();

		foreach ( $included_item_ids as $item_id ) {
			$matches = isset( $materials_by_item[ absint( $item_id ) ] ) ? $materials_by_item[ absint( $item_id ) ] : array();
			foreach ( $matches as $material ) {
				$periodic_qty = isset( $periodic_by_item[ $item_id ] ) ? max( 0, (int) $periodic_by_item[ $item_id ] ) : 0;
				$reserve_qty  = isset( $reserve_by_item[ $item_id ] ) ? max( 0, (int) $reserve_by_item[ $item_id ] ) : 0;
				$stock_qty    = max( 0, (int) $material['inventory_stock_qty'] );
				$final_qty    = max( 0, $periodic_qty + $reserve_qty - $stock_qty );
				$base_price   = max( 0, (float) $material['inventory_base_price'] );

				if ( $final_qty > 0 && $base_price <= 0 ) {
					$warnings[] = sprintf( '%s chưa có đơn giá; cần cập nhật trước khi xuất PR.', $material['item_name'] );
				}

				$rows[] = array(
					'material_id'     => absint( $material['material_id'] ),
					'inventory_item_id' => absint( $item_id ),
					'sap_code'        => (string) $material['sap_code'],
					'item_name'       => (string) $material['item_name'],
					'product_name'    => (string) $material['product_name'],
					'size'            => (string) $material['size'],
					'periodic_qty'    => $periodic_qty,
					'reserve_qty'     => $reserve_qty,
					'stock_qty'       => $stock_qty,
					'final_pr_qty'    => $final_qty,
					'base_price'      => $base_price,
					'estimated_amount' => $final_qty * $base_price,
				);
			}
		}

		usort(
			$rows,
			function ( $left, $right ) {
				return strnatcasecmp( $left['item_name'], $right['item_name'] );
			}
		);

		return array(
			'success'  => true,
			'errors'   => array(),
			'warnings' => array_values( array_unique( $warnings ) ),
			'rows'     => $rows,
			'summary'  => array(
				'row_count'     => count( $rows ),
				'periodic_qty'  => array_sum( array_column( $rows, 'periodic_qty' ) ),
				'reserve_qty'   => array_sum( array_column( $rows, 'reserve_qty' ) ),
				'stock_qty'     => array_sum( array_column( $rows, 'stock_qty' ) ),
				'final_pr_qty'  => array_sum( array_column( $rows, 'final_pr_qty' ) ),
				'estimated_amount' => array_sum( array_column( $rows, 'estimated_amount' ) ),
			),
			'year'         => $year,
			'period_month' => $period_month,
			'can_export'   => empty( $warnings ) && array_sum( array_column( $rows, 'final_pr_qty' ) ) > 0,
		);
	}

	public static function read_reserve_file( $file_path ) {
		$reader = new UMS_XLSX_Reader( $file_path );
		if ( ! $reader->has_sheet( self::RESERVE_SHEET ) ) {
			throw new RuntimeException( 'Không tìm thấy sheet Sheet1 trong file số lượng dự phòng.' );
		}

		$sheet   = $reader->read_sheet( self::RESERVE_SHEET );
		$headers = isset( $sheet[1] ) ? $sheet[1] : array();
		$errors  = array();
		$rows    = array();
		$seen    = array();

		if ( self::normalize( isset( $headers['A'] ) ? $headers['A'] : '' ) !== self::normalize( 'Loại đồng phục dự phòng' ) ) {
			$errors[] = 'Cột A phải là "Loại đồng phục dự phòng".';
		}
		if ( self::normalize( isset( $headers['B'] ) ? $headers['B'] : '' ) !== self::normalize( 'Số lượng' ) ) {
			$errors[] = 'Cột B phải là "Số lượng".';
		}

		foreach ( $sheet as $row_number => $source ) {
			if ( $row_number < 2 ) {
				continue;
			}

			$item_name = trim( sanitize_text_field( isset( $source['A'] ) ? $source['A'] : '' ) );
			$raw_qty   = trim( (string) ( isset( $source['B'] ) ? $source['B'] : '' ) );
			if ( $item_name === '' && $raw_qty === '' ) {
				continue;
			}
			if ( $item_name === '' ) {
				$errors[] = sprintf( 'Dòng %d: Chưa có Loại đồng phục dự phòng.', $row_number );
				continue;
			}
			if ( $raw_qty === '' || ! preg_match( '/^\d+$/', $raw_qty ) ) {
				$errors[] = sprintf( 'Dòng %d: Số lượng phải là số nguyên không âm.', $row_number );
				continue;
			}

			$key = self::normalize( $item_name );
			if ( isset( $seen[ $key ] ) ) {
				$errors[] = sprintf( 'Dòng %d: "%s" đã xuất hiện tại dòng %d.', $row_number, $item_name, $seen[ $key ] );
				continue;
			}
			$seen[ $key ] = $row_number;
			$rows[] = array(
				'item_name'  => $item_name,
				'quantity'   => (int) $raw_qty,
				'source_row' => (int) $row_number,
			);
		}

		if ( empty( $rows ) && empty( $errors ) ) {
			$errors[] = 'File số lượng dự phòng không có dữ liệu.';
		}

		return array( 'rows' => $rows, 'errors' => array_values( array_unique( $errors ) ) );
	}

	private static function normalize( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		$value = remove_accents( $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}
}
