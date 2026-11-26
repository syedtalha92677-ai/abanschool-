<?php
/* Abn School — front-end School Portal shortcode [abn_school_portal].
   Role-aware:
   - Admin / teacher: full staff portal (dashboard, students, fees,
     attendance, exams, timetable, homework, staff, ID cards, WhatsApp,
     announcements). Write actions need manage_options or abn_manage_school.
   - Parent: sees ONLY their own children (students linked to their user).
   - Student: sees ONLY their own record.
   Parents/students never see other children's names, fees or results. */
if (!defined('ABSPATH')) exit;

/** Attendance summary for one student over the last $days days. */
function abn_attendance_summary($student_id, $days = 30) {
    $from = date('Y-m-d', strtotime("-$days days"));
    $q = new WP_Query([
        'post_type' => 'abn_attendance', 'posts_per_page' => -1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [
            ['key' => '_abn_student_id', 'value' => (int) $student_id],
            ['key' => '_abn_date', 'value' => $from, 'compare' => '>=', 'type' => 'DATE'],
        ],
    ]);
    $counts = ['present' => 0, 'absent' => 0, 'leave' => 0];
    $rows = [];
    foreach ($q->posts as $pid) {
        $st = get_post_meta($pid, '_abn_status', true);
        if (isset($counts[$st])) $counts[$st]++;
        $rows[] = ['date' => get_post_meta($pid, '_abn_date', true), 'status' => $st];
    }
    usort($rows, function ($a, $b) { return strcmp($b['date'], $a['date']); });
    $total = array_sum($counts);
    $counts['total'] = $total;
    $counts['pct'] = $total ? round(($counts['present'] / $total) * 100) : 0;
    $counts['rows'] = array_slice($rows, 0, 30);
    return $counts;
}

/** Fee rows for a set of student IDs (optionally only pending). */
function abn_fees_for_students($student_ids, $only_pending = false) {
    $out = [];
    foreach (abn_get_fees() as $f) {
        $m = abn_fee_meta($f->ID);
        if (!in_array($m['student_id'], $student_ids, true)) continue;
        if ($only_pending && $m['status'] !== 'pending') continue;
        $out[] = [$f, $m];
    }
    return $out;
}

function abn_portal_staff_tabs() {
    return [
        'dashboard' => 'Dashboard', 'students' => 'Students', 'fees' => 'Fees',
        'attendance' => 'Attendance', 'exams' => 'Exams', 'timetable' => 'Timetable',
        'homework' => 'Homework', 'staff' => 'Staff', 'idcards' => 'ID Cards',
        'whatsapp' => 'WhatsApp', 'announcements' => 'Announcements',
    ];
}
function abn_portal_family_tabs() {
    return [
        'dashboard' => 'My Dashboard', 'attendance' => 'My Attendance', 'fees' => 'My Fees',
        'exams' => 'My Results', 'timetable' => 'My Timetable', 'homework' => 'My Homework',
        'announcements' => 'Announcements',
    ];
}

function abn_portal_handle_post($tab) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !abn_user_can_manage()) return '';
    if ($tab === 'fees' && isset($_POST['abn_collect']) && wp_verify_nonce(sanitize_key($_POST['abn_portal_nonce'] ?? ''), 'abn_portal')) {
        abn_mark_fee_paid((int) $_POST['fee_id']);
        return 'Fee marked as paid.';
    }
    if ($tab === 'attendance' && isset($_POST['abn_save_att']) && wp_verify_nonce(sanitize_key($_POST['abn_portal_nonce'] ?? ''), 'abn_portal')) {
        $date = sanitize_text_field($_POST['att_date'] ?? date('Y-m-d'));
        $n = abn_save_attendance($date, (array) ($_POST['status'] ?? []));
        return 'Attendance saved for ' . $date . ' (' . $n . ' students).';
    }
    return '';
}

