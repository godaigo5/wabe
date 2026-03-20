<?php
if (!defined('ABSPATH')) exit;
class WABE_SEO
{
    public function optimize($content, $keyword = '')
    {
        if (!WABE_Plan::can_use_seo()) return $content;
        $content = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $content);
        $content = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $content);
        $content = preg_replace('/^# (.+)$/m', '<h2>$1</h2>', $content);
        if ($keyword !== '' && mb_strpos($content, $keyword) === false) {
            if (preg_match('/<p>.*?<\/p>/s', $content)) $content = preg_replace('/(<p>.*?<\/p>)/s', '$1<p>' . esc_html($keyword) . ' について詳しく解説します。</p>', $content, 1);
            else $content = '<p>' . esc_html($keyword) . ' について詳しく解説します。</p>' . $content;
        }
        if (mb_stripos($content, 'FAQ') === false && mb_stripos($content, 'よくある質問') === false) {
            $content .= '\n\n<h2>' . esc_html__('Frequently Asked Questions', WABE_TEXTDOMAIN) . '</h2>';
        }
        return $content;
    }
}
