<?php
/* Abn School — printable output: fee challan, report card, ID cards.
   Reached via ?abn_print=challan|report|idcard|idcards (login required).
   Parents/students may print ONLY their own (linked) children's documents;
   teachers only their assigned classes; bulk ID cards are staff-only. */
if (!defined('ABSPATH')) exit;

function abn_print_head($title) {
    $css = '
    body{font-family:Arial,Helvetica,sans-serif;color:#1F2937;margin:0;padding:24px;background:#fff}
    .abn-print-head{border-bottom:3px solid #0C3B2E;padding-bottom:12px;margin-bottom:18px;display:flex;justify-content:space-between}
    .abn-print-head h1{margin:0;font-size:24px;color:#0C3B2E}
    .abn-print-head p{margin:2px 0;color:#5B6B62;font-size:13px}
    table{width:100%;border-collapse:collapse;margin:14px 0}
    th,td{border:1px solid #ccc;padding:8px 10px;text-align:left;font-size:14px}
    th{background:#FAF6EE}
    .abn-print-btn{background:#C9A227;color:#0C3B2E;border:none;border-radius:999px;padding:10px 22px;font-weight:700;cursor:pointer;margin-bottom:16px}
    .abn-idcard{width:320px;border:2px solid #0C3B2E;border-radius:10px;overflow:hidden;margin:10px;display:inline-block;vertical-align:top}
    .abn-idcard-head{background:#0C3B2E;color:#fff;text-align:center;padding:10px;font-weight:700}
    .abn-idcard-photo{width:90px;height:110px;border:2px solid #C9A227;margin:12px auto;text-align:center;line-height:110px;color:#5B6B62;background:#FAF6EE}
    .abn-idcard-name{text-align:center;font-weight:700;font-size:17px}
    .abn-idcard-row{text-align:center;font-size:13px;color:#5B6B62;padding:3px}
    .abn-idcard-foot{background:#C9A227;color:#0C3B2E;text-align:center;font-size:12px;padding:6px;font-weight:700}
    .abn-stamp{margin-top:26px;display:flex;justify-content:space-between;font-size:13px}
    @media print{.abn-print-btn{display:none}body{padding:0}}';
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html($title) . ' — ' . esc_html(abn_school_name()) . '</title><style>' . $css . '</style></head><body>';
    echo '<button class="abn-print-btn" onclick="window.print()">Print</button>';
    echo '<div class="abn-print-head"><div><h1>' . esc_html(abn_school_name()) . '</h1>';
    if (abn_has_real_phone()) echo '<p>Phone: ' . esc_html(abn_phone()) . ' · WhatsApp: ' . esc_html(abn_whatsapp()) . '</p>';
    if (strpos(abn_email1(), '[') === false && abn_email1() !== '') echo '<p>' . esc_html(abn_email1()) . (abn_email2() ? ' · ' . esc_html(abn_email2()) : '') . '</p>';
    if (strpos(abn_address(), '[') === false && abn_address() !== '') echo '<p>' . esc_html(abn_address()) . '</p>';
    echo '</div><div><p>Session ' . esc_html(abn_academic_year()) . '</p><p>Date: ' . esc_html(date('d F Y')) . '</p></div></div>';
}

function abn_print_footer() { echo '</body></html>'; }

/** May the current user print documents for this student? */
function abn_print_allowed($student_id) {
    if (current_user_can('manage_options')) return true;
    if (current_user_can('abn_manage_school')) {
        $classes = abn_teacher_classes(get_current_user_id());
        if (!$classes) return false;
        return in_array(abn_student_meta((int) $student_id)['class'], $classes, true);
    }
    $scope = abn_portal_scope();
    return in_array($scope['type'], ['parent', 'student'], true) && in_array((int) $student_id, $scope['student_ids'], true);
}

function abn_print_challan($fee_id) {
    $m = abn_fee_meta($fee_id);
    if (!$m['student_id'] || !abn_print_allowed($m['student_id'])) return false;
    $sm = abn_student_meta($m['student_id']);
    abn_print_head('Fee Challan — ' . abn_student_name($m['student_id']));
    echo '<h2>Fee Challan — ' . esc_html($m['month']) . '</h2>';
    echo '<table><tr><th>Student</th><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><th>Admission No</th><td>' . esc_html($sm['admission_no']) . '</td></tr>';
    echo '<tr><th>Class</th><td>' . esc_html($sm['class'] . '-' . $sm['section']) . '</td><th>Roll No</th><td>' . esc_html($sm['roll']) . '</td></tr>';
    echo '<tr><th>Father</th><td>' . esc_html($sm['father']) . '</td><th>Phone</th><td>' . esc_html($sm['phone']) . '</td></tr></table>';
    echo '<table><tr><th>Description</th><th>Amount</th></tr><tr><td>Monthly Fee — ' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td></tr><tr><th>Total Payable</th><th>' . esc_html(abn_rs($m['amount'])) . '</th></tr></table>';
    echo '<p>Status: <strong>' . esc_html(ucfirst($m['status'])) . '</strong>' . ($m['paid_date'] ? ' (paid on ' . esc_html($m['paid_date']) . ')' : '') . '</p>';
    echo '<div class="abn-stamp"><span>Parent Signature: ______________</span><span>Office Signature: ______________</span></div>';
    abn_print_footer();
    return true;
}

function abn_print_report($exam_id, $student_id) {
    $exam = get_post($exam_id);
    if (!$exam || !abn_print_allowed($student_id)) return false;
    $sm = abn_student_meta($student_id);
    $marks = [];
    foreach (abn_marks_for_exam($exam_id) as $mk) {
        if ((int) get_post_meta($mk->ID, '_abn_student_id', true) !== (int) $student_id) continue;
        $marks[get_post_meta($mk->ID, '_abn_subject', true)] = [
            'marks' => (int) get_post_meta($mk->ID, '_abn_marks', true),
            'total' => (int) get_post_meta($mk->ID, '_abn_total', true),
        ];
    }
    abn_print_head('Report Card — ' . abn_student_name($student_id));
    echo '<h2>Report Card — ' . esc_html($exam->post_title) . '</h2>';
    echo '<table><tr><th>Student</th><td>' . esc_html(abn_student_name($student_id)) . '</td><th>Admission No</th><td>' . esc_html($sm['admission_no']) . '</td></tr>';
    echo '<tr><th>Class</th><td>' . esc_html($sm['class'] . '-' . $sm['section']) . '</td><th>Roll No</th><td>' . esc_html($sm['roll']) . '</td></tr></table>';
    echo '<table><tr><th>Subject</th><th>Marks Obtained</th><th>Total Marks</th><th>Grade</th></tr>';
    $obt = 0; $tot = 0;
    foreach ($marks as $subj => $mk) {
        $pct = $mk['total'] ? round(($mk['marks'] / $mk['total']) * 100) : 0;
        $grade = $pct >= 80 ? 'A+' : ($pct >= 70 ? 'A' : ($pct >= 60 ? 'B' : ($pct >= 50 ? 'C' : 'D')));
        echo '<tr><td>' . esc_html($subj) . '</td><td>' . esc_html($mk['marks']) . '</td><td>' . esc_html($mk['total']) . '</td><td>' . esc_html($grade) . '</td></tr>';
        $obt += $mk['marks']; $tot += $mk['total'];
    }
    $pct = $tot ? round(($obt / $tot) * 100) : 0;
    echo '<tr><th>Total</th><th>' . esc_html($obt) . '</th><th>' . esc_html($tot) . '</th><th>' . esc_html($pct) . '%</th></tr></table>';
    echo '<p>Overall Grade: <strong>' . esc_html($pct >= 80 ? 'A+' : ($pct >= 70 ? 'A' : ($pct >= 60 ? 'B' : ($pct >= 50 ? 'C' : 'D')))) . '</strong> (' . esc_html($pct) . '%)</p>';
    echo '<div class="abn-stamp"><span>Class Teacher: ______________</span><span>Principal: ______________</span></div>';
    abn_print_footer();
    return true;
}

function abn_print_idcard_html($student_id) {
    $sm = abn_student_meta($student_id);
    echo '<div class="abn-idcard"><div class="abn-idcard-head">' . esc_html(abn_school_name()) . '</div><div class="abn-idcard-photo">Photo</div><div class="abn-idcard-name">' . esc_html(abn_student_name($student_id)) . '</div><div class="abn-idcard-row">Class: ' . esc_html($sm['class'] . '-' . $sm['section']) . ' · Roll: ' . esc_html($sm['roll']) . '</div><div class="abn-idcard-row">Adm No: ' . esc_html($sm['admission_no']) . '</div><div class="abn-idcard-row">Session ' . esc_html(abn_academic_year()) . '</div><div class="abn-idcard-foot">' . esc_html(abn_school_name()) . '</div></div>';
}

function abn_print_router() {
    if (!isset($_GET['abn_print'])) return;
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(home_url('/?abn_print=' . sanitize_key($_GET['abn_print']))));
        exit;
    }
    $kind = sanitize_key($_GET['abn_print']);
    $ok = false;
    if ($kind === 'challan') {
        $m = abn_fee_meta((int) ($_GET['fee'] ?? 0));
        if (!$m['student_id']) wp_die('Nothing to print.', '', 404);
        if (!abn_print_allowed($m['student_id'])) wp_die('You do not have permission for this record.', '', 403);
        $ok = abn_print_challan((int) ($_GET['fee'] ?? 0));
    } elseif ($kind === 'report') {
        $sid = (int) ($_GET['student'] ?? 0);
        if (!get_post($sid) || !get_post((int) ($_GET['exam'] ?? 0))) wp_die('Nothing to print.', '', 404);
        if (!abn_print_allowed($sid)) wp_die('You do not have permission for this record.', '', 403);
        $ok = abn_print_report((int) ($_GET['exam'] ?? 0), $sid);
    } elseif ($kind === 'idcard') {
        $sid = (int) ($_GET['student'] ?? 0);
        if (!get_post($sid)) wp_die('Nothing to print.', '', 404);
        if (!abn_print_allowed($sid)) wp_die('You do not have permission for this record.', '', 403);
        abn_print_head('Student ID Card');
        abn_print_idcard_html($sid);
        abn_print_footer();
        $ok = true;
    } elseif ($kind === 'idcards') {
        // Bulk class print is staff-only.
        if (!abn_user_can_manage()) wp_die('Not allowed.', '', 403);
        $class = sanitize_text_field($_GET['class'] ?? '');
        abn_print_head('ID Cards — Class ' . $class);
        foreach (abn_get_students() as $s) {
            if (abn_student_meta($s->ID)['class'] !== $class) continue;
            abn_print_idcard_html($s->ID);
        }
        abn_print_footer();
        $ok = true;
    }
    if (!$ok) wp_die('Nothing to print.', '', 404);
    exit;
}
add_action('template_redirect', 'abn_print_router');
