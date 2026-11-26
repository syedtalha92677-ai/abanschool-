<?php
/* Abn School — roles & permissions.
   - administrator: full control (manage_options).
   - abn_teacher: attendance, marks entry, timetable & homework for their
     assigned classes (capability abn_manage_school). Linked to a staff
     record via staff meta _abn_user_id; assigned classes in _abn_classes.
   - abn_parent: front-end portal only, sees ONLY their own children
     (students with meta _abn_parent_user_id = their user ID).
   - abn_student: front-end portal only, sees ONLY their own record
     (student with meta _abn_student_user_id = their user ID).
   Parents/students are kept out of wp-admin (redirected to the portal). */
if (!defined('ABSPATH')) exit;

function abn_add_roles() {
    if (!get_role('abn_teacher')) {
        add_role('abn_teacher', 'School Teacher', [
            'read' => true,
            'abn_manage_school' => true,
        ]);
    } else {
        $r = get_role('abn_teacher');
        if ($r && !$r->has_cap('abn_manage_school')) $r->add_cap('abn_manage_school');
    }
    if (!get_role('abn_parent')) {
        add_role('abn_parent', 'Parent', ['read' => true]);
    }
    if (!get_role('abn_student')) {
        add_role('abn_student', 'Student', ['read' => true]);
    }
    // Administrators also get the school capability (harmless, keeps checks uniform).
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap('abn_manage_school')) $admin->add_cap('abn_manage_school');
}

/** Can this user work inside the school admin (admin or teacher)? */
function abn_user_can_manage() {
    return current_user_can('manage_options') || current_user_can('abn_manage_school');
}

/** Children (student post IDs) of a parent user. */
function abn_parent_children($user_id) {
    $q = new WP_Query([
        'post_type' => 'abn_student', 'posts_per_page' => -1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [['key' => '_abn_parent_user_id', 'value' => (int) $user_id]],
    ]);
    return array_map('intval', $q->posts);
}

/** Own student post ID of a student user (0 if none linked). */
function abn_student_record($user_id) {
    $q = new WP_Query([
        'post_type' => 'abn_student', 'posts_per_page' => 1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [['key' => '_abn_student_user_id', 'value' => (int) $user_id]],
    ]);
    return $q->posts ? (int) $q->posts[0] : 0;
}

/** Classes assigned to a teacher user (from their linked staff record). */
function abn_teacher_classes($user_id) {
    $q = new WP_Query([
        'post_type' => 'abn_staff', 'posts_per_page' => 1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [['key' => '_abn_user_id', 'value' => (int) $user_id]],
    ]);
    if (!$q->posts) return [];
    $classes = get_post_meta($q->posts[0], '_abn_classes', true);
    if (is_string($classes)) $classes = array_filter(array_map('trim', explode(',', $classes)));
    return is_array($classes) ? array_values($classes) : [];
}

/** Staff post ID linked to a teacher user (0 if none). */
function abn_teacher_staff_id($user_id) {
    $q = new WP_Query([
        'post_type' => 'abn_staff', 'posts_per_page' => 1, 'post_status' => 'publish',
        'fields' => 'ids',
        'meta_query' => [['key' => '_abn_user_id', 'value' => (int) $user_id]],
    ]);
    return $q->posts ? (int) $q->posts[0] : 0;
}

/**
 * What this user may see in the portal.
 * Returns ['type' => admin|teacher|parent|student, 'student_ids' => [...], 'classes' => [...]].
 * Admin/teacher see everything (teachers are additionally limited to their
 * assigned classes where a class filter applies).
 */
function abn_portal_scope() {
    $u = wp_get_current_user();
    if (!$u || !$u->ID) return ['type' => 'guest', 'student_ids' => [], 'classes' => []];
    if (current_user_can('manage_options')) return ['type' => 'admin', 'student_ids' => [], 'classes' => []];
    if (in_array('abn_teacher', (array) $u->roles, true)) {
        return ['type' => 'teacher', 'student_ids' => [], 'classes' => abn_teacher_classes($u->ID)];
    }
    if (in_array('abn_parent', (array) $u->roles, true)) {
        return ['type' => 'parent', 'student_ids' => abn_parent_children($u->ID), 'classes' => []];
    }
    if (in_array('abn_student', (array) $u->roles, true)) {
        $sid = abn_student_record($u->ID);
        return ['type' => 'student', 'student_ids' => $sid ? [$sid] : [], 'classes' => []];
    }
    return ['type' => 'other', 'student_ids' => [], 'classes' => []];
}

/** Parents & students belong in the front-end portal, not in wp-admin. */
function abn_redirect_portal_users() {
    if (!is_user_logged_in() || !is_admin()) return;
    if (current_user_can('manage_options') || current_user_can('abn_manage_school')) return;
    $u = wp_get_current_user();
    $roles = (array) $u->roles;
    if (!array_intersect($roles, ['abn_parent', 'abn_student'])) return;
    // Let profile + ajax through; everything else goes to the portal.
    $screen = isset($_SERVER['SCRIPT_NAME']) ? basename((string) $_SERVER['SCRIPT_NAME']) : '';
    if (in_array($screen, ['profile.php', 'admin-ajax.php', 'async-upload.php'], true)) return;
    if (defined('DOING_AJAX') && DOING_AJAX) return;
    wp_safe_redirect(home_url('/portal/'));
    exit;
}
add_action('admin_init', 'abn_redirect_portal_users');
