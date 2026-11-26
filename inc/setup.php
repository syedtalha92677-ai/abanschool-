<?php
/* Abn School — one-step auto-setup.
   On theme activation the theme runs the full setup: settings, roles,
   public pages, demo data. No plugins are installed — the theme is
   self-contained and dependency-free, so activation alone is the install.
   Every step is idempotent: re-running never duplicates anything.
   Failures are recorded in the abn_setup_status option and shown as an
   admin notice with a "Run Setup Again" button in Abn School → Settings. */
if (!defined('ABSPATH')) exit;

/** Full setup, in order. Safe to run repeatedly — nothing is duplicated. */
function abn_run_full_setup($source = 'activation') {
    if (function_exists('set_time_limit')) @set_time_limit(300);
    $status = [
        'time' => current_time('mysql'), 'source' => $source,
        'ok' => true, 'steps' => [], 'errors' => [],
    ];
    $save = function () use (&$status) {
        update_option('abn_setup_status', $status);
    };
    $step = function ($key, $label, $fn) use (&$status, $save) {
        try {
            $r = $fn();
            if (is_array($r) && array_key_exists('ok', $r)) {
                $ok = (bool) $r['ok'];
                $msg = (string) ($r['msg'] ?? $label);
            } else {
                $ok = true;
                $msg = $label;
            }
        } catch (Throwable $e) {
            $ok = false;
            $msg = $label . ' — error: ' . $e->getMessage();
        }
        $status['steps'][$key] = ['ok' => $ok, 'msg' => $msg];
        if (!$ok) {
            $status['ok'] = false;
            $status['errors'][] = $msg;
        }
        $save();
        return $ok;
    };

    // 1) Settings, site title, permalinks.
    $step('settings', 'School settings, site title and permalinks', function () {
        abn_ensure_settings();
        update_option('blogname', abn_school_name());
        if (!get_option('permalink_structure')) {
            update_option('permalink_structure', '/%postname%/');
        }
        return ['ok' => true, 'msg' => 'Settings, site title and pretty permalinks are set.'];
    });
    // 2) Roles (teacher / parent / student).
    $step('roles', 'Create teacher, parent and student roles', function () {
        abn_add_roles();
        return ['ok' => true, 'msg' => 'Roles ready: School Teacher, Parent, Student.'];
    });
    // 3) Pages.
    $step('pages', 'Create public pages', function () {
        $ids = abn_create_pages();
        return ['ok' => true, 'msg' => count($ids) . ' pages ready (Home, About, Admissions, Classes, Teachers, Announcements, Contact, Portal).'];
    });
    // 4) Demo data (per-record check-before-create inside the seeder).
    $step('seed', 'Seed demo data', function () {
        $r = abn_seed_demo_data();
        if (!empty($r['seeded'])) {
            return ['ok' => true, 'msg' => 'Demo data seeded (' . (int) $r['students'] . ' students).'];
        }
        return ['ok' => true, 'msg' => 'Demo data already present — nothing duplicated.'];
    });
    // 5) Rewrite rules last.
    flush_rewrite_rules();
    $save();
    return $status;
}

/** Admin notice when the setup is incomplete (a step failed). */
function abn_setup_admin_notice() {
    if (!current_user_can('manage_options')) return;
    if (isset($_GET['page']) && $_GET['page'] === 'abn-settings') return; // The Settings page shows the full status card.
    $status = get_option('abn_setup_status', null);
    $has_errors = is_array($status) && isset($status['ok']) && !$status['ok'];
    if (!$has_errors) return;
    echo '<div class="notice notice-error"><p><strong>Abn School setup is incomplete.</strong> '
        . esc_html($status['errors'][0] ?? '')
        . ' <a href="' . esc_url(admin_url('admin.php?page=abn-settings')) . '">Go to Abn School → Settings and click “Run Setup Again”.</a></p></div>';
}
add_action('admin_notices', 'abn_setup_admin_notice');
