<?php get_header(); ?>
<main>
  <section class="page-hero">
    <div class="container single-column">
      <span class="eyebrow fit"><?php echo esc_html(get_the_title()); ?></span>
      <h1><?php the_title(); ?></h1>
      <?php if (has_excerpt()) : ?>
        <p class="lead"><?php echo esc_html(get_the_excerpt()); ?></p>
      <?php endif; ?>
    </div>
  </section>
  <section class="entry-content">
    <div class="container single-column">
      <div class="entry-box">
        <?php while (have_posts()) : the_post(); the_content(); endwhile; ?>
      </div>
    </div>
  </section>
</main>
<?php get_footer(); ?>
