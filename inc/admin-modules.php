<?php
/* Abn School — remaining admin module pages + Settings. */
if (!defined('ABSPATH')) exit;

/* ---------- Attendance ---------- */
function abn_render_attendance() {
    if (!abn_user_can_manage()) return;
    $date = isset($_REQUEST['att_date']) ? sanitize_text_field($_REQUEST['att_date']) : date('Y-m-d');
    $class = isset($_REQUEST['class']) ? sanitize_text_field($_REQUEST['class']) : '';
    $teacher_classes = current_user_can('manage_options') ? [] : abn_teacher_classes(get_current_user_id());
    if ($teacher_classes && ($class === '' || !in_array($class, $teacher_classes, true))) $class = $teacher_classes[0];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abn_save_att']) && wp_verify_nonce(sanitize_key($_POST['abn_att_nonce'] ?? ''), 'abn_att')) {
        $n = abn_save_attendance($date, (array) ($_POST['status'] ?? []));
        abn_notice('Attendance saved for ' . $date . ' (' . $n . ' students).');
    }
    $all = abn_get_students();
    $rows = array_filter($all, function ($p) use ($class, $teacher_classes) {
        if ($teacher_classes && !in_array(abn_student_meta($p->ID)['class'], $teacher_classes, true)) return false;
        return $class === '' || abn_student_meta($p->ID)['class'] === $class;
    });
    usort($rows, function ($a, $b) {
        $ma = abn_student_meta($a->ID); $mb = abn_student_meta($b->ID);
        return strcmp($ma['class'] . $ma['section'] . $a->post_title, $mb['class'] . $mb['section'] . $b->post_title);
    });
    $saved = abn_attendance_for_date($date);
    $present = count(array_filter($saved, fn($s) => $s === 'present'));
    abn_layout_open('Attendance', 'Mark daily attendance class-wise. ' . $present . ' present of ' . count($rows) . ' shown.');
    echo '<div class="abn-card"><form method="get" class="abn-filter"><input type="hidden" name="page" value="abn-attendance">';
    echo '<label>Date <input type="date" name="att_date" value="' . esc_attr($date) . '"></label>';
    echo '<label>Class <select name="class"><option value="">All classes</option>';
    foreach (($teacher_classes ?: abn_classes()) as $c) echo '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>' . esc_html($c) . '</option>';
    echo '</select></label><button class="abn-btn small">Show</button></form></div>';
    echo '<div class="abn-card"><h3>Mark Attendance — ' . esc_html($date) . ' <span class="abn-pill">' . esc_html($present . ' / ' . count($rows) . ' present') . '</span></h3>';
    echo '<form method="post"><input type="hidden" name="att_date" value="' . esc_attr($date) . '">';
    wp_nonce_field('abn_att', 'abn_att_nonce');
    echo '<table class="abn-table"><tr><th>Student</th><th>Class</th><th>Status</th></tr>';
    foreach ($rows as $r) {
        $m = abn_student_meta($r->ID);
        $st = $saved[$r->ID] ?? 'present';
        echo '<tr><td>' . esc_html($r->post_title) . '</td><td>' . esc_html($m['class'] . '-' . $m['section']) . '</td><td><select name="status[' . esc_attr($r->ID) . ']">';
        foreach (['present' => 'Present', 'absent' => 'Absent', 'leave' => 'Leave'] as $v => $l) {
            echo '<option value="' . esc_attr($v) . '"' . selected($st, $v, false) . '>' . esc_html($l) . '</option>';
        }
        echo '</select></td></tr>';
    }
    echo '</table><button class="abn-btn" name="abn_save_att" value="1" style="margin-top:14px">Save Attendance</button></form></div>';
    abn_layout_close();
}

