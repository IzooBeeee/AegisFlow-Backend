<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels — AegisFlow AI
|--------------------------------------------------------------------------
| Định nghĩa các kênh WebSocket và quy tắc xác thực
*/

// Kênh cá nhân (Laravel default)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// ── Kênh riêng tư: Thông báo cho từng user ──
Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// ── Kênh riêng tư: Dispatch cho đội cứu hộ ──
Broadcast::channel('team.{teamId}.dispatch', function ($user, $teamId) {
    // Kiểm tra user có phải thành viên đội này không
    return $user->rescueTeams()
        ->where('rescue_teams.id', $teamId)
        ->exists();
});

// ── Kênh công khai: Cảnh báo lũ lụt ──
Broadcast::channel('flood', function ($user) {
    // Tất cả users (kể cả unauthenticated) có thể lắng nghe alerts
    // Vì alerts là thông tin công cộng cho công dân
    return true;
});

// ── Kênh presence: Operators online ──
Broadcast::channel('operators.online', function ($user) {
    if ($user->hasAnyRole(['city_admin', 'rescue_operator'])) {
        return [
            'id' => $user->id,
            'name' => $user->name,
        ];
    }
    return false;
});
