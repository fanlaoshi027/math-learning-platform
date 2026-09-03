<?php
namespace MathCourse\Video;
defined('ABSPATH') || exit;
use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

class Video_Router {
    private $access;
    private $adapter;
    private $token_ttl = 604800;

    public function __construct($register_hooks = true) {
        $this->access = new Access_Service();
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

    public function get_media_type($source_url) {
        $path = (string) wp_parse_url((string) $source_url, PHP_URL_PATH);
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return $ext === 'm3u8' ? 'm3u8' : ($ext === 'mp4' ? 'mp4' : '');
    }

    public function get_protected_url($lesson_id) {
        $lesson_id = absint($lesson_id);
        if (!$lesson_id || !$this->adapter->get_lesson($lesson_id) || !$this->can_watch($lesson_id)) return '';
        $expires = time() + $this->token_ttl;
        return home_url('/math-video/' . $lesson_id . '/' . $expires . '/' . $this->sign($lesson_id, $expires, '') . '/');
    }

    private function sign($lesson_id, $expires, $file) {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', absint($lesson_id) . '|' . absint($expires) . '|' . (string) $file, wp_salt('auth'), true)), '+/', '-_'), '=');
    }

    private function valid_signature($lesson_id, $expires, $file, $signature) {
        if (!$expires || $expires < time() || $expires > time() + 7 * DAY_IN_SECONDS) return false;
        return hash_equals($this->sign($lesson_id, $expires, $file), (string) $signature);
    }

    private function get_source_url($lesson_id) { return $this->adapter->get_lesson_hls_url($lesson_id); }

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
        $file = isset($_GET['file']) ? wp_unslash($_GET['file']) : '';
        if (!is_string($file)) $file = '';
        if (!$this->valid_signature($lesson_id, $expires, $file, $signature)) { status_header(403); exit('视频访问链接已失效。'); }
        $source = $this->get_source_url($lesson_id);
        if (!$source) { status_header(404); exit('视频不存在。'); }
        $media_type = $this->get_media_type($source);
        if ($media_type === 'm3u8') {
            if ($file === '') $this->serve_playlist($lesson_id, $expires, $source);
            else $this->serve_media($lesson_id, $expires, $file, $source);
        } elseif ($media_type === 'mp4') {
            if ($file !== '') { status_header(403); exit('视频地址无效。'); }
            $this->serve_mp4($source);
        } else { status_header(415); exit('暂不支持的视频格式。'); }
        exit;
    }

    private function gateway_url($lesson_id, $expires, $file_ref) {
        $sig = $this->sign($lesson_id, $expires, $file_ref);
        return add_query_arg('file', $file_ref, home_url('/math-video/' . $lesson_id . '/' . $expires . '/' . $sig . '/'));
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

    private function normalize_file_reference($uri, $source_url) {
        $uri = trim((string) $uri);
        if ($uri === '') return '';
        $source_parts = wp_parse_url($source_url);
        if (!$source_parts || empty($source_parts['scheme']) || empty($source_parts['host'])) return false;
        if (preg_match('#^https?://#i', $uri)) {
            $target = wp_parse_url($uri);
            if (!$target || empty($target['scheme']) || empty($target['host'])) return false;
            if (strtolower($target['scheme']) !== strtolower($source_parts['scheme']) || strtolower($target['host']) !== strtolower($source_parts['host'])) return false;
            return (isset($target['path']) ? $target['path'] : '/') . (!empty($target['query']) ? '?' . $target['query'] : '');
        }
        $uri_parts = wp_parse_url($uri);
        if (!$uri_parts || isset($uri_parts['host']) || isset($uri_parts['scheme'])) return false;
        $base_path = isset($source_parts['path']) ? $source_parts['path'] : '/';
        $path = $this->resolve_path(trailingslashit(dirname($base_path)), isset($uri_parts['path']) ? $uri_parts['path'] : '');
        return $path . (!empty($uri_parts['query']) ? '?' . $uri_parts['query'] : '');
    }

    private function rewrite_playlist($lesson_id, $expires, $body, $source) {
        $lines = preg_split('/\r\n|\r|\n/', $body);
        foreach ($lines as $index => $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            if (strpos($trimmed, '#') === 0) {
                $rewritten = preg_replace_callback('/URI=("|\')([^"\']+)\1/i', function ($match) use ($lesson_id, $expires, $source) {
                    $ref = $this->normalize_file_reference($match[2], $source);
                    if ($ref === false || $ref === '') return $match[0];
                    return 'URI=' . $match[1] . $this->gateway_url($lesson_id, $expires, $ref) . $match[1];
                }, $line);
                if ($rewritten === null) return false;
                $lines[$index] = $rewritten;
                continue;
            }
            $ref = $this->normalize_file_reference($trimmed, $source);
            if ($ref === false || $ref === '') return false;
            $lines[$index] = $this->gateway_url($lesson_id, $expires, $ref);
        }
        return implode("\n", $lines);
    }

    private function send_hls_headers($type) {
        nocache_headers();
        header('Content-Type: ' . $type);
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
    }

    private function serve_playlist($lesson_id, $expires, $source) {
        $response = wp_remote_get($source, array('timeout' => 15, 'redirection' => 3, 'sslverify' => true));
        if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) { status_header(502); exit('视频源暂时无法访问。'); }
        $body = wp_remote_retrieve_body($response);
        $rewritten = $this->rewrite_playlist($lesson_id, $expires, $body, $source);
        if ($rewritten === false) { status_header(403); exit('视频播放列表包含无效来源。'); }
        $this->send_hls_headers('application/vnd.apple.mpegurl');
        echo $rewritten;
    }

