<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Nguyễn Văn Admin',
                'email' => 'admin@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone' => '0901234567',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'city_admin',
            ],
            [
                'name' => 'Trần Thị Điều Phối',
                'email' => 'operator@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone' => '0901234568',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'rescue_operator',
            ],
            [
                'name' => 'Lê Văn Cứu Hộ',
                'email' => 'rescue@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone' => '0901234569',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'rescue_team',
            ],
            [
                'name' => 'Phạm Thị AI',
                'email' => 'ai@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone' => '0901234570',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'ai_operator',
            ],
            [
                'name' => 'Ngô Văn Công Dân',
                'email' => 'citizen@example.com',
                'password' => Hash::make('password'),
                'phone' => '0901234571',
                'is_active' => true,
                'email_verified_at' => now(),
                'role' => 'citizen',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            $user = \App\Models\User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $roleModel = \Spatie\Permission\Models\Role::where('name', $role)->first();
            if ($roleModel) {
                $user->assignRole($roleModel);
            }
        }

        $this->command->info('✅ UserSeeder: Đã tạo '.count($users).' users');
    }
}
