<?php
declare(strict_types=1);

namespace Enyak;

/**
 * Signed stream tokens. Format: base64url("channelId:deviceId:exp") . "." . base64url(hmac).
 * Stateless: the /s endpoint verifies the signature + expiry without a DB hit.
 * deviceId is restricted to [A-Za-z0-9_-] at issue time so ':' stays a safe separator.
 */
final class Token
{
    public static function issue(int $channelId, string $deviceId, int $ttl): string
    {
        $exp  = time() + $ttl;
        $data = $channelId . ':' . $deviceId . ':' . $exp;
        return self::b64($data) . '.' . self::b64(self::sig($data));
    }

    /** @return array{channelId:int,deviceId:string,exp:int}|null */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        $data = self::unb64($parts[0]);
        $sig  = self::unb64($parts[1]);
        if ($data === false || $sig === false) {
            return null;
        }
        if (!hash_equals(self::sig($data), $sig)) {
            return null;
        }
        $seg = explode(':', $data);
        if (count($seg) !== 3) {
            return null;
        }
        [$cid, $did, $exp] = $seg;
        if ((int) $exp < time()) {
            return null;
        }
        return ['channelId' => (int) $cid, 'deviceId' => $did, 'exp' => (int) $exp];
    }

    private static function sig(string $data): string
    {
        return hash_hmac('sha256', $data, (string) Config::get('token_secret'), true);
    }

    private static function b64(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** @return string|false */
    private static function unb64(string $s)
    {
        return base64_decode(strtr($s, '-_', '+/'), true);
    }
}
