<?php
/* Abn School — admin menu, layout and core module pages.
   Role-based menus: administrators see everything; teachers see the
   dashboard, attendance, exams, timetable and homework (their assigned
   classes only); parents/students use the front-end portal instead. */
if (!defined('ABSPATH')) exit;

/* ---------- Menu ---------- */
function abn_admin_menu() {
    add_menu_page('Abn School', 'Abn School', 'abn_manage_school', 'abn-dashboard', 'abn_render_dashboard', 'dashicons-welcome-learn-more', 6);
    // [slug, label, callback, capability]
    $pages = [
        ['abn-dashboard', 'Dashboard', 'abn_render_dashboard', 'abn_manage_school'],
        ['abn-students', 'Students', 'abn_render_students', 'manage_options'],
        ['abn-fees', 'Fees & Challans', 'abn_render_fees', 'manage_options'],
        ['abn-attendance', 'Attendance', 'abn_render_attendance', 'abn_manage_school'],
        ['abn-exams', 'Exams & Report Cards', 'abn_render_exams', 'abn_manage_school'],
        ['abn-timetable', 'Timetable', 'abn_render_timetable', 'abn_manage_school'],
        ['abn-homework', 'Homework Diary', 'abn_render_homework', 'abn_manage_school'],
        ['abn-staff', 'Staff', 'abn_render_staff', 'manage_options'],
        ['abn-idcards', 'ID Cards', 'abn_render_idcards', 'manage_options'],
        ['abn-whatsapp', 'WhatsApp Reminders', 'abn_render_whatsapp', 'manage_options'],
        ['abn-inquiries', 'Admission Inquiries', 'abn_render_inquiries', 'manage_options'],
        ['abn-settings', 'Settings', 'abn_render_settings', 'manage_options'],
    ];
    foreach ($pages as $p) {
        add_submenu_page('abn-dashboard', $p[1], $p[1], $p[3], $p[0], $p[2]);
    }
}
add_action('admin_menu', 'abn_admin_menu');

function abn_admin_assets($hook) {
    if (strpos($hook, 'abn-') === false) return;
    wp_enqueue_style('abn-admin', get_template_directory_uri() . '/assets/css/admin.css', [], '1.0.0');
    if (strpos($hook, 'abn-settings') !== false) wp_enqueue_media(); // Logo upload picker.
}
add_action('admin_enqueue_scripts', 'abn_admin_assets');

/* ---------- Layout ---------- */
function abn_notice($msg) { echo '<div class="abn-alert">' . esc_html($msg) . '</div>'; }

function abn_layout_open($title, $subtitle = '') {
    echo '<div class="abn-wrap"><aside class="abn-sidebar">';
    echo '<div class="abn-logo">' . esc_html(abn_school_name()) . '<span>School Management</span></div>';
    $menu = [
        'abn-dashboard' => ['Dashboard', 'abn_manage_school'], 'abn-students' => ['Students', 'manage_options'],
        'abn-fees' => ['Fees & Challans', 'manage_options'], 'abn-attendance' => ['Attendance', 'abn_manage_school'],
        'abn-exams' => ['Exams & Report Cards', 'abn_manage_school'], 'abn-timetable' => ['Timetable', 'abn_manage_school'],
        'abn-homework' => ['Homework Diary', 'abn_manage_school'], 'abn-staff' => ['Staff', 'manage_options'],
        'abn-idcards' => ['ID Cards', 'manage_options'], 'abn-whatsapp' => ['WhatsApp Reminders', 'manage_options'],
        'abn-inquiries' => ['Admission Inquiries', 'manage_options'],
        'abn-settings' => ['Settings', 'manage_options'],
    ];
    $current = isset($_GET['page']) ? sanitize_key($_GET['page']) : 'abn-dashboard';
    echo '<nav>';
    foreach ($menu as $slug => $item) {
        if (!current_user_can($item[1])) continue;
        $active = ($current === $slug) ? ' class="active"' : '';
        echo '<a href="' . esc_url(admin_url('admin.php?page=' . $slug)) . '"' . $active . '>' . esc_html($item[0]) . '</a>';
    }
    echo '</nav><div class="abn-side-foot">Abn School<br>Session ' . esc_html(abn_academic_year()) . '</div></aside><main class="abn-main">';
    echo '<header class="abn-top"><div><h1>' . esc_html($title) . '</h1><p>' . esc_html($subtitle ? $subtitle : date('l, d F Y')) . '</p></div>';
    echo '<div class="abn-top-actions"><span class="abn-pill">Session ' . esc_html(abn_academic_year()) . '</span> ';
    echo '<a class="abn-btn small" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noopener">View Website</a></div></header>';
}
function abn_layout_close() { echo '</main></div>'; }

