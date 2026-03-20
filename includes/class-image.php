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

    public function generate($topic)
    {
        $topic = sanitize_text_field((string)$topic);

        if ($topic === '') {
            WABE_Logger::warning('Image: topic empty');
            return '';
        }

        if (!$this->is_enabled()) {
            WABE_Logger::info('Image: disabled by settings or plan');
            return '';
        }

        if ($this->provider === 'gemini') {
            $url = $this->generate_with_gemini($topic);

            if ($url !== '') {
                return $url;
            }

            WABE_Logger::warning('Image: Gemini generation failed, fallback to OpenAI');
        }

        return $this->generate_with_openai($topic);
    }

    public function set_featured_image($post_id, $image_url)
    {
        $post_id = intval($post_id);

        if ($post_id <= 0) {
            WABE_Logger::warning('Image: invalid post_id');
            return false;
        }

        if (!is_string($image_url) || $image_url === '') {
            WABE_Logger::warning('Image: empty image url');
            return false;
        }

        $attachment_id = attachment_url_to_postid($image_url);

        if (!$attachment_id) {
            WABE_Logger::warning('Image: attachment not found from url');
            return false;
        }

        set_post_thumbnail($post_id, $attachment_id);
        WABE_Logger::info('Image: featured image set - post_id=' . $post_id);

        return true;
    }

    private function is_enabled()
    {
        if (!WABE_Plan::can_use_images()) {
            return false;
        }

        $o = get_option(WABE_OPTION, []);
        return !empty($o['enable_featured_image']) && $o['enable_featured_image'] === '1';
    }

    private function generate_with_openai($topic)
    {
        if ($this->openai_key === '') {
            WABE_Logger::warning('Image: OpenAI API key missing');
            return '';
        }

        $prompt = $this->build_prompt($topic);

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', [
            'timeout' => 90,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openai_key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'  => 'gpt-image-1',
                'prompt' => $prompt,
                'size'   => '1024x1024',
            ]),
        ]);

        if (is_wp_error($response)) {
            WABE_Logger::error('Image OpenAI HTTP error: ' . $response->get_error_message());
            return '';
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $body        = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = '';
            if (is_array($body)) {
                $message = (string)($body['error']['message'] ?? $body['error']['code'] ?? '');
            }
            WABE_Logger::error('Image OpenAI API error [' . $status_code . ']: ' . ($message !== '' ? $message : 'Unknown error'));
            return '';
        }

        if (empty($body['data'][0]['b64_json'])) {
            WABE_Logger::warning('Image OpenAI: no image data returned');
            return '';
        }

        return $this->save_base64_image($body['data'][0]['b64_json'], $topic);
    }

    private function generate_with_gemini($topic)
    {
        if ($this->gemini_key === '') {
            WABE_Logger::warning('Image: Gemini API key missing');
            return '';
        }

        $prompt = $this->build_prompt($topic);

        $response = wp_remote_post(
            'https://generativelanguage.googleapis.com/v1beta/models/imagen-3.0-generate-002:predict',
            [
                'timeout' => 90,
                'headers' => [
                    'Content-Type'   => 'application/json',
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
            WABE_Logger::error('Image Gemini HTTP error: ' . $response->get_error_message());
            return '';
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $body        = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $message = '';
            if (is_array($body)) {
                $message = (string)($body['error']['message'] ?? $body['error']['status'] ?? '');
            }
            WABE_Logger::error('Image Gemini API error [' . $status_code . ']: ' . ($message !== '' ? $message : 'Unknown error'));
            return '';
        }

        $base64 = $this->extract_gemini_base64($body);

        if ($base64 === '') {
            WABE_Logger::warning('Image Gemini: no image data returned');
            return '';
        }

        return $this->save_base64_image($base64, $topic);
    }

    private function extract_gemini_base64($body)
    {
        if (!is_array($body)) {
            return '';
        }

        if (!empty($body['predictions'][0]['bytesBase64Encoded']) && is_string($body['predictions'][0]['bytesBase64Encoded'])) {
            return $body['predictions'][0]['bytesBase64Encoded'];
        }

        if (!empty($body['predictions'][0]['image']['bytesBase64Encoded']) && is_string($body['predictions'][0]['image']['bytesBase64Encoded'])) {
            return $body['predictions'][0]['image']['bytesBase64Encoded'];
        }

        if (!empty($body['images'][0]['image']['base64EncodedImage']) && is_string($body['images'][0]['image']['base64EncodedImage'])) {
            return $body['images'][0]['image']['base64EncodedImage'];
        }

        return '';
    }

    private function build_prompt($topic)
    {
        $style_text = $this->get_style_prompt();

        return trim("
Create a high-quality featured image for a WordPress blog post.

Topic: {$topic}

Visual direction:
- {$style_text}
- clean composition
- professional
- eye-catching
- suitable as a featured image
- no text overlay
- no letters
- no watermarks
- no logos

Output:
- square image
- visually clear subject
- modern blog-friendly thumbnail
");
    }

    private function get_style_prompt()
    {
        $styles = [
            'modern'   => 'modern, minimal, clean, soft lighting',
            'business' => 'professional business style, polished, trustworthy',
            'blog'     => 'friendly blog style, warm, approachable, lifestyle-oriented',
            'tech'     => 'technology themed, futuristic, sleek, digital atmosphere',
        ];

        return $styles[$this->style] ?? $styles['modern'];
    }

    private function save_base64_image($base64, $topic)
    {
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            WABE_Logger::error('Image upload dir error: ' . $upload_dir['error']);
            return '';
        }

        $binary = base64_decode($base64, true);

        if ($binary === false) {
            WABE_Logger::error('Image base64 decode failed');
            return '';
        }

        $filename = 'wabe_' . md5($topic . wp_generate_password(8, false)) . '.png';
        $file_path = trailingslashit($upload_dir['path']) . $filename;

        $written = file_put_contents($file_path, $binary);

        if ($written === false) {
            WABE_Logger::error('Image file write failed');
            return '';
        }

        $filetype = wp_check_filetype($filename, null);

        $attachment = [
            'post_mime_type' => $filetype['type'] ?: 'image/png',
            'post_title'     => sanitize_text_field($topic),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attach_id)) {
            WABE_Logger::error('Image attachment insert failed: ' . $attach_id->get_error_message());
            return '';
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        $image_url = wp_get_attachment_url($attach_id);

        if (!$image_url) {
            WABE_Logger::error('Image attachment URL not found');
            return '';
        }

        WABE_Logger::info('Image generated: ' . $image_url);

        return $image_url;
    }
}
