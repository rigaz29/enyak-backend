<?php
declare(strict_types=1);

namespace Enyak\Controllers;

use Enyak\Db;
use Enyak\Response;

/** GET /v1/config — app-facing remote config (editable in the `settings` table). */
final class ConfigController
{
    public function handle(): void
    {
        $settings = [];
        foreach (Db::conn()->query('SELECT k, v FROM settings') as $row) {
            $settings[$row['k']] = $row['v'];
        }
        Response::json([
            'website_url'         => $settings['website_url'] ?? '',
            'promo_video_url'     => $settings['promo_video_url'] ?? '',
            'min_app_version'     => $settings['min_app_version'] ?? '1.0.0',
            'update_url'          => $settings['update_url'] ?? '',
            'maintenance'         => ($settings['maintenance_enabled'] ?? '0') === '1',
            'maintenance_message' => $settings['maintenance_message'] ?? '',
            'announcement'        => $settings['announcement'] ?? '',
        ]);
    }
}
