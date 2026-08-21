<?php
/**
 * Read and import the "Mã đồng phục" sheet from the GA workbook.
 */
class UMS_Uniform_Material_Import {
	const PREVIEW_TRANSIENT_PREFIX = 'ums_sap_material_preview_';
	const PREVIEW_TTL              = 3600;

	public static function analyze( $file_path, $file_name ) {
		$reader     = new UMS_XLSX_Reader( $file_path );
		$sheet_name = self::find_sheet_name( $reader );
		$sheet      = $reader->read_sheet( $sheet_name );
		$errors     = array();
		$warnings   = array();
		$rows       = array();
		$seen_names = array();
		$headers    = isset( $sheet[1] ) ? $sheet[1] : array();
		$expected   = array(
			'A' => 'Mã đồng phục', 'B' => 'Loại', 'C' => 'Loại đồng phục lên PR', 'D' => 'Size',
		);

		foreach ( $expected as $column => $label ) {
			if ( self::normalize( isset( $headers[ $column ] ) ? $headers[ $column ] : '' ) !== self::normalize( $label ) ) {
				$errors[] = sprintf( 'Cột %s của sheet Mã đồng phục phải là "%s".', $column, $label );
			}
		}

		foreach ( $sheet as $row_number => $row ) {
			if ( $row_number < 2 ) {
				continue;
			}

			$sap_code     = trim( sanitize_text_field( isset( $row['A'] ) ? $row['A'] : '' ) );
			$item_name    = trim( sanitize_text_field( isset( $row['B'] ) ? $row['B'] : '' ) );
			$product_name = trim( sanitize_text_field( isset( $row['C'] ) ? $row['C'] : '' ) );
			$size         = trim( sanitize_text_field( isset( $row['D'] ) ? $row['D'] : '' ) );

			if ( $sap_code === '' && $item_name === '' && $product_name === '' && $size === '' ) {
				continue;
			}
			if ( $sap_code === '' || ! preg_match( '/^[0-9]{1,30}$/', $sap_code ) ) {
				$errors[] = sprintf( 'Dòng %d: Mã đồng phục phải gồm từ 1 đến 30 chữ số.', $row_number );
				continue;
			}
			if ( $item_name === '' ) {
				$errors[] = sprintf( 'Dòng %d: Chưa có giá trị cột Loại.', $row_number );
				continue;
			}
			if ( $product_name === '' ) {
				$errors[] = sprintf( 'Dòng %d: Chưa có Loại đồng phục lên PR.', $row_number );
				continue;
			}

			$source_key = hash( 'sha256', self::normalize( $item_name ) );
			if ( isset( $seen_names[ $source_key ] ) ) {
				$errors[] = sprintf( 'Dòng %d: Loại "%s" đã xuất hiện tại dòng %d.', $row_number, $item_name, $seen_names[ $source_key ] );
				continue;
			}
			$seen_names[ $source_key ] = (int) $row_number;
			$rows[] = array(
				'source_key' => $source_key, 'sap_code' => $sap_code, 'item_name' => $item_name,
				'product_name' => $product_name, 'size' => $size, 'source_row' => (int) $row_number,
				'mapping_status' => 'valid',
			);
		}

		$code_counts = array_count_values( array_column( $rows, 'sap_code' ) );
		foreach ( $rows as &$row ) {
			if ( isset( $code_counts[ $row['sap_code'] ] ) && $code_counts[ $row['sap_code'] ] > 1 ) {
				$row['mapping_status'] = 'duplicate_sap';
			}
		}
		unset( $row );

		foreach ( $code_counts as $sap_code => $count ) {
			if ( $count > 1 ) {
				$warnings[] = sprintf( 'Mã SAP %s đang được dùng cho %d loại/size.', $sap_code, $count );
			}
		}
		if ( empty( $rows ) && empty( $errors ) ) {
			$errors[] = 'Sheet Mã đồng phục không có dòng dữ liệu nào.';
		}

		return array(
			'file_name' => sanitize_file_name( $file_name ), 'file_hash' => hash_file( 'sha256', $file_path ),
			'sheet_name' => $sheet_name, 'rows' => array_values( $rows ),
			'product_names' => array_values( array_unique( array_column( $rows, 'product_name' ) ) ),
			'errors' => array_values( array_unique( $errors ) ),
			'warnings' => array_values( array_unique( $warnings ) ),
			'summary' => array(
				'total' => count( $rows ),
				'product_groups' => count( array_unique( array_column( $rows, 'product_name' ) ) ),
				'duplicate_rows' => count( array_filter( $rows, function( $row ) { return $row['mapping_status'] === 'duplicate_sap'; } ) ),
			),
		);
	}

