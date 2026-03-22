<?php
/* Template Name: Advanced Plan */
get_header(); ?>
<main>
  <section class="page-hero hero">
    <div class="container">
      <span class="eyebrow fit">一番人気</span>
      <h1>Advancedプラン</h1>
      <p class="lead">日常的に記事更新を行うサイト向け。最もバランスの取れた主力プランです。</p>
    </div>
  </section>
  <section class="section">
    <div class="container">
      <div class="section-header"><div><h2>料金と支払い方法</h2><p>用途に応じて3つの支払い方法から選べます</p></div></div>
      <div class="pricing-wrap">
        <article class="pricing-card featured"><span class="badge">おすすめ</span><div class="plan-name">年額プラン</div><div class="price">9,800円 <small>／ 年</small></div><p>一番お得で、継続運用に最適なプランです。</p><ul><li>月額より大幅にお得</li><li>継続的な記事運用に最適</li><li>コストパフォーマンス重視の方におすすめ</li></ul><div class="price-sub">月額換算：約 <strong>817円</strong></div><a class="btn btn-primary" href="#">今すぐ年額で始める</a></article>
        <article class="pricing-card"><span class="badge">気軽に始める</span><div class="plan-name">月額プラン</div><div class="price">1,480円 <small>／ 月</small></div><p>まず試したい方向けのプランです。</p><ul><li>初期コストを抑えて開始可能</li><li>短期間の利用にも対応</li></ul><a class="btn btn-secondary" href="#">月額で始める</a></article>
        <article class="pricing-card"><span class="badge">長期利用向け</span><div class="plan-name">買い切りプラン</div><div class="price">24,800円</div><p>長期的に利用する方におすすめです。</p><ul><li>一度の支払いで利用可能</li><li>長期利用なら最もお得</li></ul><a class="btn btn-secondary" href="#">買い切りで購入</a></article>
      </div>
    </div>
  </section>
  <section class="section">
    <div class="container"><div class="cta-box"><h2>どのプランを選べばいい？</h2><p>長く使う予定なら年額プランがおすすめです。まず試したい方は月額から始めることもできます。</p><div class="hero-actions"><a class="btn btn-primary" href="#">年額プランで始める</a><a class="btn btn-secondary" href="<?php echo esc_url(home_url('/#pricing')); ?>">プラン一覧に戻る</a></div></div></div>
  </section>
</main>
<?php get_footer(); ?>
