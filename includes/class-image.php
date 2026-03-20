<?php

if (!defined('ABSPATH')) exit;

class WABE_Image
{
    private $openai_key = '';
    private $gemini_key = '';
    private $provider   = 'openai';
    private $style      = 'modern';

    public function __construct()
    {
        $o = get_option(WABE_OPTION, []);

        $this->openai_key = trim((string)($o['openai_api_key'] ?? ''));
        $this->gemini_key = trim((string)($o['gemini_api_key'] ?? ''));
        $this->provider   = sanitize_key($o['ai_provider'] ?? 'openai');
        $this->style      = sanitize_key($o['image_style'] ?? 'modern');
    }

    /**
     * 画像生成して attachment 情報を返す
     *
     * @param string $topic
     * @return array{
     *   success:bool,
     *   attachment_id:int,
     *   url:string,
     *   provider:string,
     *   error:string
     * }
     */
    public function generate($topic)
    {
        $topic = sanitize_text_field((string)$topic);

        if ($topic === '') {
            WABE_Logger::warning('Image: topic empty');
            return $this->result(false, 0, '', '', 'Topic is empty.');
        }

        if (!$this->is_enabled()) {
            WABE_Logger::info('Image: disabled by settings or plan');
            return $this->result(false, 0, '', '', 'Image generation is disabled.');
        }

        $provider = $this->provider === 'gemini' ? 'gemini' : 'openai';

        if ($provider === 'gemini') {
            $result = $this->generate_with_gemini($topic);
            if (!empty($result['success'])) {
                return $result;
            }

            WABE_Logger::warning('Image: Gemini generation failed, fallback to OpenAI');
            $fallback = $this->generate_with_openai($topic);
            if (!empty($fallback['success'])) {
                return $fallback;
            }

            return $this->result(
                false,
                0,
                '',
                '',
                $fallback['error'] ?: ($result['error'] ?: 'Failed to generate image with Gemini and OpenAI.')
            );
        }

        return $this->generate_with_openai($topic);
    }

    /**
     * 後方互換用:
     * 既存コードが URL 文字列を期待している場合のために用意
     *
     * @param string $topic
     * @return string
     */
    public function generate_url($topic)
    {
        $result = $this->generate($topic);
        return (string)($result['url'] ?? '');
    }

    /**
     * アイキャッチ設定
     * attachment_id / URL / generate() の戻り値配列 のいずれでも受け付ける
     *
     * @param int              $post_id
     * @param mixed            $image
     * @return bool
     */
    public function set_featured_image($post_id, $image)
    {
        $post_id = (int)$post_id;
        if ($post_id <= 0) {
            WABE_Logger::warning('Image: invalid post_id');
            return false;
        }

        $attachment_id = 0;

        // generate() の戻り値配列
        if (is_array($image)) {
            $attachment_id = (int)($image['attachment_id'] ?? 0);

            if ($attachment_id <= 0 && !empty($image['url']) && is_string($image['url'])) {
                $attachment_id = $this->resolve_attachment_id_from_url($image['url']);
            }
        }
        // attachment_id
        elseif (is_numeric($image)) {
            $attachment_id = (int)$image;
        }
        // URL
        elseif (is_string($image) && $image !== '') {
            $attachment_id = $this->resolve_attachment_id_from_url($image);

            // URLから既存attachmentが見つからない場合はsideload
            if ($attachment_id <= 0) {
                $attachment_id = $this->sideload_image_from_url($image, $post_id, get_the_title($post_id));
            }
        }

        if ($attachment_id <= 0) {
            WABE_Logger::warning('Image: attachment_id could not be resolved');
            return false;
        }

        $mime = get_post_mime_type($attachment_id);
        if (!is_string($mime) || strpos($mime, 'image/') !== 0) {
            WABE_Logger::warning('Image: attachment is not an image');
            return false;
        }

        $set = set_post_thumbnail($post_id, $attachment_id);

        if (!$set) {
            // 既に同じサムネイルが設定済みのとき false の場合がある
            $current_thumb = (int)get_post_thumbnail_id($post_id);
            if ($current_thumb === $attachment_id) {
                WABE_Logger::info('Image: featured image already set - post_id=' . $post_id . ' attachment_id=' . $attachment_id);
                return true;
            }

            WABE_Logger::warning('Image: set_post_thumbnail returned false - post_id=' . $post_id . ' attachment_id=' . $attachment_id);
            return false;
        }

        WABE_Logger::info('Image: featured image set - post_id=' . $post_id . ' attachment_id=' . $attachment_id);
        return true;
    }

