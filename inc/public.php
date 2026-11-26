<?php
/* Abn School — public shortcodes for the website pages.
   All output is live data (settings, staff records, notices), never
   hardcoded, so editing in the admin changes the website immediately. */
if (!defined('ABSPATH')) exit;

/** [abn_classes] — classes Nursery–10 with monthly fee + core subjects. */
function abn_shortcode_classes() {
    $groups = [
        ['Nursery & KG', ['Nursery', 'KG'], 'Play-based learning, early reading & numbers'],
        ['Primary (1–5)', ['1', '2', '3', '4', '5'], 'English, Urdu, Mathematics, Science, Islamiat'],
        ['Middle (6–8)', ['6', '7', '8'], 'English, Urdu, Mathematics, Science, Islamiat, Computer'],
        ['Matric (9–10)', ['9', '10'], 'English, Urdu, Mathematics, Science, Islamiat, Computer, Pakistan Studies'],
    ];
    $html = '<div class="abn-class-list">';
    foreach ($groups as $g) {
        $fee = abn_fee_for_class($g[1][0]);
        $html .= '<div class="abn-class-card"><h3>' . esc_html($g[0]) . '</h3>'
            . '<p class="abn-class-fee">Monthly fee: <strong>' . esc_html(abn_rs($fee)) . '</strong></p>'
            . '<p>Classes: ' . esc_html(implode(', ', $g[1])) . '</p>'
            . '<p>' . esc_html($g[2]) . '</p></div>';
    }
    return $html . '</div><p class="abn-note">Fee amounts are demo defaults — the school confirms the final fee structure in Settings.</p>';
}
add_shortcode('abn_classes', 'abn_shortcode_classes');

/** [abn_teachers] — staff directory from the staff records. */
function abn_shortcode_teachers() {
    $staff = abn_get_staff();
    if (!$staff) return '<p class="abn-note">Teacher profiles will be added soon.</p>';
    $html = '<div class="abn-teacher-list">';
    foreach ($staff as $s) {
        $role = get_post_meta($s->ID, '_abn_role', true);
        $subject = get_post_meta($s->ID, '_abn_subject', true);
        $html .= '<div class="abn-teacher-card"><div class="abn-teacher-avatar">' . esc_html(mb_substr($s->post_title, 0, 1)) . '</div>'
            . '<h3>' . esc_html($s->post_title) . '</h3>'
            . '<p><strong>' . esc_html($role) . '</strong></p>'
            . '<p>' . esc_html($subject) . '</p></div>';
    }
    return $html . '</div>';
}
add_shortcode('abn_teachers', 'abn_shortcode_teachers');

/** [abn_announcements] — latest notices with dates. */
function abn_shortcode_announcements($atts = []) {
    $n = isset($atts['count']) ? (int) $atts['count'] : 10;
    $notices = abn_get_notices($n);
    if (!$notices) return '<p class="abn-note">No announcements yet.</p>';
    $html = '<div class="abn-notice-list">';
    foreach ($notices as $nt) {
        $html .= '<article class="abn-notice"><h3><a href="' . esc_url(get_permalink($nt->ID)) . '">' . esc_html($nt->post_title) . '</a></h3>'
            . '<p class="abn-notice-date">' . esc_html(get_the_date('', $nt->ID)) . '</p>'
            . '<p>' . esc_html(wp_trim_words(strip_tags($nt->post_content), 40)) . '</p></article>';
    }
    return $html . '</div>';
}
add_shortcode('abn_announcements', 'abn_shortcode_announcements');

/** [abn_admission_inquiry] — inquiry form; saves an abn_inquiry record
    that the admin reviews in Abn School → Admission Inquiries. */
function abn_shortcode_inquiry() {
    $done = false;
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['abn_inquiry_nonce'])) {
        if (!wp_verify_nonce(sanitize_key($_POST['abn_inquiry_nonce']), 'abn_inquiry')) {
            $error = 'Security check failed — please try again.';
        } else {
            $name = sanitize_text_field($_POST['child_name'] ?? '');
            $father = sanitize_text_field($_POST['father_name'] ?? '');
            $class = sanitize_text_field($_POST['apply_class'] ?? '');
            $phone = sanitize_text_field($_POST['phone'] ?? '');
            $email = sanitize_email($_POST['email'] ?? '');
            $message = sanitize_textarea_field($_POST['message'] ?? '');
            if ($name === '' || $class === '' || $phone === '') {
                $error = 'Please fill in the child name, class and phone number.';
            } else {
                $id = wp_insert_post([
                    'post_type' => 'abn_inquiry',
                    'post_title' => $name . ' — Class ' . $class,
                    'post_content' => $message,
                    'post_status' => 'publish',
                ]);
                if ($id && !is_wp_error($id)) {
                    update_post_meta($id, '_abn_child', $name);
                    update_post_meta($id, '_abn_father', $father);
                    update_post_meta($id, '_abn_class', $class);
                    update_post_meta($id, '_abn_phone', $phone);
                    update_post_meta($id, '_abn_email', $email);
                    update_post_meta($id, '_abn_date', date('Y-m-d'));
                    $done = true;
                } else {
                    $error = 'Your inquiry could not be saved — please try again or visit the school office.';
                }
            }
        }
    }
    if ($done) {
        return '<div class="abn-alert">Thank you — your admission inquiry has been received. The school office will contact you soon.</div>';
    }
    $html = '<form method="post" class="abn-form">';
    if ($error) $html .= '<div class="abn-alert warn">' . esc_html($error) . '</div>';
    $html .= wp_nonce_field('abn_inquiry', 'abn_inquiry_nonce', true, false);
    $html .= '<label>Child Full Name<input type="text" name="child_name" required></label>';
    $html .= '<label>Father / Guardian Name<input type="text" name="father_name"></label>';
    $html .= '<label>Class Applying For<select name="apply_class" required><option value="">Select class</option>';
    foreach (abn_classes() as $c) $html .= '<option value="' . esc_attr($c) . '">Class ' . esc_html($c) . '</option>';
    $html .= '</select></label>';
    $html .= '<label>Phone Number<input type="text" name="phone" required></label>';
    $html .= '<label>Email (optional)<input type="email" name="email"></label>';
    $html .= '<label style="grid-column:1/-1">Message (optional)<textarea name="message" rows="3"></textarea></label>';
    $html .= '<button class="abn-btn" type="submit">Send Admission Inquiry</button></form>';
    return $html;
}
add_shortcode('abn_admission_inquiry', 'abn_shortcode_inquiry');