/* ---------- Exams & Report Cards ---------- */
function abn_render_exams() {
    if (!abn_user_can_manage()) return;
    $teacher_classes = current_user_can('manage_options') ? [] : abn_teacher_classes(get_current_user_id());
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['abn_new_exam']) && wp_verify_nonce(sanitize_key($_POST['abn_exam_nonce'] ?? ''), 'abn_exam')) {
            $id = wp_insert_post(['post_type' => 'abn_exam', 'post_title' => sanitize_text_field($_POST['exam_name']), 'post_status' => 'publish']);
            update_post_meta($id, '_abn_class', sanitize_text_field($_POST['exam_class']));
            update_post_meta($id, '_abn_date', sanitize_text_field($_POST['exam_date']));
            abn_notice('Exam created.');
        }
        if (isset($_POST['abn_save_marks']) && wp_verify_nonce(sanitize_key($_POST['abn_exam_nonce'] ?? ''), 'abn_exam')) {
            $exam_id = (int) $_POST['exam_id'];
            $subjects = abn_subjects();
            $n = 0;
            foreach ((array) ($_POST['marks'] ?? []) as $sid => $subs) {
                foreach ($subjects as $subj) {
                    if (!isset($subs[$subj]) || $subs[$subj] === '') continue;
                    // Replace existing mark for this exam/student/subject.
                    $q = new WP_Query([
                        'post_type' => 'abn_mark', 'posts_per_page' => 1, 'post_status' => 'publish', 'fields' => 'ids',
                        'meta_query' => [
                            ['key' => '_abn_exam_id', 'value' => $exam_id],
                            ['key' => '_abn_student_id', 'value' => (int) $sid],
                            ['key' => '_abn_subject', 'value' => $subj],
                        ],
                    ]);
                    $mid = $q->posts ? $q->posts[0] : wp_insert_post(['post_type' => 'abn_mark', 'post_title' => 'Marks — ' . abn_student_name((int) $sid) . ' — ' . $subj, 'post_status' => 'publish']);
                    update_post_meta($mid, '_abn_exam_id', $exam_id);
                    update_post_meta($mid, '_abn_student_id', (int) $sid);
                    update_post_meta($mid, '_abn_subject', $subj);
                    update_post_meta($mid, '_abn_marks', (int) $subs[$subj]);
                    update_post_meta($mid, '_abn_total', 100);
                    $n++;
                }
            }
            abn_notice('Marks saved (' . $n . ' entries).');
        }
    }
    $exams = abn_get_exams();
    $sel_exam = isset($_REQUEST['exam_id']) ? (int) $_REQUEST['exam_id'] : ($exams ? $exams[0]->ID : 0);
    $sel_class = isset($_REQUEST['class']) ? sanitize_text_field($_REQUEST['class']) : '';
    if ($teacher_classes && ($sel_class === '' || !in_array($sel_class, $teacher_classes, true))) $sel_class = $teacher_classes[0];
    abn_layout_open('Exams & Report Cards', 'Create exams, enter marks, print report cards.');
    if (current_user_can('manage_options')) {
        echo '<div class="abn-card"><h3>Create Exam</h3><form method="post" class="abn-form">';
        wp_nonce_field('abn_exam', 'abn_exam_nonce');
        echo '<label>Exam Name<input type="text" name="exam_name" value="Final Examination 2026" required></label>';
        echo '<label>Classes<input type="text" name="exam_class" value="6–10"></label>';
        echo '<label>Date<input type="date" name="exam_date" value="' . esc_attr(date('Y-m-d')) . '"></label>';
        echo '<button class="abn-btn" name="abn_new_exam" value="1">Create Exam</button></form></div>';
    }
    if ($exams) {
        echo '<div class="abn-card"><h3>Enter Marks</h3><form method="get" class="abn-filter"><input type="hidden" name="page" value="abn-exams">';
        echo '<label>Exam <select name="exam_id">';
        foreach ($exams as $e) echo '<option value="' . esc_attr($e->ID) . '"' . selected($sel_exam, $e->ID, false) . '>' . esc_html($e->post_title) . '</option>';
        echo '</select></label><label>Class <select name="class"><option value="">All classes</option>';
        foreach (($teacher_classes ?: abn_classes()) as $c) echo '<option value="' . esc_attr($c) . '"' . selected($sel_class, $c, false) . '>' . esc_html($c) . '</option>';
        echo '</select></label><button class="abn-btn small">Show</button></form>';
        $students = abn_get_students();
        $students = array_filter($students, function ($p) use ($sel_class, $teacher_classes) {
            if ($teacher_classes && !in_array(abn_student_meta($p->ID)['class'], $teacher_classes, true)) return false;
            return $sel_class === '' || abn_student_meta($p->ID)['class'] === $sel_class;
        });
        $existing = [];
        foreach (abn_marks_for_exam($sel_exam) as $mk) {
            $existing[(int) get_post_meta($mk->ID, '_abn_student_id', true)][get_post_meta($mk->ID, '_abn_subject', true)] = (int) get_post_meta($mk->ID, '_abn_marks', true);
        }
        $subjects = abn_subjects();
        echo '<form method="post"><input type="hidden" name="exam_id" value="' . esc_attr($sel_exam) . '">';
        wp_nonce_field('abn_exam', 'abn_exam_nonce');
        echo '<table class="abn-table"><tr><th>Student</th><th>Class</th>';
        foreach ($subjects as $s) echo '<th>' . esc_html($s) . ' /100</th>';
        echo '<th>Report Card</th></tr>';
        foreach ($students as $stu) {
            $m = abn_student_meta($stu->ID);
            echo '<tr><td>' . esc_html($stu->post_title) . '</td><td>' . esc_html($m['class'] . '-' . $m['section']) . '</td>';
            foreach ($subjects as $s) {
                $v = $existing[$stu->ID][$s] ?? '';
                echo '<td><input type="number" min="0" max="100" name="marks[' . esc_attr($stu->ID) . '][' . esc_attr($s) . ']" value="' . esc_attr($v) . '" style="width:70px"></td>';
            }
            $rc = home_url('/?abn_print=report&exam=' . $sel_exam . '&student=' . $stu->ID);
            echo '<td><a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url($rc) . '">Print</a></td></tr>';
        }
        echo '</table><button class="abn-btn" name="abn_save_marks" value="1" style="margin-top:14px">Save Marks</button></form></div>';
    } else {
        echo '<div class="abn-card"><p class="abn-note">No exams yet — create one above.</p></div>';
    }
    abn_layout_close();
}

