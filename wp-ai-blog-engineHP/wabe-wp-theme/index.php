<?php get_header(); ?>
<main>
  <section class="page-hero">
    <div class="container single-column">
      <span class="eyebrow fit">お知らせ</span>
      <h1><?php bloginfo('name'); ?></h1>
      <p class="lead"><?php bloginfo('description'); ?></p>
    </div>
  </section>
  <section class="entry-content">
    <div class="container single-column">
      <div class="entry-box">
        <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
          <article <?php post_class(); ?>>
            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
            <?php the_excerpt(); ?>
          </article>
        <?php endwhile; else : ?>
          <p>投稿はまだありません。</p>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php get_footer(); ?>
