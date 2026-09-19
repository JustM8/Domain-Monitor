<?php

namespace App\Modules\Site\Services;

use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SiteMetadataService
{
    public function update(Site $site, array $data): Site
    {
        return DB::transaction(function () use ($site, $data) {
            $record = Site::query()->lockForUpdate()->findOrFail($site->id);
            $record->fill($data);
            if ($record->isDirty('url')) {
                // A changed target must be explicitly connected with its own new key.
                $record->forceFill([
                    'remote_control_enabled' => false,
                    'api_token' => Crypt::encryptString(Str::random(60)),
                    'confirmed_state' => null,
                    'confirmed_control_version' => null,
                ]);
            }
            if ($record->isDirty(['url', 'display_mode', 'embed_origins', 'remote_control_enabled'])) {
                $record->control_version++;
            }
            $record->save();

            return $record;
        });
    }
}
