<?php

/**
 * Template Name: Free Plan
 */

if (!defined('ABSPATH')) exit;

get_header();
?>

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
