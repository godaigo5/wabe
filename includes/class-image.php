<?php
if (!defined('ABSPATH')) exit;

class WABE_Image
{
    private $options;
    private $openai_api_key;
    private $gemini_api_key;
    private $pollinations_api_key;
    private $unsplash_access_key;
    private $image_style;

    public function __construct()
    {
        $this->options = get_option(WABE_OPTION, []);
        if (!is_array($this->options)) {
            $this->options = [];
        }

        $this->openai_api_key       = trim((string) ($this->options['openai_api_key'] ?? ''));
        $this->gemini_api_key       = trim((string) ($this->options['gemini_api_key'] ?? ''));
        $this->pollinations_api_key = trim((string) ($this->options['pollinations_api_key'] ?? ''));
        $this->unsplash_access_key  = trim((string) ($this->options['unsplash_access_key'] ?? ''));
        $this->image_style          = sanitize_key($this->options['image_style'] ?? 'modern');
    }

    /**
     * 記事タイトルから画像生成してアイキャッチ設定
     *
     * @param int $post_id
     * @param string $title
     * @return int|false
     */
    public function generate_and_attach($post_id, $title)
    {
        $post_id = (int) $post_id;
        $title   = trim((string) $title);

        if ($post_id < 1 || $title === '') {
            $this->log_error('Image: invalid arguments for generate_and_attach');
            return false;
        }

        if (!$this->is_enabled()) {
            $this->log_warning('Image: disabled by settings or plan');
            return false;
        }

        $this->log_info('Image class called');
        $this->log_info('OpenAI key exists: ' . (!empty($this->openai_api_key) ? '1' : '0'));
        $this->log_info('Gemini key exists: ' . (!empty($this->gemini_api_key) ? '1' : '0'));
        $this->log_info('Pollinations key exists: ' . (!empty($this->pollinations_api_key) ? '1' : '0'));

        $prompt = $this->build_prompt($title);

        $image = $this->generate_with_openai($prompt);
        if ($image) {
            $this->log_info('Image provider success: openai');
        } else {
            $this->log_warning('Image: OpenAI failed, fallback to Gemini');

            $image = $this->generate_with_gemini($prompt);
            if ($image) {
                $this->log_info('Image provider success: gemini');
            } else {
                $this->log_warning('Image: Gemini failed, fallback to Pollinations');

                $pollinations_prompt = $this->build_prompt_for_pollinations($title);
                $image = $this->generate_with_pollinations($pollinations_prompt);
                if ($image) {
                    $this->log_info('Image provider success: pollinations');
                }
            }
        }

        if (!$image || empty($image['bytes']) || empty($image['mime'])) {
            $this->log_error('Image: all providers failed');
            $this->log_warning('Image: generate_and_attach failed - no image binary returned.');
            return false;
        }

        $attachment_id = $this->save_generated_image($post_id, $title, $image['bytes'], $image['mime']);
        if (!$attachment_id) {
            $this->log_warning('Image: generate_and_attach failed - save_generated_image returned false.');
            return false;
        }

        $set_thumbnail = set_post_thumbnail($post_id, $attachment_id);
        if (!$set_thumbnail) {
            $this->log_warning('Image: set_post_thumbnail returned false - post_id=' . $post_id . ' attachment_id=' . $attachment_id);
        } else {
            $this->log_info('Image: featured image set - post_id=' . $post_id . ' attachment_id=' . $attachment_id);
        }

        return $attachment_id;
    }