/* ---------- Dashboard ---------- */
function abn_render_dashboard() {
    if (!abn_user_can_manage()) return;
    $students = abn_get_students();
    $total_students = count($students);
    $totals = abn_fee_totals();
    $att = abn_attendance_today();
    $att_pct = $att['marked'] ? $att['pct'] : 0;
    $chart = abn_fee_chart();
    $max = max($chart) ?: 1;
    $recent = array_slice($students, 0, 5);
    // Pending fees (latest 5).
    $pending = [];
    foreach (abn_get_fees() as $f) {
        $m = abn_fee_meta($f->ID);
        if ($m['status'] === 'pending') $pending[] = [$f, $m];
        if (count($pending) >= 5) break;
    }
    abn_layout_open('Dashboard Overview', 'Welcome back — here is what is happening at ' . abn_school_name() . ' today.');
    echo '<section class="abn-kpis">';
    $kpis = [
        ['Total Students', $total_students, 'teal', 'Enrolled & active'],
        ['Fees Collected (' . date('M Y') . ')', abn_rs($totals['collected_month']), 'mustard', 'This month — total ' . abn_rs($totals['collected'])],
        ['Pending Fees', abn_rs($totals['pending']), 'orange', $totals['pending_count'] . ' challans unpaid'],
        ['Attendance Today', ($att['marked'] ? $att_pct . '%' : 'Not marked'), 'teal', $att['present'] . ' present of ' . $att['total']],
    ];
    foreach ($kpis as $k) {
        echo '<div class="abn-card abn-kpi ' . esc_attr($k[2]) . '"><span class="abn-kpi-label">' . esc_html($k[0]) . '</span><strong>' . esc_html((string) $k[1]) . '</strong><small>' . esc_html($k[3]) . '</small></div>';
    }
    echo '</section>';
    echo '<section class="abn-grid">';
    echo '<div class="abn-card abn-chart-card"><h3>Fee Collection — Last 6 Months (real data)</h3><div class="abn-bars">';
    $i = 0;
    foreach ($chart as $label => $v) {
        $h = $max ? max(4, round(($v / $max) * 100)) : 4;
        $cls = ($i === 5) ? ' highlight' : '';
        echo '<div class="abn-bar-wrap"><div class="abn-bar' . $cls . '" style="height:' . esc_attr($h) . '%" title="' . esc_attr(abn_rs($v)) . '"></div><span>' . esc_html($label) . '</span></div>';
        $i++;
    }
    echo '</div><p class="abn-note">Total collected so far: ' . esc_html(abn_rs($totals['collected'])) . '.</p></div>';
    echo '<div class="abn-card abn-donut-card"><h3>Attendance Today</h3><div class="abn-donut" style="--p:' . esc_attr($att_pct) . '"><span>' . esc_html($att_pct) . '%</span></div><p class="abn-note">' . ($att['marked'] ? esc_html($att['present'] . ' present of ' . $att['total'] . ' students.') : 'Attendance has not been marked for today yet.') . '</p></div>';
    echo '</section>';
    echo '<section class="abn-grid">';
    echo '<div class="abn-card"><h3>Recent Admissions</h3><table class="abn-table"><tr><th>Student</th><th>Class</th><th>Admission No</th></tr>';
    foreach ($recent as $r) {
        $m = abn_student_meta($r->ID);
        echo '<tr><td>' . esc_html($r->post_title) . '</td><td>' . esc_html($m['class'] . '-' . $m['section']) . '</td><td>' . esc_html($m['admission_no']) . '</td></tr>';
    }
    echo '</table></div>';
    echo '<div class="abn-card"><h3>Pending Fees <a class="abn-btn small" href="' . esc_url(admin_url('admin.php?page=abn-fees')) . '">View All</a></h3><table class="abn-table"><tr><th>Student</th><th>Month</th><th>Amount</th><th></th></tr>';
    foreach ($pending as $row) {
        list($f, $m) = $row;
        $msg = 'Assalam-o-Alaikum, ' . abn_student_name($m['student_id']) . ' ki ' . $m['month'] . ' fee ' . abn_rs($m['amount']) . ' pending hai. — ' . abn_school_name();
        $wa = abn_wa_link(abn_student_meta($m['student_id'])['phone'], $msg);
        echo '<tr><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><td>' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td><td><a class="abn-btn small orange" target="_blank" rel="noopener" href="' . esc_url($wa) . '">Remind</a></td></tr>';
    }
    echo '</table></div></section>';
    echo '<section class="abn-actions">';
    echo '<a class="abn-btn" href="' . esc_url(admin_url('admin.php?page=abn-students')) . '">+ Add Student</a>';
    echo '<a class="abn-btn" href="' . esc_url(admin_url('admin.php?page=abn-fees')) . '">Collect Fee</a>';
    echo '<a class="abn-btn" href="' . esc_url(admin_url('admin.php?page=abn-attendance')) . '">Mark Attendance</a>';
    echo '<a class="abn-btn outline" href="' . esc_url(admin_url('admin.php?page=abn-settings')) . '">Settings</a>';
    echo '</section>';
    abn_layout_close();
}

