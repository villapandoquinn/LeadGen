<?php
/**
 * RateLimiter - Application-level protection against brute force and abuse
 */
class RateLimiter {
    public static function check(string $action, int $maxAttempts = 5, int $decaySeconds = 300): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rate_' . md5($action . '_' . $ip);

        $now = time();
        $record = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => $now, 'locked_until' => 0];

        if ($record['locked_until'] > $now) {
            $remaining = $record['locked_until'] - $now;
            return [
                'allowed' => false,
                'remaining_seconds' => $remaining,
                'message' => "Too many attempts. Please try again in {$remaining} seconds."
            ];
        }

        if ($now - $record['first_attempt'] > $decaySeconds) {
            $record['attempts'] = 0;
            $record['first_attempt'] = $now;
        }

        $record['attempts']++;

        if ($record['attempts'] > $maxAttempts) {
            $record['locked_until'] = $now + $decaySeconds;
            $_SESSION[$key] = $record;
            return [
                'allowed' => false,
                'remaining_seconds' => $decaySeconds,
                'message' => "Too many attempts. Please try again in {$decaySeconds} seconds."
            ];
        }

        $_SESSION[$key] = $record;
        return [
            'allowed' => true,
            'attempts_left' => max(0, $maxAttempts - $record['attempts'])
        ];
    }

    public static function reset(string $action): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'rate_' . md5($action . '_' . $ip);
        unset($_SESSION[$key]);
    }
}
