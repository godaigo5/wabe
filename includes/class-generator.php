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

    public function is_outline_enabled()
    {
        return class_exists('WABE_Plan')
            && WABE_Plan::can_use_outline_generator()
            && !empty($this->options['enable_outline_generator']);
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
    /** @var WABE_Generator_Settings */
    private $settings;

    /** @var WABE_Generator_AI_Client */
    private $ai;

    /** @var WABE_Generator_History_Repository */
    private $repo;

    /** @var WABE_Generator_Content_Quality */
    private $quality;

    public function __construct()
    {
        $this->settings = new WABE_Generator_Settings();
        $this->ai       = new WABE_Generator_AI_Client($this->settings);
        $this->repo     = new WABE_Generator_History_Repository();
        $this->quality  = new WABE_Generator_Content_Quality();
    }

    /**
     * 1件実行
     *
     * @return int|false
     */
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

    /**
     * 1件生成
     *
     * @param array|string $topic
     * @param string $status
     * @param string $global_tone
     * @return int|false
     */
    public function generate($topic, $status, $global_tone = 'standard')
    {
        try {
            $topic_data = $this->normalize_topic($topic, $global_tone);

            if ($topic_data['topic'] === '') {
                WABE_Logger::warning('Generator: topic text empty.');
                return false;
            }

            $status = in_array($status, ['draft', 'publish'], true) ? $status : 'draft';
            if ($status === 'publish' && !$this->settings->can_publish()) {
                $status = 'draft';
            }

            if ($this->settings->is_duplicate_check_enabled()) {
                if ($this->quality->is_similar_post_exists($topic_data['topic'])) {
                    WABE_Logger::warning('Generator: duplicate topic skipped - ' . $topic_data['topic']);

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
                $article_title = $topic_data['topic'];
            }

            $headings = $this->generate_headings($context, $article_title);
            if (empty($headings)) {
                $headings = ['概要', 'ポイント', 'まとめ'];
            }

            $intro = $this->generate_intro($context, $article_title);

            $sections = [];
            foreach ($headings as $heading) {
                $body = $this->generate_section($context, $article_title, $heading);
                if ($body !== '') {
                    $sections[] = [
                        'heading' => $heading,
                        'body'    => $body,
                    ];
                }
            }

            $content = $this->assemble_article($context, $article_title, $intro, $sections);

            if ($this->settings->is_internal_links_enabled()) {
                $content = $this->inject_internal_link_hint($content, $context);
            }

            if ($this->settings->is_external_links_enabled()) {
                $content .= "\n\n" . $this->build_external_links_block($context, $article_title);
            }

            $postarr = [
                'post_type'    => 'post',
                'post_status'  => $status,
                'post_title'   => $article_title,
                'post_content' => $content,
            ];

            $post_id = wp_insert_post(wp_slash($postarr), true);
            if (is_wp_error($post_id)) {
                WABE_Logger::error('Generator: wp_insert_post failed - ' . $post_id->get_error_message());
                return false;
            }

            $post_id = (int)$post_id;

            if ($this->settings->is_seo_enabled()) {
                $this->apply_basic_seo_meta($post_id, $context, $article_title);
            }

            $image_attached = '0';
            if ($this->settings->is_featured_image_enabled() && class_exists('WABE_Image')) {
                $image = new WABE_Image();
                $set   = $image->generate_and_attach($post_id, $topic_data['topic']);
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

            WABE_Logger::info('Generator: post generated successfully - post_id=' . $post_id);
            return $post_id;
        } catch (Throwable $e) {
            WABE_Logger::error('Generator exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 文字列/配列題材を統一
     *
     * @param mixed $topic
     * @param string $global_tone
     * @return array
     */
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
        return [
            'topic'                 => $topic_data['topic'],
            'style'                 => $topic_data['style'],
            'tone'                  => $topic_data['tone'],
            'language'              => $this->settings->get_language(),
            'site_context'          => (string)$this->settings->get('site_context', ''),
            'writing_rules'         => (string)$this->settings->get('writing_rules', ''),
            'seo_keyword'           => (string)$this->settings->get('seo_keyword', ''),
            'internal_link_url'     => (string)$this->settings->get('internal_link_url', ''),
            'external_link_url'     => (string)$this->settings->get('external_link_url', ''),
            'enable_internal_links' => $this->settings->is_internal_links_enabled(),
            'enable_external_links' => $this->settings->is_external_links_enabled(),
        ];
    }

    private function generate_title(array $context)
    {
        $prompt = $this->build_title_prompt($context);
        $text   = $this->ai->text($prompt, [
            'temperature'       => 0.8,
            'max_output_tokens' => 180,
        ]);

        $lines = $this->parse_lines($text);
        return !empty($lines[0]) ? sanitize_text_field($lines[0]) : '';
    }

    private function generate_headings(array $context, $article_title)
    {
        $heading_count = max(1, (int)$this->settings->get('heading_count', 3));

        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'heading_count_max')) {
            $heading_count = min($heading_count, (int)WABE_Plan::heading_count_max());
        } elseif (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'title_count_max')) {
            $heading_count = min($heading_count, (int)WABE_Plan::title_count_max());
        }

        $prompt = $this->build_headings_prompt($context, $article_title, $heading_count);
        $text   = $this->ai->text($prompt, [
            'temperature'       => 0.7,
            'max_output_tokens' => 400,
        ]);

        $lines = $this->parse_lines($text);
        return array_slice($lines, 0, $heading_count);
    }

    private function generate_intro(array $context, $article_title)
    {
        $prompt = $this->build_intro_prompt($context, $article_title);
        return trim((string)$this->ai->text($prompt, [
            'temperature'       => 0.7,
            'max_output_tokens' => 500,
        ]));
    }

    private function generate_section(array $context, $article_title, $heading)
    {
        $prompt = $this->build_section_prompt($context, $article_title, $heading);
        return trim((string)$this->ai->text($prompt, [
            'temperature'       => 0.7,
            'max_output_tokens' => 900,
        ]));
    }

    private function assemble_article(array $context, $article_title, $intro, array $sections)
    {
        $parts = [];

        if ($intro !== '') {
            $parts[] = trim($intro);
        }

        foreach ($sections as $section) {
            $heading = trim((string)$section['heading']);
            $body    = trim((string)$section['body']);

            if ($heading === '' || $body === '') {
                continue;
            }

            $parts[] = '## ' . $heading . "\n\n" . $body;
        }

        $content = trim(implode("\n\n", $parts));

        if ($content === '') {
            $content = wp_kses_post($article_title);
        }

        return $content;
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
            $line = trim($line, "- \t\n\r\0\x0B");
            if ($line !== '') {
                $result[] = $line;
            }
        }

        return $result;
    }

    private function inject_internal_link_hint($content, array $context)
    {
        $url = trim((string)$context['internal_link_url']);
        if ($url === '') {
            return $content;
        }

        $block  = "\n\n";
        $block .= "## 関連情報\n\n";
        $block .= 'あわせて読みたいページ: ' . esc_url($url);

        return trim($content . $block);
    }

    public function build_external_links_block(array $context, $article_title)
    {
        $url = trim((string)($context['external_link_url'] ?? ''));
        if ($url === '') {
            return '';
        }

        $lines   = [];
        $lines[] = '## 参考リンク';
        $lines[] = '';
        $lines[] = '- 関連情報: ' . esc_url($url);

        return implode("\n", $lines);
    }

    private function build_title_prompt(array $context)
    {
        $extra = '';

        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . WABE_Utils::wabe_maybe_base64_decode($context['site_context']) . "\n\n";
        }

        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . WABE_Utils::wabe_maybe_base64_decode($context['writing_rules']) . "\n\n";
        }

        if (!empty($context['seo_keyword'])) {
            $extra .= "SEO keyword:\n" . $context['seo_keyword'] . "\n\n";
        }

        return trim(
            "You are a professional editor.\n" .
                "Write in {$context['language']}.\n\n" .
                $extra .
                "Topic:\n{$context['topic']}\n\n" .
                "Style:\n{$context['style']}\n\n" .
                "Tone:\n{$context['tone']}\n\n" .
                "Task:\n" .
                "- Generate 1 attractive blog title.\n" .
                "- Keep it natural and suitable for WordPress.\n" .
                "- Output only the title."
        );
    }

    private function build_headings_prompt(array $context, $article_title, $heading_count)
    {
        $extra = '';

        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . WABE_Utils::wabe_maybe_base64_decode($context['site_context']) . "\n\n";
        }

        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . WABE_Utils::wabe_maybe_base64_decode($context['writing_rules']) . "\n\n";
        }

        if (!empty($context['seo_keyword'])) {
            $extra .= "SEO keyword:\n" . $context['seo_keyword'] . "\n\n";
        }

        return trim(
            "You are a professional editor.\n" .
                "Write in {$context['language']}.\n\n" .
                $extra .
                "Topic:\n{$context['topic']}\n\n" .
                "Article title:\n{$article_title}\n\n" .
                "Tone:\n{$context['tone']}\n\n" .
                "Task:\n" .
                "- Generate exactly {$heading_count} section headings.\n" .
                "- Use H2-compatible heading text.\n" .
                "- Output one heading per line only.\n" .
                "- Do not add explanation."
        );
    }

    private function build_intro_prompt(array $context, $article_title)
    {
        $extra = '';

        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . WABE_Utils::wabe_maybe_base64_decode($context['site_context']) . "\n\n";
        }

        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . WABE_Utils::wabe_maybe_base64_decode($context['writing_rules']) . "\n\n";
        }

        if (!empty($context['seo_keyword'])) {
            $extra .= "SEO keyword:\n" . $context['seo_keyword'] . "\n\n";
        }

        return trim(
            "You are a professional blog writer.\n" .
                "Write in {$context['language']}.\n\n" .
                $extra .
                "Topic:\n{$context['topic']}\n\n" .
                "Article title:\n{$article_title}\n\n" .
                "Style:\n{$context['style']}\n\n" .
                "Tone:\n{$context['tone']}\n\n" .
                "Task:\n" .
                "- Write the introduction section only.\n" .
                "- Make it easy to read.\n" .
                "- Use 2 to 4 short paragraphs.\n" .
                "- Do not add a heading line."
        );
    }

    private function build_section_prompt(array $context, $article_title, $heading)
    {
        $extra = '';

        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . WABE_Utils::wabe_maybe_base64_decode($context['site_context']) . "\n\n";
        }

        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . WABE_Utils::wabe_maybe_base64_decode($context['writing_rules']) . "\n\n";
        }

        if (!empty($context['seo_keyword'])) {
            $extra .= "SEO keyword:\n" . $context['seo_keyword'] . "\n\n";
        }

        if (!empty($context['internal_link_url']) && !empty($context['enable_internal_links'])) {
            $extra .= "Internal link candidate URL:\n" . $context['internal_link_url'] . "\n\n";
        }

        return trim(
            "You are a professional blog writer.\n" .
                "Write in {$context['language']}.\n\n" .
                $extra .
                "Topic:\n{$context['topic']}\n\n" .
                "Article title:\n{$article_title}\n\n" .
                "Current heading:\n{$heading}\n\n" .
                "Style:\n{$context['style']}\n\n" .
                "Tone:\n{$context['tone']}\n\n" .
                "Task:\n" .
                "- Write only the body content for this heading.\n" .
                "- Be concrete, helpful, and natural.\n" .
                "- Prefer short readable paragraphs.\n" .
                "- Do not repeat the heading line.\n" .
                "- Do not write a conclusion for the whole article in this section."
        );
    }

    private function apply_basic_seo_meta($post_id, array $context, $article_title)
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
