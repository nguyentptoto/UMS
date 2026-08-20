<?php
/**
 * Data layer for inventory import batches.
 */
class UMS_DB_Inventory_Import extends UMS_DB_Base {

	public static function table() {
		return self::prefix() . 'uniform_inventory_import_batches';
	}

	public static function is_ready() {
		$table = self::table();
		if ( self::db()->get_var( self::db()->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return false;
		}

		$movement_table = UMS_DB_Inventory_Movement::table();
		$columns        = self::db()->get_col( "SHOW COLUMNS FROM $movement_table", 0 );

		return in_array( 'import_batch_id', $columns, true ) && in_array( 'source_row', $columns, true );
	}

	public static function completed_hash_exists( $file_hash ) {
		$sql = self::db()->prepare(
			"SELECT COUNT(*) FROM " . self::table() . " WHERE file_hash = %s AND import_status = 'completed'",
			sanitize_text_field( $file_hash )
		);

		return (int) self::db()->get_var( $sql ) > 0;
	}

	public static function insert( $data ) {
		$inserted = self::db()->insert(
			self::table(),
			$data,
			array( '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s' )
		);

		return false === $inserted ? 0 : (int) self::db()->insert_id;
	}

	public static function update( $batch_id, $data ) {
		$formats = array(
			'import_status'  => '%s',
			'imported_rows'  => '%d',
			'total_quantity' => '%d',
			'error_count'    => '%d',
			'error_log'      => '%s',
			'completed_at'   => '%s',
		);
		$data_formats = array();
		foreach ( array_keys( $data ) as $field ) {
			$data_formats[] = isset( $formats[ $field ] ) ? $formats[ $field ] : '%s';
		}

		return self::db()->update(
			self::table(),
			$data,
			array( 'batch_id' => absint( $batch_id ) ),
			$data_formats,
			array( '%d' )
		);
	}

	public static function get_last_error() {
		return self::db()->last_error;
	}
}
