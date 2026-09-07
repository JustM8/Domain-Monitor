<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    public const DEFAULT_STATUSES = [
        ['code' => 'can_delete', 'sort_order' => 1, 'name' => 'portal.status_default_can_delete', 'color' => '#0f7a4a'],
        ['code' => 'prod', 'sort_order' => 2, 'name' => 'portal.status_default_prod', 'color' => '#0b5ed7'],
        ['code' => 'in_progress', 'sort_order' => 3, 'name' => 'portal.status_default_in_progress', 'color' => '#f3c742'],
        ['code' => 'blocked', 'sort_order' => 4, 'name' => 'portal.status_default_blocked', 'color' => '#c1121f'],
        ['code' => 'backup', 'sort_order' => 5, 'name' => 'portal.status_default_backup', 'color' => '#6f42c1'],
        ['code' => 'partner', 'sort_order' => 6, 'name' => 'portal.status_default_partner', 'color' => '#d7dde5'],
    ];

    protected $fillable = [
        'code',
        'name',
        'color',
        'sort_order',
        'is_archived',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
    ];

    public static function defaultCatalog(): array
    {
        return array_map(function (array $status) {
            $status['name'] = __($status['name']);

            return $status;
        }, self::DEFAULT_STATUSES);
    }
}
