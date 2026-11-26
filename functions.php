<?php
/* Abn School — theme bootstrap.
   Complete school management system built into the theme:
   no plugin, no Elementor/WooCommerce dependency. WordPress is the
   platform; the theme itself is the whole school application. */
if (!defined('ABSPATH')) exit;

require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/cpt.php';
require_once get_template_directory() . '/inc/roles.php';
require_once get_template_directory() . '/inc/seed.php';
require_once get_template_directory() . '/inc/admin.php';
require_once get_template_directory() . '/inc/admin-modules.php';
require_once get_template_directory() . '/inc/portal.php';
require_once get_template_directory() . '/inc/public.php';
require_once get_template_directory() . '/inc/print.php';
require_once get_template_directory() . '/inc/setup.php';

/* ---------- Theme setup ---------- */
function abn_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    register_nav_menus(['primary' => 'Primary Menu']);
}
add_action('after_setup_theme', 'abn_setup');

function abn_front_assets() {
    wp_enqueue_style('abn-front', get_template_directory_uri() . '/assets/css/front.css', [], '1.0.0');
    // Portal reuses the admin card/table styles.
    if (is_page('portal') || has_shortcode((string) get_post_field('post_content', get_the_ID() ?: 0), 'abn_school_portal')) {
        wp_enqueue_style('abn-portal', get_template_directory_uri() . '/assets/css/admin.css', [], '1.0.0');
    }
}
add_action('wp_enqueue_scripts', 'abn_front_assets');

/* ---------- Default settings ---------- */
function abn_ensure_settings() {
    $opts = get_option('abn_settings', []);
    if (!is_array($opts)) $opts = [];
    $opts = array_merge(abn_defaults(), $opts);
    update_option('abn_settings', $opts);
}

/* ---------- Pages (created on activation) ---------- */
function abn_page_content($slug) {
    $name = abn_school_name();
    switch ($slug) {
        case 'home':
            return '<!-- wp:paragraph --><p>' . esc_html($name) . ' provides quality education from Nursery to Class 10 — experienced teachers, a caring environment, and regular updates for parents. Admissions are open for session ' . esc_html(abn_academic_year()) . '.</p><!-- /wp:paragraph -->';
        case 'about':
            return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">About ' . esc_html($name) . '</h1><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>' . esc_html($name) . ' is a school for Nursery to Class 10, focused on strong academics, good character and confident, curious students. Our teachers know every child by name, and parents are kept informed about attendance, fees, homework and results through the School Portal.</p><!-- /wp:paragraph -->'
                . '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">What we offer</h2><!-- /wp:heading -->'
                . '<!-- wp:list --><ul class="wp-block-list"><li>Classes from Nursery to Class 10 with experienced, caring teachers</li><li>Regular assessments, report cards and parent–teacher meetings</li><li>Homework diary and announcements in the parent &amp; student portal</li><li>Affordable monthly fees with printed challans and receipts</li></ul><!-- /wp:list -->';
        case 'admissions':
            return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Admissions Open ' . esc_html(abn_academic_year()) . '</h1><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>1. Visit the school office or send an admission inquiry below. 2. Bring the child&rsquo;s B-Form / birth certificate and 2 passport photos. 3. A short assessment is held for Classes 1 and above. 4. Pay the admission fee and first month&rsquo;s fee — admission is confirmed.</p><!-- /wp:paragraph -->'
                . '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Fee Structure (Monthly — demo defaults, to be confirmed)</h2><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>Nursery &amp; KG: Rs. 2,000 · Primary (1–5): Rs. 2,500 · Middle (6–8): Rs. 3,500 · Matric (9–10): Rs. 4,500.</p><!-- /wp:paragraph -->'
                . '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">Admission Inquiry</h2><!-- /wp:heading -->'
                . '<!-- wp:shortcode -->[abn_admission_inquiry]<!-- /wp:shortcode -->';
        case 'classes':
            return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Classes &amp; Courses</h1><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>From Nursery to Class 10 — every class follows the same core subjects with age-appropriate depth. Monthly fees are shown per class group.</p><!-- /wp:paragraph -->'
                . '<!-- wp:shortcode -->[abn_classes]<!-- /wp:shortcode -->';
        case 'teachers':
            return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Our Teachers</h1><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>Meet the teachers and staff of ' . esc_html($name) . '.</p><!-- /wp:paragraph -->'
                . '<!-- wp:shortcode -->[abn_teachers]<!-- /wp:shortcode -->';
        case 'announcements':
            return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Notices &amp; Announcements</h1><!-- /wp:heading -->'
                . '<!-- wp:shortcode -->[abn_announcements]<!-- /wp:shortcode -->';
        case 'contact':
            return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Contact ' . esc_html($name) . '</h1><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>Phone / WhatsApp: ' . esc_html(abn_phone()) . '<br>Email: ' . esc_html(abn_email1()) . '</p><!-- /wp:paragraph -->'
                . '<!-- wp:paragraph --><p>Address: ' . esc_html(abn_address()) . '</p><!-- /wp:paragraph -->';
        case 'portal':
            return '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">School Portal</h1><!-- /wp:heading -->'
                . '<!-- wp:paragraph --><p>Parents and students: log in to see attendance, fees, homework, exam results and announcements for your own children. Teachers and staff: log in for the dashboard, attendance, marks and homework tools.</p><!-- /wp:paragraph -->'
                . '<!-- wp:shortcode -->[abn_school_portal]<!-- /wp:shortcode -->';
    }
    return '';
}

function abn_create_pages() {
    $pages = [
        'home' => 'Home',
        'about' => 'About',
        'admissions' => 'Admissions',
        'classes' => 'Classes',
        'teachers' => 'Teachers',
        'announcements' => 'Announcements',
        'contact' => 'Contact',
        'portal' => 'School Portal',
    ];
    $ids = [];
    foreach ($pages as $slug => $title) {
        $page = get_page_by_path($slug);
        if ($page) { $ids[$slug] = $page->ID; continue; }
        $id = wp_insert_post([
            'post_type' => 'page', 'post_name' => $slug,
            'post_title' => $title, 'post_content' => abn_page_content($slug),
            'post_status' => 'publish',
        ]);
        if ($id && !is_wp_error($id)) $ids[$slug] = $id;
    }
    // Front page = Home.
    if (!empty($ids['home'])) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $ids['home']);
    }
    return $ids;
}

/* ---------- Activation (theme switch) ----------
   One-step setup: settings, roles, pages, demo data. Idempotent —
   re-activating (or "Run Setup Again" in Settings) never duplicates
   anything. No plugins are installed; the theme is self-contained. */
function abn_activate() {
    abn_run_full_setup('activation');
}
add_action('after_switch_theme', 'abn_activate');

/* ---------- Admin bar shortcut ---------- */
function abn_admin_bar($wp_admin_bar) {
    if (!abn_user_can_manage()) return;
    $wp_admin_bar->add_node([
        'id' => 'abn-school', 'title' => 'Abn School',
        'href' => admin_url('admin.php?page=abn-dashboard'),
    ]);
}
add_action('admin_bar_menu', 'abn_admin_bar', 40);
