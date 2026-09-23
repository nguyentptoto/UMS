<?php
/**
 * Detects leavers and calculates/records uniform returns from actual stock issues.
 */
class UMS_Employee_Exit_Manager {

	const SHOE_LIFETIME_YEARS = 2;

	public static function init() {
		if ( defined( 'UMS_EMPLOYEE_EXIT_REMINDER_CRON_HOOK' ) ) {
			add_action( UMS_EMPLOYEE_EXIT_REMINDER_CRON_HOOK, array( __CLASS__, 'run_month_end_reminders' ) );
		}
	}

	public static function run_month_end_reminders() {
		$today = current_datetime();
		if ( ! UMS_DB_Employee_Exit::is_ready() ) {
			return array( 'reminders_sent' => 0, 'reminders_failed' => 0, 'reminders_skipped' => 0 );
		}
		if ( $today->format( 'Y-m-d' ) === $today->format( 'Y-m-t' ) ) {
			$target_month = $today;
		} elseif ( $today->format( 'd' ) === '01' ) {
			// First-day fallback covers a month-end WP-Cron event delayed by low site traffic.
			$target_month = $today->modify( '-1 day' );
		} else {
			return array( 'reminders_sent' => 0, 'reminders_failed' => 0, 'reminders_skipped' => 0 );
		}

		return self::send_month_end_reminders( $target_month->format( 'Y-m-01' ), $target_month->format( 'Y-m-t' ) );
	}

	public static function finalize_organization_sync( $sync_token, $detected_at ) {
		if ( ! UMS_DB_Organization::supports_employment_status() || ! UMS_DB_Employee_Exit::is_ready() ) {
			return new WP_Error( 'employee_exit_schema_missing', 'Database chưa có cấu trúc quản lý CNV nghỉ việc. Hãy chạy phần UPDATE trong ums.sql trước khi đồng bộ.' );
		}

		$employees = UMS_DB_Organization::get_active_not_in_sync( $sync_token );
		if ( empty( $employees ) ) {
			return array_merge( array( 'left' => 0, 'cases_created' => 0 ), self::send_pending_notifications() );
		}

		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$created = 0;
		foreach ( $employees as $employee ) {
			$result = self::create_case( $employee, $detected_at );
			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}
			$created += $result ? 1 : 0;
		}

		$marked = UMS_DB_Organization::mark_not_in_sync_as_left( $sync_token, $detected_at );
		if ( $marked === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_mark_failed', UMS_DB_Organization::get_last_error() );
		}
		$wpdb->query( 'COMMIT' );

