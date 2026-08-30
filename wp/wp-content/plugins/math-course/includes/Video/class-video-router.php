<?php
namespace MathCourse\Video;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

/** Short-lived signed HLS gateway. */
class Video_Router {
    private $access;
    private $adapter;
    private $token_ttl = 600;

    /**
     * Hook registration is optional so service classes can generate URLs
     * without registering duplicate WordPress request handlers.
     */
    public function __construct($register_hooks = true) {
        $this->access  = new Access_Service();
        $this->adapter = new Adapter();

        if ($register_hooks) {
            add_action('init', array($this, 'register_route'));
            add_action('template_redirect', array($this, 'handle'));
        }
    }

    public function register_route() {
        add_rewrite_rule('^math-video/([0-9]+)/([0-9]+)/([A-Za-z0-9_-]+)/?$', 'index.php?math_video=$matches[1]&math_video_exp=$matches[2]&math_video_sig=$matches[3]', 'top');
        add_rewrite_tag('%math_video%', '([0-9]+)');
        add_rewrite_tag('%math_video_exp%', '([0-9]+)');
        add_rewrite_tag('%math_video_sig%', '([A-Za-z0-9_-]+)');
    }

    public function get_protected_url($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id || !$this->adapter->get_lesson($lesson_id)) return '';
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
        return $this->adapter->get_lesson_hls_url($lesson_id);
    }

    private function can_watch($lesson_id) {
        $course_id = $this->adapter->get_lesson_course_id($lesson_id);
        if (!$course_id) return false;

        if (is_user_logged_in() && $this->access->can_watch_lesson(get_current_user_id(), $course_id, $lesson_id)) return true;
        return $this->access->can_preview($course_id, $lesson_id);
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
        else $this->serve_media($lesson_id, $expires, $file, $source);
        exit;
    }

    /**
     * Rewrite every media URI in a playlist, including nested m3u8 playlists.
     * The browser therefore never receives the real HLS origin.
     */
    private function rewrite_playlist($lesson_id, $expires, $body, $source) {
        $parts = wp_parse_url($source);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) return false;

        $lines = preg_split('/\r\n|\r|\n/', $body);
        foreach ($lines as $index => $line) {
            $uri = trim($line);
            if ($uri === '' || strpos($uri, '#') === 0) continue;

            $file_ref = $uri;
            if (preg_match('#^https?://#i', $uri)) {
                $target = wp_parse_url($uri);
                if (!$target || empty($target['scheme']) || empty($target['host']) || strtolower($target['scheme']) !== strtolower($parts['scheme']) || strtolower($target['host']) !== strtolower($parts['host']) || (!empty($parts['port']) && (string) $parts['port'] !== (string) ($target['port'] ?? ''))) {
                    return false;
                }
                $file_ref = (isset($target['path']) ? $target['path'] : '/') . (!empty($target['query']) ? '?' . $target['query'] : '');
            }

            $sig = $this->sign($lesson_id, $expires, $file_ref);
            $lines[$index] = add_query_arg('file', rawurlencode($file_ref), home_url('/math-video/' . $lesson_id . '/' . $expires . '/' . $sig . '/'));
        }

        return implode("\n", $lines);
    }

    private function serve_playlist($lesson_id, $expires, $source) {
        $response = wp_remote_get($source, array('timeout' => 15, 'redirection' => 3, 'sslverify' => true, 'headers' => array('Accept' => 'application/vnd.apple.mpegurl, application/x-mpegURL, */*')));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) {
            status_header(502); exit('视频源暂时无法访问。');
        }

        $body = wp_remote_retrieve_body($response);
        if (!$body) { status_header(502); exit('视频播放列表为空。'); }

        $rewritten = $this->rewrite_playlist($lesson_id, $expires, $body, $source);
        if (false === $rewritten) {
            status_header(403); exit('视频播放列表包含无效来源。');
        }

        nocache_headers();
        header('Content-Type: application/vnd.apple.mpegurl');
        header('X-Content-Type-Options: nosniff');
        echo $rewritten;
    }

    private function resolve_source_file($source, $file_ref) {
        $source_parts = wp_parse_url($source);
        if (!$source_parts || empty($source_parts['scheme']) || empty($source_parts['host'])) return '';
        if (preg_match('#^https?://#i', $file_ref)) return '';

        $file_parts = wp_parse_url($file_ref);
        if (!$file_parts) return '';

        $origin = strtolower($source_parts['scheme']) . '://' . strtolower($source_parts['host']);
        if (!empty($source_parts['port'])) $origin .= ':' . $source_parts['port'];

        if (!empty($file_parts['path']) && strpos($file_parts['path'], '/') === 0) {
            $path = $file_parts['path'];
        } else {
            $base_dir = trailingslashit(dirname(isset($source_parts['path']) ? $source_parts['path'] : '/'));
            $path = $this->resolve_path($base_dir, $file_parts['path'] ?? '');
        }

        return $origin . $path . (!empty($file_parts['query']) ? '?' . $file_parts['query'] : '');
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

    private function serve_media($lesson_id, $expires, $file_ref, $source) {
        $signature = sanitize_text_field(get_query_var('math_video_sig'));
        if (!$this->valid_signature($lesson_id, $expires, $file_ref, $signature)) {
            status_header(403); exit('视频片段访问链接已失效。');
        }

        $file = $this->resolve_source_file($source, $file_ref);
        if (!$file) {
            status_header(403); exit('视频片段地址无效。');
        }

        $request_headers = array('Accept' => '*/*');
        if (!empty($_SERVER['HTTP_RANGE'])) {
            $request_headers['Range'] = sanitize_text_field(wp_unslash($_SERVER['HTTP_RANGE']));
        }

        $response = wp_remote_get($file, array('timeout' => 20, 'redirection' => 0, 'sslverify' => true, 'headers' => $request_headers, 'stream' => false));
        if (is_wp_error($response)) {
            status_header(502); exit('视频片段暂时无法访问。');
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        if (200 !== $code && 206 !== $code) {
            status_header(502); exit('视频片段暂时无法访问。');
        }

        $body = wp_remote_retrieve_body($response);
        $ext = strtolower(pathinfo((string) wp_parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION));

        // Nested playlists must also be rewritten; otherwise the real origin
        // would be exposed by the second-level m3u8 response.
        if ($ext === 'm3u8') {
            $rewritten = $this->rewrite_playlist($lesson_id, $expires, $body, $file);
            if (false === $rewritten) {
                status_header(403); exit('视频子播放列表包含无效来源。');
            }
            nocache_headers();
            header('Content-Type: application/vnd.apple.mpegurl');
            header('X-Content-Type-Options: nosniff');
            echo $rewritten;
            return;
        }

        $type = 'application/octet-stream';
        if ($ext === 'ts') $type = 'video/mp2t';
        elseif ($ext === 'm4s') $type = 'video/iso.segment';
        elseif ($ext === 'aac') $type = 'audio/aac';

        if (206 === $code) {
            status_header(206);
            $content_range = wp_remote_retrieve_header($response, 'content-range');
            if ($content_range) header('Content-Range: ' . $content_range);
            header('Accept-Ranges: bytes');
        }

        nocache_headers();
        header('Content-Type: ' . $type);
        header('Content-Length: ' . strlen($body));
        header('X-Content-Type-Options: nosniff');
        echo $body;
    }
}
