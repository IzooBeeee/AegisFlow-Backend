<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ApiResponse;
use App\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * FcmTokenController — Quản lý FCM tokens của người dùng
 *
 * Endpoints:
 * - POST /api/fcm/register    - Đăng ký device token
 * - GET  /api/fcm/devices     - Danh sách devices của user
 * - DELETE /api/fcm/devices/{id} - Xóa device
 * - PUT  /api/fcm/devices/{id}    - Cập nhật device
 * - DELETE /api/fcm/token/{token} - Xóa theo token
 */
class FcmTokenController extends Controller
{
    /**
     * Đăng ký device token mới
     * POST /api/fcm/register
     *
     * Body: {
     *   "fcm_token": "xxx",
     *   "device_type": "ios|android|web",
     *   "device_name": "iPhone 15 Pro",
     *   "device_model": "iPhone15,2",
     *   "os_version": "17.0"
     * }
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'fcm_token' => 'required|string|min:1|max:500',
            'device_type' => 'required|in:ios,android,web',
            'device_name' => 'nullable|string|max:100',
            'device_model' => 'nullable|string|max:100',
            'os_version' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        $fcmToken = $data['fcm_token'];

        // Tìm device đã tồn tại với cùng token
        $existingDevice = UserDevice::where('fcm_token', $fcmToken)->first();

        if ($existingDevice) {
            // Token đã tồn tại cho user khác -> chuyển sang user hiện tại
            if ($existingDevice->user_id !== $user->id) {
                $existingDevice->update([
                    'user_id' => $user->id,
                    'device_type' => $data['device_type'],
                    'device_name' => $data['device_name'] ?? null,
                    'device_model' => $data['device_model'] ?? null,
                    'os_version' => $data['os_version'] ?? null,
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);

                return ApiResponse::success([
                    'device_id' => $existingDevice->id,
                    'is_new' => false,
                ], 'Device token đã được cập nhật cho user mới');
            }

            // Token cùng user -> chỉ update metadata
            $existingDevice->update([
                'device_type' => $data['device_type'],
                'device_name' => $data['device_name'] ?? null,
                'device_model' => $data['device_model'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'last_used_at' => now(),
            ]);

            return ApiResponse::success([
                'device_id' => $existingDevice->id,
                'is_new' => false,
            ], 'Device token đã được cập nhật');
        }

        // Tạo device mới
        // Deactive các devices cùng loại để chỉ 1 device active mỗi loại
        $this->deactivateOtherDevicesOfType($user->id, $data['device_type']);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'fcm_token' => $fcmToken,
            'device_type' => $data['device_type'],
            'device_name' => $data['device_name'] ?? null,
            'device_model' => $data['device_model'] ?? null,
            'os_version' => $data['os_version'] ?? null,
            'is_active' => true,
            'notification_enabled' => true,
            'notification_settings' => UserDevice::defaultNotificationSettings(),
            'last_used_at' => now(),
        ]);

        return ApiResponse::created([
            'device_id' => $device->id,
            'is_new' => true,
        ], 'Device token đã được đăng ký');
    }

    /**
     * Lấy danh sách devices của user
     * GET /api/fcm/devices
     */
    public function listDevices(Request $request)
    {
        $user = $request->user();

        $devices = UserDevice::where('user_id', $user->id)
            ->orderBy('last_used_at', 'desc')
            ->get()
            ->map(fn($device) => $this->formatDevice($device));

        return ApiResponse::success([
            'devices' => $devices,
            'total' => $devices->count(),
        ]);
    }

    /**
     * Xóa device
     * DELETE /api/fcm/devices/{id}
     */
    public function deleteDevice(Request $request, int $id)
    {
        $user = $request->user();

        $device = UserDevice::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $device) {
            return ApiResponse::notFound('Không tìm thấy thiết bị');
        }

        $device->delete();