function abn_portal_shortcode() {
    if (!is_user_logged_in()) {
        return '<div class="abn-portal"><p>Please <a href="' . esc_url(wp_login_url(get_permalink())) . '">log in</a> to use the School Portal.</p></div>';
    }
    $scope = abn_portal_scope();
    $is_staff = in_array($scope['type'], ['admin', 'teacher'], true);
    $tabs = $is_staff ? abn_portal_staff_tabs() : abn_portal_family_tabs();
    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard';
    if (!isset($tabs[$tab])) $tab = 'dashboard';
    $msg = abn_portal_handle_post($tab);
    $base = get_permalink();
    $out = '<div class="abn-portal">';
    if ($msg) $out .= '<div class="abn-alert">' . esc_html($msg) . '</div>';
    $out .= '<nav class="abn-portal-tabs">';
    foreach ($tabs as $slug => $label) {
        $out .= '<a href="' . esc_url(add_query_arg('tab', $slug, $base)) . '" class="' . ($tab === $slug ? 'active' : '') . '">' . esc_html($label) . '</a>';
    }
    $out .= '</nav><div class="abn-portal-body">';
    if ($is_staff) {
        switch ($tab) {
            case 'dashboard': $out .= abn_portal_dashboard(); break;
            case 'students': $out .= abn_portal_students(); break;
            case 'fees': $out .= abn_portal_fees(); break;
            case 'attendance': $out .= abn_portal_attendance(); break;
            case 'exams': $out .= abn_portal_exams(); break;
            case 'timetable': $out .= abn_portal_timetable(); break;
            case 'homework': $out .= abn_portal_homework(); break;
            case 'staff': $out .= abn_portal_staff(); break;
            case 'idcards': $out .= abn_portal_idcards(); break;
            case 'whatsapp': $out .= abn_portal_whatsapp(); break;
            case 'announcements': $out .= abn_portal_announcements(); break;
        }
    } else {
        switch ($tab) {
            case 'dashboard': $out .= abn_portal_my_dashboard($scope); break;
            case 'attendance': $out .= abn_portal_my_attendance($scope); break;
            case 'fees': $out .= abn_portal_my_fees($scope); break;
            case 'exams': $out .= abn_portal_my_exams($scope); break;
            case 'timetable': $out .= abn_portal_my_timetable($scope); break;
            case 'homework': $out .= abn_portal_my_homework($scope); break;
            case 'announcements': $out .= abn_portal_announcements(); break;
        }
    }
    $out .= '</div></div>';
    return $out;
}
add_shortcode('abn_school_portal', 'abn_portal_shortcode');

/* ================= Staff portal ================= */

function abn_portal_dashboard() {
    $total = count(abn_get_students());
    $totals = abn_fee_totals();
    $att = abn_attendance_today();
    $chart = abn_fee_chart();
    $max = max($chart) ?: 1;
    $html = '<div class="abn-kpis">';
    $kpis = [
        ['Total Students', $total],
        ['Fees Collected (' . date('M Y') . ')', abn_rs($totals['collected_month'])],
        ['Pending Fees', abn_rs($totals['pending']) . ' (' . $totals['pending_count'] . ' challans)'],
        ['Attendance Today', $att['marked'] ? $att['pct'] . '%' : 'Not marked yet'],
    ];
    foreach ($kpis as $k) $html .= '<div class="abn-card abn-kpi"><span class="abn-kpi-label">' . esc_html($k[0]) . '</span><strong>' . esc_html((string) $k[1]) . '</strong></div>';
    $html .= '</div><div class="abn-card"><h3>Fee Collection — Last 6 Months</h3><div class="abn-bars">';
    foreach ($chart as $label => $v) {
        $h = $max ? max(4, round(($v / $max) * 100)) : 4;
        $html .= '<div class="abn-bar-wrap"><div class="abn-bar" style="height:' . esc_attr($h) . '%" title="' . esc_attr(abn_rs($v)) . '"></div><span>' . esc_html($label) . '</span></div>';
    }
    $html .= '</div></div>';
    $html .= '<div class="abn-card"><h3>Pending Fees</h3><table class="abn-table"><tr><th>Student</th><th>Month</th><th>Amount</th></tr>';
    $n = 0;
    foreach (abn_get_fees() as $f) {
        $m = abn_fee_meta($f->ID);
        if ($m['status'] !== 'pending') continue;
        $html .= '<tr><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><td>' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td></tr>';
        if (++$n >= 8) break;
    }
    $html .= '</table></div>';
    return $html;
}

