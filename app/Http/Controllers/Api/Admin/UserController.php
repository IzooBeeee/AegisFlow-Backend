<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Danh sách người dùng
     * GET /api/admin/users
     */
    public function index(Request $request)
    {
        $query = User::with('roles')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%"));
        }

        if ($request->filled('role')) {
            $query->role($request->role);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $users = $query->paginate($request->get('per_page', 20));

        $data = $users->map(fn ($u) => $this->formatUser($u));

        return ApiResponse::paginate($users->setCollection($data));
    }

    /**
     * Tạo user mới
     * POST /api/admin/users
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', Password::min(8)],
            'phone' => 'nullable|string|max:20',
            'role' => 'required|string',
        ]);

        $role = $this->findRole($data['role']);
        if (! $role) {
            return ApiResponse::validationError(['role' => ['Vai trò không hợp lệ']]);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
        ]);

        $user->assignRole($role);

        return ApiResponse::created($this->formatUser($user->fresh()));
    }

    /**
     * Chi tiết người dùng
     * GET /api/admin/users/{id}
     */
    public function show(int $id)
    {
        $user = User::with('roles')->find($id);

        if (! $user) {
            return ApiResponse::notFound('Không tìm thấy người dùng');
        }

        return ApiResponse::success($this->formatUser($user));
    }

    /**
     * Cập nhật user
     * PUT /api/admin/users/{id}
     */
    public function update(Request $request, int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return ApiResponse::notFound('Không tìm thấy người dùng');
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|in:active,inactive',
            'role' => 'sometimes|string',
        ]);

        $roleValue = $data['role'] ?? null;
        $role = null;

        if ($roleValue !== null) {
            $role = $this->findRole($roleValue);
            if (! $role) {
                return ApiResponse::validationError(['role' => ['Vai trò không hợp lệ']]);
            }
        }

        if (isset($data['status'])) {
            $data['is_active'] = $data['status'] === 'active';
            unset($data['status']);
        }

        unset($data['role']);

        $user->update($data);

        if ($role) {
            $user->syncRoles([$role]);
        }

        return ApiResponse::success($this->formatUser($user->fresh()), 'Cập nhật thành công');
    }

    /**
     * Xóa user
     * DELETE /api/admin/users/{id}
     */
    public function destroy(int $id)
    {
        $user = User::find($id);

        if (! $user) {
            return ApiResponse::notFound('Không tìm thấy người dùng');
        }

        // Soft delete thay vì xóa vĩnh viễn
        $user->is_active = false;
        $user->save();
        $user->delete();

        return ApiResponse::deleted('Người dùng đã được vô hiệu hóa');
    }

    /**
     * Format user response
     */
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'is_active' => $user->is_active,
            'status' => $user->is_active ? 'active' : 'inactive',
            'role' => $user->roles->first()?->name,
            'roles' => $user->roles->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
            ]),
            'created_at' => $user->created_at?->toIso8601String(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'last_login' => $user->last_login_at?->toIso8601String(),
        ];
    }

    protected function findRole(string|int $value): ?Role
    {
        return Role::query()
            ->where('id', $value)
            ->orWhere('name', $value)
            ->first();
    }
}