/* ---------- Timetable ---------- */
function abn_render_timetable() {
    if (!abn_user_can_manage()) return;
    $teacher_classes = current_user_can('manage_options') ? [] : abn_teacher_classes(get_current_user_id());
    if ($teacher_classes && !in_array(isset($_REQUEST['class']) ? sanitize_text_field($_REQUEST['class']) : '6', $teacher_classes, true)) $_REQUEST['class'] = $teacher_classes[0];
    $class = isset($_REQUEST['class']) ? sanitize_text_field($_REQUEST['class']) : '6';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abn_save_tt']) && wp_verify_nonce(sanitize_key($_POST['abn_tt_nonce'] ?? ''), 'abn_tt')) {
        $tt = abn_timetable();
        $tt[$class] = [];
        foreach (abn_timetable_days() as $day) {
            foreach (abn_timetable_periods() as $pi => $p) {
                $tt[$class][$day][$pi] = sanitize_text_field($_POST['tt'][$day][$pi] ?? '');
            }
        }
        update_option('abn_timetable', $tt);
        abn_notice('Timetable saved for Class ' . $class . '.');
    }
    $tt = abn_timetable();
    abn_layout_open('Timetable', 'Weekly class timetable — editable per class.');
    echo '<div class="abn-card"><form method="get" class="abn-filter"><input type="hidden" name="page" value="abn-timetable">';
    echo '<label>Class <select name="class">';
    foreach (($teacher_classes ?: abn_classes()) as $c) echo '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>Class ' . esc_html($c) . '</option>';
    echo '</select></label><button class="abn-btn small">Show</button></form></div>';
    echo '<div class="abn-card"><h3>Class ' . esc_html($class) . ' — Weekly Timetable</h3><form method="post">';
    wp_nonce_field('abn_tt', 'abn_tt_nonce');
    echo '<table class="abn-table"><tr><th>Day</th>';
    foreach (abn_timetable_periods() as $p) echo '<th>' . esc_html($p) . '</th>';
    echo '</tr>';
    foreach (abn_timetable_days() as $day) {
        echo '<tr><td><strong>' . esc_html($day) . '</strong></td>';
        foreach (abn_timetable_periods() as $pi => $p) {
            $v = $tt[$class][$day][$pi] ?? '';
            echo '<td><input type="text" name="tt[' . esc_attr($day) . '][' . esc_attr($pi) . ']" value="' . esc_attr($v) . '" style="width:100%"></td>';
        }
        echo '</tr>';
    }
    echo '</table><button class="abn-btn" name="abn_save_tt" value="1" style="margin-top:14px">Save Timetable</button></form></div>';
    abn_layout_close();
}