    /**
     * 記事途中画像をUnsplashから取得して本文へ挿入
     *
     * @param string $content
     * @param array  $args [topic, title, plan]
     * @return string
     */
    public function inject_unsplash_images_into_content($content, array $args = [])
    {
        $content = (string) $content;

        if ($content === '') {
            return $content;
        }

        if (!$this->can_use_unsplash_in_article()) {
            $this->log_info('Unsplash: skipped (disabled or key missing)');
            return $content;
        }

        $plan     = sanitize_key((string) ($args['plan'] ?? 'free'));
        $topic    = trim((string) ($args['topic'] ?? ''));
        $title    = trim((string) ($args['title'] ?? ''));
        $language = trim((string) ($args['language'] ?? ''));

        $limit = $this->get_in_article_image_limit_by_plan($plan);
        if ($limit < 1) {
            return $content;
        }

        if (strpos($content, 'wabe-inline-unsplash-image') !== false) {
            $this->log_info('Unsplash: skipped because inline image already exists');
            return $content;
        }

        $headings = $this->extract_headings_from_content($content);
        if (empty($headings)) {
            $this->log_info('Unsplash: no headings found');
            return $content;
        }

        $candidate_headings = $this->select_unsplash_target_headings($headings, $limit);
        if (empty($candidate_headings)) {
            $this->log_info('Unsplash: no candidate headings selected');
            return $content;
        }

        $insertions     = [];
        $used_photo_ids = [];
        $used_queries   = [];

        foreach ($candidate_headings as $heading) {
            if (count($insertions) >= $limit) {
                break;
            }

            $queries = $this->build_unsplash_queries($heading['text'], $topic, $title, $language);
            if (empty($queries)) {
                $this->log_info('Unsplash: no query candidates for heading=' . $heading['text']);
                continue;
            }

            $photo = false;
            $used_query = '';

            foreach ($queries as $query) {
                $query = trim((string) $query);
                if ($query === '' || isset($used_queries[$query])) {
                    continue;
                }

                $used_queries[$query] = true;
                $this->log_info('Unsplash: searching query=' . $query);

                $photo = $this->search_unsplash_photo($query, array_keys($used_photo_ids));
                if ($photo) {
                    $used_query = $query;
                    break;
                }
            }

            if (!$photo) {
                $this->log_info('Unsplash: no photo found for heading=' . $heading['text']);
                continue;
            }

            $photo_id = (string) ($photo['id'] ?? '');
            if ($photo_id !== '') {
                $used_photo_ids[$photo_id] = true;
            }

            $block = $this->build_unsplash_image_block($photo, $heading['text']);
            if ($block === '') {
                $this->log_info('Unsplash: block build failed - heading=' . $heading['text']);
                continue;
            }

            $insertions[] = [
                'heading_index' => (int) $heading['index'],
                'block'         => $block,
            ];

            $this->log_info(
                'Unsplash: selected heading tag=' . $heading['tag'] .
                    ' index=' . (int) $heading['index'] .
                    ' text=' . $heading['text'] .
                    ' query=' . $used_query
            );

            $this->ping_unsplash_download($photo);
        }

        if (empty($insertions)) {
            $fallback_heading = $candidate_headings[0] ?? null;
            if (is_array($fallback_heading)) {
                $fallback_queries = $this->build_unsplash_queries('', $topic, $title, $language);
                foreach ($fallback_queries as $query) {
                    if ($query === '' || isset($used_queries[$query])) {
                        continue;
                    }
                    $used_queries[$query] = true;
                    $this->log_info('Unsplash: fallback searching query=' . $query);
                    $photo = $this->search_unsplash_photo($query, array_keys($used_photo_ids));
                    if (!$photo) {
                        continue;
                    }
                    $block = $this->build_unsplash_image_block($photo, (string) ($fallback_heading['text'] ?? ''));
                    if ($block === '') {
                        continue;
                    }
                    $insertions[] = [
                        'heading_index' => (int) ($fallback_heading['index'] ?? 0),
                        'block'         => $block,
                    ];
                    $this->ping_unsplash_download($photo);
                    $this->log_info('Unsplash: fallback image inserted with query=' . $query);
                    break;
                }
            }
        }

        if (empty($insertions)) {
            $this->log_info('Unsplash: no image blocks created');
            return $content;
        }

        $content = $this->insert_blocks_after_headings($content, $insertions);
        $this->log_info('Unsplash: inserted ' . count($insertions) . ' in-article image(s)');

        return $content;
    }


    /**
     * 画像機能が有効か
     */
    private function is_enabled()
    {
        if (empty($this->options['enable_featured_image'])) {
            return false;
        }

        if (class_exists('WABE_License') && method_exists('WABE_License', 'get_feature')) {
            return !empty(WABE_License::get_feature('can_use_images', false));
        }

        if (class_exists('WABE_Plan') && method_exists('WABE_Plan', 'normalize_plan') && method_exists('WABE_Plan', 'plan_matrix')) {
            $plan = WABE_Plan::normalize_plan($this->options['plan'] ?? 'free');
            $matrix = WABE_Plan::plan_matrix();
            return !empty($matrix[$plan]['can_use_images']);
        }

        return false;
    }

