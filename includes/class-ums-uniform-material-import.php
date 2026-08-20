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
			$product = $resolved_products[ $preview_row['product_name'] ];
			$matches = UMS_DB_Inventory::get_by_product_size( $product['category_id'], $product['item_variant'], $preview_row['size'] );
			if ( count( $matches ) !== 1 ) {
				$mapping_errors[] = sprintf(
					'Dòng %d: sản phẩm "%s" size "%s" phải khớp đúng một dòng trong kho (hiện có %d).',
					$preview_row['source_row'], $product['item_variant'], $preview_row['size'], count( $matches )
				);
				continue;
			}
			$preview_row['inventory_item_id'] = absint( $matches[0]['item_id'] );
		}
		unset( $preview_row );

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
}
