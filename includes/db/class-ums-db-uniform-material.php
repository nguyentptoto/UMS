<?php
/**
 * Data layer for the uniform SAP material master imported from the GA workbook.
 */
class UMS_DB_Uniform_Material extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_sap_materials';
	}

	public static function batch_table() {
		return self::prefix() . 'uniform_sap_import_batches';
	}

	public static function is_ready() {
		$tables = array( self::table(), self::batch_table() );
		foreach ( $tables as $table ) {
			if ( self::db()->get_var( self::db()->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
				return false;
			}
		}

		return true;
	}

	public static function get_all( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array( 'search' => '', 'status' => '', 'limit' => 5000 )
		);
		$where  = array( '1=1' );
		$params = array();

		if ( $args['search'] !== '' ) {
			$like    = '%' . self::db()->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(materials.sap_code LIKE %s OR materials.item_name LIKE %s OR materials.product_name LIKE %s OR materials.size LIKE %s)';
			array_push( $params, $like, $like, $like, $like );
		}
		if ( $args['status'] === 'active' ) {
			$where[] = 'materials.is_active = 1';
		} elseif ( $args['status'] === 'inactive' ) {
			$where[] = 'materials.is_active = 0';
		} elseif ( $args['status'] === 'duplicate_sap' ) {
			$where[] = "materials.mapping_status = 'duplicate_sap' AND materials.is_active = 1";
		}

		$limit    = max( 1, min( 10000, absint( $args['limit'] ) ) );
		$sql      = 'SELECT materials.*, inventory.item_variant AS inventory_product_name, inventory.size AS inventory_size
			FROM ' . self::table() . ' materials
			LEFT JOIN ' . UMS_DB_Inventory::table() . ' inventory ON inventory.item_id = materials.inventory_item_id
			WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY materials.is_active DESC, materials.product_name ASC, materials.size ASC, materials.item_name ASC LIMIT %d';
		$params[] = $limit;

		return self::db()->get_results( self::db()->prepare( $sql, $params ), ARRAY_A );
	}

	public static function get_latest_batch() {
		return self::db()->get_row(
			'SELECT * FROM ' . self::batch_table() . " WHERE import_status = 'completed' ORDER BY batch_id DESC LIMIT 1",
			ARRAY_A
		);
	}

	public static function completed_hash_exists( $file_hash ) {
		$sql = self::db()->prepare(
			"SELECT COUNT(*) FROM " . self::batch_table() . " WHERE file_hash = %s AND import_status = 'completed'",
			sanitize_text_field( $file_hash )
		);

		return (int) self::db()->get_var( $sql ) > 0;
	}

	public static function create_batch( $data ) {
		$inserted = self::db()->insert(
			self::batch_table(),
			$data,
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) self::db()->insert_id;
	}

	public static function update_batch( $batch_id, $data ) {
		$format_map = array(
			'import_status' => '%s', 'inserted_rows' => '%d', 'updated_rows' => '%d',
			'deactivated_rows' => '%d', 'warning_count' => '%d', 'warnings_log' => '%s',
			'completed_at' => '%s',
		);
		$formats = array();
		foreach ( array_keys( $data ) as $field ) {
			$formats[] = isset( $format_map[ $field ] ) ? $format_map[ $field ] : '%s';
		}

		return self::db()->update(
			self::batch_table(),
			$data,
			array( 'batch_id' => absint( $batch_id ) ),
			$formats,
			array( '%d' )
		);
	}

	public static function get_existing_source_keys( $source_keys ) {
		$source_keys = array_values( array_filter( array_map( 'sanitize_text_field', $source_keys ) ) );
		if ( empty( $source_keys ) ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $source_keys ), '%s' ) );
		$sql = self::db()->prepare(
			'SELECT source_key FROM ' . self::table() . " WHERE source_key IN ($placeholders)",
			$source_keys
		);

		return self::db()->get_col( $sql );
	}

	public static function upsert( $data ) {
		$sql = self::db()->prepare(
			'INSERT INTO ' . self::table() . '
			(source_key, sap_code, item_name, product_name, size, inventory_item_id, mapping_status, source_row, source_batch_id, is_active, updated_at)
			VALUES (%s, %s, %s, %s, %s, %d, %s, %d, %d, 1, %s)
			ON DUPLICATE KEY UPDATE sap_code = VALUES(sap_code), item_name = VALUES(item_name),
			product_name = VALUES(product_name), size = VALUES(size), inventory_item_id = VALUES(inventory_item_id),
			mapping_status = VALUES(mapping_status),
			source_row = VALUES(source_row), source_batch_id = VALUES(source_batch_id), is_active = 1,
			updated_at = VALUES(updated_at)',
			$data['source_key'], $data['sap_code'], $data['item_name'], $data['product_name'],
			$data['size'], $data['inventory_item_id'], $data['mapping_status'], $data['source_row'], $data['source_batch_id'],
			$data['updated_at']
		);

		return false !== self::db()->query( $sql );
	}

	public static function deactivate_other_batches( $batch_id ) {
		$sql = self::db()->prepare(
			'UPDATE ' . self::table() . ' SET is_active = 0, updated_at = %s WHERE is_active = 1 AND source_batch_id <> %d',
			current_time( 'mysql' ),
			absint( $batch_id )
		);

		$result = self::db()->query( $sql );
		return false === $result ? false : (int) $result;
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}
}
