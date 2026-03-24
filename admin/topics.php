<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($this->options ?? null) ? $this->options : [];
$topics = is_array($opt['topics'] ?? null) ? $opt['topics'] : [];
$history = is_array($opt['history'] ?? null) ? $opt['history'] : [];
$max_topics = 10;

if (!function_exists('wabe_topics_tone_options')) {
    function wabe_topics_tone_options()
    {
        return [
            'standard' => __('Standard', WABE_TEXTDOMAIN),
            'professional' => __('Professional', WABE_TEXTDOMAIN),
            'casual' => __('Casual', WABE_TEXTDOMAIN),
            'friendly' => __('Friendly', WABE_TEXTDOMAIN),
            'formal' => __('Formal', WABE_TEXTDOMAIN),
        ];
    }
}

if (!function_exists('wabe_topics_style_label')) {
    function wabe_topics_style_label($style)
    {
        $style = (string) $style;
        $map = [
            'standard' => __('Standard', WABE_TEXTDOMAIN),
            'normal'   => __('Normal', WABE_TEXTDOMAIN),
            'how-to'   => __('How-to', WABE_TEXTDOMAIN),
            'review'   => __('Review', WABE_TEXTDOMAIN),
            'news'     => __('News', WABE_TEXTDOMAIN),
            'list'     => __('List', WABE_TEXTDOMAIN),
        ];
        return $map[$style] ?? $style;
    }
}

$tone_options = wabe_topics_tone_options();
?>

<div class="wrap">
    <h1 style="margin-bottom:16px;"><?php esc_html_e('Topics', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Topics saved.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($_GET['predicted'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Predicted topics added.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:20px;align-items:start;">
        <div style="display:flex;flex-direction:column;gap:20px;">

            <div
                style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                    <div>
                        <h2 style="margin:0 0 6px;font-size:20px;">
                            <?php esc_html_e('Queued Topics', WABE_TEXTDOMAIN); ?></h2>
                        <p style="margin:0;color:#6b7280;">
                            <?php esc_html_e('Register one topic per line. Articles will be generated from the queued topics.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </div>
                    <span
                        style="display:inline-flex;align-items:center;padding:8px 14px;border-radius:999px;font-weight:700;background:#eff6ff;color:#1d4ed8;">
                        <?php echo esc_html(count($topics) . ' / ' . $max_topics); ?>
                    </span>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    style="margin-top:20px;">
                    <input type="hidden" name="action" value="wabe_save_topics">
                    <?php wp_nonce_field('wabe_save_topics'); ?>

                    <textarea name="topics" rows="12" class="large-text"
                        placeholder="<?php echo esc_attr__('Enter one topic per line.', WABE_TEXTDOMAIN); ?>"
                        style="width:100%;"><?php
                                            $topic_lines = [];
                                            foreach ($topics as $topic_item) {
                                                if (is_array($topic_item) && !empty($topic_item['topic'])) {
                                                    $topic_lines[] = $topic_item['topic'];
                                                } elseif (is_string($topic_item) && $topic_item !== '') {
                                                    $topic_lines[] = $topic_item;
                                                }
                                            }
                                            echo esc_textarea(implode("\n", $topic_lines));
                                            ?></textarea>

                    <p class="description" style="margin-top:10px;">
                        <?php esc_html_e('Only the first 10 lines are recommended for stable operation.', WABE_TEXTDOMAIN); ?>
                    </p>

                    <div style="margin-top:16px;">
                        <?php submit_button(__('Save Topics', WABE_TEXTDOMAIN), 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>

            <div
                style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                    <div>
                        <h2 style="margin:0 0 6px;font-size:20px;">
                            <?php esc_html_e('Topic History', WABE_TEXTDOMAIN); ?></h2>
                        <p style="margin:0;color:#6b7280;">
                            <?php esc_html_e('Recently generated posts and topic history.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </div>
                </div>

                <?php if (!empty($history)) : ?>
                    <div style="margin-top:18px;display:grid;gap:12px;">
                        <?php foreach (array_reverse($history) as $item) : ?>
                            <?php
                            $topic_text = is_array($item) ? (string) ($item['topic'] ?? '') : '';
                            $tone = is_array($item) ? (string) ($item['tone'] ?? 'standard') : 'standard';
                            $style = is_array($item) ? (string) ($item['style'] ?? 'standard') : 'standard';
                            $post_id = is_array($item) ? (int) ($item['post_id'] ?? 0) : 0;
                            $post_title = $post_id > 0 ? get_the_title($post_id) : '';
                            $post_link = $post_id > 0 ? get_permalink($post_id) : '';
                            ?>
                            <div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px;background:#fafafa;">
                                <div style="font-weight:700;margin-bottom:6px;">
                                    <?php echo esc_html($topic_text !== '' ? $topic_text : '—'); ?>
                                </div>
                                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                                    <span
                                        style="display:inline-flex;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:12px;font-weight:700;">
                                        <?php echo esc_html($tone_options[$tone] ?? $tone); ?>
                                    </span>
                                    <span
                                        style="display:inline-flex;padding:4px 10px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:12px;font-weight:700;">
                                        <?php echo esc_html(wabe_topics_style_label($style)); ?>
                                    </span>
                                </div>

                                <?php if ($post_id > 0 && $post_link) : ?>
                                    <div style="font-size:13px;color:#4b5563;">
                                        <?php esc_html_e('Post:', WABE_TEXTDOMAIN); ?>
                                        <a href="<?php echo esc_url($post_link); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($post_title !== '' ? $post_title : ('#' . $post_id)); ?>
                                        </a>
                                    </div>
                                <?php else : ?>
                                    <div style="font-size:13px;color:#6b7280;">—</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p style="margin-top:18px;color:#6b7280;">
                        <?php esc_html_e('No topic history yet.', WABE_TEXTDOMAIN); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">
            <div
                style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('AI Topic Suggestions', WABE_TEXTDOMAIN); ?>
                </h2>
                <p style="margin:0 0 16px;color:#6b7280;">
                    <?php esc_html_e('Generate suggested topics and append them to the queue.', WABE_TEXTDOMAIN); ?>
                </p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wabe_generate_predicted_topics">
                    <?php wp_nonce_field('wabe_generate_predicted_topics'); ?>
                    <?php submit_button(__('Generate Suggested Topics', WABE_TEXTDOMAIN), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div
                style="background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('Tips', WABE_TEXTDOMAIN); ?></h2>
                <ul style="margin:12px 0 0 18px;color:#4b5563;line-height:1.8;">
                    <li><?php esc_html_e('Add one topic per line.', WABE_TEXTDOMAIN); ?></li>
                    <li><?php esc_html_e('Clear, specific topics usually generate better articles.', WABE_TEXTDOMAIN); ?>
                    </li>
                    <li><?php esc_html_e('Too many queued topics may make operation harder to track.', WABE_TEXTDOMAIN); ?>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
