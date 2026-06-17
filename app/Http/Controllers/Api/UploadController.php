<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'files' => 'required|array|max:5',
            'files.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $urls = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('uploads/incidents', 'public');
            $urls[] = asset('storage/'.$path);
        }

        return ApiResponse::success($urls, 'Files uploaded successfully');
    }
}
