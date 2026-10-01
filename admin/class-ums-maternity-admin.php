<?php
class UMS_Maternity_Admin {
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_ums_maternity', array( __CLASS__, 'handle' ) );
	}
	public static function menu() {
		add_submenu_page( 'tvn-uniform-management', 'Đồng phục bầu', 'Đồng phục bầu', 'manage_options', 'tvn-ums-maternity', array( __CLASS__, 'render' ) );
	}
	public static function assets( $hook ) {
		if ( strpos( $hook, 'tvn-ums-maternity' ) === false ) { return; }
		UMS_Admin::enqueue_admin_assets( 'tvn-ums-approval-flows' );
		wp_enqueue_style( 'ums-maternity', UMS_PLUGIN_URL . 'admin/css/ums-maternity.css', array( 'ums-admin-css' ), filemtime( UMS_PLUGIN_DIR . 'admin/css/ums-maternity.css' ) );
		wp_enqueue_script( 'ums-maternity', UMS_PLUGIN_URL . 'admin/js/ums-maternity.js', array( 'ums-admin-js', 'ums-select2-js' ), filemtime( UMS_PLUGIN_DIR . 'admin/js/ums-maternity.js' ), true );
	}
	public static function handle() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không có quyền quản lý thai sản.', '', array( 'response' => 403 ) ); }
		check_admin_referer( 'ums_maternity' );
		$id = absint( $_POST['episode_id'] ?? 0 );
		$action = sanitize_key( $_POST['operation'] ?? '' );
		$actor = get_current_user_id();
		$message = 'Đã lưu thông tin.';
		$error = false;
		try {
			switch ( $action ) {
				case 'create':
					$id = UMS_Maternity::create( wp_unslash( $_POST['employee_no'] ?? '' ), wp_unslash( $_POST['date'] ?? '' ), $actor );
					break;
				case 'approve':
					UMS_Maternity::approve( $id, wp_unslash( $_POST['items'] ?? array() ), $actor );
					$message = 'Đã duyệt số lượng lần này. Chưa xuất kho; kỳ khóa hiện có không thay đổi.';
					break;
				case 'receive':
					UMS_Maternity::receive( $id, wp_unslash( $_POST['date'] ?? '' ), $actor, wp_unslash( $_POST['approval_hash'] ?? '' ) );
					$message = 'Đã ghi nhận thực nhận và xuất kho. Số lượng được cộng dồn trong thai kỳ; kỳ khóa tính theo lần nhận đầu tiên.';
					break;
				case 'finish':
					UMS_Maternity::finish( $id, wp_unslash( $_POST['date'] ?? '' ), $actor );
					$message = 'Đã kết thúc thai sản. Lịch sử nhận đồ và kỳ bị khóa được giữ nguyên.';
					break;
				default: throw new RuntimeException( 'Thao tác không hợp lệ.' );
			}
		} catch ( Throwable $e ) { $error = true; $message = $e->getMessage(); }
		set_transient( 'ums_maternity_notice_' . $actor, array( 'error' => $error, 'message' => $message ), 60 );
		wp_safe_redirect( add_query_arg( array( 'page' => 'tvn-ums-maternity', 'episode_id' => $id ), admin_url( 'admin.php' ) ) );
		exit;
	}
	public static function form_fields( $operation, $id = 0 ) {
		wp_nonce_field( 'ums_maternity' );
		echo '<input type="hidden" name="action" value="ums_maternity"><input type="hidden" name="operation" value="' . esc_attr( $operation ) . '"><input type="hidden" name="episode_id" value="' . esc_attr( $id ) . '">';
	}
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Không có quyền quản lý thai sản.' ); }
		global $wpdb;
		$factories = UMS_DB_Inventory::get_factory_options();
		$factory = sanitize_text_field( wp_unslash( $_GET['factory_code'] ?? '' ) );
		$factory = isset( $factories[ $factory ] ) ? $factory : '';
		$search = sanitize_text_field( wp_unslash( $_GET['search'] ?? '' ) );
		$where = '1=1';
		if ( $factory !== '' ) { $where .= $wpdb->prepare( ' AND factory_code = %s', $factory ); }
		if ( $search !== '' ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			$where .= $wpdb->prepare( ' AND (employee_no LIKE %s OR full_name LIKE %s)', $like, $like );
		}
		$rows = $wpdb->get_results( 'SELECT * FROM ' . UMS_Maternity::table() . " WHERE $where ORDER BY episode_id DESC", ARRAY_A );
		$episode = UMS_Maternity::get( absint( $_GET['episode_id'] ?? 0 ) );
		$employees = UMS_DB_Organization::get_for_allowance_export();
		$items = UMS_DB_Inventory::get_all( array( 'factory_code' => $episode ? $episode['factory_code'] : UMS_DB_Inventory::DEFAULT_FACTORY ) );
		$products = array_fill_keys( array_keys( UMS_Maternity::limits() ), array() );
		foreach ( $items as $item ) {
			$group = UMS_Maternity::product_group( $item );
			if ( $group !== '' ) { $products[ $group ][] = $item; }
		}
		$notice = get_transient( 'ums_maternity_notice_' . get_current_user_id() );
		delete_transient( 'ums_maternity_notice_' . get_current_user_id() );
		$events = $episode ? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . UMS_Maternity::event_table() . ' WHERE episode_id = %d ORDER BY event_id DESC', $episode['episode_id'] ), ARRAY_A ) : array();
		require UMS_PLUGIN_DIR . 'admin/partials/view-maternity.php';
	}
}