    /**
     * 記事途中のUnsplash画像が使えるか
     */
    private function can_use_unsplash_in_article()
    {
        if ($this->unsplash_access_key === '') {
            return false;
        }

        if (empty($this->options['enable_inline_unsplash'])) {
            return true;
        }

        return true;
    }

    /**
     * プランごとの本文画像上限
     */
    private function get_in_article_image_limit_by_plan($plan)
    {
        $plan = sanitize_key((string) $plan);

        switch ($plan) {
            case 'pro':
                return 5;
            case 'advanced':
                return 3;
            case 'free':
            default:
                return 1;
        }
    }

    /**
     * 本文からh2/h3を抽出
     */
    private function extract_headings_from_content($content)
    {
        $content = (string) $content;
        $results = [];

        if (!preg_match_all('/<h([23])\b[^>]*>(.*?)<\/h\1>/isu', $content, $matches, PREG_OFFSET_CAPTURE)) {
            return $results;
        }

        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $raw_html = (string) $matches[0][$i][0];
            $inner    = (string) $matches[2][$i][0];
            $text     = trim(wp_strip_all_tags($inner));

            if ($text === '') {
                continue;
            }

            $results[] = [
                'index' => count($results),
                'html'  => $raw_html,
                'text'  => $text,
                'tag'   => 'h' . (string) $matches[1][$i][0],
            ];
        }

