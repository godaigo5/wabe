<?php
if (!defined('ABSPATH')) exit;

$opt     = is_array($this->options ?? null) ? $this->options : [];
$topics  = is_array($opt['topics'] ?? null) ? $opt['topics'] : [];
$history = is_array($opt['history'] ?? null) ? $opt['history'] : [];
$max_topics = 10;

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

$tone_options = wabe_topics_tone_options();
?>
<div class="wrap">
    <h1><?php echo esc_html__('Topics', WABE_TEXTDOMAIN); ?></h1>

    <?php if (!empty($_GET['wabe_message'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html(wp_unslash($_GET['wabe_message'])); ?></p>
        </div>
    <?php endif; ?>

    <div class="wabe-admin-card" style="max-width: 1100px;">
        <h2><?php echo esc_html__('Topic Queue', WABE_TEXTDOMAIN); ?></h2>
        <p><?php echo esc_html__('Up to 10 topics can be registered. When a post is generated, the first topic is removed and the remaining topics are shifted up.', WABE_TEXTDOMAIN); ?>
        </p>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="wabe_save_topics">
            <?php wp_nonce_field('wabe_save_topics', 'wabe_topics_nonce'); ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php echo esc_html__('#', WABE_TEXTDOMAIN); ?></th>
                        <th><?php echo esc_html__('Topic', WABE_TEXTDOMAIN); ?></th>
                        <th style="width: 180px;"><?php echo esc_html__('Style', WABE_TEXTDOMAIN); ?></th>
                        <th style="width: 180px;"><?php echo esc_html__('Tone', WABE_TEXTDOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ($i = 0; $i < $max_topics; $i++) : ?>
                        <?php
                        $row   = $topics[$i] ?? [];
                        $topic = (string)($row['topic'] ?? '');
                        $style = (string)($row['style'] ?? 'normal');
                        $tone  = (string)($row['tone'] ?? 'standard');
                        ?>
                        <tr>
                            <td><?php echo esc_html((string)($i + 1)); ?></td>
                            <td>
                                <input type="text" name="wabe_topics[<?php echo esc_attr((string)$i); ?>][topic]"
                                    value="<?php echo esc_attr($topic); ?>" class="regular-text" style="width: 100%;"
                                    maxlength="200">
                            </td>
                            <td>
                                <select name="wabe_topics[<?php echo esc_attr((string)$i); ?>][style]">
                                    <option value="normal" <?php selected($style, 'normal'); ?>>
                                        <?php echo esc_html__('Normal', WABE_TEXTDOMAIN); ?></option>
                                    <option value="how-to" <?php selected($style, 'how-to'); ?>>
                                        <?php echo esc_html__('How-to', WABE_TEXTDOMAIN); ?></option>
                                    <option value="review" <?php selected($style, 'review'); ?>>
                                        <?php echo esc_html__('Review', WABE_TEXTDOMAIN); ?></option>
                                    <option value="news" <?php selected($style, 'news'); ?>>
                                        <?php echo esc_html__('News', WABE_TEXTDOMAIN); ?></option>
                                    <option value="list" <?php selected($style, 'list'); ?>>
                                        <?php echo esc_html__('List', WABE_TEXTDOMAIN); ?></option>
                                </select>
                            </td>
                            <td>
                                <select name="wabe_topics[<?php echo esc_attr((string)$i); ?>][tone]">
                                    <?php foreach ($tone_options as $tone_key => $tone_label) : ?>
                                        <option value="<?php echo esc_attr($tone_key); ?>" <?php selected($tone, $tone_key); ?>>
                                            <?php echo esc_html($tone_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    <?php endfor; ?>
                </tbody>
            </table>

            <p style="margin-top: 16px;">
                <button type="submit"
                    class="button button-primary"><?php echo esc_html__('Save Topics', WABE_TEXTDOMAIN); ?></button>
            </p>
        </form>
    </div>

    <div class="wabe-admin-card" style="max-width: 1100px; margin-top: 24px;">
        <h2><?php echo esc_html__('Topic History', WABE_TEXTDOMAIN); ?></h2>

        <?php if (empty($history)) : ?>
            <p><?php echo esc_html__('No history yet.', WABE_TEXTDOMAIN); ?></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width: 160px;"><?php echo esc_html__('Date', WABE_TEXTDOMAIN); ?></th>
                        <th><?php echo esc_html__('Topic', WABE_TEXTDOMAIN); ?></th>
                        <th><?php echo esc_html__('Post Title', WABE_TEXTDOMAIN); ?></th>
                        <th style="width: 140px;"><?php echo esc_html__('Post Link', WABE_TEXTDOMAIN); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Style', WABE_TEXTDOMAIN); ?></th>
                        <th style="width: 120px;"><?php echo esc_html__('Tone', WABE_TEXTDOMAIN); ?></th>
                        <th style="width: 140px;"><?php echo esc_html__('Status', WABE_TEXTDOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $row) : ?>
                        <?php
                        $created_at = (string)($row['created_at'] ?? '');
                        $topic      = (string)($row['topic'] ?? '');
                        $title      = (string)($row['title'] ?? '');
                        $post_url   = (string)($row['post_url'] ?? '');
                        $post_id    = (int)($row['post_id'] ?? 0);
                        $style      = (string)($row['style'] ?? 'normal');
                        $tone       = (string)($row['tone'] ?? 'standard');
                        $status     = (string)($row['status'] ?? '');
                        $view_url   = $post_url !== '' ? $post_url : ($post_id > 0 ? get_permalink($post_id) : '');
                        ?>
                        <tr>
                            <td><?php echo esc_html($created_at !== '' ? $created_at : '—'); ?></td>
                            <td><?php echo esc_html($topic); ?></td>
                            <td><?php echo esc_html($title); ?></td>
                            <td>
                                <?php if ($view_url !== '') : ?>
                                    <a href="<?php echo esc_url($view_url); ?>" target="_blank"
                                        rel="noopener noreferrer"><?php echo esc_html__('Open', WABE_TEXTDOMAIN); ?></a>
                                <?php else : ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(wabe_topics_style_label($style)); ?></td>
                            <td><?php echo esc_html($tone_options[$tone] ?? $tone); ?></td>
                            <td><?php echo esc_html($status); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