function abn_portal_students() {
    $scope = abn_portal_scope();
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    if ($scope['type'] === 'teacher' && $scope['classes']) {
        if ($class === '' || !in_array($class, $scope['classes'], true)) $class = $scope['classes'][0];
    }
    $rows = array_filter(abn_get_students(), function ($p) use ($search, $class) {
        $m = abn_student_meta($p->ID);
        if ($class !== '' && $m['class'] !== $class) return false;
        if ($search !== '' && stripos($p->post_title . ' ' . $m['father'], $search) === false) return false;
        return true;
    });
    $class_opts = ($scope['type'] === 'teacher' && $scope['classes']) ? $scope['classes'] : abn_classes();
    $html = '<div class="abn-card"><h3>Students (' . count($rows) . ')</h3><form method="get" class="abn-filter"><input type="hidden" name="tab" value="students">';
    $html .= '<input type="text" name="s" placeholder="Search name / father" value="' . esc_attr($search) . '"><select name="class"><option value="">All classes</option>';
    foreach ($class_opts as $c) $html .= '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>' . esc_html($c) . '</option>';
    $html .= '</select><button class="abn-btn small">Filter</button></form>';
    $html .= '<table class="abn-table"><tr><th>Name</th><th>Class</th><th>Roll</th><th>Father</th><th>Phone</th></tr>';
    foreach ($rows as $r) {
        $m = abn_student_meta($r->ID);
        $html .= '<tr><td>' . esc_html($r->post_title) . '</td><td>' . esc_html($m['class'] . '-' . $m['section']) . '</td><td>' . esc_html($m['roll']) . '</td><td>' . esc_html($m['father']) . '</td><td>' . esc_html($m['phone']) . '</td></tr>';
    }
    return $html . '</table></div>';
}

function abn_portal_fees() {
    $html = '<div class="abn-card"><h3>Fee Records</h3><table class="abn-table"><tr><th>Student</th><th>Month</th><th>Amount</th><th>Status</th><th>Actions</th></tr>';
    foreach (abn_get_fees() as $r) {
        $m = abn_fee_meta($r->ID);
        $pill = ($m['status'] === 'paid') ? 'paid' : 'pending';
        $html .= '<tr><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><td>' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td><td><span class="abn-pill-status ' . esc_attr($pill) . '">' . esc_html(ucfirst($m['status'])) . '</span></td><td>';
        $html .= '<a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url(home_url('/?abn_print=challan&fee=' . $r->ID)) . '">Challan</a> ';
        if ($m['status'] !== 'paid') {
            $html .= '<form method="post" style="display:inline">' . wp_nonce_field('abn_portal', 'abn_portal_nonce', true, false) . '<input type="hidden" name="fee_id" value="' . esc_attr($r->ID) . '"><button class="abn-btn small" name="abn_collect" value="1">Mark Paid</button></form>';
        }
        $html .= '</td></tr>';
    }
    return $html . '</table></div>';
}

function abn_portal_attendance() {
    $scope = abn_portal_scope();
    $date = isset($_GET['att_date']) ? sanitize_text_field($_GET['att_date']) : date('Y-m-d');
    $class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    if ($scope['type'] === 'teacher' && $scope['classes']) {
        if ($class === '' || !in_array($class, $scope['classes'], true)) $class = $scope['classes'][0];
    }
    $rows = array_filter(abn_get_students(), function ($p) use ($class) {
        return $class === '' || abn_student_meta($p->ID)['class'] === $class;
    });
    $saved = abn_attendance_for_date($date);
    $class_opts = ($scope['type'] === 'teacher' && $scope['classes']) ? $scope['classes'] : abn_classes();
    $html = '<div class="abn-card"><h3>Attendance — ' . esc_html($date) . '</h3>';
    $html .= '<form method="get" class="abn-filter"><input type="hidden" name="tab" value="attendance"><input type="date" name="att_date" value="' . esc_attr($date) . '"><select name="class"><option value="">All classes</option>';
    foreach ($class_opts as $c) $html .= '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>' . esc_html($c) . '</option>';
    $html .= '</select><button class="abn-btn small">Show</button></form>';
    $html .= '<form method="post"><input type="hidden" name="att_date" value="' . esc_attr($date) . '">' . wp_nonce_field('abn_portal', 'abn_portal_nonce', true, false);
    $html .= '<table class="abn-table"><tr><th>Student</th><th>Class</th><th>Status</th></tr>';
    foreach ($rows as $r) {
        $m = abn_student_meta($r->ID);
        $st = $saved[$r->ID] ?? 'present';
        $html .= '<tr><td>' . esc_html($r->post_title) . '</td><td>' . esc_html($m['class'] . '-' . $m['section']) . '</td><td><select name="status[' . esc_attr($r->ID) . ']">';
        foreach (['present' => 'Present', 'absent' => 'Absent', 'leave' => 'Leave'] as $v => $l) $html .= '<option value="' . esc_attr($v) . '"' . selected($st, $v, false) . '>' . esc_html($l) . '</option>';
        $html .= '</select></td></tr>';
    }
    return $html . '</table><button class="abn-btn" name="abn_save_att" value="1" style="margin-top:14px">Save Attendance</button></form></div>';
}

