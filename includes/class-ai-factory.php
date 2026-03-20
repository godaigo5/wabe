<?php
if (!defined('ABSPATH')) exit;

class WABE_AI_Factory
{
    public static function make()
    {
        $o = get_option(WABE_OPTION, []);
        $provider = sanitize_key($o['ai_provider'] ?? 'openai');

        switch ($provider) {
            case 'gemini':
                return new WABE_Gemini_Provider();

            case 'openai':
            default:
                return new WABE_OpenAI_Provider();
        }
    }

    public static function get_default_model()
    {
        $o = get_option(WABE_OPTION, []);
        $provider = sanitize_key($o['ai_provider'] ?? 'openai');

        if ($provider === 'gemini') {
            return sanitize_text_field($o['gemini_model'] ?? 'gemini-2.5-flash');
        }

        return sanitize_text_field($o['openai_model'] ?? 'gpt-4.1-mini');
    }
}
