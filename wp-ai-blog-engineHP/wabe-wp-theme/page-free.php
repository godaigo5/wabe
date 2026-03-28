<?php

/**
 * Template Name: Free Plan
 */

if (!defined('ABSPATH')) exit;

get_header();
?>

<style>
  .wabe-plan-page {
    background: linear-gradient(180deg, #f8fbff 0%, #ffffff 100%);
    color: #111827;
  }

  .wabe-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }

  .wabe-hero {
    padding: 88px 0 72px;
  }

  .wabe-hero__grid {
    display: grid;
    grid-template-columns: 1.1fr .9fr;
    gap: 36px;
    align-items: center;
  }

  .wabe-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    padding: 0 14px;
    border-radius: 999px;
    background: #eaf3ff;
    color: #2563eb;
    font-size: 13px;
    font-weight: 700;
  }

  .wabe-hero h1 {
    margin: 18px 0 16px;
    font-size: 48px;
    line-height: 1.2;
    letter-spacing: -.02em;
  }

  .wabe-hero__lead {
    margin: 0 0 24px;
    font-size: 18px;
    line-height: 1.9;
    color: #4b5563;
  }

  .wabe-checks {
    display: grid;
    gap: 12px;
    margin: 0 0 28px;
    padding: 0;
    list-style: none;
  }

  .wabe-checks li {
    position: relative;
    padding-left: 28px;
    color: #374151;
    line-height: 1.8;
  }

  .wabe-checks li::before {
    content: "✓";
    position: absolute;
    top: 0;
    left: 0;
    color: #2563eb;
    font-weight: 800;
  }

  .wabe-btns {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
  }

  .wabe-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 52px;
    padding: 0 22px;
    border-radius: 14px;
    text-decoration: none;
    font-weight: 700;
    transition: .25s ease;
  }

  .wabe-btn--primary {
    background: #2563eb;
    color: #fff;
  }

  .wabe-btn--secondary {
    background: #e5edff;
    color: #1d4ed8;
  }

  .wabe-btn:hover {
    transform: translateY(-1px);
    opacity: .95;
  }

  .wabe-panel {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, .06);
    padding: 28px;
  }

  .wabe-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
  }

  .wabe-panel__head h2 {
    margin: 0;
    font-size: 22px;
  }

  .wabe-price {
    display: inline-flex;
    align-items: baseline;
    gap: 8px;
    font-weight: 800;
    color: #111827;
  }

  .wabe-price strong {
    font-size: 42px;
    line-height: 1;
  }

  .wabe-price span {
    color: #6b7280;
    font-size: 14px;
  }

  .wabe-feature-list {
    display: grid;
    gap: 12px;
    margin: 0 0 22px;
    padding: 0;
    list-style: none;
  }

  .wabe-feature-list li {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #eef2f7;
    font-size: 15px;
  }

  .wabe-feature-list li strong {
    color: #111827;
  }

  .wabe-panel__note {
    margin: 0;
    padding: 16px 18px;
    border-radius: 16px;
    background: #f8fbff;
    color: #475569;
    line-height: 1.8;
    font-size: 14px;
  }

  .wabe-section {
    padding: 78px 0;
  }

  .wabe-section--white {
    background: #fff;
  }

  .wabe-section--soft {
    background: #f8fbff;
  }

  .wabe-section__head {
    text-align: center;
    margin-bottom: 42px;
  }

  .wabe-section__head h2 {
    margin: 0 0 14px;
    font-size: 36px;
    line-height: 1.35;
  }

  .wabe-section__head p {
    margin: 0;
    color: #4b5563;
    line-height: 1.9;
  }

  .wabe-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }

  .wabe-card {
    padding: 28px 24px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .05);
  }

  .wabe-card__icon {
    width: 52px;
    height: 52px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eff6ff;
    color: #2563eb;
    font-size: 24px;
    margin-bottom: 16px;
  }

  .wabe-card h3 {
    margin: 0 0 12px;
    font-size: 22px;
  }

  .wabe-card p {
    margin: 0;
    color: #4b5563;
    line-height: 1.9;
  }

  .wabe-steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 24px;
  }

  .wabe-step {
    position: relative;
    padding: 30px 24px 24px;
    border-radius: 24px;
    border: 1px solid #e5e7eb;
    background: #fff;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .05);
  }

  .wabe-step__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 52px;
    height: 52px;
    border-radius: 999px;
    background: #2563eb;
    color: #fff;
    font-size: 18px;
    font-weight: 800;
    margin-bottom: 16px;
  }

  .wabe-step h3 {
    margin: 0 0 10px;
    font-size: 22px;
  }

  .wabe-step p {
    margin: 0;
    color: #4b5563;
    line-height: 1.9;
  }

  .wabe-compare {
    overflow-x: auto;
    border-radius: 24px;
    background: #fff;
    border: 1px solid #e5e7eb;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .05);
  }

  .wabe-compare table {
    width: 100%;
    min-width: 760px;
    border-collapse: collapse;
  }

  .wabe-compare th,
  .wabe-compare td {
    padding: 18px 16px;
    border-bottom: 1px solid #eef2f7;
    font-size: 15px;
  }

  .wabe-compare thead th {
    background: #f8fafc;
    text-align: center;
  }

  .wabe-compare thead th:first-child,
  .wabe-compare tbody th {
    text-align: left;
  }

  .wabe-compare .is-advanced {
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 700;
  }

  .wabe-faq {
    display: grid;
    gap: 16px;
    max-width: 860px;
    margin: 0 auto;
  }

  .wabe-faq details {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    padding: 20px 22px;
  }

  .wabe-faq summary {
    cursor: pointer;
    font-weight: 700;
    list-style: none;
  }

  .wabe-faq summary::-webkit-details-marker {
    display: none;
  }

  .wabe-faq p {
    margin: 14px 0 0;
    color: #4b5563;
    line-height: 1.9;
  }

  .wabe-cta-box {
    text-align: center;
    padding: 40px 28px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    border-radius: 28px;
    border: 1px solid #dbeafe;
  }

  .wabe-cta-box h2 {
    margin: 0 0 14px;
    font-size: 34px;
  }

  .wabe-cta-box p {
    margin: 0 0 22px;
    color: #334155;
    line-height: 1.9;
  }

  @media (max-width: 991px) {

    .wabe-hero__grid,
    .wabe-grid-3,
    .wabe-steps {
      grid-template-columns: 1fr;
    }

    .wabe-hero h1 {
      font-size: 38px;
    }

    .wabe-section__head h2,
    .wabe-cta-box h2 {
      font-size: 30px;
    }
  }

  @media (max-width: 640px) {
    .wabe-wrap {
      padding: 0 16px;
    }

    .wabe-hero {
      padding: 72px 0 56px;
    }

    .wabe-hero h1 {
      font-size: 32px;
    }

    .wabe-hero__lead {
      font-size: 16px;
    }

    .wabe-section {
      padding: 64px 0;
    }

    .wabe-section__head h2,
    .wabe-cta-box h2 {
      font-size: 26px;
    }

    .wabe-panel,
    .wabe-card,
    .wabe-step {
      padding: 24px 20px;
    }
  }
