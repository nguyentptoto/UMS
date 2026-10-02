<?php
/** Run: php tools/test-maternity.php. Uses only an isolated in-memory SQLite DB. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
define( 'ARRAY_A', 'ARRAY_A' );
define( 'HOUR_IN_SECONDS', 3600 );
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_textarea_field( $v ) { return sanitize_text_field( $v ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ) ); }
function sanitize_file_name( $v ) { return $v; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function current_time( $format ) { return date( $format === 'mysql' ? 'Y-m-d H:i:s' : $format, strtotime( $GLOBALS['test_clock'] ?? '2027-12-31 12:00:00' ) ); }
function update_user_meta( $id, $key, $value ) { $GLOBALS['test_meta'][ $id ][ $key ] = $value; }
function apply_filters( $name, $value ) { return $value; }
function get_locale() { return 'en_US'; }
function wp_parse_args( $a, $b ) { return array_merge( $b, $a ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error {
	public $code; public $message;
	public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
function remove_accents( $value ) { return strtr( $value, array( 'Á' => 'A', 'á' => 'a', 'ầ' => 'a', 'ạ' => 'a', 'ọ' => 'o', 'ộ' => 'o' ) ); }

class TestDB {
	public $prefix = 't_'; public $users = 't_users'; public $usermeta = 't_usermeta';
	public $last_error = ''; public $insert_id = 0; public $pdo; public $fail_movement = false;
	public function __construct() { $this->pdo = new PDO( 'sqlite::memory:' ); $this->pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION ); }
	public function prepare( $sql, ...$args ) {
		if ( count( $args ) === 1 && is_array( $args[0] ) ) { $args = $args[0]; }
		$i = 0;
		return preg_replace_callback( '/%[sdf]/', function ( $m ) use ( &$i, $args ) {
			$value = $args[ $i++ ];
			return $m[0] === '%s' ? $this->pdo->quote( (string) $value ) : (string) (float) $value;
		}, $sql );
	}
	public function query( $sql ) {
		$this->last_error = '';
		try { return $this->pdo->exec( $sql === 'START TRANSACTION' ? 'BEGIN' : $sql ); }
		catch ( Throwable $e ) { $this->last_error = $e->getMessage(); return false; }
	}
	public function insert( $table, $data, $formats = null ) {
		if ( $table === 't_movements' && $this->fail_movement ) { return false; }
		$values = array_map( function ( $v ) { return $v === null ? 'NULL' : $this->pdo->quote( (string) $v ); }, $data );
		$result = $this->query( 'INSERT INTO ' . $table . ' (' . implode( ',', array_keys( $data ) ) . ') VALUES (' . implode( ',', $values ) . ')' );
		$this->insert_id = (int) $this->pdo->lastInsertId();
		return $result;
	}
	public function update( $table, $data, $where, $formats = null, $where_formats = null ) {
		$assign = function ( $data, $separator ) {
			$parts = array();
			foreach ( $data as $key => $value ) { $parts[] = $key . '=' . ( $value === null ? 'NULL' : $this->pdo->quote( (string) $value ) ); }
			return implode( $separator, $parts );
		};
		return $this->query( "UPDATE $table SET " . $assign( $data, ',' ) . ' WHERE ' . $assign( $where, ' AND ' ) );
	}
	public function delete( $table, $where, $formats = null ) {
		$parts = array();
		foreach ( $where as $key => $value ) { $parts[] = $key . '=' . $this->pdo->quote( (string) $value ); }
		return $this->query( "DELETE FROM $table WHERE " . implode( ' AND ', $parts ) );
	}
	public function get_results( $sql, $type = null ) { $this->last_error = ''; return $this->pdo->query( str_replace( ' FOR UPDATE', '', $sql ) )->fetchAll( PDO::FETCH_ASSOC ); }
	public function get_row( $sql, $type = null ) { $rows = $this->get_results( $sql ); return $rows[0] ?? null; }
	public function get_var( $sql ) { $row = $this->get_row( $sql ); return $row ? reset( $row ) : null; }
	public function get_col( $sql, $col = 0 ) { return array_map( function ( $row ) use ( $col ) { return array_values( $row )[ $col ]; }, $this->get_results( $sql ) ); }
}
$wpdb = new TestDB();
require dirname( __DIR__ ) . '/includes/db/class-ums-db-base.php';
require dirname( __DIR__ ) . '/includes/class-ums-maternity.php';
require dirname( __DIR__ ) . '/includes/class-ums-employee-allowance-report.php';
require dirname( __DIR__ ) . '/includes/class-ums-issue-registration-import.php';
require dirname( __DIR__ ) . '/includes/db/class-ums-db-allocation-calculation.php';
class UMS_DB_User extends UMS_DB_Base {
	public static function table() { return 't_profiles'; }
	public static function get_by_employee_code( $code ) { return self::db()->get_row( self::db()->prepare( 'SELECT * FROM t_profiles WHERE employee_code=%s', $code ), ARRAY_A ); }
}
class UMS_DB_Organization {
	public static $employees = array();
	public static function get_by_wp_user_id( $id ) { foreach ( self::$employees as $code => $employee ) { if ( (int) substr( $code, 1 ) === (int) $id ) { return $employee; } } return null; }
	public static function get_by_employee_no( $code ) { return self::$employees[ $code ] ?? null; }
	public static function get_by_employee_nos( $codes ) { return array_intersect_key( self::$employees, array_flip( $codes ) ); }
	public static function get_for_allowance_export( $args = array() ) { return array_values( self::$employees ); }
	public static function table_exists() { return true; }
}
class UMS_DB_Inventory extends UMS_DB_Base {
	public static function table() { return 't_catalog'; }
	public static function resolve_factory_code_for_employee( $row ) { return $row['factory']; }
	public static function normalize_factory_code( $code ) { return $code; }
	public static function normalize_product_identity( $name ) { return strtolower( $name ); }
	public static function get_factory_options() { return array( 'HY' => 'HY', 'DA' => 'DA', 'VP' => 'VP' ); }
	public static function get_by_id( $id, $factory = 'HY' ) { return self::db()->get_row( self::db()->prepare( 'SELECT * FROM t_items WHERE item_id=%d AND factory_code=%s', $id, $factory ) ); }
	public static function get_by_id_for_update( $id, $factory ) { return self::get_by_id( $id, $factory ); }
	public static function update( $id, $data, $factory ) { return self::db()->update( 't_items', $data, array( 'item_id' => $id, 'factory_code' => $factory ) ); }
	public static function get_all() { return self::db()->get_results( "SELECT * FROM t_items WHERE factory_code='HY'" ); }
}
class UMS_DB_Inventory_Movement extends UMS_DB_Base {
	public static function table() { return 't_movements'; }
	public static function insert( $data ) {
		if ( ! UMS_Maternity::allow_movement( $data ) ) { return false; }
		return self::db()->insert( 't_movements', $data );
	}
}
require dirname( __DIR__ ) . '/includes/db/class-ums-db-request.php';
class UMS_DB_Product_Category { public static function table() { return 't_categories'; } }
class UMS_DB_Annual_Allowance {
	public static function get_active_for_report() { return array(); }
	public static function normalize_position_code( $v ) { return strtoupper( $v ); }
	public static function normalize_text( $v ) { return strtolower( $v ); }
}
class UMS_DB_Position { public static function get_active() { return array(); } }
class UMS_DB_Special_Work_Assignment { public static function get_active_map( $codes, $month ) { return array(); } }

$schema = array(
	'CREATE TABLE t_uniform_maternity_episodes (episode_id INTEGER PRIMARY KEY AUTOINCREMENT, employee_no TEXT, active_employee_no TEXT UNIQUE, full_name TEXT, factory_code TEXT, notified_on TEXT, returned_on TEXT, approved_items TEXT, approved_by INTEGER, received_on TEXT, blocked_year INTEGER, blocked_month INTEGER, created_at TEXT)',
	'CREATE TABLE t_uniform_maternity_events (event_id INTEGER PRIMARY KEY AUTOINCREMENT, episode_id INTEGER, action TEXT, actor_id INTEGER, payload TEXT, created_at TEXT)',
	'CREATE TABLE t_profiles (employee_code TEXT PRIMARY KEY, gender TEXT, is_maternity INTEGER, user_id INTEGER)',
	'CREATE TABLE t_items (item_id INTEGER, factory_code TEXT, item_type TEXT, item_variant TEXT, size TEXT, stock_qty INTEGER, base_price REAL, category_id INTEGER, PRIMARY KEY (item_id, factory_code))',
	"CREATE VIEW t_catalog AS SELECT * FROM t_items WHERE factory_code='HY'",
	'CREATE TABLE t_categories (category_id INTEGER PRIMARY KEY, parent_id INTEGER, category_name TEXT)',
	'CREATE TABLE t_uniform_requests (request_id INTEGER PRIMARY KEY AUTOINCREMENT, creator_id INTEGER, target_user_id INTEGER, reason_type INTEGER, payment_method INTEGER, current_status TEXT, maternity_episode_id INTEGER, created_at TEXT)',
	'CREATE TABLE t_uniform_approval_logs (log_id INTEGER PRIMARY KEY AUTOINCREMENT, request_id INTEGER, step_order INTEGER, approver_id INTEGER, action TEXT, comment TEXT)',
	'CREATE TABLE t_uniform_request_details (detail_id INTEGER PRIMARY KEY AUTOINCREMENT, request_id INTEGER, item_id INTEGER, quantity INTEGER, price_at_request REAL)',
	'CREATE TABLE t_movements (movement_id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, factory_code TEXT, movement_type TEXT, quantity INTEGER, before_qty INTEGER, after_qty INTEGER, unit_price REAL, total_price REAL, actor_user_id INTEGER, target_employee_no TEXT, note TEXT, request_id INTEGER, target_user_id INTEGER)',
	'CREATE TABLE t_users (ID INTEGER, user_login TEXT)',
	'CREATE TABLE t_usermeta (user_id INTEGER, meta_key TEXT, meta_value TEXT)',
);
foreach ( $schema as $sql ) { if ( $wpdb->query( $sql ) === false ) { throw new RuntimeException( $wpdb->last_error ); } }
foreach ( array( 'F000001' => 'HY', 'F000002' => 'DA', 'M000003' => 'VP' ) as $code => $factory ) {
	UMS_DB_Organization::$employees[ $code ] = array( 'employee_no' => $code, 'full_name' => $code, 'factory' => $factory, 'date_joined' => '2020-01-01', 'position' => 'WK', 'department' => 'GA' );
	$wpdb->insert( 't_profiles', array( 'employee_code' => $code, 'gender' => $code[0] === 'F' ? 'Nữ' : 'Nam', 'is_maternity' => 0, 'user_id' => (int) substr( $code, 1 ) ) );
}
$names = array( 'Váy bầu', 'Quần bầu', 'Áo bầu', 'Áo khoác bầu', 'Ao phong' );
foreach ( array( 'HY', 'DA' ) as $factory ) {
	foreach ( $names as $index => $name ) {
		$wpdb->insert( 't_items', array( 'item_id' => $index + 1, 'factory_code' => $factory, 'item_variant' => $name, 'size' => 'M', 'stock_qty' => 10, 'base_price' => 100000, 'category_id' => 1 ) );
	}
}
$count = 0;
function expect( $ok, $label ) { global $count; if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); } $count++; echo "PASS: $label\n"; }
function rejects( $callback, $label ) { try { $callback(); } catch ( Throwable $e ) { expect( true, $label ); return; } expect( false, $label ); }
function receive( $id, $date ) { UMS_Maternity::receive( $id, $date, 99, hash( 'sha256', (string) UMS_Maternity::get( $id )['approved_items'] ) ); }
$cases = array( '2026-02-01' => array( 2026, 4 ), '2026-05-01' => array( 2026, 4 ), '2026-05-02' => array( 2026, 9 ), '2026-06-01' => array( 2026, 9 ), '2026-08-30' => array( 2026, 9 ), '2026-08-31' => array( 2027, 4 ), '2026-12-31' => array( 2027, 4 ), '2028-02-29' => array( 2028, 4 ) );
foreach ( $cases as $date => $period ) { expect( UMS_Maternity::blocked_period( $date ) === $period, 'boundary ' . $date ); }
rejects( function () { UMS_Maternity::date( '2026-02-30' ); }, 'invalid calendar date' );
rejects( function () { UMS_Maternity::date( '0000-01-01' ); }, 'invalid year' );
expect( UMS_Maternity::limits() === array( 'dress' => 3, 'pants' => 3, 'shirt' => 3, 'jacket' => 1 ), '3/3/3/1 caps' );
foreach ( array( 'dress', 'pants', 'shirt', 'jacket', '' ) as $index => $group ) { expect( UMS_Maternity::product_group( array( 'item_variant' => $names[ $index ] ) ) === $group, 'classification ' . $index ); }
rejects( function () { UMS_Maternity::create( 'M000003', '2026-01-01', 99 ); }, 'reject male employee' );
$id = UMS_Maternity::create( 'F000001', '2026-02-01', 99 );
expect( UMS_Maternity::managed_flag( 'F000001' ) === 1, 'active flag' );
expect( ! UMS_Maternity::is_blocked( 'F000001', 2026, 4 ), 'notification does not lock period' );
rejects( function () { UMS_Maternity::create( 'F000001', '2026-03-01', 99 ); }, 'one active episode' );
rejects( function () use ( $id ) { receive( $id, '2026-03-01' ); }, 'receipt requires approval' );
$raw = array();
foreach ( UMS_Maternity::limits() as $group => $limit ) { $raw[ $group ] = array( 'item_id' => count( $raw ) + 1, 'quantity' => $limit ); }
$bad = $raw; $bad['dress']['quantity'] = 4;
rejects( function () use ( $id, $bad ) { UMS_Maternity::approve( $id, $bad, 99 ); }, 'cap exceeded' );
$bad = $raw; $bad['dress']['quantity'] = -1;
rejects( function () use ( $id, $bad ) { UMS_Maternity::approve( $id, $bad, 99 ); }, 'negative quantity rejected' );
$bad = $raw; $bad['dress']['item_id'] = 4;
rejects( function () use ( $id, $bad ) { UMS_Maternity::approve( $id, $bad, 99 ); }, 'wrong group rejected' );
UMS_Maternity::approve( $id, $raw, 99 );
expect( ! UMS_Maternity::is_blocked( 'F000001', 2026, 4 ), 'approval does not lock period' );
rejects( function () use ( $id ) { UMS_Maternity::receive( $id, '2026-05-01', 99, 'old-hash' ); }, 'stale approval rejected' );
$wpdb->fail_movement = true;
rejects( function () use ( $id ) { receive( $id, '2026-05-01' ); }, 'movement failure rolls back' );
$wpdb->fail_movement = false;
expect( (int) UMS_DB_Inventory::get_by_id( 1 )['stock_qty'] === 10 && ! UMS_Maternity::get( $id )['received_on'], 'stock and receipt unchanged on failure' );
UMS_DB_Inventory::update( 4, array( 'stock_qty' => 0 ), 'HY' );
rejects( function () use ( $id ) { receive( $id, '2026-05-01' ); }, 'insufficient last item rolls back all items' );
expect( (int) UMS_DB_Inventory::get_by_id( 1 )['stock_qty'] === 10 && (int) $wpdb->get_var( 'SELECT COUNT(*) FROM t_movements' ) === 0, 'no partial issue' );
UMS_DB_Inventory::update( 4, array( 'stock_qty' => 10 ), 'HY' );
receive( $id, '2026-05-01' );
expect( UMS_Maternity::is_blocked( 'f000001', 2026, 4 ), 'receipt locks inclusive T4' );
expect( ! UMS_Maternity::is_blocked( 'F000001', 2026, 9 ), 'only next periodic cycle blocked' );
expect( (int) UMS_DB_Inventory::get_by_id( 1 )['stock_qty'] === 7 && (int) UMS_DB_Inventory::get_by_id( 1, 'DA' )['stock_qty'] === 10, 'correct factory deducted' );
rejects( function () use ( $id ) { receive( $id, '2026-05-02' ); }, 'duplicate receipt rejected' );
rejects( function () use ( $id, $raw ) { UMS_Maternity::approve( $id, $raw, 99 ); }, 'exhausted allowance rejects further free approval' );
UMS_Maternity::finish( $id, '2026-05-02', 99 );
expect( UMS_Maternity::managed_flag( 'F000001', 1 ) === 0 && UMS_Maternity::is_blocked( 'F000001', 2026, 4 ), 'return clears flag but preserves block' );
rejects( function () { UMS_Maternity::create( 'F000001', '2026-05-01', 99 ); }, 'overlapping historical pregnancy rejected' );
$next = UMS_Maternity::create( 'F000001', '2027-01-01', 99 );
UMS_Maternity::approve( $next, array( 'shirt' => array( 'item_id' => 3, 'quantity' => 1 ) ), 99 );
receive( $next, '2027-08-31' );
expect( UMS_Maternity::is_blocked( 'F000001', 2028, 4 ), 'new pregnancy gets one new issue and next-year lock' );
expect( ! UMS_Maternity::is_blocked( 'F000002', 2026, 4 ), 'other employee unaffected' );
$details = array( array( 'item_id' => 3, 'quantity' => 2, 'price_at_request' => 200000 ) );
expect( UMS_Maternity::validate_paid_request( $details, 3, 1 ) === '', 'salary purchase full price' );
expect( UMS_Maternity::validate_paid_request( $details, 3, 2 ) === '', 'direct purchase full price' );
expect( UMS_Maternity::validate_paid_request( $details, 3, 3 ) !== '', 'maternity cannot be advance' );
expect( UMS_Maternity::validate_paid_request( $details, 2, 0 ) !== '', 'paid-only validator rejects free reason' );
$details[0]['price_at_request'] = 100000;
expect( UMS_Maternity::validate_paid_request( $details, 3, 1 ) !== '', 'discounted maternity purchase rejected' );
expect( UMS_Maternity::validate_paid_request( array( array( 'item_id' => 5, 'quantity' => 1, 'price_at_request' => 100000 ) ), 1, 0 ) === '', 'normal advances unaffected' );
expect( ! UMS_Maternity::allow_movement( array( 'item_id' => 1, 'target_employee_no' => 'F000001', 'movement_type' => 'out' ) ), 'manual free issue bypass blocked' );
$allocations = UMS_Employee_Allowance_Report::build_employee_allocations( array( 'F000001', 'F000002' ), 2026, 4 );
expect( ! empty( $allocations['F000001']['maternity_blocked'] ) && $allocations['F000001']['allocations'] === array(), 'allocation engine blocks all items' );
expect( empty( $allocations['F000002']['maternity_blocked'] ), 'allocation engine keeps other employee' );
$headers = array( 'C' => 'Ma nhan vien', 'G' => 'Mu', 'H' => 'Giay', 'K' => 'Quan', 'N' => 'Ao' );
$registration = array( 'C' => 'F000001', 'G' => '1', 'H' => '1', 'I' => '38', 'K' => '2', 'L' => 'M', 'N' => '2', 'O' => 'M' );
$preview = UMS_Allocation_Calculation::analyze_sheet_rows( $headers, array( $registration ), array(), 2026, 4, 'HY' );
expect( $preview['errors'] === array() && $preview['total_quantity'] === 0 && $preview['details'] === array(), 'Google Sheet preview excludes every blocked product' );
expect( strpos( implode( ' ', $preview['warnings'] ), 'F000001' ) !== false && count( $preview['source_preview']['rows'] ) === 1, 'preview preserves source row and explains maternity block' );
$report = UMS_Employee_Allowance_Report::build( array( 'report_year' => 2026, 'report_month' => 4, 'report_quantity_mode' => 'quota' ) );
expect( strpos( implode( ' ', $report['warnings'] ), 'F000001' ) !== false, 'quota export includes maternity warning' );
$stale = UMS_DB_Allocation_Calculation::save_snapshot( array( 'year' => 2026, 'month' => 4, 'details' => array( array( 'employee_no' => 'F000001', 'quantity' => 1 ) ) ), 99 );
expect( is_wp_error( $stale ) && $stale->code === 'maternity_preview_stale', 'stale preview cannot finalize' );
$partial = UMS_Maternity::create( 'F000002', '2026-02-01', 99 );
$one_dress = array( 'dress' => array( 'item_id' => 1, 'quantity' => 1 ) );
UMS_Maternity::approve( $partial, $one_dress, 99 );
$first_approval = UMS_Maternity::get( $partial )['approved_items'];
receive( $partial, '2026-05-01' );
$balance = UMS_Maternity::balance( UMS_Maternity::get( $partial ) );
expect( $balance['issued']['dress'] === 1 && $balance['remaining']['dress'] === 2, 'one dress leaves two free dresses' );
expect( $balance['remaining']['pants'] === 3 && $balance['remaining']['shirt'] === 3 && $balance['remaining']['jacket'] === 1, 'independent group balances' );
expect( UMS_Maternity::pending_items( UMS_Maternity::get( $partial ) ) === array(), 'receipt consumes approval' );
rejects( function () use ( $partial, $first_approval ) { UMS_Maternity::receive( $partial, '2026-05-02', 99, hash( 'sha256', $first_approval ) ); }, 'old receipt cannot be replayed' );
// Compatibility: the previous implementation left these fields after receipt.
$wpdb->update( UMS_Maternity::table(), array( 'approved_items' => $first_approval, 'approved_by' => 99 ), array( 'episode_id' => $partial ) );
expect( UMS_Maternity::pending_items( UMS_Maternity::get( $partial ) ) === array(), 'legacy consumed approval not pending' );
UMS_Maternity::approve( $partial, $one_dress, 99 );
expect( UMS_Maternity::get( $partial )['approved_items'] !== $first_approval, 'identical quantities get a new approval token' );
rejects( function () use ( $partial, $first_approval ) { UMS_Maternity::receive( $partial, '2026-06-01', 99, hash( 'sha256', $first_approval ) ); }, 'old hash cannot consume a new identical approval' );
rejects( function () use ( $partial ) { receive( $partial, '2026-04-30' ); }, 'supplement cannot predate first receipt' );
receive( $partial, '2026-06-01' );
$balance = UMS_Maternity::balance( UMS_Maternity::get( $partial ) );
expect( $balance['issued']['dress'] === 2 && $balance['remaining']['dress'] === 1 && $balance['receipt_count'] === 2, 'second free issue within cap succeeds' );
expect( UMS_Maternity::get( $partial )['received_on'] === '2026-05-01' && UMS_Maternity::is_blocked( 'F000002', 2026, 4 ) && ! UMS_Maternity::is_blocked( 'F000002', 2026, 9 ), 'supplement preserves first date and does not block next cycle' );
rejects( function () use ( $partial ) { UMS_Maternity::approve( $partial, array( 'dress' => array( 'item_id' => 1, 'quantity' => 2 ) ), 99 ); }, 'cumulative cap enforced on approval' );
$last = array( 'dress' => array( 'item_id' => 1, 'quantity' => 1 ), 'pants' => array( 'item_id' => 2, 'quantity' => 1 ) );
UMS_Maternity::approve( $partial, $last, 99 );
UMS_DB_Inventory::update( 2, array( 'stock_qty' => 0 ), 'DA' );
rejects( function () use ( $partial ) { receive( $partial, '2026-07-01' ); }, 'supplement rollback on stock shortage' );
expect( UMS_Maternity::balance( UMS_Maternity::get( $partial ) )['issued']['dress'] === 2 && (int) UMS_DB_Inventory::get_by_id( 1, 'DA' )['stock_qty'] === 8 && count( UMS_Maternity::pending_items( UMS_Maternity::get( $partial ) ) ) === 2, 'failed supplement preserves balance stock and pending approval' );
UMS_DB_Inventory::update( 2, array( 'stock_qty' => 10 ), 'DA' );
receive( $partial, '2026-07-01' );
expect( UMS_Maternity::balance( UMS_Maternity::get( $partial ) )['remaining']['dress'] === 0, 'third dress exhausts only dress allowance' );
rejects( function () use ( $partial, $one_dress ) { UMS_Maternity::approve( $partial, $one_dress, 99 ); }, 'fourth dress cannot be free' );
UMS_Maternity::approve( $partial, array( 'pants' => array( 'item_id' => 2, 'quantity' => 2 ) ), 99 );
receive( $partial, '2026-09-01' );
expect( UMS_Maternity::balance( UMS_Maternity::get( $partial ) )['remaining']['pants'] === 0 && ! UMS_Maternity::is_blocked( 'F000002', 2027, 4 ), 'pants top-up reaches cap without extra year lock' );
rejects( function () use ( $partial ) { UMS_Maternity::finish( $partial, '2026-08-01', 99 ); }, 'return cannot predate latest supplement' );
UMS_Maternity::finish( $partial, '2026-09-02', 99 );
rejects( function () use ( $partial ) { UMS_Maternity::approve( $partial, array( 'shirt' => array( 'item_id' => 3, 'quantity' => 1 ) ), 99 ); }, 'closed episode cannot receive more even with unused allowance' );
// Exercise the real final-approval transaction, with isolated inventory/org adapters.
foreach ( array( 'F000004' => 'DA', 'F000005' => 'HY', 'F000006' => 'HY', 'F000007' => 'HY' ) as $code => $factory ) {
	UMS_DB_Organization::$employees[ $code ] = array( 'employee_no' => $code, 'full_name' => $code, 'factory' => $factory, 'date_joined' => '2020-01-01', 'position' => 'WK', 'department' => 'GA' );
	$wpdb->insert( 't_profiles', array( 'employee_code' => $code, 'gender' => 'Nữ', 'is_maternity' => 0, 'user_id' => (int) substr( $code, 1 ) ) );
}
$wpdb->query( 'UPDATE t_items SET stock_qty=100' );
$GLOBALS['test_clock'] = '2026-08-29 12:00:00';
function request_lines( $items ) {
	$result = array();
	foreach ( $items as $id => $quantity ) { $result[] = array( 'item_id' => $id, 'quantity' => $quantity, 'price_at_request' => 100000 * $quantity ); }
	return $result;
}
function portal_request( $user, $items, $reason = 1, $payment = 0, $episode = 0, $status = 'pending_step_5' ) {
	global $wpdb;
	$wpdb->insert( UMS_DB_Request::table(), array( 'target_user_id' => $user, 'reason_type' => $reason, 'payment_method' => $payment, 'maternity_episode_id' => $episode, 'current_status' => $status, 'created_at' => current_time( 'mysql' ) ) );
	$id = $wpdb->insert_id;
	foreach ( request_lines( $items ) as $line ) { $wpdb->insert( UMS_DB_Request::detail_table(), array_merge( array( 'request_id' => $id ), $line ) ); }
	return $id;
}
function request_out_count( $id ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM t_movements WHERE request_id=%d AND movement_type='out'", $id ) );
}
$context = UMS_Maternity::validate_request( 'F000004', request_lines( array( 1 => 1 ) ), 1, 0 );
expect( $context['episode_id'] === 0 && UMS_Maternity::managed_flag( 'F000004' ) === 0, 'submission detects maternity without opening episode' );
expect( (int) UMS_DB_Inventory::get_by_id( 1, 'DA' )['stock_qty'] === 100 && ! UMS_Maternity::is_blocked( 'F000004', 2026, 9 ), 'submission does not issue stock or block cycle' );
expect( UMS_Maternity::validate_request( 'F000004', request_lines( array( 5 => 1 ) ), 1, 0 ) === null, 'normal request bypasses maternity detection' );
rejects( function () { UMS_Maternity::validate_request( 'M000003', request_lines( array( 1 => 1 ) ), 1, 0 ); }, 'male recipient cannot open maternity request' );
rejects( function () { UMS_Maternity::validate_request( 'F000004', request_lines( array( 1 => 1 ) ), 3, 3 ); }, 'maternity advance rejected at submission' );
$request = portal_request( 4, array( 1 => 1 ) );
expect( UMS_Maternity::managed_flag( 'F000004' ) === 0, 'pending request does not set maternity flag' );
$GLOBALS['test_clock'] = '2026-08-30 12:00:00';
expect( UMS_DB_Request::complete_approved_request( $request, 99 ), 'final approval automatically registers and fulfils maternity request' );
$auto = (int) UMS_DB_Request::get_by_id( $request )['maternity_episode_id'];
expect( $auto > 0 && UMS_Maternity::managed_flag( 'F000004' ) === 1 && (int) UMS_DB_User::get_by_employee_code( 'F000004' )['is_maternity'] === 1 && $GLOBALS['test_meta'][4]['ums_is_maternity'] === 1, 'episode and profile/usermeta flag linked after commit' );
expect( UMS_Maternity::get( $auto )['received_on'] === '2026-08-30' && UMS_Maternity::is_blocked( 'F000004', 2026, 9 ), 'final approval date determines first-cycle lock' );
expect( request_out_count( $request ) === 1 && (int) UMS_DB_Inventory::get_by_id( 1, 'DA' )['stock_qty'] === 99 && (int) UMS_DB_Inventory::get_by_id( 1, 'HY' )['stock_qty'] === 100, 'automatic maternity issue uses recipient factory only' );
expect( UMS_DB_Request::complete_approved_request( $request, 99 ) && request_out_count( $request ) === 1 && UMS_Maternity::balance( UMS_Maternity::get( $auto ) )['issued']['dress'] === 1, 'repeat final approval cannot issue twice' );
expect( UMS_Maternity::validate_request( 'F000004', request_lines( array( 1 => 2 ) ), 2, 0 )['episode_id'] === $auto, 'supplement reuses existing episode' );
$supplement = portal_request( 4, array( 1 => 1, 5 => 2 ), 2, 0, $auto );
$GLOBALS['test_clock'] = '2026-09-01 12:00:00';
expect( UMS_DB_Request::complete_approved_request( $supplement, 99 ) && request_out_count( $supplement ) === 2, 'mixed maternity and regular request issues both exactly once' );
expect( UMS_Maternity::balance( UMS_Maternity::get( $auto ) )['remaining']['dress'] === 1 && ! UMS_Maternity::is_blocked( 'F000004', 2027, 4 ), 'supplement deducts cumulative balance without a second lock' );
$first_pending = portal_request( 4, array( 1 => 1 ), 1, 0, $auto );
$second_pending = portal_request( 4, array( 1 => 1 ), 1, 0, $auto );
expect( UMS_DB_Request::complete_approved_request( $first_pending, 99 ), 'first pending request consumes final free dress' );
expect( ! UMS_DB_Request::complete_approved_request( $second_pending, 99 ) && request_out_count( $second_pending ) === 0 && UMS_DB_Request::get_by_id( $second_pending )['current_status'] === 'pending_step_5', 'second pending request rechecks quota and stays pending' );
expect( UMS_DB_Request::get_completion_error() !== '', 'quota failure provides an actionable approval message' );
expect( UMS_Maternity::balance( UMS_Maternity::get( $auto ) )['issued']['dress'] === 3, 'competing pending requests cannot exceed free cap on final approval' );
$paid = portal_request( 4, array( 1 => 4 ), 3, 1, $auto );
expect( UMS_DB_Request::complete_approved_request( $paid, 99 ) && request_out_count( $paid ) === 1, 'salary purchase works beyond free cap' );
expect( UMS_Maternity::balance( UMS_Maternity::get( $auto ) )['issued']['dress'] === 3 && ! UMS_Maternity::is_blocked( 'F000004', 2027, 4 ), 'paid purchase leaves free balance and lock unchanged' );
$invalid_advance = portal_request( 4, array( 2 => 1 ), 3, 3, $auto );
expect( ! UMS_DB_Request::complete_approved_request( $invalid_advance, 99 ) && request_out_count( $invalid_advance ) === 0, 'final approval rejects old or tampered maternity advance' );

// Purchases also identify maternity, without consuming the free entitlement.
$new_purchase = portal_request( 5, array( 1 => 4 ), 3, 2 );
expect( UMS_Maternity::validate_request( 'F000005', request_lines( array( 1 => 4 ) ), 3, 2 )['episode_id'] === 0, 'new paid request is not capped at free limit' );
expect( UMS_DB_Request::complete_approved_request( $new_purchase, 99 ), 'direct purchase can automatically register pregnancy' );
$purchase_episode = (int) UMS_DB_Request::get_by_id( $new_purchase )['maternity_episode_id'];
expect( UMS_Maternity::managed_flag( 'F000005' ) === 1 && ! UMS_Maternity::get( $purchase_episode )['received_on'] && UMS_Maternity::balance( UMS_Maternity::get( $purchase_episode ) )['remaining'] === UMS_Maternity::limits(), 'paid-only episode has flag but full free balance and no free receipt' );
expect( ! UMS_Maternity::is_blocked( 'F000005', 2026, 9 ) && ! UMS_Maternity::is_blocked( 'F000005', 2027, 4 ), 'paid-only episode never locks periodic issue' );
$discount = portal_request( 5, array( 2 => 1 ), 3, 1, $purchase_episode );
$wpdb->update( UMS_DB_Request::detail_table(), array( 'price_at_request' => 50000 ), array( 'request_id' => $discount ) );
expect( ! UMS_DB_Request::complete_approved_request( $discount, 99 ) && request_out_count( $discount ) === 0, 'paid final approval rejects discounted price' );

// Rollback must include the auto-created episode, flags, history and all stock.
$failed = portal_request( 6, array( 1 => 1, 5 => 1 ) );
$stock_before = UMS_DB_Inventory::get_by_id( 1 )['stock_qty'];
UMS_DB_Inventory::update( 5, array( 'stock_qty' => 0 ), 'HY' );
expect( ! UMS_DB_Request::complete_approved_request( $failed, 99 ), 'ordinary stock failure rolls back mixed maternity issue' );
expect( UMS_Maternity::managed_flag( 'F000006' ) === 0 && (int) UMS_DB_User::get_by_employee_code( 'F000006' )['is_maternity'] === 0 && empty( $GLOBALS['test_meta'][6] ), 'rollback leaves profile and usermeta unchanged' );
expect( request_out_count( $failed ) === 0 && UMS_DB_Inventory::get_by_id( 1 )['stock_qty'] === $stock_before && ! UMS_DB_Request::get_by_id( $failed )['maternity_episode_id'], 'rollback leaves no stock movement or episode binding' );
UMS_DB_Inventory::update( 5, array( 'stock_qty' => 100 ), 'HY' );
$wpdb->fail_movement = true;
expect( ! UMS_DB_Request::complete_approved_request( $failed, 99 ) && UMS_Maternity::managed_flag( 'F000006' ) === 0, 'maternity movement failure rolls back auto-registration' );
$wpdb->fail_movement = false;
expect( UMS_DB_Request::complete_approved_request( $failed, 99 ), 'request can retry successfully after stock restored' );
$rejected = portal_request( 7, array( 1 => 1 ), 1, 0, 0, 'rejected' );
expect( ! UMS_DB_Request::complete_approved_request( $rejected, 99 ) && UMS_Maternity::managed_flag( 'F000007' ) === 0, 'rejected request cannot register pregnancy' );
$paid_failure = portal_request( 7, array( 4 => 1 ), 3, 2 );
UMS_DB_Inventory::update( 4, array( 'stock_qty' => 0 ), 'HY' );
expect( ! UMS_DB_Request::complete_approved_request( $paid_failure, 99 ) && UMS_Maternity::managed_flag( 'F000007' ) === 0 && request_out_count( $paid_failure ) === 0, 'paid stock failure rolls back pregnancy and purchase audit' );
UMS_DB_Inventory::update( 4, array( 'stock_qty' => 100 ), 'HY' );

// HCNS approvals reserve quantities independently of requests.
UMS_Maternity::approve( $auto, array( 'pants' => array( 'item_id' => 2, 'quantity' => 2 ) ), 99 );
$reserved = portal_request( 4, array( 2 => 2 ), 1, 0, $auto );
expect( ! UMS_DB_Request::complete_approved_request( $reserved, 99 ), 'request cannot consume reserved HCNS quota' );
$unreserved = portal_request( 4, array( 2 => 1 ), 1, 0, $auto );
expect( UMS_DB_Request::complete_approved_request( $unreserved, 99 ), 'request can consume unreserved remainder' );
expect( (int) UMS_Maternity::pending_items( UMS_Maternity::get( $auto ) )[0]['quantity'] === 2, 'portal receipt preserves pending HCNS approval' );
receive( $auto, '2026-09-01' );
expect( UMS_Maternity::balance( UMS_Maternity::get( $auto ) )['remaining']['pants'] === 0 && ! UMS_Maternity::pending_items( UMS_Maternity::get( $auto ) ), 'HCNS can receive reserved remainder after portal receipt' );

// Old pending requests cannot silently reopen a closed pregnancy.
$old_bound = portal_request( 4, array( 3 => 1 ), 1, 0, $auto );
$old_unbound = portal_request( 4, array( 3 => 1 ) );
UMS_Maternity::finish( $auto, '2026-09-01', 99 );
expect( ! UMS_DB_Request::complete_approved_request( $old_bound, 99 ) && ! UMS_DB_Request::complete_approved_request( $old_unbound, 99 ) && UMS_Maternity::managed_flag( 'F000004' ) === 0, 'closed pregnancy rejects both linked and older unlinked requests' );
$GLOBALS['test_clock'] = '2027-02-01 12:00:00';
$fresh = portal_request( 4, array( 3 => 1 ) );
expect( UMS_DB_Request::complete_approved_request( $fresh, 99 ) && (int) UMS_DB_Request::get_by_id( $fresh )['maternity_episode_id'] !== $auto, 'later pregnancy starts a new episode from new request' );
expect( ! UMS_DB_Request::complete_approved_request( $old_bound, 99 ) && request_out_count( $old_bound ) === 0, 'old request cannot migrate into new pregnancy' );
expect( UMS_Maternity::is_blocked( 'F000004', 2027, 4 ) && UMS_Maternity::is_blocked( 'F000004', 2026, 9 ), 'new pregnancy lock does not delete historic lock' );

$save_data = array( 'creator_id' => 99, 'target_user_id' => 7, 'reason_type' => 1, 'payment_method' => 0, 'current_status' => 'pending_step_1', 'maternity_episode_id' => 0, 'created_at' => current_time( 'mysql' ) );
$saved = UMS_DB_Request::insert_with_details( $save_data, request_lines( array( 3 => 1 ) ) );
expect( $saved > 0 && request_out_count( $saved ) === 0 && UMS_Maternity::managed_flag( 'F000007' ) === 0, 'real request save records request_out only without maternity mutation' );
expect( UMS_DB_Request::update_with_details( $saved, $save_data, request_lines( array( 3 => 2 ) ) ) && UMS_Maternity::managed_flag( 'F000007' ) === 0, 'editing pending request does not register pregnancy' );
UMS_DB_Request::update_status( $saved, 'pending_step_2' );
expect( UMS_Maternity::managed_flag( 'F000007' ) === 0 && request_out_count( $saved ) === 0, 'intermediate approval leaves maternity and inventory untouched' );
expect( UMS_DB_Request::complete_approved_request( $saved, 99 ) && UMS_Maternity::managed_flag( 'F000007' ) === 1 && UMS_Maternity::balance( UMS_Maternity::get( UMS_DB_Request::get_by_id( $saved )['maternity_episode_id'] ) )['issued']['shirt'] === 2, 'saved and edited request completes with current details' );

// A paid-only episode can later use free allowance, including after a transfer.
UMS_DB_Organization::$employees['F000005']['factory'] = 'DA';
$free_after_paid = portal_request( 5, array( 3 => 1 ), 2, 0, $purchase_episode );
$hy_before = UMS_DB_Inventory::get_by_id( 3, 'HY' )['stock_qty'];
$da_before = (int) UMS_DB_Inventory::get_by_id( 3, 'DA' )['stock_qty'];
expect( UMS_DB_Request::complete_approved_request( $free_after_paid, 99 ) && (int) UMS_DB_Request::get_by_id( $free_after_paid )['maternity_episode_id'] === $purchase_episode, 'first free request reuses paid-only pregnancy' );
expect( UMS_DB_Inventory::get_by_id( 3, 'HY' )['stock_qty'] === $hy_before && (int) UMS_DB_Inventory::get_by_id( 3, 'DA' )['stock_qty'] === $da_before - 1, 'transfer uses current recipient factory without resetting pregnancy quota' );
expect( UMS_Maternity::is_blocked( 'F000005', 2027, 4 ) && UMS_Maternity::get( $purchase_episode )['received_on'] === '2027-02-01', 'paid-only episode locks cycle only at first free receipt' );
$GLOBALS['test_clock'] = '2027-03-01 12:00:00';
$last_purchase = portal_request( 5, array( 2 => 1 ), 3, 2, $purchase_episode );
expect( UMS_DB_Request::complete_approved_request( $last_purchase, 99 ) && UMS_Maternity::balance( UMS_Maternity::get( $purchase_episode ) )['receipt_count'] === 1, 'later paid receipt does not increase free receipt count' );
rejects( function () use ( $purchase_episode ) { UMS_Maternity::finish( $purchase_episode, '2027-02-28', 99 ); }, 'return cannot be backdated before latest approved purchase' );

echo "OK: $count checks; no production database accessed.\n";
