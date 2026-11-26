</main>
<footer class="abn-site-footer">
  <div class="abn-footer-inner">
    <div>
      <strong><?php echo esc_html(abn_school_name()); ?></strong>
      <p>Quality education — Nursery to Class 10 · Session <?php echo esc_html(abn_academic_year()); ?></p>
      <p><?php echo esc_html(abn_address()); ?></p>
    </div>
    <div>
      <p>Phone / WhatsApp: <?php if (abn_has_real_phone()) : ?><a href="<?php echo esc_url(abn_tel_link(abn_phone())); ?>"><?php echo esc_html(abn_phone()); ?></a><?php else : ?><?php echo esc_html(abn_phone()); ?><?php endif; ?></p>
      <p>Email: <?php if (strpos(abn_email1(), '[') === false && abn_email1() !== '') : ?><a href="mailto:<?php echo esc_attr(abn_email1()); ?>"><?php echo esc_html(abn_email1()); ?></a><?php else : ?><?php echo esc_html(abn_email1()); ?><?php endif; ?><?php if (abn_email2()) : ?><br><a href="mailto:<?php echo esc_attr(abn_email2()); ?>"><?php echo esc_html(abn_email2()); ?></a><?php endif; ?></p>
      <p><a href="<?php echo esc_url(home_url('/portal/')); ?>">School Portal</a> · <a href="<?php echo esc_url(admin_url()); ?>">Staff Login</a></p>
    </div>
  </div>
  <p class="abn-copy">&copy; <?php echo esc_html(date('Y')); ?> <?php echo esc_html(abn_school_name()); ?>. All rights reserved.</p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