    private function resolve_source_file($source, $file_ref) {
        $source_parts = wp_parse_url($source);
        $file_parts = wp_parse_url($file_ref);
        if (!$source_parts || !$file_parts || empty($source_parts['scheme']) || empty($source_parts['host'])) return '';
        $origin = strtolower($source_parts['scheme']) . '://' . strtolower($source_parts['host']);
        if (!empty($source_parts['port'])) $origin .= ':' . $source_parts['port'];
        $base_dir = trailingslashit(dirname(isset($source_parts['path']) ? $source_parts['path'] : '/'));
        $path = !empty($file_parts['path']) && strpos($file_parts['path'], '/') === 0 ? $file_parts['path'] : $this->resolve_path($base_dir, isset($file_parts['path']) ? $file_parts['path'] : '');
        return $origin . $path . (!empty($file_parts['query']) ? '?' . $file_parts['query'] : '');
    }

    private function serve_media($lesson_id, $expires, $file_ref, $source) {
        $file = $this->resolve_source_file($source, $file_ref);
        if (!$file) { status_header(403); exit('视频片段地址无效。'); }
        $response = wp_remote_get($file, array('timeout' => 20, 'redirection' => 3, 'sslverify' => true));
        if (is_wp_error($response)) { status_header(502); exit('视频片段暂时无法访问。'); }
        $code = (int) wp_remote_retrieve_response_code($response);
        if ($code !== 200 && $code !== 206) { status_header(502); exit('视频片段暂时无法访问。'); }
        $body = wp_remote_retrieve_body($response);
        $ext = strtolower(pathinfo((string) wp_parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION));
        if ($ext === 'm3u8') {
            $rewritten = $this->rewrite_playlist($lesson_id, $expires, $body, $file);
            if ($rewritten === false) { status_header(403); exit('视频子播放列表包含无效来源。'); }
            $this->send_hls_headers('application/vnd.apple.mpegurl');
            echo $rewritten;
            return;
        }
        $type = $ext === 'ts' ? 'video/mp2t' : ($ext === 'm4s' ? 'video/iso.segment' : ($ext === 'aac' ? 'audio/aac' : 'application/octet-stream'));
        $this->send_hls_headers($type);
        header('Content-Length: ' . strlen($body));
        echo $body;
    }

    private function serve_mp4($source) {
        if (!function_exists('curl_init')) { status_header(500); exit('服务器暂不支持 MP4 流式播放。'); }
        $range = isset($_SERVER['HTTP_RANGE']) ? trim((string) wp_unslash($_SERVER['HTTP_RANGE'])) : '';
        $ch = curl_init($source);
        if (!$ch) { status_header(502); exit('视频源暂时无法访问。'); }
        $headers = array();
        $status = 0;
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header) use (&$headers, &$status) {
            $trim = trim($header);
            if (preg_match('#^HTTP/\S+\s+(\d+)#i', $trim, $m)) $status = (int) $m[1];
            elseif (strpos($trim, ':') !== false) { list($name, $value) = array_map('trim', explode(':', $header, 2)); $headers[strtolower($name)] = $value; }
            return strlen($header);
        });
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$headers, &$status) {
            if (!headers_sent()) {
                status_header($status === 206 ? 206 : 200);
                header('Content-Type: ' . (isset($headers['content-type']) ? $headers['content-type'] : 'video/mp4'));
                if (isset($headers['content-range'])) header('Content-Range: ' . $headers['content-range']);
                header('Accept-Ranges: bytes');
            }
            echo $data;
            flush();
            return strlen($data);
        });
        if ($range !== '' && preg_match('/^bytes=\d*-\d*$/i', $range)) curl_setopt($ch, CURLOPT_RANGE, substr($range, 6));
        curl_exec($ch);
        curl_close($ch);
    }
}
