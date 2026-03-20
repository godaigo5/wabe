<?php
if (!defined('ABSPATH')) exit;

class WABE_Gemini_Provider implements WABE_AI_Provider_Interface
{
    private $key;

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
            'model' => 'gemini-2.5-flash',
            'temperature' => 0.7,
            'max_output_tokens' => 2200,
        ]);

        $model = rawurlencode((string)$args['model']);
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => (string)$prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'      => (float)$args['temperature'],
                'maxOutputTokens'  => (int)$args['max_output_tokens'],
            ],
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'x-goog-api-key' => $this->key,
                'Content-Type'   => 'application/json',
            ],
            'timeout' => 60,
            'body'    => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            WABE_Logger::error('Gemini HTTP error: ' . $response->get_error_message());
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            WABE_Logger::error(
                'Gemini API error ' . $code . ' ' .
                    ($body['error']['status'] ?? '') . ' ' .
                    ($body['error']['message'] ?? 'Unknown error')
            );
            return '';
        }

        if (!empty($body['candidates']) && is_array($body['candidates'])) {
            foreach ($body['candidates'] as $candidate) {
                if (empty($candidate['content']['parts']) || !is_array($candidate['content']['parts'])) {
                    continue;
                }

                $text = '';
                foreach ($candidate['content']['parts'] as $part) {
                    if (isset($part['text'])) {
                        $text .= (string)$part['text'];
                    }
                }

                if ($text !== '') {
                    return trim($text);
                }
            }
        }

        return '';
    }
}