/* ---------- Homework Diary ---------- */
function abn_render_homework() {
    if (!abn_user_can_manage()) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abn_add_hw']) && wp_verify_nonce(sanitize_key($_POST['abn_hw_nonce'] ?? ''), 'abn_hw')) {
        $id = wp_insert_post([
            'post_type' => 'abn_homework',
            'post_title' => sanitize_text_field($_POST['topic']),
            'post_content' => sanitize_textarea_field($_POST['topic']),
            'post_status' => 'publish',
        ]);
        update_post_meta($id, '_abn_class', sanitize_text_field($_POST['class']));
        update_post_meta($id, '_abn_subject', sanitize_text_field($_POST['subject']));
        update_post_meta($id, '_abn_date', sanitize_text_field($_POST['due_date']));
        abn_notice('Homework added.');
    }
    $class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    $rows = abn_get_homework($class);
    abn_layout_open('Homework Diary', 'Homework per class, subject and date.');
    echo '<div class="abn-card"><h3>Add Homework</h3><form method="post" class="abn-form">';
    wp_nonce_field('abn_hw', 'abn_hw_nonce');
    echo '<label>Class<select name="class">';
    foreach (abn_classes() as $c) echo '<option value="' . esc_attr($c) . '">' . esc_html($c) . '</option>';
    echo '</select></label>';
    echo '<label>Subject<input type="text" name="subject" value="Mathematics" required></label>';
    echo '<label>Due Date<input type="date" name="due_date" value="' . esc_attr(date('Y-m-d', strtotime('+2 days'))) . '" required></label>';
    echo '<label>Homework / Topic<input type="text" name="topic" required></label>';
    echo '<button class="abn-btn" name="abn_add_hw" value="1">Add Homework</button></form></div>';
    echo '<div class="abn-card"><h3>Homework List (' . count($rows) . ')</h3>';
    echo '<form method="get" class="abn-filter"><input type="hidden" name="page" value="abn-homework"><select name="class"><option value="">All classes</option>';
    foreach (abn_classes() as $c) echo '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>' . esc_html($c) . '</option>';
    echo '</select><button class="abn-btn small">Filter</button></form>';
    echo '<table class="abn-table"><tr><th>Class</th><th>Subject</th><th>Homework</th><th>Due Date</th></tr>';
    foreach ($rows as $r) {
        echo '<tr><td>' . esc_html(get_post_meta($r->ID, '_abn_class', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_subject', true)) . '</td><td>' . esc_html($r->post_title) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_date', true)) . '</td></tr>';
    }
    echo '</table></div>';
    abn_layout_close();
}

/* ---------- Staff ---------- */
function abn_render_staff() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abn_add_staff']) && wp_verify_nonce(sanitize_key($_POST['abn_staff_nonce'] ?? ''), 'abn_staff')) {
        $id = wp_insert_post(['post_type' => 'abn_staff', 'post_title' => sanitize_text_field($_POST['name']), 'post_status' => 'publish']);
        update_post_meta($id, '_abn_role', sanitize_text_field($_POST['role']));
        update_post_meta($id, '_abn_subject', sanitize_text_field($_POST['subject']));
        update_post_meta($id, '_abn_phone', sanitize_text_field($_POST['phone']));
        update_post_meta($id, '_abn_email', sanitize_email($_POST['email']));
        update_post_meta($id, '_abn_user_id', (int) ($_POST['user_id'] ?? 0));
        $classes = array_values(array_intersect((array) ($_POST['classes'] ?? []), abn_classes()));
        update_post_meta($id, '_abn_classes', $classes);
        abn_notice('Staff member added.');
    }
    if (isset($_GET['delete']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce'] ?? ''), 'abn_delete_staff')) {
        wp_delete_post((int) $_GET['delete'], true);
        abn_notice('Staff member deleted.');
    }
    $rows = abn_get_staff();
    abn_layout_open('Staff', 'Teachers, roles and office staff.');
    echo '<div class="abn-card"><h3>Add Staff Member</h3><form method="post" class="abn-form">';
    wp_nonce_field('abn_staff', 'abn_staff_nonce');
    echo '<label>Name<input type="text" name="name" required></label>';
    echo '<label>Role<select name="role"><option>Teacher</option><option>Principal</option><option>Vice Principal</option><option>Office Clerk</option><option>Accountant</option></select></label>';
    echo '<label>Subject / Department<input type="text" name="subject" value="English"></label>';
    echo '<label>Phone<input type="text" name="phone"></label>';
    echo '<label>Email<input type="email" name="email"></label>';
    $teachers = get_users(['role' => 'abn_teacher', 'orderby' => 'display_name', 'order' => 'ASC']);
    echo '<label>Teacher Account (portal/admin login)<select name="user_id"><option value="0">— No account —</option>';
    foreach ($teachers as $u) echo '<option value="' . esc_attr($u->ID) . '">' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
    echo '</select><small>Links this staff record to a teacher login.</small></label>';
    echo '<fieldset style="grid-column:1/-1"><legend>Assigned Classes</legend>';
    foreach (abn_classes() as $c) echo '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="classes[]" value="' . esc_attr($c) . '"> ' . esc_html($c) . '</label>';
    echo '<small>Teachers only see and mark their assigned classes.</small></fieldset>';
    echo '<button class="abn-btn" name="abn_add_staff" value="1">Add Staff</button></form></div>';
    echo '<div class="abn-card"><h3>Staff List (' . count($rows) . ')</h3><table class="abn-table"><tr><th>Name</th><th>Role</th><th>Subject / Dept</th><th>Phone</th><th>Email</th><th>Account / Classes</th><th></th></tr>';
    foreach ($rows as $r) {
        $del = wp_nonce_url(admin_url('admin.php?page=abn-staff&delete=' . $r->ID), 'abn_delete_staff');
        $uid = (int) get_post_meta($r->ID, '_abn_user_id', true);
        $cls = get_post_meta($r->ID, '_abn_classes', true);
        $acct = '';
        if ($uid) { $u = get_user_by('id', $uid); if ($u) $acct .= $u->user_login; }
        if (is_array($cls) && $cls) $acct .= ($acct ? ' · ' : '') . 'Classes ' . implode(', ', $cls);
        echo '<tr><td>' . esc_html($r->post_title) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_role', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_subject', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_phone', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_email', true)) . '</td><td>' . esc_html($acct ?: '—') . '</td><td><a href="' . esc_url($del) . '" onclick="return confirm(\'Delete this staff member?\')">Delete</a></td></tr>';
    }
    echo '</table></div>';
    abn_layout_close();
}

