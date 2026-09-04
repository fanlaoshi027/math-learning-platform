<?php
namespace MathCourse\Video;

defined('ABSPATH') || exit;

/**
 * 让 HLS 播放列表可以从独立媒体目录读取。
 *
 * 课程后台仍保存站内 URL，例如：
 * https://fanlaoshishu.com/__mathcourse_hls/7shang-dapeiyou-2026/p1/index.m3u8
 *
 * PHP 只在读取 m3u8 时把这个逻辑 URL 映射到本机媒体目录；
 * ts/m4s/aac 分片由 Hls_Accelerator + Nginx 直接传输。
 */
class Local_Hls_Source {
    const URL_PREFIX = '/__mathcourse_hls/';

    public function __construct() {
        add_filter('pre_http_request', array($this, 'intercept'), 10, 3);
    }

    private function same_site_host($a, $b) {
        $a = strtolower(ltrim((string) $a, '.'));
        $b = strtolower(ltrim((string) $b, '.'));
        if (strpos($a, 'www.') === 0) $a = substr($a, 4);
        if (strpos($b, 'www.') === 0) $b = substr($b, 4);
        return $a !== '' && $a === $b;
    }

    public function intercept($preempt, $args, $url) {
        if (false !== $preempt || !defined('MATHCOURSE_MEDIA_ROOT') || !MATHCOURSE_MEDIA_ROOT) {
            return $preempt;
        }

        $parts = wp_parse_url((string) $url);
        $site = wp_parse_url(home_url('/'));
        if (!$parts || !$site) return $preempt;
        if (strtolower((string) ($parts['scheme'] ?? '')) !== strtolower((string) ($site['scheme'] ?? ''))) return $preempt;
        if (!$this->same_site_host($parts['host'] ?? '', $site['host'] ?? '')) return $preempt;
        if (!empty($site['port']) && (int) ($parts['port'] ?? 0) !== (int) $site['port']) return $preempt;

        $path = (string) ($parts['path'] ?? '');
        if (strpos($path, self::URL_PREFIX) !== 0) return $preempt;
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'm3u8') return $preempt;

        $relative = ltrim(substr($path, strlen(self::URL_PREFIX)), '/');
        if ($relative === '' || preg_match('#(^|/)\.\.?(/|$)#', $relative)) return $preempt;
        if (preg_match('#[^A-Za-z0-9._/ -]#', $relative)) return $preempt;

        $root = rtrim((string) MATHCOURSE_MEDIA_ROOT, '/\\');
        if ($root === '' || !is_dir($root)) return $preempt;
        $file = $root . '/' . $relative;
        if (!is_file($file) || !is_readable($file)) return $preempt;

        $body = file_get_contents($file);
        if (false === $body) return $preempt;

        return array(
            'headers'  => array(
                'content-type'   => 'application/vnd.apple.mpegurl',
                'content-length' => strlen($body),
            ),
            'body'     => $body,
            'response' => array('code' => 200, 'message' => 'OK'),
            'cookies'  => array(),
            'filename' => null,
        );
    }
}
