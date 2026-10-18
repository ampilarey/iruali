<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * /api/health/ready: the database answers, the cache keeps a value, storage takes a file. For an
 * uptime monitor; the full checklist is `php artisan iruali:ready`.
 */
class HealthReady
{
    /**
     * @return array{database: bool, cache: bool, storage: bool}
     */
    public static function run(): array
    {
        return [
            'database' => self::try(fn () => DB::select('select 1') !== []),
            'cache' => self::try(function () {
                Cache::put('health:ready', $value = (string) random_int(1, PHP_INT_MAX), 60);

                return Cache::get('health:ready') === $value;
            }),
            'storage' => self::try(fn () => Storage::disk('local')->put('health-ready.txt', (string) time()) !== false),
        ];
    }

    protected static function try(\Closure $check): bool
    {
        try {
            return (bool) $check();
        } catch (Throwable) {
            return false;
        }
    }
}
