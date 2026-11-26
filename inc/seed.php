<?php
/* Abn School — demo data seeder.
   Clearly labelled demo data: ~24 students (Nursery–10), staff, fee records
   (Oct paid, Nov mixed), a week of attendance, one exam with marks,
   a timetable and homework entries.
   Idempotent: every record is checked before it is created, so re-running
   the seeder (or the full setup) never duplicates anything. */
if (!defined('ABSPATH')) exit;

function abn_demo_students() {
    // name, father, class, section, roll, phone
    return [
        ['Ayaan Malik', 'Bilal Malik', 'Nursery', 'A', 'N-01', '0300-2220001'],
        ['Hania Nawaz', 'Nawaz Sharif', 'Nursery', 'A', 'N-02', '0321-2220002'],
        ['Rayyan Shah', 'Shahid Iqbal', 'KG', 'A', 'K-01', '0333-2220003'],
        ['Eshal Zahra', 'Zahid Hussain', 'KG', 'A', 'K-02', '0345-2220004'],
        ['Arham Javed', 'Javed Akhtar', '1', 'A', '101', '0301-2220005'],
        ['Maham Noor', 'Noor Muhammad', '1', 'A', '102', '0322-2220006'],
        ['Zaid Anwar', 'Anwar Ali', '1', 'B', '103', '0334-2220007'],
        ['Iqra Batool', 'Batool Ahmed', '2', 'A', '201', '0308-2220008'],
        ['Fahad Qureshi', 'Qureshi Ahmed', '2', 'A', '202', '0300-2220009'],
        ['Alina Shahid', 'Shahid Mehmood', '3', 'A', '301', '0321-2220010'],
        ['Huzaifa Raza', 'Raza Ali', '3', 'A', '302', '0333-2220011'],
        ['Warda Khalid', 'Khalid Mehmood', '4', 'A', '401', '0345-2220012'],
        ['Saad Bin Tariq', 'Tariq Jameel', '4', 'B', '402', '0301-2220013'],
        ['Areeba Asif', 'Asif Iqbal', '5', 'A', '501', '0322-2220014'],
        ['Musa Kaleem', 'Kaleem Ullah', '5', 'A', '502', '0334-2220015'],
        ['Javeria Amin', 'Amin Ullah', '6', 'A', '601', '0308-2220016'],
        ['Umar Farooq', 'Farooq Azam', '6', 'A', '602', '0300-2220017'],
        ['Laiba Siddiq', 'Siddiq Ahmed', '7', 'A', '701', '0321-2220018'],
        ['Hassan Nawaz', 'Nawaz Akhtar', '7', 'A', '702', '0333-2220019'],
        ['Anum Tariq', 'Tariq Aziz', '8', 'A', '801', '0345-2220020'],
        ['Ahmed Bilal', 'Bilal Ahmed', '8', 'A', '802', '0301-2220021'],
        ['Sara Imran', 'Imran Yousuf', '9', 'A', '901', '0322-2220022'],
        ['Ali Raza', 'Raza Hussain', '9', 'A', '902', '0334-2220023'],
        ['Zainab Akhtar', 'Akhtar Hussain', '10', 'A', '1001', '0308-2220024'],
    ];
}

/* ---------- Idempotent lookups (check-before-create) ---------- */
function abn_find_student_by_roll($class, $roll) {
    $q = new WP_Query([
        'post_type' => 'abn_student', 'posts_per_page' => 1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [
            ['key' => '_abn_class', 'value' => $class],
            ['key' => '_abn_roll', 'value' => $roll],
        ],
    ]);
    return $q->posts ? (int) $q->posts[0] : 0;
}
function abn_find_fee($student_id, $month) {
    $q = new WP_Query([
        'post_type' => 'abn_fee', 'posts_per_page' => 1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [
            ['key' => '_abn_student_id', 'value' => (int) $student_id],
            ['key' => '_abn_month', 'value' => $month],
        ],
    ]);
    return $q->posts ? (int) $q->posts[0] : 0;
}
function abn_find_mark($exam_id, $student_id, $subject) {
    $q = new WP_Query([
        'post_type' => 'abn_mark', 'posts_per_page' => 1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [
            ['key' => '_abn_exam_id', 'value' => (int) $exam_id],
            ['key' => '_abn_student_id', 'value' => (int) $student_id],
            ['key' => '_abn_subject', 'value' => $subject],
        ],
    ]);
    return $q->posts ? (int) $q->posts[0] : 0;
}
function abn_find_homework($title, $class) {
    // Exact title + class match via $wpdb ('title' is not a WP_Query arg).
    global $wpdb;
    $id = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_abn_class' AND m.meta_value = %s WHERE p.post_type = 'abn_homework' AND p.post_title = %s AND p.post_status = 'publish' LIMIT 1",
        $class, $title
    ));
    return $id ? (int) $id : 0;
}

