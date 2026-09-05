<?php
namespace MathCourse\Video;

defined('ABSPATH') || exit;

/**
 * HLS 分片加速器。
 *
 * 启用后，PHP 只验证短期 HMAC 签名，静态 .ts/.m4s/.aac 分片交给
 * Nginx X-Accel-Redirect 直接发送，避免 PHP 读取和复制分片。
 *
 * wp-config.php：
 * define('MATHCOURSE_HLS_ACCEL', true);
 * define('MATHCOURSE_MEDIA_ROOT', '/www/wwwroot/fanlaoshishu-media/hls');
 */
class Hls_Accelerator {
    private $token_ttl = 604800;
    private $url_prefix = '/__mathcourse_hls/';

    public function __construct() {
        if (!defined('MATHCOURSE_HLS_ACCEL') || !MATHCOURSE_HLS_ACCEL) return;
        add_action('template_redirect', array($this, 'handle'), 1);
    }

    private function sign($lesson_id, $expires, $file) {
        return rtrim(strtr(base64_encode(hash_hmac('sha256', absint($lesson_id).'|'.absint($expires).'|'.(string)$file, wp_salt('auth'), true)), '+/', '-_'), '=');
    }

    private function valid_signature($lesson_id, $expires, $file, $signature) {
        if (!$expires || $expires < time() || $expires > time() + $this->token_ttl) return false;
        return hash_equals($this->sign($lesson_id, $expires, $file), (string)$signature);
    }

    private function get_extension($file) {
        return strtolower(pathinfo((string)wp_parse_url($file, PHP_URL_PATH), PATHINFO_EXTENSION));
    }

    private function get_media_relative_path($file) {
        $parts = wp_parse_url((string)$file);
        $path = is_array($parts) && isset($parts['path']) ? (string)$parts['path'] : '';
        $prefix = rtrim($this->url_prefix, '/').'/';
        if (strpos($path, $prefix) !== 0) return false;
        $relative = ltrim(substr($path, strlen($prefix)), '/');
        if ($relative === '') return false;
        if (preg_match('#(^|/)\.\.?(/|$)#', $relative)) return false;
        if (preg_match('#[^A-Za-z0-9._/ -]#', $relative)) return false;
        if (!defined('MATHCOURSE_MEDIA_ROOT') || !MATHCOURSE_MEDIA_ROOT) return false;
        return $relative;
    }

    private function content_type($ext) {
        if ($ext === 'ts') return 'video/mp2t';
        if ($ext === 'm4s') return 'video/iso.segment';
        if ($ext === 'aac') return 'audio/aac';
        return 'application/octet-stream';
    }

    /**
     * m3u8 继续由原 Router 处理；这里只处理已经签名的静态分片。
     * 这样每个 TS 请求不再查询课程、Lesson、授权服务或源地址。
     */
    public function handle() {
        $lesson_id = absint(get_query_var('math_video'));
        if (!$lesson_id) return;

        $file = isset($_GET['file']) ? wp_unslash($_GET['file']) : '';
        if (!is_string($file) || $file === '') return;

        $expires = absint(get_query_var('math_video_exp'));
        $signature = sanitize_text_field(get_query_var('math_video_sig'));
        if (!$this->valid_signature($lesson_id, $expires, $file, $signature)) return;

        $type = $this->get_extension($file);
        if (!in_array($type, array('ts', 'm4s', 'aac'), true)) return;

        $relative = $this->get_media_relative_path($file);
        if (false === $relative) return;

        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: '.$this->content_type($type));
        header('Accept-Ranges: bytes');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=86400, immutable');

        $origin = home_url('/');
        $origin_parts = wp_parse_url($origin);
        if ($origin_parts && !empty($origin_parts['scheme']) && !empty($origin_parts['host'])) {
            $allow = strtolower($origin_parts['scheme']).'://'.strtolower($origin_parts['host']);
            if (!empty($origin_parts['port'])) $allow .= ':'.$origin_parts['port'];
            header('Access-Control-Allow-Origin: '.$allow);
            header('Access-Control-Allow-Credentials: true');
        }

        header('X-Accel-Redirect: '.$this->url_prefix.$relative);
        exit;
    }
}
