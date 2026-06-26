<?php
/**
 * Helper cấu hình danh mục động cho UMS.
 *
 * Tất cả dropdown/cấu hình nghiệp vụ dùng mảng tập trung tại đây và đều đi qua
 * apply_filters để sau này có thể mở rộng bằng plugin khác hoặc chuyển sang DB.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'ums_get_departments' ) ) {
	/**
	 * Lấy danh sách bộ phận/khối làm việc.
	 *
	 * @return array<string,string>
	 */
	function ums_get_departments() {
		$departments = array();

		if ( class_exists( 'UMS_DB_Department' ) ) {
			$active_departments = UMS_DB_Department::get_active();
			if ( is_array( $active_departments ) ) {
				foreach ( $active_departments as $department ) {
					$departments[ $department['department_code'] ] = $department['department_name'];
				}
			}
		}

		return apply_filters( 'ums_departments', $departments );
	}
}

if ( ! function_exists( 'ums_get_job_positions' ) ) {
	/**
	 * Lấy danh sách chức danh/cấp bậc.
	 *
	 * @return array<string,string>
	 */
	function ums_get_job_positions() {
		$positions = array();

		if ( class_exists( 'UMS_DB_Position' ) ) {
			$active_positions = UMS_DB_Position::get_active();
			if ( is_array( $active_positions ) ) {
				foreach ( $active_positions as $position ) {
					$positions[ $position['position_code'] ] = $position['position_name'];
				}
			}
		}

		return apply_filters( 'ums_job_positions', $positions );
	}
}

if ( ! function_exists( 'ums_get_factory_locations' ) ) {
	/**
	 * Lấy danh sách nhà máy/địa điểm làm việc từ danh mục động.
	 *
	 * @return array<string,string>
	 */
	function ums_get_factory_locations() {
		$locations = array();

		if ( class_exists( 'UMS_DB_Factory_Location' ) ) {
			$active_locations = UMS_DB_Factory_Location::get_active();
			if ( is_array( $active_locations ) ) {
				foreach ( $active_locations as $location ) {
					$locations[ $location['factory_location_code'] ] = $location['factory_location_name'];
				}
			}
		}

		return apply_filters( 'ums_factory_locations', $locations );
	}
}

if ( ! function_exists( 'ums_get_contract_types' ) ) {
	/**
	 * Lấy danh sách loại hợp đồng từ danh mục động.
	 *
	 * @return array<string,string>
	 */
	function ums_get_contract_types() {
		$contract_types = array();

		if ( class_exists( 'UMS_DB_Contract_Type' ) ) {
			$active_contract_types = UMS_DB_Contract_Type::get_active();
			if ( is_array( $active_contract_types ) ) {
				foreach ( $active_contract_types as $contract_type ) {
					$contract_types[ $contract_type['contract_type_code'] ] = $contract_type['contract_type_name'];
				}
			}
		}

		return apply_filters( 'ums_contract_types', $contract_types );
	}
}

if ( ! function_exists( 'ums_get_maternity_allowances' ) ) {
	/**
	 * Lấy định mức đồng phục thai sản theo nhóm đối tượng.
	 *
	 * Giá trị mặc định để trống có chủ đích. Hãy bổ sung bằng filter
	 * `ums_maternity_allowances` hoặc nguồn dữ liệu DB khi cần.
	 *
	 * @return array<string,array{item:string,max_qty:int,has_jacket:int}>
	 */
	function ums_get_maternity_allowances() {
		$allowances = array();

		return apply_filters( 'ums_maternity_allowances', $allowances );
	}
}

if ( ! function_exists( 'ums_get_color_rules' ) ) {
	/**
	 * Lấy ma trận quy tắc màu mũ/quần áo.
	 *
	 * Các nhóm rule được giữ sẵn để module khác có thể nạp dữ liệu động
	 * mà không cần sửa code lõi.
	 *
	 * @return array<string,array<string,string|array<int,string>>>
	 */
	function ums_get_color_rules() {
		$rules = array(
			'helmet_by_position'    => array(),
			'helmet_by_department'  => array(),
			'uniform_by_department' => array(),
		);

		return apply_filters( 'ums_color_rules', $rules );
	}
}