function abn_portal_exams() {
    $html = '';
    foreach (abn_get_exams() as $e) {
        $html .= '<div class="abn-card"><h3>' . esc_html($e->post_title) . ' <span class="abn-note">(' . esc_html(get_post_meta($e->ID, '_abn_class', true)) . ' · ' . esc_html(get_post_meta($e->ID, '_abn_date', true)) . ')</span></h3>';
        $html .= '<table class="abn-table"><tr><th>Student</th><th>Class</th><th>Total /500</th><th>Grade</th><th></th></tr>';
        $seen = [];
        foreach (abn_marks_for_exam($e->ID) as $mk) {
            $sid = (int) get_post_meta($mk->ID, '_abn_student_id', true);
            $seen[$sid] = ($seen[$sid] ?? 0) + (int) get_post_meta($mk->ID, '_abn_marks', true);
        }
        foreach ($seen as $sid => $total) {
            $m = abn_student_meta($sid);
            $pct = round(($total / 500) * 100);
            $grade = $pct >= 80 ? 'A+' : ($pct >= 70 ? 'A' : ($pct >= 60 ? 'B' : ($pct >= 50 ? 'C' : 'D')));
            $html .= '<tr><td>' . esc_html(abn_student_name($sid)) . '</td><td>' . esc_html($m['class'] . '-' . $m['section']) . '</td><td>' . esc_html($total) . '</td><td>' . esc_html($grade) . '</td><td><a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url(home_url('/?abn_print=report&exam=' . $e->ID . '&student=' . $sid)) . '">Report Card</a></td></tr>';
        }
        $html .= '</table></div>';
    }
    return $html ?: '<div class="abn-card"><p class="abn-note">No exams yet.</p></div>';
}

function abn_portal_timetable() {
    $scope = abn_portal_scope();
    $class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    if ($scope['type'] === 'teacher' && $scope['classes']) {
        if ($class === '' || !in_array($class, $scope['classes'], true)) $class = $scope['classes'][0];
    }
    if ($class === '') $class = '6';
    $tt = abn_timetable();
    $class_opts = ($scope['type'] === 'teacher' && $scope['classes']) ? $scope['classes'] : abn_classes();
    $html = '<div class="abn-card"><h3>Weekly Timetable</h3><form method="get" class="abn-filter"><input type="hidden" name="tab" value="timetable"><select name="class">';
    foreach ($class_opts as $c) $html .= '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>Class ' . esc_html($c) . '</option>';
    $html .= '</select><button class="abn-btn small">Show</button></form>';
    $html .= '<table class="abn-table"><tr><th>Day</th>';
    foreach (abn_timetable_periods() as $p) $html .= '<th>' . esc_html($p) . '</th>';
    $html .= '</tr>';
    foreach (abn_timetable_days() as $day) {
        $html .= '<tr><td><strong>' . esc_html($day) . '</strong></td>';
        foreach (abn_timetable_periods() as $pi => $p) $html .= '<td>' . esc_html($tt[$class][$day][$pi] ?? '—') . '</td>';
        $html .= '</tr>';
    }
    return $html . '</table></div>';
}

