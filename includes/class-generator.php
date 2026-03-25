<?php
if (!defined('ABSPATH')) exit;

/**
 * 設定ラッパ
 */
class WABE_Generator_Settings
{
    private $options = [];

    public function __construct()
    {
        $this->options = get_option(WABE_OPTION, []);
        if (!is_array($this->options)) {
            $this->options = [];
        }
    }

    public function get($key, $default = null)
    {
        return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
    }

    public function get_provider()
    {
        return sanitize_key($this->options['ai_provider'] ?? 'openai');
    }

    public function get_model()
    {
        if ($this->get_provider() === 'gemini') {
            return sanitize_text_field($this->options['gemini_model'] ?? 'gemini-2.5-flash');
        }

        return sanitize_text_field($this->options['openai_model'] ?? 'gpt-4.1');
    }

    public function get_language()
    {
        $locale = function_exists('get_locale') ? get_locale() : 'en_US';

        if (strpos((string)$locale, 'ja') === 0) {
            return 'Japanese';
        }

        return 'English';
    }

    public function can_publish()
    {
        return class_exists('WABE_Plan') ? (bool) WABE_Plan::can_publish() : false;
    }

    public function is_duplicate_check_enabled()
    {
        return class_exists('WABE_Plan')
            && WABE_Plan::can_use_duplicate_check()
            && !empty($this->options['enable_duplicate_check']);
    }

    public function is_internal_links_enabled()
    {
        return class_exists('WABE_Plan')
            && WABE_Plan::can_use_internal_links()
            && !empty($this->options['enable_internal_links']);
    }

    public function is_external_links_enabled()
    {
        return class_exists('WABE_Plan')
            && WABE_Plan::can_use_external_links()
            && !empty($this->options['enable_external_links']);
    }

    public function is_seo_enabled()
    {
        return class_exists('WABE_Plan')
            && WABE_Plan::can_use_seo()
            && !empty($this->options['enable_seo']);
    }

    public function is_topic_prediction_enabled()
    {
        return class_exists('WABE_Plan')
            && WABE_Plan::can_use_topic_prediction()
            && !empty($this->options['enable_topic_prediction']);
    }

    public function is_featured_image_enabled()
    {
        return class_exists('WABE_Plan')
            && WABE_Plan::can_use_images()
            && !empty($this->options['enable_featured_image']);
    }
}

/**
 * AIクライアントラッパ
 */
class WABE_Generator_AI_Client
{
    private $provider;
    private $model;
    private $client;

    public function __construct(WABE_Generator_Settings $settings)
    {
        $this->provider = $settings->get_provider();
        $this->model    = $settings->get_model();

        if ($this->provider === 'gemini' && class_exists('WABE_Gemini')) {
            $this->client = new WABE_Gemini();
        } elseif (class_exists('WABE_OpenAI')) {
            $this->client = new WABE_OpenAI();
            $this->provider = 'openai';
        } else {
            $this->client = null;
        }
    }

    public function provider()
    {
        return $this->provider ?: 'openai';
    }

    public function model()
    {
        return $this->model ?: 'gpt-4.1';
    }

    public function text($prompt, $args = [])
    {
        if (!$this->client || !method_exists($this->client, 'text')) {
            return '';
        }

        $args['model'] = $this->model();
        return (string)$this->client->text($prompt, $args);
    }
}

/**
 * 題材/履歴管理
 */
class WABE_Generator_History_Repository
{
    public function get_next_topic()
    {
        $options = get_option(WABE_OPTION, []);
        $topics  = $options['topics'] ?? [];

        if (!is_array($topics) || empty($topics[0])) {
            return null;
        }

        return $topics[0];
    }

    public function remove_first_topic()
    {
        $options = get_option(WABE_OPTION, []);
        $topics  = $options['topics'] ?? [];

        if (!is_array($topics) || empty($topics)) {
            return;
        }

        array_shift($topics);
        $options['topics'] = array_values($topics);
        update_option(WABE_OPTION, $options);
    }

    public function add_history(array $record)
    {
        $options = get_option(WABE_OPTION, []);
        $history = $options['history'] ?? [];

        if (!is_array($history)) {
            $history = [];
        }

        array_unshift($history, [
            'created_at'      => sanitize_text_field($record['created_at'] ?? current_time('mysql')),
            'topic'           => sanitize_text_field($record['topic'] ?? ''),
            'title'           => sanitize_text_field($record['title'] ?? ''),
            'post_id'         => (int)($record['post_id'] ?? 0),
            'post_url'        => esc_url_raw($record['post_url'] ?? ''),
            'style'           => sanitize_text_field($record['style'] ?? 'normal'),
            'tone'            => sanitize_text_field($record['tone'] ?? 'standard'),
            'status'          => sanitize_text_field($record['status'] ?? ''),
            'provider'        => sanitize_text_field($record['provider'] ?? ''),
            'model'           => sanitize_text_field($record['model'] ?? ''),
            'image_attached'  => sanitize_text_field($record['image_attached'] ?? '0'),
            'message'         => sanitize_text_field($record['message'] ?? ''),
        ]);

        $options['history'] = array_slice($history, 0, 100);
        update_option(WABE_OPTION, $options);
    }
}

/**
 * 品質チェック
 */
