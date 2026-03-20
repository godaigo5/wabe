<?php
if (!defined('ABSPATH')) exit;

class WABE_Topic_Generator
{
    /**
     * AIクライアント取得
     *
     * @return object|null
     */
    private static function get_client()
    {
        $o = get_option(WABE_OPTION, []);
        $provider = sanitize_key($o['ai_provider'] ?? 'openai');

        if ($provider === 'gemini' && class_exists('WABE_Gemini')) {
            return new WABE_Gemini();
        }

        if (class_exists('WABE_OpenAI')) {
            return new WABE_OpenAI();
        }

        return null;
    }

    /**
     * モデル名取得
     *
     * @return string
     */
    private static function get_model()
    {
        $o = get_option(WABE_OPTION, []);
        $provider = sanitize_key($o['ai_provider'] ?? 'openai');

        if ($provider === 'gemini') {
            return sanitize_text_field($o['gemini_model'] ?? 'gemini-2.5-flash');
        }

        return sanitize_text_field($o['openai_model'] ?? 'gpt-4.1');
    }

    /**
     * 既存題材一覧
     *
     * @return array
     */
    private static function existing_topic_texts()
    {
        $o = get_option(WABE_OPTION, []);
        $out = [];

        foreach (($o['topics'] ?? []) as $t) {
            if (!empty($t['topic'])) {
                $out[] = mb_strtolower((string)$t['topic']);
            }
        }

        foreach (($o['history'] ?? []) as $h) {
            if (!empty($h['topic'])) {
                $out[] = mb_strtolower((string)$h['topic']);
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * サイト傾向ベースで題材候補を生成
     *
     * @param int $limit
     * @return array
     */
    public static function predict_topics($limit = 5)
    {
        $limit = max(1, min(10, (int)$limit));

        $client = self::get_client();
        if (!$client || !method_exists($client, 'text')) {
            return [];
        }

        $context = self::build_site_trend_context();
        if ($context === '') {
            return [];
        }

        $prompt = trim(
            "You are a professional content strategist.\n" .
                "Based on the site trend information below, propose {$limit} blog topic ideas that are likely to fit the site's direction.\n\n" .
                "Rules:\n" .
                "- Output one topic per line only.\n" .
                "- No numbering.\n" .
                "- No explanation.\n" .
                "- Each line should be a practical article topic.\n" .
                "- Avoid duplicates or near-duplicates.\n" .
                "- Keep topics suitable for a WordPress blog.\n\n" .
                "Site trend information:\n{$context}"
        );

        $text = $client->text($prompt, [
            'model'             => self::get_model(),
            'temperature'       => 0.8,
            'max_output_tokens' => 350,
        ]);

        if (!is_string($text) || trim($text) === '') {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/u', $text);
        if (!is_array($lines)) {
            return [];
        }

        $existing = self::existing_topic_texts();
        $topics = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\s*[\-\*\d\.\)\(]+?\s*/u', '', (string)$line));
            if ($line === '') {
                continue;
            }

            $san = sanitize_text_field($line);
            if ($san === '') {
                continue;
            }

            $lower = mb_strtolower($san);
            if (in_array($lower, $existing, true)) {
                continue;
            }

            $topics[] = [
                'topic' => $san,
                'style' => 'normal',
                'tone'  => 'standard',
            ];

            $existing[] = $lower;

            if (count($topics) >= $limit) {
                break;
            }
        }

        return $topics;
    }

    /**
     * 題材候補をキューへ追加
     *
     * @param int $limit
     * @return int
     */
    public static function append_predicted_topics($limit = 5)
    {
        $o = get_option(WABE_OPTION, []);
        $topics = is_array($o['topics'] ?? null) ? $o['topics'] : [];

        $space = max(0, 10 - count($topics));
        if ($space <= 0) {
            return 0;
        }

        $predicted = self::predict_topics(min($limit, $space));
        if (empty($predicted)) {
            return 0;
        }

        foreach ($predicted as $row) {
            $topics[] = [
                'topic' => sanitize_text_field($row['topic'] ?? ''),
                'style' => sanitize_text_field($row['style'] ?? 'normal'),
                'tone'  => sanitize_text_field($row['tone'] ?? 'standard'),
            ];
        }

        $o['topics'] = array_slice($topics, 0, 10);
        update_option(WABE_OPTION, $o);

        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('Predicted topics appended: ' . count($predicted));
        }

        return count($predicted);
    }

    /**
     * サイト傾向コンテキスト構築
     *
     * @return string
     */
    private static function build_site_trend_context()
    {
        $o = get_option(WABE_OPTION, []);
        $chunks = [];

        if (!empty($o['site_context'])) {
            $chunks[] = "Site context:\n" . sanitize_textarea_field($o['site_context']);
        }

        if (!empty($o['writing_rules'])) {
            $chunks[] = "Writing rules:\n" . sanitize_textarea_field($o['writing_rules']);
        }

        if (!empty($o['seo_keyword'])) {
            $chunks[] = "SEO keyword:\n" . sanitize_text_field($o['seo_keyword']);
        }

        $recent_posts = get_posts([
            'post_type'              => 'post',
            'post_status'            => ['publish', 'draft'],
            'posts_per_page'         => 8,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);

        if (!empty($recent_posts)) {
            $post_lines = [];
            foreach ($recent_posts as $post) {
                $title = sanitize_text_field(get_the_title($post));
                if ($title !== '') {
                    $post_lines[] = '- ' . $title;
                }
            }

            if (!empty($post_lines)) {
                $chunks[] = "Recent post titles:\n" . implode("\n", $post_lines);
            }
        }

        $categories = get_categories([
            'hide_empty' => false,
            'number'     => 10,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]);

        if (!empty($categories) && !is_wp_error($categories)) {
            $cat_lines = [];
            foreach ($categories as $cat) {
                $cat_lines[] = '- ' . sanitize_text_field($cat->name);
            }

            if (!empty($cat_lines)) {
                $chunks[] = "Main categories:\n" . implode("\n", $cat_lines);
            }
        }

        return trim(implode("\n\n", $chunks));
    }
}
