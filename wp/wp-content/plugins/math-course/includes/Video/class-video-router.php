<?php
namespace MathCourse\Video;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;

/** Short-lived signed HLS gateway. */
class Video_Router {
    private $access;
    private $token_ttl = 600;

    public function __construct() {
        $this->access = new Access_Service();
        add_action('init', array($this, 'register_route'));
        add_action('template_redirect', array($this, 'handle'));
    }

    public function register_route() {
        add_rewrite_rule('^math-video/([0-9]+)/([0-9]+)/([A-Za-z0-9_-]+)/?$', 'index.php?math_video=$matches[1]&math_video_exp=$matches[2]&math_video_sig=$matches[3]', 'top');
        add_rewrite_tag('%math_video%', '([0-9]+)');
        add_rewrite_tag('%math_video_exp%', '([0-9]+)');
        add_rewrite_tag('%math_video_sig%', '([A-Za-z0-9_-]+)');
    }

    public function get_protected_url($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id) return '';
        $expires = time() + $this->token_ttl;
        return home_url('/math-video/' . $lesson_id . '/' . $expires . '/' . $this->sign($lesson_id, $expires, '') . '/');
    }

    private function sign($lesson_id, $expires, $file) {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', absint($lesson_id) . '|' . absint($expires) . '|' . (string) $file, wp_salt('auth'), true)), '+/', '-_'), '=');
    }

    private function valid_signature($lesson_id, $expires, $file, $signature) {
        if (!$expires || $expires < time() || $expires > time() + DAY_IN_SECONDS) return false;
        return hash_equals($this->sign($lesson_id, $expires, $file), (string) $signature);
    }

    private function get_source_url($lesson_id) {
        $url = get_post_meta($lesson_id, '_mathcourse_hls_url', true);
        if (!$url) $url = get_post_meta($lesson_id, '_mathcourse_video_url', true);
        return esc_url_raw($url);
    }

    private function can_watch($lesson_id) {
        $course_id = function_exists('tutor_utils') ? absint(tutor_utils()->get_course_id_by_content($lesson_id)) : 0;
        if (is_user_logged_in() && $course_id && $this->access->can_watch_lesson(get_current_user_id(), $course_id, $lesson_id)) return true;
        return $course_id && $this->access->can_preview($course_id, $lesson_id);
    }

    public function handle() {
        $lesson_id = absint(get_query_var('math_video'));
        if (!$lesson_id) return;

        $expires = absint(get_query_var('math_video_exp'));
        $signature = sanitize_text_field(get_query_var('math_video_sig'));
        $file = isset($_GET['file']) ? rawurldecode(wp_unslash($_GET['file'])) : '';

        if (!$this->valid_signature($lesson_id, $expires, $file, $signature)) {
            status_header(403);
            exit('视频访问链接已失效。');
        }
        if (!$this->can_watch($lesson_id)) {
            status_header(403);
            exit('暂无观看权限，请联系老师开通课程。');
        }

        $source = $this->get_source_url($lesson_id);
        if (!$source) {
            status_header(404);
            exit('视频不存在。');
        }

        if ($file === '') $this->serve_playlist($lesson_id, $expires, $source);
        else $this->serve_media($file);
        exit;
    }

    private function serve_playlist($lesson_id, $expires, $source) {
        $response = wp_remote_get($source, array('timeout' => 15, 'redirection' => 3, 'sslverify' => true));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            status_header(502); exit('视频源暂时无法访问。');
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) { status_header(502); exit('视频播放列表为空。'); }

        $parts = wp_parse_url($source);
        $origin = (!empty($parts['scheme']) && !empty($parts['host'])) ? $parts['scheme'] . '://' . $parts['host'] . (!empty($parts['port']) ? ':' . $parts['port'] : '') : '';
        $base_dir = trailingslashit(dirname(isset($parts['path']) ? $parts['path'] : '/'));
        $lines = preg_split('/\r\n|\r|\n/', $body);

        foreach ($lines as $index => $line) {
            $uri = trim($line);
            if ($uri === '' || strpos($uri, '#') === 0) continue;
            $absolute = preg_match('#^https?://#i', $uri) ? $uri : $origin . $this->resolve_path($base_dir, $uri);
            $sig = $this->sign($lesson_id, $expires, $absolute);
            $lines[$index] = add_query_arg('file', rawurlencode($absolute), home_url('/math-video/' . $lesson_id . '/' . $expires . '/' . $sig . '/'));
        }

        nocache_headers();
        header('Content-Type: application/vnd.apple.mpegurl');
        header('X-Content-Type-Options: nosniff');
        echo implode("\n", $lines);
    }

    private function resolve_path($base_dir, $relative) {
        if (strpos($relative, '/') === 0) return $relative;
        $segments = array();
        foreach (explode('/', $base_dir . $relative) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') { array_pop($segments); continue; }
            $segments[] = $segment;
        }
        return '/' . implode('/', $segments);
    }

    private function serve_media($file) {
        $response = wp_remote_get($file, array('timeout' => 20, 'redirection' => 3, 'sslverify' => true, 'headers' => array('Accept' => '*/*')));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            status_header(502); exit('视频片段暂时无法访问。');
        }

        $body = wp_remote_retrieve_body($response);
        $ext = strtolower(pathinfo((string) wp_parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION));
        $type = 'application/octet-stream';
        if ($ext === 'ts') $type = 'video/mp2t';
        elseif ($ext === 'm4s') $type = 'video/iso.segment';
        elseif ($ext === 'aac') $type = 'audio/aac';

        nocache_headers();
        header('Content-Type: ' . $type);
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        echo $body;
    }
}
