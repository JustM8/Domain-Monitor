<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Shared\Models\Role;
use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Crypt;

trait CreatesPortalRecords
{
    protected function portalUser(string $role = 'admin', string $state = 'active', array $attributes = []): User
    {
        $record = Role::firstOrCreate(['name' => $role], ['label' => $role, 'sort_order' => 1]);

        return User::factory()->create($attributes + ['role_id' => $record->id, 'is_active' => $state === 'active', 'approval_status' => $state]);
    }

    protected function site(array $attributes = []): Site
    {
        return Site::create($attributes + ['name' => 'Test site', 'url' => 'https://example.com', 'site_type' => 'site', 'environment' => 'prod', 'is_active' => true, 'api_token' => Crypt::encryptString('site-test-token')]);
    }
}
