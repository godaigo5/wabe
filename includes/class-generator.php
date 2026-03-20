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
            $title_count = max(1, intval($options['generation_count'] ?? 1));
            $status      = sanitize_text_field($options['post_status'] ?? 'draft');
            $global_tone = sanitize_text_field($options['tone'] ?? 'standard');

            $post_id = $this->generate($topic, $title_count, $status, $global_tone);

            if ($post_id) {
                $this->remove_first_topic();
            }

            return $post_id;
        } catch (Throwable $e) {
            WABE_Logger::error('Generator Error: ' . $e->getMessage());
            return false;
        }
    }

    public function generate($topic, $title_count, $status, $global_tone = 'standard')
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

        $options  = get_option(WABE_OPTION, []);
        $provider = sanitize_key($options['ai_provider'] ?? 'openai');

        if ($provider === 'gemini' && class_exists('WABE_Gemini')) {
            $ai    = new WABE_Gemini();
            $model = sanitize_text_field($options['gemini_model'] ?? 'gemini-2.5-flash');
        } else {
            $ai    = new WABE_OpenAI();
            $model = sanitize_text_field($options['openai_model'] ?? 'gpt-4.1-mini');
        }

        $image    = new WABE_Image();
        $locale   = get_locale();
        $language = $this->get_ai_language($locale);

        $titles = $this->generate_titles($ai, $model, $topic_text, $style, $tone, $language, $locale, $title_count);

        if (empty($titles)) {
            WABE_Logger::error('Generator: title generation failed - ' . $topic_text);
            return false;
        }

        $post_title    = $titles[0];
        $content_parts = [];

        foreach ($titles as $title) {
            $outline = '';

            if (WABE_Plan::can_use_outline_generator() && class_exists('WABE_Outline_Generator')) {
                $outline = WABE_Outline_Generator::generate($title);
            }

            $content_prompt = $this->build_content_prompt(
                $topic_text,
                $title,
                $style,
                $tone,
                $language,
                $locale,
                $outline
            );

            $result = trim($ai->text($content_prompt, [
                'model'             => $model,
                'temperature'       => 0.7,
                'max_output_tokens' => 2200,
            ]));

            if ($result === '') {
                WABE_Logger::warning('Generator: content empty for title - ' . $title);
                continue;
            }

            $section = "## " . esc_html($title) . "\n\n" . $result;
            $content_parts[] = $section;
        }

        if (empty($content_parts)) {
            WABE_Logger::error('Generator: all content generation failed - ' . $topic_text);
            return false;
        }

        $content = implode("\n\n", $content_parts);

        if (WABE_Plan::can_use_seo()) {
            $seo     = new WABE_SEO();
            $content = $seo->optimize($content, $topic_text);
        }

        if (WABE_Plan::can_use_internal_links() && class_exists('WABE_Internal_Links')) {
            $content = WABE_Internal_Links::generate($content);
        }

        $post_id = wp_insert_post([
            'post_title'   => sanitize_text_field($post_title),
            'post_content' => wp_kses_post($content),
            'post_status'  => $status,
            'post_type'    => 'post',
        ], true);

        if (is_wp_error($post_id)) {
            WABE_Logger::error('Generator: post insert failed - ' . $post_id->get_error_message());
            return false;
        }

        WABE_Logger::info('Generator: post created ID=' . $post_id);

        if (WABE_Plan::can_use_images()) {
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
            'date'        => current_time('mysql'),
            'topic'       => $topic_text,
            'style'       => $style,
            'tone'        => $tone,
            'status'      => $status,
            'post_id'     => $post_id,
            'title'       => $post_title,
            'title_count' => count($titles),
            'titles'      => $titles,
            'provider'    => $provider,
            'model'       => $model,
        ]);

        return $post_id;
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

    private function generate_titles($ai, $model, $topic, $style, $tone, $language, $locale, $count)
    {
        $prompt = $this->build_titles_prompt($topic, $style, $tone, $language, $locale, $count);

        $response = trim($ai->text($prompt, [
            'model'             => $model,
            'temperature'       => 0.8,
            'max_output_tokens' => 500,
        ]));

        if ($response === '') {
            return [];
        }

        $lines  = preg_split('/\r\n|\r|\n/', $response);
        $titles = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $line = preg_replace('/^\s*[\-\*\d\.\)\(]+?\s*/u', '', $line);
            $line = $this->normalize_title($line);

            if ($line !== '') {
                $titles[] = $line;
            }
        }

        $titles = array_values(array_unique($titles));
        $titles = array_slice($titles, 0, $count);

        WABE_Logger::info('Generator: titles generated count=' . count($titles));

        return $titles;
    }

    private function build_titles_prompt($topic, $style, $tone, $language, $locale, $count)
    {
        return "
You are a professional SEO writer.
Create {$count} different SEO-friendly blog titles for the following topic.

Topic: {$topic}
Style: {$style}
Tone: {$tone}
Language: {$language}
Locale: {$locale}

Requirements:
- Create exactly {$count} titles
- Each title should be natural and attractive
- Include the main keyword naturally
- Max 32 characters if possible
- Each title should have a slightly different angle
- Do not use quotation marks
- Output only the titles, one per line
";
    }

    private function build_content_prompt($topic, $title, $style, $tone, $language, $locale, $outline = '')
    {
        return "
You are a professional SEO writer.
Write a high-quality SEO blog article section based on the following title.

Main Topic: {$topic}
Section Title: {$title}
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
- Write at least 1200 words for this section
";
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
