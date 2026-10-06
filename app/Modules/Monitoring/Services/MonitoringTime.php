<?php

namespace App\Modules\Monitoring\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class MonitoringTime
{
    public const CALENDAR = 'Europe/Kyiv';

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    public static function parse(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'UTC')->utc();
    }

    public static function store(\DateTimeInterface $value): string
    {
        return CarbonImmutable::instance($value)->utc()->format('Y-m-d H:i:s.u');
    }

    public static function display(?string $value, string $zone = self::CALENDAR): string
    {
        return $value ? self::parse($value)->setTimezone($zone)->format('d.m.Y H:i:s') : '—';
    }

    public static function zone(int $siteId): string
    {
        return DB::table('monitoring_site_preferences')->where('site_id', $siteId)->value('display_timezone')
            ?? DB::table('monitoring_company_preferences')->where('company_id', DB::table('sites')->where('id', $siteId)->value('company_id'))->value('display_timezone')
            ?? self::CALENDAR;
    }

    public static function session(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("SET time_zone = '+00:00'");
        }
    }
}
