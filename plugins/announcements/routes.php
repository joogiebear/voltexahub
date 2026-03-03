<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\Admin\AdminAnnouncementController;
use Illuminate\Support\Facades\Route;

Route::get('/announcements', [AnnouncementController::class, 'index']);

Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::get('/admin/announcements', [AdminAnnouncementController::class, 'index']);
    Route::post('/admin/announcements', [AdminAnnouncementController::class, 'store']);
    Route::put('/admin/announcements/{id}', [AdminAnnouncementController::class, 'update']);
    Route::delete('/admin/announcements/{id}', [AdminAnnouncementController::class, 'destroy']);
});