/* ---------- Students ---------- */
function abn_handle_student_post() {
    if (!isset($_POST['abn_student_nonce']) || !wp_verify_nonce(sanitize_key($_POST['abn_student_nonce']), 'abn_student')) return;
    $data = [
        'name' => sanitize_text_field($_POST['name'] ?? ''),
        'father' => sanitize_text_field($_POST['father'] ?? ''),
        'class' => sanitize_text_field($_POST['class'] ?? ''),
        'section' => sanitize_text_field($_POST['section'] ?? 'A'),
        'roll' => sanitize_text_field($_POST['roll'] ?? ''),
        'admission_no' => sanitize_text_field($_POST['admission_no'] ?? ''),
        'phone' => sanitize_text_field($_POST['phone'] ?? ''),
        'address' => sanitize_textarea_field($_POST['address'] ?? ''),
        'parent_user_id' => (int) ($_POST['parent_user_id'] ?? 0),
        'student_user_id' => (int) ($_POST['student_user_id'] ?? 0),
    ];
    if ($data['name'] === '' || $data['class'] === '') return;
    $edit_id = isset($_POST['edit_id']) ? (int) $_POST['edit_id'] : 0;
    if ($edit_id) {
        wp_update_post(['ID' => $edit_id, 'post_title' => $data['name']]);
        $id = $edit_id;
        abn_notice('Student updated.');
    } else {
        $id = wp_insert_post(['post_type' => 'abn_student', 'post_title' => $data['name'], 'post_status' => 'publish']);
        if (!$data['admission_no']) $data['admission_no'] = 'ABN-' . date('Y') . '-' . str_pad($id, 3, '0', STR_PAD_LEFT);
        update_post_meta($id, '_abn_admission_date', date('Y-m-d'));
        update_post_meta($id, '_abn_status', 'active');
        abn_notice('Student added successfully.');
    }
    update_post_meta($id, '_abn_father', $data['father']);
    update_post_meta($id, '_abn_class', $data['class']);
    update_post_meta($id, '_abn_section', $data['section']);
    update_post_meta($id, '_abn_roll', $data['roll']);
    update_post_meta($id, '_abn_admission_no', $data['admission_no']);
    update_post_meta($id, '_abn_phone', $data['phone']);
    update_post_meta($id, '_abn_address', $data['address']);
    update_post_meta($id, '_abn_parent_user_id', $data['parent_user_id']);
    update_post_meta($id, '_abn_student_user_id', $data['student_user_id']);
}

