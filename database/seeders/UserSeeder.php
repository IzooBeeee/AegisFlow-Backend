<?php

namespace Database\Seeders;

use App\Models\RescueMember;
use App\Models\RescueTeam;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Mỗi tài khoản rescue_team đại diện cho 1 đội cứu hộ
     * email → RESCUE-XXX (khớp với DataSeeder)
     */
    public function run(): void
    {
        $users = [
            // ── Admin / Operator ──────────────────────────────
            [
                'name'     => 'Nguyễn Văn Admin',
                'email'    => 'admin@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0901234567',
                'role'     => 'city_admin',
            ],
            [
                'name'     => 'Trần Thị Điều Phối',
                'email'    => 'operator@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0901234568',
                'role'     => 'rescue_operator',
            ],
            [
                'name'     => 'Phạm Thị AI',
                'email'    => 'ai@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0901234570',
                'role'     => 'ai_operator',
            ],

            // ── Công dân ──────────────────────────────────────
            [
                'name'     => 'Ngô Văn Công Dân',
                'email'    => 'citizen@example.com',
                'password' => Hash::make('password'),
                'phone'    => '0901234571',
                'role'     => 'citizen',
            ],

            // ── Đội cứu hộ (mỗi tài khoản = 1 đội) ──────────
            // RESCUE-001: Đội PCCC Liên Chiểu
            [
                'name'     => 'Đội PCCC Liên Chiểu',
                'email'    => 'rescue@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0901234569',
                'role'     => 'rescue_team',
                'team_code'=> 'RESCUE-001',
            ],
            // RESCUE-002: Đội PCCC Cẩm Lệ
            [
                'name'     => 'Đội PCCC Cẩm Lệ',
                'email'    => 'pccc.camle@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0935111002',
                'role'     => 'rescue_team',
                'team_code'=> 'RESCUE-002',
            ],
            // RESCUE-003: Đội Y tế Đà Nẵng
            [
                'name'     => 'Đội Y tế Đà Nẵng',
                'email'    => 'yte.danang@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0935111003',
                'role'     => 'rescue_team',
                'team_code'=> 'RESCUE-003',
            ],
            // RESCUE-004: Đội Quân đội Hòa Vang
            [
                'name'     => 'Đội Quân đội Hòa Vang',
                'email'    => 'quandoi.hoavang@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0935111004',
                'role'     => 'rescue_team',
                'team_code'=> 'RESCUE-004',
            ],
            // RESCUE-005: Đội Tình nguyện Thanh Khê
            [
                'name'     => 'Đội Tình nguyện Thanh Khê',
                'email'    => 'tinhnguyen.thankkhe@aegisflow.ai',
                'password' => Hash::make('password'),
                'phone'    => '0935111005',
                'role'     => 'rescue_team',
                'team_code'=> 'RESCUE-005',
            ],
        ];

        foreach ($users as $userData) {
            $role     = $userData['role'];
            $teamCode = $userData['team_code'] ?? null;
            unset($userData['role'], $userData['team_code']);

            $userData['is_active']          = true;
            $userData['email_verified_at']  = now();

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $roleModel = Role::where('name', $role)->first();
            if ($roleModel) {
                $user->assignRole($roleModel);
            }

            // Liên kết tài khoản rescue_team với đội cứu hộ tương ứng
            if ($teamCode) {
                $team = RescueTeam::where('code', $teamCode)->first();
                if ($team) {
                    RescueMember::firstOrCreate(
                        ['user_id' => $user->id, 'team_id' => $team->id],
                        ['role' => 'leader', 'status' => 'active', 'is_available' => true]
                    );
                }
            }
        }

        $this->command->info('✅ UserSeeder: Đã tạo ' . count($users) . ' users (5 đội cứu hộ + admin + citizen)');
    }
}
