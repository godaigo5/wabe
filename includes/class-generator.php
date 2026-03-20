<?php
if (!defined('ABSPATH')) exit;

class WABE_Generator
{
    public function run()
    {
        try {
            $options = get_option(WABE_OPTION, []);
            $topics  = $options['topics'] ?? [];

            if (empty($topics) || !is_array($topics)) {
                WABE_Logger::warning('Generator: no topics found');
                return false;
            }

            $topic       = $topics[0];
            $status      = sanitize_text_field($options['post_status'] ?? 'draft');
            $global_tone = sanitize_text_field($options['tone'] ?? 'standard');

            $post_id = $this->generate($topic, $status, $global_tone);

            if ($post_id) {
                $this->remove_first_topic();
            }

            return $post_id;
        } catch (Throwable $e) {
            WABE_Logger::error('Generator Error: ' . $e->getMessage());
            return false;
        }
    }

    public function generate($topic, $status, $global_tone = 'standard')
    {
        $topic_text = sanitize_text_field($topic['topic'] ?? '');
        $style      = sanitize_text_field($topic['style'] ?? 'normal');
        $tone       = sanitize_text_field($topic['tone'] ?? $global_tone);

        if ($topic_text === '') {
            WABE_Logger::warning('Generator: topic text empty');
            return false;
        }

        if (!in_array($tone, ['standard', 'polite', 'casual'], true)) {
            $tone = 'standard';
        }

        if (!in_array($status, ['draft', 'publish'], true)) {
            $status = 'draft';
        }

        $options       = get_option(WABE_OPTION, []);
        $provider      = sanitize_key($options['ai_provider'] ?? 'openai');
        $heading_count = max(1, intval($options['heading_count'] ?? 1));

        if ($provider === 'gemini' && class_exists('WABE_Gemini')) {
            $ai       = new WABE_Gemini();
            $model    = sanitize_text_field($options['gemini_model'] ?? 'gemini-2.5-flash');
            $provider = 'gemini';
        } else {
            $ai       = new WABE_OpenAI();
            $model    = sanitize_text_field($options['openai_model'] ?? 'gpt-4.1');
            $provider = 'openai';
        }

        $image    = class_exists('WABE_Image') ? new WABE_Image() : null;
        $locale   = get_locale();
        $language = $this->get_ai_language($locale);

        if ($this->is_duplicate_check_enabled() && $this->is_similar_post_exists($topic_text)) {
            WABE_Logger::warning('Generator: duplicate topic skipped - ' . $topic_text);
            return false;
        }

        $article_title = $this->generate_article_title($ai, $model, $topic_text, $style, $tone, $language, $locale);
        if ($article_title === '') {
            WABE_Logger::error('Generator: article title generation failed - ' . $topic_text);
            return false;
        }

        $headings = $this->generate_headings($ai, $model, $topic_text, $article_title, $style, $tone, $language, $locale, $heading_count);
        if (empty($headings)) {
            WABE_Logger::error('Generator: heading generation failed - ' . $topic_text);
            return false;
        }

        $content_parts  = [];
        $intro          = $this->generate_intro($ai, $model, $topic_text, $article_title, $style, $tone, $language, $locale);
        $external_links = $this->is_external_links_enabled() ? $this->build_external_links_block($topic_text) : '';

        if ($intro !== '') {
            $content_parts[] = $intro;
        }

        foreach ($headings as $heading) {
            $outline = '';

            if (WABE_Plan::can_use_outline_generator() && class_exists('WABE_Outline_Generator')) {
                $outline = WABE_Outline_Generator::generate($heading);
            }

            $section_prompt = $this->build_section_prompt(
                $topic_text,
                $article_title,
                $heading,
                $style,
                $tone,
                $language,
                $locale,
                $outline
            );

            $section = trim($ai->text($section_prompt, [
                'model'             => $model,
                'temperature'       => 0.7,
                'max_output_tokens' => 2200,
            ]));

            if ($section === '') {
                WABE_Logger::warning('Generator: section empty for heading - ' . $heading);
                continue;
            }

            $content_parts[] = '## ' . $heading . "\n\n" . $section;
        }

        if ($external_links !== '') {
            $content_parts[] = $external_links;
        }

        if (empty($content_parts)) {
            WABE_Logger::error('Generator: content generation failed - ' . $topic_text);
            return false;
        }

        $content = implode("\n\n", $content_parts);

        if (WABE_Plan::can_use_seo() && class_exists('WABE_SEO')) {
            $seo     = new WABE_SEO();
            $content = $seo->optimize($content, $topic_text);
        }

        if (WABE_Plan::can_use_internal_links() && class_exists('WABE_Internal_Links')) {
            $content = WABE_Internal_Links::generate($content);
        }

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($article_title),
            'post_content' => wp_kses_post($content),
            'post_status'  => $status,
            'post_type'    => 'post',
        ], true);

        if (is_wp_error($post_id)) {
            WABE_Logger::error('Generator: post insert failed - ' . $post_id->get_error_message());
            return false;
        }

        WABE_Logger::info('Generator: post created ID=' . $post_id);

        if (WABE_Plan::can_use_images() && $image && $this->is_featured_image_enabled()) {
            try {
                $image_source = $image->generate($topic_text);

                if (!empty($image_source)) {
                    $image->set_featured_image($post_id, $image_source);
                    WABE_Logger::info('Generator: featured image set - post_id=' . $post_id);
                } else {
                    WABE_Logger::warning('Generator: image not generated - ' . $topic_text);
                }
            } catch (Throwable $e) {
                WABE_Logger::error('Generator Image Error: ' . $e->getMessage());
            }
        }

        $this->save_history([
            'date'          => current_time('mysql'),
            'topic'         => $topic_text,
            'style'         => $style,
            'tone'          => $tone,
            'status'        => $status,
            'post_id'       => $post_id,
            'title'         => $article_title,
            'heading_count' => count($headings),
            'headings'      => $headings,
            'provider'      => $provider,
            'model'         => $model,
        ]);

        return $post_id;
    }

    private function generate_article_title($ai, $model, $topic, $style, $tone, $language, $locale)
    {
        $prompt = $this->build_article_title_prompt($topic, $style, $tone, $language, $locale);

        $response = trim($ai->text($prompt, [
            'model'             => $model,
            'temperature'       => 0.8,
            'max_output_tokens' => 300,
        ]));

        if ($response === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $response);
        foreach ($lines as $line) {
            $line = trim($line);
            $line = preg_replace('/^\s*[\-\*\d\.\)\(]+?\s*/u', '', $line);
            $line = $this->normalize_title($line);

            if ($line !== '') {
                return $line;
            }
        }

        return '';
    }

    private function generate_headings($ai, $model, $topic, $article_title, $style, $tone, $language, $locale, $count)
    {
        $prompt = $this->build_headings_prompt($topic, $article_title, $style, $tone, $language, $locale, $count);

        $response = trim($ai->text($prompt, [
            'model'             => $model,
            'temperature'       => 0.8,
            'max_output_tokens' => 700,
        ]));

        if ($response === '') {
            return [];
        }

        $lines    = preg_split('/\r\n|\r|\n/', $response);
        $headings = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $line = preg_replace('/^\s*#{1,6}\s*/u', '', $line);
            $line = preg_replace('/^\s*[\-\*\d\.\)\(]+?\s*/u', '', $line);
            $line = $this->normalize_title($line);

            if ($line !== '') {
                $headings[] = $line;
            }
        }

        $headings = array_values(array_unique($headings));
        $headings = array_slice($headings, 0, $count);

        WABE_Logger::info('Generator: headings generated count=' . count($headings));

        return $headings;
    }

    private function generate_intro($ai, $model, $topic, $article_title, $style, $tone, $language, $locale)
    {
        $prompt = $this->build_intro_prompt($topic, $article_title, $style, $tone, $language, $locale);

        return trim($ai->text($prompt, [
            'model'             => $model,
            'temperature'       => 0.7,
            'max_output_tokens' => 900,
        ]));
    }

    private function build_article_title_prompt($topic, $style, $tone, $language, $locale)
    {
        return "
You are a professional SEO writer.
Create one SEO-friendly blog title for the following topic.

Topic: {$topic}
Style: {$style}
Tone: {$tone}
Language: {$language}
Locale: {$locale}

Requirements:
- Create exactly 1 title
- Natural and attractive
- Include the main keyword naturally
- Max 32 characters if possible
- Do not use quotation marks
- Output only the title
";
    }

    private function build_headings_prompt($topic, $article_title, $style, $tone, $language, $locale, $count)
    {
        return "
You are a professional SEO writer.
Create {$count} blog headings for the article below.

Main Topic: {$topic}
Article Title: {$article_title}
Style: {$style}
Tone: {$tone}
Language: {$language}
Locale: {$locale}

Requirements:
- Create exactly {$count} headings
- Each heading should cover a different subtopic
- Make headings useful for readers
- Keep them clear and natural
- Do not use quotation marks
- Output only the headings, one per line
";
    }

    private function build_intro_prompt($topic, $article_title, $style, $tone, $language, $locale)
    {
        return "
You are a professional SEO writer.
Write a short introduction for a blog article.

Main Topic: {$topic}
Article Title: {$article_title}
Style: {$style}
Tone: {$tone}
Language: {$language}
Locale: {$locale}

Requirements:
- Beginner friendly
- Natural and readable
- Explain what the reader will learn
- Around 150 to 250 words
- Do not include headings
";
    }

    private function build_section_prompt($topic, $article_title, $heading, $style, $tone, $language, $locale, $outline = '')
    {
        return "
You are a professional SEO writer.
Write a high-quality SEO blog section based on the following heading.

Main Topic: {$topic}
Article Title: {$article_title}
Section Heading: {$heading}
Style: {$style}
Tone: {$tone}
Language: {$language}
Locale: {$locale}
Outline: {$outline}

Requirements:
- Beginner friendly
- Use H3 headings where appropriate
- Include concrete explanations
- Use bullet points where useful
- Write naturally and clearly
- Avoid robotic expressions
- Optimize for search intent
- Include the main keyword naturally
- Write at least 600 words for this section
";
    }

    private function build_external_links_block($topic)
    {
        $topic_escaped = esc_html($topic);

        $links = [
            '<a href="https://www.google.com/search?q=' . rawurlencode($topic_escaped) . '" target="_blank" rel="noopener noreferrer">Google Search</a>',
            '<a href="https://www.youtube.com/results?search_query=' . rawurlencode($topic_escaped) . '" target="_blank" rel="noopener noreferrer">YouTube</a>',
        ];

        return "## Related Resources\n\n- " . implode("\n- ", $links);
    }

    private function is_duplicate_check_enabled()
    {
        if (!WABE_Plan::can_use_duplicate_check()) {
            return false;
        }

        $options = get_option(WABE_OPTION, []);
        return !empty($options['enable_duplicate_check']) && $options['enable_duplicate_check'] === '1';
    }

    private function is_external_links_enabled()
    {
        if (!WABE_Plan::can_use_external_links()) {
            return false;
        }

        $options = get_option(WABE_OPTION, []);
        return !empty($options['enable_external_links']) && $options['enable_external_links'] === '1';
    }

    private function is_featured_image_enabled()
    {
        if (!WABE_Plan::can_use_images()) {
            return false;
        }

        $options = get_option(WABE_OPTION, []);
        return !empty($options['enable_featured_image']) && $options['enable_featured_image'] === '1';
    }

    private function is_similar_post_exists($topic_text)
    {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => ['publish', 'draft', 'future', 'pending', 'private'],
            'posts_per_page' => 20,
            's'              => $topic_text,
            'fields'         => 'ids',
        ]);

        return !empty($posts);
    }

    private function remove_first_topic()
    {
        $options = get_option(WABE_OPTION, []);
        $topics  = $options['topics'] ?? [];

        if (empty($topics) || !is_array($topics)) {
            return;
        }

        array_shift($topics);
        $options['topics'] = array_values($topics);

        update_option(WABE_OPTION, $options);
        WABE_Logger::info('Generator: first topic removed');
    }

    private function normalize_title($title)
    {
        $title = wp_strip_all_tags($title);
        $title = trim($title);
        $title = preg_replace('/^["\'「『]+|["\'」』]+$/u', '', $title);

        return $title;
    }

    private function save_history($row)
    {
        $options = get_option(WABE_OPTION, []);
        $history = $options['history'] ?? [];

        array_unshift($history, $row);
        $history = array_slice($history, 0, 50);

        $options['history'] = $history;
        update_option(WABE_OPTION, $options);

        WABE_Logger::info('Generator: history saved');
    }

    private function get_ai_language($locale)
    {
        $map = [
            'ja' => 'Japanese',
            'en' => 'English',
            'zh' => 'Chinese',
            'ko' => 'Korean',
            'fr' => 'French',
            'de' => 'German',
            'es' => 'Spanish',
        ];

        $lang = substr((string) $locale, 0, 2);

        return $map[$lang] ?? 'English';
    }
}