function abn_portal_homework() {
    $scope = abn_portal_scope();
    $class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    if ($scope['type'] === 'teacher' && $scope['classes']) {
        if ($class !== '' && !in_array($class, $scope['classes'], true)) $class = $scope['classes'][0];
    }
    $class_opts = ($scope['type'] === 'teacher' && $scope['classes']) ? $scope['classes'] : abn_classes();
    $html = '<div class="abn-card"><h3>Homework Diary</h3><form method="get" class="abn-filter"><input type="hidden" name="tab" value="homework"><select name="class"><option value="">All classes</option>';
    foreach ($class_opts as $c) $html .= '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>' . esc_html($c) . '</option>';
    $html .= '</select><button class="abn-btn small">Filter</button></form>';
    $html .= '<table class="abn-table"><tr><th>Class</th><th>Subject</th><th>Homework</th><th>Due Date</th></tr>';
    foreach (abn_get_homework($class) as $r) {
        $html .= '<tr><td>' . esc_html(get_post_meta($r->ID, '_abn_class', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_subject', true)) . '</td><td>' . esc_html($r->post_title) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_date', true)) . '</td></tr>';
    }
    return $html . '</table></div>';
}

function abn_portal_staff() {
    $html = '<div class="abn-card"><h3>Staff</h3><table class="abn-table"><tr><th>Name</th><th>Role</th><th>Subject / Dept</th><th>Phone</th><th>Email</th></tr>';
    foreach (abn_get_staff() as $r) {
        $html .= '<tr><td>' . esc_html($r->post_title) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_role', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_subject', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_phone', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_email', true)) . '</td></tr>';
    }
    return $html . '</table></div>';
}

function abn_portal_idcards() {
    $class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    $students = array_filter(abn_get_students(), function ($p) use ($class) {
        return $class === '' || abn_student_meta($p->ID)['class'] === $class;
    });
    $html = '<div class="abn-card"><h3>ID Cards</h3><form method="get" class="abn-filter"><input type="hidden" name="tab" value="idcards"><select name="class"><option value="">All classes</option>';
    foreach (abn_classes() as $c) $html .= '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>' . esc_html($c) . '</option>';
    $html .= '</select><button class="abn-btn small">Filter</button></form><div class="abn-idgrid">';
    foreach ($students as $s) {
        $m = abn_student_meta($s->ID);
        $html .= '<div class="abn-idcard"><div class="abn-idcard-head">' . esc_html(abn_school_name()) . '</div><div class="abn-idcard-photo">Photo</div><div class="abn-idcard-name">' . esc_html($s->post_title) . '</div><div class="abn-idcard-row">Class: ' . esc_html($m['class'] . '-' . $m['section']) . ' · Roll: ' . esc_html($m['roll']) . '</div><div class="abn-idcard-row">Adm No: ' . esc_html($m['admission_no']) . '</div><a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url(home_url('/?abn_print=idcard&student=' . $s->ID)) . '">Print</a></div>';
    }
    return $html . '</div></div>';
}

function abn_portal_whatsapp() {
    $html = '<div class="abn-card"><h3>WhatsApp Fee Reminders</h3>';
    if (!abn_has_real_whatsapp()) {
        $html .= '<p class="abn-note">Add the school WhatsApp number in Abn School → Settings to send reminders.</p>';
    }
    $html .= '<table class="abn-table"><tr><th>Student</th><th>Month</th><th>Amount</th><th></th></tr>';
    foreach (abn_get_fees() as $f) {
        $m = abn_fee_meta($f->ID);
        if ($m['status'] !== 'pending') continue;
        $sm = abn_student_meta($m['student_id']);
        $msg = 'Assalam-o-Alaikum, ' . abn_student_name($m['student_id']) . ' ki ' . $m['month'] . ' fee ' . abn_rs($m['amount']) . ' pending hai. — ' . abn_school_name();
        $link = abn_has_real_whatsapp() ? abn_wa_link($sm['phone'], $msg) : '#';
        $html .= '<tr><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><td>' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td><td><a class="abn-btn small orange" target="_blank" rel="noopener" href="' . esc_url($link) . '">Send WhatsApp</a></td></tr>';
    }
    return $html . '</table></div>';
}

