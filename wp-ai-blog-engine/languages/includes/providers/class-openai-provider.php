<?php
if (!defined('ABSPATH')) exit;

class WABE_OpenAI_Provider implements WABE_AI_Provider_Interface
{
    private $key;

    public function __construct()
    {
        $o = get_option(WABE_OPTION, []);
        $this->key = trim((string)($o['openai_api_key'] ?? $o['api_key'] ?? ''));
    }

    public function text($prompt, $args = [])
    {
        if ($this->key === '') {
            WABE_Logger::warning('OpenAI: API key missing');
            return '';
        }

        $args = wp_parse_args($args, [
            'model' => 'gpt-4.1-mini',
            'temperature' => 0.7,
            'max_output_tokens' => 2200,
        ]);

        $response = wp_remote_post('https://api.openai.com/v1/responses', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->key,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 60,
            'body'    => wp_json_encode([
                'model'             => $args['model'],
                'input'             => $prompt,
                'temperature'       => (float)$args['temperature'],
                'max_output_tokens' => (int)$args['max_output_tokens'],
            ]),
        ]);

        if (is_wp_error($response)) {
            WABE_Logger::error('OpenAI HTTP error: ' . $response->get_error_message());
            return '';
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            WABE_Logger::error(
                'OpenAI API error ' . $code . ' ' .
                    ($body['error']['code'] ?? '') . ' ' .
                    ($body['error']['message'] ?? 'Unknown error')
            );
            return '';
        }

        if (!isset($body['output']) || !is_array($body['output'])) {
            return '';
        }

        foreach ($body['output'] as $item) {
            if (empty($item['content']) || !is_array($item['content'])) continue;
            foreach ($item['content'] as $content) {
                if (isset($content['text'])) {
                    return (string)$content['text'];
                }
            }
        }

        return '';
    }
}
