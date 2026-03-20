<?php
if (!defined('ABSPATH')) exit;

$opt = is_array($this->options ?? null) ? $this->options : [];

$topics = is_array($opt['topics'] ?? null) ? $opt['topics'] : [];
$history = is_array($opt['history'] ?? null) ? $opt['history'] : [];

if (!function_exists('wabe_topics_tone_options')) {
    function wabe_topics_tone_options()
    {
        return [
            'standard' => __('Standard', WABE_TEXTDOMAIN),
            'polite'   => __('Polite', WABE_TEXTDOMAIN),
            'casual'   => __('Casual', WABE_TEXTDOMAIN),
        ];
    }
}

if (!function_exists('wabe_topics_style_label')) {
    function wabe_topics_style_label($style)
    {
        $style = (string)$style;

        $map = [
            'normal' => __('Normal', WABE_TEXTDOMAIN),
            'how-to' => __('How-to', WABE_TEXTDOMAIN),
            'review' => __('Review', WABE_TEXTDOMAIN),
            'news'   => __('News', WABE_TEXTDOMAIN),
            'list'   => __('List', WABE_TEXTDOMAIN),
        ];

        return $map[$style] ?? $style;
    }
}

$max_topics = 10;
?>
<div class="wrap">
    <h1><?php esc_html_e('Topics', WABE_TEXTDOMAIN); ?></h1>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="wabe_save_topics">
        <?php wp_nonce_field('wabe_save_topics', 'wabe_topics_nonce'); ?>

        <table class="widefat striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th style="width:60px;"><?php esc_html_e('NO', WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Topic', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:180px;"><?php esc_html_e('Style', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:180px;"><?php esc_html_e('Tone', WABE_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php for ($i = 0; $i < $max_topics; $i++) : ?>
                    <?php
                    $row = $topics[$i] ?? [];
                    $topic_value = '';
                    $style_value = 'normal';
                    $tone_value  = 'standard';

                    if (is_string($row)) {
                        $topic_value = $row;
                    } elseif (is_array($row)) {
                        $topic_value = (string)($row['topic'] ?? '');
                        $style_value = (string)($row['style'] ?? 'normal');
                        $tone_value  = (string)($row['tone'] ?? 'standard');
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html((string)($i + 1)); ?></td>
                        <td>
                            <input type="text" name="wabe_topics[<?php echo esc_attr((string)$i); ?>][topic]"
                                value="<?php echo esc_attr($topic_value); ?>"
                                placeholder="<?php esc_attr_e('Enter a blog topic', WABE_TEXTDOMAIN); ?>"
                                class="regular-text" style="width:100%;">
                        </td>
                        <td>
                            <select name="wabe_topics[<?php echo esc_attr((string)$i); ?>][style]" style="width:100%;">
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
                        </td>
                        <td>
                            <select name="wabe_topics[<?php echo esc_attr((string)$i); ?>][tone]" style="width:100%;">
                                <?php foreach (wabe_topics_tone_options() as $key => $label) : ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($tone_value, $key); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endfor; ?>
            </tbody>
        </table>

        <p class="description" style="max-width:1100px;margin-top:10px;">
            <?php esc_html_e('Register up to 10 topics. After a post is generated successfully, the first topic is removed automatically and the list shifts up.', WABE_TEXTDOMAIN); ?>
        </p>

        <?php submit_button(__('Save Topics', WABE_TEXTDOMAIN)); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Generation History', WABE_TEXTDOMAIN); ?></h2>

    <?php if (empty($history)) : ?>
        <p><?php esc_html_e('No generation history yet.', WABE_TEXTDOMAIN); ?></p>
    <?php else : ?>
        <table class="widefat striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th style="width:180px;"><?php esc_html_e('Date', WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Post Title', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:180px;"><?php esc_html_e('Style', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:140px;"><?php esc_html_e('Tone', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:140px;"><?php esc_html_e('Status', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:140px;"><?php esc_html_e('Post Link', WABE_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $item) : ?>
                    <?php
                    $date       = sanitize_text_field($item['date'] ?? '');
                    $post_title = sanitize_text_field($item['post_title'] ?? '');
                    $post_url   = esc_url($item['post_url'] ?? '');
                    $status     = sanitize_text_field($item['status'] ?? '');
                    $style      = sanitize_text_field($item['style'] ?? 'normal');
                    $tone       = sanitize_text_field($item['tone'] ?? 'standard');

                    $tone_map = wabe_topics_tone_options();
                    $tone_label = $tone_map[$tone] ?? $tone;
                    ?>
                    <tr>
                        <td><?php echo esc_html($date); ?></td>
                        <td><?php echo esc_html($post_title); ?></td>
                        <td><?php echo esc_html(wabe_topics_style_label($style)); ?></td>
                        <td><?php echo esc_html($tone_label); ?></td>
                        <td><?php echo esc_html(__($status, WABE_TEXTDOMAIN)); ?></td>
                        <td>
                            <?php if ($post_url) : ?>
                                <a href="<?php echo esc_url($post_url); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php esc_html_e('Open Post', WABE_TEXTDOMAIN); ?>
                                </a>
                            <?php else : ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
