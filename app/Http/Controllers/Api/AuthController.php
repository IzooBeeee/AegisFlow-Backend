<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AuthController extends Controller
{
    /**
     * Đăng nhập
     * POST /api/auth/login
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('Email hoặc mật khẩu không chính xác', 401);
        }

        if (! $user->is_active) {
            return ApiResponse::error('Tài khoản đã bị vô hiệu hóa', 403);
        }

        // Cập nhật last_login
        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->saveQuietly();

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Đăng nhập thành công');
    }

    /**
     * Đăng ký tài khoản mới
     * POST /api/auth/register
     */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => 'nullable|string|max:20',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        // Gán vai trò mặc định: citizen
        $citizenRole = Role::where('name', 'citizen')->first();
        if ($citizenRole) {
            $user->assignRole($citizenRole);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::created([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Đăng ký thành công');
    }

    /**
     * Lấy thông tin user hiện tại
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        return ApiResponse::success(new UserResource($request->user()));
    }

    /**
     * Cập nhật profile
     * PUT /api/auth/profile
     */
    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
        ]);

        $user = $request->user();
        $user->update($data);

        return ApiResponse::success(new UserResource($user->fresh()), 'Cập nhật profile thành công');
    }

    /**
     * Đổi mật khẩu
     * POST /api/auth/change-password
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::error('Mật khẩu hiện tại không chính xác', 400);
        }

        $user->password = Hash::make($data['password']);
        $user->save();

        return ApiResponse::success(null, 'Đổi mật khẩu thành công');
    }

    /**
     * Upload avatar
     * POST /api/auth/avatar
     */
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $user = $request->user();
        $path = $request->file('avatar')->store('uploads/avatars', 'public');
        $url = asset('storage/'.$path);

        $user->update(['avatar' => $url]);

        return ApiResponse::success(['avatar_url' => $url], 'Cập nhật ảnh đại diện thành công');
    }

    /**
     * Xóa tài khoản
     * DELETE /api/auth/account
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, 'Tài khoản đã được xóa');
    }

    /**
     * Đăng xuất
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Đăng xuất thành công');
    }

    /**
     * Refresh token
     * POST /api/auth/refresh
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return ApiResponse::success([
            'token' => $token,
        ]);
    }

    /**
     * Gửi OTP quên mật khẩu
     * POST /api/auth/forgot-password
     */
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return ApiResponse::error('Email không tồn tại trong hệ thống', 404);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $data['email']],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        // Gửi email OTP
        try {
            Mail::raw("Mã OTP đặt lại mật khẩu AegisFlow: {$otp}\nMã có hiệu lực trong 10 phút.", function ($message) use ($data) {
                $message->to($data['email'])->subject('AegisFlow - Mã xác thực OTP');
            });
        } catch (\Exception $e) {
            // Log nhưng vẫn trả success (dev mode có thể chưa config mail)
            \Log::warning('Mail send failed: '.$e->getMessage());
        }

        return ApiResponse::success(null, 'Mã OTP đã được gửi đến email của bạn');
    }

    /**
     * Xác thực OTP quên mật khẩu
     * POST /api/auth/accept-otp-password
     */
    public function verifyOtp(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['username'])
            ->first();

        if (! $record) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy yêu cầu đặt lại mật khẩu'], 400);
        }

        // Kiểm tra hết hạn (10 phút)
        if (now()->diffInMinutes($record->created_at) > 10) {
            DB::table('password_reset_tokens')->where('email', $data['username'])->delete();

            return response()->json(['status' => false, 'message' => 'Mã OTP đã hết hạn'], 400);
        }

        if (! Hash::check($data['otp'], $record->token)) {
            return response()->json(['status' => false, 'message' => 'Mã OTP không chính xác'], 400);
        }

        // Tạo reset token
        $resetToken = bin2hex(random_bytes(32));
        DB::table('password_reset_tokens')->where('email', $data['username'])->update([
            'token' => Hash::make($resetToken),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Xác thực OTP thành công',
            'data' => ['token' => $resetToken],
        ]);
    }

    /**
     * Đặt lại mật khẩu sau OTP
     * POST /api/auth/reset-password
     */
    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record || ! Hash::check($data['token'], $record->token)) {
            return ApiResponse::error('Token không hợp lệ hoặc đã hết hạn', 400);
        }

        $user = User::where('email', $data['email'])->first();
        $user->password = Hash::make($data['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return ApiResponse::success(null, 'Đặt lại mật khẩu thành công');
    }

    /**
     * Xác thực email sau đăng ký
     * POST /api/auth/verify-email
     */
    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->first();

        if (! $record) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy mã xác thực'], 400);
        }

        if (now()->diffInMinutes($record->created_at) > 10) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

            return response()->json(['status' => false, 'message' => 'Mã OTP đã hết hạn'], 400);
        }

        if (! Hash::check($data['otp'], $record->token)) {
            return response()->json(['status' => false, 'message' => 'Mã OTP không chính xác'], 400);
        }

        $user = User::where('email', $data['email'])->first();
        if ($user) {
            $user->email_verified_at = now();
            $user->save();
        }

        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return response()->json(['status' => true, 'message' => 'Xác thực email thành công']);
    }

    /**
     * Gửi lại OTP
     * POST /api/auth/resend-otp
     */
    public function resendOtp(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|email',
            'type' => 'sometimes|string',
        ]);

        $user = User::where('email', $data['username'])->first();
        if (! $user) {
            return ApiResponse::error('Email không tồn tại', 404);
        }

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $data['username']],
            ['token' => Hash::make($otp), 'created_at' => now()]
        );

        try {
            Mail::raw("Mã OTP xác thực AegisFlow: {$otp}\nMã có hiệu lực trong 10 phút.", function ($message) use ($data) {
                $message->to($data['username'])->subject('AegisFlow - Mã xác thực OTP');
            });
        } catch (\Exception $e) {
            \Log::warning('Mail send failed: '.$e->getMessage());
        }

        return ApiResponse::success(null, 'Mã OTP đã được gửi lại');
    }
}