/* ---------- ID Cards ---------- */
function abn_render_idcards() {
    if (!current_user_can('manage_options')) return;
    $class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    $students = abn_get_students();
    $students = array_filter($students, function ($p) use ($class) {
        return $class === '' || abn_student_meta($p->ID)['class'] === $class;
    });
    abn_layout_open('ID Cards', 'Printable student ID cards — school name, photo placeholder, name, class, roll no.');
    echo '<div class="abn-card"><form method="get" class="abn-filter"><input type="hidden" name="page" value="abn-idcards"><select name="class"><option value="">All classes</option>';
    foreach (abn_classes() as $c) echo '<option value="' . esc_attr($c) . '"' . selected($class, $c, false) . '>' . esc_html($c) . '</option>';
    echo '</select><button class="abn-btn small">Filter</button></form>';
    if ($class !== '') echo '<p><a class="abn-btn" target="_blank" rel="noopener" href="' . esc_url(home_url('/?abn_print=idcards&class=' . $class)) . '">Print All ID Cards — Class ' . esc_html($class) . '</a></p>';
    echo '<div class="abn-idgrid">';
    foreach ($students as $s) {
        $m = abn_student_meta($s->ID);
        echo '<div class="abn-idcard"><div class="abn-idcard-head">' . esc_html(abn_school_name()) . '</div><div class="abn-idcard-photo">Photo</div><div class="abn-idcard-name">' . esc_html($s->post_title) . '</div><div class="abn-idcard-row">Class: ' . esc_html($m['class'] . '-' . $m['section']) . ' · Roll: ' . esc_html($m['roll']) . '</div><div class="abn-idcard-row">Adm No: ' . esc_html($m['admission_no']) . '</div><a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url(home_url('/?abn_print=idcard&student=' . $s->ID)) . '">Print</a></div>';
    }
    echo '</div></div>';
    abn_layout_close();
}

