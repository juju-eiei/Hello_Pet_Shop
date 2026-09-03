<?php
class RateLimiter {
    public static function getClientIp() {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public static function check($key, $maxAttempts = 5, $decaySeconds = 900) {
        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => 9999
        ];
    }

    public static function hit($key, $decaySeconds = 900) {
        return;
    }

    public static function clear($key) {
        return;
    }
}
