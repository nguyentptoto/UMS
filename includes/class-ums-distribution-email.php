<?php
/**
 * Import recipients and compose a reviewable uniform distribution schedule email.
 */
class UMS_Distribution_Email {
	const RECIPIENT_PREFIX = 'ums_distribution_recipients_';
	const DRAFT_PREFIX = 'ums_distribution_email_draft_';
	const TTL = 2 * HOUR_IN_SECONDS;

	public static function import_recipients( $file ) {
		if ( empty( $file['tmp_name'] ) || ! empty( $file['error'] ) || empty( $file['size'] )
			|| (int) $file['size'] > 5 * MB_IN_BYTES
			|| strtolower( pathinfo( (string) ( $file['name'] ?? '' ), PATHINFO_EXTENSION ) ) !== 'xlsx' ) {
			throw new RuntimeException( 'Hãy chọn file XLSX hợp lệ, dung lượng tối đa 5 MB.' );
		}

		$reader = new UMS_XLSX_Reader( $file['tmp_name'] );
		$sheet_rows = array();
		$sheet_name = '';
		foreach ( $reader->get_sheet_names() as $name ) {
			$rows = $reader->read_sheet( $name );
			$header = $rows[1] ?? array();
			if ( strtolower( trim( (string) ( $header['A'] ?? '' ) ) ) === 'to'
				&& strtolower( trim( (string) ( $header['B'] ?? '' ) ) ) === 'cc' ) {
				$sheet_rows = $rows;
				$sheet_name = $name;
				break;
			}
		}
		if ( $sheet_name === '' ) {
			throw new RuntimeException( 'Không tìm thấy sheet có tiêu đề To ở cột A và CC ở cột B, dòng 1.' );
		}

		$to = array();
		$cc = array();
		$errors = array();
		foreach ( $sheet_rows as $row_number => $row ) {
			if ( $row_number === 1 ) {
				continue;
			}
			$to = array_merge( $to, self::parse_addresses( $row['A'] ?? '', 'To', $errors, $row_number ) );
			$cc = array_merge( $cc, self::parse_addresses( $row['B'] ?? '', 'CC', $errors, $row_number ) );
		}
		if ( $errors ) {
			throw new RuntimeException( implode( ' ', array_slice( $errors, 0, 5 ) ) );
		}
		$recipients = self::normalize_recipients( $to, $cc );
		if ( ! $recipients['to'] ) {
			throw new RuntimeException( 'File cần ít nhất một địa chỉ To.' );
		}
		return $recipients;
	}

	public static function save_recipients( $user_id, $recipients ) {
		return set_transient( self::RECIPIENT_PREFIX . absint( $user_id ), $recipients, self::TTL );
	}

	public static function get_recipients( $user_id ) {
		$value = get_transient( self::RECIPIENT_PREFIX . absint( $user_id ) );
		return is_array( $value ) ? $value : array( 'to' => array(), 'cc' => array() );
	}

	public static function build_draft( $input ) {
		$errors = array();
		$to_input = sanitize_textarea_field( (string) ( $input['to'] ?? '' ) );
		$cc_input = sanitize_textarea_field( (string) ( $input['cc'] ?? '' ) );
		$to = self::parse_addresses( $to_input, 'To', $errors );
		$cc = self::parse_addresses( $cc_input, 'CC', $errors );
		$recipients = self::normalize_recipients( $to, $cc );
		if ( ! $recipients['to'] ) {
			$errors[] = 'Cần ít nhất một địa chỉ To hợp lệ.';
		}

		$period = sanitize_text_field( (string) ( $input['period'] ?? '' ) );
		if ( $period === '' || strlen( $period ) > 60 ) {
			$errors[] = 'Hãy nhập tên đợt cấp phát, ví dụ 2.2026.';
		}
		$date = sanitize_text_field( (string) ( $input['distribution_date'] ?? '' ) );
		$date_parts = explode( '-', $date );
		if ( count( $date_parts ) !== 3 || ! checkdate( (int) ( $date_parts[1] ?? 0 ), (int) ( $date_parts[2] ?? 0 ), (int) ( $date_parts[0] ?? 0 ) ) ) {
			$errors[] = 'Ngày cấp phát không hợp lệ.';
		}
		$link = esc_url_raw( (string) ( $input['data_link'] ?? '' ) );
		if ( ! in_array( wp_parse_url( $link, PHP_URL_SCHEME ), array( 'https', 'http' ), true ) || ! wp_parse_url( $link, PHP_URL_HOST ) ) {
			$errors[] = 'Hãy nhập link dữ liệu cấp phát hợp lệ.';
		}

		$schedule = array();
		$schedule_input = array();
		foreach ( (array) ( $input['schedule'] ?? array() ) as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$time = sanitize_text_field( (string) ( $row['time'] ?? '' ) );
			$department = sanitize_text_field( (string) ( $row['department'] ?? '' ) );
			$schedule_input[] = array( 'time' => $time, 'department' => $department );
			if ( $time === '' && $department === '' ) {
				continue;
			}
			if ( $time === '' || $department === '' || strlen( $time ) > 60 || strlen( $department ) > 200 ) {
				$errors[] = sprintf( 'Khung giờ %d cần có đủ thời gian và bộ phận.', absint( $index ) + 1 );
				continue;
			}
			$schedule[] = array( 'time' => $time, 'department' => $department );
		}
		if ( ! $schedule || count( $schedule ) > 30 ) {
			$errors[] = 'Hãy nhập từ 1 đến 30 khung giờ cấp phát.';
		}

		$subject_input = sanitize_text_field( (string) ( $input['subject'] ?? '' ) );
		$subject = $subject_input;
		if ( $subject === '' ) {
			$subject = 'LỊCH CẤP PHÁT ĐỒNG PHỤC ĐỢT ' . $period;
		}
		if ( strlen( $subject ) > 200 ) {
			$errors[] = 'Tiêu đề email quá dài.';
		}

		return array(
			'to' => $recipients['to'], 'cc' => $recipients['cc'],
			'to_input' => $to_input, 'cc_input' => $cc_input,
			'subject' => $subject, 'subject_input' => $subject_input, 'period' => $period,
			'distribution_date' => $date, 'data_link' => $link,
			'schedule' => $schedule, 'schedule_input' => $schedule_input, 'errors' => $errors,
		);
	}