        return $results;
    }

    /**
     * h2優先で本文画像を入れる見出し候補を選ぶ
     *
     * ルール:
     * - まず h2 を優先
     * - h2 だけで足りなければ h3 を追加
     * - 記事内の並び順は崩さない
     *
     * @param array $headings
     * @param int   $limit
     * @return array
     */
    private function select_unsplash_target_headings(array $headings, $limit)
    {
        $limit = max(1, (int) $limit);

        $h2 = [];
        $h3 = [];

        foreach ($headings as $heading) {
            $tag = strtolower((string) ($heading['tag'] ?? ''));
            if ($tag === 'h2') {
                $h2[] = $heading;
            } elseif ($tag === 'h3') {
                $h3[] = $heading;
            }
        }

        $selected = [];

        // まず h2 を優先採用
        foreach ($h2 as $heading) {
            if (count($selected) >= $limit) {
                break;
            }
            $selected[] = $heading;
        }

        // 足りなければ h3 を追加
        if (count($selected) < $limit) {
            foreach ($h3 as $heading) {
                if (count($selected) >= $limit) {
                    break;
                }
                $selected[] = $heading;
            }
        }

        // 実際の本文順に戻す
        usort($selected, function ($a, $b) {
            $aIndex = (int) ($a['index'] ?? 0);
            $bIndex = (int) ($b['index'] ?? 0);

            if ($aIndex === $bIndex) {
                return 0;
            }

            return ($aIndex < $bIndex) ? -1 : 1;
        });

        return $selected;
    }

    /**
     * Unsplash検索クエリ生成
     */

    private function build_unsplash_queries($heading, $topic, $title, $language = '')
    {
        $heading  = $this->normalize_unsplash_query_fragment($heading);
        $topic    = $this->normalize_unsplash_query_fragment($topic);
        $title    = $this->normalize_unsplash_query_fragment($title);
        $language = trim((string) $language);

        $queries = [];

        $primary = $this->build_unsplash_query($heading, $topic, $title);
        if ($primary !== '') {
            $queries[] = $primary;
        }

        if ($heading !== '') {
            $queries[] = $heading;
        }

        if ($topic !== '') {
            $queries[] = $topic;
        }

        if ($title !== '') {
            $queries[] = $title;
        }

        $queries = array_merge(
            $queries,
            $this->build_unsplash_keyword_fallbacks($heading),
            $this->build_unsplash_keyword_fallbacks($topic),
            $this->build_unsplash_keyword_fallbacks($title)
        );

        if (stripos($language, 'japanese') !== false || $this->contains_japanese($heading . ' ' . $topic . ' ' . $title)) {
            $queries = array_merge($queries, $this->build_unsplash_japanese_fallbacks($heading, $topic, $title));
        }

        $queries[] = 'business office technology';
        $queries[] = 'workspace laptop meeting';
        $queries[] = 'digital marketing business';
        $queries[] = 'automation workflow office';

        $queries = array_values(array_unique(array_filter(array_map('trim', $queries))));

        return array_slice($queries, 0, 12);
    }


    private function build_unsplash_query($heading, $topic, $title)
    {
        $heading = $this->normalize_unsplash_query_fragment($heading);
        $topic   = $this->normalize_unsplash_query_fragment($topic);
        $title   = $this->normalize_unsplash_query_fragment($title);

        $parts = [];

        if ($heading !== '') {
            $parts[] = $heading;
        }

        if ($topic !== '') {
            $parts[] = $topic;
        } elseif ($title !== '') {
            $parts[] = $title;
        }

        $query = trim((string) preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts))));
        if ($query === '') {
            return '';
        }

        if (function_exists('mb_strlen') && mb_strlen($query) > 80) {
            $query = mb_substr($query, 0, 80);
        } elseif (strlen($query) > 80) {
            $query = substr($query, 0, 80);
        }

        return $query;
    }


    /**
     * Unsplashから1枚取得
     */

    private function normalize_unsplash_query_fragment($value)
    {
        $value = trim((string) $value);
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/[\x{3000}\r\n\t]+/u', ' ', $value);
        $value = preg_replace('/[「」『』【】()（）［］\[\]、。,:;!！?？]/u', ' ', $value);
        $value = preg_replace('/\s+/u', ' ', (string) $value);

        return trim((string) $value);
    }

    private function contains_japanese($value)
    {
        return (bool) preg_match('/[ぁ-んァ-ヶ一-龠]/u', (string) $value);
    }

    private function build_unsplash_keyword_fallbacks($text)
    {
        $text = strtolower($this->normalize_unsplash_query_fragment($text));
        if ($text === '') {
            return [];
        }

        $map = [
            'ai'           => ['artificial intelligence technology', 'data technology'],
            'blog'         => ['blog writing laptop', 'content marketing'],
            'seo'          => ['search engine optimization', 'digital marketing analytics'],
            'marketing'    => ['digital marketing business', 'marketing strategy office'],
            'wordpress'    => ['website development laptop', 'blog website dashboard'],
            'web'          => ['website development laptop', 'web design workspace'],
            'tool'         => ['software dashboard office', 'business tools workspace'],
            'tools'        => ['software dashboard office', 'business tools workspace'],
            'automation'   => ['automation workflow office', 'business process automation'],
            'nocode'       => ['no code app builder', 'software dashboard workspace'],
            'no-code'      => ['no code app builder', 'software dashboard workspace'],
            'business'     => ['business team office', 'meeting workspace'],
            'productivity' => ['productivity workspace laptop', 'focused desk workspace'],
            'office'       => ['modern office workspace', 'business meeting office'],
            'finance'      => ['finance analytics dashboard', 'business charts laptop'],
            'ranking'      => ['comparison chart laptop', 'product comparison workspace'],
            'review'       => ['product review laptop', 'comparison workspace'],
        ];

        $queries = [];
        foreach ($map as $needle => $fallbacks) {
            if (strpos($text, $needle) !== false) {
                $queries = array_merge($queries, $fallbacks);
            }
        }

        return array_values(array_unique($queries));
    }

    private function build_unsplash_japanese_fallbacks($heading, $topic, $title)
    {
        $text = trim($heading . ' ' . $topic . ' ' . $title);
        $queries = [];

        $map = [
            '業務自動化' => ['automation workflow office', 'business process automation'],
            '自動化'     => ['automation workflow office', 'business process automation'],
            'ノーコード' => ['no code app builder', 'software dashboard workspace'],
            'ツール'     => ['software dashboard office', 'business tools workspace'],
            '活用'       => ['business workflow office', 'productivity workspace laptop'],
            '業務'       => ['business team office', 'meeting workspace'],
            '仕事'       => ['business team office', 'modern office workspace'],
            'ブログ'     => ['blog writing laptop', 'content marketing workspace'],
            '記事'       => ['content writing laptop', 'blog website dashboard'],
            'SEO'       => ['search engine optimization', 'digital marketing analytics'],
            '集客'       => ['digital marketing business', 'marketing strategy office'],
            '比較'       => ['comparison chart laptop', 'product comparison workspace'],
            'ランキング' => ['comparison chart laptop', 'product comparison workspace'],
            'AI'        => ['artificial intelligence technology', 'data technology'],
            '画像'       => ['design creative workspace', 'photo editing laptop'],
            'Web'       => ['website development laptop', 'web design workspace'],
            'サイト'     => ['website development laptop', 'web design workspace'],
            'WordPress' => ['website dashboard laptop', 'blog website dashboard'],
            '効率化'     => ['productivity workspace laptop', 'focused desk workspace'],
        ];

        foreach ($map as $needle => $fallbacks) {
            if (mb_strpos($text, $needle) !== false) {
                $queries = array_merge($queries, $fallbacks);
            }
        }

        return array_values(array_unique($queries));
    }


    private function search_unsplash_photo($query, array $exclude_ids = [])
    {
        if ($this->unsplash_access_key === '' || $query === '') {
            return false;
        }

        $url = add_query_arg([
            'query'          => $query,
            'per_page'       => 10,
            'orientation'    => 'landscape',
            'content_filter' => 'high',
        ], 'https://api.unsplash.com/search/photos');

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Client-ID ' . $this->unsplash_access_key,
                'Accept-Version' => 'v1',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Unsplash WP_Error: ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $this->log_error('Unsplash API error [' . $code . ']: ' . $this->extract_error_message($json, $raw));
            return false;
        }

        if (empty($json['results']) || !is_array($json['results'])) {
            $this->log_warning('Unsplash: no results for query=' . $query);
            return false;
        }

        foreach ($json['results'] as $photo) {
            $photo_id = (string) ($photo['id'] ?? '');
            if ($photo_id !== '' && in_array($photo_id, $exclude_ids, true)) {
                continue;
            }

            if (empty($photo['urls']['regular']) || empty($photo['links']['html'])) {
                continue;
            }

            return $photo;
        }

        return false;
    }

    /**
     * Unsplash画像ブロック生成
     */
    private function build_unsplash_image_block(array $photo, $heading_text = '')
    {
        $image_url   = esc_url_raw((string) ($photo['urls']['regular'] ?? ''));
        $photo_page  = esc_url_raw((string) ($photo['links']['html'] ?? ''));
        $author_page = esc_url_raw((string) ($photo['user']['links']['html'] ?? ''));
        $author_name = sanitize_text_field((string) ($photo['user']['name'] ?? 'Unsplash'));
        $alt         = trim((string) ($photo['alt_description'] ?? $heading_text));

        if ($image_url === '' || $photo_page === '') {
            return '';
        }

        $author_href = $author_page !== '' ? $author_page : $photo_page;
        $author_href = add_query_arg([
            'utm_source' => 'wp_ai_blog_engine',
            'utm_medium' => 'referral',
        ], $author_href);

        $unsplash_href = add_query_arg([
            'utm_source' => 'wp_ai_blog_engine',
            'utm_medium' => 'referral',
        ], 'https://unsplash.com/');

        $caption = sprintf(
            'Photo by <a href="%s" target="_blank" rel="nofollow sponsored noopener">%s</a> on <a href="%s" target="_blank" rel="nofollow sponsored noopener">Unsplash</a>',
            esc_url($author_href),
            esc_html($author_name),
            esc_url($unsplash_href)
        );

        return
            "\n<!-- wp:image {\"sizeSlug\":\"large\",\"linkDestination\":\"none\",\"className\":\"wabe-inline-unsplash-image\"} -->\n" .
            '<figure class="wp-block-image size-large wabe-inline-unsplash-image">' .
            '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" />' .
            '<figcaption>' . $caption . '</figcaption>' .
            "</figure>\n" .
            "<!-- /wp:image -->\n";
    }

    /**
     * 見出し直後に画像ブロックを差し込む
     */
    private function insert_blocks_after_headings($content, array $insertions)
    {
        if (empty($insertions)) {
            return $content;
        }

        $map = [];
        foreach ($insertions as $row) {
            $idx = (int) ($row['heading_index'] ?? -1);
            $blk = (string) ($row['block'] ?? '');
            if ($idx < 0 || $blk === '') {
                continue;
            }
            $map[$idx] = $blk;
        }

        if (empty($map)) {
            return $content;
        }

        $current_index = -1;

        $content = preg_replace_callback(
            '/(<h([23])\b[^>]*>.*?<\/h\2>)/isu',
            function ($m) use (&$current_index, $map) {
                $current_index++;
                $heading_html = (string) $m[1];

                if (!isset($map[$current_index])) {
                    return $heading_html;
                }

                return $heading_html . "\n" . $map[$current_index];
            },
            $content
        );

        return (string) $content;
    }

    /**
     * Unsplash download endpoint を通知
     */
    private function ping_unsplash_download(array $photo)
    {
        $download_location = (string) ($photo['links']['download_location'] ?? '');
        if ($download_location === '' || $this->unsplash_access_key === '') {
            return;
        }

        $url = add_query_arg([
            'client_id' => $this->unsplash_access_key,
        ], $download_location);

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept-Version' => 'v1',
            ],
        ]);

        if (is_wp_error($response)) {
            $this->log_warning('Unsplash download ping failed: ' . $response->get_error_message());
            return;
        }

        $this->log_info('Unsplash download ping success');
    }

    /**
     * OpenAI 画像生成
     */
    private function generate_with_openai($prompt)
    {
        if ($this->openai_api_key === '') {
            $this->log_warning('Image: OpenAI API key missing');
            return false;
        }

        $body = [
            'model'         => 'gpt-image-1',
            'prompt'        => $prompt,
            'size'          => '1536x1024',
            'quality'       => 'medium',
            'output_format' => 'png',
        ];

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Image OpenAI WP_Error: ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $this->log_error('Image OpenAI API error [' . $code . ']: ' . $this->extract_error_message($json, $raw));
            return false;
        }

        if (empty($json['data'][0]['b64_json'])) {
            $this->log_error('Image OpenAI API error: b64_json not found in response.');
            return false;
        }

        $bytes = base64_decode((string) $json['data'][0]['b64_json'], true);
        if ($bytes === false) {
            $this->log_error('Image OpenAI API error: base64 decode failed.');
            return false;
        }

        $this->log_info('Image generated by OpenAI successfully');

        return [
            'bytes' => $bytes,
            'mime'  => 'image/png',
        ];
    }

    /**
     * Gemini Imagen 画像生成
     */
    private function generate_with_gemini($prompt)
    {
        if ($this->gemini_api_key === '') {
            $this->log_warning('Image: Gemini API key missing');
            return false;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/imagen-4.0-generate-001:predict';

        $body = [
            'instances' => [
                [
                    'prompt' => $prompt,
                ],
            ],
            'parameters' => [
                'sampleCount' => 1,
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => 120,
            'headers' => [
                'x-goog-api-key' => $this->gemini_api_key,
                'Content-Type'   => 'application/json',
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Image Gemini WP_Error: ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $this->log_error('Image Gemini API error [' . $code . ']: ' . $raw);
            return false;
        }

        if (empty($json['predictions'][0]['bytesBase64Encoded'])) {
            $this->log_error('Image Gemini API error: image data not found');
            return false;
        }

        $bytes = base64_decode((string) $json['predictions'][0]['bytesBase64Encoded'], true);
        if ($bytes === false) {
            $this->log_error('Image Gemini API error: base64 decode failed');
            return false;
        }

        $this->log_info('Image generated by Gemini (Imagen) successfully');

        return [
            'bytes' => $bytes,
            'mime'  => 'image/png',
        ];
    }

    /**
     * Pollinations.AI 画像生成
     */
    private function generate_with_pollinations($prompt)
    {
        if ($this->pollinations_api_key === '') {
            $this->log_warning('Pollinations API key missing');
            return false;
        }

        $model = trim((string) ($this->options['pollinations_image_model'] ?? 'flux'));
        if ($model === '') {
            $model = 'flux';
        }

        $body = [
            'model'           => $model,
            'prompt'          => $prompt,
            'size'            => '1024x1024',
            'response_format' => 'b64_json',
        ];

        $response = wp_remote_post('https://gen.pollinations.ai/v1/images/generations', [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->pollinations_api_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        if (is_wp_error($response)) {
            $this->log_error('Pollinations WP_Error: ' . $response->get_error_message());
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);

        if ($code < 200 || $code >= 300) {
            $this->log_error('Pollinations API error [' . $code . ']: ' . $raw);
            return false;
        }

        if (!empty($json['data'][0]['b64_json'])) {
            $bytes = base64_decode((string) $json['data'][0]['b64_json'], true);
            if ($bytes === false) {
                $this->log_error('Pollinations API error: b64_json decode failed');
                return false;
            }

            $this->log_info('Image generated by Pollinations successfully. model=' . $model);

            return [
                'bytes' => $bytes,
                'mime'  => 'image/png',
            ];
        }

        if (!empty($json['data'][0]['url'])) {
            $image_url = (string) $json['data'][0]['url'];

            $image_response = wp_remote_get($image_url, [
                'timeout' => 120,
            ]);

            if (is_wp_error($image_response)) {
                $this->log_error('Pollinations image download error: ' . $image_response->get_error_message());
                return false;
            }

            $image_code   = (int) wp_remote_retrieve_response_code($image_response);
            $image_body   = wp_remote_retrieve_body($image_response);
            $content_type = wp_remote_retrieve_header($image_response, 'content-type');

            if ($image_code < 200 || $image_code >= 300 || empty($image_body)) {
                $this->log_error('Pollinations image download failed [' . $image_code . ']');
                return false;
            }

            $mime = is_string($content_type) && $content_type !== '' ? $content_type : 'image/png';

            $this->log_info('Image generated by Pollinations successfully via URL. model=' . $model);

            return [
                'bytes' => $image_body,
                'mime'  => $mime,
            ];
        }

        $this->log_error('Pollinations API error: neither b64_json nor url found in response');
        $this->log_error('Pollinations raw response: ' . $raw);

        return false;
    }

    /**
     * 画像保存してattachment生成
     */
    private function save_generated_image($post_id, $title, $bytes, $mime)
    {
        if (!function_exists('wp_upload_bits')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!function_exists('wp_insert_attachment')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $ext = $this->mime_to_extension($mime);
        if ($ext === '') {
            $this->log_error('Image save failed: unsupported mime type ' . $mime);
            return false;
        }

        $slug = sanitize_title($title);
        if ($slug === '') {
            $slug = 'featured-image';
        }

        $filename = $slug . '-' . time() . '.' . $ext;
        $upload = wp_upload_bits($filename, null, $bytes);

        if (!empty($upload['error'])) {
            $this->log_error('Image file write failed: ' . $upload['error']);
            return false;
        }

        $this->log_info('Image file path: ' . $upload['file']);
        $this->log_info('File exists: ' . (file_exists($upload['file']) ? '1' : '0'));

        $attachment = [
            'post_mime_type' => $mime,
            'post_title'     => sanitize_text_field($title),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

        if (is_wp_error($attachment_id) || !$attachment_id) {
            $message = is_wp_error($attachment_id) ? $attachment_id->get_error_message() : 'unknown';
            $this->log_error('Image attachment insert failed: ' . $message);
            return false;
        }

        $metadata = wp_generate_attachment_metadata($attachment_id, $upload['file']);

        if ($metadata instanceof WP_Error) {
            $error_message = $metadata->get_error_message();
            $this->log_error('Image metadata generation failed: ' . $error_message);
            return false;
        }

        if (empty($metadata) || !is_array($metadata)) {
            $this->log_error('Image metadata generation failed: empty metadata');
            return false;
        }

        wp_update_attachment_metadata($attachment_id, $metadata);

        $this->log_info('Image generated: attachment_id=' . $attachment_id);

        return (int) $attachment_id;
    }

    /**
     * OpenAI/Gemini向けプロンプト
     */
    private function build_prompt($title)
    {
        $style_map = [
            'modern'   => 'modern, clean, professional, high-quality website hero image',
            'business' => 'business, trustworthy, professional, polished marketing visual',
            'blog'     => 'editorial blog cover, attractive, readable visual composition',
            'tech'     => 'technology-focused, sleek, futuristic, startup-style visual',
            'luxury'   => 'premium, elegant, refined, luxury brand style',
            'natural'  => 'soft natural lighting, warm, approachable, organic atmosphere',
        ];

        $style_text = $style_map[$this->image_style] ?? $style_map['modern'];

        $site_context = '';
        if (class_exists('WABE_Utils') && method_exists('WABE_Utils', 'wabe_maybe_base64_decode')) {
            $site_context = (string) WABE_Utils::wabe_maybe_base64_decode($this->options['site_context'] ?? '');
        } else {
            $site_context = (string) ($this->options['site_context'] ?? '');
        }

        $site_context = trim(wp_strip_all_tags($site_context));

        if (function_exists('mb_strlen') && mb_strlen($site_context) > 300) {
            $site_context = mb_substr($site_context, 0, 300);
        } elseif (strlen($site_context) > 300) {
            $site_context = substr($site_context, 0, 300);
        }

        $prompt = 'Create a featured image for a WordPress blog post.'
            . ' Post title: ' . $title . '.'
            . ' Generate an image that visually matches the title topic as specifically as possible.'
            . ' Prefer a concrete scene related to the topic over a generic business stock image.'
            . ' Style: ' . $style_text . '.'
            . ' Landscape composition suitable for a blog featured image.'
            . ' No text, no letters, no logos, no watermark.'
            . ' High quality, visually appealing, realistic or semi-realistic depending on subject.';

        if ($site_context !== '') {
            $prompt .= ' Website/business context: ' . $site_context . '.';
        }

        return $prompt;
    }

    /**
     * Pollinations向けプロンプト
     */
    private function build_prompt_for_pollinations($title)
    {
        $title = trim((string) wp_strip_all_tags($title));
        $title_en = $this->translate_title_to_english($title);

        $keyword = trim((string) ($this->options['seo_keyword'] ?? ''));
        $keyword = wp_strip_all_tags($keyword);

        $style_keywords = $this->get_pollinations_style_keywords();

        $parts = [];
        if ($title_en !== '') {
            $parts[] = $title_en;
        }
        if ($keyword !== '') {
            $parts[] = $keyword;
        }

        $parts[] = 'specific visual scene matching the article title';
        $parts[] = 'not a generic business stock image';
        $parts[] = 'clean composition';
        $parts[] = $style_keywords;
        $parts[] = 'high quality';
        $parts[] = 'landscape';
        $parts[] = 'no text';
        $parts[] = 'no logo';
        $parts[] = 'no watermark';

        return implode(', ', array_filter($parts));
    }

    /**
     * タイトルを簡易英語化
     */
    private function translate_title_to_english($title)
    {
        $title = trim((string) $title);
        if ($title === '') {
            return '';
        }

        $map = [
            'ブログ'       => 'blog',
            '自動生成'     => 'automation',
            'メリット'     => 'benefits',
            'ポイント'     => 'key points',
            '解説'         => 'guide',
            '集客'         => 'lead generation',
            '中小企業'     => 'small business',
            'ホームページ' => 'website',
            'SEO'          => 'SEO',
            '記事'         => 'article',
            '画像'         => 'image',
            '作成'         => 'creation',
            '効率化'       => 'efficiency',
            '比較'         => 'comparison',
            '方法'         => 'how to',
            '活用'         => 'usage',
            '導入'         => 'implementation',
            '初心者'       => 'beginner',
            'おすすめ'     => 'recommended',
            '機能'         => 'features',
        ];

        $translated = $title;
        foreach ($map as $ja => $en) {
            $translated = str_replace($ja, $en, $translated);
        }

        $translated = preg_replace('/[ \s]+/u', ' ', $translated);
        return trim((string) $translated);
    }

    /**
     * Pollinations用スタイル
     */
    private function get_pollinations_style_keywords()
    {
        $map = [
            'modern'   => 'modern clean professional design',
            'business' => 'business professional trustworthy visual',
            'blog'     => 'editorial blog cover style',
            'tech'     => 'technology sleek futuristic style',
            'luxury'   => 'premium elegant refined style',
            'natural'  => 'soft natural warm atmosphere',
        ];

        return $map[$this->image_style] ?? $map['modern'];
    }

    /**
     * MIME → 拡張子
     */
    private function mime_to_extension($mime)
    {
        $mime = strtolower((string) $mime);

        if ($mime === 'image/png') {
            return 'png';
        }
        if ($mime === 'image/jpeg' || $mime === 'image/jpg') {
            return 'jpg';
        }
        if ($mime === 'image/webp') {
            return 'webp';
        }

        return '';
    }

    /**
     * APIエラー文字列抽出
     */
    private function extract_error_message($json, $raw)
    {
        if (is_array($json)) {
            if (!empty($json['error']['message'])) {
                return (string) $json['error']['message'];
            }
            if (!empty($json['message'])) {
                return (string) $json['message'];
            }
            if (!empty($json['errors'][0])) {
                return is_string($json['errors'][0]) ? $json['errors'][0] : wp_json_encode($json['errors'][0]);
            }
        }

        return trim((string) $raw);
    }

    private function log_info($message)
    {
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info($message);
        }
    }

    private function log_warning($message)
    {
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'warning')) {
            WABE_Logger::warning($message);
            return;
        }

        $this->log_info($message);
    }

    private function log_error($message)
    {
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'error')) {
            WABE_Logger::error($message);
            return;
        }

        $this->log_info($message);
    }
}
