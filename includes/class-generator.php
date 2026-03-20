<?php

if (!defined('ABSPATH')) exit;

/**
 * ------------------------------------------------------------
 * WP AI Blog Engine - Generator
 * ------------------------------------------------------------
 * このファイル内で責務分割しつつ、1ファイル差し替えで使えるように構成
 *
 * 主な役割:
 * - WABE_Generator                    : 全体オーケストレーター
 * - WABE_Generator_Settings           : 設定値の正規化
 * - WABE_Generator_AI_Factory         : OpenAI / Gemini 切替
 * - WABE_Generator_Prompt_Builder     : 各種プロンプト生成
 * - WABE_Generator_Post_Service       : 投稿作成/更新
 * - WABE_Generator_History_Repository : 履歴/ログ/題材消費
 * - WABE_Generator_Content_Quality    : 重複判定など
 * ------------------------------------------------------------
 */

class WABE_Generator
{
    /** @var WABE_Generator_Settings */
    private $settings;

    /** @var WABE_Generator_Prompt_Builder */
    private $prompt_builder;

    /** @var WABE_Generator_Post_Service */
    private $post_service;

    /** @var WABE_Generator_History_Repository */
    private $history_repository;

    /** @var WABE_Generator_Content_Quality */
    private $quality;

    public function __construct()
    {
        $this->settings           = new WABE_Generator_Settings();
        $this->prompt_builder     = new WABE_Generator_Prompt_Builder();
        $this->post_service       = new WABE_Generator_Post_Service();
        $this->history_repository = new WABE_Generator_History_Repository();
        $this->quality            = new WABE_Generator_Content_Quality();
    }

