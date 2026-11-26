<?php
/* Abn School — fallback template (posts / archives). */
if (!defined('ABSPATH')) exit;
get_header();
?>
<div class="abn-page"><div class="abn-page-inner">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
  <article>
    <h1><?php the_title(); ?></h1>
    <?php the_content(); ?>
  </article>
<?php endwhile; else : ?>
  <p>Nothing found.</p>
<?php endif; ?>
</div></div>
<?php
get_footer();