    /**
     * 記事生成→アイキャッチ設定まで一括
     *
     * @param int    $post_id
     * @param string $topic
     * @return bool
     */
    public function generate_and_attach($post_id, $topic)
    {
        $result = $this->generate($topic);

        if (empty($result['success'])) {
            WABE_Logger::warning('Image: generate_and_attach failed - ' . (string)($result['error'] ?? 'Unknown error'));
            return false;
        }

        return $this->set_featured_image($post_id, $result);
    }

    private function is_enabled()
    {
        if (!class_exists('WABE_Plan') || !WABE_Plan::can_use_images()) {
            return false;
        }

        $o = get_option(WABE_OPTION, []);
        return !empty($o['enable_featured_image']) && (string)$o['enable_featured_image'] === '1';
    }

    private function generate_with_openai($topic)
    {
        if ($this->openai_key === '') {
            WABE_Logger::warning('Image: OpenAI API key missing');
            return $this->result(false, 0, '', 'openai', 'OpenAI API key is missing.');
        }

        $prompt = $this->build_prompt($topic);

        $response = wp_remote_post(
            'https://api.openai.com/v1/images/generations',
            [
                'timeout' => 120,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openai_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode([
                    'model'  => 'gpt-image-1',
                    'prompt' => $prompt,
                    'size'   => '1024x1024',
                ]),
            ]
        );

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            WABE_Logger::error('Image OpenAI HTTP error: ' . $message);
            return $this->result(false, 0, '', 'openai', $message);
        }

        $status_code = (int)wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $body        = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = '';
            if (is_array($body)) {
                $message = (string)($body['error']['message'] ?? $body['error']['code'] ?? '');
            }
            if ($message === '') {
                $message = 'OpenAI image generation API error.';
            }

