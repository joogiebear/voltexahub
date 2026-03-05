<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Services\PerkService;
use Illuminate\Http\JsonResponse;

class AdvertisementController extends Controller
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        if ($user && app(PerkService::class)->userHasPerk($user, PerkService::NO_ADS)) {
            return response()->json(['data' => []]);
        }

        $ads = Advertisement::where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return response()->json(['data' => $ads]);
    }
}
