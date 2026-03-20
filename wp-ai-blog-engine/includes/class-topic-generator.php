<?php
if (!defined('ABSPATH')) exit;
class WABE_Topic_Generator
{
    static function generate($keyword, $count = 5)
    {
        if (!WABE_Plan::can_use_topic_generator()) return [];
        $keyword = sanitize_text_field((string)$keyword);
        if ($keyword === '') return [];
        $ai = new WABE_OpenAI();
        $text = $ai->text("Generate {$count} SEO-friendly blog topics for this seed keyword. Return one topic per line only.\n\nSeed keyword:\n{$keyword}", ['temperature' => 0.8, 'max_output_tokens' => 350]);
        if ($text === '') return [];
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $topics = [];
        $existing = self::existing_topic_texts();
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^\s*[\-\*\d\.\)\(]+?\s*/u', '', (string)$line));
            if ($line === '') continue;
            $san = sanitize_text_field($line);
            if (in_array(mb_strtolower($san), $existing, true)) continue;
            $topics[] = ['topic' => $san, 'style' => '', 'tone' => 'standard'];
            $existing[] = mb_strtolower($san);
        }
        return $topics;
    }
    private static function existing_topic_texts()
    {
        $o = get_option(WABE_OPTION, []);
        $out = [];
        foreach (($o['topics'] ?? []) as $t) {
            if (!empty($t['topic'])) $out[] = mb_strtolower((string)$t['topic']);
        }
        return $out;
    }
}
