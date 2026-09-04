<?php
namespace MathCourse\Video;

defined('ABSPATH') || exit;

use MathCourse\Access\Access_Service;
use MathCourse\Tutor\Adapter;

/**
 * HLS 分片加速器。
 *
 * PHP 只负责一次授权/签名校验；真正的 .ts/.m4s 文件由 Nginx
 * X-Accel-Redirect 直接传输，避免 PHP 读取并复制整个分片。
 *
 * 默认关闭。在 wp-config.php 中加入：
 * define('MATHCOURSE_HLS_ACCEL', true);
 *
 * 启用独立媒体目录后，再加入：
 * define('MATHCOURSE_MEDIA_ROOT', '/www/wwwroot/fanlaoshishu-media/hls');
 */
class Hls_Accelerator {
    private $access;
    private $adapter;
    private $token_ttl = 604800;
    private $url_prefix = '/__mathcourse_hls/';

    public function __construct() {
        if (!defined('MATHCOURSE_HLS_ACCEL') || !MATHCOURSE_HLS_ACCEL) return;
        $this->access = new Access_Service();
        $this->adapter = new Adapter();
        add_action('template_redirect', array($this, 'handle'), 1);
    }

    private function sign($lesson_id, $expires, $file) {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', absint($lesson_id).'|'.absint($expires).'|'.(string)$file, wp_salt('auth'), true)), '+/', '-_'), '=');
    }

    private function valid_signature($lesson_id, $expires, $file, $signature) {
        if (!$expires || $expires < time() || $expires > time() + 7 * DAY_IN_SECONDS) return false;
        return hash_equals($this->sign($lesson_id, $expires, $file), (string)$signature);
    }

    private function can_watch($lesson_id) {
        $course_id = $this->adapter->get_lesson_course_id($lesson_id);
        if (!$course_id) return false;
        if (is_user_logged_in() && $this->access->can_watch_lesson(get_current_user_id(), $course_id, $lesson_id)) return true;
        return $this->access->can_preview($course_id, $lesson_id);
    }

    private function resolve_path($base_dir, $relative) {
        if (strpos($relative, '/') === 0) return $relative;
        $segments = array();
        foreach (explode('/', $base_dir.$relative) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        return '/'.implode('/', $segments);
    }

    private function normalize_file_reference($uri, $source_url) {
        $uri = trim((string)$uri);
        if ($uri === '') return '';
        $source = wp_parse_url($source_url);
        if (!$source || empty($source['scheme']) || empty($source['host'])) return false;

        if (preg_match('#^https?://#i', $uri)) {
            $target = wp_parse_url($uri);
            if (!$target || empty($target['scheme']) || empty($target['host'])) return false;
            if (strtolower($target['scheme']) !== strtolower($source['scheme']) || strtolower($target['host']) !== strtolower($source['host'])) return false;
            $source_port = (int)($source['port'] ?? 0);
            $target_port = (int)($target['port'] ?? 0);
            $source_effective = $source_port ?: ('https' === strtolower($source['scheme']) ? 443 : 80);
            $target_effective = $target_port ?: ('https' === strtolower($target['scheme']) ? 443 : 80);
            if ($source_effective !== $target_effective) return false;
            return (isset($target['path']) ? $target['path'] : '/').(!empty($target['query']) ? '?'.$target['query'] : '');
        }

        $parts = wp_parse_url($uri);
        if (!$parts || isset($parts['host']) || isset($parts['scheme'])) return false;
        $base = isset($source['path']) ? $source['path'] : '/';
        $path = $this->resolve_path(trailingslashit(dirname($base)), isset($parts['path']) ? $parts['path'] : '');
        return $path.(!empty($parts['query']) ? '?'.$parts['query'] : '');
    }

    /**
     * 仅接管 Router 生成的带 file 参数的 HLS 分片请求。
     * m3u8 仍由 Router 读取/重写；静态分片交给 Nginx。
     */
    public function handle() {
        $lesson_id = absint(get_query_var('math_video'));
        if (!$lesson_id) return;

        $file = isset($_GET['file']) ? wp_unslash($_GET['file']) : '';
        if (!is_string($file) || $file === '') return;

        $expires = absint(get_query_var('math_video_exp'));
        $signature = sanitize_text_field(get_query_var('math_video_sig'));
        if (!$this->valid_signature($lesson_id, $expires, $file, $signature)) return;
        if (!$this->can_watch($lesson_id)) return;

        $source = $this->adapter->get_lesson_hls_url($lesson_id);
        if (!$source) return;
        $type = $this->get_extension($file);
        if (!in_array($type, array('ts', 'm4s', 'aac'), true)) return;

        $source_parts = wp_parse_url($source);
        $site_parts = wp_parse_url(home_url('/'));
        if (!$source_parts || !$site_parts) return;
        if (strtolower($source_parts['scheme'] ?? '') !== strtolower($site_parts['scheme'] ?? '') || strtolower($source_parts['host'] ?? '') !== strtolower($site_parts['host'] ?? '')) return;

        $normalized = $this->normalize_file_reference($file, $source);
        if (false === $normalized || '' === $normalized) return;
        $relative = $this->get_media_relative_path($normalized);
        if (false === $relative) return;
        if ($relative === '' || preg_match('#(^|/)\.\.?(/|$)#', $relative)) return;

        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: '.$this->content_type($type));
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=3600, immutable');
        $origin = home_url('/');
        $origin_parts = wp_parse_url($origin);
        if ($origin_parts && !empty($origin_parts['scheme']) && !empty($origin_parts['host'])) {
            $allow = strtolower($origin_parts['scheme']).'://'.strtolower($origin_parts['host']);
            if (!empty($origin_parts['port'])) $allow .= ':'.$origin_parts['port'];
            header('Access-Control-Allow-Origin: '.$allow);
            header('Access-Control-Allow-Credentials: true');
        }

        header('X-Accel-Redirect: /__mathcourse_hls/'.$relative);
        exit;
    }

    /**
     * 把受信任的逻辑媒体 URL 映射为媒体根目录下的相对路径。
     * 配置 MATHCOURSE_MEDIA_ROOT 后，媒体文件脱离 WordPress uploads。
     * 未配置时保留原 uploads 映射，兼容旧课程。
     */
    private function get_media_relative_path($normalized) {
        $prefix = rtrim($this->url_prefix, '/');
        if (strpos($normalized, $prefix.'/') === 0) {
            $relative = ltrim(substr($normalized, strlen($prefix)), '/');
            if (defined('MATHCOURSE_MEDIA_ROOT') && MATHCOURSE_MEDIA_ROOT) {
                return $relative;
            }
        }

        $uploads = wp_upload_dir();
        $uploads_url_path = wp_parse_url($uploads['baseurl'], PHP_URL_PATH);
        $uploads_url_path = rtrim((string)$uploads_url_path, '/');
        if ($uploads_url_path === '' || strpos($normalized, $uploads_url_path.'/') !== 0) return false;
        return ltrim(substr($normalized, strlen($uploads_url_path)), '/');
    }

    private function get_extension($file) {
        return strtolower(pathinfo((string)wp_parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION));
    }

    private function content_type($ext) {
        if ($ext === 'ts') return 'video/mp2t';
        if ($ext === 'm4s') return 'video/iso.segment';
        if ($ext === 'aac') return 'audio/aac';
        return 'application/octet-stream';
    }
}
