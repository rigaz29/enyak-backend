<?php
declare(strict_types=1);

namespace Enyak\Controllers;

use Enyak\Db;
use Enyak\Response;
use Enyak\Token;

/**
 * GET /s/{channelId}?token=...  Validates the signed token, then 302-redirects to the
 * real upstream stream URL. The real URL is never exposed in the app/playlist.
 */
final class StreamController
{
    public function handle(int $channelId): void
    {
        $claims = Token::verify((string) ($_GET['token'] ?? ''));
        if ($claims === null || $claims['channelId'] !== $channelId) {
            Response::error('Invalid or expired token', 403);
        }

        $pdo = Db::conn();

        // Instant-revoke check: a banned device cannot stream even with a valid token.
        $st = $pdo->prepare('SELECT status FROM devices WHERE device_id = ?');
        $st->execute([$claims['deviceId']]);
        $dev = $st->fetch();
        if ($dev && $dev['status'] === 'banned') {
            Response::error('Device banned', 403);
        }

        $st = $pdo->prepare('SELECT stream_url, is_enabled FROM channels WHERE id = ?');
        $st->execute([$channelId]);
        $ch = $st->fetch();
        if (!$ch || (int) $ch['is_enabled'] !== 1) {
            Response::error('Channel not found', 404);
        }

        header('Location: ' . $ch['stream_url'], true, 302);
        exit;
    }
}
