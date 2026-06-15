<?php
declare(strict_types=1);

namespace Enyak\Controllers;

use Enyak\Config;
use Enyak\Db;
use Enyak\Response;
use Enyak\Token;
use PDO;

/**
 * POST /v1/sync  body: { "deviceId": "...", "appVersion": "1.0.0", "catalogVersion": "2-171..." }
 * Registers a new device (starting a trial) and returns entitlement + the entitlement-
 * filtered channel catalog + app config.
 */
final class SyncController
{
    public function handle(): void
    {
        $body     = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $deviceId = trim((string) ($body['deviceId'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $deviceId)) {
            Response::error('Invalid deviceId', 422);
        }
        $clientCatalog = (string) ($body['catalogVersion'] ?? '');

        $pdo      = Db::conn();
        $now      = time();
        $settings = self::settings($pdo);

        $dev = self::upsertDevice($pdo, $deviceId, (int) ($settings['trial_seconds'] ?? 3600), $now);
        [$status, $expiresAt, $entitled] = self::entitlement($dev, $now);

        if ($status === 'banned') {
            Response::json([
                'status'      => 'banned',
                'entitled'    => false,
                'server_time' => date('c', $now),
                'channels'    => [],
                'config'      => self::appConfig($settings),
            ]);
        }

        $catalogVersion = self::catalogVersion($pdo);
        $channels = ($clientCatalog === $catalogVersion)
            ? null                                   // unchanged -> app keeps its cache
            : self::buildChannels($pdo, $deviceId, $entitled);

        Response::json([
            'status'          => $status,            // trial | premium | free
            'entitled'        => $entitled,
            'expires_at'      => $expiresAt,         // ISO 8601 or null
            'server_time'     => date('c', $now),
            'catalog_version' => $catalogVersion,
            'channels'        => $channels,          // null = unchanged
            'config'          => self::appConfig($settings),
        ]);
    }

    private static function upsertDevice(PDO $pdo, string $deviceId, int $trialSeconds, int $now): array
    {
        $row = self::findDevice($pdo, $deviceId);
        if ($row === null) {
            $trialExpires = date('Y-m-d H:i:s', $now + $trialSeconds);
            $pdo->prepare(
                'INSERT INTO devices (device_id, status, trial_expires_at, last_seen, created_at)
                 VALUES (?, "active", ?, NOW(), NOW())'
            )->execute([$deviceId, $trialExpires]);
            $row = self::findDevice($pdo, $deviceId);
        } else {
            $pdo->prepare('UPDATE devices SET last_seen = NOW() WHERE id = ?')->execute([$row['id']]);
        }
        return $row;
    }

    private static function findDevice(PDO $pdo, string $deviceId): ?array
    {
        $st = $pdo->prepare('SELECT * FROM devices WHERE device_id = ?');
        $st->execute([$deviceId]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** @return array{0:string,1:?string,2:bool} [status, expiresAtIso, entitled] */
    private static function entitlement(array $dev, int $now): array
    {
        if (($dev['status'] ?? 'active') === 'banned') {
            return ['banned', null, false];
        }
        $sub   = !empty($dev['subscription_expires_at']) ? strtotime($dev['subscription_expires_at']) : 0;
        $trial = !empty($dev['trial_expires_at']) ? strtotime($dev['trial_expires_at']) : 0;
        if ($sub > $now) {
            return ['premium', date('c', $sub), true];
        }
        if ($trial > $now) {
            return ['trial', date('c', $trial), true];
        }
        return ['free', null, false];
    }

    private static function buildChannels(PDO $pdo, string $deviceId, bool $entitled): array
    {
        $ttl  = (int) Config::get('token_ttl', 86400);
        $base = rtrim((string) Config::get('proxy_base'), '/');
        $rows = $pdo->query(
            'SELECT * FROM channels WHERE is_enabled = 1 ORDER BY sort_index ASC, id ASC'
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $isFree  = (int) $r['is_free'] === 1;
            $canPlay = $entitled || $isFree;
            $ch = [
                'id'       => (int) $r['id'],
                'name'     => $r['name'],
                'logo_url' => $r['logo_url'],
                'group'    => $r['group_title'],
                'is_free'  => $isFree,
                'locked'   => !$canPlay,
            ];
            if ($canPlay) {
                $ch['stream_type'] = $r['stream_type'];
                $ch['url']         = $base . '/s/' . (int) $r['id'] . '?token=' . Token::issue((int) $r['id'], $deviceId, $ttl);
                $drm = self::drm($r);
                if ($drm !== null) {
                    $ch['drm'] = $drm;
                }
                $headers = self::decodeJson($r['headers'] ?? null);
                if ($headers) {
                    $ch['headers'] = $headers;
                }
            }
            $out[] = $ch;
        }
        return $out;
    }

    private static function drm(array $r): ?array
    {
        $scheme   = $r['drm_scheme'] ?? null;
        $clearKey = $r['drm_clearkey'] ?? null;
        $license  = $r['drm_license_url'] ?? null;
        if (!$scheme && !$clearKey && !$license) {
            return null;
        }
        $d = ['scheme' => $scheme ?: ($clearKey ? 'clearkey' : null)];
        if ($license) {
            $d['license_url'] = $license;
        }
        if ($clearKey) {
            $d['clearkeys'] = $clearKey;   // "kid:key,kid:key"
        }
        $lh = self::decodeJson($r['drm_license_headers'] ?? null);
        if ($lh) {
            $d['license_headers'] = $lh;
        }
        return $d;
    }

    private static function catalogVersion(PDO $pdo): string
    {
        $row = $pdo->query(
            'SELECT COUNT(*) c, COALESCE(UNIX_TIMESTAMP(MAX(updated_at)), 0) m FROM channels WHERE is_enabled = 1'
        )->fetch();
        return $row['c'] . '-' . $row['m'];
    }

    private static function settings(PDO $pdo): array
    {
        $out = [];
        foreach ($pdo->query('SELECT k, v FROM settings') as $row) {
            $out[$row['k']] = $row['v'];
        }
        return $out;
    }

    private static function appConfig(array $settings): array
    {
        return [
            'website_url'     => $settings['website_url'] ?? '',
            'promo_video_url' => $settings['promo_video_url'] ?? '',
            'min_app_version' => $settings['min_app_version'] ?? '1.0.0',
        ];
    }

    private static function decodeJson(?string $s): ?array
    {
        if ($s === null || $s === '') {
            return null;
        }
        $j = json_decode($s, true);
        return (is_array($j) && $j) ? $j : null;
    }
}
