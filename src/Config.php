<?php
declare(strict_types=1);

namespace Enyak;

final class Config
{
    private static array $data = [];

    public static function load(string $path): void
    {
        self::$data = require $path;
    }

    /** @return mixed */
    public static function get(string $key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }
}
