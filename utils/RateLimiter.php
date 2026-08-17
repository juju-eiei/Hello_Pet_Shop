<?php
require_once __DIR__ . '/../config/database.php';

class RateLimiter {
    private static $tableInitialized = false;

    /**
     * Get Client IP address safely
     */
    public static function getClientIp() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * Ensure rate_limits table exists in database
     */
    private static function ensureTable($db) {
        if (self::$tableInitialized) return;
        try {
            $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
                rate_key VARCHAR(191) PRIMARY KEY,
                attempts INT NOT NULL DEFAULT 1,
                last_attempt_time INT NOT NULL,
                blocked_until INT NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
            $db->exec($sql);
            self::$tableInitialized = true;
        } catch (Exception $e) {
            error_log("RateLimiter ensureTable error: " . $e->getMessage());
        }
    }

    /**
     * Check if rate limit is exceeded
     * 
     * @param string $key Unique identifier (e.g. 'login:' . $ip)
     * @param int $maxAttempts Maximum allowed attempts
     * @param int $decaySeconds Window / Lockout time in seconds
     * @return array ['allowed' => bool, 'retry_after' => int, 'remaining' => int]
     */
    public static function check($key, $maxAttempts = 5, $decaySeconds = 900) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            self::ensureTable($db);

            $now = time();

            // Periodic garbage collection (1 in 50 requests)
            if (mt_rand(1, 50) === 1) {
                $cleanStmt = $db->prepare("DELETE FROM rate_limits WHERE blocked_until < ? AND last_attempt_time < ?");
                $cleanStmt->execute([$now, $now - $decaySeconds]);
            }

            $stmt = $db->prepare("SELECT attempts, last_attempt_time, blocked_until FROM rate_limits WHERE rate_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return [
                    'allowed' => true,
                    'retry_after' => 0,
                    'remaining' => $maxAttempts
                ];
            }

            $blockedUntil = (int)$row['blocked_until'];
            if ($blockedUntil > $now) {
                return [
                    'allowed' => false,
                    'retry_after' => $blockedUntil - $now,
                    'remaining' => 0
                ];
            }

            $lastTime = (int)$row['last_attempt_time'];
            if (($now - $lastTime) > $decaySeconds) {
                // Window expired, reset
                $resetStmt = $db->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
                $resetStmt->execute([$key]);
                return [
                    'allowed' => true,
                    'retry_after' => 0,
                    'remaining' => $maxAttempts
                ];
            }

            $attempts = (int)$row['attempts'];
            if ($attempts >= $maxAttempts) {
                $blockUntilTime = $now + $decaySeconds;
                $upStmt = $db->prepare("UPDATE rate_limits SET blocked_until = ? WHERE rate_key = ?");
                $upStmt->execute([$blockUntilTime, $key]);

                return [
                    'allowed' => false,
                    'retry_after' => $decaySeconds,
                    'remaining' => 0
                ];
            }

            return [
                'allowed' => true,
                'retry_after' => 0,
                'remaining' => max(0, $maxAttempts - $attempts)
            ];

        } catch (Exception $e) {
            error_log("RateLimiter check error: " . $e->getMessage());
            return ['allowed' => true, 'retry_after' => 0, 'remaining' => $maxAttempts];
        }
    }

    /**
     * Increment attempts count
     */
    public static function hit($key, $decaySeconds = 900) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            self::ensureTable($db);

            $now = time();

            $stmt = $db->prepare("SELECT attempts, last_attempt_time FROM rate_limits WHERE rate_key = ?");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                $ins = $db->prepare("INSERT INTO rate_limits (rate_key, attempts, last_attempt_time, blocked_until) VALUES (?, 1, ?, 0)");
                $ins->execute([$key, $now]);
            } else {
                $lastTime = (int)$row['last_attempt_time'];
                if (($now - $lastTime) > $decaySeconds) {
                    $up = $db->prepare("UPDATE rate_limits SET attempts = 1, last_attempt_time = ?, blocked_until = 0 WHERE rate_key = ?");
                    $up->execute([$now, $key]);
                } else {
                    $up = $db->prepare("UPDATE rate_limits SET attempts = attempts + 1, last_attempt_time = ? WHERE rate_key = ?");
                    $up->execute([$now, $key]);
                }
            }
        } catch (Exception $e) {
            error_log("RateLimiter hit error: " . $e->getMessage());
        }
    }

    /**
     * Clear rate limit on successful action (e.g. successful login)
     */
    public static function clear($key) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
            $stmt->execute([$key]);
        } catch (Exception $e) {
            error_log("RateLimiter clear error: " . $e->getMessage());
        }
    }
}
?>