class WABE_Generator_Content_Quality
{
    public function is_similar_post_exists($topic_text)
    {
        $topic_text = trim((string)$topic_text);
        if ($topic_text === '') {
            return false;
        }

        $args = [
            'post_type'              => 'post',
            'post_status'            => ['publish', 'draft', 'future', 'pending', 'private'],
            'posts_per_page'         => 10,
            's'                      => $topic_text,
            'fields'                 => 'ids',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        $query = new WP_Query($args);
        if (!empty($query->posts)) {
            return true;
        }

        global $wpdb;
        $like = '%' . $wpdb->esc_like($topic_text) . '%';

        $sql = $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'post'
               AND post_status IN ('publish','draft','future','pending','private')
               AND (post_title LIKE %s OR post_content LIKE %s)
             LIMIT 1",
            $like,
            $like
        );

        $found = (int)$wpdb->get_var($sql);
        return $found > 0;
    }
}

/**
 * 本体
 */
class WABE_Generator
{
    private $settings;
    private $ai;
    private $repo;
    private $quality;

    public function __construct()
    {
        $this->settings = new WABE_Generator_Settings();
        $this->ai       = new WABE_Generator_AI_Client($this->settings);
        $this->repo     = new WABE_Generator_History_Repository();
        $this->quality  = new WABE_Generator_Content_Quality();
    }

    public function run()
    {
        $topic = $this->repo->get_next_topic();

        if (!$topic && $this->settings->is_topic_prediction_enabled() && class_exists('WABE_Topic_Generator')) {
            WABE_Topic_Generator::append_predicted_topics(5);
            $topic = $this->repo->get_next_topic();
        }

        if (!$topic) {
            if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
                WABE_Logger::info('Generator: no topics in queue.');
            }
            return false;
        }

        $status = sanitize_key($this->settings->get('post_status', 'draft'));
        $tone   = sanitize_key($this->settings->get('tone', 'standard'));

