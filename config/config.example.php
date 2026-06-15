<?php
// Copy this file to config/config.php and fill in real values.
// config.php is gitignored — it holds secrets, never commit it.
return [
    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=enyak;charset=utf8mb4',
        'user' => 'enyak_user',
        'pass' => 'CHANGE_ME',
    ],

    // Secret for signing stream tokens (HMAC). Use a long random string, e.g.
    // PHP:  bin2hex(random_bytes(32))
    'token_secret' => 'CHANGE_ME_LONG_RANDOM_SECRET',

    // Public base URL of THIS API, used to build proxy stream URLs. No trailing slash.
    'proxy_base'   => 'https://api.enyak.my.id',

    // How long an issued stream token stays valid (seconds). Long enough for a live
    // session; the proxy endpoint is effectively hit only once per channel open.
    'token_ttl'    => 24 * 60 * 60,
];