</style>

<main class="wabe-plan-page">

  <section class="wabe-hero">
    <div class="wabe-wrap">
      <div class="wabe-hero__grid">
        <div>
          <span class="wabe-badge">まずはここから</span>
          <h1>Freeプランで、<br>AI記事作成を無料で試せます。</h1>
          <p class="wabe-hero__lead">
            WP AI Blog Engineの基本機能を、まずは無料で体験。
            自分のサイトに合うかを確認してから、必要に応じてAdvancedへ進めます。
          </p>

          <ul class="wabe-checks">
            <li>料金0円で始められる</li>
            <li>公開運用OK</li>
            <li>自動投稿あり</li>
            <li>アイキャッチ画像あり</li>
            <li>本文画像1枚対応</li>
          </ul>

          <div class="wabe-btns">
            <a class="wabe-btn wabe-btn--primary" href="#compare">Freeでできることを見る</a>
            <a class="wabe-btn wabe-btn--secondary"
              href="<?php echo esc_url(home_url('/advanced/')); ?>">Advancedを見る</a>
          </div>
        </div>

        <div>
          <div class="wabe-panel">
            <div class="wabe-panel__head">
              <h2>Freeプラン概要</h2>
              <div class="wabe-price"><strong>¥0</strong><span>無料で開始</span></div>
            </div>

            <ul class="wabe-feature-list">
              <li><strong>投稿数</strong><span>週1投稿</span></li>
              <li><strong>公開運用</strong><span>対応</span></li>
              <li><strong>自動投稿</strong><span>対応</span></li>
              <li><strong>見出し数</strong><span>3固定</span></li>
              <li><strong>トーン変更</strong><span>非対応</span></li>
              <li><strong>アイキャッチ画像</strong><span>対応</span></li>
              <li><strong>本文画像</strong><span>1枚</span></li>
            </ul>

            <p class="wabe-panel__note">
              まずは無料で使用感を確認し、更新頻度や機能が足りなくなったタイミングで
              Advancedへアップグレードする使い方がおすすめです。
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-section--white" id="compare">
    <div class="wabe-wrap">
      <div class="wabe-section__head">
        <span class="wabe-badge">Freeでできること</span>
        <h2>無料でも、しっかり試せる内容です</h2>
        <p>
          ただの体験版ではなく、AI記事作成の流れを実際に確認できる構成です。
          まずは導入し、操作感や生成結果、自分のサイトとの相性を見極められます。
        </p>
      </div>

      <div class="wabe-grid-3">
        <div class="wabe-card">
          <div class="wabe-card__icon">✍</div>
          <h3>記事生成を体験</h3>
          <p>
            タイトル・見出し・本文生成の流れを実際に試しながら、
            AIでどこまで記事作成を効率化できるか確認できます。
          </p>
        </div>

        <div class="wabe-card">
          <div class="wabe-card__icon">🧩</div>
          <h3>構成作成を確認</h3>
          <p>
            見出し構成や記事の流れを確認し、
            自分の運用スタイルに合うかを見ながら導入判断できます。
          </p>
        </div>

        <div class="wabe-card">
          <div class="wabe-card__icon">⚙</div>
          <h3>導入テストができる</h3>
          <p>
            WordPressへの導入や初期設定、
            自動投稿の流れまで含めて実運用前に試せます。
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-section--soft">
    <div class="wabe-wrap">
      <div class="wabe-section__head">
        <span class="wabe-badge">こんな方におすすめ</span>
        <h2>Freeプランは、まず試したい方に最適です</h2>
        <p>
          いきなり有料導入するのが不安な方でも、
          無料で使用感を確かめながら始められます。
        </p>
      </div>

      <div class="wabe-grid-3">
        <div class="wabe-card">
          <div class="wabe-card__icon">01</div>
          <h3>まずは試してみたい</h3>
          <p>
            いきなり有料は不安という方に。
            まずは無料で、プラグインの使いやすさを確認できます。
          </p>
        </div>

        <div class="wabe-card">
          <div class="wabe-card__icon">02</div>
          <h3>AI記事作成の流れを知りたい</h3>
          <p>
            どんな操作で記事が作られるのか、
            実際の管理画面を触りながら理解できます。
          </p>
        </div>

        <div class="wabe-card">
          <div class="wabe-card__icon">03</div>
          <h3>自分のサイトに合うか見たい</h3>
          <p>
            本格導入前に、更新フローや生成内容が
            自分のWordPress運用に合うかを確かめられます。
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-section--white">
    <div class="wabe-wrap">
      <div class="wabe-section__head">
        <span class="wabe-badge">使い方</span>
        <h2>Freeでも、導入から生成まで流れを確認できます</h2>
        <p>
          難しい設定を覚える前に、まずは基本の流れを試せます。
        </p>
      </div>

      <div class="wabe-steps">
        <div class="wabe-step">
          <div class="wabe-step__num">1</div>
          <h3>プラグインを導入</h3>
          <p>
            WordPressに導入し、必要なAPI設定を行います。
            まずは動作確認から始められます。
          </p>
        </div>

        <div class="wabe-step">
          <div class="wabe-step__num">2</div>
          <h3>題材を入れて生成</h3>
          <p>
            テーマや記事案をもとに、タイトル・見出し・本文の生成を試し、
            操作感を確認できます。
          </p>
        </div>

        <div class="wabe-step">
          <div class="wabe-step__num">3</div>
          <h3>公開運用を確認</h3>
          <p>
            実際の投稿フローや自動投稿の動きを見ながら、
            継続運用できそうかを判断できます。
          </p>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-section--soft">
    <div class="wabe-wrap">
      <div class="wabe-section__head">
        <span class="wabe-badge">アップグレード</span>
        <h2>無料で試して、必要になったらAdvancedへ</h2>
        <p>
          Freeは導入のハードルを下げるための入口です。
          より多く投稿したい、見出しやトーンを調整したい場合はAdvancedが最適です。
        </p>
      </div>

      <div class="wabe-compare">
        <table>
          <thead>
            <tr>
              <th>機能</th>
              <th>Free</th>
              <th class="is-advanced">Advanced</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th>投稿数</th>
              <td>週1</td>
              <td class="is-advanced">週7</td>
            </tr>
            <tr>
              <th>公開運用</th>
              <td>○</td>
              <td class="is-advanced">○</td>
            </tr>
            <tr>
              <th>自動投稿</th>
              <td>○</td>
              <td class="is-advanced">○</td>
            </tr>
            <tr>
              <th>見出し数</th>
              <td>3固定</td>
              <td class="is-advanced">拡張可能</td>
            </tr>
            <tr>
              <th>トーン変更</th>
              <td>—</td>
              <td class="is-advanced">○</td>
            </tr>
            <tr>
              <th>本文画像</th>
              <td>1枚</td>
              <td class="is-advanced">複数</td>
            </tr>
            <tr>
              <th>SEO機能</th>
              <td>—</td>
              <td class="is-advanced">○</td>
            </tr>
            <tr>
              <th>内部リンク補助</th>
              <td>—</td>
              <td class="is-advanced">○</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-section--white" id="faq">
    <div class="wabe-wrap">
      <div class="wabe-section__head">
        <span class="wabe-badge">FAQ</span>
        <h2>よくある質問</h2>
        <p>導入前によくある不安や疑問をまとめました。</p>
      </div>

      <div class="wabe-faq">
        <details>
          <summary>Q. 本当に無料ですか？</summary>
          <p>はい。Freeプランは料金なしで利用できます。</p>
        </details>

        <details>
          <summary>Q. 有料プランにしないと使えませんか？</summary>
          <p>いいえ。Freeプランでも基本機能は利用可能です。</p>
        </details>

        <details>
          <summary>Q. 途中でアップグレードできますか？</summary>
          <p>はい。必要に応じていつでもAdvancedへ移行できます。</p>
        </details>

        <details>
          <summary>Q. Freeでも公開運用できますか？</summary>
          <p>はい。Freeでも公開運用OKです。まずは小さく始めたい方にも向いています。</p>
        </details>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-section--soft">
    <div class="wabe-wrap">
      <div class="wabe-cta-box">
        <h2>まずはFreeで試してみませんか？</h2>
        <p>
          使用感を確認してから、必要に応じてAdvancedへ。
          無理なく始められるのがWP AI Blog EngineのFreeプランです。
        </p>
        <div class="wabe-btns" style="justify-content:center;">
          <a class="wabe-btn wabe-btn--primary"
            href="<?php echo esc_url(home_url('/contact/')); ?>">お問い合わせ</a>
          <a class="wabe-btn wabe-btn--secondary"
            href="<?php echo esc_url(home_url('/advanced/')); ?>">Advancedを見る</a>
        </div>
      </div>
    </div>
  </section>

</main>

<?php get_footer(); ?>