	public static function store_draft( $user_id, $draft ) {
		$token = wp_generate_password( 24, false, false );
		if ( ! set_transient( self::DRAFT_PREFIX . absint( $user_id ) . '_' . $token, $draft, self::TTL ) ) {
			throw new RuntimeException( 'Không lưu được bản xem trước email.' );
		}
		return $token;
	}

	public static function get_draft( $user_id, $token ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
		if ( strlen( $token ) !== 24 ) {
			return null;
		}
		$draft = get_transient( self::DRAFT_PREFIX . absint( $user_id ) . '_' . $token );
		return is_array( $draft ) ? $draft : null;
	}

	public static function delete_draft( $user_id, $token ) {
		$token = preg_replace( '/[^a-zA-Z0-9]/', '', (string) $token );
		if ( strlen( $token ) === 24 ) {
			delete_transient( self::DRAFT_PREFIX . absint( $user_id ) . '_' . $token );
		}
	}

	public static function build_html( $draft ) {
		$date_parts = explode( '-', (string) $draft['distribution_date'] );
		$date_label = sprintf( '%d/%d/%d', (int) $date_parts[2], (int) $date_parts[1], (int) $date_parts[0] );
		$period = esc_html( $draft['period'] );
		$rows = '';
		foreach ( $draft['schedule'] as $row ) {
			$rows .= '<tr><td style="border:1px solid #c9d2df;padding:10px 14px;white-space:nowrap">' . esc_html( $row['time'] ) . '</td>';
			$rows .= '<td style="border:1px solid #c9d2df;padding:10px 14px">' . esc_html( $row['department'] ) . '</td></tr>';
		}

		return '<div style="font-family:Arial,sans-serif;font-size:14px;line-height:1.6;color:#182230;max-width:760px">'
			. '<p><strong><em>Dear các anh/chị PIC,</em><br>CC các anh/chị quản lý,</strong></p>'
			. '<p>Em gửi thông tin lịch cấp phát đồng phục đợt ' . $period . '.<br>'
			. 'Anh/chị vui lòng sắp xếp <strong>thời gian</strong> lên nhận đồng phục đúng thời gian, tránh việc bộ phận lên không đúng giờ phải chờ.</p>'
			. '<p><strong>- Thời gian cấp: ngày ' . esc_html( $date_label ) . ', múi giờ cụ thể theo từng bộ phận như sau:</strong></p>'
			. '<table style="border-collapse:collapse;min-width:440px;max-width:100%"><thead><tr>'
			. '<th style="background:#0b376d;color:#fff;padding:10px 14px;text-align:left">Thời gian</th>'
			. '<th style="background:#0b376d;color:#fff;padding:10px 14px;text-align:left">Bộ phận</th>'
			. '</tr></thead><tbody>' . $rows . '</tbody></table>'
			. '<p>Em gửi dữ liệu cấp phát đồng phục đợt ' . $period . '. Anh/chị check <a href="' . esc_url( $draft['data_link'] ) . '">Tại đây</a> ạ.<br>'
			. 'Nếu có thắc mắc anh/chị vui lòng liên hệ lại em.</p>'
			. '</div>';
	}

	public static function send( $draft ) {
		if ( ! empty( $draft['errors'] ) || empty( $draft['to'] ) ) {
			return false;
		}
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $draft['cc'] ) ) {
			$headers[] = 'Cc: ' . implode( ', ', $draft['cc'] );
		}
		return wp_mail( $draft['to'], $draft['subject'], self::build_html( $draft ), $headers );
	}

	private static function parse_addresses( $value, $label, &$errors, $row_number = 0 ) {
		$addresses = array();
		foreach ( preg_split( '/[\s,;]+/u', trim( (string) $value ) ) as $candidate ) {
			if ( $candidate === '' ) {
				continue;
			}
			$email = sanitize_email( $candidate );
			if ( $email !== $candidate || ! is_email( $email ) ) {
				$errors[] = sprintf( '%s%s có email không hợp lệ: %s.', $label, $row_number ? ' dòng ' . $row_number : '', $candidate );
				continue;
			}
			$addresses[] = $email;
		}
		return $addresses;
	}

	private static function normalize_recipients( $to, $cc ) {
		$seen = array();
		$result = array( 'to' => array(), 'cc' => array() );
		foreach ( array( 'to' => $to, 'cc' => $cc ) as $group => $emails ) {
			foreach ( $emails as $email ) {
				$key = strtolower( $email );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$result[ $group ][] = $email;
			}
		}
		if ( count( $result['to'] ) + count( $result['cc'] ) > 300 ) {
			throw new RuntimeException( 'Danh sách vượt quá 300 người nhận. Hãy chia thành nhiều lượt gửi.' );
		}
		return $result;
	}
}