function abn_portal_announcements() {
    $notices = abn_get_notices(10);
    if (!$notices) return '<div class="abn-card"><p class="abn-note">No announcements yet.</p></div>';
    $html = '<div class="abn-card"><h3>Notices &amp; Announcements</h3>';
    foreach ($notices as $nt) {
        $html .= '<article class="abn-notice"><h3>' . esc_html($nt->post_title) . '</h3><p class="abn-notice-date">' . esc_html(get_the_date('', $nt->ID)) . '</p><p>' . wp_kses_post($nt->post_content) . '</p></article>';
    }
    return $html . '</div>';
}

/* ================= Parent / student portal (own records only) ================= */

function abn_portal_my_dashboard($scope) {
    $ids = $scope['student_ids'];
    if (!$ids) return '<div class="abn-card"><p class="abn-note">No student record is linked to your account yet. Please ask the school office to link your parent/student account to a student.</p></div>';
    $html = '<div class="abn-kpis">';
    foreach ($ids as $sid) {
        $m = abn_student_meta($sid);
        $att = abn_attendance_summary($sid);
        $pending = abn_fees_for_students([$sid], true);
        $pend_total = 0;
        foreach ($pending as $row) $pend_total += $row[1]['amount'];
        $html .= '<div class="abn-card abn-kpi"><span class="abn-kpi-label">' . esc_html(abn_student_name($sid)) . ' — Class ' . esc_html($m['class'] . '-' . $m['section']) . '</span>'
            . '<strong>Attendance ' . esc_html($att['pct']) . '%</strong>'
            . '<small>Last 30 days: ' . esc_html($att['present']) . ' present · ' . esc_html($att['absent']) . ' absent · ' . esc_html($att['leave']) . ' leave</small>'
            . '<small>Pending fees: <strong>' . esc_html(abn_rs($pend_total)) . '</strong> (' . count($pending) . ' challan' . (count($pending) === 1 ? '' : 's') . ')</small></div>';
    }
    $html .= '</div>';
    $html .= '<div class="abn-card"><h3>Latest Announcements</h3>';
    $notices = abn_get_notices(3);
    if ($notices) {
        foreach ($notices as $nt) $html .= '<p><strong>' . esc_html($nt->post_title) . '</strong> <span class="abn-note">(' . esc_html(get_the_date('', $nt->ID)) . ')</span><br>' . esc_html(wp_trim_words(strip_tags($nt->post_content), 25)) . '</p>';
    } else {
        $html .= '<p class="abn-note">No announcements yet.</p>';
    }
    return $html . '</div>';
}

function abn_portal_my_attendance($scope) {
    $ids = $scope['student_ids'];
    if (!$ids) return '<div class="abn-card"><p class="abn-note">No student record is linked to your account yet.</p></div>';
    $html = '';
    foreach ($ids as $sid) {
        $m = abn_student_meta($sid);
        $att = abn_attendance_summary($sid);
        $html .= '<div class="abn-card"><h3>' . esc_html(abn_student_name($sid)) . ' — Class ' . esc_html($m['class'] . '-' . $m['section']) . ' <span class="abn-note">(' . esc_html($att['pct']) . '% present, last 30 days)</span></h3>';
        $html .= '<table class="abn-table"><tr><th>Date</th><th>Status</th></tr>';
        if (!$att['rows']) $html .= '<tr><td colspan="2">No attendance recorded in the last 30 days.</td></tr>';
        foreach ($att['rows'] as $row) {
            $html .= '<tr><td>' . esc_html($row['date']) . '</td><td>' . esc_html(ucfirst($row['status'])) . '</td></tr>';
        }
        $html .= '</table></div>';
    }
    return $html;
}

function abn_portal_my_fees($scope) {
    $ids = $scope['student_ids'];
    if (!$ids) return '<div class="abn-card"><p class="abn-note">No student record is linked to your account yet.</p></div>';
    $rows = abn_fees_for_students($ids);
    $html = '<div class="abn-card"><h3>My Fees</h3><table class="abn-table"><tr><th>Student</th><th>Month</th><th>Amount</th><th>Status</th><th>Paid Date</th><th></th></tr>';
    foreach ($rows as $row) {
        list($f, $m) = $row;
        $pill = ($m['status'] === 'paid') ? 'paid' : 'pending';
        $html .= '<tr><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><td>' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td><td><span class="abn-pill-status ' . esc_attr($pill) . '">' . esc_html(ucfirst($m['status'])) . '</span></td><td>' . esc_html($m['paid_date'] ?: '—') . '</td><td><a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url(home_url('/?abn_print=challan&fee=' . $f->ID)) . '">Challan / Receipt</a></td></tr>';
    }
    return $html . '</table></div>';
}

