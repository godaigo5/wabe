<?php
if (!defined('ABSPATH')) exit;
class WABE_Outline_Generator
{
    static function generate($topic)
    {
        if (!WABE_Plan::can_use_outline_generator()) return '';
        $topic = sanitize_text_field((string)$topic);
        if ($topic === '') return '';
        return (new WABE_OpenAI())->text("Create a concise SEO blog outline with H2 and H3 headings only.\n\nTopic:\n{$topic}", ['temperature' => 0.6, 'max_output_tokens' => 500]);
    }
}
