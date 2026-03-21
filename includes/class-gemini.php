<?php
if (!defined('ABSPATH')) exit;

class WABE_Gemini
{
    private $key = '';

    public function __construct()
    {
        $o = get_option(WABE_OPTION, []);
        $this->key = trim((string)($o['gemini_api_key'] ?? ''));
    }

    public function text($prompt, $args = [])
    {
        if ($this->key === '') {
            WABE_Logger::warning('Gemini: API key missing');
            return '';
        }

        $args = wp_parse_args($args, [
            'model'             => 'gemini-2.5-flash',
            'temperature'       => 0.7,
            'max_output_tokens' => 2200,
        ]);

        $model = sanitize_text_field((string)$args['model']);
        if ($model === '') {
            $model = 'gemini-2.5-flash';
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => (string)$prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature'     => (float)$args['temperature'],
                'maxOutputTokens' => (int)$args['max_output_tokens'],
            ],
        ];

        $response = wp_remote_post($url, [
            'timeout' => 90,
            'headers' => [
                'Content-Type'   => 'application/json',
                'x-goog-api-key' => $this->key,
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            WABE_Logger::error('Gemini HTTP error: ' . $response->get_error_message());
            return '';
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $body        = json_decode($raw_body, true);
        if (class_exists('WABE_Logger') && method_exists('WABE_Logger', 'info')) {
            WABE_Logger::info('Gemini raw response preview: ' . mb_substr($raw_body, 0, 2000));
        }
        if ($status_code < 200 || $status_code >= 300) {
            $error_message = '';

            if (is_array($body)) {
                $error_message = (string)($body['error']['message'] ?? $body['error']['status'] ?? '');
            }

            if ($error_message === '') {
                $error_message = 'Unknown Gemini API error';
            }

            WABE_Logger::error('Gemini API error [' . $status_code . ']: ' . $error_message);
            return '';
        }

        if (!is_array($body)) {
            WABE_Logger::error('Gemini API error: invalid JSON response');
            return '';
        }

        $text = $this->extract_text_from_response($body);

        if ($text === '') {
            WABE_Logger::warning('Gemini: empty text response');
        }

        return $text;
    }

    private function extract_text_from_response($body)
    {
        if (!empty($body['candidates']) && is_array($body['candidates'])) {
            foreach ($body['candidates'] as $candidate) {
                if (empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                    continue;
                }

                $texts = [];

                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text']) && is_string($part['text'])) {
                        $texts[] = $part['text'];
                    }
                }

                $joined = trim(implode("\n", $texts));
                if ($joined !== '') {
                    return $joined;
                }
            }
        }

        if (!empty($body['text']) && is_string($body['text'])) {
            return trim($body['text']);
        }

        return '';
    }
}