function abn_portal_my_exams($scope) {
    $ids = $scope['student_ids'];
    if (!$ids) return '<div class="abn-card"><p class="abn-note">No student record is linked to your account yet.</p></div>';
    $html = '';
    foreach (abn_get_exams() as $e) {
        $seen = [];
        foreach (abn_marks_for_exam($e->ID) as $mk) {
            $sid = (int) get_post_meta($mk->ID, '_abn_student_id', true);
            if (!in_array($sid, $ids, true)) continue;
            $seen[$sid] = ($seen[$sid] ?? 0) + (int) get_post_meta($mk->ID, '_abn_marks', true);
        }
        if (!$seen) continue;
        $html .= '<div class="abn-card"><h3>' . esc_html($e->post_title) . ' <span class="abn-note">(' . esc_html(get_post_meta($e->ID, '_abn_class', true)) . ' · ' . esc_html(get_post_meta($e->ID, '_abn_date', true)) . ')</span></h3>';
        $html .= '<table class="abn-table"><tr><th>Student</th><th>Total /500</th><th>Grade</th><th></th></tr>';
        foreach ($seen as $sid => $total) {
            $pct = round(($total / 500) * 100);
            $grade = $pct >= 80 ? 'A+' : ($pct >= 70 ? 'A' : ($pct >= 60 ? 'B' : ($pct >= 50 ? 'C' : 'D')));
            $html .= '<tr><td>' . esc_html(abn_student_name($sid)) . '</td><td>' . esc_html($total) . '</td><td>' . esc_html($grade) . '</td><td><a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url(home_url('/?abn_print=report&exam=' . $e->ID . '&student=' . $sid)) . '">Report Card</a></td></tr>';
        }
        $html .= '</table></div>';
    }
    return $html ?: '<div class="abn-card"><p class="abn-note">No exam results for your children yet.</p></div>';
}

function abn_portal_my_timetable($scope) {
    $ids = $scope['student_ids'];
    if (!$ids) return '<div class="abn-card"><p class="abn-note">No student record is linked to your account yet.</p></div>';
    $html = '';
    $tt = abn_timetable();
    foreach ($ids as $sid) {
        $m = abn_student_meta($sid);
        $class = $m['class'];
        $html .= '<div class="abn-card"><h3>' . esc_html(abn_student_name($sid)) . ' — Class ' . esc_html($class . '-' . $m['section']) . ' Timetable</h3>';
        $html .= '<table class="abn-table"><tr><th>Day</th>';
        foreach (abn_timetable_periods() as $p) $html .= '<th>' . esc_html($p) . '</th>';
        $html .= '</tr>';
        foreach (abn_timetable_days() as $day) {
            $html .= '<tr><td><strong>' . esc_html($day) . '</strong></td>';
            foreach (abn_timetable_periods() as $pi => $p) $html .= '<td>' . esc_html($tt[$class][$day][$pi] ?? '—') . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table></div>';
    }
    return $html;
}

function abn_portal_my_homework($scope) {
    $ids = $scope['student_ids'];
    if (!$ids) return '<div class="abn-card"><p class="abn-note">No student record is linked to your account yet.</p></div>';
    $classes = [];
    foreach ($ids as $sid) {
        $c = abn_student_meta($sid)['class'];
        if ($c !== '') $classes[] = $c;
    }
    $classes = array_unique($classes);
    $html = '<div class="abn-card"><h3>My Homework</h3><table class="abn-table"><tr><th>Class</th><th>Subject</th><th>Homework</th><th>Due Date</th></tr>';
    $found = false;
    foreach ($classes as $c) {
        foreach (abn_get_homework($c) as $r) {
            $found = true;
            $html .= '<tr><td>' . esc_html($c) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_subject', true)) . '</td><td>' . esc_html($r->post_title) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_date', true)) . '</td></tr>';
        }
    }
    if (!$found) $html .= '<tr><td colspan="4">No homework posted for your class yet.</td></tr>';
    return $html . '</table></div>';
}
