<?php
if (!defined('ABSPATH')) exit;

class WABE_OpenAI
{
    private $key = '';

    public function __construct()
    {
        $o = get_option(WABE_OPTION, []);
        $this->key = trim((string)($o['openai_api_key'] ?? ''));
    }

    public function text($prompt, $args = [])
    {
        if ($this->key === '') {
            WABE_Logger::warning('OpenAI: API key missing');
            return '';
        }

        $args = wp_parse_args($args, [
            'model'             => 'gpt-4.1-mini',
            'temperature'       => 0.7,
            'max_output_tokens' => 2200,
        ]);

        $model = sanitize_text_field((string)$args['model']);
        if ($model === '') {
            $model = 'gpt-4.1-mini';
        }

        $payload = [
            'model'             => $model,
            'input'             => (string)$prompt,
            'temperature'       => (float)$args['temperature'],
            'max_output_tokens' => (int)$args['max_output_tokens'],
        ];

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            WABE_Logger::error('OpenAI HTTP error: ' . $response->get_error_message());
            return '';
        }

        $status_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body    = wp_remote_retrieve_body($response);
        $body        = json_decode($raw_body, true);

        if ($status_code < 200 || $status_code >= 300) {
            $error_message = '';

            if (is_array($body)) {
                $error_message = (string)($body['error']['message'] ?? $body['error']['code'] ?? '');
            }

            if ($error_message === '') {
                $error_message = 'Unknown OpenAI API error';
            }

            WABE_Logger::error('OpenAI API error [' . $status_code . ']: ' . $error_message);
            return '';
        }

        if (!is_array($body)) {
            WABE_Logger::error('OpenAI API error: invalid JSON response');
            return '';
        }

        $text = $this->extract_text_from_response($body);

        if ($text === '') {
            WABE_Logger::warning('OpenAI: empty text response');
        }

        return $text;
    }

    private function extract_text_from_response($body)
    {
        if (empty($body['output']) || !is_array($body['output'])) {
            return '';
        }

        foreach ($body['output'] as $item) {
            if (empty($item['content']) || !is_array($item['content'])) {
                continue;
            }

            $texts = [];

            foreach ($item['content'] as $content) {
                if (isset($content['text']) && is_string($content['text'])) {
                    $texts[] = $content['text'];
                }
            }

            $joined = trim(implode("\n", $texts));
            if ($joined !== '') {
                return $joined;
            }
        }

        return '';
    }
}