function abn_render_students() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['abn_add_student'])) abn_handle_student_post();
    if (isset($_GET['delete']) && wp_verify_nonce(sanitize_key($_GET['_wpnonce'] ?? ''), 'abn_delete_student')) {
        wp_delete_post((int) $_GET['delete'], true);
        abn_notice('Student deleted.');
    }
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $filter_class = isset($_GET['class']) ? sanitize_text_field($_GET['class']) : '';
    $edit_id = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
    $edit_meta = $edit_id ? abn_student_meta($edit_id) : null;
    $edit_post = $edit_id ? get_post($edit_id) : null;
    $all = abn_get_students();
    $rows = array_filter($all, function ($p) use ($search, $filter_class) {
        $m = abn_student_meta($p->ID);
        if ($filter_class !== '' && $m['class'] !== $filter_class) return false;
        if ($search !== '' && stripos($p->post_title . ' ' . $m['father'] . ' ' . $m['admission_no'], $search) === false) return false;
        return true;
    });
    abn_layout_open('Students & Admissions', 'Add, edit and search students. Admission form below.');
    echo '<div class="abn-card"><h3>' . ($edit_id ? 'Edit Student' : 'Add New Student / Admission Form') . '</h3><form method="post" class="abn-form">';
    wp_nonce_field('abn_student', 'abn_student_nonce');
    if ($edit_id) echo '<input type="hidden" name="edit_id" value="' . esc_attr($edit_id) . '">';
    $fields = [
        ['name', 'Full Name', $edit_post ? $edit_post->post_title : ''],
        ['father', 'Father Name', $edit_meta['father'] ?? ''],
        ['roll', 'Roll No', $edit_meta['roll'] ?? ''],
        ['admission_no', 'Admission No (auto if empty)', $edit_meta['admission_no'] ?? ''],
        ['phone', 'Phone (guardian)', $edit_meta['phone'] ?? ''],
        ['address', 'Address', $edit_meta['address'] ?? ''],
    ];
    foreach ($fields as $f) {
        echo '<label>' . esc_html($f[1]) . '<input type="text" name="' . esc_attr($f[0]) . '" value="' . esc_attr($f[2]) . '"' . ($f[0] === 'name' ? ' required' : '') . '></label>';
    }
    echo '<label>Class<select name="class" required><option value="">Select class</option>';
    foreach (abn_classes() as $c) {
        echo '<option value="' . esc_attr($c) . '"' . selected(($edit_meta['class'] ?? ''), $c, false) . '>' . esc_html($c) . '</option>';
    }
    echo '</select></label>';
    echo '<label>Section<select name="section">';
    foreach (['A', 'B', 'C'] as $sec) echo '<option value="' . esc_attr($sec) . '"' . selected(($edit_meta['section'] ?? 'A'), $sec, false) . '>' . esc_html($sec) . '</option>';
    echo '</select></label>';
    $parents = get_users(['role' => 'abn_parent', 'orderby' => 'display_name', 'order' => 'ASC']);
    $students_u = get_users(['role' => 'abn_student', 'orderby' => 'display_name', 'order' => 'ASC']);
    echo '<label>Parent Account (portal login)<select name="parent_user_id"><option value="0">— Not linked —</option>';
    foreach ($parents as $u) echo '<option value="' . esc_attr($u->ID) . '"' . selected(($edit_meta['parent_user_id'] ?? 0), $u->ID, false) . '>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
    echo '</select><small>Parent sees this child in the portal.</small></label>';
    echo '<label>Student Account (portal login)<select name="student_user_id"><option value="0">— Not linked —</option>';
    foreach ($students_u as $u) echo '<option value="' . esc_attr($u->ID) . '"' . selected(($edit_meta['student_user_id'] ?? 0), $u->ID, false) . '>' . esc_html($u->display_name . ' (' . $u->user_login . ')') . '</option>';
    echo '</select><small>Student sees only their own record.</small></label>';
    echo '<button class="abn-btn" name="abn_add_student" value="1">' . ($edit_id ? 'Update Student' : 'Add Student') . '</button>';
    if ($edit_id) echo ' <a class="abn-btn outline" href="' . esc_url(admin_url('admin.php?page=abn-students')) . '">Cancel</a>';
    echo '</form></div>';
    echo '<div class="abn-card"><h3>Student List (' . count($rows) . ')</h3>';
    echo '<form method="get" class="abn-filter"><input type="hidden" name="page" value="abn-students">';
    echo '<input type="text" name="s" placeholder="Search name / father / admission no" value="' . esc_attr($search) . '">';
    echo '<select name="class"><option value="">All classes</option>';
    foreach (abn_classes() as $c) echo '<option value="' . esc_attr($c) . '"' . selected($filter_class, $c, false) . '>' . esc_html($c) . '</option>';
    echo '</select><button class="abn-btn small">Filter</button></form>';
    echo '<table class="abn-table"><tr><th>Name</th><th>Class</th><th>Roll No</th><th>Admission No</th><th>Father</th><th>Phone</th><th>Fee (monthly)</th><th>Portal Accounts</th><th></th></tr>';
    foreach ($rows as $r) {
        $m = abn_student_meta($r->ID);
        $edit_url = admin_url('admin.php?page=abn-students&edit=' . $r->ID);
        $del_url = wp_nonce_url(admin_url('admin.php?page=abn-students&delete=' . $r->ID), 'abn_delete_student');
        $linked = [];
        if ($m['parent_user_id']) { $pu = get_user_by('id', $m['parent_user_id']); if ($pu) $linked[] = 'Parent: ' . $pu->user_login; }
        if ($m['student_user_id']) { $su = get_user_by('id', $m['student_user_id']); if ($su) $linked[] = 'Student: ' . $su->user_login; }
        echo '<tr><td>' . esc_html($r->post_title) . '</td><td>' . esc_html($m['class'] . '-' . $m['section']) . '</td><td>' . esc_html($m['roll']) . '</td><td>' . esc_html($m['admission_no']) . '</td><td>' . esc_html($m['father']) . '</td><td>' . esc_html($m['phone']) . '</td><td>' . esc_html(abn_rs(abn_fee_for_class($m['class']))) . '</td><td>' . esc_html($linked ? implode(' · ', $linked) : '—') . '</td>';
        echo '<td><a href="' . esc_url($edit_url) . '">Edit</a> · <a href="' . esc_url($del_url) . '" onclick="return confirm(\'Delete this student?\')">Delete</a></td></tr>';
    }
    echo '</table></div>';
    abn_layout_close();
}

