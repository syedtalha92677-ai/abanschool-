<?php
/* Abn School — front page template.
   Hero, live stats (real counts), editable intro, classes preview,
   latest notices and an admissions call to action. */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post();
?>
<div class="abn-page"><div class="abn-page-inner">
  <section class="abn-hero">
    <h1><?php echo esc_html(abn_school_name()); ?></h1>
    <p>Admissions Open for Session <?php echo esc_html(abn_academic_year()); ?> — Nursery to Class 10. Quality education, caring teachers, and parents kept informed every step of the way.</p>
    <a class="abn-btn" href="<?php echo esc_url(home_url('/admissions/')); ?>">Apply for Admission</a>
    <a class="abn-btn outline" href="<?php echo esc_url(home_url('/portal/')); ?>">School Portal</a>
  </section>

  <section class="abn-stats">
    <div class="abn-stat"><strong><?php echo esc_html(count(abn_get_students())); ?></strong><span>Students</span></div>
    <div class="abn-stat"><strong><?php echo esc_html(count(abn_get_staff())); ?></strong><span>Teachers &amp; Staff</span></div>
    <div class="abn-stat"><strong>12</strong><span>Classes (Nursery–10)</span></div>
    <div class="abn-stat"><strong><?php echo esc_html(abn_academic_year()); ?></strong><span>Academic Session</span></div>
  </section>

  <section class="abn-section">
    <?php the_content(); ?>
  </section>

  <section class="abn-section">
    <h2>Classes &amp; Monthly Fees</h2>
    <?php echo do_shortcode('[abn_classes]'); ?>
    <p><a class="abn-btn" href="<?php echo esc_url(home_url('/classes/')); ?>">See All Classes</a></p>
  </section>

  <section class="abn-section">
    <h2>Latest Announcements</h2>
    <?php echo do_shortcode('[abn_announcements count="3"]'); ?>
    <p><a class="abn-btn outline" href="<?php echo esc_url(home_url('/announcements/')); ?>">All Announcements</a></p>
  </section>
</div></div>
<?php
endwhile;
get_footer();
