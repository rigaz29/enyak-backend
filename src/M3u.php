<?php
declare(strict_types=1);

namespace Enyak;

/** Minimal M3U parser for the admin import (name, logo, group, url, type, basic DRM). */
final class M3u
{
    /**
     * @return array<int,array{name:string,group:?string,logo:?string,url:string,
     *   stream_type:string,drm_scheme:?string,drm_clearkey:?string}>
     */
    public static function parse(string $raw): array
    {
        $out = [];
        $name = ''; $group = null; $logo = null; $drmScheme = null; $clear = null;

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (stripos($line, '#EXTM3U') === 0) {
                continue;
            }
            if (stripos($line, '#EXTINF') === 0) {
                $name = ''; $group = null; $logo = null; $drmScheme = null; $clear = null;
                $comma = self::unquotedComma($line);
                $attrs = $comma >= 0 ? substr($line, 0, $comma) : $line;
                $name = $comma >= 0 ? trim(substr($line, $comma + 1)) : '';
                if (preg_match('/tvg-logo="([^"]*)"/i', $attrs, $m)) {
                    $logo = $m[1] !== '' ? $m[1] : null;
                }
                if (preg_match('/group-title="([^"]*)"/i', $attrs, $m)) {
                    $group = $m[1] !== '' ? $m[1] : null;
                }
                if ($name === '' && preg_match('/tvg-name="([^"]*)"/i', $attrs, $m)) {
                    $name = $m[1];
                }
            } elseif (stripos($line, '#KODIPROP') === 0 || stripos($line, '#EXTVLCOPT') === 0) {
                $eq = strpos($line, '=');
                $val = $eq !== false ? trim(substr($line, $eq + 1)) : '';
                $key = strtolower($line);
                if (str_contains($key, 'license_type')) {
                    $drmScheme = self::scheme($val);
                } elseif (str_contains($key, 'license_key') && stripos($val, 'http') !== 0) {
                    $clear = $val; // static "kid:key"
                }
            } elseif ($line[0] === '#') {
                continue;
            } else {
                if ($name === '') {
                    $name = $line;
                }
                $out[] = [
                    'name' => $name,
                    'group' => $group,
                    'logo' => $logo,
                    'url' => $line,
                    'stream_type' => self::type($line),
                    'drm_scheme' => $drmScheme ?: ($clear ? 'clearkey' : null),
                    'drm_clearkey' => $clear,
                ];
                $name = ''; $group = null; $logo = null; $drmScheme = null; $clear = null;
            }
        }
        return $out;
    }

    private static function type(string $url): string
    {
        $u = strtolower($url);
        if (str_contains($u, '.mpd')) return 'dash';
        if (str_contains($u, '.m3u8')) return 'hls';
        return 'other';
    }

    private static function scheme(string $v): string
    {
        $v = strtolower($v);
        if (str_contains($v, 'widevine')) return 'widevine';
        if (str_contains($v, 'playready')) return 'playready';
        if (str_contains($v, 'clearkey')) return 'clearkey';
        return $v;
    }

    private static function unquotedComma(string $s): int
    {
        $inQuotes = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($c === '"') $inQuotes = !$inQuotes;
            elseif ($c === ',' && !$inQuotes) return $i;
        }
        return -1;
    }
}
