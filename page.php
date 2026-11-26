<?php
/* Abn School — default page template. Renders the page content;
   public pages are built with the theme's own templates + shortcodes. */
if (!defined('ABSPATH')) exit;
get_header();
while (have_posts()) : the_post();
?>
<article class="abn-page">
  <div class="abn-page-inner">
    <?php the_content(); ?>
  </div>
</article>
<?php
endwhile;
get_footer();