/* ---------- WhatsApp Reminders ---------- */
function abn_render_whatsapp() {
    if (!current_user_can('manage_options')) return;
    $pending = [];
    foreach (abn_get_fees() as $f) {
        $m = abn_fee_meta($f->ID);
        if ($m['status'] === 'pending') $pending[] = [$f, $m];
    }
    abn_layout_open('WhatsApp Reminders', 'One-tap fee reminders to parents via wa.me — no API key, no paid service.');
    if (!abn_has_real_whatsapp()) {
        echo '<div class="abn-card"><p class="abn-note">Add the school WhatsApp number in Abn School → Settings to enable reminders. The buttons below are disabled until then.</p></div>';
        abn_layout_close();
        return;
    }
    echo '<div class="abn-card"><p class="abn-note">School WhatsApp number (sender, shown in the message signature): <strong>' . esc_html(abn_whatsapp()) . '</strong>. Reminders open WhatsApp with a prefilled message to the parent\'s number.</p>';
    echo '<table class="abn-table"><tr><th>Student</th><th>Class</th><th>Month</th><th>Amount</th><th>Parent Phone</th><th></th></tr>';
    foreach ($pending as $row) {
        list($f, $m) = $row;
        $sm = abn_student_meta($m['student_id']);
        $msg = 'Assalam-o-Alaikum, ' . abn_student_name($m['student_id']) . ' (Class ' . $sm['class'] . '-' . $sm['section'] . ') ki ' . $m['month'] . ' fee ' . abn_rs($m['amount']) . ' pending hai. Baraye meharbani jald ada karein. — ' . abn_school_name() . ' (' . abn_whatsapp() . ')';
        echo '<tr><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><td>' . esc_html($sm['class'] . '-' . $sm['section']) . '</td><td>' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td><td>' . esc_html($sm['phone']) . '</td><td><a class="abn-btn small orange" target="_blank" rel="noopener" href="' . esc_url(abn_wa_link($sm['phone'], $msg)) . '">Send WhatsApp</a></td></tr>';
    }
    echo '</table>';
    if (!$pending) echo '<p class="abn-note">No pending fees — nothing to remind.</p>';
    echo '</div>';
    abn_layout_close();
}

