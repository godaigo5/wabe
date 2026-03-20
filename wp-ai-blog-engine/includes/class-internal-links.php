<?php
if (!defined('ABSPATH')) exit;
class WABE_Internal_Links
{
    static function generate($content, $limit = 5)
    {
        if (!WABE_Plan::can_use_internal_links()) return $content;
        $posts = get_posts(['numberposts' => absint($limit), 'post_status' => 'publish', 'post_type' => 'post', 'orderby' => 'date', 'order' => 'DESC']);
        if (!$posts) return $content;
        $links = '<h2>' . esc_html__('Related Articles', WABE_TEXTDOMAIN) . '</h2><ul>';
        foreach ($posts as $p) {
            $links .= '<li><a href="' . esc_url(get_permalink($p->ID)) . '">' . esc_html($p->post_title) . '</a></li>';
        }
        $links .= '</ul>';
        return $content . "\n\n" . $links;
    }
}