        return ApiResponse::success(null, 'Thiết bị đã được xóa');
    }

    /**
     * Cập nhật device settings
     * PUT /api/fcm/devices/{id}
     */
    public function updateDevice(Request $request, int $id)
    {
        $data = $request->validate([
            'notification_enabled' => 'nullable|boolean',
            'notification_settings' => 'nullable|array',
            'device_name' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        $user = $request->user();

        $device = UserDevice::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $device) {
            return ApiResponse::notFound('Không tìm thấy thiết bị');
        }

        // Validate notification settings nếu có
        if (isset($data['notification_settings'])) {
            $data['notification_settings'] = array_merge(
                $device->notification_settings ?? UserDevice::defaultNotificationSettings(),
                $data['notification_settings']
            );
        }

        $device->update(array_filter($data, fn($v) => $v !== null));

        return ApiResponse::success([
            'device' => $this->formatDevice($device->fresh()),
        ], 'Cập nhật thiết bị thành công');
    }

    /**
     * Xóa device bằng FCM token
     * DELETE /api/fcm/token/{token}
     */
    public function deleteByToken(Request $request, string $token)
    {
        $user = $request->user();

        // Giải mã URL token
        $decodedToken = urldecode($token);

        $device = UserDevice::where('fcm_token', $decodedToken)
            ->where('user_id', $user->id)
            ->first();

        if (! $device) {
            return ApiResponse::notFound('Không tìm thấy thiết bị với token này');
        }

        $device->delete();

        return ApiResponse::success(null, 'Thiết bị đã được xóa');
    }

    /**
     * Refresh token - thay thế token cũ bằng token mới
     * POST /api/fcm/refresh
     */
    public function refreshToken(Request $request)
    {
        $data = $request->validate([
            'old_token' => 'required|string',
            'new_token' => 'required|string|min:1|max:500',
        ]);

        $user = $request->user();

        // Tìm device với old token
        $device = UserDevice::where('fcm_token', $data['old_token'])
            ->where('user_id', $user->id)
            ->first();

        if (! $device) {
            // Token không thuộc user này -> thử đăng ký mới
            return $this->register($request);
        }

        // Kiểm tra new token đã tồn tại chưa
        $existingWithNewToken = UserDevice::where('fcm_token', $data['new_token'])->first();

        if ($existingWithNewToken) {
            if ($existingWithNewToken->user_id !== $user->id) {
                // Token thuộc user khác -> không cho phép
                return ApiResponse::error('Token mới đã được sử dụng bởi thiết bị khác', 409);
            }
            // Cùng user -> merge
            $existingWithNewToken->delete();
        }

        // Update token
        $device->fcm_token = $data['new_token'];
        $device->last_used_at = now();
        $device->save();

        return ApiResponse::success([
            'device_id' => $device->id,
        ], 'Token đã được cập nhật');
    }

    /**
     * Đăng ký nhận notification theo topic
     * POST /api/fcm/subscribe
     */
    public function subscribeTopic(Request $request)
    {
        $data = $request->validate([
            'topic' => 'required|string|in:flood_warnings,emergency_alerts,all_users',
        ]);

        $user = $request->user();

        // Lấy tất cả active devices
        $tokens = UserDevice::getActiveTokensForUser($user->id);

        if (empty($tokens)) {
            return ApiResponse::error('Không có thiết bị nào để đăng ký topic');
        }

        // Subscribe via FCM
        $fcm = app(\App\Services\FcmPushService::class);
        $success = $fcm->subscribeToTopic($tokens, $data['topic']);

        if ($success) {
            return ApiResponse::success([
                'topic' => $data['topic'],
                'devices_count' => count($tokens),
            ], 'Đã đăng ký nhận thông báo topic');
        }

        return ApiResponse::error('Không thể đăng ký topic', 500);
    }

    /**
     * Hủy đăng ký topic
     * POST /api/fcm/unsubscribe
     */
    public function unsubscribeTopic(Request $request)
    {
        $data = $request->validate([
            'topic' => 'required|string|in:flood_warnings,emergency_alerts,all_users',
        ]);

        $user = $request->user();

        $tokens = UserDevice::getActiveTokensForUser($user->id);

        if (empty($tokens)) {
            return ApiResponse::success(null, 'Không có thiết bị nào để hủy đăng ký');
        }

        $fcm = app(\App\Services\FcmPushService::class);
        $success = $fcm->unsubscribeFromTopic($tokens, $data['topic']);

        if ($success) {
            return ApiResponse::success([
                'topic' => $data['topic'],
                'devices_count' => count($tokens),
            ], 'Đã hủy đăng ký topic');
        }

        return ApiResponse::error('Không thể hủy đăng ký topic', 500);
    }

    /**
     * Deactive tất cả devices của 1 loại
     */
    protected function deactivateOtherDevicesOfType(int $userId, string $deviceType): void
    {
        // Chỉ giữ 1 device active mỗi loại (để tránh duplicate notifications)
        UserDevice::where('user_id', $userId)
            ->where('device_type', $deviceType)
            ->update(['is_active' => false]);
    }

    /**
     * Format device response
     */
    protected function formatDevice(UserDevice $device): array
    {
        return [
            'id' => $device->id,
            'device_type' => $device->device_type,
            'device_name' => $device->device_name,
            'device_model' => $device->device_model,
            'os_version' => $device->os_version,
            'is_active' => $device->is_active,
            'notification_enabled' => $device->notification_enabled,
            'notification_settings' => $device->notification_settings,
            'last_used_at' => $device->last_used_at?->toIso8601String(),
            'created_at' => $device->created_at->toIso8601String(),
        ];
    }
}
