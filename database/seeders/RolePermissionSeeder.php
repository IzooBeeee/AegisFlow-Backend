<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Tạo vai trò và quyền hạn mặc định
     */
    public function run(): void
    {
        // ============================================================
        // PERMISSIONS
        // ============================================================
        $permissions = [
            // Dashboard
            ['name' => 'Xem dashboard', 'slug' => 'dashboard.view', 'group' => 'dashboard'],
            // Flood
            ['name' => 'Xem vùng ngập', 'slug' => 'flood.zones.view', 'group' => 'flood'],
            ['name' => 'Quản lý vùng ngập', 'slug' => 'flood.zones.manage', 'group' => 'flood'],
            ['name' => 'Xem cảm biến', 'slug' => 'flood.sensors.view', 'group' => 'flood'],
            ['name' => 'Quản lý cảm biến', 'slug' => 'flood.sensors.manage', 'group' => 'flood'],
            ['name' => 'Xem dự báo', 'slug' => 'flood.predictions.view', 'group' => 'flood'],
            ['name' => 'Xác nhận dự báo', 'slug' => 'flood.predictions.verify', 'group' => 'flood'],
            // Incidents
            ['name' => 'Xem sự cố', 'slug' => 'incidents.view', 'group' => 'incident'],
            ['name' => 'Tạo sự cố', 'slug' => 'incidents.create', 'group' => 'incident'],
            ['name' => 'Cập nhật sự cố', 'slug' => 'incidents.update', 'group' => 'incident'],
            // Rescue
            ['name' => 'Xem yêu cầu cứu hộ', 'slug' => 'rescue.requests.view', 'group' => 'rescue'],
            ['name' => 'Tạo yêu cầu cứu hộ', 'slug' => 'rescue.requests.create', 'group' => 'rescue'],
            ['name' => 'Quản lý yêu cầu cứu hộ', 'slug' => 'rescue.requests.manage', 'group' => 'rescue'],
            ['name' => 'Xem đội cứu hộ', 'slug' => 'rescue.teams.view', 'group' => 'rescue'],
            ['name' => 'Quản lý đội cứu hộ', 'slug' => 'rescue.teams.manage', 'group' => 'rescue'],
            ['name' => 'Xem điểm trú ẩn', 'slug' => 'rescue.shelters.view', 'group' => 'rescue'],
            ['name' => 'Quản lý trú ẩn', 'slug' => 'rescue.shelters.manage', 'group' => 'rescue'],
            // Alerts
            ['name' => 'Xem cảnh báo', 'slug' => 'alerts.view', 'group' => 'alert'],
            ['name' => 'Tạo cảnh báo', 'slug' => 'alerts.create', 'group' => 'alert'],
            ['name' => 'Quản lý cảnh báo', 'slug' => 'alerts.manage', 'group' => 'alert'],
            // Map
            ['name' => 'Xem bản đồ', 'slug' => 'map.view', 'group' => 'map'],
            ['name' => 'Quản lý bản đồ', 'slug' => 'map.manage', 'group' => 'map'],
            // Users
            ['name' => 'Quản lý người dùng', 'slug' => 'users.manage', 'group' => 'system'],
            // Analytics
            ['name' => 'Xem báo cáo', 'slug' => 'analytics.view', 'group' => 'analytics'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['slug'], 'guard_name' => 'sanctum'],
                ['display_name' => $perm['name'], 'group_name' => $perm['group']]
            );
        }

        // ============================================================
        // ROLES
        // ============================================================
        $roles = [
            ['name' => 'Quản trị viên', 'slug' => 'city_admin'],
            ['name' => 'Điều phối viên', 'slug' => 'rescue_operator'],
            ['name' => 'Đội cứu hộ', 'slug' => 'rescue_team'],
            ['name' => 'Công dân', 'slug' => 'citizen'],
            ['name' => 'Điều hành AI', 'slug' => 'ai_operator'],
        ];

        foreach ($roles as $roleData) {
            Role::firstOrCreate(
                ['name' => $roleData['slug'], 'guard_name' => 'sanctum'],
                ['display_name' => $roleData['name']]
            );
        }

        // ============================================================
        // GÁN PERMISSIONS CHO ROLES
        // ============================================================

        $cityAdmin = Role::where('name', 'city_admin')->first();
        $cityAdmin->syncPermissions(Permission::all());

        // rescue_operator: điều phối
        $rescueOp = Role::where('name', 'rescue_operator')->first();
        $rescueOp->syncPermissions([
            'dashboard.view',
            'flood.zones.view', 'flood.sensors.view', 'flood.predictions.view', 'flood.predictions.verify',
            'incidents.view', 'incidents.create', 'incidents.update',
            'rescue.requests.view', 'rescue.requests.create', 'rescue.requests.manage',
            'rescue.teams.view',
            'rescue.shelters.view',
            'alerts.view', 'alerts.create', 'alerts.manage',
            'map.view',
            'analytics.view',
        ]);

        // rescue_team: thực địa
        $rescueTeam = Role::where('name', 'rescue_team')->first();
        $rescueTeam->syncPermissions([
            'dashboard.view',
            'flood.zones.view', 'flood.sensors.view',
            'incidents.view',
            'rescue.requests.view', 'rescue.requests.create',
            'rescue.teams.view',
            'map.view',
            'alerts.view',
        ]);

        // ai_operator: vận hành AI
        $aiOp = Role::where('name', 'ai_operator')->first();
        $aiOp->syncPermissions([
            'dashboard.view',
            'flood.predictions.view', 'flood.predictions.verify',
            'analytics.view',
        ]);

        // citizen: công dân
        $citizen = Role::where('name', 'citizen')->first();
        $citizen->syncPermissions([
            'flood.zones.view',
            'incidents.view', 'incidents.create',
            'rescue.requests.view', 'rescue.requests.create',
            'alerts.view',
            'map.view',
        ]);

        $this->command->info('✅ RolePermissionSeeder: Đã tạo '.Role::count().' vai trò, '.Permission::count().' quyền');
    }
}
