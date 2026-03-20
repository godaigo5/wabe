<?php if (!defined('ABSPATH')) exit; $saved=isset($_GET['saved'])?sanitize_text_field(wp_unslash($_GET['saved'])):''; $generated=isset($_GET['generated'])?intval($_GET['generated']):0; $error=isset($_GET['wabe_error'])?sanitize_text_field(wp_unslash($_GET['wabe_error'])):''; ?>
<div class="wrap">
    <h1><?php esc_html_e('Topics',WABE_TEXTDOMAIN); ?></h1><?php if($saved):?><div
        class="notice notice-success is-dismissible">
        <p><?php esc_html_e('Topics saved.',WABE_TEXTDOMAIN); ?></p>
    </div><?php endif; ?><?php if($generated):?><div class="notice notice-success is-dismissible">
        <p><?php printf(esc_html__('%d topics generated.',WABE_TEXTDOMAIN),$generated); ?></p>
    </div><?php endif; ?><?php if($error):?><div class="notice notice-error is-dismissible">
        <p><?php echo esc_html($error); ?></p>
    </div><?php endif; ?>
    <?php if(WABE_Plan::can_use_topic_generator()):?><div class="card" style="padding:15px;margin-bottom:20px;">
        <h2><?php esc_html_e('Pro: AI Topic Generator',WABE_TEXTDOMAIN); ?></h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden"
                name="action"
                value="wabe_generate_topics"><?php wp_nonce_field('wabe_generate_topics','wabe_generate_topics_nonce'); ?><input
                type="text" name="wabe_seed_keyword" class="regular-text"
                placeholder="<?php esc_attr_e('Seed keyword',WABE_TEXTDOMAIN); ?>">
            <?php submit_button(__('Generate Topics',WABE_TEXTDOMAIN),'secondary','',false); ?></form>
    </div><?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action"
            value="wabe_save_topics"><?php wp_nonce_field('wabe_save_topics','wabe_topics_nonce'); ?><table
            class="widefat striped">
            <thead>
                <tr>
                    <th style="width:60px;">No</th>
                    <th><?php esc_html_e('Topic',WABE_TEXTDOMAIN); ?></th>
                    <th><?php esc_html_e('Style',WABE_TEXTDOMAIN); ?></th>
                    <th style="width:140px;"><?php esc_html_e('Tone',WABE_TEXTDOMAIN); ?></th>
                </tr>
            </thead>
            <tbody><?php for($i=0;$i<10;$i++): ?><tr>
                    <td><?php echo esc_html($i+1); ?></td>
                    <td><input class="regular-text" type="text" name="topics[<?php echo esc_attr($i); ?>][topic]"
                            value="<?php echo esc_attr($topics[$i]['topic']??''); ?>"></td>
                    <td><input class="regular-text" type="text" name="topics[<?php echo esc_attr($i); ?>][style]"
                            value="<?php echo esc_attr($topics[$i]['style']??''); ?>"></td>
                    <td><select name="topics[<?php echo esc_attr($i); ?>][tone]">
                            <option value="standard" <?php selected($topics[$i]['tone']??'','standard'); ?>>
                                <?php esc_html_e('Standard',WABE_TEXTDOMAIN); ?></option>
                            <option value="polite" <?php selected($topics[$i]['tone']??'','polite'); ?>>
                                <?php esc_html_e('Polite',WABE_TEXTDOMAIN); ?></option>
                            <option value="casual" <?php selected($topics[$i]['tone']??'','casual'); ?>>
                                <?php esc_html_e('Casual',WABE_TEXTDOMAIN); ?></option>
                        </select></td>
                </tr><?php endfor; ?></tbody>
        </table><?php submit_button(__('Save Topics',WABE_TEXTDOMAIN)); ?></form>
    <h2><?php esc_html_e('Generation History',WABE_TEXTDOMAIN); ?></h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Date',WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Topic',WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Post Title',WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Post Link',WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Style',WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Tone',WABE_TEXTDOMAIN); ?></th>
                <th><?php esc_html_e('Status',WABE_TEXTDOMAIN); ?></th>
            </tr>
        </thead>
        <tbody><?php if(empty($history)): ?><tr>
                <td colspan="7"><?php esc_html_e('No history yet.',WABE_TEXTDOMAIN); ?></td>
            </tr><?php else: foreach($history as $h): $post_id=intval($h['post_id']??0); ?><tr>
                <td><?php echo esc_html($h['date']??''); ?></td>
                <td><?php echo esc_html($h['topic']??''); ?></td>
                <td><?php echo esc_html($h['title']??''); ?></td>
                <td><?php if($post_id): ?><a
                        href="<?php echo esc_url(get_edit_post_link($post_id)); ?>"><?php esc_html_e('Edit',WABE_TEXTDOMAIN); ?></a><?php else: ?>—<?php endif; ?>
                </td>
                <td><?php echo esc_html($h['style']??''); ?></td>
                <td><?php echo esc_html($h['tone']??''); ?></td>
                <td><?php echo esc_html($h['status']??''); ?></td>
            </tr><?php if(!empty($h['titles'])&&is_array($h['titles'])): ?><tr>
                <td></td>
                <td colspan="6"><strong><?php esc_html_e('Generated Titles:',WABE_TEXTDOMAIN); ?></strong>
                    <ul style="margin:8px 0 0 18px;"><?php foreach($h['titles'] as $t): ?><li>
                            <?php echo esc_html($t); ?></li><?php endforeach; ?></ul>
                </td>
            </tr><?php endif; endforeach; endif; ?></tbody>
    </table>
</div>