/* ---------- Settings ---------- */
function abn_render_settings() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abn_save_settings']) && wp_verify_nonce(sanitize_key($_POST['abn_settings_nonce'] ?? ''), 'abn_settings')) {
        $opts = get_option('abn_settings', []);
        foreach (['school_name', 'phone', 'whatsapp', 'email1', 'email2', 'address', 'academic_year'] as $k) {
            $opts[$k] = sanitize_text_field($_POST[$k] ?? '');
        }
        $opts['logo_id'] = (int) ($_POST['logo_id'] ?? 0);
        foreach (['fee_nursery', 'fee_primary', 'fee_middle', 'fee_matric'] as $k) {
            $opts[$k] = (int) ($_POST[$k] ?? 0);
        }
        update_option('abn_settings', $opts);
        abn_notice('Settings saved — applied everywhere (header, footer, challans, ID cards).');
    }
    $opts = array_merge(abn_defaults(), (array) get_option('abn_settings', []));
    $counts = [
        'students' => count(abn_get_students()),
        'fees' => count(abn_get_fees()),
        'attendance' => count((new WP_Query(['post_type' => 'abn_attendance', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids']))->posts),
        'inquiries' => count((new WP_Query(['post_type' => 'abn_inquiry', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids']))->posts),
    ];
    abn_layout_open('Settings', 'School details, logo, fee defaults, setup status and demo data.');

    // One-click setup status + "Run Setup Again".
    $setup = get_option('abn_setup_status', null);
    echo '<div class="abn-card"><h3>One-Click Setup</h3>';
    if (is_array($setup) && !empty($setup['time'])) {
        echo '<p class="abn-note">Last setup run: ' . esc_html($setup['time']) . ' (' . esc_html($setup['source'] ?? '') . ') — '
            . ($setup['ok'] ? '<strong>all steps OK.</strong>' : '<strong>finished with problems (see below).</strong>') . '</p>';
        if (!empty($setup['steps'])) {
            echo '<table class="abn-table"><tr><th>Step</th><th>Status</th></tr>';
            foreach ($setup['steps'] as $step) {
                echo '<tr><td>' . esc_html($step['msg'] ?? '') . '</td><td>' . ($step['ok'] ? 'OK' : '<strong>Failed</strong>') . '</td></tr>';
            }
            echo '</table>';
        }
    } else {
        echo '<p class="abn-note">The one-click setup has not run yet on this install.</p>';
    }
    echo '<p><a class="abn-btn" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=abn_run_setup'), 'abn_run_setup')) . '">Run Setup Again</a> '
        . '<span class="abn-note">Re-runs the whole setup (settings, roles, pages, demo data). Safe to run any time — nothing is duplicated.</span></p></div>';

    $logo_url = abn_logo_url();
    echo '<div class="abn-card"><h3>School Details</h3><form method="post" class="abn-form">';
    wp_nonce_field('abn_settings', 'abn_settings_nonce');
    echo '<label>School Name<input type="text" name="school_name" value="' . esc_attr($opts['school_name']) . '"></label>';
    echo '<label>Phone<input type="text" name="phone" value="' . esc_attr($opts['phone']) . '" placeholder="[Insert Phone Number]"></label>';
    echo '<label>WhatsApp Number<input type="text" name="whatsapp" value="' . esc_attr($opts['whatsapp']) . '" placeholder="[Insert Phone Number]"></label>';
    echo '<label>Email 1<input type="text" name="email1" value="' . esc_attr($opts['email1']) . '" placeholder="[Insert Email]"></label>';
    echo '<label>Email 2<input type="text" name="email2" value="' . esc_attr($opts['email2']) . '"></label>';
    echo '<label>Academic Year<input type="text" name="academic_year" value="' . esc_attr($opts['academic_year']) . '"></label>';
    echo '<label style="grid-column:1/-1">Address<input type="text" name="address" value="' . esc_attr($opts['address']) . '" placeholder="[Insert Address]"><small>Shown in the footer and on challans. Leave the placeholder until the real address is provided — it is never invented.</small></label>';
    echo '<div style="grid-column:1/-1"><label>School Logo</label><div style="display:flex;gap:12px;align-items:center;margin-top:6px">';
    echo '<input type="hidden" id="abn_logo_id" name="logo_id" value="' . esc_attr($opts['logo_id']) . '">';
    echo '<span id="abn_logo_preview">' . ($logo_url ? '<img src="' . esc_url($logo_url) . '" style="max-height:64px;border-radius:8px">' : '<span class="abn-note">No logo yet.</span>') . '</span>';
    echo '<button type="button" class="abn-btn small" id="abn_logo_pick">Choose Logo</button>';
    echo '<button type="button" class="abn-btn small outline" id="abn_logo_remove">Remove</button></div>';
    echo '<small>Upload the school logo from the media library — it appears in the website header and on ID cards.</small></div>';
    echo '<script>document.addEventListener("DOMContentLoaded",function(){var pick=document.getElementById("abn_logo_pick"),rem=document.getElementById("abn_logo_remove"),inp=document.getElementById("abn_logo_id"),prev=document.getElementById("abn_logo_preview");if(!pick||!window.wp||!wp.media)return;var frame;pick.addEventListener("click",function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:"Choose School Logo",button:{text:"Use this logo"},multiple:false});frame.on("select",function(){var a=frame.state().get("selection").first().toJSON();inp.value=a.id;var im=document.createElement("img");im.src=a.url;im.style.maxHeight="64px";im.style.borderRadius="8px";prev.innerHTML="";prev.appendChild(im);});frame.open();});rem.addEventListener("click",function(e){e.preventDefault();inp.value="0";prev.textContent="No logo yet.";});});</script>';
    echo '<label>Monthly Fee — Nursery &amp; KG (Rs.) <span class="abn-note">(DEMO — confirm)</span><input type="number" name="fee_nursery" value="' . esc_attr($opts['fee_nursery']) . '"></label>';
    echo '<label>Monthly Fee — Primary 1–5 (Rs.) <span class="abn-note">(DEMO — confirm)</span><input type="number" name="fee_primary" value="' . esc_attr($opts['fee_primary']) . '"></label>';
    echo '<label>Monthly Fee — Middle 6–8 (Rs.) <span class="abn-note">(DEMO — confirm)</span><input type="number" name="fee_middle" value="' . esc_attr($opts['fee_middle']) . '"></label>';
    echo '<label>Monthly Fee — Matric 9–10 (Rs.) <span class="abn-note">(DEMO — confirm)</span><input type="number" name="fee_matric" value="' . esc_attr($opts['fee_matric']) . '"></label>';
    echo '<button class="abn-btn" name="abn_save_settings" value="1">Save Settings</button></form></div>';

    echo '<div class="abn-card"><h3>Website Content</h3><p class="abn-note">The public pages (Home, About, Admissions, Classes, Teachers, Announcements, Contact) are normal WordPress pages — edit their text in <strong>Pages</strong>. Class fees, teacher names and announcements on those pages come live from the settings, staff records and notices above, so they update automatically. The School Portal page uses the shortcode <code>[abn_school_portal]</code>.</p></div>';

    echo '<div class="abn-card"><h3>Demo Data</h3><p class="abn-note">Currently in the system: ' . esc_html($counts['students']) . ' students, ' . esc_html($counts['fees']) . ' fee records, ' . esc_html($counts['attendance']) . ' attendance records, ' . esc_html($counts['inquiries']) . ' admission inquiries. Demo data is clearly labelled and can be removed at any time (this also removes the demo parent/student/teacher accounts).</p>';
    echo '<p><a class="abn-btn" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=abn_seed_demo'), 'abn_seed_demo')) . '">Seed Demo Data</a> ';
    echo '<a class="abn-btn orange" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=abn_clear_demo'), 'abn_clear_demo')) . '" onclick="return confirm(\'Delete ALL school data (students, fees, attendance, exams, staff, homework, demo notices, demo accounts)?\')">Clear Demo Data</a></p></div>';
    abn_layout_close();
}

