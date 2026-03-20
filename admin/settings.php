<?php if (!defined('ABSPATH')) exit; $message=isset($_GET['wabe_message'])?sanitize_text_field(wp_unslash($_GET['wabe_message'])):''; $opt=$this->options; $plan=WABE_Plan::get_plan(); $license=WABE_License::sync(false); $masked=!empty($opt['api_key'])?str_repeat('*',max(12,mb_strlen((string)$opt['api_key']))):''; ?>
<div class="wrap">
    <h1><?php esc_html_e('WP AI Blog Engine',WABE_TEXTDOMAIN); ?></h1>
    <p><strong><?php esc_html_e('Current Plan:',WABE_TEXTDOMAIN); ?></strong> <?php echo esc_html(ucfirst($plan)); ?>
    </p><?php if($message):?><div class="notice notice-success is-dismissible">
        <p><?php echo esc_html($message); ?></p>
    </div><?php endif; ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action"
            value="wabe_save_settings"><?php wp_nonce_field('wabe_save_settings','wabe_settings_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="wabe_api_key"><?php esc_html_e('OpenAI API Key',WABE_TEXTDOMAIN); ?></label></th>
                <td><input id="wabe_api_key" type="text" name="wabe_api_key" class="regular-text"
                        value="<?php echo esc_attr($masked); ?>"></td>
            </tr>
            <tr>
                <th><label for="wabe_generation_count"><?php esc_html_e('Title Count',WABE_TEXTDOMAIN); ?></label></th>
                <td><input id="wabe_generation_count" type="number" min="1"
                        max="<?php echo esc_attr(WABE_Plan::title_count_max()); ?>" name="wabe_generation_count"
                        value="<?php echo esc_attr($opt['generation_count']??1); ?>" class="small-text"></td>
            </tr>
            <tr>
                <th><label for="wabe_tone"><?php esc_html_e('Tone',WABE_TEXTDOMAIN); ?></label></th>
                <td><select name="wabe_tone" id="wabe_tone">
                        <option value="standard" <?php selected($opt['tone']??'','standard'); ?>>
                            <?php esc_html_e('Standard',WABE_TEXTDOMAIN); ?></option>
                        <option value="polite" <?php selected($opt['tone']??'','polite'); ?>>
                            <?php esc_html_e('Polite',WABE_TEXTDOMAIN); ?></option>
                        <option value="casual" <?php selected($opt['tone']??'','casual'); ?>>
                            <?php esc_html_e('Casual',WABE_TEXTDOMAIN); ?></option>
                    </select></td>
            </tr>
            <tr>
                <th><label for="wabe_post_status"><?php esc_html_e('Post Status',WABE_TEXTDOMAIN); ?></label></th>
                <td><select name="wabe_post_status" id="wabe_post_status">
                        <option value="draft" <?php selected($opt['post_status']??'','draft'); ?>>
                            <?php esc_html_e('Draft',WABE_TEXTDOMAIN); ?></option>
                        <option value="publish" <?php selected($opt['post_status']??'','publish'); ?>
                            <?php disabled(!WABE_Plan::can_publish()); ?>>
                            <?php esc_html_e('Publish',WABE_TEXTDOMAIN); ?></option>
                    </select></td>
            </tr>
            <tr>
                <th><label for="wabe_weekly_posts"><?php esc_html_e('Weekly Posts',WABE_TEXTDOMAIN); ?></label></th>
                <td><input id="wabe_weekly_posts" type="number" min="1"
                        max="<?php echo esc_attr(WABE_Plan::weekly_posts_max()); ?>" name="wabe_weekly_posts"
                        value="<?php echo esc_attr($opt['weekly_posts']??1); ?>" class="small-text"></td>
            </tr>
        </table><?php submit_button(__('Save Settings',WABE_TEXTDOMAIN)); ?>
    </form>
    <hr>
    <h2><?php esc_html_e('Manual Generation',WABE_TEXTDOMAIN); ?></h2>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action"
            value="wabe_manual_generate"><?php wp_nonce_field('wabe_manual_generate'); ?><?php submit_button(__('Generate Now',WABE_TEXTDOMAIN),'secondary'); ?>
    </form>
    <div class="card" style="padding:15px;margin-top:20px;">
        <h2><?php esc_html_e('System Status',WABE_TEXTDOMAIN); ?></h2>
        <p><strong><?php esc_html_e('Next Run:',WABE_TEXTDOMAIN); ?></strong>
            <?php echo esc_html($this->next_post_date); ?></p>
        <p><strong><?php esc_html_e('Ready to Post:',WABE_TEXTDOMAIN); ?></strong>
            <?php echo $this->is_ready_to_post()?esc_html__('Yes',WABE_TEXTDOMAIN):esc_html__('No',WABE_TEXTDOMAIN); ?>
        </p>
        <p><strong><?php esc_html_e('License Status:',WABE_TEXTDOMAIN); ?></strong>
            <?php echo esc_html($license['status']??'active'); ?></p>
    </div>
</div>
