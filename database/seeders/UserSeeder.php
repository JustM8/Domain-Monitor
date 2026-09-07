<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Modules\Shared\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->first();

        User::updateOrCreate(
            ['email' => 'admin@admin.com'],
            [
            'name' => 'Admin',
            'full_name' => 'Admin Admin',
            'email' => 'admin@admin.com',
            'password' => Hash::make('12345678'),
            'role_id' => $adminRole?->id,
            'is_active' => true,
            ]
        );
    }
}