            WABE_Logger::error('Image OpenAI API error [' . $status_code . ']: ' . $message);
            return $this->result(false, 0, '', 'openai', $message);
        }

        if (empty($body['data'][0]['b64_json']) || !is_string($body['data'][0]['b64_json'])) {
            WABE_Logger::warning('Image OpenAI: no image data returned');
            return $this->result(false, 0, '', 'openai', 'OpenAI returned no image data.');
        }

        return $this->save_base64_image($body['data'][0]['b64_json'], $topic, 'openai');
    }

    private function generate_with_gemini($topic)
    {
        if ($this->gemini_key === '') {
            WABE_Logger::warning('Image: Gemini API key missing');
            return $this->result(false, 0, '', 'gemini', 'Gemini API key is missing.');
        }

        $prompt = $this->build_prompt($topic);

        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict',
            [
                'timeout' => 120,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'x-goog-api-key' => $this->gemini_key,
                ],
                'body' => wp_json_encode([
                    'instances' => [
                        [
                            'prompt' => $prompt,
                        ],
                    ],
                    'parameters' => [
                        'sampleCount' => 1,
                    ],
                ]),
            ]
        );

        if (is_wp_error($response)) {
            $message = $response->get_error_message();
            WABE_Logger::error('Image Gemini HTTP error: ' . $message);
            return $this->result(false, 0, '', 'gemini', $message);
        }

        $status_code = (int)wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $body        = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = '';
            if (is_array($body)) {
                $message = (string)($body['error']['message'] ?? $body['error']['status'] ?? '');
            }
            if ($message === '') {
                $message = 'Gemini image generation API error.';
            }

            WABE_Logger::error('Image Gemini API error [' . $status_code . ']: ' . $message);
            return $this->result(false, 0, '', 'gemini', $message);
        }

        $base64 = $this->extract_gemini_base64($body);

        if ($base64 === '') {
            WABE_Logger::warning('Image Gemini: no image data returned');
            return $this->result(false, 0, '', 'gemini', 'Gemini returned no image data.');
        }

        return $this->save_base64_image($base64, $topic, 'gemini');
    }

    private function extract_gemini_base64($body)
    {
        if (!is_array($body)) {
            return '';
        }

        $candidates = [
            $body['predictions'][0]['bytesBase64Encoded'] ?? '',
            $body['predictions'][0]['image']['bytesBase64Encoded'] ?? '',
            $body['images'][0]['image']['base64EncodedImage'] ?? '',
            $body['generatedImages'][0]['bytesBase64Encoded'] ?? '',
            $body['generated_images'][0]['bytesBase64Encoded'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function build_prompt($topic)
    {
        $style_text = $this->get_style_prompt();

        return trim(
            "Create a high-quality featured image for a WordPress blog post.\n" .
                "Topic: {$topic}\n" .
                "Visual direction:\n" .
                "- {$style_text}\n" .
                "- clean composition\n" .
                "- professional\n" .
                "- eye-catching\n" .
                "- suitable as a featured image\n" .
                "- no text overlay\n" .
                "- no letters\n" .
                "- no watermarks\n" .
                "- no logos\n" .
                "Output:\n" .
                "- square image\n" .
                "- visually clear subject\n" .
                "- modern blog-friendly thumbnail"
        );
    }

    private function get_style_prompt()
    {
        $styles = [
            'modern'   => 'modern, minimal, clean, soft lighting',
            'business' => 'professional business style, polished, trustworthy',
            'blog'     => 'friendly blog style, warm, approachable, lifestyle-oriented',
            'tech'     => 'technology themed, futuristic, sleek, digital atmosphere',
            'luxury'   => 'premium, elegant, refined, high-end visual tone',
            'natural'  => 'natural, organic, fresh, soft and authentic atmosphere',
        ];

        return $styles[$this->style] ?? $styles['modern'];
    }

    private function save_base64_image($base64, $topic, $provider = '')
    {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            WABE_Logger::error('Image upload dir error: ' . $upload_dir['error']);
            return $this->result(false, 0, '', $provider, $upload_dir['error']);
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            WABE_Logger::error('Image base64 decode failed');
            return $this->result(false, 0, '', $provider, 'Failed to decode base64 image.');
        }

        $mime = $this->detect_image_mime_from_binary($binary);
        if ($mime === '') {
            WABE_Logger::error('Image mime type detection failed');
            return $this->result(false, 0, '', $provider, 'Unsupported or invalid image binary.');
        }

        $extension = $this->mime_to_extension($mime);
        if ($extension === '') {
            WABE_Logger::error('Image extension resolve failed for mime: ' . $mime);
            return $this->result(false, 0, '', $provider, 'Unsupported image mime type.');
        }

        $safe_topic = sanitize_title($topic);
        if ($safe_topic === '') {
            $safe_topic = 'image';
        }

        $filename = wp_unique_filename(
            $upload_dir['path'],
            'wabe-' . $safe_topic . '-' . gmdate('Ymd-His') . '.' . $extension
        );

        $file_path = trailingslashit($upload_dir['path']) . $filename;

        $written = file_put_contents($file_path, $binary);
        if ($written === false) {
            WABE_Logger::error('Image file write failed');
            return $this->result(false, 0, '', $provider, 'Failed to write image file.');
        }

        $attachment = [
            'post_mime_type' => $mime,
            'post_title'     => sanitize_text_field($topic),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path);
        if (is_wp_error($attach_id)) {
            @unlink($file_path);
            WABE_Logger::error('Image attachment insert failed: ' . $attach_id->get_error_message());
            return $this->result(false, 0, '', $provider, $attach_id->get_error_message());
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);

        if ($attach_data === false || empty($attach_data) || !is_array($attach_data)) {
            wp_delete_attachment($attach_id, true);
            @unlink($file_path);

            $error_message = 'Failed to generate attachment metadata.';
            WABE_Logger::error('Image metadata generation failed: ' . $error_message);

            return $this->result(false, 0, '', $provider, $error_message);
        }

        wp_update_attachment_metadata($attach_id, $attach_data);

        if ($provider !== '') {
            update_post_meta($attach_id, '_wabe_image_provider', sanitize_key($provider));
        }
        update_post_meta($attach_id, '_wabe_image_topic', sanitize_text_field($topic));
        update_post_meta($attach_id, '_wabe_generated_at', current_time('mysql'));

        $image_url = wp_get_attachment_url($attach_id);
        if (!$image_url) {
            WABE_Logger::error('Image attachment URL not found');
            return $this->result(false, 0, '', $provider, 'Attachment URL not found.');
        }

        WABE_Logger::info('Image generated: attachment_id=' . $attach_id . ' url=' . $image_url . ' provider=' . $provider);

        return $this->result(true, (int)$attach_id, (string)$image_url, $provider, '');
    }

    private function detect_image_mime_from_binary($binary)
    {
        if (!is_string($binary) || $binary === '') {
            return '';
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_buffer($finfo, $binary);
                finfo_close($finfo);

                if (is_string($mime) && in_array($mime, ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
                    return $mime;
                }
            }
        }

        // シグネチャ判定の簡易フォールバック
        $head12 = substr($binary, 0, 12);

        if (strncmp($binary, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) {
            return 'image/png';
        }

        if (strncmp($binary, "\xFF\xD8\xFF", 3) === 0) {
            return 'image/jpeg';
        }

        if (substr($head12, 0, 4) === 'RIFF' && substr($head12, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        if (strncmp($binary, "GIF87a", 6) === 0 || strncmp($binary, "GIF89a", 6) === 0) {
            return 'image/gif';
        }

        return '';
    }

    private function mime_to_extension($mime)
    {
        $map = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        return $map[$mime] ?? '';
    }

    private function resolve_attachment_id_from_url($image_url)
    {
        $image_url = esc_url_raw((string)$image_url);
        if ($image_url === '') {
            return 0;
        }

        $attachment_id = attachment_url_to_postid($image_url);
        if ($attachment_id > 0) {
            return (int)$attachment_id;
        }

        // サイズ違いURLの可能性を考慮して "-150x150" 等を除去して再試行
        $normalized = preg_replace('/-\d+x\d+(?=\.(jpg|jpeg|png|gif|webp)$)/i', '', $image_url);
        if (is_string($normalized) && $normalized !== $image_url) {
            $attachment_id = attachment_url_to_postid($normalized);
            if ($attachment_id > 0) {
                return (int)$attachment_id;
            }
        }

        return 0;
    }

    private function sideload_image_from_url($image_url, $post_id = 0, $title = '')
    {
        $image_url = esc_url_raw((string)$image_url);
        if ($image_url === '') {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($image_url, 120);

        if (is_wp_error($tmp)) {
            WABE_Logger::error('Image sideload download error: ' . $tmp->get_error_message());
            return 0;
        }

        $path     = wp_parse_url($image_url, PHP_URL_PATH);
        $basename = $path ? basename($path) : '';
        if (!is_string($basename) || $basename === '' || strpos($basename, '.') === false) {
            $basename = 'wabe-remote-image.jpg';
        }

        $file = [
            'name'     => sanitize_file_name($basename),
            'tmp_name' => $tmp,
        ];

        $attachment_id = media_handle_sideload($file, (int)$post_id, sanitize_text_field((string)$title));

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            WABE_Logger::error('Image sideload attach error: ' . $attachment_id->get_error_message());
            return 0;
        }

        return (int)$attachment_id;
    }

    private function result($success, $attachment_id, $url, $provider, $error)
    {
        return [
            'success'       => (bool)$success,
            'attachment_id' => (int)$attachment_id,
            'url'           => (string)$url,
            'provider'      => (string)$provider,
            'error'         => (string)$error,
        ];
    }
}