        return $this->generate($topic, $status, $tone);
    }
    public function generate($topic, $status, $global_tone = 'standard')
    {
        try {
            $topic_data = $this->normalize_topic($topic, $global_tone);

            if ($topic_data['topic'] === '') {
                if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'warning')) {
                    WABE_Logger::warning('Generator: topic text empty.');
                }
                return false;
            }

            $status = in_array($status, ['draft', 'publish'], true) ? $status : 'draft';

            if ($status === 'publish' && !$this->settings->can_publish()) {
                $status = 'draft';
            }

            if ($this->settings->is_duplicate_check_enabled()) {
                if ($this->quality->is_similar_post_exists($topic_data['topic'])) {
                    if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'warning')) {
                        WABE_Logger::warning('Generator: duplicate topic skipped - ' . $topic_data['topic']);
                    }

                    $this->repo->add_history([
                        'topic'          => $topic_data['topic'],
                        'title'          => '',
                        'post_id'        => 0,
                        'post_url'       => '',
                        'style'          => $topic_data['style'],
                        'tone'           => $topic_data['tone'],
                        'status'         => 'skipped_duplicate',
                        'provider'       => $this->ai->provider(),
                        'model'          => $this->ai->model(),
                        'image_attached' => '0',
                        'message'        => 'Duplicate topic skipped.',
                    ]);

                    $this->repo->remove_first_topic();
                    return false;
                }
            }

            $context = $this->build_context($topic_data);

            $article_title = $this->generate_title($context);
            if ($article_title === '') {
                $article_title = $this->fallback_title($context['topic'], $context);
            }

            $markdown = $this->generate_full_article($context, $article_title);
            if (in_array(($context['plan'] ?? 'free'), ['advanced', 'pro'], true)) {
                $rewritten = $this->rewrite_article($markdown, $context, $article_title);

                if (trim((string) $rewritten) !== '') {
                    $rewritten_body_ok     = $this->is_body_length_ok($rewritten, $context);
                    $rewritten_headings_ok = $this->are_headings_length_ok($rewritten, $context);

                    if ($rewritten_body_ok && $rewritten_headings_ok) {
                        $markdown = $rewritten;
                    } elseif ($this->get_visible_length($rewritten) > $this->get_visible_length($markdown)) {
                        $markdown = $rewritten;
                    }
                }
            }

            if (trim($markdown) === '') {
                if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'warning')) {
                    WABE_Logger::warning('Generator: empty article body. fallback topic only. topic=' . $topic_data['topic']);
                }
                $markdown = "## はじめに\n\n" . $topic_data['topic'];
            }

            $markdown = $this->remove_duplicate_title_from_body($markdown, $article_title);

            if ($this->settings->is_internal_links_enabled()) {
                $markdown = $this->inject_internal_link_hint($markdown, $context);
            }

            if ($this->settings->is_external_links_enabled()) {
                $external = $this->build_external_links_block($context);
                if ($external !== '') {
                    $markdown .= "\n\n" . $external;
                }
            }

            if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
                WABE_Logger::info('Generated title: ' . $article_title);
                WABE_Logger::info('Generated markdown length: ' . $this->get_visible_length($markdown));
                WABE_Logger::info('Generated markdown preview: ' . mb_substr((string) $markdown, 0, 1000));
            }

            $content = $this->markdown_to_blocks($markdown, $article_title);

            if (class_exists('WABE_Image')) {
                $image = new WABE_Image();
                $before_inline_count = substr_count((string) $content, 'wabe-inline-unsplash-image');

                $content = $image->inject_unsplash_images_into_content($content, [
                    'topic'    => $topic_data['topic'],
                    'title'    => $article_title,
                    'plan'     => $context['plan'] ?? 'free',
                    'language' => $context['language'] ?? '',
                ]);

                $after_inline_count = substr_count((string) $content, 'wabe-inline-unsplash-image');

                if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
                    WABE_Logger::info('Generator: inline image blocks before=' . $before_inline_count . ' after=' . $after_inline_count);
                }
            }

            if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
                WABE_Logger::info('Final block content length: ' . mb_strlen((string) $content));
                WABE_Logger::info('Final block content preview: ' . mb_substr((string) $content, 0, 1000));
            }

            $postarr = [
                'post_type'    => 'post',
                'post_status'  => $status,
                'post_title'   => $article_title,
                'post_content' => $content,
            ];

            $post_id = wp_insert_post(wp_slash($postarr), true);

            if (is_wp_error($post_id)) {
                if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'error')) {
                    WABE_Logger::error('Generator: wp_insert_post failed - ' . $post_id->get_error_message());
                }
                return false;
            }

            $post_id = (int) $post_id;

            if ($this->settings->is_seo_enabled()) {
                $this->apply_basic_seo_meta($post_id, $context);
            }

            $image_attached = '0';
            if ($this->settings->is_featured_image_enabled() && class_exists('WABE_Image')) {
                $image = new WABE_Image();
                $set = $image->generate_and_attach($post_id, $topic_data['topic']);
                $image_attached = $set ? '1' : '0';
            }

            $post_url = get_permalink($post_id);
            if (!is_string($post_url)) {
                $post_url = '';
            }

            $this->repo->add_history([
                'topic'          => $topic_data['topic'],
                'title'          => $article_title,
                'post_id'        => $post_id,
                'post_url'       => $post_url,
                'style'          => $topic_data['style'],
                'tone'           => $topic_data['tone'],
                'status'         => $status,
                'provider'       => $this->ai->provider(),
                'model'          => $this->ai->model(),
                'image_attached' => $image_attached,
                'message'        => 'Post generated successfully.',
            ]);

            $this->repo->remove_first_topic();

            if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
                WABE_Logger::info('Generator: post generated successfully - post_id=' . $post_id);
            }

            return $post_id;
        } catch (Throwable $e) {
            if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'error')) {
                WABE_Logger::error('Generator exception: ' . $e->getMessage());
            }
            return false;
        }
    }


    private function normalize_topic($topic, $global_tone = 'standard')
    {
        if (is_string($topic)) {
            $topic = [
                'topic' => $topic,
                'style' => 'normal',
                'tone'  => $global_tone,
            ];
        }

        $topic_text = sanitize_text_field((string)($topic['topic'] ?? ''));
        $style      = sanitize_text_field((string)($topic['style'] ?? 'normal'));
        $tone       = sanitize_text_field((string)($topic['tone'] ?? $global_tone));

        if (!in_array($tone, ['standard', 'polite', 'casual'], true)) {
            $tone = 'standard';
        }

        if ($style === '') {
            $style = 'normal';
        }

        return [
            'topic' => $topic_text,
            'style' => $style,
            'tone'  => $tone,
        ];
    }

    private function build_context(array $topic_data)
    {
        $plan = $this->detect_plan_slug();

        $detail_level = sanitize_key((string)$this->settings->get('detail_level', 'medium'));
        $generation_quality = sanitize_key((string)$this->settings->get('generation_quality', 'high'));

        if (!in_array($detail_level, ['low', 'medium', 'high'], true)) {
            $detail_level = 'medium';
        }

        if (!in_array($generation_quality, ['fast', 'high'], true)) {
            $generation_quality = 'high';
        }

        return [
            'plan' => $plan,
            'topic' => $topic_data['topic'],
            'style' => $topic_data['style'],
            'tone' => $topic_data['tone'],
            'language' => $this->settings->get_language(),
            'site_context' => (string)$this->settings->get('site_context', ''),
            'writing_rules' => (string)$this->settings->get('writing_rules', ''),
            'seo_keyword' => (string)$this->settings->get('seo_keyword', ''),
            'internal_link_url' => (string)$this->settings->get('internal_link_url', ''),
            'external_link_url' => (string)$this->settings->get('external_link_url', ''),
            'enable_internal_links' => $this->settings->is_internal_links_enabled(),
            'enable_external_links' => $this->settings->is_external_links_enabled(),
            'detail_level' => $detail_level,
            'generation_quality' => $generation_quality,
            'title_profile' => $this->get_title_profile(),
            'heading_profile' => $this->get_heading_profile(),
            'length_profile' => $this->get_length_profile_by_plan($plan),
        ];
    }

    private function build_human_writing_system_prompt(array $context)
    {
        $language = !empty($context['language']) ? (string) $context['language'] : 'Japanese';
        $tone = !empty($context['tone']) ? (string) $context['tone'] : 'standard';

        $tone_guide = 'Write in a balanced, natural Japanese tone.';
        if ($tone === 'polite') {
            $tone_guide = 'Write in natural and polite Japanese. Keep it friendly, clear, and trustworthy.';
        } elseif ($tone === 'casual') {
            $tone_guide = 'Write in natural, easy-to-read Japanese with a soft conversational feel.';
        }

        return trim(
            "You are a professional {$language} blog writer.\n\n" .
                "Your writing must feel like it was written by a real human, not an AI.\n\n" .

                "[Core writing rules]\n" .
                "- Avoid robotic, formulaic, or overly templated phrasing.\n" .
                "- Vary sentence length naturally. Mix short and longer sentences.\n" .
                "- Avoid repeating the same sentence endings or patterns.\n" .
                "- Do not make every section the same length.\n" .
                "- Keep paragraphs reasonably short and readable.\n" .
                "- Each paragraph must add new value.\n" .
                "- Prefer specific, practical explanations over vague generalities.\n" .
                "- Use smooth transitions between sections and paragraphs.\n" .
                "- Do not sound like a manual, textbook, or AI template.\n\n" .

                "[Natural human-like expressions]\n" .
                "- Occasionally use subtle human phrasing when appropriate.\n" .
                "- Examples: 『実は〜』『意外と見落としがちですが〜』『正直なところ〜』『〜と感じる方も多いと思います』\n" .
                "- Do not overuse these expressions.\n\n" .

                "[Introduction rules]\n" .
                "- Start with a relatable problem, question, or insight.\n" .
                "- Avoid generic openings like 『本記事では〜について解説します』.\n\n" .

                "[Conclusion rules]\n" .
                "- End naturally and helpfully.\n" .
                "- Avoid robotic summaries like 『まとめると〜です』.\n\n" .

                "[Important constraints]\n" .
                "- Never mention AI.\n" .
                "- Never say the article was generated.\n" .
                "- Do not explain your writing process.\n" .
                "- Prioritize readability first, then SEO.\n\n" .

                "[Tone guide]\n" .
                $tone_guide
        );
    }

    private function build_human_title_system_prompt(array $context)
    {
        return trim(
            "You are a professional Japanese editor.\n" .
                "Create a natural, human-like blog title.\n" .
                "- Avoid generic AI-like titles.\n" .
                "- Avoid clickbait that feels unnatural.\n" .
                "- Make it readable, specific, and appealing.\n" .
                "- Keep SEO in mind, but prioritize natural wording.\n" .
                "- Output only the title."
        );
    }

    private function detect_plan_slug()
    {
        $plan = '';

        // 1. 明示保存されている plan を最優先
        $options = get_option(WABE_OPTION, []);
        if (is_array($options) && !empty($options['plan'])) {
            $plan = (string)$options['plan'];
        }

        // 2. WABE_Plan に安全な public static get_plan() がある場合だけ使う
        if ($plan === '' && class_exists('WABE_Plan') && method_exists('WABE_Plan', 'get_plan')) {
            try {
                $plan = (string) WABE_Plan::get_plan();
            } catch (Throwable $e) {
                $plan = '';
            }
        }

        $plan = sanitize_key($plan);

        if (!in_array($plan, ['free', 'advanced', 'pro'], true)) {
            $plan = 'free';
        }

        return $plan;
    }

    private function get_title_profile()
    {
        return [
            'min' => 18,
            'target_max' => 32,
            'soft_max' => 40,
        ];
    }

    private function get_heading_profile()
    {
        return [
            'min' => 12,
            'target_max' => 20,
            'soft_max' => 22,
        ];
    }

    private function get_length_profile_by_plan($plan)
    {
        switch ($plan) {
            case 'pro':
                return [
                    'band'   => 5000,
                    'min'    => 4500,
                    'target' => 5000,
                    'max'    => 5300,
                    'label'  => '5000',
                ];

            case 'advanced':
                return [
                    'band'   => 3000,
                    'min'    => 2500,
                    'target' => 3000,
                    'max'    => 3300,
                    'label'  => '3000',
                ];

            case 'free':
            default:
                return [
                    'band'   => 1000,
                    'min'    => 900,
                    'target' => 1000,
                    'max'    => 1100,
                    'label'  => '1000',
                ];
        }
    }

    private function decode_maybe_base64($value)
    {
        $value = (string)$value;
        if ($value === '') {
            return '';
        }

        if (class_exists('WABE_Utils')) {
            if (method_exists('WABE_Utils', 'wabe_maybe_base64_decode')) {
                try {
                    return (string)WABE_Utils::wabe_maybe_base64_decode($value);
                } catch (Throwable $e) {
                    // noop
                }
            }

            try {
                $utils = new WABE_Utils();
                if (method_exists($utils, 'wabe_maybe_base64_decode')) {
                    return (string)$utils->wabe_maybe_base64_decode($value);
                }
            } catch (Throwable $e) {
                // noop
            }
        }

        $decoded = base64_decode($value, true);
        return ($decoded !== false) ? (string)$decoded : $value;
    }

    private function parse_lines($text)
    {
        $text = trim((string)$text);
        if ($text === '') {
            return [];
        }

        $lines = preg_split("/\r\n|\r|\n/u", $text);
        if (!is_array($lines)) {
            return [];
        }

        $result = [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            $line = preg_replace('/^[\-\*\d\.\)\s]+/u', '', $line);
            $line = trim((string)$line);
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }

    private function get_visible_length($text)
    {
        $text = wp_strip_all_tags((string)$text);
        $text = preg_replace('/\s+/u', '', $text);
        return mb_strlen((string)$text);
    }

    private function extract_h2_headings($markdown)
    {
        $markdown = (string)$markdown;
        preg_match_all('/^##\s+(.+)$/um', $markdown, $matches);
        return (!empty($matches[1]) && is_array($matches[1])) ? $matches[1] : [];
    }

    private function is_between($length, $min, $max)
    {
        $length = (int)$length;
        return $length >= (int)$min && $length <= (int)$max;
    }

    private function is_title_length_ok($title, array $context)
    {
        $profile = $context['title_profile'];
        $length = $this->get_visible_length($title);

        return $this->is_between($length, $profile['min'], $profile['soft_max']);
    }

    private function are_headings_length_ok($markdown, array $context)
    {
        $profile = $context['heading_profile'];
        $headings = $this->extract_h2_headings($markdown);

        if (empty($headings)) {
            return false;
        }

        foreach ($headings as $heading) {
            $length = $this->get_visible_length($heading);
            if (!$this->is_between($length, $profile['min'], $profile['soft_max'])) {
                return false;
            }
        }

        return true;
    }

    private function is_body_length_ok($markdown, array $context)
    {
        $profile = $context['length_profile'];
        $length = $this->get_visible_length($markdown);

        return $this->is_between($length, $profile['min'], $profile['max']);
    }

    private function fallback_title($topic, array $context)
    {
        $topic = trim((string) $topic);

        if ($topic === '') {
            return 'ブログ記事';
        }

        $profile = $context['title_profile'];
        $length  = $this->get_visible_length($topic);

        if ($length < $profile['min']) {
            $topic .= 'のポイント解説';
        }

        if ($this->get_visible_length($topic) <= $profile['soft_max']) {
            return trim($topic);
        }

        // まず自然な区切りで短くする
        $separators = ['｜', '|', '：', ':', '、', 'とは', 'の', 'を', 'で'];

        foreach ($separators as $sep) {
            $pos = mb_strpos($topic, $sep);
            if ($pos !== false) {
                $candidate = trim(mb_substr($topic, 0, $pos));
                if ($candidate !== '' && $this->get_visible_length($candidate) >= $profile['min'] && $this->get_visible_length($candidate) <= $profile['soft_max']) {
                    return $candidate;
                }
            }
        }

        // 機械切りの前に少し余裕を見て切る
        $cut = mb_substr($topic, 0, $profile['soft_max']);

        // 日本語の不自然な語尾を除去
        $cut = preg_replace('/(チェ|リス|対策チ|セキュリティ対|チェックリ)$/u', '', $cut);
        $cut = rtrim((string) $cut, " 　・,:：、/-");

        if ($cut === '') {
            $cut = mb_substr($topic, 0, max(1, $profile['soft_max'] - 2));
        }

        return trim($cut);
    }

    private function generate_title(array $context)
    {
        $base_prompt = $this->build_title_prompt($context);

        $human_prompt = trim(
            "You are a professional Japanese editor.\n" .
                "Create a title that feels naturally written by a real human.\n\n" .
                "[Human-like title rules]\n" .
                "- Avoid generic AI-like phrasing.\n" .
                "- Avoid unnatural clickbait.\n" .
                "- Keep the wording specific, smooth, and readable.\n" .
                "- Prefer natural Japanese over stiff or mechanical wording.\n" .
                "- Avoid incomplete fragments.\n" .
                "- Output exactly one title only.\n" .
                "- Do not output bullets, quotation marks, labels, or explanations.\n"
        );

        $prompt = $human_prompt . "\n\n" . $base_prompt;

        $quality = $context['generation_quality'] ?? 'high';
        $temperature = ($quality === 'fast') ? 0.5 : 0.7;
        $max_tokens  = ($quality === 'fast') ? 90 : 140;

        $text = $this->ai->text($prompt, [
            'temperature'       => $temperature,
            'max_output_tokens' => $max_tokens,
        ]);

        $lines = $this->parse_lines($text);
        $title = !empty($lines[0]) ? sanitize_text_field($lines[0]) : '';
        $title = preg_replace('/^#+\s*/u', '', (string) $title);
        $title = trim((string) $title, " \t\n\r\0\x0B\"'「」〖〗");

        if (!$this->is_title_length_ok($title, $context)) {
            $retry_prompt = $prompt . "\n\nIMPORTANT:\n" .
                "- The title length is invalid.\n" .
                "- Make it natural, complete, and human-like.\n" .
                "- Do not sound robotic or templated.\n" .
                "- Keep it between {$context['title_profile']['min']} and {$context['title_profile']['soft_max']} Japanese characters.\n" .
                "- Do not output explanations.\n";

            $retry = $this->ai->text($retry_prompt, [
                'temperature'       => 0.6,
                'max_output_tokens' => $max_tokens,
            ]);

            $retry_lines = $this->parse_lines($retry);
            $retry_title = !empty($retry_lines[0]) ? sanitize_text_field($retry_lines[0]) : '';
            $retry_title = preg_replace('/^#+\s*/u', '', (string) $retry_title);
            $retry_title = trim((string) $retry_title, " \t\n\r\0\x0B\"'「」〖〗");

            if ($this->is_title_length_ok($retry_title, $context)) {
                $title = $retry_title;
            }
        }

        if ($title === '' || !$this->is_title_length_ok($title, $context)) {
            $title = $this->fallback_title($context['topic'], $context);
        }

        return $title;
    }

    private function build_title_prompt(array $context)
    {
        $keyword = !empty($context['seo_keyword']) ? $context['seo_keyword'] : $context['topic'];
        $profile = $context['title_profile'];

        return trim(
            "You are a professional editor.\n" .
                "Write in {$context['language']}.\n\n" .
                "Topic:\n{$context['topic']}\n\n" .
                "Main keyword:\n{$keyword}\n\n" .
                "Task:\n" .
                "- Generate exactly one natural blog title.\n" .
                "- Keep it specific and readable.\n" .
                "- Avoid incomplete phrases.\n" .
                "- The title should ideally be between {$profile['min']} and {$profile['target_max']} Japanese characters.\n" .
                "- The title must not exceed {$profile['soft_max']} Japanese characters.\n" .
                "- Do not output headings, bullets, quotation marks, or explanations.\n" .
                "- Output only the title.\n"
        );
    }

    private function generate_full_article(array $context, $article_title)
    {
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('AI provider used: ' . $this->ai->provider());
            WABE_Logger::info('AI model used: ' . $this->ai->model());
        }

        $base_prompt = $this->build_full_article_prompt($context, $article_title);

        $human_prompt = trim(
            "You are a professional Japanese blog writer.\n" .
                "Your article must feel like it was written by a real human, not an AI.\n\n" .

                "[Human-like writing rules]\n" .
                "- Avoid robotic, formulaic, or overly templated writing.\n" .
                "- Use natural Japanese phrasing.\n" .
                "- Mix short and long sentences for rhythm.\n" .
                "- Avoid repeating the same sentence endings or paragraph patterns.\n" .
                "- Do not make every section the same length.\n" .
                "- Keep paragraphs readable and not overly dense.\n" .
                "- Each paragraph should add new value.\n" .
                "- Prefer concrete and practical explanations over vague generalities.\n" .
                "- Use smooth transitions between sections.\n" .
                "- Do not sound like a textbook, instruction manual, or AI template.\n\n" .

                "[Natural expression guidance]\n" .
                "- When appropriate, use subtle human phrasing such as:\n" .
                "  - 「実は〜」\n" .
                "  - 「意外と見落としがちですが〜」\n" .
                "  - 「正直なところ〜」\n" .
                "  - 「〜と感じる方も多いと思います」\n" .
                "- Do not overuse these expressions.\n\n" .

                "[Introduction guidance]\n" .
                "- Start naturally with a relatable problem, question, or useful insight.\n" .
                "- Avoid generic openings like 「本記事では〜について解説します」.\n\n" .

                "[Conclusion guidance]\n" .
                "- End naturally and helpfully.\n" .
                "- Avoid robotic closings like 「まとめると〜です」.\n" .
                "- Keep the CTA natural and not pushy.\n\n" .

                "[Important constraints]\n" .
                "- Never mention AI.\n" .
                "- Never say the text was generated.\n" .
                "- Do not explain your writing process.\n" .
                "- Prioritize readability first, then SEO.\n"
        );

        $prompt = $human_prompt . "\n\n" . $base_prompt;

        $quality = $context['generation_quality'] ?? 'high';
        $temperature = ($quality === 'fast') ? 0.5 : 0.7;
        $max_tokens  = $this->estimate_max_output_tokens($context);

        $text = trim((string) $this->ai->text($prompt, [
            'temperature'       => $temperature,
            'max_output_tokens' => $max_tokens,
        ]));

        $body_ok     = $this->is_body_length_ok($text, $context);
        $headings_ok = $this->are_headings_length_ok($text, $context);

        if (!$body_ok || !$headings_ok) {
            $profile = $context['length_profile'];
            $heading = $context['heading_profile'];

            $retry_prompt = $prompt . "\n\nIMPORTANT:\n";

            if (!$body_ok) {
                $retry_prompt .= "- The total article length including headings and body must be between {$profile['min']} and {$profile['max']} Japanese characters.\n";
            }

            if (!$headings_ok) {
                $retry_prompt .= "- Every H2 heading must be between {$heading['min']} and {$heading['soft_max']} Japanese characters.\n";
            }

            $retry_prompt .=
                "- Do not repeat the title in the body.\n" .
                "- Ensure the summary and CTA are included.\n" .
                "- Make the writing more natural and human-like.\n" .
                "- Reduce robotic repetition.\n" .
                "- Keep transitions smooth between sections.\n";

            $retry = trim((string) $this->ai->text($retry_prompt, [
                'temperature'       => 0.6,
                'max_output_tokens' => $max_tokens,
            ]));

            if ($retry !== '') {
                $retry_body_ok     = $this->is_body_length_ok($retry, $context);
                $retry_headings_ok = $this->are_headings_length_ok($retry, $context);

                if ($retry_body_ok && $retry_headings_ok) {
                    $text = $retry;
                } elseif ($this->get_visible_length($retry) > $this->get_visible_length($text)) {
                    $text = $retry;
                }
            }
        }

        return $text;
    }

    private function rewrite_article($article, array $context, $article_title = '')
    {
        $article = trim((string) $article);
        if ($article === '') {
            return $article;
        }

        $plan = (string) ($context['plan'] ?? 'free');
        if (!in_array($plan, ['advanced', 'pro'], true)) {
            return $article;
        }

        $length_profile  = $context['length_profile'];
        $heading_profile = $context['heading_profile'];

        $rewrite_prompt = trim(
            "You are a professional Japanese editor.\n" .
                "Rewrite the following article so it feels more natural, more human-like, and less AI-generated.\n\n" .

                "[Goal]\n" .
                "- Improve flow, rhythm, readability, and human warmth.\n" .
                "- Remove robotic or templated phrasing.\n" .
                "- Keep the article practical and trustworthy.\n\n" .

                "[Rules]\n" .
                "- Keep the original meaning and structure.\n" .
                "- Do not remove important information.\n" .
                "- Do not add completely new sections.\n" .
                "- Preserve Markdown headings (## and ###).\n" .
                "- Do not repeat the title in the body.\n" .
                "- Keep the total article length between {$length_profile['min']} and {$length_profile['max']} Japanese characters.\n" .
                "- Every H2 heading must stay between {$heading_profile['min']} and {$heading_profile['soft_max']} Japanese characters.\n" .
                "- Keep the summary section and CTA section.\n\n" .

                "[Improvements]\n" .
                "- Vary sentence endings and sentence length.\n" .
                "- Improve transitions between paragraphs and sections.\n" .
                "- Reduce stiffness and textbook-like explanations.\n" .
                "- Add slight natural expressions where appropriate.\n" .
                "- Make the article easier to picture for real readers.\n" .
                "- Prefer concrete phrasing over vague generalities.\n\n" .

                "[Important constraints]\n" .
                "- Never mention AI.\n" .
                "- Never explain your edits.\n" .
                "- Output only the rewritten article.\n\n" .

                "Article title:\n{$article_title}\n\n" .
                "Topic:\n{$context['topic']}\n\n" .
                "Tone:\n{$context['tone']}\n\n" .
                "Article:\n{$article}"
        );

        $rewritten = trim((string) $this->ai->text($rewrite_prompt, [
            'temperature'       => 0.85,
            'max_output_tokens' => $this->estimate_max_output_tokens($context),
        ]));

        if ($rewritten === '') {
            return $article;
        }

        return $rewritten;
    }

    private function build_full_article_prompt(array $context, $article_title)
    {
        $extra = '';

        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . $this->decode_maybe_base64($context['site_context']) . "\n\n";
        }

        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . $this->decode_maybe_base64($context['writing_rules']) . "\n\n";
        }

        if (!empty($context['seo_keyword'])) {
            $extra .= "SEO keyword:\n" . $context['seo_keyword'] . "\n\n";
        }

        if (!empty($context['internal_link_url']) && !empty($context['enable_internal_links'])) {
            $extra .= "Internal link candidate URL:\n" . $context['internal_link_url'] . "\n\n";
        }

        $length_profile  = $context['length_profile'];
        $heading_profile = $context['heading_profile'];

        $detail_text = 'Give a balanced level of explanation.';
        if ($context['detail_level'] === 'low') {
            $detail_text = 'Keep explanations compact and practical.';
        } elseif ($context['detail_level'] === 'high') {
            $detail_text = 'Explain each point deeply with concrete examples and actionable advice.';
        }

        $recommended_h2_count = 3;
        if ($context['plan'] === 'advanced') {
            $recommended_h2_count = 4;
        } elseif ($context['plan'] === 'pro') {
            $recommended_h2_count = 5;
        }

        return trim(
            "You are a professional Japanese SEO writer.\n" .
                "Write in {$context['language']}.\n\n" .

                $extra .

                "Topic:\n{$context['topic']}\n\n" .
                "Article title:\n{$article_title}\n\n" .
                "Style:\n{$context['style']}\n\n" .
                "Tone:\n{$context['tone']}\n\n" .

                "Target total article length:\nAbout {$length_profile['target']} Japanese characters\n\n" .
                "Valid total article length range:\n{$length_profile['min']} to {$length_profile['max']} Japanese characters including headings and body\n\n" .
                "H2 heading length rule:\nEach H2 should ideally be between {$heading_profile['min']} and {$heading_profile['target_max']} Japanese characters, and must not exceed {$heading_profile['soft_max']} Japanese characters\n\n" .
                "Recommended H2 count:\n{$recommended_h2_count}\n\n" .
                "Detail instruction:\n{$detail_text}\n\n" .

                "Task:\n" .
                "- Write the full article body only.\n" .
                "- Do NOT output the title again in the body.\n" .
                "- Start directly with an introduction paragraph or the first H2.\n" .
                "- Use Markdown-style headings only for H2 and H3 (##, ###).\n" .
                "- Keep paragraphs short and readable.\n" .
                "- Use bullet lists only where useful.\n" .
                "- Include a summary section.\n" .
                "- Include a CTA section at the end.\n" .
                "- Keep the structure natural and complete.\n" .
                "- Make the article feel human, practical, and realistic.\n" .
                "- Avoid textbook-style explanations.\n" .
                "- Include concrete mini-scenarios or examples where appropriate.\n" .
                "- Avoid generic openings like 「本記事では〜について解説します」.\n" .
                "- Avoid robotic endings like 「まとめると〜です」.\n"
        );
    }

    private function estimate_max_output_tokens(array $context)
    {
        $band = (int)$context['length_profile']['band'];
        $quality = (string)($context['generation_quality'] ?? 'high');

        if ($band >= 5000) {
            return ($quality === 'fast') ? 3000 : 4600;
        }

        if ($band >= 3000) {
            return ($quality === 'fast') ? 1900 : 3200;
        }

        return ($quality === 'fast') ? 1100 : 1800;
    }

    private function remove_duplicate_title_from_body($content, $article_title)
    {
        $content = trim((string)$content);
        $article_title = trim((string)$article_title);

        if ($content === '' || $article_title === '') {
            return $content;
        }

        $lines = preg_split("/\r\n|\r|\n/u", $content);
        if (!is_array($lines) || empty($lines)) {
            return $content;
        }

        $first = trim((string)$lines[0]);
        $normalized_first = trim((string)preg_replace('/^#+\s*/u', '', $first));
        $normalized_first = trim($normalized_first, " \t\n\r\0\x0B\"'「」【】");
        $normalized_title = trim($article_title, " \t\n\r\0\x0B\"'「」【】");

        if ($normalized_first === $normalized_title || mb_strpos($normalized_first, $normalized_title) === 0) {
            array_shift($lines);
            $content = trim(implode("\n", $lines));
        }

        return $content;
    }

    private function inject_internal_link_hint($content, array $context)
    {
        $url = trim((string)$context['internal_link_url']);
        if ($url === '') {
            return $content;
        }

        $block  = "\n\n";
        $block .= "## 関連情報のご案内\n\n";
        $block .= "- あわせて読みたいページ: " . esc_url($url);

        return trim($content . $block);
    }

    private function build_external_links_block(array $context)
    {
        $url = trim((string)($context['external_link_url'] ?? ''));
        if ($url === '') {
            return '';
        }

        $lines   = [];
        $lines[] = '## 参考リンクのご紹介';
        $lines[] = '';
        $lines[] = '- 関連情報: ' . esc_url($url);

        return implode("\n", $lines);
    }

    private function markdown_to_blocks($markdown, $article_title = '')
    {
        $markdown = str_replace(["\r\n", "\r"], "\n", (string) $markdown);
        $lines = explode("\n", $markdown);

        $blocks = [];
        $paragraph_buffer = [];
        $list_buffer = [];

        $flush_paragraph = function () use (&$paragraph_buffer, &$blocks) {
            if (empty($paragraph_buffer)) {
                return;
            }

            $text = trim(implode("\n", $paragraph_buffer));
            $paragraph_buffer = [];

            if ($text === '') {
                return;
            }

            $text = $this->format_inline_markdown($text);

            $blocks[] = '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
        };

        $flush_list = function () use (&$list_buffer, &$blocks) {
            if (empty($list_buffer)) {
                return;
            }

            $items = [];
            foreach ($list_buffer as $item) {
                $item = trim($item);
                if ($item === '') {
                    continue;
                }
                $item = $this->format_inline_markdown($item);
                $items[] = '<li>' . $item . '</li>';
            }

            $list_buffer = [];

            if (!empty($items)) {
                $blocks[] = '<!-- wp:list --><ul>' . implode('', $items) . '</ul><!-- /wp:list -->';
            }
        };

        foreach ($lines as $line) {
            $raw = rtrim($line);
            $trimmed = trim($raw);

            // 空行
            if ($trimmed === '') {
                $flush_paragraph();
                $flush_list();
                continue;
            }

            // H4
            if (preg_match('/^####\s+(.+)$/u', $trimmed, $m)) {
                $flush_paragraph();
                $flush_list();
                $heading = esc_html(trim($m[1]));
                $blocks[] = '<!-- wp:heading {"level":4} --><h4>' . $heading . '</h4><!-- /wp:heading -->';
                continue;
            }

            // H3
            if (preg_match('/^###\s+(.+)$/u', $trimmed, $m)) {
                $flush_paragraph();
                $flush_list();
                $heading = esc_html(trim($m[1]));
                $blocks[] = '<!-- wp:heading {"level":3} --><h3>' . $heading . '</h3><!-- /wp:heading -->';
                continue;
            }

            // H2
            if (preg_match('/^##\s+(.+)$/u', $trimmed, $m)) {
                $flush_paragraph();
                $flush_list();
                $heading = esc_html(trim($m[1]));
                $blocks[] = '<!-- wp:heading --><h2>' . $heading . '</h2><!-- /wp:heading -->';
                continue;
            }

            // 箇条書き
            if (preg_match('/^\*\s+(.+)$/u', $trimmed, $m) || preg_match('/^-\s+(.+)$/u', $trimmed, $m)) {
                $flush_paragraph();
                $list_buffer[] = $m[1];
                continue;
            }

            // 通常段落
            $flush_list();
            $paragraph_buffer[] = $trimmed;
        }

        $flush_paragraph();
        $flush_list();

        return implode("\n\n", $blocks);
    }

    private function format_inline_markdown($text)
    {
        $text = esc_html((string) $text);

        // **太字**
        $text = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $text);

        // __太字__ も許可したいなら
        $text = preg_replace('/__(.+?)__/u', '<strong>$1</strong>', $text);

        return $text;
    }

    private function apply_basic_seo_meta($post_id, array $context)
    {
        $keyword = trim((string)($context['seo_keyword'] ?? ''));
        $description = wp_trim_words(
            wp_strip_all_tags(get_post_field('post_content', $post_id)),
            35,
            '...'
        );

        update_post_meta($post_id, '_wabe_seo_keyword', $keyword);
        update_post_meta($post_id, '_wabe_meta_description', $description);

        if (!metadata_exists('post', $post_id, '_yoast_wpseo_metadesc')) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $description);
        }

        if ($keyword !== '' && !metadata_exists('post', $post_id, '_yoast_wpseo_focuskw')) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $keyword);
        }
    }
}