function abn_seed_demo_data() {
    $month_now = date('F Y');
    $month_prev = date('F Y', strtotime('-1 month'));
    $student_ids = [];
    $created_students = 0;
    $i = 0;
    foreach (abn_demo_students() as $s) {
        $i++;
        // Reuse the demo student if already seeded (class + roll is unique in the demo set).
        $id = abn_find_student_by_roll($s[2], $s[4]);
        if (!$id) {
            $id = wp_insert_post([
                'post_type'   => 'abn_student',
                'post_title'  => $s[0],
                'post_status' => 'publish',
            ]);
            update_post_meta($id, '_abn_father', $s[1]);
            update_post_meta($id, '_abn_class', $s[2]);
            update_post_meta($id, '_abn_section', $s[3]);
            update_post_meta($id, '_abn_roll', $s[4]);
            update_post_meta($id, '_abn_admission_no', 'ABN-' . date('Y') . '-' . str_pad($i, 3, '0', STR_PAD_LEFT));
            update_post_meta($id, '_abn_phone', $s[5]);
            update_post_meta($id, '_abn_address', 'Demo address — replace with real address');
            update_post_meta($id, '_abn_status', 'active');
            update_post_meta($id, '_abn_admission_date', date('Y-m-d', strtotime('-' . (30 + $i) . ' days')));
            $created_students++;
        }
        $student_ids[] = $id;
        // Fees: previous month paid for everyone; this month paid for 2 of 3, pending for 1 of 3.
        $amount = abn_fee_for_class($s[2]);
        $prev = abn_find_fee($id, $month_prev);
        if (!$prev) {
            $prev = abn_create_fee($id, $month_prev, $amount);
            abn_mark_fee_paid($prev);
        }
        $cur = abn_find_fee($id, $month_now);
        if (!$cur) {
            $cur = abn_create_fee($id, $month_now, $amount);
            if ($i % 3 !== 0) abn_mark_fee_paid($cur);
        }
    }
    // Attendance: last 5 weekdays (including today), mostly present.
    // abn_save_attendance() deletes that date's records first, so re-runs are safe.
    $days = [];
    $d = strtotime('today');
    while (count($days) < 5) {
        $w = date('N', $d);
        if ($w <= 5) $days[] = date('Y-m-d', $d);
        $d = strtotime('-1 day', $d);
    }
    foreach ($days as $date) {
        $statuses = [];
        foreach ($student_ids as $idx => $sid) {
            $st = 'present';
            if ((($idx + 1) + (int) date('d', strtotime($date))) % 11 === 0) $st = 'absent';
            elseif ((($idx + 1) + (int) date('d', strtotime($date))) % 13 === 0) $st = 'leave';
            $statuses[$sid] = $st;
        }
        abn_save_attendance($date, $statuses);
    }
    // Staff.
    $staff = [
        ['Nadia Rahman', 'Principal', 'All Classes', '0333-2221001', 'principal@abnschool.example'],
        ['Kashif Mehmood', 'Teacher', 'English', '0333-2221002', 'kashif@abnschool.example'],
        ['Rubina Akhtar', 'Teacher', 'Mathematics', '0333-2221003', 'rubina@abnschool.example'],
        ['Adnan Yousuf', 'Teacher', 'Science', '0333-2221004', 'adnan@abnschool.example'],
        ['Shazia Perveen', 'Teacher', 'Urdu', '0333-2221005', 'shazia@abnschool.example'],
        ['Waqas Ahmed', 'Teacher', 'Islamiat', '0333-2221006', 'waqas@abnschool.example'],
        ['Mehwish Tariq', 'Teacher', 'Computer', '0333-2221007', 'mehwish@abnschool.example'],
        ['Imran Haider', 'Office Clerk', 'Office', '0333-2221008', 'office@abnschool.example'],
    ];
    foreach ($staff as $t) {
        $existing = get_page_by_title($t[0], OBJECT, 'abn_staff');
        if ($existing) continue;
        $id = wp_insert_post(['post_type' => 'abn_staff', 'post_title' => $t[0], 'post_status' => 'publish']);
        update_post_meta($id, '_abn_role', $t[1]);
        update_post_meta($id, '_abn_subject', $t[2]);
        update_post_meta($id, '_abn_phone', $t[3]);
        update_post_meta($id, '_abn_email', $t[4]);
    }
    // Exam + marks (classes 6–10).
    $exam = get_page_by_title('First Term Examination 2026', OBJECT, 'abn_exam');
    $exam_id = $exam ? $exam->ID : 0;
    if (!$exam_id) {
        $exam_id = wp_insert_post(['post_type' => 'abn_exam', 'post_title' => 'First Term Examination 2026', 'post_status' => 'publish']);
        update_post_meta($exam_id, '_abn_class', '6–10');
        update_post_meta($exam_id, '_abn_date', date('Y-m-d', strtotime('-10 days')));
    }
    $subjects = ['English', 'Mathematics', 'Science', 'Urdu', 'Islamiat'];
    foreach (abn_get_students() as $stu) {
        $m = abn_student_meta($stu->ID);
        if (!is_numeric($m['class']) || (int) $m['class'] < 6) continue;
        foreach ($subjects as $si => $subj) {
            if (abn_find_mark($exam_id, $stu->ID, $subj)) continue;
            $marks = 62 + ((($stu->ID * 7) + ($si * 13)) % 35); // deterministic 62–96
            $id = wp_insert_post(['post_type' => 'abn_mark', 'post_title' => 'Marks — ' . $stu->post_title . ' — ' . $subj, 'post_status' => 'publish']);
            update_post_meta($id, '_abn_exam_id', $exam_id);
            update_post_meta($id, '_abn_student_id', $stu->ID);
            update_post_meta($id, '_abn_subject', $subj);
            update_post_meta($id, '_abn_marks', $marks);
            update_post_meta($id, '_abn_total', 100);
        }
    }
    // Timetable (classes 1–10, 6 periods x 5 days). Overwriting the option is idempotent.
    $tt = [];
    $subs = ['English', 'Mathematics', 'Science', 'Urdu', 'Islamiat', 'Computer', 'Social Studies', 'Art'];
    foreach (['1','2','3','4','5','6','7','8','9','10'] as $cls) {
        foreach (abn_timetable_days() as $di => $day) {
            for ($p = 0; $p < 6; $p++) {
                $tt[$cls][$day][$p] = $subs[(($p + $di + (int) $cls) % count($subs))];
            }
        }
    }
    update_option('abn_timetable', $tt);
    // Homework.
    $hw = [
        ['6', 'Mathematics', 'Exercise 4.2 — Questions 1 to 10', date('Y-m-d', strtotime('+2 days'))],
        ['8', 'Science', 'Draw and label the human digestive system', date('Y-m-d', strtotime('+3 days'))],
        ['10', 'English', 'Write an essay: "My Aim in Life" (250 words)', date('Y-m-d', strtotime('+4 days'))],
        ['4', 'Urdu', 'Learn lesson 5 and write 10 difficult words', date('Y-m-d', strtotime('+1 day'))],
        ['9', 'Islamiat', 'Memorise Surah Al-Ikhlas with translation', date('Y-m-d', strtotime('+5 days'))],
        ['3', 'Mathematics', 'Tables 2 to 8 — written practice', date('Y-m-d', strtotime('+2 days'))],
    ];
    foreach ($hw as $h) {
        if (abn_find_homework($h[2], $h[0])) continue;
        $id = wp_insert_post([
            'post_type'    => 'abn_homework',
            'post_title'   => $h[2],
            'post_content' => $h[2],
            'post_status' => 'publish',
        ]);
        update_post_meta($id, '_abn_class', $h[0]);
        update_post_meta($id, '_abn_subject', $h[1]);
        update_post_meta($id, '_abn_date', $h[3]);
    }
    // Demo portal accounts (clearly demo-only; delete them in Users when
    // real parents/students are added). Parent is linked to the first two
    // students, the student account to the first student, the teacher
    // account to the Mathematics teacher (classes 6, 7, 8).
    abn_seed_demo_users($student_ids);
    // Demo notices (flagged _abn_demo so Clear Demo Data removes only these).
    $notices = [
        ['Admissions Open — Session 2026–27', "Admissions are open from Nursery to Class 10 for session 2026–27. Please visit the school office or use the Admissions page to send an inquiry."],
        ['First Term Examination — Date Sheet', "The First Term Examination begins next month. The date sheet will be shared with students and parents in the portal."],
        ['Parent–Teacher Meeting', "A parent–teacher meeting will be held on the first Saturday of next month. Parents are requested to attend and discuss their child's progress."],
    ];
    foreach ($notices as $nt) {
        $exists = get_page_by_title($nt[0], OBJECT, 'abn_notice');
        if ($exists) continue;
        $nid = wp_insert_post(['post_type' => 'abn_notice', 'post_title' => $nt[0], 'post_content' => $nt[1], 'post_status' => 'publish']);
        update_post_meta($nid, '_abn_demo', 1);
    }
    update_option('abn_demo_seeded', 1);
    return ['seeded' => $created_students > 0, 'students' => count($student_ids)];
}

