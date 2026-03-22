<?php
if (!defined('ABSPATH')) exit;

class WABE_Image
{
    /** @var array */
    private $options = [];

    /** @var string */
    private $openai_api_key = '';

    /** @var string */
    private $gemini_api_key = '';

    /** @var string */
    private $pollinations_api_key = '';

    /** @var string */
    private $image_style = 'modern';

    public function __construct()
    {
        $this->options = get_option(WABE_OPTION, []);
        if (!is_array($this->options)) {
            $this->options = [];
        }

        $this->openai_api_key = trim((string) ($this->options['openai_api_key'] ?? ''));
        $this->gemini_api_key = trim((string) ($this->options['gemini_api_key'] ?? ''));
        $this->pollinations_api_key = trim((string) ($this->options['pollinations_api_key'] ?? ''));
        $this->image_style = sanitize_key($this->options['image_style'] ?? 'modern');
    }

    /**
     * 記事タイトルから画像生成してアイキャッチ設定
     *
     * @param int    $post_id
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
     * OpenAI互換API
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

            $image_code = (int) wp_remote_retrieve_response_code($image_response);
            $image_body = wp_remote_retrieve_body($image_response);
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

        if (!$metadata) {
            $this->log_error('Image metadata generation failed: empty metadata');
            return false;
        }

        if (is_wp_error($metadata)) {
            /** @var WP_Error $metadata_error */
            $metadata_error = $metadata;
            $this->log_error('Image metadata generation failed: ' . $metadata_error->get_error_message());
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
     * タイトルを英語化 + キーワード補強
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
     * タイトルを英語化
     * OpenAI/Geminiなしでも動くように、まずはシンプル変換 + 主要語置換
     */
    private function translate_title_to_english($title)
    {
        $title = trim((string) $title);
        if ($title === '') {
            return '';
        }

        $map = [
            'ブログ' => 'blog',
            '自動生成' => 'automation',
            'メリット' => 'benefits',
            'ポイント' => 'key points',
            '解説' => 'guide',
            '集客' => 'lead generation',
            '中小企業' => 'small business',
            'ホームページ' => 'website',
            'SEO' => 'SEO',
            '記事' => 'article',
            '画像' => 'image',
            '作成' => 'creation',
            '効率化' => 'efficiency',
            '比較' => 'comparison',
            '方法' => 'how to',
            '活用' => 'usage',
            '導入' => 'implementation',
            '初心者' => 'beginner',
            'おすすめ' => 'recommended',
            '機能' => 'features',
        ];

        $translated = $title;
        foreach ($map as $ja => $en) {
            $translated = str_replace($ja, $en, $translated);
        }

        $translated = preg_replace('/[　\s]+/u', ' ', $translated);
        $translated = trim((string) $translated);

        return $translated;
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
