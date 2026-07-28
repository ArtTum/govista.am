<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:8192',
                'dimensions:max_width=8000,max_height=8000',
            ],
        ]);

        $path = $request->file('file')->store('uploads/'.now()->format('Y/m'), 'public');

        return response()->json([
            'path' => $path,
            'url' => asset('storage/'.$path),
        ], 201);
    }
}
