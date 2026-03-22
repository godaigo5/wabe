<?php get_header(); ?>
<main>
  <section class="page-hero">
    <div class="container single-column">
      <span class="eyebrow fit">記事</span>
      <h1><?php the_title(); ?></h1>
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
