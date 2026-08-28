<?php

namespace MathCourse\Video;

defined('ABSPATH') || exit;

/**
 * Generates short-lived signed tokens for protected video resources.
 *
 * Token format: base64url(payload).base64url(signature)
 * Payload contains video id, user id and expiration timestamp.
 */
class Token
{
    private const TTL = 1800;

    public function create($video_id, $user_id = null, $ttl = self::TTL)
    {
        $video_id = absint($video_id);
        $user_id  = $user_id === null ? get_current_user_id() : absint($user_id);
        $ttl      = max(60, absint($ttl));

        if (!$video_id || !$user_id) {
            return '';
        }

        $payload = array(
            'video_id' => $video_id,
            'user_id'  => $user_id,
            'exp'      => time() + $ttl,
        );

        $encoded = $this->base64url_encode(wp_json_encode($payload));
        $signature = $this->sign($encoded);

        return $encoded . '.' . $signature;
    }

    public function verify($token, $video_id = null, $user_id = null)
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        list($encoded, $signature) = $parts;
        $expected = $this->sign($encoded);

        if (!hash_equals($expected, $signature)) {
            return false;
        }

        $json = $this->base64url_decode($encoded);
        $payload = json_decode($json, true);

        if (!is_array($payload) || empty($payload['video_id']) || empty($payload['user_id']) || empty($payload['exp'])) {
            return false;
        }

        if ((int) $payload['exp'] < time()) {
            return false;
        }

        if ($video_id !== null && absint($video_id) !== (int) $payload['video_id']) {
            return false;
        }

        if ($user_id !== null && absint($user_id) !== (int) $payload['user_id']) {
            return false;
        }

        return $payload;
    }

    private function sign($value)
    {
        return hash_hmac('sha256', $value, wp_salt('auth'));
    }

    private function base64url_encode($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64url_decode($value)
    {
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);
        return base64_decode(strtr($value, '-_', '+/'));
    }
}
