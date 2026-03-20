<?php
if (!defined('ABSPATH')) exit;
class WABE_Image
{
    private $key;
    public function __construct()
    {
        $o = get_option(WABE_OPTION, []);
        $this->key = trim((string)($o['api_key'] ?? ''));
    }
    public function generate($keyword)
    {
        if (!WABE_Plan::can_use_images() || $this->key === '') return '';
        $r = wp_remote_post('https://api.openai.com/v1/images/generations', ['headers' => ['Authorization' => 'Bearer ' . $this->key, 'Content-Type' => 'application/json'], 'timeout' => 60, 'body' => wp_json_encode(['model' => 'gpt-image-1', 'prompt' => 'Create a clean blog featured image, no text. Topic: ' . sanitize_text_field($keyword), 'size' => '1024x1024'])]);
        if (is_wp_error($r)) return '';
        $code = wp_remote_retrieve_response_code($r);
        $body = json_decode(wp_remote_retrieve_body($r), true);
        if ($code !== 200) return '';
        if (!empty($body['data'][0]['url'])) return esc_url_raw($body['data'][0]['url']);
        if (!empty($body['data'][0]['b64_json'])) return $this->save_base64_image((string)$body['data'][0]['b64_json'], $keyword);
        return '';
    }
    public function set_featured_image($post_id, $image_source)
    {
        if (!$image_source || !$post_id) return false;
        $id = filter_var($image_source, FILTER_VALIDATE_URL) ? $this->sideload_from_url($image_source, $post_id) : $this->attach_local_file($image_source, $post_id);
        if (!$id) return false;
        set_post_thumbnail($post_id, $id);
        return true;
    }
    private function sideload_from_url($url, $post_id)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url($url);
        if (is_wp_error($tmp)) return false;
        $fa = ['name' => sanitize_file_name(basename(wp_parse_url($url, PHP_URL_PATH) ?: ('wabe-' . time() . '.png'))), 'tmp_name' => $tmp];
        $id = media_handle_sideload($fa, $post_id);
        if (is_wp_error($id)) {
            @unlink($tmp);
            return false;
        }
        return (int)$id;
    }
    private function attach_local_file($path, $post_id)
    {
        if (!file_exists($path)) return false;
        $u = wp_upload_dir();
        $target = trailingslashit($u['path']) . basename($path);
        if (!@copy($path, $target)) return false;
        $att = ['post_mime_type' => 'image/png', 'post_title' => sanitize_file_name(pathinfo($target, PATHINFO_FILENAME)), 'post_status' => 'inherit'];
        $id = wp_insert_attachment($att, $target, $post_id, true);
        if (is_wp_error($id)) return false;
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $data = wp_generate_attachment_metadata($id, $target);
        wp_update_attachment_metadata($id, $data);
        return (int)$id;
    }
    private function save_base64_image($b64, $keyword)
    {
        $u = wp_upload_dir();
        $file = 'wabe-' . sanitize_title($keyword) . '-' . time() . '.png';
        $path = trailingslashit($u['basedir']) . $file;
        $data = base64_decode($b64);
        if ($data === false) return '';
        return file_put_contents($path, $data) ? $path : '';
    }
}