	public static function store_preview( $preview ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::PREVIEW_TRANSIENT_PREFIX . $token, $preview, self::PREVIEW_TTL ) ) {
			throw new RuntimeException( 'Không lưu được dữ liệu xem trước mã SAP.' );
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
		if ( ! UMS_DB_Uniform_Material::is_ready() ) {
			return array( 'success' => false, 'errors' => array( 'Database chưa có cấu trúc master mã SAP trong ums.sql.' ) );
		}
		if ( UMS_DB_Uniform_Material::completed_hash_exists( $preview['file_hash'] ) ) {
			return array( 'success' => false, 'errors' => array( 'File GA này đã được import thành công trước đó.' ) );
		}

		$resolved_products = array();
		$mapping_errors    = array();
		$product_names = isset( $preview['product_names'] ) && is_array( $preview['product_names'] )
			? $preview['product_names']
			: array_values( array_unique( array_column( $preview['rows'], 'product_name' ) ) );
		foreach ( $product_names as $product_name ) {
			$mapping_key = hash( 'sha256', $product_name );
			$raw_mapping = isset( $mappings[ $mapping_key ] ) ? sanitize_text_field( $mappings[ $mapping_key ] ) : '';
			$parts       = explode( '|', $raw_mapping, 2 );
			$category_id = isset( $parts[0] ) ? absint( $parts[0] ) : 0;
			$item_variant = isset( $parts[1] ) ? sanitize_text_field( $parts[1] ) : '';

			if ( $category_id <= 0 || $item_variant === '' || ! UMS_DB_Inventory::product_group_exists( $category_id, $item_variant ) ) {
				$mapping_errors[] = 'Chưa ánh xạ Loại đồng phục lên PR: ' . $product_name . '.';
				continue;
			}
			$resolved_products[ $product_name ] = array(
				'category_id' => $category_id,
				'item_variant' => $item_variant,
			);
		}

		foreach ( $preview['rows'] as &$preview_row ) {
			if ( ! isset( $resolved_products[ $preview_row['product_name'] ] ) ) {
				continue;
			}
			$product      = $resolved_products[ $preview_row['product_name'] ];
			$product_rows = UMS_DB_Inventory::get_product_rows( $product['category_id'], $product['item_variant'] );
			$matches      = self::find_size_matches( $product_rows, $preview_row['size'] );
			if ( count( $matches ) > 1 ) {
				$mapping_errors[] = sprintf(
					'Dòng %d: sản phẩm "%s" size "%s" đang trùng %d dòng trong kho.',
					$preview_row['source_row'], $product['item_variant'], $preview_row['size'], count( $matches )
				);
				continue;
			}
			if ( count( $matches ) === 1 ) {
				$preview_row['inventory_item_id'] = absint( $matches[0]['item_id'] );
				continue;
			}

			$price_result = self::resolve_product_price( $product_rows );
			if ( $price_result['ambiguous'] ) {
				$mapping_errors[] = sprintf(
					'Dòng %d: sản phẩm "%s" đang có nhiều đơn giá. Hãy chuẩn hóa đơn giá trước khi import mã SAP.',
					$preview_row['source_row'], $product['item_variant']
				);
				continue;
			}
			$reference = reset( $product_rows );
			$preview_row['inventory_item_id'] = 0;
			$preview_row['create_inventory']  = array(
				'category_id'  => absint( $product['category_id'] ),
				'item_type'    => isset( $reference['item_type'] ) ? (string) $reference['item_type'] : '',
				'item_variant' => (string) $product['item_variant'],
				'size'         => self::canonical_size( $preview_row['size'] ),
				'color_code'   => '',
				'stock_qty'    => 0,
				'base_price'   => $price_result['price'],
			);
			$preview['warnings'][] = sprintf(
				'Dòng %d: size "%s" của "%s" chưa có trong kho và sẽ được tạo với tồn 0.',
				$preview_row['source_row'], $preview_row['size'], $product['item_variant']
			);
		}
		unset( $preview_row );

		$target_sap_codes = array();
		$target_labels    = array();
		foreach ( $preview['rows'] as $row ) {
			if ( ! empty( $row['inventory_item_id'] ) ) {
				$target_key   = 'item:' . absint( $row['inventory_item_id'] );
				$target_label = 'sản phẩm kho #' . absint( $row['inventory_item_id'] );
			} elseif ( ! empty( $row['create_inventory'] ) ) {
				$create       = $row['create_inventory'];
				$target_key   = 'new:' . absint( $create['category_id'] ) . '|' . self::normalize( $create['item_variant'] ) . '|' . self::normalize_size( $create['size'] );
				$target_label = sprintf( 'sản phẩm "%s" size "%s"', $create['item_variant'], $create['size'] );
			} else {
				continue;
			}

			$target_sap_codes[ $target_key ][ trim( (string) $row['sap_code'] ) ] = true;
			$target_labels[ $target_key ] = $target_label;
		}

		foreach ( $target_sap_codes as $target_key => $sap_codes ) {
			$codes = array_keys( $sap_codes );
			if ( count( $codes ) > 1 ) {
				$mapping_errors[] = sprintf(
					'%s đang được ánh xạ tới nhiều mã SAP (%s). Mỗi sản phẩm/size chỉ được có một mã SAP đầu ra.',
					$target_labels[ $target_key ],
					implode( ', ', $codes )
				);
			}
		}

		if ( ! empty( $mapping_errors ) ) {
			return array( 'success' => false, 'errors' => array_values( array_unique( $mapping_errors ) ) );
		}

		$batch_id = UMS_DB_Uniform_Material::create_batch(
			array(
				'file_name' => $preview['file_name'], 'file_hash' => $preview['file_hash'],
				'import_status' => 'processing', 'total_rows' => count( $preview['rows'] ),
				'inserted_rows' => 0, 'updated_rows' => 0, 'deactivated_rows' => 0,
				'warning_count' => count( $preview['warnings'] ),
				'warnings_log' => wp_json_encode( $preview['warnings'], JSON_UNESCAPED_UNICODE ),
				'imported_by' => absint( $user_id ), 'created_at' => current_time( 'mysql' ), 'completed_at' => null,
			)
		);
		if ( $batch_id <= 0 ) {
			return array( 'success' => false, 'errors' => array( 'Không tạo được phiên import mã SAP: ' . UMS_DB_Uniform_Material::get_last_error() ) );
		}

		$existing_keys = array_flip( UMS_DB_Uniform_Material::get_existing_source_keys( array_column( $preview['rows'], 'source_key' ) ) );
		$inserted      = 0;
		$updated       = 0;
		$deactivated   = 0;
		$errors        = array();
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );

		foreach ( $preview['rows'] as $row ) {
			if ( empty( $row['inventory_item_id'] ) && ! empty( $row['create_inventory'] ) ) {
				$create_data  = $row['create_inventory'];
				$current_rows = UMS_DB_Inventory::get_product_rows( $create_data['category_id'], $create_data['item_variant'] );
				$current_size = self::find_size_matches( $current_rows, $create_data['size'] );
				if ( count( $current_size ) > 1 ) {
					$errors[] = sprintf( 'Dòng %d: size vừa được tạo trùng trong kho.', $row['source_row'] );
					break;
				}
				if ( count( $current_size ) === 1 ) {
					$row['inventory_item_id'] = absint( $current_size[0]['item_id'] );
				} elseif ( false === UMS_DB_Inventory::insert( $create_data ) ) {
					$errors[] = sprintf( 'Dòng %d: không tạo được size mới trong kho: %s', $row['source_row'], UMS_DB_Inventory::get_last_error() );
					break;
				} else {
					$row['inventory_item_id'] = UMS_DB_Inventory::get_last_insert_id();
				}
			}
			$row['source_batch_id'] = $batch_id;
			$row['updated_at']      = current_time( 'mysql' );
			if ( ! UMS_DB_Uniform_Material::upsert( $row ) ) {
				$errors[] = sprintf( 'Không ghi được dòng Excel %d: %s', $row['source_row'], UMS_DB_Uniform_Material::get_last_error() );
				break;
			}
			if ( isset( $existing_keys[ $row['source_key'] ] ) ) {
				$updated++;
			} else {
				$inserted++;
			}
		}

		if ( empty( $errors ) ) {
			$deactivated = UMS_DB_Uniform_Material::deactivate_other_batches( $batch_id );
			if ( false === $deactivated ) {
				$errors[] = 'Không vô hiệu hóa được các dòng đã bị xóa khỏi file GA.';
			}
		}

		if ( empty( $errors ) ) {
			$wpdb->query( 'COMMIT' );
		} else {
			$wpdb->query( 'ROLLBACK' );
			$inserted = 0;
			$updated = 0;
			$deactivated = 0;
		}

		UMS_DB_Uniform_Material::update_batch(
			$batch_id,
			array(
				'import_status' => empty( $errors ) ? 'completed' : 'failed',
				'inserted_rows' => $inserted, 'updated_rows' => $updated, 'deactivated_rows' => $deactivated,
				'warning_count' => count( $preview['warnings'] ),
				'warnings_log' => wp_json_encode( array_merge( $preview['warnings'], $errors ), JSON_UNESCAPED_UNICODE ),
				'completed_at' => current_time( 'mysql' ),
			)
		);

		return array(
			'success' => empty( $errors ), 'batch_id' => $batch_id, 'inserted' => $inserted,
			'updated' => $updated, 'deactivated' => $deactivated, 'errors' => $errors,
		);
	}

	private static function find_sheet_name( $reader ) {
		foreach ( array( ' Mã đồng phục', 'Mã đồng phục' ) as $sheet_name ) {
			if ( $reader->has_sheet( $sheet_name ) ) {
				return $sheet_name;
			}
		}
		throw new RuntimeException( 'Không tìm thấy sheet Mã đồng phục trong file GA.' );
	}

	private static function normalize( $value ) {
		$value = preg_replace( '/\s+/u', ' ', trim( (string) $value ) );
		$value = remove_accents( $value );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	private static function find_size_matches( $rows, $size ) {
		$normalized = self::normalize_size( $size );
		return array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $normalized ) {
					return self::normalize_size( isset( $row['size'] ) ? $row['size'] : '' ) === $normalized;
				}
			)
		);
	}

	private static function normalize_size( $size ) {
		$size = strtoupper( preg_replace( '/\s+/u', '', trim( (string) $size ) ) );
		$aliases = array( '' => '0', '2XL' => 'XXL', '3XL' => 'XXXL' );
		return isset( $aliases[ $size ] ) ? $aliases[ $size ] : $size;
	}

	private static function canonical_size( $size ) {
		return self::normalize_size( $size );
	}

	private static function resolve_product_price( $rows ) {
		$prices = array();
		foreach ( $rows as $row ) {
			$price = isset( $row['base_price'] ) ? round( (float) $row['base_price'], 2 ) : 0;
			if ( $price > 0 ) {
				$prices[ number_format( $price, 2, '.', '' ) ] = $price;
			}
		}
		return array(
			'price'     => count( $prices ) === 1 ? (float) reset( $prices ) : 0.0,
			'ambiguous' => count( $prices ) > 1,
		);
	}
}
