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
		$materials_by_id   = array();

		foreach ( $material_rows as $material ) {
			$name_key = self::normalize( $material['item_name'] );
			$materials_by_name[ $name_key ][] = $material;
			$materials_by_item[ absint( $material['inventory_item_id'] ) ][] = $material;
			$materials_by_id[ absint( $material['material_id'] ) ] = $material;
		}

		$errors              = $reserve['errors'];
		$reserve_by_material = array();
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

			$material_id = absint( $matches[0]['material_id'] );
			$reserve_by_material[ $material_id ] = isset( $reserve_by_material[ $material_id ] )
				? $reserve_by_material[ $material_id ] + $row['quantity']
				: $row['quantity'];
		}

		$periodic_by_item = UMS_DB_Request::get_completed_demand_by_item( $year );
		foreach ( $periodic_by_item as $item_id => $quantity ) {
			if ( $quantity > 0 && empty( $materials_by_item[ absint( $item_id ) ] ) ) {
				$errors[] = sprintf( 'Sản phẩm kho #%d có nhu cầu đã duyệt nhưng chưa được ánh xạ master SAP.', $item_id );
			}
		}
		$relevant_item_ids = array_keys( array_filter( $periodic_by_item ) );
		foreach ( array_keys( array_filter( $reserve_by_material ) ) as $material_id ) {
			if ( isset( $materials_by_id[ absint( $material_id ) ] ) ) {
				$relevant_item_ids[] = absint( $materials_by_id[ absint( $material_id ) ]['inventory_item_id'] );
			}
		}
		$relevant_item_ids = array_values( array_unique( array_filter( array_map( 'absint', $relevant_item_ids ) ) ) );

		if ( ! empty( $errors ) ) {
			return array(
				'success'  => false,
				'errors'   => array_values( array_unique( $errors ) ),
				'warnings' => array(),
				'rows'     => array(),
			);
		}

		$rows     = array();
		$warnings = array();

		foreach ( $relevant_item_ids as $item_id ) {
			$matches  = isset( $materials_by_item[ $item_id ] ) ? $materials_by_item[ $item_id ] : array();
			$selected = array();
			foreach ( $matches as $material ) {
				$material_id = absint( $material['material_id'] );
				if ( ! empty( $reserve_by_material[ $material_id ] ) ) {
					$selected[ $material_id ] = $material;
				}
			}

			$periodic_total = isset( $periodic_by_item[ $item_id ] ) ? max( 0, (int) $periodic_by_item[ $item_id ] ) : 0;
			if ( $periodic_total > 0 && empty( $selected ) ) {
				if ( count( $matches ) === 1 ) {
					$material = reset( $matches );
					$selected[ absint( $material['material_id'] ) ] = $material;
				} else {
					$errors[] = sprintf(
						'Sản phẩm kho #%d có nhu cầu định kỳ nhưng liên kết với %d Loại/mã SAP. Phiếu hiện chưa lưu Loại master nên chưa thể xác định mã SAP.',
						$item_id,
						count( $matches )
					);
					continue;
				}
			}

			if ( empty( $selected ) ) {
				continue;
			}

			$periodic_weights = array();
			foreach ( $selected as $material_id => $material ) {
				$periodic_weights[ $material_id ] = max( 0, (int) ( $reserve_by_material[ $material_id ] ?? 0 ) );
			}
			$periodic_allocations = self::allocate_integer( $periodic_total, $periodic_weights );
			$gross_by_material    = array();
			foreach ( $selected as $material_id => $material ) {
				$gross_by_material[ $material_id ] = max( 0, (int) ( $reserve_by_material[ $material_id ] ?? 0 ) )
					+ max( 0, (int) ( $periodic_allocations[ $material_id ] ?? 0 ) );
			}

			$reference         = reset( $selected );
			$stock_total       = max( 0, (int) $reference['inventory_stock_qty'] );
			$gross_total       = array_sum( $gross_by_material );
			$final_total       = max( 0, $gross_total - $stock_total );
			$final_allocations = self::allocate_integer( $final_total, $gross_by_material );

			foreach ( $selected as $material_id => $material ) {
				$periodic_qty = max( 0, (int) ( $periodic_allocations[ $material_id ] ?? 0 ) );
				$reserve_qty  = max( 0, (int) ( $reserve_by_material[ $material_id ] ?? 0 ) );
				$gross_qty    = max( 0, (int) ( $gross_by_material[ $material_id ] ?? 0 ) );
				$final_qty    = max( 0, (int) ( $final_allocations[ $material_id ] ?? 0 ) );
				$stock_qty    = max( 0, $gross_qty - $final_qty );
				$base_price   = max( 0, (float) $material['inventory_base_price'] );

				if ( $final_qty > 0 && $base_price <= 0 ) {
					$warnings[] = sprintf( '%s chưa có đơn giá; cần cập nhật trước khi xuất PR.', $material['item_name'] );
				}

				$rows[] = array(
					'material_id'       => $material_id,
					'inventory_item_id' => $item_id,
					'sap_code'          => (string) $material['sap_code'],
					'item_name'         => (string) $material['item_name'],
					'product_name'      => (string) $material['product_name'],
					'size'              => (string) $material['size'],
					'periodic_qty'      => $periodic_qty,
					'reserve_qty'       => $reserve_qty,
					'stock_qty'         => $stock_qty,
					'final_pr_qty'      => $final_qty,
					'base_price'        => $base_price,
					'estimated_amount'  => $final_qty * $base_price,
				);
			}
		}

		if ( ! empty( $errors ) ) {
			return array(
				'success'  => false,
				'errors'   => array_values( array_unique( $errors ) ),
				'warnings' => array_values( array_unique( $warnings ) ),
				'rows'     => array(),
			);
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

	/**
	 * Phân bổ một tổng số nguyên theo trọng số và giữ nguyên chính xác tổng đầu vào.
	 */
	private static function allocate_integer( $total, $weights ) {
		$total   = max( 0, (int) $total );
		$weights = array_map(
			function ( $weight ) {
				return max( 0, (int) $weight );
			},
			$weights
		);
		$result = array_fill_keys( array_keys( $weights ), 0 );
		if ( $total === 0 || empty( $weights ) ) {
			return $result;
		}

		$weight_total = array_sum( $weights );
		if ( $weight_total <= 0 ) {
			$keys = array_keys( $result );
			$result[ reset( $keys ) ] = $total;
			return $result;
		}

		$fractions = array();
		$allocated = 0;
		foreach ( $weights as $key => $weight ) {
			$exact             = $total * $weight / $weight_total;
			$result[ $key ]    = (int) floor( $exact );
			$fractions[ $key ] = $exact - $result[ $key ];
			$allocated        += $result[ $key ];
		}

		arsort( $fractions, SORT_NUMERIC );
		foreach ( array_keys( $fractions ) as $key ) {
			if ( $allocated >= $total ) {
				break;
			}
			$result[ $key ]++;
			$allocated++;
		}

		return $result;
	}

	private static function normalize( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		$value = remove_accents( $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}
}
