<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($this->options ?? null) ? $this->options : [];
$topics = is_array($opt['topics'] ?? null) ? $opt['topics'] : [];
$history = is_array($opt['history'] ?? null) ? $opt['history'] : [];

for ($i = count($topics); $i < 10; $i++) {
    $topics[] = [
        'topic' => '',
        'style' => 'normal',
        'tone'  => 'standard',
    ];
}

if (!function_exists('wabe_topics_tone_options')) {
    function wabe_topics_tone_options()
    {
        return [
            'standard'     => __('Standard', WABE_TEXTDOMAIN),
            'professional' => __('Professional', WABE_TEXTDOMAIN),
            'casual'       => __('Casual', WABE_TEXTDOMAIN),
            'friendly'     => __('Friendly', WABE_TEXTDOMAIN),
            'formal'       => __('Formal', WABE_TEXTDOMAIN),
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

if (!function_exists('wabe_topics_history_date')) {
    function wabe_topics_history_date($row)
    {
        $post_id = (int) ($row['post_id'] ?? 0);

        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post && !empty($post->post_date) && $post->post_date !== '0000-00-00 00:00:00') {
                return mysql2date(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    $post->post_date
                );
            }
        }

        foreach (['created_at', 'generated_at', 'date', 'created'] as $key) {
            if (!empty($row[$key])) {
                $ts = strtotime((string) $row[$key]);
                if ($ts) {
                    return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $ts);
                }
                return (string) $row[$key];
            }
        }

        return '';
    }
}

$tone_options = wabe_topics_tone_options();
?>

