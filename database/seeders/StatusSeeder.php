<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Status::defaultCatalog() as $status) {
            Status::query()->updateOrCreate(
                ['code' => $status['code']],
                $status + ['is_archived' => false]
            );
        }
    }
}
