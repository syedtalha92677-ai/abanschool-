<?php
/* Abn School — helpers: options, branding, formatting, data queries.
   All data lives in custom post types + postmeta (prefix abn_). */
if (!defined('ABSPATH')) exit;

/* ---------- Options ---------- */
function abn_opt($key, $default = '') {
    $opts = get_option('abn_settings', []);
    return isset($opts[$key]) ? $opts[$key] : $default;
}
function abn_defaults() {
    /* Contact details are PLACEHOLDERS on purpose — the real phone, email
       and address must be entered in Abn School → Settings. Nothing here
       is a real contact detail. Fee defaults are DEMO values to confirm. */
    return [
        'school_name'   => 'Abn School',
        'logo_id'       => 0,
        'phone'         => '[Insert Phone Number]',
        'whatsapp'      => '[Insert Phone Number]',
        'email1'        => '[Insert Email]',
        'email2'        => '',
        'address'       => '[Insert Address]',
        'academic_year' => '2026–27',
        'fee_nursery'   => 2000,
        'fee_primary'   => 2500,
        'fee_middle'    => 3500,
        'fee_matric'    => 4500,
    ];
}
function abn_school_name() { return abn_opt('school_name', 'Abn School'); }
function abn_phone()       { return abn_opt('phone', '[Insert Phone Number]'); }
function abn_whatsapp()    { return abn_opt('whatsapp', '[Insert Phone Number]'); }
function abn_email1()      { return abn_opt('email1', '[Insert Email]'); }
function abn_email2()      { return abn_opt('email2', ''); }
function abn_address()     { return abn_opt('address', '[Insert Address]'); }
function abn_academic_year() { return abn_opt('academic_year', '2026–27'); }
/** True only when a real phone number has been entered (not the placeholder). */
function abn_has_real_phone() {
    $p = trim((string) abn_phone());
    return $p !== '' && strpos($p, '[') === false;
}
function abn_has_real_whatsapp() {
    $p = trim((string) abn_whatsapp());
    return $p !== '' && strpos($p, '[') === false;
}
/** Logo URL from Settings (media library) or the WordPress custom logo. */
function abn_logo_url() {
    $id = (int) abn_opt('logo_id', 0);
    if ($id) {
        $u = wp_get_attachment_image_url($id, 'full');
        if ($u) return $u;
    }
    $custom = get_theme_mod('custom_logo');
    if ($custom) {
        $u = wp_get_attachment_image_url($custom, 'full');
        if ($u) return $u;
    }
    return '';
}
function abn_subjects() { return ['English', 'Mathematics', 'Science', 'Urdu', 'Islamiat']; }
/** Latest published notices (for the public Announcements page + portal). */
function abn_get_notices($number = 10) {
    $q = new WP_Query(['post_type' => 'abn_notice', 'posts_per_page' => (int) $number, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
    return $q->posts;
}
/** Classes with their demo monthly fee (for the Classes page). */
function abn_classes_with_fees() {
    $out = [];
    foreach (abn_classes() as $c) $out[] = ['class' => $c, 'fee' => abn_fee_for_class($c)];
    return $out;
}

/** Monthly fee default for a class label (Nursery/KG/1-10). Demo defaults, changeable in Settings. */
function abn_fee_for_class($class) {
    $c = trim((string) $class);
    if ($c === 'Nursery' || $c === 'KG') return (int) abn_opt('fee_nursery', 2000);
    if (is_numeric($c) && (int) $c >= 1 && (int) $c <= 5) return (int) abn_opt('fee_primary', 2500);
    if (is_numeric($c) && (int) $c >= 6 && (int) $c <= 8) return (int) abn_opt('fee_middle', 3500);
    return (int) abn_opt('fee_matric', 4500);
}

function abn_rs($n) { return 'Rs. ' . number_format((int) $n); }
function abn_digits($phone) { return preg_replace('/\D/', '', (string) $phone); }
/** wa.me link (Pakistan numbers: local 03xx -> 92 3xx). */
function abn_wa_link($phone, $message) {
    $d = abn_digits($phone);
    if (strpos($d, '0') === 0) $d = '92' . substr($d, 1);
    return 'https://wa.me/' . $d . '?text=' . rawurlencode($message);
}
function abn_tel_link($phone) { return 'tel:+' . abn_digits($phone); }

/* ---------- Classes ---------- */
function abn_classes() {
    return ['Nursery', 'KG', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10'];
}

/* ---------- Students ---------- */
function abn_get_students($args = []) {
    $q = new WP_Query(array_merge([
        'post_type'      => 'abn_student',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'publish',
    ], $args));
    return $q->posts;
}
function abn_student_meta($id) {
    return [
        'father'       => get_post_meta($id, '_abn_father', true),
        'class'        => get_post_meta($id, '_abn_class', true),
        'section'      => get_post_meta($id, '_abn_section', true),
        'roll'         => get_post_meta($id, '_abn_roll', true),
        'admission_no' => get_post_meta($id, '_abn_admission_no', true),
        'phone'        => get_post_meta($id, '_abn_phone', true),
        'address'      => get_post_meta($id, '_abn_address', true),
        'status'       => get_post_meta($id, '_abn_status', true) ?: 'active',
        'admission_date' => get_post_meta($id, '_abn_admission_date', true),
        'parent_user_id' => (int) get_post_meta($id, '_abn_parent_user_id', true),
        'student_user_id' => (int) get_post_meta($id, '_abn_student_user_id', true),
    ];
}
function abn_student_name($id) {
    $p = get_post($id);
    return $p ? $p->post_title : '—';
}

/* ---------- Fees ---------- */
function abn_get_fees($args = []) {
    $q = new WP_Query(array_merge([
        'post_type'      => 'abn_fee',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ], $args));
    return $q->posts;
}
function abn_fee_meta($id) {
    return [
        'student_id'    => (int) get_post_meta($id, '_abn_student_id', true),
        'month'         => get_post_meta($id, '_abn_month', true),
        'amount'        => (int) get_post_meta($id, '_abn_amount', true),
        'status'        => get_post_meta($id, '_abn_status', true) ?: 'pending',
        'paid_date'     => get_post_meta($id, '_abn_paid_date', true),
    ];
}
function abn_create_fee($student_id, $month, $amount) {
    $id = wp_insert_post([
        'post_type'   => 'abn_fee',
        'post_title'  => 'Fee — ' . abn_student_name($student_id) . ' (' . $month . ')',
        'post_status' => 'publish',
    ]);
    if (is_wp_error($id) || !$id) return 0;
    update_post_meta($id, '_abn_student_id', (int) $student_id);
    update_post_meta($id, '_abn_month', $month);
    update_post_meta($id, '_abn_amount', (int) $amount);
    update_post_meta($id, '_abn_status', 'pending');
    return $id;
}
function abn_mark_fee_paid($fee_id) {
    if (abn_fee_meta($fee_id)['status'] === 'paid') return false;
    update_post_meta($fee_id, '_abn_status', 'paid');
    update_post_meta($fee_id, '_abn_paid_date', date('Y-m-d'));
    return true;
}
function abn_fee_totals() {
    $month = date('F Y');
    $collected = 0; $pending = 0; $pending_count = 0; $collected_month = 0;
    foreach (abn_get_fees() as $f) {
        $m = abn_fee_meta($f->ID);
        if ($m['status'] === 'paid') {
            $collected += $m['amount'];
            if ($m['month'] === $month) $collected_month += $m['amount'];
        } else {
            $pending += $m['amount'];
            $pending_count++;
        }
    }
    return ['collected' => $collected, 'collected_month' => $collected_month, 'pending' => $pending, 'pending_count' => $pending_count];
}
/** Collected amount per month for the last 6 months (real data, for the chart). */
function abn_fee_chart() {
    $out = [];
    for ($i = 5; $i >= 0; $i--) {
        $label = date('M Y', strtotime("-$i months"));
        $out[$label] = 0;
    }
    foreach (abn_get_fees() as $f) {
        $m = abn_fee_meta($f->ID);
        if ($m['status'] !== 'paid') continue;
        $key = date('M Y', strtotime($m['month'] . ' 1'));
        if (isset($out[$key])) $out[$key] += $m['amount'];
    }
    return $out;
}

/* ---------- Attendance ---------- */
function abn_attendance_for_date($date) {
    $q = new WP_Query([
        'post_type' => 'abn_attendance', 'posts_per_page' => -1, 'post_status' => 'publish',
        'meta_query' => [['key' => '_abn_date', 'value' => $date]],
    ]);
    $out = [];
    foreach ($q->posts as $p) {
        $out[(int) get_post_meta($p->ID, '_abn_student_id', true)] = get_post_meta($p->ID, '_abn_status', true);
    }
    return $out;
}
function abn_attendance_today() {
    $students = abn_get_students();
    $total = count($students);
    $saved = abn_attendance_for_date(date('Y-m-d'));
    $present = 0;
    foreach ($saved as $st) if ($st === 'present') $present++;
    $pct = $total ? round(($present / $total) * 100) : 0;
    return ['total' => $total, 'present' => $present, 'marked' => count($saved), 'pct' => $pct];
}
function abn_save_attendance($date, $statuses) {
    // Remove existing records for the date, then insert the new set.
    $q = new WP_Query([
        'post_type' => 'abn_attendance', 'posts_per_page' => -1, 'post_status' => 'publish',
        'meta_query' => [['key' => '_abn_date', 'value' => $date]], 'fields' => 'ids',
    ]);
    foreach ($q->posts as $pid) wp_delete_post($pid, true);
    $count = 0;
    foreach ((array) $statuses as $sid => $st) {
        $st = in_array($st, ['present', 'absent', 'leave'], true) ? $st : 'present';
        $id = wp_insert_post([
            'post_type'  => 'abn_attendance',
            'post_title' => 'Attendance — ' . abn_student_name((int) $sid) . ' — ' . $date,
            'post_status' => 'publish',
        ]);
        if ($id && !is_wp_error($id)) {
            update_post_meta($id, '_abn_student_id', (int) $sid);
            update_post_meta($id, '_abn_date', $date);
            update_post_meta($id, '_abn_status', $st);
            $count++;
        }
    }
    return $count;
}

/* ---------- Exams & marks ---------- */
function abn_get_exams() {
    $q = new WP_Query(['post_type' => 'abn_exam', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
    return $q->posts;
}
function abn_marks_for_exam($exam_id) {
    $q = new WP_Query([
        'post_type' => 'abn_mark', 'posts_per_page' => -1, 'post_status' => 'publish',
        'meta_query' => [['key' => '_abn_exam_id', 'value' => (int) $exam_id]],
    ]);
    return $q->posts;
}

/* ---------- Staff / homework / timetable ---------- */
function abn_get_staff() {
    $q = new WP_Query(['post_type' => 'abn_staff', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'title', 'order' => 'ASC']);
    return $q->posts;
}
function abn_get_homework($class = '') {
    $args = ['post_type' => 'abn_homework', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC'];
    if ($class !== '') $args['meta_query'] = [['key' => '_abn_class', 'value' => $class]];
    $q = new WP_Query($args);
    return $q->posts;
}
function abn_timetable() {
    $tt = get_option('abn_timetable', []);
    return is_array($tt) ? $tt : [];
}
function abn_timetable_days() { return ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']; }
function abn_timetable_periods() { return ['Period 1', 'Period 2', 'Period 3', 'Period 4', 'Period 5', 'Period 6']; }
