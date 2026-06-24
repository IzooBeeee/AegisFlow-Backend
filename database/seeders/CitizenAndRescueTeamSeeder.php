<?php

namespace Database\Seeders;

use App\Models\RescueTeam;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CitizenAndRescueTeamSeeder extends Seeder
{
    public function run(): void
    {
        $citizenRole = Role::where('name', 'citizen')->first();
        $rescueTeamRole = Role::where('name', 'rescue_team')->first();

        // 1. Dữ liệu tĩnh Công dân
        $citizens = [
            ['name' => 'Nguyễn Đình Hải', 'email' => 'hai.nd@example.com', 'phone' => '0905111222'],
            ['name' => 'Trần Thị Thu Thủy', 'email' => 'thuy.ttt@example.com', 'phone' => '0905111333'],
            ['name' => 'Lê Văn Hoàng', 'email' => 'hoang.lv@example.com', 'phone' => '0905111444'],
            ['name' => 'Phạm Phương Thảo', 'email' => 'thao.pp@example.com', 'phone' => '0905111555'],
            ['name' => 'Võ Trọng Nghĩa', 'email' => 'nghia.vt@example.com', 'phone' => '0905111666'],
            ['name' => 'Đinh Công Trứ', 'email' => 'tru.dc@example.com', 'phone' => '0905111777'],
            ['name' => 'Hoàng Tôn Quyền', 'email' => 'quyen.ht@example.com', 'phone' => '0905111888'],
            ['name' => 'Lý Thu Nguyệt', 'email' => 'nguyet.lt@example.com', 'phone' => '0905111999'],
            ['name' => 'Hồ Vĩnh Khoa', 'email' => 'khoa.hv@example.com', 'phone' => '0905111000'],
            ['name' => 'Ngô Bảo Châu', 'email' => 'chau.nb@example.com', 'phone' => '0905222111'],
        ];

        $this->command->info('Đang tạo '.count($citizens).' Công dân (dữ liệu tĩnh)...');
        foreach ($citizens as $c) {
            $user = User::firstOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            if ($citizenRole) {
                $user->assignRole($citizenRole);
            }
        }

        // 2. Dữ liệu tĩnh Đội cứu hộ
        $rescueTeams = [
            [
                'code' => 'RESCUE-010',
                'name' => 'Đội cứu hộ PCCC Hải Châu',
                'team_type' => 'fire',
                'district_id' => 4, // Hải Châu
                'specializations' => ['flood_rescue', 'water_pump'],
                'personnel_count' => 30,
                'vehicle_count' => 5,
                'email' => 'team_haichau@aegisflow.ai',
                'phone' => '0935111222',
            ],
            [
                'code' => 'RESCUE-011',
                'name' => 'Đội Y tế lưu động Thanh Khê',
                'team_type' => 'medical',
                'district_id' => 5, // Thanh Khê
                'specializations' => ['first_aid', 'medical_evacuation'],
                'personnel_count' => 15,
                'vehicle_count' => 3,
                'email' => 'team_thanhkhe@aegisflow.ai',
                'phone' => '0935111333',
            ],
            [
                'code' => 'RESCUE-012',
                'name' => 'Lực lượng Quân đội Quân khu 5',
                'team_type' => 'military',
                'district_id' => 4,
                'specializations' => ['flood_rescue', 'evacuation', 'logistics'],
                'personnel_count' => 100,
                'vehicle_count' => 15,
                'email' => 'team_qk5@aegisflow.ai',
                'phone' => '0935111444',
            ],
            [
                'code' => 'RESCUE-013',
                'name' => 'Đội Thanh niên xung kích Liên Chiểu',
                'team_type' => 'volunteer',
                'district_id' => 1, // Liên Chiểu
                'specializations' => ['food_distribution', 'evacuation'],
                'personnel_count' => 40,
                'vehicle_count' => 2,
                'email' => 'team_lienchieu@aegisflow.ai',
                'phone' => '0935111555',
            ],
            [
                'code' => 'RESCUE-014',
                'name' => 'Đội cứu hộ PCCC Ngũ Hành Sơn',
                'team_type' => 'fire',
                'district_id' => 7, // Ngũ Hành Sơn
                'specializations' => ['flood_rescue', 'first_aid'],
                'personnel_count' => 20,
                'vehicle_count' => 4,
                'email' => 'team_nguhanhson@aegisflow.ai',
                'phone' => '0935111666',
            ],
            [
                'code' => 'RESCUE-015',
                'name' => 'Đội Y tế Bệnh viện Sơn Trà',
                'team_type' => 'medical',
                'district_id' => 6, // Sơn Trà
                'specializations' => ['first_aid', 'triage'],
                'personnel_count' => 12,
                'vehicle_count' => 2,
                'email' => 'team_sontra@aegisflow.ai',
                'phone' => '0935111777',
            ],
            [
                'code' => 'RESCUE-016',
                'name' => 'Đội xung kích phòng chống bão lụt Hòa Vang',
                'team_type' => 'volunteer',
                'district_id' => 3, // Hòa Vang
                'specializations' => ['flood_rescue', 'logistics', 'food_distribution'],
                'personnel_count' => 50,
                'vehicle_count' => 8,
                'email' => 'team_hoavang@aegisflow.ai',
                'phone' => '0935111888',
            ],
            [
                'code' => 'RESCUE-017',
                'name' => 'Đội cứu hộ PCCC Cẩm Lệ (Tăng cường)',
                'team_type' => 'fire',
                'district_id' => 2, // Cẩm Lệ
                'specializations' => ['water_pump', 'flood_rescue'],
                'personnel_count' => 25,
                'vehicle_count' => 4,
                'email' => 'team_camle@aegisflow.ai',
                'phone' => '0935111999',
            ],
        ];

        $this->command->info('Đang tạo '.count($rescueTeams).' Đội cứu hộ (dữ liệu tĩnh)...');
        foreach ($rescueTeams as $t) {
            RescueTeam::firstOrCreate(
                ['code' => $t['code']],
                [
                    'name' => $t['name'],
                    'team_type' => $t['team_type'],
                    'district_id' => $t['district_id'],
                    'specializations' => $t['specializations'],
                    'personnel_count' => $t['personnel_count'],
                    'vehicle_count' => $t['vehicle_count'],
                    'status' => 'available',
                ]
            );

            $user = User::firstOrCreate(
                ['email' => $t['email']],
                [
                    'name' => $t['name'],
                    'phone' => $t['phone'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            if ($rescueTeamRole) {
                $user->assignRole($rescueTeamRole);
            }

            // Tạo liên kết thành viên đội cứu hộ (RescueMember)
            $team = RescueTeam::where('code', $t['code'])->first();
            if ($team) {
                \App\Models\RescueMember::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'team_id' => $team->id,
                    ],
                    [
                        'role' => 'leader',
                        'status' => 'active',
                        'is_available' => true,
                    ]
                );
            }
        }

        $this->command->info('✅ Đã tạo xong dữ liệu tĩnh cho Công dân và Đội cứu hộ!');
    }
}
