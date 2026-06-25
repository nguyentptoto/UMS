<?php
/**
 * Data layer for uniform issue requests.
 */
class UMS_DB_Request extends UMS_DB_Base {

    public static function table() {
        return self::prefix() . 'uniform_requests';
    }

    public static function detail_table() {
        return self::prefix() . 'uniform_request_details';
    }

    public static function log_table() {
        return self::prefix() . 'uniform_approval_logs';
    }

    public static function insert_with_details( $request, $details ) {
        $wpdb = self::db();

        $wpdb->query( 'START TRANSACTION' );

        $inserted = $wpdb->insert(
            self::table(),
            $request,
            array( '%d', '%d', '%s', '%d', '%s', '%d', '%s' )
        );

        if ( ! $inserted ) {
            $wpdb->query( 'ROLLBACK' );
            return false;
        }

        $request_id = (int) $wpdb->insert_id;

        foreach ( $details as $detail ) {
            $detail_inserted = $wpdb->insert(
                self::detail_table(),
                array(
                    'request_id'       => $request_id,
                    'item_id'          => (int) $detail['item_id'],
                    'quantity'         => (int) $detail['quantity'],
                    'price_at_request' => (float) $detail['price_at_request'],
                ),
                array( '%d', '%d', '%d', '%f' )
            );

            if ( ! $detail_inserted ) {
                $wpdb->query( 'ROLLBACK' );
                return false;
            }
        }

        $wpdb->query( 'COMMIT' );
        return $request_id;
    }

    public static function get_last_error() {
        return self::db()->last_error;
    }
}
