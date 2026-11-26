<?php
/* Abn School — custom post types + postmeta.
   All school data lives here (no custom tables, no plugin). */
if (!defined('ABSPATH')) exit;

function abn_register_cpts() {
    $common = [
        'public'             => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui'            => false,
        'show_in_menu'       => false,
        'show_in_rest'       => false,
        'supports'           => ['title'],
    ];
    $types = [
        'abn_student'    => 'Students',
        'abn_fee'        => 'Fee Records',
        'abn_attendance' => 'Attendance Records',
        'abn_exam'       => 'Exams',
        'abn_mark'       => 'Marks',
        'abn_homework'   => 'Homework',
        'abn_staff'      => 'Staff',
        'abn_inquiry'    => 'Admission Inquiries',
    ];
    foreach ($types as $slug => $label) {
        $args = $common;
        $args['labels'] = ['name' => $label, 'singular_name' => rtrim($label, 's')];
        if ($slug === 'abn_homework') $args['supports'] = ['title', 'editor'];
        register_post_type($slug, $args);
    }
    /* Notices & announcements: public so they have their own pages, listed
       under the Abn School admin menu with the normal WordPress editor. */
    register_post_type('abn_notice', [
        'labels' => [
            'name' => 'Notices & Announcements',
            'singular_name' => 'Notice',
            'add_new_item' => 'Add New Notice',
            'edit_item' => 'Edit Notice',
        ],
        'public'             => true,
        'publicly_queryable' => true,
        'exclude_from_search' => false,
        'show_ui'            => true,
        'show_in_menu'       => 'abn-dashboard',
        'show_in_rest'       => false,
        'supports'           => ['title', 'editor'],
        'has_archive'        => false,
        'rewrite'            => ['slug' => 'notice'],
    ]);
}
add_action('init', 'abn_register_cpts');
