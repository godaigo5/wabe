<?php

/**
 * Front Page Template
 * Theme: wabe-wp-theme
 */
if (!defined('ABSPATH')) exit;
get_header();
?>

<main class="wabe-lp">
  <section class="wabe-hero">
    <div class="wabe-container">
      <div class="wabe-hero-grid">
        <div>
          <span class="wabe-eyebrow">WordPress AI Plugin</span>
          <h1>WordPressの記事更新を、AIで“続けられる”仕組みに。</h1>
          <p class="wabe-hero-lead">
            タイトル・見出し・本文・画像まで自動生成。<br>
            記事作成の手間を減らし、更新が止まりにくい運用へ。
          </p>
          <p class="wabe-hero-desc">
            WP AI Blog Engineは、WordPress運用で使いやすい実践型のAIプラグインです。まずはFreeで試し、
            しっかり運用したい方はAdvancedへ。将来的にはProで収益化機能まで広げられる設計です。
          </p>
          <div class="wabe-cta-row">
            <a class="wabe-btn wabe-btn-main" href="<?php echo esc_url(home_url('/free')); ?>">無料で試す</a>
            <a class="wabe-btn wabe-btn-sub" href="#pricing">料金プランを見る</a>
          </div>
          <div class="wabe-badges">
            <span>Freeあり</span>
            <span>自動投稿対応</span>
            <span>SEO記事生成</span>
            <span>WordPress実装型</span>
          </div>
        </div>

        <div>
          <div class="wabe-hero-card">
            <div class="wabe-hero-shot-top">
              <div class="wabe-hero-dots"><span></span><span></span><span></span></div>
              <div class="wabe-hero-title">WP AI Blog Engine 管理画面イメージ</div>
            </div>
            <div class="wabe-hero-shot-body">
              <div class="wabe-screen-block">
                <div class="wabe-screen-label">Queued Topic</div>
                <div class="wabe-screen-panel">
                  <div class="wabe-screen-topic">WordPress表示速度、劇的改善の5ステップ！</div>
                  <div class="wabe-screen-tags">
                    <span>トーン: カジュアル</span>
                    <span>見出し: 6</span>
                    <span>画像: ON</span>
                  </div>
                  <div class="wabe-screen-lines">
                    <span></span><span></span><span></span>
                  </div>
                </div>
              </div>
              <div class="wabe-screen-block">
                <div class="wabe-screen-label">What you can automate</div>
                <div class="wabe-mini-stats">
                  <div class="wabe-mini-stat">
                    <strong>Title</strong>
                    <span>タイトルと見出しを自動生成</span>
                  </div>
                  <div class="wabe-mini-stat">
                    <strong>Body</strong>
                    <span>本文と構成をまとめて生成</span>
                  </div>
                  <div class="wabe-mini-stat">
                    <strong>Image</strong>
                    <span>アイキャッチと本文画像に対応</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section">
    <div class="wabe-container">
      <div class="wabe-points">
        <div class="wabe-point">
          <strong>時短</strong>
          <p>毎回ゼロから考える負担を減らし、記事作成の着手を早くします。</p>
        </div>
        <div class="wabe-point">
          <strong>継続</strong>
          <p>更新が止まりがちなサイトでも、投稿を続けやすい流れを作れます。</p>
        </div>
        <div class="wabe-point">
          <strong>拡張</strong>
          <p>Freeから始めて、必要に応じてAdvancedやProへ段階的に広げられます。</p>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-problems" id="problems">
    <div class="wabe-container">
      <div class="wabe-section-head">
        <span class="wabe-eyebrow">Problem</span>
        <h2>こんな悩み、ありませんか？</h2>
        <p>WP AI Blog Engineは、記事更新が続かない原因になりやすい手間や迷いを減らすためのプラグインです。</p>
      </div>

      <div class="wabe-problem-grid">
        <div class="wabe-problem">
          <div class="wabe-problem-num">01</div>
          <div>
            <h3>毎回ゼロから記事を書くのが大変</h3>
            <p>タイトル案、見出し、本文まで考えるのに時間がかかり、更新が後回しになってしまう。</p>
          </div>
        </div>
        <div class="wabe-problem">
          <div class="wabe-problem-num">02</div>
          <div>
            <h3>SEOを意識すると作業が重くなる</h3>
            <p>検索を意識した構成や見出し作りまで含めると、1本の記事にかかる負担が大きくなる。</p>
          </div>
        </div>
        <div class="wabe-problem">
          <div class="wabe-problem-num">03</div>
          <div>
            <h3>AIを使ってもWordPress運用に組み込みづらい</h3>
            <p>別ツールで生成してコピペする流れだと手間が残り、継続運用しにくい。</p>
          </div>
        </div>
        <div class="wabe-problem">
          <div class="wabe-problem-num">04</div>
          <div>
            <h3>更新頻度を保ちたいが時間が足りない</h3>
            <p>企業ブログやオウンドメディアで、継続的に記事を増やしたくても手が回らない。</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section" id="manga_problems">
    <div class="wabe-container">
      <div class="wabe-manga-problem">
        <img class="wabe-manga-img pc-only"
          src="http://wabe.d-create.online/wp-content/uploads/2026/03/Gemini_Generated_Image_6keiwq6keiwq6kei.png"
          alt="記事更新しないと・・・">
        <img class="wabe-manga-img sp-only"
          src="http://wabe.d-create.online/wp-content/uploads/2026/03/Gemini_Generated_Image_tngk1utngk1utngk.png"
          alt="記事更新しないと・・・">
      </div>
    </div>
  </section>

  <section class="wabe-section" id="flow">
    <div class="wabe-container">
      <div class="wabe-section-head">
        <span class="wabe-eyebrow">Flow</span>
        <h2>使い方はシンプルな3ステップ</h2>
        <p>WordPressに組み込んで使えるから、別ツールを行き来する手間を減らしながら運用できます。</p>
      </div>

      <div class="wabe-steps-grid">
        <div class="wabe-step">
          <div class="wabe-step-no">STEP 1</div>
          <h3>題材を追加</h3>
          <p>書きたいテーマを登録。運用に合わせて題材候補を作り、予約題材としてためておけます。</p>
        </div>
        <div class="wabe-step">
          <div class="wabe-step-no">STEP 2</div>
          <h3>AIで記事生成</h3>
          <p>タイトル・見出し・本文・画像までまとめて生成。必要に応じてトーンや見出し数も調整できます。</p>
        </div>
        <div class="wabe-step">
          <div class="wabe-step-no">STEP 3</div>
          <h3>確認して公開 / 自動投稿</h3>
          <p>下書き確認も、自動投稿運用も可能。継続しやすい投稿フローを作れます。</p>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section" id="features">
    <div class="wabe-container">
      <div class="wabe-section-head">
        <span class="wabe-eyebrow">Features</span>
        <h2>WP AI Blog Engineでできること</h2>
        <p>記事作成のスタートから公開運用まで、WordPressで使いやすい機能をまとめています。</p>
      </div>

      <div class="wabe-features-grid">
        <div class="wabe-feature">
          <div class="wabe-icon">✍</div>
          <h3>タイトル・見出し生成</h3>
          <p>テーマに沿った記事タイトルや構成を生成し、記事作成の最初のハードルを下げます。</p>
        </div>
        <div class="wabe-feature">
          <div class="wabe-icon">📝</div>
          <h3>本文の自動生成</h3>
          <p>下書きベースを素早く作れるため、記事制作にかかる時間を短縮しやすくなります。</p>
        </div>
        <div class="wabe-feature">
          <div class="wabe-icon">🖼</div>
          <h3>画像生成・本文画像対応</h3>
          <p>アイキャッチや記事途中の画像にも対応し、見た目まで整った記事を作りやすくします。</p>
        </div>
        <div class="wabe-feature">
          <div class="wabe-icon">🔎</div>
          <h3>SEOを意識した構成補助</h3>
          <p>検索を意識した記事構成づくりを支援し、質の安定した記事運用につなげやすくします。</p>
        </div>
        <div class="wabe-feature">
          <div class="wabe-icon">🔗</div>
          <h3>内部リンク補助</h3>
          <p>関連ページ同士のつながりを作りやすくし、サイト全体の回遊性向上をサポートします。</p>
        </div>
        <div class="wabe-feature">
          <div class="wabe-icon">⏰</div>
          <h3>自動投稿に対応</h3>
          <p>手動確認だけでなく、自動投稿運用にも対応。投稿の継続を仕組み化しやすくします。</p>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section wabe-pricing" id="pricing">
    <div class="wabe-container">
      <div class="wabe-section-head">
        <span class="wabe-eyebrow">Pricing</span>
        <h2>目的に合わせて選べる3つのプラン</h2>
        <p>まずはFreeで使用感を確認し、しっかり運用したい方はAdvancedがおすすめです。</p>
      </div>

      <div class="wabe-pricing-cards">
        <div class="wabe-plan">
          <h3>Free</h3>
          <div class="wabe-price">¥0</div>
          <p class="wabe-subtext">まずは試したい方向け。無料でも公開運用と自動投稿に対応しています。</p>
          <ul class="wabe-price-list">
            <li>週1投稿</li>
            <li>公開OK</li>
            <li>自動投稿あり</li>
            <li>見出し3固定</li>
            <li>アイキャッチあり</li>
            <li>本文画像1枚</li>
          </ul>
          <div class="wabe-plan-links">
            <a class="wabe-btn wabe-btn-sub" href="<?php echo esc_url(home_url('/free')); ?>">無料で試す</a>
          </div>
        </div>

        <div class="wabe-plan wabe-plan-featured">
          <div class="wabe-plan-recommend">おすすめ</div>
          <h3>Advanced</h3>
          <div class="wabe-price">主力プラン</div>
          <p class="wabe-subtext">更新をしっかり回したい方向け。日常運用で一番バランスのよいプランです。</p>
          <ul class="wabe-price-list">
            <li>週7投稿</li>
            <li>見出し数を拡張可能</li>
            <li>トーン変更対応</li>
            <li>本文画像を複数挿入</li>
            <li>SEO機能対応</li>
            <li>内部リンク補助</li>
          </ul>
          <div class="wabe-plan-links">
            <small>月額 / 年額 / 買い切りに対応</small>
            <a class="wabe-btn wabe-btn-main"
              href="<?php echo esc_url(home_url('/advanced')); ?>">Advancedを見る</a>
          </div>
        </div>

        <div class="wabe-plan">
          <h3>Pro</h3>
          <div class="wabe-price">近日強化予定</div>
          <p class="wabe-subtext">収益化を強化したい方向け。将来的にアフィリエイト機能を追加予定です。</p>
          <ul class="wabe-price-list">
            <li>投稿数無制限</li>
            <li>自動投稿あり</li>
            <li>CTA / 広告表記対応予定</li>
            <li>リンク管理予定</li>
            <li>比較記事生成予定</li>
            <li>ランキング記事生成予定</li>
          </ul>
          <div class="wabe-plan-links">
            <a class="wabe-btn wabe-btn-sub" href="<?php echo esc_url(home_url('/pro')); ?>">Proを見る</a>
          </div>
        </div>
      </div>

      <div class="wabe-pricing-table-wrap">
        <table class="wabe-pricing-table">
          <thead>
            <tr>
              <th>機能</th>
              <th>Free</th>
              <th class="wabe-featured-col">Advanced</th>
              <th>Pro</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <th>投稿数</th>
              <td>週1</td>
              <td class="wabe-featured-col">週7</td>
              <td>無制限</td>
            </tr>
            <tr>
              <th>公開運用</th>
              <td>○</td>
              <td class="wabe-featured-col">○</td>
              <td>○</td>
            </tr>
            <tr>
              <th>自動投稿</th>
              <td>○</td>
              <td class="wabe-featured-col">○</td>
              <td>○</td>
            </tr>
            <tr>
              <th>見出し数</th>
              <td>3固定</td>
              <td class="wabe-featured-col">拡張可能</td>
              <td>拡張可能</td>
            </tr>
            <tr>
              <th>トーン変更</th>
              <td>—</td>
              <td class="wabe-featured-col">○</td>
              <td>○</td>
            </tr>
            <tr>
              <th>アイキャッチ画像</th>
              <td>○</td>
              <td class="wabe-featured-col">○</td>
              <td>○</td>
            </tr>
            <tr>
              <th>本文画像</th>
              <td>1枚</td>
              <td class="wabe-featured-col">複数</td>
              <td>複数</td>
            </tr>
            <tr>
              <th>SEO機能</th>
              <td>—</td>
              <td class="wabe-featured-col">○</td>
              <td>○</td>
            </tr>
            <tr>
              <th>内部リンク補助</th>
              <td>—</td>
              <td class="wabe-featured-col">○</td>
              <td>○</td>
            </tr>
            <tr>
              <th>収益化機能</th>
              <td>—</td>
              <td class="wabe-featured-col">—</td>
              <td>○</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="wabe-pricing-note">
        迷ったら <strong>Advanced</strong> がおすすめです。更新頻度と機能のバランスがよく、実運用しやすいプランです。
      </div>
    </div>
  </section>

  <section class="wabe-section" id="faq">
    <div class="wabe-container">
      <div class="wabe-section-head">
        <span class="wabe-eyebrow">FAQ</span>
        <h2>よくある質問</h2>
        <p>購入前に気になりやすいポイントを、先に確認できるようにまとめています。</p>
      </div>

      <div class="wabe-faq-wrap">
        <div class="wabe-faq-item">
          <div class="wabe-faq-q">Q. まず無料で試せますか？</div>
          <p>A. はい。まずはFreeプランで使用感を確認し、その後に必要に応じてAdvancedやProをご検討いただけます。</p>
        </div>
        <div class="wabe-faq-item">
          <div class="wabe-faq-q">Q. 購入後はどうやって利用しますか？</div>
          <p>A. 決済後、ライセンスキーがメールで届きます。会員ページでドメインを有効化し、プラグインに入力するだけで利用開始できます。</p>
        </div>
        <div class="wabe-faq-item">
          <div class="wabe-faq-q">Q. 無料プランでも公開運用できますか？</div>
          <p>A. はい。Freeでも公開運用に対応しており、自動投稿も利用できます。まず使いながら相性を確認したい方に向いています。</p>
        </div>
        <div class="wabe-faq-item">
          <div class="wabe-faq-q">Q. AdvancedとProの違いは何ですか？</div>
          <p>A. Advancedは日常的な記事更新を効率化したい方向けです。Proは、今後アフィリエイト向けの収益化機能を強化していく上位プランです。</p>
        </div>
        <div class="wabe-faq-item">
          <div class="wabe-faq-q">Q. AIのAPIキーは必要ですか？</div>
          <p>A. はい。OpenAIまたはGeminiのAPIキーを設定してご利用いただく形です。</p>
        </div>
      </div>
    </div>
  </section>

  <section class="wabe-section">
    <div class="wabe-container">
      <div class="wabe-cta-box">
        <span class="wabe-eyebrow">Call To Action</span>
        <h2>まずはFreeで使用感を確認し、必要に応じてAdvancedへ。</h2>
        <p>
          記事作成の負担を減らしたい方、更新を継続しやすくしたい方、
          AIをWordPress運用にしっかり組み込みたい方におすすめです。
        </p>
        <div class="wabe-cta-row" style="justify-content:center; margin-bottom:0;">
          <a class="wabe-btn wabe-btn-main" href="<?php echo esc_url(home_url('/free')); ?>">無料で試す</a>
          <a class="wabe-btn wabe-btn-sub"
            href="<?php echo esc_url(home_url('/advanced')); ?>">Advancedを見る</a>
        </div>
      </div>
    </div>
  </section>
</main>

<?php get_footer(); ?>