		foreach ( $employees as $employee ) {
			self::set_wp_user_status( $employee['employee_no'], 1 );
		}
		return array_merge( array( 'left' => (int) $marked, 'cases_created' => $created ), self::send_pending_notifications() );
	}

	public static function restore_synced_employees( $rows ) {
		if ( ! UMS_DB_Employee_Exit::is_ready() ) {
			return;
		}
		$employee_nos = array();
		foreach ( (array) $rows as $row ) {
			if ( ! empty( $row['emp_no'] ) ) {
				$employee_nos[] = trim( (string) $row['emp_no'] );
			}
		}
		UMS_DB_Employee_Exit::cancel_open_cases( $employee_nos );
	}

	public static function classify_employee( $employee ) {
		$employee_no = strtoupper( trim( (string) ( $employee['employee_no'] ?? $employee['emp_no'] ?? '' ) ) );
		if ( preg_match( '/^[MF]1/', $employee_no ) ) {
			return 'labor_leasing';
		}
		$contract_date = trim( (string) ( $employee['first_contract_date'] ?? '' ) );
		return $contract_date !== '' && $contract_date !== '0000-00-00' && strtotime( $contract_date ) !== false
			? 'official'
			: 'probation';
	}

	public static function refresh_case( $exit_id, $actual_leave_date ) {
		$case = UMS_DB_Employee_Exit::get_by_id( $exit_id );
		if ( ! $case || $case['status'] === 'cancelled' ) {
			return new WP_Error( 'employee_exit_invalid', 'Không tìm thấy hồ sơ nghỉ việc đang hiệu lực.' );
		}
		$actual_leave_date = self::sanitize_date( $actual_leave_date );
		if ( $actual_leave_date === '' ) {
			return new WP_Error( 'employee_exit_date_invalid', 'Ngày nghỉ việc không hợp lệ.' );
		}

		$case['actual_leave_date'] = $actual_leave_date;
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		if ( UMS_DB_Employee_Exit::update_case(
			$exit_id,
			array( 'actual_leave_date' => $actual_leave_date, 'updated_at' => current_time( 'mysql' ) ),
			array( '%s', '%s' )
		) === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_update_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$result = self::build_return_items( $case, true );
		if ( is_wp_error( $result ) ) {
			$wpdb->query( 'ROLLBACK' );
			return $result;
		}
		$status = self::calculate_status( UMS_DB_Employee_Exit::get_items( $exit_id ) );
		$case_update = array( 'status' => $status, 'completed_at' => $status === 'completed' ? current_time( 'mysql' ) : null );
		$formats     = array( '%s', '%s' );
		$notification_error = (string) ( $case['notification_error'] ?? '' );
		if ( ( $case['notification_status'] ?? '' ) === 'skipped'
			&& ( strpos( $notification_error, 'chưa có dữ liệu đồng phục' ) !== false || strpos( $notification_error, 'hồ sơ chỉ có' ) !== false )
			&& self::has_uniform_return_obligation( UMS_DB_Employee_Exit::get_items( $exit_id ) ) ) {
			$case_update['notification_status']       = 'pending';
			$case_update['notification_attempted_at'] = null;
			$case_update['notification_error']        = '';
			$formats = array_merge( $formats, array( '%s', '%s', '%s' ) );
		}
		if ( UMS_DB_Employee_Exit::update_case(
			$exit_id,
			$case_update,
			$formats
		) === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_update_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$wpdb->query( 'COMMIT' );
		return true;
	}

	public static function save_returns( $exit_id, $posted_items, $notes = '' ) {
		$case = UMS_DB_Employee_Exit::get_by_id( $exit_id );
		if ( ! $case || in_array( $case['status'], array( 'cancelled' ), true ) ) {
			return new WP_Error( 'employee_exit_invalid', 'Không tìm thấy hồ sơ nghỉ việc đang hiệu lực.' );
		}

		$items = UMS_DB_Employee_Exit::get_items( $exit_id );
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		foreach ( $items as $item ) {
			$id = (int) $item['return_item_id'];
			$input = isset( $posted_items[ $id ] ) && is_array( $posted_items[ $id ] ) ? $posted_items[ $id ] : array();
			$returned = max( 0, absint( $input['returned_quantity'] ?? $item['returned_quantity'] ) );
			$max_return = max( (int) $item['required_quantity'], (int) $item['issued_quantity'] );
			$returned = min( $returned, $max_return );

			if ( UMS_DB_Employee_Exit::update_item(
				$id,
				array( 'returned_quantity' => $returned, 'updated_at' => current_time( 'mysql' ) ),
				array( '%d', '%s' )
			) === false ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'employee_exit_item_update_failed', UMS_DB_Employee_Exit::get_last_error() );
			}
		}

		$status = self::calculate_status( UMS_DB_Employee_Exit::get_items( $exit_id ) );
		$is_complete = $status === 'completed';
		$case_update = array(
			'status' => $status,
			'notes' => sanitize_textarea_field( $notes ),
			'updated_at' => current_time( 'mysql' ),
			'updated_by' => get_current_user_id(),
			'completed_at' => $is_complete ? current_time( 'mysql' ) : null,
		);
		if ( UMS_DB_Employee_Exit::update_case( $exit_id, $case_update, array( '%s', '%s', '%s', '%d', '%s' ) ) === false ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'employee_exit_update_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$wpdb->query( 'COMMIT' );
		return $status;
	}

	public static function ensure_case_uniform_items( $exit_id ) {
		$case = UMS_DB_Employee_Exit::get_by_id( $exit_id );
		if ( ! $case || $case['status'] === 'cancelled' ) {
			return false;
		}

		$existing_items   = UMS_DB_Employee_Exit::get_items( $exit_id );
		$existing_by_item = array();
		$existing_groups  = array();
		foreach ( $existing_items as $item ) {
			$existing_groups[ sanitize_key( (string) $item['item_group'] ) ] = true;
			if ( (int) $item['item_id'] > 0 ) {
				$existing_by_item[ (int) $item['item_id'] ] = (int) $item['issued_quantity'];
			}
		}
		if ( isset( $existing_groups['lanyard'] ) ) {
			return self::refresh_case( $exit_id, $case['actual_leave_date'] );
		}
		if ( in_array( $case['employee_type'], array( 'official', 'labor_leasing' ), true ) ) {
			foreach ( array( 'pants', 'shirt', 'hat', 'shoes', 'id_card' ) as $required_group ) {
				if ( ! isset( $existing_groups[ $required_group ] ) ) {
					return self::refresh_case( $exit_id, $case['actual_leave_date'] );
				}
			}
			return false;
		}

		$issued = self::get_issued_items( $case['employee_no'], $case['actual_leave_date'] );
		if ( empty( $issued ) ) {
			return false;
		}
		foreach ( $issued as $issued_item ) {
			$item_id = (int) $issued_item['item_id'];
			if ( ! isset( $existing_by_item[ $item_id ] ) || $existing_by_item[ $item_id ] !== (int) $issued_item['issued_quantity'] ) {
				return self::refresh_case( $exit_id, $case['actual_leave_date'] );
			}
		}

		return false;
	}

	private static function create_case( $employee, $detected_at ) {
		if ( UMS_DB_Employee_Exit::get_open_by_employee_no( $employee['employee_no'] ) ) {
			return 0;
		}
		$leave_date = mysql2date( 'Y-m-d', $detected_at );
		$data = array(
			'employee_no' => $employee['employee_no'], 'full_name' => $employee['full_name'],
			'department' => $employee['department'], 'team' => $employee['team'], 'cost_center' => $employee['cost_center'],
			'position' => $employee['position'], 'date_joined' => $employee['date_joined'],
			'first_contract_date' => $employee['first_contract_date'],
			'email' => sanitize_email( (string) ( $employee['email'] ?? '' ) ),
			'factory' => sanitize_text_field( (string) ( $employee['factory'] ?? '' ) ),
			'employee_type' => self::classify_employee( $employee ),
			'detected_at' => $detected_at, 'actual_leave_date' => $leave_date, 'status' => 'pending', 'notes' => '',
			'notification_status' => 'pending',
			'reminder_status' => 'pending',
			'organization_source_id' => absint( $employee['source_id'] ), 'created_at' => $detected_at, 'updated_at' => $detected_at,
		);
		$exit_id = UMS_DB_Employee_Exit::insert_case( $data );
		if ( $exit_id <= 0 ) {
			return new WP_Error( 'employee_exit_create_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		$data['exit_id'] = $exit_id;
		$result = self::build_return_items( $data );
		return is_wp_error( $result ) ? $result : $exit_id;
	}

	private static function send_pending_notifications() {
		$result = array( 'emails_sent' => 0, 'emails_failed' => 0, 'emails_skipped' => 0 );
		foreach ( UMS_DB_Employee_Exit::get_notification_candidates() as $case ) {
			$exit_id = absint( $case['exit_id'] );
			$items   = UMS_DB_Employee_Exit::get_items( $exit_id );
			if ( ! self::has_uniform_return_obligation( $items ) ) {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array(
						'notification_status' => 'skipped',
						'notification_attempted_at' => current_time( 'mysql' ),
						'notification_error' => 'Không gửi vì chưa có dữ liệu đồng phục đã cấp; hồ sơ chỉ có thẻ nhân viên.',
					),
					array( '%s', '%s', '%s' )
				);
				$result['emails_skipped']++;
				continue;
			}
			$email   = sanitize_email( (string) $case['email'] );
			if ( $email === '' || ! is_email( $email ) ) {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array(
						'notification_status' => 'skipped',
						'notification_attempted_at' => current_time( 'mysql' ),
						'notification_error' => 'Không có địa chỉ email hợp lệ trong Sơ đồ tổ chức.',
					),
					array( '%s', '%s', '%s' )
				);
				$result['emails_skipped']++;
				continue;
			}

			UMS_DB_Employee_Exit::update_case(
				$exit_id,
				array(
					'notification_status' => 'sending',
					'notification_attempted_at' => current_time( 'mysql' ),
					'notification_error' => '',
				),
				array( '%s', '%s', '%s' )
			);
			$subject = 'THÔNG BÁO HOÀN TRẢ ĐỒNG PHỤC NGHỈ VIỆC';
			$message = self::build_exit_notification_html( $case, $items );
			$sent = wp_mail( $email, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
			if ( $sent ) {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array( 'notification_status' => 'sent', 'notification_sent_at' => current_time( 'mysql' ), 'notification_error' => '' ),
					array( '%s', '%s', '%s' )
				);
				$result['emails_sent']++;
			} else {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array( 'notification_status' => 'failed', 'notification_error' => 'WordPress không gửi được email.' ),
					array( '%s', '%s' )
				);
				$result['emails_failed']++;
			}
		}
		return $result;
	}

	private static function send_month_end_reminders( $month_start, $month_end ) {
		$result = array( 'reminders_sent' => 0, 'reminders_failed' => 0, 'reminders_skipped' => 0 );
		foreach ( UMS_DB_Employee_Exit::get_reminder_candidates( $month_start, $month_end ) as $case ) {
			$exit_id = absint( $case['exit_id'] );
			$items   = UMS_DB_Employee_Exit::get_items( $exit_id );
			if ( ! self::has_outstanding_uniform_return( $items ) ) {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array(
						'reminder_status' => 'skipped',
						'reminder_attempted_at' => current_time( 'mysql' ),
						'reminder_error' => self::has_uniform_return_obligation( $items )
							? 'Không gửi vì CNV không còn thiếu đồng phục phải hoàn trả.'
							: 'Không gửi vì hồ sơ chỉ có thẻ nhân viên.',
					),
					array( '%s', '%s', '%s' )
				);
				$result['reminders_skipped']++;
				continue;
			}

			$email = sanitize_email( (string) $case['email'] );
			if ( $email === '' || ! is_email( $email ) ) {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array(
						'reminder_status' => 'skipped',
						'reminder_attempted_at' => current_time( 'mysql' ),
						'reminder_error' => 'Không có địa chỉ email hợp lệ trong Sơ đồ tổ chức.',
					),
					array( '%s', '%s', '%s' )
				);
				$result['reminders_skipped']++;
				continue;
			}

			UMS_DB_Employee_Exit::update_case(
				$exit_id,
				array( 'reminder_status' => 'sending', 'reminder_attempted_at' => current_time( 'mysql' ), 'reminder_error' => '' ),
				array( '%s', '%s', '%s' )
			);
			$sent = wp_mail(
				$email,
				'[NHẮC LẠI] THÔNG BÁO HOÀN TRẢ ĐỒNG PHỤC NGHỈ VIỆC',
				self::build_reminder_notification_html( $case, $items ),
				array( 'Content-Type: text/html; charset=UTF-8' )
			);
			if ( $sent ) {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array( 'reminder_status' => 'sent', 'reminder_sent_at' => current_time( 'mysql' ), 'reminder_error' => '' ),
					array( '%s', '%s', '%s' )
				);
				$result['reminders_sent']++;
			} else {
				UMS_DB_Employee_Exit::update_case(
					$exit_id,
					array( 'reminder_status' => 'failed', 'reminder_error' => 'WordPress không gửi được email nhắc cuối tháng.' ),
					array( '%s', '%s' )
				);
				$result['reminders_failed']++;
			}
		}

		return $result;
	}

	private static function build_exit_notification_html( $case, $items ) {
		$employee_no = strtoupper( trim( (string) $case['employee_no'] ) );
		$salutation  = strpos( $employee_no, 'M' ) === 0 ? 'Anh' : ( strpos( $employee_no, 'F' ) === 0 ? 'Chị' : 'Anh/chị' );
		$pronoun     = $salutation === 'Anh' ? 'anh' : ( $salutation === 'Chị' ? 'chị' : 'anh/chị' );
		$factory     = self::resolve_factory_notice( $case );
		$quantities  = array();
		$labels = array(
			'pants' => 'Quần', 'shirt' => 'Áo', 'jacket' => 'Áo khoác', 'coat' => 'Áo phao',
			'hat' => 'Mũ', 'shoes' => 'Giày', 'other' => 'Khác',
		);
		foreach ( (array) $items as $item ) {
			if ( ! self::is_uniform_return_group( $item['item_group'] ?? '' ) ) {
				continue;
			}
			$quantity = max( 0, (int) $item['required_quantity'] - (int) $item['exempt_quantity'] - (int) $item['returned_quantity'] );
			if ( $quantity <= 0 ) {
				continue;
			}
			$label = $labels[ $item['item_group'] ] ?? ( $item['item_name'] ?: 'Khác' );
			$quantities[ $label ] = ( $quantities[ $label ] ?? 0 ) + $quantity;
		}

		$rows = '';
		foreach ( $quantities as $label => $quantity ) {
			$rows .= '<tr><td style="border:1px solid #cbd5e1;padding:9px 12px">' . esc_html( $label ) . '</td>'
				. '<td style="border:1px solid #cbd5e1;padding:9px 12px;text-align:center">' . number_format_i18n( $quantity ) . '</td></tr>';
		}
		if ( $rows === '' ) {
			$rows = '<tr><td colspan="2" style="border:1px solid #cbd5e1;padding:9px 12px;text-align:center">Không có đồng phục phải hoàn trả</td></tr>';
		}

		$name = esc_html( (string) $case['full_name'] );
		return '<div style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.55;max-width:720px">'
			. '<h2 style="color:#164e86;text-align:center">THÔNG BÁO HOÀN TRẢ ĐỒNG PHỤC NGHỈ VIỆC</h2>'
			. '<p>Kính gửi ' . esc_html( $salutation ) . ' <strong>' . $name . '</strong>,</p>'
			. '<p>Phòng HCNS công ty TOTO Việt Nam - Chi nhánh ' . esc_html( $factory['branch'] )
			. ' gửi thông báo về việc hoàn trả đồng phục khi nghỉ việc với số lượng ' . esc_html( $pronoun ) . ' cần hoàn trả như sau:</p>'
			. '<p>Hiện tại HCNS chưa nhận được đồng phục hoàn trả khi nghỉ việc của ' . esc_html( $pronoun )
			. '. Vì vậy, phòng HCNS gửi thông báo để ' . esc_html( $pronoun )
			. ' nắm được và sắp xếp thời gian hoàn trả đồng phục theo đúng quy định.</p>'
			. '<p><strong>Số lượng cần hoàn trả:</strong></p>'
			. '<table style="border-collapse:collapse;width:100%;margin:12px 0 18px"><thead><tr>'
			. '<th style="background:#164e86;color:#fff;border:1px solid #cbd5e1;padding:10px">Loại đồng phục</th>'
			. '<th style="background:#164e86;color:#fff;border:1px solid #cbd5e1;padding:10px;width:180px">Số lượng cần trả</th>'
			. '</tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<p><strong>Thời gian hoàn trả:</strong><br>' . esc_html( $factory['time'] ) . '</p>'
			. '<p><strong>Địa điểm trả:</strong> ' . esc_html( $factory['location'] ) . '</p>'
			. '<p>Sau thời gian trên, HCNS sẽ chốt dữ liệu tính lương. Nếu ' . esc_html( $pronoun )
			. ' không hoàn trả theo đúng thời hạn trên, HCNS sẽ trừ tiền theo đúng quy định.</p>'
			. '<p>Trân trọng,<br><strong>Phòng Hành Chính nhân sự</strong></p></div>';
	}

	private static function build_reminder_notification_html( $case, $items ) {
		$employee_no = strtoupper( trim( (string) $case['employee_no'] ) );
		$salutation  = strpos( $employee_no, 'M' ) === 0 ? 'Anh' : ( strpos( $employee_no, 'F' ) === 0 ? 'Chị' : 'Anh/chị' );
		$pronoun     = $salutation === 'Anh' ? 'anh' : ( $salutation === 'Chị' ? 'chị' : 'anh/chị' );
		$factory     = self::resolve_factory_notice( $case );
		$labels      = array(
			'pants' => 'Quần', 'shirt' => 'Áo', 'jacket' => 'Áo khoác', 'coat' => 'Áo phao',
			'hat' => 'Mũ', 'shoes' => 'Giày', 'other' => 'Khác',
		);
		$quantities = array();
		foreach ( (array) $items as $item ) {
			if ( ! self::is_uniform_return_group( $item['item_group'] ?? '' ) ) {
				continue;
			}
			$required = max( 0, (int) $item['required_quantity'] - (int) $item['exempt_quantity'] );
			if ( $required <= 0 ) {
				continue;
			}
			$label = $labels[ $item['item_group'] ] ?? ( $item['item_name'] ?: 'Khác' );
			if ( ! isset( $quantities[ $label ] ) ) {
				$quantities[ $label ] = array( 'required' => 0, 'returned' => 0 );
			}
			$quantities[ $label ]['required'] += $required;
			$quantities[ $label ]['returned'] += min( $required, max( 0, (int) $item['returned_quantity'] ) );
		}

		$rows = '';
		foreach ( $quantities as $label => $quantity ) {
			$missing = max( 0, $quantity['required'] - $quantity['returned'] );
			$rows .= '<tr><td style="border:1px solid #cbd5e1;padding:9px 12px">' . esc_html( $label ) . '</td>'
				. '<td style="border:1px solid #cbd5e1;padding:9px 12px;text-align:center">' . number_format_i18n( $quantity['returned'] ) . '</td>'
				. '<td style="border:1px solid #cbd5e1;padding:9px 12px;text-align:center">' . number_format_i18n( $missing ) . '</td></tr>';
		}

		$name = esc_html( (string) $case['full_name'] );
		return '<div style="font-family:Arial,sans-serif;color:#1f2937;line-height:1.55;max-width:800px">'
			. '<h2 style="color:#164e86;text-align:center">THÔNG BÁO HOÀN TRẢ ĐỒNG PHỤC NGHỈ VIỆC</h2>'
			. '<p>Kính gửi ' . esc_html( $salutation ) . ' <strong>' . $name . '</strong>,</p>'
			. '<p>Phòng HCNS công ty TOTO Việt Nam - Chi nhánh ' . esc_html( $factory['branch'] )
			. ' gửi thông báo về việc hoàn trả đồng phục khi nghỉ việc.</p>'
			. '<p>Hiện tại HCNS chưa nhận đủ đồng phục hoàn trả khi nghỉ việc của ' . esc_html( $pronoun )
			. '. Vì vậy, phòng HCNS gửi thông báo nhắc lại để ' . esc_html( $pronoun )
			. ' sắp xếp thời gian tới công ty hoàn trả đồng phục theo đúng quy định.</p>'
			. '<p><strong>Số lượng đồng phục cần hoàn trả:</strong></p>'
			. '<table style="border-collapse:collapse;width:100%;margin:12px 0 18px"><thead><tr>'
			. '<th style="background:#164e86;color:#fff;border:1px solid #cbd5e1;padding:10px">Loại đồng phục</th>'
			. '<th style="background:#164e86;color:#fff;border:1px solid #cbd5e1;padding:10px;width:180px">Số lượng đã trả</th>'
			. '<th style="background:#164e86;color:#fff;border:1px solid #cbd5e1;padding:10px;width:180px">Số lượng còn thiếu</th>'
			. '</tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<p><strong>Thời gian hoàn trả:</strong><br>' . esc_html( $factory['time'] ) . '</p>'
			. '<p><strong>Địa điểm trả:</strong> ' . esc_html( $factory['location'] ) . '</p>'
			. '<p style="color:#dc2626">Sau thời gian trên, HCNS sẽ chốt dữ liệu tính lương. Nếu ' . esc_html( $pronoun )
			. ' không hoàn trả theo đúng thời hạn trên, HCNS sẽ trừ tiền theo đúng quy định.</p>'
			. '<p>Trân trọng,<br><strong>Phòng Hành Chính nhân sự</strong></p></div>';
	}

	private static function has_uniform_return_obligation( $items ) {
		foreach ( (array) $items as $item ) {
			if ( self::is_uniform_return_group( $item['item_group'] ?? '' ) && (int) $item['required_quantity'] > (int) $item['exempt_quantity'] ) {
				return true;
			}
		}
		return false;
	}

	private static function has_outstanding_uniform_return( $items ) {
		foreach ( (array) $items as $item ) {
			$missing = (int) $item['required_quantity'] - (int) $item['exempt_quantity'] - (int) $item['returned_quantity'];
			if ( self::is_uniform_return_group( $item['item_group'] ?? '' ) && $missing > 0 ) {
				return true;
			}
		}
		return false;
	}

	private static function is_uniform_return_group( $group ) {
		return ! in_array( sanitize_key( (string) $group ), array( 'id_card', 'lanyard' ), true );
	}

	private static function resolve_factory_notice( $case ) {
		$factory = strtolower( remove_accents( trim( (string) ( $case['factory'] ?? '' ) ) ) );
		$department = strtolower( remove_accents( trim( (string) ( $case['department'] ?? '' ) ) ) );
		$prefix = substr( preg_replace( '/[^0-9]/', '', (string) ( $case['cost_center'] ?? '' ) ), 0, 4 );
		if ( strpos( $factory, 'hung yen' ) !== false || strpos( $department, '(hy)' ) !== false || $prefix === '4400' ) {
			return array(
				'branch' => 'Hưng Yên',
				'time' => 'Từ 13:45 đến 14:15 chiều thứ 3 hoặc thứ 5 trong tháng nghỉ việc hoặc muộn nhất trước 10h ngày làm việc đầu tiên của tháng tiếp theo.',
				'location' => 'Cổng A nhà máy TOTO Hưng Yên',
			);
		}
		if ( strpos( $factory, 'dong anh' ) !== false || strpos( $department, '(da)' ) !== false || $prefix === '1300' ) {
			return array(
				'branch' => 'Đông Anh',
				'time' => 'Vào ngày làm việc cuối cùng. Trường hợp bất khả kháng có lý do hợp lý, thời gian hoàn trả không muộn hơn 3 ngày làm việc kể từ ngày nghỉ việc.',
				'location' => 'Cổng A nhà máy Vĩnh Phúc',
			);
		}
		if ( strpos( $factory, 'vinh phuc' ) !== false || strpos( $department, '(vp)' ) !== false || $prefix === '4900' ) {
			return array(
				'branch' => 'Vĩnh Phúc',
				'time' => 'Vào ngày làm việc cuối cùng. Trường hợp bất khả kháng có lý do hợp lý, thời gian hoàn trả không muộn hơn 3 ngày làm việc kể từ ngày nghỉ việc.',
				'location' => 'Cổng A nhà máy Vĩnh Phúc',
			);
		}
		return array(
			'branch' => trim( (string) ( $case['factory'] ?? '' ) ) ?: 'TOTO Việt Nam',
			'time' => 'Vào ngày làm việc cuối cùng. Trường hợp bất khả kháng có lý do hợp lý, thời gian hoàn trả không muộn hơn 3 ngày làm việc kể từ ngày nghỉ việc.',
			'location' => 'Phòng Hành Chính nhân sự tại nơi làm việc',
		);
	}

	private static function build_return_items( $case, $preserve_progress = false ) {
		$existing = array();
		$existing_returned_by_group = array();
		if ( $preserve_progress ) {
			foreach ( UMS_DB_Employee_Exit::get_items( $case['exit_id'] ) as $item ) {
				$existing[ self::item_key( $item['item_id'], $item['item_group'], $item['item_name'], $item['size'] ) ] = $item;
				$group = sanitize_key( (string) $item['item_group'] );
				$existing_returned_by_group[ $group ] = ( $existing_returned_by_group[ $group ] ?? 0 ) + (int) $item['returned_quantity'];
			}
		}
		$issued = self::get_issued_items( $case['employee_no'], $case['actual_leave_date'] );
		$type   = $case['employee_type'];
		$allowance_by_group = in_array( $type, array( 'official', 'labor_leasing' ), true )
			? UMS_Employee_Allowance_Report::build_context_annual_totals( $case, (int) substr( $case['actual_leave_date'], 0, 4 ) )
			: array();
		$issued_by_group = array();
		foreach ( $issued as &$row ) {
			$row['item_group'] = UMS_Employee_Allowance_Report::get_business_group( $row );
			$group = $row['item_group'] !== '' ? $row['item_group'] : 'other';
			if ( ! isset( $issued_by_group[ $group ] ) ) {
				$issued_by_group[ $group ] = array( 'quantity' => 0, 'latest_issued_at' => '' );
			}
			$issued_by_group[ $group ]['quantity'] += max( 0, (int) $row['issued_quantity'] );
			if ( $issued_by_group[ $group ]['latest_issued_at'] === '' || $row['latest_issued_at'] > $issued_by_group[ $group ]['latest_issued_at'] ) {
				$issued_by_group[ $group ]['latest_issued_at'] = $row['latest_issued_at'];
			}
		}
		unset( $row );

		$new_items = array();
		$order = 0;
		if ( $type === 'probation' ) {
			usort( $issued, function ( $a, $b ) { return strcmp( $b['latest_issued_at'], $a['latest_issued_at'] ); } );
			foreach ( $issued as $row ) {
				$issued_qty = max( 0, (int) $row['issued_quantity'] );
				$group = $row['item_group'] !== '' ? $row['item_group'] : 'other';
				$new_items[] = self::return_item_data( $case['exit_id'], $row, $group, $issued_qty, $issued_qty, 0, '', ++$order, $existing );
			}
		} else {
			$fixed_obligations = array(
				'pants' => array( 'name' => 'Quần', 'quantity' => 2 ),
				'shirt' => array( 'name' => 'Áo', 'quantity' => 2 ),
				'hat' => array( 'name' => 'Mũ', 'quantity' => 1 ),
				'shoes' => array( 'name' => 'Giày BHLĐ', 'quantity' => 1 ),
			);
			if ( ! empty( $issued_by_group['jacket']['quantity'] ) || ! empty( $allowance_by_group['jacket'] ) ) {
				$fixed_obligations = array_slice( $fixed_obligations, 0, 2, true )
					+ array( 'jacket' => array( 'name' => 'Áo khoác', 'quantity' => 1 ) )
					+ array_slice( $fixed_obligations, 2, null, true );
			}
			foreach ( $fixed_obligations as $group => $obligation ) {
				$history = $issued_by_group[ $group ] ?? array( 'quantity' => 0, 'latest_issued_at' => '' );
				$source_quantity = (int) $history['quantity'] > 0
					? (int) $history['quantity']
					: max( 0, (int) ( $allowance_by_group[ $group ] ?? 0 ) );
				$required_quantity = min( (int) $obligation['quantity'], $source_quantity );
				$latest = (string) $history['latest_issued_at'];
				$exempt = 0;
				$reason = 'Số lượng bắt buộc hoàn trả theo TVN-QDDP 01.05.';
				if ( $group === 'shoes' && $type === 'official' && $latest !== '' ) {
					$latest_date = mysql2date( 'Y-m-d', $latest );
					if ( strtotime( $latest_date ) < strtotime( '-' . self::SHOE_LIFETIME_YEARS . ' years', strtotime( $case['actual_leave_date'] ) ) ) {
						$exempt = 1;
						$reason = 'Lần cấp giày gần nhất đã quá 02 năm tính đến ngày nghỉ việc.';
					}
				}
				$row = array( 'item_id' => 0, 'item_variant' => $obligation['name'], 'size' => '', 'latest_issued_at' => $latest ?: null );
				$item = self::return_item_data( $case['exit_id'], $row, $group, $source_quantity, $required_quantity, min( $exempt, $required_quantity ), $reason, ++$order, $existing );
				$item['returned_quantity'] = min( $required_quantity, max( $item['returned_quantity'], $existing_returned_by_group[ $group ] ?? 0 ) );
				$new_items[] = $item;
			}
		}

		$row = array( 'item_id' => 0, 'item_variant' => 'Thẻ tên / thẻ nhân viên', 'size' => '', 'latest_issued_at' => null );
		$new_items[] = self::return_item_data( $case['exit_id'], $row, 'id_card', 1, 1, 0, '', ++$order, $existing );

		if ( UMS_DB_Employee_Exit::delete_items( $case['exit_id'] ) === false ) {
			return new WP_Error( 'employee_exit_items_failed', UMS_DB_Employee_Exit::get_last_error() );
		}
		foreach ( $new_items as $item ) {
			if ( ! UMS_DB_Employee_Exit::insert_item( $item ) ) {
				return new WP_Error( 'employee_exit_items_failed', UMS_DB_Employee_Exit::get_last_error() );
			}
		}
		return true;
	}

	private static function return_item_data( $exit_id, $row, $group, $issued, $required, $exempt, $reason, $order, $existing ) {
		$name = trim( (string) ( $row['item_variant'] ?? '' ) );
		$size = trim( (string) ( $row['size'] ?? '' ) );
		$key  = self::item_key( $row['item_id'] ?? 0, $group, $name, $size );
		$old  = $existing[ $key ] ?? array();
		$returned = min( (int) ( $old['returned_quantity'] ?? 0 ), max( $issued, $required ) );
		$restocked = min( (int) ( $old['restocked_quantity'] ?? 0 ), $returned );
		return array(
			'exit_id' => (int) $exit_id, 'item_id' => absint( $row['item_id'] ?? 0 ), 'item_group' => $group,
			'item_name' => $name, 'size' => $size, 'issued_quantity' => $issued, 'required_quantity' => $required,
			'returned_quantity' => $returned, 'exempt_quantity' => $exempt,
			'reusable_quantity' => max( $restocked, min( (int) ( $old['reusable_quantity'] ?? 0 ), $returned ) ),
			'restocked_quantity' => $restocked, 'exemption_reason' => $reason,
			'latest_issued_at' => $row['latest_issued_at'] ?? null, 'display_order' => $order,
		);
	}

	private static function get_issued_items( $employee_no, $until_date = '' ) {
		global $wpdb;
		$user_ids = array();
		$user = get_user_by( 'login', $employee_no );
		if ( $user instanceof WP_User ) {
			$user_ids[] = (int) $user->ID;
		}
		$meta_ids = get_users( array( 'meta_key' => 'ums_employee_code', 'meta_value' => $employee_no, 'fields' => 'ids' ) );
		$user_ids = array_values( array_unique( array_merge( $user_ids, array_map( 'absint', $meta_ids ) ) ) );
		$where = 'm.target_employee_no = %s';
		$params = array( $employee_no );
		if ( $user_ids ) {
			$where .= ' OR m.target_user_id IN (' . implode( ',', array_fill( 0, count( $user_ids ), '%d' ) ) . ')';
			$params = array_merge( $params, $user_ids );
		}
		$sql = "SELECT m.item_id, SUM(m.quantity) AS issued_quantity, MAX(m.created_at) AS latest_issued_at,
			i.item_variant, i.size, child.category_name, parent.category_name AS parent_category_name
			FROM " . UMS_DB_Inventory_Movement::table() . " m
			INNER JOIN " . UMS_DB_Inventory::table() . " i ON i.item_id = m.item_id
			LEFT JOIN " . UMS_DB_Product_Category::table() . " child ON child.category_id = i.category_id
			LEFT JOIN " . UMS_DB_Product_Category::table() . " parent ON parent.category_id = child.parent_id
			WHERE m.movement_type = 'out' AND ($where)
			GROUP BY m.item_id, i.item_variant, i.size, child.category_name, parent.category_name
			HAVING SUM(m.quantity) > 0";
		$issued = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		if ( ! empty( $issued ) ) {
			return $issued;
		}

		// Imported allocation snapshots contain exact products and sizes even when
		// the corresponding physical stock-out movement has not yet been recorded.
		return UMS_DB_Allocation_Calculation::get_employee_allocated_items( $employee_no, $until_date );
	}

	private static function item_key( $item_id, $group, $name, $size ) {
		return absint( $item_id ) . '|' . sanitize_key( $group ) . '|' . strtolower( trim( $name ) ) . '|' . strtolower( trim( $size ) );
	}

	private static function sanitize_date( $date ) {
		$date = sanitize_text_field( (string) $date );
		$parts = array_map( 'intval', explode( '-', $date ) );
		return count( $parts ) === 3 && checkdate( $parts[1], $parts[2], $parts[0] ) ? $date : '';
	}

	private static function calculate_status( $items ) {
		$has_progress = false;
		$is_complete = true;
		foreach ( (array) $items as $item ) {
			$settled = (int) $item['returned_quantity'] + (int) $item['exempt_quantity'];
			$has_progress = $has_progress || $settled > 0;
			$is_complete  = $is_complete && $settled >= (int) $item['required_quantity'];
		}
		return $is_complete ? 'completed' : ( $has_progress ? 'in_progress' : 'pending' );
	}

	private static function set_wp_user_status( $employee_no, $status ) {
		$user = get_user_by( 'login', $employee_no );
		if ( ! $user ) {
			$users = get_users( array( 'meta_key' => 'ums_employee_code', 'meta_value' => $employee_no, 'number' => 1 ) );
			$user = $users ? reset( $users ) : null;
		}
		if ( $user instanceof WP_User ) {
			global $wpdb;
			$wpdb->update( $wpdb->users, array( 'user_status' => absint( $status ) ), array( 'ID' => $user->ID ), array( '%d' ), array( '%d' ) );
			clean_user_cache( $user->ID );
		}
	}
}