    /**
     * 管理画面やCronからの標準実行
     *
     * @return int|false 投稿ID or false
     */
    public function run()
    {
        try {
            $topic = $this->history_repository->get_next_topic();

            if (empty($topic) || !is_array($topic)) {
                WABE_Logger::warning('Generator: no topic available.');
                return false;
            }

            $status      = $this->settings->get_post_status();
            $global_tone = $this->settings->get_tone();
            $post_id     = $this->generate($topic, $status, $global_tone);

            if ($post_id) {
                $this->history_repository->remove_first_topic();
            }

            return $post_id;
        } catch (Throwable $e) {
            WABE_Logger::error('Generator Run Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 1件生成
     *
     * @param array|string $topic
     * @param string       $status
     * @param string       $global_tone
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
                    $this->history_repository->add_history([
                        'topic'      => $topic_data['topic'],
                        'title'      => '',
                        'status'     => 'skipped_duplicate',
                        'provider'   => '',
                        'model'      => '',
                        'post_id'    => 0,
                        'message'    => 'Skipped because similar content already exists.',
                        'created_at' => current_time('mysql'),
                    ]);
                    return false;
                }
            }

            $ai_bundle = WABE_Generator_AI_Factory::make($this->settings);
            if (!$ai_bundle['client']) {
                WABE_Logger::error('Generator: AI client not available.');
                return false;
            }

            /** @var object $ai */
            $ai       = $ai_bundle['client'];
            $provider = $ai_bundle['provider'];
            $model    = $ai_bundle['model'];

            $context = [
                'topic'             => $topic_data['topic'],
                'style'             => $topic_data['style'],
                'tone'              => $topic_data['tone'],
                'language'          => $this->settings->get_ai_language(),
                'locale'            => get_locale(),
                'site_context'      => $this->settings->get_site_context(),
                'writing_rules'     => $this->settings->get_writing_rules(),
                'author_name'       => $this->settings->get_author_name(),
                'seo_keyword'       => $this->settings->get_seo_keyword(),
                'internal_link_url' => $this->settings->get_internal_link_url(),
                'external_link_url' => $this->settings->get_external_link_url(),
                'heading_count'     => $this->settings->get_heading_count(),
            ];

            $article_title = $this->generate_article_title($ai, $model, $context);
            if ($article_title === '') {
                WABE_Logger::error('Generator: article title generation failed - ' . $topic_data['topic']);
                return false;
            }

            $headings = $this->generate_headings($ai, $model, $context, $article_title);
            if (empty($headings)) {
                WABE_Logger::error('Generator: heading generation failed - ' . $topic_data['topic']);
                return false;
            }

            $content = $this->build_article_content($ai, $model, $context, $article_title, $headings);
            if ($content === '') {
                WABE_Logger::error('Generator: content generation failed - ' . $topic_data['topic']);
                return false;
            }

            $post_id = $this->post_service->create_post([
                'post_title'   => $article_title,
                'post_content' => $content,
                'post_status'  => $status,
                'topic'        => $topic_data['topic'],
                'provider'     => $provider,
                'model'        => $model,
                'seo_keyword'  => $this->settings->get_seo_keyword(),
            ]);

            if (!$post_id) {
                WABE_Logger::error('Generator: post creation failed - ' . $topic_data['topic']);
                return false;
            }

            $image_attached = false;
            if ($this->settings->is_featured_image_enabled() && class_exists('WABE_Image')) {
                try {
                    $image = new WABE_Image();
                    $image_attached = (bool)$image->generate_and_attach($post_id, $article_title);
                } catch (Throwable $e) {
                    WABE_Logger::warning('Generator: image attach failed - ' . $e->getMessage());
                }
            }

            $this->history_repository->add_history([
                'topic'          => $topic_data['topic'],
                'title'          => $article_title,
                'status'         => $status,
                'provider'       => $provider,
                'model'          => $model,
                'post_id'        => (int)$post_id,
                'image_attached' => $image_attached ? '1' : '0',
                'message'        => 'Post generated successfully.',
                'created_at'     => current_time('mysql'),
            ]);

            WABE_Logger::info(
                'Generator: success - post_id=' . $post_id .
                    ' provider=' . $provider .
                    ' model=' . $model .
                    ' status=' . $status
            );

            return (int)$post_id;
        } catch (Throwable $e) {
            WABE_Logger::error('Generator Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 題材1件から記事タイトルを生成
     */
    private function generate_article_title($ai, $model, array $context)
    {
        $prompt = $this->prompt_builder->build_title_prompt($context);

        $titles_text = $this->ai_text($ai, $prompt, [
            'model'             => $model,
            'temperature'       => 0.8,
            'max_output_tokens' => 1200,
        ]);

        if ($titles_text === '') {
            return '';
        }

        $candidates = $this->parse_lines($titles_text);
        if (empty($candidates)) {
            return '';
        }

        $title = trim((string)$candidates[0]);
        $title = preg_replace('/^\d+[\.\)\-、]\s*/u', '', $title);
        $title = trim((string)$title);

        return sanitize_text_field($title);
    }

    /**
     * 見出し生成
     */
    private function generate_headings($ai, $model, array $context, $article_title)
    {
        $prompt = $this->prompt_builder->build_headings_prompt($context, $article_title);

        $raw = $this->ai_text($ai, $prompt, [
            'model'             => $model,
            'temperature'       => 0.7,
            'max_output_tokens' => 1800,
        ]);

        if ($raw === '') {
            return [];
        }

        $headings = $this->parse_lines($raw);
        $normalized = [];

        foreach ($headings as $heading) {
            $heading = trim((string)$heading);
            $heading = preg_replace('/^#+\s*/u', '', $heading);
            $heading = preg_replace('/^\d+[\.\)\-、]\s*/u', '', $heading);
            $heading = sanitize_text_field($heading);

            if ($heading === '') {
                continue;
            }

            $normalized[] = $heading;
        }

        $normalized = array_values(array_unique($normalized));
        $limit = max(1, (int)$context['heading_count']);

        return array_slice($normalized, 0, $limit);
    }

    /**
     * 本文全体構築
     */
    private function build_article_content($ai, $model, array $context, $article_title, array $headings)
    {
        $parts = [];

        $intro_prompt = $this->prompt_builder->build_intro_prompt($context, $article_title);
        $intro = $this->ai_text($ai, $intro_prompt, [
            'model'             => $model,
            'temperature'       => 0.7,
            'max_output_tokens' => 1400,
        ]);

        if ($intro !== '') {
            $parts[] = trim($intro);
        }

        foreach ($headings as $heading) {
            $outline = '';

            if ($this->settings->is_outline_generator_enabled() && class_exists('WABE_Outline_Generator')) {
                try {
                    if (method_exists('WABE_Outline_Generator', 'generate')) {
                        $outline = (string)WABE_Outline_Generator::generate($heading);
                    }
                } catch (Throwable $e) {
                    WABE_Logger::warning('Generator: outline failed - ' . $e->getMessage());
                }
            }

            $section_prompt = $this->prompt_builder->build_section_prompt(
                $context,
                $article_title,
                $heading,
                $outline
            );

            $section = $this->ai_text($ai, $section_prompt, [
                'model'             => $model,
                'temperature'       => 0.7,
                'max_output_tokens' => 2200,
            ]);

            $section = trim($section);

            if ($section === '') {
                WABE_Logger::warning('Generator: section empty for heading - ' . $heading);
                continue;
            }

            $parts[] = '## ' . $heading . "\n\n" . $section;
        }

        if ($this->settings->is_external_links_enabled()) {
            $block = $this->prompt_builder->build_external_links_block($context, $article_title);
            if ($block !== '') {
                $parts[] = $block;
            }
        }

        $content = trim(implode("\n\n", array_filter($parts)));

        if ($content === '') {
            return '';
        }

        if ($this->settings->is_internal_links_enabled()) {
            $content = $this->inject_internal_link_hint($content, $context);
        }

        if ($this->settings->is_seo_enabled()) {
            $content = $this->apply_basic_seo_cleanup($content, $context, $article_title);
        }

        return trim($content);
    }

    /**
     * AI応答テキスト取得
     */
    private function ai_text($ai, $prompt, array $args = [])
    {
        if (!is_object($ai) || !method_exists($ai, 'text')) {
            return '';
        }

        try {
            $result = $ai->text($prompt, $args);

            if (is_array($result)) {
                if (!empty($result['text']) && is_string($result['text'])) {
                    return trim($result['text']);
                }
                if (!empty($result['content']) && is_string($result['content'])) {
                    return trim($result['content']);
                }
                return '';
            }

            return is_string($result) ? trim($result) : '';
        } catch (Throwable $e) {
            WABE_Logger::error('Generator AI Error: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * 文字列/配列題材を統一
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

    /**
     * 改行ベースで行配列に変換
     */
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

    /**
     * 内部リンク補助ブロック
     */
    private function inject_internal_link_hint($content, array $context)
    {
        $url = trim((string)$context['internal_link_url']);
        if ($url === '') {
            return $content;
        }

        $block  = "\n\n";
        $block .= "## 関連情報\n\n";
        $block .= 'あわせて読みたいページ: ' . esc_url_raw($url) . "\n";

        return trim($content . $block);
    }

    /**
     * SEO有効時の軽い整形
     */
    private function apply_basic_seo_cleanup($content, array $context, $article_title)
    {
        $keyword = trim((string)$context['seo_keyword']);

        $content = preg_replace("/\n{3,}/u", "\n\n", (string)$content);
        $content = trim((string)$content);

        if ($keyword !== '' && mb_stripos($article_title, $keyword) === false) {
            $content = "※この記事では「{$keyword}」の観点も踏まえて解説します。\n\n" . $content;
        }

        return trim($content);
    }

    /**
     * 後方互換: 旧コードから呼ばれる可能性のあるメソッド群
     */
    public function get_ai_language($locale = '')
    {
        return $this->settings->get_ai_language($locale);
    }

    public function is_duplicate_check_enabled()
    {
        return $this->settings->is_duplicate_check_enabled();
    }

    public function is_external_links_enabled()
    {
        return $this->settings->is_external_links_enabled();
    }

    public function is_internal_links_enabled()
    {
        return $this->settings->is_internal_links_enabled();
    }

    public function is_seo_enabled()
    {
        return $this->settings->is_seo_enabled();
    }

    public function is_similar_post_exists($topic_text)
    {
        return $this->quality->is_similar_post_exists($topic_text);
    }
}

/**
 * 設定取得・正規化
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

    public function get_options()
    {
        return $this->options;
    }

    public function get_provider()
    {
        $provider = sanitize_key($this->options['ai_provider'] ?? 'openai');
        return in_array($provider, ['openai', 'gemini'], true) ? $provider : 'openai';
    }

    public function get_openai_model()
    {
        return sanitize_text_field($this->options['openai_model'] ?? 'gpt-4.1');
    }

    public function get_gemini_model()
    {
        return sanitize_text_field($this->options['gemini_model'] ?? 'gemini-2.5-flash');
    }

    public function get_heading_count()
    {
        $count = (int)($this->options['heading_count'] ?? 3);
        return max(1, $count);
    }

    public function get_tone()
    {
        $tone = sanitize_text_field($this->options['tone'] ?? 'standard');
        return in_array($tone, ['standard', 'polite', 'casual'], true) ? $tone : 'standard';
    }

    public function get_post_status()
    {
        $status = sanitize_text_field($this->options['post_status'] ?? 'draft');
        return in_array($status, ['draft', 'publish'], true) ? $status : 'draft';
    }

    public function get_site_context()
    {
        return sanitize_textarea_field($this->options['site_context'] ?? '');
    }

    public function get_writing_rules()
    {
        return sanitize_textarea_field($this->options['writing_rules'] ?? '');
    }

    public function get_author_name()
    {
        return sanitize_text_field($this->options['author_name'] ?? '');
    }

    public function get_seo_keyword()
    {
        return sanitize_text_field($this->options['seo_keyword'] ?? '');
    }

    public function get_internal_link_url()
    {
        return esc_url_raw($this->options['internal_link_url'] ?? '');
    }

    public function get_external_link_url()
    {
        return esc_url_raw($this->options['external_link_url'] ?? '');
    }

    public function is_featured_image_enabled()
    {
        return $this->plan_bool('can_use_images') && !empty($this->options['enable_featured_image']);
    }

    public function is_seo_enabled()
    {
        return $this->plan_bool('can_use_seo') && !empty($this->options['enable_seo']);
    }

    public function is_internal_links_enabled()
    {
        return $this->plan_bool('can_use_internal_links') && !empty($this->options['enable_internal_links']);
    }

    public function is_external_links_enabled()
    {
        return $this->plan_bool('can_use_external_links') && !empty($this->options['enable_external_links']);
    }

    public function is_topic_prediction_enabled()
    {
        return $this->plan_bool('can_use_topic_prediction') && !empty($this->options['enable_topic_prediction']);
    }

    public function is_duplicate_check_enabled()
    {
        return $this->plan_bool('can_use_duplicate_check') && !empty($this->options['enable_duplicate_check']);
    }

    public function is_outline_generator_enabled()
    {
        return $this->plan_bool('can_use_outline_generator') && !empty($this->options['enable_outline_generator']);
    }

    public function can_publish()
    {
        return $this->plan_bool('can_publish');
    }

    public function get_ai_language($locale = '')
    {
        if ($locale === '') {
            $locale = get_locale();
        }

        $locale = strtolower((string)$locale);

        if (strpos($locale, 'ja') === 0) {
            return 'Japanese';
        }
        if (strpos($locale, 'en') === 0) {
            return 'English';
        }
        if (strpos($locale, 'fr') === 0) {
            return 'French';
        }
        if (strpos($locale, 'de') === 0) {
            return 'German';
        }
        if (strpos($locale, 'es') === 0) {
            return 'Spanish';
        }
        if (strpos($locale, 'it') === 0) {
            return 'Italian';
        }
        if (strpos($locale, 'pt') === 0) {
            return 'Portuguese';
        }
        if (strpos($locale, 'ko') === 0) {
            return 'Korean';
        }
        if (strpos($locale, 'zh') === 0) {
            return 'Chinese';
        }

        return 'Japanese';
    }

    private function plan_bool($key)
    {
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', $key)) {
            return (bool)call_user_func(['WABE_Plan', $key]);
        }

        if (class_exists('WABE_License') && method_exists('WABE_License', 'sync')) {
            $license = WABE_License::sync(false);
            return !empty($license['features'][$key]);
        }

        return false;
    }
}

/**
 * AIクライアント生成
 */
class WABE_Generator_AI_Factory
{
    public static function make(WABE_Generator_Settings $settings)
    {
        $provider = $settings->get_provider();

        if ($provider === 'gemini' && class_exists('WABE_Gemini')) {
            return [
                'client'   => new WABE_Gemini(),
                'provider' => 'gemini',
                'model'    => $settings->get_gemini_model(),
            ];
        }

        if (class_exists('WABE_OpenAI')) {
            return [
                'client'   => new WABE_OpenAI(),
                'provider' => 'openai',
                'model'    => $settings->get_openai_model(),
            ];
        }

        return [
            'client'   => null,
            'provider' => '',
            'model'    => '',
        ];
    }
}

/**
 * プロンプト構築
 */
class WABE_Generator_Prompt_Builder
{
    public function build_title_prompt(array $context)
    {
        $candidate_count = 1;
        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'title_count_max')) {
            $candidate_count = max(1, (int)WABE_Plan::title_count_max());
        }

        $extra = '';
        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . $context['site_context'] . "\n\n";
        }
        if (!empty($context['author_name'])) {
            $extra .= "Author / Brand:\n" . $context['author_name'] . "\n\n";
        }
        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . $context['writing_rules'] . "\n\n";
        }
        if (!empty($context['seo_keyword'])) {
            $extra .= "Important SEO keyword:\n" . $context['seo_keyword'] . "\n\n";
        }

        return trim(
            "You are a professional SEO blog writer.\n" .
                "Write in {$context['language']}.\n\n" .
                $extra .
                "Topic:\n{$context['topic']}\n\n" .
                "Style:\n{$context['style']}\n\n" .
                "Tone:\n{$context['tone']}\n\n" .
                "Task:\n" .
                "- Generate {$candidate_count} blog title candidate(s).\n" .
                "- Make them attractive and natural.\n" .
                "- Keep them suitable for WordPress blog posts.\n" .
                "- Do not add explanation.\n" .
                "- Output one title per line only."
        );
    }

    public function build_headings_prompt(array $context, $article_title)
    {
        $extra = '';
        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . $context['site_context'] . "\n\n";
        }
        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . $context['writing_rules'] . "\n\n";
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
                "- Generate exactly {$context['heading_count']} headings.\n" .
                "- Keep headings useful and non-redundant.\n" .
                "- Do not include numbering symbols if possible.\n" .
                "- Output one heading per line only."
        );
    }

    public function build_intro_prompt(array $context, $article_title)
    {
        $extra = '';
        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . $context['site_context'] . "\n\n";
        }
        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . $context['writing_rules'] . "\n\n";
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
                "- Do not add a heading label like Introduction."
        );
    }

    public function build_section_prompt(array $context, $article_title, $heading, $outline = '')
    {
        $extra = '';
        if (!empty($context['site_context'])) {
            $extra .= "Site context:\n" . $context['site_context'] . "\n\n";
        }
        if (!empty($context['writing_rules'])) {
            $extra .= "Writing rules:\n" . $context['writing_rules'] . "\n\n";
        }
        if (!empty($context['seo_keyword'])) {
            $extra .= "SEO keyword:\n" . $context['seo_keyword'] . "\n\n";
        }
        if (!empty($outline)) {
            $extra .= "Suggested outline:\n" . $outline . "\n\n";
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

    public function build_external_links_block(array $context, $article_title)
    {
        $url = trim((string)($context['external_link_url'] ?? ''));
        if ($url === '') {
            return '';
        }

        $lines = [];
        $lines[] = '## 参考リンク';
        $lines[] = '';
        $lines[] = '- 関連情報: ' . esc_url_raw($url);

        return implode("\n", $lines);
    }
}

/**
 * 投稿保存
 */
class WABE_Generator_Post_Service
{
    public function create_post(array $data)
    {
        $postarr = [
            'post_title'   => sanitize_text_field($data['post_title'] ?? ''),
            'post_content' => wp_kses_post($this->to_wp_html($data['post_content'] ?? '')),
            'post_status'  => in_array(($data['post_status'] ?? 'draft'), ['draft', 'publish'], true) ? $data['post_status'] : 'draft',
            'post_type'    => 'post',
        ];

        if ($postarr['post_title'] === '' || $postarr['post_content'] === '') {
            return false;
        }

        $post_id = wp_insert_post($postarr, true);
        if (is_wp_error($post_id)) {
            WABE_Logger::error('Post Service: wp_insert_post failed - ' . $post_id->get_error_message());
            return false;
        }

        if (!empty($data['topic'])) {
            update_post_meta($post_id, '_wabe_topic', sanitize_text_field($data['topic']));
        }
        if (!empty($data['provider'])) {
            update_post_meta($post_id, '_wabe_provider', sanitize_key($data['provider']));
        }
        if (!empty($data['model'])) {
            update_post_meta($post_id, '_wabe_model', sanitize_text_field($data['model']));
        }
        if (!empty($data['seo_keyword'])) {
            update_post_meta($post_id, '_wabe_seo_keyword', sanitize_text_field($data['seo_keyword']));
        }
        update_post_meta($post_id, '_wabe_generated_at', current_time('mysql'));

        return (int)$post_id;
    }

    private function to_wp_html($markdown_like_text)
    {
        $text = trim((string)$markdown_like_text);
        if ($text === '') {
            return '';
        }

        $lines = preg_split("/\r\n|\r|\n/u", $text);
        if (!is_array($lines)) {
            return esc_html($text);
        }

        $html = [];
        $paragraph = '';

        foreach ($lines as $line) {
            $line = rtrim((string)$line);

            if (preg_match('/^##\s+(.+)$/u', $line, $m)) {
                if ($paragraph !== '') {
                    $html[] = '<p>' . nl2br(esc_html(trim($paragraph))) . '</p>';
                    $paragraph = '';
                }
                $html[] = '<h2>' . esc_html(trim($m[1])) . '</h2>';
                continue;
            }

            if (preg_match('/^###\s+(.+)$/u', $line, $m)) {
                if ($paragraph !== '') {
                    $html[] = '<p>' . nl2br(esc_html(trim($paragraph))) . '</p>';
                    $paragraph = '';
                }
                $html[] = '<h3>' . esc_html(trim($m[1])) . '</h3>';
                continue;
            }

            if (trim($line) === '') {
                if ($paragraph !== '') {
                    $html[] = '<p>' . nl2br(esc_html(trim($paragraph))) . '</p>';
                    $paragraph = '';
                }
                continue;
            }

            if ($paragraph !== '') {
                $paragraph .= "\n";
            }
            $paragraph .= $line;
        }

        if ($paragraph !== '') {
            $html[] = '<p>' . nl2br(esc_html(trim($paragraph))) . '</p>';
        }

        return implode("\n", $html);
    }
}

/**
 * 履歴と題材管理
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
            'topic'          => sanitize_text_field($record['topic'] ?? ''),
            'title'          => sanitize_text_field($record['title'] ?? ''),
            'status'         => sanitize_text_field($record['status'] ?? ''),
            'provider'       => sanitize_text_field($record['provider'] ?? ''),
            'model'          => sanitize_text_field($record['model'] ?? ''),
            'post_id'        => (int)($record['post_id'] ?? 0),
            'image_attached' => sanitize_text_field($record['image_attached'] ?? '0'),
            'message'        => sanitize_text_field($record['message'] ?? ''),
            'created_at'     => sanitize_text_field($record['created_at'] ?? current_time('mysql')),
        ]);

        $options['history'] = array_slice($history, 0, 100);
        update_option(WABE_OPTION, $options);
    }
}

/**
 * 品質関連
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
        $post_ids = $wpdb->get_col(
            $wpdb->prepare(
                "
                SELECT ID
                FROM {$wpdb->posts}
                WHERE post_type = %s
                  AND post_status IN ('publish','draft','future','pending','private')
                  AND (post_title LIKE %s OR post_content LIKE %s)
                LIMIT 5
                ",
                'post',
                $like,
                $like
            )
        );

        return !empty($post_ids);
    }
}
