<?php
declare(strict_types=1);

namespace Enyak;

/**
 * Lightweight file-based fixed-window rate limiter (portable on shared hosting; no APCu/Redis).
 * Fails open if the filesystem is unavailable so a transient FS issue never blocks playback.
 */
final class RateLimiter
{
    public static function allow(string $key, int $max, int $windowSeconds): bool
    {
        $dir = sys_get_temp_dir() . '/enyak_rl';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $file = $dir . '/' . md5($key);
        $fp = @fopen($file, 'c+');
        if ($fp === false) {
            return true; // fail-open
        }

        $allowed = true;
        if (flock($fp, LOCK_EX)) {
            $now = time();
            $data = ['start' => $now, 'count' => 0];
            $raw = stream_get_contents($fp);
            if ($raw !== false && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
            if (($now - (int) ($data['start'] ?? 0)) >= $windowSeconds) {
                $data = ['start' => $now, 'count' => 0];
            }
            $data['count'] = (int) ($data['count'] ?? 0) + 1;
            $allowed = $data['count'] <= $max;

            rewind($fp);
            ftruncate($fp, 0);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
        }
        fclose($fp);
        return $allowed;
    }
}
