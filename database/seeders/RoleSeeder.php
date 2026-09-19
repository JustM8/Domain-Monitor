<?php

namespace Database\Seeders;

use App\Modules\Shared\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'admin', 'label' => 'Адмін', 'sort_order' => 1],
            ['name' => 'pm', 'label' => 'PM', 'sort_order' => 2],
            ['name' => 'developer', 'label' => 'Розробник', 'sort_order' => 3],
            ['name' => 'manager', 'label' => 'Менеджер', 'sort_order' => 4],
        ] as $role) {
            Role::query()->updateOrCreate(['name' => $role['name']], $role);
        }
    }
}