/* ---------- Admission Inquiries ---------- */
function abn_render_inquiries() {
    if (!current_user_can('manage_options')) return;
    if (isset($_GET['delete']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce'] ?? ''), 'abn_delete_inquiry')) {
        wp_delete_post((int) $_GET['delete'], true);
        abn_notice('Inquiry deleted.');
    }
    $q = new WP_Query(['post_type' => 'abn_inquiry', 'posts_per_page' => -1, 'post_status' => 'publish', 'orderby' => 'date', 'order' => 'DESC']);
    abn_layout_open('Admission Inquiries', 'Inquiries sent from the Admissions page form.');
    echo '<div class="abn-card"><h3>Inquiries (' . count($q->posts) . ')</h3>';
    if (!$q->posts) { echo '<p class="abn-note">No inquiries yet — they appear here when someone sends the form on the Admissions page.</p></div>'; abn_layout_close(); return; }
    echo '<table class="abn-table"><tr><th>Date</th><th>Child</th><th>Father</th><th>Class</th><th>Phone</th><th>Email</th><th>Message</th><th></th></tr>';
    foreach ($q->posts as $r) {
        $del = wp_nonce_url(admin_url('admin.php?page=abn-inquiries&delete=' . $r->ID), 'abn_delete_inquiry');
        echo '<tr><td>' . esc_html(get_post_meta($r->ID, '_abn_date', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_child', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_father', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_class', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_phone', true)) . '</td><td>' . esc_html(get_post_meta($r->ID, '_abn_email', true)) . '</td><td>' . esc_html(wp_trim_words($r->post_content, 12)) . '</td><td><a href="' . esc_url($del) . '" onclick="return confirm(\'Delete this inquiry?\')">Delete</a></td></tr>';
    }
    echo '</table></div>';
    abn_layout_close();
}

/* ---------- Admin-post actions ---------- */
function abn_admin_post_actions() {
    if (!current_user_can('manage_options')) wp_die('Not allowed.');
    $action = isset($_GET['action']) ? sanitize_key($_GET['action']) : '';
    $map = [
        'abn_seed_demo' => 'abn_seed_demo',
        'abn_clear_demo' => 'abn_clear_demo',
        'abn_run_setup' => 'abn_run_setup',
    ];
    if (!isset($map[$action]) || !wp_verify_nonce(sanitize_key($_GET['_wpnonce'] ?? ''), $map[$action])) wp_die('Invalid request.');
    $msg = '';
    if ($action === 'abn_seed_demo') {
        $r = abn_seed_demo_data();
        $msg = $r['seeded'] ? 'Demo data seeded (' . $r['students'] . ' students).' : 'Demo data already exists.';
    } elseif ($action === 'abn_clear_demo') {
        $n = abn_clear_demo_data();
        $msg = 'Demo data cleared (' . $n . ' records removed).';
    } elseif ($action === 'abn_run_setup') {
        $r = abn_run_full_setup('manual');
        $msg = $r['ok'] ? 'Setup completed: all steps OK.' : 'Setup finished with problems: ' . implode(' ', $r['errors']);
    }
    $url = admin_url('admin.php?page=abn-settings');
    // Pass the message via a transient (admin notices cannot carry it through the redirect otherwise).
    set_transient('abn_admin_notice', $msg, 60);
    wp_safe_redirect($url);
    exit;
}
add_action('admin_post_abn_seed_demo', 'abn_admin_post_actions');
add_action('admin_post_abn_clear_demo', 'abn_admin_post_actions');
add_action('admin_post_abn_run_setup', 'abn_admin_post_actions');

function abn_admin_notice_transient() {
    $msg = get_transient('abn_admin_notice');
    if ($msg) {
        delete_transient('abn_admin_notice');
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($msg) . '</p></div>';
    }
}
add_action('admin_notices', 'abn_admin_notice_transient');
