<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Spatie Permission — Cấu hình mô hình
    |--------------------------------------------------------------------------
    */

    'models' => [

        'permission' => Spatie\Permission\Models\Permission::class,

        'role' => Spatie\Permission\Models\Role::class,

    ],

    /*
    |--------------------------------------------------------------------------
    | Bảng trong CSDL
    |--------------------------------------------------------------------------
    */

    'table_names' => [

        'roles' => 'roles',

        'permissions' => 'permissions',

        'model_has_permissions' => 'model_has_permissions',

        'model_has_roles' => 'model_has_roles',

        'role_has_permissions' => 'role_has_permissions',

    ],

    /*
    |--------------------------------------------------------------------------
    | Column Names
    |--------------------------------------------------------------------------
    */

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_type' => 'model_type',
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tính năng Teams
    |--------------------------------------------------------------------------
    */

    'teams' => false,

    /*
    |--------------------------------------------------------------------------
    | Sử dụng direct permissions
    |--------------------------------------------------------------------------
    */

    'direct_permissions' => false,

    /*
    |--------------------------------------------------------------------------
    | Cấu hình team
    |--------------------------------------------------------------------------
    */

    'teams_namespace' => null,

    /*
    |--------------------------------------------------------------------------
    | Cache — Xóa cache khi thay đổi
    |--------------------------------------------------------------------------
    */

    'cache' => [

        'expiration_time' => DateInterval::createFromDateString('24 hours'),

        'key' => 'spatie.permission.cache',

        'store' => env('CACHE_STORE', 'database'),

    ],

];
