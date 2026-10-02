<?php
/** CLI-only isolated WordPress hook/REST harness. No network or production DB. */
if ( PHP_SAPI !== 'cli' ) { http_response_code( 404 ); exit; }
error_reporting( E_ALL );
set_error_handler( function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$root = dirname( __DIR__ );
$mode = $argv[1] ?? 'compatible';
$with_core = in_array( '--core', $argv, true );
define( 'ABSPATH', $root . '/' );
define( 'UMS_PLUGIN_DIR', $root . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'ARRAY_A', 'ARRAY_A' );
if ( $mode === 'legacy' ) { class UMS_Organization_Sync {} }
$hooks = $routes = $transients = $scripts = $styles = $localized = $menus = array();
$options = array( 'ums_sheet_sync_token' => str_repeat( 'test-secret-', 4 ), 'ums_auto_sync_bridge_token' => str_repeat( 'test-bridge-', 4 ), 'ums_sheet_sync_apps_script_url' => 'https://script.google.com/macros/s/org-test/exec', 'ums_allocation_sheet_apps_script_url' => 'https://script.google.com/macros/s/allocation-test/exec' );
$allowed = true;
$count = 0;
function expect( $ok, $label ) { global $count; if ( ! $ok ) { throw new RuntimeException( 'FAIL: ' . $label ); } $count++; echo "PASS: $label\n"; }
function add_action( $name, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][$name][$priority][] = array( $callback, $args ); }
function add_filter( $name, $callback, $priority = 10, $args = 1 ) { add_action( $name, $callback, $priority, $args ); }
function has_action( $name ) { return ! empty( $GLOBALS['hooks'][$name] ); }
function do_action( $name, ...$args ) { $groups = $GLOBALS['hooks'][$name] ?? array(); ksort( $groups ); foreach ( $groups as $callbacks ) { foreach ( $callbacks as $cb ) { call_user_func_array( $cb[0], array_slice( $args, 0, $cb[1] ) ); } } }
function apply_filters( $name, $value ) { $groups = $GLOBALS['hooks'][$name] ?? array(); ksort( $groups ); foreach ( $groups as $callbacks ) { foreach ( $callbacks as $cb ) { $value = call_user_func( $cb[0], $value ); } } return $value; }
function plugin_dir_path( $file ) { return dirname( $file ) . '/'; }
function plugin_dir_url( $file ) { return 'https://ums.test/wp-content/plugins/ums-google-sheets-connector/'; }
function register_deactivation_hook( $file, $cb ) { $GLOBALS['deactivate'] = $cb; }
function is_admin() { return true; }
function current_user_can( $cap ) { return $GLOBALS['allowed']; }
function get_option( $key, $fallback = false ) { return $GLOBALS['options'][$key] ?? $fallback; }
function update_option( $key, $value, $autoload = null ) { $GLOBALS['options'][$key] = $value; return true; }
function get_transient( $key ) { return $GLOBALS['transients'][$key] ?? false; }
function set_transient( $key, $value, $ttl ) { $GLOBALS['transients'][$key] = $value; return true; }
function delete_transient( $key ) { unset( $GLOBALS['transients'][$key] ); return true; }
function wp_generate_password( $length, $special = true, $extra = false ) { return str_repeat( 'x', $length ); }
function wp_next_scheduled( $hook ) { return false; }
function wp_clear_scheduled_hook( $hook ) { $GLOBALS['cleared'][] = $hook; }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $v ) ); }
function wp_unslash( $v ) { return $v; }
function absint( $v ) { return abs( (int) $v ); }
function esc_url_raw( $v ) { return trim( (string) $v ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $v ) { return esc_html( $v ); }
function esc_url( $v ) { return esc_attr( $v ); }
function esc_html__( $v, $domain = '' ) { return esc_html( $v ); }
function current_time( $format ) { return date( $format, strtotime( '2026-10-02 10:00:00' ) ); }
function number_format_i18n( $v ) { return number_format( $v ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function admin_url( $v ) { return 'https://ums.test/wp-admin/' . $v; }
function home_url( $v ) { return 'https://ums.test' . $v; }
function rest_url( $v ) { return 'https://ums.test/wp-json/' . $v; }
function add_query_arg( $args, $url ) { return $url . '?' . http_build_query( $args ); }
function selected( $a, $b ) { if ( (string) $a === (string) $b ) { echo ' selected'; } }
function disabled( $v ) { if ( $v ) { echo ' disabled'; } }
function wp_nonce_field( $v ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function submit_button( $v ) { echo '<button>' . esc_html( $v ) . '</button>'; }
function wp_create_nonce( $v ) { return 'nonce-' . $v; }
function check_ajax_referer( $v, $field ) { if ( ( $_POST[$field] ?? '' ) !== wp_create_nonce( $v ) ) { throw new RuntimeException( 'Invalid nonce' ); } }
function wp_enqueue_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['scripts'][$handle] = compact( 'url', 'deps' ); }
function wp_enqueue_style( $handle, $url, $deps, $version ) { $GLOBALS['styles'][$handle] = compact( 'url', 'deps' ); }
function wp_localize_script( $handle, $object, $data ) { $GLOBALS['localized'][$object] = $data; }
function add_menu_page( ...$args ) { $GLOBALS['menus'][$args[3]] = $args; }
function add_submenu_page( ...$args ) { $GLOBALS['menus'][$args[4]] = $args; }
function register_rest_route( $namespace, $route, $definition ) { $key = $namespace . $route; if ( isset( $GLOBALS['routes'][$key] ) ) { throw new RuntimeException( 'Duplicate route: ' . $key ); } $GLOBALS['routes'][$key] = $definition; }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error { public $code; public $message; public function __construct( $code, $message, $data = array() ) { $this->code = $code; $this->message = $message; } public function get_error_message() { return $this->message; } }
class WP_REST_Server { const CREATABLE = 'POST'; }
class WP_REST_Request { public $headers = array(); public $payload = array(); public function get_header( $key ) { return $this->headers[$key] ?? ''; } public function get_json_params() { return $this->payload; } }
class WP_REST_Response { public $data; public $status; public function __construct( $data, $status ) { $this->data = $data; $this->status = $status; } }
class UMS_DB_Organization { public static function table_exists() { return true; } public static function get_count() { return 0; } public static function get_last_synced_at() { return null; } public static function get_distinct_values( $v ) { return array(); } }
class UMS_DB_User {}
class UMS_Employee_Exit_Manager {}
class UMS_DB_Inventory { public static function get_factory_options() { return array( 'HY' => 'Hung Yen', 'DA' => 'Dong Anh', 'VP' => 'Vinh Phuc' ); } public static function normalize_factory_code( $v ) { return $v ?: 'HY'; } }
class UMS_DB_Allocation_Calculation { public static function supports_factory_sources() { return true; } }
class UMS_Allocation_Calculation {
    const PREVIEW_TTL = 1800;
    public static $calls = array();
    public static function analyze_sheet_rows( $headers, $rows, $source, $year, $month, $factory ) { self::$calls[] = compact( 'headers', 'rows', 'source', 'year', 'month', 'factory' ); return array( 'errors' => array(), 'warnings' => array(), 'rows' => $rows ); }
    public static function store_preview( $preview ) { set_transient( 'test-preview', $preview, self::PREVIEW_TTL ); return 'preview-test'; }
}
require $root . '/admin/class-ums-admin.php';
if ( $with_core ) { UMS_Admin::init(); }
if ( $mode !== 'off' ) { require $root . '/packages/ums-google-sheets-connector/ums-google-sheets-connector.php'; }
// Register UMS readiness after loading the connector, to exercise reverse plugin order.
if ( ! in_array( $mode, array( 'missing', 'off' ), true ) ) {
    add_action( 'plugins_loaded', function () use ( $mode ) { define( 'UMS_SHEETS_API_VERSION', $mode === 'incompatible' ? '2.0.0' : '1.0.0' ); }, 10 );
}
do_action( 'plugins_loaded' );
do_action( 'init' );
do_action( 'rest_api_init' );

if ( $mode !== 'compatible' ) {
    expect( count( $routes ) === 0, "$mode: no sync receivers registered" );
    expect( ! has_action( 'ums_render_allocation_sheet_controls' ), "$mode: no connector controls registered" );
    expect( ! has_action( 'admin_post_ums_save_sheet_sync_settings' ), "$mode: no settings handler registered" );
    if ( $mode !== 'off' ) { ob_start(); do_action( 'admin_notices' ); $notice = ob_get_clean(); expect( strpos( $notice, 'Connector' ) !== false, "$mode: dependency notice without fatal error" ); }
    if ( $with_core ) {
        ob_start(); UMS_Admin::render_allocation_calculation_page(); $html = ob_get_clean();
        expect( strpos( $html, 'ums_preview_allocation_calculation' ) !== false && strpos( $html, 'name="factory_code"' ) !== false, "$mode: Excel import and factory selector remain usable" );
        ob_start(); UMS_Admin::render_organization_page(); $html = ob_get_clean();
        expect( strpos( $html, 'ums-jqx-remote-grid' ) !== false, "$mode: local organization grid remains available" );
    }
    echo "OK: $count checks ($mode); no production services accessed.\n"; exit;
}

expect( array_keys( $routes ) === array( 'ums/v1/sync-users', 'ums/v1/sync-organization', 'ums/v1/sync-allocation-registration' ), 'all three REST paths preserved' );
UMS_Sheets_Connector::boot();
expect( count( $hooks['rest_api_init'][10] ) === 3, 'bootstrap idempotent, receivers registered once' );
foreach ( $routes as $route => $definition ) { expect( is_callable( $definition['callback'] ) && is_callable( $definition['permission_callback'] ), 'callable receiver and authorization: ' . $route ); }
expect( get_option( 'ums_sheet_sync_token' ) === str_repeat( 'test-secret-', 4 ) && get_option( 'ums_auto_sync_bridge_token' ) === str_repeat( 'test-bridge-', 4 ), 'existing tokens preserved' );
$request = new WP_REST_Request();
expect( is_wp_error( UMS_Sheet_User_Sync::authorize_request( $request ) ), 'missing token rejected' );
$request->headers['x-sync-token'] = 'invalid';
expect( is_wp_error( UMS_Sheet_User_Sync::authorize_request( $request ) ), 'wrong token rejected' );
$request->headers['x-sync-token'] = get_option( 'ums_sheet_sync_token' );
expect( UMS_Sheet_User_Sync::authorize_request( $request ) === true, 'existing token still authorizes' );
expect( strpos( UMS_Auto_Sync_Bridge::get_bridge_url(), 'ums_auto_sync_bridge=1' ) !== false, 'scheduled bridge URL unchanged' );
expect( has_action( 'template_redirect' ) && has_action( 'wp_ajax_ums_allocation_sync_status' ), 'bridge and polling hooks registered' );
foreach ( UMS_DB_Inventory::get_factory_options() as $factory => $label ) {
    foreach ( array( 4, 9 ) as $month ) { $sources[$factory][$month] = array( 'url' => 'https://docs.google.com/spreadsheets/d/test-' . $factory . '-' . $month . '/edit', 'sheet_name' => 'Câu trả lời biểu mẫu 1' ); }
}
$options['ums_allocation_sheet_sources'] = $sources;
expect( UMS_Allocation_Sheet_Sync::get_sources()['DA'][4]['spreadsheet_id'] === 'test-DA-4', 'reads existing per-factory source options' );
expect( UMS_Allocation_Sheet_Sync::get_apps_script_url() !== get_option( 'ums_sheet_sync_apps_script_url' ), 'organization and allocation Apps Script URLs remain separate' );
foreach ( $sources as $factory => $months ) {
    foreach ( $months as $month => $source ) {
        $payload = array( 'factory_code' => $factory, 'period_month' => $month, 'calculation_year' => 2026, 'spreadsheet_id' => 'test-' . $factory . '-' . $month, 'sheet_name' => $source['sheet_name'], 'sync_token' => 'batchtest-' . $factory . '-' . $month . '-unique', 'client_sync_id' => 'clienttest-' . $factory . '-' . $month . '-unique', 'headers' => array( 'C' => 'Ma nhan vien' ), 'rows' => array( array( 'C' => 'F001' ) ), 'batch_offset' => 0, 'finalize' => false );
        $request->payload = $payload;
        $before = count( UMS_Allocation_Calculation::$calls );
        $result = UMS_Allocation_Sheet_Sync::handle_sync( $request );
        expect( $result instanceof WP_REST_Response && $result->data['received'] === 1 && count( UMS_Allocation_Calculation::$calls ) === $before, "$factory T$month: first batch stages without calculation" );
        $request->payload['batch_offset'] = 1;
        $request->payload['finalize'] = true;
        $result = UMS_Allocation_Sheet_Sync::handle_sync( $request );
        $last = end( UMS_Allocation_Calculation::$calls );
        expect( $result instanceof WP_REST_Response && count( $last['rows'] ) === 2 && $last['factory'] === $factory && $last['month'] === $month, "$factory T$month: final batch delegates correct scope to UMS preview" );
        expect( strpos( $result->data['preview_url'], 'tvn-ums-allocation-calculation' ) !== false && UMS_Allocation_Sheet_Sync::get_sync_status( $payload['client_sync_id'] )['preview_url'] === $result->data['preview_url'], "$factory T$month: polling returns original jqx preview destination" );
    }
}
$request->payload['spreadsheet_id'] = 'wrong-sheet';
expect( UMS_Allocation_Sheet_Sync::handle_sync( $request )->code === 'allocation_source_mismatch', 'wrong factory Sheet rejected' );
$request->payload = $payload;
$request->payload['sheet_name'] = 'wrong-tab';
expect( UMS_Allocation_Sheet_Sync::handle_sync( $request )->code === 'allocation_sheet_mismatch', 'wrong tab rejected' );
$request->payload = $payload;
$request->payload['batch_offset'] = 100;
expect( is_wp_error( UMS_Allocation_Sheet_Sync::handle_sync( $request ) ), 'missing or out-of-order batch rejected' );
do_action( 'admin_menu' );
expect( isset( $menus['tvn-ums-sheet-sync'] ) && $menus['tvn-ums-sheet-sync'][5][0] === 'UMS_Sheets_Connector_Admin', 'original settings menu now owned by connector' );
$_GET['page'] = 'tvn-ums-allocation-calculation';
UMS_Sheets_Connector_Admin::enqueue_assets( 'test' );
expect( isset( $scripts['ums-sheets-connector'], $styles['ums-sheets-connector'], $localized['umsSheetsConnector']['allocationSyncNonce'] ), 'standalone script CSS and polling nonce enqueued' );
ob_start(); UMS_Sheets_Connector_Admin::render_allocation_controls( 'DA', true ); $html = ob_get_clean();
expect( substr_count( $html, '[sheet_name]' ) === 6 && strpos( $html, 'ums-start-allocation-sheet-sync' ) !== false, 'allocation controls retain six sources and existing button ID' );
$allowed = false;
ob_start(); UMS_Sheets_Connector_Admin::render_allocation_controls( 'DA', true ); $html = ob_get_clean();
expect( $html === '', 'sync tokens not rendered without admin capability' );
$allowed = true;
if ( $with_core ) {
    $_GET['notice'] = 'allocation_sheet_settings_saved';
    expect( UMS_Admin::get_notice()['type'] === 'success', 'connector notices appear in UMS page' );
    ob_start(); UMS_Admin::render_allocation_calculation_page(); $html = ob_get_clean();
    expect( substr_count( $html, 'id="ums-start-allocation-sheet-sync"' ) === 1 && strpos( $html, 'ums_preview_allocation_calculation' ) !== false, 'core page combines connector controls and Excel once' );
    ob_start(); UMS_Admin::render_organization_page(); $html = ob_get_clean();
    expect( substr_count( $html, 'id="ums-start-sheet-sync"' ) === 1 && strpos( $html, 'ums-jqx-remote-grid' ) !== false, 'organization page combines local jqx and connector once' );
    ob_start(); UMS_Sheets_Connector_Admin::render_sheet_sync_page(); $html = ob_get_clean();
    expect( strpos( $html, 'ums_save_sheet_sync_settings' ) !== false, 'settings page still renders original save form' );
}
$saved_options = $options;
$saved_transients = $transients;
call_user_func( $GLOBALS['deactivate'] );
expect( $saved_options === $options && $saved_transients === $transients, 'deactivation preserves configuration tokens and in-flight preview data' );
echo "OK: $count checks ($mode); no production services accessed.\n";