/* ---------- Fees & Challans ---------- */
function abn_render_fees() {
    if (!current_user_can('manage_options')) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['abn_new_fee']) && wp_verify_nonce(sanitize_key($_POST['abn_fee_nonce'] ?? ''), 'abn_fee')) {
            $fid = abn_create_fee((int) $_POST['student_id'], sanitize_text_field($_POST['month']), (int) $_POST['amount']);
            if ($fid) abn_notice('Fee challan created.');
        }
        if (isset($_POST['abn_collect']) && wp_verify_nonce(sanitize_key($_POST['abn_fee_nonce'] ?? ''), 'abn_fee')) {
            abn_mark_fee_paid((int) $_POST['fee_id']);
            abn_notice('Fee marked as paid.');
        }
    }
    $totals = abn_fee_totals();
    $all_students = abn_get_students();
    $rows = abn_get_fees();
    abn_layout_open('Fees & Challans', 'Collect fees, record payments and print challans / receipts. Collected: ' . abn_rs($totals['collected']) . ' · Pending: ' . abn_rs($totals['pending']) . '.');
    echo '<div class="abn-card"><h3>Create Fee Challan</h3><form method="post" class="abn-form">';
    wp_nonce_field('abn_fee', 'abn_fee_nonce');
    echo '<label>Student<select name="student_id">';
    foreach ($all_students as $s) {
        $m = abn_student_meta($s->ID);
        echo '<option value="' . esc_attr($s->ID) . '">' . esc_html($s->post_title . ' (' . $m['class'] . '-' . $m['section'] . ')') . '</option>';
    }
    echo '</select></label><label>Month<input type="text" name="month" value="' . esc_attr(date('F Y')) . '" required></label>';
    echo '<label>Amount (Rs.)<input type="number" name="amount" value="2500" required></label>';
    echo '<button class="abn-btn" name="abn_new_fee" value="1">Create Challan</button></form>';
    echo '<p class="abn-note">Monthly fee defaults (Settings): Nursery–KG ' . esc_html(abn_rs(abn_opt('fee_nursery', 2000))) . ' · Primary ' . esc_html(abn_rs(abn_opt('fee_primary', 2500))) . ' · Middle ' . esc_html(abn_rs(abn_opt('fee_middle', 3500))) . ' · Matric ' . esc_html(abn_rs(abn_opt('fee_matric', 4500))) . '.</p></div>';
    echo '<div class="abn-card"><h3>Fee Records (' . count($rows) . ')</h3><table class="abn-table"><tr><th>Student</th><th>Month</th><th>Amount</th><th>Status</th><th>Paid Date</th><th>Actions</th></tr>';
    foreach ($rows as $r) {
        $m = abn_fee_meta($r->ID);
        $pill = ($m['status'] === 'paid') ? 'paid' : 'pending';
        $challan_url = home_url('/?abn_print=challan&fee=' . $r->ID);
        echo '<tr><td>' . esc_html(abn_student_name($m['student_id'])) . '</td><td>' . esc_html($m['month']) . '</td><td>' . esc_html(abn_rs($m['amount'])) . '</td><td><span class="abn-pill-status ' . esc_attr($pill) . '">' . esc_html(ucfirst($m['status'])) . '</span></td><td>' . esc_html($m['paid_date'] ?: '—') . '</td><td>';
        echo '<a class="abn-btn small outline" target="_blank" rel="noopener" href="' . esc_url($challan_url) . '">Print Challan</a> ';
        if ($m['status'] !== 'paid') {
            echo '<form method="post" style="display:inline">' . wp_nonce_field('abn_fee', 'abn_fee_nonce', true, false) . '<input type="hidden" name="fee_id" value="' . esc_attr($r->ID) . '"><button class="abn-btn small" name="abn_collect" value="1">Mark Paid</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</table></div>';
    abn_layout_close();
}
