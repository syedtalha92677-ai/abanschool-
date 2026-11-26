<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="abn-site-header">
  <div class="abn-header-inner">
    <div class="abn-brand">
      <?php $logo = abn_logo_url(); if ($logo) : ?><img class="abn-logo" src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr(abn_school_name()); ?> logo"><?php endif; ?>
      <a href="<?php echo esc_url(home_url('/')); ?>"><?php echo esc_html(abn_school_name()); ?></a>
      <span>Admissions Open <?php echo esc_html(abn_academic_year()); ?> · Nursery to Class 10</span>
    </div>
    <nav class="abn-nav" aria-label="Primary">
      <a href="<?php echo esc_url(home_url('/')); ?>">Home</a>
      <a href="<?php echo esc_url(home_url('/about/')); ?>">About</a>
      <a href="<?php echo esc_url(home_url('/admissions/')); ?>">Admissions</a>
      <a href="<?php echo esc_url(home_url('/classes/')); ?>">Classes</a>
      <a href="<?php echo esc_url(home_url('/teachers/')); ?>">Teachers</a>
      <a href="<?php echo esc_url(home_url('/announcements/')); ?>">Announcements</a>
      <a href="<?php echo esc_url(home_url('/contact/')); ?>">Contact</a>
      <a href="<?php echo esc_url(home_url('/portal/')); ?>">School Portal</a>
      <?php if (abn_has_real_whatsapp()) : ?>
      <a class="abn-nav-cta" href="<?php echo esc_url(abn_wa_link(abn_whatsapp(), 'Assalam-o-Alaikum, I want admission information for ' . abn_school_name() . '.')); ?>" target="_blank" rel="noopener">WhatsApp</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<main class="abn-site-main">
