<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TextFormatterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentController extends Controller
{
    public function preview(Request $request, TextFormatterService $svc): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:10000'],
        ]);

        return response()->json([
            'data' => ['html' => $svc->renderFromText($request->content)],
        ]);
    }
}
