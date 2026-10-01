<?php
/** Run: php tools/test-maternity.php. Uses only an isolated in-memory SQLite DB. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
define( 'ARRAY_A', 'ARRAY_A' );
define( 'HOUR_IN_SECONDS', 3600 );
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ) ); }
function sanitize_file_name( $v ) { return $v; }
function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
function current_time( $format ) { return date( $format === 'mysql' ? 'Y-m-d H:i:s' : $format, strtotime( '2027-12-31 12:00:00' ) ); }
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
	public static function get_by_employee_no( $code ) { return self::$employees[ $code ] ?? null; }
	public static function get_by_employee_nos( $codes ) { return array_intersect_key( self::$employees, array_flip( $codes ) ); }
	public static function get_for_allowance_export( $args = array() ) { return array_values( self::$employees ); }
	public static function table_exists() { return true; }
}
class UMS_DB_Inventory extends UMS_DB_Base {
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
	public static function insert( $data ) {
		if ( ! UMS_Maternity::allow_movement( $data ) ) { return false; }
		return self::db()->insert( 't_movements', $data );
	}
}
class UMS_DB_Request {
	public static function get_by_id( $id ) { return $id === 1 ? array( 'reason_type' => 3, 'payment_method' => 1 ) : null; }
}
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
	'CREATE TABLE t_items (item_id INTEGER, factory_code TEXT, item_variant TEXT, size TEXT, stock_qty INTEGER, base_price REAL, category_id INTEGER, PRIMARY KEY (item_id, factory_code))',
	'CREATE TABLE t_movements (movement_id INTEGER PRIMARY KEY AUTOINCREMENT, item_id INTEGER, factory_code TEXT, movement_type TEXT, quantity INTEGER, before_qty INTEGER, after_qty INTEGER, unit_price REAL, total_price REAL, actor_user_id INTEGER, target_employee_no TEXT, note TEXT)',
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
expect( UMS_Maternity::validate_paid_request( $details, 2, 0 ) !== '', 'free portal bypass rejected' );
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
echo "OK: $count checks; no production database accessed.\n";