/** Delete all demo data (all abn_* posts + timetable). */
function abn_clear_demo_data() {
    global $wpdb;
    // All abn_* school records are demo-managed data — EXCEPT notices:
    // only demo-flagged notices are removed so real announcements survive.
    $ids = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type LIKE 'abn_%' AND post_type <> 'abn_notice'");
    foreach ($ids as $id) wp_delete_post((int) $id, true);
    $demo_notices = $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_abn_demo' AND m.meta_value = '1' WHERE p.post_type = 'abn_notice'");
    foreach ($demo_notices as $id) wp_delete_post((int) $id, true);
    $removed = count($ids) + count($demo_notices);
    // Remove the demo portal accounts too (they are flagged _abn_demo_user).
    $users = get_users(['meta_key' => '_abn_demo_user', 'meta_value' => '1', 'fields' => 'ID']);
    foreach ($users as $uid) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user((int) $uid);
    }
    delete_option('abn_timetable');
    delete_option('abn_demo_seeded');
    return $removed;
}

/** Create the demo portal accounts and link them to demo records.
    Passwords are demo-only; they are NOT written in the theme README —
    the installer receives them separately. Idempotent. */
function abn_seed_demo_users($student_ids) {
    if (!function_exists('abn_add_roles')) return;
    $make = function ($username, $email, $role, $password) {
        $u = get_user_by('login', $username);
        if ($u) return (int) $u->ID;
        $id = wp_insert_user([
            'user_login' => $username, 'user_email' => $email,
            'user_pass' => $password, 'role' => $role,
            'display_name' => $username,
        ]);
        if (is_wp_error($id) || !$id) return 0;
        update_user_meta($id, '_abn_demo_user', 1);
        return (int) $id;
    };
    $parent_id = $make('abn_demo_parent', 'parent@abnschool.example', 'abn_parent', 'demo-parent-2026');
    $student_id = $make('abn_demo_student', 'student@abnschool.example', 'abn_student', 'demo-student-2026');
    $teacher_id = $make('abn_demo_teacher', 'teacher@abnschool.example', 'abn_teacher', 'demo-teacher-2026');
    if ($parent_id && count($student_ids) >= 2) {
        update_post_meta($student_ids[0], '_abn_parent_user_id', $parent_id);
        update_post_meta($student_ids[1], '_abn_parent_user_id', $parent_id);
    }
    if ($student_id && !empty($student_ids[0])) {
        update_post_meta($student_ids[0], '_abn_student_user_id', $student_id);
    }
    if ($teacher_id) {
        // Link the teacher account to the Mathematics teacher record.
        $staff = get_page_by_title('Rubina Akhtar', OBJECT, 'abn_staff');
        if ($staff) {
            update_post_meta($staff->ID, '_abn_user_id', $teacher_id);
            update_post_meta($staff->ID, '_abn_classes', ['6', '7', '8']);
        }
    }
}