<div class="wrap">
    <h1 style="margin-bottom: 18px;"><?php esc_html_e('Topics', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Topics saved.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:20px;align-items:start;">

        <div style="display:flex;flex-direction:column;gap:20px;">

            <div
                style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                    <div>
                        <h2 style="margin:0 0 6px;font-size:22px;">
                            <?php esc_html_e('Queued Topics', WABE_TEXTDOMAIN); ?></h2>
                        <p style="margin:0;color:#646970;">
                            <?php esc_html_e('Register topics one by one. You can add up to 10 topics.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </div>
                </div>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
                    style="margin-top:18px;">
                    <input type="hidden" name="action" value="wabe_save_topics">
                    <?php wp_nonce_field('wabe_save_topics'); ?>

                    <div id="wabe-topics-rows" style="display:flex;flex-direction:column;gap:12px;">
                        <?php foreach ($topics as $index => $row) : ?>
                            <?php
                            $topic_value = is_array($row) ? (string) ($row['topic'] ?? '') : '';
                            $style_value = is_array($row) ? (string) ($row['style'] ?? 'normal') : 'normal';
                            $tone_value  = is_array($row) ? (string) ($row['tone'] ?? 'standard') : 'standard';
                            ?>
                            <div class="wabe-topic-row"
                                style="display:grid;grid-template-columns:minmax(0,1fr) 140px 140px auto;gap:10px;align-items:center;">
                                <input type="text"
                                    name="<?php echo esc_attr(WABE_OPTION); ?>[topics][<?php echo esc_attr($index); ?>][topic]"
                                    value="<?php echo esc_attr($topic_value); ?>"
                                    placeholder="<?php echo esc_attr__('Enter topic', WABE_TEXTDOMAIN); ?>"
                                    class="regular-text" style="width:100%;max-width:none;">

                                <select
                                    name="<?php echo esc_attr(WABE_OPTION); ?>[topics][<?php echo esc_attr($index); ?>][tone]"
                                    style="width:100%;">
                                    <?php foreach ($tone_options as $tone_key => $tone_label) : ?>
                                        <option value="<?php echo esc_attr($tone_key); ?>"
                                            <?php selected($tone_value, $tone_key); ?>>
                                            <?php echo esc_html($tone_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select
                                    name="<?php echo esc_attr(WABE_OPTION); ?>[topics][<?php echo esc_attr($index); ?>][style]"
                                    style="width:100%;">
                                    <option value="normal" <?php selected($style_value, 'normal'); ?>>
                                        <?php esc_html_e('Normal', WABE_TEXTDOMAIN); ?></option>
                                    <option value="how-to" <?php selected($style_value, 'how-to'); ?>>
                                        <?php esc_html_e('How-to', WABE_TEXTDOMAIN); ?></option>
                                    <option value="review" <?php selected($style_value, 'review'); ?>>
                                        <?php esc_html_e('Review', WABE_TEXTDOMAIN); ?></option>
                                    <option value="news" <?php selected($style_value, 'news'); ?>>
                                        <?php esc_html_e('News', WABE_TEXTDOMAIN); ?></option>
                                    <option value="list" <?php selected($style_value, 'list'); ?>>
                                        <?php esc_html_e('List', WABE_TEXTDOMAIN); ?></option>
                                </select>

                                <button type="button" class="button wabe-remove-topic-row">
                                    <?php esc_html_e('Remove', WABE_TEXTDOMAIN); ?>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px;">
                        <?php submit_button(__('Save Topics', WABE_TEXTDOMAIN), 'primary', 'submit', false); ?>
                        <button type="button" class="button" id="wabe-add-topic-row">
                            <?php esc_html_e('Add Topic', WABE_TEXTDOMAIN); ?>
                        </button>
                    </div>
                </form>
            </div>

            <div
                style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                <div
                    style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
                    <div>
                        <h2 style="margin:0 0 6px;font-size:22px;">
                            <?php esc_html_e('Topic History', WABE_TEXTDOMAIN); ?></h2>
                        <p style="margin:0;color:#646970;">
                            <?php esc_html_e('Recently generated topics and created posts.', WABE_TEXTDOMAIN); ?>
                        </p>
                    </div>
                    <div
                        style="font-size:12px;font-weight:600;color:#1d2327;background:#f6f7f7;border:1px solid #dcdcde;border-radius:999px;padding:6px 10px;">
                        <?php echo esc_html__('Latest 20', WABE_TEXTDOMAIN); ?>
                    </div>
                </div>

                <?php
                $count = 0;
                ?>

                <?php if (!empty($history)) : ?>
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        <?php foreach (array_reverse($history) as $row) : ?>
                            <?php
                            $post_id = (int) ($row['post_id'] ?? 0);
                            $post_title = $post_id > 0 ? get_the_title($post_id) : '';
                            $post_link = $post_id > 0 ? get_permalink($post_id) : '';
                            $topic = (string) ($row['topic'] ?? '');
                            $tone = (string) ($row['tone'] ?? 'standard');
                            $style = (string) ($row['style'] ?? 'normal');
                            $posted_at = wabe_topics_history_date($row);

                            if ($post_id > 0 && $post_link) :
                                $count++;
                                if ($count > 20) {
                                    break;
                                }
                            ?>
                                <div
                                    style="border:1px solid #dcdcde;border-radius:14px;background:linear-gradient(180deg,#ffffff 0%,#fbfbfc 100%);padding:6px 12px;box-shadow:0 2px 8px rgba(0,0,0,.04);">
                                    <div
                                        style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                                        <div style="flex:1;min-width:280px;">
                                            <div style="font-size:18px;font-weight:700;line-height:1.6;color:#1d2327;">
                                                <?php echo esc_html($topic); ?>
                                            </div>

                                            <div style="display:flex;gap:8px;flex-wrap:wrap;">

                                                <!-- Tone -->
                                                <span
                                                    style="display:inline-flex; align-items:center; gap:6px; border-radius:999px; background:#eef2ff;">
                                                    <?php esc_html_e('Tone', WABE_TEXTDOMAIN); ?>:
                                                    <?php echo esc_html($tone_options[$tone] ?? $tone); ?>
                                                </span>

                                                <!-- Style -->
                                                <span
                                                    style="display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border-radius:999px; background:#f3f4f6;">
                                                    <?php esc_html_e('Style', WABE_TEXTDOMAIN); ?>:
                                                    <?php echo esc_html(wabe_topics_style_label($style)); ?>
                                                </span>

                                            </div>
                                        </div>

                                        <?php if ($posted_at !== '') : ?>
                                            <div
                                                style="font-size:12px;color:#646970;white-space:nowrap;background:#f6f7f7;border:1px solid #dcdcde;border-radius:999px; ">
                                                <?php esc_html_e('Posted:', WABE_TEXTDOMAIN); ?>
                                                <?php echo esc_html($posted_at); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div
                                        style="border-top:1px solid #ececec;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                                        <div style="font-size:13px;color:#50575e;">
                                            <?php esc_html_e('Post:', WABE_TEXTDOMAIN); ?>
                                            <strong style="color:#1d2327;"><?php echo esc_html($post_title); ?></strong>
                                        </div>

                                        <a href="<?php echo esc_url($post_link); ?>" target="_blank" rel="noopener noreferrer"
                                            style="display:inline-flex;align-items:center;justify-content:center;padding:8px 14px;border-radius:8px;background:#2271b1;color:#fff;text-decoration:none;font-size:13px;font-weight:600;">
                                            <?php esc_html_e('Open Post', WABE_TEXTDOMAIN); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($count === 0) : ?>
                        <p style="margin:0;color:#646970;"><?php esc_html_e('No topics yet.', WABE_TEXTDOMAIN); ?></p>
                    <?php endif; ?>

                <?php else : ?>
                    <p style="margin:0;color:#646970;"><?php esc_html_e('No topics yet.', WABE_TEXTDOMAIN); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:20px;">
            <div
                style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('AI Topic Suggestions', WABE_TEXTDOMAIN); ?>
                </h2>
                <p style="margin:0 0 16px;color:#646970;">
                    <?php esc_html_e('Generate suggested topics and append them to the queue.', WABE_TEXTDOMAIN); ?>
                </p>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="wabe_generate_predicted_topics">
                    <?php wp_nonce_field('wabe_generate_predicted_topics'); ?>
                    <?php submit_button(__('Generate Suggested Topics', WABE_TEXTDOMAIN), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <div
                style="background:#fff;border:1px solid #dcdcde;border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(0,0,0,.04);">
                <h2 style="margin:0 0 6px;font-size:20px;"><?php esc_html_e('Tips', WABE_TEXTDOMAIN); ?></h2>
                <ul style="margin:12px 0 0 18px;color:#50575e;line-height:1.9;">
                    <li><?php esc_html_e('Add one topic per input row.', WABE_TEXTDOMAIN); ?></li>
                    <li><?php esc_html_e('Specific topics usually generate better posts.', WABE_TEXTDOMAIN); ?></li>
                    <li><?php esc_html_e('Keep the queue compact for easier operation.', WABE_TEXTDOMAIN); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const addButton = document.getElementById('wabe-add-topic-row');
        const container = document.getElementById('wabe-topics-rows');

        if (!addButton || !container) return;

        addButton.addEventListener('click', function() {
            const rows = container.querySelectorAll('.wabe-topic-row');
            const index = rows.length;

            if (index >= 10) return;

            const row = document.createElement('div');
            row.className = 'wabe-topic-row';
            row.style.display = 'grid';
            row.style.gridTemplateColumns = 'minmax(0,1fr) 140px 140px auto';
            row.style.gap = '10px';
            row.style.alignItems = 'center';

            row.innerHTML = `
            <input
                type="text"
                name="<?php echo esc_attr(WABE_OPTION); ?>[topics][${index}][topic]"
                value=""
                placeholder="<?php echo esc_attr__('Enter topic', WABE_TEXTDOMAIN); ?>"
                class="regular-text"
                style="width:100%;max-width:none;"
            >
            <select
                name="<?php echo esc_attr(WABE_OPTION); ?>[topics][${index}][tone]"
                style="width:100%;"
            >
                <option value="standard"><?php echo esc_html(__('Standard', WABE_TEXTDOMAIN)); ?></option>
                <option value="professional"><?php echo esc_html(__('Professional', WABE_TEXTDOMAIN)); ?></option>
                <option value="casual"><?php echo esc_html(__('Casual', WABE_TEXTDOMAIN)); ?></option>
                <option value="friendly"><?php echo esc_html(__('Friendly', WABE_TEXTDOMAIN)); ?></option>
                <option value="formal"><?php echo esc_html(__('Formal', WABE_TEXTDOMAIN)); ?></option>
            </select>
            <select
                name="<?php echo esc_attr(WABE_OPTION); ?>[topics][${index}][style]"
                style="width:100%;"
            >
                <option value="normal"><?php echo esc_html(__('Normal', WABE_TEXTDOMAIN)); ?></option>
                <option value="how-to"><?php echo esc_html(__('How-to', WABE_TEXTDOMAIN)); ?></option>
                <option value="review"><?php echo esc_html(__('Review', WABE_TEXTDOMAIN)); ?></option>
                <option value="news"><?php echo esc_html(__('News', WABE_TEXTDOMAIN)); ?></option>
                <option value="list"><?php echo esc_html(__('List', WABE_TEXTDOMAIN)); ?></option>
            </select>
            <button type="button" class="button wabe-remove-topic-row">
                <?php echo esc_html(__('Remove', WABE_TEXTDOMAIN)); ?>
            </button>
        `;

            container.appendChild(row);
        });

        container.addEventListener('click', function(e) {
            if (!e.target.classList.contains('wabe-remove-topic-row')) return;

            const row = e.target.closest('.wabe-topic-row');
            if (row) {
                row.remove();
            }
        });
    });
</script>
