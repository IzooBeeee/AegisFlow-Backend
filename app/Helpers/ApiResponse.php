<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * ApiResponse — Tiêu chuẩn hóa response JSON cho toàn bộ API
 *
 * @method static success(mixed $data, string $message = 'Thành công', int $status = 200)
 * @method static error(string $message, int $status = 400, array $errors = [])
 * @method static paginate($paginator, string $message = 'Thành công')
 * @method static notFound(string $message = 'Không tìm thấy')
 * @method static unauthorized(string $message = 'Chưa xác thực')
 * @method static forbidden(string $message = 'Không có quyền')
 * @method static validationError(array $errors, string $message = 'Dữ liệu không hợp lệ')
 * @method static created(mixed $data = null, string $message = 'Tạo thành công')
 * @method static deleted(string $message = 'Xóa thành công')
 * @method static serverError(string $message = 'Lỗi server')
 */
class ApiResponse
{
    /**
     * Response thành công chuẩn
     */
    public static function success(mixed $data = null, string $message = 'Thành công', int $status = 200): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => __($message),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    /**
     * Response lỗi chuẩn
     */
    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => __($message),
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    /**
     * Response phân trang
     */
    public static function paginate($paginator, string $message = 'Thành công'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => __($message),
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Response 404 — Not Found
     */
    public static function notFound(string $message = 'Không tìm thấy tài nguyên'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * Response 401 — Unauthorized
     */
    public static function unauthorized(string $message = 'Vui lòng đăng nhập để tiếp tục'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * Response 403 — Forbidden
     */
    public static function forbidden(string $message = 'Bạn không có quyền thực hiện thao tác này'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * Response 422 — Validation Error
     */
    public static function validationError(array $errors, string $message = 'Dữ liệu không hợp lệ'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * Response 201 — Created
     */
    public static function created(mixed $data = null, string $message = 'Tạo thành công'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    /**
     * Response 200 — Deleted
     */
    public static function deleted(string $message = 'Xóa thành công'): JsonResponse
    {
        return self::success(null, $message, 200);
    }

    /**
     * Response 500 — Server Error
     */
    public static function serverError(string $message = 'Đã xảy ra lỗi phía server'): JsonResponse
    {
        return self::error($message, 500);
    }
}
