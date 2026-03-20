<?php
if (!defined('ABSPATH')) exit;

$message = isset($_GET['wabe_message']) ? sanitize_text_field(wp_unslash($_GET['wabe_message'])) : '';
$opt     = $this->options;
$topics  = $opt['topics'] ?? [];
$history = $opt['history'] ?? [];

for ($i = count($topics); $i < 10; $i++) {
    $topics[] = [
        'topic' => '',
        'style' => 'normal',
        'tone'  => 'standard',
    ];
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Topics', WABE_TEXTDOMAIN); ?></h1>

    <?php if ($message): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="wabe_save_topics">
        <?php wp_nonce_field('wabe_save_topics', 'wabe_topics_nonce'); ?>

        <table class="widefat striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th style="width:60px;"><?php esc_html_e('#', WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Topic', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:180px;"><?php esc_html_e('Style', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:180px;"><?php esc_html_e('Tone', WABE_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topics as $index => $row): ?>
                    <tr>
                        <td><?php echo esc_html($index + 1); ?></td>
                        <td>
                            <input type="text" name="wabe_topics[<?php echo esc_attr($index); ?>][topic]"
                                value="<?php echo esc_attr($row['topic'] ?? ''); ?>" class="regular-text"
                                style="width:100%;"
                                placeholder="<?php esc_attr_e('Enter a blog topic', WABE_TEXTDOMAIN); ?>">
                        </td>
                        <td>
                            <input type="text" name="wabe_topics[<?php echo esc_attr($index); ?>][style]"
                                value="<?php echo esc_attr($row['style'] ?? 'normal'); ?>" class="regular-text"
                                style="width:100%;"
                                placeholder="<?php esc_attr_e('e.g. normal, how-to, review', WABE_TEXTDOMAIN); ?>">
                        </td>
                        <td>
                            <select name="wabe_topics[<?php echo esc_attr($index); ?>][tone]" style="width:100%;">
                                <option value="standard" <?php selected($row['tone'] ?? 'standard', 'standard'); ?>>
                                    <?php esc_html_e('Standard', WABE_TEXTDOMAIN); ?>
                                </option>
                                <option value="polite" <?php selected($row['tone'] ?? '', 'polite'); ?>>
                                    <?php esc_html_e('Polite', WABE_TEXTDOMAIN); ?>
                                </option>
                                <option value="casual" <?php selected($row['tone'] ?? '', 'casual'); ?>>
                                    <?php esc_html_e('Casual', WABE_TEXTDOMAIN); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="description" style="margin-top:10px;">
            <?php esc_html_e('Register up to 10 topics. After a post is generated successfully, the first topic is removed automatically and the list shifts up.', WABE_TEXTDOMAIN); ?>
        </p>

        <?php submit_button(__('Save Topics', WABE_TEXTDOMAIN)); ?>
    </form>

    <hr>

    <h2><?php esc_html_e('Generation History', WABE_TEXTDOMAIN); ?></h2>

    <?php if (empty($history)): ?>
        <div class="card" style="padding:16px;max-width:1100px;">
            <p><?php esc_html_e('No generation history yet.', WABE_TEXTDOMAIN); ?></p>
        </div>
    <?php else: ?>
        <table class="widefat striped" style="max-width:1100px;">
            <thead>
                <tr>
                    <th style="width:160px;"><?php esc_html_e('Date', WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Topic', WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Post Title', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:160px;"><?php esc_html_e('Post Link', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:120px;"><?php esc_html_e('Style', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:120px;"><?php esc_html_e('Tone', WABE_TEXTDOMAIN); ?></th>
                    <th style="width:120px;"><?php esc_html_e('Status', WABE_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history as $row): ?>
                    <?php
                    $post_id = intval($row['post_id'] ?? 0);
                    $edit_link = $post_id ? get_edit_post_link($post_id, '') : '';
                    ?>
                    <tr>
                        <td><?php echo esc_html($row['date'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['topic'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['title'] ?? ''); ?></td>
                        <td>
                            <?php if ($edit_link): ?>
                                <a href="<?php echo esc_url($edit_link); ?>">
                                    <?php esc_html_e('Open Post', WABE_TEXTDOMAIN); ?>
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($row['style'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['tone'] ?? ''); ?></td>
                        <td><?php echo esc_html($row['status'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
