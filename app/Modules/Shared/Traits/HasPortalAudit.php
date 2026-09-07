<?php

namespace App\Modules\Shared\Traits;

use Illuminate\Database\Eloquent\Model;

trait HasPortalAudit
{
    public static function bootHasPortalAudit(): void
    {
        static::creating(function (Model $model): void {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }

            if (auth()->check() && empty($model->updated_by)) {
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function (Model $model): void {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });

        static::deleting(function (Model $model): void {
            if (! $model->isForceDeleting() && auth()->check()) {
                $model->deleted_by = auth()->id();
                $model->saveQuietly();
            }
        });
    }
}
