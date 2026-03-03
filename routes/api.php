<?php

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\Admin\AdminAchievementController;
use App\Http\Controllers\Api\Admin\AdminAwardController;
use App\Http\Controllers\Api\Admin\AdminConfigController;
use App\Http\Controllers\Api\Admin\AdminContentController;
use App\Http\Controllers\Api\Admin\AdminLogoController;
use App\Http\Controllers\Api\Admin\AdminGroupController;
use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\AdminPluginController;
use App\Http\Controllers\Api\Admin\AdminReportController;
use App\Http\Controllers\Api\Admin\AdminForumController;
use App\Http\Controllers\Api\Admin\AdminForumPermissionController;
use App\Http\Controllers\Api\Admin\AdminModerationController;
use App\Http\Controllers\Api\Admin\AdminStoreController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CreditsController;
use App\Http\Controllers\Api\ForumConfigController;
use App\Http\Controllers\Api\ForumController;
use App\Http\Controllers\Api\GameController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PublicConfigController;
use App\Http\Controllers\Api\PostbitBgController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\ThreadSubscriptionController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::get('/forum/config', [ForumConfigController::class, 'index']);
Route::get('/games', [GameController::class, 'index']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/forums', [ForumController::class, 'index']);
Route::get('/forums/{slug}/threads', [ThreadController::class, 'index']);
Route::get('/threads/{id}', [ThreadController::class, 'show']);
Route::get('/threads/{id}/posts', [PostController::class, 'index']);
Route::get('/store/items', [StoreController::class, 'index']);
Route::get('/achievements', [AchievementController::class, 'index']);
Route::get('/users/online', [UserController::class, 'online']);
Route::get('/members', [UserController::class, 'members']);
Route::get('/staff', [UserController::class, 'staff']);
Route::get('/users/{username}/profile', [UserController::class, 'profile']);
Route::get('/search', [SearchController::class, 'search']);
Route::get('/credits/earning-info', [CreditsController::class, 'earningInfo']);
Route::get('/public/custom-code', [PublicConfigController::class, 'customCode']);

// Auth routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Email verification (signed URL — no auth needed)
Route::post('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

// Stripe webhook (no auth — verified by signature)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/email/resend', [AuthController::class, 'resendVerification']);

    // Current user
    Route::get('/user', [UserController::class, 'me']);
    Route::put('/user/profile', [UserController::class, 'updateProfile']);
    Route::put('/user/account', [UserController::class, 'updateAccount']);
    Route::get('/user/credits', [UserController::class, 'credits']);
    Route::get('/user/achievements', [UserController::class, 'achievements']);
    Route::get('/user/awards', [UserController::class, 'awards']);
    Route::get('/user/notifications', [UserController::class, 'notifications']);
    Route::get('/user/cosmetics', [UserController::class, 'cosmetics']);
    Route::put('/user/cosmetics/{id}/toggle', [UserController::class, 'toggleCosmetic']);
    Route::put('/user/settings/notifications', [UserController::class, 'updateNotificationSettings']);
    Route::put('/user/settings/privacy', [UserController::class, 'updatePrivacySettings']);
    Route::get('/user/sessions', [UserController::class, 'sessions']);
    Route::delete('/user/sessions/{id}', [UserController::class, 'destroySession']);
    Route::post('/user/avatar', [AvatarController::class, 'store']);
    Route::post('/user/postbit-bg', [PostbitBgController::class, 'upload']);
    Route::delete('/user/postbit-bg', [PostbitBgController::class, 'remove']);

    // Forum actions
    Route::post('/threads', [ThreadController::class, 'store']);
    Route::post('/threads/{id}/posts', [PostController::class, 'store']);
    Route::post('/posts/{id}/react', [PostController::class, 'react']);
    Route::post('/posts/{id}/like', [PostController::class, 'likePost']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
    Route::put('/threads/{id}', [ThreadController::class, 'update']);
    Route::post('/threads/{id}/like', [ThreadController::class, 'like']);
    Route::post('/threads/{id}/subscribe', [ThreadSubscriptionController::class, 'toggle']);
    Route::get('/threads/{id}/subscription', [ThreadSubscriptionController::class, 'show']);

    // Store
    Route::post('/store/purchase', [StoreController::class, 'purchaseWithCredits']);
    Route::post('/store/checkout', [StoreController::class, 'createCheckout']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Reports
    Route::post('/reports', [ReportController::class, 'store']);

    // Conversations / DMs
    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);
    Route::get('/messages/unread-count', [ConversationController::class, 'unreadCount']);
});

// Admin routes
Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    // Dashboard
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // Users
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/banned', [AdminUserController::class, 'banned']);
    Route::get('/users/{id}', [AdminUserController::class, 'show']);
    Route::put('/users/{id}', [AdminUserController::class, 'update']);
    Route::post('/users/{id}/ban', [AdminUserController::class, 'ban']);
    Route::delete('/users/{id}/ban', [AdminUserController::class, 'unban']);
    Route::post('/users/{id}/credits', [AdminUserController::class, 'adjustCredits']);
    Route::post('/users/{id}/awards', [AdminUserController::class, 'grantAward']);
    Route::delete('/users/{id}/awards/{awardId}', [AdminUserController::class, 'revokeAward']);

    // Forums (list, tree + CRUD for games, categories, forums)
    Route::get('/forums', [AdminForumController::class, 'index']);
    Route::get('/forums/tree', [AdminForumController::class, 'tree']);
    Route::post('/games', [AdminForumController::class, 'createGame']);
    Route::put('/games/{id}', [AdminForumController::class, 'updateGame']);
    Route::delete('/games/{id}', [AdminForumController::class, 'deleteGame']);
    Route::post('/categories', [AdminForumController::class, 'createCategory']);
    Route::put('/categories/{id}', [AdminForumController::class, 'updateCategory']);
    Route::delete('/categories/{id}', [AdminForumController::class, 'deleteCategory']);
    Route::post('/forums', [AdminForumController::class, 'createForum']);
    Route::put('/forums/{id}', [AdminForumController::class, 'updateForum']);
    Route::delete('/forums/{id}', [AdminForumController::class, 'deleteForum']);
    Route::post('/games/reorder', [AdminForumController::class, 'reorderGames']);
    Route::post('/categories/reorder', [AdminForumController::class, 'reorderCategories']);
    Route::post('/forums/reorder', [AdminForumController::class, 'reorderForums']);

    // Moderation
    Route::get('/moderation/reports', [AdminModerationController::class, 'reports']);
    Route::get('/threads', [AdminModerationController::class, 'threads']);
    Route::put('/threads/{id}/pin', [AdminModerationController::class, 'pinThread']);
    Route::put('/threads/{id}/lock', [AdminModerationController::class, 'lockThread']);
    Route::put('/threads/{id}/solve', [AdminModerationController::class, 'solveThread']);
    Route::delete('/posts/{id}', [AdminModerationController::class, 'deletePost']);
    Route::delete('/threads/{id}', [AdminModerationController::class, 'deleteThread']);
    Route::put('/threads/{id}/move', [AdminModerationController::class, 'moveThread']);

    // Store
    Route::get('/store/items', [AdminStoreController::class, 'index']);
    Route::post('/store/items', [AdminStoreController::class, 'store']);
    Route::put('/store/items/{id}', [AdminStoreController::class, 'update']);
    Route::delete('/store/items/{id}', [AdminStoreController::class, 'destroy']);
    Route::get('/store/purchases', [AdminStoreController::class, 'purchases']);
    Route::post('/store/purchases/{id}/deliver', [AdminStoreController::class, 'deliver']);

    // Achievements
    Route::get('/achievements', [AdminAchievementController::class, 'index']);
    Route::post('/achievements', [AdminAchievementController::class, 'store']);
    Route::put('/achievements/{id}', [AdminAchievementController::class, 'update']);
    Route::delete('/achievements/{id}', [AdminAchievementController::class, 'destroy']);

    // Awards
    Route::get('/awards', [AdminAwardController::class, 'index']);
    Route::post('/awards', [AdminAwardController::class, 'store']);
    Route::put('/awards/{id}', [AdminAwardController::class, 'update']);
    Route::delete('/awards/{id}', [AdminAwardController::class, 'destroy']);

    // Groups
    Route::get('/groups', [AdminGroupController::class, 'index']);
    Route::post('/groups', [AdminGroupController::class, 'store']);
    Route::put('/groups/{id}', [AdminGroupController::class, 'update']);
    Route::delete('/groups/{id}', [AdminGroupController::class, 'destroy']);

    // Config
    Route::get('/config', [AdminConfigController::class, 'index']);
    Route::put('/config', [AdminConfigController::class, 'update']);
    Route::post('/config/test-email', [AdminConfigController::class, 'testEmail']);

    // Logo
    Route::post('/logo', [AdminLogoController::class, 'upload']);
    Route::delete('/logo', [AdminLogoController::class, 'remove']);
    Route::get('/forums/{forum}/permissions', [AdminForumPermissionController::class, 'index']);
    Route::put('/forums/{forum}/permissions', [AdminForumPermissionController::class, 'update']);

    // Reports
    Route::get('/reports', [AdminReportController::class, 'index']);
    Route::put('/reports/{id}', [AdminReportController::class, 'update']);

    // Content management
    Route::get('/content/threads', [AdminContentController::class, 'threads']);
    Route::get('/content/posts', [AdminContentController::class, 'posts']);

    // Plugins
    Route::get('/plugins', [AdminPluginController::class, 'index']);
    Route::post('/plugins/install', [AdminPluginController::class, 'install']);
    Route::post('/plugins/{slug}/toggle', [AdminPluginController::class, 'toggle']);
    Route::delete('/plugins/{slug}', [AdminPluginController::class, 'uninstall']);
});
